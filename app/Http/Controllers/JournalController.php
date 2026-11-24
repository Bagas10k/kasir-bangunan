<?php

namespace App\Http\Controllers;

use App\Services\AccountAdjustmentService;
use App\Services\AccountBalanceService;
use App\Services\OpeningBalanceService;
use App\Services\V2\BankAccounts;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class JournalController extends Controller
{
    public function index(Request $request, OpeningBalanceService $openingService, AccountBalanceService $balances, BankAccounts $bankService): View
    {
        $filters = $this->validatedFilters($request);
        $cursorToken = $request->validate(['cursor' => 'nullable|string|max:160'])['cursor'] ?? null;
        $cursor = Cursor::fromEncoded($cursorToken);
        $position = $cursor?->toArray() ?? [];
        abort_if($cursorToken !== null && (! is_int($position['id'] ?? null) || ! is_bool($position['_pointsToNextItems'] ?? null)), 422, 'Tautan halaman jurnal tidak valid.');
        $page = $this->journalQuery($filters)->orderByDesc('id')->cursorPaginate(25, ['*'], 'cursor', $cursor)->appends($filters);
        $entries = $this->entryDetails($page->getCollection());
        $olderJournalUrl = $page->nextPageUrl();
        $newerJournalUrl = $page->previousPageUrl();
        $watchJournal = $page->cursor() === null && array_filter($filters) === [];
        $opening = $openingService->current();
        if (config('v2.enabled')) {
            $v2Opening = DB::table('v2_opening_batches')->where('singleton_key', 1)->first();
            if ($v2Opening) {
                $opening = (object) [
                    'id' => null,
                    'status' => 'posted',
                    'transaction_date' => $v2Opening->date,
                    'cash_amount' => $v2Opening->cash_amount,
                    'bank_amount' => $v2Opening->bank_amount,
                ];
            }
        }

        $cashOpening = (string) ($opening->cash_amount ?? '0.00');
        $bankOpening = (string) ($opening->bank_amount ?? '0.00');
        $cashBalance = (string) $balances->balance('1101');
        $bankAccounts = $bankService->balancesByAccount($balances, true);
        $bankBalance = (string) $bankService->totalBalance($balances, false);
        $sources = DB::table('journal_entries')->distinct()->orderBy('reference_type')->pluck('reference_type');
        $hasJournals = DB::table('journal_entries')->exists();
        $adjustableCodes = $bankService->cashAndBankCodes(true);
        $adjustmentAccounts = DB::table('chart_of_accounts')->where('is_active', true)
            ->when(config('v2.enabled'), fn ($query) => $query->whereIn('account_code', $adjustableCodes))
            ->orderBy('account_code')->get();
        $counterAccounts = config('v2.enabled')
            ? DB::table('chart_of_accounts')->where('is_active', true)->orderBy('account_code')->get()
            : $adjustmentAccounts;

        return view('journals.index', compact(
            'entries', 'olderJournalUrl', 'newerJournalUrl', 'watchJournal', 'opening', 'cashOpening', 'bankOpening', 'cashBalance', 'bankBalance', 'bankAccounts', 'sources', 'filters', 'adjustmentAccounts', 'counterAccounts', 'hasJournals'
        ));
    }

    public function feed(Request $request): JsonResponse
    {
        $validated = $request->validate(['after_id' => 'nullable|integer|min:0', 'check' => 'sometimes|boolean']);
        $afterId = (int) ($validated['after_id'] ?? 0);
        $query = $this->journalQuery(['after_id' => $afterId]);
        if ($request->boolean('check')) {
            return response()->json(['has_new' => $query->exists()], 200, ['Cache-Control' => 'no-store, private']);
        }
        $entries = $this->entryDetails($query->orderBy('id', $afterId > 0 ? 'asc' : 'desc')->limit(25)->get());

        return response()->json([
            'entries' => $entries,
            'latest_id' => (int) ($entries->max('id') ?? ($validated['after_id'] ?? 0)),
        ], 200, ['Cache-Control' => 'no-store, private']);
    }

    public function adjust(Request $request, AccountAdjustmentService $service): RedirectResponse
    {
        $validated = $request->validate([
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'account_code' => ['required', 'string', 'max:20'],
            'counter_account_code' => ['required', 'string', 'max:20', 'different:account_code'],
            'target_balance' => ['required', 'numeric', 'decimal:0,2', 'between:-99999999999999999.99,99999999999999999.99'],
            'description' => ['required', 'string', 'max:200'],
            'password' => ['required', 'current_password'],
        ], ['password.current_password' => 'Password konfirmasi tidak sesuai.']);

        try {
            $service->post($validated, (int) $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput($request->except('password'))->withErrors(['adjustment' => $e->getMessage()]);
        }

        return redirect()->route('journals.index')->with('success', 'Saldo berhasil disesuaikan. Jurnal seimbang tersimpan dan pembukuan siap digunakan.');
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

    private function journalQuery(array $filters): Builder
    {
        return DB::table('journal_entries')
            ->when(! empty($filters['start_date']), fn ($q) => $q->whereDate('transaction_date', '>=', $filters['start_date']))
            ->when(! empty($filters['end_date']), fn ($q) => $q->whereDate('transaction_date', '<=', $filters['end_date']))
            ->when(! empty($filters['source']), fn ($q) => $q->where('reference_type', $filters['source']))
            ->when(! empty($filters['account']), fn ($q) => $q->whereExists(function ($lines) use ($filters) {
                $lines->selectRaw('1')
                    ->from('journal_entry_lines')
                    ->whereColumn('journal_entry_lines.journal_entry_id', 'journal_entries.id')
                    ->where('journal_entry_lines.account_code', $filters['account']);
            }))
            ->when(! empty($filters['after_id']), fn ($q) => $q->where('id', '>', (int) $filters['after_id']));

    }

    private function entryDetails(Collection $headers): Collection
    {
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
