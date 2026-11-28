<p class="note supplier-period-label">Periode: {{ \Illuminate\Support\Carbon::parse($from)->locale('id')->translatedFormat('j M Y') }} – {{ \Illuminate\Support\Carbon::parse($to)->locale('id')->translatedFormat('j M Y') }}</p>
<dl class="insight-facts supplier-summary" aria-label="Ringkasan periode">
    <div><dt>Total tagihan</dt><dd>{{ \App\Services\V2\Money::display($billed) }}</dd></div>
    <div><dt>Pembayaran terverifikasi</dt><dd>{{ \App\Services\V2\Money::display($settled) }}</dd></div>
    <div><dt>Menunggu verifikasi</dt><dd>{{ \App\Services\V2\Money::display($awaiting) }}</dd></div>
</dl>
@if($activity->total())
    <div class="scroll" tabindex="0" role="region" aria-label="Aktivitas supplier">@include('v2.supplier-activity-table', ['entries'=>$activity, 'printing'=>false])</div>
    <div class="supplier-activity-footer">
        <span class="ui-status-line" data-supplier-page-status tabindex="-1">{{ $activity->firstItem() }}–{{ $activity->lastItem() }} dari {{ $activity->total() }} aktivitas.</span>
        @if($activity->hasPages())
            <nav class="supplier-activity-pages" aria-label="Halaman aktivitas supplier">
                @if($activity->previousPageUrl())<a href="{{ $activity->previousPageUrl() }}">Sebelumnya</a>@endif
                <span>Halaman {{ $activity->currentPage() }} dari {{ $activity->lastPage() }}</span>
                @if($activity->nextPageUrl())<a href="{{ $activity->nextPageUrl() }}">Berikutnya</a>@endif
            </nav>
        @endif
    </div>
@else
    <p class="note">Tidak ada tagihan atau pembayaran pada periode ini.</p>
@endif
