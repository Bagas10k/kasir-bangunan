<?php

namespace Tests\Feature;

use App\Services\InventoryReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class ReceiptInputPrecisionGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('ReceiptInputPrecisionGuardTest requires disposable SQLite :memory:');
        }
    }

    private function product(): int
    {
        return DB::table('products')->insertGetId([
            'sku' => 'SYN-PRECISION-INPUT',
            'name' => 'Synthetic Material',
            'unit' => 'unit',
            'cost_price' => '0.0000',
            'selling_price' => '100.00',
            'stock_quantity' => '0.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_more_than_four_nonzero_quantity_decimals_are_rejected_without_writes(): void
    {
        $id = $this->product();
        try {
            (new InventoryReceiptService())->receive([
                ['product_id' => $id, 'quantity' => '1.00005', 'unit_cost' => '12.3456'],
            ]);
            $this->fail('Expected precision rejection before any receipt is written');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('precision', strtolower($e->getMessage()));
        }
        $this->assertSame(0, DB::table('inventory_receipts')->count());
        $this->assertSame(0, DB::table('journal_entries')->count());
        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_more_than_four_nonzero_cost_decimals_are_rejected_without_writes(): void
    {
        $id = $this->product();
        try {
            (new InventoryReceiptService())->receive([
                ['product_id' => $id, 'quantity' => '1.0000', 'unit_cost' => '12.34567'],
            ]);
            $this->fail('Expected precision rejection before any receipt is written');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('precision', strtolower($e->getMessage()));
        }
        $this->assertSame(0, DB::table('inventory_receipts')->count());
        $this->assertSame(0, DB::table('journal_entries')->count());
        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_trailing_zero_precision_remains_valid(): void
    {
        $id = $this->product();
        (new InventoryReceiptService())->receive([
            ['product_id' => $id, 'quantity' => '1.00000', 'unit_cost' => '12.345600'],
        ]);
        $row = DB::table('inventory_receipt_items')->first();
        $this->assertNotNull($row);
        $this->assertSame('12.3456', (string) $row->unit_cost);
        $this->assertEquals('1', $row->quantity);
    }
}
