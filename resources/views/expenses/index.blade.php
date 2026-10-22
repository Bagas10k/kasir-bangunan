@extends('layouts.app')
@section('content')
<!-- Header Summary Card -->
<div class="card" style="margin-bottom: 22px; padding: 22px 26px; border-left: 4px solid var(--primary);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
        <div>
            <span class="badge" style="margin-bottom: 6px;">Biaya &amp; Pengeluaran Toko</span>
            <h1 style="font-size: 20px; font-weight: 800; color: var(--primary-hover); margin: 0 0 4px 0; letter-spacing: -0.01em;">Pengeluaran Operasional</h1>
            <p style="font-size: 13px; color: var(--text-muted); margin: 0; line-height: 1.5;">
                Pencatatan kas keluar untuk operasional toko material. Terhubung langsung dengan akun beban di Bagan Akun (COA).
            </p>
        </div>
        <div>
            <button type="button" class="btn" onclick="openModal('modal-expense')" style="padding: 9px 18px; font-size: 13.5px; font-weight: 600; box-shadow: 0 4px 14px rgba(45, 106, 79, 0.25);">+ Catat Pengeluaran Baru</button>
        </div>
    </div>
</div>

<!-- Full-Width Data Table Card -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2 style="margin: 0; border: none; padding: 0;">Daftar Pengeluaran Terakhir</h2>
        <span class="badge">Audit Beban Kasir</span>
    </div>

    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th style="width: 130px;">Nomor Voucher</th>
                    <th style="width: 100px;">Tanggal</th>
                    <th>Akun Beban / Kategori</th>
                    <th>Keterangan</th>
                    <th style="text-align: right; width: 150px;">Jumlah Nominal</th>
                </tr>
            </thead>
            <tbody>
                @forelse($expenses as $ex)
                <tr>
                    <td><strong class="sku-code">{{ $ex->expense_number }}</strong></td>
                    <td style="color: var(--text-caption); font-size: 12px;">{{ $ex->expense_date }}</td>
                    <td><span class="badge" style="font-size: 11px;">{{ $ex->category }}</span></td>
                    <td style="color: var(--text-main);">{{ $ex->description ?? '-' }}</td>
                    <td style="text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; color: var(--primary-hover);">Rp {{ number_format((float)$ex->amount, 2, ',', '.') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: var(--text-caption); padding: 32px;">Belum ada catatan pengeluaran operasional.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- POP-UP MODAL: Form Catat Pengeluaran Operasional -->
<div id="modal-expense" class="modal-overlay {{ $errors->any() ? 'open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="expense-modal-title">
    <div class="modal-dialog">
        <div class="modal-header">
            <h2 id="expense-modal-title" class="modal-title">Catat Pengeluaran Operasional</h2>
            <button type="button" class="modal-close-btn" onclick="closeModal('modal-expense')" aria-label="Tutup Dialog">&times;</button>
        </div>
        <div class="modal-body">
            <form action="{{ route('expenses.store') }}" method="POST">
                @csrf
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
                    <input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control tabular-nums" placeholder="0.00" value="{{ old('amount') }}" required>
                </div>

                <div class="form-group">
                    <label for="payment_method">Metode Pembayaran</label>
                    <select name="payment_method" id="payment_method" class="form-control">
                        <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Kas Tunai (1101)</option>
                        <option value="transfer" {{ old('payment_method') === 'transfer' ? 'selected' : '' }}>Transfer Bank (1102)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Keterangan / Deskripsi</label>
                    <input type="text" name="description" id="description" class="form-control" maxlength="255" placeholder="Contoh: Pembelian token listrik toko" value="{{ old('description') }}">
                </div>

                <div style="display: flex; gap: 10px; margin-top: 24px;">
                    <button type="submit" class="btn" style="flex: 1; min-height: 44px; font-weight: 600;">Simpan Pengeluaran</button>
                    <button type="button" class="btn btn-secondary" style="min-height: 44px; padding: 0 20px;" onclick="closeModal('modal-expense')">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
