<?php

namespace App\Services\V2;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Laporan harian read-only dan dokumen penjualan read-only (V2).
 *
 * Kontrak keluaran `daily()` (semua uang string skala 2, tanpa SUM SQL/float):
 *  - date, currency, scope ('all' admin | 'own' kasir)
 *  - revenue.goods_gross : subtotal tersimpan (harga sebelum diskon baris)
 *  - revenue.discount    : diskon baris + diskon faktur
 *  - revenue.goods_net   : total - ongkir (pendapatan barang bersih)
 *  - revenue.delivery    : total ongkir (pendapatan kirim, terpisah)
 *  - revenue.total_sales : total penjualan (barang bersih + ongkir)
 *  - cash.opening_float, cash.collections, cash.out, cash.expected, cash.counted, cash.difference
 *    collections hanya movement masuk kind sale/collection (uang laci yang benar-benar diterima),
 *    bukan total penjualan kredit dan bukan transfer bank. expected mengikuti rumus tutup shift:
 *    opening + seluruh movement masuk - seluruh movement keluar.
 *    counted/difference null bila belum ada shift dengan uang hitung pada hari itu.
 *  - bank.posted         : pembayaran transfer piutang berstatus posted
 *  - bank.pending        : pembayaran transfer piutang berstatus pending/proposed
 *  - shifts.count/open/closed/review
 *
 * Hari bisnis = tanggal shift dibuka (`v2_shifts.opened_at`); penjualan dan kas
 * dihitung dari shift tersebut. Bank posted mengikuti tanggal jurnal verifikasi; pending mengikuti tanggal pengajuan.
 * Kasir melihat penjualan/pembayaran miliknya; laci shift bersama mencakup seluruh kasir.
 * Shift pribadi historis tetap dibatasi ke pemiliknya; admin melihat semua.
 * Tidak ada pagination/limit: seluruh baris dijumlahkan.
 */
final class ReportsDocuments
{
    public function __construct(private readonly Operations $operations) {}

