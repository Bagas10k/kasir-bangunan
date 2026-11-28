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
        'cost_price' => 'HPP / modal', 'selling_price' => 'Harga jual', 'min_stock' => 'Stok minimum',
        'code' => 'Kode', 'notes' => 'Catatan',
        'license_plate' => 'Nomor polisi', 'vehicle_type' => 'Tipe mobil',
    ];
    $maxLengths = [
        'sku' => 64, 'name' => 255, 'unit' => 32, 'category' => 50, 'min_stock' => 30,
        'cost_price' => 30, 'selling_price' => 30, 'code' => 64, 'notes' => 255,
        'license_plate' => 32, 'vehicle_type' => 32,
    ];
    $display = [
        'product' => ['sku', 'name', 'category', 'cost_price', 'selling_price'],
        'vehicle' => ['license_plate', 'vehicle_type'],
    ][$kind] ?? ['code', 'name', 'notes'];

    $activeCount = $records->filter(fn($r) => $kind === 'vehicle' ? ($r->status ?? '') === 'active' : (bool) $r->is_active)->count();
    $totalCount = $records->count();

    $noun = ['product' => 'produk', 'customer' => 'pelanggan', 'supplier' => 'supplier', 'employee' => 'karyawan', 'vehicle' => 'mobil'][$kind];
    $searchHint = ['product' => 'Cari nama, SKU, atau kategori', 'vehicle' => 'Cari nomor polisi atau tipe'][$kind] ?? 'Cari nama atau kode '.$noun;
    $decimalText = function ($value): string {
        [$integer, $fraction] = array_pad(explode('.', (string) ($value ?? '0'), 2), 2, '');
        $fraction = rtrim($fraction, '0');
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $integer === '' ? '0' : $integer);
        return $grouped.($fraction !== '' ? ','.$fraction : '');
    };
    $stockState = function (object $record): string {
        $stock = \Brick\Math\BigDecimal::of((string) ($record->stock_quantity ?? '0'));
        if ($stock->isLessThanOrEqualTo(0)) { return 'out'; }
        return $stock->isLessThanOrEqualTo(\Brick\Math\BigDecimal::of((string) ($record->min_stock ?? '0'))) ? 'low' : 'ok';
    };
    $formSources = ['unit' => $productOptions['unit'] ?? [], 'category' => $productOptions['category'] ?? [], 'supplier' => $suppliers ?? [], 'employee' => $employees ?? []];
    $existingCodes = $records->pluck($kind === 'product' ? 'sku' : 'code')->filter()->all();
    $m = $metrics ?? null;
    $monitorCards = match (true) {
        $m === null => [['Aktif', $activeCount], ['Diarsipkan', $totalCount - $activeCount]],
        $kind === 'product' => [
            ['Produk aktif', $m['stock']['active'], null, null, 'active:1'],
            ['Stok menipis', $m['stock']['low'], 'di bawah batas minimum', $m['stock']['low'] > 0 ? 'attention' : null, 'stock:low'],
            ['Stok habis', $m['stock']['out'], 'perlu dipesan ulang', $m['stock']['out'] > 0 ? 'problem' : null, 'stock:out'],
            ['Diarsipkan', $m['stock']['archived'], null, null, 'active:0'],
        ],
        $kind === 'customer' => [
            ['Pelanggan aktif', $m['parties']['customer']['active'], null, null, 'active:1'],
            ['Diarsipkan', $m['parties']['customer']['archived'], null, null, 'active:0'],
            ['Piutang terbuka', \App\Services\V2\Money::display($m['receivable']['total']), $m['receivable']['count'].' tagihan belum lunas', null],
        ],
        $kind === 'supplier' => [
            ['Supplier aktif', $m['parties']['supplier']['active'], null, null, 'active:1'],
            ['Diarsipkan', $m['parties']['supplier']['archived'], null, null, 'active:0'],
            ['Utang terbuka', \App\Services\V2\Money::display($m['payable']['total']), $m['payable']['count'].' tagihan belum lunas', null],
        ],
        $kind === 'employee' => [
            ['Karyawan aktif', $m['parties']['employee']['active'], null, null, 'active:1'],
            ['Diarsipkan', $m['parties']['employee']['archived'], null, null, 'active:0'],
        ],
        default => [
            ['Mobil aktif', $m['vehicles']['active'], null, null, 'active:1'],
            ['Tidak aktif', $m['vehicles']['inactive'], null, null, 'active:0'],
        ],
    };
    // Kartu ringkasan bergaya Operasional: ubin pertama hijau tua (hero), pil kanan atas hanya bila ada yang perlu ditindak.
    $monitorHead = null;
    if ($m !== null) {
        $monitorCards[0][3] = 'hero';
        $monitorCards[0][2] ??= [
            'product' => 'dari '.($m['stock']['active'] + $m['stock']['archived']).' produk terdaftar',
            'customer' => 'pelanggan tetap terdaftar',
            'supplier' => 'pemasok barang yang bisa dipilih',
            'employee' => 'staf toko terdaftar',
            'vehicle' => 'siap dipakai pengiriman',
        ][$kind];
        $monitorHead = match ($kind) {
            'product' => ['Ringkasan produk', 'Katalog barang, batas stok minimum, dan barang yang perlu dipesan ulang', $m['stock']['out'] > 0 ? [$m['stock']['out'].' produk stok habis', 'bad'] : ($m['stock']['low'] > 0 ? [$m['stock']['low'].' produk stok menipis', 'warn'] : null)],
            'customer' => ['Ringkasan pelanggan', 'Pelanggan tetap dan piutang yang belum lunas', $m['receivable']['count'] > 0 ? [$m['receivable']['count'].' tagihan belum lunas', 'warn'] : null],
            'supplier' => ['Ringkasan supplier', 'Pemasok aktif dan sisa utang', $m['payable']['count'] > 0 ? [$m['payable']['count'].' tagihan belum lunas', 'warn'] : null],
            'employee' => ['Ringkasan karyawan', 'Staf toko yang terdaftar', null],
            default => ['Ringkasan mobil', 'Armada untuk pengiriman barang', null],
        };
    }
