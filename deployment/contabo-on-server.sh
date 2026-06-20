#!/usr/bin/env bash
# Contabo 서버 SSH 안에서 실행 (코드는 GitHub에서 clone, .env는 미리 업로드 필요).
#
# 1) Mac에서 .env 업로드:
#    scp "/Users/boseokhur/Desktop/Mocchi 화면 Figma/mocchi-platform/.env.production" \
#        root@5.104.82.58:/var/www/mocchi-platform/.env
# 2) Contabo SSH:
#    curl -fsSL https://raw.githubusercontent.com/Coding0508s/Mochi/main/deployment/contabo-on-server.sh | bash
#    또는 clone 후: cd /var/www/mocchi-platform && ./deployment/contabo-on-server.sh

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/mocchi-platform}"
REPO_HTTPS="${REPO_HTTPS:-https://github.com/Coding0508s/Mochi.git}"

echo "==> App dir: $APP_DIR"

if ! command -v git >/dev/null 2>&1; then
    apt update
    apt install -y git
fi

mkdir -p "$APP_DIR"

if [[ ! -d "$APP_DIR/.git" ]]; then
    echo "==> Cloning from GitHub..."
    git clone "$REPO_HTTPS" "$APP_DIR"
else
    echo "==> git pull"
    git -C "$APP_DIR" pull --ff-only
fi

cd "$APP_DIR"

if [[ ! -f .env ]]; then
    echo ""
    echo "ERROR: .env 가 없습니다."
    echo "Mac에서 먼저 실행:"
    echo "  cd \"/Users/boseokhur/Desktop/Mocchi 화면 Figma/mocchi-platform\""
    echo "  ./deployment/prepare-production-env.sh"
    echo "  scp .env.production root@5.104.82.58:${APP_DIR}/.env"
    exit 1
fi

chmod +x deployment/*.sh

echo "==> Laravel deploy"
RESTART_WORKER=1 ./deployment/deploy.sh

echo "==> Nginx / Supervisor / Cron"
./deployment/contabo-configure-web.sh

echo ""
echo "Done. http://crm.grapeseed.co.kr"
echo "SSL: sudo SSL=1 LETSENCRYPT_EMAIL=you@grapeseed.co.kr ./deployment/contabo-configure-web.sh"
echo "DB: iwinv에 이 서버 IP 허용 필요 → curl -s ifconfig.me"
