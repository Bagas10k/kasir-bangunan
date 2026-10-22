@extends('layouts.app')

@section('content')
<div class="grid-2">
    <div class="card">
        <h2>Penerimaan Barang &amp; Restock Stok</h2>
        <div class="alert alert-info" style="font-size: 12.5px; margin-bottom: 18px;">
            Mencatat barang masuk (stok awal atau pembelian). Sistem secara otomatis mengkalkulasi <strong>Moving Weighted Average HPP</strong> dan menerbitkan jurnal berimbang ke buku besar.
        </div>
        <form method="POST" action="{{ route('inventory.receipts.store') }}">
            @csrf
            <div class="form-group">
                <label for="receipt_type">Tipe Penerimaan</label>
                <select id="receipt_type" name="receipt_type" required class="form-control">
                    <option value="opening" {{ old('receipt_type') === 'opening' ? 'selected' : '' }}>Stok Awal (Opening Inventory)</option>
                    <option value="purchase" {{ old('receipt_type', 'purchase') === 'purchase' ? 'selected' : '' }}>Pembelian / Kulakan Material (Restock)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="credit_account">Akun Kredit Pengimbang (Offset)</label>
                <select id="credit_account" name="credit_account" required class="form-control">
                    @foreach($creditAccounts as $code => $label)
                        <option value="{{ $code }}" {{ old('credit_account', '2101') === $code ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="product_id">Pilih Produk</label>
                <select id="product_id" name="product_id" required class="form-control">
                    <option value="">-- Pilih Material / Barang --</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" {{ old('product_id') == $p->id ? 'selected' : '' }}>
                            {{ $p->sku }} - {{ $p->name }} (Stok saat ini: {{ number_format((float)$p->stock_quantity, 2, ',', '.') }} {{ $p->unit }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="grid-2" style="gap: 12px; margin-bottom: 0;">
                <div class="form-group">
                    <label for="quantity">Kuantitas Masuk</label>
                    <input type="number" step="0.0001" min="0.0001" id="quantity" name="quantity" value="{{ old('quantity') }}" required placeholder="0.00" class="form-control tabular-nums">
                </div>
                <div class="form-group">
                    <label for="unit_cost">Biaya Satuan / HPP (Rp)</label>
                    <input type="number" step="0.0001" min="0.0001" id="unit_cost" name="unit_cost" value="{{ old('unit_cost') }}" required placeholder="0.0000" class="form-control tabular-nums">
                </div>
            </div>
            <div class="form-group">
                <label for="supplier_name">Nama Pemasok / Distributor</label>
                <input type="text" id="supplier_name" name="supplier_name" value="{{ old('supplier_name') }}" placeholder="Nama pemasok (opsional)" autocomplete="off" class="form-control">
            </div>
            <div class="grid-2" style="gap: 12px; margin-bottom: 0;">
                <div class="form-group">
                    <label for="receipt_date">Tanggal Penerimaan</label>
                    <input type="date" id="receipt_date" name="receipt_date" value="{{ old('receipt_date', date('Y-m-d')) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label for="notes">Catatan Transaksi</label>
                    <input type="text" id="notes" name="notes" value="{{ old('notes') }}" placeholder="No. DO / Faktur supplier" class="form-control">
                </div>
            </div>
            <button type="submit" class="btn" style="width: 100%; border-radius: 9999px; min-height: 46px; font-weight: 700; margin-top: 6px;">Bukukan Penerimaan Barang</button>
        </form>
    </div>

    <div class="card">
        <h2>Riwayat Penerimaan Terkini</h2>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Material</th>
                        <th style="text-align: right;">Qty</th>
                        <th style="text-align: right;">Biaya</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($receipts as $r)
                    <tr>
                        <td style="color: var(--text-caption); font-size: 12px;">{{ substr($r->receipt_date, 0, 10) }}</td>
                        <td>
                            <div style="font-weight: 600;">{{ $r->product_name ?? 'Material' }}</div>
                            <span class="badge" style="font-size: 10.5px;">{{ strtoupper($r->receipt_type) }}</span>
                        </td>
                        <td style="text-align: right; font-variant-numeric: tabular-nums;">{{ number_format((float)$r->quantity, 2, ',', '.') }}</td>
                        <td style="text-align: right; font-variant-numeric: tabular-nums;">Rp {{ number_format((float)$r->unit_cost, 2, ',', '.') }}</td>
                        <td style="text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; color: var(--primary-hover);">Rp {{ number_format((float)$r->total_amount, 2, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-caption); padding: 24px;">Belum ada riwayat penerimaan barang.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
