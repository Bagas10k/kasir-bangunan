@extends('layouts.app')
@include('partials.master-data-styles')
@section('content')
@php
    $formatMoney = function ($value) {
        $decimal = \Brick\Math\BigDecimal::of((string) $value)->toScale(2, \Brick\Math\RoundingMode::HalfUp);
        $negative = $decimal->isNegative();
        [$whole, $fraction] = explode('.', ltrim((string) $decimal, '-'));
        $formatted = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole) . ',' . $fraction;
        return $negative ? '-' . $formatted : $formatted;
    };
@endphp
<!-- Header Summary Card -->
<div class="card" style="margin-bottom: 22px; padding: 22px 26px; border-left: 4px solid var(--primary);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
        <div>
            <span class="badge" style="margin-bottom: 6px;">Biaya &amp; Pengeluaran Toko</span>
            <h1 style="font-size: 20px; font-weight: 800; color: var(--primary-hover); margin: 0 0 4px 0; letter-spacing: -0.01em;">Pengeluaran Operasional</h1>
            <p style="font-size: 13px; color: var(--text-muted); margin: 0; line-height: 1.5;">
                Pencatatan kas keluar untuk operasional toko material. Setelah disimpan, jurnal dikunci agar riwayat keuangan tidak berubah diam-diam.
            </p>
        </div>
        <div>
            <button type="button" class="btn" onclick="openNewExpense()" style="padding: 9px 18px; font-size: 13.5px; font-weight: 600; box-shadow: 0 4px 14px rgba(45, 106, 79, 0.25);">+ Catat Pengeluaran Baru</button>
        </div>
    </div>
</div>

<!-- Operational Summary Cards (Current Calendar Month) -->
<div style="margin-bottom: 22px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <span style="font-size: 11px; font-weight: 700; color: var(--text-caption); text-transform: uppercase; letter-spacing: 0.05em;">Ringkasan Pengeluaran &middot; {{ $expenseSummary['period_label'] ?? 'Bulan Ini' }}</span>
        <span class="badge" style="font-size: 11px;">Bulan Berjalan</span>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px;">
        <div class="card" style="padding: 16px 20px; border: 1px solid var(--border-card); background: var(--surface);">
            <span style="display: block; font-size: 10px; font-weight: 800; color: var(--text-caption); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 6px;">Jumlah Pengeluaran</span>
            <strong style="display: block; font-size: 20px; font-weight: 700; color: var(--text-main); font-family: var(--font-mono); font-variant-numeric: tabular-nums;">{{ $expenseSummary['count'] ?? 0 }}</strong>
            <span style="display: block; font-size: 11px; color: var(--text-muted); margin-top: 4px;">Transaksi tercatat</span>
        </div>
        <div class="card" style="padding: 16px 20px; border: 1px solid var(--border-card); background: var(--surface); border-top: 3px solid var(--primary);">
            <span style="display: block; font-size: 10px; font-weight: 800; color: var(--text-caption); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 6px;">Total Uang Keluar</span>
            <strong style="display: block; font-size: 20px; font-weight: 700; color: var(--primary-hover); font-family: var(--font-mono); font-variant-numeric: tabular-nums;">Rp {{ $formatMoney($expenseSummary['total'] ?? '0.00') }}</strong>
            <span style="display: block; font-size: 11px; color: var(--text-muted); margin-top: 4px;">Total beban operasional</span>
        </div>
        <div class="card" style="padding: 16px 20px; border: 1px solid var(--border-card); background: var(--surface);">
            <span style="display: block; font-size: 10px; font-weight: 800; color: var(--text-caption); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 6px;">Dibayar Tunai</span>
            <strong style="display: block; font-size: 20px; font-weight: 700; color: var(--text-main); font-family: var(--font-mono); font-variant-numeric: tabular-nums;">Rp {{ $formatMoney($expenseSummary['cash'] ?? '0.00') }}</strong>
            <span style="display: block; font-size: 11px; color: var(--text-muted); margin-top: 4px;">Metode kas tunai</span>
        </div>
        <div class="card" style="padding: 16px 20px; border: 1px solid var(--border-card); background: var(--surface);">
            <span style="display: block; font-size: 10px; font-weight: 800; color: var(--text-caption); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 6px;">Dibayar Transfer</span>
            <strong style="display: block; font-size: 20px; font-weight: 700; color: var(--text-main); font-family: var(--font-mono); font-variant-numeric: tabular-nums;">Rp {{ $formatMoney($expenseSummary['transfer'] ?? '0.00') }}</strong>
            <span style="display: block; font-size: 11px; color: var(--text-muted); margin-top: 4px;">Metode transfer bank</span>
        </div>
    </div>
