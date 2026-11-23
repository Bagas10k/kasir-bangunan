@php
    $rows = collect($records);
    $nounOf = ['driver' => 'sopir', 'unit' => 'satuan', 'category' => 'kategori'][$kind] ?? strtolower($title);
    $m = $metrics ?? null;
    $cards = match (true) {
        $m === null => [['Aktif', $rows->where('is_active', true)->count()], ['Diarsipkan', $rows->where('is_active', false)->count()]],
        $kind === 'driver' => [
            ['Sopir karyawan toko', $m['drivers']['internal'], 'terhubung ke data karyawan', null],
            ['Sopir luar', $m['drivers']['external'], 'didaftarkan terpisah', null],
            ['Diarsipkan', $m['drivers']['archived'], null, null, 'active:0'],
        ],
        default => [
            ['Satuan aktif', $m['options']['unit']['active'], $m['options']['unit']['used'].' dipakai produk', $kind === 'unit' ? 'hero' : null],
            ['Kategori aktif', $m['options']['category']['active'], $m['options']['category']['used'].' dipakai produk', $kind === 'category' ? 'hero' : null],
            ['Diarsipkan', $m['options']['unit']['archived'] + $m['options']['category']['archived'], 'satuan dan kategori', null],
        ],
    };
    // Tab Satuan dan Kategori sama-sama punya ringkasan; ubin hero = tab yang sedang dibuka.
    if ($m !== null && $kind === 'category') { [$cards[0], $cards[1]] = [$cards[1], $cards[0]]; }
    $relationHead = $m === null || $kind === 'driver' ? null : ['Ringkasan satuan dan kategori', 'Pilihan bersama untuk data produk; mengarsipkan tidak menghapus riwayat', null];
@endphp
@php $dialogsOnly = $dialogsOnly ?? false; @endphp
@if(! $dialogsOnly)
    @include('partials.monitoring-cards', ['monitoringTask' => 'master-'.$kind, 'monitoringTitle' => 'Ringkasan '.$nounOf, 'monitoringHead' => $relationHead, 'monitoringCards' => $cards])
