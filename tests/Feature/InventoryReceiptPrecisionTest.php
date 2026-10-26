<?php

namespace Tests\Feature;

use App\Services\InventoryReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class InventoryReceiptPrecisionTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('RefreshDatabase is only permitted against isolated SQLite :memory:.');
        }
    }

    private function insertProduct(string $sku, string $stock, string $cost): int
    {
        return DB::table('products')->insertGetId([
            'sku' => $sku,
            'name' => 'Precision Product ' . $sku,
            'unit' => 'sak',
            'cost_price' => $cost,
            'selling_price' => '150000.00',
            'stock_quantity' => $stock,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_opening_receipt_preserves_four_decimal_precision_exactly(): void
    {
        $productId = $this->insertProduct('PREC-4DP', '0.0000', '0.0000');

        $service = new InventoryReceiptService();
        $result = $service->receive(
            items: [['product_id' => $productId, 'quantity' => '10.1234', 'unit_cost' => '1234.5678']],
            receiptType: 'opening',
            creditAccount: '3101',
        );

        // Unit cost & quantity stored at exact 4-decimal strings (no float drift).
        $this->assertSame('1234.5678', (string) DB::table('products')->where('id', $productId)->value('cost_price'));
        $this->assertSame('10.1234', (string) DB::table('products')->where('id', $productId)->value('stock_quantity'));

        $item = DB::table('inventory_receipt_items')->where('inventory_receipt_id', $result['receipt_id'])->first();
        $this->assertSame('10.1234', (string) $item->quantity);
        $this->assertSame('1234.5678', (string) $item->unit_cost);
        // 1234.5678 * 10.1234 = 12498.02366652 -> HalfUp 2dp = 12498.02
        $this->assertSame('12498.02', (string) $item->subtotal);

        // Balanced journal.
        $debit = DB::table('journal_entry_lines')->where('journal_entry_id', $result['journal_entry_id'])->sum('debit');
        $credit = DB::table('journal_entry_lines')->where('journal_entry_id', $result['journal_entry_id'])->sum('credit');
        $this->assertSame(number_format((float) $debit, 2, '.', ''), number_format((float) $credit, 2, '.', ''));
    }

    public function test_restock_weights_average_cost_and_locks_four_decimals(): void
    {
        // Existing stock 10 @ 1000.0000; incoming 5 @ 2000.0000.
        $productId = $this->insertProduct('PREC-WAVG', '10.0000', '1000.0000');

        $service = new InventoryReceiptService();
        $service->receive(
            items: [['product_id' => $productId, 'quantity' => '5.0000', 'unit_cost' => '2000.0000']],
            receiptType: 'purchase',
            creditAccount: '2101',
        );

        // Weighted average: (10*1000 + 5*2000) / 15 = 20000/15 = 1333.3333... -> HalfUp 4dp = 1333.3333
        $this->assertSame('1333.3333', (string) DB::table('products')->where('id', $productId)->value('cost_price'));
    }

    public function test_receipt_rejects_quantity_exceeding_four_decimal_places(): void
    {
        $productId = $this->insertProduct('PREC-REJ', '0.0000', '0.0000');

        $this->expectException(InvalidArgumentException::class);

        $service = new InventoryReceiptService();
        $service->receive(
            items: [['product_id' => $productId, 'quantity' => '0.12345', 'unit_cost' => '1000.0000']],
            receiptType: 'opening',
            creditAccount: '3101',
        );
    }
}