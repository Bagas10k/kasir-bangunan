<template id="item-tpl">
<div class="item-row">
    <select data-name="product_id" data-native-select hidden aria-label="Barang">@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select>
    <div class="cart-product-heading"><div class="cart-product-title"></div><div class="cart-product-meta"></div></div>
    <div class="qty-stepper"><button type="button" data-step="-1" aria-label="Kurangi jumlah">−</button><input data-name="quantity" type="text" inputmode="decimal" value="1" maxlength="30" aria-label="Jumlah barang" required><button type="button" data-step="1" aria-label="Tambah jumlah">+</button></div>
    <strong class="item-subtotal">Rp 0,00</strong><button type="button" class="secondary" data-remove aria-label="Hapus item">×</button>
    <details class="line-discount"><summary>Diskon barang</summary><div class="discount-controls"><label>Jenis<select data-name="discount_type"><option value="">Tanpa diskon</option><option value="amount">Nominal (Rp)</option><option value="percent">Persen (%)</option></select></label><label>Nilai<input data-name="discount_value" inputmode="decimal" maxlength="30" placeholder="0"></label></div></details>
</div>
</template>
