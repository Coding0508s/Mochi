#!/usr/bin/env bash
# Post-clone deploy script for GS Brochure (Laravel) on Contabo/server.
# Run from repo root (parent of laravel/) or set LARAVEL_DIR.
# Prerequisites: .env already created and DB_* set (MySQL), APP_KEY generated if needed.

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LARAVEL_DIR="${LARAVEL_DIR:-$(dirname "$SCRIPT_DIR")}"
cd "$LARAVEL_DIR"

echo "Laravel dir: $LARAVEL_DIR"

if [ ! -f .env ]; then
    echo "Missing .env. Copy .env.example to .env and set DB_*, APP_*, APP_URL."
    exit 1
fi

echo "Running composer install --no-dev --optimize-autoloader..."
composer install --no-dev --optimize-autoloader

echo "Running migrations..."
php artisan migrate --force

echo "Creating storage link if not exists..."
php artisan storage:link || true

echo "Cache clear..."
php artisan config:clear
php artisan cache:clear

echo "Deploy script done. Ensure storage and bootstrap/cache are writable (e.g. chown -R www-data:www-data storage bootstrap/cache)."
