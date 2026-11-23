@php $invoiceWorkspace = route(config('v2.enabled') ? 'pos.index' : 'v2.index'); @endphp
<style>
html body #tagihan #invoice-table td.ui-actions>:is(button,.popup-action){min-height:44px !important;height:44px !important}
html body #tagihan #invoice-table td.ui-actions>.popup-action{background:#14532D !important;border-color:#14532D !important;color:#fff !important}
html body [data-invoice-detail] .invoice-payment-history{padding-left:20px;display:grid;gap:16px;font-size:14px;line-height:1.5}
html body [data-invoice-detail] .invoice-payment-history p{margin:4px 0;overflow-wrap:anywhere}
</style>
<section class="card" id="tagihan" aria-labelledby="h-collect" data-live-skip>
    <h2 id="h-collect">Daftar tagihan</h2>
    <p class="note">Awalnya menampilkan yang belum lunas, termasuk pembayaran sebagian. Gunakan Filter untuk riwayat lunas atau semua status.</p>
    <div class="ui-subtabs" id="invoice-tabs" role="tablist" aria-label="Jenis tagihan">
        @foreach(['receivable'=>'Piutang pelanggan', 'payable'=>'Utang supplier'] as $direction=>$label)
            <a class="ui-subtab" style="min-height:44px" id="invoice-tab-{{ $direction }}" data-invoice-tab="{{ $direction }}" aria-selected="{{ $invoiceFilters['invoice_direction'] === $direction ? 'true' : 'false' }}" tabindex="{{ $invoiceFilters['invoice_direction'] === $direction ? '0' : '-1' }}" role="tab" aria-controls="invoice-results" href="{{ $invoiceWorkspace.'?'.http_build_query(array_merge($invoiceFilters, ['invoice_direction'=>$direction, 'invoice_page'=>1])) }}#tagihan">{{ $label }}</a>
        @endforeach
    </div>
    <form method="get" action="{{ $invoiceWorkspace }}#tagihan" id="invoice-filter-form" class="sales-filter-grid" data-filter-inline data-invoice-url="{{ route('v2.invoices.index') }}">
        <input type="hidden" name="invoice_direction" value="{{ $invoiceFilters['invoice_direction'] }}">
        <div class="field"><label for="invoice-search">Cari tagihan</label><input id="invoice-search" name="invoice_search" type="search" maxlength="100" value="{{ $invoiceFilters['invoice_search'] }}" placeholder="Cari nomor atau nama…"></div>
        <div class="field"><label for="invoice-status">Status</label><select id="invoice-status" name="invoice_status">@foreach(['unpaid'=>'Belum lunas','paid'=>'Lunas','all'=>'Semua status'] as $value=>$label)<option value="{{ $value }}" @selected($invoiceFilters['invoice_status'] === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="field field-date-range"><label for="invoice-start">Tanggal tagihan</label><input id="invoice-start" name="invoice_start" type="date" data-date-range-start="invoice-end" value="{{ $invoiceFilters['invoice_start'] }}"><input id="invoice-end" name="invoice_end" type="date" data-date-range-end aria-label="Sampai tanggal tagihan" value="{{ $invoiceFilters['invoice_end'] }}"></div>
        <div class="field"><label for="invoice-per-page">Jumlah baris</label><select id="invoice-per-page" name="invoice_per_page">@foreach([10,25,50] as $size)<option value="{{ $size }}" @selected($invoiceFilters['invoice_per_page'] === $size)>{{ $size }} baris</option>@endforeach</select></div>
        <div class="sales-filter-actions"><button type="submit">Tampilkan</button><a id="invoice-reset" class="page-btn" href="{{ $invoiceWorkspace.'?invoice_direction='.$invoiceFilters['invoice_direction'] }}#tagihan">Reset</a></div>
    </form>
    <p class="err" id="invoice-error" role="alert" tabindex="-1" hidden></p>
    <div id="invoice-results" role="tabpanel" aria-labelledby="invoice-tab-{{ $invoiceFilters['invoice_direction'] }}" aria-live="polite" data-invoice-page="{{ $invoiceFilters['invoice_page'] }}">@include('v2.invoice-results')</div>
</section>
