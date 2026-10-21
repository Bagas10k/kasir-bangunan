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
    input:focus-visible, select:focus-visible, textarea:focus-visible, button:focus-visible, a:focus-visible {
        outline: 2px solid #111;
        outline-offset: 2px;
    }
    .form-group {
        margin-bottom: 12px;
    }
    .form-group label {
        display: block;
        margin-bottom: 4px;
        font-weight: bold;
        font-size: 12px;
        text-transform: uppercase;
        color: #222;
    }
    .btn-secondary {
        background: #eee;
        color: #111;
        border: 1px solid #ccc;
    }
    .btn-secondary:hover {
        background: #ddd;
    }
    @media print {
        header, nav, .filter-box {
            display: none !important;
        }
        main {
            padding: 0 !important;
            max-width: 100% !important;
        }
        .card {
            border: none !important;
            padding: 0 !important;
        }
    }
</style>

<div class="card">
    <h2>Laporan Neraca Keuangan (Balance Sheet)</h2>

    <div class="filter-box" style="margin-bottom: 20px; background: #f9f9f9; border: 1px solid #ddd; padding: 12px;">
        <form method="GET" action="{{ route('reports.balance_sheet') }}" style="display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="as_of_date">Per Tanggal (As of Date)</label>
                <input type="date" name="as_of_date" id="as_of_date" value="{{ $asOfDate ?? date('Y-m-d') }}" style="padding: 6px;">
            </div>
            <div>
                <button type="submit" class="btn" style="padding: 7px 16px;">Tampilkan</button>
                <button type="button" class="btn btn-secondary" onclick="window.print()" style="padding: 7px 14px;">Cetak</button>
            </div>
        </form>
    </div>

    <div class="grid-2">
        <div>
            <h3>Aktiva (Aset)</h3>
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
                        <td>{{ $a['code'] }} - {{ $a['name'] }}</td>
                        <td style="text-align: right;">Rp {{ $a['amount'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" style="color: #777;">(Tidak ada saldo aset tercatat)</td>
                    </tr>
                    @endforelse
                    <tr style="font-weight: bold; border-top: 2px solid #111; font-size: 14px;">
                        <td>TOTAL ASET</td>
                        <td style="text-align: right;">Rp {{ $report['total_assets'] }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div>
            <h3>Pasiva (Kewajiban & Ekuitas)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Akun Pasiva</th>
                        <th style="text-align: right;">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="background: #f5f5f5;">
                        <th colspan="2" style="padding: 6px 8px;">Kewajiban (Liabilitas)</th>
                    </tr>
                    @forelse($report['liabilities'] as $l)
                    <tr>
                        <td style="padding-left: 18px;">{{ $l['code'] }} - {{ $l['name'] }}</td>
                        <td style="text-align: right;">Rp {{ $l['amount'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td style="padding-left: 18px; color: #777;">(Tidak ada hutang / kewajiban)</td>
                        <td style="text-align: right;">Rp 0.00</td>
                    </tr>
                    @endforelse
                    <tr style="font-weight: bold; border-top: 1px solid #ccc;">
                        <td>Total Kewajiban</td>
                        <td style="text-align: right;">Rp {{ $report['total_liabilities'] }}</td>
                    </tr>

                    <tr style="background: #f5f5f5;">
                        <th colspan="2" style="padding: 6px 8px;">Ekuitas Modal</th>
                    </tr>
                    @forelse($report['equity'] as $eq)
                    <tr>
                        <td style="padding-left: 18px;">{{ $eq['code'] }} - {{ $eq['name'] }}</td>
                        <td style="text-align: right;">Rp {{ $eq['amount'] }}</td>
                    </tr>
                    @empty
                    @endforelse
                    <tr>
                        <td style="padding-left: 18px;">Laba Tahun Berjalan (Net Profit)</td>
                        <td style="text-align: right;">Rp {{ $report['retained_earnings'] }}</td>
                    </tr>
                    <tr style="font-weight: bold; border-top: 1px solid #ccc;">
                        <td>Total Ekuitas</td>
                        <td style="text-align: right;">Rp {{ $report['total_equity'] }}</td>
                    </tr>

                    <tr style="font-weight: bold; border-top: 2px solid #111; font-size: 14px;">
                        <td>TOTAL PASIVA</td>
                        <td style="text-align: right;">Rp {{ $report['total_liabilities_and_equity'] }}</td>
                    </tr>
                </tbody>
            </table>

            <div style="margin-top: 16px; padding: 10px; border: 1px solid #111; background: {{ $report['is_balanced'] ? '#f5f5f5' : '#fff' }};">
                <strong>Status Keseimbangan Neraca:</strong>
                <span style="display: inline-block; padding: 2px 8px; font-weight: bold;">
                    {{ $report['is_balanced'] ? 'SEIMBANG (BALANCED)' : 'TIDAK SEIMBANG (UNBALANCED)' }}
                </span>
            </div>
        </div>
    </div>
</div>
@endsection
