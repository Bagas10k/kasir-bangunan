@php
    $costDec = \Brick\Math\BigDecimal::of((string) ($record->cost_price ?? '0'));
    $marginSummary = function ($priceValue) use ($costDec, $rp): string {
        $sellDec = \Brick\Math\BigDecimal::of((string) ($priceValue ?? '0'));
        $diff = $sellDec->minus($costDec);
        $label = $diff->isNegative() ? 'Rugi ' : 'Untung ';
        $amountText = $rp((string) $diff->abs());
        if ($costDec->isPositive()) {
            $pct = $diff->multipliedBy(100)->dividedBy($costDec, 1, \Brick\Math\RoundingMode::HalfUp);
            $pctText = str_replace('.', ',', rtrim(rtrim((string) $pct, '0'), '.'));
            return $label.$amountText.' ('.$pctText.'%)';
        }
        return $label.$amountText;
    };
    $retailMargin = $marginSummary($record->selling_price ?? '0');
    $contractorMargin = ($record->contractor_price ?? null) !== null ? $marginSummary($record->contractor_price) : null;
@endphp
<dialog class="input-dialog uf-dialog uf-dialog-wide" id="popup-detail-product-{{ $record->id }}" aria-labelledby="title-detail-product-{{ $record->id }}">
    <div class="popup-heading">
        <div><h2 id="title-detail-product-{{ $record->id }}">Detail produk</h2><p class="uf-dialog-sub">{{ $record->name }} · SKU {{ $record->sku }}</p></div>
        <button type="button" class="popup-close secondary" data-close-dialog aria-label="Tutup detail produk">×</button>
    </div>
    <div class="popup-content detail-content">
        <section class="detail-section">
        <h3>Identitas dan stok</h3>
        <dl class="detail-facts">
            <div><dt>Kategori</dt><dd>{{ $record->category ?: '—' }}</dd></div>
            <div><dt>Status</dt><dd><x-ui.status :status="$isActive ? 'active' : 'archived'"/></dd></div>
            <div><dt>Stok saat ini</dt><dd>{{ $decimalText($record->stock_quantity) }} {{ $record->unit }}</dd></div>
            <div><dt>Stok minimum</dt><dd>{{ $decimalText($record->min_stock ?? '0') }} {{ $record->unit }}</dd></div>
            <div><dt>Kondisi stok</dt><dd>{{ ['out' => 'Habis', 'low' => 'Menipis', 'ok' => 'Tersedia'][$stock] }}</dd></div>
        </dl>
        </section>
        <section class="detail-section">
        <h3>Harga dan margin</h3>
        <dl class="detail-facts">
            <div><dt>HPP / modal per {{ $record->unit }} <button type="button" class="ui-info-btn" data-tip-key="harga_modal"></button></dt><dd>Rp {{ $decimalText($record->cost_price ?? '0') }}</dd></div>
            <div><dt>Harga jual per {{ $record->unit }}</dt><dd>{{ $rp($record->selling_price) }}</dd></div>
            <div><dt>Harga kontraktor per {{ $record->unit }} <button type="button" class="ui-info-btn" data-tip-key="harga_kontraktor"></button></dt><dd>{{ $rp($record->contractor_price ?? $record->selling_price) }}@if(($record->contractor_price ?? null) === null)<span class="ui-sub">Mengikuti harga jual</span>@endif</dd></div>
            <div><dt>Margin keuntungan <button type="button" class="ui-info-btn" data-tip-key="margin_keuntungan"></button></dt><dd>{{ $retailMargin }}@if($contractorMargin)<span class="ui-sub">Kontraktor: {{ $contractorMargin }}</span>@endif</dd></div>
        </dl>
        @if(in_array((int) $record->id, $lockedCostIds, true))
            <p class="note">HPP mengikuti pembukuan persediaan dan tidak dapat diubah langsung karena produk sudah memiliki stok atau riwayat mutasi.</p>
        @endif
        </section>
        <section class="detail-section">
        <h3>Supplier barang</h3>
        <ul class="product-detail-suppliers">
            @forelse($productSupplierLinks[$record->id] ?? [] as $link)
                <li>{{ $link->name }}@if($link->is_primary) <span class="ui-muted">(utama)</span>@endif @unless($link->is_active)<span class="ui-muted">(diarsipkan)</span>@endunless</li>
            @empty
                <li>Belum ditautkan ke supplier.</li>
            @endforelse
        </ul>
        </section>
    </div>
    <div class="popup-footer"><button type="button" class="secondary" data-close-dialog>Tutup</button></div>
</dialog>
