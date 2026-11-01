@if ($role === 'admin')
@php
    $masterDefs = ['product'=>['Master Produk','Katalog produk, satuan, harga jual dan stok.'],'customer'=>['Master Pelanggan','Daftar pelanggan dan catatan.'],'supplier'=>['Master Supplier','Daftar pemasok dan catatan.'],'employee'=>['Master Karyawan','Daftar staf operasional.'],'vehicle'=>['Master Mobil','Daftar armada pengiriman.']];
@endphp
@foreach ($masterDefs as $entityKind => $entityMeta)
    @include('v2.master-page-item', ['kind'=>$entityKind,'title'=>$entityMeta[0],'subtitle'=>$entityMeta[1],'records'=>$allMasterRecords[$entityKind] ?? collect()])
@endforeach

<section class="card" aria-labelledby="opening-heading">
<h2 id="opening-heading">Saldo awal pembukuan</h2>
<p class="note">Hanya untuk instalasi kosong. Periksa pratinjau, lalu konfirmasi posting saldo awal yang seimbang dan tidak dapat diulang. Data operasional lama tidak ditimpa.</p>
<form id="opening-form" action="{{ route('v2.openings.store') }}" novalidate>
<div class="row">
    <div class="field">
        <label for="ob-date">Tanggal saldo awal</label>
        <input id="ob-date" type="date" name="date" value="{{ now()->toDateString() }}" required>
    </div>
    <div class="field">
        <label for="ob-cash">Kas awal (tunai)</label>
        <input id="ob-cash" name="cash" type="text" inputmode="decimal" autocomplete="off" maxlength="30" placeholder="0" required>
    </div>
    <div class="field">
        <label for="ob-bank">Bank awal</label>
        <input id="ob-bank" name="bank" type="text" inputmode="decimal" autocomplete="off" maxlength="30" placeholder="0" required>
    </div>
    <div class="field">
        <label for="ob-ref">Referensi / catatan</label>
        <input id="ob-ref" name="reference" type="text" maxlength="255" autocomplete="off" placeholder="mis. catatan berita acara">
    </div>
</div>
<details style="margin:8px 0 12px">
    <summary>Rincian opsional: persediaan, piutang, utang</summary>
    <p class="note">Isi sebagai array JSON bila ada. Kosongkan bila belum ada; server tetap memvalidasi angka.</p>
    <div class="field">
        <label for="ob-inventory">Persediaan awal (JSON array)</label>
        <textarea id="ob-inventory" name="inventory" data-label="Persediaan awal" rows="3" placeholder='[{"product_id":1,"quantity":"10","unit_cost":"65000"}]'></textarea>
    </div>
    <div class="field">
        <label for="ob-receivables">Piutang awal (JSON array)</label>
        <textarea id="ob-receivables" name="receivables" data-label="Piutang awal" rows="3" placeholder='[{"party_id":1,"number":"AR-AWAL-001","amount":"250000"}]'></textarea>
    </div>
    <div class="field">
        <label for="ob-payables">Utang awal (JSON array)</label>
        <textarea id="ob-payables" name="payables" data-label="Utang awal" rows="3" placeholder='[{"party_id":2,"number":"AP-AWAL-001","amount":"400000"}]'></textarea>
    </div>
</details>
<p><button type="submit">Periksa saldo awal</button></p>
<p data-error class="err" tabindex="-1" hidden></p>
<p data-ok class="ok" hidden></p>
</form>
</section>

<section class="card" aria-labelledby="review-heading">
<h2 id="review-heading">Pemeriksaan selisih shift</h2>
@php $reviewShifts = collect($shifts)->filter(fn ($s) => ($s->status ?? '') === 'review')->values()->all(); @endphp
@if (count($reviewShifts) === 0)
    <p class="note">Tidak ada selisih shift yang perlu diperiksa.</p>
@else
    @foreach ($reviewShifts as $shift)
    <form class="v2-json-form" action="{{ route('v2.shifts.resolve', $shift->id) }}">
        <p>Shift #{{ $shift->id }}: fisik {{ $shift->counted_cash }}; selisih {{ $shift->difference }}. Angka historis tidak diubah.</p>
        <div class="field">
            <label for="review-{{ $shift->id }}">Catatan pemeriksaan</label>
            <input id="review-{{ $shift->id }}" name="reason" required minlength="5" maxlength="1000">
        </div>
        <button type="submit">Catat pemeriksaan admin</button>
        <p data-error class="err" tabindex="-1" hidden></p>
    </form>
    @endforeach
