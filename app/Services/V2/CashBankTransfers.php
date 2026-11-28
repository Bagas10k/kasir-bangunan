<?php

namespace App\Services\V2;

use App\Services\AccountBalanceService;
use App\Support\SafeLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Pindah dana internal antar akun Kas (1101) dan Rekening Bank aktif:
 * setor tunai ke bank, tarik tunai ke kas utama, atau transfer antar rekening bank.
 *
 * Mencatat jurnal seimbang DR akun tujuan / CR akun sumber; arus kas bersih
 * pada Laporan Arus Kas otomatis nol karena kedua sisi berada di kelompok Kas & Bank.
 */
final class CashBankTransfers
{
    public function __construct(
        private AccountBalanceService $balances,
        private Operations $operations,
        private BankAccounts $bankAccounts,
        private OperationIdempotency $idempotency,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function post(int $actor, array $data): array
    {
        $this->operations->requireRole($actor, ['admin']);
        $this->confirmPassword($actor, (string) ($data['password'] ?? ''));

        $fromCode = trim((string) ($data['from_account_code'] ?? ''));
        $toCode = trim((string) ($data['to_account_code'] ?? ''));
        $allowed = $this->bankAccounts->cashAndBankCodes(true);

        if (! in_array($fromCode, $allowed, true)) {
            throw ValidationException::withMessages([
                'from_account_code' => 'Akun sumber harus Kas (1101) atau Rekening Bank yang aktif.',
            ]);
        }
        if (! in_array($toCode, $allowed, true)) {
            throw ValidationException::withMessages([
                'to_account_code' => 'Akun tujuan harus Kas (1101) atau Rekening Bank yang aktif.',
            ]);
        }
        if ($fromCode === $toCode) {
            throw ValidationException::withMessages([
                'to_account_code' => 'Akun tujuan harus berbeda dari akun sumber.',
            ]);
        }

        $date = trim((string) ($data['transaction_date'] ?? now()->toDateString()));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            throw ValidationException::withMessages(['transaction_date' => 'Tanggal pindah dana tidak valid.']);
        }
        if ($date > now()->toDateString()) {
            throw ValidationException::withMessages(['transaction_date' => 'Tanggal pindah dana tidak boleh melewati hari ini.']);
        }

        try {
            $amount = Money::value((string) ($data['amount'] ?? '0'));
        } catch (\Throwable) {
            throw ValidationException::withMessages(['amount' => 'Nominal pindah dana tidak valid.']);
        }
        if (! $amount->isPositive()) {
            throw ValidationException::withMessages(['amount' => 'Nominal pindah dana harus lebih dari nol.']);
        }

        $reason = trim((string) ($data['description'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 200) {
            throw ValidationException::withMessages(['description' => 'Keterangan wajib diisi (maksimal 200 karakter).']);
        }

        $idempotencyKey = $this->idempotency->normalizeKey($data['idempotency_key'] ?? null);
        $confirmDuplicate = filter_var($data['confirm_duplicate'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $requestHash = $this->idempotency->hashPayload([
            'transaction_date' => $date,
            'from_account_code' => $fromCode,
            'to_account_code' => $toCode,
            'amount' => (string) $amount,
            'description' => $reason,
        ]);

        $wasReplayed = false;
        $result = DB::transaction(function () use ($actor, $date, $fromCode, $toCode, $amount, $reason, $idempotencyKey, $confirmDuplicate, $requestHash, &$wasReplayed): array {
            $replayed = $this->idempotency->replay('cash_bank_transfer', $actor, $idempotencyKey, $requestHash);
            if ($replayed !== null) {
                $wasReplayed = true;

                return $replayed;
            }

            $this->assertBookkeepingStarted();

            $accounts = DB::table('chart_of_accounts')
                ->whereIn('account_code', [$fromCode, $toCode])
                ->orderBy('account_code')
                ->lockForUpdate()
                ->get()
                ->keyBy('account_code');

            if (! $accounts->has($fromCode) || ! $accounts[$fromCode]->is_active) {
                throw ValidationException::withMessages(['from_account_code' => 'Akun sumber tidak ditemukan atau tidak aktif.']);
            }
            if (! $accounts->has($toCode) || ! $accounts[$toCode]->is_active) {
                throw ValidationException::withMessages(['to_account_code' => 'Akun tujuan tidak ditemukan atau tidak aktif.']);
            }

            try {
                $this->balances->assertSufficient($fromCode, (string) $amount);
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
            }

            $description = mb_substr(
                'Pindah dana '.$fromCode.' ke '.$toCode.' oleh pengguna #'.$actor.': '.$reason,
                0,
                255
            );

            if (! $confirmDuplicate) {
                $recentCutoff = now()->subMinutes(15);
                $normalizedDesc = mb_strtolower(trim($description));
                $candidates = DB::table('journal_entries as je')
                    ->where('je.reference_type', 'cash_bank_transfer')
                    ->where('je.transaction_date', $date)
                    ->whereExists(function ($query) use ($toCode, $amount): void {
                        $query->selectRaw('1')
                            ->from('journal_entry_lines as l1')
                            ->whereColumn('l1.journal_entry_id', 'je.id')
                            ->where('l1.account_code', $toCode)
                            ->where('l1.debit', (string) $amount);
                    })
                    ->whereExists(function ($query) use ($fromCode, $amount): void {
                        $query->selectRaw('1')
                            ->from('journal_entry_lines as l2')
                            ->whereColumn('l2.journal_entry_id', 'je.id')
                            ->where('l2.account_code', $fromCode)
                            ->where('l2.credit', (string) $amount);
                    })
                    ->orderByDesc('je.id')
                    ->get(['je.id', 'je.transaction_date', 'je.description', 'je.created_at']);

                $duplicate = $candidates->first(function ($entry) use ($normalizedDesc, $recentCutoff): bool {
                    if (mb_strtolower(trim((string) ($entry->description ?? ''))) === $normalizedDesc) {
                        return true;
                    }

                    return $entry->created_at !== null && Carbon::parse($entry->created_at)->greaterThanOrEqualTo($recentCutoff);
                });

                if ($duplicate) {
                    $dateText = Carbon::parse($duplicate->transaction_date)->locale('id')->translatedFormat('j M Y');
                    throw ValidationException::withMessages([
                        'duplicate_transfer' => 'Pindah dana serupa dari '.$fromCode.' ke '.$toCode.' sebesar '.Money::display((string) $amount).' pada '.$dateText.' sudah tercatat sebagai Jurnal #'.$duplicate->id.'. Bila memang ada pindah dana kedua yang sama, tekan tombol "Ya, tetap pindahkan dana".',
                    ]);
                }
            }

            $now = now();
            $journalId = DB::table('journal_entries')->insertGetId([
                'reference_type' => 'cash_bank_transfer',
                'reference_id' => null,
                'description' => $description,
                'transaction_date' => $date,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ([
                ['account' => $accounts[$toCode], 'debit' => (string) $amount, 'credit' => '0.00'],
                ['account' => $accounts[$fromCode], 'debit' => '0.00', 'credit' => (string) $amount],
            ] as $line) {
                DB::table('journal_entry_lines')->insert([
                    'journal_entry_id' => $journalId,
                    'account_code' => $line['account']->account_code,
                    'account_name' => $line['account']->account_name,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $payload = [
                'status' => 'posted',
                'journal_entry_id' => $journalId,
                'from_account_code' => $fromCode,
                'to_account_code' => $toCode,
                'amount' => (string) $amount,
                'message' => 'Pindah dana dari '.$fromCode.' ke '.$toCode.' tersimpan dan jurnalnya dikunci.',
            ];

            $this->idempotency->record('cash_bank_transfer', $actor, $idempotencyKey, $requestHash, $journalId, $payload);

            return $payload;
        });

        if (! $wasReplayed) {
            SafeLog::info('cash_bank_transfer.posted', [
                'actor_id' => $actor,
                'journal_entry_id' => $result['journal_entry_id'],
                'from_account_code' => $fromCode,
                'to_account_code' => $toCode,
                'amount' => $result['amount'],
            ]);
        }

        return $result;
    }

    private function confirmPassword(int $actor, string $password): void
    {
        $hash = DB::table('users')->where('id', $actor)->value('password');
        if ($password === '' || ! is_string($hash) || ! Hash::check($password, $hash)) {
            throw ValidationException::withMessages(['password' => 'Password konfirmasi tidak sesuai.']);
        }
    }

    private function assertBookkeepingStarted(): void
    {
        $started = DB::table('journal_entries')->exists()
            || DB::table('v2_opening_batches')->exists()
            || DB::table('opening_balances')->where('status', 'posted')->exists();

        if (! $started) {
            throw ValidationException::withMessages([
                'transfer' => 'Mulai pembukuan melalui saldo awal terlebih dahulu sebelum memindahkan dana Kas/Bank.',
            ]);
        }
    }
}
