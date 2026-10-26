@extends('layouts.app')

@php
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
#modal-coa .modal-footer { border-top: 1px solid var(--border-card, #e5e7eb); padding: 14px 20px; }
#modal-coa .modal-footer .btn { border-radius: 9999px; padding: 8px 18px; min-height: 38px; }
#modal-coa .modal-footer .btn-secondary {
    border-radius: 9999px;
    padding: 8px 18px;
    min-height: 38px;
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
}
</style>
<div class="master-page coa-pos-theme">
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
                                    <small><span class="sku-code">{{ $acc->account_code }}</span>@if($acc->is_system) · Akun inti sistem @endif</small>
                                </div>
                            </td>
                            <td data-label="Klasifikasi">{{ $typeLabels[$acc->account_type] ?? ucfirst($acc->account_type) }}</td>
                            <td data-label="Saldo Normal">{{ $acc->normal_balance === 'debit' ? 'Debet' : 'Kredit' }}</td>
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
                                        data-system="{{ $acc->is_system ? '1' : '0' }}" data-active="{{ $acc->is_active ? '1' : '0' }}"
                                        onclick="editAccountBtn(this)">Ubah</button>
                                    @if($acc->is_system || $acc->transaction_count > 0)
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
        <form id="coa-form" method="POST" action="{{ route('coa.store') }}" style="display:flex;flex-direction:column;flex:1;overflow:hidden;margin:0;">
            @csrf
            <input type="hidden" name="_method" id="form-method" value="POST">
            <div class="modal-body">
                <div class="master-help" style="margin-bottom:14px;"><strong>Panduan:</strong> 1=Aset, 2=Kewajiban, 3=Ekuitas, 4/7=Pendapatan, 5/6/8=Beban. Klasifikasi akun yang sudah digunakan tidak dapat diubah.</div>
                <div class="master-modal-grid">
                    <div class="form-group"><label for="account_code">Kode Akun</label><input class="form-control tabular-nums" id="account_code" name="account_code" value="{{ old('account_code') }}" required autocomplete="off"></div>
                    <div class="form-group"><label for="account_name">Nama Akun</label><input class="form-control" id="account_name" name="account_name" value="{{ old('account_name') }}" required autocomplete="off"></div>
                    <div class="form-group"><label for="account_type">Klasifikasi</label><select class="form-control" id="account_type" name="account_type" required onchange="autoSuggestBalance()"><option value="">Pilih klasifikasi</option>@foreach($typeLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="form-group"><label for="normal_balance">Saldo Normal</label><select class="form-control" id="normal_balance" name="normal_balance" required><option value="debit">Debet</option><option value="credit">Kredit</option></select></div>
                    <div class="form-group span-2">
                        <input type="hidden" name="is_active" value="0">
                        <label style="display:flex;align-items:center;gap:8px;min-height:38px;"><input type="checkbox" id="account-active" name="is_active" value="1" checked> Aktif dan dapat dipilih pada transaksi baru</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modal-coa')">Batal</button><button type="submit" id="btn-submit-coa" class="btn">Simpan Akun</button></div>
        </form>
    </div>
</div>
<script>
function autoSuggestBalance() {
    var type = document.getElementById('account_type').value;
    document.getElementById('normal_balance').value = (type === 'asset' || type === 'expense') ? 'debit' : 'credit';
}
function resetAccountForm() {
    document.getElementById('coa-form').action = "{{ route('coa.store') }}";
    document.getElementById('form-method').value = 'POST';
    document.getElementById('account_code').value = '';
    document.getElementById('account_code').readOnly = false;
    document.getElementById('account_name').value = '';
    document.getElementById('account_type').value = '';
    document.getElementById('normal_balance').value = 'debit';
    document.getElementById('account-active').checked = true;
    document.getElementById('account-active').disabled = false;
    document.getElementById('account-active').onclick = null;
    document.getElementById('form-title').textContent = 'Tambah Akun';
    document.getElementById('form-subtitle').textContent = 'Struktur buku besar';
    document.getElementById('btn-submit-coa').textContent = 'Simpan Akun';
}
function openNewAccount() { resetAccountForm(); openModal('modal-coa'); }
function editAccountBtn(btn) {
    var isSystem = btn.dataset.system === '1';
    document.getElementById('coa-form').action = "{{ url('/coa') }}/" + btn.dataset.id;
    document.getElementById('form-method').value = 'PUT';
    document.getElementById('account_code').value = btn.dataset.code;
    document.getElementById('account_code').readOnly = isSystem;
    document.getElementById('account_name').value = btn.dataset.name;
    document.getElementById('account_type').value = btn.dataset.type;
    document.getElementById('normal_balance').value = btn.dataset.balance;
    document.getElementById('account-active').checked = btn.dataset.active === '1';
    document.getElementById('account-active').disabled = false;
    document.getElementById('account-active').onclick = isSystem ? function () { this.checked = true; return false; } : null;
    document.getElementById('form-title').textContent = 'Ubah Akun ' + btn.dataset.code;
    document.getElementById('form-subtitle').textContent = isSystem ? 'Akun inti sistem tetap aktif' : 'Perbarui identitas atau status akun';
    document.getElementById('btn-submit-coa').textContent = 'Perbarui Akun';
    openModal('modal-coa');
}
</script>
@endsection
