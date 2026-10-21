<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RitaseService
{
    /**
     * Records a vehicle ritase trip and fuel/driver costs:
     * Balanced Journal:
     * Debit: Beban Transportasi & Ritase (6201)
     * Credit: Kas Toko (1101)
     */
    public function recordRitase(
        int $vehicleId,
        string $driverName,
        string $destination,
        ?string $cargoSummary,
        float|string $fuelCost = 0,
        float|string $driverAllowance = 0,
        ?int $saleId = null
    ): array {
        if ($vehicleId <= 0) {
            throw new InvalidArgumentException("Invalid vehicle ID: {$vehicleId}");
        }
        if (trim($driverName) === '') {
            throw new InvalidArgumentException("Driver name cannot be empty");
        }
        if (trim($destination) === '') {
            throw new InvalidArgumentException("Destination cannot be empty");
        }
        if (!is_numeric($fuelCost) || (float) $fuelCost < 0) {
            throw new InvalidArgumentException("Fuel cost must be non-negative");
        }
        if (!is_numeric($driverAllowance) || (float) $driverAllowance < 0) {
            throw new InvalidArgumentException("Driver allowance must be non-negative");
        }
        if ($saleId !== null) {
            if ($saleId <= 0) {
                throw new InvalidArgumentException("Invalid sale ID: {$saleId}");
            }
            $saleExists = DB::table('sales')->where('id', $saleId)->exists();
            if (!$saleExists) {
                throw new InvalidArgumentException("Sale not found for ID: {$saleId}");
            }
        }

        $fuelCents = (int) round(((float) $fuelCost) * 100);
        $allowanceCents = (int) round(((float) $driverAllowance) * 100);
        $totalCostCents = $fuelCents + $allowanceCents;

        return DB::transaction(function () use ($vehicleId, $driverName, $destination, $cargoSummary, $fuelCents, $allowanceCents, $totalCostCents, $saleId) {
            $fuelStr = number_format($fuelCents / 100, 2, '.', '');
            $allowanceStr = number_format($allowanceCents / 100, 2, '.', '');
            $totalCostStr = number_format($totalCostCents / 100, 2, '.', '');

            $ritaseId = DB::table('ritases')->insertGetId([
                'ritase_number' => null,
                'vehicle_id' => $vehicleId,
                'sale_id' => $saleId,
                'driver_name' => $driverName,
                'ritase_date' => date('Y-m-d'),
                'destination' => $destination,
                'cargo_summary' => $cargoSummary,
                'fuel_cost' => $fuelStr,
                'driver_allowance' => $allowanceStr,
                'total_cost' => $totalCostStr,
                'status' => 'completed',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $ritaseNumber = sprintf('RIT-%s-%06d', date('Ymd'), $ritaseId);
            DB::table('ritases')->where('id', $ritaseId)->update(['ritase_number' => $ritaseNumber]);

            $journalId = null;
            if ($totalCostCents > 0) {
                $journalDesc = "Biaya Ritase {$ritaseNumber} Tujuan {$destination}";
                if ($saleId !== null) {
                    $sale = DB::table('sales')->where('id', $saleId)->first();
                    if ($sale && !empty($sale->invoice_number)) {
                        $journalDesc = "Biaya Ritase {$ritaseNumber} (Invoice {$sale->invoice_number}) Tujuan {$destination}";
                    }
                }

                $journalId = DB::table('journal_entries')->insertGetId([
                    'reference_type' => 'ritase',
                    'reference_id' => $ritaseId,
                    'description' => $journalDesc,
                    'transaction_date' => date('Y-m-d'),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                DB::table('ritases')->where('id', $ritaseId)->update(['journal_entry_id' => $journalId]);

                // Debit: Beban Transportasi & Ritase (6201)
                DB::table('journal_entry_lines')->insert([
                    'journal_entry_id' => $journalId,
                    'account_code' => '6201',
                    'account_name' => 'Beban Transportasi & Ritase',
                    'debit' => $totalCostStr,
                    'credit' => '0.00',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                // Credit: Kas Toko (1101)
                DB::table('journal_entry_lines')->insert([
                    'journal_entry_id' => $journalId,
                    'account_code' => '1101',
                    'account_name' => 'Kas Toko',
                    'debit' => '0.00',
                    'credit' => $totalCostStr,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            return [
                'ritase_id' => $ritaseId,
                'ritase_number' => $ritaseNumber,
                'sale_id' => $saleId,
                'total_cost' => $totalCostStr,
                'journal_entry_id' => $journalId,
            ];
        });
    }
}
