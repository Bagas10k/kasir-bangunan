@php
    $receiptTypes = ['purchase' => ['Pembelian', 'ok'], 'opening' => ['Stok awal', 'mute'], 'restock' => ['Tambah stok', 'info']];
    $receiptItems = $receiptItems ?? collect();
    $qty = function ($value): string {
        [$int, $frac] = array_pad(explode('.', (string) $value), 2, '');
        $frac = rtrim($frac, '0');
        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', ltrim($int, '0') === '' ? '0' : ltrim($int, '0')).($frac !== '' ? ','.$frac : '');
    };
    $receiptPay = ['2101' => ['payable', 'Utang supplier'], '1101' => ['cash', 'Tunai'], '1102' => ['bank', 'Bank'], '3101' => ['equity', 'Modal awal']];
@endphp
<div class="ui-table-wrap scroll" tabindex="0" role="region" aria-label="Riwayat penerimaan barang">
<table class="ui-table">
<caption class="sr">Riwayat penerimaan barang</caption>
<thead><tr><th scope="col">Tanggal</th><th scope="col">Nomor dan pemasok</th><th scope="col">Produk</th><th scope="col">Tipe</th><th scope="col" class="ui-num">Total</th><th scope="col" class="ui-actions">Aksi</th></tr></thead>
<tbody>
@forelse($receiptHistory as $receipt)
@php
    [$payKey, $payLabel] = $receiptPay[$receipt->credit_account ?? ''] ?? ['other', (string) ($receipt->credit_account ?? '—')];
    [$typeLabel, $typeTone] = $receiptTypes[$receipt->receipt_type ?? ''] ?? [(string) ($receipt->receipt_type ?? '—'), 'mute'];
    $lines = $receiptItems->get($receipt->id, collect());
    $account = (string) ($receipt->credit_account ?? '');
    $voided = $receipt->voided_at !== null;
@endphp
<tr data-pay="{{ $payKey }}" data-type="{{ $receipt->receipt_type }}" data-ui-search-text="{{ $lines->pluck('product_name')->implode(' ') }}" data-amount="{{ $voided ? '0' : $receipt->total_amount }}" @class(['rh-void' => $voided])>
<td data-label="Tanggal" class="ui-nowrap rh-date">{{ \Carbon\Carbon::parse($receipt->receipt_date)->locale('id')->translatedFormat('d M Y') }}</td>
<td data-label="Nomor" class="ui-primary rh-number">{{ $receipt->receipt_number ?: '#'.$receipt->id }}<span class="ui-sub">{{ $receipt->supplier_name ? 'Pemasok: '.$receipt->supplier_name : ($receipt->receipt_type === 'opening' ? 'Stok awal gudang' : 'Tanpa pemasok') }}@if(! empty($receipt->supplier_invoice_number))<span class="rh-ref"> · Nota {{ $receipt->supplier_invoice_number }}</span>@endif
@if($receipt->notes)<span class="rh-ref"> · {{ \Illuminate\Support\Str::limit($receipt->notes, 40) }}</span>@endif</span></td>
<td data-label="Produk" class="rh-product">@if($lines->isNotEmpty())<strong>{{ $lines->count() }} jenis barang</strong><span class="ui-sub">{{ $lines->first()->product_name }}@if($lines->count() > 1) + {{ $lines->count() - 1 }} lainnya @endif</span>@else<span class="ui-muted">—</span>@endif</td>
<td data-label="Tipe" class="rh-type">@if($voided)<span class="ui-badge ui-badge--bad">Dibatalkan</span><span class="ui-sub rh-void-note">{{ \Carbon\Carbon::parse($receipt->voided_at)->locale('id')->translatedFormat('d M Y H:i') }}{{ $receipt->voided_by_name ? ' · '.$receipt->voided_by_name : '' }} · {{ $receipt->void_reason }}</span>@else<span class="ui-badge ui-badge--{{ $typeTone }}">{{ $typeLabel }}</span>@endif<span class="ui-sub" data-tip="{{ $payLabel }}">Akun {{ $account !== '' ? $account : '—' }} · {{ $payLabel }}</span></td>
<td data-label="Total" class="ui-num rh-total"><strong>{{ \App\Services\V2\Money::display($receipt->total_amount) }}</strong>@if(\Brick\Math\BigDecimal::of((string) ($receipt->freight_amount ?? '0'))->isPositive())<span class="ui-sub">Nilai barang</span><span class="ui-sub">Ongkir {{ \App\Services\V2\Money::display($receipt->freight_amount) }} · {{ ['supplier'=>'Utang supplier','cash'=>'Kas','bank'=>'Bank'][$receipt->freight_method] ?? '—' }}</span>@endif</td>
<td data-label="Aksi" class="ui-actions">
    <button type="button" class="btn-action-pill" data-open-popup="receipt-detail-{{ $receipt->id }}">Detail</button>
    @if(!$voided && ($receipt->receipt_type ?? '') === 'purchase')
        <button type="button" class="secondary" data-purchase-return="{{ $receipt->id }}" data-returnable-url="{{ route('v2.receipts.returnable', $receipt->id) }}" data-post-url="{{ route('v2.receipts.returns.store', $receipt->id) }}">Retur</button>
    @endif
    @include('v2.receipt-history-detail')
</td>
</tr>
@empty
<tr data-ui-empty-static><td colspan="6" class="ui-empty"><strong>Tidak ada penerimaan barang sesuai saringan.</strong>Tekan "Catat penerimaan" untuk mencatat barang masuk.</td></tr>
@endforelse
</tbody>
</table>
</div>
@include('v2.tables.pagination', ['paginator' => $receiptHistory, 'noun' => 'penerimaan'])
