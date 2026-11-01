#!/usr/bin/env bash
# Isolated MariaDB rehearsal for kasir-bangunan rebuild.
# Touches ONLY: new docker network kasir-v2-rehearsal (--internal), new containers,
# a scratch password/env file, and the JSON report below.
# NEVER connects to, or writes to, production/legacy DB, source tree, or .env files.
set -euo pipefail

ROOT=${V2_ROOT:-/home/ubuntu/kasir-bangunan-rebuild}
BACKUP=${V2_BACKUP:-/home/ubuntu/backups/kasir-bangunan/rebuild-20261030_000823/database-before.sql.gz}
RUNTIME=${V2_TEST_RUNTIME:-/home/ubuntu/.hermes/cache/scratch/kasir-v2-runtime}
SCRATCH=${V2_SCRATCH:-/home/ubuntu/.hermes/cache/scratch/kasir-mariadb-rehearsal}
REPORT=${V2_REPORT:-/home/ubuntu/.hermes/work/kasir-rebuild-20261029/mariadb-rehearsal.json}

NET=kasir-v2-rehearsal
DBCT=kasir-v2-rehearsal-db
DBNAME=kasir_v2_rehearsal
DBIMAGE=mariadb:11.4
PHPIMAGE=kasir-mysql-test:local

PASSFILE="$SCRATCH/db-root.pass"
DBENV="$SCRATCH/db.env"
PHPENV="$SCRATCH/php.env"

log() { printf '[rehearse-v2] %s\n' "$*" >&2; }
die() { printf '[rehearse-v2] FAIL: %s\n' "$*" >&2; exit 1; }

mkdir -p "$SCRATCH" "$(dirname "$REPORT")"
chmod 700 "$SCRATCH"

# ---------- 0. preconditions ----------
test -f "$BACKUP" || die "backup missing: $BACKUP"
test -f "$RUNTIME/vendor/autoload.php" || die "missing disposable vendor tree: $RUNTIME/vendor"
gzip -t "$BACKUP" || die "gzip integrity test failed"
BACKUP_BYTES=$(stat -c %s "$BACKUP")

# ---------- 1. random root password (never printed) ----------
if [ ! -s "$PASSFILE" ]; then
  umask 077
  head -c 48 /dev/urandom | base64 | tr -d '\n=+/' > "$PASSFILE"
