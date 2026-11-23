#!/bin/sh
# Menyamakan izin folder storage dengan user PHP-FPM pada setiap start container.
# Volume Docker bisa membawa uid lama (mis. 82 dari image Alpine), sehingga www-data (33)
# tidak dapat menulis log/sesi dan exception logging berubah menjadi HTTP 500.
set -e

mkdir -p /app/storage/logs \
    /app/storage/framework/cache/data \
    /app/storage/framework/sessions \
    /app/storage/framework/views \
    /app/bootstrap/cache

chown -R www-data:www-data /app/storage /app/bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX /app/storage /app/bootstrap/cache 2>/dev/null || true

exec "$@"
