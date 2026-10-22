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
            <h2>Catat Pengeluaran Operasional</h2>
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

                <div style="margin-top: 18px;">
                    <button type="submit" class="btn" style="width: 100%; border-radius: 9999px; min-height: 46px; font-weight: 700;">Simpan Pengeluaran</button>
                </div>
            </form>
        </div>
    </div>

    <div>
        <div class="card">
            <h2>Daftar Pengeluaran Terakhir</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Nomor</th>
                            <th>Tanggal</th>
                            <th>Kategori</th>
                            <th>Keterangan</th>
                            <th style="text-align: right;">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expenses as $ex)
                        <tr>
                            <td><strong class="sku-code">{{ $ex->expense_number }}</strong></td>
                            <td style="color: var(--text-caption); font-size: 12px;">{{ $ex->expense_date }}</td>
                            <td><span class="badge" style="font-size: 10.5px;">{{ $ex->category }}</span></td>
                            <td>{{ $ex->description ?? '-' }}</td>
                            <td style="text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; color: var(--primary-hover);">Rp {{ number_format((float)$ex->amount, 2, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align: center; color: var(--text-caption); padding: 24px;">Belum ada catatan pengeluaran operasional.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
