<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Services\StockOpnameService;
use InvalidArgumentException;

class FractionalOpnameTest extends TestCase
{
    use RefreshDatabase;

    private StockOpnameService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StockOpnameService();
    }

    public function test_exact_fractional_valuation_preserves_4dp_cost_and_calculates_exact_value_difference(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'MAT-PASIR-OPN',
            'name' => 'Pasir Cor Super',
            'unit' => 'm3',
            'cost_price' => '275000.1234',
            'selling_price' => '350000.00',
            'stock_quantity' => '10.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Difference: 8.7500 - 10.0000 = -1.2500 m3
        // Exact intermediate: -1.2500 * 275000.1234 = -343750.15425
        // Money 2dp HalfUp: -343750.15 (Loss 343,750.15)
        $result = $this->service->recordOpname(
            items: [['product_id' => $productId, 'physical_qty' => '8.7500']],
            notes: 'Physical audit fractional loss'
        );

        $product = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals('8.7500', $product->stock_quantity);

        $item = DB::table('stock_opname_items')->where('stock_opname_id', $result['opname_id'])->first();
        $this->assertEquals('10.0000', $item->system_qty);
        $this->assertEquals('8.7500', $item->physical_qty);
        $this->assertEquals('-1.2500', $item->difference_qty);
        $this->assertEquals('275000.1234', $item->cost_price);
        $this->assertEquals('-343750.15', $item->value_difference);

        $movement = DB::table('stock_movements')->where('reference_id', $result['opname_id'])->first();
        $this->assertEquals('-1.2500', $movement->quantity_delta);
        $this->assertEquals('8.7500', $movement->balance_after);

        $this->assertNotNull($result['journal_entry_id']);
        $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $result['journal_entry_id'])->get();
        $lossLine = $lines->where('account_code', '5201')->first();
        $inventoryLine = $lines->where('account_code', '1301')->first();

        $this->assertNotNull($lossLine);
        $this->assertEquals('343750.15', $lossLine->debit);
        $this->assertEquals('0.00', $lossLine->credit);

        $this->assertNotNull($inventoryLine);
        $this->assertEquals('0.00', $inventoryLine->debit);
        $this->assertEquals('343750.15', $inventoryLine->credit);
    }

    public function test_positive_variance_with_fractional_subcent_cost(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'MAT-CAT-OPN',
            'name' => 'Cat Tinting Formula',
            'unit' => 'kaleng',
            'cost_price' => '10000.5555',
            'selling_price' => '15000.00',
            'stock_quantity' => '5.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Difference: 7.5000 - 5.0000 = +2.5000
        // Exact intermediate: 2.5000 * 10000.5555 = 25001.38875
        // Money 2dp HalfUp: 25001.39 (Gain 25,001.39)
        $result = $this->service->recordOpname(
            items: [['product_id' => $productId, 'physical_qty' => '7.5000']],
            notes: 'Physical audit fractional gain'
        );

        $item = DB::table('stock_opname_items')->where('stock_opname_id', $result['opname_id'])->first();
        $this->assertEquals('10000.5555', $item->cost_price);
        $this->assertEquals('25001.39', $item->value_difference);

        $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $result['journal_entry_id'])->get();
        $inventoryLine = $lines->where('account_code', '1301')->first();
        $gainLine = $lines->where('account_code', '4201')->first();

        $this->assertNotNull($inventoryLine);
        $this->assertEquals('25001.39', $inventoryLine->debit);
        $this->assertEquals('0.00', $inventoryLine->credit);

        $this->assertNotNull($gainLine);
        $this->assertEquals('0.00', $gainLine->debit);
        $this->assertEquals('25001.39', $gainLine->credit);
    }

    public function test_rejects_quantity_exceeding_four_decimal_places(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'MAT-SCALE-OPN',
            'name' => 'Scale Precision Item',
            'unit' => 'kg',
            'cost_price' => '50000.0000',
            'selling_price' => '70000.00',
            'stock_quantity' => '10.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot exceed 4 decimal places');

        try {
            $this->service->recordOpname(
                items: [['product_id' => $productId, 'physical_qty' => '3.14159']]
            );
        } finally {
            $this->assertEquals(0, DB::table('stock_opnames')->count());
            $this->assertEquals(0, DB::table('stock_movements')->count());
        }
    }

    public function test_rejects_negative_physical_quantity(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'MAT-NEG-OPN',
            'name' => 'Negative Qty Item',
            'unit' => 'kg',
            'cost_price' => '50000.0000',
            'selling_price' => '70000.00',
            'stock_quantity' => '10.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Physical quantity cannot be negative');

        try {
            $this->service->recordOpname(
                items: [['product_id' => $productId, 'physical_qty' => '-0.0001']]
            );
        } finally {
            $this->assertEquals(0, DB::table('stock_opnames')->count());
        }
    }

    public function test_rejects_duplicate_product_ids_before_transaction_with_no_persisted_rows(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'MAT-DUP-OPN',
            'name' => 'Duplicate Audit Item',
            'unit' => 'pcs',
            'cost_price' => '25000.0000',
            'selling_price' => '35000.00',
            'stock_quantity' => '10.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Duplicate product ID {$productId}");

        try {
            $this->service->recordOpname(
                items: [
                    ['product_id' => $productId, 'physical_qty' => '8.0000'],
                    ['product_id' => $productId, 'physical_qty' => '9.0000'],
                ],
                notes: 'Conflicting audit count'
            );
        } finally {
            $this->assertEquals(0, DB::table('stock_opnames')->count());
            $this->assertEquals(0, DB::table('stock_opname_items')->count());
            $this->assertEquals(0, DB::table('stock_movements')->count());
            $this->assertEquals(0, DB::table('journal_entries')->count());
        }
    }

    public function test_zero_variance_creates_no_orphan_journal(): void
    {
        $productId = DB::table('products')->insertGetId([
            'sku' => 'MAT-ZERO-OPN',
            'name' => 'Zero Variance Item',
            'unit' => 'm3',
            'cost_price' => '50000.1234',
            'selling_price' => '70000.00',
            'stock_quantity' => '12.3456',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->service->recordOpname(
            items: [['product_id' => $productId, 'physical_qty' => '12.3456']],
            notes: 'Exact count check'
        );

        $this->assertNull($result['journal_entry_id']);
        $this->assertEquals(0, DB::table('journal_entries')->count());

        $item = DB::table('stock_opname_items')->where('stock_opname_id', $result['opname_id'])->first();
        $this->assertEquals('12.3456', $item->system_qty);
        $this->assertEquals('12.3456', $item->physical_qty);
        $this->assertEquals('0.0000', $item->difference_qty);
        $this->assertEquals('50000.1234', $item->cost_price);
        $this->assertEquals('0.00', $item->value_difference);
    }
}
