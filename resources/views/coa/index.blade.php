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
    .filter-tabs {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    .filter-tab {
        padding: 6px 16px;
        border-radius: 9999px;
        font-size: 12.5px;
        font-weight: 600;
        text-decoration: none;
        color: var(--text-muted);
        background: #ffffff;
        border: 1px solid var(--border-card);
        transition: all 0.15s ease;
    }
    .filter-tab:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    .filter-tab.active {
        background-color: var(--primary);
        color: #ffffff;
        border-color: var(--primary);
    }
    .badge-type-asset {
        background-color: #d8f3dc;
        color: #1b4332;
        border: 1px solid #b7e4c7;
    }
    .badge-type-liability {
        background-color: #fff3cd;
        color: #664d03;
        border: 1px solid #ffecb5;
    }
    .badge-type-equity {
        background-color: #e2eafc;
        color: #1d3557;
        border: 1px solid #c7d2fe;
    }
    .badge-type-revenue {
        background-color: #e8f5e9;
        color: #2d6a4f;
        border: 1px solid #a7f3d0;
    }
    .badge-type-expense {
        background-color: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    .btn-action-edit {
        background-color: var(--mint-surface);
        color: var(--primary-hover);
        border: 1px solid var(--mint-border);
        border-radius: 9999px;
        padding: 3px 12px;
        font-size: 11.5px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .btn-action-edit:hover {
        background-color: var(--primary-light);
        border-color: var(--accent);
    }
    .btn-action-delete {
        background-color: #fde8e8;
        color: var(--danger-text);
        border: 1px solid var(--danger-border);
        border-radius: 9999px;
        padding: 3px 10px;
        font-size: 11.5px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .btn-action-delete:hover {
        background-color: #fbd5d5;
    }
    .btn-action-disabled {
        background-color: #f3f4f6;
        color: #9ca3af;
        border: 1px solid #e5e7eb;
        border-radius: 9999px;
        padding: 3px 10px;
        font-size: 11.5px;
        cursor: not-allowed;
    }
</style>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-error">{{ session('error') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-error">
        <strong>Terjadi kesalahan input:</strong>
        <ul style="margin: 6px 0 0 18px; padding: 0;">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<!-- Hero Header Banner -->
<div class="card" style="margin-bottom: 22px; padding: 22px 26px; border-left: 4px solid var(--primary);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
        <div>
            <span class="badge" style="margin-bottom: 6px;">Buku Besar &amp; Akuntansi</span>
            <h1 style="font-size: 20px; font-weight: 800; color: var(--primary-hover); margin: 0 0 4px 0; letter-spacing: -0.01em;">Bagan Akun (Chart of Accounts)</h1>
            <p style="font-size: 13px; color: var(--text-muted); margin: 0; line-height: 1.5;">
                Daftar akun standar pembukuan kasir material. Seluruh transaksi kasir, inventori, ritase, dan beban operasional otomatis bermuara ke akun-akun ini.
            </p>
        </div>
        <div>
            <button type="button" class="btn" onclick="openNewModal()" style="border-radius: 9999px; padding: 10px 22px; font-size: 13.5px; font-weight: 700; box-shadow: 0 4px 14px rgba(45, 106, 79, 0.25);">+ Tambah Akun Baru</button>
        </div>
    </div>
</div>

<!-- Filter Pills -->
<div class="filter-tabs">
    <a href="{{ route('coa.index') }}" class="filter-tab {{ empty($typeFilter) ? 'active' : '' }}">
        Semua Akun ({{ count($accounts) }})
    </a>
    <a href="{{ route('coa.index', ['type' => 'asset']) }}" class="filter-tab {{ $typeFilter === 'asset' ? 'active' : '' }}">
        Aset (1xxx) <span style="opacity: 0.8; font-size: 11px;">({{ $typeCounts['asset'] ?? 0 }})</span>
    </a>
    <a href="{{ route('coa.index', ['type' => 'liability']) }}" class="filter-tab {{ $typeFilter === 'liability' ? 'active' : '' }}">
        Kewajiban (2xxx) <span style="opacity: 0.8; font-size: 11px;">({{ $typeCounts['liability'] ?? 0 }})</span>
    </a>
    <a href="{{ route('coa.index', ['type' => 'equity']) }}" class="filter-tab {{ $typeFilter === 'equity' ? 'active' : '' }}">
        Ekuitas (3xxx) <span style="opacity: 0.8; font-size: 11px;">({{ $typeCounts['equity'] ?? 0 }})</span>
    </a>
    <a href="{{ route('coa.index', ['type' => 'revenue']) }}" class="filter-tab {{ $typeFilter === 'revenue' ? 'active' : '' }}">
        Pendapatan (4/7xxx) <span style="opacity: 0.8; font-size: 11px;">({{ $typeCounts['revenue'] ?? 0 }})</span>
    </a>
    <a href="{{ route('coa.index', ['type' => 'expense']) }}" class="filter-tab {{ $typeFilter === 'expense' ? 'active' : '' }}">
        Beban (5/6/8xxx) <span style="opacity: 0.8; font-size: 11px;">({{ $typeCounts['expense'] ?? 0 }})</span>
    </a>
</div>

<!-- Full-Width Data Table Card -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2 style="margin: 0; border: none; padding: 0;">Daftar Akun Terdaftar ({{ count($accounts) }})</h2>
        <span class="badge">Tersinkronisasi Buku Besar</span>
    </div>

    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th style="width: 100px;">Kode Akun</th>
                    <th>Nama Akun</th>
                    <th style="width: 110px;">Klasifikasi</th>
                    <th style="width: 100px; text-align: center;">Saldo Normal</th>
                    <th style="width: 90px; text-align: center;">Transaksi</th>
                    <th style="width: 120px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($accounts as $acc)
                <tr>
                    <td><span class="sku-code badge">{{ $acc->account_code }}</span></td>
                    <td>
                        <div style="font-weight: 600; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                            <span>{{ $acc->account_name }}</span>
                            @if($acc->is_system)
                                <span class="badge" style="background: var(--mint-surface); color: var(--primary); border-color: var(--mint-border); font-size: 10px; padding: 1px 7px;">Inti Sistem</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        @if($acc->account_type === 'asset')
                            <span class="badge badge-type-asset">Aset</span>
                        @elseif($acc->account_type === 'liability')
                            <span class="badge badge-type-liability">Kewajiban</span>
                        @elseif($acc->account_type === 'equity')
                            <span class="badge badge-type-equity">Ekuitas</span>
                        @elseif($acc->account_type === 'revenue')
                            <span class="badge badge-type-revenue">Pendapatan</span>
                        @else
                            <span class="badge badge-type-expense">Beban</span>
                        @endif
                    </td>
                    <td style="text-align: center;">
                        <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-caption);">
                            {{ $acc->normal_balance }}
                        </span>
                    </td>
                    <td style="text-align: center;">
                        @if($acc->transaction_count > 0)
                            <span class="badge" style="background: var(--mint-surface); color: var(--primary-hover); font-variant-numeric: tabular-nums;">
                                {{ $acc->transaction_count }} jrn
                            </span>
                        @else
                            <span style="font-size: 11.5px; color: var(--text-caption);">&bull; 0</span>
                        @endif
                    </td>
                    <td style="text-align: center;">
                        <div style="display: inline-flex; gap: 6px; align-items: center;">
                            <button type="button" class="btn-action-edit"
                                data-id="{{ $acc->id }}"
                                data-code="{{ $acc->account_code }}"
                                data-name="{{ $acc->account_name }}"
                                data-type="{{ $acc->account_type }}"
                                data-balance="{{ $acc->normal_balance }}"
                                data-system="{{ $acc->is_system ? '1' : '0' }}"
                                onclick="editAccountBtn(this)">Ubah</button>

                            @if($acc->is_system)
                                <span class="btn-action-disabled" title="Akun inti sistem terlindungi dan tidak dapat dihapus">Terkunci</span>
                            @elseif($acc->transaction_count > 0)
                                <span class="btn-action-disabled" title="Akun sudah memiliki riwayat transaksi di jurnal umum dan terkunci">Hapus</span>
                            @else
                                <form method="POST" action="{{ route('coa.destroy', $acc->id) }}" style="margin: 0;" onsubmit="return confirm('Hapus akun {{ $acc->account_code }} ({{ $acc->account_name }}) dari Bagan Akun?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-action-delete">Hapus</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-caption); padding: 32px;">
                        Belum ada akun pada klasifikasi ini.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- POP-UP MODAL: Form Tambah / Ubah Akun -->
<div id="modal-coa" class="modal-overlay {{ $errors->any() ? 'open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="form-title">
    <div class="modal-dialog">
        <div class="modal-header">
            <h2 id="form-title" class="modal-title">Tambah Akun Baru</h2>
            <button type="button" class="modal-close-btn" onclick="closeModal('modal-coa')" aria-label="Tutup Dialog">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size: 12.5px; color: var(--text-muted); margin: 0 0 16px 0; line-height: 1.5;">
                Hierarki akun standar: <strong>1</strong>=Aset, <strong>2</strong>=Kewajiban, <strong>3</strong>=Ekuitas, <strong>4</strong>=Pendapatan, <strong>5</strong>=HPP, <strong>6</strong>=Beban Operasional.
            </p>

            <form id="coa-form" method="POST" action="{{ route('coa.store') }}">
                @csrf
                <input type="hidden" name="_method" id="form-method" value="POST">

                <div class="form-group">
                    <label for="account_code">Kode Akun</label>
                    <input type="text" id="account_code" name="account_code" value="{{ old('account_code') }}" required placeholder="Contoh: 1104" autocomplete="off" class="form-control tabular-nums" style="font-family: var(--font-mono);">
                </div>

                <div class="form-group">
                    <label for="account_name">Nama Akun</label>
                    <input type="text" id="account_name" name="account_name" value="{{ old('account_name') }}" required placeholder="Contoh: Kas Kecil Operasional" autocomplete="off" class="form-control">
                </div>

                <div class="form-group">
                    <label for="account_type">Tipe Klasifikasi Akun</label>
                    <select id="account_type" name="account_type" required class="form-control" onchange="autoSuggestBalance()">
                        <option value="">-- Pilih Klasifikasi --</option>
                        <option value="asset" {{ old('account_type') === 'asset' ? 'selected' : '' }}>Aset / Harta (1xxx)</option>
                        <option value="liability" {{ old('account_type') === 'liability' ? 'selected' : '' }}>Kewajiban / Utang (2xxx)</option>
                        <option value="equity" {{ old('account_type') === 'equity' ? 'selected' : '' }}>Ekuitas / Modal (3xxx)</option>
                        <option value="revenue" {{ old('account_type') === 'revenue' ? 'selected' : '' }}>Pendapatan Operasional / Lainnya (4/7xxx)</option>
                        <option value="expense" {{ old('account_type') === 'expense' ? 'selected' : '' }}>Beban HPP / Operasional (5/6/8xxx)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="normal_balance">Saldo Normal</label>
                    <select id="normal_balance" name="normal_balance" required class="form-control">
                        <option value="debit" {{ old('normal_balance') === 'debit' ? 'selected' : '' }}>Debet (Bertambah di Debet)</option>
                        <option value="credit" {{ old('normal_balance') === 'credit' ? 'selected' : '' }}>Kredit (Bertambah di Kredit)</option>
                    </select>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 24px;">
                    <button type="submit" id="btn-submit-coa" class="btn" style="flex: 1; border-radius: 9999px; min-height: 46px; font-weight: 700;">Simpan Akun</button>
                    <button type="button" class="btn btn-secondary" style="border-radius: 9999px; min-height: 46px; padding: 0 20px;" onclick="closeModal('modal-coa')">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function autoSuggestBalance() {
    const type = document.getElementById('account_type').value;
    const balance = document.getElementById('normal_balance');
    if (type === 'asset' || type === 'expense') {
        balance.value = 'debit';
    } else if (type === 'liability' || type === 'equity' || type === 'revenue') {
        balance.value = 'credit';
    }
}

function editAccountBtn(btn) {
    const id = btn.getAttribute('data-id');
    const code = btn.getAttribute('data-code');
    const name = btn.getAttribute('data-name');
    const type = btn.getAttribute('data-type');
    const balance = btn.getAttribute('data-balance');
    const isSystem = btn.getAttribute('data-system') === '1';

    const codeInput = document.getElementById('account_code');
    document.getElementById('form-title').textContent = 'Ubah Akun ' + code + (isSystem ? ' (Inti Sistem)' : '');
    document.getElementById('coa-form').action = "{{ url('/coa') }}/" + id;
    document.getElementById('form-method').value = 'PUT';

    codeInput.value = code;
    if (isSystem) {
        codeInput.readOnly = true;
        codeInput.style.backgroundColor = '#f3f4f6';
        codeInput.title = 'Nomor kode akun inti sistem terkunci agar transaksi otomatis tetap sinkron.';
    } else {
        codeInput.readOnly = false;
        codeInput.style.backgroundColor = '#ffffff';
        codeInput.title = '';
    }

    document.getElementById('account_name').value = name;
    document.getElementById('account_type').value = type;
    document.getElementById('normal_balance').value = balance;

    document.getElementById('btn-submit-coa').textContent = 'Perbarui Akun';

    openModal('modal-coa');
}

function openNewModal() {
    const codeInput = document.getElementById('account_code');
    document.getElementById('form-title').textContent = 'Tambah Akun Baru';
    document.getElementById('coa-form').action = "{{ route('coa.store') }}";
    document.getElementById('form-method').value = 'POST';

    codeInput.value = '';
    codeInput.readOnly = false;
    codeInput.style.backgroundColor = '#ffffff';
    codeInput.title = '';

    document.getElementById('account_name').value = '';
    document.getElementById('account_type').value = '';
    document.getElementById('normal_balance').value = 'debit';

    document.getElementById('btn-submit-coa').textContent = 'Simpan Akun';

    openModal('modal-coa');
}
</script>
@endsection
