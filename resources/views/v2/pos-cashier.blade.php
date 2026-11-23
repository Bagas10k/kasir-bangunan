@php
    $catalogRupiah = fn ($value) => \App\Services\V2\Money::display($value);
@endphp
<section id="kasir" aria-label="Kasir penjualan">
@include('v2.pos-monitor')
<div class="pos-workspace">
    <div class="pos-catalog-pane"><section class="card pos-catalog-card" aria-labelledby="catalog-heading">
        <div class="pos-catalog-head"><h2 id="catalog-heading">Katalog Produk Material</h2>
        <div class="pos-search-wrapper" role="search"><label for="pos-product-search">Cari produk di katalog</label><div class="pos-catalog-tools"><div class="pos-search-box"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="7.5"/><path d="m16 16 5 5"/></svg><input type="search" id="pos-product-search" placeholder="Ketik minimal 2 huruf nama atau SKU" autocomplete="off" aria-controls="products-tbody"><span class="search-kbd-hint">Pintasan: [/]</span></div>
        <div class="catalog-filter-row"><div><label for="product-category-filter">Kelompok</label><select id="product-category-filter"><option value="">Semua kelompok</option>@foreach(collect($products)->pluck('category')->filter()->unique()->sort() as $category)<option value="{{ $category }}">{{ $category }}</option>@endforeach</select></div><div><label for="product-stock-filter">Kondisi stok</label><select id="product-stock-filter"><option value="all">Semua stok</option><option value="available">Tersedia</option><option value="low">Menipis</option><option value="out">Habis</option></select></div><button type="button" class="secondary" id="catalog-reset">Reset</button></div>
        </div><p id="product-search-status" data-live-skip class="note" aria-live="polite">Pilih produk untuk memasukkannya ke keranjang.</p></div></div>
        <div class="catalog-table-scroll" tabindex="0" role="region" aria-label="Katalog produk"><table id="products-table" data-live-skip class="ui-table"><caption class="sr">Katalog produk aktif</caption><thead><tr><th scope="col">Produk</th><th scope="col" class="ui-num">Stok</th><th scope="col" class="ui-num">Harga</th><th scope="col" class="ui-actions">Aksi</th></tr></thead><tbody id="products-tbody">
        {{-- Baris katalog dirender oleh pos-cashier-script dari data produk (bertahap, 40 per halaman). --}}
        <tr id="catalog-empty" data-motion-item hidden><td colspan="4">@if(collect($products)->isEmpty())Belum ada produk aktif.@else Produk tidak ditemukan.@endif</td></tr></tbody></table></div>
        <nav class="catalog-pager" id="catalog-pager" data-live-skip aria-label="Halaman katalog" hidden><p class="note" id="catalog-range" aria-live="polite"></p><div class="catalog-pager-controls"><button type="button" class="secondary" id="catalog-prev">Sebelumnya</button><span id="catalog-pages" class="catalog-page-list"></span><button type="button" class="secondary" id="catalog-next">Lanjut</button></div></nav>
    </section></div>
    <div class="pos-cart-pane"><section class="card" aria-labelledby="h-checkout"><h2 id="h-checkout">Keranjang</h2>
        <form id="form-checkout" action="{{ route('v2.sales.store') }}" novalidate>
            <!-- Informasi Pembeli / Pelanggan -->
            <div class="pos-buyer-card">
                <div class="pos-buyer-header">
                    <label for="pos-customer-quick" class="pos-buyer-title">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span>Nama Pembeli</span>
                        <span id="pos-buyer-req" class="pos-req-indicator" hidden>(wajib)</span>
                    </label>
                    <span id="pos-buyer-badge" class="pos-buyer-badge badge-general">Pelanggan Umum</span>
                </div>
                <div class="pos-buyer-select-wrapper">
                    <select id="pos-customer-quick" class="pos-customer-select" aria-label="Pilih pelanggan transaksi">
                        <option value="">Pelanggan Umum (Walk-in / Tunai)</option>
                    </select>
                </div>
                <div class="pos-buyer-meta">
                    <span id="pos-buyer-hint">Transaksi langsung kasir (ambil di toko). Penjualan kredit/tempo wajib memilih pelanggan terdaftar.</span>
                </div>
                <div class="pos-tier" role="radiogroup" aria-label="Harga"><span class="pos-tier-label" aria-hidden="true">Harga</span><button type="button" role="radio" data-tier="retail" aria-checked="true">Eceran</button><button type="button" role="radio" data-tier="contractor" aria-checked="false" tabindex="-1">Kontraktor</button></div>
            </div>
            <div class="pos-cart-section"><div class="cart-label"><strong>Keranjang Belanja</strong><span id="cart-item-count" data-live-skip>0 jenis item</span></div><div class="cart-table-scroll"><div class="cart-column-header"><span>Produk</span><span class="cart-column-quantity">Jumlah</span><span class="cart-column-subtotal">Subtotal</span><span class="sr-only">Aksi</span></div><div id="cart-empty-row" data-live-skip class="note cart-empty-message">Keranjang belanja kosong. Cari produk di katalog lalu pilih item untuk dimasukkan ke keranjang.</div><div id="items"></div></div></div>
            <button type="button" id="add-item" hidden>Tambah barang</button>
            <div class="summary-box"><div class="summary-row summary-total"><span>Total Belanja:</span><strong id="display-total" data-live-skip>Rp 0</strong></div><p class="note" id="cart-error" role="alert" tabindex="-1" hidden></p></div>
            <p class="pos-shift-reminder" id="store-shift-reminder" role="status" data-live-skip @if($activeStoreShift) hidden @endif>{{ !empty($todayCashDay) ? 'Belum bisa checkout: kas harian sudah ditutup. Penjualan baru menunggu kas hari berikutnya; keranjang tetap tersimpan.' : ($isAdmin ? 'Belum bisa checkout: kas harian belum dibuka. Buka kas harian melalui menu Kas harian. Keranjang tetap tersimpan.' : 'Belum bisa checkout: kas harian belum dibuka. Minta admin membuka kas harian. Keranjang tetap tersimpan.') }}</p>
            <div class="pos-checkout-action"><button type="button" id="open-payment" class="btn-pill-submit" aria-describedby="store-shift-reminder">Checkout &amp; Pembayaran</button></div>
            <dialog id="payment-modal-overlay" class="input-dialog" aria-labelledby="payment-modal-title">
                <div class="popup-heading"><h2 id="payment-modal-title">Pembayaran</h2><button type="button" class="popup-close secondary" data-close-payment aria-label="Tutup pembayaran">×</button></div>
                <div class="popup-content"><div class="summary-box"><div class="summary-row"><strong>Total Tagihan</strong><strong id="payment-modal-total" data-live-skip>Rp 0</strong></div></div>
                    <!-- Informasi Pembeli di Modal Pembayaran -->
                    <div class="modal-buyer-block">
                        <div class="pos-buyer-header">
                            <label for="customer-id" class="pos-buyer-title">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <span>Informasi Pembeli</span>
                                <span id="customer-required" class="pos-req-indicator"></span>
                            </label>
                            <span id="modal-buyer-badge" class="pos-buyer-badge badge-general">Pelanggan Umum</span>
                        </div>
                        <div class="field" id="customer-field" style="margin-top:4px;">
                            <select id="customer-id" name="customer_id" class="pos-customer-select">
                                <option value="">Pelanggan Umum (Walk-in / Tunai)</option>
                            </select>
                        </div>
                        <div id="modal-credit-warning" class="pos-credit-warning-box" hidden>
                            Perhatian: Transaksi kredit (piutang usaha) wajib memilih pelanggan terdaftar!
                        </div>
                        <p id="modal-buyer-hint" class="note pos-buyer-meta" style="margin-top:6px;">Pelanggan umum hanya untuk pembayaran tunai atau transfer lunas.</p>
                        <div class="pos-tier pos-tier--light" role="radiogroup" aria-label="Tipe pelanggan"><span class="pos-tier-label" aria-hidden="true">Tipe pelanggan</span><button type="button" role="radio" data-tier="retail" aria-checked="true">Eceran</button><button type="button" role="radio" data-tier="contractor" aria-checked="false" tabindex="-1">Kontraktor</button></div>
                    </div>

                    <div class="field pay-method-field"><label id="payment-method-label" for="payment-method">Metode Pembayaran</label><select id="payment-method" name="payment_method" class="pay-method-native" data-native-select tabindex="-1" aria-hidden="true"><option value="cash">Tunai (Cash)</option><option value="transfer">Transfer Bank</option><option value="credit">Kredit (Piutang Usaha)</option></select>
                        <div class="pay-method" role="radiogroup" aria-labelledby="payment-method-label">
                            <button type="button" role="radio" data-value="cash" aria-checked="true">Tunai</button>
                            <button type="button" role="radio" data-value="transfer" aria-checked="false" tabindex="-1">Transfer</button>
                            <button type="button" role="radio" data-value="credit" aria-checked="false" tabindex="-1">Kredit<small>Piutang</small></button>
                        </div>
                        <script>
                        /* Tombol pilihan untuk select metode (select asli tetap dikirim dan tetap memicu skrip kasir lewat event change). */
                        (()=>{const select=document.getElementById('payment-method'),group=select.nextElementSibling,buttons=[...group.querySelectorAll('button')];
                         const sync=()=>{for(const b of buttons){const on=b.dataset.value===select.value;b.setAttribute('aria-checked',String(on));b.tabIndex=on?0:-1;}};
                         for(const b of buttons)b.addEventListener('click',()=>{if(select.value!==b.dataset.value){select.value=b.dataset.value;select.dispatchEvent(new Event('change',{bubbles:true}));}sync();});
                         group.addEventListener('keydown',e=>{if(!['ArrowLeft','ArrowRight'].includes(e.key))return;e.preventDefault();const i=buttons.findIndex(b=>b.dataset.value===select.value),next=buttons[(i+(e.key==='ArrowRight'?1:-1)+buttons.length)%buttons.length];next.click();next.focus();});
                         select.addEventListener('change',sync);select.form?.addEventListener('reset',()=>setTimeout(sync));
                         const dialog=select.closest('dialog');if(dialog)new MutationObserver(sync).observe(dialog,{attributes:true,attributeFilter:['open']});sync();})();
                        </script></div>
                    <div class="field"><label for="paid-amount-display">Nominal Pembayaran (Rp)</label><input id="paid-amount-display" type="text" data-money-display inputmode="decimal" value="" placeholder="0" maxlength="30" required autocomplete="off"><input id="paid-amount" name="paid_amount" type="hidden" value="0"></div>
                    <div class="field" id="deposit-field" hidden><label for="deposit-method">Uang muka (DP) masuk ke</label><select id="deposit-method" name="deposit_method" disabled><option value="cash">Tunai (kas laci)</option><option value="transfer">Transfer (bank)</option></select></div>
                    <div class="field" data-transfer-proof-field hidden><label for="checkout-transfer-proof">Foto bukti transfer</label><input id="checkout-transfer-proof" name="transfer_proof" type="file" accept="image/jpeg,image/png,image/webp" disabled><p class="note">Wajib untuk transfer pelanggan. JPG/PNG/WebP, maksimal 5 MB. Menunggu verifikasi admin.</p></div>
                    <p class="note" id="paid-hint">Tunai berlebih menjadi kembalian.</p>
                    <div class="summary-row"><span>Kembalian:</span><strong id="display-change" data-live-skip>Rp 0</strong></div>
                    <details class="checkout-options"><summary>Diskon nota &amp; pengiriman</summary><div class="row"><div class="field"><label for="inv-disc-type">Diskon nota</label><select id="inv-disc-type" name="discount_type"><option value="amount">Nominal (Rp)</option><option value="percent">Persen (%)</option></select></div><div class="field"><label for="inv-disc-value">Nilai diskon nota</label><input id="inv-disc-value" name="discount_value" inputmode="decimal" maxlength="30" placeholder="0"></div></div><div class="field"><label for="fulfillment">Cara penyerahan</label><select id="fulfillment" name="fulfillment"><option value="pickup">Diambil di toko</option><option value="delivery">Dikirim</option></select></div><div id="delivery-fields" hidden><div class="field"><label for="destination">Tujuan kirim</label><input id="destination" name="destination" maxlength="255" disabled></div><div class="field"><label for="delivery-fee">Ongkir</label><input id="delivery-fee" name="delivery_fee" data-money inputmode="decimal" value="0" maxlength="30" required></div></div></details>
                    <input id="checkout-key" name="idempotency_key" type="hidden" maxlength="100" required>
                    <select id="price-tier" name="price_tier" data-native-select hidden tabindex="-1" aria-hidden="true"><option value="retail">Eceran</option><option value="contractor">Kontraktor</option></select>
                    <p class="err" data-error tabindex="-1" hidden></p><p class="ok" data-ok hidden></p>
                    <div id="checkout-result" aria-live="polite"></div>
                </div>
                <div class="popup-footer"><button type="button" class="secondary" data-close-payment>Kembali ke Keranjang</button><button type="button" id="review-payment">Tinjau Pembayaran</button><button type="submit" id="checkout-submit" hidden>Simpan penjualan</button></div>
            </dialog>
        </form>
    </section>
    </div>
</div>
<dialog id="checkout-confirm-overlay" class="input-dialog" aria-labelledby="checkout-confirm-title"><div class="popup-heading"><h2 id="checkout-confirm-title">Tinjau Pembayaran</h2><button type="button" class="popup-close secondary" data-cancel-review aria-label="Kembali ke pembayaran">×</button></div><div class="popup-content"><div id="checkout-review"></div><p class="note">Belum disimpan. Pastikan barang dan pembayaran benar.</p></div><div class="popup-footer"><button type="button" class="secondary" data-cancel-review>Batal</button><button type="button" id="checkout-confirm-submit">Konfirmasi &amp; Simpan Transaksi</button></div></dialog>
</section>
