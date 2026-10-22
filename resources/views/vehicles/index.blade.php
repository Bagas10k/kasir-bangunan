@extends('layouts.app')

@section('content')
<!-- Header Summary Card -->
<div class="card" style="margin-bottom: 22px; padding: 22px 26px; border-left: 4px solid var(--primary);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
        <div>
            <span class="badge" style="margin-bottom: 6px;">Logistik &amp; Armada Toko</span>
            <h1 style="font-size: 20px; font-weight: 800; color: var(--primary-hover); margin: 0 0 4px 0; letter-spacing: -0.01em;">Armada Kendaraan</h1>
            <p style="font-size: 13px; color: var(--text-muted); margin: 0; line-height: 1.5;">
                Daftar kendaraan angkutan material untuk pelacakan ritase dan efisiensi operasional pengiriman barang.
            </p>
        </div>
        <div>
            <button type="button" class="btn" onclick="openModal('modal-vehicle')" style="border-radius: 9999px; padding: 10px 22px; font-size: 13.5px; font-weight: 700; box-shadow: 0 4px 14px rgba(45, 106, 79, 0.25);">+ Daftarkan Kendaraan</button>
        </div>
    </div>
</div>

<!-- Full-Width Data Table Card -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2 style="margin: 0; border: none; padding: 0;">Daftar Armada Terdaftar ({{ count($vehicles) }})</h2>
        <span class="badge">Armada Angkutan Toko</span>
    </div>

    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th style="width: 160px;">Nomor Plat Polisi</th>
                    <th>Tipe Kendaraan</th>
                    <th style="width: 140px;">Status Operasional</th>
                    <th style="width: 140px;">Tanggal Terdaftar</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vehicles as $v)
                <tr>
                    <td><strong class="sku-code badge" style="font-size: 12px;">{{ $v->license_plate }}</strong></td>
                    <td style="text-transform: capitalize; font-weight: 600; color: var(--text-main);">{{ $v->vehicle_type }}</td>
                    <td>
                        @if($v->status === 'active')
                            <span class="badge" style="background: var(--mint-surface); color: var(--primary-hover); border-color: var(--mint-border);">Aktif</span>
                        @elseif($v->status === 'maintenance')
                            <span class="badge" style="background: #fff3cd; color: #664d03; border-color: #ffecb5;">Bengkel</span>
                        @else
                            <span class="badge" style="background: #f8f9fa; color: #6c757d; border-color: #dee2e6;">Nonaktif</span>
                        @endif
                    </td>
                    <td style="color: var(--text-caption); font-size: 12px;">{{ substr($v->created_at, 0, 10) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: var(--text-caption); padding: 32px;">Belum ada armada terdaftar.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- POP-UP MODAL: Form Pendaftaran Armada Kendaraan -->
<div id="modal-vehicle" class="modal-overlay {{ $errors->any() ? 'open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="vehicle-modal-title">
    <div class="modal-dialog">
        <div class="modal-header">
            <h2 id="vehicle-modal-title" class="modal-title">Pendaftaran Armada Kendaraan</h2>
            <button type="button" class="modal-close-btn" onclick="closeModal('modal-vehicle')" aria-label="Tutup Dialog">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size: 12.5px; color: var(--text-muted); margin: 0 0 16px 0; line-height: 1.5;">
                Daftarkan armada angkutan material untuk pelacakan ritase dan operasional pengiriman.
            </p>
            <form method="POST" action="{{ route('vehicles.store') }}">
                @csrf
                <div class="form-group">
                    <label for="license_plate">Nomor Plat Polisi</label>
                    <input type="text" id="license_plate" name="license_plate" value="{{ old('license_plate') }}" required placeholder="Contoh: B 9123 PBJ" autocomplete="off" class="form-control">
                </div>
                <div class="form-group">
                    <label for="vehicle_type">Tipe Kendaraan</label>
                    <select id="vehicle_type" name="vehicle_type" required class="form-control">
                        <option value="truck" {{ old('vehicle_type') === 'truck' ? 'selected' : '' }}>Truk (Dump / Engkel)</option>
                        <option value="pickup" {{ old('vehicle_type') === 'pickup' ? 'selected' : '' }}>Pickup</option>
                        <option value="lainnya" {{ old('vehicle_type') === 'lainnya' ? 'selected' : '' }}>Lainnya</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status Operasional</label>
                    <select id="status" name="status" class="form-control">
                        <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Aktif (Siap Jalan)</option>
                        <option value="maintenance" {{ old('status') === 'maintenance' ? 'selected' : '' }}>Perawatan / Bengkel</option>
                        <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 24px;">
                    <button type="submit" class="btn" style="flex: 1; border-radius: 9999px; min-height: 46px; font-weight: 700;">Daftarkan Kendaraan</button>
                    <button type="button" class="btn btn-secondary" style="border-radius: 9999px; min-height: 46px; padding: 0 20px;" onclick="closeModal('modal-vehicle')">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
