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
            'is_active' => (bool) $employee->is_active,
        ];
    })->concat($staffDrivers->where('kind', 'external')->map(fn ($driver) => (object) [
        'kind' => 'driver', 'id' => $driver->id, 'code' => $driver->code, 'name' => $driver->name,
        'role' => 'driver-external', 'position' => 'Sopir luar', 'phone' => $driver->phone,
        'driver_code' => $driver->code, 'is_active' => (bool) $driver->is_active,
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
    <p class="note">Satu daftar untuk seluruh staf. Karyawan yang juga menyetir ditautkan ke data sopir agar surat jalan memakai identitas yang sama.</p>

    <x-ui.toolbar search="Cari nama, kode, atau posisi" label="Cari karyawan atau sopir" :chips="['Semua' => 'all', 'Karyawan' => 'role:staff,driver-internal', 'Sopir' => 'role:driver-internal,driver-external', 'Diarsipkan' => 'active:0']">
        <button type="button" class="btn-add-master" data-open-popup="popup-add-employee">Tambah karyawan</button>
        <button type="button" class="btn-import-master" data-open-popup="popup-add-driver">Tambah sopir luar</button>
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
                        <button type="button" class="btn-action-pill btn-action-edit" data-open-popup="popup-edit-{{ $staff->kind }}-{{ $staff->id }}" aria-label="Ubah {{ $staff->name }}">Ubah</button>
                        <form method="POST" action="{{ $staff->kind === 'employee' ? route('v2.master.archive', ['kind' => 'employee', 'id' => $staff->id]) : route('v2.relations.archive', ['kind' => 'driver', 'id' => $staff->id]) }}" data-master-form data-method="PATCH">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-action-pill btn-action-archive" @disabled(! $staff->is_active) aria-label="Arsipkan {{ $staff->name }}">Arsipkan</button>
                            <span class="mr-msg" role="status" aria-live="polite"></span>
                        </form>
                    </td>
                </tr>
            @empty
                <tr data-ui-empty-static><td colspan="6" class="ui-empty"><strong>Belum ada karyawan atau sopir.</strong>Tekan "Tambah karyawan" untuk memasukkan data pertama.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
