#!/usr/bin/env bash
# Nginx + Supervisor + Cron + permissions for Contabo (run on server as root or sudo).
# Prerequisite: /var/www/mocchi-platform with .env and ./deployment/deploy.sh already run.
#
# Usage:
#   sudo ./deployment/contabo-configure-web.sh
#   sudo SSL=1 ./deployment/contabo-configure-web.sh   # also run certbot

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/mocchi-platform}"
DOMAIN="${DOMAIN:-crm.grapeseed.co.kr}"

if [[ ! -d "$APP_DIR/public" ]]; then
    echo "Missing $APP_DIR/public. Clone the repo first."
    exit 1
fi

echo "==> Nginx site: $DOMAIN"
cp "$APP_DIR/deployment/nginx.conf.example" /etc/nginx/sites-available/mocchi
ln -sf /etc/nginx/sites-available/mocchi /etc/nginx/sites-enabled/mocchi
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

echo "==> Supervisor queue worker"
cp "$APP_DIR/deployment/supervisor-worker.conf.example" /etc/supervisor/conf.d/mocchi-worker.conf
supervisorctl reread
supervisorctl update
supervisorctl start 'mocchi-worker:*' || supervisorctl restart 'mocchi-worker:*' || true

echo "==> Cron (Laravel schedule)"
CRON_LINE="* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1"
( crontab -u www-data -l 2>/dev/null | grep -v "artisan schedule:run" || true
  echo "$CRON_LINE"
) | crontab -u www-data -

echo "==> Permissions"
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

if [[ "${SSL:-0}" == "1" ]]; then
    echo "==> Certbot SSL"
    apt install -y certbot python3-certbot-nginx
    certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "${LETSENCRYPT_EMAIL:-admin@grapeseed.co.kr}" || certbot --nginx -d "$DOMAIN"
fi

echo ""
echo "Done. Open: http://${DOMAIN} (or https after SSL=1 certbot)"
echo "Ensure iwinv DB allows this server IP: $(curl -s ifconfig.me 2>/dev/null || echo 'check with curl ifconfig.me')"
