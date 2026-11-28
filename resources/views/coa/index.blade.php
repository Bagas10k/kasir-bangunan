{{-- Panel workspace memuat halaman ini lewat fetch (?partial=1); URL penuh tetap berfungsi. --}}
@extends(request()->boolean('partial') ? 'layouts.partial' : 'layouts.app')

@php
    $coaBounded = (bool) config('v2.enabled');
    $coaWritePrefix = $coaBounded ? 'v2.coa' : 'coa';
    $typeLabels = ['asset' => 'Aset', 'liability' => 'Kewajiban', 'equity' => 'Ekuitas', 'revenue' => 'Pendapatan', 'expense' => 'Beban'];
    $formatMoney = function ($value): string {
        $decimal = \Brick\Math\BigDecimal::of((string) $value)->toScale(2, \Brick\Math\RoundingMode::HalfUp);
        $negative = $decimal->isNegative();
        $raw = ltrim((string) $decimal->abs(), '+-');
        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '00');
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole);
        $formatted = $whole . ',' . str_pad($fraction, 2, '0');
        return $negative ? '(' . $formatted . ')' : $formatted;
    };
@endphp

@section('content')
@include('partials.master-data-styles')
<style>
/* --- KASIR VISUAL THEME SCOPED OVERRIDES FOR BAGAN AKUN --- */
.coa-pos-theme { gap: 16px; }