    public function daily(int $actor, string $date): array
    {
        $this->assertDate($date);
        $role = $this->operations->role($actor);
        $own = $role !== 'admin';

        $shiftQuery = DB::table('v2_shifts')
            ->whereDate('opened_at', $date)
            ->when($own, fn ($q) => $q->where(fn ($visible) => $visible->where('is_shared', true)->orWhere('user_id', $actor)));
        $shifts = $shiftQuery->get(['id', 'opening_cash', 'expected_cash', 'counted_cash', 'difference', 'status', 'is_shared']);
        $shiftIds = $shifts->pluck('id')->map(fn ($id) => (int) $id)->all();

        $sales = $shiftIds === []
            ? collect()
            : DB::table('v2_sales')
                ->whereIn('shift_id', $shiftIds)
                ->when($own, fn ($q) => $q->where('user_id', $actor))
                ->get(['subtotal', 'line_discount', 'invoice_discount', 'delivery_fee', 'total']);

        $gross = $this->zero();
        $discount = $this->zero();
        $delivery = $this->zero();
        $totalSales = $this->zero();
        $goodsNet = $this->zero();

        foreach ($sales as $sale) {
            $subtotal = $this->money($sale->subtotal);
            $line = $this->money($sale->line_discount);
            $invoice = $this->money($sale->invoice_discount);
            $fee = $this->money($sale->delivery_fee);
            $total = $this->money($sale->total);

            $gross = $gross->plus($subtotal);
            $discount = $discount->plus($line)->plus($invoice);
            $delivery = $delivery->plus($fee);
            $totalSales = $totalSales->plus($total);
            $goodsNet = $goodsNet->plus($total->minus($fee));
        }

        $opening = $this->zero();
        $collections = $this->zero();
        $out = $this->zero();
        $movementsIn = $this->zero();
        $counted = null;
        $difference = null;
        $openCount = 0;
        $closedCount = 0;
        $reviewCount = 0;

        foreach ($shifts as $shift) {
            $opening = $opening->plus($this->money($shift->opening_cash));
            $shift->status === 'open' ? $openCount++ : null;
            $shift->status === 'closed' ? $closedCount++ : null;
            $shift->status === 'review' ? $reviewCount++ : null;

            if ($shift->counted_cash !== null) {
                $counted = ($counted ?? $this->zero())->plus($this->money($shift->counted_cash));
                $difference = ($difference ?? $this->zero())->plus($this->money($shift->difference));
            }
        }

        if ($shiftIds !== []) {
            $movements = DB::table('v2_shift_movements')
                ->whereIn('shift_id', $shiftIds)
                ->get(['direction', 'kind', 'amount']);

            foreach ($movements as $movement) {
                $amount = $this->money($movement->amount);
                if ($movement->direction === 'in') {
                    $movementsIn = $movementsIn->plus($amount);
                    if (in_array($movement->kind, ['sale', 'collection'], true)) {
                        $collections = $collections->plus($amount);
                    }
                } else {
                    $out = $out->plus($amount);
                }
            }
        }

        $expected = $opening->plus($movementsIn)->minus($out);

        $posted = $this->zero();
        $pending = $this->zero();
        $hasBankCol = Schema::hasColumn('v2_payments', 'bank_account_code');
        $paymentCols = ['v2_payments.amount', 'v2_payments.status'];
        if ($hasBankCol) {
            $paymentCols[] = 'v2_payments.bank_account_code';
        }
        $payments = DB::table('v2_payments')
            ->join('v2_invoices', 'v2_invoices.id', '=', 'v2_payments.invoice_id')
            ->where('v2_invoices.direction', 'receivable')
            ->where('v2_payments.method', 'transfer')
            ->leftJoin('journal_entries', 'journal_entries.id', '=', 'v2_payments.journal_entry_id')
            ->where(function ($q) use ($date) {
                $q->where(function ($posted) use ($date) {
                    $posted->where('v2_payments.status', 'posted')->whereDate('journal_entries.transaction_date', $date);
                })
                    ->orWhere(function ($pending) use ($date) {
                        $pending->whereIn('v2_payments.status', ['pending', 'proposed'])->whereDate('v2_payments.created_at', $date);
                    });
            })
            ->when($own, fn ($q) => $q->where('v2_payments.requested_by', $actor))
            ->get($paymentCols);

        $bankNames = app(BankAccounts::class)->nameMap(false);
        $byBank = [];
        foreach ($payments as $payment) {
            $bankCode = trim((string) ($payment->bank_account_code ?? '')) ?: BankAccounts::DEFAULT_BANK;
            if (! isset($byBank[$bankCode])) {
                $byBank[$bankCode] = [
                    'code' => $bankCode,
                    'name' => $bankNames[$bankCode] ?? ($bankCode === BankAccounts::DEFAULT_BANK ? 'Bank Utama' : $bankCode),
                    'posted' => $this->zero(),
                    'pending' => $this->zero(),
                ];
            }
            $amount = $this->money($payment->amount);
            if ($payment->status === 'posted') {
                $posted = $posted->plus($amount);
                $byBank[$bankCode]['posted'] = $byBank[$bankCode]['posted']->plus($amount);
            } elseif (in_array($payment->status, ['pending', 'proposed'], true)) {
                $pending = $pending->plus($amount);
                $byBank[$bankCode]['pending'] = $byBank[$bankCode]['pending']->plus($amount);
            }
        }
        ksort($byBank);
        $bankBreakdown = array_values(array_map(fn (array $row): array => [
            'code' => $row['code'],
            'name' => $row['name'],
            'posted' => $this->str($row['posted']),
            'pending' => $this->str($row['pending']),
        ], $byBank));

        return [
            'date' => $date,
            'currency' => 'IDR',
            'scope' => $own ? 'own' : 'all',
            'cash_scope' => $shifts->contains(fn ($shift) => (bool) $shift->is_shared) ? 'store' : ($own ? 'own' : 'all'),
            'revenue' => [
                'goods_gross' => $this->str($gross),
                'discount' => $this->str($discount),
                'goods_net' => $this->str($goodsNet),
                'delivery' => $this->str($delivery),
                'total_sales' => $this->str($totalSales),
            ],
            'sales_count' => $sales->count(),
            'cash' => [
                'opening_float' => $this->str($opening),
                'collections' => $this->str($collections),
                'out' => $this->str($out),
                'expected' => $this->str($expected),
                'counted' => $counted === null ? null : $this->str($counted),
                'difference' => $difference === null ? null : $this->str($difference),
            ],
            'bank' => [
                'posted' => $this->str($posted),
                'pending' => $this->str($pending),
                'by_account' => $bankBreakdown,
            ],
            'shifts' => [
                'count' => count($shiftIds),
                'open' => $openCount,
                'closed' => $closedCount,
                'review' => $reviewCount,
            ],
        ];
    }

