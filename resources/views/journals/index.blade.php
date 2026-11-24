{{-- Panel workspace memuat halaman ini lewat fetch (?partial=1); URL penuh tetap berfungsi. --}}
@extends(request()->boolean('partial') ? 'layouts.partial' : 'layouts.app')

@section('content')
@php
    $adjustmentAccounts = $adjustmentAccounts ?? collect();
    $counterAccounts = $counterAccounts ?? $adjustmentAccounts;
    $hasJournals = $hasJournals ?? false;
    $v2Writes = config('v2.enabled');
    $adjustmentAction = $v2Writes ? route('v2.journals.adjustments.store') : route('journals.adjustments.store');
    $sourceLabels = [
        'opening_balance' => 'Saldo Pembukaan',
        'sale' => 'Penjualan',
        'expense' => 'Pengeluaran',
        'ritase' => 'Ritase',
        'inventory_receipt' => 'Penerimaan Barang',
        'stock_opname' => 'Stok Opname',
        'adjustment' => 'Penyesuaian',
    ];
    $openingStatus = $opening?->status ?? 'not_started';
    $formatMoney = function ($value) {
        $decimal = \Brick\Math\BigDecimal::of((string) $value)->toScale(2, \Brick\Math\RoundingMode::HalfUp);
        $negative = $decimal->isNegative();
        [$whole, $fraction] = explode('.', ltrim((string) $decimal, '-'));
        $formatted = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole) . ',' . $fraction;
        return $negative ? '-' . $formatted : $formatted;
    };
    $openingTotal = \Brick\Math\BigDecimal::of($cashOpening)->plus($bankOpening)->toScale(2);
