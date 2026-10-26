<?php

namespace App\Http\Controllers;

use App\Services\InventoryReceiptService;
use App\Services\RitaseService;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryReceiptController extends Controller
{
    public function index(Request $request)
    {
        $products = DB::table('products')->where('is_active', true)->orderBy('name')->get();

        $search = trim((string) $request->input('search', ''));
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50], true) ? (int) $request->input('per_page') : 15;
        $sort = (string) $request->input('sort', 'receipt_date');
        $dir = $request->input('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortable = [
            'receipt_date' => 'receipt_date',
            'receipt_number' => 'receipt_number',
            'supplier_name' => 'supplier_name',
            'product_name' => 'product_name',
            'receipt_type' => 'receipt_type',
            'quantity' => 'quantity',
            'unit_cost' => 'unit_cost',
            'total_amount' => 'total_amount',
        ];
        $sortCol = $sortable[$sort] ?? 'id';

        $receipts = DB::table('inventory_receipts')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('receipt_number', 'like', "%{$search}%")
                      ->orWhere('supplier_name', 'like', "%{$search}%")
                      ->orWhere('product_name', 'like', "%{$search}%")
                      ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortCol, $dir)
            ->paginate($perPage)
            ->appends($request->query());

        $fleetReady = \Illuminate\Support\Facades\Schema::hasColumn('ritases', 'inventory_receipt_id');
        $vehicles = $fleetReady
            ? DB::table('vehicles')->where('status', 'active')->orderBy('license_plate')->get(['id', 'license_plate', 'vehicle_type'])
            : collect();

        $creditAccounts = [
            '3101' => '3101 - Modal Awal (Stok Awal)',
            '2101' => '2101 - Hutang Usaha (Pemasok)',
            '1101' => '1101 - Kas Toko (Tunai)',
            '1102' => '1102 - Bank (Transfer)',
        ];

        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();
        $periodLabel = now()->translatedFormat('F Y');

        $receiptSummary = [
            'total_receipts' => 0,
            'purchase_count' => 0,
            'opening_count' => 0,
            'total_amount' => BigDecimal::zero(),
            'period_label' => 'Bulan Ini (' . $periodLabel . ')',
        ];

        DB::table('inventory_receipts')
            ->whereBetween('receipt_date', [$startOfMonth, $endOfMonth])
            ->select(['id', 'receipt_type', 'total_amount'])
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$receiptSummary) {
                foreach ($rows as $row) {
                    $receiptSummary['total_receipts']++;
                    $type = strtolower((string) ($row->receipt_type ?? ''));
                    if (in_array($type, ['purchase', 'restock'], true)) {
                        $receiptSummary['purchase_count']++;
                    } elseif ($type === 'opening') {
                        $receiptSummary['opening_count']++;
                    }
                    $amount = BigDecimal::of((string) ($row->total_amount ?? '0'));
                    $receiptSummary['total_amount'] = $receiptSummary['total_amount']->plus($amount);
                }
            });

        return view('inventory.receipts', compact('products', 'receipts', 'creditAccounts', 'vehicles', 'fleetReady', 'search', 'perPage', 'sort', 'dir', 'receiptSummary'));
    }

    public function store(Request $request, InventoryReceiptService $receiptService, RitaseService $ritaseService)
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
            'use_armada' => 'nullable|in:0,1',
            'vehicle_id' => 'required_if:use_armada,1|nullable|integer|exists:vehicles,id',
            'ritase_amount' => ['required_if:use_armada,1', 'nullable', 'regex:/^\d{1,17}(\.\d{1,2})?$/', 'numeric', 'gt:0'],
        ]);

        if ((string) $request->input('use_armada') !== '1' && ($request->filled('vehicle_id') || $request->filled('ritase_amount'))) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Pilih penggunaan armada untuk mengisi biaya ritase.'], 422);
            }
            return redirect()->back()->withInput()->withErrors(['use_armada' => 'Pilih penggunaan armada untuk mengisi biaya ritase.']);
        }

        if ((string) $request->input('use_armada') === '1' && !\Illuminate\Support\Facades\Schema::hasColumn('ritases', 'inventory_receipt_id')) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Pilihan armada belum tersedia sampai pembaruan database selesai.'], 422);
            }
            return redirect()->back()->withInput()->withErrors(['use_armada' => 'Pilihan armada belum tersedia sampai pembaruan database selesai.']);
        }

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

        try {
            $result = DB::transaction(function () use ($receiptService, $ritaseService, $items, $receiptType, $creditAccount, $notes, $supplierName, $receiptDate, $request) {
                $receipt = $receiptService->receive(
                    items: $items,
                    receiptType: $receiptType,
                    creditAccount: $creditAccount,
                    notes: $notes,
                    supplierName: $supplierName,
                    receiptDate: $receiptDate
                );
                if ((string) $request->input('use_armada') === '1') {
                    $receipt['ritase'] = $ritaseService->recordPendingTrip(
                        (int) $request->vehicle_id,
                        (string) $request->ritase_amount,
                        null,
                        (int) $receipt['receipt_id']
                    );
                }
                return $receipt;
            });
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return redirect()->back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'receipt' => $result], 201);
        }

        return redirect()->route('inventory.receipts')->with('success', "Penerimaan barang {$result['receipt_number']} berhasil dibukukan.");
    }
}
