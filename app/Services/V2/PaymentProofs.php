<?php

namespace App\Services\V2;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PaymentProofs
{
    /**
     * @param  callable(): array{payment_id: ?int, data: array<string, mixed>}  $operation
     * @return array<string, mixed>
     */
    public function record(int $actor, ?UploadedFile $photo, callable $operation): array
    {
        $path = null;
        $keep = false;
        try {
            return DB::transaction(function () use ($actor, $photo, $operation, &$path, &$keep): array {
                $result = $operation();
                if ($photo === null || $result['payment_id'] === null) {
                    return $result['data'];
                }
                $payment = DB::table('v2_payments')->where('id', $result['payment_id'])->lockForUpdate()->first();
                abort_unless($payment && (int) $payment->requested_by === $actor && $payment->method === 'transfer', 403);
                $hash = hash_file('sha256', $photo->getRealPath());
                $existing = DB::table('v2_payment_proofs')->where('payment_id', $payment->id)->first();
                if ($existing) {
                    if (! hash_equals($existing->sha256, $hash)) {
                        throw ValidationException::withMessages(['transfer_proof' => 'Pembayaran ini sudah memiliki bukti berbeda. Bukti tidak boleh ditimpa.']);
                    }

                    return $result['data'];
                }
                try {
                    $path = $photo->store('transfer-proofs', 'local');
                } catch (Throwable $error) {
                    throw new RuntimeException('Foto bukti belum dapat disimpan. Pembayaran belum dicatat.', previous: $error);
                }
                if (! is_string($path) || $path === '') {
                    throw new RuntimeException('Foto bukti belum dapat disimpan. Pembayaran belum dicatat.');
                }
                DB::table('v2_payment_proofs')->insert([
                    'payment_id' => $payment->id,
                    'uploaded_by' => $actor,
                    'path' => $path,
                    'mime_type' => $photo->getMimeType(),
                    'sha256' => $hash,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $keep = true;

                return $result['data'];
            });
        } catch (Throwable $error) {
            $keep = false;
            throw $error;
        } finally {
            if ($path && ! $keep) {
                Storage::disk('local')->delete($path);
            }
        }
    }
}
