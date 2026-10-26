<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RitaseService
{
    public function recordPendingTrip(int $vehicleId, string $amount, ?int $saleId = null, ?int $receiptId = null): array
    {
        if (($saleId === null) === ($receiptId === null)) {
            throw new InvalidArgumentException('Ritase harus terkait tepat satu transaksi.');
        }
        if (!preg_match('/^\d{1,17}(\.\d{1,2})?$/', $amount) || BigDecimal::of($amount)->isLessThanOrEqualTo(0)) {
            throw new InvalidArgumentException('Uang ritase harus positif dengan maksimal dua desimal.');
        }
        $money = (string) BigDecimal::of($amount)->toScale(2);

        return DB::transaction(function () use ($vehicleId, $money, $saleId, $receiptId) {
            $vehicle = DB::table('vehicles')->where('id', $vehicleId)->lockForUpdate()->first();
            if (!$vehicle || $vehicle->status !== 'active') {
                throw new InvalidArgumentException('Armada tidak aktif atau tidak ditemukan.');
            }
            if ($saleId !== null && !DB::table('sales')->where('id', $saleId)->exists()) {
                throw new InvalidArgumentException('Nota penjualan tidak ditemukan.');
            }
            if ($receiptId !== null && !DB::table('inventory_receipts')->where('id', $receiptId)->exists()) {
                throw new InvalidArgumentException('Penerimaan barang tidak ditemukan.');
            }
            (new AccountBalanceService())->assertSufficient('1101', $money);
            $stamp = now();
            $ritaseId = DB::table('ritases')->insertGetId([
                'ritase_number' => null, 'vehicle_id' => $vehicleId,
                'sale_id' => $saleId, 'inventory_receipt_id' => $receiptId,
                'driver_name' => null, 'destination' => null, 'cargo_summary' => null,
                'ritase_date' => $stamp->toDateString(), 'fuel_cost' => '0.00',
                'driver_allowance' => '0.00', 'total_cost' => $money,
                'status' => 'pending_details', 'created_at' => $stamp, 'updated_at' => $stamp,
            ]);
            $number = sprintf('RIT-%s-%06d', $stamp->format('Ymd'), $ritaseId);
            $journalId = DB::table('journal_entries')->insertGetId([
                'reference_type' => 'ritase', 'reference_id' => $ritaseId,
                'description' => "Biaya Ritase {$number}", 'transaction_date' => $stamp->toDateString(),
                'created_at' => $stamp, 'updated_at' => $stamp,
            ]);
            DB::table('ritases')->where('id', $ritaseId)->update(['ritase_number' => $number, 'journal_entry_id' => $journalId]);
            DB::table('journal_entry_lines')->insert([
                ['journal_entry_id' => $journalId, 'account_code' => '6201', 'account_name' => 'Beban Transportasi & Ritase', 'debit' => $money, 'credit' => '0.00', 'created_at' => $stamp, 'updated_at' => $stamp],
                ['journal_entry_id' => $journalId, 'account_code' => '1101', 'account_name' => 'Kas Toko', 'debit' => '0.00', 'credit' => $money, 'created_at' => $stamp, 'updated_at' => $stamp],
            ]);
            return ['ritase_id' => $ritaseId, 'ritase_number' => $number, 'total_cost' => $money, 'journal_entry_id' => $journalId];
        });
    }

    public function completePendingTrip(int $ritaseId, string $driverName, string $destination, ?string $cargoSummary = null): void
    {
        $driverName = trim($driverName);
        $destination = trim($destination);
        if ($driverName === '' || $destination === '' || mb_strlen($driverName) > 100 || mb_strlen($destination) > 255) {
            throw new InvalidArgumentException('Sopir dan tujuan wajib diisi.');
        }
        DB::transaction(function () use ($ritaseId, $driverName, $destination, $cargoSummary) {
            $trip = DB::table('ritases')->where('id', $ritaseId)->lockForUpdate()->first();
            if (!$trip || $trip->status !== 'pending_details') {
                throw new InvalidArgumentException('Ritase menunggu detail tidak ditemukan.');
            }
            DB::table('ritases')->where('id', $ritaseId)->update([
                'driver_name' => $driverName, 'destination' => $destination,
                'cargo_summary' => $cargoSummary, 'status' => 'completed', 'updated_at' => now(),
            ]);
        });
    }

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
        string $fuelCost = '0',
        string $driverAllowance = '0',
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
        $fuelStr = $this->normalizeMoney($fuelCost, 'Biaya bahan bakar');
        $allowanceStr = $this->normalizeMoney($driverAllowance, 'Uang jalan sopir');
        if ($saleId !== null) {
            if ($saleId <= 0) {
                throw new InvalidArgumentException("Invalid sale ID: {$saleId}");
            }
            $saleExists = DB::table('sales')->where('id', $saleId)->exists();
            if (!$saleExists) {
                throw new InvalidArgumentException("Sale not found for ID: {$saleId}");
            }
        }

        $totalCost = BigDecimal::of($fuelStr)->plus($allowanceStr)->toScale(2);
        if ($totalCost->isGreaterThan('99999999999999999.99')) {
            throw new InvalidArgumentException('Total biaya ritase di luar batas DECIMAL(19,2).');
        }
        $totalCostStr = (string) $totalCost;

        return DB::transaction(function () use ($vehicleId, $driverName, $destination, $cargoSummary, $fuelStr, $allowanceStr, $totalCost, $totalCostStr, $saleId) {
            $vehicle = DB::table('vehicles')->where('id', $vehicleId)->lockForUpdate()->first();
            if (!$vehicle || $vehicle->status !== 'active') {
                throw new InvalidArgumentException('Armada tidak aktif atau tidak ditemukan.');
            }
            if ($totalCost->isGreaterThan(0)) {
                (new AccountBalanceService())->assertSufficient('1101', $totalCostStr);
            }

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
            if ($totalCost->isGreaterThan(0)) {
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

    private function normalizeMoney(string $value, string $label): string
    {
        $value = trim($value);
        if (!preg_match('/^\d{1,17}(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException("{$label} harus berupa nominal non-negatif dengan maksimal dua desimal.");
        }

        return (string) BigDecimal::of($value)->toScale(2);
    }
}
