#!/usr/bin/env bash
#
# Harborline production deploy script for Plesk.
# Usage: bash deploy/deploy.sh [--first-run] [--skip-migrations]
#
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

FIRST_RUN=false
SKIP_MIGRATIONS=false
for arg in "$@"; do
  case "$arg" in
    --first-run) FIRST_RUN=true ;;
    --skip-migrations) SKIP_MIGRATIONS=true ;;
  esac
done

echo "==> Deploying in $APP_DIR"

[ -f .env ] || { echo "ERROR: .env missing. Copy .env.example and configure it first."; exit 1; }
grep -q '^APP_KEY=base64' .env || { echo "ERROR: APP_KEY not set. Run: php artisan key:generate"; exit 1; }

echo "==> Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Building frontend assets"
npm ci --no-audit --no-fund
npm run build

if [ "$SKIP_MIGRATIONS" = false ]; then
  echo "==> Running database migrations"
  php artisan migrate --force
  if [ "$FIRST_RUN" = true ]; then
    echo "==> Seeding baseline data (roles, stages, templates, automations)"
    php artisan db:seed --force
  fi
else
  echo "==> Skipping migrations (--skip-migrations)"
fi

echo "==> Storage link"
php artisan storage:link || true

echo "==> Clearing and rebuilding caches"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> Restarting queue workers"
php artisan queue:restart

echo "==> Checking file permissions"
for dir in storage bootstrap/cache; do
  if [ ! -w "$dir" ]; then
    echo "    fixing permissions on $dir"
    chmod -R u+rwX,g+rwX "$dir"
  fi
done
mkdir -p storage/app/private-documents
chmod -R u+rwX,g+rwX storage/app/private-documents

echo "==> Health check"
php artisan about --only=environment | head -5
if command -v curl >/dev/null && grep -q '^APP_URL=' .env; then
  APP_URL="$(grep '^APP_URL=' .env | cut -d= -f2)"
  if curl -fsS --max-time 10 "$APP_URL/up" >/dev/null 2>&1; then
    echo "    $APP_URL/up -> OK"
  else
    echo "    WARNING: health endpoint $APP_URL/up not reachable from this shell (may be fine behind proxy)"
  fi
fi

echo "==> Deploy complete"
