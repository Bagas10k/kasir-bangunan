<?php

namespace App\Http\Controllers;

use App\Services\V2\BankAccounts;
use App\Services\V2\Operations;
use App\Support\SafeLog;
use Brick\Math\BigDecimal;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChartOfAccountController extends Controller
{
    /** Core accounts used directly by automatic operational journals. */
    public const SYSTEM_ACCOUNTS = [
        '1101', '1102', '1103', '1191', '1301', '2101',
        '3101', '4101', '4102', '4103', '4104', '5101', '5201', '6101', '6201', '7101',
    ];

    /** Akun pengurang (kontra) yang dikelola otomatis oleh sistem. */
    public const SYSTEM_CONTRA_ACCOUNTS = ['4103', '4104'];

    /** Keterangan integrasi sistem untuk setiap akun inti yang dikunci. */
    public const SYSTEM_ACCOUNT_ROLES = [
        '1101' => 'Kasir & pembayaran tunai',
        '1102' => 'Rekening bank utama',
        '1103' => 'Penjualan & piutang pelanggan',
        '1191' => 'Uang muka sopir & pengiriman',
        '1301' => 'Persediaan & penerimaan barang',
        '2101' => 'Utang pembelian supplier',
        '3101' => 'Saldo awal & modal pemilik',
        '4101' => 'Penjualan material POS',
        '4102' => 'Pendapatan ongkir POS',
        '4103' => 'Pengurang otomatis diskon item & nota POS',
        '4104' => 'Pengurang otomatis retur penjualan',
        '5101' => 'HPP otomatis saat penjualan',
        '5201' => 'Selisih kurang opname persediaan',
        '6101' => 'Beban operasional toko',
        '6201' => 'Beban transportasi & ritase',
        '7101' => 'Selisih lebih opname & pendapatan lain',
    ];

    /**
     * Saran akun siap pakai untuk admin toko (hanya Rekening Bank tambahan & Beban Operasional).
     * Akun inti transaksi & akun pengurang (4103/4104) dikelola sistem.
     *
     * @var array<int, array{code:string, name:string, type:string, balance:string, contra:bool, bank?:bool, group:string}>
     */
    public const RECOMMENDED_PRESETS = [
        ['code' => '6102', 'name' => 'Beban Listrik & Air', 'type' => 'expense', 'balance' => 'debit', 'contra' => false, 'group' => 'Beban Operasional & Pengeluaran Toko (muncul di menu Pengeluaran)'],
        ['code' => '6103', 'name' => 'Beban Servis & Perawatan', 'type' => 'expense', 'balance' => 'debit', 'contra' => false, 'group' => 'Beban Operasional & Pengeluaran Toko (muncul di menu Pengeluaran)'],
        ['code' => '6104', 'name' => 'Beban Perlengkapan & ATK', 'type' => 'expense', 'balance' => 'debit', 'contra' => false, 'group' => 'Beban Operasional & Pengeluaran Toko (muncul di menu Pengeluaran)'],
        ['code' => '6105', 'name' => 'Beban Gaji & Upah Karyawan', 'type' => 'expense', 'balance' => 'debit', 'contra' => false, 'group' => 'Beban Operasional & Pengeluaran Toko (muncul di menu Pengeluaran)'],
        ['code' => '6106', 'name' => 'Beban Internet, Pulsa & Telepon', 'type' => 'expense', 'balance' => 'debit', 'contra' => false, 'group' => 'Beban Operasional & Pengeluaran Toko (muncul di menu Pengeluaran)'],
        ['code' => '6107', 'name' => 'Beban Konsumsi & Reimburse Operasional', 'type' => 'expense', 'balance' => 'debit', 'contra' => false, 'group' => 'Beban Operasional & Pengeluaran Toko (muncul di menu Pengeluaran)'],
        ['code' => '1104', 'name' => 'Rekening Bank BCA', 'type' => 'asset', 'balance' => 'debit', 'contra' => false, 'bank' => true, 'group' => 'Rekening Bank Tambahan (muncul di pilihan Transfer)'],
        ['code' => '1105', 'name' => 'Rekening Bank BRI', 'type' => 'asset', 'balance' => 'debit', 'contra' => false, 'bank' => true, 'group' => 'Rekening Bank Tambahan (muncul di pilihan Transfer)'],
        ['code' => '1106', 'name' => 'Rekening Bank Mandiri', 'type' => 'asset', 'balance' => 'debit', 'contra' => false, 'bank' => true, 'group' => 'Rekening Bank Tambahan (muncul di pilihan Transfer)'],
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
            'scope' => ['nullable', Rule::in(['system', 'custom'])],
            'per_page' => ['nullable', Rule::in(['10', '25', '50'])],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 10);
        $typeFilter = $filters['type'] ?? null;

        $selectColumns = ['id', 'account_code', 'account_name', 'account_type', 'normal_balance', 'is_active'];
        if (Schema::hasColumn('chart_of_accounts', 'is_bank_account')) {
            $selectColumns[] = 'is_bank_account';
        }
        if (Schema::hasColumn('chart_of_accounts', 'is_contra')) {
            $selectColumns[] = 'is_contra';
        }

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
            ->when(($filters['scope'] ?? null) === 'system', fn ($query) => $query->whereIn('account_code', self::SYSTEM_ACCOUNTS))
            ->when(($filters['scope'] ?? null) === 'custom', fn ($query) => $query->whereNotIn('account_code', self::SYSTEM_ACCOUNTS))
            ->when(($filters['usage'] ?? null) === 'used', fn ($query) => $query->whereExists(
                fn ($lines) => $lines->selectRaw('1')->from('journal_entry_lines')->whereColumn('journal_entry_lines.account_code', 'chart_of_accounts.account_code')
            ))
            ->when(($filters['usage'] ?? null) === 'unused', fn ($query) => $query->whereNotExists(
                fn ($lines) => $lines->selectRaw('1')->from('journal_entry_lines')->whereColumn('journal_entry_lines.account_code', 'chart_of_accounts.account_code')
            ))
            ->select($selectColumns)
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
            $account->system_role = self::SYSTEM_ACCOUNT_ROLES[$account->account_code] ?? null;
            $account->is_bank_account = (bool) ($account->is_bank_account ?? false) || $account->account_code === BankAccounts::DEFAULT_BANK;
            $account->is_contra = $this->resolveContraFlag($account);
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
            'system' => DB::table('chart_of_accounts')->whereIn('account_code', self::SYSTEM_ACCOUNTS)->count(),
            'custom' => DB::table('chart_of_accounts')->whereNotIn('account_code', self::SYSTEM_ACCOUNTS)->count(),
        ];

        $existingRows = DB::table('chart_of_accounts')->orderBy('account_code')->get(['account_code', 'account_name']);
        $existingAccountCodes = $existingRows->pluck('account_code')->values()->all();
        $existingCodes = $existingRows->pluck('account_code')->flip()->all();
        $existingNames = $existingRows
            ->mapWithKeys(fn ($row): array => [mb_strtolower(trim((string) $row->account_name)) => true])
            ->all();
        $recommendedPresets = array_values(array_filter(
            self::RECOMMENDED_PRESETS,
            fn (array $preset): bool => ! isset($existingCodes[$preset['code']])
                && ! isset($existingNames[mb_strtolower(trim($preset['name']))])
        ));

        return view('coa.index', compact(
            'accounts',
            'typeFilter',
            'typeCounts',
            'filters',
            'search',
            'perPage',
            'summary',
            'recommendedPresets',
            'existingAccountCodes'
        ));
    }

    private function standardNormalBalance(string $accountType): ?string
    {
        return match ($accountType) {
            'asset', 'expense' => 'debit',
            'liability', 'equity', 'revenue' => 'credit',
            default => null,
        };
    }

    private function expectedNormalBalance(string $accountType, bool $isContra): ?string
    {
        $standard = $this->standardNormalBalance($accountType);
        if ($standard === null) {
            return null;
        }

        if (! $isContra) {
            return $standard;
        }

        return $standard === 'debit' ? 'credit' : 'debit';
    }

    private function resolveContraFlag(object $account): bool
    {
        if (property_exists($account, 'is_contra') && $account->is_contra !== null) {
            return (bool) $account->is_contra;
        }

        $standard = $this->standardNormalBalance((string) $account->account_type);

        return $standard !== null && (string) $account->normal_balance !== $standard;
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
            $isContra = filter_var($request->input('is_contra', false), FILTER_VALIDATE_BOOLEAN);
            $expected = $this->expectedNormalBalance((string) $request->input('account_type'), $isContra);
            if ($expected !== null && $value !== $expected) {
                $label = $isContra ? 'akun kontra jenis ini' : 'jenis akun ini';
                $fail("Saldo normal untuk {$label} harus {$expected}.");
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
            'is_contra' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'is_bank_account' => ['nullable', 'boolean'],
        ], [
            'account_code.unique' => 'Kode akun ini sudah terdaftar. Gunakan kode unik lain.',
            'account_code.alpha_dash' => 'Kode akun hanya boleh memuat huruf, angka, tanda strip, atau garis bawah.',
        ]);

        $code = trim($validated['account_code']);
        $active = (bool) ($validated['is_active'] ?? true);
        $isContra = (bool) ($validated['is_contra'] ?? false);
        $isBank = array_key_exists('is_bank_account', $validated)
            ? (bool) $validated['is_bank_account']
            : ($code === BankAccounts::DEFAULT_BANK);

        if ($bankError = $this->bankFlagError($code, $validated['account_type'], $validated['normal_balance'], $isBank)) {
            return $this->failure($request, $bankError, 'is_bank_account');
        }
        if ($request->routeIs('v2.coa.*')) {
            if ($scopeError = $this->v2CustomScopeError($code, $validated['account_type'], $validated['normal_balance'], $isContra, $isBank)) {
                return $this->failure($request, $scopeError[1], $scopeError[0]);
            }
        } else {
            if (in_array($code, self::SYSTEM_ACCOUNTS, true) && ! $active) {
                return $this->failure($request, 'Akun inti sistem wajib tetap aktif karena dipakai transaksi otomatis.', 'is_active');
            }
            if ($contraError = $this->contraFlagError($code, $isContra, $isBank)) {
                return $this->failure($request, $contraError, 'is_contra');
            }
        }

        $row = [
            'account_code' => $code,
            'account_name' => trim($validated['account_name']),
            'account_type' => $validated['account_type'],
            'normal_balance' => $validated['normal_balance'],
            'is_active' => $active,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('chart_of_accounts', 'is_contra')) {
            $row['is_contra'] = $isContra;
        }
        if (Schema::hasColumn('chart_of_accounts', 'is_bank_account')) {
            $row['is_bank_account'] = $isBank;
        }
        $id = DB::table('chart_of_accounts')->insertGetId($row);

        if ($request->routeIs('v2.coa.*')) {
            SafeLog::info('coa.created', ['actor_id' => $request->user()->id, 'account_id' => $id, 'account_code' => $code, 'is_bank_account' => $isBank, 'is_contra' => $isContra]);
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
            'is_contra' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'is_bank_account' => ['nullable', 'boolean'],
        ]);

        return DB::transaction(function () use ($request, $id, $validated): JsonResponse|RedirectResponse {
            $account = DB::table('chart_of_accounts')->where('id', $id)->lockForUpdate()->firstOrFail();
            $newCode = trim($validated['account_code']);
            $oldCode = $account->account_code;
            $newActive = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : (bool) $account->is_active;
            $oldContra = $this->resolveContraFlag($account);
            $newContra = array_key_exists('is_contra', $validated) ? (bool) $validated['is_contra'] : $oldContra;
            $isSystem = in_array($oldCode, self::SYSTEM_ACCOUNTS, true);

            if ($isSystem && $newCode !== $oldCode) {
                return $this->failure($request, 'Kode akun inti sistem ('.$oldCode.') tidak boleh diubah nomornya karena terhubung langsung dengan alur transaksi otomatis kasir/operasional.', 'account_code');
            }
            if ($isSystem && ($validated['account_type'] !== $account->account_type || $validated['normal_balance'] !== $account->normal_balance || $newContra !== $oldContra)) {
                return $this->failure($request, 'Klasifikasi dan saldo normal akun inti sistem ('.$oldCode.') terkunci karena terhubung transaksi otomatis; hanya nama akun yang dapat disesuaikan.', 'account_type');
            }
            if (($isSystem || in_array($newCode, self::SYSTEM_ACCOUNTS, true)) && ! $newActive) {
                return $this->failure($request, 'Akun inti sistem wajib tetap aktif karena dipakai transaksi otomatis.', 'is_active');
            }

            $oldIsBank = (bool) ($account->is_bank_account ?? false) || $oldCode === BankAccounts::DEFAULT_BANK;
            $newIsBank = array_key_exists('is_bank_account', $validated)
                ? (bool) $validated['is_bank_account']
                : ((bool) ($account->is_bank_account ?? false) || $newCode === BankAccounts::DEFAULT_BANK);
            if ($bankError = $this->bankFlagError($newCode, $validated['account_type'], $validated['normal_balance'], $newIsBank)) {
                return $this->failure($request, $bankError, 'is_bank_account');
            }
            if ($contraError = $this->contraFlagError($newCode, $newContra, $newIsBank)) {
                return $this->failure($request, $contraError, 'is_contra');
            }

            $classificationChanged = $newCode !== $oldCode
                || $validated['account_type'] !== $account->account_type
                || $validated['normal_balance'] !== $account->normal_balance
                || $newContra !== $oldContra;
            if ($classificationChanged && DB::table('journal_entry_lines')->where('account_code', $oldCode)->exists()) {
                return $this->failure($request, 'Akun yang sudah digunakan di jurnal tidak boleh diubah kode, jenis, atau saldo normalnya.', 'account_code');
            }
            if ($request->routeIs('v2.coa.*') && ! $isSystem && ($classificationChanged || $newIsBank !== $oldIsBank)) {
                if ($scopeError = $this->v2CustomScopeError($newCode, $validated['account_type'], $validated['normal_balance'], $newContra, $newIsBank)) {
                    return $this->failure($request, $scopeError[1], $scopeError[0]);
                }
            }

            $update = [
                'account_code' => $newCode,
                'account_name' => trim($validated['account_name']),
                'account_type' => $validated['account_type'],
                'normal_balance' => $validated['normal_balance'],
                'is_active' => $newActive,
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('chart_of_accounts', 'is_contra')) {
                $update['is_contra'] = $newContra;
            }
            if (Schema::hasColumn('chart_of_accounts', 'is_bank_account')) {
                $update['is_bank_account'] = $newIsBank;
            }
            DB::table('chart_of_accounts')->where('id', $id)->update($update);

            if ($request->routeIs('v2.coa.*')) {
                SafeLog::info('coa.updated', ['actor_id' => $request->user()->id, 'account_id' => $id, 'account_code' => $newCode, 'is_active' => $newActive, 'is_bank_account' => $newIsBank, 'is_contra' => $newContra]);
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Akun '.$newCode.' berhasil diperbarui.']);
                }
            }

            return redirect()->route('coa.index')->with('success', 'Akun '.$newCode.' berhasil diperbarui.');
        });
    }

    /**
     * Batasan fitur V2: akun buatan admin hanya untuk Rekening Bank tambahan atau Beban Operasional (6xxx/8xxx).
     * Akun inti transaksi dan akun pengurang (4103/4104) dikelola otomatis oleh sistem.
     *
     * @return array{0: string, 1: string}|null
     */
    private function v2CustomScopeError(string $code, string $type, string $normalBalance, bool $isContra, bool $isBank): ?array
    {
        if ($isContra) {
            return ['is_contra', 'Akun pengurang (kontra) dikelola otomatis oleh sistem (4103 Potongan Penjualan & 4104 Retur Penjualan) dan tidak dibuat manual.'];
        }
        if (in_array($code, self::SYSTEM_ACCOUNTS, true)) {
            return ['account_code', 'Kode '.$code.' adalah akun inti sistem dan dikelola otomatis oleh sistem.'];
        }

        $isExtraBank = $isBank && $type === 'asset' && $normalBalance === 'debit' && str_starts_with($code, '1');
        $isExtraOperatingExpense = ! $isBank && $type === 'expense' && $normalBalance === 'debit' && (str_starts_with($code, '6') || str_starts_with($code, '8'));

        if (! $isExtraBank && ! $isExtraOperatingExpense) {
            return ['account_type', 'Akun baru hanya untuk Rekening Bank tambahan (1xxx) atau Beban Operasional (6xxx/8xxx). Akun inti transaksi dan akun pengurang dikelola sistem.'];
        }

        return null;
    }

    private function contraFlagError(string $code, bool $isContra, bool $isBank): ?string
    {
        if (! $isContra) {
            return null;
        }
        if (in_array($code, self::SYSTEM_ACCOUNTS, true) && ! in_array($code, self::SYSTEM_CONTRA_ACCOUNTS, true)) {
            return 'Akun inti sistem tidak boleh ditandai sebagai akun kontra.';
        }
        if ($isBank) {
            return 'Rekening bank tidak boleh ditandai sebagai akun kontra.';
        }

        return null;
    }

    private function bankFlagError(string $code, string $type, string $normalBalance, bool $isBank): ?string
    {
        if ($code === BankAccounts::DEFAULT_BANK && ! $isBank) {
            return 'Akun 1102 (Bank utama) wajib tetap bertanda Rekening Bank.';
        }
        if ($isBank && $code !== BankAccounts::DEFAULT_BANK) {
            if ($type !== 'asset' || $normalBalance !== 'debit') {
                return 'Rekening bank harus berupa akun Aset bersaldo normal Debet.';
            }
            if (in_array($code, self::SYSTEM_ACCOUNTS, true)) {
                return 'Akun inti non-bank tidak boleh ditandai sebagai rekening bank.';
            }
        }

        return null;
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
