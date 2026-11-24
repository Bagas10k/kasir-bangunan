<?php

namespace App\Services\V2;

use App\Services\AccountBalanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Pengeluaran operasional V2: biaya di luar penjualan, piutang, dan laci kasir.
 * Debit akun beban, kredit Kas Tunai (1101) atau Bank (1102); jurnal seimbang dan terkunci.
 */
final class OperatingExpenses
{
    private const BLOCKED_ACCOUNTS = ['5101', '5201', '6201'];

    public function __construct(
        private AccountBalanceService $balances,
        private BankAccounts $bankAccounts,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function post(int $actor, array $data): array
    {
        app(Operations::class)->requireRole($actor, ['admin']);

        $date = trim((string) ($data['date'] ?? now()->toDateString()));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            throw ValidationException::withMessages(['date' => 'Tanggal pengeluaran tidak valid.']);
        }
        if ($date > now()->toDateString()) {
            throw ValidationException::withMessages(['date' => 'Tanggal pengeluaran tidak boleh melewati hari ini.']);
        }

        $code = trim((string) ($data['account_code'] ?? ''));
        $source = (string) ($data['source'] ?? 'cash');
        if (! in_array($source, ['cash', 'bank'], true)) {
            throw ValidationException::withMessages(['source' => 'Sumber dana harus Kas Tunai atau Bank.']);
        }
        $sourceCode = $source === 'bank'
            ? $this->bankAccounts->resolve(isset($data['bank_account_code']) ? (string) $data['bank_account_code'] : null)
            : '1101';

        try {
            $amount = Money::value((string) ($data['amount'] ?? '0'));
        } catch (\Throwable) {
            throw ValidationException::withMessages(['amount' => 'Nominal pengeluaran tidak valid.']);
        }
        if ($amount->isZero() || $amount->isNegative()) {
            throw ValidationException::withMessages(['amount' => 'Nominal pengeluaran harus lebih dari nol.']);
        }

        $description = trim((string) ($data['description'] ?? ''));
        if (mb_strlen($description) > 255) {
            throw ValidationException::withMessages(['description' => 'Keterangan maksimal 255 karakter.']);
        }

        return DB::transaction(function () use ($date, $code, $source, $sourceCode, $amount, $description): array {
            $accounts = DB::table('chart_of_accounts')
                ->whereIn('account_code', [$code, $sourceCode])
                ->orderBy('account_code')
                ->lockForUpdate()
                ->get()
                ->keyBy('account_code');

            $expense = $accounts->get($code);
            $paidFrom = $accounts->get($sourceCode);

            if (! $expense || ! $expense->is_active) {
                throw ValidationException::withMessages(['account_code' => 'Akun beban tidak ditemukan atau tidak aktif.']);
            }
            if ($expense->account_type !== 'expense' || in_array($code, self::BLOCKED_ACCOUNTS, true)) {
                throw ValidationException::withMessages(['account_code' => 'Pilih akun beban operasional; HPP/persediaan tidak boleh dipakai.']);
            }
            if (! $paidFrom || ! $paidFrom->is_active) {
                throw ValidationException::withMessages(['source' => 'Akun Kas/Bank sumber dana tidak ditemukan atau tidak aktif.']);
            }
            if ($code === $sourceCode) {
                throw ValidationException::withMessages(['account_code' => 'Akun beban tidak boleh sama dengan akun pembayaran.']);
            }

            try {
                $this->balances->assertSufficient($sourceCode, (string) $amount);
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
            }

            $now = now();
            $expenseRow = [
                'expense_number' => null,
                'expense_date' => $date,
                'category' => $code,
                'amount' => (string) $amount,
                'payment_method' => $source === 'bank' ? 'transfer' : 'cash',
                'description' => $description !== '' ? $description : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (Schema::hasColumn('expenses', 'bank_account_code')) {
                $expenseRow['bank_account_code'] = $source === 'bank' ? $sourceCode : null;
            }
            $expenseId = DB::table('expenses')->insertGetId($expenseRow);

            $number = sprintf('EXP-%s-%06d', str_replace('-', '', $date), $expenseId);
            DB::table('expenses')->where('id', $expenseId)->update(['expense_number' => $number, 'updated_at' => $now]);

            $journalId = Ledger::post(
                'operating_expense',
                $expenseId,
                mb_substr('Pengeluaran '.$number.': '.$expense->account_name, 0, 255),
                [
                    ['account_code' => $code, 'debit' => (string) $amount],
                    ['account_code' => $sourceCode, 'credit' => (string) $amount],
                ],
                $date
            );

            DB::table('expenses')->where('id', $expenseId)->update(['journal_entry_id' => $journalId, 'updated_at' => $now]);

            return [
                'expense_id' => $expenseId,
                'expense_number' => $number,
                'amount' => (string) $amount,
                'journal_entry_id' => $journalId,
            ];
        });
    }
}