.coa-pos-theme .card {
    background: #ffffff;
    border: 1px solid var(--border-card, #e5e7eb);
    border-radius: 14px;
    box-shadow: 0 4px 16px rgba(27, 67, 50, 0.04);
}

.coa-pos-theme .card:hover { box-shadow: 0 4px 16px rgba(27, 67, 50, 0.04); transform: none; }

.coa-pos-theme .master-head { border-radius: 14px; padding: 18px 20px; }
.coa-pos-theme .master-eyebrow { color: var(--primary, #2d6a4f); font-size: 11px; font-weight: 700; letter-spacing: .06em; }
.coa-pos-theme .master-head h1 { font-size: 20px; font-weight: 700; color: var(--text-main, #1c2826); letter-spacing: -.01em; }
.coa-pos-theme .master-head p { color: var(--text-muted, #5c6b64); font-size: 13px; line-height: 1.5; }

.coa-pos-theme .btn {
    border-radius: 9999px;
    font-size: 13px;
    font-weight: 600;
    padding: 8px 18px;
    min-height: 38px;
    background: var(--primary, #2d6a4f);
    color: #ffffff;
    border: 1px solid var(--primary, #2d6a4f);
    box-shadow: 0 3px 10px rgba(45, 106, 79, .18);
    transition: background-color .15s ease, box-shadow .15s ease, transform .15s ease;
}

.coa-pos-theme .btn:hover {
    background: var(--primary-hover, #1b4332);
    border-color: var(--primary-hover, #1b4332);
    box-shadow: 0 4px 14px rgba(45, 106, 79, .25);
    transform: translateY(-1px);
}

.coa-pos-theme .btn-secondary {
    background: var(--mint-surface, #e8f5e9);
    color: var(--primary-hover, #1b4332);
    border: 1px solid var(--mint-border, #a3b18a);
    box-shadow: none;
}

.coa-pos-theme .btn-secondary:hover {
    background: var(--primary-light, #d8f3dc);
    border-color: var(--accent, #52b788);
    box-shadow: none;
    transform: translateY(-1px);
}

.coa-pos-theme .master-summary-strip {
    border-radius: 14px;
    padding: 14px 18px;
    box-shadow: 0 3px 10px rgba(27, 67, 50, .04);
}

.coa-pos-theme .master-summary-label { color: var(--text-caption, #526359); font-size: 11px; font-weight: 700; letter-spacing: .05em; }
.coa-pos-theme .master-summary-value { color: var(--text-main, #1c2826); font-size: 20px; font-weight: 750; }

.coa-pos-theme .master-workspace { border-radius: 14px; padding: 18px 20px; }

.coa-pos-theme .btn,
.coa-pos-theme .master-head .btn,
.coa-pos-theme .master-toolbar-actions .btn {
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

.coa-pos-theme .btn:hover,
.coa-pos-theme .master-head .btn:hover,
.coa-pos-theme .master-toolbar-actions .btn:hover {
    background: var(--primary-hover, #1b4332);
    border-color: var(--primary-hover, #1b4332);
    box-shadow: 0 3px 8px rgba(45, 106, 79, 0.22);
    transform: translateY(-1px);
}

.coa-pos-theme .btn-secondary,
.coa-pos-theme .master-toolbar-actions .btn-secondary {
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

.coa-pos-theme .btn-secondary:hover,
.coa-pos-theme .master-toolbar-actions .btn-secondary:hover {
    background: var(--primary-light, #d8f3dc);
    border-color: var(--accent, #52b788);
    box-shadow: none;
    transform: translateY(-1px);
}

.coa-pos-theme .master-toolbar {
    background: #fbfdfc;
    border: 1px solid var(--border-card, #e5e7eb);
    border-radius: 12px;
    padding: 10px 14px;
    margin-bottom: 14px;
}

.coa-pos-theme .form-control {
    border-radius: 8px;
    border: 1px solid var(--border-control, #d9e2dc);
    background: #ffffff;
    font-size: 12px;
    padding: 5px 10px;
    min-height: 32px;
    height: 32px;
    color: var(--text-main, #1c2826);
    transition: border-color .15s ease, box-shadow .15s ease;
}

.coa-pos-theme .form-control:focus {
    border-color: var(--primary, #2d6a4f);
    box-shadow: 0 0 0 3px rgba(45, 106, 79, .15);
    outline: none;
}

.coa-pos-theme .master-table-wrap {
    border: 1px solid var(--border-card, #e5e7eb);
    border-radius: 12px;
    overflow-x: auto;
    background: #ffffff;
}

.coa-pos-theme .master-table { width: 100%; border-collapse: separate; border-spacing: 0; }

.coa-pos-theme .master-table th {
    background: #fbfdfc;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: var(--text-caption, #526359);
    padding: 10px 14px;
    border-bottom: 2px solid var(--border-card, #e5e7eb);
    border-top: none; border-left: none; border-right: none;
}

.coa-pos-theme .master-table td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--border-card, #e5e7eb);
    border-top: none; border-left: none; border-right: none;
    font-size: 13.5px;
    vertical-align: middle;
    color: var(--text-main, #1c2826);
    background: #ffffff;
}

.coa-pos-theme .master-table tbody tr:last-child td { border-bottom: none; }
.coa-pos-theme .master-table tbody tr:hover td { background: #f8faf9; }

/* Data link: reads as value at rest, affordance on hover/focus only */
.coa-pos-theme .master-table td .master-link {
    color: var(--text-main, #1c2826);
    text-decoration: none;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    border-bottom: 0;
}

.coa-pos-theme .master-table td .master-link:hover,
.coa-pos-theme .master-table td .master-link:focus-visible {
    color: var(--primary-hover, #1b4332);
    text-decoration: underline;
    text-underline-offset: 2px;
}

.coa-pos-theme .master-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2.5px 9px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.2;
}

.coa-pos-theme .master-status.active { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.coa-pos-theme .master-status.warning { background: #fef9c3; color: #854d0e; border: 1px solid #fef08a; }
.coa-pos-theme .master-status.critical { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
.coa-pos-theme .master-status:not(.active):not(.warning):not(.critical) {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}

.coa-pos-theme .btn-action-edit,
.coa-pos-theme .master-row-actions .master-link {
    background: var(--mint-surface, #e8f5e9);
    color: var(--primary-hover, #1b4332);
    border: 1px solid var(--mint-border, #a3b18a);
    border-radius: 9999px;
    padding: 5px 14px;
    font-size: 12px;
    font-weight: 600;
    min-height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: background-color .15s ease, border-color .15s ease;
}

.coa-pos-theme .btn-action-edit:hover,
.coa-pos-theme .master-row-actions .master-link:hover { background: var(--primary-light, #d8f3dc); border-color: var(--accent, #52b788); }

.coa-pos-theme .btn-action-delete {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    border-radius: 9999px;
    padding: 5px 14px;
    font-size: 12px;
    font-weight: 600;
    min-height: 32px;
    transition: background-color .15s ease;
}

.coa-pos-theme .btn-action-delete:hover { background: #fca5a5; }

.coa-pos-theme .btn-action-locked {
    background: #f3f4f6;
    color: #9ca3af;
    border: 1px solid #e5e7eb;
    border-radius: 9999px;
    padding: 5px 14px;
    font-size: 12px;
    font-weight: 600;
    min-height: 32px;
    cursor: not-allowed;
}

#modal-coa .modal-dialog { border-radius: 16px; border: 1px solid var(--border-card, #e5e7eb); box-shadow: 0 16px 40px rgba(27, 67, 50, .12); }
#modal-coa .modal-header { border-bottom: 1px solid var(--border-card, #e5e7eb); padding: 16px 20px; }
#modal-coa .modal-icon-badge { background: var(--mint-surface, #e8f5e9); color: var(--primary, #2d6a4f); border-radius: 10px; }
#modal-coa .modal-body { padding: 18px 20px; }
#modal-coa .form-control { border-radius: 10px; border: 1px solid var(--border-control, #d9e2dc); }
#modal-coa .form-control:focus { border-color: var(--primary, #2d6a4f); box-shadow: 0 0 0 3px rgba(45, 106, 79, .15); }
#modal-coa .master-modal-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px 16px; }
#modal-coa .form-group { min-width: 0; }
#modal-coa .form-control { width: 100%; box-sizing: border-box; min-height: 44px; font-size: 14px; }
#modal-coa .form-control[readonly] { background: #f5f8f6; color: var(--text-muted); }
#modal-coa .coa-status-label {
    display: flex; align-items: center; gap: 12px; padding: 12px 14px;
    margin: 0; min-height: 44px; border: 1px solid var(--border-control, #d9e2dc);
    border-radius: 10px; background: #f8fbf9; cursor: pointer;
    text-transform: none; letter-spacing: normal;
}
#modal-coa .coa-status-label input[type="checkbox"] {
    appearance: auto; width: 18px; height: 18px; min-width: 18px; min-height: 18px;
    flex: 0 0 18px; margin: 0; padding: 0; accent-color: var(--primary, #2d6a4f);
}
#modal-coa .coa-status-label input:focus-visible { outline: 2px solid var(--primary, #2d6a4f); outline-offset: 4px; }
#modal-coa .coa-status-label strong { display: block; font-size: 13px; color: var(--text-main, #1c2826); }
#modal-coa .coa-status-label small { display: block; margin-top: 3px; font-size: 12px; font-weight: 400; line-height: 1.5; }
#modal-coa .coa-lock-note {
    display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; margin-bottom: 14px;
    border: 1px solid #d5e3da; border-radius: 10px; background: #f4f8f5; color: #243b2e;
    font-size: 12.5px; line-height: 1.5;
}
#modal-coa .coa-lock-note[hidden] { display: none; }
#modal-coa .coa-lock-note svg { width: 16px; height: 16px; flex: 0 0 16px; margin-top: 2px; color: var(--primary, #2d6a4f); }
#modal-coa .coa-presets {
    margin-bottom: 14px; border: 1px solid var(--border-control, #d9e2dc); border-radius: 10px; background: #fbfdfc;
}
#modal-coa .coa-presets[hidden] { display: none; }
#modal-coa .coa-presets > summary {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    padding: 10px 14px; min-height: 44px; font-size: 13px; font-weight: 600; color: var(--primary-hover, #1b4332); cursor: pointer;
}
#modal-coa .coa-presets-body { padding: 0 14px 12px; display: flex; flex-direction: column; gap: 10px; }
#modal-coa .coa-preset-group-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--text-caption, #526359); margin: 0 0 6px; }
#modal-coa .coa-preset-list { display: flex; flex-wrap: wrap; gap: 6px; }
#modal-coa .coa-preset-btn {
    display: inline-flex; align-items: center; gap: 6px; min-height: 34px; padding: 5px 10px;
    border: 1px solid #cfe0d5; border-radius: 999px; background: #ffffff; color: #1c2826;
    font-size: 12px; font-weight: 500; cursor: pointer; text-align: left;
    transition: background-color .15s ease, border-color .15s ease;
}
#modal-coa .coa-preset-btn:hover { background: #eaf5ee; border-color: var(--primary, #2d6a4f); }
#modal-coa .coa-preset-btn:focus-visible { outline: 2px solid var(--primary, #2d6a4f); outline-offset: 2px; }
#modal-coa .coa-preset-code { font-weight: 700; color: var(--primary-hover, #1b4332); font-variant-numeric: tabular-nums; }
#modal-coa .coa-preset-tag { font-size: 10.5px; font-weight: 700; padding: 1px 6px; border-radius: 999px; background: #fef3c7; color: #92400e; }
#modal-coa .coa-purpose-box {
    margin-bottom: 14px; padding: 12px 14px; border: 1px solid var(--border-control, #d9e2dc);
    border-radius: 10px; background: #f8fbf9;
}
#modal-coa .coa-purpose-box[hidden] { display: none; }
#modal-coa .coa-purpose-label { display: block; font-size: 12.5px; font-weight: 700; color: var(--text-main, #1c2826); margin-bottom: 8px; }
#modal-coa .coa-purpose-btn.is-selected { background: var(--primary, #2d6a4f); border-color: var(--primary, #2d6a4f); color: #ffffff; }
#modal-coa .coa-code-row { display: flex; gap: 8px; align-items: stretch; }
#modal-coa .coa-code-row .form-control { flex: 1 1 auto; min-width: 0; }
#modal-coa .coa-code-auto { flex: 0 0 auto; white-space: nowrap; min-height: 44px; border-radius: 10px; padding: 0 12px; }
#modal-coa .coa-code-auto[hidden] { display: none; }
#modal-coa .coa-code-hint { margin: 6px 0 0; font-size: 12px; line-height: 1.45; color: var(--text-caption, #526359); }
#modal-coa .coa-code-hint.is-warn { color: #b45309; font-weight: 600; }
.coa-pos-theme .coa-badge-contra {
    display: inline-flex; align-items: center; margin-left: 6px; padding: 1px 7px; border-radius: 999px;
    background: #fef3c7; color: #92400e; border: 1px solid #fde68a; font-size: 10.5px; font-weight: 700;
}
.coa-pos-theme .coa-badge-system { color: var(--primary, #2d6a4f); font-weight: 600; }
#modal-coa select.form-control:disabled { background: #f5f8f6; color: var(--text-muted, #5c6b64); opacity: 1; cursor: not-allowed; }
#modal-coa .modal-close-btn { min-width: 44px; min-height: 44px; }
#modal-coa .modal-footer { border-top: 1px solid var(--border-card, #e5e7eb); padding: 14px 20px; }
#modal-coa .modal-footer .btn { border-radius: 9999px; padding: 8px 18px; min-height: 44px; }
#modal-coa .modal-footer .btn-secondary {
    border-radius: 9999px;
    padding: 8px 18px;
    min-height: 44px;
    background: var(--mint-surface, #e8f5e9);
    color: var(--primary-hover, #1b4332);
    border: 1px solid var(--mint-border, #a3b18a);
}

@media (max-width: 720px) {
    .coa-pos-theme { gap: 10px; }
    .coa-pos-theme .card,
    .coa-pos-theme .master-head,
    .coa-pos-theme .master-workspace { border-radius: 14px; padding: 14px; }
    .coa-pos-theme .master-table tbody tr {
        border-radius: 12px;
        border: 1px solid var(--border-card, #e5e7eb);
        box-shadow: 0 2px 8px rgba(27, 67, 50, .03);
    }
    .coa-pos-theme .master-head .btn,
    .coa-pos-theme .master-toolbar-actions > *,
    .coa-pos-theme .btn-action-edit,
    .coa-pos-theme .btn-action-delete,
    .coa-pos-theme .btn-action-locked,
    .coa-pos-theme .master-row-actions .master-link {
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    #modal-coa .modal-footer .btn { min-height: 44px; }
    #modal-coa .master-modal-grid { grid-template-columns: minmax(0, 1fr); }
    #modal-coa .modal-footer { flex-wrap: wrap; }
}
</style>
<div class="master-page coa-pos-theme" data-coa-root data-refresh-url="{{ route('coa.index', request()->except('partial')) }}">
    <p class="note" data-coa-feedback role="status" aria-live="polite" tabindex="-1" hidden></p>
    <header class="card master-head">
        <div class="master-head-copy">
            <p class="master-eyebrow">Buku besar dan struktur akuntansi</p>
            <h1>Bagan Akun (Chart of Accounts)</h1>
            <p>Kelola klasifikasi, saldo normal, status pemakaian, dan akses jurnal setiap akun tanpa mengubah histori pembukuan.</p>
        </div>
        <button type="button" class="btn" onclick="openNewAccount()">Tambah Akun</button>
    </header>

    <section class="card master-summary-strip" aria-label="Ringkasan bagan akun">
        <div class="master-summary-item"><span class="master-summary-label">Total Akun</span><strong class="master-summary-value">{{ $summary['total'] }}</strong></div>
        <div class="master-summary-item"><span class="master-summary-label">Akun Aktif</span><strong class="master-summary-value">{{ $summary['active'] }}</strong></div>
        <div class="master-summary-item"><span class="master-summary-label">Akun Arsip</span><strong class="master-summary-value">{{ $summary['inactive'] }}</strong></div>
        <div class="master-summary-item"><span class="master-summary-label">Sudah Digunakan</span><strong class="master-summary-value">{{ $summary['used'] }}</strong></div>
    </section>

    <section class="card master-workspace" aria-labelledby="coa-table-title">
        <form method="GET" action="{{ route('coa.index') }}" class="master-toolbar coa-toolbar">
            <div class="master-control master-control-search">
                <label for="coa-search">Cari akun</label>
                <input class="form-control" id="coa-search" name="search" value="{{ $search }}" placeholder="Kode atau nama akun">
            </div>
            <div class="master-control">
                <label for="coa-type-filter">Klasifikasi</label>
                <select class="form-control" id="coa-type-filter" name="type">
                    <option value="">Semua klasifikasi</option>
                    @foreach($typeLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }} ({{ $typeCounts[$value] ?? 0 }})</option>
                    @endforeach
                </select>
            </div>
            <div class="master-control">
                <label for="coa-status-filter">Status</label>
                <select class="form-control" id="coa-status-filter" name="status">
                    <option value="">Semua status</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Arsip</option>
                </select>
            </div>
            <div class="master-control">
                <label for="coa-scope-filter">Pengelolaan</label>
                <select class="form-control" id="coa-scope-filter" name="scope">
                    <option value="">Semua akun</option>
                    <option value="custom" @selected(($filters['scope'] ?? '') === 'custom')>Dapat disesuaikan ({{ $summary['custom'] ?? 0 }})</option>
                    <option value="system" @selected(($filters['scope'] ?? '') === 'system')>Terhubung sistem ({{ $summary['system'] ?? 0 }})</option>
                </select>
            </div>
            <div class="master-control">
                <label for="coa-usage-filter">Pemakaian</label>
                <select class="form-control" id="coa-usage-filter" name="usage">
                    <option value="">Semua pemakaian</option>
                    <option value="used" @selected(($filters['usage'] ?? '') === 'used')>Sudah digunakan</option>
                    <option value="unused" @selected(($filters['usage'] ?? '') === 'unused')>Belum digunakan</option>
                </select>
            </div>
            <div class="master-control">
                <label for="coa-per-page">Baris</label>
                <select class="form-control" id="coa-per-page" name="per_page">
                    @foreach([10, 25, 50] as $size)
                        <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="master-toolbar-actions">
                <button type="submit" class="btn">Terapkan</button>
                <a href="{{ route('coa.index') }}" class="btn btn-secondary">Reset</a>
            </div>
        </form>

        <div class="master-table-head">
            <h2 id="coa-table-title">Daftar Akun</h2>
            <span>{{ $accounts->firstItem() ?? 0 }}–{{ $accounts->lastItem() ?? 0 }} dari {{ $accounts->total() }} hasil</span>
        </div>

        <div class="master-table-wrap">
            <table class="master-table">
                <thead>
                    <tr>
                        <th scope="col">Akun</th><th scope="col">Klasifikasi</th><th scope="col">Saldo Normal</th>
                        <th scope="col" style="text-align:right;">Transaksi</th><th scope="col" style="text-align:right;">Saldo (Rp)</th>
                        <th scope="col">Status</th><th scope="col" style="text-align:right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $acc)
                        <tr>
                            <td data-label="Akun">
                                <div class="master-identity">
                                    <strong>{{ $acc->account_name }}</strong>
                                    <small><span class="sku-code">{{ $acc->account_code }}</span>@if($acc->is_system) · <span class="coa-badge-system">Terhubung sistem</span>@if(!empty($acc->system_role))<x-ui.info-tip title="Dipakai otomatis oleh sistem" :text="$acc->system_role" :label="'Keterangan penggunaan otomatis '.$acc->account_name" />@endif @else · Dapat disesuaikan @endif @if($acc->is_bank_account ?? false) · Rekening bank @endif</small>
                                </div>
                            </td>
                            <td data-label="Klasifikasi">{{ $typeLabels[$acc->account_type] ?? ucfirst($acc->account_type) }}</td>
                            <td data-label="Saldo Normal">{{ $acc->normal_balance === 'debit' ? 'Debet' : 'Kredit' }}@if($acc->is_contra ?? false)<span class="coa-badge-contra" data-tip="Akun pengurang bawaan sistem">Pengurang</span>@endif</td>
                            <td data-label="Transaksi" class="quantity">
                                @if($acc->transaction_count > 0)
                                    <a class="master-link" href="{{ route('journals.index', ['account' => $acc->account_code]) }}">{{ $acc->transaction_count }} transaksi</a>
                                @else
                                    <span style="color:var(--text-caption);">0</span>
                                @endif
                            </td>
                            <td data-label="Saldo (Rp)" class="money">{{ $formatMoney($acc->balance) }}</td>
                            <td data-label="Status"><span class="master-status {{ $acc->is_active ? 'active' : '' }}" data-active="{{ $acc->is_active ? '1' : '0' }}">{{ $acc->is_active ? 'Aktif' : 'Arsip' }}</span></td>
                            <td data-label="Aksi">
                                <div class="master-row-actions">
                                    @if($acc->transaction_count > 0)
                                        <a class="master-link" href="{{ route('journals.index', ['account' => $acc->account_code]) }}">Lihat Jurnal</a>
                                    @endif
                                    <button type="button" class="btn-action-edit"
                                        data-id="{{ $acc->id }}" data-code="{{ $acc->account_code }}" data-name="{{ $acc->account_name }}"
                                        data-type="{{ $acc->account_type }}" data-balance="{{ $acc->normal_balance }}"
                                        data-contra="{{ ($acc->is_contra ?? false) ? '1' : '0' }}"
                                        data-used="{{ $acc->transaction_count > 0 ? '1' : '0' }}"
                                        data-system="{{ $acc->is_system ? '1' : '0' }}"
                                        data-system-role="{{ $acc->system_role ?? '' }}"
                                        data-active="{{ $acc->is_active ? '1' : '0' }}"
                                        data-bank="{{ ($acc->is_bank_account ?? false) ? '1' : '0' }}"
                                        data-update-url="{{ route($coaWritePrefix.'.update', $acc->id) }}"
                                        onclick="editAccountBtn(this)">Ubah</button>
                                    @if(config('v2.enabled'))
                                        @if(!$acc->is_system && $acc->is_active)
                                            <form method="POST" action="{{ route('v2.coa.archive', $acc->id) }}" data-coa-archive>
                                                @csrf @method('PATCH')
                                                <button type="submit" class="btn-action-delete">Arsipkan</button>
                                            </form>
                                        @endif
                                    @elseif($acc->is_system || $acc->transaction_count > 0)
                                        <button type="button" class="btn-action-locked" onclick="showLockInfo('Hapus Terkunci', 'Akun inti atau akun yang sudah digunakan wajib dipertahankan agar histori jurnal tetap utuh. Akun non-sistem dapat diarsipkan melalui tombol Ubah.')">Terkunci</button>
                                    @else
                                        <form method="POST" action="{{ route('coa.destroy', $acc->id) }}" onsubmit="return confirm('Hapus akun ini dari Bagan Akun?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn-action-delete">Hapus</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="master-empty">Tidak ada akun yang cocok dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $accounts])
    </section>
</div>
@include('v2.finance-dna')
@endsection

@section('modals')
<div id="modal-coa" class="modal-overlay {{ $errors->any() ? 'open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="form-title">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-title-wrapper">
                <div class="modal-icon-badge" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></div>
                <div><h2 id="form-title" class="modal-title">Tambah Akun</h2><div id="form-subtitle" style="font-size:12px;color:var(--text-caption);margin-top:2px;">Struktur buku besar</div></div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('modal-coa')" aria-label="Tutup dialog">&times;</button>
        </div>
        <form id="coa-form" method="POST" action="{{ route($coaWritePrefix.'.store') }}" data-bounded="{{ $coaBounded ? '1' : '0' }}" style="display:flex;flex-direction:column;flex:1;overflow:hidden;margin:0;">
            @csrf
            <input type="hidden" name="_method" id="form-method" value="POST">
            <div class="modal-body">
                <p class="err" data-coa-error role="alert" tabindex="-1" hidden></p>
                <div class="coa-lock-note" id="coa-lock-note" hidden>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    <span id="coa-lock-text"></span>
                </div>
                @if($coaBounded)
                <div class="master-help" style="margin-bottom:14px;"><strong>Batasan fitur Bagan Akun:</strong> Anda cukup menambahkan <strong>Beban Operasional</strong> (muncul di menu Pengeluaran) atau <strong>Rekening Bank tambahan</strong> (muncul di Transfer). Akun inti dan akun pengurang sistem (<code>4103</code> Potongan Penjualan &amp; <code>4104</code> Retur Penjualan) dikelola otomatis. <x-ui.info-tip key="coa_akun_inti" label="Keterangan batasan akun sistem"/></div>
                @else
                <div class="master-help" style="margin-bottom:14px;"><strong>Tidak perlu hafal kode akun:</strong> pilih keperluan atau klasifikasi di bawah, nanti <strong>nomor kode dan arah saldo diisi otomatis</strong>. <x-ui.info-tip key="coa_akun_inti" label="Keterangan akun inti sistem"/></div>
                @endif
                <div class="coa-purpose-box" id="coa-purpose-box">
                    <span class="coa-purpose-label">Pilih jenis akun yang ingin ditambahkan (nomor akun otomatis terisi):</span>
                    <div class="coa-preset-list" role="group" aria-label="Pilih jenis akun">
                        <button type="button" class="coa-preset-btn coa-purpose-btn" data-purpose="expense" onclick="applyCoaPurpose(this)">Beban / Pengeluaran Operasional (Air, Listrik, Servis, Reimburse)</button>
                        <button type="button" class="coa-preset-btn coa-purpose-btn" data-purpose="bank" onclick="applyCoaPurpose(this)">Rekening Bank Tambahan (BCA, BRI, Mandiri)</button>
                        @if(!$coaBounded)
                        <button type="button" class="coa-preset-btn coa-purpose-btn" data-purpose="cash" onclick="applyCoaPurpose(this)">Kas Kecil / Tunai</button>
                        <button type="button" class="coa-preset-btn coa-purpose-btn" data-purpose="fixed_asset" onclick="applyCoaPurpose(this)">Aset Tetap (Kendaraan, Mesin)</button>
                        <button type="button" class="coa-preset-btn coa-purpose-btn" data-purpose="liability" onclick="applyCoaPurpose(this)">Utang / Titipan Pelanggan</button>
                        <button type="button" class="coa-preset-btn coa-purpose-btn" data-purpose="equity" onclick="applyCoaPurpose(this)">Modal / Prive Pemilik</button>
                        <button type="button" class="coa-preset-btn coa-purpose-btn" data-purpose="revenue" onclick="applyCoaPurpose(this)">Pemasukan / Pendapatan Lain</button>
                        @endif
                    </div>
                </div>
                @php
                    $presetGroups = collect($recommendedPresets ?? [])->groupBy('group');
                @endphp
                <div id="coa-used-codes" data-codes='@json($existingAccountCodes ?? [])' hidden></div>
                <div id="coa-presets-wrap">
                @if($presetGroups->isNotEmpty())
                <details class="coa-presets" id="coa-presets-box">
                    <summary><span>Atau pilih dari contoh akun siap pakai (opsional)</span><span style="font-size:12px;color:var(--text-caption);">{{ count($recommendedPresets) }} saran</span></summary>
                    <div class="coa-presets-body">
                        <p style="margin:0;font-size:12px;color:var(--text-caption);line-height:1.45;">Hanya menampilkan contoh yang belum ada di daftar Anda. Jika sudah pernah ditambahkan, saran otomatis hilang.</p>
                        @foreach($presetGroups as $groupName => $groupItems)
                        <div>
                            <p class="coa-preset-group-title">{{ $groupName }}</p>
                            <div class="coa-preset-list">
                                @foreach($groupItems as $preset)
                                <button type="button" class="coa-preset-btn"
                                    data-preset-code="{{ $preset['code'] }}"
                                    data-preset-name="{{ $preset['name'] }}"
                                    data-preset-type="{{ $preset['type'] }}"
                                    data-preset-balance="{{ $preset['balance'] }}"
                                    data-preset-contra="{{ $preset['contra'] ? '1' : '0' }}"
                                    data-preset-bank="{{ !empty($preset['bank']) ? '1' : '0' }}"
                                    onclick="applyCoaPreset(this)">
                                    <span class="coa-preset-code">{{ $preset['code'] }}</span>
                                    <span>{{ $preset['name'] }}</span>
                                </button>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                </details>
                @endif
                </div>
                <div class="master-modal-grid">
                    <div class="form-group"><label for="account_name">Nama Akun</label><input class="form-control" id="account_name" name="account_name" value="{{ old('account_name') }}" required autocomplete="off" placeholder="Mis. Beban Air PDAM atau Bank BCA"></div>
                    <div class="form-group">
                        <label for="account_type">Klasifikasi</label>
                        <select class="form-control" id="account_type" name="account_type" data-native-select required onchange="autoSuggestBalance()"><option value="">Pilih klasifikasi</option>@foreach($typeLabels as $value => $label)<option value="{{ $value }}" @selected(old('account_type') === $value)>{{ $label }}</option>@endforeach</select>
                        <input type="hidden" id="account_type_locked" disabled>
                    </div>
                    <div class="form-group">
                        <label for="account_code">Kode Akun</label>
                        <div class="coa-code-row">
                            <input class="form-control tabular-nums" id="account_code" name="account_code" value="{{ old('account_code') }}" required autocomplete="off" placeholder="Otomatis / ketik kode" oninput="onAccountCodeInput()">
                            <button type="button" class="btn btn-secondary coa-code-auto" id="coa-code-auto-btn" onclick="suggestUnusedCoaCode(true)">Otomatis</button>
                        </div>
                        <p class="coa-code-hint" id="coa-code-hint">Kode akun terisi otomatis sesuai jenis akun di atas.</p>
                    </div>
                    <div class="form-group">
                        <label for="normal_balance">Saldo Normal <x-ui.info-tip key="coa_saldo_normal" label="Penjelasan saldo normal"/></label>
                        <select class="form-control" id="normal_balance" name="normal_balance" data-native-select required><option value="debit" @selected(old('normal_balance', 'debit') === 'debit')>Debet</option><option value="credit" @selected(old('normal_balance') === 'credit')>Kredit</option></select>
                        <input type="hidden" id="normal_balance_locked" disabled>
                    </div>
                    <div class="form-group span-2" id="coa-contra-wrap" @if($coaBounded) hidden @endif>
                        <input type="hidden" name="is_contra" id="account-contra-hidden" value="0">
                        <label class="coa-status-label" for="account-contra">
                            <input type="checkbox" id="account-contra" name="is_contra" value="1" @checked(old('is_contra') === '1') onchange="onContraToggle()" aria-describedby="account-contra-help">
                            <span><strong>Akun pengurang kelompok (Kontra) — opsional <x-ui.info-tip key="coa_akun_kontra" label="Penjelasan akun pengurang (kontra)"/></strong><small id="account-contra-help">Biarkan tidak dicentang untuk akun biasa.</small></span>
                        </label>
                    </div>
                    <div class="form-group span-2">
                        <input type="hidden" name="is_active" value="0">
                        <label class="coa-status-label" for="account-active">
                            <input type="checkbox" id="account-active" name="is_active" value="1" @checked(old('is_active', '1') === '1') aria-describedby="account-active-help">
                            <span><strong>Akun aktif</strong><small id="account-active-help">Dapat dipilih pada transaksi baru.</small></span>
                        </label>
                    </div>
                    <div class="form-group span-2" id="coa-bank-wrap" hidden>
                        <input type="hidden" name="is_bank_account" value="0">
                        <label class="coa-status-label" for="account-bank">
                            <input type="checkbox" id="account-bank" name="is_bank_account" value="1" @checked(old('is_bank_account') === '1') aria-describedby="account-bank-help">
                            <span><strong>Rekening Bank (metode pembayaran Transfer)</strong><small id="account-bank-help">Otomatis muncul sebagai pilihan rekening bank di Kasir, Tagihan, Pengeluaran, Pengiriman, Penyesuaian Saldo, dan Arus Kas.</small></span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modal-coa')">Batal</button><button type="submit" id="btn-submit-coa" class="btn">Simpan Akun</button></div>
        </form>
    </div>
</div>
<script>
function setSelectLocked(selectId, hiddenId, locked) {
    var select = document.getElementById(selectId);
    var mirror = document.getElementById(hiddenId);
    if (!select || !mirror) return;
    if (locked) {
        mirror.name = select.name;
        mirror.value = select.value;
        mirror.disabled = false;
        select.disabled = true;
    } else {
        select.disabled = false;
        mirror.disabled = true;
        mirror.removeAttribute('name');
    }
}
function expectedCoaBalance(type, isContra) {
    var base = (type === 'asset' || type === 'expense') ? 'debit' : 'credit';
    if (!isContra) return base;
    return base === 'debit' ? 'credit' : 'debit';
}
function syncBankAccountField(isSystem, code) {
    var wrap = document.getElementById('coa-bank-wrap');
    var box = document.getElementById('account-bank');
    var contra = document.getElementById('account-contra');
    var type = document.getElementById('account_type').value;
    if (!wrap || !box) return;
    var isDefaultBank = code === '1102';
    var isContra = Boolean(contra && contra.checked);
    var eligible = type === 'asset' && !isContra && (!isSystem || isDefaultBank);
    wrap.hidden = !eligible;
    if (!eligible) {
        box.checked = false;
        box.onclick = null;
    } else if (isDefaultBank) {
        box.checked = true;
        box.onclick = function () { this.checked = true; return false; };
    } else {
        box.onclick = null;
    }
}
function coaUsedCodeSet() {
    var holder = document.getElementById('coa-used-codes');
    var list = [];
    if (holder && holder.dataset.codes) {
        try { list = JSON.parse(holder.dataset.codes); } catch (e) { list = []; }
    }
    return new Set(Array.isArray(list) ? list.map(String) : []);
}
function coaTypeFromCode(code) {
    var ch = String(code || '').trim().charAt(0);
    if (ch === '1') return 'asset';
    if (ch === '2') return 'liability';
    if (ch === '3') return 'equity';
    if (ch === '4' || ch === '7') return 'revenue';
    if (ch === '5' || ch === '6' || ch === '8') return 'expense';
    return '';
}
function nextUnusedCoaCode(type, purpose) {
    var used = coaUsedCodeSet();
    var plan = {
        expense: [6102, 6199],
        bank: [1104, 1189],
        cash: [1104, 1189],
        fixed_asset: [1501, 1589],
        asset: [1104, 1589],
        liability: [2102, 2199],
        equity: [3102, 3199],
        revenue: [7102, 7199],
    };
    var key = purpose && plan[purpose] ? purpose : type;
    var range = plan[key];
    if (!range) return '';
    for (var n = range[0]; n <= range[1]; n++) {
        var candidate = String(n);
        if (candidate === '1191' || candidate === '6201') continue;
        if (!used.has(candidate)) return candidate;
    }
    return '';
}
function updateCoaCodeHint() {
    var hint = document.getElementById('coa-code-hint');
    var codeEl = document.getElementById('account_code');
    var typeEl = document.getElementById('account_type');
    var bankEl = document.getElementById('account-bank');
    if (!hint || !codeEl) return;
    var isCreate = (document.getElementById('form-method')?.value || 'POST') === 'POST';
    var code = codeEl.value.trim();
    var type = typeEl ? typeEl.value : '';
    var prefixType = coaTypeFromCode(code);
    hint.classList.remove('is-warn');
    if (!code) {
        hint.textContent = 'Pilih keperluan atau klasifikasi agar kode akun terisi otomatis.';
        return;
    }
    if (isCreate && coaUsedCodeSet().has(code)) {
        hint.classList.add('is-warn');
        hint.textContent = 'Kode ' + code + ' sudah terdaftar. Tekan "Otomatis" untuk mencari nomor kosong.';
        return;
    }
    if (prefixType && type && prefixType !== type) {
        var names = { asset: 'Aset', liability: 'Kewajiban', equity: 'Ekuitas', revenue: 'Pendapatan', expense: 'Beban' };
        hint.classList.add('is-warn');
        hint.textContent = 'Awalan angka "' + code.charAt(0) + '" harus masuk klasifikasi ' + names[prefixType] + ', atau klik "Otomatis".';
        return;
    }
    if (type === 'expense' && code.charAt(0) === '6' && code !== '6201') {
        hint.textContent = 'Kode ' + code + ' termasuk Beban Operasional — otomatis tampil di pilihan menu Pengeluaran.';
    } else if (type === 'asset' && bankEl && bankEl.checked) {
        hint.textContent = 'Kode ' + code + ' ditandai sebagai Rekening Bank — tampil di metode pembayaran Transfer.';
    } else if (type) {
        var labels = { asset: 'Aset / Harta', liability: 'Kewajiban / Utang', equity: 'Modal / Ekuitas', revenue: 'Pendapatan', expense: 'Beban / Biaya' };
        hint.textContent = 'Kode ' + code + ' cocok dengan kelompok ' + labels[type] + '.';
    } else {
        hint.textContent = '';
    }
}
function setActivePurpose(purpose) {
    window._coaActivePurpose = purpose || '';
    document.querySelectorAll('.coa-purpose-btn').forEach(function (btn) {
        btn.classList.toggle('is-selected', Boolean(purpose && btn.dataset.purpose === purpose));
    });
}
function suggestUnusedCoaCode(force) {
    var codeEl = document.getElementById('account_code');
    var typeEl = document.getElementById('account_type');
    if (!codeEl || !typeEl || codeEl.readOnly) return;
    var type = typeEl.value;
    if (!type) return;
    var current = codeEl.value.trim();
    var mismatch = current !== '' && coaTypeFromCode(current) && coaTypeFromCode(current) !== type;
    if (force || !window._coaManualCode || current === '' || mismatch) {
        var next = nextUnusedCoaCode(type, window._coaActivePurpose);
        if (next) {
            codeEl.value = next;
            window._coaManualCode = false;
        }
    }
    updateCoaCodeHint();
}
function isCoaBounded() {
    return document.getElementById('coa-form')?.dataset?.bounded === '1';
}
function applyCoaPurpose(btn) {
    var purpose = btn.dataset.purpose || '';
    var map = {
        expense: { type: 'expense', bank: false },
        bank: { type: 'asset', bank: true },
        cash: { type: 'asset', bank: false },
        fixed_asset: { type: 'asset', bank: false },
        liability: { type: 'liability', bank: false },
        equity: { type: 'equity', bank: false },
        revenue: { type: 'revenue', bank: false },
    };
    var cfg = map[purpose];
    if (!cfg) return;
    setActivePurpose(purpose);
    var typeEl = document.getElementById('account_type');
    var contraEl = document.getElementById('account-contra');
    var bankEl = document.getElementById('account-bank');
    var bounded = isCoaBounded();
    if (bounded) {
        setSelectLocked('account_type', 'account_type_locked', false);
        setSelectLocked('normal_balance', 'normal_balance_locked', false);
    }
    if (typeEl && !typeEl.disabled) typeEl.value = cfg.type;
    if (contraEl) contraEl.checked = false;
    var contraHidden = document.getElementById('account-contra-hidden');
    if (contraHidden) contraHidden.value = '0';
    window._coaManualCode = false;
    autoSuggestBalance();
    if (bankEl) {
        bankEl.checked = cfg.bank;
        bankEl.onclick = (bounded && cfg.bank) ? function () { this.checked = true; return false; } : null;
    }
    syncBankAccountField(false, document.getElementById('account_code').value.trim());
    if (bounded) {
        setSelectLocked('account_type', 'account_type_locked', true);
        setSelectLocked('normal_balance', 'normal_balance_locked', true);
    }
    updateCoaCodeHint();
    document.getElementById('account_name')?.focus();
}
function onAccountCodeInput() {
    var codeEl = document.getElementById('account_code');
    var typeEl = document.getElementById('account_type');
    var bankEl = document.getElementById('account-bank');
    var val = codeEl ? codeEl.value.trim() : '';
    var bounded = isCoaBounded();
    window._coaManualCode = val !== '';
    if ((document.getElementById('form-method')?.value || 'POST') === 'POST') {
        if (bounded) {
            var ch = val.charAt(0);
            if (ch === '1' || ch === '6' || ch === '8') {
                var targetPurpose = ch === '1' ? 'bank' : 'expense';
                setActivePurpose(targetPurpose);
                setSelectLocked('account_type', 'account_type_locked', false);
                setSelectLocked('normal_balance', 'normal_balance_locked', false);
                typeEl.value = ch === '1' ? 'asset' : 'expense';
                document.getElementById('normal_balance').value = 'debit';
                if (bankEl) {
                    bankEl.checked = ch === '1';
                    bankEl.onclick = ch === '1' ? function () { this.checked = true; return false; } : null;
                }
                syncBankAccountField(false, val);
                setSelectLocked('account_type', 'account_type_locked', true);
                setSelectLocked('normal_balance', 'normal_balance_locked', true);
            } else if (val) {
                var hint = document.getElementById('coa-code-hint');
                if (hint) {
                    hint.classList.add('is-warn');
                    hint.textContent = 'Akun baru hanya boleh memakai awalan 1 (Rekening Bank) atau 6/8 (Beban Operasional). Klik "Otomatis".';
                }
                return;
            }
        } else if (typeEl && !typeEl.disabled) {
            setActivePurpose('');
            var inferred = coaTypeFromCode(val);
            if (inferred && typeEl.value !== inferred) {
                typeEl.value = inferred;
                var contraEl = document.getElementById('account-contra');
                document.getElementById('normal_balance').value = expectedCoaBalance(inferred, Boolean(contraEl && contraEl.checked));
                syncBankAccountField(false, val);
            }
        }
    }
    updateCoaCodeHint();
}
function autoSuggestBalance() {
    var type = document.getElementById('account_type').value;
    var contra = document.getElementById('account-contra');
    var isContra = Boolean(contra && contra.checked);
    document.getElementById('normal_balance').value = expectedCoaBalance(type, isContra);
    if ((document.getElementById('form-method')?.value || 'POST') === 'POST') {
        suggestUnusedCoaCode(false);
    }
    var codeEl = document.getElementById('account_code');
    syncBankAccountField(Boolean(codeEl && codeEl.dataset.isSystem === '1'), codeEl ? codeEl.value.trim() : '');
    updateCoaCodeHint();
}
function onContraToggle() {
    autoSuggestBalance();
}
function applyCoaPreset(btn) {
    var codeEl = document.getElementById('account_code');
    var nameEl = document.getElementById('account_name');
    var typeEl = document.getElementById('account_type');
    var contraEl = document.getElementById('account-contra');
    var balanceEl = document.getElementById('normal_balance');
    var bankEl = document.getElementById('account-bank');
    var bounded = isCoaBounded();
    if (bounded) {
        setSelectLocked('account_type', 'account_type_locked', false);
        setSelectLocked('normal_balance', 'normal_balance_locked', false);
    }
    var isBankPreset = btn.dataset.presetBank === '1';
    setActivePurpose(isBankPreset ? 'bank' : (btn.dataset.presetType === 'expense' ? 'expense' : ''));
    window._coaManualCode = true;
    codeEl.value = btn.dataset.presetCode || '';
    nameEl.value = btn.dataset.presetName || '';
    typeEl.value = btn.dataset.presetType || '';
    contraEl.checked = !bounded && btn.dataset.presetContra === '1';
    var contraHidden = document.getElementById('account-contra-hidden');
    if (contraHidden) contraHidden.value = contraEl.checked ? '1' : '0';
    balanceEl.value = btn.dataset.presetBalance || expectedCoaBalance(typeEl.value, contraEl.checked);
    syncBankAccountField(false, codeEl.value.trim());
    if (bankEl) {
        bankEl.checked = isBankPreset;
        bankEl.onclick = (bounded && isBankPreset) ? function () { this.checked = true; return false; } : null;
    }
    if (bounded) {
        setSelectLocked('account_type', 'account_type_locked', true);
        setSelectLocked('normal_balance', 'normal_balance_locked', true);
    }
    updateCoaCodeHint();
    var box = document.getElementById('coa-presets-box');
    if (box) box.open = false;
    nameEl.focus();
}
function resetAccountForm() {
    var bounded = isCoaBounded();
    document.getElementById('coa-form').action = "{{ route($coaWritePrefix.'.store') }}";
    var error = document.querySelector('[data-coa-error]');
    if (error) error.hidden = true;
    window.v2FormFeedback?.clear(document.getElementById('coa-form'));
    var lockNote = document.getElementById('coa-lock-note');
    if (lockNote) lockNote.hidden = true;
    var purposeBox = document.getElementById('coa-purpose-box');
    if (purposeBox) purposeBox.hidden = false;
    var autoBtn = document.getElementById('coa-code-auto-btn');
    if (autoBtn) autoBtn.hidden = false;
    setActivePurpose('');
    window._coaManualCode = false;
    var presetsBox = document.getElementById('coa-presets-box');
    if (presetsBox) { presetsBox.hidden = false; presetsBox.open = false; }
    document.getElementById('form-method').value = 'POST';
    var codeEl = document.getElementById('account_code');
    codeEl.value = '';
    codeEl.readOnly = false;
    codeEl.dataset.isSystem = '0';
    document.getElementById('account_name').value = '';
    document.getElementById('account_type').value = '';
    document.getElementById('normal_balance').value = 'debit';
    setSelectLocked('account_type', 'account_type_locked', false);
    setSelectLocked('normal_balance', 'normal_balance_locked', false);
    var contraWrap = document.getElementById('coa-contra-wrap');
    var contraEl = document.getElementById('account-contra');
    var contraHidden = document.getElementById('account-contra-hidden');
    if (contraWrap) contraWrap.hidden = bounded;
    if (contraEl) { contraEl.checked = false; contraEl.onclick = null; }
    if (contraHidden) contraHidden.value = '0';
    document.getElementById('account-active').checked = true;
    document.getElementById('account-active').disabled = false;
    document.getElementById('account-active').onclick = null;
    var bankEl = document.getElementById('account-bank');
    if (bankEl) { bankEl.checked = false; bankEl.onclick = null; }
    syncBankAccountField(false, '');
    if (bounded) {
        var defaultBtn = document.querySelector('.coa-purpose-btn[data-purpose="expense"]');
        if (defaultBtn) applyCoaPurpose(defaultBtn);
    } else {
        updateCoaCodeHint();
    }
    document.getElementById('form-title').textContent = 'Tambah Akun';
    document.getElementById('form-subtitle').textContent = bounded ? 'Tambahkan Beban Operasional atau Rekening Bank baru' : 'Tambahkan akun baru sesuai kebutuhan pembukuan';
    document.getElementById('btn-submit-coa').textContent = 'Simpan Akun';
}
function openNewAccount() { resetAccountForm(); openModal('modal-coa'); }
function editAccountBtn(btn) {
    var bounded = isCoaBounded();
    var isSystem = btn.dataset.system === '1';
    var isUsed = btn.dataset.used === '1';
    var lockStructure = isSystem || isUsed || bounded;
    document.getElementById('coa-form').action = btn.dataset.updateUrl;
    var error = document.querySelector('[data-coa-error]');
    if (error) error.hidden = true;
    window.v2FormFeedback?.clear(document.getElementById('coa-form'));
    var purposeBox = document.getElementById('coa-purpose-box');
    if (purposeBox) purposeBox.hidden = true;
    var autoBtn = document.getElementById('coa-code-auto-btn');
    if (autoBtn) autoBtn.hidden = isSystem || isUsed;
    setActivePurpose('');
    window._coaManualCode = true;
    var presetsBox = document.getElementById('coa-presets-box');
    if (presetsBox) presetsBox.hidden = true;
    var lockNote = document.getElementById('coa-lock-note');
    var lockText = document.getElementById('coa-lock-text');
    if (lockNote && lockText) {
        if (isSystem) {
            var role = btn.dataset.systemRole ? ' (' + btn.dataset.systemRole + ')' : '';
            lockText.textContent = 'Akun terhubung sistem' + role + ': kode, klasifikasi, saldo normal, dan status aktif terkunci. Nama akun tetap dapat disesuaikan.';
            lockNote.hidden = false;
        } else if (isUsed) {
            lockText.textContent = 'Akun sudah memiliki transaksi di jurnal: kode, klasifikasi, dan saldo normal terkunci demi keutuhan histori. Nama akun dan status aktif tetap dapat disesuaikan.';
            lockNote.hidden = false;
        } else {
            lockNote.hidden = true;
        }
    }
    document.getElementById('form-method').value = 'PUT';
    var codeEl = document.getElementById('account_code');
    codeEl.value = btn.dataset.code;
    codeEl.readOnly = isSystem || isUsed;
    codeEl.dataset.isSystem = isSystem ? '1' : '0';
    document.getElementById('account_name').value = btn.dataset.name;
    document.getElementById('account_type').value = btn.dataset.type;
    document.getElementById('normal_balance').value = btn.dataset.balance;
    setSelectLocked('account_type', 'account_type_locked', lockStructure);
    setSelectLocked('normal_balance', 'normal_balance_locked', lockStructure);
    var contraWrap = document.getElementById('coa-contra-wrap');
    var contraEl = document.getElementById('account-contra');
    var contraHidden = document.getElementById('account-contra-hidden');
    var isContra = btn.dataset.contra === '1';
    if (contraWrap) contraWrap.hidden = isSystem || bounded;
    if (contraEl) {
        contraEl.checked = isContra;
        contraEl.onclick = lockStructure ? function () { this.checked = isContra; return false; } : null;
    }
    if (contraHidden) contraHidden.value = isContra ? '1' : '0';
    document.getElementById('account-active').checked = btn.dataset.active === '1';
    document.getElementById('account-active').disabled = false;
    document.getElementById('account-active').onclick = isSystem ? function () { this.checked = true; return false; } : null;
    var isBank = btn.dataset.bank === '1' || btn.dataset.code === '1102';
    var bankEl = document.getElementById('account-bank');
    if (bankEl) {
        bankEl.checked = isBank;
        bankEl.onclick = (bounded && isBank) ? function () { this.checked = true; return false; } : null;
    }
    syncBankAccountField(isSystem, btn.dataset.code);
    updateCoaCodeHint();
    document.getElementById('form-title').textContent = 'Ubah Akun ' + btn.dataset.code;
    document.getElementById('form-subtitle').textContent = isSystem ? 'Terhubung sistem — nama akun dapat disesuaikan' : (isUsed ? 'Sudah dijurnal — nama & status dapat diubah' : 'Akun kustom — nama & status dapat disesuaikan');
    document.getElementById('btn-submit-coa').textContent = 'Perbarui Akun';
    openModal('modal-coa');
}
</script>
@if(config('v2.enabled'))
    @include('v2.coa-script')
@endif
@endsection