@endphp
<style>
    .ledger-heading { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; margin-bottom:20px; }
    .ledger-heading h1 { margin:0 0 5px; font-size:24px; letter-spacing:-.025em; color:var(--primary-hover); }
    .ledger-heading p { margin:0; max-width:680px; color:var(--text-muted); font-size:13px; line-height:1.55; }
    .ledger-status { border:1px solid var(--mint-border); background:var(--mint-surface); color:var(--primary-hover); padding:7px 10px; border-radius:var(--radius-inner); font:700 11px var(--font-mono); white-space:nowrap; }
    .balance-strip { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin-bottom:16px; }
    .balance-tile { border:1px solid var(--border-card); border-radius:var(--radius-card); background:var(--surface); padding:16px; min-width:0; }
    .balance-tile small { display:block; color:var(--text-caption); font-size:10px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; margin-bottom:8px; }
    .balance-tile strong { display:block; color:var(--text-main); font:800 clamp(18px,2vw,25px) var(--font-mono); font-variant-numeric:tabular-nums; overflow-wrap:anywhere; }
    .balance-tile span { display:block; color:var(--text-muted); font-size:11.5px; margin-top:5px; }
    .opening-panel { display:grid; grid-template-columns:minmax(0,1.1fr) minmax(280px,.9fr); gap:18px; margin-bottom:18px; }
    .opening-panel .card { margin:0; }
    .section-title { margin:0 0 4px; font-size:16px; color:var(--text-main); }
    .section-copy { margin:0 0 16px; color:var(--text-muted); font-size:12.5px; line-height:1.55; }
    .opening-form { display:grid; grid-template-columns:150px 1fr 1fr; gap:12px; align-items:end; }
    .opening-form .form-group { margin:0; }
    .opening-actions { display:flex; align-items:center; gap:9px; flex-wrap:wrap; margin-top:14px; }
    .lock-note { border-left:3px solid var(--primary); padding:10px 12px; background:var(--mint-surface); color:var(--primary-hover); font-size:12px; line-height:1.55; }
    .opening-list { display:grid; gap:10px; }
    .opening-row { display:flex; justify-content:space-between; gap:16px; border-bottom:1px solid var(--border-subtle); padding-bottom:9px; font-size:12.5px; }
    .opening-row:last-child { border-bottom:0; padding-bottom:0; }
    .opening-row span { color:var(--text-muted); }
    .opening-row strong { color:var(--text-main); font-family:var(--font-mono); font-variant-numeric:tabular-nums; text-align:right; }
    .ledger-filter {
        display: grid;
        grid-template-columns: repeat(auto-fit,minmax(min(100%,var(--ui-detail-summary-min)),1fr));
        align-items: end;
        gap: var(--ui-space-group);
        margin-bottom: var(--ui-space-section);
        padding: var(--ui-space-section);
        background: var(--ui-canvas);
        border: var(--ui-stroke) solid var(--ui-line-soft);
        border-radius: var(--ui-radius);
    }
    .ledger-filter .form-group {
        margin: 0;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: var(--ui-space-tight);
        min-width: 0;
    }
    .ledger-filter .form-group label {
        color: var(--ui-ink-2);
        font-size: var(--ui-t-caption);
        font-weight: var(--ui-weight-strong);
        white-space: nowrap;
        margin: 0;
    }
    .ledger-filter .form-control {
        min-height: var(--ui-tap);
        padding: var(--ui-space-control) var(--ui-space-group);
        font-size: var(--ui-t-label);
        border-radius: var(--ui-radius);
        border: var(--ui-stroke) solid var(--ui-line);
        background: var(--ui-surface);
        color: var(--ui-ink);
        width: 100%;
        box-sizing: border-box;
    }
    .ledger-filter .form-control:focus {
        border-color: var(--primary, #2d6a4f);
        outline: none;
        box-shadow: 0 0 0 2px rgba(45, 106, 79, .12);
    }
    .ledger-filter-title{grid-column:1/-1;margin:0;font-size:var(--ui-t-label);font-weight:var(--ui-weight-strong)}
    .ledger-filter-actions {
        grid-column: 1/-1;
        display: flex;
        justify-content: flex-end;
        gap: var(--ui-space-control);
        align-items: center;
        margin: 0;
    }
    .ledger-filter-actions > * { white-space: nowrap; }
    .ledger-filter .btn,
    .ledger-filter .btn-secondary {
        min-height: var(--ui-tap);
        padding: 6px 16px;
        font-size: 12px;
        font-weight: 600;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        white-space: nowrap;
        text-decoration: none;
        box-sizing: border-box;
    }
    .ledger-filter .btn {
        background: var(--primary, #2d6a4f);
        color: #ffffff;
        border: 1px solid var(--primary, #2d6a4f);
    }
    .ledger-filter .btn-secondary {
        background: var(--mint-surface, #e8f5e9);
        color: var(--primary-hover, #1b4332);
        border: 1px solid var(--mint-border, #a3b18a);
    }
    .journal-stack { display:grid; gap:12px; }
    .journal-entry { border:1px solid var(--border-card); border-radius:var(--radius-card); background:var(--surface); overflow:hidden; }
    .journal-head { display:grid; grid-template-columns:120px minmax(0,1fr) auto; gap:14px; align-items:center; padding:14px 16px; background:var(--surface-subtle, #f7f9f7); border-bottom:1px solid var(--border-subtle); }
    .journal-date { color:var(--text-main); font:700 12px var(--font-mono); }
    .journal-desc strong { display:block; font-size:13px; color:var(--text-main); margin-bottom:3px; }
    .journal-desc span { color:var(--text-caption); font:600 10.5px var(--font-mono); text-transform:uppercase; letter-spacing:.04em; }
    .balance-state { color:var(--primary-hover); font-size:10.5px; font-weight:800; text-transform:uppercase; }
    .journal-table { width:100%; margin:0; }
    .journal-table th { font-size:10px; letter-spacing:.06em; }
    .journal-table td { padding-top:10px; padding-bottom:10px; }
    .journal-table .amount { text-align:right; font-family:var(--font-mono); font-variant-numeric:tabular-nums; }
    .journal-total td { font-weight:800; border-top:1px solid var(--border-card); background:var(--mint-surface); }
    .journal-empty { text-align:center; padding:34px 18px; color:var(--text-muted); }
    .feed-note:not([hidden]){display:flex;flex-wrap:wrap;align-items:center;gap:var(--ui-space-control);margin-block:var(--ui-space-group);padding:var(--ui-space-group);border-radius:var(--ui-radius);background:var(--ui-green-soft);color:var(--ui-green)}
    .journal-page-links { display:flex; flex-wrap:wrap; align-items:center; gap:var(--ui-space-control); margin-top:var(--ui-space-section); }
    @media print { .journal-page-links,.feed-note,.ledger-filter { display:none!important; } }
    @media(max-width:900px) {
        .opening-panel { grid-template-columns:1fr; }
        .opening-form { grid-template-columns:1fr; }
        .balance-strip { grid-template-columns:1fr; }
    }
    @media(max-width:640px) {
        .ledger-heading { flex-direction:column; }
        .ledger-filter .btn,
        .ledger-filter .btn-secondary {
            flex: 1;
        }
        .journal-head { grid-template-columns:1fr; gap:7px; }
    }
    @media(prefers-reduced-motion:reduce) { * { scroll-behavior:auto !important; transition:none !important; } }
</style>

<header class="ledger-heading">
    <div>
        <h1>Jurnal Umum</h1>
        <p>Pantau saldo dan riwayat jurnal pembukuan.</p>
    </div>
</header>

@if($errors->has('opening_balance'))
    <div class="alert alert-danger" role="alert">{{ $errors->first('opening_balance') }}</div>
@endif

<div data-journal-root data-refresh-url="{{ route('journals.index', request()->except('partial')) }}">
<p class="note" data-journal-feedback role="status" aria-live="polite" tabindex="-1" hidden></p>
<section class="balance-strip" aria-label="Ringkasan Kas dan Bank">
    <article class="balance-tile">
        <small>Saldo Akhir Kas Tunai</small>
        <strong>Rp {{ $formatMoney($cashBalance) }}</strong>
        <span>Saldo awal + seluruh mutasi akun 1101</span>
    </article>
    <article class="balance-tile">
        <small>Saldo Akhir Bank</small>
        <strong>Rp {{ $formatMoney($bankBalance) }}</strong>
        <span>@if(count($bankAccounts ?? []) > 1)Total {{ count($bankAccounts) }} rekening bank aktif @else Saldo awal + seluruh mutasi akun 1102 @endif</span>
    </article>
    <article class="balance-tile">
        <small>Status Saldo Pembukaan</small>
        <strong>{{ $openingStatus === 'posted' ? 'Terkunci' : ($openingStatus === 'draft' ? 'Draf' : 'Belum diisi') }}</strong>
        <span>{{ $openingStatus === 'posted' ? 'Koreksi melalui jurnal penyesuaian' : ($hasJournals ? 'Pembukuan sudah berjalan; koreksi lewat penyesuaian' : 'Dapat ditinjau sebelum Mulai Pembukuan') }}</span>
    </article>
</section>

@if($v2Writes)
<section class="card journal-tools" aria-labelledby="journal-tools-title">
    <h2 class="section-title" id="journal-tools-title">Kelola saldo Kas &amp; Bank</h2>
    <div class="journal-tools-grid">
        <div><h3>Pindah dana</h3><p class="note">Setor, tarik, atau pindahkan uang antar rekening. Bukan pengeluaran.</p><button class="btn" type="button" data-journal-open="transfer-panel" aria-haspopup="dialog" aria-controls="transfer-panel">Pindah dana</button></div>
        <div><h3>Penyesuaian saldo</h3><p class="note">Koreksi saldo buku jika tidak sama dengan saldo yang sebenarnya.</p><button class="btn btn-secondary" type="button" data-journal-open="adjustment-panel" aria-haspopup="dialog" aria-controls="adjustment-panel">Sesuaikan saldo</button></div>
    </div>
</section>
<dialog class="input-dialog uf-dialog journal-popup" id="transfer-panel" aria-labelledby="transfer-title">
    <div class="popup-heading"><div><h2 id="transfer-title">Pindah dana</h2><p class="uf-dialog-sub">Kas ke Bank, Bank ke Kas, atau antar rekening.</p></div><button class="popup-close secondary" type="button" data-journal-close aria-label="Tutup pindah dana">×</button></div>
    <div class="popup-content">
    <form id="transfer-form" method="POST" action="{{ route('v2.journals.transfers.store') }}">
        @csrf
        <p class="err" data-adjustment-error role="alert" tabindex="-1" hidden></p>
        <div class="adjustment-grid">
            <div class="form-group"><label for="transfer-from">Dari akun (sumber)</label><select class="form-control" id="transfer-from" name="from_account_code" data-native-select required><option value="">Pilih akun sumber</option>@foreach($adjustmentAccounts as $account)<option value="{{ $account->account_code }}">{{ $account->account_code }} — {{ $account->account_name }}</option>@endforeach</select></div>
            <div class="form-group"><label for="transfer-to">Ke akun (tujuan)</label><select class="form-control" id="transfer-to" name="to_account_code" data-native-select required><option value="">Pilih akun tujuan</option>@foreach($adjustmentAccounts as $account)<option value="{{ $account->account_code }}">{{ $account->account_code }} — {{ $account->account_name }}</option>@endforeach</select></div>
            <div class="form-group"><label for="transfer-amount">Nominal (Rp)</label><input class="form-control" id="transfer-amount" name="amount" type="number" step="0.01" min="0.01" placeholder="0,00" required style="text-align:right;font-variant-numeric:tabular-nums"></div>
            <div class="form-group"><label for="transfer-date">Tanggal</label><input class="form-control" id="transfer-date" type="date" name="transaction_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
            <div class="form-group adjustment-wide"><label for="transfer-description">Keterangan</label><input class="form-control" id="transfer-description" name="description" maxlength="200" placeholder="Mis. setor kas harian ke Bank BCA" required></div>
        </div>
        <p class="adjustment-hint">Saldo akun sumber tidak boleh menjadi negatif. Jurnal terkunci permanen setelah disimpan.</p>
        <div class="adjustment-confirm">
            <div class="form-group"><label for="transfer-password">Password login untuk konfirmasi</label><input class="form-control" id="transfer-password" name="password" type="password" autocomplete="current-password" placeholder="Masukkan password Anda" required></div>
            <div class="adjustment-actions"><button class="btn btn-secondary" type="button" data-journal-close>Batal</button><button class="btn" type="submit" @disabled($adjustmentAccounts->count() < 2)>Pindahkan dana</button></div>
        </div>
    </form>
    </div>
</dialog>
@endif

@if($v2Writes)
<dialog class="input-dialog uf-dialog journal-popup" id="adjustment-panel" aria-labelledby="adjustment-title">
    <div class="popup-heading"><div><h2 id="adjustment-title">Penyesuaian saldo</h2><p class="uf-dialog-sub">Koreksi saldo Kas atau Bank tanpa mengubah jurnal lama.</p></div><button class="popup-close secondary" type="button" data-journal-close aria-label="Tutup penyesuaian saldo">×</button></div>
    <div class="popup-content">
@else
<details class="card adjustment-panel" id="adjustment-panel" @if($errors->any() && !$errors->has('opening_balance')) open @endif>
    <summary class="adjustment-summary">
        <span><strong id="adjustment-title">Sesuaikan saldo</strong><small>Saldo awal go-live atau koreksi akun.</small></span>
        <span class="adjustment-toggle" aria-hidden="true">+</span>
    </summary>
    <div class="adjustment-content">
@endif
    <h2 class="section-title">Jurnal Penyesuaian Saldo <x-ui.info-tip text="Mengoreksi saldo Kas (1101) atau Bank (1102) ke angka tujuan tanpa mengubah saldo awal yang sudah terkunci." title="Jurnal Penyesuaian" label="Keterangan jurnal penyesuaian"/></h2>
    <p class="section-copy">Isi saldo tujuan. Selisihnya otomatis masuk ke akun lawan.</p>
    <form id="adjustment-form" method="POST" action="{{ $adjustmentAction }}">
        @csrf
        <p class="err" data-adjustment-error role="alert" tabindex="-1" hidden></p>
        <div class="adjustment-grid">
            <div class="form-group"><label for="adjustment-account">Akun yang disesuaikan</label><select class="form-control" id="adjustment-account" name="account_code" data-native-select required><option value="">Pilih akun</option>@foreach($adjustmentAccounts as $account)<option value="{{ $account->account_code }}" @selected(old('account_code') === $account->account_code)>{{ $account->account_code }} — {{ $account->account_name }} ({{ $account->normal_balance === 'credit' ? 'Kredit' : 'Debet' }})</option>@endforeach</select></div>
            <div class="form-group"><label for="adjustment-target">Saldo tujuan (Rp)</label><input class="form-control" id="adjustment-target" name="target_balance" type="number" step="0.01" min="0" placeholder="0,00" value="{{ old('target_balance') }}" required aria-describedby="adjustment-help"></div>
            <div class="form-group"><label for="adjustment-counter">Akun pencatat selisih</label><select class="form-control" id="adjustment-counter" name="counter_account_code" data-native-select required><option value="">Pilih akun pencatat selisih</option>@foreach($counterAccounts as $account)<option value="{{ $account->account_code }}" @selected(old('counter_account_code') === $account->account_code)>{{ $account->account_code }} — {{ $account->account_name }}</option>@endforeach</select></div>
            <div class="form-group"><label for="adjustment-date">Tanggal</label><input class="form-control" id="adjustment-date" type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" required></div>
            <div class="form-group adjustment-wide"><label for="adjustment-description">Alasan koreksi</label><input class="form-control" id="adjustment-description" name="description" maxlength="200" value="{{ old('description') }}" placeholder="Mis. selisih hitung kas pagi" required></div>
        </div>
        <p class="adjustment-hint" id="adjustment-help">Saldo tujuan mengikuti sisi normal akun. Jurnal lama tetap tersimpan.</p>
        @if($v2Writes)<p class="adjustment-hint">Pada tahap ini hanya Kas (1101) dan akun Rekening Bank aktif yang dapat disesuaikan. Saldo awal yang sudah diposting tidak diubah; selisih dicatat sebagai jurnal penyesuaian baru.</p>@endif
        @if(!$v2Writes && $openingStatus !== 'posted')<p class="adjustment-hint">Posting pertama memulai pembukuan dan menggantikan draf saldo awal.</p>@endif
        @if($adjustmentAccounts->isEmpty())<p class="section-copy">Belum ada akun aktif. Siapkan dahulu melalui <a href="{{ route('coa.index') }}">Bagan Akun</a>.</p>@endif
        <div class="adjustment-confirm">
            <div class="form-group"><label for="adjustment-password">Password login untuk konfirmasi</label><input class="form-control" id="adjustment-password" name="password" type="password" autocomplete="current-password" placeholder="Masukkan password Anda" required></div>
            <div class="adjustment-actions"><button class="btn btn-secondary" type="button" @if($v2Writes) data-journal-close @else onclick="document.getElementById('adjustment-panel').open = false; document.getElementById('adjustment-password').value = ''; document.querySelector('#adjustment-panel > summary').focus();" @endif>Batal</button><button class="btn" type="submit" @disabled($adjustmentAccounts->isEmpty())>Simpan penyesuaian</button></div>
        </div>
    </form>
    </div>
@if($v2Writes)</dialog>@else</details>@endif
<style>
    .journal-tools-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,var(--ui-detail-column-min)),1fr));gap:var(--ui-space-section)}
    .journal-tools-grid>div{padding:var(--ui-space-section);background:var(--ui-canvas);border:var(--ui-stroke) solid var(--ui-line-soft);border-radius:var(--ui-radius)}
    .journal-tools-grid h3{margin:0;font-size:var(--ui-t-body);color:var(--ui-ink)}
    .journal-tools-grid button{min-height:var(--ui-tap)}
    html body dialog.journal-popup{width:min(var(--ui-cart-width),calc(100% - 2 * var(--ui-space-group)));max-height:calc(100dvh - 2 * var(--ui-space-group));margin:auto;padding:0;border:0;border-radius:var(--ui-radius);background:var(--ui-surface);color:var(--ui-ink)}
    html body dialog.journal-popup .popup-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:var(--ui-space-group);padding:var(--ui-space-section);background:var(--ui-green-2);color:var(--ui-surface)}
    html body dialog.journal-popup .popup-heading h2{margin:0;color:var(--ui-lime);font-size:var(--ui-h2)}
    /* Tema popup uf-dialog mengunci padding bawah isi ke 0 (!important, selektor id ganda).
       Ruang bawah dipasang pada elemen terakhir agar tetap berlaku tanpa melawan tema. */
    html body :is(#transfer-panel,#adjustment-panel) .popup-content>:last-child{margin-bottom:var(--ui-space-panel)}
    @media(max-width:640px){
        html body :is(#transfer-panel,#adjustment-panel) .popup-content>:last-child{margin-bottom:calc(var(--ui-space-panel) + env(safe-area-inset-bottom))}
        html body :is(#transfer-panel,#adjustment-panel) .popup-heading{padding:var(--ui-space-group) var(--ui-space-section)}
        html body :is(#transfer-panel,#adjustment-panel) .adjustment-grid{grid-template-columns:minmax(0,1fr)}
    }
    .journal-popup .popup-content>.section-title{display:none}
    .journal-popup .uf-dialog-sub{margin:0}
    .adjustment-panel { margin-bottom:16px; padding:0; overflow:hidden; }
    .adjustment-summary { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:18px 20px; min-height:44px; cursor:pointer; list-style:none; }
    .adjustment-summary::-webkit-details-marker { display:none; }
    .adjustment-summary strong { display:block; font-size:15px; color:var(--text-main); }
    .adjustment-summary small { display:block; margin-top:4px; font-size:12px; color:var(--text-muted); }
    .adjustment-toggle { display:grid; place-items:center; width:32px; height:32px; border-radius:50%; background:var(--mint-surface); color:var(--primary); font-size:22px; flex-shrink:0; }
    .adjustment-panel[open] .adjustment-toggle { transform:rotate(45deg); }
    .adjustment-summary:focus-visible, .opening-disclosure > summary:focus-visible { outline:2px solid var(--primary); outline-offset:-4px; }
    .adjustment-content { border-top:1px solid var(--border-card); padding:20px; }
    .adjustment-content > h2 { position:absolute; width:1px; height:1px; overflow:hidden; clip-path:inset(50%); }
    .adjustment-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; max-width:800px; }
    .adjustment-wide { grid-column:1 / -1; }
    .adjustment-grid .form-group { margin:0; min-width:0; }
    .adjustment-grid .form-control { width:100%; min-height:44px; box-sizing:border-box; }
    .adjustment-panel .btn { min-height:44px; }
    .adjustment-panel .form-group label { text-transform:none; letter-spacing:0; font-size:12px; }
    .adjustment-hint { font-size:12px; line-height:1.6; color:var(--text-muted); margin:10px 0 0; }
    .adjustment-confirm { display:flex; align-items:end; justify-content:space-between; flex-wrap:wrap; gap:18px; margin-top:20px; padding-top:18px; border-top:1px solid var(--border-card); }
    .adjustment-confirm .form-group { margin:0; flex:1 1 240px; max-width:360px; }
    .adjustment-actions { display:flex; gap:8px; }
    .opening-disclosure { margin-bottom:18px; }
    .opening-disclosure > summary { cursor:pointer; min-height:44px; display:flex; align-items:center; gap:8px; font-size:12px; font-weight:600; color:var(--text-muted); }
    .opening-disclosure > summary::before { content:'›'; font-size:18px; }
    .opening-disclosure[open] > summary::before { transform:rotate(90deg); }
    #adjustment-target { text-align:right; font-variant-numeric:tabular-nums; }
    @media(max-width:720px) { .adjustment-grid { grid-template-columns:minmax(0,1fr); } .adjustment-content { padding:16px; } .adjustment-confirm .form-group { max-width:none; } .adjustment-actions { width:100%; } .adjustment-panel .btn { flex:1; white-space:normal; } }
</style>
<details class="opening-disclosure" @if($errors->has('opening_balance')) open @endif>
<summary>Saldo pembukaan &amp; rincian awal</summary>
<section class="opening-panel">
    <div class="card">
        <h2 class="section-title">Saldo Pembukaan</h2>
        <p class="section-copy">Isi nilai Kas Tunai dan Bank pada tanggal mulai pembukuan. Sistem otomatis membuat lawan jurnal ke Ekuitas Saldo Awal agar Neraca tetap seimbang.</p>

        @if($v2Writes)
            @if($openingStatus !== 'posted')
                @if($hasJournals)
                    <div class="lock-note"><strong>Saldo awal belum tercatat, tetapi pembukuan sudah berjalan.</strong> Pembukaan baru tidak dapat diposting lagi. Gunakan panel <strong>Sesuaikan saldo</strong> di halaman ini untuk mengoreksi Kas atau Bank; jurnal lama tetap utuh.</div>
                @else
                    <div class="lock-note"><strong>Saldo awal belum diposting.</strong> Pembukaan hanya dilakukan sekali dari menu <a href="{{ route('v2.index') }}#shift">Kas harian → Saldo awal pembukuan</a>. Halaman ini tidak menyediakan pembukaan kedua; gunakan penyesuaian hanya untuk koreksi sesudahnya.</div>
                @endif
            @else
                <div class="lock-note"><strong>Saldo awal telah diposting.</strong> Angka pembukaan tidak dapat diubah atau dihapus. Koreksi dilakukan melalui jurnal penyesuaian agar jejak audit tetap utuh.</div>
            @endif
        @elseif($openingStatus !== 'posted')
        <form method="POST" action="{{ route('journals.opening.save') }}">
            @csrf
            <div class="opening-form">
                <div class="form-group">
                    <label for="transaction_date">Tanggal Mulai</label>
                    <input class="form-control" id="transaction_date" name="transaction_date" type="date" value="{{ old('transaction_date', $opening?->transaction_date ?? now()->format('Y-m-d')) }}" required>
                </div>
                <div class="form-group">
                    <label for="cash_amount">Saldo Awal Kas</label>
                    <input class="form-control" id="cash_amount" name="cash_amount" type="text" inputmode="decimal" data-currency-input data-currency-scale="2" value="{{ old('cash_amount', $cashOpening) }}" required>
                </div>
                <div class="form-group">
                    <label for="bank_amount">Saldo Awal Bank</label>
                    <input class="form-control" id="bank_amount" name="bank_amount" type="text" inputmode="decimal" data-currency-input data-currency-scale="2" value="{{ old('bank_amount', $bankOpening) }}" required>
                </div>
            </div>
            <div class="opening-actions">
                <button class="btn" type="submit">Simpan Draf</button>
                @if($openingStatus === 'draft')
                    <button class="btn btn-secondary" type="submit" form="post-opening-form">Mulai Pembukuan</button>
                @endif
            </div>
        </form>
        @if($openingStatus === 'draft')
        <form id="post-opening-form" method="POST" action="{{ route('journals.opening.post') }}" onsubmit="return confirm('Mulai pembukuan sekarang? Saldo awal akan terkunci permanen dan koreksi berikutnya wajib melalui jurnal penyesuaian.');">
            @csrf
        </form>
        @endif
        @else
            <div class="lock-note"><strong>Saldo awal telah diposting.</strong> Angka pembukaan tidak dapat diubah atau dihapus. Koreksi dilakukan melalui jurnal penyesuaian agar jejak audit tetap utuh.</div>
        @endif
    </div>

    <aside class="card">
        <h2 class="section-title">Rincian Pembukaan</h2>
        <p class="section-copy">Sumber pembentukan saldo awal yang menjadi titik mulai Buku Besar.</p>
        <div class="opening-list">
            <div class="opening-row"><span>1101 · Kas Tunai</span><strong>Rp {{ $formatMoney($cashOpening) }}</strong></div>
            <div class="opening-row"><span>1102 · Bank</span><strong>Rp {{ $formatMoney($bankOpening) }}</strong></div>
            <div class="opening-row"><span>3101 · Ekuitas Saldo Awal</span><strong>Rp {{ $formatMoney($openingTotal) }}</strong></div>
            <div class="opening-row"><span>Tanggal mulai</span><strong>{{ $opening?->transaction_date ? \Carbon\Carbon::parse($opening->transaction_date)->format('d/m/Y') : '—' }}</strong></div>
        </div>
    </aside>
</section>

 </details>
</div>
<section class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:14px;">
        <div>
            <h2 class="section-title" id="journal-activity" tabindex="-1">Aktivitas Perpindahan Nilai</h2>
            <p class="section-copy" style="margin-bottom:0;">{{ $entries->count() }} entri di halaman ini · maksimal 25 entri per halaman.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('journals.index') }}" class="ledger-filter" data-filter-inline>
        <p class="ledger-filter-title">Saring aktivitas jurnal</p>
        <div class="form-group ledger-filter-start">
            <label for="start_date">Dari Tanggal</label>
            <input class="form-control" id="start_date" name="start_date" type="date" value="{{ $filters['start_date'] ?? '' }}">
        </div>
        <div class="form-group ledger-filter-end">
            <label for="end_date">Sampai Tanggal</label>
            <input class="form-control" id="end_date" name="end_date" type="date" value="{{ $filters['end_date'] ?? '' }}">
        </div>
        <div class="form-group ledger-filter-source">
            <label for="source">Sumber Aktivitas</label>
            <select class="form-control" id="source" name="source">
                <option value="">Semua sumber</option>
                @foreach($sources as $source)
                    <option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ $sourceLabels[$source] ?? ucfirst(str_replace('_', ' ', $source)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group ledger-filter-account">
            <label for="account">Kode Akun</label>
            <input class="form-control" id="account" name="account" value="{{ $filters['account'] ?? '' }}" placeholder="Semua akun">
        </div>
        <div class="ledger-filter-actions">
            <button class="btn" type="submit">Terapkan</button>
            <a class="btn btn-secondary" href="{{ route('journals.index') }}">Reset</a>
        </div>
    </form>

    @if ($watchJournal ?? false)
        <div class="feed-note" hidden>
            <span id="feed-note-text" role="status"></span>
            <a data-journal-page href="{{ route('journals.index') }}">Perbarui aktivitas</a>
        </div>
    @endif
    <div class="journal-stack" id="journal-stack" data-latest-id="{{ (int) ($entries->max('id') ?? 0) }}">
        @forelse($entries as $entry)
        <article class="journal-entry" data-entry-id="{{ $entry['id'] }}">
            <header class="journal-head">
                <div class="journal-date">{{ \Carbon\Carbon::parse($entry['transaction_date'])->format('d/m/Y') }}</div>
                <div class="journal-desc">
                    <strong>{{ $entry['description'] }}</strong>
                    <span>{{ $sourceLabels[$entry['reference_type']] ?? str_replace('_', ' ', $entry['reference_type']) }} · #{{ $entry['id'] }}</span>
                </div>
                <div class="balance-state">{{ $entry['is_balanced'] ? 'Seimbang' : 'Tidak Seimbang' }}</div>
            </header>
            <div style="overflow-x:auto;">
                <table class="journal-table">
                    <thead><tr><th>Kode</th><th>Nama Akun</th><th style="text-align:right;">Debit (Rp)</th><th style="text-align:right;">Kredit (Rp)</th></tr></thead>
                    <tbody>
                    @foreach($entry['lines'] as $line)
                        <tr>
                            <td><span class="sku-code">{{ $line['account_code'] }}</span></td>
                            <td>{{ $line['account_name'] }}</td>
                            <td class="amount">{{ \Brick\Math\BigDecimal::of($line['debit'])->isGreaterThan(0) ? $formatMoney($line['debit']) : '—' }}</td>
                            <td class="amount">{{ \Brick\Math\BigDecimal::of($line['credit'])->isGreaterThan(0) ? $formatMoney($line['credit']) : '—' }}</td>
                        </tr>
                    @endforeach
                    <tr class="journal-total"><td colspan="2">Total</td><td class="amount">{{ $formatMoney($entry['total_debit']) }}</td><td class="amount">{{ $formatMoney($entry['total_credit']) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </article>
        @empty
            <div class="journal-empty">Belum ada aktivitas jurnal pada filter ini.</div>
        @endforelse
    </div>
    @if (($newerJournalUrl ?? null) || ($olderJournalUrl ?? null))
        <nav class="journal-page-links" aria-label="Halaman jurnal">
            @if ($newerJournalUrl)<a class="btn btn-secondary" data-journal-page href="{{ $newerJournalUrl }}">Aktivitas lebih baru</a>@endif
            @if ($olderJournalUrl)<a class="btn btn-secondary" data-journal-page href="{{ $olderJournalUrl }}">Aktivitas lebih lama</a>@endif
        </nav>
    @endif
</section>

@if ($watchJournal ?? false)
<script>
(function () {
    const stack = document.getElementById('journal-stack');
    const note = document.getElementById('feed-note-text');
    if (!stack || !note) return;
    let pending = false;
    const timer = setInterval(refreshFeed, 10000);

    async function refreshFeed() {
        if (pending || document.hidden || !stack.getClientRects().length) return;
        pending = true;
        try {
            const url = new URL(@json(route('journals.feed')), location.href);
            url.searchParams.set('after_id', stack.dataset.latestId || '0');
            url.searchParams.set('check', '1');
            const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) throw new Error();
            const {has_new: hasNew} = await response.json();
            if (hasNew) {
                note.textContent = 'Ada aktivitas jurnal baru.';
                note.parentElement.hidden = false;
                clearInterval(timer);
            }
        } catch {
            // The current list remains usable; the next poll can retry.
        } finally {
            pending = false;
        }
    }
})();
</script>
@endif
@if($v2Writes)
    @include('v2.journal-adjustment-script')
@endif
@include('v2.finance-dna')
@endsection
