<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index()
    {
        $products = DB::table('products')->orderBy('name')->get();
        if (view()->exists('products.index')) {
            return view('products.index', compact('products'));
        }
        return response()->json($products);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:50|unique:products,sku',
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:20',
            'cost_price' => ['required', 'regex:/^\d+(\.\d{1,4})?$/', 'numeric', 'min:0'],
            'selling_price' => ['required', 'regex:/^\d+(\.\d{1,2})?$/', 'numeric', 'min:0'],
        ]);

        $rawCost = (string) $validated['cost_price'];
        if (!str_contains($rawCost, '.')) {
            $costPrice = $rawCost . '.0000';
        } else {
            [$intPart, $fracPart] = explode('.', $rawCost, 2);
            $costPrice = $intPart . '.' . str_pad($fracPart, 4, '0');
        }

        // Opening inventory without offset journal unbalances equity/balance sheet.
        // Safer initial stock is strictly 0.0000 until approved stock receipt/opname.
        $id = DB::table('products')->insertGetId([
            'sku' => trim($validated['sku']),
            'name' => trim($validated['name']),
            'unit' => trim($validated['unit']),
            'cost_price' => $costPrice,
            'selling_price' => (string) number_format((float) $validated['selling_price'], 2, '.', ''),
            'stock_quantity' => 0.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'id' => $id], 201);
        }

        return redirect()->route('products.index')->with('success', 'Produk berhasil didaftarkan dengan stok awal 0.');
    }
}
