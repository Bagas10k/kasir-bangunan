@extends('layouts.app')

@section('content')
@php
    $formatMoney = static function ($value): string {
        $amount = \Brick\Math\BigDecimal::of((string) $value)->toScale(2, \Brick\Math\RoundingMode::HalfUp);
        [$whole, $fraction] = explode('.', (string) $amount);
        return 'Rp ' . preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole) . ',' . $fraction;
    };
@endphp
<style>
    .receivables-page { display:grid; gap:16px; min-width:0; }
    .receivables-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; }
    .receivables-head h1 { margin:0 0 5px; color:var(--primary-hover); font-size:22px; letter-spacing:-.02em; }
    .receivables-head p { margin:0; color:var(--text-muted); font-size:13px; }
    .receivables-note { padding:12px 14px; border:1px solid var(--mint-border); border-radius:var(--radius-inner); background:var(--mint-surface); color:var(--primary-hover); font-size:12.5px; line-height:1.55; }
    .receivables-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
    .receivable-summary-card { min-width:0; padding:15px 16px; border:1px solid var(--border-card); border-radius:var(--radius-card); background:var(--surface); box-shadow:0 4px 20px -2px rgba(27,67,50,.05); }
    .receivable-summary-card span { display:block; margin-bottom:7px; color:var(--text-caption); font-size:10px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
    .receivable-summary-card strong { display:block; color:var(--text-main); font:700 clamp(16px,1.6vw,21px) var(--font-mono); font-variant-numeric:tabular-nums; overflow-wrap:anywhere; }
    .receivable-summary-card.is-primary strong { color:var(--primary-hover); }
    .receivables-filter {
        display: grid;
        grid-template-columns: minmax(180px, 1.6fr) minmax(140px, 1.2fr) repeat(2, minmax(130px, 1fr)) minmax(90px, .6fr) auto;
        grid-template-areas: "search customer start end size actions";
        align-items: end;
        gap: 12px;
        margin: 0 0 16px;
        padding: 14px;
        border: 1px solid var(--border-card, #e5e7eb);
        border-radius: 12px;
        background: var(--surface, #fff);
    }
    .receivables-filter .form-group {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 5px;
        min-width: 0;
        margin: 0;
    }
    .receivables-filter .form-group label {
        display: block;
        margin: 0;
        color: var(--text-caption);
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }
    .receivables-filter .form-control {
        min-height: 38px;
        height: 38px;
        width: 100%;
        box-sizing: border-box;
    }
    .receivables-filter-search { grid-area: search; }
    .receivables-filter-customer { grid-area: customer; }
    .receivables-filter-start { grid-area: start; }
    .receivables-filter-end { grid-area: end; }
    .receivables-filter-size { grid-area: size; }
    .receivables-filter-actions {
        grid-area: actions;
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }
    .receivables-filter-actions > * { white-space: nowrap; }
    .receivables-filter .btn,
    .receivables-filter .btn-secondary {
        min-height: 38px;
        height: 38px;
        padding: 6px 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        box-sizing: border-box;
    }
    .receivables-table-wrap { width:100%; overflow-x:auto; }
    .receivables-table { min-width:780px; }
    .receivables-table .money { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .receivables-table .remaining { color:var(--primary-hover); font-weight:800; }
    .receivables-empty { padding:28px 16px; color:var(--text-muted); text-align:center; }
    .receivables-pagination { margin-top:16px; }
    @media (max-width:900px) {
        .receivables-summary { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media (max-width:1024px) {
        .receivables-filter {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            grid-template-areas:
                "search search customer customer"
                "start start end end"
                "size size actions actions";
        }
    }
    @media (max-width:720px) {
        .receivables-head { flex-direction:column; }
        .receivables-summary { grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }
        .receivable-summary-card { padding:12px; }
        .receivable-summary-card span { font-size:9px; }
        .receivable-summary-card strong { font-size:14px; }
        .receivables-filter {
            grid-template-columns: minmax(0, 1fr);
            grid-template-areas: "search" "customer" "start" "end" "size" "actions";
            gap: 10px;
            padding: 12px;
        }
        .receivables-filter .form-group { min-width:0; }
        .receivables-filter-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: 100%;
        }
        .receivables-filter .btn,
        .receivables-filter .btn-secondary {
            width: 100%;
            min-height: 40px;
            height: 40px;
        }
        .receivables-table-wrap { overflow:visible; }
        .receivables-table { min-width:0; }
        .receivables-table thead { display:none; }
        .receivables-table, .receivables-table tbody, .receivables-table tr, .receivables-table td { display:block; width:100%; }
        .receivables-table tr { margin:0 0 12px; padding:8px 12px; border:1px solid var(--border-card); border-radius:var(--radius-inner); background:var(--surface); }
        .receivables-table td { display:flex; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px solid var(--border-subtle); text-align:right; }
        .receivables-table td:last-child { border-bottom:0; }
        .receivables-table td::before { content:attr(data-label); flex:0 0 42%; color:var(--text-caption); font-size:11px; font-weight:700; text-align:left; }
        .receivables-table .money { white-space:normal; }
    }
</style>

<div class="receivables-page">
    <header class="receivables-head">
        <div>
            <h1>Daftar Piutang</h1>
            <p>Penjualan kredit yang masih memiliki sisa tagihan.</p>
        </div>
    </header>

    <div class="receivables-note" role="note">
        Halaman ini bersifat baca-saja. Saldo menunjukkan sisa tagihan saat checkout (total dikurangi pembayaran awal); pencatatan pelunasan setelah transaksi belum tersedia.
    </div>

    <section class="receivables-summary" aria-label="Ringkasan piutang">
        <article class="receivable-summary-card">
            <span>Transaksi piutang</span>
            <strong>{{ number_format($summary['count'], 0, ',', '.') }}</strong>
        </article>
        <article class="receivable-summary-card">
            <span>Total tagihan</span>
            <strong>{{ $formatMoney($summary['total']) }}</strong>
        </article>
        <article class="receivable-summary-card">
            <span>Total pembayaran awal</span>
            <strong>{{ $formatMoney($summary['paid']) }}</strong>
        </article>
        <article class="receivable-summary-card is-primary">
            <span>Total sisa piutang</span>
            <strong>{{ $formatMoney($summary['remaining']) }}</strong>
        </article>
    </section>

    <section class="card" aria-label="Daftar piutang">
        <form method="GET" action="{{ route('receivables.index') }}" class="receivables-filter">
            <div class="form-group receivables-filter-search">
                <label for="receivables-search">No. invoice</label>
                <input id="receivables-search" class="form-control" type="search" name="search" value="{{ $filters['search'] }}" maxlength="50" placeholder="Cari invoice">
            </div>
            <div class="form-group receivables-filter-customer">
                <label for="receivables-customer">Jenis pelanggan</label>
                <select id="receivables-customer" class="form-control" name="customer_type">
                    <option value="">Semua jenis</option>
                    @foreach($customerTypes as $type)
                        <option value="{{ $type }}" @selected($filters['customer_type'] === $type)>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group receivables-filter-start">
                <label for="receivables-start">Dari tanggal</label>
                <input id="receivables-start" class="form-control" type="date" name="start_date" value="{{ $filters['start_date'] }}">
            </div>
            <div class="form-group receivables-filter-end">
                <label for="receivables-end">Sampai tanggal</label>
                <input id="receivables-end" class="form-control" type="date" name="end_date" value="{{ $filters['end_date'] }}">
            </div>
            <div class="form-group receivables-filter-size">
                <label for="receivables-per-page">Baris</label>
                <select id="receivables-per-page" class="form-control" name="per_page">
                    @foreach([10, 15, 25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="receivables-filter-actions">
                <button type="submit" class="btn">Terapkan</button>
                <a class="btn-secondary" href="{{ route('receivables.index') }}">Reset</a>
            </div>
        </form>

        @if($receivables->isEmpty())
            <div class="receivables-empty">Tidak ada piutang yang sesuai dengan filter.</div>
        @else
            <div class="receivables-table-wrap">
                <table class="receivables-table">
                    <thead>
                        <tr>
                            <th scope="col">Invoice</th>
                            <th scope="col">Jenis pelanggan</th>
                            <th scope="col">Tanggal</th>
                            <th scope="col" style="text-align:right">Total</th>
                            <th scope="col" style="text-align:right">Dibayar</th>
                            <th scope="col" style="text-align:right">Sisa piutang</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($receivables as $sale)
                            <tr>
                                <td data-label="Invoice"><span class="sku-code">{{ $sale->invoice_number }}</span></td>
                                <td data-label="Jenis pelanggan">{{ ucfirst($sale->customer_type) }}</td>
                                <td data-label="Tanggal">{{ \Illuminate\Support\Carbon::parse($sale->created_at)->format('d/m/Y') }}</td>
                                <td data-label="Total" class="money">{{ $formatMoney($sale->total_amount) }}</td>
                                <td data-label="Dibayar" class="money">{{ $formatMoney($sale->paid_amount) }}</td>
                                <td data-label="Sisa piutang" class="money remaining">{{ $formatMoney($sale->remaining_balance) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="receivables-pagination">{{ $receivables->links() }}</div>
        @endif
    </section>
</div>
@endsection