@endphp
@php $dialogsOnly = $dialogsOnly ?? false; @endphp
@unless($dialogsOnly)
@include('partials.monitoring-cards', ['monitoringTask' => 'master-'.$kind, 'monitoringTitle' => 'Ringkasan '.$noun, 'monitoringHead' => $monitorHead, 'monitoringCards' => $monitorCards])

@endunless
<section class="@unless($dialogsOnly)card @endunless master-entity-card" id="master-section-{{ $kind }}" @if($dialogsOnly) data-dialog-host @else aria-labelledby="heading-master-{{ $kind }}" @endif data-master-kind="{{ $kind }}">
    @unless($dialogsOnly)
    <h2 id="heading-master-{{ $kind }}">Daftar {{ $noun }}</h2>
    <p class="note">{{ $subtitle }} {{ in_array($kind, ['employee', 'vehicle'], true) ? 'Mengarsipkan tidak menghapus riwayat pengiriman.' : 'Mengarsipkan tidak menghapus riwayat transaksi.' }}</p>

    <x-ui.toolbar :search="$searchHint" :label="'Cari '.$noun" :chips="['Semua' => 'all', 'Aktif' => 'active:1', 'Diarsipkan' => 'active:0']">
        <button type="button" class="btn-add-master" data-open-popup="popup-add-{{ $kind }}">Tambah {{ $noun }}</button>
        <button type="button" class="btn-import-master" data-open-popup="popup-import-{{ $kind }}">Impor Excel</button>
        <a href="{{ route('v2.templates', $kind) }}" class="btn-template-download" data-tip="Unduh format berkas Excel">Unduh template</a>
    </x-ui.toolbar>

    <div class="ui-table-wrap scroll master-table-scroll" data-ui-table tabindex="0" role="region" aria-label="Daftar {{ $noun }}">
        <table class="ui-table master-clean-table" id="table-master-{{ $kind }}">
            <caption class="sr">Daftar {{ $noun }}</caption>
            @php
                $headerTips = ['sku' => 'Kode barang; boleh diganti.', 'code' => 'Kode otomatis; boleh diganti.'];
                $headerInfoKeys = ['cost_price' => 'harga_modal', 'selling_price' => 'harga_kontraktor'];
            @endphp
            <thead>
                <tr>
                    @foreach ($display as $column)
                        <th scope="col" @class(['ui-num' => in_array($column, ['cost_price', 'selling_price'], true)]) @isset($headerTips[$column]) data-tip="{{ $headerTips[$column] }}" @endisset>{{ $labels[$column] ?? $column }}@isset($headerInfoKeys[$column])<x-ui.info-tip :key="$headerInfoKeys[$column]" :label="'Keterangan '.($labels[$column] ?? $column)" :dark="true"/>@endisset</th>
                    @endforeach
                    @if ($kind === 'product')
                        <th scope="col" class="ui-num">Stok / satuan<x-ui.info-tip key="stok_saat_ini" label="Keterangan Stok / satuan" :dark="true"/></th>
                    @elseif ($kind === 'supplier')
                        <th scope="col" class="ui-num">Produk</th>
                        <th scope="col" class="ui-num">Utang berjalan</th>
                    @elseif ($kind === 'customer')
                        <th scope="col" class="ui-num">Frekuensi belanja<x-ui.info-tip text="Jumlah transaksi penjualan yang tercatat atas nama pelanggan ini." title="Frekuensi Belanja" label="Keterangan Frekuensi belanja" :dark="true"/></th>
                        <th scope="col" class="ui-num">Piutang pelanggan</th>
                    @elseif ($kind === 'vehicle')
                        <th scope="col" class="ui-num">Dipakai</th>
                        <th scope="col">Terakhir</th>
                    @endif
                    <th scope="col">Status</th>
                    <th scope="col" class="ui-actions">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $isActive = $kind === 'vehicle' ? ($record->status ?? '') === 'active' : (bool) $record->is_active;
                        $formId = 'edit-' . $kind . '-' . $record->id;
                        $recordName = (string) ($record->name ?? $record->license_plate ?? $record->code ?? $record->id);
                        $stock = $kind === 'product' ? $stockState($record) : null;
                    @endphp
                    <tr data-record-id="{{ $record->id }}" data-active="{{ $isActive ? '1' : '0' }}" @if($stock) data-stock="{{ $stock }}" @endif>
                        @foreach ($display as $column)
                            <td data-field="{{ $column }}" data-label="{{ $labels[$column] ?? $column }}" @class(['ui-num' => in_array($column, ['cost_price', 'selling_price'], true), 'ui-primary' => in_array($column, ['name', 'license_plate'], true), 'ui-nowrap' => in_array($column, ['sku', 'code'], true)])>
                                @if($column === 'cost_price')
                                    Rp {{ $decimalText($record->cost_price ?? '0') }}
                                @elseif($column === 'selling_price')
                                    {{ $rp($record->{$column} ?? 0) }}@if($column === 'selling_price' && ($record->contractor_price ?? null) !== null)<span class="ui-sub">Kontraktor {{ $rp($record->contractor_price) }}</span>@endif
                                @elseif($kind === 'customer' && $column === 'name' && ($record->price_tier ?? '') === 'contractor')
                                    {{ $record->name }} <span class="ui-badge ui-badge--ok">Kontraktor</span>
                                @elseif($kind === 'supplier' && $column === 'name')
                                    @php $hcSup = ($supplierInsights ?? [])[$record->id] ?? null; @endphp
                                    <button type="button" class="ui-link" data-insight="supplier" data-insight-id="{{ $record->id }}" data-insight-name="{{ $record->name }}" data-insight-meta="Kode {{ $record->code }}" data-hover-card data-hc-title="{{ $record->name }}" data-hc-meta="Kode {{ $record->code }}" data-hc-rows="{{ json_encode([['Produk terhubung', (string) ($hcSup ? count($hcSup['products']) : 0)], ['Utang berjalan', $rp($hcSup['outstanding'] ?? '0')]], JSON_UNESCAPED_UNICODE) }}" data-hc-foot="Klik untuk lihat daftar produk & tagihan" aria-haspopup="dialog">{{ $record->name }}</button>
                                @elseif($kind === 'vehicle' && $column === 'license_plate')
                                    @php $hcVeh = ($vehicleInsights ?? [])[$record->id] ?? null; @endphp
                                    <button type="button" class="ui-link" data-insight="vehicle" data-insight-id="{{ $record->id }}" data-insight-name="{{ $record->license_plate }}" data-insight-meta="{{ $record->vehicle_type }}" data-hover-card data-hc-title="{{ $record->license_plate }}" data-hc-meta="{{ $record->vehicle_type ?: 'Armada pengiriman' }}" data-hc-rows="{{ json_encode([['Total pemakaian', ($hcVeh ? $hcVeh['deliveries'] + $hcVeh['trips'] : 0).' kali'], ['Terakhir dipakai', ($hcVeh['last_used'] ?? null) ? \Carbon\Carbon::parse($hcVeh['last_used'])->locale('id')->translatedFormat('j M Y') : 'Belum pernah']], JSON_UNESCAPED_UNICODE) }}" data-hc-foot="Klik untuk lihat riwayat pengiriman & sopir" aria-haspopup="dialog">{{ $record->license_plate }}</button>
                                @else
                                    {{ $record->{$column} ?? '—' }}
                                @endif
                            </td>
                        @endforeach
                        @if ($kind === 'product')
                            <td data-field="stock_quantity" data-label="Stok / satuan" class="ui-num">
                                <strong class="ui-figure">{{ $decimalText($record->stock_quantity) }} {{ $record->unit }}</strong>
                                @if($stock === 'out')<span class="ui-sub"><x-ui.status status="rejected" label="Habis"/></span>@elseif($stock === 'low')<span class="ui-sub"><x-ui.status status="pending" label="Menipis"/></span>@endif
                            </td>
                        @endif
                        @if ($kind === 'supplier')
                            @php $supplierInfo = ($supplierInsights ?? [])[$record->id] ?? null; @endphp
                            <td data-label="Produk" class="ui-num">{{ $supplierInfo ? count($supplierInfo['products']) : 0 }}</td>
                            <td data-label="Utang berjalan" class="ui-num">{{ $rp($supplierInfo['outstanding'] ?? '0') }}</td>
                        @elseif ($kind === 'customer')
                            @php $customerInfo = ($customerInsights ?? [])[$record->id] ?? ['purchase_count' => 0, 'outstanding' => '0.00']; @endphp
                            <td data-label="Frekuensi belanja" class="ui-num">{{ number_format($customerInfo['purchase_count'], 0, ',', '.') }}<span class="ui-sub">transaksi</span></td>
                            <td data-label="Piutang pelanggan" class="ui-num">{{ $rp($customerInfo['outstanding']) }}</td>
                        @elseif ($kind === 'vehicle')
                            @php $vehicleInfo = ($vehicleInsights ?? [])[$record->id] ?? null; @endphp
                            <td data-label="Dipakai" class="ui-num">{{ $vehicleInfo ? $vehicleInfo['deliveries'] + $vehicleInfo['trips'] : 0 }}<span class="ui-sub">kali</span></td>
                            <td data-label="Terakhir" class="ui-nowrap">{{ ($vehicleInfo['last_used'] ?? null) ? \Carbon\Carbon::parse($vehicleInfo['last_used'])->locale('id')->translatedFormat('j M Y') : 'Belum pernah' }}</td>
                        @endif
                        <td data-label="Status"><x-ui.status :status="$isActive ? 'active' : ($kind === 'vehicle' ? 'inactive' : 'archived')"/></td>
                        <td class="ui-actions">
                             @if($kind === 'product')
                                 <button type="button" class="btn-action-pill" data-open-popup="popup-detail-product-{{ $record->id }}" aria-label="Detail {{ $recordName }}">Detail</button>
                                 @include('v2.master-product-detail')
                             @elseif($kind === 'customer')
                                 <button type="button" class="btn-action-pill" data-customer-detail="{{ route('v2.customers.activity', $record->id) }}" data-detail-name="{{ $recordName }}" data-detail-code="{{ $record->code }}" aria-haspopup="dialog">Detail</button>
                             @elseif($kind === 'supplier' || $kind === 'vehicle')
                                 <button type="button" class="btn-action-pill" data-insight="{{ $kind }}" data-insight-id="{{ $record->id }}" data-insight-name="{{ $recordName }}" data-insight-meta="{{ $kind === 'supplier' ? 'Kode '.$record->code : $record->vehicle_type }}" aria-haspopup="dialog">Detail</button>
                             @endif
                            <button type="button" class="btn-action-pill btn-action-edit" data-open-popup="popup-edit-{{ $kind }}-{{ $record->id }}" aria-label="Ubah {{ $recordName }}">Ubah</button>
                            @if($isActive)
                            <form method="POST" action="{{ route('v2.master.archive', ['kind' => $kind, 'id' => $record->id]) }}" data-master-form data-method="PATCH">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn-action-pill btn-action-archive" aria-label="Arsipkan {{ $recordName }}">Arsipkan</button>
                                <span class="mr-msg" role="status" aria-live="polite"></span>
                            </form>
                            @else
                            <form method="POST" action="{{ route('v2.master.activate', ['kind' => $kind, 'id' => $record->id]) }}" data-master-form data-method="PATCH">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn-action-pill btn-action-activate" aria-label="Aktifkan kembali {{ $recordName }}">Aktifkan kembali</button>
                                <span class="mr-msg" role="status" aria-live="polite"></span>
                            </form>
                            @endif

                            {{-- Dialog Popup Ubah Record Ini --}}
                            <dialog class="input-dialog uf-dialog @if($kind === 'product') uf-dialog-wide @endif" id="popup-edit-{{ $kind }}-{{ $record->id }}" aria-labelledby="title-edit-{{ $kind }}-{{ $record->id }}">
                                <div class="popup-heading">
                                    <div><h2 id="title-edit-{{ $kind }}-{{ $record->id }}">Ubah {{ $noun }}</h2><p class="uf-dialog-sub">{{ $recordName }}</p></div>
                                    <button type="button" class="popup-close secondary" data-close-dialog aria-label="Tutup form ubah">×</button>
                                </div>
                                <div class="popup-content">
                                    <form method="POST" action="{{ route('v2.master.update', ['kind' => $kind, 'id' => $record->id]) }}" data-master-form data-method="PUT" class="uf-form">
                                        @csrf
                                        @method('PUT')
                                        <x-ui.form-fields :kind="$kind" :prefix="$formId" :record="$record" :sources="$formSources + ['supplierLinks' => $productSupplierLinks[$record->id] ?? []]" :locked="in_array((int) $record->id, $lockedCostIds, true) ? ['cost_price'] : []"/>
                                        <p class="mr-msg err" data-error role="alert" tabindex="-1" hidden></p>
                                        <div class="uf-actions">
                                            <button type="button" class="secondary" data-close-dialog>Batal</button>
                                            <button type="submit" class="uf-primary">Simpan perubahan</button>
                                        </div>
                                    </form>
                                </div>
                            </dialog>
                        </td>
                    </tr>
                @empty
                    <tr data-ui-empty-static>
                        <td colspan="{{ count($display) + ($kind === 'product' ? 3 : (in_array($kind, ['supplier', 'customer', 'vehicle'], true) ? 4 : 2)) }}" class="ui-empty">
                            <strong>Belum ada data {{ $noun }}.</strong>
                            Tekan tombol "Tambah {{ $noun }}" di atas untuk memasukkan data pertama.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @endunless

    {{-- Dialog Popup Tambah Master --}}
    <dialog class="input-dialog uf-dialog @if($kind === 'product') uf-dialog-wide @endif" id="popup-add-{{ $kind }}" aria-labelledby="title-add-{{ $kind }}">
        <div class="popup-heading">
            <div><h2 id="title-add-{{ $kind }}">Tambah {{ $noun }}</h2><p class="uf-dialog-sub">{{ ['product' => 'Daftarkan barang baru ke katalog.', 'customer' => 'Simpan pelanggan tetap agar mudah dipilih di Kasir.', 'supplier' => 'Simpan pemasok untuk penerimaan barang.', 'employee' => 'Daftarkan staf toko.', 'vehicle' => 'Daftarkan mobil untuk pengiriman.'][$kind] ?? '' }} Kolom bertanda * wajib diisi.</p></div>
            <button type="button" class="popup-close secondary" data-close-dialog aria-label="Tutup formulir tambah">×</button>
        </div>
        <div class="popup-content">
            <form class="master-create-form uf-form" action="{{ route('v2.master.store', $kind) }}" method="POST" data-master-kind="{{ $kind }}">
                @csrf
                <x-ui.form-fields :kind="$kind" :prefix="'add-'.$kind" :sources="$formSources" :existing-codes="$existingCodes"/>
                <p data-error class="err" tabindex="-1" hidden></p>
                <p data-ok class="ok" hidden></p>
                <div class="uf-actions">
                    <button type="button" class="secondary" data-close-dialog>Batal</button>
                    <button type="submit" class="uf-primary">Simpan {{ $noun }}</button>
                </div>
            </form>
        </div>
    </dialog>

    {{-- Dialog Popup Impor Excel --}}
    <dialog class="input-dialog uf-dialog" id="popup-import-{{ $kind }}" aria-labelledby="title-import-{{ $kind }}">
        <div class="popup-heading">
            <div><h2 id="title-import-{{ $kind }}">Impor {{ $noun }} dari Excel</h2><p class="uf-dialog-sub">Tambahkan banyak {{ $noun }} sekaligus. Data diperiksa dulu sebelum disimpan.</p></div>
            <button type="button" class="popup-close secondary" data-close-dialog aria-label="Tutup form impor">×</button>
        </div>
        <div class="popup-content">
            <form class="master-import-form uf-form" action="{{ route('v2.imports.preview', $kind) }}" method="POST" enctype="multipart/form-data" data-master-kind="{{ $kind }}">
                @csrf
                <ol class="uf-steps">
                    <li><span><a href="{{ route('v2.templates', $kind) }}">Unduh template {{ $noun }}</a></span></li>
                    <li><span>Isi data di Excel, satu baris satu {{ $noun }}</span></li>
                    <li><span>Unggah berkas lalu periksa hasilnya</span></li>
                </ol>
                <div class="uf uf-w-6">
                    <label class="uf-label" for="import-file-{{ $kind }}">Berkas Excel (.xlsx)<span class="uf-req" aria-hidden="true">*</span></label>
                    <div class="uf-drop" data-ui-drop>
                        <span class="uf-drop-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M5 20h14"/></svg></span>
                        <span class="uf-drop-title" data-drop-title>Pilih berkas Excel</span>
                        <span class="uf-drop-sub">atau seret berkas ke sini · format .xlsx</span>
                        <span class="uf-drop-file" data-drop-file></span>
                        <input id="import-file-{{ $kind }}" type="file" name="file" accept=".xlsx" required>
                    </div>
                </div>
                <p data-error class="err" tabindex="-1" hidden></p>
                <p class="import-preview-status note" aria-live="polite"></p>
                <div class="import-preview-table-wrap scroll" hidden></div>
                <div class="uf-actions">
                    <button type="button" class="secondary" data-close-dialog>Batal</button>
                    <button type="submit" class="uf-primary">Periksa berkas</button>
                    <div class="import-commit-area" style="display:none"><button type="button" class="uf-primary btn-do-commit">Simpan hasil impor</button></div>
                </div>
            </form>
        </div>
    </dialog>
</section>