@endif
<section class="@unless($dialogsOnly)card @endunless master-entity-card" id="master-section-{{ $kind }}" @if($dialogsOnly) data-dialog-host @else aria-labelledby="heading-master-{{ $kind }}" @endif>
    @unless($dialogsOnly)
    <h2 id="heading-master-{{ $kind }}">Daftar {{ $nounOf }}</h2>
    <p class="note">{{ $kind === 'driver' ? 'Sopir karyawan ditautkan ke data staf. Sopir luar didaftarkan terpisah. Nama pada transaksi lama tetap tersimpan.' : 'Pilihan bersama pada produk. Mengarsipkan pilihan tidak menghapus riwayat.' }}</p>

    <x-ui.toolbar :search="'Cari '.$nounOf" :label="'Cari '.$nounOf" :chips="['Semua' => 'all', 'Aktif' => 'active:1', 'Diarsipkan' => 'active:0']">
        <button type="button" class="btn-add-master" data-open-popup="popup-add-{{ $kind }}">Tambah {{ $nounOf }}</button>
    </x-ui.toolbar>

    <div class="ui-table-wrap scroll" data-ui-table tabindex="0" role="region" aria-label="Daftar {{ $nounOf }}">
        <table class="ui-table master-clean-table">
            <caption class="sr">Daftar {{ $nounOf }}</caption>
            <thead><tr>
                @if($kind === 'driver')<th scope="col">Kode</th><th scope="col">Nama</th><th scope="col">Jenis</th>
                @else<th scope="col">Nama</th><th scope="col" class="ui-num">Dipakai produk</th>@endif
                <th scope="col">Status</th><th scope="col" class="ui-actions">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $record)
                <tr data-record-id="{{ $record->id }}" data-active="{{ $record->is_active ? '1' : '0' }}">
                    @if($kind === 'driver')
                        <td data-label="Kode" class="ui-nowrap">{{ $record->code }}</td>
                        <td data-label="Nama" class="ui-primary">{{ $record->name }}@if($record->phone)<span class="ui-sub">{{ $record->phone }}</span>@endif</td>
                        <td data-label="Jenis"><x-ui.status :status="$record->kind"/></td>
                    @else
                        <td data-label="Nama" class="ui-primary">{{ $record->name }}</td>
                        <td data-label="Dipakai produk" class="ui-num">{{ (int) ($record->used_count ?? 0) }}</td>
                    @endif
                    <td data-label="Status"><x-ui.status :status="$record->is_active ? 'active' : 'archived'"/></td>
                    <td class="ui-actions">
                        <button type="button" class="btn-action-pill btn-action-edit" data-open-popup="popup-edit-{{ $kind }}-{{ $record->id }}" aria-label="Ubah {{ $record->name }}">Ubah</button>
                        <form action="{{ route('v2.relations.archive', ['kind' => $kind, 'id' => $record->id]) }}" method="post" data-master-form data-method="PATCH">
                            @csrf @method('PATCH')<button type="submit" class="btn-action-pill btn-action-archive" @disabled(!$record->is_active) aria-label="Arsipkan {{ $record->name }}">Arsipkan</button><span class="mr-msg" role="status"></span>
                        </form>
                    </td>
                </tr>
            @empty
                <tr data-ui-empty-static><td colspan="{{ $kind === 'driver' ? 5 : 4 }}" class="ui-empty"><strong>Belum ada {{ $nounOf }} terdaftar.</strong>Tekan tombol "Tambah {{ $nounOf }}" di atas untuk memasukkan data pertama.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @endunless
    @php
        $formSources = ['employee' => $employees ?? []];
        $existingCodes = $kind === 'driver' ? $rows->pluck('code')->filter()->all() : [];
    @endphp
    <dialog class="input-dialog uf-dialog" id="popup-add-{{ $kind }}" aria-labelledby="title-add-{{ $kind }}">
        <div class="popup-heading"><div><h2 id="title-add-{{ $kind }}">Tambah {{ $nounOf }}</h2><p class="uf-dialog-sub">{{ ['driver' => 'Sopir luar atau karyawan toko yang ikut mengantar.', 'unit' => 'Satuan baru langsung bisa dipilih di data produk.', 'category' => 'Kategori baru langsung bisa dipilih di data produk.'][$kind] ?? '' }} Kolom bertanda * wajib diisi.</p></div><button type="button" class="popup-close secondary" data-close-dialog aria-label="Tutup tambah {{ $nounOf }}">×</button></div>
        <div class="popup-content"><form class="master-create-form uf-form" action="{{ route('v2.relations.store', $kind) }}" method="post" @if($kind === 'driver') data-driver-master @endif>
            @csrf
            <x-ui.form-fields :kind="$kind" :prefix="'add-'.$kind" :sources="$formSources" :existing-codes="$existingCodes"/>
            <p class="err" data-error hidden></p>
            <div class="uf-actions"><button type="button" class="secondary" data-close-dialog>Batal</button><button type="submit" class="uf-primary">Simpan {{ $nounOf }}</button></div>
        </form></div>
    </dialog>
    @foreach($records as $record)
        <dialog class="input-dialog uf-dialog" id="popup-edit-{{ $kind }}-{{ $record->id }}" aria-labelledby="title-edit-{{ $kind }}-{{ $record->id }}">
            <div class="popup-heading"><div><h2 id="title-edit-{{ $kind }}-{{ $record->id }}">Ubah {{ $nounOf }}</h2><p class="uf-dialog-sub">{{ $record->name }}</p></div><button type="button" class="popup-close secondary" data-close-dialog aria-label="Tutup ubah {{ $nounOf }}">×</button></div>
            <div class="popup-content"><form action="{{ route('v2.relations.update', ['kind' => $kind, 'id' => $record->id]) }}" method="post" data-master-form data-method="PUT" class="uf-form" @if($kind === 'driver') data-driver-master @endif>
                @csrf @method('PUT')
                <x-ui.form-fields :kind="$kind" :prefix="'edit-'.$kind.'-'.$record->id" :record="$record" :sources="$formSources"/>
                <p class="err" data-error hidden></p>
                <div class="uf-actions"><button type="button" class="secondary" data-close-dialog>Batal</button><button type="submit" class="uf-primary">Simpan perubahan</button></div>
            </form></div>
        </dialog>
    @endforeach
</section>
