#!/usr/bin/env bash
set -euo pipefail

# Script: scripts/rehearse-mariadb-financial.sh
# Purpose: Isolated MariaDB 11.4 financial integration rehearsal for CURRENT Laravel app.

RUN_ID=$(head /dev/urandom | tr -dc a-z0-9 | head -c 8)
NET_NAME="kasir_ephem_net_${RUN_ID}"
CONTAINER_NAME="kasir_ephem_mariadb_${RUN_ID}"
DB_NAME="kasir_ephem_db_${RUN_ID}"
DB_USER="kasir_ephem_usr_${RUN_ID}"
DB_PASS=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 16)
ROOT_PASS=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 16)

cleanup() {
  echo "Cleaning up ephemeral rehearsal resources..."
  docker stop "${CONTAINER_NAME}" >/dev/null 2>&1 || true
  docker rm -f "${CONTAINER_NAME}" >/dev/null 2>&1 || true
  docker network rm "${NET_NAME}" >/dev/null 2>&1 || true
  echo "Cleanup complete."
}
trap cleanup EXIT INT TERM

echo "[1/4] Creating isolated Docker network ${NET_NAME}..."
docker network create "${NET_NAME}" >/dev/null

echo "[2/4] Starting ephemeral MariaDB 11.4 container ${CONTAINER_NAME}..."
docker run -d \
  --name "${CONTAINER_NAME}" \
  --network "${NET_NAME}" \
  -e MARIADB_ROOT_PASSWORD="${ROOT_PASS}" \
  -e MARIADB_DATABASE="${DB_NAME}" \
  -e MARIADB_USER="${DB_USER}" \
  -e MARIADB_PASSWORD="${DB_PASS}" \
  mariadb:11.4 >/dev/null

echo "Waiting for MariaDB 11.4 to become ready..."
READY=0
for i in $(seq 1 45); do
  if docker exec -e MYSQL_PWD="${DB_PASS}" "${CONTAINER_NAME}" mariadb -u"${DB_USER}" -e "SELECT 1" "${DB_NAME}" >/dev/null 2>&1; then
    READY=1
    break
  fi
  sleep 1
done

if [ "$READY" -ne 1 ]; then
  echo "ERROR: MariaDB container failed to become ready in time." >&2
  exit 1
fi
echo "MariaDB 11.4 is accepting connections."

run_in_container() {
  docker run --rm \
    --network "${NET_NAME}" \
    -v /home/ubuntu/kasir-bangunan-app:/app:ro \
    --tmpfs /app/storage/framework/cache:rw,exec \
    --tmpfs /app/storage/framework/sessions:rw,exec \
    --tmpfs /app/storage/framework/views:rw,exec \
    --tmpfs /app/storage/logs:rw,exec \
    --tmpfs /app/bootstrap/cache:rw,exec \
    -w /app \
    -e DB_CONNECTION=mysql \
    -e DB_HOST="${CONTAINER_NAME}" \
    -e DB_PORT=3306 \
    -e DB_DATABASE="${DB_NAME}" \
    -e DB_USERNAME="${DB_USER}" \
    -e DB_PASSWORD="${DB_PASS}" \
    -e KASIR_DISPOSABLE_DB_TEST=1 \
    kasir-mysql-test:local "$@"
}

echo "[3/4] Running all migrations to initialize empty database schema..."
run_in_container php artisan migrate --force

echo "[4/4] Executing financial integration rehearsal and SQL SUM assertions..."
run_in_container php scripts/rehearse-mariadb-financial.php

echo "MARIADB_FINANCIAL_REHEARSAL_PASS"
