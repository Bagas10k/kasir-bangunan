#!/usr/bin/env bash
set -euo pipefail

# Disposable negative rehearsal: dirty legacy data must stop migration 000006
# before either MariaDB CHECK constraint is installed.
PROJECT_DIR=/home/ubuntu/kasir-bangunan-app
RUN_ID=$(od -An -N4 -tx1 /dev/urandom | tr -d '[:space:]')
NET_NAME="kasir_dirty_net_${RUN_ID}"
CONTAINER_NAME="kasir_dirty_mariadb_${RUN_ID}"
DB_NAME="kasir_dirty_db_${RUN_ID}"
DB_USER="kasir_dirty_usr_${RUN_ID}"
DB_PASS=$(od -An -N16 -tx1 /dev/urandom | tr -d '[:space:]')
ROOT_PASS=$(od -An -N16 -tx1 /dev/urandom | tr -d '[:space:]')
NET_OWNED=0
CONTAINER_OWNED=0

cleanup() {
  trap - EXIT
  if [ "${CONTAINER_OWNED}" -eq 1 ]; then
    docker rm -f "${CONTAINER_NAME}" >/dev/null 2>&1 || true
  fi
  if [ "${NET_OWNED}" -eq 1 ]; then
    docker network rm "${NET_NAME}" >/dev/null 2>&1 || true
  fi
  echo "Cleanup complete: only this rehearsal's named resources were removed."
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

run_artisan() {
  docker run --rm \
    --network "${NET_NAME}" \
    -v "${PROJECT_DIR}:/app:ro" \
    --tmpfs /app/bootstrap/cache:rw,nosuid,nodev,size=16m \
    --tmpfs /app/storage/logs:rw,nosuid,nodev,size=16m \
    --tmpfs /app/storage/framework/cache:rw,nosuid,nodev,size=16m \
    --tmpfs /app/storage/framework/sessions:rw,nosuid,nodev,size=16m \
    --tmpfs /app/storage/framework/views:rw,nosuid,nodev,size=16m \
    -w /app \
    -e DB_CONNECTION=mysql \
    -e DB_HOST="${CONTAINER_NAME}" \
    -e DB_PORT=3306 \
    -e DB_DATABASE="${DB_NAME}" \
    -e DB_USERNAME="${DB_USER}" \
    -e DB_PASSWORD="${DB_PASS}" \
    -e KASIR_DISPOSABLE_DB_TEST=1 \
    -e LOG_CHANNEL=stderr \
    -e CACHE_STORE=array \
    -e SESSION_DRIVER=array \
    kasir-mysql-test:local php artisan "$@"
}

run_sql() {
  docker exec -i -e MYSQL_PWD="${DB_PASS}" "${CONTAINER_NAME}" \
    mariadb -u"${DB_USER}" "${DB_NAME}" "$@"
}

constraint_counts() {
  run_sql -BNe "
SELECT
  COUNT(CASE WHEN CONSTRAINT_NAME = 'chk_products_stock_nonnegative' THEN 1 END),
  COUNT(CASE WHEN CONSTRAINT_NAME = 'chk_journal_entry_lines_legs' THEN 1 END)
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE()
  AND CONSTRAINT_TYPE = 'CHECK';"
}

echo "[1/7] Create isolated random network and MariaDB (no host port)."
docker network create \
  --label kasir.rehearsal=dirty-preflight \
  --label "kasir.run-id=${RUN_ID}" \
  "${NET_NAME}" >/dev/null
NET_OWNED=1

docker run -d \
  --name "${CONTAINER_NAME}" \
  --network "${NET_NAME}" \
  --label kasir.rehearsal=dirty-preflight \
  --label "kasir.run-id=${RUN_ID}" \
  -e MARIADB_ROOT_PASSWORD="${ROOT_PASS}" \
  -e MARIADB_DATABASE="${DB_NAME}" \
  -e MARIADB_USER="${DB_USER}" \
  -e MARIADB_PASSWORD="${DB_PASS}" \
  mariadb:11.4 >/dev/null
CONTAINER_OWNED=1

READY=0
for _ in $(seq 1 45); do
  if docker exec -e MYSQL_PWD="${DB_PASS}" "${CONTAINER_NAME}" \
      mariadb -u"${DB_USER}" -e "SELECT 1" "${DB_NAME}" >/dev/null 2>&1; then
    READY=1
    break
  fi
  sleep 1
done
if [ "${READY}" -ne 1 ]; then
  echo "ERROR: disposable MariaDB did not become ready." >&2
  exit 1
fi

echo "[2/7] Apply baseline migrations 000000 through 000004."
run_artisan migrate --force \
  --path=database/migrations/0001_01_01_000000_create_users_table.php \
  --path=database/migrations/0001_01_01_000001_create_cache_table.php \
  --path=database/migrations/0001_01_01_000002_create_jobs_table.php \
  --path=database/migrations/2026_10_21_000001_create_materials_pos_schema.php \
  --path=database/migrations/2026_10_21_000002_add_accounting_hardening_constraints.php \
  --path=database/migrations/2026_10_21_000003_create_inventory_receipts.php \
  --path=database/migrations/2026_10_21_000004_link_ritases_to_sales.php

echo "[3/7] Insert one schema-valid legacy row with negative stock."
run_sql <<'SQL'
INSERT INTO products
  (sku, name, unit, cost_price, selling_price, stock_quantity, created_at, updated_at)
VALUES
  ('SYN-DIRTY-NEG', 'Synthetic Dirty Negative Stock', 'pcs', 1000.00, 2000.00, -5.0000, NOW(), NOW());
SQL

echo "[4/7] Apply migration 000005 first."
run_artisan migrate --force \
  --path=database/migrations/2026_10_21_000005_increase_inventory_cost_precision.php

MIGRATION_5_COUNT=$(run_sql -BNe "SELECT COUNT(*) FROM migrations WHERE migration = '2026_10_21_000005_increase_inventory_cost_precision';")
if [ "${MIGRATION_5_COUNT}" != "1" ]; then
  echo "ERROR: migration 000005 was not recorded exactly once." >&2
  exit 1
fi

read -r PRE_STOCK_CHECK PRE_LEGS_CHECK < <(constraint_counts)
if [ "${PRE_STOCK_CHECK:-}" != "0" ] || [ "${PRE_LEGS_CHECK:-}" != "0" ]; then
  echo "ERROR: a target CHECK existed before migration 000006." >&2
  exit 1
fi
echo "EVIDENCE before_000006: stock_check=0 journal_legs_check=0 migration_000005_recorded=1"

echo "[5/7] Run only migration 000006; an exact pre-flight failure is required."
set +e
MIGRATE_OUTPUT=$(run_artisan migrate --force \
  --path=database/migrations/2026_10_21_000006_add_mysql_inventory_journal_checks.php 2>&1)
MIGRATE_STATUS=$?
set -e

if [ "${MIGRATE_STATUS}" -eq 0 ]; then
  echo "ERROR: migration 000006 unexpectedly succeeded." >&2
  exit 1
fi
if ! grep -Fq "Pre-flight migration check failed: detected 1 product(s) with negative stock" <<<"${MIGRATE_OUTPUT}"; then
  echo "ERROR: migration failed, but not for the expected 000006 pre-flight reason." >&2
  exit 1
fi
echo "EVIDENCE migrate_000006_exit=${MIGRATE_STATUS} cause=expected_negative_stock_preflight"

echo "[6/7] Prove no partial DDL, no migration record, and dirty row preservation."
read -r POST_STOCK_CHECK POST_LEGS_CHECK < <(constraint_counts)
MIGRATION_6_COUNT=$(run_sql -BNe "SELECT COUNT(*) FROM migrations WHERE migration = '2026_10_21_000006_add_mysql_inventory_journal_checks';")
read -r DIRTY_COUNT DIRTY_STOCK < <(run_sql -BNe "SELECT COUNT(*), MIN(stock_quantity) FROM products WHERE sku = 'SYN-DIRTY-NEG';")

if [ "${POST_STOCK_CHECK:-}" != "0" ] || [ "${POST_LEGS_CHECK:-}" != "0" ]; then
  echo "ERROR: migration 000006 left a target CHECK partially installed." >&2
  exit 1
fi
if [ "${MIGRATION_6_COUNT}" != "0" ]; then
  echo "ERROR: failed migration 000006 was recorded." >&2
  exit 1
fi
if [ "${DIRTY_COUNT:-}" != "1" ] || [ "${DIRTY_STOCK:-}" != "-5.0000" ]; then
  echo "ERROR: dirty legacy row was lost or changed." >&2
  exit 1
fi

echo "EVIDENCE after_failure: stock_check=0 journal_legs_check=0 migration_000006_recorded=0"
echo "EVIDENCE legacy_row: count=${DIRTY_COUNT} stock_quantity=${DIRTY_STOCK}"
echo "[7/7] PASS: migration 000006 failed before DDL and preserved the dirty legacy row."
