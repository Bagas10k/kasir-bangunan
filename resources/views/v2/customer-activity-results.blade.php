<p class="note">Periode {{ \Carbon\Carbon::parse($historyFilters['start_date'])->format('d/m/Y') }}–{{ \Carbon\Carbon::parse($historyFilters['end_date'])->format('d/m/Y') }} · {{ $historySales->total() }} transaksi</p>
@if($historySales->isEmpty())
    <p class="note">Tidak ada transaksi pelanggan ini pada periode tersebut.</p>
@else
    <div class="ui-table-wrap" tabindex="0" role="region" aria-label="Transaksi pelanggan">
        <table class="ui-table">
            <thead><tr><th>Nota</th><th>Pembayaran</th><th class="ui-num">Total</th><th class="ui-num">Sisa tagihan</th></tr></thead>
            <tbody>
                @foreach($historySales as $sale)
                    @php
                        $remaining = \Brick\Math\BigDecimal::of((string) ($sale->invoice_amount ?? ($sale->status === 'paid' ? '0' : $sale->total)))->minus((string) ($sale->invoice_paid ?? '0'));
                    @endphp
                    <tr>
                        <td class="ui-primary" data-label="Nota"><a href="{{ route('v2.documents', ['id' => $sale->id, 'kind' => 'receipt']) }}" target="_blank" rel="noopener">{{ $sale->number }}</a><span class="ui-sub">{{ \Carbon\Carbon::parse($sale->created_at)->format('d/m/Y H:i') }}</span></td>
                        <td data-label="Pembayaran">{{ ['cash'=>'Tunai','transfer'=>'Transfer','credit'=>'Kredit'][$sale->payment_method] ?? $sale->payment_method }}</td>
                        <td data-label="Total" class="ui-num">{{ \App\Services\V2\Money::display($sale->total) }}</td>
                        <td data-label="Sisa tagihan" class="ui-num">{{ \App\Services\V2\Money::display($remaining->isPositive() ? (string) $remaining : '0') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($historySales->hasPages())
        <nav class="detail-actions" aria-label="Halaman transaksi pelanggan">
            @unless($historySales->onFirstPage())<button type="button" class="secondary" data-customer-page="{{ $historySales->currentPage() - 1 }}">Sebelumnya</button>@endunless
            @if($historySales->hasMorePages())<button type="button" class="secondary" data-customer-page="{{ $historySales->currentPage() + 1 }}">Berikutnya</button>@endif
        </nav>
    @endif
@endif
