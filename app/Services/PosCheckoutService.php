<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PosCheckoutService
{
    /**
     * Executes atomic POS checkout:
     * - Uses exact BigDecimal arithmetic for fractional quantities and 4-decimal HPP
     * - Retains products.cost_price and sale_items.cost_price at 4 decimal places
     * - Validates payment method against strict whitelist
     * - Validates non-negative paid amount without float precision leakage
     * - Validates maximum 4 decimal places on cart item quantities
     * - Deterministically consolidates duplicate cart items before locking and decrementing
     * - Classifies transfer to Bank (1102) rather than AR (1103)
     * - Handles credit sales with partial deposit: debits Kas (1101) for deposit, Piutang (1103) for remainder
     * - Rejects credit sales where paid_amount exceeds total_amount
     * - Strict double-entry balanced journal entry
     */
    public function checkout(array $cartItems, string $paymentMethod, float|string $paidAmount, string $customerType = 'retail'): array
    {
        if (empty($cartItems)) {
            throw new InvalidArgumentException("Cart items cannot be empty");
        }

        $validPaymentMethods = ['cash', 'transfer', 'credit'];
        if (!in_array($paymentMethod, $validPaymentMethods, true)) {
            throw new InvalidArgumentException("Invalid payment method: {$paymentMethod}");
        }

        if (!is_numeric($paidAmount)) {
            throw new InvalidArgumentException("Paid amount must be non-negative");
        }
        $paidAmountBd = BigDecimal::of((string) $paidAmount);
        if ($paidAmountBd->isNegative()) {
            throw new InvalidArgumentException("Paid amount must be non-negative");
        }

        $validCustomerTypes = ['retail', 'contractor'];
        if (!in_array($customerType, $validCustomerTypes, true)) {
            throw new InvalidArgumentException("Invalid customer type: {$customerType}");
        }

        return DB::transaction(function () use ($cartItems, $paymentMethod, $paidAmountBd, $customerType) {
            // Consolidate duplicate products in cart deterministically to prevent double decrement anomalies
            $consolidatedItems = [];
            foreach ($cartItems as $index => $item) {
                if (!isset($item['product_id']) || !isset($item['quantity'])) {
                    throw new InvalidArgumentException("Cart item at index {$index} must contain product_id and quantity");
                }
                $productId = (int) $item['product_id'];
                if ($productId <= 0) {
                    throw new InvalidArgumentException("Invalid product ID: {$productId}");
                }
                if (!is_numeric($item['quantity'])) {
                    throw new InvalidArgumentException("Quantity must be numeric for product ID {$productId}");
                }

                $qtyBd = BigDecimal::of((string) $item['quantity']);
                if ($qtyBd->isNegativeOrZero()) {
                    throw new InvalidArgumentException("Quantity must be greater than zero for product ID {$productId}");
                }
                if ($qtyBd->strippedOfTrailingZeros()->getScale() > 4) {
                    throw new InvalidArgumentException("Quantity cannot exceed 4 decimal places for product ID {$productId}");
                }

                if (!isset($consolidatedItems[$productId])) {
                    $consolidatedItems[$productId] = [
                        'product_id' => $productId,
                        'quantity' => $qtyBd,
                    ];
                } else {
                    $consolidatedItems[$productId]['quantity'] = $consolidatedItems[$productId]['quantity']->plus($qtyBd);
                }
            }

            // Lock products in deterministic ascending ID order to prevent deadlocks
            ksort($consolidatedItems);
            $lockedProducts = [];
            foreach ($consolidatedItems as $productId => $itemData) {
                $product = DB::table('products')->where('id', $productId)->lockForUpdate()->first();
                if (!$product) {
                    throw new RuntimeException("Product ID {$productId} not found");
                }
                if (!(bool) $product->is_active) {
                    throw new InvalidArgumentException("Product ID {$productId} is inactive");
                }
                $currentStockBd = BigDecimal::of((string) $product->stock_quantity);
                if ($currentStockBd->isLessThan($itemData['quantity'])) {
                    throw new RuntimeException(
                        "Insufficient stock for product ID {$productId} ({$product->name}). Requested: {$itemData['quantity']}, Current: {$product->stock_quantity}"
                    );
                }
                $lockedProducts[$productId] = $product;
            }

            $totalAmountBd = BigDecimal::zero();
            $totalCogsBd = BigDecimal::zero();
            $processedItems = [];

            // Process items deterministically
            foreach ($consolidatedItems as $productId => $itemData) {
                $product = $lockedProducts[$productId];
                $quantityBd = $itemData['quantity'];
                $quantityStr = (string) $quantityBd->toScale(4, RoundingMode::HalfUp);

                // Atomic stock decrement
                DB::table('products')
                    ->where('id', $productId)
                    ->decrement('stock_quantity', $quantityStr);

                $updatedProduct = DB::table('products')->where('id', $productId)->first();
                $balanceAfterStr = (string) BigDecimal::of((string) $updatedProduct->stock_quantity)->toScale(4, RoundingMode::HalfUp);

                $costPriceBd = BigDecimal::of((string) $product->cost_price);
                $costPriceStr = (string) $costPriceBd->toScale(4, RoundingMode::HalfUp);

                $sellingPriceBd = BigDecimal::of((string) $product->selling_price);
                $sellingPriceStr = (string) $sellingPriceBd->toScale(2, RoundingMode::HalfUp);

                // Exact arithmetic: unit prices multiplied by quantity, rounded to 2 decimals for currency totals
                $itemSubtotalBd = $sellingPriceBd->multipliedBy($quantityBd)->toScale(2, RoundingMode::HalfUp);
                $itemCogsBd = $costPriceBd->multipliedBy($quantityBd)->toScale(2, RoundingMode::HalfUp);

                $totalAmountBd = $totalAmountBd->plus($itemSubtotalBd);
                $totalCogsBd = $totalCogsBd->plus($itemCogsBd);

                $processedItems[] = [
                    'product_id' => $productId,
                    'name' => $product->name,
                    'unit' => $product->unit,
                    'quantity' => $quantityStr,
                    'unit_price' => $sellingPriceStr,
                    'cost_price' => $costPriceStr,
                    'subtotal' => (string) $itemSubtotalBd,
                    'balance_after' => $balanceAfterStr,
                ];
            }

            if ($paymentMethod === 'cash' && $paidAmountBd->isLessThan($totalAmountBd)) {
                throw new InvalidArgumentException("Paid amount is less than total amount");
            }
            if ($paymentMethod === 'transfer' && $paidAmountBd->isLessThan($totalAmountBd)) {
                throw new InvalidArgumentException("Paid amount must cover total amount for bank transfer");
            }
            if ($paymentMethod === 'credit' && $paidAmountBd->isGreaterThan($totalAmountBd)) {
                throw new InvalidArgumentException("Paid deposit cannot exceed total amount for credit sales");
            }

            $changeAmountBd = ($paymentMethod === 'credit')
                ? BigDecimal::zero()
                : ($paidAmountBd->isGreaterThan($totalAmountBd) ? $paidAmountBd->minus($totalAmountBd) : BigDecimal::zero());

            $totalStr = (string) $totalAmountBd->toScale(2, RoundingMode::HalfUp);
            $cogsStr = (string) $totalCogsBd->toScale(2, RoundingMode::HalfUp);
            $paidStr = (string) $paidAmountBd->toScale(2, RoundingMode::HalfUp);
            $changeStr = (string) $changeAmountBd->toScale(2, RoundingMode::HalfUp);

            // Insert sale record
            $saleId = DB::table('sales')->insertGetId([
                'invoice_number' => null,
                'customer_type' => $customerType,
                'payment_method' => $paymentMethod,
                'total_amount' => $totalStr,
                'cogs_amount' => $cogsStr,
                'paid_amount' => $paidStr,
                'change_amount' => $changeStr,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $invoiceNumber = sprintf('INV-%s-%06d', date('Ymd'), $saleId);
            DB::table('sales')->where('id', $saleId)->update(['invoice_number' => $invoiceNumber]);

            foreach ($processedItems as $item) {
                DB::table('sale_items')->insert([
                    'sale_id' => $saleId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                    'cost_price' => $item['cost_price'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                DB::table('stock_movements')->insert([
                    'product_id' => $item['product_id'],
                    'reference_type' => 'sale',
                    'reference_id' => $saleId,
                    'quantity_delta' => (string) BigDecimal::of($item['quantity'])->negated()->toScale(4, RoundingMode::HalfUp),
                    'balance_after' => $item['balance_after'],
                    'notes' => "Sale {$invoiceNumber}",
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Balanced Double-Entry Accounting
            $journalId = DB::table('journal_entries')->insertGetId([
                'reference_type' => 'sale',
                'reference_id' => $saleId,
                'description' => "POS Sale {$invoiceNumber}",
                'transaction_date' => date('Y-m-d'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Asset Accounts:
            // cash -> 1101 (Kas Toko)
            // transfer -> 1102 (Kas Bank)
            // credit -> if partial deposit: 1101 (Kas) for deposit, 1103 (Piutang) for remaining balance
            if ($paymentMethod === 'cash') {
                DB::table('journal_entry_lines')->insert([
                    'journal_entry_id' => $journalId,
                    'account_code' => '1101',
                    'account_name' => 'Kas Toko',
                    'debit' => $totalStr,
                    'credit' => '0.00',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            } elseif ($paymentMethod === 'transfer') {
                DB::table('journal_entry_lines')->insert([
                    'journal_entry_id' => $journalId,
                    'account_code' => '1102',
                    'account_name' => 'Kas Bank',
                    'debit' => $totalStr,
                    'credit' => '0.00',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            } else { // credit
                $depositBd = $paidAmountBd;
                $receivableBd = $totalAmountBd->minus($depositBd);

                if ($depositBd->isGreaterThan(BigDecimal::zero())) {
                    DB::table('journal_entry_lines')->insert([
                        'journal_entry_id' => $journalId,
                        'account_code' => '1101',
                        'account_name' => 'Kas Toko (Uang Muka Penjualan)',
                        'debit' => (string) $depositBd->toScale(2, RoundingMode::HalfUp),
                        'credit' => '0.00',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                if ($receivableBd->isGreaterThan(BigDecimal::zero())) {
                    DB::table('journal_entry_lines')->insert([
                        'journal_entry_id' => $journalId,
                        'account_code' => '1103',
                        'account_name' => 'Piutang Usaha',
                        'debit' => (string) $receivableBd->toScale(2, RoundingMode::HalfUp),
                        'credit' => '0.00',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            // Credit: Pendapatan Penjualan (4101)
            DB::table('journal_entry_lines')->insert([
                'journal_entry_id' => $journalId,
                'account_code' => '4101',
                'account_name' => 'Pendapatan Penjualan',
                'debit' => '0.00',
                'credit' => $totalStr,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Debit: Beban Pokok Penjualan / COGS (5101)
            DB::table('journal_entry_lines')->insert([
                'journal_entry_id' => $journalId,
                'account_code' => '5101',
                'account_name' => 'Beban Pokok Penjualan',
                'debit' => $cogsStr,
                'credit' => '0.00',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Credit: Persediaan Barang (1301)
            DB::table('journal_entry_lines')->insert([
                'journal_entry_id' => $journalId,
                'account_code' => '1301',
                'account_name' => 'Persediaan Material',
                'debit' => '0.00',
                'credit' => $cogsStr,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'sale_id' => $saleId,
                'invoice_number' => $invoiceNumber,
                'items' => $processedItems,
                'total_amount' => $totalStr,
                'cogs_amount' => $cogsStr,
                'paid_amount' => $paidStr,
                'change_amount' => $changeStr,
                'journal_entry_id' => $journalId,
            ];
        });
    }
}
