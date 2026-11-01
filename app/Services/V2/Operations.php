<?php

namespace App\Services\V2;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Operasi kasir V2: peran, shift, penjualan, penagihan, persetujuan pembayaran.
 * Semua uang BigDecimal skala 2 (qty 4), jurnal lewat Ledger, stok legacy products.
 */
class Operations
{
    private const CASH = '1101';
    private const BANK = '1102';
    private const AR = '1103';
    private const INVENTORY = '1301';
    private const AP = '2101';
    private const GOODS_REVENUE = '4101';
    private const DELIVERY_REVENUE = '4102';
    private const COGS = '5101';

    public function role(int $actor): string
    {
        $role = DB::table('v2_user_roles')->join('users', 'users.id', '=', 'v2_user_roles.user_id')->where('v2_user_roles.user_id', $actor)->where('users.is_active', true)->value('v2_user_roles.role');
        if (!is_string($role) || !in_array($role, ['admin', 'cashier'], true)) {
            abort(403);
        }

        return $role;
    }

    public function requireRole(int $actor, array $roles): void
    {
        if (!in_array($this->role($actor), $roles, true)) {
            abort(403);
        }
    }

    public function openShift(int $actor, string $cash, string $source, ?int $carryFrom = null): int
    {
        $this->requireRole($actor, ['admin', 'cashier']);
        if (!in_array($source, ['admin', 'carry', 'both'], true)) {
            $this->invalid('Sumber modal tidak valid.');
        }
        if (in_array($source, ['carry', 'both'], true) && $carryFrom === null) {
            $this->invalid('Shift asal wajib untuk sumber carry atau both.');
        }
        if ($source === 'admin' && $carryFrom !== null) {
            $this->invalid('Sumber admin tidak boleh membawa shift asal.');
        }
        $opening = $this->amount($cash, 2, 'Modal awal');
        if ($opening->isNegative()) {
            $this->invalid('Modal awal tidak boleh negatif.');
        }

        try {
            return DB::transaction(function () use ($actor, $opening, $source, $carryFrom) {
                if (DB::table('v2_shifts')->where('user_id', $actor)->where('status', 'open')->lockForUpdate()->exists()) {
                    $this->invalid('Masih ada shift terbuka untuk kasir ini.');
                }

                $carry = Money::value('0');
                $admin = $opening;

                if ($carryFrom !== null) {
                    $prev = DB::table('v2_shifts')->where('id', $carryFrom)->lockForUpdate()->first();
                    if (!$prev || $prev->status !== 'closed') {
                        $this->invalid('Shift asal harus sudah ditutup.');
                    }
                    if ((int) $prev->user_id !== $actor) {
                        abort(403);
                    }
                    if ($prev->counted_cash === null) {
                        $this->invalid('Shift asal belum memiliki uang hitung.');
                    }
                    $counted = Money::value((string) $prev->counted_cash, 2);
                    if ($counted->isNegative()) {
                        $this->invalid('Uang hitung shift asal negatif.');
                    }
                    if ($source === 'carry' && $opening->isGreaterThan($counted)) {
                        $this->invalid('Modal carry melebihi uang hitung shift asal.');
                    }
                    if ($source === 'both') {
                        if ($opening->isLessThanOrEqualTo($counted)) {
                            $this->invalid('Sumber both memerlukan modal melebihi uang hitung shift asal.');
                        }
                        $carry = $counted;
                        $admin = $opening->minus($counted);
                        if (!$carry->isPositive() || !$admin->isPositive()) {
                            $this->invalid('Nilai carry dan modal admin harus lebih dari nol.');
                        }
                    } elseif ($source === 'carry') {
                        $carry = $opening;
                        $admin = Money::value('0');
                    }
                    if (DB::table('v2_shifts')->where('carried_from_id', $carryFrom)->exists()) {
                        $this->invalid('Saldo shift asal sudah dibawa.');
                    }
                }

                return DB::table('v2_shifts')->insertGetId([
                    'user_id' => $actor,
                    'open_user_id' => $actor,
                    'source' => $source,
                    'opening_cash' => $this->str($opening),
                    'carry_amount' => $this->str($carry),
                    'admin_amount' => $this->str($admin),
                    'status' => 'open',
                    'opened_at' => now(),
                    'carried_from_id' => $carryFrom,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (QueryException $e) {
            $this->invalid('Shift gagal dibuka; periksa shift terbuka atau carry ganda.');
        }
    }

    public function closeShift(int $actor, int $shiftId, string $counted): array
    {
        $this->requireRole($actor, ['admin', 'cashier']);
        $count = $this->amount($counted, 2, 'Uang hitung');
        if ($count->isNegative()) {
            $this->invalid('Uang hitung tidak boleh negatif.');
        }

        return DB::transaction(function () use ($actor, $shiftId, $count) {
            $shift = DB::table('v2_shifts')->where('id', $shiftId)->lockForUpdate()->first();
            if (!$shift) {
                $this->invalid('Shift tidak ditemukan.');
            }
            if ($shift->status !== 'open') {
                $this->invalid('Shift tidak dalam status terbuka.');
            }
            if ($this->role($actor) !== 'admin' && (int) $shift->user_id !== $actor) {
                abort(403);
            }

            $in = Money::value('0');
            $out = Money::value('0');
            foreach (DB::table('v2_shift_movements')->where('shift_id', $shiftId)->get(['direction', 'amount']) as $movement) {
                if ($movement->direction === 'in') { $in = $in->plus((string) $movement->amount); }
                else { $out = $out->plus((string) $movement->amount); }
            }

            $expected = Money::value((string) $shift->opening_cash, 2)
                ->plus((string) $in)->minus((string) $out)->toScale(2, RoundingMode::HalfUp);
            $difference = $count->minus($expected)->toScale(2, RoundingMode::HalfUp);
            $status = $difference->isZero() ? 'closed' : 'review';

            DB::table('v2_shifts')->where('id', $shiftId)->update([
                'expected_cash' => $this->str($expected),
                'counted_cash' => $this->str($count),
                'difference' => $this->str($difference),
                'status' => $status,
                'open_user_id' => null,
                'closed_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'expected' => $this->str($expected),
                'counted' => $this->str($count),
                'difference' => $this->str($difference),
                'status' => $status,
            ];
        });
    }

    public function resolveShift(int $actor, int $shiftId, string $reason): void
    {
        $this->requireRole($actor, ['admin']);
        $note = trim($reason);
        $length = mb_strlen($note);
        if ($length < 5 || $length > 1000) {
            $this->invalid('Alasan resolusi 5 sampai 1000 karakter.');
        }

        DB::transaction(function () use ($actor, $shiftId, $note) {
            $shift = DB::table('v2_shifts')->where('id', $shiftId)->lockForUpdate()->first();
            if (!$shift) {
                $this->invalid('Shift tidak ditemukan.');
            }
            if ($shift->status !== 'review') {
                if ($shift->status === 'closed' && (int) $shift->reviewed_by === $actor && $shift->review_note === $note) {
                    return;
                }
                $this->invalid('Hanya shift berstatus review yang dapat diresolusi.');
            }

            DB::table('v2_shifts')->where('id', $shiftId)->update([
                'status' => 'closed',
                'reviewed_by' => $actor,
                'reviewed_at' => now(),
                'review_note' => $note,
                'updated_at' => now(),
            ]);
        });
    }

    public function rejectPayment(int $actor, int $paymentId, string $reason): void
    {
        $this->requireRole($actor, ['admin']);
        $note = trim($reason);
        $length = mb_strlen($note);
        if ($length < 5 || $length > 1000) {
            $this->invalid('Alasan penolakan 5 sampai 1000 karakter.');
        }

        DB::transaction(function () use ($actor, $paymentId, $note) {
            $payment = DB::table('v2_payments')->where('id', $paymentId)->lockForUpdate()->first();
            if (!$payment) {
                $this->invalid('Pembayaran tidak ditemukan.');
            }
            if ($payment->status === 'rejected') {
                if ((int) $payment->resolved_by === $actor && $payment->resolution_note === $note) {
                    return;
                }
                $this->invalid('Pembayaran sudah ditolak dengan catatan berbeda.');
            }
            if (!in_array($payment->status, ['pending', 'proposed'], true)) {
                $this->invalid('Hanya pembayaran pending atau proposed yang dapat ditolak.');
            }

            DB::table('v2_payments')->where('id', $paymentId)->update([
                'status' => 'rejected',
                'resolution_note' => $note,
                'resolved_by' => $actor,
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function checkout(int $actor, array $data): array
    {
        $this->requireRole($actor, ['admin', 'cashier']);

        $key = trim((string) ($data['idempotency_key'] ?? ''));
        if ($key === '') {
            $this->invalid('Kunci idempotensi wajib diisi.');
        }

        $method = (string) ($data['payment_method'] ?? '');
        if (!in_array($method, ['cash', 'transfer', 'credit'], true)) {
            $this->invalid('Metode pembayaran tidak valid.');
        }

        $fulfillment = (string) ($data['fulfillment'] ?? 'pickup');
        if (!in_array($fulfillment, ['pickup', 'delivery'], true)) {
            $this->invalid('Pemenuhan pesanan tidak valid.');
        }
        $destination = trim((string) ($data['destination'] ?? ''));
        if ($fulfillment === 'delivery' && $destination === '') {
            $this->invalid('Tujuan pengiriman wajib untuk delivery.');
        }

        $items = $data['items'] ?? null;
        if (!is_array($items) || $items === []) {
            $this->invalid('Item penjualan wajib diisi.');
        }

        $deliveryFee = $this->amount($data['delivery_fee'] ?? 0, 2, 'Ongkir');
        if ($deliveryFee->isNegative()) {
            $this->invalid('Ongkir tidak boleh negatif.');
        }

        $customerId = $this->customer(isset($data['customer_id']) && $data['customer_id'] !== '' ? (int) $data['customer_id'] : null);
        if (in_array($method, ['transfer', 'credit'], true) && $customerId === null) {
            $this->invalid('Pelanggan wajib untuk transaksi transfer atau kredit.');
        }

        $vehicleId = isset($data['vehicle_id']) && $data['vehicle_id'] !== '' ? (int) $data['vehicle_id'] : null;
        $driverName = isset($data['driver_name']) && trim((string) $data['driver_name']) !== '' ? trim((string) $data['driver_name']) : null;

        return DB::transaction(function () use ($actor, $data, $key, $method, $fulfillment, $destination, $items, $deliveryFee, $customerId, $vehicleId, $driverName) {
            $shift = $this->openShiftFor($actor, true);
            $requestHash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $existing = DB::table('v2_sales')->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_unless((int) $existing->user_id === $actor, 403);
                if ($existing->request_hash !== $requestHash) { $this->invalid('Kunci transaksi sudah dipakai untuk isi berbeda.'); }
                return $this->replay((int) $existing->id);
            }

            if ($vehicleId !== null && !DB::table('vehicles')->where('id', $vehicleId)->exists()) {
                $this->invalid('Kendaraan tidak ditemukan.');
            }

            $ids = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    $this->invalid('Format item tidak valid.');
                }
                $pid = (int) ($item['product_id'] ?? 0);
                if ($pid <= 0) {
                    $this->invalid('Produk item tidak valid.');
                }
                if (in_array($pid, $ids, true)) {
                    $this->invalid('Produk duplikat dalam satu penjualan.');
                }
                $ids[] = $pid;
            }
            sort($ids);

            $products = DB::table('products')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($products->count() !== count($ids)) {
                $this->invalid('Produk tidak ditemukan.');
            }

            $rows = [];
            $subtotal = Money::value('0');
            $lineDiscountTotal = Money::value('0');
            $goodsBase = Money::value('0');
            $cogs = Money::value('0');

            foreach ($items as $item) {
                $product = $products[(int) $item['product_id']];
                if (!(bool) $product->is_active) { $this->invalid('Produk sudah diarsipkan.'); }
                $qty = $this->amount($item['quantity'] ?? null, 4, 'Kuantitas');
                if (!$qty->isPositive()) {
                    $this->invalid('Kuantitas harus lebih dari nol.');
                }
                $stock = Money::value((string) $product->stock_quantity, 4);
                if ($qty->isGreaterThan($stock)) {
                    $this->invalid('Stok '.$product->name.' tidak mencukupi.');
                }

                $price = Money::value((string) $product->selling_price, 2)->toScale(2, RoundingMode::HalfUp);
                $cost = Money::value((string) $product->cost_price, 4);
                $gross = $qty->multipliedBy($price)->toScale(2, RoundingMode::HalfUp);
                $discount = Money::value(Money::discount((string) $gross, $this->discount($item['discount'] ?? null)), 2);
                $net = $gross->minus($discount);
                if ($net->isNegative()) {
                    $this->invalid('Diskon item melebihi harga barang.');
                }

                $rows[] = [
                    'product_id' => (int) $product->id,
                    'name' => $product->name,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'cost_price' => $cost,
                    'discount' => $discount,
                    'subtotal' => $net,
                    'stock' => $stock,
                ];

                $subtotal = $subtotal->plus($gross);
                $lineDiscountTotal = $lineDiscountTotal->plus($discount);
                $goodsBase = $goodsBase->plus($net);
                $cogs = $cogs->plus($qty->multipliedBy($cost)->toScale(2, RoundingMode::HalfUp));
            }

            $invoiceDiscount = Money::value(Money::discount(
                $this->str($goodsBase),
                $this->discount($data['discount'] ?? null)
            ), 2);

            $total = $goodsBase->minus($invoiceDiscount)->plus($deliveryFee)->toScale(2, RoundingMode::HalfUp);
            if (!$total->isPositive()) {
                $this->invalid('Total penjualan harus lebih dari nol.');
            }

            $paid = Money::value('0');
            $change = Money::value('0');
            $tendered = Money::value('0');
            $depositMethod = null;

            if ($method === 'cash') {
                if (!array_key_exists('paid_amount', $data)) {
                    $this->invalid('Uang tunai diterima wajib diisi.');
                }
                $tendered = $this->amount($data['paid_amount'], 2, 'Uang diterima');
                if ($tendered->isLessThan($total)) {
                    $this->invalid('Uang tunai kurang dari total.');
                }
                $paid = $total;
                $change = $tendered->minus($total);
            } else {
                $tendered = $this->amount($data['paid_amount'] ?? 0, 2, 'Nominal dibayar');
                if ($tendered->isNegative()) {
                    $this->invalid('Nominal dibayar tidak boleh negatif.');
                }
                if ($method === 'transfer') {
                    if (!$tendered->isZero() && !$tendered->isEqualTo($total)) {
                        $this->invalid('Transfer harus nol atau tepat sebesar total.');
                    }
                    $paid = Money::value('0');
                } else {
                    if ($tendered->isGreaterThan($total)) {
                        $this->invalid('Uang muka melebihi total.');
                    }
                    if ($tendered->isPositive()) {
                        $depositMethod = (string) ($data['deposit_method'] ?? '');
                        if (!in_array($depositMethod, ['cash', 'transfer'], true)) {
                            $this->invalid('Metode uang muka wajib cash atau transfer.');
                        }
                        $paid = $depositMethod === 'cash' ? $tendered : Money::value('0');
                    } else {
                        $paid = Money::value('0');
                    }
                }
            }

            $status = $this->saleStatus($paid, $total);

            $saleId = DB::table('v2_sales')->insertGetId([
                'number' => 'TMP-'.bin2hex(random_bytes(8)),
                'user_id' => $actor,
                'shift_id' => (int) $shift->id,
                'customer_id' => $customerId,
                'subtotal' => $this->str($subtotal),
                'line_discount' => $this->str($lineDiscountTotal),
                'invoice_discount' => $this->str($invoiceDiscount),
                'delivery_fee' => $this->str($deliveryFee),
                'total' => $this->str($total),
                'cogs' => $this->str($cogs),
                'tendered' => $this->str($tendered),
                'change_amount' => $this->str($change),
                'request_hash' => $requestHash,
                'payment_method' => $method,
                'status' => $status,
                'fulfillment' => $fulfillment,
                'idempotency_key' => $key,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $number = 'INV-'.now()->format('Ymd').'-'.str_pad((string) $saleId, 5, '0', STR_PAD_LEFT);
            DB::table('v2_sales')->where('id', $saleId)->update(['number' => $number, 'updated_at' => now()]);

            foreach ($rows as $row) {
                DB::table('v2_sale_items')->insert([
                    'sale_id' => $saleId,
                    'product_id' => $row['product_id'],
                    'name' => $row['name'],
                    'quantity' => $this->str($row['quantity'], 4),
                    'unit_price' => $this->str($row['unit_price']),
                    'cost_price' => $this->str($row['cost_price'], 4),
                    'discount' => $this->str($row['discount']),
                    'subtotal' => $this->str($row['subtotal']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $balance = $row['stock']->minus($row['quantity'])->toScale(4, RoundingMode::HalfUp);
                DB::table('products')->where('id', $row['product_id'])->update([
                    'stock_quantity' => $this->str($balance, 4),
                    'updated_at' => now(),
                ]);
                DB::table('stock_movements')->insert([
                    'product_id' => $row['product_id'],
                    'reference_type' => 'v2_sale',
                    'reference_id' => $saleId,
                    'quantity_delta' => $this->str($row['quantity']->negated(), 4),
                    'balance_after' => $this->str($balance, 4),
                    'notes' => 'Penjualan '.$number,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $invoiceId = DB::table('v2_invoices')->insertGetId([
                'direction' => 'receivable',
                'party_id' => $customerId,
                'sale_id' => $saleId,
                'number' => 'TMP-'.bin2hex(random_bytes(8)),
                'amount' => $this->str($total),
                'paid' => $this->str($paid),
                'due_date' => null,
                'status' => $paid->isGreaterThanOrEqualTo($total) ? 'paid' : 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $invoiceNumber = 'AR-'.now()->format('Ymd').'-'.str_pad((string) $invoiceId, 5, '0', STR_PAD_LEFT);
            DB::table('v2_invoices')->where('id', $invoiceId)->update(['number' => $invoiceNumber, 'updated_at' => now()]);

            $goodsNet = $goodsBase->minus($invoiceDiscount)->toScale(2, RoundingMode::HalfUp);
            $saleLines = [
                ['account_code' => self::AR, 'debit' => $this->str($total), 'credit' => '0.00'],
            ];
            if ($goodsNet->isPositive()) {
                $saleLines[] = ['account_code' => self::GOODS_REVENUE, 'debit' => '0.00', 'credit' => $this->str($goodsNet)];
            }
            if ($deliveryFee->isPositive()) {
                $saleLines[] = ['account_code' => self::DELIVERY_REVENUE, 'debit' => '0.00', 'credit' => $this->str($deliveryFee)];
            }
            if ($cogs->isPositive()) {
                $saleLines[] = ['account_code' => self::COGS, 'debit' => $this->str($cogs), 'credit' => '0.00'];
                $saleLines[] = ['account_code' => self::INVENTORY, 'debit' => '0.00', 'credit' => $this->str($cogs)];
            }
            Ledger::post('v2_sale', $saleId, 'Penjualan '.$number, $saleLines);

            if ($paid->isPositive()) {
                $paymentId = DB::table('v2_payments')->insertGetId([
                    'invoice_id' => $invoiceId,
                    'shift_id' => (int) $shift->id,
                    'amount' => $this->str($paid),
                    'method' => 'cash',
                    'status' => 'posted',
                    'requested_by' => $actor,
                    'approved_by' => $actor,
                    'reference' => null,
                    'idempotency_key' => $key.':payment',
                    'request_hash' => $requestHash,
                    'journal_entry_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $entryId = Ledger::post('v2_payment', $paymentId, 'Penerimaan tunai '.$number, [
                    ['account_code' => self::CASH, 'debit' => $this->str($paid), 'credit' => '0.00'],
                    ['account_code' => self::AR, 'debit' => '0.00', 'credit' => $this->str($paid)],
                ]);
                DB::table('v2_payments')->where('id', $paymentId)->update(['journal_entry_id' => $entryId, 'updated_at' => now()]);

                DB::table('v2_shift_movements')->insert([
                    'shift_id' => (int) $shift->id,
                    'direction' => 'in',
                    'kind' => $method === 'cash' ? 'sale' : 'collection',
                    'amount' => $this->str($paid),
                    'reference_type' => $method === 'cash' ? 'v2_sale' : 'v2_payment',
                    'reference_id' => $method === 'cash' ? $saleId : $paymentId,
                    'note' => 'Penjualan '.$number,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif ($tendered->isPositive() && ($method === 'transfer' || $depositMethod === 'transfer')) {
                DB::table('v2_payments')->insert([
                    'invoice_id' => $invoiceId,
                    'shift_id' => null,
                    'amount' => $this->str($tendered),
                    'method' => 'transfer',
                    'status' => 'pending',
                    'requested_by' => $actor,
                    'approved_by' => null,
                    'reference' => null,
                    'idempotency_key' => $key.':payment',
                    'request_hash' => $requestHash,
                    'journal_entry_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $deliveryNumber = null;
            if ($fulfillment === 'delivery') {
                $deliveryId = DB::table('v2_deliveries')->insertGetId([
                    'sale_id' => $saleId,
                    'number' => 'TMP-'.bin2hex(random_bytes(8)),
                    'vehicle_id' => $vehicleId,
                    'driver_name' => $driverName,
                    'destination' => $destination,
                    'status' => 'prepared',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $deliveryNumber = 'SJ-'.now()->format('Ymd').'-'.str_pad((string) $deliveryId, 5, '0', STR_PAD_LEFT);
                DB::table('v2_deliveries')->where('id', $deliveryId)->update(['number' => $deliveryNumber, 'updated_at' => now()]);
            }

            return [
                'sale_id' => $saleId,
                'number' => $number,
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'total' => $this->str($total),
                'paid' => $this->str($paid),
                'change' => $this->str($change),
                'status' => $status,
                'delivery_number' => $deliveryNumber,
            ];
        });
    }

    public function collect(int $actor, int $invoiceId, string $amount, string $method, string $key, ?string $reference = null): int
    {
        $this->requireRole($actor, ['admin', 'cashier']);
        if (!in_array($method, ['cash', 'transfer'], true)) {
            $this->invalid('Metode pembayaran tidak valid.');
        }
        $key = trim($key);
        if ($key === '') {
            $this->invalid('Kunci idempotensi wajib diisi.');
        }
        $value = $this->amount($amount, 2, 'Nominal');
        if (!$value->isPositive()) {
            $this->invalid('Nominal harus lebih dari nol.');
        }

        return DB::transaction(function () use ($actor, $invoiceId, $value, $method, $key, $reference) {
            $invoice = DB::table('v2_invoices')->where('id', $invoiceId)->lockForUpdate()->first();
            if (!$invoice) {
                $this->invalid('Tagihan tidak ditemukan.');
            }
            $requestHash = hash('sha256', json_encode([$invoiceId, $this->str($value), $method, $reference], JSON_THROW_ON_ERROR));
            $existing = DB::table('v2_payments')->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_unless((int) $existing->requested_by === $actor, 403);
                if ($existing->request_hash !== $requestHash) { $this->invalid('Kunci pembayaran sudah dipakai untuk isi berbeda.'); }
                return (int) $existing->id;
            }
            if (!in_array($invoice->direction, ['receivable', 'payable'], true)) {
                $this->invalid('Arah tagihan tidak valid.');
            }

            $remaining = Money::value((string) $invoice->amount, 2)->minus(Money::value((string) $invoice->paid, 2));
            if (!$remaining->isPositive()) {
                $this->invalid('Tagihan sudah lunas.');
            }
            if ($value->isGreaterThan($remaining)) {
                $this->invalid('Nominal melebihi sisa tagihan.');
            }

            if ($invoice->direction === 'payable') {
                $shiftId = $this->currentShiftId($actor);
                if ($method === 'cash' && $shiftId === null) {
                    $this->invalid('Shift kasir wajib terbuka untuk pengajuan tunai ke supplier.');
                }

                return DB::table('v2_payments')->insertGetId([
                    'invoice_id' => $invoiceId,
                    'shift_id' => $shiftId,
                    'amount' => $this->str($value),
                    'method' => $method,
                    'status' => 'proposed',
                    'requested_by' => $actor,
                    'approved_by' => null,
                    'reference' => $reference,
                    'idempotency_key' => $key,
                    'request_hash' => $requestHash,
                    'journal_entry_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($method === 'transfer') {
                return DB::table('v2_payments')->insertGetId([
                    'invoice_id' => $invoiceId,
                    'shift_id' => null,
                    'amount' => $this->str($value),
                    'method' => 'transfer',
                    'status' => 'pending',
                    'requested_by' => $actor,
                    'approved_by' => null,
                    'reference' => $reference,
                    'idempotency_key' => $key,
                    'request_hash' => $requestHash,
                    'journal_entry_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $shift = $this->openShiftFor($actor, true);
            $paymentId = DB::table('v2_payments')->insertGetId([
                'invoice_id' => $invoiceId,
                'shift_id' => (int) $shift->id,
                'amount' => $this->str($value),
                'method' => 'cash',
                'status' => 'posted',
                'requested_by' => $actor,
                'approved_by' => $actor,
                'reference' => $reference,
                'idempotency_key' => $key,
                'request_hash' => $requestHash,
                'journal_entry_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $entryId = Ledger::post('v2_payment', $paymentId, 'Penerimaan '.$invoice->number, [
                ['account_code' => self::CASH, 'debit' => $this->str($value), 'credit' => '0.00'],
                ['account_code' => self::AR, 'debit' => '0.00', 'credit' => $this->str($value)],
            ]);
            DB::table('v2_payments')->where('id', $paymentId)->update(['journal_entry_id' => $entryId, 'updated_at' => now()]);

            DB::table('v2_shift_movements')->insert([
                'shift_id' => (int) $shift->id,
                'direction' => 'in',
                'kind' => 'collection',
                'amount' => $this->str($value),
                'reference_type' => 'v2_payment',
                'reference_id' => $paymentId,
                'note' => 'Penerimaan '.$invoice->number,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('v2_invoices')->where('id', $invoiceId)->update([
                'paid' => $this->str(Money::value((string) $invoice->paid, 2)->plus($value)),
                'status' => $value->isEqualTo($remaining) ? 'paid' : 'open',
                'updated_at' => now(),
            ]);

            if ($invoice->sale_id !== null) {
                DB::table('v2_sales')->where('id', $invoice->sale_id)->update(['status' => $this->saleStatus(Money::value((string) $invoice->paid)->plus($value), Money::value((string) $invoice->amount)), 'updated_at' => now()]);
            }

            return $paymentId;
        });
    }

    public function approvePayment(int $actor, int $paymentId): void
    {
        $this->requireRole($actor, ['admin']);

        DB::transaction(function () use ($actor, $paymentId) {
            $head = DB::table('v2_payments')->where('id', $paymentId)->first();
            if (!$head) {
                $this->invalid('Pembayaran tidak ditemukan.');
            }

            $invoice = DB::table('v2_invoices')->where('id', $head->invoice_id)->lockForUpdate()->first();
            if (!$invoice) {
                $this->invalid('Tagihan pembayaran tidak ditemukan.');
            }
            $payment = DB::table('v2_payments')->where('id', $paymentId)->lockForUpdate()->first();

            if ($payment->status === 'posted') {
                return;
            }
            if (!in_array($payment->status, ['pending', 'proposed'], true)) {
                $this->invalid('Status pembayaran tidak dapat disetujui.');
            }
            if ($invoice->direction === 'payable' && (int) $payment->requested_by === $actor) {
                abort(403);
            }

            $amount = Money::value((string) $payment->amount, 2);
            if (!$amount->isPositive()) {
                $this->invalid('Nominal pembayaran tidak valid.');
            }
            $remaining = Money::value((string) $invoice->amount, 2)->minus(Money::value((string) $invoice->paid, 2));
            if ($amount->isGreaterThan($remaining)) {
                $this->invalid('Nominal melebihi sisa tagihan.');
            }

            $entryId = null;
            if ($invoice->direction === 'receivable') {
                if ($payment->method !== 'transfer') {
                    $this->invalid('Hanya transfer yang menunggu verifikasi bank.');
                }
                $entryId = Ledger::post('v2_payment', $paymentId, 'Verifikasi transfer '.$invoice->number, [
                    ['account_code' => self::BANK, 'debit' => $this->str($amount), 'credit' => '0.00'],
                    ['account_code' => self::AR, 'debit' => '0.00', 'credit' => $this->str($amount)],
                ]);
            } elseif ($invoice->direction === 'payable') {
                $cash = $payment->method === 'cash';
                $account = $cash ? self::CASH : self::BANK;
                if ($cash) {
                    $drawer = DB::table('v2_shifts')->where('id', $payment->shift_id)->lockForUpdate()->first();
                    if (!$drawer || $drawer->status !== 'open' || (int) $drawer->user_id !== (int) $payment->requested_by) {
                        $this->invalid('Shift pengajuan tidak terbuka; ajukan ulang.');
                    }
                    $drawerBalance = Money::value((string) $drawer->opening_cash);
                    foreach (DB::table('v2_shift_movements')->where('shift_id', $drawer->id)->get(['direction', 'amount']) as $movement) {
                        $drawerBalance = $movement->direction === 'in' ? $drawerBalance->plus((string) $movement->amount) : $drawerBalance->minus((string) $movement->amount);
                    }
                    if ($drawerBalance->isLessThan($amount)) { $this->invalid('Uang tunai laci tidak cukup untuk pembayaran.'); }
                }
                $this->guardAccount($account, $amount->negated());
                $entryId = Ledger::post('v2_payment', $paymentId, 'Pembayaran utang '.$invoice->number, [
                    ['account_code' => self::AP, 'debit' => $this->str($amount), 'credit' => '0.00'],
                    ['account_code' => $account, 'debit' => '0.00', 'credit' => $this->str($amount)],
                ]);

                if ($cash) {
                    if ($payment->shift_id === null) {
                        $this->invalid('Pengajuan tunai tanpa shift; ajukan ulang.');
                    }
                    $shift = DB::table('v2_shifts')->where('id', $payment->shift_id)->lockForUpdate()->first();
                    if (!$shift || $shift->status !== 'open') {
                        $this->invalid('Shift pengajuan sudah ditutup; ajukan ulang.');
                    }
                    if ((int) $shift->user_id !== (int) $payment->requested_by) {
                        $this->invalid('Shift pengajuan bukan milik pemohon.');
                    }
                    DB::table('v2_shift_movements')->insert([
                        'shift_id' => (int) $shift->id,
                        'direction' => 'out',
                        'kind' => 'supplier',
                        'amount' => $this->str($amount),
                        'reference_type' => 'v2_payment',
                        'reference_id' => $paymentId,
                        'note' => 'Pembayaran utang '.$invoice->number,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } else {
                $this->invalid('Arah tagihan tidak valid.');
            }

            DB::table('v2_payments')->where('id', $paymentId)->update([
                'status' => 'posted',
                'approved_by' => $actor,
                'journal_entry_id' => $entryId,
                'updated_at' => now(),
            ]);
            DB::table('v2_invoices')->where('id', $invoice->id)->update([
                'paid' => $this->str(Money::value((string) $invoice->paid, 2)->plus($amount)),
                'status' => $amount->isEqualTo($remaining) ? 'paid' : 'open',
                'updated_at' => now(),
            ]);

            if ($invoice->sale_id !== null) {
                DB::table('v2_sales')->where('id', $invoice->sale_id)->update(['status' => $this->saleStatus(Money::value((string) $invoice->paid)->plus($amount), Money::value((string) $invoice->amount)), 'updated_at' => now()]);
            }
        });
    }

    private function guardAccount(string $code, BigDecimal $delta): void
    {
        if (!DB::table('chart_of_accounts')->where('account_code', $code)->lockForUpdate()->exists()) {
            $this->invalid('Akun '.$code.' belum tersedia di bagan akun.');
        }
        $balance = Money::value('0');
        foreach (DB::table('journal_entry_lines')->where('account_code', $code)->get(['debit', 'credit']) as $line) {
            $balance = $balance->plus((string) $line->debit)->minus((string) $line->credit);
        }
        if ($balance->plus($delta)->isNegative()) {
            $this->invalid('Saldo akun '.$code.' tidak cukup untuk pembayaran keluar.');
        }
    }

    private function replay(int $saleId): array
    {
        $sale = DB::table('v2_sales')->where('id', $saleId)->first();
        if (!$sale) {
            $this->invalid('Penjualan idempoten tidak ditemukan.');
        }
        $invoice = DB::table('v2_invoices')->where('sale_id', $saleId)->first();

        return [
            'sale_id' => $saleId,
            'number' => $sale->number,
            'invoice_id' => $invoice ? (int) $invoice->id : null,
            'invoice_number' => $invoice->number ?? null,
            'total' => $this->str(Money::value((string) $sale->total)),
            'paid' => $invoice ? $this->str(Money::value((string) $invoice->paid)) : '0.00',
            'change' => $this->str(Money::value((string) $sale->change_amount)),
            'status' => $sale->status,
            'delivery_number' => DB::table('v2_deliveries')->where('sale_id', $saleId)->value('number'),
            'replayed' => true,
        ];
    }

    private function openShiftFor(int $actor, bool $lock): object
    {
        $query = DB::table('v2_shifts')->where('user_id', $actor)->where('status', 'open');
        if ($lock) {
            $query->lockForUpdate();
        }
        $shift = $query->first();
        if (!$shift) {
            $this->invalid('Shift kasir belum dibuka.');
        }

        return $shift;
    }

    private function currentShiftId(int $actor): ?int
    {
        $id = DB::table('v2_shifts')->where('user_id', $actor)->where('status', 'open')->value('id');

        return $id === null ? null : (int) $id;
    }

    private function customer(?int $id): ?int
    {
        if ($id === null) {
            return null;
        }
        $party = DB::table('v2_parties')->where('id', $id)->first();
        if (!$party || $party->kind !== 'customer' || !$party->is_active) {
            $this->invalid('Pelanggan tidak valid.');
        }

        return (int) $party->id;
    }

    private function saleStatus(BigDecimal $paid, BigDecimal $total): string
    {
        if ($paid->isZero()) {
            return 'unpaid';
        }

        return $paid->isGreaterThanOrEqualTo($total) ? 'paid' : 'partial';
    }

    private function discount(mixed $discount): array
    {
        if ($discount === null || $discount === []) {
            return [];
        }
        if (!is_array($discount)) {
            $this->invalid('Diskon harus berupa objek.');
        }
        $type = $discount['type'] ?? null;
        $value = $discount['value'] ?? null;
        if (!in_array($type, ['amount', 'percent'], true)) {
            $this->invalid('Tipe diskon tidak valid.');
        }
        if (!is_int($value) && !(is_string($value) && trim($value) !== '')) {
            $this->invalid('Nilai diskon tidak valid.');
        }

        return ['type' => $type, 'value' => is_string($value) ? trim($value) : (string) $value];
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
            if ($scale === 4 && BigDecimal::of($raw)->strippedOfTrailingZeros()->getScale() > 4) {
                $this->invalid($field.' maksimal empat desimal.');
            }
            return Money::value($raw, $scale)->toScale($scale, RoundingMode::HalfUp);
        } catch (\Throwable $e) {
            $this->invalid($field.' tidak valid.');
        }
    }

    private function str(BigDecimal $value, int $scale = 2): string
    {
        return (string) $value->toScale($scale, RoundingMode::HalfUp);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['operations' => $message]);
    }
}
