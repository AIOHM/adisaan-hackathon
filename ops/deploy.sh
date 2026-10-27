#!/usr/bin/env bash
# Deploy the app on the server from GitHub. Run ON the server:
#
#   sudo bash /var/www/tbwim.adisaan.com/ops/deploy.sh [ref]
#
# Pulls the given ref (default: origin/main) into the checkout, installs
# production dependencies, migrates, rebuilds caches, fixes permissions and
# restarts the queue worker. .env, storage and vendor are never touched by git.
set -euo pipefail

# The whole body lives in a function so bash parses the file completely before
# running it: git replaces this very file mid-deploy, and a script read line by
# line would otherwise continue from the new file's bytes.
main() {
APP="${APP:-/var/www/tbwim.adisaan.com}"
REF="${1:-origin/main}"
DOMAIN="${DOMAIN:-tbwim.adisaan.com}"
cd "$APP"
git config --global --add safe.directory "$APP" >/dev/null 2>&1 || true
echo "== fetch"; git fetch --prune origin
before=$(git rev-parse --short HEAD 2>/dev/null || echo none)
git checkout -q -B main 2>/dev/null || true
git reset -q --hard "$REF"
git branch -q --set-upstream-to=origin/main main 2>/dev/null || true
after=$(git rev-parse --short HEAD)
echo "   $before -> $after ($(git log -1 --format='%s' HEAD))"
echo "== dependencies"; composer install --no-dev --optimize-autoloader --no-interaction --quiet
echo "== migrate"; php artisan migrate --force --no-interaction | tail -1
echo "== caches"; php artisan config:cache -q && php artisan route:cache -q && php artisan view:cache -q
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
echo "== queue"; systemctl restart tbwim-queue && systemctl is-active tbwim-queue
echo "== check"
if [ -s "/etc/letsencrypt/live/adisaan.com/fullchain.pem" ] || [ -s "/etc/ssl/cloudflare/${DOMAIN}.pem" ]; then
  curl -sk -o /dev/null -w "   local https: %{http_code}\n" --resolve "${DOMAIN}:443:127.0.0.1" "https://${DOMAIN}/api/agent/answer"
else
  curl -s -o /dev/null -w "   local http: %{http_code}\n" -H "Host: ${DOMAIN}" http://127.0.0.1/api/agent/answer
fi
echo "deployed $after"
}

main "$@"
