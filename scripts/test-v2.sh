#!/usr/bin/env bash
# Isolated core checks only; never connects to production database.
set -euo pipefail
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
RUNTIME=${V2_TEST_RUNTIME:-/home/ubuntu/.hermes/cache/scratch/kasir-v2-runtime}
test -f "$RUNTIME/vendor/autoload.php" || { printf 'Missing disposable vendor tree: %s\n' "$RUNTIME/vendor" >&2; exit 2; }
mkdir -p "$RUNTIME/storage/framework/views" "$RUNTIME/storage/framework/sessions" "$RUNTIME/storage/framework/cache" "$RUNTIME/storage/logs" "$RUNTIME/bootstrap-cache"
export APP_KEY
APP_KEY=$(python3 -c 'import secrets,base64;print("base64:"+base64.b64encode(secrets.token_bytes(32)).decode())')
docker run --rm --network none --entrypoint sh \
  -e APP_KEY -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: \
  -e DB_URL= -e SESSION_DRIVER=array -e LOG_CHANNEL=stderr \
  -v "$ROOT:/app:ro" -v "$RUNTIME/vendor:/app/vendor:ro" \
  -v "$RUNTIME/storage:/app/storage" -v "$RUNTIME/bootstrap-cache:/app/bootstrap/cache" \
  -w /app composer:2 -lc \
  'php vendor/bin/phpunit --do-not-cache-result --fail-on-warning --display-warnings tests/Feature/V2*Test.php'
