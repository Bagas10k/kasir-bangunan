<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2ReceiptSupplierLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Receipt supplier tests require isolated SQLite :memory:.');
        }
    }

    private function actor(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function product(): int
    {
        return (int) DB::table('products')->insertGetId([
            'sku' => 'SYN-LINK', 'name' => 'Barang Sintetis', 'unit' => 'sak', 'category' => 'Umum',
            'stock_quantity' => '0', 'min_stock' => '1', 'cost_price' => '100', 'selling_price' => '150',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function supplier(string $code, bool $active = true): int
    {
        return (int) DB::table('v2_parties')->insertGetId([
            'kind' => 'supplier', 'code' => $code, 'name' => 'Supplier Sintetis '.$code,
            'is_active' => $active, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_confirmed_existing_supplier_link_is_additive_and_replay_safe(): void
    {
        $admin = $this->actor();
        $product = $this->product();
        $old = $this->supplier('SUP-0001');
        $supplier = $this->supplier('SUP-0002');
        DB::table('v2_product_suppliers')->insert([
            'product_id' => $product, 'supplier_id' => $old, 'is_primary' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $payload = ['product_id' => $product, 'supplier_id' => $supplier, 'confirmed' => true, 'idempotency_key' => 'synthetic-receipt-link'];

        $first = $this->actingAs($admin)->postJson('/v2/receipts/product-supplier', $payload)->assertOk()
            ->assertJsonPath('supplier.id', $supplier)->assertJsonPath('created', false)->json();
        $this->postJson('/v2/receipts/product-supplier', $payload)->assertOk()->assertExactJson($first);

        $this->assertDatabaseCount('v2_product_suppliers', 2);
        $this->assertDatabaseHas('v2_product_suppliers', ['product_id' => $product, 'supplier_id' => $old, 'is_primary' => true]);
        $this->assertDatabaseHas('v2_product_suppliers', ['product_id' => $product, 'supplier_id' => $supplier, 'is_primary' => false]);
        $this->assertDatabaseCount('inventory_receipts', 0);
        $this->assertDatabaseCount('v2_invoices', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_new_supplier_is_created_and_linked_once_using_master_code_conventions(): void
    {
        $product = $this->product();
        $this->supplier('SUP-0041');
        $payload = ['product_id' => $product, 'name' => ' Supplier Baru Sintetis ', 'notes' => 'Catatan sintetis',
            'confirmed' => true, 'idempotency_key' => 'synthetic-create-supplier'];

        $first = $this->actingAs($this->actor())->postJson('/v2/receipts/product-supplier', $payload)->assertOk()
            ->assertJsonPath('created', true)->assertJsonPath('supplier.code', 'SUP-0042')
            ->assertJsonPath('supplier.name', 'Supplier Baru Sintetis')->json();
        $this->postJson('/v2/receipts/product-supplier', $payload)->assertOk()->assertExactJson($first);
        $this->postJson('/v2/receipts/product-supplier', [...$payload, 'name' => 'Nama berbeda'])
            ->assertUnprocessable();

        $this->assertDatabaseCount('v2_parties', 2);
        $this->assertDatabaseHas('v2_product_suppliers', ['product_id' => $product, 'supplier_id' => $first['supplier']['id'], 'is_primary' => true]);
        $this->assertDatabaseHas('v2_parties', ['id' => $first['supplier']['id'], 'notes' => 'Catatan sintetis']);
    }

    public function test_linking_requires_active_admin_confirmation_and_valid_parties(): void
    {
        $product = $this->product();
        $supplier = $this->supplier('SUP-0001', false);
        $url = '/v2/receipts/product-supplier';
        $payload = ['product_id' => $product, 'supplier_id' => $supplier, 'confirmed' => true, 'idempotency_key' => 'synthetic-invalid-link'];
        $this->postJson($url, $payload)->assertUnauthorized();
        $this->actingAs($this->actor('cashier'))->postJson($url, $payload)->assertForbidden();
        $admin = $this->actor();
        $this->actingAs($admin)->postJson($url, [...$payload, 'confirmed' => false])->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $this->postJson($url, $payload)->assertUnprocessable();
        DB::table('v2_parties')->where('id', $supplier)->update(['is_active' => true, 'kind' => 'customer']);
        $this->postJson($url, $payload)->assertUnprocessable();
        DB::table('products')->where('id', $product)->update(['is_active' => false]);
        $this->postJson($url, ['product_id' => $product, 'name' => 'Supplier Terlarang Sintetis', 'confirmed' => true, 'idempotency_key' => 'synthetic-archived-product'])->assertUnprocessable();
        DB::table('users')->where('id', $admin->id)->update(['is_active' => false]);
        $this->actingAs($admin->fresh())->postJson($url, $payload)->assertUnauthorized();

        $this->assertDatabaseCount('v2_product_suppliers', 0);
        $this->assertDatabaseCount('v2_requests', 0);
        $this->assertDatabaseCount('v2_parties', 1);
    }

    public function test_existing_supplier_name_is_not_created_again_or_unarchived(): void
    {
        $product = $this->product();
        $this->supplier('SUP-0001', false);
        $this->actingAs($this->actor())->postJson('/v2/receipts/product-supplier', [
            'product_id' => $product, 'name' => 'supplier sintetis sup-0001', 'confirmed' => true, 'idempotency_key' => 'synthetic-duplicate-name',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('v2_parties', 1);
        $this->assertDatabaseCount('v2_product_suppliers', 0);
        $this->assertDatabaseHas('v2_parties', ['code' => 'SUP-0001', 'is_active' => false]);
    }

    public function test_failed_link_rolls_back_the_new_supplier_and_hides_database_details(): void
    {
        $product = $this->product();
        DB::unprepared("CREATE TRIGGER synthetic_reject_supplier_link BEFORE INSERT ON v2_product_suppliers BEGIN SELECT RAISE(ABORT, 'synthetic-link-failure'); END");

        $response = $this->actingAs($this->actor())->postJson('/v2/receipts/product-supplier', [
            'product_id' => $product, 'name' => 'Supplier Rollback Sintetis', 'confirmed' => true, 'idempotency_key' => 'synthetic-rollback-link',
        ])->assertUnprocessable();

        $this->assertStringNotContainsString('synthetic-link-failure', $response->getContent());
        $this->assertDatabaseCount('v2_parties', 0);
        $this->assertDatabaseCount('v2_product_suppliers', 0);
        $this->assertDatabaseCount('v2_requests', 0);
    }
}
