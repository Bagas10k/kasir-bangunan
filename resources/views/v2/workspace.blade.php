<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Ruang Kerja V2 — Pratinjau</title>
@include('v2.ui-styles')
@include('v2.pos-cashier-styles')
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@include('v2.incumbent-styles')
@include('v2.incumbent-bridge')
</head>
<body>
@php
    $shifts = $shifts ?? [];
    $invoices = $invoices ?? [];
    $payments = $payments ?? [];
    $products = $products ?? [];
    $customers = $customers ?? [];
    $sales = $sales ?? [];
    $deliveries = $deliveries ?? [];
    $suppliers = $suppliers ?? [];
    $vehicles = $vehicles ?? [];
    $daily = $daily ?? null;
    $isAdmin = ($role ?? '') === 'admin';
    $openShifts = [];
    foreach ($shifts as $s) { if (($s->status ?? '') === 'open') { $openShifts[] = $s; } }
    $ownOpenShift = collect($shifts)->first(fn ($s) => ($s->status ?? '') === 'open' && (int)$s->user_id === (int)auth()->id());
    $statusLabel = ['open'=>'Terbuka','closed'=>'Ditutup','review'=>'Perlu diperiksa','unpaid'=>'Belum lunas','partial'=>'Dibayar sebagian','paid'=>'Lunas','posted'=>'Terverifikasi','pending'=>'Menunggu verifikasi','proposed'=>'Diajukan','prepared'=>'Disiapkan','sent'=>'Dikirim','received'=>'Diterima','rejected'=>'Ditolak'];
    $methodLabel = ['cash'=>'Tunai','transfer'=>'Transfer','credit'=>'Kredit'];
    $sourceLabel = ['admin'=>'Dari admin','carry'=>'Sisa shift','both'=>'Admin + sisa shift'];
    $deliveryBySale = [];
    foreach ($deliveries as $d) { $deliveryBySale[(int) ($d->sale_id ?? 0)] = $d; }
    $reportDate = is_array($daily) && isset($daily['date']) ? $daily['date'] : now()->toDateString();
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
@endphp

<aside id="workspace-sidebar" class="workspace-sidebar app-sidebar collapsed" aria-label="Navigasi aplikasi">
    <div class="sidebar-brand sidebar-header"><div class="brand-mark" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4zM8 8h8M8 12h3M8 16h8"/></svg></div><div class="sidebar-brand-text"><strong>Kasir Bangunan</strong><small>Terminal Material</small></div><button type="button" class="sidebar-close secondary" aria-label="Tutup menu">×</button></div>
    <nav class="nav sidebar-nav" aria-label="Ruang kerja" data-role="{{ $role }}" data-default-task="{{ $isAdmin ? 'ringkasan' : 'kasir' }}">
        @if($isAdmin)
        <span class="workspace-nav-group">Pengawasan</span>
        <a href="#ringkasan" data-task="ringkasan"><span>Ringkasan Admin</span></a>
        <a href="#persetujuan" data-task="persetujuan"><span>Persetujuan Pembayaran</span></a>
        @endif
        <span class="workspace-nav-group">Operasional</span>
        <a href="#kasir" data-task="kasir"><span>Kasir (POS)</span></a>
        <a href="#tagihan" data-task="tagihan"><span>Tagihan</span></a>
        <a href="#pengiriman" data-task="pengiriman"><span>Pengiriman</span></a>
        @if($isAdmin)
        <a href="#penerimaan" data-task="penerimaan"><span>Penerimaan barang</span></a>
        <span class="workspace-nav-group">Master Data</span>
        <a href="#master-product" data-task="master-product"><span>Produk</span></a>
        <a href="#master-customer" data-task="master-customer"><span>Pelanggan</span></a>
        <a href="#master-supplier" data-task="master-supplier"><span>Supplier</span></a>
        <a href="#master-employee" data-task="master-employee"><span>Karyawan</span></a>
        <a href="#master-vehicle" data-task="master-vehicle"><span>Mobil</span></a>
        <span class="workspace-nav-group">Pengguna & Akses</span>
        <a href="#akun" data-task="akun"><span>Akun Login</span></a>
        @endif
        <span class="workspace-nav-group">Kas & Laporan</span>
        <a href="#shift" data-task="shift"><span>{{ $isAdmin ? 'Pengawasan Shift' : 'Shift Saya' }}</span></a>
        <a href="#laporan" data-task="laporan"><span>{{ $isAdmin ? 'Laporan Semua Kasir' : 'Laporan Saya' }}</span></a>
        @if($isAdmin)
        <a href="#keuangan" data-task="keuangan"><span>Laporan Keuangan</span></a>
        @endif
    </nav>
    <div class="sidebar-footer"><span class="role-label">{{ $isAdmin ? 'Admin' : 'Kasir' }}</span><form method="post" action="{{ route('logout') }}">@csrf<button class="secondary" title="Keluar"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M9 4H4v16h5M13 7l5 5-5 5M8 12h10"/></svg><span>Keluar</span></button></form></div>
