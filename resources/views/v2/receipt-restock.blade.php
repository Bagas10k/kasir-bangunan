<section id="receipt-restock" class="rr-panel" aria-labelledby="rr-title" data-url="{{ route('v2.receipts.restock-products') }}" data-link-url="{{ route('v2.receipts.product-supplier') }}">
    <div class="rr-heading"><div><h3 id="rr-title">Perlu restok</h3><p class="note">Pilih barang seperti katalog. Supplier mengikuti barang; jumlah dan HPP diisi sesuai barang yang datang.</p></div><button type="button" class="secondary" data-restock-refresh>Perbarui</button></div>
    <div class="rr-tools">
        <div class="rr-search" role="search"><label class="sr" for="rr-search">Cari kebutuhan restok</label><input type="search" id="rr-search" data-restock-search maxlength="100" autocomplete="off" placeholder="Cari nama atau SKU"><button type="button" class="secondary" data-restock-search-button>Cari</button></div>
    </div>
    <div class="rr-statuses" role="group" aria-label="Kondisi stok">
        <button type="button" class="secondary" data-restock-status="critical" aria-pressed="true">Perlu restok</button>
        <button type="button" class="secondary" data-restock-status="out" aria-pressed="false">Stok habis</button>
        <button type="button" class="secondary" data-restock-status="low" aria-pressed="false">Stok menipis</button>
    </div>
    <p class="note" data-restock-scope>Seluruh toko · satu penerimaan untuk satu supplier.</p>
    <div class="rr-editor" data-restock-editor hidden aria-labelledby="rr-supplier-title">
        <h4 id="rr-supplier-title" data-restock-supplier-title tabindex="-1">Supplier barang</h4>
        <p class="note">Supplier baru dan hubungan ke barang hanya disimpan setelah Anda mengonfirmasi. Pembatalan tidak mengubah isian penerimaan.</p>
        <label for="rr-supplier-choice">Supplier</label>
        <select id="rr-supplier-choice" data-restock-supplier-choice><option value="">Pilih supplier</option></select>
        <div data-restock-new-fields hidden>
            <label for="rr-new-name">Nama supplier baru</label><input id="rr-new-name" type="text" maxlength="255" autocomplete="off" data-restock-new-name>
            <label for="rr-new-notes">Catatan supplier (opsional)</label><input id="rr-new-notes" type="text" maxlength="255" data-restock-new-notes>
        </div>
        <p class="note" role="alert" data-restock-supplier-error hidden></p>
        <div class="rr-editor-actions"><button type="button" class="secondary" data-restock-supplier-cancel>Batal</button><button type="button" data-restock-supplier-save>Gunakan supplier</button></div>
    </div>
    <p class="note" data-restock-message role="status" aria-live="polite">Daftar dimuat saat formulir dibuka.</p>
    <ul class="rr-results" data-restock-results aria-label="Barang yang perlu restok" tabindex="0" data-live-skip></ul>
    <div class="rr-pager"><button type="button" class="secondary" data-restock-prev disabled>Sebelumnya</button><span class="note" data-restock-page>Halaman 1</span><button type="button" class="secondary" data-restock-next disabled>Lanjut</button></div>
</section>
