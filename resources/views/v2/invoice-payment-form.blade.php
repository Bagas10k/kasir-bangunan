@php
    $paymentFieldId = $inv->id.($paymentFormSuffix ?? '');
    $payLabel = ! $payable ? 'Catat pembayaran' : ($role === 'admin' ? 'Bayar ke supplier' : 'Ajukan pembayaran');
    $payHint = ! $payable
        ? 'Tunai langsung tercatat dan masuk laci kas harian. Transfer menunggu verifikasi admin.'
        : ($role === 'admin'
            ? 'Pilih sumber dana. Kas Utama dan Bank Utama memakai saldo toko dan tidak mengubah laci; Laci shift memakai uang laci yang sedang terbuka.'
            : 'Diajukan ke admin. Kas Utama dan Bank Utama tidak mengubah laci; Laci shift memakai laci toko aktif. Utang berkurang setelah disetujui.');
@endphp
<form class="form-collect" action="{{ route('v2.payments.store', ['id' => $inv->id]) }}" data-invoice="{{ $inv->id }}" data-direction="{{ $inv->direction }}" data-hint="{{ $payHint }}" novalidate>
    <h3 class="sr">{{ $payLabel }} {{ $inv->number }}</h3>
    <div class="row">
        <div class="field">
            <label for="amt-{{ $paymentFieldId }}">Jumlah dibayar</label>
            <input id="amt-{{ $paymentFieldId }}" name="amount" type="text" data-money inputmode="decimal" autocomplete="off" maxlength="30" required>
        </div>
        @unless($payable)
        <div class="field"><label for="meth-{{ $paymentFieldId }}">Metode</label><select id="meth-{{ $paymentFieldId }}" name="method"><option value="cash">Tunai</option><option value="transfer">Transfer</option></select></div>
        @endunless
    </div>
    @if($payable)
    <div class="field collect-source"><label for="meth-{{ $paymentFieldId }}">Sumber dana</label><select id="meth-{{ $paymentFieldId }}" name="method"><option value="main">Kas Utama</option><option value="transfer">Bank Utama</option><option value="cash">Laci shift</option></select></div>
    @endif
    @unless($payable)
    <div class="field" data-transfer-proof-field hidden><label for="transfer-proof-{{ $paymentFieldId }}">Foto bukti transfer</label><input id="transfer-proof-{{ $paymentFieldId }}" name="transfer_proof" type="file" accept="image/jpeg,image/png,image/webp" disabled><p class="note">Wajib bila transfer. JPG/PNG/WebP, maksimal 5 MB. Foto tersimpan pada pembayaran ini dan menunggu verifikasi admin.</p></div>
    @endunless
    <div class="field"><label for="ref-{{ $paymentFieldId }}">Referensi (opsional)</label><input id="ref-{{ $paymentFieldId }}" name="reference" type="text" maxlength="255" autocomplete="off"></div>
    <div class="field"><label for="key-{{ $paymentFieldId }}">Referensi sistem (otomatis)</label><input id="key-{{ $paymentFieldId }}" name="idempotency_key" type="text" maxlength="100" readonly required></div>
    <input type="hidden" name="seen_payment_id" value="{{ (int) $inv->last_payment_id }}">
    <p><button type="submit">{{ $payLabel }}</button></p>
    <p class="err" data-error tabindex="-1" hidden></p>
    <p class="ok" data-ok hidden></p>
</form>
