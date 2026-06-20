#!/usr/bin/env bash
# Post-clone / update deploy script for MOCHI platform (Laravel 13) on Contabo VPS.
#
# Usage (from repo root):
#   chmod +x deployment/deploy.sh
#   ./deployment/deploy.sh
#
# Options:
#   SKIP_NPM=1          Skip npm ci && npm run build
#   SKIP_MIGRATE=1      Skip php artisan migrate --force
#   RESTART_WORKER=1    Run supervisorctl restart after deploy (needs sudo)
#   APP_DIR=/path       Override app directory (default: repo root)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${APP_DIR:-$(dirname "$SCRIPT_DIR")}"
cd "$APP_DIR"

echo "App dir: $APP_DIR"

if [[ ! -f .env ]]; then
    echo "Missing .env. Copy .env.example to .env, set DB_*, APP_*, APP_URL, then run php artisan key:generate."
    exit 1
fi

if [[ ! -f artisan ]]; then
    echo "artisan not found. Run this script from the MOCHI platform repo root."
    exit 1
fi

echo "Running composer install --no-dev --optimize-autoloader..."
composer install --no-dev --optimize-autoloader

if [[ "${SKIP_NPM:-0}" != "1" ]]; then
    if command -v npm >/dev/null 2>&1; then
        echo "Running npm ci && npm run build..."
        npm ci
        npm run build
    else
        echo "WARN: npm not found. Set SKIP_NPM=1 or install Node.js 20+ and re-run."
        exit 1
    fi
else
    echo "Skipping npm build (SKIP_NPM=1)."
fi

if [[ "${SKIP_MIGRATE:-0}" != "1" ]]; then
    echo "Running migrations..."
    php artisan migrate --force
else
    echo "Skipping migrations (SKIP_MIGRATE=1)."
fi

echo "Ensuring storage link..."
php artisan storage:link 2>/dev/null || true

echo "Caching config, routes, views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

if [[ "${RESTART_WORKER:-0}" == "1" ]]; then
    if command -v supervisorctl >/dev/null 2>&1; then
        echo "Restarting queue workers..."
        sudo supervisorctl restart 'mocchi-worker:*' || true
    else
        echo "WARN: supervisorctl not found. Restart queue workers manually."
    fi
fi

echo ""
echo "Deploy script done."
echo "Ensure permissions: sudo chown -R www-data:www-data storage bootstrap/cache"
echo "Cron (www-data): * * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1"
