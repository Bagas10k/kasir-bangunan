# Kontrak sprint 2 — operasi inti lanjutan

Status: implementasi berjalan. Semua file pada worktree `/home/ubuntu/kasir-bangunan-rebuild`; produksi `/home/ubuntu/kasir-bangunan-app` tidak boleh disentuh. Framework Laravel 13, PHP 8.5, runtime test vendor disposable mount `/app`. Child uses combo deepseek-1017-rr. Tidak ada placeholder sukses atau data riil. Parent mengintegrasikan route/controller/view, bukan child.

## Ownership maksimal tiga file per pekerja

A: `app/Services/V2/Operations.php`, `database/migrations/2026_10_30_000002_v2_shift_resolution.php`, `tests/Feature/V2PaymentShiftTest.php`.
B: `app/Services/V2/MasterData.php`, `database/migrations/2026_10_30_000003_v2_import_batches.php`, `tests/Feature/V2MasterDataTest.php`.
C: `app/Services/V2/SupplyTrips.php`, `database/migrations/2026_10_30_000004_v2_supply_trips.php`, `tests/Feature/V2SupplyTripsTest.php`.
Parent: controller/routes/workspace/docs/test runner. File anak tidak boleh saling disentuh.

## A — DP dan resolusi shift

- `checkout($actor,$data)` menerima `deposit_method=cash|transfer` untuk `payment_method=credit` dengan paid_amount>0. Wajib eksplisit pada kredit ber-DP; direct credit zero tanpa field tetap sah. Cash DP posted pada drawer; transfer DP pending, paid invoice/sale=0 sampai admin approve. Transfer sale existing tetap utuh.
- `resolveShift(int $actor,int $shiftId,string $reason):void`: admin saja; sumber review, lock; alasan 5..1000; simpan reviewed_by/reviewed_at/review_note pada shift dan status closed; expected/count/difference tidak ditulis ulang, jurnal tidak dibuat. Repeat identik no-op atau validation, jangan overwrite bukti.
- Shift both: nominal cash berarti TOTAL opening; `carry_amount` (kolom shift baru) jumlah counted sebelumnya yang dibawa (min opening/prev counted), `admin_amount=opening-carry`. Sumber carry harus opening<=prev counted, both harus carryFrom ada dan admin_amount>0, carry>0; admin tidak boleh carryFrom. Cegah carry ganda via unique. Parent UI menerangkan total.
- Tolak supplier cash request tanpa open shift pada collect, bukan baru saat approve. Tambah rejectPayment($actor,$paymentId,$reason) admin, catat reason actor/time, hanya pending/proposed -> rejected, tidak ubah saldo/jurnal; alasan simpan kolom payments resolution_note/resolved_at nullable.
- No concurrency claims without MariaDB tests. Do not rewrite whole file. Preserve exact decimal and existing tests.

## B — Master dan XLSX

Service `MasterData`:
`kinds():array` return product/customer/supplier/employee/vehicle.
`columns(string $kind):array` field headers.
`records(int $actor,string $kind):object` admin only, query max100 or paginate.
`store(int $actor,string $kind,array $data):int` creates valid master (DB transaction), product stock=0; parties code unique, vehicle license_plate unique. Update/archive can separate later but never delete used records.
`template(int $actor,string $kind):string` raw bytes real XLSX headers only, no real names.
`preview(int $actor,string $kind,string $file):array` parses XLSX, validates headers/rows max1000, max2MB compressed/10MB total uncompressed, reject macros/externallinks/formulas/duplicate codes and data stock/balance fields; returns batch_id,count,rows(max100 preview),errors; any errors no committable batch. `commit(int $actor,int $batchId):array` lock batch, owner+admin, revalidate DB uniqueness; atomic rows inserts once, repeated commit returns same result no double insert. Batch table rows JSON, kind, actor,status [preview,committed], row_count,created_at/committed_at.
Use installed PHP native PharData ZIP + SimpleXML where feasible (no ZipArchive in prod); retain bounded decompression entry checks and LIBXML_NONET, reject DOCTYPE/entity, column count bounds. Header rows use actual workbook sharedStrings or inlineStr. Numeric strings never float-cast. Excel generator uses minimal OOXML package via PharData zip in private storage temp paths, explicit cleanup. Do not add dependencies or use Python at request time. Test actual XLSX template -> preview/commit plus formulas, duplicate, auth rejection, product zero stock. If native ZIP infeasible return honest blocker, no fake CSV named xlsx.

## C — Receiving dan trip expenses

Service SupplyTrips:
`receive(int $actor,array $data):array`: admin only, data supplier_id,receipt_date,items[{product_id,quantity,unit_cost}],idempotency_key. Validate active supplier and products; positive qty max4 decimals; unit cost>=0 scale4; inventory receipt type purchase credit_account2101. Lock products sorted, update moving-average exact BigDecimal new cost=(oldStock*oldCost+qty*cost)/newQty scale4, stock once, stock_movements reference v2_receipt; journal DR1301 CR2101; create v2_invoices payable linked receipt. New v2_requests table unique(actor,type,key),request_hash,result JSON to enforce idempotent same payload; lock actor row before key read; keep successful result. Never duplicate legacy service flow. Return receipt_id,invoice_id,total.
`trip(int $actor,array $data):array`: admin only to post expense; cashier can draft later if needed. data delivery_id?,vehicle_id,driver_name,date,destination,fuel_cost,allowance,payment_method cash|transfer,idempotency_key. Validate active vehicle and optional delivery exists. Ledger DR6101 CR cash/bank, independent fee revenue4102; ledger balance sufficient under COA lock; cash trip must use admin's open shift, movement direction out kind adjustment with note explicit trip expense (no generic unapproved adjustment). v2_trips row journal_entry_id posted, new columns payment_method/idempotency_key nullable? enforce unique; amount>0. Documents separate so no stock effect. Driver advance settlement NOT implemented -> label limitation, no pretend.
Schema v2_requests in migration, trip payment info new columns. Existing trips table enum and legacy receipt models retained. Tests permission, atomic credit receipt/journal/stock/invoice, repeat key, overpay guards handled Operations; trip expense vs delivery revenue separated, insufficient bank fails no writes. Do not use RefreshDatabase without beforeRefreshingDatabase :memory: guard.

## Tests and evidence

PHP host absent. Parent runs PHP in network-isolated composer:2 with source bind `/app`, disposable vendor + storage. Use `RoundingMode::HalfUp`. Each child <=6 tool calls, create files early, no browser/config/services, no prod writes; concise output artifact paths and exact NOT RUN cases. QC parent after landing.
