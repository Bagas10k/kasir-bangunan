<?php

namespace Tests\Feature;

use App\Services\PosCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class FractionalCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function createSyntheticProduct(string $sku, string $name, string $costPrice, string $sellingPrice, string $stockQuantity): int
    {
        return DB::table('products')->insertGetId([
            'sku' => $sku,
            'name' => $name,
            'unit' => 'kg',
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'stock_quantity' => $stockQuantity,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_fractional_checkout_exact_arithmetic_with_four_decimal_places(): void
    {
        $productId = $this->createSyntheticProduct('SYN-FRAC-01', 'Synthetic Sand Super', '1234.5678', '2500.00', '10.0000');
        $service = new PosCheckoutService();

        $result = $service->checkout(
            cartItems: [['product_id' => $productId, 'quantity' => '0.0001']],
            paymentMethod: 'cash',
            paidAmount: '1.00',
            customerType: 'retail'
        );

        $this->assertEquals('0.25', $result['total_amount']);
        $this->assertEquals('0.12', $result['cogs_amount']);
        $this->assertEquals('0.75', $result['change_amount']);

        $saleItem = DB::table('sale_items')->where('sale_id', $result['sale_id'])->first();
        $this->assertNotNull($saleItem);
        $this->assertEquals('0.0001', $saleItem->quantity);
        $this->assertEquals('1234.5678', $saleItem->cost_price, 'sale_items.cost_price must retain 4 decimal places');
        $this->assertEquals('0.25', $saleItem->subtotal);

        $productAfter = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals('9.9999', $productAfter->stock_quantity);

        $movement = DB::table('stock_movements')->where('reference_id', $result['sale_id'])->first();
        $this->assertNotNull($movement);
        $this->assertEquals('-0.0001', $movement->quantity_delta);
        $this->assertEquals('9.9999', $movement->balance_after);
    }

    public function test_duplicate_cart_items_consolidated_deterministically(): void
    {
        $productId = $this->createSyntheticProduct('SYN-FRAC-02', 'Synthetic Gravel Extra', '1000.0000', '2000.00', '5.0000');
        $service = new PosCheckoutService();

        $result = $service->checkout(
            cartItems: [
                ['product_id' => $productId, 'quantity' => '0.0002'],
                ['product_id' => $productId, 'quantity' => '0.0003'],
            ],
            paymentMethod: 'cash',
            paidAmount: '10.00',
            customerType: 'retail'
        );

        $saleItems = DB::table('sale_items')->where('sale_id', $result['sale_id'])->get();
        $this->assertCount(1, $saleItems, 'Duplicate cart items must be consolidated into exactly one sale_item per product');
        $this->assertEquals('0.0005', $saleItems->first()->quantity);
        $this->assertEquals('1.00', $saleItems->first()->subtotal);

        $movements = DB::table('stock_movements')->where('reference_id', $result['sale_id'])->get();
        $this->assertCount(1, $movements, 'Duplicate cart items must record exactly one stock movement per product');
        $this->assertEquals('-0.0005', $movements->first()->quantity_delta);
        $this->assertEquals('4.9995', $movements->first()->balance_after);

        $productAfter = DB::table('products')->where('id', $productId)->first();
        $this->assertEquals('4.9995', $productAfter->stock_quantity);
    }

    public function test_quantity_exceeding_four_decimal_places_rejected(): void
    {
        $productId = $this->createSyntheticProduct('SYN-FRAC-03', 'Synthetic Micro Powder', '500.0000', '1000.00', '10.0000');
        $service = new PosCheckoutService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity cannot exceed 4 decimal places');

        $service->checkout(
            cartItems: [['product_id' => $productId, 'quantity' => '0.00001']],
            paymentMethod: 'cash',
            paidAmount: '100.00'
        );
    }

    public function test_nonnegative_payment_validation_without_float(): void
    {
        $productId = $this->createSyntheticProduct('SYN-FRAC-04', 'Synthetic Aggregate', '500.0000', '1000.00', '10.0000');
        $service = new PosCheckoutService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Paid amount must be non-negative');

        $service->checkout(
            cartItems: [['product_id' => $productId, 'quantity' => '1.0000']],
            paymentMethod: 'cash',
            paidAmount: '-0.0001'
        );
    }

    public function test_credit_payment_with_paid_amount_exceeding_total_rejected(): void
    {
        $productId = $this->createSyntheticProduct('SYN-FRAC-05', 'Synthetic Cement Sack', '50000.0000', '75000.00', '10.0000');
        $service = new PosCheckoutService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Paid deposit cannot exceed total amount for credit sales');

        $service->checkout(
            cartItems: [['product_id' => $productId, 'quantity' => '1.0000']],
            paymentMethod: 'credit',
            paidAmount: '80000.00'
        );
    }
}
