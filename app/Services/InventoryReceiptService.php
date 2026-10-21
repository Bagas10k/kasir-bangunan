<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class InventoryReceiptService
{
    /**
     * Allowed credit accounts per receipt type:
     * - opening: ONLY 3101 (Modal Awal)
     * - purchase / restock: 2101 (Hutang Usaha), 1101 (Kas Toko), 1102 (Bank)
     */
    protected const ALLOWED_CREDIT_ACCOUNTS = [
        'opening' => ['3101'],
        'purchase' => ['2101', '1101', '1102'],
    ];

    protected const ACCOUNT_NAMES = [
        '3101' => 'Modal Awal',
        '2101' => 'Hutang Usaha',
        '1101' => 'Kas Toko',
        '1102' => 'Bank',
    ];

    /**
     * Executes atomic inventory receipt (Opening Stock or Material Purchase/Restock):
     * - Requires positive quantity (precision 4dp) and positive unit cost (precision 4dp)
     * - Uses Brick\Math\BigDecimal for exact intermediate calculations (no float drift)
     * - Opening stock is allowed ONLY when prior stock is zero
     * - Atomic stock increment using deterministic row locking (lockForUpdate)
     * - Computes moving weighted average unit cost when existing stock > 0
     * - Inserts stock movement log with positive quantity delta
     * - Creates balanced double-entry journal entry:
     *     Debit: 1301 (Persediaan Material)
     *     Credit: 3101 (Modal Awal) for opening stock OR 2101/1101/1102 for purchase
     */
    public function receive(
        array $items,
        string $receiptType = 'opening',
        ?string $creditAccount = null,
        ?string $notes = null,
        ?string $supplierName = null,
        ?string $receiptDate = null
    ): array {
        if (empty($items)) {
            throw new InvalidArgumentException("Receipt items cannot be empty");
        }

        if ($receiptType === 'restock') {
            $receiptType = 'purchase';
        }

        if (!isset(self::ALLOWED_CREDIT_ACCOUNTS[$receiptType])) {
            throw new InvalidArgumentException("Invalid receipt type: {$receiptType}");
        }

        if ($creditAccount === null) {
            $creditAccount = ($receiptType === 'opening') ? '3101' : '2101';
        }

        if (!in_array($creditAccount, self::ALLOWED_CREDIT_ACCOUNTS[$receiptType], true)) {
            $allowed = implode(', ', self::ALLOWED_CREDIT_ACCOUNTS[$receiptType]);
            throw new InvalidArgumentException(
                "Invalid credit account [{$creditAccount}] for receipt type [{$receiptType}]. Allowed: {$allowed}"
            );
        }

        $creditAccountName = self::ACCOUNT_NAMES[$creditAccount] ?? 'Modal Awal / Hutang Usaha';
        $receiptDate = $receiptDate ?: date('Y-m-d');

        // Validate items format, positivity, and precision before opening transaction
        $parsedItems = [];
        foreach ($items as $idx => $item) {
            if (!is_array($item) || !isset($item['product_id'])) {
                throw new InvalidArgumentException("Each item must specify product_id (index {$idx})");
            }
            $productId = (int) $item['product_id'];
            if ($productId <= 0) {
                throw new InvalidArgumentException("Invalid product ID: {$productId}");
            }
            if (!isset($item['quantity']) || !is_numeric($item['quantity'])) {
                throw new InvalidArgumentException("Quantity must be numeric for product ID {$productId}");
            }
            
            try {
                $rawQuantity = BigDecimal::of((string) $item['quantity']);
                if ($rawQuantity->strippedOfTrailingZeros()->getScale() > 4) {
                    throw new InvalidArgumentException("Quantity precision exceeds 4 decimal places for product ID {$productId}");
                }
                $qtyDecimal = $rawQuantity->toScale(4, RoundingMode::HalfUp);
            } catch (InvalidArgumentException $e) {
                throw $e;
            } catch (\Exception $e) {
                throw new InvalidArgumentException("Invalid quantity value for product ID {$productId}: {$e->getMessage()}");
            }

            if ($qtyDecimal->isLessThanOrEqualTo(0)) {
                throw new InvalidArgumentException("Quantity must be greater than zero for product ID {$productId}");
            }

            if (!isset($item['unit_cost']) || !is_numeric($item['unit_cost'])) {
                throw new InvalidArgumentException("Unit cost must be numeric for product ID {$productId}");
            }

            try {
                $rawUnitCost = BigDecimal::of((string) $item['unit_cost']);
                if ($rawUnitCost->strippedOfTrailingZeros()->getScale() > 4) {
                    throw new InvalidArgumentException("Unit cost precision exceeds 4 decimal places for product ID {$productId}");
                }
                $unitCostDecimal = $rawUnitCost->toScale(4, RoundingMode::HalfUp);
            } catch (InvalidArgumentException $e) {
                throw $e;
            } catch (\Exception $e) {
                throw new InvalidArgumentException("Invalid unit cost value for product ID {$productId}: {$e->getMessage()}");
            }

            if ($unitCostDecimal->isLessThanOrEqualTo(0)) {
                throw new InvalidArgumentException("Unit cost must be greater than zero for product ID {$productId}");
            }

            $parsedItems[] = [
                'product_id' => $productId,
                'quantity' => $qtyDecimal,
                'unit_cost' => $unitCostDecimal,
            ];
        }

        return DB::transaction(function () use (
            $parsedItems,
            $receiptType,
            $creditAccount,
            $creditAccountName,
            $notes,
            $supplierName,
            $receiptDate
        ) {
            // Distinct sorted product IDs for deterministic locking order (deadlock prevention)
            $productIds = array_values(array_unique(array_map(fn($it) => $it['product_id'], $parsedItems)));
            sort($productIds);

            $lockedProducts = [];
            foreach ($productIds as $pid) {
                $product = DB::table('products')->where('id', $pid)->lockForUpdate()->first();
                if (!$product) {
                    throw new RuntimeException("Product ID {$pid} not found");
                }
                $lockedProducts[$pid] = $product;
            }

            // Enforce: opening stock ONLY allowed when prior stock is exactly zero
            if ($receiptType === 'opening') {
                foreach ($lockedProducts as $pid => $product) {
                    $priorStock = BigDecimal::of((string) $product->stock_quantity);
                    if (!$priorStock->isZero()) {
                        throw new InvalidArgumentException(
                            "Opening stock receipt is only allowed when prior stock is zero for product ID {$pid} (current stock: {$product->stock_quantity})"
                        );
                    }
                }
            }

            $receiptId = DB::table('inventory_receipts')->insertGetId([
                'receipt_number' => null,
                'receipt_type' => $receiptType,
                'credit_account' => $creditAccount,
                'receipt_date' => $receiptDate,
                'supplier_name' => $supplierName,
                'total_amount' => '0.00',
                'notes' => $notes,
                'journal_entry_id' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $prefix = ($receiptType === 'opening') ? 'OPN-STK' : 'RCV';
            $receiptNumber = sprintf('%s-%s-%06d', $prefix, date('Ymd'), $receiptId);
            DB::table('inventory_receipts')->where('id', $receiptId)->update([
                'receipt_number' => $receiptNumber,
            ]);

            // Track rolling product state in memory for accurate multi-item processing
            $productState = [];
            foreach ($lockedProducts as $pid => $product) {
                $productState[$pid] = [
                    'stock' => BigDecimal::of((string) $product->stock_quantity),
                    'cost' => BigDecimal::of((string) $product->cost_price),
                ];
            }

            $totalJournalMoney = BigDecimal::zero();
            $processedItems = [];

            foreach ($parsedItems as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];
                $unitCost = $item['unit_cost'];

                /** @var BigDecimal $currentQty */
                $currentQty = $productState[$productId]['stock'];
                /** @var BigDecimal $currentCost */
                $currentCost = $productState[$productId]['cost'];

                // Moving weighted average unit cost policy:
                // If current stock > 0: merge prior inventory valuation with incoming batch
                // Total Valuation = (currentQty * currentCost) + (quantity * unitCost) [exact intermediate]
                // newCostPrice = Total Valuation / (currentQty + quantity) [scaled to 4 decimal places, HalfUp]
                // If current stock <= 0: unit cost directly becomes new cost price
                if ($currentQty->isGreaterThan(0)) {
                    $priorValuation = $currentQty->multipliedBy($currentCost);
                    $incomingValuation = $quantity->multipliedBy($unitCost);
                    $totalValuation = $priorValuation->plus($incomingValuation);
                    $totalQty = $currentQty->plus($quantity);

                    $newCostPrice = $totalValuation->dividedBy($totalQty, 4, RoundingMode::HalfUp);
                } else {
                    $newCostPrice = $unitCost->toScale(4, RoundingMode::HalfUp);
                }

                $newBalance = $currentQty->plus($quantity)->toScale(4, RoundingMode::HalfUp);

                // Exact intermediate subtotal, stored at 2 decimal places for journal & invoice money
                $subtotalExact = $quantity->multipliedBy($unitCost);
                $subtotalMoney = $subtotalExact->toScale(2, RoundingMode::HalfUp);
                $totalJournalMoney = $totalJournalMoney->plus($subtotalMoney);

                $productState[$productId]['stock'] = $newBalance;
                $productState[$productId]['cost'] = $newCostPrice;

                // Atomic stock increment and cost price update (stored at 4 decimals)
                DB::table('products')->where('id', $productId)->update([
                    'stock_quantity' => (string) $newBalance,
                    'cost_price' => (string) $newCostPrice,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                // Record stock movement log
                DB::table('stock_movements')->insert([
                    'product_id' => $productId,
                    'reference_type' => 'inventory_receipt',
                    'reference_id' => $receiptId,
                    'quantity_delta' => (string) $quantity,
                    'balance_after' => (string) $newBalance,
                    'notes' => $notes ?: sprintf('Penerimaan Barang (%s) #%s', $receiptType, $receiptNumber),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                // Record item in receipt: unit_cost stored 4 decimals, subtotal 2 decimals
                DB::table('inventory_receipt_items')->insert([
                    'inventory_receipt_id' => $receiptId,
                    'product_id' => $productId,
                    'quantity' => (string) $quantity,
                    'unit_cost' => (string) $unitCost,
                    'subtotal' => (string) $subtotalMoney,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                $processedItems[] = [
                    'product_id' => $productId,
                    'quantity' => (float) (string) $quantity,
                    'unit_cost' => (float) (string) $unitCost,
                    'subtotal' => (float) (string) $subtotalMoney,
                    'new_stock' => (float) (string) $newBalance,
                    'new_cost_price' => (float) (string) $newCostPrice,
                ];
            }

            $totalAmountStr = (string) $totalJournalMoney->toScale(2, RoundingMode::HalfUp);

            // Update receipt total amount
            DB::table('inventory_receipts')->where('id', $receiptId)->update([
                'total_amount' => $totalAmountStr,
            ]);

            // Balanced double-entry journal entry
            $journalDescription = sprintf(
                'Penerimaan Barang %s #%s%s',
                strtoupper($receiptType),
                $receiptNumber,
                $supplierName ? ' - ' . $supplierName : ''
            );

            $journalId = DB::table('journal_entries')->insertGetId([
                'reference_type' => 'inventory_receipt',
                'reference_id' => $receiptId,
                'description' => $journalDescription,
                'transaction_date' => $receiptDate,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Debit: 1301 Persediaan Material
            DB::table('journal_entry_lines')->insert([
                'journal_entry_id' => $journalId,
                'account_code' => '1301',
                'account_name' => 'Persediaan Material',
                'debit' => $totalAmountStr,
                'credit' => '0.00',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Credit: 3101 Modal Awal / 2101 Hutang Usaha / 1101 Kas Toko / 1102 Bank
            DB::table('journal_entry_lines')->insert([
                'journal_entry_id' => $journalId,
                'account_code' => $creditAccount,
                'account_name' => $creditAccountName,
                'debit' => '0.00',
                'credit' => $totalAmountStr,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            DB::table('inventory_receipts')->where('id', $receiptId)->update([
                'journal_entry_id' => $journalId,
            ]);

            return [
                'receipt_id' => $receiptId,
                'receipt_number' => $receiptNumber,
                'receipt_type' => $receiptType,
                'credit_account' => $creditAccount,
                'total_amount' => (float) $totalAmountStr,
                'journal_entry_id' => $journalId,
                'items' => $processedItems,
            ];
        });
    }
}
