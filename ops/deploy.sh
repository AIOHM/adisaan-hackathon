#!/usr/bin/env bash
# Deploy the observatory on the server from GitHub. Run ON the server:
#
#   sudo bash /var/www/tbwim.adisaan.com/ops/deploy.sh [ref]
#
# Pulls the given ref (default: origin/main) into the checkout, installs
# production dependencies, migrates, rebuilds caches, fixes permissions and
# restarts the queue worker. .env, storage and vendor are never touched by git.
set -euo pipefail
APP="${APP:-/var/www/tbwim.adisaan.com}"
REF="${1:-origin/main}"
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
echo "== queue"; systemctl restart observatory-queue && systemctl is-active observatory-queue
echo "== check"; curl -s -o /dev/null -w "   local http: %{http_code}\n" -H "Host: adisaan.com" http://127.0.0.1/api/agent/answer
echo "deployed $after"
