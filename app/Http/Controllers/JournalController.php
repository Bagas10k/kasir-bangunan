<?php

namespace App\Http\Controllers;

use App\Services\AccountBalanceService;
use App\Services\OpeningBalanceService;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class JournalController extends Controller
{
    public function index(Request $request, OpeningBalanceService $openingService, AccountBalanceService $balances): View
    {
        $filters = $this->validatedFilters($request);
        $entries = $this->entries($filters);
        $opening = $openingService->current();

        $cashOpening = (string) ($opening->cash_amount ?? '0.00');
        $bankOpening = (string) ($opening->bank_amount ?? '0.00');
        $cashBalance = (string) $balances->balance('1101');
        $bankBalance = (string) $balances->balance('1102');
        $sources = DB::table('journal_entries')->distinct()->orderBy('reference_type')->pluck('reference_type');

        return view('journals.index', compact(
            'entries', 'opening', 'cashOpening', 'bankOpening', 'cashBalance', 'bankBalance', 'sources', 'filters'
        ));
    }

    public function feed(Request $request): JsonResponse
    {
        $validated = $request->validate(['after_id' => 'nullable|integer|min:0']);
        $afterId = (int) ($validated['after_id'] ?? 0);
        $entries = $this->entries(['after_id' => $afterId], 25, $afterId > 0);

        return response()->json([
            'entries' => $entries,
            'latest_id' => (int) ($entries->max('id') ?? ($validated['after_id'] ?? 0)),
        ], 200, ['Cache-Control' => 'no-store, private']);
    }

    public function saveOpening(Request $request, OpeningBalanceService $service): RedirectResponse
    {
        $validated = $request->validate([
            'transaction_date' => 'required|date_format:Y-m-d',
            'cash_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'bank_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ]);

        try {
            $service->saveDraft(
                $validated['transaction_date'],
                (string) $validated['cash_amount'],
                (string) $validated['bank_amount']
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->withInput()->withErrors(['opening_balance' => $e->getMessage()]);
        }

        return redirect()->route('journals.index')->with('success', 'Draf saldo awal disimpan. Periksa kembali sebelum memulai pembukuan.');
    }

    public function postOpening(OpeningBalanceService $service): RedirectResponse
    {
        try {
            $service->post();
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->withErrors(['opening_balance' => $e->getMessage()]);
        }

        return redirect()->route('journals.index')->with('success', 'Pembukuan dimulai. Saldo awal kini terkunci permanen.');
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'source' => 'nullable|string|max:50',
            'account' => 'nullable|string|max:20',
        ]);
    }

    private function entries(array $filters, int $limit = 25, bool $ascending = false): \Illuminate\Support\Collection
    {
        $query = DB::table('journal_entries')
            ->when(!empty($filters['start_date']), fn ($q) => $q->whereDate('transaction_date', '>=', $filters['start_date']))
            ->when(!empty($filters['end_date']), fn ($q) => $q->whereDate('transaction_date', '<=', $filters['end_date']))
            ->when(!empty($filters['source']), fn ($q) => $q->where('reference_type', $filters['source']))
            ->when(!empty($filters['account']), fn ($q) => $q->whereExists(function ($lines) use ($filters) {
                $lines->selectRaw('1')
                    ->from('journal_entry_lines')
                    ->whereColumn('journal_entry_lines.journal_entry_id', 'journal_entries.id')
                    ->where('journal_entry_lines.account_code', $filters['account']);
            }))
            ->when(!empty($filters['after_id']), fn ($q) => $q->where('id', '>', (int) $filters['after_id']));

        $headers = $query
            ->orderBy('id', $ascending ? 'asc' : 'desc')
            ->limit(min(max($limit, 1), 50))
            ->get();

        if ($headers->isEmpty()) {
            return collect();
        }

        $lines = DB::table('journal_entry_lines')
            ->whereIn('journal_entry_id', $headers->pluck('id'))
            ->orderBy('id')
            ->get()
            ->groupBy('journal_entry_id');

        return $headers->map(function ($entry) use ($lines) {
            $entryLines = ($lines[$entry->id] ?? collect())->map(fn ($line) => [
                'account_code' => $line->account_code,
                'account_name' => $line->account_name,
                'debit' => (string) BigDecimal::of((string) $line->debit)->toScale(2),
                'credit' => (string) BigDecimal::of((string) $line->credit)->toScale(2),
            ])->values();
            $debitValue = BigDecimal::zero();
            $creditValue = BigDecimal::zero();
            foreach ($entryLines as $line) {
                $debitValue = $debitValue->plus($line['debit']);
                $creditValue = $creditValue->plus($line['credit']);
            }
            $debit = (string) $debitValue->toScale(2);
            $credit = (string) $creditValue->toScale(2);

            return [
                'id' => (int) $entry->id,
                'transaction_date' => $entry->transaction_date,
                'created_at' => $entry->created_at,
                'reference_type' => $entry->reference_type,
                'reference_id' => $entry->reference_id,
                'description' => $entry->description,
                'lines' => $entryLines,
                'total_debit' => $debit,
                'total_credit' => $credit,
                'is_balanced' => $debit === $credit,
            ];
        });
    }
}
