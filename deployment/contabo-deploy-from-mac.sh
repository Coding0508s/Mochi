#!/usr/bin/env bash
# Mac에서 실행: production .env 업로드 + Contabo 서버 배포 (SSH 키 필요).
#
# Usage:
#   ./deployment/prepare-production-env.sh
#   ./deployment/contabo-deploy-from-mac.sh
#
# Env overrides:
#   CONTABO_HOST=5.104.82.58
#   CONTABO_USER=root
#   APP_DIR=/var/www/mocchi-platform

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HOST="${CONTABO_HOST:-5.104.82.58}"
USER="${CONTABO_USER:-root}"
APP_DIR="${APP_DIR:-/var/www/mocchi-platform}"
REPO="${GIT_REPO:-git@github.com:Coding0508s/Mochi.git}"

ENV_FILE="${ROOT}/.env.production"
if [[ ! -f "$ENV_FILE" ]]; then
    echo "Run ./deployment/prepare-production-env.sh first."
    exit 1
fi

echo "==> Upload .env"
scp "$ENV_FILE" "${USER}@${HOST}:${APP_DIR}/.env"

echo "==> Deploy on server"
ssh "${USER}@${HOST}" bash -s <<REMOTE
set -euo pipefail
APP_DIR="${APP_DIR}"
REPO="${REPO}"

if [[ ! -d "\$APP_DIR/.git" ]]; then
  mkdir -p "\$APP_DIR"
  git clone "\$REPO" "\$APP_DIR"
fi

cd "\$APP_DIR"
git pull --ff-only
chmod +x deployment/deploy.sh deployment/contabo-configure-web.sh
RESTART_WORKER=1 ./deployment/deploy.sh
sudo APP_DIR="\$APP_DIR" ./deployment/contabo-configure-web.sh
REMOTE

echo ""
echo "Deploy finished. Visit https://crm.grapeseed.co.kr"
echo "If DB connection fails, allow server IP on iwinv: curl -s ifconfig.me (on server)"
