#!/usr/bin/env bash
# One-time server bootstrap for Ubuntu 22.04/24.04 (Contabo VPS).
# Run on a fresh VPS as a sudo-capable user — NOT inside the Laravel app directory.
#
# Usage:
#   chmod +x deployment/server-bootstrap.sh
#   ./deployment/server-bootstrap.sh
#
# After this script:
#   1. Clone the repo to /var/www/mocchi-platform
#   2. Configure .env and run deployment/deploy.sh
#   3. Copy nginx + supervisor examples from deployment/

set -euo pipefail

PHP_VERSION="${PHP_VERSION:-8.3}"

echo "==> Updating packages..."
sudo apt update
sudo apt upgrade -y

echo "==> Installing base packages..."
sudo apt install -y software-properties-common git curl unzip nginx mysql-server supervisor

echo "==> Installing PHP ${PHP_VERSION}..."
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y \
    "php${PHP_VERSION}-fpm" \
    "php${PHP_VERSION}-mysql" \
    "php${PHP_VERSION}-mbstring" \
    "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-curl" \
    "php${PHP_VERSION}-zip" \
    "php${PHP_VERSION}-bcmath" \
    "php${PHP_VERSION}-intl" \
    "php${PHP_VERSION}-gd"

if ! command -v composer >/dev/null 2>&1; then
    echo "==> Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
fi

if ! command -v node >/dev/null 2>&1; then
    echo "==> Installing Node.js 20..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
    sudo apt install -y nodejs
fi

echo "==> Creating app directory..."
sudo mkdir -p /var/www/mocchi-platform
sudo chown -R "$USER":"$USER" /var/www/mocchi-platform

echo ""
echo "Bootstrap complete."
echo ""
echo "Next steps:"
echo "  1. cd /var/www/mocchi-platform && git clone <repo-url> ."
echo "  2. cp .env.example .env && edit .env (MySQL, APP_URL, integrations)"
echo "  3. php artisan key:generate"
echo "  4. ./deployment/deploy.sh"
echo "  5. sudo cp deployment/nginx.conf.example /etc/nginx/sites-available/mocchi"
echo "  6. sudo cp deployment/supervisor-worker.conf.example /etc/supervisor/conf.d/mocchi-worker.conf"
echo "  7. sudo nginx -t && sudo systemctl reload nginx"
echo "  8. sudo supervisorctl reread && sudo supervisorctl update"
echo "  9. sudo crontab -u www-data -e  (see deployment/cron.example)"
echo " 10. sudo certbot --nginx -d yourdomain.com  (optional SSL)"