@endif
</section>

<section class="card" aria-labelledby="receipt-heading">
<h2 id="receipt-heading">Penerimaan barang berutang</h2>
<p class="note">Satu penerimaan menambah stok dan menambah tagihan supplier satu kali. Satu penerimaan boleh berisi beberapa barang.</p>
<form id="receipt-form" novalidate>
<div class="row">
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
</div>
<fieldset style="border:0;padding:0;margin:8px 0 0">
    <legend class="lbl">Barang diterima</legend>
    <div id="receipt-items"></div>
    <p><button type="button" class="secondary" id="receipt-add">Tambah barang</button></p>
</fieldset>
<p class="note">HPP satuan dipakai untuk nilai persediaan, bukan harga jual.</p>
<p><button type="submit">Simpan penerimaan</button></p>
<p data-error class="err" tabindex="-1" hidden></p>
<p data-ok class="ok" hidden></p>
</form>
</section>

<template id="receipt-tpl">
<div class="item-row">
    <div class="field">
        <label class="sr" data-label>Barang</label>
        <select data-name="product_id" required>
            <option value="">— pilih barang —</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}">{{ $product->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label class="sr" data-label>Jumlah</label>
        <input data-name="quantity" type="text" inputmode="decimal" maxlength="30" placeholder="jumlah" required>
    </div>
    <div class="field">
        <label class="sr" data-label>HPP satuan</label>
        <input data-name="unit_cost" type="text" inputmode="decimal" maxlength="30" placeholder="HPP satuan" required>
    </div>
    <button type="button" class="secondary" data-remove aria-label="Hapus baris barang">×</button>
</div>
</template>