fi
chmod 600 "$PASSFILE"
DBPASS=$(cat "$PASSFILE")
[ ${#DBPASS} -ge 32 ] || die "password generation too short"

umask 077
printf 'MARIADB_ROOT_PASSWORD=%s\nMARIADB_DATABASE=%s\n' "$DBPASS" "$DBNAME" > "$DBENV"
APPKEY=$(python3 -c 'import secrets,base64;print("base64:"+base64.b64encode(secrets.token_bytes(32)).decode())')
cat > "$PHPENV" <<EOF
APP_ENV=local
APP_DEBUG=false
APP_KEY=$APPKEY
DB_CONNECTION=mysql
DB_HOST=$DBCT
DB_PORT=3306
DB_DATABASE=$DBNAME
DB_USERNAME=root
DB_PASSWORD=$DBPASS
DB_URL=
SESSION_DRIVER=array
CACHE_STORE=array
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr
EOF
chmod 600 "$DBENV" "$PHPENV"
unset DBPASS APPKEY

# ---------- 2. isolated network ----------
if ! docker network inspect "$NET" >/dev/null 2>&1; then
  docker network create --internal "$NET" >/dev/null
fi
NET_INTERNAL=$(docker network inspect -f '{{.Internal}}' "$NET")
[ "$NET_INTERNAL" = "true" ] || die "network $NET is not --internal"

# ---------- 3. DB container ----------
if docker container inspect "$DBCT" >/dev/null 2>&1; then
  log "reusing existing container $DBCT"
  docker start "$DBCT" >/dev/null 2>&1 || true
else
  docker run -d --name "$DBCT" --network "$NET" --network-alias "$DBCT" \
    --memory 384m --cpus 1 \
    --tmpfs /var/lib/mysql:rw,size=320m \
    --env-file "$DBENV" \
    "$DBIMAGE" \
    --innodb-buffer-pool-size=128M --skip-name-resolve >/dev/null
fi

q() { docker exec -e MYSQL_PWD="$(cat "$PASSFILE")" "$DBCT" mariadb -uroot -N -B -e "$1" 2>&1; }
qdb() { docker exec -e MYSQL_PWD="$(cat "$PASSFILE")" "$DBCT" mariadb -uroot -N -B -e "$1" "$DBNAME" 2>&1; }

READY=0
for _ in $(seq 1 60); do
  if docker exec -e MYSQL_PWD="$(cat "$PASSFILE")" "$DBCT" mariadb -uroot -N -B -e 'SELECT 1' >/dev/null 2>&1; then READY=1; break; fi
  sleep 1
done
[ "$READY" = "1" ] || die "mariadb did not become ready in 60s"
DB_VERSION=$(q "SELECT VERSION()")

# ---------- 4. fresh schema + restore ----------
q "DROP DATABASE IF EXISTS \`$DBNAME\`; CREATE DATABASE \`$DBNAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gzip -dc "$BACKUP" \
  | sed -E 's/^CREATE DATABASE.*$//; s/^USE .*$//' \
  | docker exec -i -e MYSQL_PWD="$(cat "$PASSFILE")" "$DBCT" mariadb -uroot "$DBNAME" \
  || die "restore failed"
TABLES_RESTORED=$(q "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DBNAME' AND table_type='BASE TABLE'")
[ "$TABLES_RESTORED" -ge 5 ] || die "restore produced only $TABLES_RESTORED tables"

# ---------- 5. snapshot before migrate ----------
snapshot() { # $1 = outfile
  local tables list
  tables=$(q "SELECT table_name FROM information_schema.tables WHERE table_schema='$DBNAME' AND table_type='BASE TABLE' ORDER BY table_name" | tr -d '\r')
  : > "$1"
  while IFS= read -r t; do
    [ -z "$t" ] && continue
    local cnt sum
    cnt=$(q "SELECT COUNT(*) FROM \`$DBNAME\`.\`$t\`")
    sum=$(q "CHECKSUM TABLE \`$DBNAME\`.\`$t\`" | awk -F'\t' '{print $2}')
    printf '%s\t%s\t%s\n' "$t" "$cnt" "$sum" >> "$1"
  done <<< "$tables"
}
SNAP_BEFORE="$SCRATCH/snap-before.tsv"
SNAP_AFTER="$SCRATCH/snap-after.tsv"
snapshot "$SNAP_BEFORE"

JOURNAL_TABLES=$(awk -F'\t' '$1 ~ /journal/ {print $1}' "$SNAP_BEFORE" | tr '\n' ' ')
PRODUCT_TABLES=$(awk -F'\t' '$1 ~ /product/ {print $1}' "$SNAP_BEFORE" | tr '\n' ' ')

# ---------- 6. migrate candidate (additive, --force) ----------
MIGRATE_LOG="$SCRATCH/migrate.log"
set +e
docker run --rm --network "$NET" \
  --memory 384m \
  -v "$ROOT:/app:ro" \
  -v "$RUNTIME/vendor:/app/vendor:ro" \
  -v "$RUNTIME/storage:/app/storage" \
  -v "$RUNTIME/bootstrap-cache:/app/bootstrap/cache" \
  --env-file "$PHPENV" \
  -w /app --entrypoint php "$PHPIMAGE" \
  artisan migrate --force > "$MIGRATE_LOG" 2>&1
MIGRATE_RC=$?
set -e
MIGRATE_DONE=$(grep -c 'DONE' "$MIGRATE_LOG" 2>/dev/null || true)
MIGRATE_NOTHING=$(grep -c 'Nothing to migrate' "$MIGRATE_LOG" 2>/dev/null || true)

# ---------- 7. snapshot after + integrity compare ----------
snapshot "$SNAP_AFTER"
TABLES_AFTER=$(q "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DBNAME' AND table_type='BASE TABLE'")

# ---------- 8. HPP cost precision ----------
COST_TYPE=$(qdb "SHOW COLUMNS FROM products LIKE 'cost_price'" | awk -F'\t' '{print $2}')
COST_SCALE=$(printf '%s' "$COST_TYPE" | sed -nE 's/.*decimal\([0-9]+,([0-9]+)\).*/\1/p')
[ -z "$COST_SCALE" ] && COST_SCALE=0
UIT_COST=$(qdb "SHOW COLUMNS FROM inventory_receipt_items LIKE 'unit_cost'" | awk -F'\t' '{print $2}')
SI_COST=$(qdb "SHOW COLUMNS FROM sale_items LIKE 'cost_price'" | awk -F'\t' '{print $2}')

# ---------- 9. financial aggregate (no PII) ----------
FIN_COUNT=''; FIN_SUM=''; FIN_QUERY=''
if [ -n "$(q "SELECT 1 FROM information_schema.columns WHERE table_schema='$DBNAME' AND table_name='sales' AND column_name='total_amount'")" ]; then
  FIN_ROW=$(q "SELECT COUNT(*), COALESCE(SUM(GREATEST(total_amount-paid_amount,0)),0), COALESCE(SUM(total_amount-paid_amount),0), COALESCE(SUM(total_amount),0) FROM \`$DBNAME\`.sales")
  FIN_QUERY='SELECT COUNT(*), SUM(GREATEST(total_amount-paid_amount,0)) FROM sales'
  FIN_COUNT=$(printf '%s' "$FIN_ROW" | awk -F'\t' '{print $1}')
  FIN_SUM=$(printf '%s' "$FIN_ROW" | awk -F'\t' '{print $2}')
  FIN_NET=$(printf '%s' "$FIN_ROW" | awk -F'\t' '{print $3}')
  FIN_TOT=$(printf '%s' "$FIN_ROW" | awk -F'\t' '{print $4}')
fi

# column inventory (names only, no data)
COLUMNS=$(q "SELECT table_name, GROUP_CONCAT(column_name ORDER BY ordinal_position) FROM information_schema.columns WHERE table_schema='$DBNAME' GROUP BY table_name" | tr '\t' ':')

# ---------- 10. report ----------
SNAP_BEFORE="$SNAP_BEFORE" SNAP_AFTER="$SNAP_AFTER" \
REPORT="$REPORT" BACKUP="$BACKUP" BACKUP_BYTES="$BACKUP_BYTES" \
DB_VERSION="$DB_VERSION" TABLES_RESTORED="$TABLES_RESTORED" \
TABLES_AFTER="$TABLES_AFTER" MIGRATE_RC="$MIGRATE_RC" MIGRATE_DONE="$MIGRATE_DONE" MIGRATE_NOTHING="$MIGRATE_NOTHING" \
MIGRATE_LOG="$MIGRATE_LOG" COST_TYPE="$COST_TYPE" COST_SCALE="$COST_SCALE" UIT_COST="$UIT_COST" SI_COST="$SI_COST" \
FIN_COUNT="$FIN_COUNT" FIN_SUM="$FIN_SUM" FIN_NET="${FIN_NET:-}" FIN_TOT="${FIN_TOT:-}" FIN_QUERY="$FIN_QUERY" \
JOURNAL_TABLES="$JOURNAL_TABLES" PRODUCT_TABLES="$PRODUCT_TABLES" \
COLUMNS="$COLUMNS" SCRATCH="$SCRATCH" \
NET="$NET" DBCT="$DBCT" DBNAME="$DBNAME" PHPIMAGE="$PHPIMAGE" DBIMAGE="$DBIMAGE" \
RUNTIME="$RUNTIME" ROOT="$ROOT" BACKUP_BYTES="$BACKUP_BYTES" \
python3 - <<'PY'
import json, os, datetime

def tsv(p):
    d = {}
    with open(p) as f:
        for line in f:
            parts = line.rstrip('\n').split('\t')
            if len(parts) >= 3:
                d[parts[0]] = {'rows': int(parts[1]), 'checksum': parts[2]}
    return d

before, after = tsv(os.environ['SNAP_BEFORE']), tsv(os.environ['SNAP_AFTER'])

def compare(pred):
    out = []
    for t, b in before.items():
        if not pred(t):
            continue
        a = after.get(t, {'rows': None, 'checksum': None})
        out.append({
            'table': t,
            'rows_before': b['rows'],
            'rows_after': a['rows'],
            'checksum_before': b['checksum'],
            'checksum_after': a['checksum'],
            'count_match': b['rows'] == a['rows'],
            'checksum_match': b['checksum'] == a['checksum'],
        })
    return out

journal = compare(lambda t: 'journal' in t)
products = compare(lambda t: 'product' in t)

scale = int(os.environ.get('COST_SCALE') or 0)
mig_rc = int(os.environ['MIGRATE_RC'])
fin_count = int(os.environ['FIN_COUNT']) if os.environ['FIN_COUNT'].isdigit() else None
fin_sum = os.environ['FIN_SUM'] or None

report = {
    'generated_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
    'script': 'scripts/rehearse-v2.sh',
    'scope': 'isolated rehearsal; production DB/source/env untouched',
    'backup': {
        'path': os.environ['BACKUP'],
        'bytes': int(os.environ['BACKUP_BYTES']),
        'gzip_test': 'ok',
    },
    'network': {'name': os.environ['NET'], 'driver': 'bridge', 'internal': True},
    'db_container': {
        'name': os.environ['DBCT'],
        'image': os.environ['DBIMAGE'],
        'server_version': os.environ['DB_VERSION'],
        'memory_limit': '384m',
        'cpus': '1.0',
        'tmpfs_data_dir': '/var/lib/mysql',
        'left_running_for_parent_probe': True,
    },
    'database': {
        'name': os.environ['DBNAME'],
        'tables_restored': int(os.environ['TABLES_RESTORED']),
        'tables_after_migrate': int(os.environ['TABLES_AFTER']),
        'restore_mode': 'CREATE DATABASE/USE lines stripped so dump lands only in the rehearsal schema',
        'secrets': {'root_password_file': os.path.join(os.environ['SCRATCH'], 'db-root.pass'), 'mode': '600', 'printed': False},
    },
    'php_runner': {
        'image': os.environ['PHPIMAGE'],
        'entrypoint': 'php artisan migrate --force',
        'app_mount': os.environ['ROOT'] + ':/app:ro',
        'vendor_mount': os.environ['RUNTIME'] + '/vendor:/app/vendor:ro',
        'composer_network_installs': False,
    },
    'migrate': {
        'rc': mig_rc,
        'forced': True,
        'migrations_done': int(os.environ['MIGRATE_DONE'] or 0),
        'nothing_to_migrate': int(os.environ['MIGRATE_NOTHING'] or 0),
        'log_path': os.environ['MIGRATE_LOG'],
    },
    'integrity': {
        'method': 'COUNT(*) + CHECKSUM TABLE, captured after restore (before migrate) and after migrate',
        'journal': {'tables': journal, 'all_counts_match': all(x['count_match'] for x in journal),
                    'all_checksums_match': all(x['checksum_match'] for x in journal)},
        'products': {'tables': products, 'all_counts_match': all(x['count_match'] for x in products),
                     'all_checksums_match': all(x['checksum_match'] for x in products)},
        'checksums_captured': len(before),
    },
    'financial': {
        'active_sales_count': fin_count,
        'outstanding_sum': fin_sum,
        'net_balance_sum': os.environ.get('FIN_NET') or None,
        'sales_total_sum': os.environ.get('FIN_TOT') or None,
        'basis': 'outstanding = SUM(GREATEST(total_amount-paid_amount,0)); net_balance keeps raw sign so overpaid/change rows are visible; sales has no status/void column in this backup',
        'query_used': os.environ['FIN_QUERY'] or None,
        'pii': 'aggregate only, no names/customers/phones emitted',
    },
    'hpp_cost_precision': {
        'table': 'products',
        'column': 'cost_price',
        'type': os.environ['COST_TYPE'] or None,
        'scale': scale,
        'ready_for_hpp_4dp': scale == 4,
        'related': {'inventory_receipt_items.unit_cost': os.environ.get('UIT_COST'),
                    'sale_items.cost_price': os.environ.get('SI_COST')},
        'note': 'scale 2 means migration 2026_10_21_000005 did not apply -> HPP 4dp NOT ready',
    },
    'schema_inventory': {
        'tables': sorted(before.keys()),
        'columns_by_table': os.environ['COLUMNS'].splitlines(),
    },
    'limitations': [],
}
lim = report['limitations']
if mig_rc != 0:
    lim.append('migrate --force exited non-zero; see log_path')
if scale != 4:
    lim.append('products.cost_price scale=%d (expected 4) -> HPP precision not ready' % scale)
if fin_count is None:
    lim.append('outstanding sale total could not be computed (sales columns differ)')
if 'journal' not in ' '.join(before.keys()):
    lim.append('no journal table found in backup')
lim.append('tmpfs data dir: rehearsal DB is non-persistent by design, gone on container removal')
lim.append('CHECKSUM TABLE is server/engine dependent; only before/after on the same instance is comparable')
lim.append('DB container intentionally left running for the parent concurrency probe (no cleanup)')

with open(os.environ['REPORT'], 'w') as f:
    json.dump(report, f, indent=2)
print(os.environ['REPORT'])
PY

log "report: $REPORT"
log "migrate rc=$MIGRATE_RC done=$MIGRATE_DONE cost_type=${COST_TYPE:-none}"
log "db container $DBCT left running on $NET for parent probe"