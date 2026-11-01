{{--
    Panel Khusus Satu Entitas Master V2
    Props wajib:
    - $kind: 'product'|'customer'|'supplier'|'employee'|'vehicle'
    - $title: Judul halaman (cth: 'Master Produk Material')
    - $subtitle: Keterangan singkat halaman
    - $records: Koleksi record data
    - $allMasterRecords: Koleksi master lengkap (untuk fallback/akses)
--}}
@php
    $service = app(\App\Services\V2\MasterData::class);
    $records = collect($records ?? []);
    $columns = $service->columns($kind);
    $lockedCostIds = $kind === 'product' ? $service->costLockedIds($records->pluck('id')->all()) : [];

    $labels = [
        'sku' => 'SKU', 'name' => 'Nama', 'unit' => 'Satuan', 'category' => 'Kategori',
        'cost_price' => 'HPP', 'selling_price' => 'Harga Jual', 'min_stock' => 'Min. Stok',
        'code' => 'Kode', 'notes' => 'Catatan',
        'license_plate' => 'Nomor Polisi', 'vehicle_type' => 'Tipe Mobil',
    ];
    $maxLengths = [
        'sku' => 64, 'name' => 255, 'unit' => 32, 'category' => 50, 'min_stock' => 30,
        'cost_price' => 30, 'selling_price' => 30, 'code' => 64, 'notes' => 255,
        'license_plate' => 32, 'vehicle_type' => 32,
    ];
    $display = [
        'product' => ['sku', 'name', 'unit', 'category', 'selling_price'],
        'vehicle' => ['license_plate', 'vehicle_type'],
    ][$kind] ?? ['code', 'name', 'notes'];

    $activeCount = $records->filter(fn($r) => $kind === 'vehicle' ? ($r->status ?? '') === 'active' : (bool) $r->is_active)->count();
    $totalCount = $records->count();
@endphp

