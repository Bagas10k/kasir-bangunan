<?php

namespace App\Http\Controllers;

use App\Services\RitaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RitaseController extends Controller
{
    public function index()
    {
        $vehicles = DB::table('vehicles')->get();
        $sales = DB::table('sales')->select('id', 'invoice_number', 'total_amount')->latest('id')->limit(50)->get();
        $ritases = DB::table('ritases')
            ->leftJoin('sales', 'ritases.sale_id', '=', 'sales.id')
            ->select('ritases.*', 'sales.invoice_number as linked_invoice_number')
            ->latest('ritases.id')
            ->limit(20)
            ->get();

        return view('ritase.index', compact('vehicles', 'sales', 'ritases'));
    }

    public function store(Request $request, RitaseService $service)
    {
        $request->validate([
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'sale_id' => 'nullable|integer|exists:sales,id',
            'driver_name' => 'required|string|max:100',
            'destination' => 'required|string|max:255',
            'cargo_summary' => 'nullable|string|max:255',
            'fuel_cost' => 'nullable|numeric|min:0',
            'driver_allowance' => 'nullable|numeric|min:0',
        ]);

        $saleId = $request->filled('sale_id') ? (int) $request->sale_id : null;

        $result = $service->recordRitase(
            (int) $request->vehicle_id,
            $request->driver_name,
            $request->destination,
            $request->cargo_summary,
            (float) ($request->fuel_cost ?? 0),
            (float) ($request->driver_allowance ?? 0),
            $saleId
        );

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return redirect()->route('ritase.index')->with('success', 'Ritase recorded');
    }
}
