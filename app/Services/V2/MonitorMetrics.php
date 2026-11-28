<?php

namespace App\Services\V2;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Angka ringkasan untuk monitor di atas setiap menu. Murni baca: tidak ada INSERT/UPDATE,
 * tidak menyentuh Ledger atau Operations. Uang dijumlahkan dengan BigDecimal di PHP
 * (konvensi proyek: tanpa SUM SQL/float), hitungan memakai COUNT SQL penuh sehingga tidak
 * terpotong batas 30/50/100 baris yang dimuat halaman.
 */
final class MonitorMetrics
{
    /** @return array<string, mixed> */
    public function snapshot(string $today, bool $admin, int $actor): array
    {
        $monthStart = substr($today, 0, 8).'01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $open = DB::table('v2_invoices')->where('status', '<>', 'paid')->get(['direction', 'amount', 'paid']);
        $outstanding = fn (string $direction): array => $this->sumRows(
            $open->where('direction', $direction),
            fn (object $row): BigDecimal => BigDecimal::of((string) $row->amount)->minus((string) $row->paid)
        );
        [$receivable, $receivableCount] = $outstanding('receivable');
        // Nilai tagihan dan bagian yang sudah dibayar dari piutang yang sama (untuk ringkasan Tagihan).
        $openReceivable = $open->where('direction', 'receivable');
        [$receivableBilled] = $this->sumRows($openReceivable, fn (object $row): BigDecimal => BigDecimal::of((string) $row->amount));
        [$receivablePaid] = $this->sumRows($openReceivable, fn (object $row): BigDecimal => BigDecimal::of((string) $row->paid));
        [$payable, $payableCount] = $outstanding('payable');

        $awaiting = DB::table('v2_payments')->whereIn('status', ['pending', 'proposed'])->get(['amount']);
        [$awaitingTotal, $awaitingCount] = $this->sumRows($awaiting, fn (object $row): BigDecimal => BigDecimal::of((string) $row->amount));

        $advances = DB::table('v2_driver_advances')->where('status', 'open')->get(['amount']);
        [$advanceTotal, $advanceCount] = $this->sumRows($advances, fn (object $row): BigDecimal => BigDecimal::of((string) $row->amount));

        $receipts = DB::table('inventory_receipts')->whereNull('voided_at')->whereBetween('receipt_date', [$monthStart, $monthEnd])->get(['total_amount', 'receipt_type']);
        [$receiptTotal, $receiptCount] = $this->sumRows($receipts, fn (object $row): BigDecimal => BigDecimal::of((string) $row->total_amount));

        $opnamesMonth = DB::table('v2_stock_opnames')
            ->whereBetween('opname_date', [$monthStart, $monthEnd])
            ->get(['gain_total', 'loss_total']);
        [$opnameGainTotal, $opnameCount] = $this->sumRows($opnamesMonth, fn (object $row): BigDecimal => BigDecimal::of((string) $row->gain_total));
        [$opnameLossTotal] = $this->sumRows($opnamesMonth, fn (object $row): BigDecimal => BigDecimal::of((string) $row->loss_total));

        $shiftScope = fn () => DB::table('v2_shifts')->when(! $admin, fn ($q) => $q->where(fn ($visible) => $visible->where('is_shared', true)->orWhere('user_id', $actor)));
        $differences = $shiftScope()->whereIn('status', ['closed', 'review'])->whereBetween(DB::raw('date(closed_at)'), [$monthStart, $monthEnd])->get(['difference']);
        [$differenceTotal] = $this->sumRows($differences, fn (object $row): BigDecimal => BigDecimal::of((string) ($row->difference ?? '0')));
        $unresolvedDifferences = $shiftScope()->where('status', 'review')->get(['difference']);
        [$unresolvedTotal, $unresolvedCount] = $this->sumRows($unresolvedDifferences, fn (object $row): BigDecimal => BigDecimal::of((string) ($row->difference ?? '0')));

        $products = DB::table('products');

        return [
            'receivable' => ['total' => $receivable, 'count' => $receivableCount, 'billed' => $receivableBilled, 'paid' => $receivablePaid],
            'payable' => ['total' => $payable, 'count' => $payableCount],
            'awaiting' => ['total' => $awaitingTotal, 'count' => $awaitingCount],
            'rejected_30d' => DB::table('v2_payments')->where('status', 'rejected')->where('created_at', '>=', date('Y-m-d', strtotime($today.' -30 days')))->count(),
            'advances' => ['total' => $advanceTotal, 'count' => $advanceCount],
            'deliveries' => [
                'prepared' => DB::table('v2_deliveries')->where('status', 'prepared')->count(),
                'sent' => DB::table('v2_deliveries')->where('status', 'sent')->count(),
                'received_today' => DB::table('v2_deliveries')->where('status', 'received')->whereDate('received_at', $today)->count(),
                'unassigned' => DB::table('v2_deliveries')->where('status', 'prepared')->whereNull('vehicle_id')->count(),
            ],
            'receipts_month' => [
                'total' => $receiptTotal,
                'count' => $receiptCount,
                'purchase' => $receipts->whereIn('receipt_type', ['purchase', 'restock'])->count(),
                'opening' => $receipts->where('receipt_type', 'opening')->count(),
            ],
            'shifts' => [
                'open' => $shiftScope()->where('status', 'open')->count(),
                'review' => $unresolvedCount,
                'difference_month' => $differenceTotal,
                'difference_unresolved' => $unresolvedTotal,
            ],
            'stock_opnames' => [
                'count' => $opnameCount,
                'gain_total' => $opnameGainTotal,
                'loss_total' => $opnameLossTotal,
            ],
            'stock' => [
                'active' => (clone $products)->where('is_active', true)->count(),
                'archived' => (clone $products)->where('is_active', false)->count(),
                'out' => (clone $products)->where('is_active', true)->where('stock_quantity', '<=', 0)->count(),
                'low' => (clone $products)->where('is_active', true)->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '<=', 'min_stock')->count(),
            ],
            'parties' => $this->partyCounts(),
            'vehicles' => ['active' => DB::table('vehicles')->where('status', 'active')->count(), 'inactive' => DB::table('vehicles')->where('status', '<>', 'active')->count()],
            'drivers' => [
                'internal' => DB::table('v2_drivers')->where('is_active', true)->where('kind', 'internal')->count(),
                'external' => DB::table('v2_drivers')->where('is_active', true)->where('kind', 'external')->count(),
                'archived' => DB::table('v2_drivers')->where('is_active', false)->count(),
            ],
            'options' => $this->optionCounts(),
            'accounts' => $admin ? $this->accountCounts() : null,
        ];
    }

    /** @return array{0:string,1:int} */
    private function sumRows(iterable $rows, callable $value): array
    {
        $sum = BigDecimal::zero();
        $count = 0;
        foreach ($rows as $row) {
            $sum = $sum->plus($value($row));
            $count++;
        }

        return [(string) $sum->toScale(2), $count];
    }

    /** @return array<string, array{active:int,archived:int}> */
    private function partyCounts(): array
    {
        $rows = DB::table('v2_parties')->selectRaw('kind, is_active, count(*) as total')->groupBy('kind', 'is_active')->get();
        $out = [];
        foreach (['customer', 'supplier', 'employee'] as $kind) {
            $out[$kind] = [
                'active' => (int) $rows->where('kind', $kind)->where('is_active', 1)->sum('total'),
                'archived' => (int) $rows->where('kind', $kind)->where('is_active', 0)->sum('total'),
            ];
        }

        return $out;
    }

    /** @return array<string, array{active:int,archived:int,used:int}> */
    private function optionCounts(): array
    {
        $out = [];
        foreach (['unit', 'category'] as $type) {
            $column = $type.'_id';
            $out[$type] = [
                'active' => DB::table('product_options')->where('type', $type)->where('is_active', true)->count(),
                'archived' => DB::table('product_options')->where('type', $type)->where('is_active', false)->count(),
                'used' => DB::table('products')->whereNotNull($column)->distinct()->count($column),
            ];
        }

        return $out;
    }

    /** @return array{admin:int,cashier:int,inactive:int} */
    private function accountCounts(): array
    {
        $roles = DB::table('v2_user_roles')->join('users', 'users.id', '=', 'v2_user_roles.user_id')->where('users.is_active', true)
            ->selectRaw('v2_user_roles.role as role, count(*) as total')->groupBy('v2_user_roles.role')->pluck('total', 'role');

        return [
            'admin' => (int) ($roles['admin'] ?? 0),
            'cashier' => (int) ($roles['cashier'] ?? 0),
            'inactive' => DB::table('users')->where('is_active', false)->count(),
        ];
    }
}
