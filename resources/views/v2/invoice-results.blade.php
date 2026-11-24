@php
    $invoiceMoney = fn ($value): string => \App\Services\V2\Money::display($value);
    $paymentLabels = ['posted' => 'Tercatat', 'pending' => 'Menunggu verifikasi', 'proposed' => 'Diajukan', 'rejected' => 'Ditolak'];
@endphp
<div class="scroll" tabindex="0" role="region" aria-label="Daftar tagihan">
    <table class="ui-table" id="invoice-table">
        <caption class="sr">{{ $invoiceFilters['invoice_direction'] === 'payable' ? 'Utang supplier' : 'Piutang pelanggan' }} sesuai filter</caption>
        <thead><tr><th scope="col">Nomor</th><th scope="col">Nama</th><th scope="col" class="ui-num">Total</th><th scope="col" class="ui-num">Terbayar</th><th scope="col" class="ui-num">Sisa</th><th scope="col">Status</th><th scope="col" class="ui-actions">Aksi</th></tr></thead>
        <tbody>
        @forelse($invoices as $inv)
            @php
                $rest = \Brick\Math\BigDecimal::of((string) $inv->amount)->minus((string) $inv->paid);
                $state = $rest->isLessThanOrEqualTo(0) ? 'paid' : (\Brick\Math\BigDecimal::of((string) $inv->paid)->isPositive() ? 'partial' : 'unpaid');
                $payable = $inv->direction === 'payable';
            @endphp
            <tr data-invoice-row="{{ $inv->id }}" data-direction="{{ $inv->direction }}" data-state="{{ $state }}" data-remaining="{{ $rest->toScale(2) }}">
                <td data-label="Nomor" class="ui-primary ui-nowrap">{{ $inv->number }}<span class="ui-sub">{{ $inv->created_at ? \Illuminate\Support\Carbon::parse($inv->created_at)->format('d/m/Y') : '—' }}</span></td>
                <td data-label="Nama">{{ $inv->party_name ?? ($payable ? 'Supplier' : 'Pelanggan umum') }}</td>
                <td data-label="Total" class="ui-num">{{ $invoiceMoney($inv->amount) }}</td>
                <td data-label="Terbayar" class="ui-num">{{ $invoiceMoney($inv->paid) }}</td>
                <td data-label="Sisa" class="ui-num"><strong>{{ $invoiceMoney((string) $rest) }}</strong></td>
                <td data-label="Status"><x-ui.status :status="$state"/></td>
                <td class="ui-actions">
                    <button type="button" class="secondary" data-open-popup="input-popup-invoice-detail-{{ $inv->id }}" aria-haspopup="dialog">Detail<span class="sr"> {{ $inv->number }}</span></button>
                    <dialog class="input-dialog popup-wide" data-invoice-detail id="input-popup-invoice-detail-{{ $inv->id }}" aria-labelledby="invoice-detail-title-{{ $inv->id }}">
                        <div class="popup-heading"><div class="popup-titles"><h2 id="invoice-detail-title-{{ $inv->id }}">Detail {{ $inv->number }}</h2></div><button type="button" class="popup-close secondary" data-close-dialog aria-label="Tutup detail">×</button></div>
                        <div class="popup-content">
                            <p>{{ $inv->party_name ?? ($payable ? 'Supplier' : 'Pelanggan umum') }} · {{ $payable ? 'Utang supplier' : 'Piutang pelanggan' }}</p>
                            <dl class="popup-summary">
                                <div><dt>Total</dt><dd>{{ $invoiceMoney($inv->amount) }}</dd></div>
                                <div><dt>Terbayar</dt><dd>{{ $invoiceMoney($inv->paid) }}</dd></div>
                                <div class="is-due"><dt>Sisa</dt><dd>{{ $invoiceMoney((string) $rest) }}</dd></div>
                            </dl>
                            <p>Jatuh tempo: {{ $inv->due_date ? \Illuminate\Support\Carbon::parse($inv->due_date)->format('d/m/Y') : 'Tidak ditentukan' }}</p>
                            <p>Sumber: {{ $inv->sale_id ? 'Penjualan #'.$inv->sale_id : ($inv->receipt_id ? 'Penerimaan #'.$inv->receipt_id : 'Saldo awal / migrasi') }}</p>
                            <h4>Riwayat pembayaran</h4>
                            <p class="note">Hanya pembayaran Tercatat yang mengurangi sisa. Pengajuan, transfer menunggu, dan pembayaran ditolak tetap ditampilkan untuk audit.</p>
                            <ol class="invoice-payment-history">
                            @forelse($inv->payment_history as $payment)
                                <li>
                                    <strong>{{ $invoiceMoney($payment->amount) }} · {{ $paymentLabels[$payment->status] ?? $payment->status }}</strong>
                                    <p>{{ $payment->created_at ? \Illuminate\Support\Carbon::parse($payment->created_at)->format('d/m/Y H:i') : '—' }} · {{ $payment->requester_name ?? '—' }}</p>
                                    <p>{{ $payable ? (['main'=>'Kas Utama','transfer'=>'Bank Utama','cash'=>'Laci kas harian'][$payment->method] ?? '—') : (['cash'=>'Tunai','transfer'=>'Transfer'][$payment->method] ?? '—') }}@if($payment->method === 'transfer' && !empty($payment->bank_account_code)) ({{ $payment->bank_account_code }}{{ isset($bankAccountNames[$payment->bank_account_code]) ? ' · '.$bankAccountNames[$payment->bank_account_code] : '' }})@endif · Referensi: {{ $payment->reference ?? '—' }}</p>
                                    @if($payment->proof_id)<a href="{{ route('v2.payments.proof', $payment->id) }}" target="_blank" rel="noopener">Lihat bukti transfer</a>@elseif($payment->method === 'transfer')<p class="note">Bukti foto belum tersedia pada catatan ini.</p>@endif
                                    @if($payment->approver_name)<p>Disetujui: {{ $payment->approver_name }}</p>@endif
                                    @if($payment->resolution_note)<p>Alasan: {{ $payment->resolution_note }} · {{ $payment->resolver_name ?? '—' }} · {{ $payment->resolved_at }}</p>@endif
                                </li>
                            @empty
                                <li>Belum ada pembayaran tercatat pada tagihan ini.</li>
                            @endforelse
                            </ol>
                        </div>
                        <div class="popup-footer"><button type="button" class="secondary" data-close-dialog>Tutup</button></div>
                    </dialog>
                    @if($state !== 'paid' && ! ($inv->voided_at ?? null))
                        @include('v2.invoice-payment-form')
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="ui-empty">Tidak ada tagihan yang sesuai filter.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pagination" aria-label="Halaman tagihan">
    <p class="pagination-meta">Menampilkan {{ $invoices->firstItem() ?? 0 }}–{{ $invoices->lastItem() ?? 0 }} dari {{ $invoices->total() }} tagihan sesuai filter</p>
    <div class="pagination-controls">
        @if(!$invoices->onFirstPage())<a class="page-btn" href="{{ $invoices->previousPageUrl() }}">Sebelumnya</a>@endif
        @php $from = max(1, min($invoices->currentPage() - 1, $invoices->lastPage() - 2)); @endphp
        @for($page = $from; $page <= min($invoices->lastPage(), $from + 2); $page++)
            @if($page === $invoices->currentPage())<span class="page-btn active" aria-current="page">{{ $page }}</span>
            @else<a class="page-btn" href="{{ $invoices->url($page) }}">{{ $page }}</a>@endif
        @endfor
        @if($invoices->hasMorePages())<a class="page-btn" href="{{ $invoices->nextPageUrl() }}">Lanjut</a>@endif
    </div>
</div>
