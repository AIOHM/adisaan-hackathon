# Deploying the app as adisaan.com on a fresh VPS

> Current production (2026-10-30): this app serves
> tbwim.adisaan.com from `/var/www/tbwim.adisaan.com` (a git checkout
> of this repo). adisaan.com itself is rendered by the private AIOHM
> platform, so the DNS move below is historical. Rollback is a
> redeploy of a previous ref, e.g.
> `sudo bash /var/www/tbwim.adisaan.com/ops/deploy.sh 408c607`.
> Last known-good: `b0d6373` (live), `408c607`.

adisaan.com is behind Cloudflare, so the move is one DNS change and the
rollback is the same change in reverse. The current site keeps running on the
AIOHM platform until the record is switched; nothing is deleted there.

## 1. The server (Ubuntu 24.04, one hour)

```bash
apt update && apt install -y nginx mariadb-server php8.4-fpm php8.4-cli php8.4-mysql php8.4-sqlite3 \
  php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath git unzip composer
mysql -e "CREATE DATABASE tbwim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; \
  CREATE USER 'tbwim'@'localhost' IDENTIFIED BY '<strong password>'; \
  GRANT ALL ON tbwim.* TO 'tbwim'@'localhost';"
git clone https://github.com/AIOHM/adisaan-hackathon.git /var/www/tbwim.adisaan.com
cd /var/www/tbwim.adisaan.com && composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
# edit .env: APP_ENV=production, APP_DEBUG=false, APP_URL=https://adisaan.com,
#   DB_CONNECTION=mysql + credentials, META_API_KEY, META_MONTHLY_LIMIT_USD
php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
```

The server copy is a git checkout of https://github.com/AIOHM/adisaan-hackathon.
After the first provisioning, every later deploy is, on the server:

```bash
sudo bash /var/www/tbwim.adisaan.com/ops/deploy.sh          # origin/main
sudo bash /var/www/tbwim.adisaan.com/ops/deploy.sh v1.2.0   # or any ref
```

It fetches, checks out the ref, runs composer install, migrations and caches,
and restarts the queue. `.env`, `storage/` and `vendor/` are outside git.

Or, from the checkout on your Mac, `bash ops/provision-vps.sh <ssh host> adisaan.com`
(when the app serves a different hostname than its folder, pass
`APP_DIR=<folder>` and that hostname)
does all of the above idempotently (it uses `mariadb-server`, never
`mysql-server`, which would replace an existing MariaDB). Ports 80 and 443
must be free on the host first: stop any Caddy or other reverse proxy that
holds them.

nginx: a standard Laravel server block for `adisaan.com` with root
`/var/www/tbwim.adisaan.com/public`, `try_files $uri /index.php?$query_string`, and
`fastcgi_pass unix:/run/php/php8.4-fpm.sock`. Set `real_ip_header
CF-Connecting-IP` with Cloudflare's ranges so rate limits see visitors, not
the edge.

TLS: in Cloudflare, SSL/TLS → Origin Server → Create Certificate (RSA, hosts
`adisaan.com` and `*.adisaan.com`, 15 years). Save the certificate as
`/etc/ssl/cloudflare/adisaan.com.pem` and the private key as
`/etc/ssl/cloudflare/adisaan.com.key` (mode 0600) on the server, then re-run
`ops/provision-vps.sh`: it adds the 443 server block when both files exist.
Set the zone's SSL/TLS mode to "Full (strict)". Until the certificate is in
place the site answers on port 80 only, which works with mode "Flexible" but
not "Full".

Queue and schedule (systemd):

```
[Unit] Description=tbwim queue
[Service] User=www-data ExecStart=/usr/bin/php /var/www/tbwim.adisaan.com/artisan queue:work --sleep=3 --tries=2 --max-time=3600 Restart=always
[Install] WantedBy=multi-user.target
```

cron for `www-data`: `* * * * * php /var/www/tbwim.adisaan.com/artisan schedule:run`
(hourly `tbwim:synthesize` and `tbwim:review-answers` are in
`routes/console.php`).

## 2. Bring the archive across

```bash
php artisan tbwim:import-live --from=https://adisaan.com   # published agent answers, by receipt
```

Human answers are not public data and are not on the live API. Export them
from the platform tenant with a documented query, review the JSON, then
`php artisan tbwim:import-live --file=export.json`.

## 3. Switch and verify

1. In Cloudflare, change the `A` record for `adisaan.com` (and `www`) to the
   new server's address. TTL is Cloudflare's; the switch is live in minutes.
2. `curl -s https://adisaan.com/api/agent/answer | jq .question_id`
   and `curl -sI https://adisaan.com/.well-known/agent.json` must answer
   from the new box (check the `Server`/timing headers or a temporary header).
3. Post one agent answer with the published contract and watch its receipt
   move from `pending_review` to `published` or `held` after the queue runs.

Rollback: point the record back. The platform copy was never touched.

## 4. What stays on the platform

`admin.adisaan.com` (the private AIOHM workspace, Social publishing,
workflows) is unaffected. Only the public site moves. If both must read the
same answers for a while, run the app as the source of truth and pull
from `/api/dataset.jsonl` into the platform; never the other way round twice.
