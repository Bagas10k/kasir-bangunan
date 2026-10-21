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
    <h2>Laporan Laba Rugi (Profit & Loss)</h2>
    
    <div class="filter-box" style="margin-bottom: 20px; background: #f9f9f9; border: 1px solid #ddd; padding: 12px;">
        <form method="GET" action="{{ route('reports.profit_loss') }}" style="display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="start_date">Dari Tanggal</label>
                <input type="date" name="start_date" id="start_date" value="{{ $startDate ?? '' }}" style="padding: 6px;">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="end_date">Sampai Tanggal</label>
                <input type="date" name="end_date" id="end_date" value="{{ $endDate ?? '' }}" style="padding: 6px;">
            </div>
            <div>
                <button type="submit" class="btn" style="padding: 7px 16px;">Tampilkan</button>
                <a href="{{ route('reports.profit_loss') }}" class="btn btn-secondary" style="padding: 7px 14px; text-decoration: none; display: inline-block;">Reset</a>
                <button type="button" class="btn btn-secondary" onclick="window.print()" style="padding: 7px 14px;">Cetak</button>
            </div>
        </form>
    </div>

    <div style="max-width: 650px;">
        <table style="width: 100%;">
            <tbody>
                <tr style="background: #f5f5f5;">
                    <th colspan="2" style="padding: 8px;">1. PENDAPATAN OPERASIONAL</th>
                </tr>
                @forelse($report['revenues'] as $rev)
                <tr>
                    <td style="padding-left: 18px;">{{ $rev['code'] }} - {{ $rev['name'] }}</td>
                    <td style="text-align: right;">Rp {{ $rev['amount'] }}</td>
                </tr>
                @empty
                <tr>
                    <td style="padding-left: 18px; color: #777;">(Belum ada pendapatan pada periode ini)</td>
                    <td style="text-align: right;">Rp 0.00</td>
                </tr>
                @endforelse
                <tr style="font-weight: bold; border-top: 1px solid #ccc;">
                    <td>Total Pendapatan</td>
                    <td style="text-align: right;">Rp {{ $report['total_revenue'] }}</td>
                </tr>

                <tr style="background: #f5f5f5;">
                    <th colspan="2" style="padding: 8px; margin-top: 14px;">2. BEBAN POKOK PENJUALAN (HPP)</th>
                </tr>
                @forelse($report['cogs'] as $c)
                <tr>
                    <td style="padding-left: 18px;">{{ $c['code'] }} - {{ $c['name'] }}</td>
                    <td style="text-align: right;">(Rp {{ $c['amount'] }})</td>
                </tr>
                @empty
                <tr>
                    <td style="padding-left: 18px; color: #777;">(Tidak ada HPP tercatat)</td>
                    <td style="text-align: right;">Rp 0.00</td>
                </tr>
                @endforelse
                <tr style="font-weight: bold; border-top: 1px solid #ccc;">
                    <td>Total HPP</td>
                    <td style="text-align: right;">(Rp {{ $report['total_cogs'] }})</td>
                </tr>

                <tr style="background: #eee; font-weight: bold; border-top: 1px solid #111; border-bottom: 1px solid #111;">
                    <td>LABA KOTOR (GROSS PROFIT)</td>
                    <td style="text-align: right;">Rp {{ $report['gross_profit'] }}</td>
                </tr>

                <tr style="background: #f5f5f5;">
                    <th colspan="2" style="padding: 8px;">3. BEBAN OPERASIONAL</th>
                </tr>
                @forelse($report['expenses'] as $exp)
                <tr>
                    <td style="padding-left: 18px;">{{ $exp['code'] }} - {{ $exp['name'] }}</td>
                    <td style="text-align: right;">(Rp {{ $exp['amount'] }})</td>
                </tr>
                @empty
                <tr>
                    <td style="padding-left: 18px; color: #777;">(Tidak ada beban operasional tercatat)</td>
                    <td style="text-align: right;">Rp 0.00</td>
                </tr>
                @endforelse
                <tr style="font-weight: bold; border-top: 1px solid #ccc;">
                    <td>Total Beban Operasional</td>
                    <td style="text-align: right;">(Rp {{ $report['total_expense'] }})</td>
                </tr>

                <tr style="background: #111; color: #fff; font-size: 15px; font-weight: bold;">
                    <td style="padding: 10px;">LABA BERSIH (NET PROFIT)</td>
                    <td style="padding: 10px; text-align: right;">Rp {{ $report['net_profit'] }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
