@php
    $srMethodLabels = ['credit_adjustment' => 'Potong piutang', 'cash' => 'Laci shift', 'main' => 'Kas Utama', 'transfer' => 'Transfer bank'];
@endphp
<div class="ui-table-wrap scroll" tabindex="0" role="region" aria-label="Riwayat retur penjualan">
<table class="ui-table" id="sale-returns-table">
<caption class="sr">Daftar retur penjualan V2</caption>
<thead><tr><th scope="col">Tanggal</th><th scope="col">Nomor &amp; nota</th><th scope="col">Pembeli</th><th scope="col" class="ui-num">Total retur</th><th scope="col">Penyelesaian</th><th scope="col">Alasan</th></tr></thead>
<tbody>
@forelse(($saleReturns ?? collect()) as $sr)
<tr>
    <td data-label="Tanggal" class="ui-nowrap">{{ \Carbon\Carbon::parse($sr->return_date)->locale('id')->translatedFormat('d M Y') }}</td>
    <td data-label="Nomor" class="ui-primary ui-nowrap">{{ $sr->number }}<span class="ui-sub">{{ $sr->sale_number }} · {{ $sr->author_name ?? 'Admin' }}</span></td>
    <td data-label="Pembeli">{{ $sr->customer_name ?? 'Pelanggan umum' }}</td>
    <td data-label="Total retur" class="ui-num">{{ \App\Services\V2\Money::display($sr->total_amount) }}</td>
    <td data-label="Penyelesaian">{{ $srMethodLabels[$sr->refund_method] ?? $sr->refund_method }}@if(\Brick\Math\BigDecimal::of((string) $sr->refund_amount)->isPositive())<span class="ui-sub">Dana keluar {{ \App\Services\V2\Money::display($sr->refund_amount) }}</span>@endif</td>
    <td data-label="Alasan">{{ $sr->reason }}</td>
</tr>
@empty
<tr data-ui-empty-static><td colspan="6" class="ui-empty"><strong>Tidak ada retur penjualan sesuai saringan.</strong>Klik nama pembeli pada Riwayat penjualan untuk memproses retur barang.</td></tr>
@endforelse
</tbody>
</table>
</div>
@include('v2.tables.pagination', ['paginator' => $saleReturns, 'noun' => 'retur penjualan'])
