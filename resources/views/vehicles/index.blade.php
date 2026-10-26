@extends('layouts.app')

@section('content')
@include('partials.master-data-styles')
<style>
/* --- KASIR VISUAL THEME SCOPED OVERRIDES FOR MASTER KENDARAAN --- */
.vehicle-pos-theme { gap: 16px; }

.vehicle-pos-theme .card {
    background: #ffffff;
    border: 1px solid var(--border-card, #e5e7eb);
    border-radius: 14px;
    box-shadow: 0 4px 16px rgba(27, 67, 50, 0.04);
}

.vehicle-pos-theme .card:hover { box-shadow: 0 4px 16px rgba(27, 67, 50, 0.04); transform: none; }

.vehicle-pos-theme .master-head { border-radius: 14px; padding: 18px 20px; }
.vehicle-pos-theme .master-eyebrow { color: var(--primary, #2d6a4f); font-size: 11px; font-weight: 700; letter-spacing: .06em; }
.vehicle-pos-theme .master-head h1 { font-size: 20px; font-weight: 700; color: var(--text-main, #1c2826); letter-spacing: -.01em; }
.vehicle-pos-theme .master-head p { color: var(--text-muted, #5c6b64); font-size: 13px; line-height: 1.5; }

.vehicle-pos-theme .btn {
    border-radius: 9999px;
    font-size: 13px;
    font-weight: 600;
    padding: 8px 18px;
    min-height: 38px;
    background: var(--primary, #2d6a4f);
    color: #ffffff;
    border: 1px solid var(--primary, #2d6a4f);
    box-shadow: 0 3px 10px rgba(45, 106, 79, .18);
    transition: background-color .15s ease, box-shadow .15s ease, transform .15s ease;
}

.vehicle-pos-theme .btn:hover {
    background: var(--primary-hover, #1b4332);
    border-color: var(--primary-hover, #1b4332);
    box-shadow: 0 4px 14px rgba(45, 106, 79, .25);
    transform: translateY(-1px);
}

.vehicle-pos-theme .btn-secondary {
    background: var(--mint-surface, #e8f5e9);
    color: var(--primary-hover, #1b4332);
    border: 1px solid var(--mint-border, #a3b18a);
    box-shadow: none;
}

.vehicle-pos-theme .btn-secondary:hover {
    background: var(--primary-light, #d8f3dc);
    border-color: var(--accent, #52b788);
    box-shadow: none;
    transform: translateY(-1px);
}

.vehicle-pos-theme .master-summary-strip {
    border-radius: 14px;
    padding: 14px 18px;
    box-shadow: 0 3px 10px rgba(27, 67, 50, .04);
}

.vehicle-pos-theme .master-summary-label { color: var(--text-caption, #526359); font-size: 11px; font-weight: 700; letter-spacing: .05em; }
.vehicle-pos-theme .master-summary-value { color: var(--text-main, #1c2826); font-size: 20px; font-weight: 750; }

.vehicle-pos-theme .master-workspace { border-radius: 14px; padding: 18px 20px; }

.vehicle-pos-theme .btn,
.vehicle-pos-theme .master-head .btn,
.vehicle-pos-theme .master-toolbar-actions .btn {
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 600;
    padding: 6px 16px;
    min-height: 32px;
    background: var(--primary, #2d6a4f);
    color: #ffffff;
    border: 1px solid var(--primary, #2d6a4f);
    box-shadow: 0 2px 6px rgba(45, 106, 79, 0.15);
    transition: background-color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
}

.vehicle-pos-theme .btn:hover,
.vehicle-pos-theme .master-head .btn:hover,
.vehicle-pos-theme .master-toolbar-actions .btn:hover {
    background: var(--primary-hover, #1b4332);
    border-color: var(--primary-hover, #1b4332);
    box-shadow: 0 3px 8px rgba(45, 106, 79, 0.22);
    transform: translateY(-1px);
}

.vehicle-pos-theme .btn-secondary,
.vehicle-pos-theme .master-toolbar-actions .btn-secondary {
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 600;
    padding: 6px 14px;
    min-height: 32px;
    background: var(--mint-surface, #e8f5e9);
    color: var(--primary-hover, #1b4332);
    border: 1px solid var(--mint-border, #a3b18a);
    box-shadow: none;
}

.vehicle-pos-theme .btn-secondary:hover,
.vehicle-pos-theme .master-toolbar-actions .btn-secondary:hover {
    background: var(--primary-light, #d8f3dc);
    border-color: var(--accent, #52b788);
    box-shadow: none;
    transform: translateY(-1px);
}

.vehicle-pos-theme .master-toolbar {
    background: #fbfdfc;
    border: 1px solid var(--border-card, #e5e7eb);
    border-radius: 12px;
    padding: 10px 14px;
    margin-bottom: 14px;
}

.vehicle-pos-theme .form-control {
    border-radius: 8px;
    border: 1px solid var(--border-control, #d9e2dc);
    background: #ffffff;
    font-size: 12px;
    padding: 5px 10px;
    min-height: 32px;
    height: 32px;
    color: var(--text-main, #1c2826);
    transition: border-color .15s ease, box-shadow .15s ease;
}

.vehicle-pos-theme .form-control:focus {
    border-color: var(--primary, #2d6a4f);
    box-shadow: 0 0 0 3px rgba(45, 106, 79, .15);
    outline: none;
}

.vehicle-pos-theme .master-table-wrap {
    border: 1px solid var(--border-card, #e5e7eb);
    border-radius: 12px;
    overflow-x: auto;
    background: #ffffff;
}

.vehicle-pos-theme .master-table { width: 100%; border-collapse: separate; border-spacing: 0; }

.vehicle-pos-theme .master-table th {
    background: #fbfdfc;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: var(--text-caption, #526359);
    padding: 10px 14px;
    border-bottom: 2px solid var(--border-card, #e5e7eb);
    border-top: none; border-left: none; border-right: none;
}

.vehicle-pos-theme .master-table td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--border-card, #e5e7eb);
    border-top: none; border-left: none; border-right: none;
    font-size: 13.5px;
    vertical-align: middle;
    color: var(--text-main, #1c2826);
    background: #ffffff;
}

.vehicle-pos-theme .master-table tbody tr:last-child td { border-bottom: none; }
.vehicle-pos-theme .master-table tbody tr:hover td { background: #f8faf9; }

.vehicle-pos-theme .master-table td .master-link {
    color: var(--text-main, #1c2826);
    text-decoration: none;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    border-bottom: 0;
}

.vehicle-pos-theme .master-table td .master-link:hover,
.vehicle-pos-theme .master-table td .master-link:focus-visible {
    color: var(--primary-hover, #1b4332);
    text-decoration: underline;
    text-underline-offset: 2px;
}

.vehicle-pos-theme .master-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2.5px 9px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.2;
}

.vehicle-pos-theme .master-status.active { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.vehicle-pos-theme .master-status.warning { background: #fef9c3; color: #854d0e; border: 1px solid #fef08a; }
.vehicle-pos-theme .master-status.critical { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

.vehicle-pos-theme .btn-action-edit,
.vehicle-pos-theme .master-row-actions .master-link {
    background: var(--mint-surface, #e8f5e9);
    color: var(--primary-hover, #1b4332);
    border: 1px solid var(--mint-border, #a3b18a);
    border-radius: 9999px;
    padding: 5px 14px;
    font-size: 12px;
    font-weight: 600;
    min-height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: background-color .15s ease, border-color .15s ease;
}

.vehicle-pos-theme .btn-action-edit:hover,
.vehicle-pos-theme .master-row-actions .master-link:hover { background: var(--primary-light, #d8f3dc); border-color: var(--accent, #52b788); }

.vehicle-pos-theme .btn-action-delete {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    border-radius: 9999px;
    padding: 5px 14px;
    font-size: 12px;
    font-weight: 600;
    min-height: 32px;
    transition: background-color .15s ease;
}

.vehicle-pos-theme .btn-action-delete:hover { background: #fca5a5; }

.vehicle-pos-theme .btn-action-locked {
    background: #f3f4f6;
    color: #9ca3af;
    border: 1px solid #e5e7eb;
    border-radius: 9999px;
    padding: 5px 14px;
    font-size: 12px;
    font-weight: 600;
    min-height: 32px;
    cursor: not-allowed;
}

#modal-vehicle .modal-dialog { border-radius: 16px; border: 1px solid var(--border-card, #e5e7eb); box-shadow: 0 16px 40px rgba(27, 67, 50, .12); }
#modal-vehicle .modal-header { border-bottom: 1px solid var(--border-card, #e5e7eb); padding: 16px 20px; }
#modal-vehicle .modal-icon-badge { background: var(--mint-surface, #e8f5e9); color: var(--primary, #2d6a4f); border-radius: 10px; }
#modal-vehicle .modal-body { padding: 18px 20px; }
#modal-vehicle .form-control { border-radius: 10px; border: 1px solid var(--border-control, #d9e2dc); }
#modal-vehicle .form-control:focus { border-color: var(--primary, #2d6a4f); box-shadow: 0 0 0 3px rgba(45, 106, 79, .15); }
#modal-vehicle .modal-footer { border-top: 1px solid var(--border-card, #e5e7eb); padding: 14px 20px; }
#modal-vehicle .modal-footer .btn { border-radius: 9999px; padding: 8px 18px; min-height: 38px; }
#modal-vehicle .modal-footer .btn-secondary {
    border-radius: 9999px;
    padding: 8px 18px;
    min-height: 38px;
    background: var(--mint-surface, #e8f5e9);
    color: var(--primary-hover, #1b4332);
    border: 1px solid var(--mint-border, #a3b18a);
}

@media (max-width: 720px) {
    .vehicle-pos-theme { gap: 10px; }
    .vehicle-pos-theme .card,
    .vehicle-pos-theme .master-head,
    .vehicle-pos-theme .master-workspace { border-radius: 14px; padding: 14px; }
    .vehicle-pos-theme .master-table tbody tr {
        border-radius: 12px;
        border: 1px solid var(--border-card, #e5e7eb);
        box-shadow: 0 2px 8px rgba(27, 67, 50, .03);
    }
    .vehicle-pos-theme .master-head .btn,
    .vehicle-pos-theme .master-toolbar-actions > *,
    .vehicle-pos-theme .btn-action-edit,
    .vehicle-pos-theme .btn-action-delete,
    .vehicle-pos-theme .btn-action-locked,
    .vehicle-pos-theme .master-row-actions .master-link {
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    #modal-vehicle .modal-footer .btn { min-height: 44px; }
}
</style>
<div class="master-page vehicle-pos-theme">
    <header class="card master-head">
        <div class="master-head-copy">
            <p class="master-eyebrow">Logistik dan kesiapan armada</p>
            <h1>Master Kendaraan</h1>
            <p>Pantau kesiapan kendaraan, jumlah ritase, status perawatan, dan histori operasional sebelum armada dipilih untuk perjalanan baru.</p>
        </div>
        <button type="button" class="btn" onclick="openNewVehicle()">Daftarkan Kendaraan</button>
    </header>

    <section class="card master-summary-strip" aria-label="Ringkasan armada">
        <div class="master-summary-item"><span class="master-summary-label">Total Armada</span><strong class="master-summary-value">{{ $summary['total'] }}</strong></div>
        <div class="master-summary-item"><span class="master-summary-label">Siap Jalan</span><strong class="master-summary-value">{{ $summary['active'] }}</strong></div>
        <div class="master-summary-item"><span class="master-summary-label">Dalam Perawatan</span><strong class="master-summary-value">{{ $summary['maintenance'] }}</strong></div>
        <div class="master-summary-item"><span class="master-summary-label">Nonaktif</span><strong class="master-summary-value">{{ $summary['inactive'] }}</strong></div>
    </section>

    <section class="card master-workspace" aria-labelledby="vehicles-table-title">
        <form method="GET" action="{{ route('vehicles.index') }}" class="master-toolbar vehicle-toolbar">
            <div class="master-control master-control-search">
                <label for="vehicle-search">Cari armada</label>
                <input class="form-control" id="vehicle-search" name="search" value="{{ $search }}" placeholder="Nomor plat kendaraan">
            </div>
            <div class="master-control">
                <label for="vehicle-status-filter">Status operasional</label>
                <select class="form-control" id="vehicle-status-filter" name="status">
                    <option value="">Semua status</option>
                    <option value="active" @selected(($validated['status'] ?? '') === 'active')>Siap jalan</option>
                    <option value="maintenance" @selected(($validated['status'] ?? '') === 'maintenance')>Dalam perawatan</option>
                    <option value="inactive" @selected(($validated['status'] ?? '') === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <div class="master-control">
                <label for="vehicle-per-page">Baris</label>
                <select class="form-control" id="vehicle-per-page" name="per_page">
                    @foreach([10, 25, 50] as $size)
                        <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="master-toolbar-actions">
                <button type="submit" class="btn">Terapkan</button>
                <a href="{{ route('vehicles.index') }}" class="btn btn-secondary">Reset</a>
            </div>
        </form>

        <div class="master-table-head">
            <h2 id="vehicles-table-title">Daftar Armada</h2>
            <span>{{ $vehicles->firstItem() ?? 0 }}–{{ $vehicles->lastItem() ?? 0 }} dari {{ $vehicles->total() }} hasil</span>
        </div>

        <div class="master-table-wrap">
            <table class="master-table">
                <thead><tr><th scope="col">Kendaraan</th><th scope="col">Jenis</th><th scope="col">Status</th><th scope="col" style="text-align:right;">Total Ritase</th><th scope="col">Terdaftar</th><th scope="col" style="text-align:right;">Aksi</th></tr></thead>
                <tbody>
                    @forelse($vehicles as $v)
                        <tr>
                            <td data-label="Kendaraan"><div class="master-identity"><strong class="sku-code">{{ $v->license_plate }}</strong><small>ID armada {{ $v->id }}</small></div></td>
                            <td data-label="Jenis" style="text-transform:capitalize;">{{ $v->vehicle_type }}</td>
                            <td data-label="Status">
                                <span class="master-status {{ $v->status === 'active' ? 'active' : ($v->status === 'maintenance' ? 'warning' : '') }}">
                                    {{ $v->status === 'active' ? 'Siap Jalan' : ($v->status === 'maintenance' ? 'Dalam Perawatan' : 'Nonaktif') }}
                                </span>
                            </td>
                            <td data-label="Total Ritase" class="quantity">
                                <a class="master-link" href="{{ route('ritase.index', ['vehicle_id' => $v->id]) }}" data-ritase-count="{{ $v->ritase_count }}">{{ $v->ritase_count }} ritase</a>
                            </td>
                            <td data-label="Terdaftar">{{ \Illuminate\Support\Carbon::parse($v->created_at)->format('d/m/Y') }}</td>
                            <td data-label="Aksi">
                                <div class="master-row-actions">
                                    <a class="master-link" href="{{ route('ritase.index', ['vehicle_id' => $v->id]) }}">Riwayat</a>
                                    <button type="button" class="btn-action-edit" data-id="{{ $v->id }}" data-plate="{{ $v->license_plate }}" data-type="{{ $v->vehicle_type }}" data-status="{{ $v->status }}" onclick="editVehicleBtn(this)">Ubah</button>
                                    @if($v->ritase_count > 0)
                                        <button type="button" class="btn-action-locked" onclick="showLockInfo('Hapus Terkunci', 'Armada ini sudah memiliki histori ritase. Ubah status menjadi nonaktif agar tidak dapat dipilih untuk perjalanan baru.')">Terkunci</button>
                                    @else
                                        <form method="POST" action="{{ route('vehicles.destroy', $v->id) }}" onsubmit="return confirm('Hapus kendaraan ini dari master armada?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn-action-delete">Hapus</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="master-empty">Tidak ada kendaraan yang cocok dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $vehicles])
    </section>
</div>
@endsection

@section('modals')
<div id="modal-vehicle" class="modal-overlay {{ $errors->any() ? 'open' : '' }}" role="dialog" aria-modal="true" aria-labelledby="vehicle-modal-title">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-title-wrapper">
                <div class="modal-icon-badge" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="13" height="10"/><path d="M15 9h4l3 3v3h-7V9Z"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg></div>
                <div><h2 id="vehicle-modal-title" class="modal-title">Pendaftaran Armada Kendaraan</h2><div id="vehicle-modal-subtitle" style="font-size:12px;color:var(--text-caption);margin-top:2px;">Master logistik dan armada</div></div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('modal-vehicle')" aria-label="Tutup dialog">&times;</button>
        </div>
        <form id="vehicle-form" method="POST" action="{{ route('vehicles.store') }}" style="display:flex;flex-direction:column;flex:1;overflow:hidden;margin:0;">
            @csrf
            <input type="hidden" name="_method" id="vehicle-method" value="POST">
            <div class="modal-body">
                <div class="master-help" style="margin-bottom:14px;">Kendaraan berstatus siap jalan dapat dipilih untuk ritase baru. Histori kendaraan tetap tersedia setelah status diubah menjadi nonaktif.</div>
                <div class="master-modal-grid">
                    <div class="form-group span-2"><label for="license_plate">Nomor Plat Polisi</label><input class="form-control" id="license_plate" name="license_plate" value="{{ old('license_plate') }}" required autocomplete="off" data-plate-input></div>
                    <div class="form-group"><label for="vehicle_type">Jenis Kendaraan</label><select class="form-control" id="vehicle_type" name="vehicle_type" required><option value="truck">Truk</option><option value="pickup">Pickup</option><option value="lainnya">Lainnya</option></select></div>
                    <div class="form-group"><label for="vehicle_status">Status Operasional</label><select class="form-control" id="vehicle_status" name="status" required><option value="active">Siap Jalan</option><option value="maintenance">Dalam Perawatan</option><option value="inactive">Nonaktif</option></select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modal-vehicle')">Batal</button><button type="submit" id="btn-submit-vehicle" class="btn">Daftarkan Kendaraan</button></div>
        </form>
    </div>
</div>
<script>
function resetVehicleForm() {
    document.getElementById('vehicle-form').action = "{{ route('vehicles.store') }}";
    document.getElementById('vehicle-method').value = 'POST';
    document.getElementById('license_plate').value = '';
    document.getElementById('vehicle_type').value = 'truck';
    document.getElementById('vehicle_status').value = 'active';
    document.getElementById('vehicle-modal-title').textContent = 'Pendaftaran Armada Kendaraan';
    document.getElementById('vehicle-modal-subtitle').textContent = 'Master logistik dan armada';
    document.getElementById('btn-submit-vehicle').textContent = 'Daftarkan Kendaraan';
}
function openNewVehicle() { resetVehicleForm(); openModal('modal-vehicle'); }
function editVehicleBtn(btn) {
    document.getElementById('vehicle-form').action = "{{ url('/vehicles') }}/" + btn.dataset.id;
    document.getElementById('vehicle-method').value = 'PUT';
    document.getElementById('license_plate').value = btn.dataset.plate;
    document.getElementById('vehicle_type').value = btn.dataset.type;
    document.getElementById('vehicle_status').value = btn.dataset.status;
    document.getElementById('vehicle-modal-title').textContent = 'Ubah Kendaraan';
    document.getElementById('vehicle-modal-subtitle').textContent = 'Perbarui identitas dan kesiapan armada';
    document.getElementById('btn-submit-vehicle').textContent = 'Perbarui Kendaraan';
    openModal('modal-vehicle');
}
</script>
@endsection
