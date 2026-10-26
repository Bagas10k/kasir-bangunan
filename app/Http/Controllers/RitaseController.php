<?php

namespace App\Http\Controllers;

use App\Services\RitaseService;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RitaseController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(['completed', 'pending_details'])],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', Rule::in(['10', '15', '25', '50'])],
            'sort' => ['nullable', Rule::in(['ritase_number', 'ritase_date', 'driver_name', 'destination', 'total_cost'])],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $vehicles = DB::table('vehicles')->orderBy('license_plate')->get();
        $activeVehicles = $vehicles->where('status', 'active');

        $sales = DB::table('sales')->select('id', 'invoice_number', 'total_amount')->latest('id')->limit(50)->get();

        $search = trim((string) $request->input('search', ''));
        $perPage = in_array((int) $request->input('per_page'), [10, 15, 25, 50], true) ? (int) $request->input('per_page') : 15;
        $sort = (string) $request->input('sort', 'ritase_date');
        $dir = $request->input('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortable = [
            'ritase_number' => 'ritases.ritase_number',
            'ritase_date' => 'ritases.ritase_date',
            'driver_name' => 'ritases.driver_name',
            'destination' => 'ritases.destination',
            'total_cost' => 'ritases.total_cost',
        ];
        $sortCol = $sortable[$sort] ?? 'ritases.id';

        // The shared public code may precede the additive migration; keep legacy reads available.
        $hasReceiptLink = \Illuminate\Support\Facades\Schema::hasColumn('ritases', 'inventory_receipt_id');
        $query = DB::table('ritases')
            ->leftJoin('sales', 'ritases.sale_id', '=', 'sales.id')
            ->leftJoin('vehicles', 'ritases.vehicle_id', '=', 'vehicles.id')
            ->when($hasReceiptLink, fn ($q) => $q->leftJoin('inventory_receipts', 'ritases.inventory_receipt_id', '=', 'inventory_receipts.id'))
            ->when(!empty($filters['vehicle_id']), fn ($q) => $q->where('ritases.vehicle_id', $filters['vehicle_id']))
            ->when(!empty($filters['start_date']), fn ($q) => $q->whereDate('ritases.ritase_date', '>=', $filters['start_date']))
            ->when(!empty($filters['end_date']), fn ($q) => $q->whereDate('ritases.ritase_date', '<=', $filters['end_date']))
            ->when(!empty($filters['status']), fn ($q) => $q->where('ritases.status', $filters['status']))
            ->when($search !== '', function ($q) use ($search, $hasReceiptLink) {
                $q->where(function ($w) use ($search, $hasReceiptLink) {
                    $w->where('ritases.ritase_number', 'like', "%{$search}%")
                      ->orWhere('ritases.driver_name', 'like', "%{$search}%")
                      ->orWhere('ritases.destination', 'like', "%{$search}%")
                      ->orWhere('ritases.cargo_summary', 'like', "%{$search}%")
                      ->orWhere('vehicles.license_plate', 'like', "%{$search}%")
                      ->orWhere('sales.invoice_number', 'like', "%{$search}%");
                    if ($hasReceiptLink) {
                        $w->orWhere('inventory_receipts.receipt_number', 'like', "%{$search}%");
                    }
                });
            });

        // Aggregate the same filtered source before pagination, never a second journal total.
        $perVehicle = [];
        $grandTotal = BigDecimal::of('0.00');
        $tripCount = 0;
        // Sum original DECIMAL strings; SQLite's SUM coerces them to floating point.
        foreach ((clone $query)->select('ritases.vehicle_id', 'vehicles.license_plate', 'ritases.total_cost')->cursor() as $trip) {
            $id = (int) $trip->vehicle_id;
            if (!isset($perVehicle[$id])) {
                $perVehicle[$id] = (object) [
                    'vehicle_id' => $id, 'license_plate' => $trip->license_plate,
                    'trip_count' => 0, 'total_cost' => '0.00',
                ];
            }
            $amount = BigDecimal::of((string) $trip->total_cost);
            $perVehicle[$id]->trip_count++;
            $perVehicle[$id]->total_cost = (string) BigDecimal::of($perVehicle[$id]->total_cost)->plus($amount)->toScale(2);
            $grandTotal = $grandTotal->plus($amount);
            $tripCount++;
        }
        $vehicleTotals = collect(array_values($perVehicle))->sortBy('license_plate')->values();
        $summary = ['count' => $tripCount, 'total' => (string) $grandTotal->toScale(2)];
        $ritases = (clone $query)
            ->select('ritases.*', 'sales.invoice_number as linked_invoice_number', 'vehicles.license_plate as vehicle_plate')
            ->when($hasReceiptLink, fn ($q) => $q->addSelect('inventory_receipts.receipt_number as linked_receipt_number'))
            ->orderBy($sortCol, $dir)->orderBy('ritases.id', 'desc')
            ->paginate($perPage)->appends($request->query());

        return view('ritase.index', compact('vehicles', 'activeVehicles', 'sales', 'ritases', 'search', 'perPage', 'sort', 'dir', 'filters', 'vehicleTotals', 'summary'));
    }

    public function completeDetails(Request $request, RitaseService $service, int $id)
    {
        $validated = $request->validate([
            'driver_name' => 'required|string|max:100',
            'destination' => 'required|string|max:255',
            'cargo_summary' => 'nullable|string|max:255',
        ]);

        try {
            $service->completePendingTrip(
                $id,
                $validated['driver_name'],
                $validated['destination'],
                $validated['cargo_summary'] ?? null
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return redirect()->back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return redirect()->route('ritase.index')->with('success', 'Detail ritase telah dilengkapi.');
    }

    public function store(Request $request, RitaseService $service)
    {
        $request->validate([
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')->where('status', 'active')],
            'sale_id' => 'nullable|integer|exists:sales,id',
            'driver_name' => 'required|string|max:100',
            'destination' => 'required|string|max:255',
            'cargo_summary' => 'nullable|string|max:255',
            'fuel_cost' => ['nullable', 'regex:/^\d{1,17}(?:\.\d{1,2})?$/'],
            'driver_allowance' => ['nullable', 'regex:/^\d{1,17}(?:\.\d{1,2})?$/'],
        ]);

        $saleId = $request->filled('sale_id') ? (int) $request->sale_id : null;

        try {
            $result = $service->recordRitase(
                (int) $request->vehicle_id,
                $request->driver_name,
                $request->destination,
                $request->cargo_summary,
                (string) ($request->fuel_cost ?? '0'),
                (string) ($request->driver_allowance ?? '0'),
                $saleId
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return redirect()->back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return redirect()->route('ritase.index')->with('success', 'Ritase recorded');
    }
}
