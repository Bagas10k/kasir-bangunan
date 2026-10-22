<?php

namespace App\Http\Controllers;

use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChartOfAccountController extends Controller
{
    /**
     * Display listing of Chart of Accounts with transaction usage metrics.
     */
    public function index(Request $request)
    {
        // Auto-seed canonical accounts if table is completely empty
        if (DB::table('chart_of_accounts')->count() === 0) {
            (new ChartOfAccountsSeeder())->run();
        }

        $typeFilter = $request->query('type');
        $validTypes = ['asset', 'liability', 'equity', 'revenue', 'expense'];

        $query = DB::table('chart_of_accounts');

        if ($typeFilter && in_array($typeFilter, $validTypes, true)) {
            $query->where('account_type', $typeFilter);
        }

        // Subquery to check how many journal lines use each account
        $accounts = $query->leftJoin('journal_entry_lines', 'chart_of_accounts.account_code', '=', 'journal_entry_lines.account_code')
            ->select(
                'chart_of_accounts.id',
                'chart_of_accounts.account_code',
                'chart_of_accounts.account_name',
                'chart_of_accounts.account_type',
                'chart_of_accounts.normal_balance',
                DB::raw('COUNT(journal_entry_lines.id) as transaction_count'),
                DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit')
            )
            ->groupBy(
                'chart_of_accounts.id',
                'chart_of_accounts.account_code',
                'chart_of_accounts.account_name',
                'chart_of_accounts.account_type',
                'chart_of_accounts.normal_balance'
            )
            ->orderBy('chart_of_accounts.account_code', 'asc')
            ->get();

        // Count per type for summary badges
        $typeCounts = DB::table('chart_of_accounts')
            ->select('account_type', DB::raw('count(*) as count'))
            ->groupBy('account_type')
            ->pluck('count', 'account_type')
            ->toArray();

        return view('coa.index', compact('accounts', 'typeFilter', 'typeCounts'));
    }

    /**
     * Store a newly created account.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_code' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:chart_of_accounts,account_code'],
            'account_name' => ['required', 'string', 'max:100'],
            'account_type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
        ], [
            'account_code.unique' => 'Kode akun ini sudah terdaftar. Gunakan kode unik lain.',
            'account_code.alpha_dash' => 'Kode akun hanya boleh memuat huruf, angka, tanda strip, atau garis bawah.',
        ]);

        DB::table('chart_of_accounts')->insert([
            'account_code' => trim($validated['account_code']),
            'account_name' => trim($validated['account_name']),
            'account_type' => $validated['account_type'],
            'normal_balance' => $validated['normal_balance'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('coa.index')->with('success', 'Akun ' . $validated['account_code'] . ' (' . $validated['account_name'] . ') berhasil ditambahkan ke Bagan Akun.');
    }

    /**
     * Update an existing account.
     */
    public function update(Request $request, $id)
    {
        $account = DB::table('chart_of_accounts')->where('id', $id)->first();
        if (!$account) {
            return redirect()->route('coa.index')->with('error', 'Akun tidak ditemukan.');
        }

        $validated = $request->validate([
            'account_code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('chart_of_accounts', 'account_code')->ignore($id)],
            'account_name' => ['required', 'string', 'max:100'],
            'account_type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
        ]);

        $newCode = trim($validated['account_code']);
        $oldCode = $account->account_code;

        // Check if code changed and if it is used in journal entries
        if ($newCode !== $oldCode) {
            $isUsed = DB::table('journal_entry_lines')->where('account_code', $oldCode)->exists();
            if ($isUsed) {
                // Cascade update account_code in journal lines to preserve historical linkage
                DB::table('journal_entry_lines')->where('account_code', $oldCode)->update([
                    'account_code' => $newCode,
                    'account_name' => trim($validated['account_name']),
                ]);
            }
        }

        DB::table('chart_of_accounts')->where('id', $id)->update([
            'account_code' => $newCode,
            'account_name' => trim($validated['account_name']),
            'account_type' => $validated['account_type'],
            'normal_balance' => $validated['normal_balance'],
            'updated_at' => now(),
        ]);

        return redirect()->route('coa.index')->with('success', 'Akun ' . $newCode . ' berhasil diperbarui.');
    }

    /**
     * Remove the specified account with strict integrity check.
     */
    public function destroy($id)
    {
        $account = DB::table('chart_of_accounts')->where('id', $id)->first();
        if (!$account) {
            return redirect()->route('coa.index')->with('error', 'Akun tidak ditemukan.');
        }

        // Strict integrity guard: cannot delete if referenced in general ledger
        $transactionCount = DB::table('journal_entry_lines')->where('account_code', $account->account_code)->count();
        if ($transactionCount > 0) {
            return redirect()->route('coa.index')->with('error', 'Akun ' . $account->account_code . ' (' . $account->account_name . ') tidak dapat dihapus karena sudah memiliki ' . $transactionCount . ' catatan transaksi di jurnal umum.');
        }

        DB::table('chart_of_accounts')->where('id', $id)->delete();

        return redirect()->route('coa.index')->with('success', 'Akun ' . $account->account_code . ' berhasil dihapus dari Bagan Akun.');
    }
}