</aside>
<button type="button" id="sidebar-overlay" class="sidebar-overlay" aria-label="Tutup menu"></button>
<header class="top main-topbar"><div class="wrap">
    <button type="button" class="mobile-menu-toggle secondary" aria-label="Buka menu" aria-controls="workspace-sidebar" aria-expanded="false"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
    <div class="top-context"><strong>Ruang kerja</strong><span>Kasir Bangunan</span></div>
    <div class="header-state"><span>{{ $isAdmin ? 'Admin' : 'Kasir' }}</span><a href="#shift" data-task-link="shift">{{ $ownOpenShift ? 'Shift #'.$ownOpenShift->id.' terbuka' : 'Buka shift' }}</a></div>
</div></header>

<main class="wrap main-content">
    <p class="preview" role="note">{{ config('v2.enabled') ? 'Operasional V2 · transaksi tersimpan di server.' : 'Pratinjau V2 / belum menggantikan produksi. Data dan tombol di layar ini bukan rilis final.' }}</p>

    <div class="work-heading"><div><h2 id="workspace-title">Penjualan</h2><p id="workspace-description">Pilih barang, catat pembayaran, lalu simpan nota.</p></div><div class="work-state"><strong>{{ $ownOpenShift ? 'Shift #'.$ownOpenShift->id.' terbuka' : 'Belum buka shift' }}</strong><a href="#shift" data-task-link="shift">{{ $ownOpenShift ? 'Kelola shift' : 'Buka shift dahulu' }}</a></div></div>
    <div id="task-panels"></div>
    @if($isAdmin)
    <section class="card" id="admin-overview" aria-labelledby="admin-overview-title">
        <h2 id="admin-overview-title">Ringkasan Pengawasan Admin</h2>
        <p class="note">Anda masuk sebagai Admin. Awasi operasional seluruh kasir di sini. Menu Kasir (POS) tetap tersedia bila Anda perlu melayani transaksi.</p>
        <dl class="admin-overview-metrics">
            <div><dt>Penjualan pada tanggal laporan {{ $reportDate }}</dt><dd>{{ $rp($daily['revenue']['total_sales'] ?? '0.00') }}</dd></div>
            <div><dt>Shift terbuka pada tanggal laporan</dt><dd>{{ $daily['shifts']['open'] ?? 0 }}</dd></div>
            <div><dt>Shift perlu diperiksa pada tanggal laporan</dt><dd>{{ $daily['shifts']['review'] ?? 0 }}</dd></div>
        </dl>
        <p><a href="#persetujuan" data-task-link="persetujuan">Periksa pembayaran</a> · <a href="#shift" data-task-link="shift">Awasi shift</a> · <a href="#laporan" data-task-link="laporan">Laporan seluruh kasir</a></p>
    </section>
    <section class="card" id="financial-reports" aria-labelledby="financial-reports-title">
        <h2 id="financial-reports-title">Keuangan & Pembukuan</h2>
        <p class="note">Laporan buku besar seluruh usaha, terpisah dari uang laci dan laporan shift. Akun COA adalah akun pembukuan, bukan akun login pengguna.</p>
        <ul>
            <li><a href="{{ route('journals.index') }}">Jurnal Umum</a></li>
            <li><a href="{{ route('reports.balance_sheet') }}">Neraca</a></li>
            <li><a href="{{ route('reports.profit_loss') }}">Laba Rugi</a></li>
            <li><a href="{{ route('reports.cash_flow') }}">Arus Kas</a></li>
            <li><a href="{{ route('coa.index') }}">Bagan Akun (COA)</a></li>
        </ul>
        <p class="note">Halaman pembukuan menggunakan tampilan laporan terpisah. Saat mode V2 aktif, perubahan melalui halaman lama tetap dikunci.</p>
    </section>
    @endif
    <div class="split">
        <div class="stack">

            <section class="card" aria-labelledby="h-shift">
                <h2 id="h-shift">Shift</h2>
                <p class="note">Buka shift mengunci kas awal. Tutup shift membandingkan uang fisik dengan perkiraan server.</p>

                <form id="form-open" action="{{ route('v2.shifts.open') }}" novalidate>
                    <fieldset style="border:0;padding:0;margin:0">
                        <legend class="lbl">Buka shift</legend>
                        <div class="row">
                            <div class="field">
                                <label for="open-cash">Kas awal</label>
                                <input id="open-cash" name="cash" type="text" inputmode="decimal" autocomplete="off" maxlength="30" required>
                            </div>
                            <div class="field">
                                <label for="open-source">Sumber</label>
                                <select id="open-source" name="source" required>
                                    <option value="admin">Dari admin</option>
                                    <option value="carry">Sisa shift sebelumnya</option>
                                    <option value="both">Admin + sisa shift</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="open-carry">Bawa sisa dari shift</label>
                                <select id="open-carry" name="carry_from">
                                    <option value="">— tanpa carry —</option>
                                    @foreach ($shifts as $s)
                                        @if (($s->status ?? '') === 'closed')
                                            <option value="{{ $s->id }}">#{{ $s->id }} · terkunci {{ $rp($s->counted_cash ?? null) }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <p class="note">Kas awal adalah total uang di laci. Sisa shift hanya dapat dibawa sekali, paling banyak sebesar uang fisik yang ditutup.</p>
                        <p><button type="submit">Buka shift</button></p>
                        <p class="err" data-error tabindex="-1" hidden></p>
                        <p class="ok" data-ok hidden></p>
                    </fieldset>
                </form>

                <h3 class="lbl" style="margin-top:14px">Riwayat shift</h3>
                @if (count($shifts) === 0)
                    <p class="note">Belum ada shift pada data yang dikirim.</p>
                @else
                    <ul class="plain">
                        @foreach ($shifts as $s)
                            <li>
                                <div><strong>#{{ $s->id }}</strong> · <span class="status">{{ $statusLabel[$s->status ?? ''] ?? '-' }}</span> · sumber {{ $sourceLabel[$s->source ?? ''] ?? '-' }}</div>
                                <div class="note">
                                    kas awal {{ $rp($s->opening_cash ?? null) }} · perkiraan {{ $rp($s->expected_cash ?? null) }} ·
                                    fisik {{ $rp($s->counted_cash ?? null) }} · selisih {{ $rp($s->difference ?? null) }}
                                </div>
                                @if (($s->status ?? '') === 'open')
                                    <form class="form-close" action="{{ route('v2.shifts.close', ['id' => $s->id]) }}" data-shift="{{ $s->id }}" novalidate>
                                        <div class="row">
                                            <div class="field">
                                                <label for="counted-{{ $s->id }}">Uang fisik dihitung (shift #{{ $s->id }})</label>
                                                <input id="counted-{{ $s->id }}" name="counted" type="text" inputmode="decimal" autocomplete="off" maxlength="30" required>
                                            </div>
                                            <div class="field" style="flex:0 0 auto">
                                                <button type="submit" class="secondary">Tutup shift</button>
                                            </div>
                                        </div>
                                        <p class="err" data-error tabindex="-1" hidden></p>
                                        <p class="ok" data-ok hidden></p>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @include('v2.pos-cashier')
        </div>

        <div class="stack">
            <section class="card" id="tagihan" aria-labelledby="h-collect">
                <h2 id="h-collect">Piutang pelanggan</h2>
                <p class="note">Pilih tagihan yang belum lunas. Tunai langsung tercatat; transfer menunggu verifikasi admin.</p>
                @if (count($invoices) === 0)
                    <p class="note">Belum ada tagihan. Penjualan kredit dan penerimaan berutang akan muncul di sini.</p>
                @else
                    <ul class="plain">
                        @foreach ($invoices as $inv)
                            <li>
                                <div>
                                    <strong>{{ $inv->number ?? ('#'.$inv->id) }}</strong> ·
                                    <span class="status">{{ $statusLabel[$inv->status ?? ''] ?? '-' }}</span> ·
                                    {{ ($inv->direction ?? '') === 'payable' ? 'Utang supplier' : 'Piutang' }}
                                </div>
                                <div class="note">nilai {{ $rp($inv->amount ?? null) }} · terbayar {{ $rp($inv->paid ?? null) }} · pelanggan id {{ $inv->party_id ?? '-' }}</div>
                                @if (($inv->direction ?? '') === 'receivable' && ($inv->status ?? '') !== 'paid')
                                    <form class="form-collect" action="{{ route('v2.payments.store', ['id' => $inv->id]) }}" data-invoice="{{ $inv->id }}" novalidate>
                                        <div class="row">
                                            <div class="field">
                                                <label for="amt-{{ $inv->id }}">Jumlah (invoice {{ $inv->number ?? $inv->id }})</label>
                                                <input id="amt-{{ $inv->id }}" name="amount" type="text" inputmode="decimal" autocomplete="off" maxlength="30" required>
                                            </div>
                                            <div class="field">
                                                <label for="meth-{{ $inv->id }}">Metode</label>
                                                <select id="meth-{{ $inv->id }}" name="method">
                                                    <option value="cash">Tunai</option>
                                                    <option value="transfer">Transfer</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="field" style="margin-top:8px">
                                            <label for="ref-{{ $inv->id }}">Referensi (opsional)</label>
                                            <input id="ref-{{ $inv->id }}" name="reference" type="text" maxlength="255" autocomplete="off">
                                        </div>
                                        <div class="field">
                                            <label for="key-{{ $inv->id }}">Referensi sistem (otomatis)</label>
                                            <input id="key-{{ $inv->id }}" name="idempotency_key" type="text" maxlength="100" readonly required>
                                        </div>
                                        <p><button type="submit">Catat pembayaran</button></p>
                                        <p class="err" data-error tabindex="-1" hidden></p>
                                        <p class="ok" data-ok hidden></p>
                                    </form>
                                @else
                                    <p class="note">Pembayaran supplier diajukan melalui formulir di bagian Tagihan; admin mengesahkan pembayaran aktual.</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="card" aria-labelledby="h-verify">
                <h2 id="h-verify">Verifikasi pembayaran</h2>
                @if ($isAdmin)
                    <p class="note">Pastikan dana transfer benar-benar diterima, atau pembayaran supplier benar-benar dilakukan, sebelum mengesahkan.</p>
                @else
                    <p class="note">Pembayaran yang Anda ajukan menunggu pemeriksaan admin.</p>
                @endif
                @php $awaiting = []; @endphp
                @foreach ($payments as $pm)
                    @if (in_array(($pm->status ?? ''), ['pending', 'proposed'], true))
                        @php $awaiting[] = $pm; @endphp
                    @endif
                @endforeach
                @if (count($awaiting) === 0)
                    <p class="note">Tidak ada pembayaran menunggu.</p>
                @else
                    <ul class="plain">
                        @foreach ($awaiting as $pm)
                            <li>
                                <div><strong>#{{ $pm->id }}</strong> · invoice {{ $pm->invoice_id ?? '-' }} · <span class="status">{{ $statusLabel[$pm->status] ?? $pm->status }}</span></div>
                                <div class="note">nilai {{ $rp($pm->amount ?? null) }} · {{ $methodLabel[$pm->method ?? ''] ?? '-' }} · diminta oleh {{ $pm->requested_by ?? '-' }}</div>
                                @if ($isAdmin)
                                    <form class="form-approve" action="{{ route('v2.payments.approve', ['id' => $pm->id]) }}" novalidate>
                                        <p><button type="submit" class="secondary">Verifikasi #{{ $pm->id }}</button></p>
                                        <p class="err" data-error tabindex="-1" hidden></p>
                                        <p class="ok" data-ok hidden></p>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @include('v2.sales-history')

            <section class="card" aria-labelledby="h-deliveries">
                <h2 id="h-deliveries">Pengiriman</h2>
                @if (count($deliveries) === 0)
                    <p class="note">Belum ada pengiriman pada data yang dikirim.</p>
                @else
                    <div class="scroll" tabindex="0" role="region" aria-label="Daftar pengiriman">
                        <table>
                            <caption class="sr">Daftar pengiriman terakhir</caption>
                            <thead><tr><th scope="col">Nomor</th><th scope="col">Tujuan</th><th scope="col">Status</th><th scope="col">Dokumen</th></tr></thead>
                            <tbody>
                            @foreach ($deliveries as $d)
                                <tr>
                                    <td>{{ $d->number ?? ('#'.$d->id) }}</td>
                                    <td>{{ $d->destination ?? '-' }}</td>
                                    <td>{{ $statusLabel[$d->status ?? ''] ?? '-' }}</td>
                                    <td><a href="{{ route('v2.documents', ['id' => $d->sale_id, 'kind' => 'delivery']) }}">Surat jalan</a></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>


        </div>
    </div>

    <section id="admin" aria-labelledby="h-admin">
        <h2 id="h-admin" class="section-title">Admin dan operasional lanjutan</h2>
        @include('v2.sprint2')
        @if($isAdmin)@include('v2.accounts')@endif
        @include('v2.driver-operations')
        @if (! $isAdmin)
            <section class="card">
                <p class="note">Menu admin (master data, impor Excel, penerimaan barang berutang, ritase, saldo awal) hanya untuk peran admin. Server menolak peran lain dengan 403.</p>
            </section>
        @endif
    </section>

    <section class="card" id="laporan" aria-labelledby="h-laporan">
        <h2 id="h-laporan">Laporan harian per tanggal buka shift</h2>
        @if($isAdmin)
            <p class="note">Laporan keuangan dari buku besar tersedia terpisah dari ringkasan shift.</p>
            <p><a href="{{ route('reports.cash_flow') }}">Buka Laporan Arus Kas</a></p>
        @endif
        <form method="get" action="{{ route('v2.index') }}" class="row" novalidate>
            <div class="field">
                <label for="report-date">Tanggal laporan</label>
                <input id="report-date" name="date" type="date" value="{{ $reportDate }}" required>
            </div>
            <div class="field" style="flex:0 0 auto">
                <button type="submit">Tampilkan</button>
            </div>
        </form>
        @if (! is_array($daily))
            <p class="note">Ringkasan harian belum dikirim server untuk permintaan ini. Pilih tanggal lalu tekan Tampilkan.</p>
        @else
            <p class="note">
                Cakupan: {{ ($daily['scope'] ?? 'own') === 'all' ? 'semua kasir (admin)' : 'shift milik sendiri' }} ·
                {{ (int) ($daily['sales_count'] ?? 0) }} penjualan ·
                {{ (int) ($daily['shifts']['count'] ?? 0) }} shift
                ({{ (int) ($daily['shifts']['open'] ?? 0) }} buka, {{ (int) ($daily['shifts']['closed'] ?? 0) }} tutup, {{ (int) ($daily['shifts']['review'] ?? 0) }} review).
                Mata uang {{ $daily['currency'] ?? 'IDR' }}.
            </p>
            <div class="totals">
                <div class="tile">
                    <h3>Tunai (kas laci)</h3>
                    <dl>
                        <div><dt>Kas awal</dt><dd>{{ $rp($daily['cash']['opening_float'] ?? null) }}</dd></div>
                        <div><dt>Uang masuk</dt><dd>{{ $rp($daily['cash']['collections'] ?? null) }}</dd></div>
                        <div><dt>Uang keluar</dt><dd>{{ $rp($daily['cash']['out'] ?? null) }}</dd></div>
                        <div><dt>Seharusnya di laci</dt><dd>{{ $rp($daily['cash']['expected'] ?? null) }}</dd></div>
                        <div><dt>Fisik dihitung</dt><dd>{{ ($daily['cash']['counted'] ?? null) === null ? 'belum dihitung' : $rp($daily['cash']['counted']) }}</dd></div>
                        <div><dt>Selisih</dt><dd>{{ ($daily['cash']['difference'] ?? null) === null ? '—' : $rp($daily['cash']['difference']) }}</dd></div>
                    </dl>
                </div>
                <div class="tile">
                    <h3>Bank (transfer masuk)</h3>
                    <dl>
                        <div><dt>Transfer terverifikasi</dt><dd>{{ $rp($daily['bank']['posted'] ?? null) }}</dd></div>
                    </dl>
                </div>
                <div class="tile pending">
                    <h3>Menunggu verifikasi</h3>
                    <dl>
                        <div><dt>Transfer pending</dt><dd>{{ $rp($daily['bank']['pending'] ?? null) }}</dd></div>
                    </dl>
                    <p class="note" style="margin:6px 0 0">Belum masuk bank sampai admin memverifikasi.</p>
                </div>
                <div class="tile">
                    <h3>Penjualan</h3>
                    <dl>
                        <div><dt>Barang sebelum diskon</dt><dd>{{ $rp($daily['revenue']['goods_gross'] ?? null) }}</dd></div>
                        <div><dt>Diskon</dt><dd>{{ $rp($daily['revenue']['discount'] ?? null) }}</dd></div>
                        <div><dt>Barang bersih</dt><dd>{{ $rp($daily['revenue']['goods_net'] ?? null) }}</dd></div>
                        <div><dt>Ongkir</dt><dd>{{ $rp($daily['revenue']['delivery'] ?? null) }}</dd></div>
                        <div><dt>Total penjualan</dt><dd>{{ $rp($daily['revenue']['total_sales'] ?? null) }}</dd></div>
                    </dl>
                </div>
            </div>
        @endif
    </section>
</main>

@include('v2.pos-cart-template')

<script>
(function () {
    var tokenMeta = document.querySelector('meta[name="csrf-token"]');
    var token = tokenMeta ? tokenMeta.getAttribute('content') : '';
    var docsBase = @json(url('/v2/documents'));

    function uuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        var b = new Uint8Array(16);
        if (window.crypto && window.crypto.getRandomValues) { window.crypto.getRandomValues(b); }
        else { for (var i = 0; i < 16; i++) { b[i] = Math.floor(Math.random() * 256); } }
        return Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
    }

    function freshKey() { return 'wf-' + uuid(); }

    function setKey(input) { if (input) { input.value = freshKey(); } }

    function showErr(form, text) {
        var box = form.querySelector('[data-error]');
        var ok = form.querySelector('[data-ok]');
        if (ok) { ok.hidden = true; ok.textContent = ''; }
        if (!box) { return; }
        box.textContent = text;
        box.hidden = false;
        box.setAttribute('tabindex', '-1');
        box.focus();
    }

    function clearMsgs(form) {
        var err = form.querySelector('[data-error]');
        var ok = form.querySelector('[data-ok]');
        if (err) { err.hidden = true; err.textContent = ''; }
        if (ok) { ok.hidden = true; ok.textContent = ''; }
    }

    function errorText(data) {
        var out = [];
        if (data && data.errors) {
            Object.keys(data.errors).forEach(function (k) {
                var v = data.errors[k];
                out.push(Array.isArray(v) ? v.join(' ') : String(v));
            });
        }
        if (data && data.message) { out.unshift(data.message); }
        if (out.length === 0) { out.push('Permintaan gagal. Periksa isian lalu coba lagi.'); }
        return out.join(' ');
    }

    function post(form, url, payload) {
        var btn = form.querySelector('button[type="submit"]');
        if (form.dataset.busy) { return Promise.resolve(null); }
        form.dataset.busy = '1';
        clearMsgs(form);
        if (btn) { btn.disabled = true; }
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) { throw data; }
                return data;
            });
        }).then(function (data) {
            var ok = form.querySelector('[data-ok]');
            if (ok) {
                ok.textContent = 'Berhasil disimpan.';
                ok.hidden = false;
            }
            return data;
        }).catch(function (data) {
            showErr(form, errorText(data));
            return null;
        }).then(function (data) {
            if (btn) { btn.disabled = false; }
            delete form.dataset.busy;
            return data;
        });
    }

    function val(form, name) {
        var el = form.elements[name];
        return el ? String(el.value).trim() : '';
    }

    function reloadSoon() { window.setTimeout(function () { window.location.reload(); }, 800); }

    // ---- buka shift ----
    var openForm = document.getElementById('form-open');
    if (openForm) {
        var sourceSel = document.getElementById('open-source');
        var carrySel = document.getElementById('open-carry');
        function syncCarry() {
            var need = sourceSel.value !== 'admin';
            carrySel.disabled = !need;
            if (!need) { carrySel.value = ''; }
        }
        sourceSel.addEventListener('change', syncCarry);
        syncCarry();
        openForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var payload = { cash: val(openForm, 'cash'), source: val(openForm, 'source') };
            var carry = val(openForm, 'carry_from');
            payload.carry_from = carry === '' ? null : carry;
            post(openForm, openForm.action, payload).then(function (data) { if (data) { reloadSoon(); } });
        });
    }

    // ---- tutup shift ----
    Array.prototype.forEach.call(document.querySelectorAll('.form-close'), function (form) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            post(form, form.action, { counted: val(form, 'counted') }).then(function (data) { if (data) { reloadSoon(); } });
        });
    });

    // ---- checkout ----
    var checkout = document.getElementById('form-checkout');
    var itemsBox = document.getElementById('items');
    var tpl = document.getElementById('item-tpl');
    var addBtn = document.getElementById('add-item');

    function addRow(focus) {
        var node = tpl.content.firstElementChild.cloneNode(true);
        var idx = itemsBox.children.length + 1;
        node.querySelectorAll('[data-name]').forEach(function (el) {
            el.setAttribute('aria-label', el.getAttribute('data-name') === 'product_id' ? ('Barang baris ' + idx) :
                el.getAttribute('data-name') === 'quantity' ? ('Jumlah baris ' + idx) :
                el.getAttribute('data-name') === 'discount_type' ? ('Jenis diskon baris ' + idx) :
                ('Nilai diskon baris ' + idx));
        });
        node.querySelector('[data-remove]').addEventListener('click', function () { node.remove(); });
        itemsBox.appendChild(node);
        if (focus) { node.querySelector('[data-name=quantity]').focus(); }
        return node;
    }

    if (checkout && tpl && itemsBox) {
        setKey(document.getElementById('checkout-key'));
        window.v2AddCartRow = addRow;
        addBtn.addEventListener('click', function () { addRow(true); });

        var fulfillment = document.getElementById('fulfillment');
        var dest = document.getElementById('destination');
        function syncDest() {
            var on = fulfillment.value === 'delivery';
            dest.disabled = !on;
            dest.required = on;
            if (on) { dest.focus(); } else { dest.value = ''; }
        }
        fulfillment.addEventListener('change', syncDest);
        syncDest();

        var payMethod = document.getElementById('payment-method');
        var deposit = document.getElementById('deposit-method');
        var paidHint = document.getElementById('paid-hint');
        function syncPay() {
            var credit = payMethod.value === 'credit';
            deposit.disabled = !credit;
            if (!credit) { deposit.value = 'cash'; }
            paidHint.textContent = credit
                ? 'Kredit: isi DP yang dibayar sekarang di kolom Dibayar; sisa tagihan menjadi piutang pelanggan.'
                : 'Tunai berlebih menjadi kembalian; transfer menerima sebesar total.';
        }
        payMethod.addEventListener('change', syncPay);
        syncPay();

        checkout.addEventListener('submit', function (ev) {
            ev.preventDefault();
            if (!checkout.__finalConfirmed || checkout.dataset.busy) { return; }
            checkout.__finalConfirmed = false;
            var items = [];
            Array.prototype.forEach.call(itemsBox.children, function (row) {
                var pid = row.querySelector('[data-name="product_id"]').value;
                var qty = row.querySelector('[data-name="quantity"]').value.trim();
                if (pid === '' && qty === '') { return; }
                var dtype = row.querySelector('[data-name="discount_type"]').value;
                var dvalue = row.querySelector('[data-name="discount_value"]').value.trim();
                var item = { product_id: pid, quantity: qty };
                if (dtype !== '' && dvalue !== '') { item.discount = { type: dtype, value: dvalue }; }
                items.push(item);
            });
            var method = val(checkout, 'payment_method');
            var payload = {
                items: items,
                delivery_fee: val(checkout, 'delivery_fee'),
                payment_method: method,
                deposit_method: method === 'credit' ? val(checkout, 'deposit_method') : null,
                paid_amount: val(checkout, 'paid_amount'),
                idempotency_key: val(checkout, 'idempotency_key'),
                fulfillment: val(checkout, 'fulfillment')
            };
            var cid = val(checkout, 'customer_id');
            payload.customer_id = cid === '' ? null : cid;
            payload.destination = payload.fulfillment === 'delivery' ? val(checkout, 'destination') : null;
            var dt = document.getElementById('inv-disc-type').value;
            var dv = val(checkout, 'discount_value');
            if (dv !== '') { payload.discount = { type: dt, value: dv }; }

            var result = document.getElementById('checkout-result');
            result.replaceChildren();
            post(checkout, checkout.action, payload).then(function (data) {
                if (!data) { return; }
                var saleId = data.sale_id || data.id || null;
                var number = data.number || data.sale_number || (saleId ? ('#' + saleId) : '');
                var line = document.createElement('p');
                line.className = 'ok';
                var text = 'Penjualan tersimpan';
                var states = {paid:'Lunas',partial:'Dibayar sebagian',unpaid:'Belum lunas'};
                function rupiah(value) { var s=String(value), parts=s.split('.'); return 'Rp '+parts[0].replace(/\B(?=(\d{3})+(?!\d))/g,'.')+(parts[1] && !/^0+$/.test(parts[1]) ? ','+parts[1] : ''); }
                if (number) { text += ' · ' + number; }
                if (data.total) { text += ' · total ' + rupiah(data.total); }
                if (data.change && String(data.change) !== '0.00') { text += ' · kembalian ' + rupiah(data.change); }
                if (data.status) { text += ' · ' + (states[data.status] || data.status); }
                line.textContent = text + '.';
                result.appendChild(line);
                if (saleId) {
                    var link = document.createElement('a');
                    link.href = docsBase + '/' + encodeURIComponent(saleId) + '/receipt';
                    link.textContent = 'Buka nota penjualan';
                    link.style.fontWeight = '600';
                    result.appendChild(link);
                }
                setKey(document.getElementById('checkout-key'));
                while (itemsBox.firstChild) { itemsBox.removeChild(itemsBox.firstChild); }
                checkout.dispatchEvent(new CustomEvent('v2:sale-saved', {detail:data}));
            });
        });
    }

    // ---- penagihan per invoice ----
    Array.prototype.forEach.call(document.querySelectorAll('.form-collect'), function (form) {
        setKey(form.elements['idempotency_key']);
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var payload = {
                amount: val(form, 'amount'),
                method: val(form, 'method'),
                idempotency_key: val(form, 'idempotency_key'),
                reference: val(form, 'reference') === '' ? null : val(form, 'reference')
            };
            post(form, form.action, payload).then(function (data) {
                if (data) { setKey(form.elements['idempotency_key']); reloadSoon(); }
            });
        });
    });

    // ---- verifikasi admin ----
    Array.prototype.forEach.call(document.querySelectorAll('.form-approve'), function (form) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            post(form, form.action, {}).then(function (data) { if (data) { reloadSoon(); } });
        });
    });
})();
</script>
@include('v2.ui-workspace')
@include('v2.pos-cashier-script')
@include('partials.compact-form-styles')
@include('v2.shared-selects')
@include('v2.shared-date-picker')
@include('partials.filter-disclosure-script')
@include('v2.shared-controls-styles')
@include('v2.master-pages-styles')
</body>
</html>
