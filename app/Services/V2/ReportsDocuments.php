<?php

namespace App\Services\V2;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
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
 * Kasir hanya melihat shift/penjualan/pembayaran miliknya; admin melihat semua.
 * Tidak ada pagination/limit: seluruh baris dijumlahkan.
 */
final class ReportsDocuments
{
    public function __construct(private readonly Operations $operations)
    {
    }

    public function daily(int $actor, string $date): array
    {
        $this->assertDate($date);
        $role = $this->operations->role($actor);
        $own = $role !== 'admin';

        $shiftQuery = DB::table('v2_shifts')
            ->whereDate('opened_at', $date)
            ->when($own, fn ($q) => $q->where('user_id', $actor));
        $shifts = $shiftQuery->get(['id', 'opening_cash', 'expected_cash', 'counted_cash', 'difference', 'status']);
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
        $payments = DB::table('v2_payments')
            ->join('v2_invoices', 'v2_invoices.id', '=', 'v2_payments.invoice_id')
            ->where('v2_invoices.direction', 'receivable')
            ->where('v2_payments.method', 'transfer')
            ->leftJoin('journal_entries', 'journal_entries.id', '=', 'v2_payments.journal_entry_id')
            ->where(function ($q) use ($date) {
                $q->where(function ($posted) use ($date) { $posted->where('v2_payments.status', 'posted')->whereDate('journal_entries.transaction_date', $date); })
                  ->orWhere(function ($pending) use ($date) { $pending->whereIn('v2_payments.status', ['pending','proposed'])->whereDate('v2_payments.created_at', $date); });
            })
            ->when($own, fn ($q) => $q->where('v2_payments.requested_by', $actor))
            ->get(['v2_payments.amount', 'v2_payments.status']);

        foreach ($payments as $payment) {
            if ($payment->status === 'posted') {
                $posted = $posted->plus($this->money($payment->amount));
            } elseif (in_array($payment->status, ['pending', 'proposed'], true)) {
                $pending = $pending->plus($this->money($payment->amount));
            }
        }

        return [
            'date' => $date,
            'currency' => 'IDR',
            'scope' => $own ? 'own' : 'all',
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

        $role = $this->operations->role($actor);

        $sale = DB::table('v2_sales')->where('id', $saleId)->first();
        if (! $sale) {
            $this->fail('Penjualan tidak ditemukan.');
        }
        if ($role !== 'admin' && (int) $sale->user_id !== $actor) {
            abort(403);
        }

        $items = DB::table('v2_sale_items')
            ->where('sale_id', $saleId)
            ->orderBy('id')
            ->get(['product_id', 'name', 'quantity', 'unit_price', 'cost_price', 'discount', 'subtotal'])
            ->map(fn ($item) => [
                'product_id' => (int) $item->product_id,
                'name' => (string) $item->name,
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
            'sale' => [
                'id' => (int) $sale->id,
                'number' => (string) $sale->number,
                'date' => substr((string) $sale->created_at, 0, 10),
                'shift_id' => (int) $sale->shift_id,
                'cashier_id' => (int) $sale->user_id,
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