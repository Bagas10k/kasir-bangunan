<?php

namespace App\Services\V2;

use App\Services\AccountBalanceService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * Angka tambahan untuk halaman Pengawasan · Ringkasan yang belum ada di MonitorMetrics.
 * Murni baca (SELECT), uang dijumlah BigDecimal di PHP, tidak menyentuh Ledger/Operations.
 */
final class AdminSummary
{
    private const WATCH_LIMIT = 5;

    public function __construct(private readonly AccountBalanceService $balances) {}

    /** @return array<string, mixed> */
    public function snapshot(string $today): array
    {
        return [
            'drawer' => $this->drawer(),
            'bank' => (string) $this->balances->balance('1102'),
            'receivable_overdue' => DB::table('v2_invoices')->where('direction', 'receivable')->where('status', '<>', 'paid')
                ->whereNotNull('due_date')->where('due_date', '<', $today)->count(),
            'unassigned' => DB::table('v2_deliveries')->where('status', 'prepared')->whereNull('vehicle_id')->orderBy('id')->limit(1)->value('number'),
            'stock_watch' => $this->stockWatch(),
        ];
    }

    /**
     * Uang tunai yang seharusnya ada di laci shift terbuka: kas awal + masuk - keluar
     * (rumus yang sama dengan Operations::closeShift).
     *
     * @return array{total:string,count:int}
     */
    private function drawer(): array
    {
        $shifts = DB::table('v2_shifts')->where('status', 'open')->get(['id', 'opening_cash']);
        $total = BigDecimal::zero();
        foreach ($shifts as $shift) {
            $total = $total->plus((string) $shift->opening_cash);
        }
        if ($shifts->isNotEmpty()) {
            $movements = DB::table('v2_shift_movements')->whereIn('shift_id', $shifts->pluck('id'))->cursor(['direction', 'amount']);
            foreach ($movements as $movement) {
                $total = $movement->direction === 'in' ? $total->plus((string) $movement->amount) : $total->minus((string) $movement->amount);
            }
        }

        return ['total' => (string) $total->toScale(2, RoundingMode::HalfUp), 'count' => $shifts->count()];
    }

    /**
     * Produk aktif yang habis atau menipis: habis dulu, lalu yang paling dekat/jauh di bawah batas minimum.
     *
     * @return list<array{name:string,qty:string,unit:string,min:string,out:bool}>
     */
    private function stockWatch(): array
    {
        $rows = DB::table('products')->where('is_active', true)
            ->where(fn ($q) => $q->where('stock_quantity', '<=', 0)->orWhereColumn('stock_quantity', '<=', 'min_stock'))
            ->get(['name', 'unit', 'stock_quantity', 'min_stock']);

        $items = [];
        foreach ($rows as $row) {
            $qty = BigDecimal::of((string) $row->stock_quantity);
            $min = BigDecimal::of((string) ($row->min_stock ?? '0'));
            $items[] = ['row' => $row, 'qty' => $qty, 'min' => $min, 'out' => $qty->isLessThanOrEqualTo(0)];
        }
        usort($items, function (array $a, array $b): int {
            if ($a['out'] !== $b['out']) {
                return $a['out'] ? -1 : 1;
            }
            // Rasio stok terhadap minimum, terkecil lebih dulu; perbandingan silang agar tetap BigDecimal.
            $left = $a['qty']->multipliedBy($b['min']->isZero() ? 1 : $b['min']);
            $right = $b['qty']->multipliedBy($a['min']->isZero() ? 1 : $a['min']);

            return $left->compareTo($right) ?: strcmp((string) $a['row']->name, (string) $b['row']->name);
        });

        return array_map(fn (array $item): array => [
            'name' => (string) $item['row']->name,
            'qty' => $this->quantity($item['qty']),
            'unit' => (string) ($item['row']->unit ?? ''),
            'min' => $this->quantity($item['min']),
            'out' => $item['out'],
        ], array_slice($items, 0, self::WATCH_LIMIT));
    }

    private function quantity(BigDecimal $value): string
    {
        $text = (string) $value->toScale(4, RoundingMode::HalfUp);
        [$int, $frac] = explode('.', $text);
        $frac = rtrim($frac, '0');

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $int).($frac !== '' ? ','.$frac : '');
    }
}
