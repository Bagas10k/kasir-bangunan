@php
    $audit = ($receiptFinalAudit ?? collect())->get($receipt->id);
@endphp
<dialog class="input-dialog uf-dialog uf-dialog-wide" id="receipt-detail-{{ $receipt->id }}" aria-labelledby="receipt-detail-title-{{ $receipt->id }}">
    <div class="popup-heading">
        <div><h2 id="receipt-detail-title-{{ $receipt->id }}">Detail penerimaan</h2><p class="uf-dialog-sub">{{ $receipt->receipt_number ?: '#'.$receipt->id }}</p></div>
        <button type="button" class="popup-close secondary" data-close-dialog aria-label="Tutup detail penerimaan">×</button>
    </div>
    <div class="popup-content detail-content">
        <section class="detail-section">
            <h3>Ringkasan penerimaan</h3>
            <dl class="detail-facts">
                <div><dt>Pemasok</dt><dd>{{ $receipt->supplier_name ?: '—' }}</dd></div>
                <div><dt>Status</dt><dd>{{ $voided ? 'Dibatalkan' : 'Dikonfirmasi · terkunci' }}</dd></div>
                <div><dt>Total nilai barang</dt><dd>{{ \App\Services\V2\Money::display($receipt->total_amount) }}</dd></div>
                <div><dt>Ongkir terpisah</dt><dd>{{ \App\Services\V2\Money::display($receipt->freight_amount ?? '0') }}</dd></div>
                @if($audit)<div><dt>Dikonfirmasi oleh</dt><dd>{{ $audit->confirmed_by_name }} · {{ \Carbon\Carbon::parse($audit->confirmed_at)->format('d/m/Y H:i') }}</dd></div>@endif
            </dl>
        </section>
        <section class="detail-section">
            <h3>Barang diterima · {{ $lines->count() }} jenis</h3>
            @if($lines->isEmpty())
                <p class="note">Tidak ada rincian barang tersimpan.</p>
            @else
                <div class="ui-table-wrap" tabindex="0" role="region" aria-label="Barang yang diterima">
                    <table class="ui-table">
                        <thead><tr><th>Produk</th><th class="ui-num">Jumlah</th><th class="ui-num">HPP satuan</th></tr></thead>
                        <tbody>@foreach($lines as $line)<tr><td class="ui-primary" data-label="Produk">{{ $line->product_name }}</td><td class="ui-num" data-label="Jumlah">{{ $qty($line->quantity) }} {{ $line->product_unit }}</td><td class="ui-num" data-label="HPP satuan">{{ \App\Services\V2\Money::display($line->unit_cost) }}</td></tr>@endforeach</tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</dialog>
