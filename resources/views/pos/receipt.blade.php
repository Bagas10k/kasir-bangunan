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
    
    .receipt-container {
        max-width: 500px;
        margin: 0 auto;
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid var(--border-card);
        box-shadow: 0 10px 25px -5px rgba(27, 67, 50, 0.08);
        padding: 30px;
        font-family: var(--font-sans);
    }
    
    .receipt-header {
        text-align: center;
        border-bottom: 1px dashed var(--border-card);
        padding-bottom: 18px;
        margin-bottom: 18px;
    }

    .receipt-badge {
        display: inline-block;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--primary);
        background-color: var(--mint-surface);
        border: 1px solid var(--mint-border);
        padding: 4px 12px;
        border-radius: 9999px;
        margin-bottom: 8px;
    }
    
    .receipt-title {
        font-size: 18px;
        font-weight: 800;
        color: var(--primary-hover);
        margin: 0 0 4px 0;
        letter-spacing: -0.01em;
    }

    .receipt-subtitle {
        font-size: 12px;
        color: var(--text-muted);
        margin: 0;
    }

    .meta-table {
        width: 100%;
        font-size: 12.5px;
        margin-bottom: 16px;
        border-collapse: collapse;
    }

    .meta-table td {
        padding: 4px 0;
        border: none;
        color: var(--text-main);
    }

    .meta-table td.label {
        width: 110px;
        color: var(--text-caption);
    }

    .items-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12.5px;
        margin-bottom: 18px;
        border-top: 1px solid var(--border-subtle);
        border-bottom: 1px solid var(--border-subtle);
    }

    .items-table th {
        background-color: var(--mint-surface);
        color: var(--primary-hover);
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        padding: 8px 10px;
        border: none;
    }

    .items-table td {
        padding: 10px;
        border-bottom: 1px solid #f0f4f1;
        color: var(--text-main);
    }

    .receipt-summary-box {
        background-color: var(--mint-surface);
        border: 1px solid var(--mint-border);
        border-radius: 14px;
        padding: 14px 16px;
        margin-bottom: 20px;
    }

    .summary-line {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 13px;
        margin-bottom: 6px;
    }

    .summary-line.grand-total {
        font-size: 16px;
        font-weight: 800;
        color: var(--primary-hover);
        border-top: 1px solid var(--mint-border);
        padding-top: 8px;
        margin-top: 8px;
        margin-bottom: 0;
    }

    .receipt-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
    }

    .btn-pill-print {
        background-color: var(--primary);
        color: #ffffff;
        border: 1px solid var(--primary);
        border-radius: 9999px;
        padding: 9px 22px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.15s ease;
        box-shadow: 0 2px 8px rgba(45, 106, 79, 0.2);
    }

    .btn-pill-print:hover {
        background-color: var(--primary-hover);
    }

    .btn-pill-back {
        background-color: #ffffff;
        color: var(--primary-hover);
        border: 1px solid var(--border-card);
        border-radius: 9999px;
        padding: 9px 20px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.15s ease;
    }

    .btn-pill-back:hover {
        background-color: var(--mint-surface);
        border-color: var(--mint-border);
    }

    @media print {
        header, nav, .sidebar-wrapper, .topbar, .receipt-actions, .no-print {
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
        .receipt-container {
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            max-width: 100% !important;
        }
    }
</style>

<div class="receipt-container">
    <div class="receipt-header">
        <span class="receipt-badge">Struk Transaksi Resmi</span>
        <h1 class="receipt-title">Kasir Material &amp; Bangunan</h1>
        <p class="receipt-subtitle">Terminal Penjualan Bahan Bangunan &bull; Sistem Terpadu</p>
    </div>

    <table class="meta-table">
        <tr>
            <td class="label">No. Invoice</td>
            <td>: <strong class="sku-code">{{ $sale->invoice_number }}</strong></td>
        </tr>
        <tr>
            <td class="label">Waktu Transaksi</td>
            <td>: {{ $sale->created_at }}</td>
        </tr>
        <tr>
            <td class="label">Pelanggan</td>
            <td>: <span class="badge" style="text-transform: uppercase;">{{ $sale->customer_type }}</span></td>
        </tr>
        <tr>
            <td class="label">Metode Bayar</td>
            <td>: <span class="badge" style="text-transform: uppercase;">{{ $sale->payment_method }}</span></td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Item Material</th>
                <th style="text-align: center; width: 60px;">Qty</th>
                <th style="text-align: right; width: 95px;">Harga</th>
                <th style="text-align: right; width: 105px;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td>
                    <div style="font-weight: 600;">{{ $item->name ?? 'Item Material' }}</div>
                    <div style="font-size: 11px; color: var(--text-caption);">Satuan: {{ $item->unit ?? 'unit' }}</div>
                </td>
                <td style="text-align: center; font-variant-numeric: tabular-nums;">{{ number_format((float)$item->quantity, 2, ',', '.') }}</td>
                <td style="text-align: right; font-variant-numeric: tabular-nums;">Rp {{ number_format((float)$item->unit_price, 2, ',', '.') }}</td>
                <td style="text-align: right; font-variant-numeric: tabular-nums; font-weight: 600;">Rp {{ number_format((float)$item->subtotal, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="receipt-summary-box">
        <div class="summary-line">
            <span style="color: var(--text-caption);">Total Tagihan:</span>
            <span class="tabular-nums" style="font-weight: 700;">Rp {{ number_format((float)$sale->total_amount, 2, ',', '.') }}</span>
        </div>
        <div class="summary-line">
            <span style="color: var(--text-caption);">Uang Tunai:</span>
            <span class="tabular-nums">Rp {{ number_format((float)$sale->paid_amount, 2, ',', '.') }}</span>
        </div>
        <div class="summary-line grand-total">
            <span>Uang Kembalian:</span>
            <span class="tabular-nums">Rp {{ number_format((float)$sale->change_amount, 2, ',', '.') }}</span>
        </div>
    </div>

    <div style="text-align: center; font-size: 11.5px; color: var(--text-caption); margin-bottom: 20px;">
        Terima kasih atas kunjungan &amp; kepercayaan Anda.<br>
        Barang yang sudah dibeli tidak dapat ditukar tanpa bukti struk ini.
    </div>

    <div class="receipt-actions no-print">
        <button type="button" class="btn-pill-print" onclick="window.print()">Cetak Struk</button>
        <a href="{{ route('pos.index') }}" class="btn-pill-back">&larr; Kembali ke Kasir</a>
    </div>
</div>
@endsection
