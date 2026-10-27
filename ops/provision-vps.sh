#!/usr/bin/env bash
# Put the observatory on a fresh Ubuntu server, from this checkout, without
# touching DNS. Safe to re-run. Run from the repository root on your Mac:
#
#   bash ops/provision-vps.sh <ssh host> [domain]
#
# Example: bash ops/provision-vps.sh blanka-vps-legacy adisaan.com
#
# What it does on the server: installs nginx, PHP 8.4-FPM, MySQL and
# composer if missing; creates database + user (password generated once and
# kept in /root/.observatory-db); rsyncs this checkout to /var/www/<domain>
# (no vendor, no .env, no sqlite); composer install; writes .env on first run
# (META_API_KEY left empty for you to fill); migrates; caches; installs the
# queue service and the scheduler cron; writes an HTTP nginx vhost for
# <domain> and the server IP. TLS: add a Cloudflare origin certificate
# afterwards (see ops/DEPLOY.md). Nothing here changes any other site.
set -euo pipefail
HOST="${1:?usage: provision-vps.sh <ssh host> [domain]}"
DOMAIN="${2:-adisaan.com}"
APP="/var/www/${DOMAIN}"
HERE="$(cd "$(dirname "$0")/.." && pwd)"

echo "== sync source to ${HOST}:${APP}"
ssh -o ConnectTimeout=12 "$HOST" "mkdir -p '$APP'"
rsync -az --delete \
  --exclude .git --exclude vendor --exclude node_modules --exclude .env --exclude 'database/*.sqlite' \
  --exclude 'storage/logs/*' --exclude 'storage/framework/cache/*' --exclude 'storage/framework/sessions/*' --exclude 'storage/framework/views/*' \
  "$HERE/" "$HOST:$APP/"

ssh -o ConnectTimeout=12 "$HOST" "DOMAIN='$DOMAIN' APP='$APP' bash -s" <<'REMOTE'
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
need() { command -v "$1" >/dev/null 2>&1; }

echo "== packages"
if ! need php || ! php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);'; then
  apt-get update -qq
  apt-get install -y -qq software-properties-common >/dev/null
  add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1 || true
  apt-get update -qq
fi
apt-get install -y -qq nginx mysql-server php8.4-fpm php8.4-cli php8.4-mysql php8.4-sqlite3 php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath php8.4-intl git unzip rsync >/dev/null
need composer || { curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer >/dev/null; }
PHPSOCK="$(ls /run/php/php8.*-fpm.sock | head -1)"

echo "== database"
DBFILE=/root/.observatory-db
if [ ! -f "$DBFILE" ]; then
  PASS="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-28)"
  printf 'observatory\n%s\n' "$PASS" > "$DBFILE"; chmod 600 "$DBFILE"
fi
DBPASS="$(sed -n 2p "$DBFILE")"
mysql -e "CREATE DATABASE IF NOT EXISTS observatory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'observatory'@'localhost' IDENTIFIED BY '${DBPASS}'; ALTER USER 'observatory'@'localhost' IDENTIFIED BY '${DBPASS}'; GRANT ALL ON observatory.* TO 'observatory'@'localhost'; FLUSH PRIVILEGES;"

echo "== application"
cd "$APP"
composer install --no-dev --optimize-autoloader --no-interaction --quiet
if [ ! -f .env ]; then
  cp .env.example .env
  sed -i "s#^APP_ENV=.*#APP_ENV=production#; s#^APP_DEBUG=.*#APP_DEBUG=false#; s#^APP_URL=.*#APP_URL=https://${DOMAIN}#" .env
  sed -i "s#^DB_CONNECTION=.*#DB_CONNECTION=mysql#; s#^\# DB_HOST=.*#DB_HOST=127.0.0.1#; s#^\# DB_PORT=.*#DB_PORT=3306#; s#^\# DB_DATABASE=.*#DB_DATABASE=observatory#; s#^\# DB_USERNAME=.*#DB_USERNAME=observatory#; s#^\# DB_PASSWORD=.*#DB_PASSWORD=${DBPASS}#" .env
  php artisan key:generate --force --quiet
  echo "   .env written. META_API_KEY is empty: fill it in ${APP}/.env"
fi
php artisan migrate --force --quiet
php artisan config:cache --quiet && php artisan route:cache --quiet && php artisan view:cache --quiet
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

echo "== queue service + scheduler"
cat > /etc/systemd/system/observatory-queue.service <<UNIT
[Unit]
Description=Observatory queue worker
After=network.target mysql.service
[Service]
User=www-data
WorkingDirectory=${APP}
ExecStart=/usr/bin/php ${APP}/artisan queue:work --sleep=3 --tries=2 --max-time=3600
Restart=always
RestartSec=5
[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload && systemctl enable --now observatory-queue >/dev/null && systemctl restart observatory-queue
( crontab -u www-data -l 2>/dev/null | grep -v "${APP}/artisan schedule:run" || true; echo "* * * * * php ${APP}/artisan schedule:run >> /dev/null 2>&1" ) | crontab -u www-data -

echo "== nginx (HTTP; TLS via Cloudflare origin certificate, see ops/DEPLOY.md)"
cat > /etc/nginx/sites-available/observatory.conf <<NGX
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN} www.${DOMAIN} _;
    root ${APP}/public;
    index index.php;
    client_max_body_size 8m;
    real_ip_header CF-Connecting-IP;
    set_real_ip_from 173.245.48.0/20; set_real_ip_from 103.21.244.0/22; set_real_ip_from 103.22.200.0/22; set_real_ip_from 103.31.4.0/22;
    set_real_ip_from 141.101.64.0/18; set_real_ip_from 108.162.192.0/18; set_real_ip_from 190.93.240.0/20; set_real_ip_from 188.114.96.0/20;
    set_real_ip_from 197.234.240.0/22; set_real_ip_from 198.41.128.0/17; set_real_ip_from 162.158.0.0/15; set_real_ip_from 104.16.0.0/13;
    set_real_ip_from 104.24.0.0/14; set_real_ip_from 172.64.0.0/13; set_real_ip_from 131.0.72.0/22;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHPSOCK};
        fastcgi_param ADISAAN_PUBLIC_SITE 1;
    }
    location ~ /\.(?!well-known) { deny all; }
    add_header X-Observatory-Origin "${DOMAIN}" always;
}
NGX
ln -sf /etc/nginx/sites-available/observatory.conf /etc/nginx/sites-enabled/observatory.conf
if [ -e /etc/nginx/sites-enabled/default ]; then rm -f /etc/nginx/sites-enabled/default; fi
nginx -t && systemctl reload nginx

echo "== checks"
curl -s -o /dev/null -w "local http: %{http_code}\n" -H "Host: ${DOMAIN}" http://127.0.0.1/api/agent/answer
echo "done: http://$(hostname -I | awk '{print $1}')/ and http://${DOMAIN}/ once DNS points here"
REMOTE
