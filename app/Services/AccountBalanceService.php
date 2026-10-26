<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AccountBalanceService
{
    public function balance(string $accountCode, ?int $excludeJournalId = null): BigDecimal
    {
        $query = DB::table('journal_entry_lines')->where('account_code', $accountCode);
        if ($excludeJournalId !== null) {
            $query->where('journal_entry_id', '<>', $excludeJournalId);
        }

        $debit = BigDecimal::zero();
        $credit = BigDecimal::zero();
        foreach ($query->orderBy('id')->cursor(['debit', 'credit']) as $line) {
            $debit = $debit->plus((string) $line->debit);
            $credit = $credit->plus((string) $line->credit);
        }

        return $debit->minus($credit)->toScale(2);
    }

    public function assertSufficient(string $accountCode, string $amount, ?int $excludeJournalId = null): void
    {
        $labels = ['1101' => 'Kas Tunai', '1102' => 'Bank'];
        if (!isset($labels[$accountCode])) {
            throw new InvalidArgumentException('Kontrol saldo hanya berlaku untuk akun Kas dan Bank.');
        }

        $opening = DB::table('opening_balances')->where('singleton_key', 1)->lockForUpdate()->first();
        if (!$opening || $opening->status !== 'posted') {
            throw new InvalidArgumentException('Mulai pembukuan dan posting saldo awal sebelum mengeluarkan Kas atau Bank.');
        }

        $available = $this->balance($accountCode, $excludeJournalId);
        $required = BigDecimal::of($amount)->toScale(2);
        if ($available->isLessThan($required)) {
            throw new InvalidArgumentException(sprintf(
                'Saldo %s tidak cukup. Tersedia Rp %s, dibutuhkan Rp %s.',
                $labels[$accountCode],
                $this->formatMoney($available),
                $this->formatMoney($required)
            ));
        }
    }

    private function formatMoney(BigDecimal $amount): string
    {
        $scaled = (string) $amount->toScale(2);
        $negative = str_starts_with($scaled, '-');
        [$whole, $fraction] = explode('.', ltrim($scaled, '-'));
        $formatted = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole) . ',' . $fraction;

        return $negative ? '-' . $formatted : $formatted;
    }
}
