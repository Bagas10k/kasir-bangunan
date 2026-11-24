@if ($role === 'admin')
@php
    $masterDefs = ['product'=>['Master Produk','Katalog produk, satuan, harga jual dan stok.'],'customer'=>['Master Pelanggan','Daftar pelanggan dan catatan.'],'supplier'=>['Master Supplier','Daftar pemasok dan catatan.'],'employee'=>['Master Karyawan','Daftar staf operasional.'],'vehicle'=>['Master Mobil','Daftar armada pengiriman.']];
@endphp
@foreach ($masterDefs as $entityKind => $entityMeta)
    @include('v2.master-page-item', ['kind'=>$entityKind,'title'=>$entityMeta[0],'subtitle'=>$entityMeta[1],'records'=>$allMasterRecords[$entityKind] ?? collect(),'dialogsOnly'=>$entityKind === 'employee'])
@endforeach
@include('v2.master-staff')

@foreach(['driver' => 'Sopir', 'unit' => 'Satuan', 'category' => 'Kategori'] as $relationKind => $relationTitle)
    @include('v2.relation-master', ['kind' => $relationKind, 'title' => $relationTitle, 'records' => $relationRecords[$relationKind] ?? collect(), 'dialogsOnly' => $relationKind === 'driver'])
@endforeach

@include('v2.opening-form')

<section class="card" aria-labelledby="review-heading">
<h2 id="review-heading">Pemeriksaan selisih kas harian</h2>
<p class="note">Mencakup semua tanggal. Mencatat pemeriksaan mengurangi daftar belum diselesaikan, tetapi tidak menghapus riwayat selisih atau membuat jurnal penyesuaian kas.</p>
<div data-live-list>
@if (count($reviewShifts) === 0)
    <p class="note">Tidak ada selisih kas harian yang perlu diperiksa.</p>
@else
    @foreach ($reviewShifts as $shift)
    <form class="v2-json-form" action="{{ route('v2.shifts.resolve', $shift->id) }}">
        <p>Kas harian {{ substr((string) $shift->opened_at, 0, 10) }}: fisik {{ $shift->counted_cash }}; selisih {{ $shift->difference }}. Angka historis tidak diubah.</p>
        <div class="field">
            <label for="review-{{ $shift->id }}">Catatan pemeriksaan</label>
            <input id="review-{{ $shift->id }}" name="reason" required minlength="5" maxlength="1000">
        </div>
        <button type="submit">Catat pemeriksaan admin</button>
        <p data-error class="err" tabindex="-1" hidden></p>
    </form>
    @endforeach
@endif
</div>
</section>

<section class="card" aria-labelledby="receipt-heading">
<h2 id="receipt-heading">Penerimaan barang berutang <x-ui.info-tip key="draf_penerimaan" label="Keterangan draf dan konfirmasi final penerimaan"/></h2>
<p class="note">Simpan sebagai draf dahulu. Stok, HPP, dan utang supplier baru berubah saat penerimaan dikonfirmasi dan dikunci.</p>
<form id="receipt-form" novalidate>
<fieldset class="rc-info">
    <legend>Info penerimaan</legend>
    <div class="field">
        <label for="rc-supplier">Supplier</label>
        <select id="rc-supplier" name="supplier_id" required>
            <option value="">Pilih supplier</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="rc-date">Tanggal terima</label>
        <input id="rc-date" type="date" name="receipt_date" value="{{ now()->toDateString() }}" required>
    </div>
    <div class="field">
        <label for="rc-nota">No. nota supplier <span class="rc-opt">(opsional)</span></label>
        <input id="rc-nota" name="supplier_invoice_number" type="text" maxlength="64" autocomplete="off" placeholder="Nomor di nota/faktur supplier">
    </div>
</fieldset>
@include('v2.receipt-restock')
<fieldset class="rc-items">
    <legend class="lbl">Barang diterima <x-ui.info-tip key="hpp_penerimaan" label="Keterangan HPP satuan pembelian"/></legend>
    <div class="rc-head" aria-hidden="true"><span>Barang</span><span>Jumlah</span><span>HPP satuan</span><span>Nilai</span><span></span></div>
    <div id="receipt-items"></div>
    <div class="rc-foot">
        <button type="button" class="secondary" id="receipt-add">+ Tambah barang manual</button>
        <p class="rc-total" role="status" aria-live="polite"><span id="rc-count">0 barang</span><strong id="rc-sum">Rp 0</strong></p>
    </div>
</fieldset>
<p class="note">HPP satuan dipakai untuk nilai persediaan, bukan harga jual.</p>
<fieldset class="rc-freight" @disabled(!($freightReady ?? false)) @if(!($freightReady ?? false)) hidden @endif>
    <legend>Ongkir pembelian (beban, bukan HPP) <x-ui.info-tip key="ongkir_penerimaan" label="Keterangan ongkir pembelian"/></legend>
    <div class="field"><label for="rc-freight">Ongkir</label><input id="rc-freight" name="freight_amount" data-money inputmode="decimal" value="0" maxlength="30"></div>
    <div class="field"><label for="rc-freight-method">Pembayaran ongkir</label><select id="rc-freight-method" name="freight_method"><option value="supplier">Ikut tagihan supplier</option><option value="cash">Kas harian</option><option value="bank">Bank</option></select></div>
    <div class="field" data-bank-account-field hidden><label for="rc-freight-bank">Rekening bank ongkir</label><select id="rc-freight-bank" name="freight_bank_account_code" disabled>@foreach(($bankAccounts ?? collect([(object)['account_code' => '1102', 'account_name' => 'Rekening Bank']])) as $bankAcc)<option value="{{ $bankAcc->account_code }}">{{ $bankAcc->account_code }} · {{ $bankAcc->account_name }}</option>@endforeach</select></div>
    <div class="field"><label for="rc-freight-account">Akun beban ongkir</label><select id="rc-freight-account" name="freight_account"><option value="">Pilih akun beban</option>@foreach(($freightAccounts ?? collect()) as $account)<option value="{{ $account->account_code }}">{{ $account->account_code }} · {{ $account->account_name }}</option>@endforeach</select></div>
