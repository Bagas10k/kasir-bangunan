<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\BankAccounts;
use App\Services\V2\CashBankTransfers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashBankTransferController extends Controller
{
    public function store(Request $request, CashBankTransfers $transfers, BankAccounts $bankAccounts): JsonResponse
    {
        $allowed = $bankAccounts->cashAndBankCodes(true);
        $validated = $request->validate([
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'from_account_code' => ['required', 'string', Rule::in($allowed)],
            'to_account_code' => ['required', 'string', Rule::in($allowed), 'different:from_account_code'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'description' => ['required', 'string', 'max:200'],
            'password' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'min:8', 'max:100', 'regex:/^[\x20-\x7E]+$/'],
            'confirm_duplicate' => ['nullable', 'boolean'],
        ], [
            'from_account_code.in' => 'Akun sumber harus Kas (1101) atau Rekening Bank yang aktif.',
            'to_account_code.in' => 'Akun tujuan harus Kas (1101) atau Rekening Bank yang aktif.',
            'to_account_code.different' => 'Akun tujuan harus berbeda dari akun sumber.',
            'amount.gt' => 'Nominal pindah dana harus lebih dari nol.',
            'idempotency_key.min' => 'Kunci transaksi tidak valid.',
            'idempotency_key.max' => 'Kunci transaksi tidak valid.',
            'idempotency_key.regex' => 'Kunci transaksi tidak valid.',
        ]);

        return response()->json(
            $transfers->post((int) $request->user()->id, $validated),
            201
        );
    }
}
