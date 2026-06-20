#!/usr/bin/env bash
# Mac에서 실행: 로컬 .env를 Contabo용 production .env로 복사 (비밀값은 로컬 .env 그대로).
#
# Usage (repo root):
#   ./deployment/prepare-production-env.sh
#   scp .env.production root@5.104.82.58:/var/www/mocchi-platform/.env

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC="${ROOT}/.env"
OUT="${ROOT}/.env.production"

if [[ ! -f "$SRC" ]]; then
    echo "Missing $SRC"
    exit 1
fi

cp "$SRC" "$OUT"

# production overrides (macOS sed -i '')
if sed --version >/dev/null 2>&1; then
    SED=(sed -i)
else
    SED=(sed -i '')
fi

"${SED[@]}" 's/^APP_ENV=.*/APP_ENV=production/' "$OUT"
"${SED[@]}" 's/^APP_DEBUG=.*/APP_DEBUG=false/' "$OUT"
"${SED[@]}" 's|^APP_URL=.*|APP_URL=https://crm.grapeseed.co.kr|' "$OUT"
"${SED[@]}" 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=database/' "$OUT"
"${SED[@]}" 's/^SESSION_DRIVER=.*/SESSION_DRIVER=file/' "$OUT"
"${SED[@]}" 's/^CACHE_STORE=.*/CACHE_STORE=file/' "$OUT"
"${SED[@]}" 's/^LOG_LEVEL=.*/LOG_LEVEL=warning/' "$OUT"

echo "Wrote $OUT"
echo "Upload: scp .env.production root@5.104.82.58:/var/www/mocchi-platform/.env"