</fieldset>
@unless($freightReady ?? false)<p class="note">Ongkir belum aktif: migrasi perlu dijalankan setelah mendapat persetujuan admin. Penerimaan tanpa ongkir tetap tersedia.</p>@endunless
<p class="rc-submit"><button type="submit">Periksa penerimaan</button></p>
<div class="rc-review" id="receipt-review" data-popup-skip hidden>
    <h3 id="receipt-review-heading" tabindex="-1">Periksa sebelum simpan</h3>
    <p class="note">Pastikan jumlah dan harga sesuai nota supplier. Simpan draf belum mengubah stok, HPP, atau utang. Konfirmasi dilakukan terpisah dari daftar draf.</p>
    <dl class="rc-review-meta"><div><dt>Supplier</dt><dd data-review-supplier></dd></div><div><dt>Tanggal terima</dt><dd data-review-date></dd></div><div data-review-nota-row><dt>No. nota supplier</dt><dd data-review-nota></dd></div></dl>
    <ul class="rc-review-list" data-review-list></ul>
    <p class="rc-review-total"><span>Nilai barang (tanpa ongkir)</span><strong data-review-total></strong></p>
    <p class="note" data-freight-summary></p>
    <p class="rc-review-actions"><button type="button" class="secondary" data-review-back>Ubah lagi</button><button type="submit">Simpan draf penerimaan</button></p>
</div>
<p data-error class="err" tabindex="-1" hidden></p>
<p data-ok class="ok" hidden></p>
</form>
</section>
@include('v2.receipt-drafts')
<section class="card" id="receipt-history" aria-labelledby="h-receipt-history">
<h2 id="h-receipt-history">Riwayat penerimaan</h2>
<p class="note">Total nilai halaman ini: <strong data-receipt-page-total data-live-skip>{{ \App\Services\V2\Money::display('0') }}</strong></p>
@php
    $receiptTypes = ['purchase' => ['Pembelian', 'ok'], 'opening' => ['Stok awal', 'mute'], 'restock' => ['Tambah stok', 'info']];
    $receiptItems = $receiptItems ?? collect();
    // Qty tampil tanpa nol di belakang koma, ribuan pakai titik (3.000; 2,5).
    $qty = function ($value): string {
        [$int, $frac] = array_pad(explode('.', (string) $value), 2, '');
        $frac = rtrim($frac, '0');
        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', ltrim($int, '0') === '' ? '0' : ltrim($int, '0')).($frac !== '' ? ','.$frac : '');
    };
    $receiptPay = ['2101' => ['payable', 'Utang supplier'], '1101' => ['cash', 'Tunai'], '1102' => ['bank', 'Bank'], '3101' => ['equity', 'Modal awal']];
@endphp
<x-ui.toolbar search="Cari nomor, pemasok, atau produk" label="Cari penerimaan" :chips="['Semua' => 'all', 'Pembelian' => 'type:purchase,restock', 'Stok awal' => 'type:opening', 'Utang supplier' => 'pay:payable', 'Tunai atau bank' => 'pay:cash,bank']"/>
<div class="ui-table-wrap scroll" data-ui-table data-ui-noun="penerimaan" tabindex="0" role="region" aria-label="Riwayat penerimaan barang">
<table class="ui-table">
<caption class="sr">Riwayat penerimaan barang</caption>
<thead><tr><th scope="col">Tanggal</th><th scope="col">Nomor dan pemasok</th><th scope="col">Produk</th><th scope="col">Tipe</th><th scope="col" class="ui-num">Total</th><th scope="col" class="ui-actions">Aksi</th></tr></thead>
<tbody>
@forelse($receiptHistory as $receipt)
@php [$payKey, $payLabel] = $receiptPay[$receipt->credit_account ?? ''] ?? ['other', (string) ($receipt->credit_account ?? '—')]; @endphp
@php
    [$typeLabel, $typeTone] = $receiptTypes[$receipt->receipt_type ?? ''] ?? [(string) ($receipt->receipt_type ?? '—'), 'mute'];
    $lines = $receiptItems->get($receipt->id, collect());
    $account = (string) ($receipt->credit_account ?? '');
@endphp
@php $voided = $receipt->voided_at !== null; @endphp
<tr data-pay="{{ $payKey }}" data-type="{{ $receipt->receipt_type }}" data-ui-search-text="{{ $lines->pluck('product_name')->implode(' ') }}" data-amount="{{ $voided ? '0' : $receipt->total_amount }}" @class(['rh-void' => $voided])>
<td data-label="Tanggal" class="ui-nowrap rh-date">{{ \Carbon\Carbon::parse($receipt->receipt_date)->locale('id')->translatedFormat('d M Y') }}</td>
<td data-label="Nomor" class="ui-primary rh-number">{{ $receipt->receipt_number ?: '#'.$receipt->id }}<span class="ui-sub">{{ $receipt->supplier_name ? 'Pemasok: '.$receipt->supplier_name : ($receipt->receipt_type === 'opening' ? 'Stok awal gudang' : 'Tanpa pemasok') }}@if(! empty($receipt->supplier_invoice_number))<span class="rh-ref"> · Nota {{ $receipt->supplier_invoice_number }}</span>@endif
@if($receipt->notes)<span class="rh-ref"> · {{ \Illuminate\Support\Str::limit($receipt->notes, 40) }}</span>@endif</span></td>
<td data-label="Produk" class="rh-product">@if($lines->isNotEmpty())<strong>{{ $lines->count() }} jenis barang</strong><span class="ui-sub">{{ $lines->first()->product_name }}@if($lines->count() > 1) + {{ $lines->count() - 1 }} lainnya @endif</span>@else<span class="ui-muted">—</span>@endif</td>
<td data-label="Tipe" class="rh-type">@if($voided)<span class="ui-badge ui-badge--bad">Dibatalkan</span><span class="ui-sub rh-void-note">{{ \Carbon\Carbon::parse($receipt->voided_at)->locale('id')->translatedFormat('d M Y H:i') }}{{ $receipt->voided_by_name ? ' · '.$receipt->voided_by_name : '' }} · {{ $receipt->void_reason }}</span>@else<span class="ui-badge ui-badge--{{ $typeTone }}">{{ $typeLabel }}</span>@endif<span class="ui-sub" data-tip="{{ $payLabel }}">Akun {{ $account !== '' ? $account : '—' }} · {{ $payLabel }}</span></td>
<td data-label="Total" class="ui-num rh-total"><strong>{{ \App\Services\V2\Money::display($receipt->total_amount) }}</strong>@if(\Brick\Math\BigDecimal::of((string) ($receipt->freight_amount ?? '0'))->isPositive())<span class="ui-sub">Nilai barang</span><span class="ui-sub">Ongkir {{ \App\Services\V2\Money::display($receipt->freight_amount) }} · {{ ['supplier'=>'Utang supplier','cash'=>'Kas','bank'=>'Bank'][$receipt->freight_method] ?? '—' }}</span>@endif</td>
<td data-label="Aksi" class="ui-actions">
    <button type="button" class="btn-action-pill" data-open-popup="receipt-detail-{{ $receipt->id }}">Detail</button>
    @include('v2.receipt-history-detail')