</div>

<!-- Full-Width Data Table Card -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2 style="margin: 0; border: none; padding: 0;">Daftar Pengeluaran Terakhir</h2>
        <span class="badge">Audit Beban Kasir</span>
    </div>

    <form method="GET" action="{{ route('expenses.index') }}" class="master-toolbar">
        <div class="master-control">
            <label for="expense-filter-start">Tanggal awal</label>
            <input type="date" id="expense-filter-start" name="start_date" class="form-control" value="{{ request('start_date') }}">
        </div>
        <div class="master-control">
            <label for="expense-filter-end">Tanggal akhir</label>
            <input type="date" id="expense-filter-end" name="end_date" class="form-control" value="{{ request('end_date') }}">
        </div>
        <div class="master-control">
            <label for="expense-filter-category">Kategori</label>
            <select id="expense-filter-category" name="category" class="form-control">
                <option value="">Semua kategori</option>
                @foreach($expenseCategories ?? [] as $expenseCategory)
                    <option value="{{ $expenseCategory }}" @selected(request('category') === $expenseCategory)>{{ $expenseCategory }}</option>
                @endforeach
            </select>
        </div>
        <div class="master-control">
            <label for="expense-filter-method">Metode pembayaran</label>
            <select id="expense-filter-method" name="payment_method" class="form-control">
                <option value="">Semua metode</option>
                <option value="cash" @selected(request('payment_method') === 'cash')>Kas tunai</option>
                <option value="transfer" @selected(request('payment_method') === 'transfer')>Transfer bank</option>
            </select>
        </div>
        <div class="master-toolbar-actions">
            <a href="{{ route('expenses.index') }}" class="btn btn-secondary">Reset</a>
            <button type="submit" class="btn">Terapkan</button>
        </div>
    </form>

    <div>
        <table class="master-table">
            <thead>
                <tr>
                    <th style="width: 130px;">Nomor Voucher</th>
                    <th style="width: 100px;">Tanggal</th>
                    <th>Akun Beban / Kategori</th>
                    <th>Keterangan</th>
                    <th style="text-align: right; width: 150px;">Jumlah Nominal</th>
                    <th style="width: 130px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($expenses as $ex)
                <tr>
                    <td data-label="Nomor Voucher"><strong class="sku-code">{{ $ex->expense_number }}</strong></td>
                    <td data-label="Tanggal" style="color: var(--text-caption); font-size: 12px;">{{ $ex->expense_date }}</td>
                    <td data-label="Akun Beban"><span class="badge" style="font-size: 11px;">{{ $ex->category }}</span></td>
                    <td data-label="Keterangan" style="color: var(--text-main);">{{ $ex->description ?? '-' }}</td>
                    <td data-label="Jumlah Nominal" class="money" style="text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; color: var(--primary-hover);">Rp {{ $formatMoney($ex->amount) }}</td>
                    <td data-label="Aksi">
                        @if($ex->journal_entry_id)
                        <span class="badge" title="Jurnal terposting tidak boleh diubah atau dihapus">Terkunci</span>
                        @else
                        <button type="button" class="btn-action-edit"
                            data-id="{{ $ex->id }}"
                            data-category="{{ $ex->category }}"
                            data-amount="{{ $ex->amount }}"
                            data-description="{{ $ex->description ?? '' }}"
                            data-method="{{ $ex->payment_method }}"
                            onclick="editExpenseBtn(this)">Ubah</button>
                        <form action="{{ route('expenses.destroy', $ex->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('Hapus draf pengeluaran ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-action-delete">Hapus</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-caption); padding: 32px;">Belum ada catatan pengeluaran operasional.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
function expenseModalReset() {
    document.getElementById('expense-form').action = "{{ route('expenses.store') }}";
    document.getElementById('expense-method').value = 'POST';
    document.getElementById('category').value = '';
    setCurrencyInput(document.getElementById('amount'), '');
    document.getElementById('payment_method').value = 'cash';
    document.getElementById('description').value = '';
    document.getElementById('expense-modal-title').textContent = 'Catat Pengeluaran Operasional';
    document.getElementById('btn-submit-expense').textContent = 'Simpan Pengeluaran';
}

function openNewExpense() {
    expenseModalReset();
    openModal('modal-expense');
}

