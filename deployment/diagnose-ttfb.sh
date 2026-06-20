#!/usr/bin/env bash
# Read-only performance snapshot for Laravel/Nginx/PHP-FPM hosts.
# Usage:
#   chmod +x deployment/diagnose-ttfb.sh
#   ./deployment/diagnose-ttfb.sh

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/mocchi-platform}"
ACCESS_LOG="${ACCESS_LOG:-/var/log/nginx/access.log}"
SLOW_LOG="${SLOW_LOG:-/var/log/php8.4-fpm/slow.log}"
PHP_BIN="${PHP_BIN:-php}"

echo "== System baseline =="
echo "[time] $(date)"
echo "[host] $(hostname)"
echo "[uptime]"
uptime || true
echo "[memory]"
free -h || true
echo "[disk]"
df -h / || true
echo ""

echo "== CPU / io-wait snapshot =="
if command -v vmstat >/dev/null 2>&1; then
    vmstat 1 5 || true
else
    echo "vmstat not available"
fi
echo ""

echo "== PHP-FPM process status =="
systemctl is-active php8.4-fpm 2>/dev/null || true
systemctl status php8.4-fpm --no-pager -n 20 2>/dev/null || true
echo ""

echo "== Nginx process status =="
systemctl is-active nginx 2>/dev/null || true
systemctl status nginx --no-pager -n 20 2>/dev/null || true
echo ""

echo "== OPcache status =="
"${PHP_BIN}" -r '
$s = function_exists("opcache_get_status") ? opcache_get_status(false) : null;
if (!$s || !isset($s["opcache_enabled"]) || !$s["opcache_enabled"]) {
    echo "opcache_enabled=no\n";
    exit(0);
}
$m = $s["memory_usage"] ?? [];
$st = $s["opcache_statistics"] ?? [];
echo "opcache_enabled=yes\n";
echo "used_memory=" . ($m["used_memory"] ?? 0) . "\n";
echo "free_memory=" . ($m["free_memory"] ?? 0) . "\n";
echo "wasted_memory=" . ($m["wasted_memory"] ?? 0) . "\n";
echo "num_cached_scripts=" . ($st["num_cached_scripts"] ?? 0) . "\n";
echo "hits=" . ($st["hits"] ?? 0) . "\n";
echo "misses=" . ($st["misses"] ?? 0) . "\n";
' || true
echo ""

echo "== Laravel runtime config =="
if [[ -d "${APP_DIR}" && -f "${APP_DIR}/artisan" ]]; then
    (
        cd "${APP_DIR}"
        "${PHP_BIN}" artisan about --only=environment,cache,drivers 2>/dev/null || true
    )
else
    echo "App dir not found: ${APP_DIR}"
fi
echo ""

echo "== Nginx request_time summary (last 5000 lines) =="
if [[ -f "${ACCESS_LOG}" ]]; then
    tail -n 5000 "${ACCESS_LOG}" | awk '
        {
            rt = $(NF-1);
            if (rt ~ /^[0-9.]+$/) {
                c += 1;
                v[c] = rt + 0;
            }
        }
        END {
            if (c == 0) {
                print "no request_time parsed";
                exit 0;
            }
            asort(v);
            p50 = v[int(c*0.50) < 1 ? 1 : int(c*0.50)];
            p95 = v[int(c*0.95) < 1 ? 1 : int(c*0.95)];
            p99 = v[int(c*0.99) < 1 ? 1 : int(c*0.99)];
            print "count=" c;
            print "p50=" p50 "s";
            print "p95=" p95 "s";
            print "p99=" p99 "s";
        }
    ' || true
else
    echo "access log not found: ${ACCESS_LOG}"
fi
echo ""

echo "== Recent PHP-FPM / app errors =="
journalctl -u php8.4-fpm --since "30 min ago" --no-pager -n 50 2>/dev/null || true
if [[ -f "${SLOW_LOG}" ]]; then
    echo ""
    echo "[php-fpm slowlog tail]"
    tail -n 80 "${SLOW_LOG}" || true
fi
echo ""

echo "Done."
