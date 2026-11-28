@php
    $rp = fn ($value): string => \App\Services\V2\Money::display($value);
    $when = fn ($value): string => $value ? \Carbon\Carbon::parse($value)->locale('id')->translatedFormat('j M Y, H:i') : '—';
    $sourceLabel = ['main' => 'Kas Utama', 'transfer' => 'Bank Utama', 'cash' => 'Laci kas harian'];
@endphp
<div class="ui-table-wrap scroll" tabindex="0" role="region" aria-label="Daftar pembayaran">
    <table class="ui-table">
        <caption class="sr">Daftar pembayaran yang diajukan</caption>
        <thead><tr><th scope="col">Tanggal</th><th scope="col">Tagihan</th><th scope="col">Nama</th><th scope="col">Sumber dana</th><th scope="col" class="ui-num">Jumlah</th>@if($isAdmin)<th scope="col">Pengaju</th>@endif<th scope="col">Status</th>@if($isAdmin)<th scope="col" class="ui-actions">Aksi</th>@endif</tr></thead>
        <tbody>
        @forelse ($payments as $pm)
            <tr data-status="{{ $pm->status ?? '' }}">
                <td data-label="Tanggal" class="ui-nowrap">{{ $when($pm->created_at ?? null) }}</td>
                <td data-label="Tagihan" class="ui-primary ui-nowrap">{{ $pm->invoice_number ?? ('#'.($pm->invoice_id ?? '')) }}<span class="ui-sub">{{ ($pm->invoice_direction ?? '') === 'payable' ? 'Utang supplier' : 'Piutang pelanggan' }}</span></td>
                <td data-label="Nama">{{ $pm->party_name ?? '—' }}</td>
                <td data-label="Sumber dana">{{ $sourceLabel[$pm->method ?? ''] ?? '—' }}@if(($pm->method ?? '') === 'transfer' && !empty($pm->bank_account_code))<span class="ui-sub">{{ $pm->bank_account_code }}{{ isset($bankAccountNames[$pm->bank_account_code]) ? ' · '.$bankAccountNames[$pm->bank_account_code] : '' }}</span>@endif @if($pm->proof_id)<span class="ui-sub"><a href="{{ route('v2.payments.proof', $pm->id) }}" target="_blank" rel="noopener">Lihat bukti transfer</a></span>@endif</td>
                <td data-label="Jumlah" class="ui-num">{{ $rp($pm->amount ?? null) }}</td>
                @if($isAdmin)<td data-label="Pengaju">{{ $pm->requester_name ?? '—' }}</td>@endif
                <td data-label="Status"><x-ui.status :status="$pm->status ?? ''"/></td>
                @if($isAdmin)<td class="ui-actions">
                @if ($isAdmin && in_array(($pm->status ?? ''), ['pending', 'proposed'], true))
                    <form class="form-approve" action="{{ route('v2.payments.approve', ['id' => $pm->id]) }}" novalidate>
                        @if(($pm->method ?? '') === 'transfer' && count($bankAccounts ?? []) > 1)
                            @php $selectedApproveBank = trim((string) ($pm->bank_account_code ?? '')) ?: '1102'; @endphp
                            <label class="sr" for="approve-bank-{{ $pm->id }}">Rekening bank verifikasi</label>
                            <select id="approve-bank-{{ $pm->id }}" name="bank_account_code" style="margin-bottom:6px;max-width:210px">
                                @foreach($bankAccounts as $bankAcc)
                                    <option value="{{ $bankAcc->account_code }}" @selected($selectedApproveBank === (string) $bankAcc->account_code)>{{ $bankAcc->account_code }} · {{ $bankAcc->account_name }}</option>
                                @endforeach
                            </select>
                        @endif
                        <p><button type="submit" class="secondary">Verifikasi pembayaran</button></p>
                        <p class="err" data-error tabindex="-1" hidden></p>
                        <p class="ok" data-ok hidden></p>
                    </form>
                @else
                    <span class="ui-muted">—</span>
                @endif
                </td>@endif
            </tr>
        @empty
            <tr data-ui-empty-static><td colspan="{{ $isAdmin ? 8 : 6 }}" class="ui-empty"><strong>Tidak ada pembayaran sesuai saringan.</strong>Transfer pelanggan dan pengajuan pembayaran supplier akan muncul di sini.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@include('v2.tables.pagination', ['paginator' => $payments, 'noun' => 'pembayaran'])
