@php
    $catalogRupiah = function ($value) {
        $s = (string) \Brick\Math\BigDecimal::of((string)$value)->toScale(2, \Brick\Math\RoundingMode::HalfUp);
        [$integer, $fraction] = explode('.', $s);
        return 'Rp '.preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $integer).','.$fraction;
    };
@endphp
<section id="kasir" aria-label="Kasir penjualan">
@include('v2.pos-monitor')
<div class="pos-workspace">
    <div class="pos-catalog-pane"><section class="card pos-catalog-card" aria-labelledby="catalog-heading">
        <h2 id="catalog-heading">Katalog Produk Material</h2>
        <div class="pos-search-wrapper" role="search"><label for="pos-product-search">Cari produk di katalog</label><div class="pos-search-box"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="7.5"/><path d="m16 16 5 5"/></svg><input type="search" id="pos-product-search" placeholder="Ketik minimal 2 huruf nama atau SKU" autocomplete="off" aria-controls="products-tbody"><span class="search-kbd-hint">Pintasan: [/]</span></div><p id="product-search-status" class="note" aria-live="polite">Pilih produk untuk memasukkannya ke keranjang.</p></div>
        <div class="catalog-filter-row"><div><label for="product-category-filter">Kelompok</label><select id="product-category-filter"><option value="">Semua kelompok</option>@foreach(collect($products)->pluck('category')->filter()->unique()->sort() as $category)<option value="{{ $category }}">{{ $category }}</option>@endforeach</select></div><div><label for="product-stock-filter">Kondisi stok</label><select id="product-stock-filter"><option value="all">Semua stok</option><option value="available">Tersedia</option><option value="low">Menipis</option><option value="out">Habis</option></select></div><button type="button" class="secondary" id="catalog-reset">Reset</button></div>
        <div class="catalog-table-scroll" tabindex="0" role="region" aria-label="Katalog produk"><table id="products-table"><thead><tr><th scope="col">SKU</th><th scope="col">Nama</th><th scope="col">Kelompok</th><th scope="col">Stok</th><th scope="col">Harga</th><th scope="col">Aksi</th></tr></thead><tbody id="products-tbody">
        @forelse($products as $product)<tr data-product="{{ $product->id }}"><td><span class="sku-code">{{ $product->sku ?? '' }}</span></td><td>{{ $product->name }}</td><td><span class="stock-badge">{{ $product->category ?: 'Umum' }}</span></td><td><span class="stock-badge in-stock">{{ str_contains((string)$product->stock_quantity,'.') ? rtrim(rtrim((string)$product->stock_quantity,'0'),'.') : (string)$product->stock_quantity }} {{ $product->unit }}</span></td><td class="money">{{ $catalogRupiah($product->selling_price) }}</td><td><button type="button" class="btn-table-action" data-add-product="{{ $product->id }}">+ Pilih</button></td></tr>@empty<tr><td colspan="6">Belum ada produk aktif.</td></tr>@endforelse
        <tr id="catalog-empty" hidden><td colspan="6">Produk tidak ditemukan.</td></tr></tbody></table></div>
    </section></div>
    <div class="pos-cart-pane"><section class="card" aria-labelledby="h-checkout"><h2 id="h-checkout">Kasir</h2>
        <form id="form-checkout" action="{{ route('v2.sales.store') }}" novalidate>
            <div class="pos-cart-section"><div class="cart-label"><strong>Keranjang Belanja</strong><span id="cart-item-count">0 jenis item</span></div><div class="cart-table-scroll"><div id="cart-empty-row" class="note cart-empty-message">Keranjang belanja kosong. Cari produk di katalog lalu pilih item untuk dimasukkan ke keranjang.</div><div id="items"></div></div></div>
            <button type="button" id="add-item" hidden>Tambah barang</button>
            <div class="summary-box"><div class="summary-row summary-total"><span>Total Belanja:</span><strong id="display-total">Rp 0,00</strong></div><p class="note" id="cart-error" role="alert" hidden></p></div>
            @if(!$ownOpenShift)<p class="note">Buka shift sebelum menyimpan penjualan. <a href="#shift" data-task-link="shift">Buka shift</a></p>@endif
            <div class="pos-checkout-action"><button type="button" id="open-payment" class="btn-pill-submit">Checkout &amp; Pembayaran</button></div>
            <dialog id="payment-modal-overlay" class="input-dialog" aria-labelledby="payment-modal-title">
                <div class="popup-heading"><h2 id="payment-modal-title">Pembayaran</h2><button type="button" class="popup-close secondary" data-close-payment aria-label="Tutup pembayaran">×</button></div>
                <div class="popup-content"><div class="summary-box"><div class="summary-row"><strong>Total Tagihan</strong><strong id="payment-modal-total">Rp 0,00</strong></div></div>
                    <div class="row"><div class="field"><label for="payment-method">Metode Pembayaran</label><select id="payment-method" name="payment_method"><option value="cash">Tunai (Cash)</option><option value="transfer">Transfer Bank</option><option value="credit">Kredit (Piutang Usaha)</option></select></div><div class="field" id="customer-field"><label for="customer-id">Pelanggan <span id="customer-required"></span></label><select id="customer-id" name="customer_id"><option value="">Tanpa pelanggan</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select></div></div>
                    <div class="field"><label for="paid-amount-display">Nominal Pembayaran (Rp)</label><input id="paid-amount-display" type="text" inputmode="decimal" value="0,00" maxlength="30" required autocomplete="off"><input id="paid-amount" name="paid_amount" type="hidden" value="0"></div>
                    <div class="field" id="deposit-field" hidden><label for="deposit-method">Uang muka (DP) masuk ke</label><select id="deposit-method" name="deposit_method" disabled><option value="cash">Tunai (kas laci)</option><option value="transfer">Transfer (bank)</option></select></div>
                    <p class="note" id="paid-hint">Tunai berlebih menjadi kembalian.</p>
                    <div class="summary-row"><span>Kembalian:</span><strong id="display-change">Rp 0,00</strong></div>
                    <details class="checkout-options"><summary>Diskon nota &amp; pengiriman</summary><div class="row"><div class="field"><label for="inv-disc-type">Diskon nota</label><select id="inv-disc-type" name="discount_type"><option value="amount">Nominal (Rp)</option><option value="percent">Persen (%)</option></select></div><div class="field"><label for="inv-disc-value">Nilai diskon nota</label><input id="inv-disc-value" name="discount_value" inputmode="decimal" maxlength="30" placeholder="0"></div></div><div class="field"><label for="fulfillment">Cara penyerahan</label><select id="fulfillment" name="fulfillment"><option value="pickup">Diambil di toko</option><option value="delivery">Dikirim</option></select></div><div id="delivery-fields" hidden><div class="field"><label for="destination">Tujuan kirim</label><input id="destination" name="destination" maxlength="255" disabled></div><div class="field"><label for="delivery-fee">Ongkir</label><input id="delivery-fee" name="delivery_fee" inputmode="decimal" value="0" maxlength="30" required></div></div></details>
                    <input id="checkout-key" name="idempotency_key" type="hidden" maxlength="100" required>
                    <p class="err" data-error tabindex="-1" hidden></p><p class="ok" data-ok hidden></p>
                    <div id="checkout-result" aria-live="polite"></div>
                </div>
                <div class="popup-footer"><button type="button" class="secondary" data-close-payment>Kembali ke Keranjang</button><button type="button" id="review-payment">Tinjau Pembayaran</button><button type="submit" id="checkout-submit" hidden>Simpan penjualan</button></div>
            </dialog>
        </form>
    </section></div>
</div>
<dialog id="checkout-confirm-overlay" class="input-dialog" aria-labelledby="checkout-confirm-title"><div class="popup-heading"><h2 id="checkout-confirm-title">Tinjau Pembayaran</h2><button type="button" class="popup-close secondary" data-cancel-review aria-label="Kembali ke pembayaran">×</button></div><div class="popup-content"><div id="checkout-review"></div><p class="note">Belum disimpan. Pastikan barang dan pembayaran benar.</p></div><div class="popup-footer"><button type="button" class="secondary" data-cancel-review>Batal</button><button type="button" id="checkout-confirm-submit">Konfirmasi &amp; Simpan Transaksi</button></div></dialog>
</section>
