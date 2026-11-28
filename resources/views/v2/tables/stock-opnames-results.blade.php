@php
    $opnameQty = function ($value): string {
        [$int, $frac] = array_pad(explode('.', (string) $value), 2, '');
        $frac = rtrim($frac, '0');
        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', ltrim($int, '0') === '' ? '0' : ltrim($int, '0')).($frac !== '' ? ','.$frac : '');
    };
@endphp
<div class="ui-table-wrap scroll" tabindex="0" role="region" aria-label="Riwayat stok opname">
    <table class="ui-table" id="stock-opname-table">
        <caption class="sr">Riwayat stok opname</caption>
        <thead><tr><th scope="col">Tanggal</th><th scope="col">Nomor</th><th scope="col">Petugas</th><th scope="col" class="ui-num">Item</th><th scope="col" class="ui-num">Selisih lebih</th><th scope="col" class="ui-num">Selisih kurang</th><th scope="col" class="ui-actions">Aksi</th></tr></thead>
        <tbody>
        @forelse(($stockOpnames ?? collect()) as $opname)
            @php
                $lines = ($stockOpnameItems ?? collect())->get($opname->id, collect());
                $hasVariance = \Brick\Math\BigDecimal::of((string) $opname->gain_total)->isPositive()
                    || \Brick\Math\BigDecimal::of((string) $opname->loss_total)->isPositive();
            @endphp
            <tr data-variance="{{ $hasVariance ? 'yes' : 'no' }}" data-ui-search-text="{{ $lines->pluck('product_name')->implode(' ') }}">
                <td data-label="Tanggal" class="ui-nowrap">{{ \Carbon\Carbon::parse($opname->opname_date)->locale('id')->translatedFormat('d M Y') }}</td>
                <td data-label="Nomor" class="ui-primary ui-nowrap">{{ $opname->number }}@if($opname->notes)<span class="ui-sub">{{ \Illuminate\Support\Str::limit($opname->notes, 45) }}</span>@endif</td>
                <td data-label="Petugas">{{ $opname->author_name ?? '—' }}</td>
                <td data-label="Item" class="ui-num">{{ $lines->count() }}</td>
                <td data-label="Selisih lebih" class="ui-num">{{ \App\Services\V2\Money::display($opname->gain_total) }}</td>
                <td data-label="Selisih kurang" class="ui-num @if(\Brick\Math\BigDecimal::of((string) $opname->loss_total)->isPositive()) ui-neg @endif">{{ \App\Services\V2\Money::display($opname->loss_total) }}</td>
                <td data-label="Aksi" class="ui-actions">
                    <button type="button" class="btn-action-pill popup-action" data-open-popup="opname-detail-{{ $opname->id }}">Detail</button>
                    <dialog class="input-dialog uf-dialog uf-dialog-wide" id="opname-detail-{{ $opname->id }}" aria-labelledby="opname-detail-title-{{ $opname->id }}">
                        <div class="popup-heading">
                            <div><h2 id="opname-detail-title-{{ $opname->id }}">Detail stok opname</h2><p class="uf-dialog-sub">{{ $opname->number }}</p></div>
                            <button type="button" class="popup-close secondary" data-close-dialog aria-label="Tutup detail opname">×</button>
                        </div>
                        <div class="popup-content detail-content">
                            <dl class="detail-facts">
                                <div><dt>Tanggal</dt><dd>{{ \Carbon\Carbon::parse($opname->opname_date)->locale('id')->translatedFormat('d F Y') }}</dd></div>
                                <div><dt>Petugas</dt><dd>{{ $opname->author_name ?? '—' }}</dd></div>
                                <div><dt>Selisih lebih</dt><dd>{{ \App\Services\V2\Money::display($opname->gain_total) }}</dd></div>
                                <div><dt>Selisih kurang</dt><dd>{{ \App\Services\V2\Money::display($opname->loss_total) }}</dd></div>
                                @if($opname->notes)<div><dt>Catatan</dt><dd>{{ $opname->notes }}</dd></div>@endif
                            </dl>
                            <div class="ui-table-wrap" tabindex="0" role="region" aria-label="Rincian barang opname">
                                <table class="ui-table">
                                    <thead><tr><th scope="col">Produk</th><th scope="col" class="ui-num">Sistem</th><th scope="col" class="ui-num">Fisik</th><th scope="col" class="ui-num">Selisih</th><th scope="col" class="ui-num">Nilai</th></tr></thead>
                                    <tbody>
                                    @foreach($lines as $line)
                                        <tr>
                                            <td class="ui-primary" data-label="Produk">{{ $line->product_name }}</td>
                                            <td class="ui-num" data-label="Sistem">{{ $opnameQty($line->system_qty) }} {{ $line->product_unit }}</td>
                                            <td class="ui-num" data-label="Fisik">{{ $opnameQty($line->physical_qty) }} {{ $line->product_unit }}</td>
                                            <td class="ui-num" data-label="Selisih">{{ $opnameQty($line->difference_qty) }} {{ $line->product_unit }}</td>
                                            <td class="ui-num" data-label="Nilai">{{ \App\Services\V2\Money::display($line->value_difference) }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </dialog>
                </td>
            </tr>
        @empty
            <tr data-ui-empty-static><td colspan="7" class="ui-empty"><strong>Tidak ada catatan stok opname sesuai saringan.</strong>Tekan + Catat stok opname untuk menyesuaikan stok fisik.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@include('v2.tables.pagination', ['paginator' => $stockOpnames, 'noun' => 'opname'])
