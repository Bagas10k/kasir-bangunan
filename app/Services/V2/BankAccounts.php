<?php

namespace App\Services\V2;

use App\Services\AccountBalanceService;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Sumber kebenaran tunggal untuk daftar akun Rekening Bank (COA) yang dipakai sebagai
 * metode pembayaran / sumber dana transfer di seluruh modul V2.
 *
 * Akun 1102 selalu dikenali sebagai rekening bank bawaan demi kompatibilitas penuh
 * dengan data historis dan pemanggil API tanpa parameter rekening bank.
 */
final class BankAccounts
{
    public const CASH = '1101';

    public const DEFAULT_BANK = '1102';

    public function hasFlagColumn(): bool
    {
        return Schema::hasTable('chart_of_accounts') && Schema::hasColumn('chart_of_accounts', 'is_bank_account');
    }

    /** @return Collection<int, object{account_code:string, account_name:string, is_active:bool|int}> */
    public function active(): Collection
    {
        return $this->query(true)->get(['account_code', 'account_name', 'is_active']);
    }

    /** @return Collection<int, object{account_code:string, account_name:string, is_active:bool|int}> */
    public function all(): Collection
    {
        return $this->query(false)->get(['account_code', 'account_name', 'is_active']);
    }

    /** @return list<string> */
    public function codes(bool $activeOnly = true): array
    {
        $codes = $this->query($activeOnly)->pluck('account_code')->map(fn ($code): string => (string) $code)->all();
        if (! $activeOnly && ! in_array(self::DEFAULT_BANK, $codes, true)) {
            array_unshift($codes, self::DEFAULT_BANK);
        }

        return array_values(array_unique($codes));
    }

    /** @return list<string> */
    public function cashAndBankCodes(bool $activeOnly = false): array
    {
        return array_values(array_unique(array_merge([self::CASH], $this->codes($activeOnly))));
    }

    public function isBankAccount(string $code, bool $activeOnly = false): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }

        return $this->query($activeOnly)->where('account_code', $code)->exists();
    }

    /**
     * Validasi dan selesaikan kode akun bank tujuan/sumber.
     * Nilai kosong/null otomatis jatuh ke 1102 agar kompatibel dengan alur lama.
     */
    public function resolve(?string $code, string $field = 'bank_account_code'): string
    {
        $trimmed = trim((string) ($code ?? ''));
        if ($trimmed === '') {
            return self::DEFAULT_BANK;
        }

        if (! $this->isBankAccount($trimmed, true)) {
            throw ValidationException::withMessages([
                $field => 'Pilih rekening bank yang aktif dari Bagan Akun.',
            ]);
        }

        return $trimmed;
    }

    /** @return array<string, string> */
    public function nameMap(bool $activeOnly = false): array
    {
        $map = [];
        foreach ($this->query($activeOnly)->get(['account_code', 'account_name']) as $row) {
            $map[(string) $row->account_code] = (string) $row->account_name;
        }

        return $map;
    }

    public function label(?string $code, ?array $nameMap = null): string
    {
        $resolved = trim((string) ($code ?? '')) ?: self::DEFAULT_BANK;
        $map = $nameMap ?? $this->nameMap(false);
        if (isset($map[$resolved])) {
            return $resolved.' · '.$map[$resolved];
        }

        return $resolved === self::DEFAULT_BANK ? 'Bank Utama' : $resolved;
    }

    public function totalBalance(AccountBalanceService $balances, bool $activeOnly = false): BigDecimal
    {
        $total = BigDecimal::zero();
        foreach ($this->codes($activeOnly) as $code) {
            $total = $total->plus($balances->balance($code));
        }

        return $total->toScale(2);
    }

    /** @return list<array{account_code:string, account_name:string, balance:string}> */
    public function balancesByAccount(AccountBalanceService $balances, bool $activeOnly = true): array
    {
        $rows = [];
        foreach ($this->query($activeOnly)->get(['account_code', 'account_name']) as $acc) {
            $code = (string) $acc->account_code;
            $rows[] = [
                'account_code' => $code,
                'account_name' => (string) $acc->account_name,
                'balance' => (string) $balances->balance($code),
            ];
        }

        return $rows;
    }

    private function query(bool $activeOnly)
    {
        $hasFlag = $this->hasFlagColumn();

        return DB::table('chart_of_accounts')
            ->where('account_type', 'asset')
            ->where('normal_balance', 'debit')
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->where(function ($q) use ($hasFlag): void {
                $q->where('account_code', self::DEFAULT_BANK);
                if ($hasFlag) {
                    $q->orWhere('is_bank_account', true);
                }
            })
            ->orderByRaw('CASE WHEN account_code = ? THEN 0 ELSE 1 END', [self::DEFAULT_BANK])
            ->orderBy('account_code');
    }
}
