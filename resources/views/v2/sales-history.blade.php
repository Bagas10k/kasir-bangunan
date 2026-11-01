<section class="card pos-recent-card pos-history-full" aria-labelledby="h-sales">
<h2 id="h-sales">Riwayat Transaksi</h2>
<form method="get" action="{{ route('v2.index') }}#kasir" class="sales-filter-grid" id="history-filter-form">
    <div class="field"><label for="sales-search">Cari invoice</label><input id="sales-search" name="sale_search" type="search" class="form-control" maxlength="100" value="{{ $historyFilters['sale_search'] }}" placeholder="Nomor invoice"></div>
    <div class="field"><label for="sales-method">Pembayaran</label><select id="sales-method" name="sale_method" class="form-control"><option value="">Semua metode</option>@foreach(['cash'=>'Tunai','transfer'=>'Transfer','credit'=>'Kredit'] as $value=>$label)<option value="{{ $value }}" @selected($historyFilters['sale_method']===$value)>{{ $label }}</option>@endforeach</select></div>
    <div class="field"><label for="sales-start">Dari tanggal</label><input id="sales-start" name="start_date" type="date" class="form-control" value="{{ $historyFilters['start_date'] }}"></div>
    <div class="field"><label for="sales-end">Sampai tanggal</label><input id="sales-end" name="end_date" type="date" class="form-control" value="{{ $historyFilters['end_date'] }}"></div>
    <div class="field"><label for="sales-perpage">Baris per halaman</label><select id="sales-perpage" name="history_per_page" class="form-control">@foreach([10,25,50] as $perPage)<option value="{{ $perPage }}" @selected($historyFilters['history_per_page']===$perPage)>{{ $perPage }} baris</option>@endforeach</select></div>
    @if(request()->filled('date'))<input type="hidden" name="date" value="{{ $reportDate }}">@endif
    @if(request()->filled('master_kind'))<input type="hidden" name="master_kind" value="{{ $masterKind }}">@endif
    <div class="sales-filter-actions"><button type="submit" class="btn-table-action">Terapkan</button><a id="history-reset" class="btn-table-action" href="{{ route('v2.index', request()->only(['date','master_kind'])) }}#kasir">Reset</a></div>
</form>
@if($historySales->isEmpty())
<p class="note" id="history-empty">Tidak ada transaksi yang sesuai filter. Ubah pilihan atau tekan Reset.</p>
@else
<div class="scroll" tabindex="0" role="region" aria-label="Riwayat penjualan"><table id="history-table"><caption class="sr">Transaksi sesuai filter dan hak akses akun</caption><thead><tr><th scope="col">Invoice</th><th scope="col">Tanggal</th><th scope="col">Metode</th><th scope="col">Total</th><th scope="col">Status</th><th scope="col">Dokumen</th></tr></thead><tbody>
@foreach($historySales as $historySale)
<tr data-history-sale="{{ $historySale->id }}"><td><span class="sku-code">{{ $historySale->number }}</span></td><td>{{ \Illuminate\Support\Carbon::parse($historySale->created_at)->locale('id')->translatedFormat('d M Y') }}</td><td>{{ $methodLabel[$historySale->payment_method] ?? $historySale->payment_method }}</td><td class="money">{{ $rp($historySale->total) }}</td><td>{{ $statusLabel[$historySale->status] ?? $historySale->status }}</td><td><span class="doc-links"><a href="{{ route('v2.documents',['id'=>$historySale->id,'kind'=>'receipt']) }}">Nota</a>@if($historySale->has_delivery)<a href="{{ route('v2.documents',['id'=>$historySale->id,'kind'=>'delivery']) }}">Surat jalan</a>@endif</span></td></tr>
@endforeach
</tbody></table></div>
@endif
<div class="pagination" aria-label="Halaman riwayat"><p class="pagination-meta">Menampilkan {{ $historySales->firstItem() ?? 0 }}–{{ $historySales->lastItem() ?? 0 }} dari {{ $historySales->total() }} transaksi sesuai filter</p><div class="pagination-controls">@if($historySales->onFirstPage())<span class="page-btn disabled" aria-disabled="true">Sebelumnya</span>@else<a class="page-btn" href="{{ $historySales->previousPageUrl() }}">Sebelumnya</a>@endif<span class="page-btn active">{{ $historySales->currentPage() }} / {{ $historySales->lastPage() }}</span>@if($historySales->hasMorePages())<a class="page-btn" href="{{ $historySales->nextPageUrl() }}">Berikutnya</a>@else<span class="page-btn disabled" aria-disabled="true">Berikutnya</span>@endif</div></div>
</section>
