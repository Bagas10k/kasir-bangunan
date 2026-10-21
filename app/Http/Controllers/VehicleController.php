<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleController extends Controller
{
    public function index()
    {
        $vehicles = DB::table('vehicles')->orderBy('license_plate')->get();
        return view('vehicles.index', compact('vehicles'));
    }

    public function store(Request $request)
    {
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
}
