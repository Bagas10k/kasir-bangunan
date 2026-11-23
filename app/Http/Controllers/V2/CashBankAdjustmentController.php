<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\CashBankAdjustments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashBankAdjustmentController extends Controller
{
    public function store(Request $request, CashBankAdjustments $adjustments): JsonResponse
    {
        $validated = $request->validate([
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'account_code' => ['required', 'string', Rule::in(CashBankAdjustments::ALLOWED_ACCOUNTS)],
            'counter_account_code' => ['required', 'string', 'max:20', 'different:account_code'],
            'target_balance' => ['required', 'numeric', 'decimal:0,2', 'between:0,99999999999999999.99'],
            'description' => ['required', 'string', 'max:200'],
            'password' => ['required', 'string', 'max:255'],
        ], [
            'account_code.in' => 'Tahap ini hanya mengizinkan penyesuaian saldo Kas (1101) atau Bank (1102).',
            'counter_account_code.different' => 'Akun lawan harus berbeda dari akun yang disesuaikan.',
        ]);

        return response()->json(
            $adjustments->post((int) $request->user()->id, $validated)
        );
    }
}
