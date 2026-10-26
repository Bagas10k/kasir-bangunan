@extends('layouts.app')
@section('content')
@include('reports._styles')

@php
    $formatMoney = function ($value, bool $withSymbol = false, bool $accountingSign = false) {
        $decimal = \Brick\Math\BigDecimal::of((string) $value)->toScale(2, \Brick\Math\RoundingMode::HalfUp);
        $negative = $decimal->isNegative();
        [$whole, $fraction] = explode('.', ltrim((string) $decimal, '-'));
        $formatted = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole) . ',' . $fraction;
        $formatted = ($withSymbol ? 'Rp ' : '') . $formatted;

        return $negative ? ($accountingSign ? '(' . $formatted . ')' : '-' . $formatted) : $formatted;
    };
    $num = fn ($n) => $formatMoney($n);
    $rp = fn ($n) => $formatMoney($n, true);
    $rpSigned = fn ($n) => $formatMoney($n, true, true);

    $totalPendapatan = \Brick\Math\BigDecimal::of((string) $report['total_revenue'])
        ->plus((string) $report['total_other_income'])->toScale(2, \Brick\Math\RoundingMode::HalfUp);
    $totalBeban = \Brick\Math\BigDecimal::of((string) $report['total_cogs'])
        ->plus((string) $report['total_expense'])->plus((string) $report['total_other_expense'])
        ->toScale(2, \Brick\Math\RoundingMode::HalfUp);
    $net = \Brick\Math\BigDecimal::of((string) $report['net_profit'])->toScale(2, \Brick\Math\RoundingMode::HalfUp);
    $isProfit = !$net->isNegative();
    $periodLabel = ($startDate || $endDate)
        ? 'Periode ' . ($startDate ? \Carbon\Carbon::parse($startDate)->locale('id')->translatedFormat('d M Y') : 'awal pembukuan')
            . ' s.d. ' . ($endDate ? \Carbon\Carbon::parse($endDate)->locale('id')->translatedFormat('d M Y') : 'sekarang')
        : 'Seluruh periode pembukuan';
    $accountCount = count($report['revenues']) + count($report['cogs']) + count($report['expenses'])
        + count($report['other_income']) + count($report['other_expenses']);

    $now = now();
    $presets = [
        'Bulan Ini' => [$now->copy()->startOfMonth()->toDateString(), $now->toDateString()],
        'Bulan Lalu' => [$now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
        'Tahun Ini' => [$now->copy()->startOfYear()->toDateString(), $now->toDateString()],
    ];
    $isPresetActive = fn ($s, $e) => (($startDate ?? '') === $s && ($endDate ?? '') === $e);
@endphp

<div class="card report-shell">
    <header class="statement-header">
        <div class="statement-title">
            <nav class="breadcrumb no-print" aria-label="Breadcrumb">Laporan Keuangan<span class="sep" aria-hidden="true">›</span>Laba Rugi (Income Statement)</nav>
            <h2>Laporan Laba Rugi</h2>
            <p class="report-period">{{ $periodLabel }}</p>
        </div>
        <div class="statement-actions no-print">
            <a href="{{ route('reports.profit_loss.csv', array_filter(['start_date' => $startDate ?? null, 'end_date' => $endDate ?? null])) }}" class="btn btn-secondary report-action">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Unduh CSV
            </a>
            <button type="button" class="btn report-action" onclick="window.print()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Cetak / PDF
            </button>
        </div>
    </header>

    <section class="statement-summary" aria-label="Ringkasan Laba Rugi">
        <div class="summary-item">
            <span class="summary-label">Pendapatan Keseluruhan</span>
            <strong class="summary-value">{{ $rp($totalPendapatan) }}</strong>
        </div>
        <div class="summary-item">
            <span class="summary-label">Total Beban</span>
            <strong class="summary-value">{{ $rp($totalBeban) }}</strong>
        </div>
        <div class="summary-item {{ $isProfit ? 'is-result' : 'is-alert' }}">
            <span class="summary-label">{{ $isProfit ? 'Laba Bersih (Net Profit)' : 'Rugi Bersih (Net Loss)' }}</span>
            <strong class="summary-value">{{ $rpSigned($net) }}</strong>
        </div>
    </section>

    <div class="report-toolbar no-print">
        <div class="report-filter-form">
            <div class="filter-cluster">
                <div class="preset-cluster">
                    <span class="filter-caption">Periode cepat</span>
                    <div class="period-presets">
                        @foreach($presets as $label => $range)
                            <a class="preset-btn {{ $isPresetActive($range[0], $range[1]) ? 'active' : '' }}"
                               href="{{ route('reports.profit_loss', ['start_date' => $range[0], 'end_date' => $range[1]]) }}">{{ $label }}</a>
                        @endforeach
                        <a class="preset-btn" href="{{ route('reports.profit_loss') }}">Semua</a>
                    </div>
                </div>
                <details class="custom-period">
                    <summary>Rentang khusus</summary>
                    <form method="GET" action="{{ route('reports.profit_loss') }}" class="date-cluster">
                        <div class="field">
                            <label for="start_date">Dari</label>
                            <input type="date" name="start_date" id="start_date" value="{{ $startDate ?? '' }}">
                        </div>
                        <span class="range-sep">s.d.</span>
                        <div class="field">
                            <label for="end_date">Sampai</label>
                            <input type="date" name="end_date" id="end_date" value="{{ $endDate ?? '' }}">
                        </div>
                        <button type="submit" class="btn report-apply">Tampilkan</button>
                        <a href="{{ route('reports.profit_loss') }}" class="report-reset">Reset</a>
                    </form>
                </details>
            </div>
        </div>
    </div>

    <div class="ledger-caption">
        <strong>Rincian akun · {{ $accountCount }} akun</strong>
        <span>Mata uang Rupiah · angka dalam nilai penuh</span>
    </div>
    <div class="report-ledger">
        <table class="report-table" aria-label="Rincian Laporan Laba Rugi">
            <thead>
                <tr>
                    <th scope="col" style="width: 90px;">Kode</th>
                    <th scope="col">Nama Akun</th>
                    <th scope="col" class="num">Jumlah (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <tr><th scope="row" colspan="3" class="section-title">1. Pendapatan Operasional</th></tr>
                @forelse($report['revenues'] as $rev)
                <tr>
                    <td class="code"><span class="sku-code">{{ $rev['code'] }}</span></td>
                    <td>{{ $rev['name'] }}</td>
                    <td class="amount">{{ $num($rev['amount']) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="2" style="color: var(--text-caption);">(Belum ada pendapatan pada periode ini)</td>
                    <td class="amount">0,00</td>
                </tr>
                @endforelse
                <tr class="total">
                    <td></td>
                    <td>Total Pendapatan</td>
                    <td class="amount">{{ $num($report['total_revenue']) }}</td>
                </tr>

                <tr><th scope="row" colspan="3" class="section-title">2. Beban Pokok Penjualan (HPP)</th></tr>
                @forelse($report['cogs'] as $c)
                <tr>
                    <td class="code"><span class="sku-code">{{ $c['code'] }}</span></td>
                    <td>{{ $c['name'] }}</td>
                    <td class="amount neg">({{ $num($c['amount']) }})</td>
                </tr>
                @empty
                <tr>
                    <td colspan="2" style="color: var(--text-caption);">(Tidak ada HPP tercatat)</td>
                    <td class="amount">0,00</td>
                </tr>
                @endforelse
                <tr class="total">
                    <td></td>
                    <td>Total HPP</td>
                    <td class="amount neg">({{ $num($report['total_cogs']) }})</td>
                </tr>
                <tr class="grand">
                    <td></td>
                    <td>LABA KOTOR (GROSS PROFIT)</td>
                    <td class="amount">{{ $num($report['gross_profit']) }}</td>
                </tr>

                <tr><th scope="row" colspan="3" class="section-title">3. Beban Operasional</th></tr>
                @forelse($report['expenses'] as $exp)
                <tr>
                    <td class="code"><span class="sku-code">{{ $exp['code'] }}</span></td>
                    <td>{{ $exp['name'] }}</td>
                    <td class="amount neg">({{ $num($exp['amount']) }})</td>
                </tr>
                @empty
                <tr>
                    <td colspan="2" style="color: var(--text-caption);">(Tidak ada beban operasional tercatat)</td>
                    <td class="amount">0,00</td>
                </tr>
                @endforelse
                <tr class="total">
                    <td></td>
                    <td>Total Beban Operasional</td>
                    <td class="amount neg">({{ $num($report['total_expense']) }})</td>
                </tr>

                @if(count($report['other_income']) > 0)
                <tr><th scope="row" colspan="3" class="section-title">4. Pendapatan Lain-lain</th></tr>
                @foreach($report['other_income'] as $oi)
                <tr>
                    <td class="code"><span class="sku-code">{{ $oi['code'] }}</span></td>
                    <td>{{ $oi['name'] }}</td>
                    <td class="amount">{{ $num($oi['amount']) }}</td>
                </tr>
                @endforeach
                <tr class="total">
                    <td></td>
                    <td>Total Pendapatan Lain-lain</td>
                    <td class="amount">{{ $num($report['total_other_income']) }}</td>
                </tr>
                @endif

                @if(count($report['other_expenses']) > 0)
                <tr><th scope="row" colspan="3" class="section-title">5. Beban Lain-lain</th></tr>
                @foreach($report['other_expenses'] as $oe)
                <tr>
                    <td class="code"><span class="sku-code">{{ $oe['code'] }}</span></td>
                    <td>{{ $oe['name'] }}</td>
                    <td class="amount neg">({{ $num($oe['amount']) }})</td>
                </tr>
                @endforeach
                <tr class="total">
                    <td></td>
                    <td>Total Beban Lain-lain</td>
                    <td class="amount neg">({{ $num($report['total_other_expense']) }})</td>
                </tr>
                @endif

                <tr class="grand">
                    <td></td>
                    <td>LABA BERSIH (NET PROFIT)</td>
                    <td class="amount">{{ $num($report['net_profit']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection