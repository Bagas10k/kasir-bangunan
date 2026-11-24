<?php

namespace App\Services;

use App\Services\V2\BankAccounts;
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
        $label = $this->resolveCashOrBankLabel($accountCode);
        if ($label === null) {
            throw new InvalidArgumentException('Kontrol saldo hanya berlaku untuk akun Kas dan Bank.');
        }

        $opening = DB::table('opening_balances')->where('singleton_key', 1)->lockForUpdate()->first();
        $bookkeepingStarted = ($opening && $opening->status === 'posted')
            || DB::table('v2_opening_batches')->where('singleton_key', 1)->exists()
            || DB::table('journal_entries')->exists();
        if (! $bookkeepingStarted) {
            throw new InvalidArgumentException('Mulai pembukuan dan posting saldo awal sebelum mengeluarkan Kas atau Bank.');
        }

        $available = $this->balance($accountCode, $excludeJournalId);
        $required = BigDecimal::of($amount)->toScale(2);
        if ($available->isLessThan($required)) {
            throw new InvalidArgumentException(sprintf(
                'Saldo %s tidak cukup. Tersedia Rp %s, dibutuhkan Rp %s.',
                $label,
                $this->formatMoney($available),
                $this->formatMoney($required)
            ));
        }
    }

    private function resolveCashOrBankLabel(string $accountCode): ?string
    {
        if ($accountCode === '1101') {
            return 'Kas Tunai';
        }
        if ($accountCode === '1102') {
            $name = DB::table('chart_of_accounts')->where('account_code', '1102')->value('account_name');

            return is_string($name) && $name !== '' ? $name.' (1102)' : 'Bank';
        }
        if (app(BankAccounts::class)->isBankAccount($accountCode, false)) {
            $name = DB::table('chart_of_accounts')->where('account_code', $accountCode)->value('account_name');

            return is_string($name) && $name !== '' ? $name.' ('.$accountCode.')' : 'Bank ('.$accountCode.')';
        }

        return null;
    }

    private function formatMoney(BigDecimal $amount): string
    {
        $scaled = (string) $amount->toScale(2);
        $negative = str_starts_with($scaled, '-');
        [$whole, $fraction] = explode('.', ltrim($scaled, '-'));
        $formatted = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole).','.$fraction;

        return $negative ? '-'.$formatted : $formatted;
    }
}
