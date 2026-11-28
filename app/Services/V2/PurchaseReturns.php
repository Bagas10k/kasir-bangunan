<?php

namespace App\Services\V2;

use App\Support\SafeLog;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Retur pembelian V2 ke supplier atas penerimaan yang telah dikonfirmasi:
 * mengurangi stok gudang & menyesuaikan nilai persediaan (1301), mengurangi utang usaha (2101)
 * atau mencatat pengembalian dana dari supplier ke Kas/Bank, dengan jurnal balik seimbang.
 */
final class PurchaseReturns
{
    private const CASH = '1101';

    private const INVENTORY = '1301';

    private const PAYABLE = '2101';

    private const COGS = '5101';

    public function __construct(
        private readonly Operations $operations,
        private readonly BankAccounts $bankAccounts,
    ) {}

    /** @return array<string, mixed> */
    public function summary(int $actor, int $receiptId): array
    {
        $this->operations->requireRole($actor, ['admin']);
        $receipt = DB::table('inventory_receipts')->where('id', $receiptId)->first();
        abort_unless($receipt, 404);

        $invoice = DB::table('v2_invoices')->where('receipt_id', $receiptId)->first();
        $hasPending = $invoice && DB::table('v2_payments')
            ->where('invoice_id', $invoice->id)
            ->whereIn('status', ['pending', 'proposed'])
            ->exists();

        $receiptItems = DB::table('inventory_receipt_items as ri')
            ->join('products as p', 'p.id', '=', 'ri.product_id')
            ->where('ri.inventory_receipt_id', $receiptId)
            ->orderBy('ri.id')
            ->get(['ri.*', 'p.name as product_name', 'p.unit as product_unit', 'p.stock_quantity as current_stock']);

        $returnedByItem = $this->returnedByItem($receiptItems->pluck('id')->all());

        $items = [];
        foreach ($receiptItems as $item) {
            $received = Money::value((string) $item->quantity, 4);
            $prev = $returnedByItem[(int) $item->id] ?? ['qty' => Money::value('0', 4), 'subtotal' => Money::value('0', 2)];
            $remainingQty = $received->minus($prev['qty'])->toScale(4, RoundingMode::HalfUp);
            $warehouseStock = Money::value((string) $item->current_stock, 4);
            $maxReturnable = $remainingQty->isLessThan($warehouseStock) ? $remainingQty : $warehouseStock;
            if ($maxReturnable->isNegative()) {
                $maxReturnable = Money::value('0', 4);
            }
            $items[] = [
                'receipt_item_id' => (int) $item->id,
                'product_id' => (int) $item->product_id,
                'name' => (string) $item->product_name,
                'unit' => (string) $item->product_unit,
                'received_qty' => (string) $received,
                'returned_qty' => (string) $prev['qty'],
                'remaining_qty' => (string) $remainingQty,
                'warehouse_stock' => (string) $warehouseStock,
                'max_returnable_qty' => (string) $maxReturnable,
                'unit_cost' => (string) Money::value((string) $item->unit_cost, 4),
                'remaining_subtotal' => (string) Money::value((string) $item->subtotal, 2)->minus($prev['subtotal'])->toScale(2, RoundingMode::HalfUp),
            ];
        }

        $returns = DB::table('v2_purchase_returns as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.created_by')
            ->where('r.receipt_id', $receiptId)
            ->orderByDesc('r.id')
            ->get(['r.*', 'u.name as author_name']);

        return [
            'receipt_id' => (int) $receipt->id,
            'receipt_number' => (string) ($receipt->receipt_number ?: '#'.$receipt->id),
            'receipt_type' => (string) $receipt->receipt_type,
            'receipt_date' => (string) $receipt->receipt_date,
            'voided' => $receipt->voided_at !== null,
            'supplier_name' => (string) ($receipt->supplier_name ?? '—'),
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
    public function store(int $actor, int $receiptId, array $data): array
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
            $this->invalid('Pilih minimal 1 barang yang diretur ke supplier.');
        }

        $parsedItems = [];
        foreach ($rawItems as $row) {
            if (! is_array($row)) {
                $this->invalid('Format item retur tidak valid.');
            }
            $receiptItemId = isset($row['receipt_item_id']) && $row['receipt_item_id'] !== '' ? $this->id($row['receipt_item_id'], 'Item penerimaan') : null;
            $productId = isset($row['product_id']) && $row['product_id'] !== '' ? $this->id($row['product_id'], 'Produk') : null;
            if ($receiptItemId === null && $productId === null) {
                $this->invalid('Item penerimaan atau produk wajib dipilih.');
            }
            $qty = $this->decimal($row['quantity'] ?? null, 'Jumlah retur');
            if (! $qty->isPositive()) {
                $this->invalid('Jumlah retur harus lebih dari nol.');
            }
            $parsedItems[] = [
                'receipt_item_id' => $receiptItemId,
                'product_id' => $productId,
                'quantity' => $qty,
            ];
        }

        $hash = hash('sha256', json_encode([
            'receipt_id' => $receiptId,
            'return_date' => $returnDate,
            'reason' => $reason,
            'refund_method' => $requestedMethod,
            'bank_account_code' => $requestedBank,
            'items' => array_map(fn (array $row): array => [
                'receipt_item_id' => $row['receipt_item_id'],
                'product_id' => $row['product_id'],
                'quantity' => (string) $row['quantity'],
            ], $parsedItems),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $result = DB::transaction(function () use ($actor, $receiptId, $returnDate, $reason, $key, $requestedMethod, $requestedBank, $parsedItems, $hash): array {
            DB::table('users')->where('id', $actor)->lockForUpdate()->first();

            $existing = DB::table('v2_purchase_returns')->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                abort_unless((int) $existing->created_by === $actor, 403);
                if (! hash_equals((string) $existing->request_hash, $hash)) {
                    $this->invalid('Kunci transaksi retur pembelian sudah dipakai untuk isi berbeda.');
                }

                return $this->replay((int) $existing->id);
            }

            $receipt = DB::table('inventory_receipts')->where('id', $receiptId)->lockForUpdate()->first();
            if (! $receipt) {
                abort(404);
            }
            if ($receipt->receipt_type !== 'purchase') {
                $this->invalid('Retur ke supplier hanya berlaku untuk penerimaan pembelian barang.');
            }
            if ($receipt->voided_at !== null) {
                $this->invalid('Penerimaan yang sudah dibatalkan tidak dapat diretur.');
            }
            if ($returnDate < (string) $receipt->receipt_date) {
                $this->invalid('Tanggal retur tidak boleh sebelum tanggal penerimaan barang.');
            }

            $invoice = DB::table('v2_invoices')->where('receipt_id', $receiptId)->lockForUpdate()->first();
            if (! $invoice) {
                $this->invalid('Tagihan utang supplier untuk penerimaan ini tidak ditemukan.');
            }
            if (DB::table('v2_payments')->where('invoice_id', $invoice->id)->whereIn('status', ['pending', 'proposed'])->lockForUpdate()->exists()) {
                $this->invalid('Tagihan penerimaan masih memiliki pengajuan pembayaran yang menunggu persetujuan admin.');
            }

            // Checkout locks the shift before products; follow that order to avoid a lock cycle.
            $lockedShift = $requestedMethod === 'cash' ? $this->operations->activeStoreShift(true) : null;

            $receiptItems = DB::table('inventory_receipt_items')
                ->where('inventory_receipt_id', $receiptId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($receiptItems->isEmpty()) {
                $this->invalid('Dokumen penerimaan tidak memiliki rincian barang.');
            }

            $byReceiptItemId = $receiptItems->keyBy('id');
            $byProductId = $receiptItems->keyBy('product_id');

            $resolvedLines = [];
            foreach ($parsedItems as $entry) {
                $receiptItem = $entry['receipt_item_id'] !== null
                    ? $byReceiptItemId->get($entry['receipt_item_id'])
                    : $byProductId->get($entry['product_id']);
                if (! $receiptItem) {
                    $this->invalid('Barang yang dipilih tidak termasuk dalam penerimaan ini.');
                }
                if (isset($resolvedLines[(int) $receiptItem->id])) {
                    $this->invalid('Barang yang sama tidak boleh diduplikasi dalam satu retur.');
                }
                $resolvedLines[(int) $receiptItem->id] = [
                    'receipt_item' => $receiptItem,
                    'quantity' => $entry['quantity'],
                ];
            }
            ksort($resolvedLines);

            $productIds = array_values(array_unique(array_map(fn (array $r): int => (int) $r['receipt_item']->product_id, $resolvedLines)));
            sort($productIds);
            $products = DB::table('products')->whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $returnedByItem = $this->returnedByItem(array_keys($resolvedLines), true);

            $totalReturn = BigDecimal::zero()->toScale(2);
            $totalInvCredit = BigDecimal::zero()->toScale(2);
            $preparedItems = [];

            foreach ($resolvedLines as $receiptItemId => $entry) {
                $item = $entry['receipt_item'];
                /** @var BigDecimal $qty */
                $qty = $entry['quantity'];
                $product = $products[(int) $item->product_id];
                $receivedQty = Money::value((string) $item->quantity, 4);
                $prev = $returnedByItem[$receiptItemId] ?? ['qty' => Money::value('0', 4), 'subtotal' => Money::value('0', 2)];
                $remainingQty = $receivedQty->minus($prev['qty'])->toScale(4, RoundingMode::HalfUp);

                if ($qty->isGreaterThan($remainingQty)) {
                    $this->invalid('Jumlah retur '.$product->name.' melebihi sisa yang dapat diretur ('.(string) $remainingQty.').');
                }

                $oldStock = Money::value((string) $product->stock_quantity, 4);
                if ($qty->isGreaterThan($oldStock)) {
                    $this->invalid('Stok gudang '.$product->name.' hanya '.(string) $oldStock.'; tidak mencukupi untuk retur ke supplier.');
                }

                $unitCost = Money::value((string) $item->unit_cost, 4);
                $origSubtotal = Money::value((string) $item->subtotal, 2);
                $lineAmount = Money::returnPortion($origSubtotal, $receivedQty, $prev['qty']->plus($qty), $prev['subtotal']);

                $oldCost = Money::value((string) $product->cost_price, 4);
                $oldValue = $oldStock->multipliedBy($oldCost)->toScale(2, RoundingMode::HalfUp);
                $newStock = $oldStock->minus($qty)->toScale(4, RoundingMode::HalfUp);

                if ($newStock->isZero()) {
                    $lineInvCredit = $oldValue;
                    $newCost = $oldCost;
                } elseif ($oldValue->isGreaterThan($lineAmount)) {
                    $lineInvCredit = $lineAmount;
                    $newCost = $oldValue->minus($lineAmount)->dividedBy($newStock, 4, RoundingMode::HalfUp);
                } else {
                    $lineInvCredit = $qty->multipliedBy($oldCost)->toScale(2, RoundingMode::HalfUp);
                    $newCost = $oldCost;
                }

                $totalReturn = $totalReturn->plus($lineAmount);
                $totalInvCredit = $totalInvCredit->plus($lineInvCredit);

                $preparedItems[] = [
                    'receipt_item' => $item,
                    'product_id' => (int) $item->product_id,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'subtotal' => $lineAmount,
                    'new_stock' => $newStock,
                    'new_cost' => $newCost,
                ];
            }

            if (! $totalReturn->isPositive()) {
                $this->invalid('Nilai retur kurang dari Rp 0,01. Jika ada barang lain yang dikembalikan, catat bersama dalam satu retur.');
            }

            $invAmount = Money::value((string) $invoice->amount, 2);
            $invPaid = Money::value((string) $invoice->paid, 2);
            $goodsOutstanding = $invAmount->minus($invPaid)->toScale(2);
            if ($goodsOutstanding->isNegative()) {
                $goodsOutstanding = Money::value('0', 2);
            }

            // Potong utang yang masih tersisa dahulu; kelebihan dari yang sudah dibayar harus dikembalikan supplier ke kas/bank.
            $apReduction = $totalReturn->isLessThanOrEqualTo($goodsOutstanding) ? $totalReturn : $goodsOutstanding;
            $refundAmount = $totalReturn->minus($apReduction)->toScale(2);

            $effectiveMethod = 'payable_adjustment';
            $shiftId = null;
            $refundAccount = null;
            $resolvedBank = null;

            if ($refundAmount->isPositive()) {
                if ($requestedMethod === null) {
                    $this->invalid('Tagihan supplier sudah dibayar sebagian/lunas; pilih rekening tujuan penerimaan dana retur (laci shift, Kas Utama, atau transfer bank).');
                }
                $effectiveMethod = $requestedMethod;
                if ($effectiveMethod === 'cash') {
                    $shift = $lockedShift;
                    if (! $shift) {
                        $this->invalid('Kas harian belum dibuka admin; penerimaan dana retur ke laci membutuhkan shift aktif.');
                    }
                    $shiftId = (int) $shift->id;
                    $refundAccount = self::CASH;
                } elseif ($effectiveMethod === 'main') {
                    $refundAccount = self::CASH;
                } else {
                    $resolvedBank = $this->bankAccounts->resolve($requestedBank);
                    $refundAccount = $resolvedBank;
                }
            }

            $now = now();
            $returnId = (int) DB::table('v2_purchase_returns')->insertGetId([
                'number' => 'TMP-'.bin2hex(random_bytes(8)),
                'receipt_id' => $receiptId,
                'invoice_id' => (int) $invoice->id,
                'shift_id' => $shiftId,
                'return_date' => $returnDate,
                'total_amount' => (string) $totalReturn->toScale(2),
                'inventory_credit' => (string) $totalInvCredit->toScale(2),
                'ap_reduction' => (string) $apReduction->toScale(2),
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
            $returnNumber = 'RTP-'.str_replace('-', '', $returnDate).'-'.str_pad((string) $returnId, 5, '0', STR_PAD_LEFT);
            DB::table('v2_purchase_returns')->where('id', $returnId)->update(['number' => $returnNumber, 'updated_at' => $now]);

            foreach ($preparedItems as $row) {
                DB::table('v2_purchase_return_items')->insert([
                    'purchase_return_id' => $returnId,
                    'receipt_item_id' => (int) $row['receipt_item']->id,
                    'product_id' => $row['product_id'],
                    'quantity' => (string) $row['quantity'],
                    'unit_cost' => (string) $row['unit_cost'],
                    'subtotal' => (string) $row['subtotal'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('products')->where('id', $row['product_id'])->update([
                    'stock_quantity' => (string) $row['new_stock'],
                    'cost_price' => (string) $row['new_cost'],
                    'updated_at' => $now,
                ]);

                DB::table('stock_movements')->insert([
                    'product_id' => $row['product_id'],
                    'reference_type' => 'v2_purchase_return',
                    'reference_id' => $returnId,
                    'quantity_delta' => (string) $row['quantity']->negated(),
                    'balance_after' => (string) $row['new_stock'],
                    'notes' => 'Retur pembelian '.$returnNumber.' ('.($receipt->receipt_number ?: '#'.$receiptId).')',
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
            DB::table('v2_invoices')->where('id', $invoice->id)->update([
                'amount' => (string) $newInvAmount,
                'paid' => (string) $newInvPaid,
                'status' => $newInvPaid->isGreaterThanOrEqualTo($newInvAmount) ? 'paid' : 'open',
                'updated_at' => $now,
            ]);

            if ($shiftId !== null && $refundAmount->isPositive()) {
                DB::table('v2_shift_movements')->insert([
                    'shift_id' => $shiftId,
                    'direction' => 'in',
                    'kind' => 'adjustment',
                    'amount' => (string) $refundAmount,
                    'reference_type' => 'v2_purchase_return',
                    'reference_id' => $returnId,
                    'note' => 'Pengembalian dana retur '.$returnNumber,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $lines = [];
            if ($apReduction->isPositive()) {
                $lines[] = ['account_code' => self::PAYABLE, 'debit' => (string) $apReduction, 'credit' => '0.00'];
            }
            if ($refundAmount->isPositive()) {
                $lines[] = ['account_code' => $refundAccount, 'debit' => (string) $refundAmount, 'credit' => '0.00'];
            }
            if ($totalInvCredit->isPositive()) {
                $lines[] = ['account_code' => self::INVENTORY, 'debit' => '0.00', 'credit' => (string) $totalInvCredit];
            }

            $costVariance = $totalReturn->minus($totalInvCredit)->toScale(2);
            if ($costVariance->isPositive()) {
                $lines[] = ['account_code' => self::COGS, 'debit' => '0.00', 'credit' => (string) $costVariance];
            } elseif ($costVariance->isNegative()) {
                $lines[] = ['account_code' => self::COGS, 'debit' => (string) $costVariance->abs(), 'credit' => '0.00'];
            }

            $journalId = Ledger::post(
                'v2_purchase_return',
                $returnId,
                'Retur pembelian '.$returnNumber.' ('.($receipt->receipt_number ?: '#'.$receiptId).')',
                $lines,
                $returnDate
            );
            DB::table('v2_purchase_returns')->where('id', $returnId)->update([
                'journal_entry_id' => $journalId,
                'updated_at' => $now,
            ]);

            return [
                'return_id' => $returnId,
                'number' => $returnNumber,
                'receipt_id' => $receiptId,
                'return_date' => $returnDate,
                'total_amount' => (string) $totalReturn,
                'inventory_credit' => (string) $totalInvCredit,
                'ap_reduction' => (string) $apReduction,
                'refund_amount' => (string) $refundAmount,
                'refund_method' => $effectiveMethod,
                'journal_entry_id' => $journalId,
            ];
        });

        SafeLog::info('purchase_return.posted', [
            'actor_id' => $actor,
            'return_id' => $result['return_id'],
            'receipt_id' => $receiptId,
            'total_amount' => $result['total_amount'],
            'refund_method' => $result['refund_method'],
        ]);

        return $result;
    }

    /**
     * @param  array<int, int>  $receiptItemIds
     * @return array<int, array{qty: BigDecimal, subtotal: BigDecimal}>
     */
    private function returnedByItem(array $receiptItemIds, bool $lock = false): array
    {
        if ($receiptItemIds === []) {
            return [];
        }
        $query = DB::table('v2_purchase_return_items')->whereIn('receipt_item_id', $receiptItemIds);
        if ($lock) {
            $query->lockForUpdate();
        }
        $map = [];
        foreach ($query->get(['receipt_item_id', 'quantity', 'subtotal']) as $row) {
            $id = (int) $row->receipt_item_id;
            if (! isset($map[$id])) {
                $map[$id] = ['qty' => Money::value('0', 4), 'subtotal' => Money::value('0', 2)];
            }
            $map[$id]['qty'] = $map[$id]['qty']->plus((string) $row->quantity);
            $map[$id]['subtotal'] = $map[$id]['subtotal']->plus((string) $row->subtotal);
        }

        return $map;
    }

    /** @return array<string, mixed> */
    private function replay(int $returnId): array
    {
        $row = DB::table('v2_purchase_returns')->where('id', $returnId)->first();

        return [
            'return_id' => (int) $row->id,
            'number' => (string) $row->number,
            'receipt_id' => (int) $row->receipt_id,
            'return_date' => (string) $row->return_date,
            'total_amount' => (string) Money::value((string) $row->total_amount),
            'inventory_credit' => (string) Money::value((string) $row->inventory_credit),
            'ap_reduction' => (string) Money::value((string) $row->ap_reduction),
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
        throw ValidationException::withMessages(['purchase_return' => $message]);
    }
}