</td>
</tr>
@empty
<tr data-ui-empty-static><td colspan="6" class="ui-empty"><strong>Belum ada penerimaan barang.</strong>Tekan "Catat penerimaan" untuk mencatat barang masuk pertama.</td></tr>
@endforelse
</tbody>
</table>
</div>
<script>
/* Total nilai baris yang sedang tampil. Dijumlah dalam sen (BigInt) dari atribut data-amount, bukan float; hanya tampilan. */
(()=>{const wrap=document.querySelector('#receipt-history [data-ui-table]');const out=document.querySelector('[data-receipt-page-total]');if(!wrap||!out)return;
 const cents=v=>{const [i,f='']=String(v||'0').split('.');return BigInt(i||'0')*100n+BigInt((f+'00').slice(0,2));};
 const fmt=c=>{const neg=c<0n;if(neg)c=-c;const i=(c/100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g,'.'),f=(c%100n).toString().padStart(2,'0');return (neg?'-':'')+'Rp '+i+(f==='00'?'':','+f);};
 const update=()=>{let sum=0n;for(const r of wrap.querySelectorAll('tbody tr[data-amount]'))if(!r.hidden)sum+=cents(r.dataset.amount);out.textContent=fmt(sum);};
 wrap.addEventListener('ui-table:render',update);update();})();
</script>
</section>

<template id="receipt-tpl">
<div class="rc-row">
    <div class="field">
        <label class="rc-field-label" data-label>Barang</label>
        <select data-name="product_id" required data-pick-placeholder="Cari nama atau SKU barang">
            <option value="">— pilih barang —</option>
        </select>
    </div>
    <div class="field">
        <label class="rc-field-label" data-label>Jumlah</label>
        <input data-name="quantity" type="text" inputmode="decimal" maxlength="30" placeholder="jumlah" required>
    </div>
    <div class="field">
        <label class="rc-field-label" data-label>HPP satuan</label>
        <input data-name="unit_cost" type="text" data-money data-money-scale="4" inputmode="decimal" maxlength="30" placeholder="HPP satuan" required>
    </div>
    <div class="rc-line-value"><span class="rc-field-label">Subtotal</span><output class="rc-value" data-line-total>Rp 0</output></div>
    <button type="button" class="secondary rc-remove" data-remove aria-label="Hapus baris barang">×</button>
</div>
</template>

