<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExpenseService
{
    private ?AccountBalanceService $balances = null;

    /**
     * Records an operational expense and posts double-entry balanced journal:
     * Debit: Beban Operasional (6101)
     * Credit: Kas Toko (1101) or Kas Bank (1102)
     */
    public function recordExpense(string $category, float|string $amount, ?string $description = null, string $paymentMethod = 'cash'): array
    {
        if (trim($category) === '') {
            throw new InvalidArgumentException("Expense category cannot be empty");
        }

        if (!is_numeric($amount)) {
            throw new InvalidArgumentException("Expense amount must be numeric");
        }

        $validPaymentMethods = ['cash', 'transfer'];
        if (!in_array($paymentMethod, $validPaymentMethods, true)) {
            throw new InvalidArgumentException("Invalid payment method for expense: {$paymentMethod}");
        }

        try {
            $bd = BigDecimal::of((string) $amount);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException("Expense amount must be numeric", 0, $e);
        }

        if ($bd->isLessThanOrEqualTo(0)) {
            throw new InvalidArgumentException("Expense amount must be greater than zero");
        }

        $stripped = $bd->strippedOfTrailingZeros();
        if ($stripped->getScale() > 2) {
            throw new InvalidArgumentException("Expense amount cannot have more than 2 decimal places");
        }

        $scaled = $stripped->toScale(2);
        if ($scaled->isGreaterThan('99999999999999999.99')) {
            throw new InvalidArgumentException('Expense amount is outside DECIMAL(19,2) range');
        }
        $amountStr = (string) $scaled;

        return DB::transaction(function () use ($category, $amountStr, $description, $paymentMethod) {
            $creditAccount = ($paymentMethod === 'transfer') ? '1102' : '1101';
            $creditName = ($paymentMethod === 'transfer') ? 'Kas Bank' : 'Kas Toko';
            [$debitCode, $debitName] = $this->resolveDebitAccount($category);
            if ($debitCode === $creditAccount) {
                throw new InvalidArgumentException('Akun beban tidak boleh sama dengan akun pembayaran.');
            }
            $this->balanceService()->assertSufficient($creditAccount, $amountStr);

            $expenseId = DB::table('expenses')->insertGetId([
                'expense_number' => null,
                'expense_date' => date('Y-m-d'),
                'category' => $category,
                'amount' => $amountStr,
                'payment_method' => $paymentMethod,
                'description' => $description,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $expenseNumber = sprintf('EXP-%s-%06d', date('Ymd'), $expenseId);
            DB::table('expenses')->where('id', $expenseId)->update(['expense_number' => $expenseNumber]);

            // Balanced journal
            $journalId = DB::table('journal_entries')->insertGetId([
                'reference_type' => 'expense',
                'reference_id' => $expenseId,
                'description' => "Pengeluaran {$expenseNumber}: {$category}",
                'transaction_date' => date('Y-m-d'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            DB::table('expenses')->where('id', $expenseId)->update(['journal_entry_id' => $journalId]);

            // Debit: Selected Expense Account
            DB::table('journal_entry_lines')->insert([
                'journal_entry_id' => $journalId,
                'account_code' => $debitCode,
                'account_name' => $debitName,
                'debit' => $amountStr,
                'credit' => '0.00',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Credit: Kas Toko (1101) or Kas Bank (1102)
            DB::table('journal_entry_lines')->insert([
                'journal_entry_id' => $journalId,
                'account_code' => $creditAccount,
                'account_name' => $creditName,
                'debit' => '0.00',
                'credit' => $amountStr,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'expense_id' => $expenseId,
                'expense_number' => $expenseNumber,
                'amount' => $amountStr,
                'journal_entry_id' => $journalId,
            ];
        });
    }

    public function updateExpense(int $id, string $category, float|string $amount, ?string $description = null, string $paymentMethod = 'cash'): array
    {
        if (trim($category) === '') {
            throw new InvalidArgumentException('Expense category cannot be empty');
        }
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException('Expense amount must be numeric');
        }
        if (!in_array($paymentMethod, ['cash', 'transfer'], true)) {
            throw new InvalidArgumentException("Invalid payment method for expense: {$paymentMethod}");
        }

        $bd = BigDecimal::of((string) $amount);
        if ($bd->isLessThanOrEqualTo(0)) {
            throw new InvalidArgumentException('Expense amount must be greater than zero');
        }
        $stripped = $bd->strippedOfTrailingZeros();
        if ($stripped->getScale() > 2) {
            throw new InvalidArgumentException('Expense amount cannot have more than 2 decimal places');
        }
        $amountStr = (string) $stripped->toScale(2);

        return DB::transaction(function () use ($id, $category, $amountStr, $description, $paymentMethod) {
            $expense = DB::table('expenses')->where('id', $id)->lockForUpdate()->first();
            if (!$expense) {
                throw new InvalidArgumentException('Expense not found');
            }
            if ($expense->journal_entry_id !== null) {
                throw new InvalidArgumentException('Pengeluaran sudah diposting dan terkunci; perubahan langsung ditolak untuk menjaga jejak audit.');
            }

            $journalId = $expense->journal_entry_id;
            $creditCode = ($paymentMethod === 'transfer') ? '1102' : '1101';
            $creditName = ($paymentMethod === 'transfer') ? 'Kas Bank' : 'Kas Toko';
            [$debitCode, $debitName] = $this->resolveDebitAccount($category);
            if ($debitCode === $creditCode) {
                throw new InvalidArgumentException('Akun beban tidak boleh sama dengan akun pembayaran.');
            }
            $this->balanceService()->assertSufficient($creditCode, $amountStr, $journalId ? (int) $journalId : null);

            DB::table('expenses')->where('id', $id)->update([
                'category' => $category,
                'amount' => $amountStr,
                'payment_method' => $paymentMethod,
                'description' => $description,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return ['expense_id' => $id, 'amount' => $amountStr];
        });
    }

    public function deleteExpense(int $id): void
    {
        DB::transaction(function () use ($id) {
            $expense = DB::table('expenses')->where('id', $id)->lockForUpdate()->first();
            if (!$expense) {
                throw new InvalidArgumentException('Expense not found');
            }
            if ($expense->journal_entry_id !== null) {
                throw new InvalidArgumentException('Pengeluaran sudah diposting dan terkunci; perubahan langsung ditolak untuk menjaga jejak audit.');
            }

            DB::table('expenses')->where('id', $id)->delete();
        });
    }

    private function resolveDebitAccount(string $category): array
    {
        $coa = DB::table('chart_of_accounts')
            ->where('is_active', true)
            ->where(function ($query) use ($category) {
                $query->where('account_code', $category)
                    ->orWhere('account_name', $category);
            })
            ->first();

        if ($coa) {
            if ($coa->account_type !== 'expense' || !in_array(substr($coa->account_code, 0, 1), ['5', '6', '8'], true)) {
                throw new InvalidArgumentException('Kategori pengeluaran harus akun beban.');
            }
            return [$coa->account_code, $coa->account_name];
        }

        if (preg_match('/^\d+$/', $category)) {
            throw new InvalidArgumentException('Kode akun beban tidak ditemukan.');
        }

        $categoryMap = [
            'Operasional Toko' => ['6101', 'Beban Operasional Toko'],
            'Listrik & Air' => ['6102', 'Beban Listrik & Air'],
            'Pemeliharaan & Perbaikan' => ['6103', 'Beban Pemeliharaan & Perbaikan'],
            'Perlengkapan & ATK' => ['6104', 'Beban Perlengkapan & ATK'],
            'Gaji & Upah Tenaga' => ['6105', 'Beban Gaji & Upah Tenaga'],
            'Lain-lain' => ['6199', 'Beban Lain-lain'],
        ];

        if (isset($categoryMap[$category])) {
            return $categoryMap[$category];
        }

        return ['6101', 'Beban Operasional'];
    }

    private function balanceService(): AccountBalanceService
    {
        return $this->balances ??= new AccountBalanceService();
    }
}
