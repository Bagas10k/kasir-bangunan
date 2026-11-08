@props(['status' => '', 'label' => null])
@php
    // Satu peta status -> label + nada. Status tidak pernah hanya berupa warna: selalu ada teks.
    $map = [
        'pending' => ['Menunggu verifikasi', 'warn'], 'proposed' => ['Diajukan', 'warn'], 'posted' => ['Terverifikasi', 'ok'],
        'rejected' => ['Ditolak', 'bad'], 'open' => ['Terbuka', 'info'], 'closed' => ['Ditutup', 'mute'],
        'review' => ['Perlu diperiksa', 'warn'], 'prepared' => ['Siap dikirim', 'warn'], 'sent' => ['Sedang dikirim', 'info'],
        'received' => ['Diterima', 'ok'], 'paid' => ['Lunas', 'ok'], 'unpaid' => ['Belum dibayar', 'bad'],
        'partial' => ['Dibayar sebagian', 'warn'], 'active' => ['Aktif', 'ok'], 'archived' => ['Diarsipkan', 'mute'],
        'inactive' => ['Nonaktif', 'mute'], 'internal' => ['Karyawan toko', 'info'], 'external' => ['Sopir luar', 'mute'],
        'info' => ['Info', 'info'], 'admin' => ['Admin', 'info'], 'cashier' => ['Kasir', 'mute'], 'draft' => ['Draf', 'mute'],
    ];
    [$text, $tone] = $map[$status] ?? [$status === '' ? '—' : (string) $status, 'mute'];
@endphp
<span class="ui-badge ui-badge--{{ $tone }}" data-status="{{ $status }}">{{ $label ?? $text }}</span>
