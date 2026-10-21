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
    .receipt-box {
        max-width: 480px;
        margin: 0 auto;
        background: #fff;
        border: 1px solid #111;
        padding: 24px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
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
        header, nav, .no-print {
            display: none !important;
        }
        main {
            padding: 0 !important;
            max-width: 100% !important;
        }
        .receipt-box {
            border: none !important;
            padding: 0 !important;
            max-width: 100% !important;
        }
    }
</style>

<div class="receipt-box">
    <div style="text-align: center; border-bottom: 1px solid #111; padding-bottom: 12px; margin-bottom: 12px;">
        <h2 style="margin: 0; font-size: 16px; border: none; padding: 0;">STRUK PENJUALAN</h2>
        <div style="font-size: 12px; color: #555; margin-top: 4px;">KASIR MATERIAL & BANGUNAN</div>
    </div>

    <table style="font-size: 12px; margin-bottom: 12px; width: 100%;">
        <tr>
            <td style="padding: 2px 0; border: none; width: 120px;">No. Invoice</td>
            <td style="padding: 2px 0; border: none;">: <strong>{{ $sale->invoice_number }}</strong></td>
        </tr>
        <tr>
            <td style="padding: 2px 0; border: none;">Tanggal</td>
            <td style="padding: 2px 0; border: none;">: {{ $sale->created_at }}</td>
        </tr>
        <tr>
            <td style="padding: 2px 0; border: none;">Pelanggan</td>
            <td style="padding: 2px 0; border: none;">: {{ strtoupper($sale->customer_type) }}</td>
        </tr>
        <tr>
            <td style="padding: 2px 0; border: none;">Metode Bayar</td>
            <td style="padding: 2px 0; border: none;">: {{ strtoupper($sale->payment_method) }}</td>
        </tr>
    </table>

    <table style="font-size: 12px; margin-bottom: 12px;">
        <thead>
            <tr style="border-top: 1px solid #111; border-bottom: 1px solid #111;">
                <th style="padding: 6px 0; background: none;">Barang</th>
                <th style="padding: 6px 0; background: none; text-align: center;">Qty</th>
                <th style="padding: 6px 0; background: none; text-align: right;">Harga</th>
                <th style="padding: 6px 0; background: none; text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td style="padding: 6px 0;">{{ $item->name }}</td>
                <td style="padding: 6px 0; text-align: center;">{{ $item->quantity }} {{ $item->unit }}</td>
                <td style="padding: 6px 0; text-align: right;">{{ number_format($item->unit_price, 2) }}</td>
                <td style="padding: 6px 0; text-align: right;">{{ number_format($item->subtotal, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="border-top: 1px solid #111; padding-top: 8px; font-size: 13px;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
            <span>Total Belanja:</span>
            <strong>Rp {{ number_format($sale->total_amount, 2) }}</strong>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
            <span>Total Bayar:</span>
            <span>Rp {{ number_format($sale->paid_amount, 2) }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
            <span>Kembalian:</span>
            <span>Rp {{ number_format($sale->change_amount, 2) }}</span>
        </div>
    </div>

    <div style="text-align: center; border-top: 1px dashed #999; padding-top: 10px; margin-top: 10px; font-size: 11px; color: #555;">
        Terima kasih atas transaksi Anda
    </div>

    <div class="no-print" style="margin-top: 20px; display: flex; gap: 10px; justify-content: space-between;">
        <a href="{{ route('pos.index') }}" class="btn btn-secondary" style="text-decoration: none; text-align: center; flex: 1;">Transaksi Baru</a>
        <button type="button" class="btn" onclick="window.print()" style="flex: 1;">Cetak Struk</button>
    </div>
</div>
@endsection
