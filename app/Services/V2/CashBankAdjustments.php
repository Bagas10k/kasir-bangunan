<?php

namespace App\Services\V2;

use App\Services\AccountBalanceService;
use App\Support\SafeLog;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Penyesuaian kas/bank operasional V2.
 *
 * Saldo awal tetap satu kali; koreksi sesudah pembukuan berjalan dicatat sebagai
 * jurnal penyesuaian baru yang seimbang. Saldo awal yang sudah diposting tidak
 * diubah dan baris jurnal lama tidak pernah ditimpa.
 */
class CashBankAdjustments
{
    public const ALLOWED_ACCOUNTS = ['1101', '1102'];

    public function __construct(
        private AccountBalanceService $balances,
        private Operations $operations,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function post(int $actor, array $data): array
    {
        $this->operations->requireRole($actor, ['admin']);
        $this->confirmPassword($actor, (string) ($data['password'] ?? ''));

        $code = (string) $data['account_code'];
        $counterCode = (string) $data['counter_account_code'];
        if (! in_array($code, self::ALLOWED_ACCOUNTS, true)) {
            throw ValidationException::withMessages([
                'account_code' => 'Tahap ini hanya mengizinkan penyesuaian saldo Kas (1101) atau Bank (1102).',
            ]);
        }

        $result = DB::transaction(function () use ($actor, $data, $code, $counterCode): array {
            $this->assertBookkeepingStarted();

            $accounts = DB::table('chart_of_accounts')
                ->whereIn('account_code', [$code, $counterCode])
                ->orderBy('account_code')
                ->lockForUpdate()
                ->get()
                ->keyBy('account_code');

            if (! $accounts->has($code) || ! $accounts[$code]->is_active) {
                throw ValidationException::withMessages(['account_code' => 'Akun kas/bank yang disesuaikan tidak ditemukan atau tidak aktif.']);
            }
            if (! $accounts->has($counterCode) || ! $accounts[$counterCode]->is_active) {
                throw ValidationException::withMessages(['counter_account_code' => 'Akun lawan tidak ditemukan atau tidak aktif.']);
            }
            if ($accounts[$code]->normal_balance !== 'debit') {
                throw ValidationException::withMessages(['account_code' => 'Akun kas/bank harus bersaldo normal debet.']);
            }

            $target = BigDecimal::of((string) $data['target_balance'])->toScale(2);
            $current = $this->balances->balance($code);
            $difference = $target->minus($current)->toScale(2);
            if ($difference->isZero()) {
                throw ValidationException::withMessages([
                    'target_balance' => 'Saldo sudah sesuai; tidak ada penyesuaian yang perlu diposting.',
                ]);
            }

            $counterCurrent = $this->balances->balance($counterCode);
            $counterAfter = $counterCurrent->minus($difference);
            foreach ([[$code, $target], [$counterCode, $counterAfter]] as [$checkedCode, $result]) {
                if (in_array($checkedCode, self::ALLOWED_ACCOUNTS, true) && $result->isNegative()) {
                    throw ValidationException::withMessages([
                        'adjustment' => 'Penyesuaian tidak boleh membuat saldo Kas atau Bank negatif.',
                    ]);
                }
            }

            $reason = trim((string) $data['description']);
            $journalId = DB::table('journal_entries')->insertGetId([
                'reference_type' => 'adjustment',
                'reference_id' => null,
                'description' => mb_substr('Penyesuaian saldo '.$code.' oleh pengguna #'.$actor.': '.$reason, 0, 255),
                'transaction_date' => $data['transaction_date'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $amount = (string) $difference->abs()->toScale(2);
            $debit = $difference->isPositive() ? $accounts[$code] : $accounts[$counterCode];
            $credit = $difference->isPositive() ? $accounts[$counterCode] : $accounts[$code];
            foreach ([['account' => $debit, 'debit' => $amount, 'credit' => '0.00'], ['account' => $credit, 'debit' => '0.00', 'credit' => $amount]] as $line) {
                DB::table('journal_entry_lines')->insert([
                    'journal_entry_id' => $journalId,
                    'account_code' => $line['account']->account_code,
                    'account_name' => $line['account']->account_name,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return [
                'status' => 'posted',
                'journal_entry_id' => $journalId,
                'account_code' => $code,
                'counter_account_code' => $counterCode,
                'previous_balance' => (string) $current,
                'target_balance' => (string) $target,
                'difference' => (string) $difference,
                'message' => 'Penyesuaian saldo tersimpan; jurnal seimbang dibuat tanpa mengubah saldo awal.',
            ];
        });

        // Log sesudah commit dan tidak fatal: kegagalan menulis log tidak boleh membatalkan jurnal.
        SafeLog::info('cash_bank_adjustment.posted', [
            'actor_id' => $actor,
            'journal_entry_id' => $result['journal_entry_id'],
            'account_code' => $code,
            'counter_account_code' => $counterCode,
            'previous_balance' => $result['previous_balance'],
            'target_balance' => $result['target_balance'],
            'difference' => $result['difference'],
        ]);

        return $result;
    }

    private function confirmPassword(int $actor, string $password): void
    {
        $hash = DB::table('users')->where('id', $actor)->value('password');
        if ($password === '' || ! is_string($hash) || ! Hash::check($password, $hash)) {
            throw ValidationException::withMessages(['password' => 'Password konfirmasi tidak sesuai.']);
        }
    }

    private function assertBookkeepingStarted(): void
    {
        $started = DB::table('journal_entries')->exists()
            || DB::table('v2_opening_batches')->exists()
            || DB::table('opening_balances')->where('status', 'posted')->exists();

        if (! $started) {
            throw ValidationException::withMessages([
                'adjustment' => 'Mulai pembukuan melalui saldo awal terlebih dahulu. Penyesuaian hanya untuk koreksi setelah saldo awal diposting.',
            ]);
        }
    }
}
