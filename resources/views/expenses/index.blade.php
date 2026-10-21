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
    input:focus-visible, select:focus-visible, textarea:focus-visible, button:focus-visible, a:focus-visible {
        outline: 2px solid #111;
        outline-offset: 2px;
    }
    .alert-error {
        background: #fff;
        border: 1px solid #111;
        color: #111;
        padding: 10px 14px;
        margin-bottom: 16px;
        font-size: 13px;
    }
    .alert-success {
        background: #f5f5f5;
        border: 1px solid #333;
        color: #111;
        padding: 10px 14px;
        margin-bottom: 16px;
        font-size: 13px;
    }
    .form-group {
        margin-bottom: 12px;
    }
    .form-group label {
        display: block;
        margin-bottom: 4px;
        font-weight: bold;
        font-size: 12px;
        text-transform: uppercase;
        color: #222;
    }
    .form-control {
        width: 100%;
        box-sizing: border-box;
    }
</style>

@if(session('success'))
    <div class="alert-success" role="status">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert-error" role="alert">{{ session('error') }}</div>
@endif

@if($errors->any())
    <div class="alert-error" role="alert">
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
                    <label for="category">Kategori Biaya</label>
                    <select name="category" id="category" class="form-control" required>
                        <option value="">-- Pilih Kategori --</option>
                        <option value="Operasional Toko" {{ old('category') === 'Operasional Toko' ? 'selected' : '' }}>Operasional Toko</option>
                        <option value="Listrik & Air" {{ old('category') === 'Listrik & Air' ? 'selected' : '' }}>Listrik & Air</option>
                        <option value="Pemeliharaan & Perbaikan" {{ old('category') === 'Pemeliharaan & Perbaikan' ? 'selected' : '' }}>Pemeliharaan & Perbaikan</option>
                        <option value="Perlengkapan & ATK" {{ old('category') === 'Perlengkapan & ATK' ? 'selected' : '' }}>Perlengkapan & ATK</option>
                        <option value="Gaji & Upah Tenaga" {{ old('category') === 'Gaji & Upah Tenaga' ? 'selected' : '' }}>Gaji & Upah Tenaga</option>
                        <option value="Lain-lain" {{ old('category') === 'Lain-lain' ? 'selected' : '' }}>Lain-lain</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="amount">Nominal Pengeluaran (Rp)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control" placeholder="0.00" value="{{ old('amount') }}" required>
                </div>

                <div class="form-group">
                    <label for="payment_method">Metode Pembayaran</label>
                    <select name="payment_method" id="payment_method" class="form-control">
                        <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Kas Tunai (1101)</option>
                        <option value="transfer" {{ old('payment_method') === 'transfer' ? 'selected' : '' }}>Transfer Bank</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Keterangan / Deskripsi</label>
                    <input type="text" name="description" id="description" class="form-control" maxlength="255" placeholder="Contoh: Pembelian token listrik toko" value="{{ old('description') }}">
                </div>

                <div style="margin-top: 16px;">
                    <button type="submit" class="btn" style="width: 100%; padding: 10px; font-weight: bold;">Simpan Pengeluaran</button>
                </div>
            </form>
        </div>
    </div>

    <div>
        <div class="card">
            <h2>Daftar Pengeluaran Terakhir</h2>
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
                        <td><strong>{{ $ex->expense_number }}</strong></td>
                        <td>{{ $ex->expense_date }}</td>
                        <td>{{ $ex->category }}</td>
                        <td>{{ $ex->description ?? '-' }}</td>
                        <td style="text-align: right; font-weight: bold;">Rp {{ number_format($ex->amount, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5">Belum ada catatan pengeluaran operasional.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
