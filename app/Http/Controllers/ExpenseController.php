<?php

namespace App\Http\Controllers;

use App\Services\AccountBalanceService;
use App\Services\ExpenseService;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request, AccountBalanceService $balances): View
    {
        $filters = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => ['nullable', 'date_format:Y-m-d', ...($request->filled('start_date') ? ['after_or_equal:start_date'] : [])],
            'category' => 'nullable|string|max:100',
            'payment_method' => 'nullable|in:cash,transfer',
        ]);
        $expenseQuery = DB::table('expenses');
        if (! empty($filters['start_date'])) {
            $expenseQuery->where('expense_date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $expenseQuery->where('expense_date', '<=', $filters['end_date']);
        }
        if (! empty($filters['category'])) {
            $expenseQuery->where('category', $filters['category']);
        }
        if (! empty($filters['payment_method'])) {
            $expenseQuery->where('payment_method', $filters['payment_method']);
        }
        $expenses = $expenseQuery->latest()->limit(10)->get();
        $expenseCategories = DB::table('expenses')->distinct()->orderBy('category')->pluck('category');

        $expenseAccounts = DB::table('chart_of_accounts')
            ->where('account_type', 'expense')
            ->where('is_active', true)
            ->whereNotIn('account_code', ['5101', '5201', '6201'])
            ->orderBy('account_code')
            ->get();

        $cashBalance = (string) $balances->balance('1101');
        $bankBalance = (string) $balances->balance('1102');

        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth()->toDateString();
        $endOfMonth = $now->copy()->endOfMonth()->toDateString();

        $monthlyExpenses = DB::table('expenses')
            ->whereBetween('expense_date', [$startOfMonth, $endOfMonth]);
        $expenseCount = (clone $monthlyExpenses)->count();

        $totalExpense = BigDecimal::zero();
        $cashExpense = BigDecimal::zero();
        $transferExpense = BigDecimal::zero();

        foreach ($monthlyExpenses->orderBy('id')->cursor(['amount', 'payment_method']) as $item) {
            $amount = BigDecimal::of((string) ($item->amount ?? '0'));
            $totalExpense = $totalExpense->plus($amount);
            if ($item->payment_method === 'transfer') {
                $transferExpense = $transferExpense->plus($amount);
            } else {
                $cashExpense = $cashExpense->plus($amount);
            }
        }

        $expenseSummary = [
            'count' => $expenseCount,
            'total' => (string) $totalExpense,
            'cash' => (string) $cashExpense,
            'transfer' => (string) $transferExpense,
            'period_label' => $now->translatedFormat('F Y'),
        ];

        return view('expenses.index', compact('expenses', 'expenseAccounts', 'expenseCategories', 'cashBalance', 'bankBalance', 'expenseSummary'));
    }

    public function store(Request $request, ExpenseService $service)
    {
        $validated = $request->validate([
            'category' => 'required|string|max:100',
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'description' => 'nullable|string|max:255',
            'payment_method' => 'nullable|in:cash,transfer',
        ]);

        try {
            $result = $service->recordExpense(
                $validated['category'],
                (string) $validated['amount'],
                $validated['description'] ?? null,
                $validated['payment_method'] ?? 'cash'
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return redirect()->back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return redirect()->route('expenses.index')->with('success', 'Pengeluaran berhasil dicatat dan jurnalnya telah dikunci.');
    }

    public function update(Request $request, ExpenseService $service, $id)
    {
        $validated = $request->validate([
            'category' => 'required|string|max:100',
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'description' => 'nullable|string|max:255',
            'payment_method' => 'nullable|in:cash,transfer',
        ]);

        try {
            $result = $service->updateExpense(
                (int) $id,
                $validated['category'],
                (string) $validated['amount'],
                $validated['description'] ?? null,
                $validated['payment_method'] ?? 'cash'
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return redirect()->back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return redirect()->route('expenses.index')->with('success', 'Draf pengeluaran berhasil diperbarui.');
    }

    public function destroy(Request $request, ExpenseService $service, $id)
    {
        $id = (int) $id;

        try {
            $service->deleteExpense($id);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('expenses.index')->with('success', 'Draf pengeluaran berhasil dihapus.');
    }
}
