@extends('layouts.app')
@section('content')
<style>
    @media (prefers-reduced-motion: reduce) {
        *, ::before, ::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
            scroll-behavior: auto !important;
        }
    }
    .filter-card {
        background-color: #ffffff;
        border: 1px solid var(--border-card);
        border-radius: 18px;
        padding: 16px 20px;
        margin-bottom: 22px;
        box-shadow: 0 4px 16px rgba(45, 106, 79, 0.04);
    }
    .report-table {
        width: 100%;
        border-collapse: collapse;
    }
    .report-table th.section-title {
        background-color: var(--mint-surface);
        color: var(--primary-hover);
        font-size: 11.5px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        padding: 10px 14px;
        border-radius: 8px;
    }
    .report-table td {
        padding: 9px 14px;
        border-bottom: 1px solid #f0f4f1;
        font-size: 13.5px;
    }
    .highlight-card {
        background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%);
        color: #ffffff;
        border-radius: 16px;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 18px;
        box-shadow: 0 6px 18px rgba(27, 67, 50, 0.2);
    }
    @media print {
        header, nav, .sidebar-wrapper, .topbar, .filter-card, .no-print {
            display: none !important;
        }
        .app-layout {
            display: block !important;
        }
        .main-wrapper {
            margin: 0 !important;
        }
        main {
            padding: 0 !important;
            max-width: 100% !important;
        }
        .card {
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
        }
    }
</style>

<div class="card">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
        <h2 style="margin: 0; border: none; padding: 0;">Laporan Laba Rugi (Profit &amp; Loss)</h2>
        <span class="badge" style="font-size: 11px;">Buku Besar Akuntansi</span>
    </div>
    
    <div class="filter-card no-print">
        <form method="GET" action="{{ route('reports.profit_loss') }}" style="display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="start_date">Dari Tanggal</label>
                <input type="date" name="start_date" id="start_date" value="{{ $startDate ?? '' }}" class="form-control" style="border-radius: 9999px; padding: 8px 14px;">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="end_date">Sampai Tanggal</label>
                <input type="date" name="end_date" id="end_date" value="{{ $endDate ?? '' }}" class="form-control" style="border-radius: 9999px; padding: 8px 14px;">
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn" style="border-radius: 9999px; padding: 8px 20px;">Tampilkan</button>
                <a href="{{ route('reports.profit_loss') }}" class="btn btn-secondary" style="border-radius: 9999px; padding: 8px 16px; text-decoration: none; display: inline-block;">Reset</a>
                <button type="button" class="btn btn-secondary" onclick="window.print()" style="border-radius: 9999px; padding: 8px 16px;">Cetak</button>
            </div>
        </form>
    </div>

    <div style="max-width: 680px;">
        <table class="report-table">
            <tbody>
                <tr>
                    <th colspan="2" class="section-title">1. Pendapatan Operasional</th>
                </tr>
                @forelse($report['revenues'] as $rev)
                <tr>
                    <td style="padding-left: 20px;">
                        <span class="sku-code" style="color: var(--primary);">{{ $rev['code'] }}</span> &bull; {{ $rev['name'] }}
                    </td>
                    <td style="text-align: right; font-variant-numeric: tabular-nums;">Rp {{ $rev['amount'] }}</td>
                </tr>
                @empty
                <tr>
                    <td style="padding-left: 20px; color: var(--text-caption);">(Belum ada pendapatan pada periode ini)</td>
                    <td style="text-align: right; font-variant-numeric: tabular-nums;">Rp 0.00</td>
                </tr>
                @endforelse
                <tr style="font-weight: 700; border-top: 1px solid var(--border-subtle);">
                    <td>Total Pendapatan</td>
                    <td style="text-align: right; font-variant-numeric: tabular-nums; color: var(--primary-hover);">Rp {{ $report['total_revenue'] }}</td>
                </tr>

                <tr>
                    <th colspan="2" class="section-title" style="margin-top: 14px;">2. Beban Pokok Penjualan (HPP)</th>
                </tr>
                @forelse($report['cogs'] as $c)
                <tr>
                    <td style="padding-left: 20px;">
                        <span class="sku-code" style="color: var(--primary);">{{ $c['code'] }}</span> &bull; {{ $c['name'] }}
                    </td>
                    <td style="text-align: right; font-variant-numeric: tabular-nums;">(Rp {{ $c['amount'] }})</td>
                </tr>
                @empty
                <tr>
                    <td style="padding-left: 20px; color: var(--text-caption);">(Tidak ada HPP tercatat)</td>
                    <td style="text-align: right; font-variant-numeric: tabular-nums;">Rp 0.00</td>
                </tr>
                @endforelse
                <tr style="font-weight: 700; border-top: 1px solid var(--border-subtle);">
                    <td>Total HPP</td>
                    <td style="text-align: right; font-variant-numeric: tabular-nums; color: var(--danger);">(Rp {{ $report['total_cogs'] }})</td>
                </tr>

                <tr style="background-color: var(--mint-surface); font-weight: 800; border-radius: 8px;">
                    <td style="color: var(--primary-hover);">LABA KOTOR (GROSS PROFIT)</td>
                    <td style="text-align: right; font-variant-numeric: tabular-nums; color: var(--primary-hover); font-size: 15px;">Rp {{ $report['gross_profit'] }}</td>
                </tr>

                <tr>
                    <th colspan="2" class="section-title">3. Beban Operasional</th>
                </tr>
                @forelse($report['expenses'] as $exp)
                <tr>
                    <td style="padding-left: 20px;">
                        <span class="sku-code" style="color: var(--primary);">{{ $exp['code'] }}</span> &bull; {{ $exp['name'] }}
                    </td>
                    <td style="text-align: right; font-variant-numeric: tabular-nums;">(Rp {{ $exp['amount'] }})</td>
                </tr>
                @empty
                <tr>
                    <td style="padding-left: 20px; color: var(--text-caption);">(Tidak ada beban operasional tercatat)</td>
                    <td style="text-align: right; font-variant-numeric: tabular-nums;">Rp 0.00</td>
                </tr>
                @endforelse
                <tr style="font-weight: 700; border-top: 1px solid var(--border-subtle);">
                    <td>Total Beban Operasional</td>
                    <td style="text-align: right; font-variant-numeric: tabular-nums; color: var(--danger);">(Rp {{ $report['total_expense'] }})</td>
                </tr>
            </tbody>
        </table>

        <div class="highlight-card">
            <div>
                <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.85;">Hasil Bersih Periode</div>
                <div style="font-size: 16px; font-weight: 800;">LABA BERSIH (NET PROFIT)</div>
            </div>
            <div class="tabular-nums" style="font-size: 22px; font-weight: 800;">
                Rp {{ $report['net_profit'] }}
            </div>
        </div>
    </div>
</div>
@endsection
