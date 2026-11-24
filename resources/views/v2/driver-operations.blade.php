@if($role === 'admin')
<section class="card" id="delivery-start" aria-labelledby="delivery-start-title">
    <h2 id="delivery-start-title">Biaya perjalanan sopir</h2>
    <div class="detail-facts delivery-actions">
        <div><h3>Ritase langsung</h3><p class="note">Biaya perjalanan sudah diketahui dan tidak memakai uang muka.</p><div data-delivery-ritase></div></div>
        <div><h3>Uang muka sopir</h3><p class="note">Berikan dana sebelum berangkat, lalu catat realisasi dari daftar uang muka.</p><div data-delivery-advance></div></div>
    </div>
</section>
@endif
<section class="card" id="pengiriman" aria-labelledby="h-pengiriman">
<h2 id="h-pengiriman">Daftar pengiriman</h2>
<p class="note">Surat jalan dari penjualan yang dikirim. Kirim barang setelah memilih mobil dan sopir, lalu catat saat barang diterima.</p>
<x-ui.toolbar search="Cari surat jalan, tujuan, atau sopir" label="Cari pengiriman" :chips="['Semua' => 'all', 'Siap dikirim' => 'status:prepared', 'Sedang dikirim' => 'status:sent', 'Diterima' => 'status:received']"/>
<div class="ui-table-wrap scroll" data-ui-table tabindex="0" role="region" aria-label="Daftar pengiriman">
<table class="ui-table">
<caption class="sr">Daftar pengiriman terbaru</caption>
<thead><tr><th scope="col">Surat jalan</th><th scope="col">Tujuan</th><th scope="col">Mobil dan sopir</th><th scope="col">Status</th><th scope="col" class="ui-actions">Aksi</th></tr></thead>
<tbody>
@forelse($deliveries as $delivery)
<tr data-status="{{ $delivery->status }}">
<td class="ui-primary ui-nowrap" data-label="Surat jalan">{{ $delivery->number }}</td>
<td data-label="Tujuan">{{ $delivery->destination ?: '—' }}</td>
<td data-label="Mobil dan sopir">{{ $delivery->license_plate ?? 'Belum ditugaskan' }}@if($delivery->driver_name)<span class="ui-sub">{{ $delivery->driver_name }}</span>@endif</td>
<td data-label="Status"><x-ui.status :status="$delivery->status"/></td>
<td class="ui-actions">
@if($delivery->status==='prepared')
<form class="driver-form" action="{{ route('v2.deliveries.dispatch',$delivery->id) }}">
    <h3 class="sr">Kirim surat jalan {{ $delivery->number }}</h3>
    <label>Kendaraan<select name="vehicle_id" required><option value="">Pilih mobil</option>@foreach($dispatchVehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected((int) $delivery->vehicle_id === (int) $vehicle->id)>{{ $vehicle->license_plate }}</option>@endforeach</select></label>
    <label>Sopir terdaftar<select name="driver_id" required data-selected-driver="{{ $delivery->driver_id }}">@include('v2.driver-options')</select></label>
    <button>Kirim barang</button><p class="err" data-error hidden></p>
</form>
@elseif($delivery->status==='sent')
<form class="driver-form" action="{{ route('v2.deliveries.receive',$delivery->id) }}"><button class="btn-action-pill">Catat barang diterima</button><p class="err" data-error hidden></p></form>
@endif
<a class="btn-action-pill" href="{{ route('v2.documents', ['id' => $delivery->sale_id, 'kind' => 'delivery']) }}">Surat jalan</a>
</td>
</tr>
@empty
<tr data-ui-empty-static><td colspan="5" class="ui-empty"><strong>Belum ada pengiriman.</strong>Penjualan dengan pengiriman akan menghasilkan surat jalan di sini.</td></tr>
@endforelse
</tbody>
</table>
</div>
</section>
@if($role==='admin')
<section class="card" id="uang-muka-sopir"><h2>Uang muka yang perlu direalisasi <x-ui.info-tip key="uang_muka_sopir" label="Keterangan uang muka sopir"/></h2><p class="note">Setelah sopir kembali, buka aksi realisasi pada baris uang muka yang sesuai.</p>
<form class="driver-form" action="{{ route('v2.driver-advances.store') }}" data-key data-transport-form>
    <label>Surat jalan<select name="delivery_id"><option value="">Tanpa tautan</option>@foreach($transportDeliveries as $d)<option value="{{ $d->id }}" data-vehicle="{{ $d->vehicle_id }}" data-driver="{{ $d->driver_id }}" data-destination="{{ $d->destination }}">{{ $d->number }} · {{ $d->destination }}</option>@endforeach</select></label>
    <label>Mobil<select name="vehicle_id" required><option value="">Pilih mobil</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}">{{ $vehicle->license_plate }}</option>@endforeach</select></label>
    <label>Sopir terdaftar<select name="driver_id" required>@include('v2.driver-options')</select></label>
    <label>Tujuan<input name="destination" maxlength="150" required></label>
    <p class="note">Surat jalan mengisi tujuan dan penugasan yang sudah tercatat. Bila belum ditugaskan, pilih mobil dan sopir; pilihan disimpan bersama surat jalan.</p>
    <label>Tanggal<input type="date" name="date" value="{{ now()->toDateString() }}" required></label><label>Uang muka<input name="amount" data-money inputmode="decimal" required></label><label>Sumber<select name="method"><option value="cash">Kas shift admin</option><option value="transfer">Bank</option></select></label><label data-bank-account-field hidden>Rekening bank<select name="bank_account_code" disabled>@foreach(($bankAccounts ?? collect([(object)['account_code' => '1102', 'account_name' => 'Rekening Bank']])) as $bankAcc)<option value="{{ $bankAcc->account_code }}">{{ $bankAcc->account_code }} · {{ $bankAcc->account_name }}</option>@endforeach</select></label><button>Bayarkan uang muka</button><p class="err" data-error hidden></p>
