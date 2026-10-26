<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FleetFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Only isolated SQLite :memory: may run fleet tests.');
        }
    }

    private function product(): int
    {
        return DB::table('products')->insertGetId([
            'sku' => 'SYN-FLEET-01', 'name' => 'Synthetic Material Alpha', 'unit' => 'zak',
            'cost_price' => '5000.0000', 'selling_price' => '10000.00', 'stock_quantity' => '10.0000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function vehicle(string $status = 'active'): int
    {
        return DB::table('vehicles')->insertGetId([
            'license_plate' => 'SYN-01', 'vehicle_type' => 'pickup', 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function salePayload(int $productId, array $extra = []): array
    {
        return array_merge([
            'items' => [['product_id' => $productId, 'quantity' => '1']],
            'customer_type' => 'retail', 'payment_method' => 'cash', 'paid_amount' => '10000.00',
        ], $extra);
    }

    private function receiptPayload(int $productId, array $extra = []): array
    {
        return array_merge([
            'receipt_type' => 'purchase', 'credit_account' => '2101',
            'product_id' => $productId, 'quantity' => '2', 'unit_cost' => '5000.0000',
        ], $extra);
    }

    public function test_pos_checkout_with_armada_creates_pending_ritase_and_separate_cash_expense(): void
    {
        $this->startBooks();
        $vehicleId = $this->vehicle();
        $productId = $this->product();
        $response = $this->actingAs(User::factory()->create())->postJson(route('pos.checkout'),
            $this->salePayload($productId, ['use_armada' => '1', 'vehicle_id' => $vehicleId, 'ritase_amount' => '125.50']));
        $response->assertOk()->assertJsonPath('total_amount', '10000.00');
        $saleId = $response->json('sale_id');
        $this->assertDatabaseHas('ritases', ['sale_id' => $saleId, 'vehicle_id' => $vehicleId,
            'total_cost' => '125.50', 'status' => 'pending_details']);
        $trip = DB::table('ritases')->where('sale_id', $saleId)->first();
        $this->assertNull($trip->driver_name);
        $this->assertNull($trip->destination);
        $this->assertSame(1, DB::table('journal_entries')->where('reference_type', 'ritase')->count());
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $trip->journal_entry_id,
            'account_code' => '1101', 'credit' => '125.50']);
    }

    public function test_receipt_with_armada_creates_pending_ritase_without_changing_stock_cost(): void
    {
        $this->startBooks();
        $vehicleId = $this->vehicle();
        $productId = $this->product();
        $response = $this->actingAs(User::factory()->create())->postJson(route('inventory.receipts.store'),
            $this->receiptPayload($productId, ['use_armada' => '1', 'vehicle_id' => $vehicleId, 'ritase_amount' => '80.25']));
        $response->assertCreated()->assertJsonPath('receipt.total_amount', '10000.00');
        $receiptId = $response->json('receipt.receipt_id');
        $this->assertDatabaseHas('ritases', ['inventory_receipt_id' => $receiptId,
            'vehicle_id' => $vehicleId, 'total_cost' => '80.25', 'status' => 'pending_details']);
        $this->assertSame('5000', (string) (float) DB::table('products')->where('id', $productId)->value('cost_price'));
    }

    public function test_insufficient_cash_rolls_back_checkout_and_stock(): void
    {
        $this->startBooks('0.00', '1.00');
        $vehicleId = $this->vehicle();
        $productId = $this->product();
        $response = $this->actingAs(User::factory()->create())->postJson(route('pos.checkout'),
            $this->salePayload($productId, ['use_armada' => '1', 'vehicle_id' => $vehicleId, 'ritase_amount' => '20000.00']));
        $response->assertStatus(422);
        $this->assertSame(0, DB::table('sales')->count());
        $this->assertSame(0, DB::table('ritases')->count());
        $this->assertSame('10', (string) (float) DB::table('products')->where('id', $productId)->value('stock_quantity'));
    }

    public function test_pending_trip_details_are_completed_without_second_cash_journal(): void
    {
        $this->startBooks();
        $vehicleId = $this->vehicle();
        $productId = $this->product();
        $user = User::factory()->create();
        $sale = $this->actingAs($user)->postJson(route('pos.checkout'),
            $this->salePayload($productId, ['use_armada' => '1', 'vehicle_id' => $vehicleId, 'ritase_amount' => '125.50']));
        $sale->assertOk();
        $trip = DB::table('ritases')->where('sale_id', $sale->json('sale_id'))->first();
        $journalsBefore = DB::table('journal_entries')->count();
        $this->actingAs($user)->patchJson(route('ritase.details', ['id' => $trip->id]), [
            'driver_name' => 'Synthetic Driver Alpha', 'destination' => 'Zone Alpha',
        ])->assertOk();
        $this->assertDatabaseHas('ritases', ['id' => $trip->id, 'status' => 'completed',
            'driver_name' => 'Synthetic Driver Alpha', 'destination' => 'Zone Alpha']);
        $this->assertSame($journalsBefore, DB::table('journal_entries')->count());
        $this->actingAs($user)->patchJson(route('ritase.details', ['id' => $trip->id]), [
            'driver_name' => 'Synthetic Driver Beta', 'destination' => 'Zone Beta',
        ])->assertStatus(422);
    }

    public function test_pages_show_fleet_controls_and_pending_trip_details(): void
    {
        $this->startBooks();
        $this->vehicle();
        $this->actingAs(User::factory()->create())->get(route('pos.index'))
            ->assertOk()->assertSee('Pengiriman dengan Armada')->assertSee('Uang Ritase (Rp)');
        $this->get(route('inventory.receipts'))
            ->assertOk()->assertSee('Angkutan dengan Armada Toko')->assertSee('Uang Ritase (Rp)');
    }

    public function test_without_armada_preserves_legacy_checkout_and_receipt(): void
    {
        $this->startBooks();
        $productId = $this->product();
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('pos.checkout'), $this->salePayload($productId))->assertOk();
        $this->actingAs($user)->postJson(route('inventory.receipts.store'), $this->receiptPayload($productId))->assertCreated();
        $this->assertSame(0, DB::table('ritases')->count());
    }

    public function test_integer_armada_flag_creates_trip_from_json_client(): void
    {
        $this->startBooks();
        $vehicleId = $this->vehicle();
        $productId = $this->product();
        $response = $this->actingAs(User::factory()->create())->postJson(route('pos.checkout'),
            $this->salePayload($productId, ['use_armada' => 1, 'vehicle_id' => $vehicleId, 'ritase_amount' => '125.50']));
        $response->assertOk();
        $this->assertDatabaseHas('ritases', ['sale_id' => $response->json('sale_id'), 'total_cost' => '125.50']);
    }

    public function test_receipt_and_stock_roll_back_when_cash_cannot_pay_ritase(): void
    {
        $this->startBooks('0.00', '1.00');
        $vehicleId = $this->vehicle();
        $productId = $this->product();
        $this->actingAs(User::factory()->create())->postJson(route('inventory.receipts.store'),
            $this->receiptPayload($productId, ['use_armada' => '1', 'vehicle_id' => $vehicleId, 'ritase_amount' => '1.00']))
            ->assertStatus(422);
        $this->assertSame(0, DB::table('inventory_receipts')->count());
        $this->assertSame(0, DB::table('ritases')->count());
        $this->assertSame('10', (string) (float) DB::table('products')->where('id', $productId)->value('stock_quantity'));
    }

    public function test_missing_ritase_amount_is_rejected_before_checkout(): void
    {
        $vehicleId = $this->vehicle();
        $productId = $this->product();
        $this->actingAs(User::factory()->create())->postJson(route('pos.checkout'),
            $this->salePayload($productId, ['use_armada' => '1', 'vehicle_id' => $vehicleId]))
            ->assertUnprocessable()->assertJsonValidationErrors('ritase_amount');
        $this->assertSame(0, DB::table('sales')->count());
    }

    public function test_oversize_ritase_amount_is_rejected_before_writes(): void
    {
        $vehicleId = $this->vehicle();
        $productId = $this->product();
        $this->actingAs(User::factory()->create())->postJson(route('pos.checkout'),
            $this->salePayload($productId, ['use_armada' => '1', 'vehicle_id' => $vehicleId,
                'ritase_amount' => '999999999999999999.00']))
            ->assertUnprocessable()->assertJsonValidationErrors('ritase_amount');
        $this->assertSame(0, DB::table('sales')->count());
    }

    public function test_invalid_or_inactive_vehicle_is_rejected_without_receipt(): void
    {
        $this->startBooks();
        $vehicleId = $this->vehicle('inactive');
        $productId = $this->product();
        $response = $this->actingAs(User::factory()->create())->postJson(route('inventory.receipts.store'),
            $this->receiptPayload($productId, ['use_armada' => '1', 'vehicle_id' => $vehicleId, 'ritase_amount' => '20.00']));
        $response->assertStatus(422);
        $this->assertSame(0, DB::table('inventory_receipts')->count());
        $this->assertSame(0, DB::table('ritases')->count());
    }
}
