#!/usr/bin/env bash
#
# Harborline production deploy script for Plesk Node.js hosting.
# Usage: bash deploy/deploy.sh [--first-run] [--skip-migrations]
# After it finishes, click "Restart App" in Plesk -> Node.js
# (or: mkdir -p tmp && touch tmp/restart.txt).
#
set -euo pipefail
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

FIRST_RUN=false; SKIP_MIGRATIONS=false
for arg in "$@"; do
  case "$arg" in
    --first-run) FIRST_RUN=true ;;
    --skip-migrations) SKIP_MIGRATIONS=true ;;
  esac
done

echo "==> Deploying in $APP_DIR"

if [ ! -f .env ] && [ -z "${APP_KEY:-}" ]; then
  echo "WARNING: no .env file found — make sure environment variables are set in the Plesk Node.js panel."
fi

echo "==> Installing dependencies (including dev, for the CSS build)"
npm ci --no-audit --no-fund

echo "==> Building stylesheet"
npm run build:css

echo "==> Pruning dev dependencies"
npm prune --omit=dev --no-audit --no-fund

if [ "$SKIP_MIGRATIONS" = false ]; then
  echo "==> Running database migrations"
  node src/cli.js migrate
  if [ "$FIRST_RUN" = true ]; then
    echo "==> Seeding baseline data (roles, stages, templates, automations)"
    node src/cli.js seed
  fi
else
  echo "==> Skipping migrations (--skip-migrations)"
fi

echo "==> Checking writable folders"
mkdir -p storage/app/private-documents storage/logs tmp
chmod -R u+rwX storage tmp

echo "==> Requesting Passenger restart"
touch tmp/restart.txt

echo "==> Health check (after restart the app may need a few seconds)"
if [ -f .env ] && grep -q '^APP_URL=' .env; then
  APP_URL="$(grep '^APP_URL=' .env | cut -d= -f2)"
  sleep 3
  if curl -fsS --max-time 10 "$APP_URL/up" >/dev/null 2>&1; then
    echo "    $APP_URL/up -> OK"
  else
    echo "    NOTE: $APP_URL/up not reachable from this shell; verify in a browser."
  fi
fi

echo "==> Deploy complete"
