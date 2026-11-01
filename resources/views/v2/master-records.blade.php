{{--
    Partial master V2: daftar + form edit inline (detail native, tanpa prompt/modal).
    Wajib: $masterKind (string allowlist), $masterRecords (koleksi/array dari MasterData::records()).
    Aksi ke route v2.master.update / v2.master.archive dengan method eksplisit PUT / PATCH.
--}}
@php
    $masterKind = (string) $masterKind;
    $service = app(\App\Services\V2\MasterData::class);
    abort_unless(in_array($masterKind, $service->kinds(), true), 404);

    $records = collect($masterRecords ?? []);
    $columns = $service->columns($masterKind);
    $lockedCostIds = $masterKind === 'product' ? $service->costLockedIds($records->pluck('id')->all()) : [];

    $labels = [
        'sku' => 'SKU', 'name' => 'Nama', 'unit' => 'Satuan', 'category' => 'Kategori',
        'cost_price' => 'HPP', 'selling_price' => 'Harga Jual', 'min_stock' => 'Min. Stok',
        'code' => 'Kode', 'notes' => 'Catatan',
        'license_plate' => 'Nomor Polisi', 'vehicle_type' => 'Tipe',
    ];
    $maxLengths = [
        'sku' => 64, 'name' => 255, 'unit' => 32, 'category' => 50, 'min_stock' => 30,
        'cost_price' => 30, 'selling_price' => 30, 'code' => 64, 'notes' => 255,
        'license_plate' => 32, 'vehicle_type' => 32,
    ];
    $display = [
        'product' => ['sku', 'name', 'unit', 'category', 'selling_price'],
        'vehicle' => ['license_plate', 'vehicle_type'],
    ][$masterKind] ?? ['code', 'name', 'notes'];
@endphp

