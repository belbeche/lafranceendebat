#!/usr/bin/env bash
# Lance le site en local, sans dependre du binaire "symfony" (qui se fige souvent dans le terminal Git Bash).
# Usage : bash scripts/serve.sh [port]    puis ouvrir http://127.0.0.1:8000
cd "$(dirname "$0")/.." || exit 1
PORT="${1:-8000}"
export APP_ENV="${APP_ENV:-dev}"
echo "La France en Debat : http://127.0.0.1:${PORT}  (Ctrl+C pour arreter)"
exec php -d variables_order=EGPCS -S "127.0.0.1:${PORT}" -t public public/index.php
