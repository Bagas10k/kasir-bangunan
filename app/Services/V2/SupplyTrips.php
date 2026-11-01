<?php

namespace App\Services\V2;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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
        ]);

        return DB::transaction(function () use ($actor, $supplierId, $date, $key, $notes, $items, $hash) {
            $this->lockActor($actor);

            $seen = $this->replay($actor, 'receive', $key, $hash);
            if ($seen !== null) {
                return $seen;
            }

            $supplier = DB::table('v2_parties')->where('id', $supplierId)->lockForUpdate()->first();
            if (! $supplier || $supplier->kind !== 'supplier' || ! $supplier->is_active) {
                $this->invalid('Supplier tidak ditemukan atau tidak aktif.');
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
            foreach ($items as $productId => $row) {
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

            $now = now();
            $receiptId = (int) DB::table('inventory_receipts')->insertGetId([
                'receipt_number' => null,
                'receipt_type' => 'purchase',
                'credit_account' => self::PAYABLE,
                'receipt_date' => $date,
                'supplier_name' => $supplier->name,
                'total_amount' => $this->str($total),
                'notes' => $notes,
                'journal_entry_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

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

            $journalId = Ledger::post('v2_receipt', $receiptId, 'Penerimaan barang '.$date.' dari '.$supplier->name, [
                ['account_code' => self::INVENTORY, 'debit' => $this->str($total), 'credit' => '0.00'],
                ['account_code' => self::PAYABLE, 'debit' => '0.00', 'credit' => $this->str($total)],
            ], $date);

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
                'amount' => $this->str($total),
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

        $driver = $this->text($data['driver_name'] ?? null, 'Nama sopir', 100);
        if ($driver === null) {
            $this->invalid('Nama sopir wajib diisi.');
        }
        $date = $this->date($data['date'] ?? null, 'Tanggal perjalanan');
        $destination = $this->text($data['destination'] ?? null, 'Tujuan', 150);
        if ($destination === null) {
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
            'driver_name' => $driver,
            'date' => $date,
            'destination' => $destination,
            'fuel_cost' => (string) $fuel,
            'allowance' => (string) $allowance,
            'payment_method' => $method,
        ]);

        try {
            return DB::transaction(function () use ($actor, $vehicleId, $deliveryId, $driver, $date, $destination, $fuel, $allowance, $method, $key, $total, $hash) {
                $this->lockActor($actor);

                $seen = $this->replay($actor, 'trip', $key, $hash);
                if ($seen !== null) {
                    return $seen;
                }

                $vehicle = DB::table('vehicles')->where('id', $vehicleId)->lockForUpdate()->first();
                if (! $vehicle || $vehicle->status !== 'active') {
                    $this->invalid('Kendaraan tidak ditemukan atau tidak aktif.');
                }
                if ($deliveryId !== null && ! DB::table('v2_deliveries')->where('id', $deliveryId)->exists()) {
                    $this->invalid('Pengiriman tidak ditemukan.');
                }

                $account = $method === 'cash' ? self::CASH : self::BANK;
                $shift = null;

                if ($method === 'cash') {
                    $shift = DB::table('v2_shifts')
                        ->where('user_id', $actor)
                        ->where('status', 'open')
                        ->lockForUpdate()
                        ->first();
                    if (! $shift) {
                        $this->invalid('Biaya tunai wajib memakai shift admin yang sedang terbuka.');
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
                    'driver_name' => $driver,
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
