@extends('layouts.app')

@section('content')
<div class="grid-2">
    <div class="card">
        <h2>Pendaftaran Produk Baru</h2>
        <p style="font-size:12px; color:#555; margin-bottom:16px;">
            Sesuai prinsip integritas akuntansi, produk baru otomatis didaftarkan dengan <strong>stok awal 0.00</strong>. Penambahan stok fisik wajib melalui form <em>Penerimaan Barang</em> agar jurnal neraca tetap seimbang.
        </p>
        <form method="POST" action="{{ route('products.store') }}">
            @csrf
            <div class="form-group">
                <label for="sku">Kode SKU / Barcode</label>
                <input type="text" id="sku" name="sku" value="{{ old('sku') }}" required placeholder="Contoh: SEM-001" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="name">Nama Material / Barang</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required placeholder="Nama barang" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="unit">Satuan</label>
                <input type="text" id="unit" name="unit" value="{{ old('unit', 'sak') }}" required placeholder="sak, m3, btg, kg" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="cost_price">Harga Pokok Pembelian (HPP)</label>
                <input type="number" step="0.0001" min="0" id="cost_price" name="cost_price" value="{{ old('cost_price', '0.0000') }}" required>
            </div>
            <div class="form-group">
                <label for="selling_price">Harga Jual Satuan</label>
                <input type="number" step="0.01" min="0" id="selling_price" name="selling_price" value="{{ old('selling_price', '0.00') }}" required>
            </div>
            <button type="submit" class="btn" style="width:100%;">Simpan Produk (Stok Awal 0)</button>
        </form>
    </div>

    <div class="card">
        <h2>Daftar Produk Aktif ({{ count($products) }})</h2>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Nama Produk</th>
                        <th>Satuan</th>
                        <th style="text-align:right;">HPP</th>
                        <th style="text-align:right;">Harga Jual</th>
                        <th style="text-align:right;">Stok</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $p)
                    <tr>
                        <td><code>{{ $p->sku }}</code></td>
                        <td>{{ $p->name }}</td>
                        <td>{{ $p->unit }}</td>
                        <td style="text-align:right;">Rp {{ $p->cost_price }}</td>
                        <td style="text-align:right;">Rp {{ number_format((float)$p->selling_price, 2, ',', '.') }}</td>
                        <td style="text-align:right; font-weight:bold;">{{ number_format((float)$p->stock_quantity, 2, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="text-align:center; color:#777; padding:20px;">Belum ada data produk terdaftar.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