<section class="card" aria-labelledby="trip-heading">
<h2 id="trip-heading">Biaya ritase sopir</h2>
<p class="note">Untuk perjalanan tanpa uang muka. Jika sudah ada uang muka, gunakan realisasi sopir agar biaya tidak dicatat dua kali.</p>
<form id="trip-form" data-transport-form novalidate>
<div class="row">
    <div class="field">
        <label for="trip-vehicle">Mobil</label>
        <select id="trip-vehicle" name="vehicle_id">
            @foreach ($vehicles as $vehicle)
                <option value="{{ $vehicle->id }}">{{ $vehicle->license_plate }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="trip-driver">Sopir</label>
        <select id="trip-driver" name="driver_id" required>@include('v2.driver-options')</select>
    </div>
    <div class="field">
        <label for="trip-date">Tanggal</label>
        <input id="trip-date" type="date" name="date" value="{{ now()->toDateString() }}" required>
    </div>
</div>
<div class="row" style="margin-top:8px">
    <div class="field">
        <label for="trip-destination">Tujuan</label>
        <input id="trip-destination" name="destination" required maxlength="150" autocomplete="off">
    </div>
    <div class="field">
        <label for="trip-delivery">Surat jalan</label>
        <select id="trip-delivery" name="delivery_id">
            <option value="">Tanpa tautan</option>
            @foreach ($transportDeliveries as $delivery)
                <option value="{{ $delivery->id }}" data-vehicle="{{ $delivery->vehicle_id }}" data-driver="{{ $delivery->driver_id }}" data-destination="{{ $delivery->destination }}">{{ $delivery->number }} · {{ $delivery->destination }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="row" style="margin-top:8px">
    <div class="field">
        <label for="trip-fuel">BBM</label>
        <input id="trip-fuel" name="fuel_cost" value="0" data-money inputmode="decimal" maxlength="30">
    </div>
    <div class="field">
        <label for="trip-allowance">Uang jalan</label>
        <input id="trip-allowance" name="allowance" value="0" data-money inputmode="decimal" maxlength="30">
    </div>
    <div class="field">
        <label for="trip-method">Pembayaran</label>
        <select id="trip-method" name="payment_method">
            <option value="cash">Tunai</option>
            <option value="transfer">Transfer</option>
        </select>
    </div>
    <div class="field" data-bank-account-field hidden>
        <label for="trip-bank">Rekening bank</label>
        <select id="trip-bank" name="bank_account_code" disabled>
            @foreach(($bankAccounts ?? collect([(object)['account_code' => '1102', 'account_name' => 'Rekening Bank']])) as $bankAcc)
                <option value="{{ $bankAcc->account_code }}">{{ $bankAcc->account_code }} · {{ $bankAcc->account_name }}</option>
            @endforeach
        </select>
    </div>
</div>
<p><button type="submit">Catat biaya aktual</button></p>
<p data-error class="err" tabindex="-1" hidden></p>
<p data-ok class="ok" hidden></p>
</form>
</section>
@endif

@if ($role === 'admin')
<section class="card" aria-labelledby="reject-heading">
<h2 id="reject-heading">Tolak pengajuan / transfer</h2>
@php $waitingPayments = collect($payments)->filter(fn ($p) => in_array(($p->status ?? ''), ['pending', 'proposed'], true))->values()->all(); @endphp
<div data-live-list>
@if (count($waitingPayments) === 0)
    <p class="note">Tidak ada pengajuan menunggu.</p>
@else
    @foreach ($waitingPayments as $payment)
    <form class="v2-json-form" action="{{ route('v2.payments.reject', $payment->id) }}">
        <p><strong>{{ \App\Services\V2\Money::display($payment->amount) }}</strong> · {{ ($payment->invoice_direction ?? '') === 'payable' ? 'Bayar ke' : 'Dari' }} {{ $payment->party_name ?? 'Pelanggan umum' }} · {{ $payment->invoice_number }} · {{ ['main' => 'Kas Utama', 'cash' => 'Laci shift', 'transfer' => 'Bank Utama'][$payment->method] ?? $payment->method }}@if($payment->method === 'transfer' && !empty($payment->bank_account_code)) ({{ $payment->bank_account_code }}{{ isset($bankAccountNames[$payment->bank_account_code]) ? ' · '.$bankAccountNames[$payment->bank_account_code] : '' }})@endif @if($payment->requester_name) · diajukan {{ $payment->requester_name }}@endif</p>
        <div class="field">
            <label for="reject-{{ $payment->id }}">Alasan penolakan</label>
            <input id="reject-{{ $payment->id }}" name="reason" required minlength="5" maxlength="1000">
        </div>
        <button type="submit" class="secondary">Tolak dengan catatan</button>
        <p data-error class="err" tabindex="-1" hidden></p>
    </form>
    @endforeach
@endif
</div>
</section>
@endif

<script>
(() => {
const csrfMeta = document.querySelector('meta[name="csrf-token"]');
const csrf = csrfMeta ? csrfMeta.content : '';
const key = () => (window.crypto && typeof crypto.randomUUID === 'function') ? crypto.randomUUID() : String(Date.now()) + Math.random().toString(16).slice(2);
document.querySelectorAll('[data-fresh-key]').forEach(e => { e.value = key(); });
// Isi baru dari uiLive (baris/daftar yang diganti) juga mendapat kunci transaksi baru.
window.uiLive?.onPatch(root => root.querySelectorAll('[data-fresh-key]').forEach(e => { if (!e.value) { e.value = key(); } }));

async function send(form, url, data, multipart = false, btn = null, lockOnSuccess = false) {
    if (!form || form.dataset.busy || !form.reportValidity()) { return null; }
    form.dataset.busy = '1';
    form.setAttribute('aria-busy', 'true');
    window.v2FormFeedback?.clear(form);
    const button = btn || form.querySelector('button[type="submit"], button:not([type])');
    if (button) { button.disabled = true; }
    const error = form.querySelector('[data-error]');
    const ok = form.querySelector('[data-ok]');
    if (error) { error.hidden = true; error.textContent = ''; }
    if (ok) { ok.hidden = true; ok.textContent = ''; }
    let saved = false;
    try {
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: multipart
                ? { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
                : { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: multipart ? data : JSON.stringify(data)
        });
        const result = await res.json().catch(() => ({}));
        if (!res.ok) {
            const messages = Object.values(result.errors || {}).flat().join(' ') || result.message || ('Gagal menyimpan (HTTP ' + res.status + ').');
            const failure = new Error(messages);
            failure.errors = result.errors;
            throw failure;
        }
        saved = true;
        return result;
    } catch (e) {
        form._lastError = e;
        window.v2FormFeedback.summary(form, e.message, e.errors);
        return null;
    } finally {
        if (!saved || !lockOnSuccess) {
            if (button) { button.disabled = false; }
            delete form.dataset.busy;
            form.removeAttribute('aria-busy');
        }
    }
}

// Penangan dipasang di dokumen (bukan per form) agar form baru dari uiLive langsung berfungsi.
document.addEventListener('submit', async e => {
    const f = e.target.closest?.('.v2-json-form');
    if (!f) { return; }
    e.preventDefault();
    if (!f.reportValidity()) { return; }
    const payload = Object.fromEntries(new FormData(f));
    if (f.matches('.receipt-confirm-form')) payload.confirm_duplicate = f.dataset.confirmDuplicate === '1';
    f._lastError = null;
    if (await send(f, f.action, payload, false, null, true)) { await window.uiLive.saved(f); }
    else if (f.matches('.receipt-confirm-form') && f._lastError?.errors?.duplicate_receipt) {
        window.uiConfirmDuplicate?.(f, 'Ya, tetap konfirmasi');
    }
});

// === Master Entity V2 Handlers ===
const masterPayload = form => {
    const data = Object.fromEntries(new FormData(form));
    if (form.querySelector('[data-product-suppliers]')) {
        data.supplier_ids = new FormData(form).getAll('supplier_ids[]');
        delete data['supplier_ids[]'];
    }
    return data;
};
const driverMaster = form => {
    if (form.dataset.driverReady) { return; }
    form.dataset.driverReady = '1';
    const sync = () => {
        const internal = form.elements.kind.value === 'internal';
        const employee = form.elements.employee_id;
        form.querySelector('[data-driver-employee]').hidden = !internal;
        employee.disabled = !internal;
        employee.required = internal;
        form.elements.name.readOnly = internal;
        if (internal) form.elements.name.value = employee.selectedOptions[0]?.dataset.name || '';
    };
    // Jenis sopir berupa tombol pilihan (radio): dengarkan perubahan di tingkat form.
    form.addEventListener('change', e => { if (e.target.name === 'kind' || e.target.name === 'employee_id') sync(); });
    form.addEventListener('reset', () => setTimeout(sync));
    sync();
};
document.querySelectorAll('[data-driver-master]').forEach(driverMaster);
window.uiLive?.onPatch(root => root.querySelectorAll('[data-driver-master]').forEach(driverMaster));
// 1. Create master records
document.querySelectorAll('form.master-create-form').forEach(form => {
    form.addEventListener('submit', async e => {
        e.preventDefault();
        if (form.dataset.busy || !form.reportValidity()) return;
        const err = form.querySelector('[data-error]');
        if (err) { err.hidden = true; err.textContent = ''; }
        const ok = form.querySelector('[data-ok]');
        if (ok) { ok.hidden = true; ok.textContent = ''; }
        const res = await send(form, form.action, masterPayload(form), false, null, true);
        if (res) { window.uiMotion?.markSaved(form); await window.uiLive.saved(form); }
    });
});

// 2. Edit & Archive master records (PUT / PATCH)
document.addEventListener('submit', async e => {
    const form = e.target.closest?.('form[data-master-form]');
    if (!form) { return; }
    {
        e.preventDefault();
        if (form.dataset.busy || !form.reportValidity()) return;
        const method = (form.dataset.method || 'POST').toUpperCase();
        const data = masterPayload(form);
        if (method !== 'POST') data._method = method;
        const statusEl = form.querySelector('.mr-msg') || form.querySelector('[data-error]');
        if (statusEl) { statusEl.textContent = ''; if (statusEl.hasAttribute('hidden')) statusEl.hidden = true; }
        const res = await send(form, form.action, data, false, null, true);
        if (res) { window.uiMotion?.markSaved(form); await window.uiLive.saved(form, {reset: false}); }
    }
});

// 3. Excel Import with in-flight cancellation, localized preview table & manual commit
document.querySelectorAll('form.master-import-form').forEach(impForm => {
    const fileInput = impForm.querySelector('input[type="file"]');
    const previewWrap = impForm.querySelector('.import-preview-table-wrap');
    const statusMsg = impForm.querySelector('.import-preview-status');
    const commitArea = impForm.querySelector('.import-commit-area');
    const commitBtn = impForm.querySelector('.btn-do-commit');
    const errBox = impForm.querySelector('[data-error]');
    let currentBatchId = null;
    let fileReqGen = 0;

    const resetImportState = () => {
        currentBatchId = null;
        if (commitArea) commitArea.style.display = 'none';
        if (commitBtn) commitBtn.disabled = false;
        if (previewWrap) { previewWrap.replaceChildren(); previewWrap.hidden = true; }
        if (statusMsg) statusMsg.textContent = '';
        if (errBox) { errBox.hidden = true; errBox.textContent = ''; }
    };

    if (fileInput) {
        fileInput.addEventListener('change', () => {
            fileReqGen++; // Invalidate any in-flight response
            resetImportState();
        });
    }

    impForm.addEventListener('submit', async e => {
        e.preventDefault();
        const curGen = ++fileReqGen;
        resetImportState();
        if (!impForm.reportValidity()) return;

        const r = await send(impForm, impForm.action, new FormData(impForm), true);
        if (curGen !== fileReqGen) return; // Discard stale async in-flight response
        if (!r) return;

        const raw = r.errors;
        const errs = Array.isArray(raw) ? raw.map(String) : (raw ? Object.values(raw).flat().map(String) : []);
        const rows = Array.isArray(r.rows) ? r.rows : [];

        if (rows.length > 0 && previewWrap) {
            const table = document.createElement('table');
            table.className = 'master-clean-table';
            const cols = Object.keys(rows[0]);
            const thead = document.createElement('thead');
            const trH = document.createElement('tr');
            cols.forEach(c => {
                const th = document.createElement('th');
                th.textContent = c;
                trH.append(th);
            });
            thead.append(trH);
            table.append(thead);

            const tbody = document.createElement('tbody');
            rows.slice(0, 50).forEach(row => {
                const tr = document.createElement('tr');
                cols.forEach(c => {
                    const td = document.createElement('td');
                    td.textContent = row[c] !== null && row[c] !== undefined ? String(row[c]) : '—';
                    tr.append(td);
                });
                tbody.append(tr);
            });
            table.append(tbody);
            previewWrap.replaceChildren(table);
            if (rows.length > 50) {
                const moreNote = document.createElement('p');
                moreNote.className = 'note';
                moreNote.textContent = 'Menampilkan 50 baris pertama dari total ' + rows.length + ' baris pratinjau.';
                previewWrap.append(moreNote);
            }
            previewWrap.hidden = false;
        }

        if (r.batch_id && errs.length === 0) {
            currentBatchId = r.batch_id;
            if (commitArea) commitArea.style.display = 'block';
            if (statusMsg) {
                statusMsg.textContent = 'Pratinjau berkas berhasil (' + rows.length + ' baris). Belum ada data yang disimpan; periksa tabel lalu tekan Simpan Hasil Impor.';
            }
        } else if (statusMsg) {
            statusMsg.textContent = 'Pratinjau menemukan masalah: ' + (errs.join(' ') || 'Format berkas tidak valid.');
        }
    });

    if (commitBtn) {
        commitBtn.addEventListener('click', async () => {
            if (!currentBatchId || commitBtn.disabled) return;
            commitBtn.disabled = true;
            const commitUrl = @json(url('/v2/import-batches')) + '/' + encodeURIComponent(currentBatchId) + '/commit';
            const r = await send(impForm, commitUrl, {}, false, commitBtn, true);
            if (r) { resetImportState(); impForm.reset(); await window.uiLive.saved(impForm); } else { commitBtn.disabled = false; }
        });
    }
});

// 4. Client-side local search (scopes only to 100 latest visible datacells, not edit inputs)
document.querySelectorAll('input.master-instant-search').forEach(input => {
    input.addEventListener('input', () => {
        const term = input.value.trim().toLowerCase();
        const targetSel = input.getAttribute('data-search-target');
        const table = document.querySelector(targetSel);
        if (!table) return;
        const rows = table.querySelectorAll('tbody tr[data-record-id]');
        rows.forEach(tr => {
            const dataCells = Array.from(tr.querySelectorAll('td[data-field]'));
            const matches = !term || dataCells.some(td => td.textContent.toLowerCase().includes(term));
             tr.style.display = matches ? '' : 'none';
         });
        const count = Array.from(rows).filter(row => row.style.display !== 'none').length;
        const status = document.querySelector('[data-master-search-status="' + table.id + '"]');
        if (status) { status.textContent = count ? count + ' dari ' + rows.length + ' data ditampilkan.' : 'Tidak ada data sesuai pencarian. Ubah kata kunci untuk mencoba lagi.'; }
    });
});

@include('v2.opening-form-script')

const receipt = document.querySelector('#receipt-form');
if (receipt) {
    const box = document.querySelector('#receipt-items');
    const tpl = document.querySelector('#receipt-tpl');
    const labels = { product_id: 'Barang', quantity: 'Jumlah', unit_cost: 'HPP satuan' };
    const productsUrl = @json(route('v2.suppliers.products', ['id' => 0]));
    const count = value => Number(value).toLocaleString('id-ID', {maximumFractionDigits: 4});
    const takenElsewhere = select => Array.from(box.querySelectorAll('[data-name=product_id]')).filter(other => other !== select).map(other => other.value).filter(Boolean);
    // Pencarian barang dilakukan di server (nama atau SKU) hanya untuk barang yang tertaut ke supplier terpilih.
    const searchProducts = select => async term => {
        const supplier = receipt.elements.supplier_id.value;
        const response = await fetch(productsUrl.replace(/\/0\//, '/' + supplier + '/') + '?q=' + encodeURIComponent(term.trim()), {credentials: 'same-origin', headers: {'Accept': 'application/json'}});
        if (!response.ok) { throw new Error('gagal'); }
        const data = await response.json();
        const taken = takenElsewhere(select);
        return {
            total: data.total,
            items: data.results.map(p => ({
                value: String(p.id),
                label: p.name + ' · ' + p.unit,
                meta: p.sku + ' · stok ' + count(p.stock) + ' · minimum ' + count(p.minimum) + ' · ' + ({out:'Stok habis',low:'Stok menipis',available:'Stok tersedia'}[p.status] || '') + ' · HPP saat ini ' + rupiah(Number(p.cost) || 0),
                cost: p.cost,
                disabled: taken.includes(String(p.id))
            }))
        };
    };
    const filterProducts = row => {
        const select = row.querySelector('[data-name=product_id]');
        select.disabled = !receipt.elements.supplier_id.value;
        select._uiPickSync?.();
    };
    let receiptSupplier = receipt.elements.supplier_id.value;
    function hasReceiptItems() {
        return Array.from(box.children).some(row => ['product_id', 'quantity', 'unit_cost'].some(name => row.querySelector('[data-name=' + name + ']').value.trim() !== ''));
    }
    function canUseReceiptSupplier(id) {
        return String(id) === receipt.elements.supplier_id.value || !hasReceiptItems();
    }
    receipt._hasReceiptItems = hasReceiptItems;
    receipt._canUseReceiptSupplier = canUseReceiptSupplier;
    receipt._selectReceiptSupplier = selected => {
        if (receipt.dataset.busy || receipt.classList.contains('is-reviewing') || !canUseReceiptSupplier(selected.id)) { return false; }
        const select = receipt.elements.supplier_id;
        if (!Array.from(select.options).some(option => option.value === String(selected.id))) {
            select.add(new Option(selected.name, String(selected.id)));
        }
        select.value = String(selected.id);
        select.dispatchEvent(new Event('change', {bubbles:true}));
        return select.value === String(selected.id);
    };
    receipt.elements.supplier_id.addEventListener('change', () => {
        const select = receipt.elements.supplier_id;
        if (select.value !== receiptSupplier && hasReceiptItems()) {
            select.value = receiptSupplier;
            select.dispatchEvent(new Event('ui-refresh'));
            const error = receipt.querySelector('[data-error]');
            if (error) { error.textContent = 'Selesaikan atau simpan draf supplier saat ini dahulu. Isian barang tidak dihapus.'; error.hidden = false; }
            return;
        }
        receiptSupplier = select.value;
        Array.from(box.children).forEach(filterProducts);
    });
    receipt.addEventListener('reset', () => queueMicrotask(() => { receiptSupplier = receipt.elements.supplier_id.value; }));
    const renumber = () => Array.from(box.children).forEach((row, i) => {
        row.querySelectorAll('[data-name]').forEach(el => {
            const field = el.closest('.field'), control = field.querySelector('.ui-pick-input') || el;
            const label = labels[el.dataset.name] + ' baris ' + (i + 1);
            control.id = 'rc-item-' + (i + 1) + '-' + el.dataset.name;
            field.querySelector('label').htmlFor = control.id;
            el.setAttribute('aria-label', label);
            control.setAttribute('aria-label', label);
        });
    });
    // Nilai per baris dan ringkasan di bawah: angka dipakai untuk membaca, server tetap menghitung ulang.
    const rupiah = value => 'Rp ' + Math.round(value).toLocaleString('id-ID');
    const number = text => { const n = parseFloat(String(text).replace(/[^0-9,.-]/g, '').replace(/\.(?=\d{3}\b)/g, '').replace(',', '.')); return Number.isFinite(n) ? n : 0; };
    const recalc = () => {
        let total = 0, filled = 0;
        for (const row of box.children) {
            const qty = number(row.querySelector('[data-name=quantity]').value);
            const cost = Number(row.querySelector('[data-name=unit_cost]').value) || 0;
            const line = qty * cost;
            row.querySelector('[data-line-total]').textContent = line > 0 ? rupiah(line) : '—';
            if (row.querySelector('[data-name=product_id]').value) { filled++; }
            total += line;
        }
        document.querySelector('#rc-count').textContent = filled + ' barang';
        document.querySelector('#rc-sum').textContent = rupiah(total);
    };
    box.addEventListener('input', recalc);
    box.addEventListener('change', recalc);
    function fillReceiptCost(row, raw) {
        const cost = row.querySelector('[data-name=unit_cost]');
        const value = String(raw ?? '').trim();
        if (!/^\d{1,17}(?:\.\d{1,4})?$/.test(value) || BigInt(value.replace('.', '')) <= 0n) { return; }
        if (cost.value.trim() !== '' && (cost.dataset.costEdited === '1' || cost.value !== cost.dataset.autoCost)) { return; }
        window.uiMoney?.enhance(cost);
        cost.value = value;
        cost.dataset.autoCost = cost.value;
        delete cost.dataset.costEdited;
        cost.dispatchEvent(new Event('change', {bubbles:true}));
    }
    const addRow = focus => {
        const node = tpl.content.firstElementChild.cloneNode(true);
        node.querySelector('[data-remove]').addEventListener('click', () => { node.remove(); renumber(); recalc(); });
        box.append(node);
        filterProducts(node);
        const productSelect = node.querySelector('[data-name=product_id]');
        node.querySelector('[data-name=unit_cost]').addEventListener('input', event => { event.target.dataset.costEdited = '1'; });
        if (window.uiPick) {
            window.uiPick(productSelect, {
                source: searchProducts(productSelect),
                offText: 'Pilih supplier dulu',
                emptyText: 'Barang harus ditautkan ke supplier ini di Master Produk.',
                onPick: item => { const cost = node.querySelector('[data-name=unit_cost]'); cost.placeholder = rupiah(Number(item.cost) || 0); cost.title = 'HPP saat ini ' + cost.placeholder; node.dataset.label = item.label; node.dataset.meta = item.meta || ''; node.dataset.cost = item.cost ?? ''; fillReceiptCost(node, item.cost); recalc(); }
            });
        }
        renumber();
        recalc();
        if (focus) { (node.querySelector('.ui-pick-input') || node.querySelector('select')).focus(); }
        return node;
    };
    receipt.dataset.key = key();
    addRow(false);
    document.querySelector('#receipt-add').addEventListener('click', () => addRow(true));
    function addRestockProduct(product) {
        if (receipt.dataset.busy || receipt.classList.contains('is-reviewing')) { return 'busy'; }
        const supplier = receipt.elements.supplier_id.value;
        if (!supplier || !product.suppliers.some(item => String(item.id) === supplier)) { return 'supplier'; }
        const rows = Array.from(box.children);
        if (rows.some(row => row.querySelector('[data-name=product_id]').value === String(product.id))) { return 'duplicate'; }
        const empty = rows.find(row => ['product_id', 'quantity', 'unit_cost'].every(name => !row.querySelector('[data-name=' + name + ']').value.trim()));
        if (!empty && rows.length >= 100) { return 'limit'; }
        const row = empty || addRow(false), select = row.querySelector('[data-name=product_id]');
        select.add(new Option(product.name + ' · ' + product.unit, String(product.id), true, true));
        select.value = String(product.id);
        select.dispatchEvent(new Event('change', {bubbles:true}));
        select._uiPickSync?.();
        row.dataset.label = product.name + ' · ' + product.unit;
        row.dataset.meta = 'stok ' + count(product.stock);
        row.dataset.cost = product.cost;
        const cost = row.querySelector('[data-name=unit_cost]');
        cost.placeholder = rupiah(Number(product.cost) || 0); cost.title = 'HPP saat ini ' + cost.placeholder;
        fillReceiptCost(row, product.cost);
        recalc();
        return 'added';
    }
    receipt._addRestockProduct = addRestockProduct;
    // Tinjau sebelum simpan: ringkasan per barang + perkiraan HPP baru (rata-rata bergerak, hanya tampilan; server menghitung ulang).
    // Harga yang jauh dari HPP saat ini (di bawah setengah atau di atas dua kali) ditandai supaya salah ketik nol terlihat.
    const review = receipt.querySelector('#receipt-review');
    // Bagian isian disembunyikan selama tinjau (inline !important karena aturan tema memaksa display pada .row).
    const editParts = hide => Array.from(receipt.children).filter(el => el !== review && !el.matches('[data-error],[data-ok]')).forEach(el => hide ? el.style.setProperty('display', 'none', 'important') : el.style.removeProperty('display'));
    const showReview = () => {
        const list = review.querySelector('[data-review-list]');
        list.replaceChildren();
        let total = 0, flagged = 0;
        for (const row of box.children) {
            const select = row.querySelector('[data-name=product_id]');
            if (!select.value) { continue; }
            const qty = number(row.querySelector('[data-name=quantity]').value);
            const cost = Number(row.querySelector('[data-name=unit_cost]').value) || 0;
            const before = Number(row.dataset.cost);
            const stock = Number((row.dataset.meta || '').match(/stok ([\d.,]+)/)?.[1]?.replace(/\./g, '').replace(',', '.'));
            const line = qty * cost; total += line;
            const odd = before > 0 && (cost < before / 2 || cost > before * 2);
            if (odd) { flagged++; }
            const li = document.createElement('li');
            if (odd) { li.className = 'is-odd'; }
            const name = document.createElement('strong'); name.textContent = row.dataset.label || select.selectedOptions[0]?.textContent || 'Barang';
            const calc = document.createElement('span'); calc.textContent = count(qty) + ' × ' + rupiah(cost) + ' = ' + rupiah(line);
            li.append(name, calc);
            if (before > 0) {
                const hpp = document.createElement('small');
                const after = Number.isFinite(stock) && stock + qty > 0 ? (stock * before + qty * cost) / (stock + qty) : cost;
                hpp.textContent = 'HPP ' + rupiah(before) + ' → ' + rupiah(after) + (odd ? ' · Harga jauh berbeda dari HPP saat ini, periksa lagi.' : '');
                li.append(hpp);
            }
            list.append(li);
        }
        review.querySelector('[data-review-supplier]').textContent = receipt.elements.supplier_id.selectedOptions[0]?.textContent.trim() || '—';
        const date = receipt.elements.receipt_date.value;
        const nota = receipt.elements.supplier_invoice_number.value.trim();
        review.querySelector('[data-review-nota]').textContent = nota;
        review.querySelector('[data-review-nota-row]').hidden = nota === '';
        review.querySelector('[data-review-date]').textContent = date ? new Date(date + 'T00:00').toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'}) : '—';
        review.querySelector('[data-review-total]').textContent = rupiah(total);
        review.classList.toggle('has-odd', flagged > 0);
        review.hidden = false;
        receipt.classList.add('is-reviewing');
        // Pesan (mis. peringatan nota ganda) tampil tepat di atas tombol konfirmasi.
        review.querySelector('.rc-review-actions').before(receipt.querySelector(':scope > [data-error]') || review.querySelector('[data-error]'));
        editParts(true);
        review.querySelector('#receipt-review-heading').focus();
    };
    // Penjaga entri dobel: nota supplier sudah pernah dicatat; simpan berikutnya = konfirmasi. Direset bila supplier/nota diganti.
    const resetDuplicate = () => {
        delete receipt.dataset.confirmDuplicate;
        receipt.classList.remove('is-duplicate-warning');
        const btn = review.querySelector('button[type=submit]');
        if (btn?.dataset.label) { btn.textContent = btn.dataset.label; }
    };
    receipt.elements.supplier_invoice_number.addEventListener('input', resetDuplicate);
    receipt.elements.supplier_id.addEventListener('change', resetDuplicate);
    const hideReview = focus => {
        review.hidden = true;
        receipt.classList.remove('is-reviewing');
        const moved = review.querySelector('[data-error]');
        if (moved) { review.after(moved); }
        editParts(false);
        if (focus) { box.querySelector('.ui-pick-input, select, input')?.focus(); }
    };
    review.querySelector('[data-review-back]').addEventListener('click', () => hideReview(true));
    receipt.closest('dialog')?.addEventListener('close', () => hideReview(false));
    const draftUpdateUrl = @json(route('v2.receipt-drafts.update', ['id' => 0]));
    const resetDraft = () => {
        delete receipt.dataset.draftId; delete receipt.dataset.draftVersion; delete receipt.dataset.draftNotes;
        receipt.dataset.key = key();
        hideReview(false); resetDuplicate(); receipt.reset();
        box.replaceChildren(); addRow(false); recalc();
    };
    document.addEventListener('click', event => {
        if (event.target.closest('[data-receipt-new]')) { resetDraft(); return; }
        const trigger = event.target.closest('[data-receipt-edit]');
        if (!trigger || receipt.dataset.busy) return;
        const draft = JSON.parse(trigger.dataset.receiptEdit);
        resetDraft();
        receipt.dataset.draftId = draft.id; receipt.dataset.draftVersion = draft.version;
        receipt.dataset.draftNotes = draft.notes || '';
        const supplier = receipt.elements.supplier_id;
        if (![...supplier.options].some(o => o.value === String(draft.supplier_id))) supplier.add(new Option(draft.supplier_name, draft.supplier_id));
        supplier.value = String(draft.supplier_id); supplier.dispatchEvent(new Event('change', {bubbles:true}));
        receipt.elements.receipt_date.value = draft.receipt_date;
        receipt.elements.receipt_date.dispatchEvent(new Event('change', {bubbles:true}));
        receipt.elements.supplier_invoice_number.value = draft.supplier_invoice_number || '';
        if (receipt.elements.freight_amount) {
            receipt.elements.freight_amount.value = draft.freight_amount || '0';
            receipt.elements.freight_method.value = draft.freight_method || 'supplier';
            receipt.elements.freight_account.value = draft.freight_account || '';
            if (receipt.elements.freight_bank_account_code && draft.freight_bank_account_code) {
                receipt.elements.freight_bank_account_code.value = draft.freight_bank_account_code;
            }
            for (const name of ['freight_amount','freight_method','freight_account']) receipt.elements[name].dispatchEvent(new Event('change',{bubbles:true}));
        }
        box.replaceChildren();
        for (const item of draft.items) {
            const row = addRow(false), select = row.querySelector('[data-name=product_id]');
            select.add(new Option(item.name + ' · ' + item.unit, String(item.product_id), true, true));
            select.dispatchEvent(new Event('change', {bubbles:true})); select._uiPickSync?.();
            row.querySelector('[data-name=quantity]').value = item.quantity;
            row.querySelector('[data-name=unit_cost]').value = item.unit_cost;
            row.dataset.label = item.name; row.dataset.cost = item.current_cost;
            row.dataset.meta = 'stok ' + count(item.stock);
        }
        recalc();
        const dialog = receipt.closest('dialog');
        if (dialog && !dialog.open) { dialog._openerTrigger = trigger; dialog.showModal(); }
        receipt.elements.supplier_invoice_number.focus();
    });
    receipt.addEventListener('submit', async e => {
        e.preventDefault();
        if (receipt.dataset.busy || !receipt.reportValidity()) { return; }
        const error = receipt.querySelector('[data-error]');
        const fail = msg => { error.textContent = msg; error.hidden = false; };
        const items = Array.from(box.children).map(row => {
            const get = n => row.querySelector('[data-name="' + n + '"]').value.trim();
            return { product_id: get('product_id'), quantity: get('quantity'), unit_cost: get('unit_cost') };
        }).filter(x => x.product_id || x.quantity || x.unit_cost);
        if (items.length === 0) { fail('Tambahkan minimal satu barang.'); return; }
        if (items.some(x => !x.product_id)) { fail('Setiap baris harus memilih barang.'); return; }
        if (items.some(x => !x.quantity || !x.unit_cost)) { fail('Jumlah dan HPP satuan wajib diisi untuk setiap barang.'); return; }
        if (new Set(items.map(x => x.product_id)).size !== items.length) { fail('Barang yang sama tidak boleh muncul dua kali dalam satu penerimaan.'); return; }
        if (!receipt.classList.contains('is-reviewing')) { showReview(); return; }
        const payload = {
            supplier_id: receipt.elements.supplier_id.value,
            receipt_date: receipt.elements.receipt_date.value,
            supplier_invoice_number: receipt.elements.supplier_invoice_number.value.trim() || null,
            confirm_duplicate: receipt.dataset.confirmDuplicate === '1',
            items: items,
            idempotency_key: receipt.dataset.key,
            version: receipt.dataset.draftVersion || null,
            notes: receipt.dataset.draftNotes || null
        };
        if (receipt.elements.freight_amount && !receipt.elements.freight_amount.closest('fieldset')?.disabled) {
            payload.freight_amount = receipt.elements.freight_amount.value || '0';
            payload.freight_method = receipt.elements.freight_method.value;
            payload.freight_account = receipt.elements.freight_account.value || null;
            if (receipt.elements.freight_bank_account_code && !receipt.elements.freight_bank_account_code.disabled) {
                payload.freight_bank_account_code = receipt.elements.freight_bank_account_code.value || null;
            }
        }
        receipt._lastError = null;
        const url = receipt.dataset.draftId ? draftUpdateUrl.replace(/\/0$/, '/' + receipt.dataset.draftId) : @json(route('v2.receipts.store'));
        if (await send(receipt, url, payload, false, null, true)) {
            hideReview(false);
            resetDuplicate();
            delete receipt.dataset.draftId; delete receipt.dataset.draftVersion; delete receipt.dataset.draftNotes;
            receipt.dataset.key = key();
            box.replaceChildren();
            addRow(false);
            await window.uiLive.saved(receipt);
            Array.from(box.children).forEach(filterProducts);
            recalc();
        } else if (receipt._lastError?.errors?.duplicate_receipt) {
            window.uiConfirmDuplicate?.(receipt, 'Ya, tetap simpan');
        }
    });
}

const trip = document.querySelector('#trip-form');
if (trip) {
    trip.dataset.key = key();
    trip.addEventListener('submit', async e => {
        e.preventDefault();
        if (trip.dataset.busy || !trip.reportValidity()) { return; }
        const d = Object.fromEntries(new FormData(trip));
        d.idempotency_key = trip.dataset.key;
        if (await send(trip, @json(route('v2.trips.store')), d, false, null, true)) { trip.dataset.key = key(); await window.uiLive.saved(trip); }
    });
}
})();
</script>
