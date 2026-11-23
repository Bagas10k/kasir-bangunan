<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Penulisan log tidak boleh menggagalkan operasi bisnis.
 * Media log (folder storage) bisa tidak dapat ditulis karena izin volume; dalam kondisi itu
 * transaksi uang harus tetap tersimpan, bukan dibatalkan oleh exception dari Monolog.
 */
final class SafeLog
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function info(string $message, array $context = []): void
    {
        try {
            Log::info($message, $context);
        } catch (Throwable) {
            // Diabaikan: kegagalan log bukan alasan membatalkan penyimpanan.
        }
    }
}
