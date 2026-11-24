<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\OperatingExpenses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OperatingExpenseController extends Controller
{
    public function store(Request $request, OperatingExpenses $expenses): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'account_code' => 'required|string|max:20',
            'source' => 'required|in:cash,bank',
            'bank_account_code' => 'nullable|string|max:20',
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'description' => 'nullable|string|max:255',
        ], [
            'date.date_format' => 'Tanggal pengeluaran tidak valid.',
            'account_code.required' => 'Pilih akun beban operasional.',
            'account_code.max' => 'Kode akun terlalu panjang.',
            'source.required' => 'Pilih sumber dana.',
            'source.in' => 'Sumber dana harus Kas Tunai atau Bank.',
            'bank_account_code.max' => 'Kode rekening bank terlalu panjang.',
            'amount.required' => 'Isi nominal pengeluaran.',
            'amount.numeric' => 'Nominal pengeluaran harus berupa angka.',
            'amount.gt' => 'Nominal pengeluaran harus lebih dari nol.',
            'amount.decimal' => 'Nominal pengeluaran maksimal 2 angka desimal.',
            'description.max' => 'Keterangan maksimal 255 karakter.',
        ]);

        try {
            $result = $expenses->post($request->user()->id, $validated);
        } catch (ValidationException $exception) {
            if ($request->wantsJson()) {
                throw $exception;
            }

            return redirect()->route('expenses.index')->withErrors($exception->errors())->withInput();
        }

        if ($request->wantsJson()) {
            return response()->json($result, 201);
        }

        return redirect()->route('expenses.index')
            ->with('success', 'Pengeluaran '.$result['expense_number'].' tersimpan dan jurnalnya dikunci.');
    }
}
