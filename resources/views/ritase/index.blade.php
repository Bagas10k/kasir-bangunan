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

                <div class="grid-2" style="gap: 12px; margin-bottom: 0;">
                    <div class="form-group">
                        <label for="fuel_cost">Biaya BBM (Rp)</label>
                        <input type="number" step="0.01" min="0" name="fuel_cost" id="fuel_cost" class="form-control tabular-nums" placeholder="0.00" value="{{ old('fuel_cost', '0') }}">
                    </div>
                    <div class="form-group">
                        <label for="driver_allowance">Uang Jalan Sopir (Rp)</label>
                        <input type="number" step="0.01" min="0" name="driver_allowance" id="driver_allowance" class="form-control tabular-nums" placeholder="0.00" value="{{ old('driver_allowance', '0') }}">
                    </div>
                </div>

                <div style="margin-top: 18px;">
                    <button type="submit" class="btn" style="width: 100%; border-radius: 9999px; min-height: 46px; font-weight: 700;">Simpan Data Ritase</button>
                </div>
            </form>
        </div>
    </div>

    <div>
        <div class="card">
            <h2>Riwayat Ritase Pengiriman</h2>
            <div style="max-height: 520px; overflow-y: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Nomor</th>
                            <th>Tanggal</th>
                            <th>Kendaraan / Sopir</th>
                            <th>Tujuan &amp; Muatan</th>
                            <th style="text-align: right;">Total Biaya</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ritases as $r)
                        <tr>
                            <td><strong class="sku-code">{{ $r->ritase_number }}</strong></td>
                            <td style="color: var(--text-caption); font-size: 12px;">{{ $r->ritase_date }}</td>
                            <td>
                                <div style="font-weight: 600;">Kendaraan #{{ $r->vehicle_id }}</div>
                                <div style="font-size: 11.5px; color: var(--text-muted);">{{ $r->driver_name }}</div>
                            </td>
                            <td>
                                <div>{{ $r->destination }}</div>
                                @if($r->cargo_summary)
                                    <div style="font-size: 11px; color: var(--text-caption);">Muatan: {{ $r->cargo_summary }}</div>
                                @endif
                                @if(!empty($r->linked_invoice_number))
                                    <div style="font-size: 11px; color: var(--primary); font-weight: 700;">Nota: {{ $r->linked_invoice_number }}</div>
                                @elseif(!empty($r->sale_id))
                                    <div style="font-size: 11px; color: var(--primary);">Nota #{{ $r->sale_id }}</div>
                                @else
                                    <div style="font-size: 11px; color: var(--text-caption);">(Mandiri)</div>
                                @endif
                            </td>
                            <td style="text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; color: var(--primary-hover);">Rp {{ number_format((float)$r->total_cost, 2, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align: center; color: var(--text-caption); padding: 24px;">Belum ada catatan ritase armada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
