@extends('layouts.app')

@section('content')
<!-- Header Summary Card -->
<div class="card" style="margin-bottom: 22px; padding: 22px 26px; border-left: 4px solid var(--primary);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
        <div>
            <span class="badge" style="margin-bottom: 6px;">Katalog &amp; Master Data</span>
            <h1 style="font-size: 20px; font-weight: 800; color: var(--primary-hover); margin: 0 0 4px 0; letter-spacing: -0.01em;">Katalog Produk Material</h1>
            <p style="font-size: 13px; color: var(--text-muted); margin: 0; line-height: 1.5;">
                Daftar material bangunan aktif beserta satuan, HPP, harga jual retail, dan stok fisik tersinkronisasi.
            </p>
        </div>
        <div>
            <button type="button" class="btn" onclick="openModal('modal-product')" style="padding: 9px 18px; font-size: 13.5px; font-weight: 600; box-shadow: 0 4px 14px rgba(45, 106, 79, 0.25);">+ Tambah Produk Baru</button>
        </div>
    </div>
</div>

<!-- Full-Width Data Table Card -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2 style="margin: 0; border: none; padding: 0;">Daftar Produk Aktif ({{ count($products) }})</h2>
        <span class="badge">Master Material Bangunan</span>
    </div>

    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th style="width: 140px;">SKU</th>
                    <th>Nama Produk</th>
                    <th style="width: 90px;">Satuan</th>
                    <th style="text-align: right; width: 130px;">HPP</th>
                    <th style="text-align: right; width: 140px;">Harga Jual</th>
                    <th style="text-align: right; width: 100px;">Stok</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $p)
                <tr>
                    <td><span class="sku-code badge">{{ $p->sku }}</span></td>
                    <td style="font-weight: 600; color: var(--text-main);">{{ $p->name }}</td>
                    <td><span style="color: var(--text-muted);">{{ $p->unit }}</span></td>
                    <td style="text-align:right;">Rp {{ $p->cost_price }}</td>
                    <td style="text-align:right;">Rp {{ number_format((float)$p->selling_price, 2, ',', '.') }}</td>
                    <td style="text-align:right; font-weight:bold;">{{ number_format((float)$p->stock_quantity, 2, ',', '.') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-caption); padding: 32px;">Belum ada data produk terdaftar.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('modals')
<!-- POP-UP MODAL: Form Pendaftaran Produk Baru -->
<div id="modal-product" class="modal-overlay {{ $errors->any() ? 'open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="product-modal-title">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-title-wrapper">
                <div class="modal-icon-badge">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path>
                        <path d="m3.3 7 8.7 5 8.7-5"></path>
                        <path d="M12 22V12"></path>
                    </svg>
                </div>
                <div>
                    <h2 id="product-modal-title" class="modal-title">Pendaftaran Produk Baru</h2>
                    <div style="font-size: 12px; color: var(--text-caption); margin-top: 2px;">Katalog Master Material Toko</div>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('modal-product')" aria-label="Tutup Dialog">&times;</button>
        </div>

        <form method="POST" action="{{ route('products.store') }}" style="display: flex; flex-direction: column; flex: 1; overflow: hidden; margin: 0;">
            @csrf
            <div class="modal-body">
                <div class="alert alert-info" style="font-size: 12.5px; margin-bottom: 16px; background-color: var(--mint-surface); border: 1px solid var(--mint-border); color: var(--primary-hover);">
                    Sesuai prinsip integritas akuntansi, produk baru otomatis didaftarkan dengan <strong>stok awal 0.00</strong>. Penambahan stok fisik wajib melalui form <em>Penerimaan Barang</em> agar jurnal neraca tetap seimbang.
                </div>

                <div class="form-group">
                    <label for="sku">Kode SKU / Barcode</label>
                    <input type="text" id="sku" name="sku" value="{{ old('sku') }}" required placeholder="Contoh: SEM-001" autocomplete="off" class="form-control">
                </div>
                <div class="form-group">
                    <label for="name">Nama Material / Barang</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required placeholder="Contoh: Semen Gresik 50kg (PCC)" autocomplete="off" class="form-control">
                </div>
                <div class="form-group">
                    <label for="unit">Satuan Kuantitas</label>
                    <input type="text" id="unit" name="unit" value="{{ old('unit', 'sak') }}" required placeholder="sak, m3, btg, kg" autocomplete="off" class="form-control">
                </div>
                <div class="form-group">
                    <label for="cost_price">Harga Pokok Pembelian (HPP)</label>
                    <input type="number" step="0.0001" min="0" id="cost_price" name="cost_price" value="{{ old('cost_price', '0.0000') }}" required class="form-control tabular-nums">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="selling_price">Harga Jual Satuan</label>
                    <input type="number" step="0.01" min="0" id="selling_price" name="selling_price" value="{{ old('selling_price', '0.00') }}" required class="form-control tabular-nums">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-product')">Batal</button>
                <button type="submit" class="btn">Simpan Produk (Stok Awal 0)</button>
            </div>
        </form>
    </div>
</div>
@endsection
