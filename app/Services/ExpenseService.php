<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExpenseService
{
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

            // Debit: Beban Operasional (6101)
            DB::table('journal_entry_lines')->insert([
                'journal_entry_id' => $journalId,
                'account_code' => '6101',
                'account_name' => 'Beban Operasional',
                'debit' => $amountStr,
                'credit' => '0.00',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Credit: Kas Toko (1101) or Kas Bank (1102)
            $creditAccount = ($paymentMethod === 'transfer') ? '1102' : '1101';
            $creditName = ($paymentMethod === 'transfer') ? 'Kas Bank' : 'Kas Toko';

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
}
