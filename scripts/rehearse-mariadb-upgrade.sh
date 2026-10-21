#!/usr/bin/env bash
set -euo pipefail

# Script: scripts/rehearse-mariadb-upgrade.sh
# Purpose: Safe, disposable MariaDB rehearsal of migrations 000005 & 000006 from baseline schema.

RUN_ID=$(head /dev/urandom | tr -dc a-z0-9 | head -c 8)
NET_NAME="kasir_ephem_net_${RUN_ID}"
CONTAINER_NAME="kasir_ephem_mariadb_${RUN_ID}"
DB_NAME="kasir_ephem_db_${RUN_ID}"
DB_USER="kasir_ephem_usr_${RUN_ID}"
DB_PASS=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 16)
ROOT_PASS=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 16)

cleanup() {
  echo "Cleaning up ephemeral resources..."
  docker stop "${CONTAINER_NAME}" >/dev/null 2>&1 || true
  docker rm -f "${CONTAINER_NAME}" >/dev/null 2>&1 || true
  docker network rm "${NET_NAME}" >/dev/null 2>&1 || true
  echo "Cleanup complete."
}
trap cleanup EXIT INT TERM

echo "[1/7] Creating isolated Docker network ${NET_NAME}..."
docker network create "${NET_NAME}" >/dev/null

echo "[2/7] Starting ephemeral MariaDB container ${CONTAINER_NAME}..."
docker run -d \
  --name "${CONTAINER_NAME}" \
  --network "${NET_NAME}" \
  -e MARIADB_ROOT_PASSWORD="${ROOT_PASS}" \
  -e MARIADB_DATABASE="${DB_NAME}" \
  -e MARIADB_USER="${DB_USER}" \
  -e MARIADB_PASSWORD="${DB_PASS}" \
  mariadb:11.4 >/dev/null

echo "Waiting for MariaDB to be fully initialized and accepting connections..."
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
echo "MariaDB is ready."

run_artisan() {
  docker run --rm \
    --network "${NET_NAME}" \
    -v /home/ubuntu/kasir-bangunan-app:/app \
    -w /app \
    -e DB_CONNECTION=mysql \
    -e DB_HOST="${CONTAINER_NAME}" \
    -e DB_PORT=3306 \
    -e DB_DATABASE="${DB_NAME}" \
    -e DB_USERNAME="${DB_USER}" \
    -e DB_PASSWORD="${DB_PASS}" \
    -e KASIR_DISPOSABLE_DB_TEST=1 \
    kasir-mysql-test:local php artisan "$@"
}

run_sql() {
  docker exec -i -e MYSQL_PWD="${DB_PASS}" "${CONTAINER_NAME}" mariadb -u"${DB_USER}" "${DB_NAME}" "$@"
}

echo "[3/7] Running baseline migrations (000000 through 000004)..."
run_artisan migrate --force \
  --path=database/migrations/0001_01_01_000000_create_users_table.php \
  --path=database/migrations/0001_01_01_000001_create_cache_table.php \
  --path=database/migrations/0001_01_01_000002_create_jobs_table.php \
  --path=database/migrations/2026_10_21_000001_create_materials_pos_schema.php \
  --path=database/migrations/2026_10_21_000002_add_accounting_hardening_constraints.php \
  --path=database/migrations/2026_10_21_000003_create_inventory_receipts.php \
  --path=database/migrations/2026_10_21_000004_link_ritases_to_sales.php

echo "[4/7] Inserting neutral synthetic historical data..."
run_sql << 'SQL'
INSERT INTO products (id, sku, name, unit, cost_price, selling_price, stock_quantity, created_at, updated_at)
VALUES (1, 'SYN-001', 'Synthetic Cement 50kg', 'sak', 65000.00, 72000.00, 50.0000, NOW(), NOW());

INSERT INTO sales (id, invoice_number, customer_type, payment_method, total_amount, cogs_amount, paid_amount, change_amount, created_at, updated_at)
VALUES (1, 'INV-SYN-001', 'retail', 'cash', 72000.00, 65000.00, 72000.00, 0.00, NOW(), NOW());

