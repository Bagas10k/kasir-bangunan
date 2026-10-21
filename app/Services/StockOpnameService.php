<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use RuntimeException;
use InvalidArgumentException;

class StockOpnameService
{
    /**
     * Executes atomic Stock Opname:
     * - Pre-validates duplicate product IDs and quantities before starting transaction
     * - Uses lockForUpdate to prevent stale overwrite races
     * - Adjusts stock quantity directly to physical count with exact 4dp precision
     * - Calculates valuation differences using exact intermediate math and 4dp unit cost
     * - Records stock movement
     * - Only creates double-entry journal if variance exists (prevents orphan empty journal entries)
     */
    public function recordOpname(array $items, ?string $notes = null): array
    {
        if (empty($items)) {
            throw new InvalidArgumentException("Opname items cannot be empty");
        }

        // Validate items before transaction: check duplicate product IDs and quantity constraints
        $seenProductIds = [];
        $validatedItems = [];
        foreach ($items as $item) {
            if (!isset($item['product_id']) || !isset($item['physical_qty'])) {
                throw new InvalidArgumentException("Opname item must contain product_id and physical_qty");
            }
            $productId = (int) $item['product_id'];
            if ($productId <= 0) {
                throw new InvalidArgumentException("Invalid product ID: {$productId}");
            }
            if (isset($seenProductIds[$productId])) {
                throw new InvalidArgumentException("Duplicate product ID {$productId} in stock opname");
            }
            $seenProductIds[$productId] = true;

            if (!is_numeric($item['physical_qty'])) {
                throw new InvalidArgumentException("Physical quantity must be numeric for product ID {$productId}");
            }

            try {
                $qtyDecimal = BigDecimal::of((string) $item['physical_qty']);
            } catch (\Exception $e) {
                throw new InvalidArgumentException("Invalid physical quantity for product ID {$productId}: {$e->getMessage()}");
            }

            if ($qtyDecimal->isLessThan(0)) {
                throw new InvalidArgumentException("Physical quantity cannot be negative for product ID {$productId}");
            }

            if ($qtyDecimal->strippedOfTrailingZeros()->getScale() > 4) {
                throw new InvalidArgumentException("Physical quantity cannot exceed 4 decimal places for product ID {$productId}");
            }

            $validatedItems[] = [
                'product_id' => $productId,
                'physical_qty' => $qtyDecimal->toScale(4, RoundingMode::HalfUp),
            ];
        }

        return DB::transaction(function () use ($validatedItems, $notes) {
            // Sort items by product_id ascending to prevent deadlocks
            usort($validatedItems, fn($a, $b) => ((int) $a['product_id']) <=> ((int) $b['product_id']));

            $opnameId = DB::table('stock_opnames')->insertGetId([
                'opname_number' => null,
                'opname_date' => date('Y-m-d'),
                'status' => 'posted',
                'notes' => $notes,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $opnameNumber = sprintf('OPN-%s-%06d', date('Ymd'), $opnameId);
            DB::table('stock_opnames')->where('id', $opnameId)->update(['opname_number' => $opnameNumber]);

            $totalLossMoney = BigDecimal::zero();
            $totalGainMoney = BigDecimal::zero();
            $processedItems = [];

            foreach ($validatedItems as $item) {
                $productId = $item['product_id'];
                /** @var BigDecimal $physicalQty */
                $physicalQty = $item['physical_qty'];

                // Acquire pessimistic write lock on the product row
                $product = DB::table('products')->where('id', $productId)->lockForUpdate()->first();
                if (!$product) {
                    throw new RuntimeException("Product ID {$productId} not found");
                }

                $systemQty = BigDecimal::of((string) $product->stock_quantity)->toScale(4, RoundingMode::HalfUp);
                $diffQty = $physicalQty->minus($systemQty);

                $unitCost = BigDecimal::of((string) $product->cost_price)->toScale(4, RoundingMode::HalfUp);
                $valueDiffExact = $diffQty->multipliedBy($unitCost);
                $valueDiffMoney = $valueDiffExact->toScale(2, RoundingMode::HalfUp);

                // Update product stock atomically while holding the lock
                DB::table('products')->where('id', $productId)->update([
                    'stock_quantity' => (string) $physicalQty,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                DB::table('stock_opname_items')->insert([
                    'stock_opname_id' => $opnameId,
                    'product_id' => $productId,
                    'system_qty' => (string) $systemQty,
                    'physical_qty' => (string) $physicalQty,
                    'difference_qty' => (string) $diffQty,
                    'cost_price' => (string) $unitCost,
                    'value_difference' => (string) $valueDiffMoney,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                DB::table('stock_movements')->insert([
                    'product_id' => $productId,
                    'reference_type' => 'stock_opname',
                    'reference_id' => $opnameId,
                    'quantity_delta' => (string) $diffQty,
                    'balance_after' => (string) $physicalQty,
                    'notes' => "Stock Opname {$opnameNumber}",
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                if ($valueDiffMoney->isNegative()) {
                    $totalLossMoney = $totalLossMoney->plus($valueDiffMoney->abs());
                } elseif ($valueDiffMoney->isPositive()) {
                    $totalGainMoney = $totalGainMoney->plus($valueDiffMoney);
                }

                $processedItems[] = [
                    'product_id' => $productId,
                    'system_qty' => (string) $systemQty,
                    'physical_qty' => (string) $physicalQty,
                    'difference_qty' => (string) $diffQty,
                ];
            }

            // Journal Entry for Stock Variance ONLY if variance exists (prevent orphan empty entries)
            $journalId = null;
            if ($totalLossMoney->isPositive() || $totalGainMoney->isPositive()) {
                $journalId = DB::table('journal_entries')->insertGetId([
                    'reference_type' => 'stock_opname',
                    'reference_id' => $opnameId,
                    'description' => "Penyesuaian Stok Opname {$opnameNumber}",
                    'transaction_date' => date('Y-m-d'),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                // If stock loss: Debit Beban Selisih Stok (5201), Credit Persediaan (1301)
                if ($totalLossMoney->isPositive()) {
                    $lossStr = (string) $totalLossMoney->toScale(2, RoundingMode::HalfUp);
                    DB::table('journal_entry_lines')->insert([
                        'journal_entry_id' => $journalId,
                        'account_code' => '5201',
                        'account_name' => 'Beban Selisih Stok',
                        'debit' => $lossStr,
                        'credit' => '0.00',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    DB::table('journal_entry_lines')->insert([
                        'journal_entry_id' => $journalId,
                        'account_code' => '1301',
                        'account_name' => 'Persediaan Material',
                        'debit' => '0.00',
                        'credit' => $lossStr,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                // If stock gain: Debit Persediaan (1301), Credit Pendapatan Selisih Stok (4201)
                if ($totalGainMoney->isPositive()) {
                    $gainStr = (string) $totalGainMoney->toScale(2, RoundingMode::HalfUp);
                    DB::table('journal_entry_lines')->insert([
                        'journal_entry_id' => $journalId,
                        'account_code' => '1301',
                        'account_name' => 'Persediaan Material',
                        'debit' => $gainStr,
                        'credit' => '0.00',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    DB::table('journal_entry_lines')->insert([
                        'journal_entry_id' => $journalId,
                        'account_code' => '4201',
                        'account_name' => 'Pendapatan Selisih Stok',
                        'debit' => '0.00',
                        'credit' => $gainStr,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            return [
                'opname_id' => $opnameId,
                'opname_number' => $opnameNumber,
                'journal_entry_id' => $journalId,
                'items' => $processedItems,
            ];
        });
    }
}
