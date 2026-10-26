@extends('layouts.app')

@section('content')
@php
    $formatMoney = static function ($value): string {
        $amount = \Brick\Math\BigDecimal::of((string) $value)->toScale(2, \Brick\Math\RoundingMode::HalfUp);
        [$whole, $fraction] = explode('.', (string) $amount);
        return 'Rp ' . preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole) . ',' . $fraction;
    };
@endphp
<style>
    .inventory-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:16px; }
    .inventory-summary-card { min-width:0; padding:15px 16px; border:1px solid var(--border-card); border-radius:var(--radius-card); background:var(--surface); box-shadow:0 4px 20px -2px rgba(27,67,50,.05); }
    .inventory-summary-card span { display:block; margin-bottom:7px; color:var(--text-caption); font-size:10px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
    .inventory-summary-card strong { display:block; color:var(--text-main); font:700 clamp(16px,1.6vw,21px) var(--font-mono); font-variant-numeric:tabular-nums; overflow-wrap:anywhere; }
    .inventory-summary-card.is-primary strong { color:var(--primary-hover); }
    .inventory-summary-card small { display:block; margin-top:4px; font-size:11px; color:var(--text-muted); }
    @media (max-width:900px) {
        .inventory-summary { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media (max-width:540px) {
        .inventory-summary { grid-template-columns:1fr; gap:8px; }
        .inventory-summary-card { padding:12px; }
    }
</style>

@if(isset($receiptSummary))
<div class="inventory-summary">
    <div class="inventory-summary-card">
        <span>Total Penerimaan</span>
        <strong>{{ number_format($receiptSummary['total_receipts']) }}</strong>
        <small>{{ $receiptSummary['period_label'] }}</small>
    </div>
    <div class="inventory-summary-card">
        <span>Pembelian / Restock</span>
        <strong>{{ number_format($receiptSummary['purchase_count']) }}</strong>
        <small>Transaksi Masuk</small>
    </div>
    <div class="inventory-summary-card">
        <span>Saldo / Stok Awal</span>
        <strong>{{ number_format($receiptSummary['opening_count']) }}</strong>
        <small>Injeksi Stok Awal</small>
    </div>
    <div class="inventory-summary-card is-primary">
        <span>Total Nilai Penerimaan</span>
        <strong>{{ $formatMoney($receiptSummary['total_amount']) }}</strong>
        <small>{{ $receiptSummary['period_label'] }}</small>
    </div>
</div>
@endif

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; padding-bottom: 14px; border-bottom: 1px solid var(--border-color);">
        <div>
            <h2 style="margin: 0; border: none; padding: 0; font-size: 16px;">Riwayat Penerimaan Terkini</h2>
            <div style="font-size: 12.5px; color: var(--text-muted); margin-top: 4px;">
                Menampilkan <strong>{{ $receipts->firstItem() ?? 0 }}&ndash;{{ $receipts->lastItem() ?? 0 }}</strong> dari <strong>{{ $receipts->total() }}</strong> penerimaan
                @php
                    $visibleTotal = $receipts->getCollection()->sum(function($r) { return (float)($r->total_amount ?? 0); });
                @endphp
                &bull; Total nilai halaman ini: <strong style="color: var(--primary-hover);">Rp {{ number_format($visibleTotal, 2, ',', '.') }}</strong>
            </div>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <button type="button" class="btn" onclick="openModal('modal-receipt')" style="padding: 8px 16px; font-size: 13px; font-weight: 600;">+ Catat Penerimaan</button>
        </div>
    </div>

    @include('partials.table-controls', ['search' => $search, 'perPage' => $perPage, 'placeholder' => 'Cari nomor bukti, pemasok, atau material...'])

    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th style="width: 105px;"><a class="th-sort" href="{{ request()->fullUrlWithQuery(['sort' => 'receipt_date', 'dir' => ($sort === 'receipt_date' && $dir === 'asc') ? 'desc' : 'asc']) }}">Tanggal @if($sort === 'receipt_date')<span class="th-arrow">{{ $dir === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                    <th><a class="th-sort" href="{{ request()->fullUrlWithQuery(['sort' => 'receipt_number', 'dir' => ($sort === 'receipt_number' && $dir === 'asc') ? 'desc' : 'asc']) }}">No. Bukti / Pemasok @if($sort === 'receipt_number')<span class="th-arrow">{{ $dir === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                    <th><a class="th-sort" href="{{ request()->fullUrlWithQuery(['sort' => 'product_name', 'dir' => ($sort === 'product_name' && $dir === 'asc') ? 'desc' : 'asc']) }}">Material @if($sort === 'product_name')<span class="th-arrow">{{ $dir === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                    <th style="width: 130px;"><a class="th-sort" href="{{ request()->fullUrlWithQuery(['sort' => 'receipt_type', 'dir' => ($sort === 'receipt_type' && $dir === 'asc') ? 'desc' : 'asc']) }}">Tipe &amp; Akun @if($sort === 'receipt_type')<span class="th-arrow">{{ $dir === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                    <th style="text-align: right; width: 90px;"><a class="th-sort" href="{{ request()->fullUrlWithQuery(['sort' => 'quantity', 'dir' => ($sort === 'quantity' && $dir === 'asc') ? 'desc' : 'asc']) }}">Qty @if($sort === 'quantity')<span class="th-arrow">{{ $dir === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                    <th style="text-align: right; width: 130px;"><a class="th-sort" href="{{ request()->fullUrlWithQuery(['sort' => 'unit_cost', 'dir' => ($sort === 'unit_cost' && $dir === 'asc') ? 'desc' : 'asc']) }}">Biaya Satuan @if($sort === 'unit_cost')<span class="th-arrow">{{ $dir === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                    <th style="text-align: right; width: 140px;"><a class="th-sort" href="{{ request()->fullUrlWithQuery(['sort' => 'total_amount', 'dir' => ($sort === 'total_amount' && $dir === 'asc') ? 'desc' : 'asc']) }}">Total @if($sort === 'total_amount')<span class="th-arrow">{{ $dir === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                </tr>
            </thead>
            <tbody>
                @forelse($receipts as $r)
                <tr>
                    <td style="color: var(--text-caption); font-size: 12px; white-space: nowrap;">{{ substr($r->receipt_date, 0, 10) }}</td>
                    <td>
                        <div style="font-weight: 600; font-size: 13px;">{{ $r->receipt_number ?? '-' }}</div>
                        @if(!empty($r->supplier_name))
                            <div style="font-size: 11.5px; color: var(--text-muted);">Pemasok: {{ $r->supplier_name }}</div>
                        @endif
                        @if(!empty($r->notes))
                            <div style="font-size: 11px; color: var(--text-caption); font-style: italic;">{{ $r->notes }}</div>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight: 600;">{{ $r->product_name ?? 'Material' }}</div>
                    </td>
                    <td>
                        <span class="badge" style="font-size: 10.5px;">{{ strtoupper($r->receipt_type) }}</span>
                        @if(!empty($r->credit_account))
                            <div style="font-size: 11px; color: var(--text-caption); margin-top: 2px;">Akun: {{ $r->credit_account }}</div>
                        @endif
                    </td>
                    <td style="text-align: right; font-variant-numeric: tabular-nums;">{{ rtrim(rtrim(number_format((float)($r->quantity ?? 0), 4, ',', '.'), '0'), ',') }}</td>
                    <td style="text-align: right; font-variant-numeric: tabular-nums;">Rp {{ number_format((float)($r->unit_cost ?? 0), 2, ',', '.') }}</td>
                    <td style="text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; color: var(--primary-hover);">Rp {{ number_format((float)($r->total_amount ?? 0), 2, ',', '.') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-caption); padding: 32px 16px;">Belum ada riwayat penerimaan barang.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('partials.pagination', ['paginator' => $receipts])
</div>
@endsection

@section('modals')
<!-- POP-UP MODAL: Form Penerimaan Barang & Restock Stok -->
<div id="modal-receipt" class="modal-overlay {{ $errors->any() ? 'open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="receipt-modal-title">
    <div class="modal-dialog" style="max-width: 680px; width: 100%;">
        <div class="modal-header">
            <div class="modal-title-wrapper">
                <div class="modal-icon-badge">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                    </svg>
                </div>
                <div>
                    <h2 id="receipt-modal-title" class="modal-title">Penerimaan Barang &amp; Restock Stok</h2>
                    <div style="font-size: 12px; color: var(--text-caption); margin-top: 2px;">Input stok awal atau pembelian material</div>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('modal-receipt')" aria-label="Tutup Dialog">&times;</button>
        </div>

        <form method="POST" action="{{ route('inventory.receipts.store') }}" style="display: flex; flex-direction: column; flex: 1; overflow: hidden; margin: 0;">
            @csrf
            <div class="modal-body" style="overflow-y: auto; max-height: calc(85vh - 130px); padding: 18px 24px;">
                @if($errors->any())
                    <div class="alert alert-error" role="alert"><strong>Periksa kembali isian:</strong> {{ $errors->first() }}</div>
                @endif
                <div class="alert alert-info" style="font-size: 12.5px; margin-bottom: 18px;">
                    Mencatat barang masuk (stok awal atau pembelian). Sistem secara otomatis mengkalkulasi <strong>Moving Weighted Average HPP</strong> dan menerbitkan jurnal berimbang ke buku besar.
                </div>

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
                                {{ $p->sku }} - {{ $p->name }} (Stok saat ini: {{ rtrim(rtrim(number_format((float)$p->stock_quantity, 4, ',', '.'), '0'), ',') }} {{ $p->unit }})
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
                        <input type="text" inputmode="decimal" id="unit_cost" name="unit_cost" value="{{ old('unit_cost') }}" required placeholder="0,0000" class="form-control tabular-nums" data-currency-input data-currency-scale="4">
                    </div>
                </div>

                @if($fleetReady)
                <style>#receipt-armada-fields[hidden] { display: none; }</style>
                <div class="form-group">
                    <label for="receipt_use_armada">Angkutan dengan Armada Toko</label>
                    <select id="receipt_use_armada" name="use_armada" class="form-control" onchange="syncReceiptArmada()">
                        <option value="0" {{ old('use_armada', '0') === '0' ? 'selected' : '' }}>Tanpa armada</option>
                        <option value="1" {{ old('use_armada') === '1' ? 'selected' : '' }}>Gunakan armada toko</option>
                    </select>
                </div>
                <div class="grid-2" id="receipt-armada-fields" style="gap: 12px;" hidden>
                    <div class="form-group">
                        <label for="receipt_vehicle_id">Pilih Armada</label>
                        <select id="receipt_vehicle_id" name="vehicle_id" class="form-control" disabled>
                            <option value="">-- Pilih armada aktif --</option>
                            @foreach($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}" {{ old('vehicle_id') == $vehicle->id ? 'selected' : '' }}>{{ $vehicle->license_plate }} ({{ strtoupper($vehicle->vehicle_type) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="receipt_ritase_amount">Uang Ritase (Rp)</label>
                        <input type="text" inputmode="decimal" id="receipt_ritase_amount" name="ritase_amount" value="{{ old('ritase_amount') }}" placeholder="0,00" class="form-control tabular-nums" data-currency-input data-currency-scale="2" disabled>
                    </div>
                </div>
                @endif
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
            </div>

            <div class="modal-footer" style="padding: 14px 24px; border-top: 1px solid var(--border-color); background: var(--bg-card); display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-receipt')">Batal</button>
                <button type="submit" class="btn" style="font-weight: 700;">Bukukan Penerimaan Barang</button>
            </div>
        </form>
    </div>
</div>
<script>
function syncReceiptArmada() {
    if (!document.getElementById('receipt_use_armada')) return;
    const enabled = document.getElementById('receipt_use_armada').value === '1';
    document.getElementById('receipt-armada-fields').hidden = !enabled;
    for (const id of ['receipt_vehicle_id', 'receipt_ritase_amount']) {
        const field = document.getElementById(id);
        field.disabled = !enabled;
        field.required = enabled;
        if (!enabled) field.value = '';
    }
}
document.addEventListener('DOMContentLoaded', syncReceiptArmada);
</script>
@endsection
