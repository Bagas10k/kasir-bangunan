<p class="note" data-history-scope>Transaksi {{ $historyFilters['start_date'] !== '' && $historyFilters['start_date'] === $historyFilters['end_date'] ? \Illuminate\Support\Carbon::parse($historyFilters['start_date'])->locale('id')->translatedFormat('j M Y') : ($historyFilters['start_date'] !== '' || $historyFilters['end_date'] !== '' ? 'sesuai rentang tanggal' : 'seluruh tanggal') }} · semua petugas</p>
@if($historySales->isEmpty())
<p class="note" id="history-empty">Belum ada transaksi pada tanggal ini.</p>
@else
<div class="scroll" tabindex="0" role="region" aria-label="Riwayat penjualan"><table id="history-table" class="ui-table"><caption class="sr">Transaksi sesuai tanggal dan filter, mencakup semua petugas</caption><thead><tr><th scope="col">Nomor nota</th><th scope="col">Pembeli</th><th scope="col">Pembayaran</th><th scope="col" class="ui-num">Total</th><th scope="col" class="ui-num">Sisa tagihan</th><th scope="col">Status</th><th scope="col" class="ui-actions">Dokumen</th></tr></thead><tbody>
@foreach($historySales as $historySale)
@php
    $outstanding = $historySale->invoice_amount !== null ? \Brick\Math\BigDecimal::of((string) $historySale->invoice_amount)->minus((string) $historySale->invoice_paid) : \Brick\Math\BigDecimal::of($historySale->status === 'paid' ? '0' : (string) $historySale->total);
    $settlement = $outstanding->isLessThanOrEqualTo(0) ? 'paid' : ($historySale->invoice_paid !== null && \Brick\Math\BigDecimal::of((string) $historySale->invoice_paid)->isPositive() ? 'partial' : $historySale->status);
@endphp
<tr data-history-sale="{{ $historySale->id }}">
    <td data-label="Nomor nota" class="history-reference">
        <strong class="history-number">{{ $historySale->number }}</strong>
        <time class="ui-sub" datetime="{{ \Illuminate\Support\Carbon::parse($historySale->created_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($historySale->created_at)->locale('id')->translatedFormat('j M Y · H:i') }}</time>
        <span class="sr" data-cash-day="{{ $historySale->cash_day ?? '' }}">{{ isset($historySale->cash_day) ? 'Kas harian '.$historySale->cash_day : 'Tanpa catatan laci' }}</span>
    </td>
    <td data-label="Pembeli" class="history-buyer">
        <button type="button" class="secondary" data-sale-detail="{{ $historySale->id }}" aria-label="Detail nota {{ $historySale->number }} — {{ $historySale->customer_name ?? 'Pelanggan umum' }}">{{ $historySale->customer_name ?? 'Pelanggan umum' }}</button>
        <span class="ui-sub" data-history-actor>Petugas: {{ $historySale->user_name ?? '—' }}</span>
    </td>
    <td data-label="Pembayaran">{{ ['cash'=>'Tunai','transfer'=>'Transfer','credit'=>'Kredit'][$historySale->payment_method] ?? $historySale->payment_method }}</td>
    <td data-label="Total" class="money ui-num">{{ \App\Services\V2\Money::display($historySale->total) }}</td>
    <td data-label="Sisa tagihan" class="ui-num">{{ \App\Services\V2\Money::display((string) $outstanding) }}</td>
    <td data-label="Status"><x-ui.status :status="$settlement"/>@if($historySale->pending_count)<span class="ui-sub">Menunggu verifikasi</span>@endif</td>
    <td data-label="Dokumen" class="ui-actions"><span class="doc-links"><a href="{{ route('v2.documents',['id'=>$historySale->id,'kind'=>'receipt']) }}">Lihat nota</a>@if($historySale->has_delivery)<a href="{{ route('v2.documents',['id'=>$historySale->id,'kind'=>'delivery']) }}">Surat jalan</a>@endif</span></td>
</tr>
@endforeach
</tbody></table></div>
@endif
<div class="pagination" aria-label="Halaman riwayat"><p class="pagination-meta">Menampilkan {{ $historySales->firstItem() ?? 0 }}–{{ $historySales->lastItem() ?? 0 }} dari {{ $historySales->total() }} transaksi sesuai filter</p><div class="pagination-controls">@if($historySales->onFirstPage())<span class="page-btn disabled" aria-disabled="true">Sebelumnya</span>@else<a class="page-btn" href="{{ $historySales->previousPageUrl() }}">Sebelumnya</a>@endif@php $pageFrom = max(1, min($historySales->currentPage() - 1, $historySales->lastPage() - 2)); $pageTo = min($historySales->lastPage(), $pageFrom + 2); @endphp<span class="page-list">@for($pageNo = $pageFrom; $pageNo <= $pageTo; $pageNo++)@if($pageNo === $historySales->currentPage())<span class="page-btn active" aria-current="page">{{ $pageNo }}</span>@else<a class="page-btn" href="{{ $historySales->url($pageNo) }}">{{ $pageNo }}</a>@endif @endfor</span>@if($historySales->hasMorePages())<a class="page-btn" href="{{ $historySales->nextPageUrl() }}">Lanjut</a>@else<span class="page-btn disabled" aria-disabled="true">Lanjut</span>@endif</div></div>
