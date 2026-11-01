<section class="card" id="pengiriman"><h2>Pengiriman dan penerimaan</h2>
@forelse($deliveries as $delivery)
<p><strong>{{ $delivery->number }}</strong> · {{ $statusLabel[$delivery->status] ?? $delivery->status }} · {{ $delivery->destination }}</p>
@if($delivery->status==='prepared')
<form class="driver-form" action="{{ route('v2.deliveries.dispatch',$delivery->id) }}"><label>Kendaraan<select name="vehicle_id" required>@foreach($dispatchVehicles as $vehicle)<option value="{{ $vehicle->id }}">{{ $vehicle->license_plate }}</option>@endforeach</select></label><label>Nama sopir<input name="driver_name" required maxlength="100"></label><button>Kirim barang</button><p class="err" data-error hidden></p></form>
@elseif($delivery->status==='sent')
<form class="driver-form" action="{{ route('v2.deliveries.receive',$delivery->id) }}"><button>Catat barang diterima</button><p class="err" data-error hidden></p></form>
@endif
@empty<p class="note">Belum ada pengiriman.</p>@endforelse
</section>
@if($role==='admin')
<section class="card" id="uang-muka-sopir"><h2>Uang muka dan realisasi sopir</h2><p class="note">Uang muka belum menjadi biaya. Realisasi mencatat biaya satu kali; sisa wajib benar-benar diterima kembali. Biaya melebihi uang muka belum didukung dalam formulir ini.</p>
<form class="driver-form" action="{{ route('v2.driver-advances.store') }}" data-key><label>Mobil<select name="vehicle_id" required>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}">{{ $vehicle->license_plate }}</option>@endforeach</select></label><label>Sopir<input name="driver_name" required maxlength="100"></label><label>Tujuan<input name="destination" maxlength="150" required></label><label>Surat jalan<select name="delivery_id"><option value="">Tanpa tautan</option>@foreach($deliveries as $d)<option value="{{ $d->id }}">{{ $d->number }}</option>@endforeach</select></label><label>Tanggal<input type="date" name="date" value="{{ now()->toDateString() }}" required></label><label>Uang muka<input name="amount" inputmode="decimal" required></label><label>Sumber<select name="method"><option value="cash">Kas shift admin</option><option value="transfer">Bank</option></select></label><button>Bayarkan uang muka</button><p class="err" data-error hidden></p></form>
@foreach($driverAdvances as $advance)
<hr><form class="driver-form" action="{{ route('v2.driver-advances.settle',$advance->id) }}" data-key data-settlement><h3>Realisasi #{{ $advance->id }}</h3><p>{{ $advance->driver_name }} · uang muka {{ $advance->amount }}</p><label>BBM aktual<input name="fuel_cost" inputmode="decimal" value="0" required></label><label>Uang jalan aktual<input name="allowance" inputmode="decimal" value="0" required></label><label>Pengembalian sisa<select name="refund_method"><option value="cash">Kas shift admin</option><option value="transfer">Bank</option></select></label><label><input type="checkbox" name="refund_received"> Saya memastikan sisa uang sudah diterima kembali (wajib bila ada sisa)</label><button>Posting realisasi</button><p class="err" data-error hidden></p></form>
@endforeach
</section>
@endif
<script>
(()=>{for(const f of document.querySelectorAll('.driver-form')){const key=crypto.randomUUID();f.addEventListener('submit',async e=>{e.preventDefault();if(f.dataset.busy||!f.reportValidity())return;f.dataset.busy='1';const b=f.querySelector('button'),error=f.querySelector('[data-error]');b.disabled=true;error.hidden=true;const payload=Object.fromEntries(new FormData(f));if(f.hasAttribute('data-key'))payload.idempotency_key=key;if(f.hasAttribute('data-settlement'))payload.refund_received=f.elements.refund_received.checked;try{const r=await fetch(f.action,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify(payload)});const d=await r.json();if(!r.ok)throw Error(Object.values(d.errors||{}).flat().join(' ')||d.message||'Gagal menyimpan');location.reload();}catch(e){error.textContent=e.message;error.hidden=false;}finally{delete f.dataset.busy;b.disabled=false;}})}})();
</script>
