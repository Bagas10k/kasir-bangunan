<?php

namespace App\Services\V2;

use App\Support\SafeLog;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Retur penjualan operasional V2: mengembalikan barang terjual ke stok (menyesuaikan HPP tertimbang),
 * mengurangi piutang pelanggan atau mencatat pengembalian dana tunai/bank, dan memposting jurnal seimbang.
 */
final class SalesReturns
{
    private const CASH = '1101';

    private const AR = '1103';

    private const INVENTORY = '1301';

    private const SALES_REVENUE = '4101';

    private const SALES_RETURN_CONTRA = '4104';

    private const COGS = '5101';

    public function __construct(
        private readonly Operations $operations,
        private readonly BankAccounts $bankAccounts,
    ) {}

    /** @return array<string, mixed> */
    public function summary(int $actor, int $saleId): array
    {
        $this->operations->requireRole($actor, ['admin', 'cashier']);
        $sale = DB::table('v2_sales')->where('id', $saleId)->first();
        abort_unless($sale, 404);

        $invoice = DB::table('v2_invoices')->where('sale_id', $saleId)->first();
        $hasPending = $invoice && DB::table('v2_payments')
            ->where('invoice_id', $invoice->id)
            ->whereIn('status', ['pending', 'proposed'])
            ->exists();

        $saleItems = DB::table('v2_sale_items')
            ->join('products', 'products.id', '=', 'v2_sale_items.product_id')
            ->where('v2_sale_items.sale_id', $saleId)
            ->orderBy('v2_sale_items.id')
            ->get(['v2_sale_items.*', 'products.unit as product_unit']);

        $returnedByItem = $this->returnedByItem($saleItems->pluck('id')->all());
        $effectiveTotals = $this->allocateEffectiveItemTotals($saleItems, Money::value((string) $sale->invoice_discount, 2));

        $items = [];
        foreach ($saleItems as $item) {
            $sold = Money::value((string) $item->quantity, 4);
            $prev = $returnedByItem[(int) $item->id] ?? ['qty' => Money::value('0', 4), 'subtotal' => Money::value('0', 2)];
            $remainingQty = $sold->minus($prev['qty'])->toScale(4, RoundingMode::HalfUp);
            $effectiveTotal = $effectiveTotals[(int) $item->id];
            $remainingSubtotal = $effectiveTotal->minus($prev['subtotal'])->toScale(2, RoundingMode::HalfUp);
            $items[] = [
                'sale_item_id' => (int) $item->id,
                'product_id' => (int) $item->product_id,
                'name' => (string) $item->name,
                'unit' => (string) $item->product_unit,
                'sold_qty' => (string) $sold,
                'returned_qty' => (string) $prev['qty'],
                'remaining_qty' => (string) $remainingQty,
                'effective_unit_net' => $sold->isPositive() ? (string) $effectiveTotal->dividedBy($sold, 2, RoundingMode::HalfUp) : '0.00',
                'remaining_subtotal' => (string) $remainingSubtotal,
                'cost_price' => (string) Money::value((string) $item->cost_price, 4),
            ];
        }

        $returns = DB::table('v2_sale_returns as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.created_by')
            ->where('r.sale_id', $saleId)
            ->orderByDesc('r.id')
            ->get(['r.*', 'u.name as author_name']);

        return [
            'sale_id' => (int) $sale->id,
            'sale_number' => (string) $sale->number,
            'has_pending_payment' => $hasPending,
            'invoice_remaining' => $invoice
                ? (string) Money::value((string) $invoice->amount, 2)->minus(Money::value((string) $invoice->paid, 2))
                : '0.00',
            'invoice_paid' => $invoice ? (string) Money::value((string) $invoice->paid, 2) : '0.00',
            'items' => $items,
            'returns' => $returns,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function store(int $actor, int $saleId, array $data): array
    {
        $this->operations->requireRole($actor, ['admin']);

        $returnDate = $this->date($data['return_date'] ?? now()->toDateString());
        if ($returnDate > now()->toDateString()) {
            $this->invalid('Tanggal retur tidak boleh melewati hari ini.');
        }

        $reason = $this->reason($data['reason'] ?? null);
        $key = $this->key($data['idempotency_key'] ?? null);

        $requestedMethod = isset($data['refund_method']) && trim((string) $data['refund_method']) !== ''
            ? trim((string) $data['refund_method'])
            : null;
        if ($requestedMethod !== null && ! in_array($requestedMethod, ['cash', 'main', 'transfer'], true)) {
            $this->invalid('Metode pengembalian dana tidak valid (tunai laci, Kas Utama, atau transfer bank).');
        }
        $requestedBank = isset($data['bank_account_code']) && trim((string) $data['bank_account_code']) !== ''
            ? trim((string) $data['bank_account_code'])
            : null;

        $rawItems = $data['items'] ?? null;
        if (! is_array($rawItems) || count($rawItems) < 1 || count($rawItems) > 100) {
            $this->invalid('Pilih minimal 1 barang yang diretur.');
        }

        $parsedItems = [];
        foreach ($rawItems as $row) {
            if (! is_array($row)) {
                $this->invalid('Format item retur tidak valid.');
            }
            $saleItemId = isset($row['sale_item_id']) && $row['sale_item_id'] !== '' ? $this->id($row['sale_item_id'], 'Item penjualan') : null;
            $productId = isset($row['product_id']) && $row['product_id'] !== '' ? $this->id($row['product_id'], 'Produk') : null;
            if ($saleItemId === null && $productId === null) {
                $this->invalid('Item penjualan atau produk wajib dipilih.');
            }
            $qty = $this->decimal($row['quantity'] ?? null, 'Jumlah retur');
            if (! $qty->isPositive()) {
                $this->invalid('Jumlah retur harus lebih dari nol.');
            }
            $parsedItems[] = [
                'sale_item_id' => $saleItemId,
                'product_id' => $productId,
                'quantity' => $qty,
            ];
        }

        $hash = hash('sha256', json_encode([
            'sale_id' => $saleId,
            'return_date' => $returnDate,
            'reason' => $reason,
            'refund_method' => $requestedMethod,
            'bank_account_code' => $requestedBank,
            'items' => array_map(fn (array $row): array => [
                'sale_item_id' => $row['sale_item_id'],
                'product_id' => $row['product_id'],
                'quantity' => (string) $row['quantity'],
            ], $parsedItems),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $result = DB::transaction(function () use ($actor, $saleId, $returnDate, $reason, $key, $requestedMethod, $requestedBank, $parsedItems, $hash): array {
            DB::table('users')->where('id', $actor)->lockForUpdate()->first();

            $existing = DB::table('v2_sale_returns')->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                abort_unless((int) $existing->created_by === $actor, 403);
                if (! hash_equals((string) $existing->request_hash, $hash)) {
                    $this->invalid('Kunci transaksi retur penjualan sudah dipakai untuk isi berbeda.');
                }

                return $this->replay((int) $existing->id);
            }

            // Payment posting also locks the invoice before updating its sale.
            $invoice = DB::table('v2_invoices')->where('sale_id', $saleId)->lockForUpdate()->first();
            $sale = DB::table('v2_sales')->where('id', $saleId)->lockForUpdate()->first();
            if (! $sale) {
                abort(404);
            }
            if ($returnDate < substr((string) $sale->created_at, 0, 10)) {
                $this->invalid('Tanggal retur tidak boleh sebelum tanggal nota penjualan.');
            }

            if (! $invoice) {
                $this->invalid('Tagihan nota penjualan tidak ditemukan.');
            }
            if (DB::table('v2_payments')->where('invoice_id', $invoice->id)->whereIn('status', ['pending', 'proposed'])->lockForUpdate()->exists()) {
                $this->invalid('Nota masih memiliki pembayaran menunggu verifikasi; setujui atau tolak pembayaran tersebut sebelum memproses retur.');
            }

            // Checkout locks the shift before products; follow that order to avoid a lock cycle.
            $lockedShift = $requestedMethod === 'cash' ? $this->operations->activeStoreShift(true) : null;

            $saleItems = DB::table('v2_sale_items')
                ->where('sale_id', $saleId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($saleItems->isEmpty()) {
                $this->invalid('Nota tidak memiliki item barang.');
            }

            $bySaleItemId = $saleItems->keyBy('id');
            $byProductId = $saleItems->keyBy('product_id');

            $resolvedLines = [];
            foreach ($parsedItems as $entry) {
                $saleItem = $entry['sale_item_id'] !== null
                    ? $bySaleItemId->get($entry['sale_item_id'])
                    : $byProductId->get($entry['product_id']);
                if (! $saleItem) {
                    $this->invalid('Barang yang dipilih tidak termasuk dalam nota penjualan ini.');
                }
                if (isset($resolvedLines[(int) $saleItem->id])) {
                    $this->invalid('Barang yang sama tidak boleh diduplikasi dalam satu retur.');
                }
                $resolvedLines[(int) $saleItem->id] = [
                    'sale_item' => $saleItem,
                    'quantity' => $entry['quantity'],
                ];
            }
            ksort($resolvedLines);

            $productIds = array_values(array_unique(array_map(fn (array $r): int => (int) $r['sale_item']->product_id, $resolvedLines)));
            sort($productIds);
            $products = DB::table('products')->whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $returnedByItem = $this->returnedByItem(array_keys($resolvedLines), true);
            $effectiveTotals = $this->allocateEffectiveItemTotals($saleItems, Money::value((string) $sale->invoice_discount, 2));

            $totalReturn = BigDecimal::zero()->toScale(2);
            $totalCogsReturn = BigDecimal::zero()->toScale(2);
            $preparedItems = [];

            foreach ($resolvedLines as $saleItemId => $entry) {
                $item = $entry['sale_item'];
                /** @var BigDecimal $qty */
                $qty = $entry['quantity'];
                $soldQty = Money::value((string) $item->quantity, 4);
                $prev = $returnedByItem[$saleItemId] ?? ['qty' => Money::value('0', 4), 'subtotal' => Money::value('0', 2)];
                $remainingQty = $soldQty->minus($prev['qty'])->toScale(4, RoundingMode::HalfUp);

                if ($qty->isGreaterThan($remainingQty)) {
                    $this->invalid('Jumlah retur '.$item->name.' melebihi sisa yang dapat diretur ('.(string) $remainingQty.').');
                }

                $effectiveTotal = $effectiveTotals[$saleItemId];
                $lineSubtotal = Money::returnPortion($effectiveTotal, $soldQty, $prev['qty']->plus($qty), $prev['subtotal']);

                $itemCost = Money::value((string) $item->cost_price, 4);
                $originalLineCogs = $soldQty->multipliedBy($itemCost)->toScale(2, RoundingMode::HalfUp);
                $lineCogs = Money::portionAt($originalLineCogs, $soldQty, $prev['qty']->plus($qty))
                    ->minus(Money::portionAt($originalLineCogs, $soldQty, $prev['qty']))
                    ->toScale(2);
                if ($lineCogs->isNegative()) {
                    $lineCogs = Money::value('0', 2);
                }

                $totalReturn = $totalReturn->plus($lineSubtotal);
                $totalCogsReturn = $totalCogsReturn->plus($lineCogs);

                $preparedItems[] = [
                    'sale_item' => $item,
                    'product_id' => (int) $item->product_id,
                    'quantity' => $qty,
                    'unit_price' => Money::value((string) $item->unit_price, 2),
                    'cost_price' => $itemCost,
                    'subtotal' => $lineSubtotal,
                    'cogs' => $lineCogs,
                ];
            }

            if (! $totalReturn->isPositive()) {
                $this->invalid('Nilai retur kurang dari Rp 0,01. Jika ada barang lain yang dikembalikan, catat bersama dalam satu retur.');
            }

            $invAmount = Money::value((string) $invoice->amount, 2);
            $invPaid = Money::value((string) $invoice->paid, 2);
            $remainingAR = $invAmount->minus($invPaid)->toScale(2);
            if ($remainingAR->isNegative()) {
                $remainingAR = Money::value('0', 2);
            }

            $arReduction = $totalReturn->isLessThanOrEqualTo($remainingAR) ? $totalReturn : $remainingAR;
            $refundAmount = $totalReturn->minus($arReduction)->toScale(2);

            $effectiveMethod = 'credit_adjustment';
            $shiftId = null;
            $refundAccount = null;
            $resolvedBank = null;

            if ($refundAmount->isPositive()) {
                if ($requestedMethod === null) {
                    $this->invalid('Nota sudah dibayar sebagian/lunas; pilih sumber pengembalian dana (tunai laci, Kas Utama, atau transfer bank).');
                }
                $effectiveMethod = $requestedMethod;

                if ($effectiveMethod === 'cash') {
                    $shift = $lockedShift;
                    if (! $shift) {
                        $this->invalid('Kas harian belum dibuka admin; retur tunai dari laci membutuhkan shift aktif.');
                    }
                    $drawer = Money::value((string) $shift->opening_cash, 2);
                    foreach (DB::table('v2_shift_movements')->where('shift_id', $shift->id)->get(['direction', 'amount']) as $movement) {
                        $drawer = $movement->direction === 'in'
                            ? $drawer->plus((string) $movement->amount)
                            : $drawer->minus((string) $movement->amount);
                    }
                    if ($drawer->isLessThan($refundAmount)) {
                        $this->invalid('Uang tunai di laci tidak cukup untuk pengembalian retur.');
                    }
                    $shiftId = (int) $shift->id;
                    $refundAccount = self::CASH;
                } elseif ($effectiveMethod === 'main') {
                    $refundAccount = self::CASH;
                } else {
                    $resolvedBank = $this->bankAccounts->resolve($requestedBank);
                    $refundAccount = $resolvedBank;
                }

                $this->guardAccount($refundAccount, $refundAmount);
            }

            $now = now();
            $returnId = (int) DB::table('v2_sale_returns')->insertGetId([
                'number' => 'TMP-'.bin2hex(random_bytes(8)),
                'sale_id' => $saleId,
                'invoice_id' => (int) $invoice->id,
                'shift_id' => $shiftId,
                'return_date' => $returnDate,
                'total_amount' => (string) $totalReturn->toScale(2),
                'cogs_amount' => (string) $totalCogsReturn->toScale(2),
                'ar_reduction' => (string) $arReduction->toScale(2),
                'refund_amount' => (string) $refundAmount->toScale(2),
                'refund_method' => $effectiveMethod,
                'bank_account_code' => $resolvedBank,
                'reason' => $reason,
                'created_by' => $actor,
                'journal_entry_id' => null,
                'idempotency_key' => $key,
                'request_hash' => $hash,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $returnNumber = 'RET-'.str_replace('-', '', $returnDate).'-'.str_pad((string) $returnId, 5, '0', STR_PAD_LEFT);
            DB::table('v2_sale_returns')->where('id', $returnId)->update(['number' => $returnNumber, 'updated_at' => $now]);

            foreach ($preparedItems as $row) {
                DB::table('v2_sale_return_items')->insert([
                    'sale_return_id' => $returnId,
                    'sale_item_id' => (int) $row['sale_item']->id,
                    'product_id' => $row['product_id'],
                    'quantity' => (string) $row['quantity'],
                    'unit_price' => (string) $row['unit_price'],
                    'cost_price' => (string) $row['cost_price'],
                    'subtotal' => (string) $row['subtotal'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $product = $products[$row['product_id']];
                $oldStock = Money::value((string) $product->stock_quantity, 4);
                $oldCost = Money::value((string) $product->cost_price, 4);
                $newStock = $oldStock->plus($row['quantity'])->toScale(4, RoundingMode::HalfUp);
                $invValue = $oldStock->multipliedBy($oldCost)->plus($row['quantity']->multipliedBy($row['cost_price']));
                $newCost = $newStock->isPositive()
                    ? $invValue->dividedBy($newStock, 4, RoundingMode::HalfUp)
                    : $oldCost;

                DB::table('products')->where('id', $row['product_id'])->update([
                    'stock_quantity' => (string) $newStock,
                    'cost_price' => (string) $newCost,
                    'updated_at' => $now,
                ]);

                DB::table('stock_movements')->insert([
                    'product_id' => $row['product_id'],
                    'reference_type' => 'v2_sale_return',
                    'reference_id' => $returnId,
                    'quantity_delta' => (string) $row['quantity'],
                    'balance_after' => (string) $newStock,
                    'notes' => 'Retur penjualan '.$returnNumber.' ('.$sale->number.')',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $newInvAmount = $invAmount->minus($totalReturn)->toScale(2);
            if ($newInvAmount->isNegative()) {
                $newInvAmount = Money::value('0', 2);
            }
            $newInvPaid = $invPaid->minus($refundAmount)->toScale(2);
            if ($newInvPaid->isNegative()) {
                $newInvPaid = Money::value('0', 2);
            }
            $invoiceStatus = $newInvPaid->isGreaterThanOrEqualTo($newInvAmount) ? 'paid' : 'open';
            $saleStatus = $invoiceStatus === 'paid'
                ? 'paid'
                : ($newInvPaid->isPositive() ? 'partial' : 'unpaid');

            DB::table('v2_invoices')->where('id', $invoice->id)->update([
                'amount' => (string) $newInvAmount,
                'paid' => (string) $newInvPaid,
                'status' => $invoiceStatus,
                'updated_at' => $now,
            ]);
            DB::table('v2_sales')->where('id', $saleId)->update([
                'status' => $saleStatus,
                'updated_at' => $now,
            ]);

            if ($shiftId !== null && $refundAmount->isPositive()) {
                DB::table('v2_shift_movements')->insert([
                    'shift_id' => $shiftId,
                    'direction' => 'out',
                    'kind' => 'adjustment',
                    'amount' => (string) $refundAmount,
                    'reference_type' => 'v2_sale_return',
                    'reference_id' => $returnId,
                    'note' => 'Retur penjualan '.$returnNumber,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $returnRevenueAccount = DB::table('chart_of_accounts')
                ->where('account_code', self::SALES_RETURN_CONTRA)
                ->where('account_type', 'revenue')
                ->where('is_active', true)
                ->exists()
                ? self::SALES_RETURN_CONTRA
                : self::SALES_REVENUE;

            $lines = [
                ['account_code' => $returnRevenueAccount, 'debit' => (string) $totalReturn, 'credit' => '0.00'],
            ];
            if ($arReduction->isPositive()) {
                $lines[] = ['account_code' => self::AR, 'debit' => '0.00', 'credit' => (string) $arReduction];
            }
            if ($refundAmount->isPositive()) {
                $lines[] = ['account_code' => $refundAccount, 'debit' => '0.00', 'credit' => (string) $refundAmount];
            }
            if ($totalCogsReturn->isPositive()) {
                $lines[] = ['account_code' => self::INVENTORY, 'debit' => (string) $totalCogsReturn, 'credit' => '0.00'];
                $lines[] = ['account_code' => self::COGS, 'debit' => '0.00', 'credit' => (string) $totalCogsReturn];
            }

            $journalId = Ledger::post(
                'v2_sale_return',
                $returnId,
                'Retur penjualan '.$returnNumber.' ('.$sale->number.')',
                $lines,
                $returnDate
            );
            DB::table('v2_sale_returns')->where('id', $returnId)->update([
                'journal_entry_id' => $journalId,
                'updated_at' => $now,
            ]);

            return [
                'return_id' => $returnId,
                'number' => $returnNumber,
                'sale_id' => $saleId,
                'return_date' => $returnDate,
                'total_amount' => (string) $totalReturn,
                'ar_reduction' => (string) $arReduction,
                'refund_amount' => (string) $refundAmount,
                'refund_method' => $effectiveMethod,
                'journal_entry_id' => $journalId,
            ];
        });

        SafeLog::info('sale_return.posted', [
            'actor_id' => $actor,
            'return_id' => $result['return_id'],
            'sale_id' => $saleId,
            'total_amount' => $result['total_amount'],
            'refund_method' => $result['refund_method'],
        ]);

        return $result;
    }

    /**
     * @param  iterable<int, object>  $saleItems
     * @return array<int, BigDecimal>
     */
    private function allocateEffectiveItemTotals(iterable $saleItems, BigDecimal $invoiceDiscount): array
    {
        $items = collect($saleItems)->values();
        $out = [];
        $goodsBase = Money::value('0', 2);
        foreach ($items as $item) {
            $sub = Money::value((string) $item->subtotal, 2);
            $out[(int) $item->id] = $sub;
            $goodsBase = $goodsBase->plus($sub);
        }

        if (! $invoiceDiscount->isPositive() || ! $goodsBase->isPositive()) {
            return $out;
        }

        $allocated = Money::value('0', 2);
        $lastIndex = $items->count() - 1;
        foreach ($items as $idx => $item) {
            $id = (int) $item->id;
            $sub = $out[$id];
            if ($idx === $lastIndex) {
                $share = $invoiceDiscount->minus($allocated);
            } else {
                $share = $invoiceDiscount->multipliedBy($sub)->dividedBy($goodsBase, 2, RoundingMode::HalfUp);
                $allocated = $allocated->plus($share);
            }
            $net = $sub->minus($share)->toScale(2, RoundingMode::HalfUp);
            $out[$id] = $net->isNegative() ? Money::value('0', 2) : $net;
        }

        return $out;
    }

    /**
     * @param  array<int, int>  $saleItemIds
     * @return array<int, array{qty: BigDecimal, subtotal: BigDecimal}>
     */
    private function returnedByItem(array $saleItemIds, bool $lock = false): array
    {
        if ($saleItemIds === []) {
            return [];
        }
        $query = DB::table('v2_sale_return_items')->whereIn('sale_item_id', $saleItemIds);
        if ($lock) {
            $query->lockForUpdate();
        }
        $map = [];
        foreach ($query->get(['sale_item_id', 'quantity', 'subtotal']) as $row) {
            $id = (int) $row->sale_item_id;
            if (! isset($map[$id])) {
                $map[$id] = ['qty' => Money::value('0', 4), 'subtotal' => Money::value('0', 2)];
            }
            $map[$id]['qty'] = $map[$id]['qty']->plus((string) $row->quantity);
            $map[$id]['subtotal'] = $map[$id]['subtotal']->plus((string) $row->subtotal);
        }

        return $map;
    }

    private function guardAccount(string $code, BigDecimal $delta): void
    {
        if (! DB::table('chart_of_accounts')->where('account_code', $code)->where('is_active', true)->lockForUpdate()->exists()) {
            $this->invalid('Akun '.$code.' tidak ditemukan atau tidak aktif di Bagan Akun.');
        }
        $balance = Money::value('0', 2);
        foreach (DB::table('journal_entry_lines')->where('account_code', $code)->get(['debit', 'credit']) as $line) {
            $balance = $balance->plus((string) $line->debit)->minus((string) $line->credit);
        }
        if ($balance->isLessThan($delta)) {
            $this->invalid('Saldo akun '.$code.' tidak mencukupi untuk pengembalian dana retur.');
        }
    }

    /** @return array<string, mixed> */
    private function replay(int $returnId): array
    {
        $row = DB::table('v2_sale_returns')->where('id', $returnId)->first();

        return [
            'return_id' => (int) $row->id,
            'number' => (string) $row->number,
            'sale_id' => (int) $row->sale_id,
            'return_date' => (string) $row->return_date,
            'total_amount' => (string) Money::value((string) $row->total_amount),
            'ar_reduction' => (string) Money::value((string) $row->ar_reduction),
            'refund_amount' => (string) Money::value((string) $row->refund_amount),
            'refund_method' => (string) $row->refund_method,
            'journal_entry_id' => (int) $row->journal_entry_id,
            'replayed' => true,
        ];
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

    private function decimal(mixed $value, string $field): BigDecimal
    {
        if (is_int($value)) {
            $raw = (string) $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $raw = trim($value);
        } else {
            $this->invalid($field.' wajib berupa angka.');
        }

        if (preg_match('/^-?\d{1,15}(\.\d{1,4})?$/', $raw) !== 1) {
            $this->invalid($field.' wajib angka desimal maksimal 4 angka pecahan.');
        }

        try {
            return Money::value($raw, 4)->toScale(4, RoundingMode::HalfUp);
        } catch (\Throwable) {
            $this->invalid($field.' tidak valid.');
        }
    }

    private function date(mixed $value): string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            $this->invalid('Tanggal retur harus berformat YYYY-MM-DD.');
        }
        [$year, $month, $day] = array_map('intval', explode('-', $value));
        if (! checkdate($month, $day, $year)) {
            $this->invalid('Tanggal retur tidak sah.');
        }

        return $value;
    }

    private function reason(mixed $value): string
    {
        if (! is_string($value)) {
            $this->invalid('Alasan retur wajib diisi.');
        }
        $trimmed = trim($value);
        $len = mb_strlen($trimmed);
        if ($len < 5 || $len > 255) {
            $this->invalid('Alasan retur harus 5 sampai 255 karakter.');
        }

        return $trimmed;
    }

    private function key(mixed $value): string
    {
        if (! is_string($value) || preg_match('/^[\x20-\x7E]{1,100}$/', trim($value)) !== 1) {
            $this->invalid('Kunci idempotensi wajib 1..100 karakter ASCII.');
        }

        return trim($value);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['sale_return' => $message]);
    }
}
