<?php

namespace App\Services\V2;

use App\Support\V2\FormCatalog;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Penerimaan barang (pembelian kredit) + biaya perjalanan V2.
 * Uang lewat Money, jurnal lewat Ledger. Tidak memakai ulang alur legacy.
 *
 * Batasan jujur: penyelesaian uang muka (advance) sopir BELUM diimplementasikan.
 * Trip hanya mencatat biaya perjalanan aktual (BBM + uang jalan), tanpa
 * memotong/menutup uang muka.
 */
class SupplyTrips
{
    private const CASH = '1101';

    private const BANK = '1102';

    private const INVENTORY = '1301';

    private const PAYABLE = '2101';

    private const TRIP_EXPENSE = '6101';

    public function __construct(private TransportAssignment $assignment) {}

    /**
     * Penerimaan barang kredit: stok + moving average, jurnal DR 1301 / CR 2101,
     * faktur utang. Idempoten per (actor, 'receive', key).
     *
     * @return array{receipt_id:int, invoice_id:int, total:string}
     */
    public function receive(int $actor, array $data): array
    {
        $this->requireAdmin($actor);

        $supplierId = $this->id($data['supplier_id'] ?? null, 'Supplier');
        $date = $this->date($data['receipt_date'] ?? null, 'Tanggal penerimaan');
        $key = $this->key($data['idempotency_key'] ?? null);
        $notes = $this->text($data['notes'] ?? null, 'Catatan', 255);
        $nota = $this->text($data['supplier_invoice_number'] ?? null, 'Nomor nota supplier', 64);
        $confirmed = filter_var($data['confirm_duplicate'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $freight = $this->freight($data);

        $raw = $data['items'] ?? null;
        if (! is_array($raw) || $raw === [] || count($raw) > 100) {
            $this->invalid('Item penerimaan wajib berisi 1 sampai 100 baris.');
        }

        $items = [];
        foreach (array_values($raw) as $index => $row) {
            $label = ' baris #'.($index + 1);
            if (! is_array($row)) {
                $this->invalid('Item'.$label.' tidak valid.');
            }
            $productId = $this->id($row['product_id'] ?? null, 'Produk'.$label);
            if (isset($items[$productId])) {
                $this->invalid('Produk'.$label.' duplikat; gabungkan menjadi satu baris.');
            }
            $quantity = $this->amount($row['quantity'] ?? null, 4, 'Kuantitas'.$label);
            if (! $quantity->isPositive()) {
                $this->invalid('Kuantitas'.$label.' harus lebih dari nol.');
            }
            $unitCost = $this->amount($row['unit_cost'] ?? null, 4, 'Harga satuan'.$label);
            if ($unitCost->isNegative()) {
                $this->invalid('Harga satuan'.$label.' tidak boleh negatif.');
            }
            $items[$productId] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'subtotal' => $quantity->multipliedBy($unitCost)->toScale(2, RoundingMode::HalfUp),
            ];
        }
        ksort($items);

        $hash = $this->hash('receive', $actor, [
            'supplier_id' => $supplierId,
            'receipt_date' => $date,
            'notes' => $notes,
            'items' => array_map(fn (array $row) => [
                'product_id' => $row['product_id'],
                'quantity' => (string) $row['quantity'],
                'unit_cost' => (string) $row['unit_cost'],
            ], array_values($items)),
        ] + ($nota !== null ? ['supplier_invoice_number' => $nota] : []) + ($freight['freight_amount'] !== '0.00' ? $freight : []));

        return DB::transaction(function () use ($actor, $supplierId, $date, $key, $notes, $items, $hash, $nota, $confirmed, $freight) {
            $this->lockActor($actor);

            $seen = $this->replay($actor, 'receive', $key, $hash);
            if ($seen !== null) {
                return $seen;
            }

            $supplier = DB::table('v2_parties')->where('id', $supplierId)->lockForUpdate()->first();
            if (! $supplier || $supplier->kind !== 'supplier' || ! $supplier->is_active) {
                $this->invalid('Supplier tidak ditemukan atau tidak aktif.');
            }
            // Penjaga entri dobel: nota supplier yang sama sudah tercatat (penerimaan yang dibatalkan tidak dihitung).
            if ($nota !== null && ! $confirmed) {
                $same = DB::table('inventory_receipts as r')->join('v2_invoices as i', 'i.receipt_id', '=', 'r.id')
                    ->where('i.party_id', $supplierId)->whereNull('r.voided_at')
                    ->whereRaw('lower(trim(r.supplier_invoice_number)) = ?', [mb_strtolower($nota)])
                    ->orderByDesc('r.id')->first(['r.receipt_number', 'r.receipt_date', 'r.total_amount']);
                if ($same) {
                    throw ValidationException::withMessages(['duplicate_receipt' => 'Nota '.$nota.' dari '.$supplier->name.' sudah dicatat sebagai '.$same->receipt_number.' tanggal '.Carbon::parse($same->receipt_date)->locale('id')->translatedFormat('j M Y').' ('.Money::display($same->total_amount).'). Bila memang nota berbeda, tekan tombol Ya, tetap konfirmasi.']);
                }
            }

            $products = DB::table('products')
                ->whereIn('id', array_keys($items))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== count($items)) {
                $this->invalid('Sebagian produk tidak ditemukan.');
            }
            $links = DB::table('v2_product_suppliers')
                ->where('supplier_id', $supplierId)
                ->whereIn('product_id', array_keys($items))
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            foreach ($items as $productId => $row) {
                if (! $links->has($productId)) {
                    $this->invalid('Produk #'.$productId.' belum terhubung ke supplier yang dipilih. Atur relasi produk-supplier pada master terlebih dahulu.');
                }
                if (DB::table('stock_movements')->where('product_id', $productId)->whereDate('created_at', '>', $date)->exists()) {
                    $this->invalid('Penerimaan mundur melewati mutasi stok berikutnya; gunakan tanggal operasional yang benar.');
                }
                if (isset($products[$productId]->is_active) && ! $products[$productId]->is_active) {
                    $this->invalid('Produk #'.$productId.' tidak aktif.');
                }
            }

            $total = BigDecimal::zero()->toScale(2);
            foreach ($items as $row) {
                $total = $total->plus($row['subtotal']);
            }
            if (! $total->isPositive()) {
                $this->invalid('Total penerimaan harus lebih dari nol.');
            }
            $freightAmount = Money::value($freight['freight_amount']);
            $payableTotal = $freight['freight_method'] === 'supplier' ? $total->plus($freightAmount) : $total;
            Money::value($this->str($payableTotal));
            $shift = null;
            $freightCredit = $freight['freight_method'] === 'cash' ? self::CASH : self::BANK;
            if ($freightAmount->isPositive() && $freight['freight_method'] !== 'supplier') {
                if ($freight['freight_method'] === 'cash') {
                    $shift = app(Operations::class)->activeStoreShift(true);
                    if (! $shift || substr((string) $shift->opened_at, 0, 10) !== $date) {
                        $this->invalid('Ongkir tunai wajib memakai kas harian aktif pada tanggal penerimaan.');
                    }
                    $drawer = Money::value((string) $shift->opening_cash);
                    foreach (DB::table('v2_shift_movements')->where('shift_id', $shift->id)->get(['direction', 'amount']) as $movement) {
                        $drawer = $movement->direction === 'in' ? $drawer->plus((string) $movement->amount) : $drawer->minus((string) $movement->amount);
                    }
                    if ($drawer->isLessThan($freightAmount)) {
                        $this->invalid('Saldo laci kas tidak cukup untuk ongkir.');
                    }
                }
                $this->guardAccount($freightCredit, $freightAmount);
            }

            $now = now();
            $receiptId = (int) DB::table('inventory_receipts')->insertGetId([
                'receipt_number' => null,
                'receipt_type' => 'purchase',
                'credit_account' => self::PAYABLE,
                'receipt_date' => $date,
                'supplier_name' => $supplier->name,
                'supplier_invoice_number' => $nota,
                'total_amount' => $this->str($total),
                'notes' => $notes,
                'journal_entry_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ] + (Schema::hasColumn('inventory_receipts', 'freight_amount') ? $freight : []));

            DB::table('inventory_receipts')->where('id', $receiptId)->update([
                'receipt_number' => 'RCV-'.str_pad((string) $receiptId, 6, '0', STR_PAD_LEFT),
                'updated_at' => $now,
            ]);

            foreach ($items as $row) {
                DB::table('inventory_receipt_items')->insert([
                    'inventory_receipt_id' => $receiptId,
                    'product_id' => $row['product_id'],
                    'quantity' => (string) $row['quantity'],
                    'unit_cost' => (string) $row['unit_cost'],
                    'cost_before' => (string) $products[$row['product_id']]->cost_price,
                    'subtotal' => $this->str($row['subtotal']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $product = $products[$row['product_id']];
                $oldStock = Money::value((string) $product->stock_quantity, 4);
                $oldCost = Money::value((string) $product->cost_price, 4);
                $newStock = $oldStock->plus($row['quantity']);
                $value = $oldStock->multipliedBy($oldCost)
                    ->plus($row['quantity']->multipliedBy($row['unit_cost']));
                $newCost = $value->dividedBy($newStock, 4, RoundingMode::HalfUp);

                DB::table('products')->where('id', $row['product_id'])->update([
                    'stock_quantity' => (string) $newStock->toScale(4, RoundingMode::HalfUp),
                    'cost_price' => (string) $newCost,
                    'updated_at' => $now,
                ]);

                DB::table('stock_movements')->insert([
                    'product_id' => $row['product_id'],
                    'reference_type' => 'v2_receipt',
                    'reference_id' => $receiptId,
                    'quantity_delta' => (string) $row['quantity'],
                    'balance_after' => (string) $newStock->toScale(4, RoundingMode::HalfUp),
                    'notes' => 'Penerimaan kredit '.$date,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $lines = [
                ['account_code' => self::INVENTORY, 'debit' => $this->str($total), 'credit' => '0.00'],
                ['account_code' => self::PAYABLE, 'debit' => '0.00', 'credit' => $this->str($payableTotal)],
            ];
            if ($freightAmount->isPositive()) {
                $lines[] = ['account_code' => $freight['freight_account'], 'debit' => $this->str($freightAmount), 'credit' => '0.00'];
                if ($freight['freight_method'] !== 'supplier') {
                    $lines[] = ['account_code' => $freightCredit, 'debit' => '0.00', 'credit' => $this->str($freightAmount)];
                }
            }
            $journalId = Ledger::post('v2_receipt', $receiptId, 'Penerimaan barang '.$date.' dari '.$supplier->name, $lines, $date);
            if ($shift !== null) {
                DB::table('v2_shift_movements')->insert([
                    'shift_id' => $shift->id, 'direction' => 'out', 'kind' => 'adjustment',
                    'amount' => $this->str($freightAmount), 'reference_type' => 'v2_receipt_freight',
                    'reference_id' => $receiptId, 'note' => 'Ongkir penerimaan '.$receiptId,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            DB::table('inventory_receipts')->where('id', $receiptId)->update([
                'journal_entry_id' => $journalId,
                'updated_at' => $now,
            ]);

            $invoiceId = (int) DB::table('v2_invoices')->insertGetId([
                'direction' => 'payable',
                'party_id' => $supplierId,
                'sale_id' => null,
                'receipt_id' => $receiptId,
                'number' => 'AP-'.str_pad((string) $receiptId, 6, '0', STR_PAD_LEFT),
                'amount' => $this->str($payableTotal),
                'paid' => '0.00',
                'due_date' => null,
                'status' => 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $result = ['receipt_id' => $receiptId, 'invoice_id' => $invoiceId, 'total' => $this->str($total)];
            $this->remember($actor, 'receive', $key, $hash, $result);

            return $result;
        });
    }

    /**
     * Biaya perjalanan: DR 6101, CR kas/bank. Beban terpisah dari pendapatan
     * ongkir 4102 (tidak saling memotong). Trip tunai wajib shift admin terbuka.
     *
     * @return array{trip_id:int, total:string, journal_entry_id:int}
     */
    public function trip(int $actor, array $data): array
    {
        $this->requireAdmin($actor);

        $vehicleId = $this->id($data['vehicle_id'] ?? null, 'Kendaraan');
        $deliveryRaw = $data['delivery_id'] ?? null;
        $deliveryId = ($deliveryRaw === null || $deliveryRaw === '')
            ? null
            : $this->id($deliveryRaw, 'Pengiriman');

        $driverId = $this->id($data['driver_id'] ?? null, 'Sopir');
        $date = $this->date($data['date'] ?? null, 'Tanggal perjalanan');
        $destination = $deliveryId === null ? $this->text($data['destination'] ?? null, 'Tujuan', 150) : null;
        if ($deliveryId === null && $destination === null) {
            $this->invalid('Tujuan perjalanan wajib diisi.');
        }

        $fuel = $this->amount($data['fuel_cost'] ?? '0', 2, 'Biaya bahan bakar');
        $allowance = $this->amount($data['allowance'] ?? '0', 2, 'Uang jalan');
        if ($fuel->isNegative() || $allowance->isNegative()) {
            $this->invalid('Biaya perjalanan tidak boleh negatif.');
        }

        $method = $data['payment_method'] ?? null;
        if (! in_array($method, ['cash', 'transfer'], true)) {
            $this->invalid('Metode pembayaran perjalanan harus cash atau transfer.');
        }

        $key = $this->key($data['idempotency_key'] ?? null);
        $total = $fuel->plus($allowance);
        if (! $total->isPositive()) {
            $this->invalid('Total biaya perjalanan harus lebih dari nol.');
        }

        $hash = $this->hash('trip', $actor, [
            'delivery_id' => $deliveryId,
            'vehicle_id' => $vehicleId,
            'driver_id' => $driverId,
            'date' => $date,
            'destination' => $destination,
            'fuel_cost' => (string) $fuel,
            'allowance' => (string) $allowance,
            'payment_method' => $method,
        ]);

        try {
            return DB::transaction(function () use ($actor, $vehicleId, $deliveryId, $driverId, $date, $destination, $fuel, $allowance, $method, $key, $total, $hash) {
                $this->lockActor($actor);

                $seen = $this->replay($actor, 'trip', $key, $hash);
                if ($seen !== null) {
                    return $seen;
                }

                $delivery = $deliveryId === null ? null : $this->assignment->delivery($deliveryId);
                $driver = $this->assignment->resolve($driverId, $vehicleId, $delivery);
                $destination = $delivery?->destination ?? $destination;

                $account = $method === 'cash' ? self::CASH : self::BANK;
                $shift = null;

                if ($method === 'cash') {
                    $shift = app(Operations::class)->activeStoreShift(true);
                    if (! $shift) {
                        $this->invalid('Biaya tunai wajib memakai shift toko yang dibuka admin.');
                    }

                    $drawer = Money::value((string) $shift->opening_cash);
                    foreach (DB::table('v2_shift_movements')->where('shift_id', $shift->id)->get(['direction', 'amount']) as $movement) {
                        $drawer = $movement->direction === 'in'
                            ? $drawer->plus((string) $movement->amount)
                            : $drawer->minus((string) $movement->amount);
                    }
                    if ($drawer->isLessThan($total)) {
                        $this->invalid('Saldo laci kas tidak cukup untuk biaya perjalanan.');
                    }
                }

                $this->guardAccount($account, $total);

                $now = now();
                $tripId = (int) DB::table('v2_trips')->insertGetId([
                    'delivery_id' => $deliveryId,
                    'vehicle_id' => $vehicleId,
                    'driver_id' => $driverId,
                    'driver_name' => $driver->name,
                    'date' => $date,
                    'destination' => $destination,
                    'fuel_cost' => $this->str($fuel),
                    'allowance' => $this->str($allowance),
                    'total_cost' => $this->str($total),
                    'status' => 'posted',
                    'requested_by' => $actor,
                    'journal_entry_id' => null,
                    'payment_method' => $method,
                    'idempotency_key' => $key,
                    'request_hash' => $hash,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $journalId = Ledger::post('v2_trip', $tripId, 'Biaya perjalanan '.$destination, [
                    ['account_code' => self::TRIP_EXPENSE, 'debit' => $this->str($total), 'credit' => '0.00'],
                    ['account_code' => $account, 'debit' => '0.00', 'credit' => $this->str($total)],
                ], $date);

                DB::table('v2_trips')->where('id', $tripId)->update([
                    'journal_entry_id' => $journalId,
                    'updated_at' => now(),
                ]);

                if ($shift !== null) {
                    DB::table('v2_shift_movements')->insert([
                        'shift_id' => (int) $shift->id,
                        'direction' => 'out',
                        'kind' => 'adjustment',
                        'amount' => $this->str($total),
                        'reference_type' => 'v2_trip',
                        'reference_id' => $tripId,
                        'note' => 'Biaya perjalanan '.$destination.' (pengeluaran trip, bukan penyesuaian umum)',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $result = ['trip_id' => $tripId, 'total' => $this->str($total), 'journal_entry_id' => $journalId];
                $this->remember($actor, 'trip', $key, $hash, $result);

                return $result;
            });
        } catch (QueryException $e) {
            $this->invalid('Kunci idempotensi perjalanan sudah dipakai.');
        }
    }

    /**
     * Barang yang boleh diterima dari satu supplier, untuk pemilih barang di form penerimaan.
     * Dicari di server (nama atau SKU) agar halaman tidak memuat seluruh katalog.
     *
     * @return array{total:int, results:list<array{id:int, sku:string, name:string, unit:string, stock:string, cost:string}>}
     */
    public function productsFor(int $actor, int $supplierId, string $term = '', int $limit = 30): array
    {
        $this->requireAdmin($actor);

        $query = DB::table('v2_product_suppliers')
            ->join('products', 'products.id', '=', 'v2_product_suppliers.product_id')
            ->join('v2_parties', 'v2_parties.id', '=', 'v2_product_suppliers.supplier_id')
            ->where('v2_product_suppliers.supplier_id', $supplierId)
            ->where('v2_parties.kind', 'supplier')
            ->where('v2_parties.is_active', true)
            ->where('products.is_active', true);

        $term = trim($term);
        if ($term !== '') {
            $query->where(fn ($match) => $match->where('products.name', 'like', '%'.$term.'%')->orWhere('products.sku', 'like', '%'.$term.'%'));
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('products.name')->limit(max(1, min($limit, 50)))
            ->get(['products.id', 'products.sku', 'products.name', 'products.unit', 'products.stock_quantity', 'products.min_stock', 'products.cost_price']);

        return [
            'total' => $total,
            'results' => $rows->map(fn ($row) => [
                'id' => (int) $row->id,
                'sku' => (string) $row->sku,
                'name' => (string) $row->name,
                'unit' => (string) $row->unit,
                'stock' => (string) $row->stock_quantity,
                'minimum' => (string) BigDecimal::of((string) $row->min_stock)->strippedOfTrailingZeros(),
                'status' => BigDecimal::of((string) $row->stock_quantity)->isLessThanOrEqualTo(0) ? 'out'
                    : (BigDecimal::of((string) $row->stock_quantity)->isLessThanOrEqualTo((string) $row->min_stock) ? 'low' : 'available'),
                'cost' => (string) $row->cost_price,
            ])->all(),
        ];
    }

    /**
     * @return array{total:int, page:int, last_page:int, counts:array{critical:int,out:int,low:int}, results:array<int,array<string,mixed>>}
     */
    public function restockProducts(int $actor, string $term = '', string $status = 'critical', ?int $supplierId = null, int $pageNumber = 1): array
    {
        $this->requireAdmin($actor);
        $query = DB::table('products')->where('is_active', true)
            ->where(fn ($stock) => $stock->where('stock_quantity', '<=', 0)->orWhereColumn('stock_quantity', '<=', 'min_stock'));
        if (trim($term) !== '') {
            $query->where(fn ($match) => $match->where('name', 'like', '%'.trim($term).'%')->orWhere('sku', 'like', '%'.trim($term).'%'));
        }
        if ($supplierId !== null) {
            $query->whereExists(fn ($links) => $links->selectRaw('1')->from('v2_product_suppliers as links')
                ->join('v2_parties as supplier', 'supplier.id', '=', 'links.supplier_id')
                ->whereColumn('links.product_id', 'products.id')->where('links.supplier_id', $supplierId)
                ->where('supplier.kind', 'supplier')->where('supplier.is_active', true));
        }
        $out = (clone $query)->where('stock_quantity', '<=', 0)->count();
        $low = (clone $query)->where('stock_quantity', '>', 0)->count();
        if ($status === 'out') {
            $query->where('stock_quantity', '<=', 0);
        } elseif ($status === 'low') {
            $query->where('stock_quantity', '>', 0);
        }
        $total = match ($status) {
            'out' => $out, 'low' => $low, default => $out + $low,
        };
        $pageNumber = max(1, min($pageNumber, (int) ceil($total / 20)));
        $page = $query->orderByRaw('CASE WHEN stock_quantity <= 0 THEN 0 ELSE 1 END')
            ->orderBy('name')->orderBy('id')->paginate(20, ['id', 'sku', 'name', 'unit', 'stock_quantity', 'min_stock', 'cost_price'], 'page', $pageNumber);
        $suppliers = DB::table('v2_product_suppliers as links')
            ->join('v2_parties as suppliers', 'suppliers.id', '=', 'links.supplier_id')
            ->whereIn('links.product_id', $page->getCollection()->pluck('id'))
            ->where('suppliers.kind', 'supplier')->where('suppliers.is_active', true)
            ->orderBy('suppliers.name')->orderBy('suppliers.id')
            ->get(['links.product_id', 'suppliers.id', 'suppliers.name'])->groupBy('product_id');

        return [
            'total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'counts' => ['critical' => $out + $low, 'out' => $out, 'low' => $low],
            'results' => $page->getCollection()->map(fn ($row): array => [
                'id' => (int) $row->id, 'sku' => $row->sku, 'name' => $row->name, 'unit' => $row->unit,
                'stock' => (string) BigDecimal::of((string) $row->stock_quantity)->strippedOfTrailingZeros(),
                'minimum' => (string) BigDecimal::of((string) $row->min_stock)->strippedOfTrailingZeros(),
                'cost' => (string) $row->cost_price,
                'status' => BigDecimal::of((string) $row->stock_quantity)->isLessThanOrEqualTo(0) ? 'out' : 'low',
                'suppliers' => $suppliers->get($row->id, collect())->map(fn ($supplier): array => [
                    'id' => (int) $supplier->id, 'name' => $supplier->name,
                ])->values()->all(),
            ])->all(),
        ];
    }

    /**
     * @param  array{product_id:int|string,supplier_id?:int|string|null,name?:string|null,notes?:string|null,confirmed:mixed,idempotency_key:string}  $data
     * @return array{product_id:int,supplier:array{id:int,name:string,code:string},created:bool}
     */
    public function linkReceiptSupplier(int $actor, array $data): array
    {
        $this->requireAdmin($actor);
        if (! filter_var($data['confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $this->invalid('Konfirmasi penghubungan supplier diperlukan.');
        }
        $productId = $this->id($data['product_id'] ?? null, 'Produk');
        $supplierId = isset($data['supplier_id']) ? $this->id($data['supplier_id'], 'Supplier') : null;
        $name = $this->text($data['name'] ?? null, 'Nama supplier', 255);
        $notes = $this->text($data['notes'] ?? null, 'Catatan supplier', 255);
        if (($supplierId === null && $name === null) || ($supplierId !== null && $name !== null)) {
            $this->invalid('Pilih supplier yang ada atau isi nama supplier baru.');
        }
        $key = $this->key($data['idempotency_key'] ?? null);
        $hash = $this->hash('receipt_supplier', $actor, ['product_id' => $productId, 'supplier_id' => $supplierId, 'name' => $name, 'notes' => $notes]);

        try {
            return DB::transaction(function () use ($actor, $productId, $supplierId, $name, $notes, $key, $hash): array {
                $this->lockActor($actor);
                if ($replay = $this->replay($actor, 'receipt_supplier', $key, $hash)) {
                    return $replay;
                }
                $product = DB::table('products')->where('id', $productId)->lockForUpdate()->first();
                if (! $product || ! $product->is_active) {
                    $this->invalid('Produk tidak ditemukan atau tidak aktif.');
                }
                $created = false;
                if ($supplierId === null) {
                    if (DB::table('v2_parties')->where('kind', 'supplier')->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])->exists()) {
                        $this->invalid('Nama supplier sudah terdaftar. Pilih supplier yang ada; supplier arsip tidak diaktifkan otomatis.');
                    }
                    $code = FormCatalog::nextCode('supplier', DB::table('v2_parties')->where('code', 'like', 'SUP-%')->pluck('code'));
                    $supplierId = app(MasterData::class)->store($actor, 'supplier', ['code' => $code, 'name' => $name, 'notes' => $notes]);
                    $created = true;
                }
                $supplier = DB::table('v2_parties')->where('id', $supplierId)->where('kind', 'supplier')->lockForUpdate()->first();
                if (! $supplier || ! $supplier->is_active) {
                    $this->invalid('Supplier tidak ditemukan atau tidak aktif.');
                }
                $links = DB::table('v2_product_suppliers')->where('product_id', $productId)->lockForUpdate()->get();
                if (! $links->contains('supplier_id', $supplierId)) {
                    DB::table('v2_product_suppliers')->insert([
                        'product_id' => $productId, 'supplier_id' => $supplierId, 'is_primary' => $links->isEmpty(),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $result = [
                    'product_id' => $productId,
                    'supplier' => ['id' => (int) $supplier->id, 'name' => (string) $supplier->name, 'code' => (string) $supplier->code],
                    'created' => $created,
                ];
                $this->remember($actor, 'receipt_supplier', $key, $hash, $result);

                return $result;
            }, 3);
        } catch (QueryException $exception) {
            $this->invalid('Gagal menyimpan supplier dan relasi barang. Tidak ada perubahan yang disimpan; periksa daftar supplier lalu coba lagi.');
        }
    }

    /**
     * Batalkan penerimaan yang salah input: stok dan HPP kembali ke sebelum penerimaan, jurnal balik
     * DR 2101 / CR 1301, tagihan supplier ditutup. Baris asli tidak dihapus (jejak audit).
     * Hanya admin, wajib alasan, dan hanya bila tagihannya belum punya pembayaran apa pun
     * serta barangnya belum punya mutasi stok sesudah penerimaan ini. Idempoten per (actor, 'receipt_void', key).
     *
     * @return array{receipt_id:int, status:string}
     */
    public function voidReceipt(int $actor, int $receiptId, array $input): array
    {
        $this->requireAdmin($actor);
        $receipt = DB::table('inventory_receipts')->where('id', $receiptId)->first();
        abort_unless($receipt, 404);

        $this->invalid('Penerimaan sudah dikonfirmasi dan terkunci. Pembatalan biasa tidak diizinkan; gunakan proses koreksi atau retur.');
    }

    /** @return array{freight_amount:string,freight_method:?string,freight_account:?string} */
    public function freight(array $data): array
    {
        $amount = $this->amount($data['freight_amount'] ?? '0', 2, 'Ongkir');
        if ($amount->isNegative()) {
            $this->invalid('Ongkir tidak boleh negatif.');
        }
        if ($amount->isZero()) {
            return ['freight_amount' => '0.00', 'freight_method' => null, 'freight_account' => null];
        }
        if (! Schema::hasColumn('v2_receipt_drafts', 'freight_amount') || ! Schema::hasColumn('inventory_receipts', 'freight_amount')) {
            $this->invalid('Fitur ongkir belum diaktifkan. Migrasi ongkir perlu dijalankan admin terlebih dahulu.');
        }
        $method = $data['freight_method'] ?? null;
        $account = $data['freight_account'] ?? null;
        if (! in_array($method, ['supplier', 'cash', 'bank'], true)) {
            $this->invalid('Pilih pembayaran ongkir: supplier, kas, atau bank.');
        }
        if (! is_string($account) || ! DB::table('chart_of_accounts')->where('account_code', $account)
            ->where('account_type', 'expense')->where('normal_balance', 'debit')->where('is_active', true)->exists()) {
            $this->invalid('Pilih akun beban ongkir yang aktif.');
        }

        return ['freight_amount' => $this->str($amount), 'freight_method' => $method, 'freight_account' => $account];
    }

    private function requireAdmin(int $actor): void
    {
        $role = DB::table('v2_user_roles')->where('user_id', $actor)->value('role');
        if ($role !== 'admin') {
            abort(403);
        }
    }

    private function lockActor(int $actor): void
    {
        if (! DB::table('users')->where('id', $actor)->lockForUpdate()->exists()) {
            abort(403);
        }
    }

    private function replay(int $actor, string $type, string $key, string $hash): ?array
    {
        $row = DB::table('v2_requests')
            ->where('actor', $actor)
            ->where('type', $type)
            ->where('key', $key)
            ->first();

        if ($row === null) {
            return null;
        }

        if (! hash_equals((string) $row->request_hash, $hash)) {
            $this->invalid('Kunci idempotensi sudah dipakai untuk muatan berbeda.');
        }

        $result = json_decode((string) $row->result, true);
        if (! is_array($result)) {
            $this->invalid('Hasil idempotensi tidak terbaca.');
        }

        return $result;
    }

    private function remember(int $actor, string $type, string $key, string $hash, array $result): void
    {
        DB::table('v2_requests')->insert([
            'actor' => $actor,
            'type' => $type,
            'key' => $key,
            'request_hash' => $hash,
            'result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function hash(string $type, int $actor, array $payload): string
    {
        return hash('sha256', json_encode(
            ['type' => $type, 'actor' => $actor, 'payload' => $payload],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }

    /** Saldo akun tidak boleh negatif setelah pengeluaran, dengan kunci COA. */
    private function guardAccount(string $code, BigDecimal $amount): void
    {
        if (! DB::table('chart_of_accounts')->where('account_code', $code)->where('is_active', true)->lockForUpdate()->exists()) {
            $this->invalid('Akun '.$code.' belum tersedia di bagan akun.');
        }

        $balance = Money::value('0');
        foreach (DB::table('journal_entry_lines')->where('account_code', $code)->get(['debit', 'credit']) as $line) {
            $balance = $balance->plus((string) $line->debit)->minus((string) $line->credit);
        }
        if ($balance->isLessThan($amount)) {
            $this->invalid('Saldo akun '.$code.' tidak cukup untuk pengeluaran ini.');
        }
    }

    private function id(mixed $value, string $field): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[1-9]\d{0,17}$/', trim($value)) === 1) {
            return (int) trim($value);
        }
        $this->invalid($field.' tidak valid.');
    }

    private function amount(mixed $value, int $scale, string $field): BigDecimal
    {
        if (is_int($value)) {
            $raw = (string) $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $raw = trim($value);
        } else {
            $this->invalid($field.' wajib berupa angka.');
        }

        try {
            if (BigDecimal::of($raw)->strippedOfTrailingZeros()->getScale() > $scale) {
                $this->invalid($field.' maksimal '.$scale.' desimal.');
            }

            return Money::value($raw, $scale)->toScale($scale, RoundingMode::HalfUp);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->invalid($field.' tidak valid.');
        }
    }

    private function date(mixed $value, string $field): string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            $this->invalid($field.' harus berformat YYYY-MM-DD.');
        }
        [$year, $month, $day] = array_map('intval', explode('-', $value));
        if (! checkdate($month, $day, $year)) {
            $this->invalid($field.' bukan tanggal yang sah.');
        }

        return $value;
    }

    private function key(mixed $value): string
    {
        if (! is_string($value)) {
            $this->invalid('Kunci idempotensi wajib diisi.');
        }
        $value = trim($value);
        if (preg_match('/^[\x20-\x7E]{1,100}$/', $value) !== 1) {
            $this->invalid('Kunci idempotensi 1..100 karakter ASCII.');
        }

        return $value;
    }

    private function text(mixed $value, string $field, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            $this->invalid($field.' harus berupa teks.');
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $max) {
            $this->invalid($field.' maksimal '.$max.' karakter.');
        }

        return $value;
    }

    private function str(BigDecimal $value): string
    {
        return (string) $value->toScale(2, RoundingMode::HalfUp);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['supply' => $message]);
    }
}
