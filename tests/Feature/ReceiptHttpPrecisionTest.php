<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\InventoryReceiptService;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReceiptHttpPrecisionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected int $productId;

    /**
     * Fail-closed safety guard: must only execute against in-memory SQLite (:memory:).
     * Prevents accidental execution against live or non-memory databases.
     */
    protected function beforeRefreshingDatabase(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $database = $connection->getDatabaseName();

        if ($driver !== 'sqlite' || $database !== ':memory:') {
            throw new \RuntimeException(
                sprintf(
                    'ReceiptHttpPrecisionTest must only be run with an in-memory SQLite database (:memory:). Current driver [%s] with database [%s] rejected.',
                    $driver,
                    $database
                )
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (class_exists(PreventRequestForgery::class)) {
            $this->withoutMiddleware(PreventRequestForgery::class);
        }

        $this->user = User::create([
            'name' => 'Synthetic Cashier User',
            'email' => 'cashier.precision@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'sku' => 'MAT-PREC-001',
            'name' => 'Synthetic Precision Material',
            'unit' => 'zak',
            'cost_price' => '0.0000',
            'selling_price' => '250000.00',
            'stock_quantity' => '0.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Test authenticated HTTP POST request to /inventory/receipts with 4-decimal
     * unit_cost 1234.5678 and quantity 10.5000 persists products.cost_price and
     * inventory_receipt_items.unit_cost at 4 decimal places.
     */
    public function test_authenticated_post_persists_four_decimal_precision(): void
    {
        $response = $this->actingAs($this->user)->post('/inventory/receipts', [
            'receipt_type' => 'opening',
            'credit_account' => '3101',
            'product_id' => $this->productId,
            'quantity' => '10.5000',
            'unit_cost' => '1234.5678',
            'supplier_name' => 'Synthetic Supplier Alpha',
            'receipt_date' => '2026-10-21',
            'notes' => 'Synthetic 4dp precision test opening',
        ]);

        $response->assertRedirect(route('inventory.receipts'));
        $response->assertSessionHas('success');

        // Assert products.cost_price persisted with 4 decimal places
        $product = DB::table('products')->where('id', $this->productId)->first();
        $this->assertNotNull($product);
        $this->assertSame('1234.5678', (string) $product->cost_price);
        $this->assertTrue(BigDecimal::of((string) $product->cost_price)->isEqualTo('1234.5678'));

        // Assert stock quantity decimal equivalence without float conversion
        $this->assertTrue(
            BigDecimal::of((string) $product->stock_quantity)->isEqualTo('10.5000'),
            'Stock quantity must match 10.5000 decimal equivalence without float conversion'
        );

        // Assert receipt item.unit_cost persisted with 4 decimal places
        $receiptItem = DB::table('inventory_receipt_items')->first();
        $this->assertNotNull($receiptItem);
        $this->assertSame('1234.5678', (string) $receiptItem->unit_cost);
        $this->assertTrue(BigDecimal::of((string) $receiptItem->unit_cost)->isEqualTo('1234.5678'));

        // Assert receipt item quantity decimal equivalence without float conversion
        $this->assertTrue(
            BigDecimal::of((string) $receiptItem->quantity)->isEqualTo('10.5000'),
            'Receipt item quantity must match 10.5000 decimal equivalence without float conversion'
        );
    }

    /**
     * Test controller passes validated quantity and unit_cost as strings with NO float conversion.
     */
    public function test_controller_passes_validated_inputs_as_strings_without_float_conversion(): void
    {
        $spy = $this->spy(InventoryReceiptService::class);
        $spy->shouldReceive('receive')->andReturn([
            'receipt_id' => 999,
            'receipt_number' => 'OPN-STK-20261021-000999',
            'journal_entry_id' => 999,
            'total_amount' => '12962.96',
        ]);

        $response = $this->actingAs($this->user)->post('/inventory/receipts', [
            'receipt_type' => 'opening',
            'credit_account' => '3101',
            'product_id' => $this->productId,
            'quantity' => '10.5000',
            'unit_cost' => '1234.5678',
        ]);

        $response->assertRedirect(route('inventory.receipts'));

        $spy->shouldHaveReceived('receive')->withArgs(function ($items, $receiptType, $creditAccount, $notes, $supplierName, $receiptDate) {
            $item = $items[0];
            return is_string($item['quantity'])
                && is_string($item['unit_cost'])
                && $item['quantity'] === '10.5000'
                && $item['unit_cost'] === '1234.5678';
        });
    }

    /**
     * Adversarial near-max scale input test to confirm no IEEE-754 precision distortion.
     */
    public function test_adversarial_near_max_scale_preserves_exact_string_without_float_distortion(): void
    {
        $spy = $this->spy(InventoryReceiptService::class);
        $spy->shouldReceive('receive')->andReturn([
            'receipt_id' => 1000,
            'receipt_number' => 'RCV-20261021-001000',
            'journal_entry_id' => 1000,
            'total_amount' => '999999999999.5678',
        ]);

        $adversarialCost = '999999999999.5678';
        $adversarialQty = '999999.1234';

        $response = $this->actingAs($this->user)->post('/inventory/receipts', [
            'receipt_type' => 'purchase',
            'credit_account' => '2101',
            'product_id' => $this->productId,
            'quantity' => $adversarialQty,
            'unit_cost' => $adversarialCost,
        ]);

        $response->assertRedirect(route('inventory.receipts'));

        $spy->shouldHaveReceived('receive')->withArgs(function ($items) use ($adversarialCost, $adversarialQty) {
            $item = $items[0];
            return is_string($item['quantity'])
                && is_string($item['unit_cost'])
                && $item['quantity'] === $adversarialQty
                && $item['unit_cost'] === $adversarialCost;
        });
    }

    /**
     * Test JSON request to /inventory/receipts preserves 4-decimal precision in response.
     */
    public function test_authenticated_json_request_preserves_four_decimal_precision(): void
    {
        $response = $this->actingAs($this->user)->postJson('/inventory/receipts', [
            'receipt_type' => 'opening',
            'credit_account' => '3101',
            'product_id' => $this->productId,
            'quantity' => '5.1234',
            'unit_cost' => '1234.5678',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
        ]);

        $product = DB::table('products')->where('id', $this->productId)->first();
        $this->assertSame('1234.5678', (string) $product->cost_price);
        $this->assertTrue(BigDecimal::of((string) $product->stock_quantity)->isEqualTo('5.1234'));
    }
}
