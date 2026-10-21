<?php

namespace App\Http\Controllers;

use App\Services\PosCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    public function index()
    {
        $products = DB::table('products')->orderBy('name')->get();
        $recentSales = DB::table('sales')->latest()->limit(5)->get();
        return view('pos.index', compact('products', 'recentSales'));
    }

    public function checkout(Request $request, PosCheckoutService $service)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,4})?$/', 'numeric', 'min:0.0001'],
            'payment_method' => 'required|in:cash,transfer,credit',
            'paid_amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/', 'numeric', 'min:0'],
            'customer_type' => 'nullable|string',
        ]);

        // Pass strict decimal string to PosCheckoutService; avoid binary float precision corruption
        $result = $service->checkout(
            $request->items,
            $request->payment_method,
            (string) $request->paid_amount,
            $request->customer_type ?? 'retail'
        );

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return redirect()->route('pos.receipt', ['id' => $result['sale_id']]);
    }

    public function receipt($id)
    {
        $sale = DB::table('sales')->where('id', $id)->first();
        if (!$sale) {
            abort(404, 'Sale not found');
        }

        $items = DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sale_items.sale_id', $id)
            ->select('products.name', 'products.unit', 'sale_items.quantity', 'sale_items.unit_price', 'sale_items.subtotal')
            ->get();

        return view('pos.receipt', compact('sale', 'items'));
    }
}