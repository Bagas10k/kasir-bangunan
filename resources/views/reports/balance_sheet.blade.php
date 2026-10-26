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

    $balanced = (bool) $report['is_balanced'];
    $difference = \Brick\Math\BigDecimal::of((string) $report['total_assets'])
        ->minus((string) $report['total_liabilities_and_equity'])
        ->toScale(2, \Brick\Math\RoundingMode::HalfUp);
    $totalEquityIncludingProfit = \Brick\Math\BigDecimal::of((string) $report['total_equity'])
        ->toScale(2, \Brick\Math\RoundingMode::HalfUp);
    $recordedEquity = $totalEquityIncludingProfit
        ->minus((string) $report['retained_earnings'])->toScale(2, \Brick\Math\RoundingMode::HalfUp);
    $accountCount = count($report['assets']) + count($report['liabilities']) + count($report['equity']);

    $now = now();
    $presets = [
        'Hari Ini' => $now->toDateString(),
        'Akhir Bulan Lalu' => $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
    ];
    $isPresetActive = fn ($d) => (($asOfDate ?? '') === $d);
    $asOfLabel = \Carbon\Carbon::parse($asOfDate ?? date('Y-m-d'))->locale('id')->translatedFormat('d M Y');
@endphp

<div class="card report-shell">
    <header class="statement-header">
        <div class="statement-title">
            <nav class="breadcrumb no-print" aria-label="Breadcrumb">Laporan Keuangan<span class="sep" aria-hidden="true">›</span>Neraca (Balance Sheet)</nav>
            <h2>Laporan Neraca Keuangan</h2>
            <p class="report-period">Posisi keuangan per {{ $asOfLabel }}</p>
        </div>
        <div class="statement-actions no-print">
            <a href="{{ route('reports.balance_sheet.csv', ['as_of_date' => $asOfDate ?? null]) }}" class="btn btn-secondary report-action">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Unduh CSV
            </a>
            <button type="button" class="btn report-action" onclick="window.print()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Cetak / PDF
            </button>
        </div>
    </header>

    <section class="statement-summary" aria-label="Ringkasan Neraca">
        <div class="summary-item">
            <span class="summary-label">Total Aset (Aktiva)</span>
            <strong class="summary-value">{{ $rp($report['total_assets']) }}</strong>
        </div>
        <div class="summary-item">
            <span class="summary-label">Total Pasiva (Kewajiban + Ekuitas)</span>
            <strong class="summary-value">{{ $rp($report['total_liabilities_and_equity']) }}</strong>
        </div>
        <div class="summary-item {{ $balanced ? 'is-result' : 'is-alert' }}">
            <span class="summary-label">{{ $balanced ? 'Status Neraca' : 'Selisih Aset − Pasiva' }}</span>
            <strong class="summary-value">{{ $balanced ? 'SEIMBANG' : $rpSigned($difference) }}</strong>
        </div>
    </section>

    <div class="report-toolbar no-print">
        <div class="report-filter-form">
            <div class="filter-cluster">
                <div class="preset-cluster">
                    <span class="filter-caption">Tanggal cepat</span>
                    <div class="period-presets">
                        @foreach($presets as $label => $d)
                            <a class="preset-btn {{ $isPresetActive($d) ? 'active' : '' }}"
                               href="{{ route('reports.balance_sheet', ['as_of_date' => $d]) }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
                <details class="custom-period">
                    <summary>Tanggal khusus</summary>
                    <form method="GET" action="{{ route('reports.balance_sheet') }}" class="date-cluster">
                        <div class="field">
                            <label for="as_of_date">Per Tanggal</label>
                            <input type="date" name="as_of_date" id="as_of_date" value="{{ $asOfDate ?? date('Y-m-d') }}">
                        </div>
                        <button type="submit" class="btn report-apply">Tampilkan</button>
                        <a href="{{ route('reports.balance_sheet') }}" class="report-reset">Reset</a>
                    </form>
                </details>
            </div>
        </div>
    </div>

    <div class="ledger-caption">
        <strong>Posisi akun · {{ $accountCount }} akun</strong>
        <span>Mata uang Rupiah · angka dalam nilai penuh</span>
    </div>
    <div class="report-ledger">
        <table class="report-table" aria-label="Rincian Laporan Neraca Keuangan">
            <thead>
                <tr>
                    <th scope="col" style="width: 90px;">Kode</th>
                    <th scope="col">Nama Akun</th>
                    <th scope="col" class="num">Jumlah (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <tr><th scope="row" colspan="3" class="section-title">Aktiva (Aset)</th></tr>
                @forelse($report['assets'] as $a)
                <tr>
                    <td class="code"><span class="sku-code">{{ $a['code'] }}</span></td>
                    <td>{{ $a['name'] }}</td>
                    <td class="amount">{{ $num($a['amount']) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="2" style="color: var(--text-caption);">(Tidak ada saldo aset tercatat)</td>
                    <td class="amount">0,00</td>
                </tr>
                @endforelse
                <tr class="grand">
                    <td></td>
                    <td>TOTAL ASET (AKTIVA)</td>
                    <td class="amount">{{ $num($report['total_assets']) }}</td>
                </tr>

                <tr><th scope="row" colspan="3" class="section-title">Kewajiban (Liabilitas)</th></tr>
                @forelse($report['liabilities'] as $l)
                <tr>
                    <td class="code"><span class="sku-code">{{ $l['code'] }}</span></td>
                    <td>{{ $l['name'] }}</td>
                    <td class="amount">{{ $num($l['amount']) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="2" style="color: var(--text-caption);">(Tidak ada kewajiban tercatat)</td>
                    <td class="amount">0,00</td>
                </tr>
                @endforelse
                <tr class="total">
                    <td></td>
                    <td>Total Kewajiban</td>
                    <td class="amount">{{ $num($report['total_liabilities']) }}</td>
                </tr>

                <tr><th scope="row" colspan="3" class="section-title">Ekuitas (Modal)</th></tr>
                @forelse($report['equity'] as $eq)
                <tr>
                    <td class="code"><span class="sku-code">{{ $eq['code'] }}</span></td>
                    <td>{{ $eq['name'] }}</td>
                    <td class="amount">{{ $num($eq['amount']) }}</td>
                </tr>
                @empty
                @endforelse
                <tr class="total">
                    <td></td>
                    <td>Subtotal Modal Tercatat</td>
                    <td class="amount">{{ $num($recordedEquity) }}</td>
                </tr>
                <tr>
                    <td></td>
                    <td>Laba Tahun Berjalan (Net Profit)</td>
                    <td class="amount">{{ $num($report['retained_earnings']) }}</td>
                </tr>
                <tr class="total">
                    <td></td>
                    <td>Total Ekuitas Termasuk Laba Berjalan</td>
                    <td class="amount">{{ $num($totalEquityIncludingProfit) }}</td>
                </tr>

                <tr class="grand">
                    <td></td>
                    <td>TOTAL PASIVA (KEWAJIBAN + EKUITAS)</td>
                    <td class="amount">{{ $num($report['total_liabilities_and_equity']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection