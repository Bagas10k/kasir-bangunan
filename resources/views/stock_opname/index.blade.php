@extends('layouts.app')
@section('content')
<style>
    @media (prefers-reduced-motion: reduce) {
        *, ::before, ::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
            scroll-behavior: auto !important;
        }
    }
    .form-group {
        margin-bottom: 14px;
    }
    .form-control {
        width: 100%;
        box-sizing: border-box;
    }
</style>

@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-error" role="alert">{{ session('error') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-error" role="alert">
        <strong>Terjadi kesalahan input:</strong>
        <ul style="margin: 6px 0 0 18px; padding: 0;">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid-2">
    <div>
        <div class="card">
            <h2>Pencatatan Stock Opname Material</h2>
            <form action="{{ route('opname.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label>Item Fisik Opname</label>
                    <table id="opname-table">
                        <thead>
                            <tr>
                                <th>Produk Material</th>
                                <th style="width: 130px;">Qty Fisik</th>
                                <th style="width: 40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="opname-tbody">
                            <tr class="opname-row">
                                <td>
                                    <select name="items[0][product_id]" class="form-control opname-product" required>
                                        <option value="">-- Pilih Produk --</option>
                                        @foreach($products as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }} (Sistem: {{ $p->stock_quantity }} {{ $p->unit }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.0001" min="0" name="items[0][physical_qty]" class="form-control tabular-nums" placeholder="0.00" required>
                                </td>
                                <td style="text-align: center; vertical-align: middle;">
                                    <button type="button" class="btn btn-secondary" style="min-height: 28px; padding: 2px 8px; font-size: 11px;" onclick="removeOpnameRow(this)">x</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div style="margin-top: 10px;">
                        <button type="button" class="btn btn-secondary" onclick="addOpnameRow()">+ Tambah Baris</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Catatan Opname</label>
                    <input type="text" name="notes" id="notes" class="form-control" maxlength="255" placeholder="Contoh: Audit stok berkala fisik gudang" value="{{ old('notes') }}">
                </div>

                <div style="margin-top: 18px;">
                    <button type="submit" class="btn" style="width: 100%; border-radius: 9999px; min-height: 46px; font-weight: 700;">Simpan Penyesuaian Stok</button>
                </div>
            </form>
        </div>
    </div>

    <div>
        <div class="card">
            <h2>Riwayat Stock Opname</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nomor Opname</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentOpnames as $op)
                    <tr>
                        <td><strong class="sku-code">{{ $op->opname_number }}</strong></td>
                        <td style="color: var(--text-caption); font-size: 12px;">{{ $op->opname_date }}</td>
                        <td><span class="badge">{{ strtoupper($op->status) }}</span></td>
                        <td>{{ $op->notes ?? '-' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="text-align: center; color: var(--text-caption); padding: 20px;">Belum ada riwayat stock opname.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Referensi Stok Sistem Saat Ini</h2>
            <div style="max-height: 260px; overflow-y: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Nama</th>
                            <th>Satuan</th>
                            <th style="text-align: right;">Stok Sistem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $p)
                        <tr>
                            <td><span class="sku-code badge">{{ $p->sku }}</span></td>
                            <td style="font-weight: 600;">{{ $p->name }}</td>
                            <td><span style="color: var(--text-muted);">{{ $p->unit }}</span></td>
                            <td style="text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; color: var(--primary-hover);">
                                {{ number_format((float)$p->stock_quantity, 2, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="text-align: center; color: var(--text-caption); padding: 20px;">Belum ada produk terdaftar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
let opnameIndex = 1;

function addOpnameRow() {
    const tbody = document.getElementById('opname-tbody');
    const firstSelect = tbody.querySelector('.opname-product');
    const optionsHtml = firstSelect ? firstSelect.innerHTML : '';

    const tr = document.createElement('tr');
    tr.className = 'opname-row';
    tr.innerHTML = `
        <td>
            <select name="items[${opnameIndex}][product_id]" class="form-control opname-product" required>
                ${optionsHtml}
            </select>
        </td>
        <td>
            <input type="number" step="0.0001" min="0" name="items[${opnameIndex}][physical_qty]" class="form-control tabular-nums" placeholder="0.00" required>
        </td>
        <td style="text-align: center; vertical-align: middle;">
            <button type="button" class="btn btn-secondary" style="min-height: 28px; padding: 2px 8px; font-size: 11px;" onclick="removeOpnameRow(this)">x</button>
        </td>
    `;
    tbody.appendChild(tr);
    opnameIndex++;
}

function removeOpnameRow(btn) {
    const rows = document.querySelectorAll('.opname-row');
    if (rows.length > 1) {
        btn.closest('tr').remove();
    }
}
</script>
@endsection
