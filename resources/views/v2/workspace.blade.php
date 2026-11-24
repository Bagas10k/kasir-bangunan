<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('partials.numeric-input-guard')
@include('v2.ui-live')
@include('v2.ui-number-tween')
@include('v2.ui-money')
<title>Ruang Kerja — Kasir Bangunan</title>
@include('v2.ui-styles')
@include('v2.pos-cashier-styles')
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
@include('v2.incumbent-styles')
@include('v2.incumbent-bridge')
{{-- Gaya V2 dimuat di <head>: kalau di bawah <body>, tampilan lama sempat terlihat sekejap saat halaman dibuka. --}}
@include('v2.shared-controls-styles')
@include('v2.master-pages-styles')
@include('v2.data-navigation-styles')
@include('v2.ui-tokens')
@include('v2.ui-system')
@include('v2.ui-forms-styles')
@include('v2.ui-sidebar')
@include('v2.ui-theme')
@include('v2.ui-pick')
@include('v2.ui-minimal')
@include('v2.pos-theme')
@include('v2.ui-dna')
@include('v2.sale-detail-styles')
@include('partials.table-filter-styles')
@include('v2.responsive-refinements')
@include('v2.receipt-restock-styles')
@include('v2.loading-styles')
<template id="workspace-skeleton-template">@include('v2.loading-skeleton')</template>
{{-- Isi baru tampil setelah panel dibentuk; tanpa ini susunan mentah sempat berkedip saat halaman dibuka. --}}
{{-- Keadaan sidebar dipasang sebelum gambar pertama supaya tidak melompat dari tertutup ke terbuka. --}}
<script>try{localStorage.removeItem('v2-sidebar-pinned');}catch{}</script>
<style>main.wrap:not([data-ready]){visibility:hidden}main.wrap:not([data-ready])::after{content:'';display:block;min-height:60vh}</style>
<noscript><style>main.wrap{visibility:visible!important}#workspace-loading{display:none}</style></noscript>
<script>document.addEventListener('DOMContentLoaded',()=>document.querySelector('main.wrap')?.setAttribute('data-ready','1'),{once:true});</script>
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
    $activeStoreShift = $activeStoreShift ?? null;
    $statusLabel = ['open'=>'Terbuka','closed'=>'Ditutup','review'=>'Perlu diperiksa','unpaid'=>'Belum lunas','partial'=>'Dibayar sebagian','paid'=>'Lunas','posted'=>'Terverifikasi','pending'=>'Menunggu verifikasi','proposed'=>'Diajukan','prepared'=>'Disiapkan','sent'=>'Dikirim','received'=>'Diterima','rejected'=>'Ditolak'];
    $methodLabel = ['cash'=>'Tunai','transfer'=>'Transfer','credit'=>'Kredit'];
    $sourceLabel = ['main'=>'Kas Utama','transfer'=>'Bank Utama','cash'=>'Laci kas harian','admin'=>'Dari admin','carry'=>'Sisa kas sebelumnya','both'=>'Admin + sisa kas'];

    $deliveryBySale = [];
    foreach ($deliveries as $d) { $deliveryBySale[(int) ($d->sale_id ?? 0)] = $d; }
    $reportDate = is_array($daily) && isset($daily['date']) ? $daily['date'] : now()->toDateString();
    $rp = fn ($value): string => \App\Services\V2\Money::display($value);
    $when = fn ($value): string => $value ? \Carbon\Carbon::parse($value)->locale('id')->translatedFormat('j M Y, H:i') : '—';
@endphp

<aside id="workspace-sidebar" class="workspace-sidebar app-sidebar collapsed" aria-label="Navigasi aplikasi">
    <div class="sidebar-brand sidebar-header"><div class="brand-mark" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4zM8 8h8M8 12h3M8 16h8"/></svg></div><div class="sidebar-brand-text"><strong>Kasir Bangunan</strong><small>Terminal Material</small></div><button type="button" class="sidebar-close secondary" aria-label="Tutup menu">×</button></div>
    @include('partials.workspace-navigation', ['workspacePage' => true])
    <div class="sidebar-footer"><span class="role-label">{{ $isAdmin ? 'Admin' : 'Kasir' }}</span><form method="post" action="{{ route('logout') }}">@csrf<button class="secondary" title="Keluar"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M9 4H4v16h5M13 7l5 5-5 5M8 12h10"/></svg><span>Keluar</span></button></form></div>
</aside>
@include('partials.workspace-header', ['workspacePage' => true])