<style>
    .master-records { background: #fff; color: #14301f; font-size: 14px; }
    .master-records table { width: 100%; border-collapse: collapse; }
    .master-records th, .master-records td { border-bottom: 1px solid #d7e8dd; padding: 8px 10px; text-align: left; vertical-align: top; }
    .master-records th { background: #f2f9f4; color: #14532d; font-weight: 600; }
    .master-records tr[data-active="0"] td { color: #405a49; }
    .master-records details summary { color: #14532d; cursor: pointer; font-weight: 600; }
    .master-records .mr-field { margin: 8px 0; display: flex; flex-direction: column; gap: 2px; }
    .master-records .mr-field label { font-weight: 600; color: #14532d; }
    .master-records .mr-field input, .master-records .mr-field textarea { border: 1px solid #b9d6c5; border-radius: 4px; padding: 6px 8px; max-width: 320px; font: inherit; }
    .master-records .mr-field input[readonly] { background: #f2f9f4; color: #44584c; }
    .master-records button { background: #14532d; border: 0; border-radius: 4px; color: #fff; cursor: pointer; margin-top: 6px; padding: 6px 12px; }
    .master-records button[disabled] { background: #9db8a8; cursor: not-allowed; }
    .master-records .mr-msg { color: #14532d; margin: 6px 0 0; min-height: 1em; }
    .master-records .mr-msg.mr-error { color: #a4262c; }
    .master-records .mr-hint { color: #405a49; font-size: 12px; }
</style>

<section class="master-records" data-master-kind="{{ $masterKind }}">
    <table>
        <thead>
            <tr>
                @foreach ($display as $column)
                    <th scope="col">{{ $labels[$column] ?? $column }}</th>
                @endforeach
                @if ($masterKind === 'product')
                    <th scope="col">Stok</th>
                @endif
                <th scope="col">Status</th>
                <th scope="col">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $record)
                @php
                    $isActive = $masterKind === 'vehicle'
                        ? (string) ($record->status ?? '') === 'active'
                        : (bool) $record->is_active;
                    $statusText = $masterKind === 'vehicle'
                        ? (string) ($record->status ?? '')
                        : ($isActive ? 'Aktif' : 'Nonaktif');
                    $formId = $masterKind . '-' . $record->id;
                    $summaryLabel = (string) ($record->{$display[1]} ?? $record->{$display[0]} ?? $record->id);
                @endphp
                <tr data-record="{{ $record->id }}" data-active="{{ $isActive ? '1' : '0' }}">
                    @foreach ($display as $column)
                        <td data-field="{{ $column }}">{{ $record->{$column} ?? '' }}</td>
                    @endforeach
                    @if ($masterKind === 'product')
                        <td data-field="stock_quantity">{{ $record->stock_quantity }}</td>
                    @endif
                    <td data-field="status">{{ $statusText }}</td>
                    <td>
                        <details class="mr-edit">
                            <summary>Ubah {{ $summaryLabel }}</summary>

                            <form method="POST"
                                  action="{{ route('v2.master.update', ['kind' => $masterKind, 'id' => $record->id]) }}"
                                  data-master-form data-method="PUT">
                                @csrf
                                @method('PUT')

                                @foreach ($columns as $column)
                                    @php
                                        $inputId = $formId . '-' . $column;
                                        $value = $record->{$column} ?? '';
                                        $isLockedCost = $column === 'cost_price' && in_array((int) $record->id, $lockedCostIds, true);
                                    @endphp
                                    <p class="mr-field">
                                        <label for="{{ $inputId }}">{{ $labels[$column] ?? $column }}</label>
                                        @if ($column === 'notes')
                                            <textarea id="{{ $inputId }}" name="notes" rows="2" maxlength="{{ $maxLengths['notes'] }}">{{ $value }}</textarea>
                                        @else
                                            <input id="{{ $inputId }}" name="{{ $column }}" type="text" value="{{ $value }}"
                                                   maxlength="{{ $maxLengths[$column] ?? 255 }}"
                                                   @if ($isLockedCost) readonly aria-describedby="{{ $inputId }}-lock" @endif>
                                        @endif
                                        @if ($isLockedCost)
                                            <span class="mr-hint" id="{{ $inputId }}-lock">HPP terkunci: produk sudah punya stok/riwayat. Ubah lewat penerimaan/saldo awal.</span>
                                        @endif
                                    </p>
                                @endforeach

                                @if ($masterKind === 'product')
                                    <p class="mr-field">
                                        <label for="{{ $formId }}-stock">Stok</label>
                                        <input id="{{ $formId }}-stock" type="text" value="{{ $record->stock_quantity }}" readonly disabled>
                                        <span class="mr-hint">Stok hanya berubah lewat transaksi.</span>
                                    </p>
                                @endif

                                <button type="submit">Simpan</button>
                                <p class="mr-msg" role="status" aria-live="polite"></p>
                            </form>

                            <form method="POST"
                                  action="{{ route('v2.master.archive', ['kind' => $masterKind, 'id' => $record->id]) }}"
                                  data-master-form data-method="PATCH">
                                @csrf
                                @method('PATCH')
                                <button type="submit" @disabled(! $isActive)>Arsipkan</button>
                                <p class="mr-msg" role="status" aria-live="polite"></p>
                            </form>
                        </details>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($display) + 3 }}">Belum ada data {{ $masterKind }}.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</section>

@once
    <script>
        (function () {
            if (window.__v2MasterRecords) {
                return;
            }
            window.__v2MasterRecords = true;

            document.addEventListener('submit', function (event) {
                var form = event.target.closest('form[data-master-form]');
                if (!form) {
                    return;
                }
                event.preventDefault();

                var message = form.querySelector('.mr-msg');
                var token = form.querySelector('input[name="_token"]');
                var payload = {};
                new FormData(form).forEach(function (value, key) {
                    if (key !== '_token' && key !== '_method') {
                        payload[key] = value;
                    }
                });

                if (form.dataset.busy) { return; }
                form.dataset.busy='1';
                message.className = 'mr-msg';
                message.textContent = 'Menyimpan...';

                fetch(form.action, {
                    method: form.dataset.method,
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token ? token.value : ''
                    },
                    body: JSON.stringify(payload)
                }).then(function (response) {
                    return response.json().catch(function () { return {}; }).then(function (body) {
                        if (!response.ok) {
                            throw new Error(body.message || ('Gagal menyimpan (HTTP ' + response.status + ').'));
                        }
                        return body;
                    });
                }).then(function () {
                    var row = form.closest('tr');
                    if (form.dataset.method === 'PATCH') {
                        row.dataset.active = '0';
                        row.querySelector('[data-field="status"]').textContent = 'Nonaktif';
                        form.querySelector('button[type="submit"]').disabled = true;
                    } else {
                        Object.keys(payload).forEach(function (key) {
                            var cell = row.querySelector('[data-field="' + key + '"]');
                            if (cell) {
                                cell.textContent = payload[key];
                            }
                        });
                    }
                    message.textContent = 'Tersimpan.';
                }).catch(function (error) {
                    message.className = 'mr-msg mr-error';
                    message.textContent = error.message;
                }).finally(function () { delete form.dataset.busy; });
            });
        })();
    </script>
@endonce