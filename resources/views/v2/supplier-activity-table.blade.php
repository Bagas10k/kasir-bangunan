@php $methodLabels = ['cash'=>'Kas harian','transfer'=>'Bank','main'=>'Kas utama']; $stateLabels = ['open'=>'Belum lunas','paid'=>'Lunas','posted'=>'Terverifikasi','pending'=>'Menunggu verifikasi','proposed'=>'Diajukan','rejected'=>'Ditolak','void'=>'Dibatalkan']; @endphp
<table class="ui-table supplier-activity-table">
<caption class="sr">Aktivitas {{ $supplier->name }} berdasarkan tanggal pencatatan</caption>
<thead><tr><th scope="col">Dicatat</th><th scope="col">Aktivitas / tagihan</th><th scope="col" class="ui-num">Nominal</th><th scope="col">Status</th>@unless($printing ?? false)<th scope="col">Dokumen</th>@endunless</tr></thead>
<tbody>
@forelse($entries as $entry)
<tr>
<td data-label="Dicatat">{{ \Illuminate\Support\Carbon::parse($entry['date'])->locale('id')->translatedFormat('j M Y · H:i') }}</td>
<td data-label="Aktivitas / tagihan"><strong>{{ $entry['label'] }}</strong><span class="ui-sub">{{ $entry['number'] }}</span>@if($entry['method'])<span class="ui-sub">{{ $methodLabels[$entry['method']] ?? $entry['method'] }}</span>@endif @if($entry['reference'])<span class="ui-sub">{{ $entry['reference'] }}</span>@endif</td>
<td data-label="Nominal" class="ui-num">{{ \App\Services\V2\Money::display($entry['amount']) }}</td>
<td data-label="Status"><x-ui.status :status="$entry['status']" :label="$stateLabels[$entry['status']] ?? $entry['status']"/></td>
@unless($printing ?? false)<td data-label="Dokumen"><a class="supplier-print-link" target="_blank" rel="noopener" href="{{ route('v2.suppliers.statement', ['id'=>$supplier->id,'from'=>$from,'to'=>$to,'entry_type'=>$entry['type'],'entry_id'=>$entry['id']]) }}">Cetak transaksi</a></td>@endunless
</tr>
@empty
<tr><td colspan="{{ ($printing ?? false) ? 4 : 5 }}">Belum ada aktivitas pada rentang ini.</td></tr>
@endforelse
</tbody></table>
