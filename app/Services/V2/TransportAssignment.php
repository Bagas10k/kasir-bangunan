<?php

namespace App\Services\V2;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransportAssignment
{
    public function __construct(private MasterRelations $relations) {}

    /** Call within the operational transaction, before locking driver/vehicle masters. */
    public function delivery(int $deliveryId): object
    {
        $delivery = DB::table('v2_deliveries')->where('id', $deliveryId)->lockForUpdate()->first();
        if ($delivery === null) {
            $this->invalid('Pengiriman tidak ditemukan.');
        }

        return $delivery;
    }

    /** Compare persisted identities before checking mutable master state on replay. */
    public function assertMatches(object $delivery, int $driverId, int $vehicleId): void
    {
        if ($delivery->driver_id === null && $delivery->driver_name !== null) {
            $this->invalid('Pengiriman ini hanya punya nama sopir historis tanpa master sopir. Rekonsiliasi manual diperlukan; identitas tidak ditebak dari nama.');
        }
        if ((int) $delivery->driver_id !== $driverId || (int) $delivery->vehicle_id !== $vehicleId) {
            $this->invalid('Sopir dan kendaraan harus sesuai penugasan pengiriman.');
        }
    }

    /** Validate active masters and reserve an unassigned prepared delivery atomically. */
    public function resolve(int $driverId, int $vehicleId, ?object $delivery = null): object
    {
        $unassigned = $delivery !== null
            && $delivery->status === 'prepared'
            && $delivery->driver_id === null
            && $delivery->vehicle_id === null
            && $delivery->driver_name === null;

        if ($delivery !== null && ! $unassigned) {
            $this->assertMatches($delivery, $driverId, $vehicleId);
        }

        $driver = $this->relations->driver($driverId);
        $vehicle = DB::table('vehicles')->where('id', $vehicleId)->lockForUpdate()->first();
        if ($vehicle === null || $vehicle->status !== 'active') {
            $this->invalid('Kendaraan tidak ditemukan atau tidak aktif.');
        }

        if ($unassigned) {
            DB::table('v2_deliveries')->where('id', $delivery->id)->update([
                'driver_id' => $driverId,
                'driver_name' => $driver->name,
                'vehicle_id' => $vehicleId,
                'updated_at' => now(),
            ]);
        }

        return $driver;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['delivery' => $message]);
    }
}
