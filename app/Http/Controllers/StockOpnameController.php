<?php

namespace App\Http\Controllers;

use App\Services\StockOpnameService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockOpnameController extends Controller
{
    public function index()
    {
        $products = DB::table('products')->orderBy('name')->get();
        $recentOpnames = DB::table('stock_opnames')->latest()->limit(5)->get();
        return view('stock_opname.index', compact('products', 'recentOpnames'));
    }

    public function store(Request $request, StockOpnameService $service)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.physical_qty' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        $result = $service->recordOpname($request->items, $request->notes);

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return redirect()->route('opname.index')->with('success', 'Stock opname recorded');
    }
}
