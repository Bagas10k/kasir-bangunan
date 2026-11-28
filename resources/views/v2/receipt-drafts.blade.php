{{-- Draf terpisah dari dokumen stok: hanya konfirmasi yang memposting stok dan utang. --}}
<section class="card" id="receipt-drafts" aria-labelledby="h-receipt-drafts">
<h2 id="h-receipt-drafts">Draf penerimaan</h2>
<p class="note">Belum masuk stok atau utang. Periksa dan ubah draf sebelum konfirmasi. Penerimaan yang sudah dikonfirmasi terkunci.</p>
<x-ui.toolbar search="Cari draf, supplier, atau barang" label="Cari draf penerimaan" :chips="['Semua' => 'all', 'Draf' => 'status:draft', 'Dibatalkan' => 'status:cancelled']"/>
<div data-server-table="receipt-drafts" data-table-url="{{ route('v2.tables.show', 'receipt-drafts') }}" data-table-default-filter="all" data-live-skip aria-live="polite">@include('v2.tables.receipt-drafts-results')</div>
</section>
