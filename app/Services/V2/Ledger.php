<?php

namespace App\Services\V2;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Posting jurnal umum V2: seimbang, atomik, nama akun diturunkan dari chart_of_accounts.
 * Tidak mengarang akun; akun yang tidak ada menggagalkan transaksi.
 */
final class Ledger
{
    /**
     * @param  array<int, array{account_code: string, debit?: string|int|null, credit?: string|int|null}>  $lines
     * @return int journal_entries.id
     */
    public static function post(string $referenceType, int $referenceId, string $description, array $lines, ?string $date = null): int
    {
        $referenceType = trim($referenceType);
        $description = trim($description);
        if ($date !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4)))) {
            throw new InvalidArgumentException('Tanggal jurnal tidak sah.');
        }

        if ($referenceType === '' || mb_strlen($referenceType) > 50) {
            throw new InvalidArgumentException('Jenis referensi jurnal wajib diisi (maks 50 karakter).');
        }

        if ($referenceId <= 0) {
            throw new InvalidArgumentException('ID referensi jurnal tidak valid.');
        }

        if ($description === '' || mb_strlen($description) > 255) {
            throw new InvalidArgumentException('Keterangan jurnal wajib diisi (maks 255 karakter).');
        }

        if (count($lines) < 2) {
            throw new InvalidArgumentException('Jurnal membutuhkan minimal dua baris (debit dan kredit).');
        }

        $rows = [];
        $debitTotal = BigDecimal::zero();
        $creditTotal = BigDecimal::zero();

        foreach ($lines as $index => $line) {
            if (!is_array($line) || !isset($line['account_code'])) {
                throw new InvalidArgumentException('Baris jurnal #'.($index + 1).' tidak memiliki kode akun.');
            }

            $code = trim((string) $line['account_code']);

            if (preg_match('/^[0-9A-Za-z.\-]{1,20}$/', $code) !== 1) {
                throw new InvalidArgumentException('Kode akun "'.$code.'" tidak valid.');
            }

            $debit = self::side($line, 'debit', $code);
            $credit = self::side($line, 'credit', $code);

            if ($debit->isZero() && $credit->isZero()) {
                throw new InvalidArgumentException('Baris akun '.$code.' harus memiliki debit atau kredit.');
            }

            if (! $debit->isZero() && ! $credit->isZero()) {
                throw new InvalidArgumentException('Baris akun '.$code.' tidak boleh berisi debit dan kredit sekaligus.');
            }

            $debitTotal = $debitTotal->plus($debit);
            $creditTotal = $creditTotal->plus($credit);

            $rows[] = [
                'account_code' => $code,
                'debit' => (string) $debit,
                'credit' => (string) $credit,
            ];
        }

        if ($debitTotal->isZero()) {
            throw new InvalidArgumentException('Total jurnal tidak boleh nol.');
        }

        if (! $debitTotal->isEqualTo($creditTotal)) {
            throw new InvalidArgumentException(
                'Jurnal tidak seimbang: debit '.$debitTotal.' vs kredit '.$creditTotal.'.'
            );
        }

        return DB::transaction(function () use ($referenceType, $referenceId, $description, $rows, $date) {
            $codes = array_values(array_unique(array_column($rows, 'account_code')));
            sort($codes); // urutan kunci konsisten -> mengurangi deadlock

            $accounts = DB::table('chart_of_accounts')
                ->whereIn('account_code', $codes)
                ->where('is_active', true)
                ->orderBy('account_code')
                ->lockForUpdate()
                ->pluck('account_name', 'account_code');

            foreach ($codes as $code) {
                if (! isset($accounts[$code])) {
                    throw new InvalidArgumentException(
                        'Akun '.$code.' belum ada di bagan akun. Tambahkan akun tersebut lebih dahulu; jurnal dibatalkan.'
                    );
                }
            }

            $now = now();

            $journalId = DB::table('journal_entries')->insertGetId([
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'transaction_date' => $date ?? $now->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('journal_entry_lines')->insert(array_map(
                fn (array $row) => $row + [
                    'journal_entry_id' => $journalId,
                    'account_name' => $accounts[$row['account_code']],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $rows
            ));

            return (int) $journalId;
        });
    }

    private static function side(array $line, string $key, string $code): BigDecimal
    {
        $raw = $line[$key] ?? null;

        if ($raw === null || $raw === '') {
            return BigDecimal::zero()->toScale(2);
        }

        if (is_float($raw) || ! is_scalar($raw) || is_bool($raw)) {
            throw new InvalidArgumentException('Nilai '.$key.' akun '.$code.' harus string desimal.');
        }

        $value = Money::value((string) $raw);

        if ($value->isNegative()) {
            throw new InvalidArgumentException('Nilai '.$key.' akun '.$code.' tidak boleh negatif.');
        }

        return $value;
    }
}
