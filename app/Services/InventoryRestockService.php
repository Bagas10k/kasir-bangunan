<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryRestockService
{
    public function __construct(
        protected ?InventoryReceiptService $receiptService = null
    ) {
        $this->receiptService = $receiptService ?? new InventoryReceiptService();
    }

    /**
     * Canonical delegation adapter to InventoryReceiptService:
     * Enforces moving weighted average valuation and verified double-entry journal.
     */
    public function recordStockIn(
        int $productId,
        float|string $quantity,
        float|string $unitCost,
        string $sourceType = 'opening_equity',
        ?string $notes = null
    ): array {
        if ($productId <= 0) {
            throw new InvalidArgumentException("Invalid product ID: {$productId}");
        }
        if (!is_numeric($quantity) || BigDecimal::of((string) $quantity)->isLessThanOrEqualTo(0)) {
            throw new InvalidArgumentException("Restock quantity must be greater than zero");
        }
        if (!is_numeric($unitCost) || BigDecimal::of((string) $unitCost)->isLessThanOrEqualTo(0)) {
            throw new InvalidArgumentException("Unit cost must be greater than zero");
        }

        $sourceMap = [
            'opening_equity' => ['receipt_type' => 'opening', 'credit_account' => '3101'],
            'supplier_payable' => ['receipt_type' => 'purchase', 'credit_account' => '2101'],
            'cash_purchase' => ['receipt_type' => 'purchase', 'credit_account' => '1101'],
        ];

        if (!isset($sourceMap[$sourceType])) {
            throw new InvalidArgumentException("Invalid stock-in source: {$sourceType}");
        }

        $config = $sourceMap[$sourceType];

        $receiptResult = $this->receiptService->receive(
            items: [
                [
                    'product_id' => $productId,
                    'quantity' => (string) $quantity,
                    'unit_cost' => (string) $unitCost,
                ],
            ],
            receiptType: $config['receipt_type'],
            creditAccount: $config['credit_account'],
            notes: $notes ?? "Inventory Stock-In ({$sourceType})"
        );

        $movement = DB::table('stock_movements')
            ->where('reference_type', 'inventory_receipt')
            ->where('reference_id', $receiptResult['receipt_id'])
            ->where('product_id', $productId)
            ->first();

        $firstItem = $receiptResult['items'][0];

        return [
            'movement_id' => $movement ? $movement->id : null,
            'journal_entry_id' => $receiptResult['journal_entry_id'],
            'product_id' => $productId,
            'quantity' => (string) $firstItem['quantity'],
            'total_cost' => (string) $receiptResult['total_amount'],
            'balance_after' => (string) $firstItem['new_stock'],
        ];
    }
}
