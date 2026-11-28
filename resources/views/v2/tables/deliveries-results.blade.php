<div class="ui-table-wrap scroll" tabindex="0" role="region" aria-label="Daftar pengiriman">
<table class="ui-table">
<caption class="sr">Daftar pengiriman</caption>
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
<a class="btn-action-pill" href="{{ route('v2.documents', ['id' => $delivery->sale_id, 'kind' => 'delivery']) }}" target="_blank" rel="noopener">Surat jalan</a>
</td>
</tr>
@empty
<tr data-ui-empty-static><td colspan="5" class="ui-empty"><strong>Tidak ada pengiriman sesuai saringan.</strong>Penjualan dengan pengiriman akan menghasilkan surat jalan di sini.</td></tr>
@endforelse
</tbody>
</table>
</div>
@include('v2.tables.pagination', ['paginator' => $deliveries, 'noun' => 'pengiriman'])
