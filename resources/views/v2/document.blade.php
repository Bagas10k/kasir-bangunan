<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ ($document['kind'] ?? 'receipt') === 'delivery' ? 'Surat Jalan' : 'Nota Penjualan' }} {{ $document['sale']['number'] ?? '' }}</title>
<style>
    :root {
        --green: #14532d; --green-dark: #14532d; --green-soft: #ecfdf3;
        --line: #cfe3d6; --ink: #12261a; --muted: #3f5648; --bg: #f5faf7;
        --danger: #b42318; --warn-ink: #6b4b00; --warn-bg: #fffbeb; --warn-line: #f2d98c;
    }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--bg); color: var(--ink); font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
    .wrap { max-width: 860px; margin: 0 auto; padding: 20px 16px; }
    header.top { background: #fff; border-bottom: 2px solid var(--green); padding: 10px 16px; }
    header.top .wrap { padding: 0; display: flex; flex-wrap: wrap; gap: 10px 16px; align-items: center; justify-content: space-between; }
    h1 { font-size: 1.0625rem; margin: 0; color: var(--green-dark); letter-spacing: -.01em; }
    .nav { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .nav a { color: var(--green-dark); font-weight: 600; min-height: 44px; padding: 0 6px; display: inline-flex; align-items: center; text-underline-offset: 3px; }
    .nav a:hover { text-decoration-thickness: 2px; }
    .layout-switch { display: inline-flex; border: 1px solid var(--line); border-radius: 10px; background: var(--bg); padding: 2px; gap: 2px; }
    .layout-switch button { min-height: 40px; padding: 6px 12px; border: 0; border-radius: 8px; background: transparent; color: var(--green-dark); font-size: .875rem; font-weight: 600; cursor: pointer; }
    .layout-switch button[aria-checked="true"] { background: var(--green); color: #fff; }
    button.btn-print { font: inherit; min-height: 44px; border-radius: 8px; border: 1px solid var(--green); background: var(--green); color: #fff; font-weight: 600; padding: 8px 16px; cursor: pointer; }
    button:focus-visible, a:focus-visible { outline: 3px solid var(--green); outline-offset: 2px; }
    .doc { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 24px; margin: 16px 0; animation: doc-in .16s ease-out both; }
    @keyframes doc-in { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
    .store-head { margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid var(--line); }
    .store-name { font-size: 1.25rem; font-weight: 800; color: var(--green-dark); margin: 0; letter-spacing: -.01em; }
    .store-meta { margin: 2px 0 0; font-size: .875rem; color: var(--muted); }
    .doc-head { display: flex; flex-wrap: wrap; gap: 16px; justify-content: space-between; align-items: start; border-bottom: 2px solid var(--green-soft); padding-bottom: 14px; }
    h2 { font-size: 1.0625rem; margin: 0 0 2px; color: var(--green-dark); }
    h3 { font-size: .9375rem; margin: 20px 0 8px; color: var(--green-dark); letter-spacing: .01em; }
    .note { color: var(--muted); font-size: .875rem; margin: 4px 0; max-width: 64ch; }
    .doc-note-box { margin: 12px 0 0; padding: 10px 12px; border: 1px solid var(--line); border-radius: 8px; background: var(--bg); font-size: .875rem; }
    .doc-note-box strong { display: block; color: var(--green-dark); margin-bottom: 2px; }
    .meta { font-size: .875rem; margin: 0; min-width: 240px; }
    .meta div { display: flex; gap: 16px; justify-content: space-between; padding: 2px 0; }
    .meta dt { color: var(--muted); }
    .meta dd { margin: 0; font-weight: 600; text-align: right; font-variant-numeric: tabular-nums; }
    .tier-badge { display: inline-block; margin-top: 6px; padding: 2px 10px; border-radius: 999px; background: var(--green-soft); border: 1px solid var(--line); color: var(--green-dark); font-size: .75rem; font-weight: 700; }
    .banner { border-radius: 10px; padding: 10px 14px; margin: 14px 0; display: flex; flex-wrap: wrap; gap: 4px 12px; align-items: baseline; }
    .banner strong { font-size: 1rem; letter-spacing: .06em; }
    .banner.unpaid { background: var(--warn-bg); border: 2px solid var(--warn-line); color: var(--warn-ink); }
    .banner.paid { background: var(--green-soft); border: 2px solid var(--green); color: var(--green-dark); }
    .banner.other { background: #fff; border: 2px dashed var(--line); color: var(--ink); }
    .banner span { font-size: .875rem; }
    table { border-collapse: collapse; width: 100%; font-size: .875rem; }
    caption { text-align: left; color: var(--muted); font-size: .8125rem; padding-bottom: 6px; }
    th, td { text-align: left; border-bottom: 1px solid var(--line); padding: 8px 10px; vertical-align: top; }
    th { color: var(--green-dark); white-space: nowrap; font-size: .8125rem; letter-spacing: .02em; }
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
    .sign-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-top: 28px; break-inside: avoid; }
    .sign-box { border: 1px dashed var(--line); border-radius: 8px; padding: 12px; text-align: center; font-size: .8125rem; color: var(--muted); }
    .sign-box strong { display: block; color: var(--ink); font-size: .875rem; }
    .sign-line { height: 54px; border-bottom: 1px solid var(--muted); margin: 8px 10px 6px; }
    footer.foot { color: var(--muted); font-size: .8125rem; padding: 14px 0 24px; border-top: 1px solid var(--line); margin-top: 8px; text-align: center; }

    /* Mode Struk Thermal 80mm */
    body.is-thermal .wrap { max-width: 340px; padding: 12px 10px; }
    body.is-thermal header.top .wrap { max-width: 860px; padding: 0 16px; }
    body.is-thermal .doc { padding: 14px 12px; border-radius: 6px; font-size: 12.5px; }
    body.is-thermal .store-head { text-align: center; border-bottom: 1px dashed #666; padding-bottom: 8px; margin-bottom: 10px; }
    body.is-thermal .store-name { font-size: 1rem; }
    body.is-thermal .store-meta { font-size: 11.5px; }
    body.is-thermal .doc-head { display: block; border-bottom: 1px dashed #666; padding-bottom: 8px; }
    body.is-thermal .meta { min-width: 0; margin-top: 8px; font-size: 12px; }
    body.is-thermal .banner { padding: 6px 8px; border-width: 1px; margin: 10px 0; font-size: 12px; }
    body.is-thermal .banner strong { font-size: 12.5px; }
    body.is-thermal h3 { font-size: 12.5px; margin: 12px 0 4px; text-transform: uppercase; letter-spacing: .04em; }
    body.is-thermal table { font-size: 12px; }
    body.is-thermal caption { display: none; }
    body.is-thermal th, body.is-thermal td { padding: 4px 3px; border-bottom: 1px dotted #999; }
    body.is-thermal .th-hide-thermal { display: none; }
    body.is-thermal .sum > div { padding: 3px 0; font-size: 12px; }
    body.is-thermal .sum .grand dt, body.is-thermal .sum .grand dd { font-size: 13.5px; }
    body.is-thermal .sign-grid { grid-template-columns: 1fr; gap: 10px; margin-top: 16px; }
    body.is-thermal .sign-line { height: 34px; }
    body.is-thermal footer.foot { font-size: 11.5px; padding: 10px 0; border-top: 1px dashed #666; }

    @media (max-width: 600px) {
        .sign-grid { grid-template-columns: 1fr; }
    }
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation: none !important; transition: none !important; }
    }
    @media print {
        body { background: #fff; }
        header.top, .no-print, .nav { display: none !important; }
        .wrap { max-width: none; padding: 0; }
        body.is-thermal .wrap { max-width: 80mm; padding: 2mm; }
        .doc { border: 0; padding: 0; margin: 0; border-radius: 0; }
        body.is-thermal .doc { padding: 0; }
        .doc-head { border-bottom-color: #9bb3a4; }
        .note, .meta dt, .sum dt, .store-meta { color: #222; }
        th, td { border-bottom-color: #b9c9c0; }
        .banner, tr, .sum > div, .sign-grid { break-inside: avoid; }
        .banner { border-width: 1px; }
    }
</style>
</head>
<body>
@php
    $doc = is_array($document ?? null) ? $document : (array) ($document ?? []);
    $store = $doc['store'] ?? [];
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
    $deliveryStatusLabels = ['prepared' => 'Siap dikirim', 'sent' => 'Sedang dikirim', 'received' => 'Diterima'];
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
    $qtyFmt = function ($value, string $unit = ''): string {
        $s = trim((string) ($value ?? ''));
        if ($s === '') { return '—'; }
        if (str_contains($s, '.')) {
            $s = rtrim(rtrim($s, '0'), '.');
        }
        $s = str_replace('.', ',', $s);
        return $unit !== '' ? $s . ' ' . $unit : $s;
    };
    $isPositive = fn ($value): bool => $value !== null && $value !== '' && ! preg_match('/^-?0+(?:\.0+)?$/', trim((string) $value));
    $pick = function ($row, array $keys, string $fallback = '—'): string {
        $row = (array) $row;
        foreach ($keys as $k) {
            if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) { return (string) $row[$k]; }
        }
        return $fallback;
    };
    $storeName = trim((string) ($store['name'] ?? config('v2.store.name', 'Toko Bahan Bangunan')));
    $storeAddress = trim((string) ($store['address'] ?? config('v2.store.address', '')));
    $storePhone = trim((string) ($store['phone'] ?? config('v2.store.phone', '')));
    $storeFooter = trim((string) ($store['footer'] ?? config('v2.store.footer', '')));
    $dateDisplay = ! empty($sale['created_at'])
        ? \Illuminate\Support\Carbon::parse($sale['created_at'])->format('d/m/Y H:i')
        : ($sale['date'] ?? '-');
@endphp

<header class="top no-print">
    <div class="wrap">
        <h1>{{ $storeName }} · {{ $kind === 'delivery' ? 'Surat Jalan' : 'Nota Penjualan' }}</h1>
        <div class="nav">
            <a href="{{ route(config('v2.enabled') ? 'pos.index' : 'v2.index') }}">Kembali ke ruang kerja</a>
            <div class="layout-switch" role="radiogroup" aria-label="Ukuran kertas cetak">
                <button type="button" role="radio" data-doc-layout="a4" aria-checked="true">A4</button>
                <button type="button" role="radio" data-doc-layout="thermal" aria-checked="false" tabindex="-1">Struk 80mm</button>
            </div>
            <button type="button" class="btn-print" onclick="window.print()">Cetak</button>
        </div>
    </div>
</header>

<main class="wrap">
    <article class="doc" aria-labelledby="doc-title">
        <div class="store-head">
            <p class="store-name">{{ $storeName }}</p>
            @if ($storeAddress !== '' || $storePhone !== '')
                <p class="store-meta">{{ implode(' · ', array_filter([$storeAddress, $storePhone !== '' ? 'Telp/WA: ' . $storePhone : ''])) }}</p>
            @endif
        </div>

        <div class="doc-head">
            <div>
                <h2 id="doc-title">{{ $kind === 'delivery' ? 'Surat Jalan' : 'Nota Penjualan' }}</h2>
                @if (($sale['price_tier'] ?? 'retail') === 'contractor')
                    <span class="tier-badge">Harga Kontraktor</span>
                @endif
            </div>
            <dl class="meta">
                <div><dt>Nomor</dt><dd>{{ $sale['number'] ?? ('#' . ($sale['id'] ?? '-')) }}</dd></div>
                <div><dt>Tanggal</dt><dd>{{ $dateDisplay }}</dd></div>
                @if (! empty($sale['cashier_name']))
                    <div><dt>Kasir</dt><dd>{{ $sale['cashier_name'] }}</dd></div>
                @endif
                @if (! empty($sale['cash_day']))
                    <div><dt>Kas harian</dt><dd>{{ $sale['cash_day'] }}</dd></div>
                @endif
                <div><dt>Pemenuhan</dt><dd>{{ ($sale['fulfillment'] ?? '-') === 'delivery' ? 'Kirim' : 'Ambil di toko' }}</dd></div>
                @if (! empty($sale['due_date']))
                    <div><dt>Jatuh tempo</dt><dd>{{ \Illuminate\Support\Carbon::parse($sale['due_date'])->format('d/m/Y') }}</dd></div>
                @endif
            </dl>
        </div>

        @if ($unpaid)
            <div class="banner unpaid" role="status">
                <strong>{{ $status === 'partial' ? 'DIBAYAR SEBAGIAN' : 'BELUM LUNAS' }}</strong>
                <span>Metode: {{ $method[$sale['payment_method'] ?? ''] ?? ($sale['payment_method'] ?? '-') }} · sisa tagihan tercatat pada piutang pelanggan.</span>
            </div>
        @elseif ($settled)
            <div class="banner paid" role="status">
                <strong>LUNAS</strong>
                <span>Metode: {{ $method[$sale['payment_method'] ?? ''] ?? ($sale['payment_method'] ?? '-') }}.</span>
            </div>
        @else
            <div class="banner other" role="status">
                <strong>STATUS: {{ strtoupper($sale['status'] ?? '-') }}</strong>
                <span>Metode: {{ $method[$sale['payment_method'] ?? ''] ?? ($sale['payment_method'] ?? '-') }}.</span>
            </div>
        @endif

        <h3>Pelanggan</h3>
        @if (is_array($customer))
            <p class="note">{{ $customer['name'] ?? 'Tanpa nama' }}@if (! empty($customer['code'])) · {{ $customer['code'] }}@endif</p>
        @else
            <p class="note">Pelanggan umum (tunai di toko).</p>
        @endif

        @if (! empty($sale['notes']))
            <div class="doc-note-box">
                <strong>Catatan / Instruksi</strong>
                <span>{{ $sale['notes'] }}</span>
            </div>
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
                            <th scope="col" class="num">Harga</th>
                            <th scope="col" class="num th-hide-thermal">Diskon</th>
                            <th scope="col" class="num">Subtotal</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>{{ $item['name'] ?? '-' }}</td>
                        <td class="num">{{ $qtyFmt($item['quantity'] ?? null, (string) ($item['unit'] ?? '')) }}</td>
                        @if ($kind === 'receipt')
                            <td class="num">{{ $rp($item['unit_price'] ?? null) }}</td>
                            <td class="num th-hide-thermal">{{ $rp($item['discount'] ?? null) }}</td>
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
            <h3>Ringkasan pembayaran</h3>
            <dl class="sum">
                <div><dt>Subtotal barang</dt><dd>{{ $rp($sale['subtotal'] ?? '0') }}</dd></div>
                @if ($isPositive($sale['line_discount'] ?? null))
                    <div><dt>Diskon barang</dt><dd>- {{ $rp($sale['line_discount']) }}</dd></div>
                @endif
                @if ($isPositive($sale['invoice_discount'] ?? null))
                    <div><dt>Diskon nota</dt><dd>- {{ $rp($sale['invoice_discount']) }}</dd></div>
                @endif
                @if ($isPositive($sale['delivery_fee'] ?? null))
                    <div><dt>Ongkos kirim</dt><dd>{{ $rp($sale['delivery_fee']) }}</dd></div>
                @endif
                <div class="grand"><dt>Total</dt><dd>{{ $rp($sale['total'] ?? ($totals['total_sales'] ?? null)) }}</dd></div>
                <div><dt>Terbayar terverifikasi</dt><dd>{{ $rp($document['paid'] ?? '0') }}</dd></div>
                <div><dt>Sisa tagihan</dt><dd>{{ $rp($document['remaining'] ?? $sale['total'] ?? '0') }}</dd></div>
                @if ($isPositive($sale['change'] ?? null))
                    <div><dt>Kembalian</dt><dd>{{ $rp($sale['change']) }}</dd></div>
                @endif
            </dl>
        @else
            <h3>Pengiriman</h3>
            <p class="note">{{ is_array($delivery) ? ($delivery['destination'] ?? '-') : '-' }}</p>
            @if (is_array($delivery))
                <dl class="meta" style="margin:0;max-width:420px">
                    <div><dt>Nomor surat jalan</dt><dd>{{ $delivery['number'] ?? '-' }}</dd></div>
                    <div><dt>Sopir</dt><dd>{{ $delivery['driver_name'] ?? 'Belum ditentukan' }}</dd></div>
                    <div><dt>Status kirim</dt><dd>{{ $deliveryStatusLabels[$delivery['status'] ?? ''] ?? ($delivery['status'] ?? '-') }}</dd></div>
                </dl>
            @endif
            <p class="split-note" style="margin-top:12px">Surat jalan tidak memuat harga. Nilai transaksi tercantum pada nota penjualan.</p>

            <div class="sign-grid" aria-label="Kolom tanda tangan serah terima">
                <div class="sign-box">
                    <strong>Disiapkan / Pengirim</strong>
                    <div class="sign-line"></div>
                    <span>Nama &amp; paraf</span>
                </div>
                <div class="sign-box">
                    <strong>Sopir / Armada</strong>
                    <div class="sign-line"></div>
                    <span>{{ is_array($delivery) && ! empty($delivery['driver_name']) ? $delivery['driver_name'] : 'Nama & paraf' }}</span>
                </div>
                <div class="sign-box">
                    <strong>Diterima oleh</strong>
                    <div class="sign-line"></div>
                    <span>Nama, tanggal &amp; paraf</span>
                </div>
            </div>
        @endif

        @if ($kind === 'receipt' && count($history) > 0)
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
                            <th scope="col" class="th-hide-thermal">Referensi</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($history as $row)
                        <tr>
                            <td>{{ substr($pick($row, ['date', 'created_at', 'paid_at', 'payment_date']), 0, 10) }}</td>
                            <td class="num">{{ $rp($pick($row, ['amount'], '')) }}</td>
                            <td>{{ $method[$pick($row, ['method'], '')] ?? $pick($row, ['method']) }}</td>
                            <td>{{ $payStatus[strtolower($pick($row, ['status'], ''))] ?? $pick($row, ['status']) }}</td>
                            <td class="th-hide-thermal">{{ $pick($row, ['reference', 'note', 'notes']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="nav no-print" style="margin-top:16px">
            @if ($kind === 'receipt' && is_array($delivery))
                <a href="{{ route('v2.documents', ['id' => $sale['id'] ?? 0, 'kind' => 'delivery']) }}">Lihat surat jalan</a>
            @elseif ($kind === 'delivery')
                <a href="{{ route('v2.documents', ['id' => $sale['id'] ?? 0, 'kind' => 'receipt']) }}">Lihat nota penjualan</a>
            @endif
        </div>
    </article>

    <footer class="foot">{{ $storeFooter !== '' ? $storeFooter : 'Dokumen resmi penjualan tersimpan.' }}</footer>
</main>
<script>
(() => {
    const key = 'kasir-v2-doc-layout';
    const buttons = Array.from(document.querySelectorAll('[data-doc-layout]'));
    if (!buttons.length) return;
    function apply(mode) {
        const thermal = mode === 'thermal';
        document.body.classList.toggle('is-thermal', thermal);
        for (const btn of buttons) {
            const on = btn.dataset.docLayout === (thermal ? 'thermal' : 'a4');
            btn.setAttribute('aria-checked', String(on));
            btn.tabIndex = on ? 0 : -1;
        }
        try { localStorage.setItem(key, thermal ? 'thermal' : 'a4'); } catch {}
    }
    for (const btn of buttons) {
        btn.addEventListener('click', () => apply(btn.dataset.docLayout));
    }
    try { apply(localStorage.getItem(key) || 'a4'); } catch { apply('a4'); }
})();
</script>
</body>
</html>
