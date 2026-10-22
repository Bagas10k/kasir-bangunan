@extends('layouts.app')

@section('content')
<div class="grid-2">
    <div class="card">
        <h2>Pendaftaran Armada Kendaraan</h2>
        <p style="font-size: 12.5px; color: var(--text-muted); margin-bottom: 18px; line-height: 1.5;">
            Daftarkan armada angkutan material untuk pelacakan ritase dan efisiensi operasional logistik pengiriman.
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
            <button type="submit" class="btn" style="width: 100%; border-radius: 9999px; min-height: 46px; font-weight: 700; margin-top: 6px;">Daftarkan Kendaraan</button>
        </form>
    </div>

    <div class="card">
        <h2>Daftar Armada Terdaftar ({{ count($vehicles) }})</h2>
        <div style="overflow-x: auto;">
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
                        <td><strong class="sku-code">{{ $v->license_plate }}</strong></td>
                        <td style="text-transform: capitalize;">{{ $v->vehicle_type }}</td>
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
                        <td colspan="4" style="text-align: center; color: var(--text-caption); padding: 24px;">Belum ada armada terdaftar.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