INSERT INTO sale_items (id, sale_id, product_id, quantity, unit_price, cost_price, subtotal, created_at, updated_at)
VALUES (1, 1, 1, 1.0000, 72000.00, 65000.00, 72000.00, NOW(), NOW());

INSERT INTO inventory_receipts (id, receipt_number, receipt_type, credit_account, receipt_date, supplier_name, total_amount, notes, created_at, updated_at)
VALUES (1, 'REC-SYN-001', 'purchase', '1101', '2026-10-21', 'Neutral Supplier', 650000.00, 'Baseline receipt', NOW(), NOW());

INSERT INTO inventory_receipt_items (id, inventory_receipt_id, product_id, quantity, unit_cost, subtotal, created_at, updated_at)
VALUES (1, 1, 1, 10.0000, 65000.00, 650000.00, NOW(), NOW());

INSERT INTO stock_opnames (id, opname_number, opname_date, status, notes, created_at, updated_at)
VALUES (1, 'OPN-SYN-001', '2026-10-21', 'posted', 'Baseline opname', NOW(), NOW());

INSERT INTO stock_opname_items (id, stock_opname_id, product_id, system_qty, physical_qty, difference_qty, cost_price, value_difference, created_at, updated_at)
VALUES (1, 1, 1, 50.0000, 48.0000, -2.0000, 65000.00, -130000.00, NOW(), NOW());

INSERT INTO chart_of_accounts (id, account_code, account_name, account_type, normal_balance, created_at, updated_at)
VALUES (1, '1101', 'Kas Toko', 'asset', 'debit', NOW(), NOW()),
       (2, '4101', 'Pendapatan Penjualan', 'revenue', 'credit', NOW(), NOW());

INSERT INTO journal_entries (id, reference_type, reference_id, description, transaction_date, created_at, updated_at)
VALUES (1, 'sale', 1, 'Penjualan INV-SYN-001', '2026-10-21', NOW(), NOW());

INSERT INTO journal_entry_lines (id, journal_entry_id, account_code, account_name, debit, credit, created_at, updated_at)
VALUES (1, 1, '1101', 'Kas Toko', 72000.00, 0.00, NOW(), NOW()),
       (2, 1, '4101', 'Pendapatan Penjualan', 0.00, 72000.00, NOW(), NOW());
SQL
echo "Synthetic historical data inserted."

echo "[5/7] Running pending migrations (000005 & 000006)..."
run_artisan migrate --force

