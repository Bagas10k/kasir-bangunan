<?php

namespace App\Services\V2;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Penjaga idempotency lintas operasi keuangan V2 (pengeluaran operasional, pindah dana).
 * Mencegah pencatatan ganda akibat klik berulang atau kirim ulang setelah koneksi terputus.
 */
final class OperationIdempotency
{
    public function normalizeKey(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $key = trim((string) $raw);
        if ($key === '') {
            return null;
        }

        if (strlen($key) < 8 || strlen($key) > 100 || preg_match('/^[\x20-\x7E]+$/', $key) !== 1) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Kunci transaksi tidak valid.',
            ]);
        }

        return $key;
    }

    /**
     * @param  array<string, mixed>  $canonicalPayload
     */
    public function hashPayload(array $canonicalPayload): string
    {
        return hash('sha256', json_encode($canonicalPayload, JSON_THROW_ON_ERROR));
    }

    /**
     * Harus dipanggil di dalam DB::transaction() sebelum menulis data.
     * Mengembalikan payload hasil sebelumnya bila kunci dan isi permintaan sama persis.
     *
     * @return array<string, mixed>|null
     */
    public function replay(string $scope, int $actor, ?string $key, string $requestHash): ?array
    {
        if ($key === null || ! Schema::hasTable('v2_operation_idempotencies')) {
            return null;
        }

        DB::table('users')->where('id', $actor)->lockForUpdate()->first();

        $existing = DB::table('v2_operation_idempotencies')
            ->where('scope', $scope)
            ->where('idempotency_key', $key)
            ->lockForUpdate()
            ->first();

        if (! $existing) {
            return null;
        }

        abort_unless((int) $existing->actor_id === $actor, 403);

        if (! hash_equals((string) $existing->request_hash, $requestHash)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Kunci transaksi sudah dipakai untuk isian berbeda. Tutup lalu buka ulang formulir sebelum mencoba lagi.',
            ]);
        }

        $decoded = json_decode((string) $existing->response_payload, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Harus dipanggil di dalam DB::transaction() yang sama setelah entitas & jurnal tersimpan.
     *
     * @param  array<string, mixed>  $responsePayload
     */
    public function record(string $scope, int $actor, ?string $key, string $requestHash, int $resourceId, array $responsePayload): void
    {
        if ($key === null || ! Schema::hasTable('v2_operation_idempotencies')) {
            return;
        }

        $now = now();
        DB::table('v2_operation_idempotencies')->insert([
            'scope' => $scope,
            'actor_id' => $actor,
            'idempotency_key' => $key,
            'request_hash' => $requestHash,
            'resource_id' => $resourceId,
            'response_payload' => json_encode($responsePayload, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
