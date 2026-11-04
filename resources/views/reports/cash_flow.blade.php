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

    $netChange = \Brick\Math\BigDecimal::of((string) $report['net_cash_change'])->toScale(2, \Brick\Math\RoundingMode::HalfUp);
    $isNetPositive = !$netChange->isNegative();
    $reconciled = (bool) $report['is_reconciled'];

    $periodLabel = ($startDate || $endDate)
        ? 'Periode ' . ($startDate ? \Carbon\Carbon::parse($startDate)->locale('id')->translatedFormat('d M Y') : 'awal pembukuan')
            . ' s.d. ' . ($endDate ? \Carbon\Carbon::parse($endDate)->locale('id')->translatedFormat('d M Y') : 'sekarang')
        : 'Seluruh periode pembukuan';

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
            <nav class="breadcrumb no-print" aria-label="Breadcrumb">Laporan Keuangan<span class="sep" aria-hidden="true">›</span>Arus Kas (Cash Flow)</nav>
            <h2>Laporan Arus Kas</h2>
            <p class="report-period">{{ $periodLabel }} · Metode Langsung</p>
        </div>
        <div class="statement-actions no-print">
            <a href="{{ route('reports.cash_flow.csv', array_filter(['start_date' => $startDate ?? null, 'end_date' => $endDate ?? null])) }}" class="btn btn-secondary report-action">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Unduh CSV
            </a>
            <button type="button" class="btn report-action" onclick="window.print()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Cetak / PDF
            </button>
        </div>
    </header>

    <section class="statement-summary" aria-label="Ringkasan Arus Kas">
        <div class="summary-item">
            <span class="summary-label">Saldo Awal Kas & Bank</span>
            <strong class="summary-value">{{ $rp($report['beginning_cash']) }}</strong>
        </div>
        <div class="summary-item {{ $isNetPositive ? 'is-result' : 'is-alert' }}">
            <span class="summary-label">{{ $isNetPositive ? 'Kenaikan Bersih Kas' : 'Penurunan Bersih Kas' }}</span>
            <strong class="summary-value">{{ $rpSigned($report['net_cash_change']) }}</strong>
        </div>
        <div class="summary-item is-result">
            <span class="summary-label">Saldo Akhir Kas & Bank</span>
            <strong class="summary-value">{{ $rp($report['ending_cash']) }}</strong>
        </div>
    </section>

    <div class="report-toolbar no-print">
        <div class="report-filter-form">
            <div class="filter-cluster">
                <div class="preset-cluster">
                    <span class="filter-caption">Rentang cepat</span>
                    <div class="period-presets">
                        @foreach($presets as $label => [$s, $e])
                            <a class="preset-btn {{ $isPresetActive($s, $e) ? 'active' : '' }}"
                               href="{{ route('reports.cash_flow', ['start_date' => $s, 'end_date' => $e]) }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
                <details class="custom-period">
                    <summary>Rentang tanggal</summary>
                    <form method="GET" action="{{ route('reports.cash_flow') }}" class="date-cluster">
                        <div class="field">
                            <label for="start_date">Mulai</label>
                            <input type="date" name="start_date" id="start_date" value="{{ $startDate ?? '' }}">
                        </div>
                        <span class="range-sep" aria-hidden="true">s.d.</span>
                        <div class="field">
                            <label for="end_date">Sampai</label>
                            <input type="date" name="end_date" id="end_date" value="{{ $endDate ?? '' }}">
                        </div>
                        <button type="submit" class="btn report-apply">Tampilkan</button>
                        <a href="{{ route('reports.cash_flow') }}" class="report-reset">Reset</a>
                    </form>
                </details>
            </div>
        </div>
    </div>

    <div class="ledger-caption">
        <strong>Aktivitas Arus Kas · Buku Besar Berpasangan</strong>
        <span>Status rekonsiliasi: {{ $reconciled ? 'Tervalidasi Seimbang' : 'Periksa Selisih' }}</span>
    </div>

    <div class="report-ledger">
        <table class="report-table" aria-label="Rincian Laporan Arus Kas">
            <thead>
                <tr>
                    <th scope="col" style="width: 140px;">Arus</th>
                    <th scope="col">Keterangan Arus Kas</th>
                    <th scope="col" class="num">Nominal (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <!-- 1. AKTIVITAS OPERASI -->
                <tr><th scope="row" colspan="3" class="section-title">1. Arus Kas dari Aktivitas Operasi</th></tr>
                @forelse($report['operating_activities']['inflows'] as $in)
                <tr>
                    <td class="code"><span class="sku-code">Penerimaan</span></td>
                    <td>{{ $in['label'] }}</td>
                    <td class="amount">{{ $num($in['amount']) }}</td>
                </tr>
                @empty
                @endforelse

                @forelse($report['operating_activities']['outflows'] as $out)
                <tr>
                    <td class="code"><span class="sku-code">Pengeluaran</span></td>
                    <td>{{ $out['label'] }}</td>
                    <td class="amount neg">-{{ $num($out['amount']) }}</td>
                </tr>
                @empty
                @endforelse

                @if(empty($report['operating_activities']['inflows']) && empty($report['operating_activities']['outflows']))
                <tr><td colspan="3" style="text-align:center; color:var(--text-muted); padding:12px;">Tidak ada mutasi kas operasional pada periode ini.</td></tr>
                @endif
                <tr class="total">
                    <td colspan="2"><strong>Arus Kas Bersih dari Aktivitas Operasi</strong></td>
                    <td class="amount"><strong>{{ $num($report['operating_activities']['net_cash']) }}</strong></td>
                </tr>

                <!-- 2. AKTIVITAS INVESTASI -->
                <tr><th scope="row" colspan="3" class="section-title">2. Arus Kas dari Aktivitas Investasi</th></tr>
                @forelse($report['investing_activities']['inflows'] as $in)
                <tr>
                    <td class="code"><span class="sku-code">Penerimaan</span></td>
                    <td>{{ $in['label'] }}</td>
                    <td class="amount">{{ $num($in['amount']) }}</td>
                </tr>
                @empty
                @endforelse

                @forelse($report['investing_activities']['outflows'] as $out)
                <tr>
                    <td class="code"><span class="sku-code">Pengeluaran</span></td>
                    <td>{{ $out['label'] }}</td>
                    <td class="amount neg">-{{ $num($out['amount']) }}</td>
                </tr>
                @empty
                @endforelse

                @if(empty($report['investing_activities']['inflows']) && empty($report['investing_activities']['outflows']))
                <tr><td colspan="3" style="text-align:center; color:var(--text-muted); padding:8px;">Tidak ada aktivitas investasi pada periode ini.</td></tr>
                @endif
                <tr class="total">
                    <td colspan="2"><strong>Arus Kas Bersih dari Aktivitas Investasi</strong></td>
                    <td class="amount"><strong>{{ $num($report['investing_activities']['net_cash']) }}</strong></td>
                </tr>

                <!-- 3. AKTIVITAS PENDANAAN -->
                <tr><th scope="row" colspan="3" class="section-title">3. Arus Kas dari Aktivitas Pendanaan</th></tr>
                @forelse($report['financing_activities']['inflows'] as $in)
                <tr>
                    <td class="code"><span class="sku-code">Penerimaan</span></td>
                    <td>{{ $in['label'] }}</td>
                    <td class="amount">{{ $num($in['amount']) }}</td>
                </tr>
                @empty
                @endforelse

                @forelse($report['financing_activities']['outflows'] as $out)
                <tr>
                    <td class="code"><span class="sku-code">Pengeluaran</span></td>
                    <td>{{ $out['label'] }}</td>
                    <td class="amount neg">-{{ $num($out['amount']) }}</td>
                </tr>
                @empty
                @endforelse

                @if(empty($report['financing_activities']['inflows']) && empty($report['financing_activities']['outflows']))
                <tr><td colspan="3" style="text-align:center; color:var(--text-muted); padding:8px;">Tidak ada aktivitas pendanaan pada periode ini.</td></tr>
                @endif
                <tr class="total">
                    <td colspan="2"><strong>Arus Kas Bersih dari Aktivitas Pendanaan</strong></td>
                    <td class="amount"><strong>{{ $num($report['financing_activities']['net_cash']) }}</strong></td>
                </tr>

                <!-- REKONSILIASI KAS & BANK -->
                <tr><th scope="row" colspan="3" class="section-title">Rekonsiliasi Saldo Kas & Bank</th></tr>
                <tr>
                    <td class="code"><span class="sku-code">Saldo Awal</span></td>
                    <td>Saldo Awal Kas & Bank (sebelum periode)</td>
                    <td class="amount">{{ $num($report['beginning_cash']) }}</td>
                </tr>
                <tr>
                    <td class="code"><span class="sku-code">Mutasi Bersih</span></td>
                    <td>Kenaikan / (Penurunan) Bersih Kas Periode Ini</td>
                    <td class="amount">{{ $num($report['net_cash_change']) }}</td>
                </tr>
                <tr class="grand">
                    <td colspan="2"><strong>Saldo Akhir Kas & Bank</strong></td>
                    <td class="amount"><strong>{{ $num($report['ending_cash']) }}</strong></td>
                </tr>

                <!-- RINCIAN SALDO AKHIR PER AKUN -->
                <tr><th scope="row" colspan="3" class="section-title">Rincian Saldo Kas & Bank per Akun</th></tr>
                @foreach($report['cash_breakdown'] as $b)
                <tr>
                    <td class="code"><span class="sku-code">{{ $b['code'] }}</span></td>
                    <td>{{ $b['name'] }}</td>
                    <td class="amount">{{ $num($b['balance']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
