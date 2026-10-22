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
    .balance-status-box {
        margin-top: 18px;
        padding: 14px 18px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border: 1px solid var(--mint-border);
        background-color: var(--mint-surface);
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
        <h2 style="margin: 0; border: none; padding: 0;">Laporan Neraca Keuangan (Balance Sheet)</h2>
        <span class="badge" style="font-size: 11px;">Buku Besar Akuntansi</span>
    </div>

    <div class="filter-card no-print">
        <form method="GET" action="{{ route('reports.balance_sheet') }}" style="display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="as_of_date">Per Tanggal (As of Date)</label>
                <input type="date" name="as_of_date" id="as_of_date" value="{{ $asOfDate ?? date('Y-m-d') }}" class="form-control" style="border-radius: 9999px; padding: 8px 14px;">
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn" style="border-radius: 9999px; padding: 8px 20px;">Tampilkan</button>
                <button type="button" class="btn btn-secondary" onclick="window.print()" style="border-radius: 9999px; padding: 8px 16px;">Cetak</button>
            </div>
        </form>
    </div>

    <div class="grid-2">
        <div>
            <h3 style="color: var(--primary-hover); font-size: 15px; margin-top: 0; display: flex; align-items: center; gap: 8px;">
                <span style="width: 4px; height: 14px; background-color: var(--accent); border-radius: 2px; display: inline-block;"></span>
                Aktiva (Aset)
            </h3>
            <table>
                <thead>
                    <tr>
                        <th>Akun Aset</th>
                        <th style="text-align: right;">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($report['assets'] as $a)
                    <tr>
                        <td>
                            <span class="sku-code" style="color: var(--primary);">{{ $a['code'] }}</span> &bull; {{ $a['name'] }}
                        </td>
                        <td style="text-align: right; font-variant-numeric: tabular-nums;">Rp {{ $a['amount'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" style="color: var(--text-caption); padding: 14px;">(Tidak ada saldo aset tercatat)</td>
                    </tr>
                    @endforelse
                    <tr style="font-weight: 800; border-top: 2px solid var(--primary); font-size: 14px; background-color: var(--mint-surface);">
                        <td style="color: var(--primary-hover);">TOTAL ASET</td>
                        <td style="text-align: right; font-variant-numeric: tabular-nums; color: var(--primary-hover);">Rp {{ $report['total_assets'] }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div>
            <h3 style="color: var(--primary-hover); font-size: 15px; margin-top: 0; display: flex; align-items: center; gap: 8px;">
                <span style="width: 4px; height: 14px; background-color: var(--accent); border-radius: 2px; display: inline-block;"></span>
                Pasiva (Kewajiban &amp; Ekuitas)
            </h3>
            <table>
                <thead>
                    <tr>
                        <th>Akun Pasiva</th>
                        <th style="text-align: right;">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th colspan="2" style="background-color: var(--mint-surface); color: var(--primary-hover); font-size: 11px; padding: 6px 10px; text-transform: uppercase;">Kewajiban (Liabilitas)</th>
                    </tr>
                    @forelse($report['liabilities'] as $l)
                    <tr>
                        <td style="padding-left: 20px;">
                            <span class="sku-code" style="color: var(--primary);">{{ $l['code'] }}</span> &bull; {{ $l['name'] }}
                        </td>
                        <td style="text-align: right; font-variant-numeric: tabular-nums;">Rp {{ $l['amount'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td style="padding-left: 20px; color: var(--text-caption);">(Tidak ada hutang / kewajiban)</td>
                        <td style="text-align: right; font-variant-numeric: tabular-nums;">Rp 0.00</td>
                    </tr>
                    @endforelse
                    <tr style="font-weight: 700; border-top: 1px solid var(--border-subtle);">
                        <td>Total Kewajiban</td>
                        <td style="text-align: right; font-variant-numeric: tabular-nums;">Rp {{ $report['total_liabilities'] }}</td>
                    </tr>

                    <tr>
                        <th colspan="2" style="background-color: var(--mint-surface); color: var(--primary-hover); font-size: 11px; padding: 6px 10px; text-transform: uppercase;">Ekuitas Modal</th>
                    </tr>
                    @forelse($report['equity'] as $eq)
                    <tr>
                        <td style="padding-left: 20px;">
                            <span class="sku-code" style="color: var(--primary);">{{ $eq['code'] }}</span> &bull; {{ $eq['name'] }}
                        </td>
                        <td style="text-align: right; font-variant-numeric: tabular-nums;">Rp {{ $eq['amount'] }}</td>
                    </tr>
                    @empty
                    @endforelse
                    <tr>
                        <td style="padding-left: 20px;">Laba Tahun Berjalan (Net Profit)</td>
                        <td style="text-align: right; font-variant-numeric: tabular-nums;">Rp {{ $report['retained_earnings'] }}</td>
                    </tr>
                    <tr style="font-weight: 700; border-top: 1px solid var(--border-subtle);">
                        <td>Total Ekuitas</td>
                        <td style="text-align: right; font-variant-numeric: tabular-nums;">Rp {{ $report['total_equity'] }}</td>
                    </tr>

                    <tr style="font-weight: 800; border-top: 2px solid var(--primary); font-size: 14px; background-color: var(--mint-surface);">
                        <td style="color: var(--primary-hover);">TOTAL PASIVA</td>
                        <td style="text-align: right; font-variant-numeric: tabular-nums; color: var(--primary-hover);">Rp {{ $report['total_liabilities_and_equity'] }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="balance-status-box" style="{{ $report['is_balanced'] ? '' : 'background-color: #fde8e8; border-color: #f5c2c7;' }}">
                <div>
                    <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-caption);">Kondisi Neraca</div>
                    <strong style="color: {{ $report['is_balanced'] ? 'var(--primary-hover)' : 'var(--danger)' }}; font-size: 13.5px;">Status Keseimbangan:</strong>
                </div>
                <span class="badge" style="background-color: {{ $report['is_balanced'] ? '#ffffff' : '#ffffff' }}; color: {{ $report['is_balanced'] ? 'var(--primary-hover)' : 'var(--danger)' }}; border-color: {{ $report['is_balanced'] ? 'var(--mint-border)' : '#f5c2c7' }}; font-weight: 800;">
                    {{ $report['is_balanced'] ? '● SEIMBANG (BALANCED)' : '● TIDAK SEIMBANG' }}
                </span>
            </div>
        </div>
    </div>
</div>
@endsection
