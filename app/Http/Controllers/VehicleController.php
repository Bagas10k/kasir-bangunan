<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:active,maintenance,inactive',
            'per_page' => 'nullable|integer|in:10,25,50',
        ]);

        $query = DB::table('vehicles')
            ->leftJoin('ritases', 'vehicles.id', '=', 'ritases.vehicle_id')
            ->select(
                'vehicles.id',
                'vehicles.license_plate',
                'vehicles.vehicle_type',
                'vehicles.status',
                'vehicles.created_at',
                'vehicles.updated_at',
                DB::raw('COUNT(ritases.id) as ritase_count')
            )
            ->groupBy(
                'vehicles.id',
                'vehicles.license_plate',
                'vehicles.vehicle_type',
                'vehicles.status',
                'vehicles.created_at',
                'vehicles.updated_at'
            )
            ->orderBy('vehicles.license_plate');

        if (!empty($validated['search'])) {
            $searchTerm = '%' . strtolower(trim($validated['search'])) . '%';
            $query->whereRaw('LOWER(vehicles.license_plate) LIKE ?', [$searchTerm]);
        }

        if (!empty($validated['status'])) {
            $query->where('vehicles.status', $validated['status']);
        }

        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 10);
        $vehicles = $query->paginate($perPage)->withQueryString();
        $summary = [
            'total' => DB::table('vehicles')->count(),
            'active' => DB::table('vehicles')->where('status', 'active')->count(),
            'maintenance' => DB::table('vehicles')->where('status', 'maintenance')->count(),
            'inactive' => DB::table('vehicles')->where('status', 'inactive')->count(),
        ];

        if ($request->wantsJson()) {
            return response()->json($vehicles);
        }

        return view('vehicles.index', compact('vehicles', 'validated', 'search', 'perPage', 'summary'));
    }

    public function store(Request $request)
    {
        $rawPlate = $request->input('license_plate');
        if (is_string($rawPlate)) {
            $request->merge(['license_plate' => strtoupper(trim($rawPlate))]);
        }

        $validated = $request->validate([
            'license_plate' => 'required|string|max:20|unique:vehicles,license_plate',
            'vehicle_type' => 'required|string|in:truck,pickup,lainnya',
            'status' => 'nullable|string|in:active,maintenance,inactive',
        ]);

        $id = DB::table('vehicles')->insertGetId([
            'license_plate' => strtoupper(trim($validated['license_plate'])),
            'vehicle_type' => $validated['vehicle_type'],
            'status' => $validated['status'] ?? 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'id' => $id], 201);
        }

        return redirect()->route('vehicles.index')->with('success', 'Kendaraan operasional berhasil didaftarkan.');
    }

    public function update(Request $request, $id)
    {
        $vehicle = DB::table('vehicles')->where('id', $id)->first();
        if (! $vehicle) {
            abort(404);
        }

        $rawPlate = $request->input('license_plate');
        if (is_string($rawPlate)) {
            $request->merge(['license_plate' => strtoupper(trim($rawPlate))]);
        }

        $validated = $request->validate([
            'license_plate' => 'required|string|max:20|unique:vehicles,license_plate,' . $id,
            'vehicle_type' => 'required|string|in:truck,pickup,lainnya',
            'status' => 'required|string|in:active,maintenance,inactive',
        ]);

        DB::table('vehicles')->where('id', $id)->update([
            'license_plate' => strtoupper(trim($validated['license_plate'])),
            'vehicle_type' => $validated['vehicle_type'],
            'status' => $validated['status'],
            'updated_at' => now(),
        ]);

        $updatedVehicle = DB::table('vehicles')->where('id', $id)->first();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Data kendaraan berhasil diperbarui.',
                'data' => $updatedVehicle,
            ]);
        }

        return redirect()->route('vehicles.index')->with('success', 'Data kendaraan berhasil diperbarui.');
    }

    public function destroy(Request $request, $id)
    {
        $vehicle = DB::table('vehicles')->where('id', $id)->first();
        if (! $vehicle) {
            abort(404);
        }

        $hasRitase = DB::table('ritases')->where('vehicle_id', $id)->exists();
        if ($hasRitase) {
            $errorMessage = 'Kendaraan memiliki riwayat ritase dan tidak dapat dihapus. Silakan nonaktifkan status armada menjadi inactive.';
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 422);
            }

            return redirect()->route('vehicles.index')->with('error', $errorMessage);
        }

        DB::table('vehicles')->where('id', $id)->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Kendaraan berhasil dihapus.',
            ]);
        }

        return redirect()->route('vehicles.index')->with('success', 'Kendaraan berhasil dihapus.');
    }
}