function editExpenseBtn(btn) {
    document.getElementById('expense-form').action = "{{ url('/expenses') }}/" + btn.getAttribute('data-id');
    document.getElementById('expense-method').value = 'PUT';
    document.getElementById('category').value = btn.getAttribute('data-category');
    setCurrencyInput(document.getElementById('amount'), btn.getAttribute('data-amount'));
    document.getElementById('payment_method').value = btn.getAttribute('data-method');
    document.getElementById('description').value = btn.getAttribute('data-description');
    document.getElementById('expense-modal-title').textContent = 'Ubah Pengeluaran';
    document.getElementById('btn-submit-expense').textContent = 'Perbarui Pengeluaran';
    openModal('modal-expense');
}
</script>
@endsection

@section('modals')
<!-- POP-UP MODAL: Form Catat Pengeluaran Operasional -->
<div id="modal-expense" class="modal-overlay {{ $errors->any() ? 'open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="expense-modal-title">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-title-wrapper">
                <div class="modal-icon-badge">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                </div>
                <div>
                    <h2 id="expense-modal-title" class="modal-title">Catat Pengeluaran Operasional</h2>
                    <div style="font-size: 12px; color: var(--text-caption); margin-top: 2px;">Beban Kasir &amp; Operasional Toko</div>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('modal-expense')" aria-label="Tutup Dialog">&times;</button>
        </div>

        <form action="{{ route('expenses.store') }}" method="POST" id="expense-form" style="display: flex; flex-direction: column; flex: 1; overflow: hidden; margin: 0;">
            @csrf
            <input type="hidden" name="_method" id="expense-method" value="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label for="category">Akun Biaya / Kategori</label>
                    <select name="category" id="category" class="form-control" required>
                        <option value="">-- Pilih Akun Biaya Operasional --</option>
                        @if(isset($expenseAccounts) && count($expenseAccounts) > 0)
                            @foreach($expenseAccounts as $acc)
                                <option value="{{ $acc->account_code }}" {{ old('category') == $acc->account_code ? 'selected' : '' }}>
                                    {{ $acc->account_code }} - {{ $acc->account_name }}
                                </option>
                            @endforeach
                        @else
                            <option value="Operasional Toko" {{ old('category') === 'Operasional Toko' ? 'selected' : '' }}>6101 - Operasional Toko</option>
                            <option value="Listrik & Air" {{ old('category') === 'Listrik & Air' ? 'selected' : '' }}>6102 - Listrik &amp; Air</option>
                            <option value="Pemeliharaan & Perbaikan" {{ old('category') === 'Pemeliharaan & Perbaikan' ? 'selected' : '' }}>6103 - Pemeliharaan &amp; Perbaikan</option>
                            <option value="Perlengkapan & ATK" {{ old('category') === 'Perlengkapan & ATK' ? 'selected' : '' }}>6104 - Perlengkapan &amp; ATK</option>
                            <option value="Gaji & Upah Tenaga" {{ old('category') === 'Gaji & Upah Tenaga' ? 'selected' : '' }}>6105 - Gaji &amp; Upah Tenaga</option>
                            <option value="Lain-lain" {{ old('category') === 'Lain-lain' ? 'selected' : '' }}>6199 - Lain-lain</option>
                        @endif
                    </select>
                </div>

                <div class="form-group">
                    <label for="amount">Nominal Pengeluaran (Rp)</label>
                    <input type="text" inputmode="decimal" name="amount" id="amount" class="form-control tabular-nums" placeholder="0,00" value="{{ old('amount') }}" required data-currency-input data-currency-scale="2">
                </div>

                <div class="form-group">
                    <label for="payment_method">Metode Pembayaran</label>
                    <select name="payment_method" id="payment_method" class="form-control">
                        <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Kas Tunai (1101)</option>
                        <option value="transfer" {{ old('payment_method') === 'transfer' ? 'selected' : '' }}>Transfer Bank (1102)</option>
                    </select>
                    <div style="font-size: 11.5px; color: var(--text-caption); margin-top: 6px; line-height: 1.5;">
                        Saldo Kas Tunai: <strong style="color: var(--text-main);">{{ $formatMoney($cashBalance) }}</strong>
                        &middot; Saldo Bank: <strong style="color: var(--text-main);">{{ $formatMoney($bankBalance) }}</strong>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="description">Keterangan / Deskripsi</label>
                    <input type="text" name="description" id="description" class="form-control" maxlength="255" placeholder="Contoh: Pembelian token listrik toko" value="{{ old('description') }}">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-expense')">Batal</button>
                <button type="submit" id="btn-submit-expense" class="btn">Simpan Pengeluaran</button>
            </div>
        </form>
    </div>
</div>
@endsection
