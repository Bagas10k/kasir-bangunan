<section class="card pos-recent-card pos-history-full" id="sales-history" aria-labelledby="h-sales" data-live-skip data-history-today="{{ now()->toDateString() }}" data-history-default-start="{{ now()->subDays(6)->toDateString() }}" data-history-selected="{{ $historyFilters['history_period'] ?? 'week' }}" data-history-page="{{ $historyFilters['history_page'] }}">
<h2 id="h-sales">Riwayat penjualan</h2>
<p class="note">Penjualan dan piutang pada nota yang sama. Pilih nama pembeli untuk detail, cicilan, dan cetak nota.</p>
<form method="get" action="{{ route(config('v2.enabled') ? 'pos.index' : 'v2.index') }}#riwayat-penjualan" class="sales-filter-grid" id="history-filter-form" data-filter-inline data-history-url="{{ route('v2.sales.history') }}" data-filtered="{{ $historyFilters['sale_search'] !== '' || $historyFilters['sale_method'] !== '' || $historyFilters['start_date'] !== now()->subDays(6)->toDateString() || $historyFilters['end_date'] !== now()->toDateString() || $historyFilters['history_per_page'] !== 10 ? '1' : '0' }}">
    <div class="field"><label for="sales-search">Cari nota</label><input id="sales-search" name="sale_search" type="search" class="form-control" maxlength="100" value="{{ $historyFilters['sale_search'] }}" placeholder="Cari nomor nota atau pembeli…"></div>

    <div class="field field-date-range"><label for="sales-start">Rentang tanggal</label><input id="sales-start" name="start_date" type="date" class="form-control" data-date-range-start="sales-end" value="{{ $historyFilters['start_date'] }}"><input id="sales-end" name="end_date" type="date" class="form-control" data-date-range-end aria-label="Sampai tanggal" value="{{ $historyFilters['end_date'] }}"></div>
    <details class="history-options" @if($historyFilters['sale_method'] !== '' || ($historyFilters['sale_status'] ?? '') !== '' || $historyFilters['history_per_page'] !== 10) open @endif>
        <summary>Filter tambahan</summary>
        <div class="history-options-fields">
            <div class="field"><label for="sales-status">Status pembayaran</label><select id="sales-status" name="sale_status"><option value="">Semua penjualan</option><option value="unpaid" @selected(($historyFilters['sale_status'] ?? '') === 'unpaid')>Belum lunas</option><option value="partial" @selected(($historyFilters['sale_status'] ?? '') === 'partial')>Dibayar sebagian</option><option value="paid" @selected(($historyFilters['sale_status'] ?? '') === 'paid')>Lunas</option></select></div>
            <div class="field"><label for="sales-method">Pembayaran</label><select id="sales-method" name="sale_method" class="form-control"><option value="">Semua metode</option>@foreach(['cash'=>'Tunai','transfer'=>'Transfer','credit'=>'Kredit'] as $value=>$label)<option value="{{ $value }}" @selected($historyFilters['sale_method']===$value)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="sales-perpage">Jumlah baris</label><select id="sales-perpage" name="history_per_page" class="form-control">@foreach([10,25,50] as $perPage)<option value="{{ $perPage }}" @selected($historyFilters['history_per_page']===$perPage)>{{ $perPage }} baris</option>@endforeach</select></div>
        </div>
    </details>
    <div class="history-filter-bar">
        <div class="history-periods" role="group" aria-label="Periode riwayat">@foreach(['day'=>'Harian','week'=>'Mingguan','month'=>'Bulanan'] as $period=>$label)<button type="button" data-history-period="{{ $period }}" aria-pressed="{{ ($historyFilters['history_period'] ?? 'week') === $period ? 'true' : 'false' }}">{{ $label }}</button>@endforeach</div>
        <div class="sales-filter-actions"><button type="submit" class="btn-table-action">Tampilkan</button><a id="history-reset" class="btn-table-action" href="{{ route(config('v2.enabled') ? 'pos.index' : 'v2.index') }}#riwayat-penjualan">Atur ulang</a></div>
    </div>
</form>
<p class="err" id="history-error" role="alert" tabindex="-1" hidden></p>
<div id="history-results" aria-live="polite">@include('v2.sales-history-results')</div>
</section>
<style>
html body #panel-riwayat-penjualan #sales-history{margin:0 !important;min-width:0}
html body #sales-history #history-table{width:100%;border-collapse:collapse}
html body #sales-history #history-table .ui-num{font-variant-numeric:tabular-nums;text-align:right}
html body #sales-history #history-table button[data-sale-detail]{min-height:44px;white-space:normal;text-align:left}
html body #sales-history .pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:16px}
html body #sales-history :is(.pagination-controls,.page-list,.doc-links){display:flex;align-items:center;gap:8px;flex-wrap:wrap}
html body #sales-history .page-btn{display:inline-flex;align-items:center;justify-content:center;min-width:44px;min-height:44px}
@media(max-width:760px){html body #sales-history #history-table td.ui-actions{grid-column:1/-1}html body #sales-history .pagination-controls{width:100%}}
</style>
