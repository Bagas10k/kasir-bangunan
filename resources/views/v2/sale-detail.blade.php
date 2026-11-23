@php
    $money = fn ($value): string => \App\Services\V2\Money::display($value);
    $sale = $document['sale'];
    $rest = $inv ? \Brick\Math\BigDecimal::of((string) $inv->amount)->minus((string) $inv->paid) : \Brick\Math\BigDecimal::of($sale['status'] === 'paid' ? '0' : $sale['total']);
    $paid = $inv->paid ?? ($sale['status'] === 'paid' ? $sale['total'] : '0');
    $state = $rest->isLessThanOrEqualTo(0) ? 'paid' : (\Brick\Math\BigDecimal::of((string) $paid)->isPositive() ? 'partial' : 'unpaid');
    $methodLabels = ['cash'=>'Tunai', 'transfer'=>'Transfer', 'credit'=>'Kredit'];
    $payable = false;
@endphp
<div id="sale-detail-view">
    <header class="sd-identity">
        <div>
            <p class="sd-eyebrow">Nota penjualan</p>
            <h3 tabindex="-1" data-sale-detail-heading>{{ $sale['number'] }}</h3>
            <p class="sd-buyer">{{ $document['customer']['name'] ?? 'Pelanggan umum' }}</p>
        </div>
        <x-ui.status :status="$state"/>
    </header>

    <dl class="sd-meta">
        <div><dt>Tanggal transaksi</dt><dd>{{ \Illuminate\Support\Carbon::parse($sale['date'])->locale('id')->translatedFormat('d F Y') }}</dd></div>
        <div><dt>Metode awal</dt><dd>{{ $methodLabels[$sale['payment_method']] ?? '—' }}</dd></div>
        <div><dt>Penyerahan</dt><dd>{{ $sale['fulfillment'] === 'delivery' ? 'Dikirim' : 'Diambil di toko' }}</dd></div>
    </dl>

    <section class="sd-section" aria-labelledby="sale-items-title">
        <div class="sd-section-heading"><h4 id="sale-items-title">Rincian barang</h4><span>{{ count($document['items']) }} jenis barang</span></div>
        <div class="sd-table-scroll" role="region" aria-label="Rincian barang penjualan" tabindex="0">
            <table class="sd-items">
                <caption class="sr">Barang, jumlah, harga satuan, diskon, dan nilai setelah diskon</caption>
                <thead><tr><th scope="col">Barang</th><th scope="col" class="sd-number">Jumlah</th><th scope="col" class="sd-number">Harga satuan</th><th scope="col" class="sd-number">Diskon</th><th scope="col" class="sd-number">Subtotal</th></tr></thead>
                <tbody>@foreach($document['items'] as $item)
                    <tr><td>{{ $item['name'] }}</td><td class="sd-number">{{ rtrim(rtrim($item['quantity'], '0'), '.') }}</td><td class="sd-number">{{ $money($item['unit_price']) }}</td><td class="sd-number">{{ $money($item['discount']) }}</td><td class="sd-number sd-emphasis">{{ $money($item['subtotal']) }}</td></tr>
                @endforeach</tbody>
            </table>
        </div>
        <dl class="sd-adjustments">
            @if(\Brick\Math\BigDecimal::of($sale['invoice_discount'])->isPositive())<div><dt>Diskon nota</dt><dd>−{{ $money($sale['invoice_discount']) }}</dd></div>@endif
            @if(\Brick\Math\BigDecimal::of($sale['delivery_fee'])->isPositive())<div><dt>Ongkir</dt><dd>{{ $money($sale['delivery_fee']) }}</dd></div>@endif
        </dl>
    </section>

    <dl class="sd-summary" aria-label="Ringkasan pembayaran">
        <div><dt>Total belanja</dt><dd>{{ $money($sale['total']) }}</dd></div>
        <div><dt>Sudah dibayar</dt><dd>{{ $money($paid) }}</dd></div>
        <div class="sd-balance"><dt>Sisa tagihan</dt><dd>{{ $money((string) $rest) }}</dd></div>
    </dl>

    <section class="sd-section" aria-labelledby="sale-payments-title">
        <div class="sd-section-heading"><h4 id="sale-payments-title">Riwayat pembayaran</h4><span>{{ $paymentHistory->count() }} pembayaran</span></div>
        <p class="sd-hint">Hanya pembayaran tercatat yang mengurangi sisa tagihan. Transfer menunggu pemeriksaan admin.</p>
        <ol class="sd-payments">
        @forelse($paymentHistory as $payment)
            <li>
                <div class="sd-payment-top">
                    <div><strong class="sd-amount">{{ $money($payment->amount) }}</strong><span class="sd-method">{{ $methodLabels[$payment->method] ?? $payment->method }}</span></div>
                    <x-ui.status :status="$payment->status" :label="$payment->status === 'posted' ? 'Tercatat' : null"/>
                </div>
                <dl class="sd-payment-meta">
                    <div><dt>Waktu pembayaran</dt><dd>{{ $payment->created_at ? \Illuminate\Support\Carbon::parse($payment->created_at)->locale('id')->translatedFormat('d M Y · H:i') : '—' }}</dd></div>
                    <div><dt>Dicatat oleh</dt><dd>{{ $payment->requester_name ?? '—' }}</dd></div>
                    @if($payment->reference)<div><dt>Referensi</dt><dd>{{ $payment->reference }}</dd></div>@endif
                </dl>
                @if($payment->proof_id)
                    <a class="sd-evidence" href="{{ route('v2.payments.proof', $payment->id) }}" target="_blank" rel="noopener">Lihat bukti transfer <span aria-hidden="true">↗</span></a>
                @elseif($payment->method === 'transfer')
                    <p class="sd-hint">Bukti foto belum tersedia pada catatan ini.</p>
                @endif
                @if($payment->resolution_note)<p class="sd-rejection">Alasan penolakan: {{ $payment->resolution_note }}</p>@endif
            </li>
        @empty
            <li class="sd-empty">Belum ada pembayaran pada nota ini.</li>
        @endforelse
        </ol>
    </section>

    @if($inv && $rest->isPositive())
        <section class="sd-section sd-collection" aria-labelledby="sale-collection-title">
            <div class="sd-section-heading"><h4 id="sale-collection-title">Catat pembayaran</h4></div>
            <p class="sd-hint">Pembayaran sebagian atau pelunasan tetap dicatat pada nota yang sama.</p>
            @include('v2.invoice-payment-form', ['paymentFormSuffix' => '-sale'])
        </section>
    @endif
    <div class="sd-print-action" data-sale-actions>
        <a target="_blank" rel="noopener" href="{{ route('v2.documents', ['id'=>$sale['id'], 'kind'=>'receipt']) }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 8V3h10v5M7 17H4V9h16v8h-3M7 14h10v7H7zM17 11h.01"/></svg>
            Cetak struk/nota
        </a>
    </div>
</div>
