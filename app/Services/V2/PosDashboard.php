<?php

namespace App\Services\V2;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Ringkasan dashboard kasir: omzet, transaksi, grafik, produk terlaris, dan pembanding periode lalu.
 *
 * Hanya SELECT. Dibaca dengan cursor() dan kolom seperlunya, jadi memori tetap kecil walau transaksinya ribuan;
 * yang disimpan hanya jumlah per hari/jam. Uang dan jumlah dijumlah BigDecimal, bukan SUM SQL atau float.
 */
final class PosDashboard
{
    public const PERIODS = [
        'today' => ['Hari ini', 1],
        '7d' => ['7 hari terakhir', 7],
        '30d' => ['30 hari terakhir', 30],
    ];

    private const TOP_LIMIT = 10;

    /** @return array<string,mixed> */
    public function custom(string $from, string $to): array
    {
        Validator::make(['from' => $from, 'to' => $to], [
            'from' => ['required', 'date_format:Y-m-d', 'before_or_equal:to'],
            'to' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ])->validate();
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        $days = (int) $start->diffInDays($end) + 1;
        if ($days > 366) {
            throw ValidationException::withMessages(['to' => 'Rentang khusus maksimal 366 hari.']);
        }
        $totals = $counts = $hours = $quantities = [];
        foreach (DB::table('v2_sales')->where('created_at', '>=', $start->copy()->subDays($days))
            ->where('created_at', '<=', $end->copy()->endOfDay())->select(['total', 'created_at'])->cursor() as $sale) {
            $day = substr((string) $sale->created_at, 0, 10);
            $totals[$day] = ($totals[$day] ?? BigDecimal::zero())->plus((string) $sale->total);
            $counts[$day] = ($counts[$day] ?? 0) + 1;
            if ($days === 1 && $day === $from) {
                $hour = (int) substr((string) $sale->created_at, 11, 2);
                $hours[$hour] = ($hours[$hour] ?? BigDecimal::zero())->plus((string) $sale->total);
            }
        }
        foreach (DB::table('v2_sale_items as i')->join('v2_sales as s', 's.id', '=', 'i.sale_id')
            ->where('s.created_at', '>=', $start)->where('s.created_at', '<=', $end->copy()->endOfDay())
            ->select(['i.product_id', 'i.name', 'i.quantity'])->cursor() as $item) {
            $entry = $quantities[$item->product_id] ?? ['name' => $item->name, 'qty' => BigDecimal::zero()];
            $entry['qty'] = $entry['qty']->plus((string) $item->quantity);
            $quantities[$item->product_id] = $entry;
        }
        $data = $this->period($days === 1 ? 'today' : 'custom', $start->format('d/m/Y').' – '.$end->format('d/m/Y'), $days, $end, $totals, $counts, $hours, $quantities);
        $data['period'] = 'custom';
        $data['hourly'] = $days === 1;
        $data['previous_label'] = 'periode sebelumnya';

        return $data;
    }

    /** @return array<string, array<string, mixed>> kunci: today, 7d, 30d */
    public function build(?int $onlyUserId, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $today = $now->copy()->startOfDay();
        $earliest = $today->copy()->subDays(59);   // 30 hari + 30 hari pembanding

        $dayTotal = [];
        $dayCount = [];
        $hourTotal = [];
        $sales = DB::table('v2_sales')
            ->where('created_at', '>=', $earliest->toDateTimeString())
            ->where('created_at', '<=', $now->copy()->endOfDay()->toDateTimeString())
            ->when($onlyUserId !== null, fn ($q) => $q->where('user_id', $onlyUserId))
            ->select(['total', 'created_at'])
            ->cursor();
        $todayKey = $today->format('Y-m-d');
        foreach ($sales as $sale) {
            $at = (string) $sale->created_at;
            $day = substr($at, 0, 10);
            $amount = BigDecimal::of((string) $sale->total);
            $dayTotal[$day] = ($dayTotal[$day] ?? BigDecimal::zero())->plus($amount);
            $dayCount[$day] = ($dayCount[$day] ?? 0) + 1;
            if ($day === $todayKey) {
                $hour = (int) substr($at, 11, 2);
                $hourTotal[$hour] = ($hourTotal[$hour] ?? BigDecimal::zero())->plus($amount);
            }
        }

        $quantities = $this->quantities($onlyUserId, $today->copy()->subDays(29), $today);

        $out = [];
        foreach (self::PERIODS as $key => [$label, $days]) {
            $out[$key] = $this->period($key, $label, $days, $today, $dayTotal, $dayCount, $hourTotal, $quantities[$key]);
        }

        return $out;
    }

