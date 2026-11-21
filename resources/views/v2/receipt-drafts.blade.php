{{-- Draf terpisah dari dokumen stok: hanya konfirmasi yang memposting stok dan utang. --}}
@php
    $draftQty = function ($value): string {
        [$integer, $fraction] = array_pad(explode('.', (string) $value), 2, '');
        $fraction = rtrim($fraction, '0');
        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $integer).($fraction !== '' ? ','.$fraction : '');
    };
@endphp
<section class="card" id="receipt-drafts" aria-labelledby="h-receipt-drafts">
<h2 id="h-receipt-drafts">Draf penerimaan</h2>
<p class="note">Belum masuk stok atau utang. Periksa dan ubah draf sebelum konfirmasi. Penerimaan yang sudah dikonfirmasi terkunci.</p>
<x-ui.toolbar search="Cari draf, supplier, atau barang" label="Cari draf penerimaan" :chips="['Semua' => 'all', 'Draf' => 'status:draft', 'Dibatalkan' => 'status:cancelled']"/>
<div class="ui-table-wrap scroll" data-ui-table data-ui-noun="draf" tabindex="0" role="region" aria-label="Daftar draf penerimaan">
<table class="ui-table">
<caption class="sr">Draf penerimaan yang belum diposting dan draf yang dibatalkan</caption>
<thead><tr><th scope="col">Draf dan supplier</th><th scope="col">Barang</th><th scope="col" class="ui-num">Total</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead>
<tbody>
@forelse (($receiptDrafts ?? collect()) as $draft)
@php
    $draftNumber = 'DRF-'.str_pad((string) $draft->id, 6, '0', STR_PAD_LEFT);
    $editData = ['id' => $draft->id, 'version' => $draft->version, 'supplier_id' => $draft->supplier_id, 'supplier_name' => $draft->supplier_name, 'receipt_date' => $draft->receipt_date, 'supplier_invoice_number' => $draft->supplier_invoice_number, 'notes' => $draft->notes,
        'items' => array_map(function ($item) use ($draftProducts) {
            $product = $draftProducts->get($item['product_id']);
            return $item + ['name' => $product->name ?? 'Barang tidak tersedia', 'unit' => $product->unit ?? '', 'current_cost' => (string) ($product->cost_price ?? '0'), 'stock' => (string) ($product->stock_quantity ?? '0')];
        }, $draft->items)];
@endphp
<tr data-status="{{ $draft->status }}">
<td data-label="Draf" class="ui-primary">{{ $draftNumber }}<span class="ui-sub">{{ $draft->supplier_name }} · {{ \Carbon\Carbon::parse($draft->receipt_date)->locale('id')->translatedFormat('d M Y') }}</span><span class="ui-sub">{{ $draft->author_name }}@if ($draft->supplier_invoice_number) · Nota {{ $draft->supplier_invoice_number }}@endif</span></td>
<td data-label="Barang" class="rh-lines">@foreach ($editData['items'] as $item)<span>{{ $item['name'] }} · {{ $draftQty($item['quantity']) }} {{ $item['unit'] }}</span>@endforeach</td>
<td data-label="Total" class="ui-num"><strong>{{ \App\Services\V2\Money::display($draft->total_amount) }}</strong></td>
<td data-label="Status">@if ($draft->status === 'draft')<span class="ui-badge ui-badge--warn">Draf</span><span class="ui-sub">Belum masuk stok/utang</span>@else<span class="ui-badge ui-badge--bad">Dibatalkan</span><span class="ui-sub">{{ $draft->cancel_reason }}</span>@endif</td>
<td data-label="Aksi" class="ui-actions">
@if ($draft->status === 'draft')
    <button type="button" class="secondary" data-receipt-edit="{{ json_encode($editData, JSON_THROW_ON_ERROR) }}">Ubah draf</button>
    <form class="v2-json-form receipt-confirm-form" action="{{ route('v2.receipt-drafts.confirm', $draft->id) }}" data-popup-title="Konfirmasi penerimaan">
        <h3 class="sr">{{ $draftNumber }} · {{ $draft->supplier_name }}</h3>
        <p class="note">Konfirmasi menambah stok dan utang supplier. Setelah dikonfirmasi, penerimaan terkunci dan tidak dapat dibatalkan biasa.</p>
        <dl class="popup-summary"><div><dt>Supplier</dt><dd>{{ $draft->supplier_name }}</dd></div><div><dt>Tanggal terima</dt><dd>{{ \Carbon\Carbon::parse($draft->receipt_date)->format('d/m/Y') }}</dd></div><div class="is-total"><dt>Total utang supplier</dt><dd>{{ \App\Services\V2\Money::display($draft->total_amount) }}</dd></div></dl>
        <p class="note">No. nota supplier: <strong>{{ $draft->supplier_invoice_number ?: 'Tidak diisi' }}</strong></p>
        <ul class="rc-review-list">
        @foreach ($editData['items'] as $item)
            <li><strong>{{ $item['name'] }}</strong><span>{{ $draftQty($item['quantity']) }} {{ $item['unit'] }} × {{ \App\Services\V2\Money::display($item['unit_cost']) }}</span></li>
        @endforeach
        </ul>
        <label class="receipt-final-ack"><input type="checkbox" name="confirm_final" value="1" required> <span>Saya sudah memeriksa barang, jumlah, dan harga. Saya memahami penerimaan akan dikunci.</span></label>
        <input type="hidden" name="version" value="{{ $draft->version }}">
        <button type="submit">Konfirmasi dan kunci</button>
        <p data-error class="err" tabindex="-1" hidden></p>
    </form>
    <form class="v2-json-form" action="{{ route('v2.receipt-drafts.cancel', $draft->id) }}" data-popup-title="Batalkan draf penerimaan">
        <h3 class="sr">{{ $draftNumber }} · {{ $draft->supplier_name }}</h3>
        <p class="note">Hanya membatalkan draf ini. Stok, HPP, utang, kas, dan bank tidak berubah. Catatan draf tetap tersimpan.</p>
        <div class="field"><label for="draft-cancel-{{ $draft->id }}">Alasan pembatalan draf</label><input id="draft-cancel-{{ $draft->id }}" name="reason" required minlength="5" maxlength="255" autocomplete="off"></div>
        <input type="hidden" name="version" value="{{ $draft->version }}">
        <button type="submit" class="secondary">Batalkan draf</button>
        <p data-error class="err" tabindex="-1" hidden></p>
    </form>
@else
    <span class="ui-muted">Draf dibatalkan</span>
@endif
</td>
</tr>
@empty
<tr data-ui-empty-static><td colspan="5" class="ui-empty"><strong>Belum ada draf penerimaan.</strong>Simpan draf terlebih dahulu, lalu periksa dan konfirmasi di sini.</td></tr>
@endforelse
</tbody>
</table>
</div>
</section>