<div id="workspace-loading">@include('v2.loading-skeleton')</div>
<main class="wrap main-content">
    @unless(config('v2.enabled'))<p class="preview" role="note">Pratinjau V2 / belum menggantikan produksi. Data dan tombol di layar ini bukan rilis final.</p>@endunless

    @if(config('v2.enabled'))<p class="ui-eyebrow">Ruang kerja {{ $isAdmin ? 'Admin' : 'Kasir' }}</p>@endif
    <header class="ui-page-header"><h1 id="workspace-title">Penjualan</h1><p id="workspace-description">Pilih barang, catat pembayaran, lalu simpan nota.</p></header>
    <div id="task-panels"></div>
    @include('v2.workspace-monitoring')
    @if($isAdmin)
    @include('v2.admin-summary')
    @endif
    <div class="split">
        <div class="stack">

            <section class="card" aria-labelledby="h-shift">
                <h2 id="h-shift">Kas harian</h2>
                <p class="note">Satu catatan laci per tanggal, dibuka dan ditutup sekali oleh admin. Semua kasir memakai laci yang sama; nama petugas tetap tercatat di transaksi.</p>

                @if($isAdmin)
                <p class="note" id="cash-day-open-help" data-live-skip>
                @if($activeStoreShift)
                Kas harian {{ substr((string) $activeStoreShift->opened_at, 0, 10) }} masih terbuka. Selesaikan penghitungan dan penutupannya sebelum memulai hari berikutnya.
                @elseif(!empty($todayCashDay))
                Kas harian {{ substr((string) $todayCashDay->opened_at, 0, 10) }} sudah ditutup dan tidak dapat dibuka lagi. Catatan baru tersedia pada hari berikutnya. Perlu setor kas ke bank? Buka <a href="#jurnal" data-task-link="jurnal">Jurnal umum → Pindah dana</a>.
                @else
                Buka satu catatan kas harian untuk tanggal hari ini.
                @endif
                </p>
                <form id="form-open" action="{{ route('v2.shifts.open') }}" data-popup-title="Buka kas harian" data-open-allowed="{{ $activeStoreShift || !empty($todayCashDay) ? '0' : '1' }}" novalidate>
                    <fieldset style="border:0;padding:0;margin:0" @disabled($activeStoreShift || !empty($todayCashDay))>
                        <legend class="sr">Buka kas harian</legend>
                        <div class="row">
                            <div class="field">
                                <label for="open-cash">Kas awal</label>
                                <input id="open-cash" name="cash" type="text" data-money inputmode="decimal" autocomplete="off" maxlength="30" pattern="[0-9]+([.][0-9]{1,2})?" aria-describedby="shift-money-help" required>
                            </div>
                            <div class="field">
                                <label for="open-source">Sumber</label>
                                <select id="open-source" name="source" required>
                                    <option value="admin">Dari admin</option>
                                    <option value="carry">Sisa kas hari sebelumnya</option>
                                    <option value="both">Admin + sisa kas sebelumnya</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="open-carry">Bawa sisa kas dari tanggal</label>
                                <select id="open-carry" name="carry_from">
                                    <option value="">Tidak membawa sisa</option>
                                    @foreach ($shifts as $s)
                                        @if (($s->status ?? '') === 'closed' && (($s->is_shared ?? false) || (int) $s->user_id === (int) auth()->id()))
                                            <option value="{{ $s->id }}">{{ substr((string) $s->opened_at, 0, 10) }} · uang fisik {{ $rp($s->counted_cash ?? null) }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <p class="note" id="shift-money-help">Kas awal adalah total uang di laci. Titik ribuan muncul sendiri saat mengetik, misalnya 100.000; desimal pakai koma. Sisa kas hanya dapat dibawa sekali, paling banyak sebesar uang fisik yang ditutup.</p>
                        <p><button type="submit">Buka kas harian</button></p>
                        <p class="err" data-error tabindex="-1" hidden></p>
                        <p class="ok" data-ok hidden></p>
                    </fieldset>
                </form>
                @else
                <p class="note">Pembukaan, penghitungan uang fisik, dan penutupan kas harian dilakukan admin. Kasir memakai catatan tanggal yang sama.</p>
                @endif

                <h3 class="ui-subhead">Riwayat kas harian</h3>
                <x-ui.toolbar search="Cari tanggal atau pembuka kas" label="Cari kas harian" :chips="['Semua' => 'all', 'Terbuka' => 'status:open', 'Perlu diperiksa' => 'status:review', 'Ditutup' => 'status:closed']"/>
                <div class="ui-table-wrap scroll" data-ui-table tabindex="0" role="region" aria-label="Riwayat kas harian">
                    <table class="ui-table">
                        <caption class="sr">Riwayat kas per tanggal, termasuk catatan lama yang tetap disimpan</caption>
                        <thead><tr><th scope="col">Tanggal kas</th><th scope="col">Dibuka oleh</th><th scope="col">Dibuka</th><th scope="col" class="ui-num">Kas awal</th><th scope="col" class="ui-num">Perkiraan</th><th scope="col" class="ui-num">Uang fisik</th><th scope="col" class="ui-num">Selisih</th><th scope="col">Status</th><th scope="col" class="ui-actions">Aksi</th></tr></thead>
                        <tbody>
                        @forelse ($shifts as $s)
                            <tr data-status="{{ $s->status ?? '' }}">
                                <td class="ui-primary ui-nowrap" data-label="Tanggal kas">{{ substr((string) $s->opened_at, 0, 10) }}<span class="ui-sub">{{ !empty($s->business_date) ? 'Kas harian' : 'Catatan lama' }} · {{ $sourceLabel[$s->source ?? ''] ?? '' }}</span></td>
                                <td data-label="Dibuka oleh">{{ $s->user_name ?? '—' }}</td>
                                <td data-label="Dibuka" class="ui-nowrap">{{ $when($s->opened_at ?? null) }}</td>
                                <td data-label="Kas awal" class="ui-num">{{ $rp($s->opening_cash ?? null) }}</td>
                                <td data-label="Perkiraan" class="ui-num">{{ $rp($s->expected_cash ?? null) }}</td>
                                <td data-label="Uang fisik" class="ui-num">{{ ($s->counted_cash ?? null) === null ? 'Belum dihitung' : $rp($s->counted_cash) }}</td>
                                <td data-label="Selisih" class="ui-num @if(($s->difference ?? null) !== null && (float) $s->difference !== 0.0) ui-neg @endif">{{ ($s->difference ?? null) === null ? '—' : $rp($s->difference) }}</td>
                                <td data-label="Status"><x-ui.status :status="$s->status ?? ''"/></td>
                                <td class="ui-actions">
                                @if ($isAdmin && ($s->status ?? '') === 'open')
                                    <form class="form-close" action="{{ route('v2.shifts.close', ['id' => $s->id]) }}" data-shift="{{ $s->id }}" novalidate>
                                        <h3 class="sr">Tutup kas harian {{ substr((string) $s->opened_at, 0, 10) }}</h3>
                                        <div class="close-recap" data-close-recap hidden></div>
                                        <div class="row">
                                            <div class="field">
                                                <label for="counted-{{ $s->id }}">Uang fisik dihitung ({{ substr((string) $s->opened_at, 0, 10) }})</label>
                                                <input id="counted-{{ $s->id }}" name="counted" type="text" data-money inputmode="decimal" autocomplete="off" maxlength="30" pattern="[0-9]+([.][0-9]{1,2})?" aria-describedby="counted-help-{{ $s->id }}" required>
                                                <p class="note" id="counted-help-{{ $s->id }}">Angka otomatis di atas menunjukkan yang seharusnya ada di laci. Hitung uang fisik, lalu isi hasilnya di sini untuk dibandingkan.</p>
                                                <p class="close-match" data-close-match hidden></p>
                                            </div>
                                            <div class="field" style="flex:0 0 auto">
                                                <button type="submit" class="secondary">Tutup kas harian</button>
                                            </div>
                                        </div>
                                        <p class="err" data-error tabindex="-1" hidden></p>
                                        <p class="ok" data-ok hidden></p>
                                    </form>
                                @else
                                    <span class="ui-muted">—</span>
                                @endif
                                </td>
                            </tr>
                        @empty
                            <tr data-ui-empty-static><td colspan="9" class="ui-empty"><strong>Belum ada kas harian.</strong>{{ $isAdmin ? 'Tekan Buka kas harian untuk memulai.' : 'Menunggu admin membuka kas harian.' }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @include('v2.pos-cashier')
        </div>

        <div class="stack">
            @include('v2.invoices')

            <section class="card" id="payment-verification" aria-labelledby="h-verify" aria-describedby="payment-verification-help">
                <h2 id="h-verify">{{ $isAdmin ? 'Verifikasi pembayaran' : 'Status pembayaran' }}</h2>
                @if ($isAdmin)
                    <p class="note" id="payment-verification-help">Periksa transfer pelanggan dan pengajuan pembayaran supplier sebelum menyetujui. Pembayaran tunai pelanggan langsung tercatat, tanpa verifikasi.</p>
                @else
                    <p class="note" id="payment-verification-help">Pantau transfer pelanggan dan pengajuan pembayaran supplier Anda. Pembayaran yang menunggu diperiksa oleh admin; tunai pelanggan langsung tercatat.</p>
                @endif
                <x-ui.toolbar search="Cari tagihan, nama, atau pengaju" label="Cari pembayaran" default="Menunggu verifikasi" :chips="['Menunggu verifikasi' => 'status:pending,proposed', 'Terverifikasi' => 'status:posted', 'Ditolak' => 'status:rejected', 'Semua' => 'all']"/>
                <div class="ui-table-wrap scroll" data-ui-table tabindex="0" role="region" aria-label="Daftar pembayaran">
                    <table class="ui-table">
                        <caption class="sr">Daftar pembayaran yang diajukan</caption>
                        <thead><tr><th scope="col">Tanggal</th><th scope="col">Tagihan</th><th scope="col">Nama</th><th scope="col">Sumber dana</th><th scope="col" class="ui-num">Jumlah</th>@if($isAdmin)<th scope="col">Pengaju</th>@endif<th scope="col">Status</th>@if($isAdmin)<th scope="col" class="ui-actions">Aksi</th>@endif</tr></thead>
                        <tbody>
                        @forelse ($payments as $pm)
                            <tr data-status="{{ $pm->status ?? '' }}">
                                <td data-label="Tanggal" class="ui-nowrap">{{ $when($pm->created_at ?? null) }}</td>
                                <td data-label="Tagihan" class="ui-primary ui-nowrap">{{ $pm->invoice_number ?? ('#'.($pm->invoice_id ?? '')) }}<span class="ui-sub">{{ ($pm->invoice_direction ?? '') === 'payable' ? 'Utang supplier' : 'Piutang pelanggan' }}</span></td>
                                <td data-label="Nama">{{ $pm->party_name ?? '—' }}</td>
                                <td data-label="Sumber dana">{{ $sourceLabel[$pm->method ?? ''] ?? '—' }}@if(($pm->method ?? '') === 'transfer' && !empty($pm->bank_account_code))<span class="ui-sub">{{ $pm->bank_account_code }}{{ isset($bankAccountNames[$pm->bank_account_code]) ? ' · '.$bankAccountNames[$pm->bank_account_code] : '' }}</span>@endif @if($pm->proof_id)<span class="ui-sub"><a href="{{ route('v2.payments.proof', $pm->id) }}" target="_blank" rel="noopener">Lihat bukti transfer</a></span>@endif</td>
                                <td data-label="Jumlah" class="ui-num">{{ $rp($pm->amount ?? null) }}</td>
                                @if($isAdmin)<td data-label="Pengaju">{{ $pm->requester_name ?? '—' }}</td>@endif
                                <td data-label="Status"><x-ui.status :status="$pm->status ?? ''"/></td>
                                @if($isAdmin)<td class="ui-actions">
                                @if ($isAdmin && in_array(($pm->status ?? ''), ['pending', 'proposed'], true))
                                    <form class="form-approve" action="{{ route('v2.payments.approve', ['id' => $pm->id]) }}" novalidate>
                                        @if(($pm->method ?? '') === 'transfer' && count($bankAccounts ?? []) > 1)
                                            @php $selectedApproveBank = trim((string) ($pm->bank_account_code ?? '')) ?: '1102'; @endphp
                                            <label class="sr" for="approve-bank-{{ $pm->id }}">Rekening bank verifikasi</label>
                                            <select id="approve-bank-{{ $pm->id }}" name="bank_account_code" style="margin-bottom:6px;max-width:210px">
                                                @foreach($bankAccounts as $bankAcc)
                                                    <option value="{{ $bankAcc->account_code }}" @selected($selectedApproveBank === (string) $bankAcc->account_code)>{{ $bankAcc->account_code }} · {{ $bankAcc->account_name }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                        <p><button type="submit" class="secondary">Verifikasi pembayaran</button></p>
                                        <p class="err" data-error tabindex="-1" hidden></p>
                                        <p class="ok" data-ok hidden></p>
                                    </form>
                                @else
                                    <span class="ui-muted">—</span>
                                @endif
                                </td>@endif
                            </tr>
                        @empty
                            <tr data-ui-empty-static><td colspan="{{ $isAdmin ? 8 : 6 }}" class="ui-empty"><strong>Belum ada pembayaran untuk diperiksa.</strong>Transfer pelanggan dan pengajuan pembayaran supplier akan muncul di sini. Pembayaran tunai pelanggan tidak perlu menunggu verifikasi.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @include('v2.sales-history')

        </div>
    </div>

    <section id="admin" aria-labelledby="h-admin">
        <h2 id="h-admin" class="section-title">Admin dan operasional lanjutan</h2>
        @include('v2.form-feedback')
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
        <h2 id="h-laporan">Rincian per tanggal</h2>
        <p class="note">Dihitung per tanggal shift dibuka. Uang tunai, transfer terverifikasi, dan transfer yang masih menunggu dipisahkan agar tidak tercampur.@if($isAdmin) Laporan keuangan dari buku besar tersedia di <a href="{{ route('reports.cash_flow') }}">Laporan Arus Kas</a>.@endif</p>
        <form method="get" action="{{ route(config('v2.enabled') ? 'pos.index' : 'v2.index') }}" class="ui-toolbar" novalidate>
            <div class="field">
                <label for="report-date">Tanggal laporan</label>
                <input id="report-date" name="date" type="date" value="{{ $reportDate }}" required>
            </div>
            <div class="ui-toolbar-actions" style="margin-left:0;align-self:flex-end">
                <button type="submit" class="ui-btn">Tampilkan laporan</button>
            </div>
        </form>
        @if (! is_array($daily))
            <x-ui.empty title="Laporan belum tersedia" hint="Pilih tanggal lalu tekan Tampilkan laporan."/>
        @else
            <p class="ui-status-line">Mencakup {{ ($daily['scope'] ?? 'own') === 'all' ? 'semua kasir' : 'penjualan Anda dan laci toko bersama' }} · {{ (int) ($daily['sales_count'] ?? 0) }} penjualan · {{ (int) ($daily['shifts']['count'] ?? 0) }} shift ({{ (int) ($daily['shifts']['open'] ?? 0) }} terbuka, {{ (int) ($daily['shifts']['closed'] ?? 0) }} ditutup, {{ (int) ($daily['shifts']['review'] ?? 0) }} perlu diperiksa).</p>
            <div class="ui-report-grid">
                <section class="ui-report-card" aria-labelledby="rep-cash">
                    <h3 id="rep-cash">Uang tunai di laci</h3>
                    <dl class="ui-kv">
                        <div><dt>Kas awal</dt><dd>{{ $rp($daily['cash']['opening_float'] ?? null) }}</dd></div>
                        <div><dt>Uang masuk</dt><dd>{{ $rp($daily['cash']['collections'] ?? null) }}</dd></div>
                        <div><dt>Uang keluar</dt><dd>{{ $rp($daily['cash']['out'] ?? null) }}</dd></div>
                        <div class="is-total"><dt>Seharusnya di laci</dt><dd>{{ $rp($daily['cash']['expected'] ?? null) }}</dd></div>
                        <div><dt>Uang fisik dihitung</dt><dd>{{ ($daily['cash']['counted'] ?? null) === null ? 'Belum dihitung' : $rp($daily['cash']['counted']) }}</dd></div>
                        <div><dt>Selisih</dt><dd>{{ ($daily['cash']['difference'] ?? null) === null ? '—' : $rp($daily['cash']['difference']) }}</dd></div>
                    </dl>
                </section>
                <section class="ui-report-card" aria-labelledby="rep-bank">
                    <h3 id="rep-bank">Transfer bank</h3>
                    <dl class="ui-kv">
                        <div class="is-total"><dt>Transfer terverifikasi</dt><dd>{{ $rp($daily['bank']['posted'] ?? null) }}</dd></div>
                        <div><dt>Menunggu verifikasi <x-ui.info-tip key="transfer_menunggu" label="Keterangan transfer menunggu verifikasi"/></dt><dd>{{ $rp($daily['bank']['pending'] ?? null) }}</dd></div>
                        @if(!empty($daily['bank']['by_account']))
                            @foreach($daily['bank']['by_account'] as $bankDay)
                                <div><dt>&nbsp;&nbsp;↳ {{ $bankDay['code'] }} · {{ $bankDay['name'] }}</dt><dd>{{ $rp($bankDay['posted']) }}@if(\Brick\Math\BigDecimal::of((string) $bankDay['pending'])->isPositive()) <small class="ui-muted">(+{{ $rp($bankDay['pending']) }} tunggu)</small>@endif</dd></div>
                            @endforeach
                        @endif
                    </dl>
                    <p class="ui-sub">Transfer yang menunggu belum masuk bank sampai admin memverifikasi.</p>
                </section>
                <section class="ui-report-card" aria-labelledby="rep-sales">
                    <h3 id="rep-sales">Penjualan</h3>
                    <dl class="ui-kv">
                        <div><dt>Barang sebelum diskon</dt><dd>{{ $rp($daily['revenue']['goods_gross'] ?? null) }}</dd></div>
                        <div><dt>Diskon</dt><dd>{{ $rp($daily['revenue']['discount'] ?? null) }}</dd></div>
                        <div><dt>Barang setelah diskon</dt><dd>{{ $rp($daily['revenue']['goods_net'] ?? null) }}</dd></div>
                        <div><dt>Ongkos kirim</dt><dd>{{ $rp($daily['revenue']['delivery'] ?? null) }}</dd></div>
                        <div class="is-total"><dt>Total penjualan</dt><dd>{{ $rp($daily['revenue']['total_sales'] ?? null) }}</dd></div>
                    </dl>
                </section>
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

    function showErr(form, text, errors) {
        var box = form.querySelector('[data-error]');
        var ok = form.querySelector('[data-ok]');
        if (ok) { ok.hidden = true; ok.textContent = ''; }
        if (window.v2FormFeedback) { window.v2FormFeedback.summary(form, text, errors); return; }
        if (!box) { return; }
        box.textContent = text;
        box.hidden = false;
        box.setAttribute('tabindex', '-1');
        box.focus();
    }

    function clearMsgs(form) {
        window.v2FormFeedback?.clear(form);
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
        // Pesan Laravel untuk validasi = salinan galat pertama; tampilkan hanya bila tidak ada rincian galat.
        if (data && data.message && !out.length) { out.unshift(data.message); }
        if (out.length === 0) { out.push('Permintaan gagal. Periksa isian lalu coba lagi.'); }
        return out.join(' ');
    }

    function post(form, url, payload, lockOnSuccess) {
        var btn = form.querySelector('button[type="submit"]');
        if (form.dataset.busy) { return Promise.resolve(null); }
        if (form.id !== 'form-checkout' && !form.reportValidity()) { return Promise.resolve(null); }
        form.dataset.busy = '1';
        form.setAttribute('aria-busy', 'true');
        clearMsgs(form);
        if (btn) { btn.disabled = true; }
        var request = window.v2PaymentRequest ? window.v2PaymentRequest(payload, form) : {headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)};
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                ...request.headers,
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: request.body
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
            form._lastError = data;
            showErr(form, errorText(data), data && data.errors);
            return null;
        }).then(function (data) {
            if (!data || !lockOnSuccess) {
                if (btn) { btn.disabled = false; }
                delete form.dataset.busy;
                form.removeAttribute('aria-busy');
            }
            return data;
        });
    }

    function val(form, name) {
        var el = form.elements[name];
        return el ? String(el.value).trim() : '';
    }

    // Setelah simpan: data halaman diperbarui di tempat (uiLive), tanpa memuat ulang halaman.
    function liveSaved(form, opts) { return window.uiLive.saved(form, opts); }

    // ---- buka shift ----
    var openForm = document.getElementById('form-open');
    if (openForm) {
        var sourceSel = document.getElementById('open-source');
        var carrySel = document.getElementById('open-carry');
        function syncCarry() {
            var need = sourceSel.value !== 'admin';
            carrySel.disabled = !need;
            carrySel.required = need;
            if (!need) { carrySel.value = ''; }
        }
        sourceSel.addEventListener('change', syncCarry);
        syncCarry();
        openForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var payload = { cash: val(openForm, 'cash'), source: val(openForm, 'source') };
            var carry = val(openForm, 'carry_from');
            payload.carry_from = carry === '' ? null : carry;
            post(openForm, openForm.action, payload, true).then(function (data) { if (data) { liveSaved(openForm).then(syncCarry); } });
        });
    }

    // ---- tutup shift ----
    // Form di baris tabel (tutup shift, tagih, setujui) ditangani di tingkat dokumen agar baris baru dari uiLive ikut berfungsi.
    document.addEventListener('submit', function (ev) {
        var form = ev.target.closest && ev.target.closest('.form-close');
        if (!form) { return; }
        ev.preventDefault();
        post(form, form.action, { counted: val(form, 'counted'), fingerprint: form._fingerprint || null }, true).then(function (data) { if (data) { liveSaved(form); } });
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
            var dueInput = document.getElementById('credit-due-date');
            if (dueInput) { dueInput.disabled = !credit; if (!credit) { dueInput.value = ''; } }
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
                bank_account_code: checkout.elements['bank_account_code'] && !checkout.elements['bank_account_code'].disabled && checkout.elements['bank_account_code'].value ? checkout.elements['bank_account_code'].value : null,
                due_date: method === 'credit' && val(checkout, 'due_date') !== '' ? val(checkout, 'due_date') : null,
                paid_amount: val(checkout, 'paid_amount'),
                idempotency_key: val(checkout, 'idempotency_key'),
                fulfillment: val(checkout, 'fulfillment'),
                price_tier: val(checkout, 'price_tier') || 'retail'
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

    // Penjaga entri dobel: tombol simpan berganti menjadi konfirmasi; dikembalikan saat popup ditutup.
    function confirmDuplicate(form, label) {
        var btn = form.querySelector('button[type="submit"]');
        form.dataset.confirmDuplicate = '1';
        form.classList.add('is-duplicate-warning');
        if (btn) { if (!btn.dataset.label) { btn.dataset.label = btn.textContent; } btn.textContent = label; }
        var dialog = form.closest('dialog');
        if (dialog && !dialog.dataset.duplicateReset) {
            dialog.dataset.duplicateReset = '1';
            dialog.addEventListener('close', function () {
                delete form.dataset.confirmDuplicate;
                form.classList.remove('is-duplicate-warning');
                if (btn && btn.dataset.label) { btn.textContent = btn.dataset.label; }
            });
        }
    }
    window.uiConfirmDuplicate = confirmDuplicate;

    // ---- penagihan per invoice ----
    function keyCollect(root) { Array.prototype.forEach.call(root.querySelectorAll('.form-collect'), function (form) { if (!val(form, 'idempotency_key')) { setKey(form.elements['idempotency_key']); } }); }
    keyCollect(document);
    if (window.uiLive) { window.uiLive.onPatch(keyCollect); }
    document.addEventListener('ui-invoices:render', function (event) { keyCollect(event.detail.root); });
    document.addEventListener('submit', function (ev) {
        var form = ev.target.closest && ev.target.closest('.form-collect');
        if (!form) { return; }
        {
            ev.preventDefault();
            if (!val(form, 'idempotency_key')) { setKey(form.elements['idempotency_key']); }
            var payload = {
                amount: val(form, 'amount'),
                method: val(form, 'method'),
                bank_account_code: form.elements['bank_account_code'] && !form.elements['bank_account_code'].disabled && form.elements['bank_account_code'].value ? form.elements['bank_account_code'].value : null,
                idempotency_key: val(form, 'idempotency_key'),
                reference: val(form, 'reference') === '' ? null : val(form, 'reference'),
                seen_payment_id: val(form, 'seen_payment_id') === '' ? null : val(form, 'seen_payment_id'),
                confirm_duplicate: form.dataset.confirmDuplicate === '1'
            };
            form._lastError = null;
            post(form, form.action, payload, true).then(function (data) {
                if (data) { delete form.dataset.confirmDuplicate; liveSaved(form).then(function () { if (form.isConnected) { setKey(form.elements['idempotency_key']); } }); return; }
                // Penjaga entri dobel: tagihan sudah dibayar orang lain sejak form dibuka. Simpan berikutnya = konfirmasi.
                var err = form._lastError;
                if (err && err.errors && err.errors.duplicate_payment) { confirmDuplicate(form, 'Ya, tetap catat'); }
            });
        }
    });

    // ---- verifikasi admin ----
    document.addEventListener('submit', function (ev) {
        var form = ev.target.closest && ev.target.closest('.form-approve');
        if (!form) { return; }
        {
            ev.preventDefault();
            var approveBank = form.elements['bank_account_code'];
            var approvePayload = approveBank && approveBank.value ? { bank_account_code: approveBank.value } : {};
            post(form, form.action, approvePayload, true).then(function (data) { if (data) { liveSaved(form); } });
        }
    });
})();
</script>
<script>
(() => {
    'use strict';
    // Rekap tutup shift: ambil angka otomatis saat dialog dibuka, lalu bandingkan dengan uang fisik yang diketik.
    const summaryUrl = @json(route('v2.shifts.closing-summary', ['id' => '__ID__']));
    const cents = value => { const [i, f = ''] = String(value ?? '0').split('.'); return BigInt(i || '0') * 100n + BigInt((f + '00').slice(0, 2)); };
    const fmt = value => { let c = cents(value); const neg = c < 0n; if (neg) c = -c; const i = (c / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); const f = (c % 100n).toString().padStart(2, '0'); return (neg ? '-' : '') + 'Rp ' + (f === '00' ? i : i + ',' + f); };
    // fmtCents memformat nilai yang sudah dalam satuan sen (BigInt), tanpa mengalikan 100 lagi.
    const fmtCents = c => { const neg = c < 0n; if (neg) c = -c; const i = (c / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); const f = (c % 100n).toString().padStart(2, '0'); return (neg ? '-' : '') + 'Rp ' + (f === '00' ? i : i + ',' + f); };
    const dlRow = (label, value, strong) => { const div = document.createElement('div'); const dt = document.createElement('dt'); dt.textContent = label; const dd = document.createElement('dd'); dd.textContent = value; if (strong) div.className = 'is-total'; div.append(dt, dd); return div; };

    function loadRecap(form) {
        const id = form.dataset.shift;
        delete form._fingerprint; delete form._expected;
        if (form.dataset.recapLoading) return;
        form.dataset.recapLoading = '1';
        const box = form.querySelector('[data-close-recap]');
        box.hidden = false;
        box.textContent = 'Memuat rekap transaksi…';
        fetch(summaryUrl.replace('__ID__', id), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(response => { if (!response.ok) throw new Error('HTTP ' + response.status); return response.json(); })
            .then(data => { renderRecap(form, data); })
            .catch(() => { box.textContent = 'Rekap gagal dimuat. Tetap bisa menutup shift.'; })
            .finally(() => { delete form.dataset.recapLoading; });
    }

    function renderRecap(form, data) {
        const box = form.querySelector('[data-close-recap]');
        box.replaceChildren();
        form._fingerprint = data.fingerprint;
        form._expected = data.cash.expected;

        const sales = document.createElement('dl');
        sales.className = 'recap-sales';
        sales.append(
            dlRow('Penjualan (' + data.sales.count + ' nota)', fmt(data.sales.total)),
            dlRow('— tunai', fmt(data.sales.cash)),
            dlRow('— transfer', fmt(data.sales.transfer)),
            dlRow('— kredit', fmt(data.sales.credit)),
        );

        const cash = document.createElement('dl');
        cash.className = 'recap-cash';
        cash.append(
            dlRow('Kas awal', fmt(data.cash.opening)),
            dlRow('Masuk laci', fmt(data.cash.in)),
            dlRow('Keluar laci', fmt(data.cash.out)),
            dlRow('Seharusnya di laci', fmt(data.cash.expected), true),
        );

        box.append(sales, cash);
        if (data.pending_drawer_payments > 0) {
            const pending = document.createElement('p');
            pending.className = 'recap-pending';
            pending.textContent = 'Ada ' + data.pending_drawer_payments + ' pengajuan tunai dari laci ini yang belum disetujui admin.';
            box.append(pending);
        }

        const counted = form.elements.counted;
        const match = form.querySelector('[data-close-match]');
        const recalc = () => {
            const raw = counted.value;
            if (!raw) { match.hidden = true; return; }
            match.hidden = false;
            const diff = cents(raw) - cents(form._expected);
            if (diff === 0n) { match.className = 'close-match ok'; match.textContent = 'Cocok dengan perkiraan ' + fmt(form._expected) + '.'; }
            else { match.className = 'close-match warn'; match.textContent = 'Selisih ' + (diff > 0n ? 'lebih ' : 'kurang ') + fmtCents(diff < 0n ? -diff : diff) + ' dari perkiraan.'; }
        };
        counted.addEventListener('input', recalc);
        recalc();
    }

    new MutationObserver(mutations => {
        for (const mutation of mutations) {
            const dialog = mutation.target;
            if (dialog.tagName !== 'DIALOG' || mutation.attributeName !== 'open' || !dialog.open) continue;
            const form = dialog.querySelector('.form-close');
            if (form) loadRecap(form);
        }
    }).observe(document.body, { subtree: true, attributes: true, attributeFilter: ['open'] });
})();
</script>
@include('v2.master-insights')
@include('v2.ui-sidebar-js')
@include('v2.ui-workspace')
@include('v2.receipt-restock-script')
@include('v2.pos-cashier-script')
@include('v2.receipt-freight-script')
@include('v2.pos-mobile')
@include('v2.pos-customer-combo')
@include('partials.compact-form-styles')
@include('v2.shared-selects')
@include('v2.shared-date-picker')
@include('partials.filter-disclosure-script')
@include('v2.ui-tables')
@include('v2.ui-motion')
@include('v2.ui-forms')
@include('v2.ui-result')
@include('v2.store-shift-script')
@include('v2.sales-history-script')
@include('v2.invoice-script')
<dialog class="input-dialog popup-wide" id="input-popup-sale-detail" aria-labelledby="sale-detail-title" data-url="{{ route('v2.sales.detail', '__ID__') }}">
    <div class="popup-heading"><div class="popup-titles"><h2 id="sale-detail-title">Detail penjualan</h2></div><button type="button" class="popup-close secondary" data-close-dialog aria-label="Tutup detail penjualan">×</button></div>
    <div class="popup-content" data-sale-detail-content aria-live="polite"></div>
    <div class="popup-footer sd-footer"><button type="button" class="secondary" data-close-dialog>Tutup</button><div data-sale-footer-actions></div></div>
</dialog>
@include('v2.sale-detail-script')
@include('v2.transfer-proof-script')
@include('v2.table-actions')
@include('v2.ui-tooltip')
@include('v2.mobile-quick-actions')
</body>
</html>