    /** Total jumlah terjual per produk untuk tiap periode. */
    private function quantities(?int $onlyUserId, CarbonInterface $from, CarbonInterface $today): array
    {
        $bounds = [];
        foreach (self::PERIODS as $key => [, $days]) {
            $bounds[$key] = $today->copy()->subDays($days - 1)->format('Y-m-d');
        }
        $sum = array_fill_keys(array_keys(self::PERIODS), []);
        $rows = DB::table('v2_sale_items as i')
            ->join('v2_sales as s', 's.id', '=', 'i.sale_id')
            ->where('s.created_at', '>=', $from->copy()->startOfDay()->toDateTimeString())
            ->when($onlyUserId !== null, fn ($q) => $q->where('s.user_id', $onlyUserId))
            ->select(['i.product_id', 'i.name', 'i.quantity', 's.created_at'])
            ->cursor();
        foreach ($rows as $row) {
            $day = substr((string) $row->created_at, 0, 10);
            foreach ($bounds as $key => $start) {
                if ($day < $start) {
                    continue;
                }
                $id = (int) $row->product_id;
                $entry = $sum[$key][$id] ?? ['name' => (string) $row->name, 'qty' => BigDecimal::zero()];
                $entry['qty'] = $entry['qty']->plus(BigDecimal::of((string) $row->quantity));
                $sum[$key][$id] = $entry;
            }
        }

        return $sum;
    }

    private function period(string $key, string $label, int $days, CarbonInterface $today, array $dayTotal, array $dayCount, array $hourTotal, array $quantities): array
    {
        $isToday = $key === 'today';
        $slots = [];
        if ($isToday) {
            for ($h = 0; $h < 24; $h++) {
                $slots[] = ['label' => sprintf('%02d.00', $h), 'amount' => $hourTotal[$h] ?? BigDecimal::zero(), 'show' => $h % 3 === 0];
            }
        } else {
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = $today->copy()->subDays($i);
                $slots[] = ['label' => $date->format('d/m'), 'date' => $date->format('Y-m-d'), 'amount' => $dayTotal[$date->format('Y-m-d')] ?? BigDecimal::zero(), 'show' => $days <= 7 || $i % 5 === 0 || $i === 0];
            }
        }

        $revenue = BigDecimal::zero();
        $count = 0;
        for ($i = 0; $i < $days; $i++) {
            $d = $today->copy()->subDays($i)->format('Y-m-d');
            $revenue = $revenue->plus($dayTotal[$d] ?? BigDecimal::zero());
            $count += $dayCount[$d] ?? 0;
        }
        $previous = BigDecimal::zero();
        $previousCount = 0;
        for ($i = $days; $i < $days * 2; $i++) {
            $d = $today->copy()->subDays($i)->format('Y-m-d');
            $previous = $previous->plus($dayTotal[$d] ?? BigDecimal::zero());
            $previousCount += $dayCount[$d] ?? 0;
        }

        if ($key === 'custom' && $days > 62) {
            $grouped = [];
            foreach (array_chunk($slots, $days > 180 ? 30 : 7) as $chunk) {
                $sum = BigDecimal::zero();
                foreach ($chunk as $slot) {
                    $sum = $sum->plus($slot['amount']);
                }
                $grouped[] = ['label' => $chunk[0]['label'].'–'.end($chunk)['label'], 'date' => $chunk[0]['date'], 'amount' => $sum, 'show' => true];
            }
            $slots = $grouped;
        }
        $max = BigDecimal::zero();
        $peak = null;
        foreach ($slots as $index => $slot) {
            if ($slot['amount']->isGreaterThan($max)) {
                $max = $slot['amount'];
                $peak = $index;
            }
        }
        $ceiling = $this->niceCeiling($max);
        $buckets = [];
        foreach ($slots as $index => $slot) {
            $height = 0;
            if ($ceiling->isGreaterThan(0) && $slot['amount']->isGreaterThan(0)) {
                $height = max(3, $slot['amount']->multipliedBy(100)->dividedBy($ceiling, 0, RoundingMode::HalfUp)->toInt());
            }
            $buckets[] = [
                'label' => $slot['label'], 'date' => $slot['date'] ?? null, 'show' => $slot['show'], 'height' => $height,
                'peak' => $index === $peak,
                'total' => (string) $slot['amount']->toScale(2),
                'formatted_total' => Money::display((string) $slot['amount']->toScale(2)),
            ];
        }
        $axis = [];
        foreach ([0, 50, 100] as $pct) {
            $axis[] = ['pct' => $pct, 'label' => $this->short($ceiling->multipliedBy($pct)->dividedBy(100, 2, RoundingMode::HalfUp))];
        }

