<p class="note">Tanggal pencatatan: {{ $from }} – {{ $to }}. {{ $activity->total() }} aktivitas.</p>
<dl class="supplier-summary">
<div><dt>Utang berjalan saat ini</dt><dd>{{ \App\Services\V2\Money::display($outstanding) }}</dd></div>
<div><dt>Tagihan dalam rentang</dt><dd>{{ \App\Services\V2\Money::display($billed) }}</dd></div>
<div><dt>Pembayaran terverifikasi dalam rentang</dt><dd>{{ \App\Services\V2\Money::display($settled) }}</dd></div>
<div><dt>Pengajuan menunggu dalam rentang</dt><dd>{{ \App\Services\V2\Money::display($awaiting) }}</dd></div>
</dl>
<div class="scroll" tabindex="0" role="region" aria-label="Aktivitas supplier">@include('v2.supplier-activity-table', ['entries'=>$activity, 'printing'=>false])</div>
<nav class="supplier-activity-pages" aria-label="Halaman aktivitas supplier">
@if($activity->previousPageUrl())<a href="{{ $activity->previousPageUrl() }}">Sebelumnya</a>@endif
<span>Halaman {{ $activity->currentPage() }} dari {{ $activity->lastPage() }}</span>
@if($activity->nextPageUrl())<a href="{{ $activity->nextPageUrl() }}">Lanjut</a>@endif
</nav>
<p class="note">Pengajuan menunggu/ditolak belum mengurangi utang. Tagihan dan pembayaran adalah jenis aktivitas berbeda, bukan dua nilai yang dijumlahkan. Utang berjalan adalah kondisi saat ini, bukan saldo penutupan historis.</p>
