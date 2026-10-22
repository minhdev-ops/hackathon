#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if php -m 2>/dev/null | grep -qx 'pdo_sqlite'; then
    php artisan config:clear >/dev/null
    exec php artisan test "$@"
fi

if php -m 2>/dev/null | grep -qx 'pdo_mysql'; then
    php artisan config:clear >/dev/null
    exec php artisan test "$@"
fi

if ! command -v docker >/dev/null 2>&1; then
    echo "php thiếu pdo_sqlite và không có docker để chạy test." >&2
    exit 1
fi

IMAGE="${TEST_PHP_IMAGE:-php:8.2-cli}"

echo "Host PHP thiếu pdo_sqlite/pdo_mysql; chạy test trong Docker image ${IMAGE}." >&2

docker run --rm \
    --user "$(id -u):$(id -g)" \
    -e HOME=/tmp \
    -v "$ROOT":/app \
    -w /app \
    "$IMAGE" \
    sh -lc 'php artisan config:clear >/dev/null && php artisan test "$@"' sh "$@"