echo "[6/7] Verifying row preservation, 19,4 precision, and DB CHECK constraints..."
# 1. Row preservation and exact value verification
read -r p_cnt p_cost s_cnt s_cost r_cnt r_cost o_cnt o_cost j_cnt < <(run_sql -BNe "
SELECT 
  (SELECT count(*) FROM products),
  (SELECT cost_price FROM products WHERE id=1),
  (SELECT count(*) FROM sale_items),
  (SELECT cost_price FROM sale_items WHERE id=1),
  (SELECT count(*) FROM inventory_receipt_items),
  (SELECT unit_cost FROM inventory_receipt_items WHERE id=1),
  (SELECT count(*) FROM stock_opname_items),
  (SELECT cost_price FROM stock_opname_items WHERE id=1),
  (SELECT count(*) FROM journal_entry_lines);
")

echo "Row preservation check:"
echo "products count=${p_cnt} cost_price=${p_cost}"
echo "sale_items count=${s_cnt} cost_price=${s_cost}"
echo "inventory_receipt_items count=${r_cnt} unit_cost=${r_cost}"
echo "stock_opname_items count=${o_cnt} cost_price=${o_cost}"
echo "journal_entry_lines count=${j_cnt}"

if [ "${p_cnt:-}" != "1" ] || [ "${p_cost:-}" != "65000.0000" ] || \
   [ "${s_cnt:-}" != "1" ] || [ "${s_cost:-}" != "65000.0000" ] || \
   [ "${r_cnt:-}" != "1" ] || [ "${r_cost:-}" != "65000.0000" ] || \
   [ "${o_cnt:-}" != "1" ] || [ "${o_cost:-}" != "65000.0000" ] || \
   [ "${j_cnt:-}" != "2" ]; then
  echo "ERROR: Row preservation check failed! Unexpected row count or altered values." >&2
  exit 1
fi
echo "Verified: Row preservation and exact values intact."

# 2. Decimal(19,4) column precision check
PREC_CHECK=$(run_sql -e "
SELECT TABLE_NAME, COLUMN_NAME, NUMERIC_PRECISION, NUMERIC_SCALE
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND (
    (TABLE_NAME = 'products' AND COLUMN_NAME = 'cost_price') OR
    (TABLE_NAME = 'inventory_receipt_items' AND COLUMN_NAME = 'unit_cost') OR
    (TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'cost_price') OR
    (TABLE_NAME = 'stock_opname_items' AND COLUMN_NAME = 'cost_price')
  );
")
echo "Column precision check:"
echo "${PREC_CHECK}"

PREC_COUNT=$(echo "${PREC_CHECK}" | awk '$3 == "19" && $4 == "4" {count++} END {print count+0}')
if [ "${PREC_COUNT}" -ne 4 ]; then
  echo "ERROR: Expected 4 columns with precision=19 and scale=4, got ${PREC_COUNT}" >&2
  exit 1
fi
echo "Verified: All 4 target columns altered to decimal(19, 4)."

# 3. DB CHECK constraint: negative stock violation probe
set +e
NEGATIVE_STOCK_ERR=$(run_sql -e "
INSERT INTO products (sku, name, unit, cost_price, selling_price, stock_quantity, created_at, updated_at)
VALUES ('NEG-001', 'Negative Item', 'pcs', 1000.0000, 2000.0000, -5.0000, NOW(), NOW());
" 2>&1)
CHECK_STOCK_RES=$?
set -e

if [ $CHECK_STOCK_RES -eq 0 ]; then
  echo "ERROR: Negative stock insert succeeded! Expected CHECK constraint failure." >&2
  exit 1
fi
if ! echo "${NEGATIVE_STOCK_ERR}" | grep -q "chk_products_stock_nonnegative"; then
  echo "ERROR: Expected error mentioning chk_products_stock_nonnegative, got: ${NEGATIVE_STOCK_ERR}" >&2
  exit 1
fi
echo "Negative stock CHECK enforced: constraint violation chk_products_stock_nonnegative caught successfully."

# 4. DB CHECK constraint: invalid journal legs violation probe
set +e
INVALID_LEGS_ERR=$(run_sql -e "
INSERT INTO journal_entry_lines (journal_entry_id, account_code, account_name, debit, credit, created_at, updated_at)
VALUES (1, '1101', 'Kas Toko', 100.0000, 100.0000, NOW(), NOW());
" 2>&1)
CHECK_LEGS_RES=$?
set -e

if [ $CHECK_LEGS_RES -eq 0 ]; then
  echo "ERROR: Invalid journal legs insert succeeded! Expected CHECK constraint failure." >&2
  exit 1
fi
if ! echo "${INVALID_LEGS_ERR}" | grep -q "chk_journal_entry_lines_legs"; then
  echo "ERROR: Expected error mentioning chk_journal_entry_lines_legs, got: ${INVALID_LEGS_ERR}" >&2
  exit 1
fi
echo "Journal legs CHECK enforced: constraint violation chk_journal_entry_lines_legs caught successfully."

echo "[7/7] Testing migration idempotency..."
IDEMPOTENT_OUT=$(run_artisan migrate --force)
echo "${IDEMPOTENT_OUT}"
if echo "${IDEMPOTENT_OUT}" | grep -qi "Nothing to migrate"; then
  echo "Verified: Rerunning migrate is idempotent (Nothing to migrate)."
else
  echo "ERROR: Expected 'Nothing to migrate'." >&2
  exit 1
fi

echo "ALL CHECKS PASSED: MariaDB upgrade rehearsal for migrations 000005 & 000006 verified successfully."