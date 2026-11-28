@php
    $returnMethodLabels = ['payable_adjustment' => 'Potong utang', 'cash' => 'Laci shift', 'main' => 'Kas Utama', 'transfer' => 'Transfer bank'];
@endphp
<div class="ui-table-wrap scroll" tabindex="0" role="region" aria-label="Daftar retur pembelian">
    <table class="ui-table" id="purchase-returns-table">
        <caption class="sr">Daftar retur pembelian barang V2</caption>
        <thead><tr><th scope="col">Tanggal</th><th scope="col">Nomor &amp; penerimaan</th><th scope="col">Supplier</th><th scope="col" class="ui-num">Total retur</th><th scope="col">Penyelesaian</th><th scope="col">Alasan</th></tr></thead>
        <tbody>
        @forelse(($purchaseReturns ?? collect()) as $ret)
            <tr>
                <td data-label="Tanggal" class="ui-nowrap">{{ \Carbon\Carbon::parse($ret->return_date)->locale('id')->translatedFormat('d M Y') }}</td>
                <td data-label="Nomor" class="ui-primary ui-nowrap">{{ $ret->number }}<span class="ui-sub">{{ $ret->receipt_number ?: '#'.$ret->receipt_id }} · {{ $ret->author_name ?? 'Admin' }}</span></td>
                <td data-label="Supplier">{{ $ret->supplier_name ?? '—' }}</td>
                <td data-label="Total retur" class="ui-num">{{ \App\Services\V2\Money::display($ret->total_amount) }}</td>
                <td data-label="Penyelesaian">{{ $returnMethodLabels[$ret->refund_method] ?? $ret->refund_method }}@if(\Brick\Math\BigDecimal::of((string) $ret->refund_amount)->isPositive())<span class="ui-sub">Dana kembali {{ \App\Services\V2\Money::display($ret->refund_amount) }}</span>@endif</td>
                <td data-label="Alasan">{{ $ret->reason }}</td>
            </tr>
        @empty
            <tr data-ui-empty-static><td colspan="6" class="ui-empty"><strong>Tidak ada retur pembelian sesuai saringan.</strong>Gunakan tombol Retur pada baris Riwayat penerimaan bila ada barang dikembalikan ke supplier.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@include('v2.tables.pagination', ['paginator' => $purchaseReturns, 'noun' => 'retur pembelian'])
