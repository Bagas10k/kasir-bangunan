<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OpeningBalanceService
{
    private const MAX_MONEY = '99999999999999999.99';

    public function current(): ?object
    {
        return DB::table('opening_balances')->where('singleton_key', 1)->first();
    }

    public function saveDraft(string $transactionDate, string $cashAmount, string $bankAmount): object
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $transactionDate);
        if (!$date || $date->format('Y-m-d') !== $transactionDate) {
            throw new InvalidArgumentException('Tanggal saldo awal tidak valid.');
        }

        $cash = $this->money($cashAmount, 'Kas');
        $bank = $this->money($bankAmount, 'Bank');
        if ($cash->plus($bank)->isGreaterThan(self::MAX_MONEY)) {
            throw new InvalidArgumentException('Total saldo awal melebihi batas penyimpanan.');
        }

        return DB::transaction(function () use ($transactionDate, $cash, $bank) {
            $current = DB::table('opening_balances')->where('singleton_key', 1)->lockForUpdate()->first();
            if ($current && $current->status !== 'draft') {
                throw new InvalidArgumentException('Saldo awal sudah diposting dan tidak dapat diubah. Gunakan jurnal penyesuaian.');
            }

            $values = [
                'transaction_date' => $transactionDate,
                'cash_amount' => (string) $cash,
                'bank_amount' => (string) $bank,
                'updated_at' => now(),
            ];

            if ($current) {
                DB::table('opening_balances')->where('id', $current->id)->update($values);
            } else {
                $id = DB::table('opening_balances')->insertGetId($values + [
                    'singleton_key' => 1,
                    'status' => 'draft',
                    'created_at' => now(),
                ]);
                $current = (object) ['id' => $id];
            }

            return DB::table('opening_balances')->where('id', $current->id)->first();
        });
    }

    public function post(): object
    {
        return DB::transaction(function () {
            $opening = DB::table('opening_balances')->where('singleton_key', 1)->lockForUpdate()->first();
            if (!$opening) {
                throw new InvalidArgumentException('Simpan draf saldo awal terlebih dahulu.');
            }
            if ($opening->status !== 'draft') {
                throw new InvalidArgumentException('Saldo awal sudah diposting.');
            }

            $cash = BigDecimal::of((string) $opening->cash_amount)->toScale(2);
            $bank = BigDecimal::of((string) $opening->bank_amount)->toScale(2);
            $total = $cash->plus($bank)->toScale(2);
            if ($total->isZero()) {
                throw new InvalidArgumentException('Kas dan Bank tidak boleh sama-sama nol saat mulai pembukuan.');
            }

            $journalId = DB::table('journal_entries')->insertGetId([
                'reference_type' => 'opening_balance',
                'reference_id' => $opening->id,
                'description' => 'Saldo Pembukaan Kas dan Bank',
                'transaction_date' => $opening->transaction_date,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $lines = [];
            if ($cash->isGreaterThan(0)) {
                $lines[] = $this->line($journalId, '1101', 'Kas Toko (Tunai)', (string) $cash, '0.00');
            }
            if ($bank->isGreaterThan(0)) {
                $lines[] = $this->line($journalId, '1102', 'Rekening Bank', (string) $bank, '0.00');
            }
            $lines[] = $this->line($journalId, '3101', 'Modal Pemilik / Modal Awal', '0.00', (string) $total);
            DB::table('journal_entry_lines')->insert($lines);

            DB::table('opening_balances')->where('id', $opening->id)->update([
                'status' => 'posted',
                'journal_entry_id' => $journalId,
                'posted_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('opening_balances')->where('id', $opening->id)->first();
        });
    }

    private function money(string $value, string $label): BigDecimal
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException("Saldo {$label} harus berupa angka.");
        }

        try {
            $amount = BigDecimal::of($value);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException("Saldo {$label} harus berupa angka.", 0, $e);
        }

        if ($amount->isNegative() || $amount->strippedOfTrailingZeros()->getScale() > 2) {
            throw new InvalidArgumentException("Saldo {$label} harus nol atau positif dengan maksimal 2 desimal.");
        }
        if ($amount->isGreaterThan(self::MAX_MONEY)) {
            throw new InvalidArgumentException("Saldo {$label} melebihi batas penyimpanan.");
        }

        return $amount->toScale(2);
    }

    private function line(int $journalId, string $code, string $name, string $debit, string $credit): array
    {
        return [
            'journal_entry_id' => $journalId,
            'account_code' => $code,
            'account_name' => $name,
            'debit' => $debit,
            'credit' => $credit,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