<section class="card master-entity-card" id="master-section-{{ $kind }}" aria-labelledby="heading-master-{{ $kind }}" data-master-kind="{{ $kind }}">
    <div class="master-page-header">
        <div class="master-page-copy">
            <h2 id="heading-master-{{ $kind }}">{{ $title }}</h2>
            <p class="note">{{ $subtitle }}</p>
        </div>
        <div class="master-header-stats">
            <span class="stat-pill">Ditampilkan: <strong>{{ $totalCount }}</strong></span>
            <span class="stat-pill active">Aktif: <strong>{{ $activeCount }}</strong></span>
        </div>
    </div>

    <p class="note">Daftar dan pencarian mencakup maksimal 100 data terbaru jenis ini. Arsip tidak menghapus riwayat.</p>
    {{-- Toolbar Aksi & Filter Lokal --}}
    <div class="master-toolbar-row">
        <div class="master-search-wrap">
            <input type="search" class="form-control master-instant-search" placeholder="Cari {{ strtolower($title) }}..." data-search-target="#table-master-{{ $kind }}" aria-label="Cari data {{ $title }}">
        </div>
        <div class="master-actions-group">
            <button type="button" class="btn-table-action btn-add-master" data-open-popup="popup-add-{{ $kind }}">
                + Tambah {{ ['product'=>'Produk','customer'=>'Pelanggan','supplier'=>'Supplier','employee'=>'Karyawan','vehicle'=>'Mobil'][$kind] }}
            </button>
            <button type="button" class="btn-table-action btn-import-master" data-open-popup="popup-import-{{ $kind }}">
                Impor Excel
            </button>
            <a href="{{ route('v2.templates', $kind) }}" class="btn-table-action btn-template-download" title="Unduh format berkas Excel">
                Template
            </a>
        </div>
    </div>

    {{-- Tabel Data Master --}}
    <div class="scroll master-table-scroll" tabindex="0" role="region" aria-label="Daftar {{ $title }}">
        <table class="master-clean-table" id="table-master-{{ $kind }}">
            <thead>
                <tr>
                    @foreach ($display as $column)
                        <th scope="col">{{ $labels[$column] ?? $column }}</th>
                    @endforeach
                    @if ($kind === 'product')
                        <th scope="col" style="text-align: right">Stok</th>
                    @endif
                    <th scope="col" style="text-align: center">Status</th>
                    <th scope="col" style="text-align: center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $isActive = $kind === 'vehicle' ? ($record->status ?? '') === 'active' : (bool) $record->is_active;
                        $statusText = $kind === 'vehicle' ? ($record->status ?? '') : ($isActive ? 'Aktif' : 'Arsip');
                        $statusClass = $isActive ? 'status-pill-active' : 'status-pill-archived';
                        $formId = 'edit-' . $kind . '-' . $record->id;
                        $recordName = (string) ($record->name ?? $record->license_plate ?? $record->code ?? $record->id);
                    @endphp
                    <tr data-record-id="{{ $record->id }}" data-active="{{ $isActive ? '1' : '0' }}">
                        @foreach ($display as $column)
                            <td data-field="{{ $column }}" @if(in_array($column, ['cost_price', 'selling_price'])) class="money" @endif>
                                @if(in_array($column, ['cost_price', 'selling_price']))
                                    {{ $rp($record->{$column} ?? 0) }}
                                @else
                                    {{ $record->{$column} ?? '—' }}
                                @endif
                            </td>
                        @endforeach
                        @if ($kind === 'product')
                            <td data-field="stock_quantity" style="text-align: right; font-variant-numeric: tabular-nums;">
                                <strong>{{ $record->stock_quantity }}</strong>
                            </td>
                        @endif
                        <td style="text-align: center">
                            <span class="status-pill {{ $statusClass }}">{{ $statusText }}</span>
                        </td>
                        <td style="text-align: center; white-space: nowrap;">
                            <button type="button" class="btn-action-pill btn-action-edit" data-open-popup="popup-edit-{{ $kind }}-{{ $record->id }}">
                                Ubah
                            </button>
                            <form method="POST" action="{{ route('v2.master.archive', ['kind' => $kind, 'id' => $record->id]) }}" data-master-form data-method="PATCH" style="display: inline-block; margin: 0;">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn-action-pill btn-action-archive" @disabled(! $isActive)>
                                    Arsip
                                </button>
                                <span class="mr-msg" role="status" aria-live="polite"></span>
                            </form>

                            {{-- Dialog Popup Ubah Record Ini --}}
                            <dialog class="input-dialog" id="popup-edit-{{ $kind }}-{{ $record->id }}" aria-labelledby="title-edit-{{ $kind }}-{{ $record->id }}">
                                <div class="popup-heading">
                                    <h2 id="title-edit-{{ $kind }}-{{ $record->id }}">Ubah {{ $title }}: {{ $recordName }}</h2>
                                    <button type="button" class="popup-close secondary" data-close-dialog aria-label="Tutup form ubah">×</button>
                                </div>
                                <div class="popup-content">
                                    <form method="POST" action="{{ route('v2.master.update', ['kind' => $kind, 'id' => $record->id]) }}" data-master-form data-method="PUT">
                                        @csrf
                                        @method('PUT')
                                        <div class="form-grid-2">
                                            @foreach ($columns as $column)
                                                @php
                                                    $inpId = $formId . '-' . $column;
                                                    $val = $record->{$column} ?? '';
                                                    $isLocked = $column === 'cost_price' && in_array((int)$record->id, $lockedCostIds, true);
                                                @endphp
                                                <div class="field @if($column === 'notes') col-full @endif">
                                                    <label for="{{ $inpId }}">{{ $labels[$column] ?? $column }}</label>
                                                    @if ($column === 'notes')
                                                        <textarea id="{{ $inpId }}" name="notes" rows="2" maxlength="{{ $maxLengths['notes'] }}">{{ $val }}</textarea>
                                                    @else
                                                        <input id="{{ $inpId }}" name="{{ $column }}" type="text" value="{{ $val }}"
                                                               maxlength="{{ $maxLengths[$column] ?? 255 }}"
                                                               @if ($isLocked) readonly aria-describedby="{{ $inpId }}-lock" @endif>
                                                    @endif
                                                    @if ($isLocked)
                                                        <span class="field-hint" id="{{ $inpId }}-lock">HPP terkunci karena sudah ada mutasi.</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="popup-submit-bar" style="margin-top: 14px; display: flex; justify-content: flex-end; gap: 8px;">
                                            <button type="button" class="secondary" data-close-dialog>Batal</button>
                                            <button type="submit" class="btn-table-action">Simpan Perubahan</button>
                                        </div>
                                        <p class="mr-msg err" data-error role="alert" tabindex="-1" hidden></p>
                                    </form>
                                </div>
                            </dialog>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($display) + ($kind === 'product' ? 3 : 2) }}" style="text-align: center; padding: 24px; color: var(--text-caption);">
                            Belum ada data {{ strtolower($title) }}. Gunakan tombol "+ Tambah" di atas untuk memasukkan data baru.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Dialog Popup Tambah Master --}}
    <dialog class="input-dialog" id="popup-add-{{ $kind }}" aria-labelledby="title-add-{{ $kind }}">
        <div class="popup-heading">
            <h2 id="title-add-{{ $kind }}">Tambah {{ $title }} Baru</h2>
            <button type="button" class="popup-close secondary" data-close-dialog aria-label="Tutup formulir tambah">×</button>
        </div>
        <div class="popup-content">
            <form class="master-create-form" action="{{ route('v2.master.store', $kind) }}" method="POST" data-master-kind="{{ $kind }}">
                @csrf
                <div class="form-grid-2">
                    @foreach ($columns as $column)
                        @php
                            $addInpId = 'add-' . $kind . '-' . $column;
                            $isReq = !in_array($column, ['notes', 'category', 'cost_price', 'selling_price', 'min_stock'], true);
                            $defVal = in_array($column, ['cost_price', 'selling_price', 'min_stock'], true) ? '0' : '';
                        @endphp
                        <div class="field @if($column === 'notes') col-full @endif">
                            <label for="{{ $addInpId }}">{{ $labels[$column] ?? $column }} @if($isReq)<span style="color:var(--danger)">*</span>@endif</label>
                            @if ($column === 'notes')
                                <textarea id="{{ $addInpId }}" name="{{ $column }}" rows="2" maxlength="{{ $maxLengths['notes'] }}"></textarea>
                            @else
                                <input id="{{ $addInpId }}" name="{{ $column }}" type="text" value="{{ $defVal }}"
                                       maxlength="{{ $maxLengths[$column] ?? 255 }}"
                                       @if($isReq) required @endif
                                       @if(in_array($column, ['cost_price', 'selling_price', 'min_stock'])) inputmode="decimal" @endif>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="popup-submit-bar" style="margin-top: 14px; display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" class="secondary" data-close-dialog>Batal</button>
                    <button type="submit" class="btn-table-action">Simpan Data</button>
                </div>
                <p data-error class="err" tabindex="-1" hidden></p>
                <p data-ok class="ok" hidden></p>
            </form>
        </div>
    </dialog>

    {{-- Dialog Popup Impor Excel --}}
    <dialog class="input-dialog" id="popup-import-{{ $kind }}" aria-labelledby="title-import-{{ $kind }}">
        <div class="popup-heading">
            <h2 id="title-import-{{ $kind }}">Impor {{ $title }} dari Excel</h2>
            <button type="button" class="popup-close secondary" data-close-dialog aria-label="Tutup form impor">×</button>
        </div>
        <div class="popup-content">
            <p class="note" style="margin-top: 0">
                Pilih berkas template spreadsheet (.xlsx) khusus <strong>{{ $title }}</strong>. Data stok awal dan saldo kas tidak diimpor di sini.
            </p>
            <form class="master-import-form" action="{{ route('v2.imports.preview', $kind) }}" method="POST" enctype="multipart/form-data" data-master-kind="{{ $kind }}">
                @csrf
                <div class="field">
                    <label for="import-file-{{ $kind }}">Pilih Berkas Excel (.xlsx)</label>
                    <input id="import-file-{{ $kind }}" type="file" name="file" accept=".xlsx" required>
                </div>
                <div style="margin: 10px 0;">
                    <a href="{{ route('v2.templates', $kind) }}" style="font-size: 12.5px; font-weight: 600; color: var(--primary-hover);">
                        Unduh Format Template {{ $title }}
                    </a>
                </div>
                <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 14px;">
                    <button type="button" class="secondary" data-close-dialog>Batal</button>
                    <button type="submit" class="btn-table-action">Periksa Berkas</button>
                </div>
                <p data-error class="err" tabindex="-1" hidden></p>
                <p class="import-preview-status note" aria-live="polite"></p>
                <div class="import-preview-table-wrap scroll" hidden style="max-height: 220px; margin-top: 10px;"></div>
                <div class="import-commit-area" style="margin-top: 10px; display: none; text-align: right;">
                    <button type="button" class="btn-table-action btn-do-commit">Simpan Hasil Impor</button>
                </div>
            </form>
        </div>
    </dialog>
</section>