        $average = $count > 0 ? $revenue->dividedBy($count, 2, RoundingMode::HalfUp) : BigDecimal::zero();

        return [
            'period' => $key,
            'sub_label' => $label,
            'formatted_revenue' => Money::display((string) $revenue->toScale(2)),
            'transaction_count' => $count,
            'formatted_average' => Money::display((string) $average->toScale(2)),
            'previous_label' => $isToday ? 'kemarin' : 'periode lalu',
            'formatted_previous' => Money::display((string) $previous->toScale(2)),
            'previous_count' => $previousCount,
            'delta' => $this->delta($revenue, $previous),
            'peak' => $peak === null ? null : ['label' => $slots[$peak]['label'], 'date' => $slots[$peak]['date'] ?? null, 'formatted' => Money::display((string) $max->toScale(2)), 'short' => $this->short($max)],
            'revenue' => (string) $revenue->toScale(2),
            'buckets' => $buckets,
            'axis' => $axis,
            'top_products' => $this->top($quantities),
        ];
    }

    /** @return array{pct:int|null, direction:string} naik|turun|tetap|baru */
    private function delta(BigDecimal $current, BigDecimal $previous): array
    {
        if ($previous->isZero()) {
            return ['pct' => null, 'direction' => $current->isZero() ? 'tetap' : 'baru'];
        }
        $pct = $current->minus($previous)->multipliedBy(100)->dividedBy($previous, 0, RoundingMode::HalfUp)->toInt();

        return ['pct' => abs($pct), 'direction' => $pct > 0 ? 'naik' : ($pct < 0 ? 'turun' : 'tetap')];
    }

    private function top(array $quantities): array
    {
        uasort($quantities, fn ($a, $b) => $b['qty']->compareTo($a['qty']));
        $top = array_slice($quantities, 0, self::TOP_LIMIT, true);
        if ($top === []) {
            return [];
        }
        $units = DB::table('products')->whereIn('id', array_keys($top))->pluck('unit', 'id');
        $leader = reset($top)['qty'];
        $rows = [];
        foreach ($top as $id => $entry) {
            $rows[] = [
                'name' => $entry['name'],
                'formatted_qty' => $this->quantity($entry['qty']),
                'unit' => (string) ($units[$id] ?? ''),
                'share' => $leader->isGreaterThan(0) ? max(4, $entry['qty']->multipliedBy(100)->dividedBy($leader, 0, RoundingMode::HalfUp)->toInt()) : 0,
            ];
        }

        return $rows;
    }

    private function quantity(BigDecimal $value): string
    {
        $text = (string) $value->toScale(4, RoundingMode::HalfUp);
        [$int, $frac] = explode('.', $text);
        $frac = rtrim($frac, '0');

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $int).($frac !== '' ? ','.$frac : '');
    }

    /** Batas atas sumbu yang "bulat" (1; 1,5; 2; 2,5; 3; 4; 5; 6; 8 kali pangkat sepuluh). */
    private function niceCeiling(BigDecimal $max): BigDecimal
    {
        if ($max->isLessThanOrEqualTo(0)) {
            return BigDecimal::zero();
        }
        $digits = strlen((string) $max->toScale(0, RoundingMode::Up)->abs()) - 1;
        $base = BigDecimal::of(10)->power($digits);
        foreach (['1', '1.5', '2', '2.5', '3', '4', '5', '6', '8', '10'] as $step) {
            $candidate = $base->multipliedBy($step);
            if ($candidate->isGreaterThanOrEqualTo($max)) {
                return $candidate;
            }
        }

        return $base->multipliedBy(10);
    }

    /** Singkatan untuk sumbu: 1,5 jt, 500 rb. */
    private function short(BigDecimal $value): string
    {
        $units = [['mi', '1000000000'], ['jt', '1000000'], ['rb', '1000']];
        foreach ($units as [$suffix, $size]) {
            if ($value->isGreaterThanOrEqualTo($size)) {
                $scaled = $value->dividedBy($size, 1, RoundingMode::HalfUp);
                $text = rtrim(rtrim((string) $scaled, '0'), '.');

                return str_replace('.', ',', $text).' '.$suffix;
            }
        }

        return (string) $value->toScale(0, RoundingMode::HalfUp);
    }
}
