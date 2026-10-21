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
            <h2>Catat Log Ritase Pengiriman</h2>
            <form action="{{ route('ritase.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="vehicle_id">Armada Kendaraan</label>
                    <select name="vehicle_id" id="vehicle_id" class="form-control" required>
                        <option value="">-- Pilih Kendaraan --</option>
                        @foreach($vehicles as $v)
                            <option value="{{ $v->id }}" {{ old('vehicle_id') == $v->id ? 'selected' : '' }}>
                                {{ $v->license_plate }} ({{ strtoupper($v->vehicle_type) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="sale_id">Kaitan Nota Penjualan (Opsional)</label>
                    <select name="sale_id" id="sale_id" class="form-control">
                        <option value="">-- Angkutan Mandiri / Standalone --</option>
                        @if(isset($sales))
                            @foreach($sales as $s)
                                <option value="{{ $s->id }}" {{ old('sale_id') == $s->id ? 'selected' : '' }}>
                                    {{ $s->invoice_number }} (Rp {{ number_format($s->total_amount, 0, ',', '.') }})
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <div class="form-group">
                    <label for="driver_name">Nama Pengemudi (Sopir)</label>
                    <input type="text" name="driver_name" id="driver_name" class="form-control" maxlength="100" placeholder="Nama sopir" value="{{ old('driver_name') }}" required>
                </div>

                <div class="form-group">
                    <label for="destination">Tujuan Pengiriman</label>
                    <input type="text" name="destination" id="destination" class="form-control" maxlength="255" placeholder="Alamat / lokasi proyek tujuan" value="{{ old('destination') }}" required>
                </div>

                <div class="form-group">
                    <label for="cargo_summary">Ringkasan Muatan Material</label>
                    <input type="text" name="cargo_summary" id="cargo_summary" class="form-control" maxlength="255" placeholder="Contoh: Semen 50 sak, Pasir 1 rit" value="{{ old('cargo_summary') }}">
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label for="fuel_cost">Biaya BBM (Rp)</label>
                        <input type="number" step="0.01" min="0" name="fuel_cost" id="fuel_cost" class="form-control" placeholder="0.00" value="{{ old('fuel_cost', '0') }}">
                    </div>
                    <div class="form-group">
                        <label for="driver_allowance">Uang Jalan Sopir (Rp)</label>
                        <input type="number" step="0.01" min="0" name="driver_allowance" id="driver_allowance" class="form-control" placeholder="0.00" value="{{ old('driver_allowance', '0') }}">
                    </div>
                </div>

                <div style="margin-top: 16px;">
                    <button type="submit" class="btn" style="width: 100%; padding: 10px; font-weight: bold;">Simpan Data Ritase</button>
                </div>
            </form>
        </div>
    </div>

    <div>
        <div class="card">
            <h2>Riwayat Ritase Pengiriman</h2>
            <div style="max-height: 480px; overflow-y: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Nomor</th>
                            <th>Tanggal</th>
                            <th>Kendaraan / Sopir</th>
                            <th>Tujuan / Muatan / Nota</th>
                            <th style="text-align: right;">Total Biaya</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ritases as $r)
                        <tr>
                            <td><strong>{{ $r->ritase_number }}</strong></td>
                            <td>{{ $r->ritase_date }}</td>
                            <td>
                                Kendaraan #{{ $r->vehicle_id }}<br>
                                <span style="color: #555;">{{ $r->driver_name }}</span>
                            </td>
                            <td>
                                {{ $r->destination }}
                                @if($r->cargo_summary)
                                    <br><span style="font-size: 11px; color: #555;">Muatan: {{ $r->cargo_summary }}</span>
                                @endif
                                @if(!empty($r->linked_invoice_number))
                                    <br><span style="font-size: 11px; color: #0b69a3; font-weight: bold;">Nota: {{ $r->linked_invoice_number }}</span>
                                @elseif(!empty($r->sale_id))
                                    <br><span style="font-size: 11px; color: #0b69a3;">Nota #{{ $r->sale_id }}</span>
                                @else
                                    <br><span style="font-size: 11px; color: #888;">(Mandiri)</span>
                                @endif
                            </td>
                            <td style="text-align: right; font-weight: bold;">Rp {{ number_format($r->total_cost, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5">Belum ada catatan ritase armada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
