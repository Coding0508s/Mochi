#!/usr/bin/env bash
# Contabo 서버 PHP 8.4 설치 (composer.lock 이 Symfony 8 / PHP 8.4+ 필요)
# Usage: sudo ./deployment/contabo-install-php84.sh

set -euo pipefail

PHP_VERSION=8.4

apt update
add-apt-repository -y ppa:ondrej/php
apt update
apt install -y \
    "php${PHP_VERSION}-fpm" \
    "php${PHP_VERSION}-cli" \
    "php${PHP_VERSION}-mysql" \
    "php${PHP_VERSION}-mbstring" \
    "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-curl" \
    "php${PHP_VERSION}-zip" \
    "php${PHP_VERSION}-bcmath" \
    "php${PHP_VERSION}-intl" \
    "php${PHP_VERSION}-gd"

update-alternatives --set php "/usr/bin/php${PHP_VERSION}" 2>/dev/null || true

if [[ -f /etc/nginx/sites-available/mocchi ]]; then
    sed -i 's/php8\.3-fpm/php8.4-fpm/g' /etc/nginx/sites-available/mocchi
    nginx -t
    systemctl reload nginx
fi

systemctl enable "php${PHP_VERSION}-fpm"
systemctl restart "php${PHP_VERSION}-fpm"

php -v
echo "Done. Re-run: cd /var/www/mocchi-platform && ./deployment/deploy.sh"