    public function document(int $actor, int $saleId, string $kind = 'receipt'): array
    {
        if (! in_array($kind, ['receipt', 'delivery'], true)) {
            $this->fail('Jenis dokumen harus "receipt" atau "delivery".');
        }

        $this->operations->requireRole($actor, ['admin', 'cashier']);

        $sale = DB::table('v2_sales')->where('id', $saleId)->first();
        if (! $sale) {
            $this->fail('Penjualan tidak ditemukan.');
        }

        $items = DB::table('v2_sale_items as si')
            ->leftJoin('products as p', 'p.id', '=', 'si.product_id')
            ->where('si.sale_id', $saleId)
            ->orderBy('si.id')
            ->get(['si.product_id', 'si.name', 'si.quantity', 'si.unit_price', 'si.cost_price', 'si.discount', 'si.subtotal', 'p.unit'])
            ->map(fn ($item) => [
                'product_id' => (int) $item->product_id,
                'name' => (string) $item->name,
                'unit' => (string) ($item->unit ?? ''),
                'quantity' => $this->str($this->money($item->quantity, 4), 4),
                'unit_price' => $this->str($this->money($item->unit_price)),
                'cost_price' => $this->str($this->money($item->cost_price, 4), 4),
                'discount' => $this->str($this->money($item->discount)),
                'subtotal' => $this->str($this->money($item->subtotal)),
            ])
            ->all();

        $customer = null;
        if ($sale->customer_id !== null) {
            $party = DB::table('v2_parties')->where('id', $sale->customer_id)->first(['id', 'code', 'name']);
            if ($party) {
                $customer = ['id' => (int) $party->id, 'code' => (string) $party->code, 'name' => (string) $party->name];
            }
        }

        $cashierName = (string) (DB::table('users')->where('id', $sale->user_id)->value('name') ?? '');
        $shiftRow = DB::table('v2_shifts')->where('id', $sale->shift_id)->first(['business_date', 'opened_at']);
        $cashDay = $shiftRow
            ? (string) ($shiftRow->business_date ?: substr((string) $shiftRow->opened_at, 0, 10))
            : substr((string) $sale->created_at, 0, 10);
        $dueDate = DB::table('v2_invoices')->where('sale_id', $saleId)->value('due_date');
        $dueDate = $dueDate !== null && $dueDate !== '' ? (string) $dueDate : null;

        $deliveryRow = DB::table('v2_deliveries')->where('sale_id', $saleId)->first();
        $delivery = $deliveryRow === null ? null : [
            'id' => (int) $deliveryRow->id,
            'number' => (string) $deliveryRow->number,
            'driver_name' => $deliveryRow->driver_name === null ? null : (string) $deliveryRow->driver_name,
            'destination' => (string) $deliveryRow->destination,
            'status' => (string) $deliveryRow->status,
            'vehicle_id' => $deliveryRow->vehicle_id === null ? null : (int) $deliveryRow->vehicle_id,
        ];

        if ($kind === 'delivery' && $delivery === null) {
            $this->fail('Penjualan ini tidak memiliki data pengiriman.');
        }

        $total = $this->money($sale->total);
        $fee = $this->money($sale->delivery_fee);

        return [
            'kind' => $kind,
            'store' => [
                'name' => (string) config('v2.store.name', 'Toko Bahan Bangunan'),
                'address' => (string) config('v2.store.address', ''),
                'phone' => (string) config('v2.store.phone', ''),
                'footer' => (string) config('v2.store.footer', ''),
            ],
            'sale' => [
                'id' => (int) $sale->id,
                'number' => (string) $sale->number,
                'date' => substr((string) $sale->created_at, 0, 10),
                'created_at' => (string) $sale->created_at,
                'shift_id' => (int) $sale->shift_id,
                'cash_day' => $cashDay,
                'cashier_id' => (int) $sale->user_id,
                'cashier_name' => $cashierName,
                'price_tier' => (string) ($sale->price_tier ?? 'retail'),
                'due_date' => $dueDate,
                'notes' => property_exists($sale, 'notes') && $sale->notes !== null && trim((string) $sale->notes) !== ''
                    ? (string) $sale->notes
                    : null,
                'status' => (string) $sale->status,
                'payment_method' => (string) $sale->payment_method,
                'fulfillment' => (string) $sale->fulfillment,
                'subtotal' => $this->str($this->money($sale->subtotal)),
                'line_discount' => $this->str($this->money($sale->line_discount)),
                'invoice_discount' => $this->str($this->money($sale->invoice_discount)),
                'delivery_fee' => $this->str($fee),
                'total' => $this->str($total),
                'cogs' => $this->str($this->money($sale->cogs)),
                'tendered' => $this->str($this->money($sale->tendered)),
                'change' => $this->str($this->money($sale->change_amount)),
            ],
            'items' => $items,
            'customer' => $customer,
            'delivery' => $delivery,
            'totals' => [
                'goods_net' => $this->str($total->minus($fee)),
                'delivery' => $this->str($fee),
                'total_sales' => $this->str($total),
            ],
        ];
    }

    private function assertDate(string $date): void
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            $this->fail('Tanggal laporan tidak sah; gunakan format YYYY-MM-DD.');
        }
    }

    private function zero(): BigDecimal
    {
        return BigDecimal::zero()->toScale(2, RoundingMode::HalfUp);
    }

    private function money(mixed $value, int $scale = 2): BigDecimal
    {
        return Money::value((string) $value, $scale);
    }

    private function str(BigDecimal $value, int $scale = 2): string
    {
        return (string) $value->toScale($scale, RoundingMode::HalfUp);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['documents' => $message]);
    }
}
