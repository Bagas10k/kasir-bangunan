{{--
    Karyawan & sopir dalam satu daftar. Sumbernya tetap dua: v2_parties (karyawan) dan v2_drivers (sopir).
    Kolom "Posisi" menandai perannya; tombol tambah/ubah memakai dialog milik masing-masing data.
--}}
@php
    $staffEmployees = collect($allMasterRecords['employee'] ?? []);
    $staffDrivers = collect($relationRecords['driver'] ?? []);
    $driverByEmployee = $staffDrivers->whereNotNull('employee_id')->keyBy(fn ($driver) => (int) $driver->employee_id);
    $staffRows = $staffEmployees->map(function ($employee) use ($driverByEmployee) {
        $driver = $driverByEmployee->get((int) $employee->id);
        return (object) [
            'kind' => 'employee', 'id' => $employee->id, 'code' => $employee->code, 'name' => $employee->name,
            'role' => $driver ? 'driver-internal' : 'staff',
            'position' => $employee->notes ?: 'Staf toko',
            'phone' => $driver->phone ?? null,
            'driver_code' => $driver->code ?? null,
            'driver_active' => $driver ? (bool) $driver->is_active : null,
            'notes' => $driver->notes ?? null,
            'is_active' => (bool) $employee->is_active,
        ];
    })->concat($staffDrivers->where('kind', 'external')->map(fn ($driver) => (object) [
        'kind' => 'driver', 'id' => $driver->id, 'code' => $driver->code, 'name' => $driver->name,
        'role' => 'driver-external', 'position' => 'Sopir luar', 'phone' => $driver->phone,
        'driver_code' => $driver->code, 'is_active' => (bool) $driver->is_active,
        'driver_active' => null,
        'notes' => $driver->notes,
    ]))->values();
    $staffMetrics = $metrics ?? null;
    $staffCards = $staffMetrics === null
        ? [['Aktif', $staffRows->where('is_active', true)->count()], ['Diarsipkan', $staffRows->where('is_active', false)->count()]]
        : [
            ['Karyawan aktif', $staffMetrics['parties']['employee']['active'], 'staf toko terdaftar', 'hero', 'role:staff,driver-internal'],
            ['Bertugas sebagai sopir', $staffMetrics['drivers']['internal'], 'karyawan yang ditautkan ke data sopir', null, 'role:driver-internal'],
            ['Sopir luar', $staffMetrics['drivers']['external'], 'didaftarkan terpisah, bukan karyawan', null, 'role:driver-external'],
            ['Diarsipkan', $staffMetrics['parties']['employee']['archived'] + $staffMetrics['drivers']['archived'], 'karyawan dan sopir', null, 'active:0'],
        ];
    $positionLabel = ['staff' => 'Karyawan', 'driver-internal' => 'Karyawan · Sopir', 'driver-external' => 'Sopir luar'];
@endphp
@include('partials.monitoring-cards', ['monitoringTask' => 'master-employee', 'monitoringTitle' => 'Ringkasan karyawan dan sopir', 'monitoringHead' => $staffMetrics === null ? null : ['Ringkasan karyawan dan sopir', 'Staf toko dan sopir yang bisa ditugaskan pada surat jalan', null], 'monitoringCards' => $staffCards])

