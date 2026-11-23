<?php

namespace App\Http\Controllers;

use App\Services\V2\Operations;
use App\Support\SafeLog;
use Brick\Math\BigDecimal;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChartOfAccountController extends Controller
{
    /** Core accounts used directly by automatic operational journals. */
    public const SYSTEM_ACCOUNTS = [
        '1101', '1102', '1103', '1191', '1301', '2101',
        '3101', '4101', '4102', '5101', '5201', '6101', '6201',
    ];

    public function index(Request $request)
    {
        if (! config('v2.enabled') && DB::table('chart_of_accounts')->count() === 0) {
            (new ChartOfAccountsSeeder)->run();
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'usage' => ['nullable', Rule::in(['used', 'unused'])],
            'per_page' => ['nullable', Rule::in(['10', '25', '50'])],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 10);
        $typeFilter = $filters['type'] ?? null;

        $accounts = DB::table('chart_of_accounts')
            ->when($search !== '', function ($query) use ($search) {
                $needle = mb_strtolower($search);
                $query->where(function ($where) use ($needle) {
                    $where->where(DB::raw('LOWER(account_code)'), 'like', "%{$needle}%")
                        ->orWhere(DB::raw('LOWER(account_name)'), 'like', "%{$needle}%");
                });
            })
            ->when($typeFilter, fn ($query) => $query->where('account_type', $typeFilter))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when(($filters['usage'] ?? null) === 'used', fn ($query) => $query->whereExists(
                fn ($lines) => $lines->selectRaw('1')->from('journal_entry_lines')->whereColumn('journal_entry_lines.account_code', 'chart_of_accounts.account_code')
            ))
            ->when(($filters['usage'] ?? null) === 'unused', fn ($query) => $query->whereNotExists(
                fn ($lines) => $lines->selectRaw('1')->from('journal_entry_lines')->whereColumn('journal_entry_lines.account_code', 'chart_of_accounts.account_code')
            ))
            ->select('id', 'account_code', 'account_name', 'account_type', 'normal_balance', 'is_active')
            ->orderBy('account_code')
            ->paginate($perPage)
            ->setPath(route('coa.index'))
            ->appends($request->query());

        $codes = collect($accounts->items())->pluck('account_code');
        $linesByAccount = $codes->isEmpty()
            ? collect()
            : DB::table('journal_entry_lines')
                ->whereIn('account_code', $codes)
                ->get(['account_code', 'debit', 'credit'])
                ->groupBy('account_code');

        foreach ($accounts->items() as $account) {
            $debit = BigDecimal::zero();
            $credit = BigDecimal::zero();
            foreach ($linesByAccount->get($account->account_code, collect()) as $line) {
                $debit = $debit->plus((string) $line->debit);
                $credit = $credit->plus((string) $line->credit);
            }
            $account->transaction_count = $linesByAccount->get($account->account_code, collect())->count();
            $account->total_debit = (string) $debit->toScale(2);
            $account->total_credit = (string) $credit->toScale(2);
            $account->balance = (string) ($account->normal_balance === 'credit'
                ? $credit->minus($debit)
                : $debit->minus($credit))->toScale(2);
            $account->is_system = in_array($account->account_code, self::SYSTEM_ACCOUNTS, true);
        }

        $typeCounts = DB::table('chart_of_accounts')
            ->select('account_type', DB::raw('count(*) as count'))
            ->groupBy('account_type')
            ->pluck('count', 'account_type')
            ->toArray();
        $summary = [
            'total' => DB::table('chart_of_accounts')->count(),
            'active' => DB::table('chart_of_accounts')->where('is_active', true)->count(),
            'inactive' => DB::table('chart_of_accounts')->where('is_active', false)->count(),
            'used' => DB::table('chart_of_accounts')->whereExists(
                fn ($lines) => $lines->selectRaw('1')->from('journal_entry_lines')->whereColumn('journal_entry_lines.account_code', 'chart_of_accounts.account_code')
            )->count(),
        ];

        return view('coa.index', compact('accounts', 'typeFilter', 'typeCounts', 'filters', 'search', 'perPage', 'summary'));
    }

    private function classificationRule(Request $request): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
            $type = match (substr((string) $value, 0, 1)) {
                '1' => 'asset', '2' => 'liability', '3' => 'equity',
                '4', '7' => 'revenue', '5', '6', '8' => 'expense',
                default => null,
            };
            if ($type !== null && $request->input('account_type') !== $type) {
                $fail('Jenis akun tidak sesuai dengan kelompok kode akun.');
            }
        };
    }

    private function normalBalanceRule(Request $request): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
            $expected = match ($request->input('account_type')) {
                'asset', 'expense' => 'debit',
                'liability', 'equity', 'revenue' => 'credit',
                default => null,
            };
            if ($expected !== null && $value !== $expected) {
                $fail("Saldo normal untuk jenis akun ini harus {$expected}.");
            }
        };
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeOperationalWrite($request);
        $validated = $request->validate([
            'account_code' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:chart_of_accounts,account_code', $this->classificationRule($request)],
            'account_name' => ['required', 'string', 'max:100'],
            'account_type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'normal_balance' => ['required', Rule::in(['debit', 'credit']), $this->normalBalanceRule($request)],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'account_code.unique' => 'Kode akun ini sudah terdaftar. Gunakan kode unik lain.',
            'account_code.alpha_dash' => 'Kode akun hanya boleh memuat huruf, angka, tanda strip, atau garis bawah.',
        ]);

        $code = trim($validated['account_code']);
        $active = (bool) ($validated['is_active'] ?? true);
        if (in_array($code, self::SYSTEM_ACCOUNTS, true) && ! $active) {
            return $this->failure($request, 'Akun inti sistem wajib tetap aktif karena dipakai transaksi otomatis.', 'is_active');
        }

        $id = DB::table('chart_of_accounts')->insertGetId([
            'account_code' => trim($validated['account_code']),
            'account_name' => trim($validated['account_name']),
            'account_type' => $validated['account_type'],
            'normal_balance' => $validated['normal_balance'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($request->routeIs('v2.coa.*')) {
            SafeLog::info('coa.created', ['actor_id' => $request->user()->id, 'account_id' => $id, 'account_code' => $code]);
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Akun '.$code.' berhasil ditambahkan.'], 201);
            }
        }

        return redirect()->route('coa.index')->with('success', 'Akun '.$validated['account_code'].' ('.$validated['account_name'].') berhasil ditambahkan ke Bagan Akun.');
    }

    public function update(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $this->authorizeOperationalWrite($request);
        $account = DB::table('chart_of_accounts')->where('id', $id)->first();
        if (! $account) {
            abort_if($request->routeIs('v2.coa.*'), 404);

            return redirect()->route('coa.index')->with('error', 'Akun tidak ditemukan.');
        }

        $validated = $request->validate([
            'account_code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('chart_of_accounts', 'account_code')->ignore($id), $this->classificationRule($request)],
            'account_name' => ['required', 'string', 'max:100'],
            'account_type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'normal_balance' => ['required', Rule::in(['debit', 'credit']), $this->normalBalanceRule($request)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return DB::transaction(function () use ($request, $id, $validated): JsonResponse|RedirectResponse {
            $account = DB::table('chart_of_accounts')->where('id', $id)->lockForUpdate()->firstOrFail();
            $newCode = trim($validated['account_code']);
            $oldCode = $account->account_code;
            $newActive = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : (bool) $account->is_active;
            $isSystem = in_array($oldCode, self::SYSTEM_ACCOUNTS, true);

            if ($isSystem && $newCode !== $oldCode) {
                return $this->failure($request, 'Kode akun inti sistem ('.$oldCode.') tidak boleh diubah nomornya karena terhubung langsung dengan alur transaksi otomatis kasir/operasional.', 'account_code');
            }
            if (($isSystem || in_array($newCode, self::SYSTEM_ACCOUNTS, true)) && ! $newActive) {
                return $this->failure($request, 'Akun inti sistem wajib tetap aktif karena dipakai transaksi otomatis.', 'is_active');
            }

            $classificationChanged = $newCode !== $oldCode
                || $validated['account_type'] !== $account->account_type
                || $validated['normal_balance'] !== $account->normal_balance;
            if ($classificationChanged && DB::table('journal_entry_lines')->where('account_code', $oldCode)->exists()) {
                return $this->failure($request, 'Akun yang sudah digunakan di jurnal tidak boleh diubah kode, jenis, atau saldo normalnya.', 'account_code');
            }

            DB::table('chart_of_accounts')->where('id', $id)->update([
                'account_code' => $newCode,
                'account_name' => trim($validated['account_name']),
                'account_type' => $validated['account_type'],
                'normal_balance' => $validated['normal_balance'],
                'is_active' => $newActive,
                'updated_at' => now(),
            ]);

            if ($request->routeIs('v2.coa.*')) {
                SafeLog::info('coa.updated', ['actor_id' => $request->user()->id, 'account_id' => $id, 'account_code' => $newCode, 'is_active' => $newActive]);
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Akun '.$newCode.' berhasil diperbarui.']);
                }
            }

            return redirect()->route('coa.index')->with('success', 'Akun '.$newCode.' berhasil diperbarui.');
        });
    }

    public function archive(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $this->authorizeOperationalWrite($request);

        return DB::transaction(function () use ($request, $id): JsonResponse|RedirectResponse {
            $account = DB::table('chart_of_accounts')->where('id', $id)->lockForUpdate()->firstOrFail();
            if (in_array($account->account_code, self::SYSTEM_ACCOUNTS, true)) {
                return $this->failure($request, 'Akun inti sistem wajib tetap aktif karena dipakai transaksi otomatis.', 'is_active');
            }
            DB::table('chart_of_accounts')->where('id', $id)->update(['is_active' => false, 'updated_at' => now()]);
            SafeLog::info('coa.archived', ['actor_id' => $request->user()->id, 'account_id' => $id, 'account_code' => $account->account_code]);

            return $request->expectsJson()
                ? response()->json(['message' => 'Akun '.$account->account_code.' diarsipkan; riwayat jurnal tetap tersimpan.'])
                : redirect()->route('coa.index')->with('success', 'Akun diarsipkan; riwayat jurnal tetap tersimpan.');
        });
    }

    private function authorizeOperationalWrite(Request $request): void
    {
        if ($request->routeIs('v2.coa.*')) {
            app(Operations::class)->requireRole((int) $request->user()->id, ['admin']);
        }
    }

    private function failure(Request $request, string $message, string $field): RedirectResponse
    {
        if ($request->routeIs('v2.coa.*') && $request->expectsJson()) {
            throw ValidationException::withMessages([$field => $message]);
        }

        return redirect()->route('coa.index')->with('error', $message);
    }

    public function destroy($id)
    {
        $account = DB::table('chart_of_accounts')->where('id', $id)->first();
        if (! $account) {
            return redirect()->route('coa.index')->with('error', 'Akun tidak ditemukan.');
        }
        if (in_array($account->account_code, self::SYSTEM_ACCOUNTS, true)) {
            return redirect()->route('coa.index')->with('error', 'Akun '.$account->account_code.' ('.$account->account_name.') adalah akun inti sistem dan tidak boleh dihapus.');
        }

        $transactionCount = DB::table('journal_entry_lines')->where('account_code', $account->account_code)->count();
        if ($transactionCount > 0) {
            return redirect()->route('coa.index')->with('error', 'Akun '.$account->account_code.' ('.$account->account_name.') tidak dapat dihapus karena sudah memiliki '.$transactionCount.' catatan transaksi di jurnal umum.');
        }

        DB::table('chart_of_accounts')->where('id', $id)->delete();

        return redirect()->route('coa.index')->with('success', 'Akun '.$account->account_code.' berhasil dihapus dari Bagan Akun.');
    }
}
