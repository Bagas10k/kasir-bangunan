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
    .opname-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:16px; }
    .opname-summary-card { min-width:0; padding:15px 16px; border:1px solid var(--border-card); border-radius:var(--radius-card); background:var(--surface); box-shadow:0 4px 20px -2px rgba(27,67,50,.05); }
    .opname-summary-card span { display:block; margin-bottom:7px; color:var(--text-caption); font-size:10px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
    .opname-summary-card strong { display:block; color:var(--text-main); font:700 clamp(16px,1.6vw,21px) var(--font-mono); font-variant-numeric:tabular-nums; overflow-wrap:anywhere; }
    .opname-summary-card.is-primary strong { color:var(--primary-hover); }
    .opname-summary-card.is-danger strong { color:#c53030; }
    .opname-summary-card small { display:block; margin-top:4px; font-size:11px; color:var(--text-muted); }
    @media (max-width:900px) {
        .opname-summary { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media (max-width:540px) {
        .opname-summary { grid-template-columns:1fr; gap:8px; }
        .opname-summary-card { padding:12px; }
    }
</style>

@if(isset($opnameSummary))
<div class="opname-summary">
    <div class="opname-summary-card is-primary">
        <span>Opname Posted</span>
        <strong>{{ number_format($opnameSummary['posted_count']) }}</strong>
        <small>{{ $opnameSummary['period_label'] }}</small>
    </div>
    <div class="opname-summary-card">
        <span>Item Dihitung</span>
        <strong>{{ number_format($opnameSummary['items_counted']) }}</strong>
        <small>Baris Item Fisik</small>
    </div>
    <div class="opname-summary-card">
        <span style="color:#2f855a;">Nilai Kenaikan Stok</span>
        <strong style="color:#2f855a;">{{ $formatMoney($opnameSummary['stock_increase_value']) }}</strong>
        <small>Selisih Lebih (+)</small>
    </div>
    <div class="opname-summary-card is-danger">
        <span style="color:#c53030;">Nilai Penurunan Stok</span>
        <strong style="color:#c53030;">{{ $formatMoney($opnameSummary['stock_decrease_value']) }}</strong>
        <small>Selisih Kurang (-)</small>
    </div>
</div>
@endif

<style>
    @media (prefers-reduced-motion: reduce) {
        *, ::before, ::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
            scroll-behavior: auto !important;
        }
    }
    .page-header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 20px;
    }
    .page-header-meta {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .page-title {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
        color: var(--text-main);
        letter-spacing: -0.01em;
    }
    .count-indicator {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 9999px;
        font-size: 12px;
        font-weight: 600;
        background-color: var(--mint-surface);
        border: 1px solid var(--mint-border);
        color: var(--primary-hover);
    }
    .form-group {
        margin-bottom: 14px;
    }
    .form-control {
        width: 100%;
        box-sizing: border-box;
    }
</style>

<div class="card" style="margin-bottom: 24px;">
    <div class="page-header-row">
        <div class="page-header-meta">
            <h1 class="page-title">Riwayat Stock Opname</h1>
            <span class="count-indicator">{{ $recentOpnames->total() }} total opname</span>
        </div>
        <div>
            <button type="button" class="btn" onclick="openModal('modal-opname')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px; vertical-align: -2px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Tambah Stock Opname
            </button>
        </div>
    </div>

    @include('partials.table-controls', ['search' => $search, 'perPage' => $perPage, 'placeholder' => 'Cari nomor, status, atau catatan opname...'])

    <div class="table-responsive" style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th><a class="th-sort" href="{{ request()->fullUrlWithQuery(['sort' => 'opname_number', 'dir' => ($sort === 'opname_number' && $dir === 'asc') ? 'desc' : 'asc']) }}">Nomor Opname @if($sort === 'opname_number')<span class="th-arrow">{{ $dir === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                    <th><a class="th-sort" href="{{ request()->fullUrlWithQuery(['sort' => 'opname_date', 'dir' => ($sort === 'opname_date' && $dir === 'asc') ? 'desc' : 'asc']) }}">Tanggal @if($sort === 'opname_date')<span class="th-arrow">{{ $dir === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                    <th><a class="th-sort" href="{{ request()->fullUrlWithQuery(['sort' => 'status', 'dir' => ($sort === 'status' && $dir === 'asc') ? 'desc' : 'asc']) }}">Status @if($sort === 'status')<span class="th-arrow">{{ $dir === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                    <th><a class="th-sort" href="{{ request()->fullUrlWithQuery(['sort' => 'notes', 'dir' => ($sort === 'notes' && $dir === 'asc') ? 'desc' : 'asc']) }}">Catatan @if($sort === 'notes')<span class="th-arrow">{{ $dir === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentOpnames as $op)
                <tr>
                    <td><strong class="sku-code">{{ $op->opname_number }}</strong></td>
                    <td style="color: var(--text-caption); font-size: 12px; white-space: nowrap;">{{ $op->opname_date }}</td>
                    <td><span class="badge">{{ strtoupper($op->status) }}</span></td>
                    <td>{{ $op->notes ?? '-' }}</td>
                </tr>
                @empty
                <tr><td colspan="4" style="text-align: center; color: var(--text-caption); padding: 24px;">Belum ada riwayat stock opname.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('partials.pagination', ['paginator' => $recentOpnames])
</div>

<div class="card">
    <div class="page-header-row" style="margin-bottom: 16px;">
        <div class="page-header-meta">
            <h2 class="page-title" style="font-size: 17px;">Referensi Stok Sistem Saat Ini</h2>
            <span class="count-indicator">{{ count($products) }} item material ditampilkan</span>
        </div>
    </div>
    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead style="position: sticky; top: 0; background-color: var(--surface); z-index: 2;">
                <tr>
                    <th>SKU</th>
                    <th>Nama Produk</th>
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
                        {{ rtrim(rtrim(number_format((float)$p->stock_quantity, 4, ',', '.'), '0'), ',') }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" style="text-align: center; color: var(--text-caption); padding: 20px;">Belum ada produk terdaftar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('modals')
<div class="modal-overlay" id="modal-opname" role="dialog" aria-modal="true" aria-labelledby="modal-opname-title">
    <div class="modal-dialog" style="max-width: 680px;">
        <div class="modal-header">
            <div class="modal-title-wrapper">
                <div class="modal-icon-badge">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                </div>
                <h3 class="modal-title" id="modal-opname-title">Pencatatan Stock Opname Material</h3>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('modal-opname')" aria-label="Tutup">&times;</button>
        </div>

        <form action="{{ route('opname.store') }}" method="POST" style="display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden; margin: 0;">
            @csrf
            <div class="modal-body" style="overflow-y: auto; flex: 1; padding: 20px 24px;">
                <div class="form-group">
                    <label style="font-weight: 600; margin-bottom: 8px; display: block;">Item Fisik Opname</label>
                    <div style="border: 1px solid var(--border-subtle); border-radius: var(--radius-control); overflow: hidden;">
                        <table id="opname-table" style="width: 100%; border-collapse: collapse; margin: 0;">
                            <thead>
                                <tr>
                                    <th>Produk Material</th>
                                    <th style="width: 140px;">Qty Fisik</th>
                                    <th style="width: 44px; text-align: center;"></th>
                                </tr>
                            </thead>
                            <tbody id="opname-tbody">
                                <tr class="opname-row">
                                    <td style="padding: 8px;">
                                        <select name="items[0][product_id]" class="form-control opname-product" required>
                                            <option value="">-- Pilih Produk --</option>
                                            @foreach($products as $p)
                                                <option value="{{ $p->id }}">{{ $p->name }} (Sistem: {{ rtrim(rtrim(number_format((float)$p->stock_quantity, 4, ',', '.'), '0'), ',') }} {{ $p->unit }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td style="padding: 8px;">
                                        <input type="number" step="0.0001" min="0" name="items[0][physical_qty]" class="form-control tabular-nums" placeholder="0.00" required>
                                    </td>
                                    <td style="text-align: center; vertical-align: middle; padding: 8px;">
                                        <button type="button" class="btn btn-secondary" style="min-height: 28px; padding: 2px 8px; font-size: 11px;" onclick="removeOpnameRow(this)">x</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 10px;">
                        <button type="button" class="btn btn-secondary" onclick="addOpnameRow()" style="font-size: 13px;">+ Tambah Baris</button>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 16px;">
                    <label for="notes">Catatan Opname</label>
                    <input type="text" name="notes" id="notes" class="form-control" maxlength="255" placeholder="Contoh: Audit stok berkala fisik gudang" value="{{ old('notes') }}">
                </div>
            </div>

            <div class="modal-footer" style="flex-shrink: 0; padding: 16px 24px; border-top: 1px solid var(--border-subtle); background-color: var(--surface); display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-opname')">Batal</button>
                <button type="submit" class="btn" style="font-weight: 700;">Simpan Penyesuaian Stok</button>
            </div>
        </form>
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
        <td style="padding: 8px;">
            <select name="items[${opnameIndex}][product_id]" class="form-control opname-product" required>
                ${optionsHtml}
            </select>
        </td>
        <td style="padding: 8px;">
            <input type="number" step="0.0001" min="0" name="items[${opnameIndex}][physical_qty]" class="form-control tabular-nums" placeholder="0.00" required>
        </td>
        <td style="text-align: center; vertical-align: middle; padding: 8px;">
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

@if($errors->any())
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof openModal === 'function') {
            openModal('modal-opname');
        }
    });
</script>
@endif
@endsection
