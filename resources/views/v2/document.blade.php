<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dokumen {{ $document['sale']['number'] ?? '' }} — Pratinjau V2</title>
<style>
    :root {
        --green: #14532d; --green-dark: #14532d; --green-soft: #ecfdf3;
        --line: #cfe3d6; --ink: #12261a; --muted: #3f5648; --bg: #f5faf7;
        --danger: #b42318; --warn-ink: #6b4b00; --warn-bg: #fffbeb; --warn-line: #f2d98c;
    }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--bg); color: var(--ink); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
    .wrap { max-width: 860px; margin: 0 auto; padding: 20px 16px; }
    header.top { background: #fff; border-bottom: 2px solid var(--green); padding: 10px 16px; }
    header.top .wrap { padding: 0; display: flex; flex-wrap: wrap; gap: 10px 16px; align-items: center; justify-content: space-between; }
    h1 { font-size: 1.125rem; margin: 0; color: var(--green-dark); letter-spacing: -.01em; }
    .nav { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .nav a { color: var(--green-dark); font-weight: 600; min-height: 44px; padding: 0 2px; display: inline-flex; align-items: center; text-underline-offset: 3px; }
    .nav a:hover { text-decoration-thickness: 2px; }
    button { font: inherit; min-height: 44px; border-radius: 8px; border: 1px solid var(--green); background: var(--green); color: #fff; font-weight: 600; padding: 8px 16px; cursor: pointer; }
    button:focus-visible, a:focus-visible { outline: 3px solid var(--green); outline-offset: 2px; }
    .doc { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 24px; margin: 16px 0; animation: doc-in .16s ease-out both; }
    @keyframes doc-in { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
    .doc-head { display: flex; flex-wrap: wrap; gap: 16px; justify-content: space-between; align-items: start; border-bottom: 2px solid var(--green-soft); padding-bottom: 14px; }
    h2 { font-size: 1.0625rem; margin: 0 0 2px; color: var(--green-dark); }
    h3 { font-size: .9375rem; margin: 22px 0 8px; color: var(--green-dark); letter-spacing: .01em; }
    .note { color: var(--muted); font-size: .875rem; margin: 4px 0; max-width: 62ch; }
    .meta { font-size: .875rem; margin: 0; }
    .meta div { display: flex; gap: 16px; justify-content: space-between; padding: 2px 0; }
    .meta dt { color: var(--muted); }
    .meta dd { margin: 0; font-weight: 600; text-align: right; font-variant-numeric: tabular-nums; }
    .banner { border-radius: 10px; padding: 12px 14px; margin: 14px 0; display: flex; flex-wrap: wrap; gap: 4px 12px; align-items: baseline; }
    .banner strong { font-size: 1.125rem; letter-spacing: .06em; }
    .banner.unpaid { background: var(--warn-bg); border: 2px solid var(--warn-line); color: var(--warn-ink); }
    .banner.paid { background: var(--green-soft); border: 2px solid var(--green); color: var(--green-dark); }
    .banner.other { background: #fff; border: 2px dashed var(--line); color: var(--ink); }
    .banner span { font-size: .875rem; }
    table { border-collapse: collapse; width: 100%; font-size: .875rem; }
    caption { text-align: left; color: var(--muted); font-size: .875rem; padding-bottom: 6px; }
    th, td { text-align: left; border-bottom: 1px solid var(--line); padding: 8px 10px; vertical-align: top; }
    th { color: var(--green-dark); white-space: nowrap; }
    th { font-size: .8125rem; letter-spacing: .02em; }
    td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    tbody tr:last-child td { border-bottom: 0; }
    .scroll { overflow-x: auto; }
    .sum { margin: 14px 0 0; padding: 0; }
    .sum > div { display: flex; justify-content: space-between; gap: 16px; padding: 6px 0; border-bottom: 1px dotted var(--line); }
    .sum dt { color: var(--muted); font-size: .875rem; }
    .sum dd { margin: 0; font-weight: 600; font-variant-numeric: tabular-nums; text-align: right; white-space: nowrap; }
    .sum .grand { border-bottom: 2px solid var(--green); }
    .sum .grand dt, .sum .grand dd { font-size: 1rem; color: var(--green-dark); font-weight: 700; }
    .split-note { background: var(--green-soft); border: 1px solid var(--line); border-radius: 8px; padding: 10px 12px; font-size: .875rem; }
    .sr { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }
    footer.foot { color: var(--muted); font-size: .875rem; padding: 14px 0 24px; border-top: 1px solid var(--line); margin-top: 8px; }
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation: none !important; transition: none !important; }
    }
    @media print {
        body { background: #fff; }
        header.top, .no-print, .nav { display: none !important; }
        .wrap { max-width: none; padding: 0; }
        .doc { border: 0; padding: 0; margin: 0; }
        .doc-head { border-bottom-color: #9bb3a4; }
        .note, .meta dt, .sum dt { color: #222; }
        th, td { border-bottom-color: #b9c9c0; }
        .banner, tr, .sum > div { break-inside: avoid; }
        .banner { border-width: 1px; }
    }
</style>
</head>
<body>
@php
    $doc = is_array($document ?? null) ? $document : (array) ($document ?? []);
    $sale = $doc['sale'] ?? [];
    $items = $doc['items'] ?? [];
    $customer = $doc['customer'] ?? null;
    $delivery = $doc['delivery'] ?? null;
    $totals = $doc['totals'] ?? [];
    $kind = ($doc['kind'] ?? 'receipt') === 'delivery' ? 'delivery' : 'receipt';
    $status = strtolower((string) ($sale['status'] ?? ''));
    $unpaid = in_array($status, ['credit', 'unpaid', 'open', 'pending', 'partial', 'dp', 'tempo'], true);
    $settled = in_array($status, ['paid', 'settled', 'completed', 'closed', 'lunas'], true);
    $history = $paymentHistory ?? [];
    $history = collect($history)->map(fn ($row) => (array) $row)->all();
    $method = ['cash' => 'Tunai', 'transfer' => 'Transfer', 'credit' => 'Kredit'];
    $payStatus = ['posted' => 'Terverifikasi', 'pending' => 'Menunggu admin', 'rejected' => 'Ditolak'];
    $rp = function ($value): string {
        if ($value === null || $value === '') { return '—'; }
        $s = (string) $value;
        $neg = str_starts_with($s, '-');
        $s = ltrim($s, '+-');
        $parts = explode('.', $s, 2);
        $int = $parts[0] === '' ? '0' : $parts[0];
        $dec = rtrim($parts[1] ?? '', '0');
        $int = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $int);
        return ($neg ? '-' : '') . 'Rp ' . $int . ($dec !== '' ? ',' . $dec : '');
    };
    $pick = function ($row, array $keys, string $fallback = '—'): string {
        $row = (array) $row;
        foreach ($keys as $k) {
            if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) { return (string) $row[$k]; }
        }
        return $fallback;
    };
@endphp

<header class="top">
    <div class="wrap">
        <h1>Kasir Bangunan V2 · Dokumen</h1>
        <div class="nav">
            <a href="{{ route('v2.index') }}">Kembali ke ruang kerja</a>
            <button type="button" class="no-print" onclick="window.print()">Cetak</button>
        </div>
    </div>
</header>

<main class="wrap">
    <article class="doc" aria-labelledby="doc-title">
        <div class="doc-head">
            <div>
                <h2 id="doc-title">{{ $kind === 'delivery' ? 'Surat Jalan' : 'Nota Penjualan' }}</h2>
                <p class="note">Pratinjau V2 · dicetak dari data penjualan tersimpan, bukan hitungan ulang di peramban.</p>
            </div>
            <dl class="meta" style="margin:0">
                <div><dt>Nomor</dt><dd>{{ $sale['number'] ?? ('#' . ($sale['id'] ?? '-')) }}</dd></div>
                <div><dt>Tanggal</dt><dd>{{ $sale['date'] ?? '-' }}</dd></div>
                <div><dt>Shift</dt><dd>#{{ $sale['shift_id'] ?? '-' }}</dd></div>
                <div><dt>Pemenuhan</dt><dd>{{ ($sale['fulfillment'] ?? '-') === 'delivery' ? 'Kirim' : 'Ambil di toko' }}</dd></div>
            </dl>
        </div>

        @if ($unpaid)
            <div class="banner unpaid" role="status">
                <strong>BELUM LUNAS</strong>
                <span>Jenis transaksi awal: {{ $method[$sale['payment_method'] ?? ''] ?? ($sale['payment_method'] ?? '-') }} · sisa tagihan dicatat sebagai piutang pelanggan.</span>
            </div>
        @elseif ($settled)
            <div class="banner paid" role="status">
                <strong>LUNAS</strong>
                <span>Jenis transaksi awal: {{ $method[$sale['payment_method'] ?? ''] ?? ($sale['payment_method'] ?? '-') }} · pelunasan tercatat pada riwayat pembayaran di bawah.</span>
            </div>
        @else
            <div class="banner other" role="status">
                <strong>STATUS: {{ strtoupper($sale['status'] ?? '-') }}</strong>
                <span>Jenis transaksi awal: {{ $method[$sale['payment_method'] ?? ''] ?? ($sale['payment_method'] ?? '-') }}.</span>
            </div>
        @endif

        <h3>Pelanggan</h3>
        @if (is_array($customer))
            <p class="note">{{ $customer['name'] ?? 'Tanpa nama' }}@if (! empty($customer['code'])) · kode {{ $customer['code'] }}@endif</p>
        @else
            <p class="note">Tanpa pelanggan tercatat (penjualan tunai walk-in).</p>
        @endif

        <h3>Barang</h3>
        <div class="scroll">
            <table>
                <caption>Rincian barang pada dokumen ini</caption>
                <thead>
                    <tr>
                        <th scope="col">Barang</th>
                        <th scope="col" class="num">Jumlah</th>
                        @if ($kind === 'receipt')
                            <th scope="col" class="num">Harga satuan</th>
                            <th scope="col" class="num">Diskon</th>
                            <th scope="col" class="num">Subtotal</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>{{ $item['name'] ?? '-' }}</td>
                        <td class="num">{{ $item['quantity'] ?? '-' }}</td>
                        @if ($kind === 'receipt')
                            <td class="num">{{ $rp($item['unit_price'] ?? null) }}</td>
                            <td class="num">{{ $rp($item['discount'] ?? null) }}</td>
                            <td class="num">{{ $rp($item['subtotal'] ?? null) }}</td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $kind === 'receipt' ? 5 : 2 }}">Tidak ada barang pada dokumen ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($kind === 'receipt')
            <h3>Ringkasan uang</h3>
            <dl class="sum">
                <div><dt>Subtotal barang</dt><dd>{{ $rp($sale['subtotal'] ?? '0') }}</dd></div>
                <div><dt>Diskon baris</dt><dd>- {{ $rp($sale['line_discount'] ?? null) }}</dd></div>
                <div><dt>Diskon invoice</dt><dd>- {{ $rp($sale['invoice_discount'] ?? null) }}</dd></div>
                <div><dt>Ongkos kirim</dt><dd>{{ $rp($sale['delivery_fee'] ?? null) }}</dd></div>
                <div class="grand"><dt>Total</dt><dd>{{ $rp($sale['total'] ?? ($totals['total_sales'] ?? null)) }}</dd></div>
                <div><dt>Pembayaran terverifikasi</dt><dd>{{ $rp($document['paid'] ?? '0') }}</dd></div>
                <div><dt>Sisa tagihan</dt><dd>{{ $rp($document['remaining'] ?? $sale['total'] ?? '0') }}</dd></div>
                <div><dt>Kembalian</dt><dd>{{ $rp($sale['change'] ?? null) }}</dd></div>
            </dl>
            <p class="note">Ongkir dipisahkan dari nilai barang; total penjualan = barang bersih + ongkir.</p>
        @else
            <h3>Tujuan kirim</h3>
            <p class="note">{{ is_array($delivery) ? ($delivery['destination'] ?? '-') : '-' }}</p>
            @if (is_array($delivery))
                <dl class="meta" style="margin:0;max-width:420px">
                    <div><dt>Nomor surat jalan</dt><dd>{{ $delivery['number'] ?? '-' }}</dd></div>
                    <div><dt>Sopir</dt><dd>{{ $delivery['driver_name'] ?? 'belum ditentukan' }}</dd></div>
                    <div><dt>Status kirim</dt><dd>{{ $delivery['status'] ?? '-' }}</dd></div>
                </dl>
            @endif
            <p class="split-note" style="margin-top:12px">Surat jalan tidak memuat harga. Nilai transaksi ada di nota penjualan.</p>
        @endif

        @if (count($history) > 0)
            <h3>Riwayat pembayaran</h3>
            <div class="scroll">
                <table>
                    <caption>Pembayaran yang tercatat untuk penjualan ini</caption>
                    <thead>
                        <tr>
                            <th scope="col">Tanggal</th>
                            <th scope="col" class="num">Nominal</th>
                            <th scope="col">Metode</th>
                            <th scope="col">Status</th>
                            <th scope="col">Referensi</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($history as $row)
                        <tr>
                            <td>{{ substr($pick($row, ['date', 'created_at', 'paid_at', 'payment_date']), 0, 10) }}</td>
                            <td class="num">{{ $rp($pick($row, ['amount'], '')) }}</td>
                            <td>{{ $method[$pick($row, ['method'], '')] ?? $pick($row, ['method']) }}</td>
                            <td>{{ $payStatus[strtolower($pick($row, ['status'], ''))] ?? $pick($row, ['status']) }}</td>
                            <td>{{ $pick($row, ['reference', 'note', 'notes']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <h3>Riwayat pembayaran</h3>
            <p class="note">Belum ada riwayat pembayaran yang dikirim untuk dokumen ini.</p>
        @endif

        <div class="nav" style="margin-top:16px">
            @if ($kind === 'receipt' && is_array($delivery))
                <a href="{{ route('v2.documents', ['id' => $sale['id'] ?? 0, 'kind' => 'delivery']) }}">Lihat surat jalan</a>
            @elseif ($kind === 'delivery')
                <a href="{{ route('v2.documents', ['id' => $sale['id'] ?? 0, 'kind' => 'receipt']) }}">Lihat nota penjualan</a>
            @endif
        </div>
    </article>

    <footer class="foot">Pratinjau V2 · dokumen ini hanya untuk dibaca dan dibuat dari data penjualan tersimpan. Harga pokok tidak ditampilkan.</footer>
</main>
</body>
</html>
