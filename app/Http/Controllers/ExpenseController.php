<?php

namespace App\Http\Controllers;

use App\Services\ExpenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function index()
    {
        $expenses = DB::table('expenses')->latest()->limit(10)->get();

        if (DB::table('chart_of_accounts')->count() === 0) {
            (new \Database\Seeders\ChartOfAccountsSeeder())->run();
        }

        $expenseAccounts = DB::table('chart_of_accounts')
            ->where('account_type', 'expense')
            ->whereNotIn('account_code', ['5101', '5201', '6201'])
            ->orderBy('account_code')
            ->get();

        return view('expenses.index', compact('expenses', 'expenseAccounts'));
    }

    public function store(Request $request, ExpenseService $service)
    {
        $validated = $request->validate([
            'category' => 'required|string|max:100',
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'description' => 'nullable|string|max:255',
            'payment_method' => 'nullable|in:cash,transfer',
        ]);

        $result = $service->recordExpense(
            $validated['category'],
            (string) $validated['amount'],
            $validated['description'] ?? null,
            $validated['payment_method'] ?? 'cash'
        );

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return redirect()->route('expenses.index')->with('success', 'Expense recorded');
    }
}
