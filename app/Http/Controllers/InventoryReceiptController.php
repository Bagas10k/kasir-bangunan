<?php

namespace App\Http\Controllers;

use App\Services\InventoryReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryReceiptController extends Controller
{
    public function index()
    {
        $products = DB::table('products')->orderBy('name')->get();
        $receipts = DB::table('inventory_receipts')
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get();

        $creditAccounts = [
            '3101' => '3101 - Modal Awal (Stok Awal)',
            '2101' => '2101 - Hutang Usaha (Pemasok)',
            '1101' => '1101 - Kas Toko (Tunai)',
            '1102' => '1102 - Bank (Transfer)',
        ];

        return view('inventory.receipts', compact('products', 'receipts', 'creditAccounts'));
    }

    public function store(Request $request, InventoryReceiptService $receiptService)
    {
        $validated = $request->validate([
            'receipt_type' => 'required|string|in:opening,purchase,restock',
            'credit_account' => 'required|string|in:3101,2101,1101,1102',
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|numeric|gt:0',
            'unit_cost' => 'required|numeric|min:0',
            'supplier_name' => 'nullable|string|max:255',
            'receipt_date' => 'nullable|date',
            'notes' => 'nullable|string|max:255',
        ]);

        $items = [
            [
                'product_id' => (int) $validated['product_id'],
                'quantity' => (string) $validated['quantity'],
                'unit_cost' => (string) $validated['unit_cost'],
            ],
        ];

        $receiptType = (string) $validated['receipt_type'];
        $creditAccount = (string) $validated['credit_account'];
        $notes = !empty($validated['notes']) ? (string) $validated['notes'] : null;
        $supplierName = !empty($validated['supplier_name']) ? (string) $validated['supplier_name'] : null;
        $receiptDate = !empty($validated['receipt_date']) ? (string) $validated['receipt_date'] : null;

        $result = $receiptService->receive(
            items: $items,
            receiptType: $receiptType,
            creditAccount: $creditAccount,
            notes: $notes,
            supplierName: $supplierName,
            receiptDate: $receiptDate
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'receipt' => $result], 201);
        }

        return redirect()->route('inventory.receipts')->with('success', "Penerimaan barang {$result['receipt_number']} berhasil dibukukan.");
    }
}