</form>
<div class="ui-table-wrap scroll" tabindex="0" role="region" aria-label="Uang muka sopir terbuka">
<table class="ui-table">
<caption class="sr">Uang muka sopir yang belum direalisasi</caption>
<thead><tr><th scope="col">Tanggal</th><th scope="col">Sopir</th><th scope="col">Tujuan</th><th scope="col" class="ui-num">Uang muka</th><th scope="col" class="ui-actions">Aksi</th></tr></thead>
<tbody>
@forelse($driverAdvances as $advance)
<tr>
<td data-label="Tanggal" class="ui-nowrap">{{ \Carbon\Carbon::parse($advance->date)->locale('id')->translatedFormat('j M Y') }}</td>
<td data-label="Sopir" class="ui-primary">{{ $advance->driver_name }}</td>
<td data-label="Tujuan">{{ $advance->destination ?: '—' }}</td>
<td data-label="Uang muka" class="ui-num">{{ \App\Services\V2\Money::display($advance->amount) }}</td>
<td class="ui-actions"><form class="driver-form" action="{{ route('v2.driver-advances.settle',$advance->id) }}" data-key data-settlement data-popup-title="Realisasi uang muka" data-advance="{{ $advance->amount }}"><h3 class="sr">Realisasi uang muka {{ $advance->driver_name }} ({{ \App\Services\V2\Money::display($advance->amount) }})</h3><label>BBM aktual<input name="fuel_cost" data-money inputmode="decimal" value="0" required></label><label>Uang jalan aktual<input name="allowance" data-money inputmode="decimal" value="0" required></label><label>Pengembalian sisa<select name="refund_method"><option value="cash">Kas shift admin</option><option value="transfer">Bank</option></select></label><label data-bank-account-field hidden>Rekening bank<select name="refund_bank_account_code" disabled>@foreach(($bankAccounts ?? collect([(object)['account_code' => '1102', 'account_name' => 'Rekening Bank']])) as $bankAcc)<option value="{{ $bankAcc->account_code }}">{{ $bankAcc->account_code }} · {{ $bankAcc->account_name }}</option>@endforeach</select></label><label><input type="checkbox" name="refund_received"> Saya memastikan sisa uang sudah diterima kembali (wajib bila ada sisa)</label><p class="note dv-calc" data-settle-calc aria-live="polite"></p><button>Simpan realisasi</button><p class="err" data-error hidden></p></form></td>
</tr>
@empty
<tr data-ui-empty-static><td colspan="5" class="ui-empty"><strong>Tidak ada uang muka terbuka.</strong>Uang muka yang dibayarkan ke sopir akan muncul di sini sampai direalisasi.</td></tr>
@endforelse
</tbody>
</table>
</div>
</section>
@endif
<script>
document.querySelectorAll('[data-selected-driver]').forEach(select => { select.value = select.dataset.selectedDriver || ''; });
// Realisasi: sisa yang harus dikembalikan sopir dihitung saat mengetik (hanya tampilan; server menghitung ulang).
const settleCalc = form => {
    const out = form.querySelector('[data-settle-calc]');
    if (!out) { return; }
    const advance = Number(form.dataset.advance) || 0;
    const spent = (Number(form.elements.fuel_cost.value) || 0) + (Number(form.elements.allowance.value) || 0);
    const rp = v => 'Rp ' + Math.round(v).toLocaleString('id-ID');
    out.textContent = spent > advance
        ? 'Biaya ' + rp(spent) + ' melebihi uang muka ' + rp(advance) + '. Belum didukung di formulir ini.'
        : 'Uang muka ' + rp(advance) + ' − biaya ' + rp(spent) + ' = sisa dikembalikan sopir ' + rp(advance - spent) + '.';
    out.classList.toggle('is-warn', spent > advance);
};
document.addEventListener('input', e => { const form = e.target.closest?.('[data-settlement]'); if (form) { settleCalc(form); } });
document.querySelectorAll('[data-settlement]').forEach(settleCalc);
window.uiLive?.onPatch(root => root.querySelectorAll('[data-settlement]').forEach(settleCalc));
document.querySelectorAll('[data-transport-form]').forEach(form => {
    const delivery = form.elements.delivery_id;
    const sync = () => {
        const option = delivery.selectedOptions[0];
        const linked = delivery.value !== '';
        for (const [name, attribute] of [['vehicle_id', 'vehicle'], ['driver_id', 'driver'], ['destination', 'destination']]) {
            const field = form.elements[name];
            const value = linked ? option.dataset[attribute] : '';
            if (linked && value && field.value !== value) {
                field.value = value;
                // Beri tahu enhancer select agar teks tampilan ikut berubah.
                if (field.tagName === 'SELECT') field.dispatchEvent(new Event('change', {bubbles: true}));
            }
            if (name === 'destination') field.readOnly = linked;
            if (field.tagName === 'SELECT') {
                for (const choice of field.options) choice.disabled = Boolean(linked && value && choice.value !== value);
            }
        }
    };
    delivery.addEventListener('change', sync);
    sync();
});
(()=>{const keys=new WeakMap();/* ditangani di dokumen: form di baris baru dari uiLive ikut berfungsi */document.addEventListener('submit',async e=>{const f=e.target.closest?.('.driver-form');if(!f)return;if(!keys.has(f))keys.set(f,crypto.randomUUID());const key=keys.get(f);e.preventDefault();if(f.dataset.busy||!f.reportValidity())return;f.dataset.busy='1';const b=f.querySelector('button'),error=f.querySelector('[data-error]');b.disabled=true;error.hidden=true;const payload=Object.fromEntries(new FormData(f));if(f.hasAttribute('data-key'))payload.idempotency_key=key;if(f.hasAttribute('data-settlement'))payload.refund_received=f.elements.refund_received.checked;try{const r=await fetch(f.action,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify(payload)});const d=await r.json();if(!r.ok)throw Error(Object.values(d.errors||{}).flat().join(' ')||d.message||'Gagal menyimpan');keys.delete(f);delete f.dataset.busy;b.disabled=false;await window.uiLive.saved(f);}catch(e){error.textContent=e.message;error.hidden=false;}finally{delete f.dataset.busy;b.disabled=false;}});})();
</script>