<section class="card" aria-labelledby="trip-heading">
<h2 id="trip-heading">Biaya ritase sopir</h2>
<p class="note">Untuk perjalanan tanpa uang muka. Jika sudah ada uang muka, gunakan realisasi sopir agar biaya tidak dicatat dua kali.</p>
<form id="trip-form" novalidate>
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
        <input id="trip-driver" name="driver_name" required maxlength="100" autocomplete="off">
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
            @foreach ($deliveries as $delivery)
                <option value="{{ $delivery->id }}">{{ $delivery->number }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="row" style="margin-top:8px">
    <div class="field">
        <label for="trip-fuel">BBM</label>
        <input id="trip-fuel" name="fuel_cost" value="0" inputmode="decimal" maxlength="30">
    </div>
    <div class="field">
        <label for="trip-allowance">Uang jalan</label>
        <input id="trip-allowance" name="allowance" value="0" inputmode="decimal" maxlength="30">
    </div>
    <div class="field">
        <label for="trip-method">Pembayaran</label>
        <select id="trip-method" name="payment_method">
            <option value="cash">Tunai</option>
            <option value="transfer">Transfer</option>
        </select>
    </div>
</div>
<p><button type="submit">Catat biaya aktual</button></p>
<p data-error class="err" tabindex="-1" hidden></p>
<p data-ok class="ok" hidden></p>
</form>
</section>
@endif

<section class="card" aria-labelledby="supplier-pay-heading">
<h2 id="supplier-pay-heading">Pengajuan pembayaran supplier</h2>
@php $payables = collect($invoices)->filter(fn ($i) => ($i->direction ?? '') === 'payable' && ($i->status ?? '') === 'open')->values()->all(); @endphp
@if (count($payables) === 0)
    <p class="note">Tidak ada tagihan supplier terbuka.</p>
@else
    @foreach ($payables as $invoice)
    <form class="v2-json-form" action="{{ route('v2.payments.store', $invoice->id) }}">
        <p>{{ $invoice->number }} · nilai {{ $invoice->amount }} · terbayar {{ $invoice->paid }}</p>
        <div class="row">
            <div class="field">
                <label for="pay-amt-{{ $invoice->id }}">Nominal cicilan</label>
                <input id="pay-amt-{{ $invoice->id }}" name="amount" required inputmode="decimal" maxlength="30">
            </div>
            <div class="field">
                <label for="pay-method-{{ $invoice->id }}">Metode</label>
                <select id="pay-method-{{ $invoice->id }}" name="method">
                    <option value="cash">Tunai</option>
                    <option value="transfer">Transfer</option>
                </select>
            </div>
        </div>
        <input type="hidden" name="idempotency_key" data-fresh-key>
        <button type="submit">Ajukan pembayaran</button>
        <p data-error class="err" tabindex="-1" hidden></p>
    </form>
    @endforeach
@endif
</section>

@if ($role === 'admin')
<section class="card" aria-labelledby="reject-heading">
<h2 id="reject-heading">Tolak pengajuan / transfer</h2>
@php $waitingPayments = collect($payments)->filter(fn ($p) => in_array(($p->status ?? ''), ['pending', 'proposed'], true))->values()->all(); @endphp
@if (count($waitingPayments) === 0)
    <p class="note">Tidak ada pengajuan menunggu.</p>
@else
    @foreach ($waitingPayments as $payment)
    <form class="v2-json-form" action="{{ route('v2.payments.reject', $payment->id) }}">
        <p>Pembayaran #{{ $payment->id }} · {{ $payment->amount }}</p>
        <div class="field">
            <label for="reject-{{ $payment->id }}">Alasan penolakan</label>
            <input id="reject-{{ $payment->id }}" name="reason" required minlength="5" maxlength="1000">
        </div>
        <button type="submit" class="secondary">Tolak dengan catatan</button>
        <p data-error class="err" tabindex="-1" hidden></p>
    </form>
    @endforeach
@endif
</section>
@endif

<script>
(() => {
const csrfMeta = document.querySelector('meta[name="csrf-token"]');
const csrf = csrfMeta ? csrfMeta.content : '';
const key = () => (window.crypto && typeof crypto.randomUUID === 'function') ? crypto.randomUUID() : String(Date.now()) + Math.random().toString(16).slice(2);
document.querySelectorAll('[data-fresh-key]').forEach(e => { e.value = key(); });

async function send(form, url, data, multipart = false, btn = null) {
    if (!form || form.dataset.busy) { return null; }
    form.dataset.busy = '1';
    const button = btn || form.querySelector('button[type="submit"], button:not([type])');
    if (button) { button.disabled = true; }
    const error = form.querySelector('[data-error]');
    const ok = form.querySelector('[data-ok]');
    if (error) { error.hidden = true; error.textContent = ''; }
    if (ok) { ok.hidden = true; ok.textContent = ''; }
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
            throw new Error(messages);
        }
        return result;
    } catch (e) {
        if (error) { error.textContent = e.message; error.hidden = false; }
        return null;
    } finally {
        if (button) { button.disabled = false; }
        delete form.dataset.busy;
    }
}

document.querySelectorAll('.v2-json-form').forEach(f => f.addEventListener('submit', async e => {
    e.preventDefault();
    if (!f.reportValidity()) { return; }
    if (await send(f, f.action, Object.fromEntries(new FormData(f)))) { location.reload(); }
}));

// === Master Entity V2 Handlers ===
// 1. Create master records
document.querySelectorAll('form.master-create-form').forEach(form => {
    form.addEventListener('submit', async e => {
        e.preventDefault();
        if (form.dataset.busy || !form.reportValidity()) return;
        const err = form.querySelector('[data-error]');
        if (err) { err.hidden = true; err.textContent = ''; }
        const ok = form.querySelector('[data-ok]');
        if (ok) { ok.hidden = true; ok.textContent = ''; }
        const res = await send(form, form.action, Object.fromEntries(new FormData(form)));
        if (res) { location.reload(); }
    });
});

// 2. Edit & Archive master records (PUT / PATCH)
document.querySelectorAll('form[data-master-form]').forEach(form => {
    form.addEventListener('submit', async e => {
        e.preventDefault();
        if (form.dataset.busy || !form.reportValidity()) return;
        const method = (form.dataset.method || 'POST').toUpperCase();
        const data = Object.fromEntries(new FormData(form));
        if (method !== 'POST') data._method = method;
        const statusEl = form.querySelector('.mr-msg') || form.querySelector('[data-error]');
        if (statusEl) { statusEl.textContent = ''; if (statusEl.hasAttribute('hidden')) statusEl.hidden = true; }
        const res = await send(form, form.action, data);
        if (res) { location.reload(); }
    });
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
            const r = await send(impForm, commitUrl, {}, false, commitBtn);
            if (r) { location.reload(); } else { commitBtn.disabled = false; }
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
    });
});

