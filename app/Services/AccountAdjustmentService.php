<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AccountAdjustmentService
{
    public function __construct(private AccountBalanceService $balances) {}

    public function post(array $data, int $userId): int
    {
        return DB::transaction(function () use ($data, $userId): int {
            $opening = DB::table('opening_balances')->where('singleton_key', 1)->lockForUpdate()->first();
            $accounts = DB::table('chart_of_accounts')
                ->whereIn('account_code', [$data['account_code'], $data['counter_account_code']])
                ->orderBy('account_code')->lockForUpdate()->get()->keyBy('account_code');
            if ($accounts->count() !== 2 || $accounts->contains(fn ($account) => ! $account->is_active)) {
                throw new InvalidArgumentException('Pilih dua akun aktif yang berbeda.');
            }
            $account = $accounts[$data['account_code']];
            $target = BigDecimal::of($data['target_balance'])->toScale(2);
            $signedTarget = $account->normal_balance === 'credit' ? $target->negated() : $target;
            $difference = $signedTarget->minus($this->balances->balance($account->account_code));
            if ($difference->isZero()) {
                throw new InvalidArgumentException('Saldo sudah sesuai; tidak ada penyesuaian yang perlu diposting.');
            }
            if ($difference->abs()->isGreaterThan('99999999999999999.99')) {
                throw new InvalidArgumentException('Selisih penyesuaian melebihi batas penyimpanan.');
            }
            $counter = $accounts[$data['counter_account_code']];
            foreach ([$account->account_code => $signedTarget, $counter->account_code => $this->balances->balance($counter->account_code)->minus($difference)] as $code => $result) {
                if (in_array((string) $code, ['1101', '1102'], true) && $result->isNegative()) {
                    throw new InvalidArgumentException('Penyesuaian tidak boleh membuat saldo Kas atau Bank negatif.');
                }
            }
            $id = DB::table('journal_entries')->insertGetId([
                'reference_type' => 'adjustment', 'reference_id' => null,
                'description' => 'Penyesuaian saldo oleh pengguna #'.$userId.': '.$data['description'],
                'transaction_date' => $data['transaction_date'], 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ([$account, $counter] as $index => $lineAccount) {
                $debit = ($index === 0) === $difference->isPositive();
                DB::table('journal_entry_lines')->insert([
                    'journal_entry_id' => $id, 'account_code' => $lineAccount->account_code,
                    'account_name' => $lineAccount->account_name,
                    'debit' => $debit ? (string) $difference->abs() : '0.00',
                    'credit' => $debit ? '0.00' : (string) $difference->abs(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            if (! $opening || $opening->status === 'draft') {
                $values = [
                    'transaction_date' => $data['transaction_date'],
                    'cash_amount' => (string) $this->balances->balance('1101'),
                    'bank_amount' => (string) $this->balances->balance('1102'),
                    'status' => 'posted', 'journal_entry_id' => $id, 'posted_at' => now(), 'updated_at' => now(),
                ];
                if ($opening) {
                    DB::table('opening_balances')->where('id', $opening->id)->update($values);
                } else {
                    DB::table('opening_balances')->insert($values + ['singleton_key' => 1, 'created_at' => now()]);
                }
            }

            return $id;
        });
    }
}
