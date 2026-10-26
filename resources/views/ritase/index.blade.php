@extends('layouts.app')
@section('content')
@php
    $formatRitaseAmount = static function (string $amount): string {
        [$whole, $cents] = explode('.', (string) \Brick\Math\BigDecimal::of($amount)->toScale(2));
        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole) . ',' . $cents;
    };
@endphp
<style>
    @media (prefers-reduced-motion: reduce) {
        *, ::before, ::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
            scroll-behavior: auto !important;
        }
    }
    .page-header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 20px;
    }
    .page-header-meta {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .page-title {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
        color: var(--text-main);
        letter-spacing: -0.01em;
    }
    .count-indicator {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 9999px;
        font-size: 12px;
        font-weight: 600;
        background-color: var(--mint-surface);
        border: 1px solid var(--mint-border);
        color: var(--primary-hover);
    }
    .form-group {
        margin-bottom: 14px;
    }
    .form-control {
        width: 100%;
        box-sizing: border-box;
    }
</style>

<div class="card">
    <div class="page-header-row">
        <div class="page-header-meta">
            <h1 class="page-title">Riwayat Ritase Armada</h1>
            <span class="count-indicator">{{ $ritases->total() }} total ritase</span>
        </div>
        <div>
            <button type="button" class="btn" onclick="openModal('modal-ritase')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px; vertical-align: -2px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Tambah Ritase
            </button>
        </div>
    </div>

    <form method="GET" action="{{ route('ritase.index') }}" class="master-toolbar" style="margin-bottom: 18px; border-radius: 12px; border: 1px solid var(--border-card, #e5e7eb);">
        <div class="master-control master-control-search">
            <label for="history-search">Cari riwayat</label>
            <input class="form-control" id="history-search" name="search" value="{{ $search }}" placeholder="Nomor, plat, nota, tujuan">
        </div>
        <div class="master-control">
            <label for="history-vehicle">Armada</label>
            <select class="form-control" id="history-vehicle" name="vehicle_id">
                <option value="">Semua armada</option>
                @foreach($vehicles as $v)
                    <option value="{{ $v->id }}" @selected(($filters['vehicle_id'] ?? '') == $v->id)>{{ $v->license_plate }}{{ $v->status === 'active' ? '' : ' (nonaktif)' }}</option>
                @endforeach
            </select>
        </div>
        <div class="master-control">
            <label for="history-start">Dari</label>
            <input class="form-control" type="date" id="history-start" name="start_date" value="{{ $filters['start_date'] ?? '' }}">
        </div>
        <div class="master-control">
            <label for="history-end">Sampai</label>
            <input class="form-control" type="date" id="history-end" name="end_date" value="{{ $filters['end_date'] ?? '' }}">
        </div>
        <div class="master-control">
            <label for="history-status">Status</label>
            <select class="form-control" id="history-status" name="status">
                <option value="">Semua status</option>
                <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>Lengkap</option>
                <option value="pending_details" @selected(($filters['status'] ?? '') === 'pending_details')>Perlu detail</option>
            </select>
        </div>
        <div class="master-control">
            <label for="history-page-size">Baris</label>
            <select class="form-control" id="history-page-size" name="per_page">
                @foreach([10, 15, 25, 50] as $size)
                    <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>
        <div class="master-toolbar-actions">
            <button class="btn" type="submit">Terapkan</button>
            <a class="btn btn-secondary" href="{{ route('ritase.index') }}">Reset</a>
        </div>
    </form>
    @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
    <div aria-live="polite" style="margin-bottom: 18px;">
        <h2 style="font-size: 16px; margin: 0 0 6px;">Ringkasan hasil filter</h2>
        <p style="margin: 0 0 10px;">{{ $summary['count'] }} ritase &middot; Total biaya Rp {{ $formatRitaseAmount($summary['total']) }} <small>(seluruh hasil, bukan hanya halaman ini)</small></p>
        @if($vehicleTotals->isNotEmpty())
            <div class="table-responsive" style="overflow-x: auto;"><table style="width: 100%; border-collapse: collapse;"><thead><tr><th>Armada</th><th style="text-align: right;">Ritase</th><th style="text-align: right;">Total biaya</th><th>Riwayat</th></tr></thead><tbody>
                @foreach($vehicleTotals as $item)
                    <tr><td>{{ $item->license_plate ?? ('Armada #' . $item->vehicle_id) }}</td><td style="text-align: right;">{{ $item->trip_count }}</td><td style="text-align: right; font-variant-numeric: tabular-nums;">Rp {{ $formatRitaseAmount($item->total_cost) }}</td><td><a href="{{ request()->fullUrlWithQuery(['vehicle_id' => $item->vehicle_id, 'page' => null]) }}">Lihat riwayat</a></td></tr>
                @endforeach
            </tbody></table></div>
        @endif
    </div>
    <div class="table-responsive" style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th><a class="th-sort" href="{{ request()->fullUrlWithQuery(['sort' => 'ritase_number', 'dir' => ($sort === 'ritase_number' && $dir === 'asc') ? 'desc' : 'asc']) }}">Nomor @if($sort === 'ritase_number')<span class="th-arrow">{{ $dir === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                    <th><a class="th-sort" href="{{ request()->fullUrlWithQuery(['sort' => 'ritase_date', 'dir' => ($sort === 'ritase_date' && $dir === 'asc') ? 'desc' : 'asc']) }}">Tanggal @if($sort === 'ritase_date')<span class="th-arrow">{{ $dir === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                    <th><a class="th-sort" href="{{ request()->fullUrlWithQuery(['sort' => 'driver_name', 'dir' => ($sort === 'driver_name' && $dir === 'asc') ? 'desc' : 'asc']) }}">Sopir @if($sort === 'driver_name')<span class="th-arrow">{{ $dir === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                    <th><a class="th-sort" href="{{ request()->fullUrlWithQuery(['sort' => 'destination', 'dir' => ($sort === 'destination' && $dir === 'asc') ? 'desc' : 'asc']) }}">Tujuan &amp; Muatan @if($sort === 'destination')<span class="th-arrow">{{ $dir === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                    <th style="text-align: right;"><a class="th-sort" href="{{ request()->fullUrlWithQuery(['sort' => 'total_cost', 'dir' => ($sort === 'total_cost' && $dir === 'asc') ? 'desc' : 'asc']) }}">Total Biaya @if($sort === 'total_cost')<span class="th-arrow">{{ $dir === 'asc' ? '↑' : '↓' }}</span>@endif</a></th>
                </tr>
            </thead>
            <tbody>
                @forelse($ritases as $r)
                <tr>
                    <td><strong class="sku-code">{{ $r->ritase_number }}</strong><div style="font-size: 11px; color: var(--text-caption);">{{ $r->status === 'pending_details' ? 'Perlu detail' : 'Lengkap' }}</div></td>
                    <td style="color: var(--text-caption); font-size: 12px; white-space: nowrap;">{{ $r->ritase_date }}</td>
                    <td>
                        <div style="font-weight: 600;">{{ $r->vehicle_plate ?? ('Armada #' . $r->vehicle_id) }}</div>
                            <div style="font-size: 11.5px; color: var(--text-muted);">{{ $r->driver_name ?: 'Detail sopir menunggu' }}</div>
                    </td>
                    <td>
                        <div>{{ $r->destination ?: 'Tujuan menunggu detail' }}</div>
                        @if($r->cargo_summary)
                            <div style="font-size: 11px; color: var(--text-caption);">Muatan: {{ $r->cargo_summary }}</div>
                        @endif
                        @if(!empty($r->linked_invoice_number))
                            <div style="font-size: 11px; color: var(--primary); font-weight: 700;">Nota: {{ $r->linked_invoice_number }}</div>
                        @elseif(!empty($r->sale_id))
                            <div style="font-size: 11px; color: var(--primary);">Nota #{{ $r->sale_id }}</div>
                        @elseif(!empty($r->linked_receipt_number))
                            <div style="font-size: 11px; color: var(--primary);">Penerimaan: {{ $r->linked_receipt_number }}</div>
                        @else
                            <div style="font-size: 11px; color: var(--text-caption);">(Mandiri)</div>
                        @endif
                        @if($r->status === 'pending_details')
                            <details style="margin-top: 8px;">
                                <summary style="cursor: pointer; font-weight: 700;">Lengkapi sopir dan tujuan</summary>
                                <form method="POST" action="{{ route('ritase.details', ['id' => $r->id]) }}" style="padding-top: 10px; display: grid; gap: 8px;">
                                    @csrf
                                    @method('PATCH')
                                    <label for="driver-{{ $r->id }}">Nama sopir</label>
                                    <input id="driver-{{ $r->id }}" name="driver_name" class="form-control" maxlength="100" required>
                                    <label for="destination-{{ $r->id }}">Tujuan</label>
                                    <input id="destination-{{ $r->id }}" name="destination" class="form-control" maxlength="255" required>
                                    <label for="cargo-{{ $r->id }}">Muatan (opsional)</label>
                                    <input id="cargo-{{ $r->id }}" name="cargo_summary" class="form-control" maxlength="255">
                                    <button class="btn" type="submit">Simpan detail</button>
                                </form>
                            </details>
                        @endif
                    </td>
                    <td style="text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; color: var(--primary-hover); white-space: nowrap;">Rp {{ $formatRitaseAmount($r->total_cost) }}</td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align: center; color: var(--text-caption); padding: 28px;">Belum ada catatan ritase armada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('partials.pagination', ['paginator' => $ritases])
</div>
@endsection

@section('modals')
<div class="modal-overlay" id="modal-ritase" role="dialog" aria-modal="true" aria-labelledby="modal-ritase-title">
    <div class="modal-dialog" style="max-width: 580px;">
        <div class="modal-header">
            <div class="modal-title-wrapper">
                <div class="modal-icon-badge">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                </div>
                <h3 class="modal-title" id="modal-ritase-title">Catat Log Ritase Pengiriman</h3>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('modal-ritase')" aria-label="Tutup">&times;</button>
        </div>

        <form action="{{ route('ritase.store') }}" method="POST" style="display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden; margin: 0;">
            @csrf
            <div class="modal-body" style="overflow-y: auto; flex: 1; padding: 20px 24px;">
                <div class="form-group">
                    <label for="vehicle_id">Armada Kendaraan</label>
                    <select name="vehicle_id" id="vehicle_id" class="form-control" required>
                        <option value="">-- Pilih Kendaraan --</option>
                        @foreach($activeVehicles as $v)
                            <option value="{{ $v->id }}" {{ old('vehicle_id') == $v->id ? 'selected' : '' }}>
                                {{ $v->license_plate }} ({{ strtoupper($v->vehicle_type) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="sale_id">Kaitan Nota Penjualan (Opsional)</label>
                    <select name="sale_id" id="sale_id" class="form-control">
                        <option value="">-- Angkutan Mandiri / Standalone --</option>
                        @if(isset($sales))
                            @foreach($sales as $s)
                                <option value="{{ $s->id }}" {{ old('sale_id') == $s->id ? 'selected' : '' }}>
                                    {{ $s->invoice_number }} (Rp {{ number_format($s->total_amount, 0, ',', '.') }})
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <div class="form-group">
                    <label for="driver_name">Nama Pengemudi (Sopir)</label>
                    <input type="text" name="driver_name" id="driver_name" class="form-control" maxlength="100" placeholder="Nama sopir" value="{{ old('driver_name') }}" required>
                </div>

                <div class="form-group">
                    <label for="destination">Tujuan Pengiriman</label>
                    <input type="text" name="destination" id="destination" class="form-control" maxlength="255" placeholder="Alamat / lokasi proyek tujuan" value="{{ old('destination') }}" required>
                </div>

                <div class="form-group">
                    <label for="cargo_summary">Ringkasan Muatan Material</label>
                    <input type="text" name="cargo_summary" id="cargo_summary" class="form-control" maxlength="255" placeholder="Contoh: Semen 50 sak, Pasir 1 rit" value="{{ old('cargo_summary') }}">
                </div>

                <div class="grid-2" style="gap: 12px; margin-bottom: 0;">
                    <div class="form-group">
                        <label for="fuel_cost">Biaya BBM (Rp)</label>
                        <input type="text" inputmode="decimal" name="fuel_cost" id="fuel_cost" class="form-control tabular-nums" placeholder="0,00" value="{{ old('fuel_cost') }}" data-currency-input data-currency-scale="2">
                    </div>
                    <div class="form-group">
                        <label for="driver_allowance">Uang Jalan Sopir (Rp)</label>
                        <input type="text" inputmode="decimal" name="driver_allowance" id="driver_allowance" class="form-control tabular-nums" placeholder="0,00" value="{{ old('driver_allowance') }}" data-currency-input data-currency-scale="2">
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="flex-shrink: 0; padding: 16px 24px; border-top: 1px solid var(--border-subtle); background-color: var(--surface); display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-ritase')">Batal</button>
                <button type="submit" class="btn" style="font-weight: 700;">Simpan Data Ritase</button>
            </div>
        </form>
    </div>
</div>

@if($errors->any())
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof openModal === 'function') {
            openModal('modal-ritase');
        }
    });
</script>
@endif
@endsection
