@extends('layouts.app')

@section('content')
<div class="grid-2">
    <div class="card">
        <h2>Penerimaan Barang & Restock Stok</h2>
        <p style="font-size:12px; color:#555; margin-bottom:16px;">
            Mencatat barang masuk (stok awal atau pembelian). Sistem secara otomatis mengkalkulasi <strong>Moving Weighted Average HPP</strong> dan menerbitkan jurnal berimbang ke buku besar.
        </p>
        <form method="POST" action="{{ route('inventory.receipts.store') }}">
            @csrf
            <div class="form-group">
                <label for="receipt_type">Tipe Penerimaan</label>
                <select id="receipt_type" name="receipt_type" required>
                    <option value="opening" {{ old('receipt_type') === 'opening' ? 'selected' : '' }}>Stok Awal (Opening Inventory)</option>
                    <option value="purchase" {{ old('receipt_type', 'purchase') === 'purchase' ? 'selected' : '' }}>Pembelian / Kulakan Material (Restock)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="credit_account">Akun Kredit Pengimbang (Offset)</label>
                <select id="credit_account" name="credit_account" required>
                    @foreach($creditAccounts as $code => $label)
                        <option value="{{ $code }}" {{ old('credit_account', '2101') === $code ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="product_id">Pilih Produk</label>
                <select id="product_id" name="product_id" required>
                    <option value="">-- Pilih Material / Barang --</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" {{ old('product_id') == $p->id ? 'selected' : '' }}>
                            {{ $p->sku }} - {{ $p->name }} (Stok saat ini: {{ number_format((float)$p->stock_quantity, 2, ',', '.') }} {{ $p->unit }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="grid-2" style="gap:10px; margin-bottom:0;">
                <div class="form-group">
                    <label for="quantity">Kuantitas Masuk</label>
                    <input type="number" step="0.0001" min="0.0001" id="quantity" name="quantity" value="{{ old('quantity') }}" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label for="unit_cost">Biaya Satuan / HPP (Rp)</label>
                    <input type="number" step="0.0001" min="0.0001" id="unit_cost" name="unit_cost" value="{{ old('unit_cost') }}" required placeholder="0.0000">
                </div>
            </div>
            <div class="form-group">
                <label for="supplier_name">Nama Pemasok / Distributor</label>
                <input type="text" id="supplier_name" name="supplier_name" value="{{ old('supplier_name') }}" placeholder="Nama pemasok (opsional)" autocomplete="off">
            </div>
            <div class="grid-2" style="gap:10px; margin-bottom:0;">
                <div class="form-group">
                    <label for="receipt_date">Tanggal Penerimaan</label>
                    <input type="date" id="receipt_date" name="receipt_date" value="{{ old('receipt_date', date('Y-m-d')) }}">
                </div>
                <div class="form-group">
                    <label for="notes">Catatan Transaksi</label>
                    <input type="text" id="notes" name="notes" value="{{ old('notes') }}" placeholder="No. DO / Faktur supplier">
                </div>
            </div>
            <button type="submit" class="btn" style="width:100%;">Bukukan Penerimaan Barang</button>
        </form>
    </div>

    <div class="card">
        <h2>Riwayat Penerimaan Terkini</h2>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>No. Bukti</th>
                        <th>Tanggal</th>
                        <th>Tipe</th>
                        <th>Akun Kredit</th>
                        <th>Pemasok</th>
                        <th style="text-align:right;">Total Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($receipts as $r)
                    <tr>
                        <td><code>{{ $r->receipt_number }}</code></td>
                        <td>{{ $r->receipt_date }}</td>
                        <td>
                            @if($r->receipt_type === 'opening')
                                <span class="badge" style="background:#e2e3e5; color:#383d41;">Stok Awal</span>
                            @else
                                <span class="badge" style="background:#d1e7dd; color:#0f5132;">Pembelian</span>
                            @endif
                        </td>
                        <td>{{ $r->credit_account }}</td>
                        <td>{{ $r->supplier_name ?: '-' }}</td>
                        <td style="text-align:right; font-weight:bold;">Rp {{ number_format((float)$r->total_amount, 2, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="text-align:center; color:#777; padding:20px;">Belum ada riwayat penerimaan barang.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
