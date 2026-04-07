#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

echo "[1/5] Current size overview"
du -h --max-depth=1 | sort -hr | head -n 20

echo "[2/5] Cleaning Laravel caches"
php artisan optimize:clear >/dev/null 2>&1 || true

echo "[3/5] Truncating logs"
if [[ -f storage/logs/laravel.log ]]; then
  : > storage/logs/laravel.log
fi
find storage/logs -type f -name '*.log' -exec sh -c ': > "$1"' _ {} \;

echo "[4/5] Removing local cache artifacts"
rm -rf .phpunit.cache .phpunit.result.cache coverage
find . -type f -name '*.DS_Store' -delete

echo "[5/5] Final size overview"
du -h --max-depth=1 | sort -hr | head -n 20

echo "Done."
