@extends('layouts.app')

@section('content')
<div class="grid-2">
    <div class="card">
        <h2>Pendaftaran Armada Kendaraan</h2>
        <p style="font-size:12px; color:#555; margin-bottom:16px;">
            Daftarkan armada angkutan material untuk pelacakan ritase dan operasional pengiriman.
        </p>
        <form method="POST" action="{{ route('vehicles.store') }}">
            @csrf
            <div class="form-group">
                <label for="license_plate">Nomor Plat Polisi</label>
                <input type="text" id="license_plate" name="license_plate" value="{{ old('license_plate') }}" required placeholder="Kode kendaraan" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="vehicle_type">Tipe Kendaraan</label>
                <select id="vehicle_type" name="vehicle_type" required>
                    <option value="truck" {{ old('vehicle_type') === 'truck' ? 'selected' : '' }}>Truk (Dump / Engkel)</option>
                    <option value="pickup" {{ old('vehicle_type') === 'pickup' ? 'selected' : '' }}>Pickup</option>
                    <option value="lainnya" {{ old('vehicle_type') === 'lainnya' ? 'selected' : '' }}>Lainnya</option>
                </select>
            </div>
            <div class="form-group">
                <label for="status">Status Operasional</label>
                <select id="status" name="status">
                    <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Aktif (Siap Jalan)</option>
                    <option value="maintenance" {{ old('status') === 'maintenance' ? 'selected' : '' }}>Perawatan / Bengkel</option>
                    <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
            <button type="submit" class="btn" style="width:100%;">Daftarkan Kendaraan</button>
        </form>
    </div>

    <div class="card">
        <h2>Daftar Armada Terdaftar ({{ count($vehicles) }})</h2>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Nomor Plat</th>
                        <th>Tipe</th>
                        <th>Status</th>
                        <th>Terdaftar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vehicles as $v)
                    <tr>
                        <td><strong>{{ $v->license_plate }}</strong></td>
                        <td style="text-transform:capitalize;">{{ $v->vehicle_type }}</td>
                        <td>
                            @if($v->status === 'active')
                                <span class="badge" style="background:#eefbee; color:#0f5132; border-color:#badbcc;">Aktif</span>
                            @elseif($v->status === 'maintenance')
                                <span class="badge" style="background:#fff3cd; color:#664d03; border-color:#ffecb5;">Bengkel</span>
                            @else
                                <span class="badge" style="background:#f8f9fa; color:#6c757d; border-color:#dee2e6;">Nonaktif</span>
                            @endif
                        </td>
                        <td style="color:#666; font-size:12px;">{{ substr($v->created_at, 0, 10) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align:center; color:#777; padding:20px;">Belum ada armada terdaftar.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