const opening = document.querySelector('#opening-form');
if (opening) {
    opening.dataset.key = key();
    let reviewed=null;
    opening.addEventListener('submit', async e => {
        e.preventDefault();
        if (opening.dataset.busy) { return; }
        const error = opening.querySelector('[data-error]');
        const okBox = opening.querySelector('[data-ok]');
        const fail = msg => {
            if (okBox) { okBox.hidden = true; }
            error.textContent = msg;
            error.hidden = false;
        };
        const parseList = name => {
            const el = opening.elements[name];
            const raw = (el.value || '').trim();
            if (raw === '') { return []; }
            let parsed;
            try { parsed = JSON.parse(raw); } catch (err) { throw new Error(el.dataset.label + ' bukan JSON yang sah.'); }
            if (!Array.isArray(parsed)) { throw new Error(el.dataset.label + ' harus berupa array JSON.'); }
            return parsed;
        };
        const payload = {
            idempotency_key: opening.dataset.key,
            date: opening.elements.date.value,
            cash: opening.elements.cash.value.trim(),
            bank: opening.elements.bank.value.trim(),
            reference: (opening.elements.reference.value || '').trim() || null
        };
        if (!payload.date) { fail('Tanggal saldo awal wajib diisi.'); return; }
        if (payload.cash === '' || payload.bank === '') { fail('Kas dan bank wajib diisi, boleh 0.'); return; }
        try {
            payload.inventory = parseList('inventory');
            payload.receivables = parseList('receivables');
            payload.payables = parseList('payables');
        } catch (err) { fail(err.message); return; }
        const fingerprint=JSON.stringify(payload);
        const button=opening.querySelector('button[type="submit"]');
        if(reviewed!==fingerprint){
            const preview=await send(opening,@json(route('v2.openings.preview')),payload,false,button);
            if(preview){reviewed=fingerprint;okBox.textContent='Pratinjau: '+JSON.stringify(preview.summary)+'. Belum diposting. Periksa lalu tekan Konfirmasi posting.';okBox.hidden=false;button.textContent='Konfirmasi posting saldo awal';}
            return;
        }
        const saved = await send(opening, opening.action, payload, false, button);
        if (saved && okBox) {
            okBox.textContent = 'Saldo awal sudah diposting oleh server. Simpan nomor batch ini; pembukaan tidak dapat diulang.';
            okBox.hidden = false;
        }
    });
}

const receipt = document.querySelector('#receipt-form');
if (receipt) {
    const box = document.querySelector('#receipt-items');
    const tpl = document.querySelector('#receipt-tpl');
    const labels = { product_id: 'Barang', quantity: 'Jumlah', unit_cost: 'HPP satuan' };
    const renumber = () => Array.from(box.children).forEach((row, i) => {
        row.querySelectorAll('[data-name]').forEach(el => { el.setAttribute('aria-label', labels[el.dataset.name] + ' baris ' + (i + 1)); });
    });
    const addRow = focus => {
        const node = tpl.content.firstElementChild.cloneNode(true);
        node.querySelector('[data-remove]').addEventListener('click', () => { node.remove(); renumber(); });
        box.append(node);
        renumber();
        if (focus) { node.querySelector('select').focus(); }
    };
    receipt.dataset.key = key();
    addRow(false);
    document.querySelector('#receipt-add').addEventListener('click', () => addRow(true));
    receipt.addEventListener('submit', async e => {
        e.preventDefault();
        if (receipt.dataset.busy) { return; }
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
        const payload = {
            supplier_id: receipt.elements.supplier_id.value,
            receipt_date: receipt.elements.receipt_date.value,
            items: items,
            idempotency_key: receipt.dataset.key
        };
        if (await send(receipt, @json(route('v2.receipts.store')), payload)) { location.reload(); }
    });
}

const trip = document.querySelector('#trip-form');
if (trip) {
    trip.dataset.key = key();
    trip.addEventListener('submit', async e => {
        e.preventDefault();
        if (trip.dataset.busy) { return; }
        const d = Object.fromEntries(new FormData(trip));
        d.idempotency_key = trip.dataset.key;
        if (await send(trip, @json(route('v2.trips.store')), d)) { location.reload(); }
    });
}
})();
</script>