<section class="card master-entity-card" id="master-section-staff" aria-labelledby="heading-master-staff">
    <h2 id="heading-master-staff">Daftar karyawan dan sopir</h2>
    <p class="note">Satu daftar untuk seluruh staf. Karyawan yang juga menyetir ditautkan ke data sopir agar surat jalan memakai identitas yang sama. Mengarsipkan karyawan atau sopir menyembunyikannya dari pilihan baru tanpa menghapus riwayat pengiriman.</p>

    <x-ui.toolbar search="Cari nama, kode, atau posisi" label="Cari karyawan atau sopir" :chips="['Semua' => 'all', 'Karyawan' => 'role:staff,driver-internal', 'Sopir' => 'role:driver-internal,driver-external', 'Diarsipkan' => 'active:0']">
        <button type="button" class="btn-add-master" data-open-popup="popup-add-employee">Tambah karyawan</button>
        <button type="button" class="btn-import-master" data-open-popup="popup-add-driver">Tambah sopir</button>
    </x-ui.toolbar>

    <div class="ui-table-wrap scroll master-table-scroll" data-ui-table tabindex="0" role="region" aria-label="Daftar karyawan dan sopir">
        <table class="ui-table master-clean-table" id="table-master-staff">
            <caption class="sr">Daftar karyawan dan sopir</caption>
            <thead><tr>
                <th scope="col">Kode</th><th scope="col">Nama</th><th scope="col">Posisi</th><th scope="col">Telepon</th><th scope="col">Status</th><th scope="col" class="ui-actions">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse ($staffRows as $staff)
                <tr data-role="{{ $staff->role }}" data-active="{{ $staff->is_active ? '1' : '0' }}">
                    <td data-label="Kode" class="ui-nowrap">{{ $staff->code }}</td>
                    <td data-label="Nama" class="ui-primary">{{ $staff->name }}@if($staff->role === 'driver-internal' && $staff->driver_code)<span class="ui-sub">Sopir {{ $staff->driver_code }}</span>@endif</td>
                    <td data-label="Posisi"><x-ui.status :status="$staff->role === 'staff' ? 'info' : ($staff->role === 'driver-internal' ? 'internal' : 'external')" :label="$positionLabel[$staff->role]"/>@if($staff->role === 'staff' && $staff->position !== 'Staf toko')<span class="ui-sub">{{ $staff->position }}</span>@endif</td>
                    <td data-label="Telepon" class="ui-nowrap">{{ $staff->phone ?: '—' }}</td>
                    <td data-label="Status"><x-ui.status :status="$staff->is_active ? 'active' : 'archived'"/></td>
                    <td class="ui-actions">
                        @php $staffDialog = 'staff-detail-'.$staff->kind.'-'.$staff->id; @endphp
                        <button type="button" class="btn-action-pill" data-open-popup="{{ $staffDialog }}" aria-haspopup="dialog">Detail</button>
                        <dialog class="input-dialog uf-dialog" id="{{ $staffDialog }}" aria-labelledby="{{ $staffDialog }}-title">
                            <div class="popup-heading">
                                <div><h2 id="{{ $staffDialog }}-title">Detail {{ $staff->kind === 'driver' ? 'sopir' : 'karyawan' }}</h2><p class="uf-dialog-sub">{{ $staff->name }} · {{ $staff->code }}</p></div>
                                <button type="button" class="popup-close secondary" data-close-dialog aria-label="Tutup detail staf">×</button>
                            </div>
                            <div class="popup-content detail-content">
                                <section class="detail-section">
                                    <h3>Identitas dan penugasan</h3>
                                    <dl class="detail-facts">
                                        <div><dt>Peran</dt><dd>{{ $positionLabel[$staff->role] }}</dd></div>
                                        <div><dt>Posisi</dt><dd>{{ $staff->position }}</dd></div>
                                        <div><dt>Status</dt><dd><x-ui.status :status="$staff->is_active ? 'active' : 'archived'"/></dd></div>
                                        <div><dt>Telepon sopir</dt><dd>{{ $staff->phone ?: '—' }}</dd></div>
                                        @if($staff->driver_code)<div><dt>Kode sopir</dt><dd>{{ $staff->driver_code }}</dd></div>@endif
                                        @if($staff->driver_active !== null)<div><dt>Status penugasan sopir</dt><dd><x-ui.status :status="$staff->driver_active ? 'active' : 'archived'"/></dd></div>@endif
                                        @if($staff->notes)<div><dt>Catatan sopir</dt><dd>{{ $staff->notes }}</dd></div>@endif
                                    </dl>
                                </section>
                            </div>
                        </dialog>
                        <button type="button" class="btn-action-pill btn-action-edit" data-open-popup="popup-edit-{{ $staff->kind }}-{{ $staff->id }}" aria-label="Ubah {{ $staff->name }}">Ubah</button>
                        @if($staff->is_active)
                        <form method="POST" action="{{ $staff->kind === 'employee' ? route('v2.master.archive', ['kind' => 'employee', 'id' => $staff->id]) : route('v2.relations.archive', ['kind' => 'driver', 'id' => $staff->id]) }}" data-master-form data-method="PATCH">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-action-pill btn-action-archive" aria-label="Arsipkan {{ $staff->name }}">Arsipkan</button>
                            <span class="mr-msg" role="status" aria-live="polite"></span>
                        </form>
                        @else
                        <form method="POST" action="{{ $staff->kind === 'employee' ? route('v2.master.activate', ['kind' => 'employee', 'id' => $staff->id]) : route('v2.relations.activate', ['kind' => 'driver', 'id' => $staff->id]) }}" data-master-form data-method="PATCH">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-action-pill btn-action-activate" aria-label="Aktifkan kembali {{ $staff->name }}">Aktifkan kembali</button>
                            <span class="mr-msg" role="status" aria-live="polite"></span>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr data-ui-empty-static><td colspan="6" class="ui-empty"><strong>Belum ada karyawan atau sopir.</strong>Tekan "Tambah karyawan" untuk memasukkan data pertama.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
