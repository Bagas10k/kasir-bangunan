@extends('layouts.app')

@php
    $formatDecimal = function ($value, int $scale, bool $trim = false): string {
        $decimal = \Brick\Math\BigDecimal::of((string) $value)->toScale($scale, \Brick\Math\RoundingMode::HalfUp);
        $negative = $decimal->isNegative();
        $raw = ltrim((string) $decimal->abs(), '+-');
        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole);
        if ($trim) {
            $fraction = rtrim($fraction, '0');
        }
        $formatted = $fraction === '' ? $whole : $whole . ',' . $fraction;
        return ($negative ? '-' : '') . $formatted;
    };
@endphp

@section('content')
@include('partials.master-data-styles')
<style>
/* --- POS VISUAL THEME SCOPED OVERRIDES FOR MASTER PRODUK --- */
.product-pos-theme {
    gap: 16px;
}

.product-pos-theme .card,
.product-pos-theme.master-page .card {
    background: #ffffff;
    border: 1px solid var(--border-card, #e5e7eb);
    border-radius: 14px;
    box-shadow: 0 4px 16px rgba(27, 67, 50, 0.04);
}

.product-pos-theme .card:hover {
    box-shadow: 0 4px 16px rgba(27, 67, 50, 0.04);
    transform: none;
}

/* Header */
.product-pos-theme .master-head {
    border-radius: 14px;
    border: 1px solid var(--border-card, #e5e7eb);
    background: #ffffff;
    box-shadow: 0 4px 16px rgba(27, 67, 50, 0.04);
    padding: 18px 20px;
}

.product-pos-theme .master-eyebrow {
    color: var(--primary, #2d6a4f);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.product-pos-theme .master-head h1 {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-main, #1c2826);
    letter-spacing: -0.01em;
}

.product-pos-theme .master-head p {
    color: var(--text-muted, #5c6b64);
    font-size: 13px;
    line-height: 1.5;
}

/* Buttons */
.product-pos-theme .btn,
.product-pos-theme .master-head .btn,
.product-pos-theme .master-toolbar-actions .btn {
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 600;
    padding: 6px 16px;
    min-height: 32px;
    background: var(--primary, #2d6a4f);
    color: #ffffff;
    border: 1px solid var(--primary, #2d6a4f);
    box-shadow: 0 2px 6px rgba(45, 106, 79, 0.15);
    transition: background-color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
}

.product-pos-theme .btn:hover,
.product-pos-theme .master-head .btn:hover,
.product-pos-theme .master-toolbar-actions .btn:hover {
    background: var(--primary-hover, #1b4332);
    border-color: var(--primary-hover, #1b4332);
    box-shadow: 0 3px 8px rgba(45, 106, 79, 0.22);
    transform: translateY(-1px);
}

.product-pos-theme .btn-secondary,
.product-pos-theme .master-toolbar-actions .btn-secondary {
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 600;
    padding: 6px 14px;
    min-height: 32px;
    background: var(--mint-surface, #e8f5e9);
    color: var(--primary-hover, #1b4332);
    border: 1px solid var(--mint-border, #a3b18a);
    box-shadow: none;
}

.product-pos-theme .btn-secondary:hover,
.product-pos-theme .master-toolbar-actions .btn-secondary:hover {
    background: var(--primary-light, #d8f3dc);
    border-color: var(--accent, #52b788);
    box-shadow: none;
    transform: translateY(-1px);
}

/* Summary Strip */
.product-pos-theme .master-summary-strip {
    border-radius: 14px;
    border: 1px solid var(--border-card, #e5e7eb);
    background: #ffffff;
    box-shadow: 0 3px 10px rgba(27, 67, 50, 0.04);
    padding: 14px 18px;
}

.product-pos-theme .master-summary-label {
    color: var(--text-caption, #526359);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.product-pos-theme .master-summary-value {
    color: var(--text-main, #1c2826);
    font-size: 20px;
    font-weight: 750;
}

/* Workspace & Toolbar */
.product-pos-theme .master-workspace {
    border-radius: 14px;
    border: 1px solid var(--border-card, #e5e7eb);
    background: #ffffff;
    box-shadow: 0 4px 16px rgba(27, 67, 50, 0.04);
    padding: 18px 20px;
}

.product-pos-theme .master-toolbar {
    background: #fbfdfc;
    border: 1px solid var(--border-card, #e5e7eb);
    border-radius: 12px;
    padding: 10px 14px;
    margin-bottom: 14px;
}

.product-pos-theme .form-control {
    border-radius: 8px;
    border: 1px solid var(--border-control, #d9e2dc);
    background: #ffffff;
    font-size: 12px;
    padding: 5px 10px;
    min-height: 32px;
    height: 32px;
    color: var(--text-main, #1c2826);
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.product-pos-theme .form-control:focus {
    border-color: var(--primary, #2d6a4f);
    box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.15);
    outline: none;
}

/* Table */
.product-pos-theme .master-table-wrap {
    border: 1px solid var(--border-card, #e5e7eb);
    border-radius: 12px;
    overflow-x: auto;
    background: #ffffff;
}

.product-pos-theme .master-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.product-pos-theme .master-table th {
    background: #fbfdfc;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--text-caption, #526359);
    padding: 10px 14px;
    border-bottom: 2px solid var(--border-card, #e5e7eb);
    border-top: none;
    border-left: none;
    border-right: none;
}

.product-pos-theme .master-table td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--border-card, #e5e7eb);
    border-top: none;
    border-left: none;
    border-right: none;
    font-size: 13.5px;
    vertical-align: middle;
    color: var(--text-main, #1c2826);
    background: #ffffff;
}

.product-pos-theme .master-table tbody tr:last-child td {
    border-bottom: none;
}

.product-pos-theme .master-table tbody tr:hover td {
    background: #f8faf9;
}

/* Badges: pill-shaped */
.product-pos-theme .master-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2.5px 9px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.2;
}

.product-pos-theme .master-status.active {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
}

.product-pos-theme .master-status.warning {
    background: #fef9c3;
    color: #854d0e;
    border: 1px solid #fef08a;
}

.product-pos-theme .master-status.critical {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.product-pos-theme .master-status.product-lifecycle {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}

/* Table Action Buttons */
.product-pos-theme .btn-action-edit {
    background-color: var(--mint-surface, #e8f5e9);
    color: var(--primary-hover, #1b4332);
    border: 1px solid var(--mint-border, #a3b18a);
    border-radius: 9999px;
    padding: 4px 14px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    min-height: 32px;
    transition: background-color 0.15s ease, border-color 0.15s ease;
}

.product-pos-theme .btn-action-edit:hover {
    background-color: var(--primary-light, #d8f3dc);
    border-color: var(--accent, #52b788);
}

.product-pos-theme .btn-action-delete {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    border-radius: 9999px;
    padding: 4px 14px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    min-height: 32px;
    transition: background-color 0.15s ease;
}

.product-pos-theme .btn-action-delete:hover {
    background: #fca5a5;
}

.product-pos-theme .btn-action-locked {
    background: #f3f4f6;
    color: #9ca3af;
    border: 1px solid #e5e7eb;
    border-radius: 9999px;
    padding: 4px 14px;
    font-size: 12px;
    font-weight: 600;
    cursor: not-allowed;
    min-height: 32px;
}

/* Modal Product Overrides */
#modal-product .modal-dialog {
    border-radius: 16px;
    border: 1px solid var(--border-card, #e5e7eb);
    box-shadow: 0 16px 40px rgba(27, 67, 50, 0.12);
}

#modal-product .modal-header {
    border-bottom: 1px solid var(--border-card, #e5e7eb);
    padding: 16px 20px;
}

#modal-product .modal-icon-badge {
    background: var(--mint-surface, #e8f5e9);
    color: var(--primary, #2d6a4f);
    border-radius: 10px;
}

#modal-product .modal-body {
    padding: 18px 20px;
}

#modal-product .form-control {
    border-radius: 10px;
    border: 1px solid var(--border-control, #d9e2dc);
}

#modal-product .form-control:focus {
    border-color: var(--primary, #2d6a4f);
    box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.15);
}

#modal-product .modal-footer {
    border-top: 1px solid var(--border-card, #e5e7eb);
    padding: 14px 20px;
}

#modal-product .modal-footer .btn {
    border-radius: 9999px;
    padding: 8px 18px;
    min-height: 38px;
}

#modal-product .modal-footer .btn-secondary {
    border-radius: 9999px;
    padding: 8px 18px;
    min-height: 38px;
    background: var(--mint-surface, #e8f5e9);
    color: var(--primary-hover, #1b4332);
    border: 1px solid var(--mint-border, #a3b18a);
}

/* Mobile Responsive */
@media (max-width: 720px) {
    .product-pos-theme { gap: 10px; }
    .product-pos-theme .card,
    .product-pos-theme .master-head,
    .product-pos-theme .master-workspace {
        border-radius: 14px;
        padding: 14px;
    }
    .product-pos-theme .master-table tbody tr {
        border-radius: 12px;
        border: 1px solid var(--border-card, #e5e7eb);
        box-shadow: 0 2px 8px rgba(27, 67, 50, 0.03);
    }
    .product-pos-theme .master-head .btn,
    .product-pos-theme .master-toolbar-actions > *,
    .product-pos-theme .btn-action-edit,
    .product-pos-theme .btn-action-delete,
    .product-pos-theme .btn-action-locked {
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    #modal-product .modal-footer .btn,
    #modal-product .modal-footer .btn-secondary {
        min-height: 44px;
    }
}
</style>
<div class="master-page product-pos-theme">
    <header class="card master-head">
        <div class="master-head-copy">
            <p class="master-eyebrow">Katalog dan kendali persediaan</p>
            <h1>Master Produk Material</h1>
            <p>Kelola identitas, kategori, harga, batas minimum stok, dan ketersediaan produk untuk transaksi baru tanpa mengubah histori stok.</p>
        </div>
        <button type="button" class="btn" onclick="openNewProduct()">Tambah Produk</button>
    </header>

    <section class="card master-summary-strip" aria-label="Ringkasan produk">
        <div class="master-summary-item"><span class="master-summary-label">Total Katalog</span><strong class="master-summary-value">{{ $summary['total'] }}</strong></div>
        <div class="master-summary-item"><span class="master-summary-label">Produk Aktif</span><strong class="master-summary-value">{{ $summary['active'] }}</strong></div>
        <div class="master-summary-item"><span class="master-summary-label">Stok Menipis</span><strong class="master-summary-value">{{ $summary['low'] }}</strong></div>
        <div class="master-summary-item"><span class="master-summary-label">Stok Habis</span><strong class="master-summary-value">{{ $summary['out'] }}</strong></div>
    </section>

    <section class="card master-workspace" aria-labelledby="products-table-title">
        <form method="GET" action="{{ route('products.index') }}" class="master-toolbar product-toolbar">
            <div class="master-control master-control-search">
                <label for="product-search">Cari produk</label>
                <input class="form-control" id="product-search" name="search" value="{{ $search }}" placeholder="SKU atau nama material">
            </div>
            <div class="master-control">
                <label for="product-category-filter">Kategori</label>
                <select class="form-control" id="product-category-filter" name="category">
                    <option value="">Semua kategori</option>
                    @foreach($categories as $category)
                        <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </div>
            <div class="master-control">
                <label for="product-unit-filter">Satuan</label>
                <select class="form-control" id="product-unit-filter" name="unit">
                    <option value="">Semua satuan</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit }}" @selected(($filters['unit'] ?? '') === $unit)>{{ $unit }}</option>
                    @endforeach
                </select>
            </div>
            <div class="master-control">
                <label for="product-status-filter">Status</label>
                <select class="form-control" id="product-status-filter" name="status">
                    <option value="">Semua status</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Arsip</option>
                </select>
            </div>
            <div class="master-control">
                <label for="product-stock-filter">Kondisi stok</label>
                <select class="form-control" id="product-stock-filter" name="stock">
                    <option value="">Semua kondisi</option>
                    <option value="available" @selected(($filters['stock'] ?? '') === 'available')>Tersedia</option>
                    <option value="low" @selected(($filters['stock'] ?? '') === 'low')>Menipis</option>
                    <option value="out" @selected(($filters['stock'] ?? '') === 'out')>Habis</option>
                </select>
            </div>
            <div class="master-control">
                <label for="product-per-page">Baris</label>
                <select class="form-control" id="product-per-page" name="per_page">
                    @foreach([10, 25, 50] as $size)
                        <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="master-toolbar-actions">
                <button type="submit" class="btn">Terapkan</button>
                <a href="{{ route('products.index') }}" class="btn btn-secondary">Reset</a>
            </div>
        </form>

        <div class="master-table-head">
            <h2 id="products-table-title">Daftar Produk</h2>
            <span>{{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }} dari {{ $products->total() }} hasil</span>
        </div>

        <div class="master-table-wrap">
            <table class="master-table">
                <thead>
                    <tr>
                        <th scope="col">Produk</th>
                        <th scope="col">Kategori</th>
                        <th scope="col">Satuan</th>
                        <th scope="col" style="text-align:right;">HPP</th>
                        <th scope="col" style="text-align:right;">Harga Jual</th>
                        <th scope="col" style="text-align:right;">Stok / Minimum</th>
                        <th scope="col">Status</th>
                        <th scope="col" style="text-align:right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $p)
                        @php
                            $stock = \Brick\Math\BigDecimal::of((string) $p->stock_quantity);
                            $minimum = \Brick\Math\BigDecimal::of((string) $p->min_stock);
                            $stockState = $stock->isLessThanOrEqualTo(\Brick\Math\BigDecimal::zero()) ? 'critical' : ($minimum->isGreaterThan(\Brick\Math\BigDecimal::zero()) && $stock->isLessThanOrEqualTo($minimum) ? 'warning' : 'active');
                            $stockLabel = $stockState === 'critical' ? 'Habis' : ($stockState === 'warning' ? 'Menipis' : 'Tersedia');
                        @endphp
                        <tr>
                            <td data-label="Produk">
                                <div class="master-identity"><strong>{{ $p->name }}</strong><small class="sku-code">{{ $p->sku }}</small></div>
                            </td>
                            <td data-label="Kategori">{{ $p->category }}</td>
                            <td data-label="Satuan">{{ $p->unit }}</td>
                            <td data-label="HPP" class="money">Rp {{ $formatDecimal($p->cost_price, 4, true) }}</td>
                            <td data-label="Harga Jual" class="money">Rp {{ $formatDecimal($p->selling_price, 2) }}</td>
                            <td data-label="Stok / Minimum" class="quantity">
                                <strong>{{ $formatDecimal($p->stock_quantity, 4, true) }}</strong>
                                <small style="display:block;color:var(--text-caption);">min. {{ $formatDecimal($p->min_stock, 4, true) }}</small>
                            </td>
                            <td data-label="Status">
                                <div class="product-status-stack">
                                    <span class="master-status product-lifecycle">{{ $p->is_active ? 'Aktif' : 'Arsip' }}</span>
                                    <span class="master-status {{ $stockState }}">{{ $stockLabel }}</span>
                                </div>
                            </td>
                            <td data-label="Aksi">
                                <div class="master-row-actions">
                                    <button type="button" class="btn-action-edit"
                                        data-id="{{ $p->id }}" data-sku="{{ $p->sku }}" data-name="{{ $p->name }}"
                                        data-unit="{{ $p->unit }}" data-category="{{ $p->category }}"
                                        data-cost="{{ $p->cost_price }}" data-price="{{ $p->selling_price }}"
                                        data-min-stock="{{ $p->min_stock }}" data-active="{{ $p->is_active ? '1' : '0' }}"
                                        onclick="editProductBtn(this)">Ubah</button>
                                    @if($p->has_history)
                                        <button type="button" class="btn-action-locked" onclick="showLockInfo('Hapus Terkunci', 'Produk ini sudah memiliki histori transaksi atau stok. Arsipkan produk untuk menghentikan pemakaian pada transaksi baru tanpa merusak histori.')">Terkunci</button>
                                    @else
                                        <form method="POST" action="{{ route('products.destroy', $p->id) }}" onsubmit="return confirm('Hapus produk ini dari katalog?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn-action-delete">Hapus</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="master-empty">Tidak ada produk yang cocok dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $products])
    </section>
</div>
@endsection

@section('modals')
<div id="modal-product" class="modal-overlay {{ $errors->any() ? 'open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="product-modal-title">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-title-wrapper">
                <div class="modal-icon-badge" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="m3 8 9 5 9-5v8l-9 5-9-5V8Z"/></svg></div>
                <div><h2 id="product-modal-title" class="modal-title">Pendaftaran Produk Baru</h2><div id="product-modal-subtitle" style="font-size:12px;color:var(--text-caption);margin-top:2px;">Master katalog material</div></div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('modal-product')" aria-label="Tutup dialog">&times;</button>
        </div>
        <form id="product-form" method="POST" action="{{ route('products.store') }}" style="display:flex;flex-direction:column;flex:1;overflow:hidden;margin:0;">
            @csrf
            <input type="hidden" name="_method" id="product-method" value="POST">
            <div class="modal-body">
                <div id="product-modal-info" class="master-help" style="margin-bottom:14px;">Produk baru dimulai dari stok awal 0.00. Perubahan stok tetap melalui penerimaan atau stock opname agar jejak audit terjaga.</div>
                <div class="master-modal-grid">
                    <div class="form-group"><label for="sku">SKU / Barcode</label><input class="form-control" id="sku" name="sku" value="{{ old('sku') }}" required autocomplete="off"></div>
                    <div class="form-group"><label for="unit">Satuan</label><input class="form-control" id="unit" name="unit" value="{{ old('unit', 'sak') }}" required autocomplete="off"></div>
                    <div class="form-group span-2"><label for="name">Nama Material</label><input class="form-control" id="name" name="name" value="{{ old('name') }}" required autocomplete="off"></div>
                    <div class="form-group"><label for="category">Kelompok Produk</label><input class="form-control" id="category" name="category" value="{{ old('category', 'Umum') }}" list="product-category-options" required maxlength="50" autocomplete="off" placeholder="Misal: Semen, Besi, Cat"><datalist id="product-category-options">@foreach($categories as $category)<option value="{{ $category }}">@endforeach</datalist><small style="display:block;margin-top:4px;color:var(--text-caption);">Pilih saran atau ketik nama kelompok baru.</small></div>
                    <div class="form-group"><label for="min_stock">Minimum Stok</label><input class="form-control tabular-nums" type="text" inputmode="decimal" id="min_stock" name="min_stock" value="{{ old('min_stock', '0') }}" required></div>
                    <div class="form-group"><label for="cost_price">HPP</label><input class="form-control tabular-nums" type="text" inputmode="decimal" id="cost_price" name="cost_price" value="{{ old('cost_price') }}" required data-currency-input data-currency-scale="4"></div>
                    <div class="form-group"><label for="selling_price">Harga Jual</label><input class="form-control tabular-nums" type="text" inputmode="decimal" id="selling_price" name="selling_price" value="{{ old('selling_price') }}" required data-currency-input data-currency-scale="2"></div>
                    <div class="form-group span-2">
                        <input type="hidden" name="is_active" value="0">
                        <label style="display:flex;align-items:center;gap:8px;min-height:38px;"><input type="checkbox" id="product-active" name="is_active" value="1" @checked(old('is_active', '1') === '1')> Aktif dan dapat dipakai pada transaksi baru</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modal-product')">Batal</button><button type="submit" id="btn-submit-product" class="btn">Simpan Produk</button></div>
        </form>
    </div>
</div>
<script>
function productModalReset() {
    document.getElementById('product-form').action = "{{ route('products.store') }}";
    document.getElementById('product-method').value = 'POST';
    document.getElementById('sku').value = '';
    document.getElementById('name').value = '';
    document.getElementById('unit').value = 'sak';
    document.getElementById('category').value = 'Umum';
    document.getElementById('min_stock').value = '0';
    document.getElementById('product-active').checked = true;
    setCurrencyInput(document.getElementById('cost_price'), '');
    setCurrencyInput(document.getElementById('selling_price'), '');
    document.getElementById('product-modal-title').textContent = 'Pendaftaran Produk Baru';
    document.getElementById('product-modal-subtitle').textContent = 'Master katalog material';
    document.getElementById('product-modal-info').style.display = '';
    document.getElementById('btn-submit-product').textContent = 'Simpan Produk';
}
function openNewProduct() { productModalReset(); openModal('modal-product'); }
function editProductBtn(btn) {
    document.getElementById('product-form').action = "{{ url('/products') }}/" + btn.dataset.id;
    document.getElementById('product-method').value = 'PUT';
    document.getElementById('sku').value = btn.dataset.sku;
    document.getElementById('name').value = btn.dataset.name;
    document.getElementById('unit').value = btn.dataset.unit;
    document.getElementById('category').value = btn.dataset.category;
    document.getElementById('min_stock').value = btn.dataset.minStock;
    document.getElementById('product-active').checked = btn.dataset.active === '1';
    setCurrencyInput(document.getElementById('cost_price'), btn.dataset.cost);
    setCurrencyInput(document.getElementById('selling_price'), btn.dataset.price);
    document.getElementById('product-modal-title').textContent = 'Ubah Produk';
    document.getElementById('product-modal-subtitle').textContent = 'Stok fisik tidak diubah dari master';
    document.getElementById('product-modal-info').style.display = 'none';
    document.getElementById('btn-submit-product').textContent = 'Perbarui Produk';
    openModal('modal-product');
}
</script>
@endsection
