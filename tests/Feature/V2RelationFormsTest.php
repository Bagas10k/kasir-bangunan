<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\Ledger;
use App\Services\V2\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2RelationFormsTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('V2 relation form tests require isolated SQLite :memory:.');
        }
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert([
            'user_id' => $user->id,
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function fundAccounts(): void
    {
        foreach ([
            ['1101', 'Kas', 'asset', 'debit'],
            ['1102', 'Bank', 'asset', 'debit'],
            ['1191', 'Uang Muka Karyawan', 'asset', 'debit'],
            ['1301', 'Persediaan', 'asset', 'debit'],
            ['2101', 'Utang Usaha', 'liability', 'credit'],
            ['3101', 'Modal', 'equity', 'credit'],
            ['6101', 'Beban Operasional Toko', 'expense', 'debit'],
            ['6201', 'Beban Transportasi & Ritase Armada', 'expense', 'debit'],
        ] as [$code, $name, $type, $normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore([
                'account_code' => $code, 'account_name' => $name, 'account_type' => $type,
                'normal_balance' => $normal, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Ledger::post('form_test_fund', 1, 'Modal awal', [
            ['account_code' => '1101', 'debit' => '5000.00', 'credit' => '0.00'],
            ['account_code' => '1102', 'debit' => '5000.00', 'credit' => '0.00'],
            ['account_code' => '3101', 'debit' => '0.00', 'credit' => '10000.00'],
        ]);
    }

    public function test_product_form_persists_unit_category_options_and_supplier_links(): void
    {
        $admin = $this->admin();

        $unit = (int) DB::table('product_options')->where('type', 'unit')->where('name', 'sak')->value('id');
        if (! $unit) {
            $unit = (int) DB::table('product_options')->insertGetId([
                'type' => 'unit', 'name' => 'sak', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $category = (int) DB::table('product_options')->where('type', 'category')->where('name', 'Semen')->value('id');
        if (! $category) {
            $category = (int) DB::table('product_options')->insertGetId([
                'type' => 'category', 'name' => 'Semen', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $supplierA = (int) DB::table('v2_parties')->insertGetId([
            'kind' => 'supplier', 'code' => 'SYN-SUP-A', 'name' => 'PT Semen Maju', 'is_active' => true,
        ]);
        $supplierB = (int) DB::table('v2_parties')->insertGetId([
            'kind' => 'supplier', 'code' => 'SYN-SUP-B', 'name' => 'CV Distribusi Bangunan', 'is_active' => true,
        ]);

        $payload = [
            'sku' => 'SYN-PROD-001',
            'name' => 'Semen Gresik 50kg',
            'unit_id' => $unit,
            'category_id' => $category,
            'cost_price' => '65000.00',
            'selling_price' => '72000.00',
            'min_stock' => '10',
            'supplier_ids' => [$supplierA, $supplierB],
            'primary_supplier_id' => $supplierA,
        ];

        $response = $this->actingAs($admin)->postJson(route('v2.master.store', 'product'), $payload);
        $response->assertOk();
        $productId = $response->json('id');
        $this->assertIsInt($productId);

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'name' => 'Semen Gresik 50kg',
            'unit' => 'sak',
            'category' => 'Semen',
            'unit_id' => $unit,
            'category_id' => $category,
        ]);

        $this->assertDatabaseHas('v2_product_suppliers', [
            'product_id' => $productId,
            'supplier_id' => $supplierA,
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('v2_product_suppliers', [
            'product_id' => $productId,
            'supplier_id' => $supplierB,
            'is_primary' => false,
        ]);
    }

    public function test_receipt_form_validates_that_products_must_be_supplied_by_selected_supplier(): void
    {
        $this->fundAccounts();
        $admin = $this->admin();

        $supplierA = (int) DB::table('v2_parties')->insertGetId([
            'kind' => 'supplier', 'code' => 'SYN-SUP-A', 'name' => 'Supplier Semen', 'is_active' => true,
        ]);
        $supplierB = (int) DB::table('v2_parties')->insertGetId([
            'kind' => 'supplier', 'code' => 'SYN-SUP-B', 'name' => 'Supplier Pasir', 'is_active' => true,
        ]);

        $productA = (int) DB::table('products')->insertGetId([
            'sku' => 'PRD-A', 'name' => 'Semen Tiga Roda', 'unit' => 'sak', 'cost_price' => '60000.00',
            'selling_price' => '70000.00', 'stock_quantity' => '0', 'min_stock' => '0', 'is_active' => true,
        ]);
        $productB = (int) DB::table('products')->insertGetId([
            'sku' => 'PRD-B', 'name' => 'Pasir Cor 1 Truk', 'unit' => 'm3', 'cost_price' => '250000.00',
            'selling_price' => '300000.00', 'stock_quantity' => '0', 'min_stock' => '0', 'is_active' => true,
        ]);

        // Link product A only to supplier A, product B only to supplier B
        DB::table('v2_product_suppliers')->insert([
            ['product_id' => $productA, 'supplier_id' => $supplierA, 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $productB, 'supplier_id' => $supplierB, 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Valid receipt: Supplier A provides Product A
        $validPayload = [
            'supplier_id' => $supplierA,
            'receipt_date' => now()->toDateString(),
            'idempotency_key' => 'syn-rcv-valid-01',
            'items' => [
                ['product_id' => $productA, 'quantity' => '10', 'unit_cost' => '60000'],
            ],
        ];
        $this->actingAs($admin)->postJson(route('v2.receipts.store'), $validPayload)->assertOk();

        // Invalid receipt: Supplier A cannot receive Product B
        $invalidPayload = [
            'supplier_id' => $supplierA,
            'receipt_date' => now()->toDateString(),
            'idempotency_key' => 'syn-rcv-invalid-01',
            'items' => [
                ['product_id' => $productB, 'quantity' => '2', 'unit_cost' => '250000'],
            ],
        ];
        $this->actingAs($admin)->postJson(route('v2.receipts.store'), $invalidPayload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Produk #'.$productB.' belum terhubung ke supplier yang dipilih. Atur relasi produk-supplier pada master terlebih dahulu.');
    }

    public function test_trip_form_rejects_unregistered_driver_and_accepts_registered_driver(): void
    {
        $this->fundAccounts();
        $admin = $this->admin();

        // Open shift for cash trip
        app(Operations::class)->openShift($admin->id, '1000.00', 'admin');

        $vehicle = (int) DB::table('vehicles')->insertGetId([
            'license_plate' => 'B 1234 SYN', 'vehicle_type' => 'pickup', 'status' => 'active',
        ]);
        $driver = (int) DB::table('v2_drivers')->insertGetId([
            'code' => 'DRV-001', 'name' => 'Pak Joko', 'kind' => 'external', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Rejection with unregistered driver
        $this->actingAs($admin)->postJson(route('v2.trips.store'), [
            'vehicle_id' => $vehicle,
            'driver_id' => 99999,
            'date' => now()->toDateString(),
            'destination' => 'Proyek B',
            'fuel_cost' => '50.00',
            'allowance' => '50.00',
            'payment_method' => 'cash',
            'idempotency_key' => 'syn-trip-test-01',
        ])->assertUnprocessable();

        // Success with registered driver
        $this->actingAs($admin)->postJson(route('v2.trips.store'), [
            'vehicle_id' => $vehicle,
            'driver_id' => $driver,
            'date' => now()->toDateString(),
            'destination' => 'Proyek B',
            'fuel_cost' => '50.00',
            'allowance' => '50.00',
            'payment_method' => 'cash',
            'idempotency_key' => 'syn-trip-test-02',
        ])->assertOk();

        $this->assertDatabaseHas('v2_trips', [
            'vehicle_id' => $vehicle,
            'driver_id' => $driver,
            'driver_name' => 'Pak Joko',
        ]);
    }

    public function test_advance_form_with_delivery_link_automatically_inherits_destination(): void
    {
        $this->fundAccounts();
        $admin = $this->admin();

        // Open shift for cash advance
        app(Operations::class)->openShift($admin->id, '1000.00', 'admin');

        $vehicle = (int) DB::table('vehicles')->insertGetId([
            'license_plate' => 'B 5678 SYN', 'vehicle_type' => 'truck', 'status' => 'active',
        ]);
        $driver = (int) DB::table('v2_drivers')->insertGetId([
            'code' => 'DRV-002', 'name' => 'Pak Bambang', 'kind' => 'external', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $saleId = (int) DB::table('v2_sales')->insertGetId([
            'number' => 'SYN-SALE-FORM', 'user_id' => $admin->id,
            'shift_id' => DB::table('v2_shifts')->where('user_id', $admin->id)->value('id'),
            'subtotal' => '0.00', 'total' => '0.00', 'request_hash' => str_repeat('b', 64),
            'payment_method' => 'cash', 'status' => 'paid', 'fulfillment' => 'delivery',
            'idempotency_key' => 'syn-sale-form-key', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $deliveryId = (int) DB::table('v2_deliveries')->insertGetId([
            'sale_id' => $saleId, 'number' => 'SYN-SJ-FORM-001', 'destination' => 'Gudang Cabang Depok',
            'status' => 'prepared', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $payload = [
            'delivery_id' => $deliveryId,
            'vehicle_id' => $vehicle,
            'driver_id' => $driver,
            'amount' => '200.00',
            'method' => 'cash',
            'date' => now()->toDateString(),
            'idempotency_key' => 'syn-advance-form-key',
        ];

        $response = $this->actingAs($admin)->postJson(route('v2.driver-advances.store'), $payload);
        $response->assertOk();

        $this->assertDatabaseHas('v2_driver_advances', [
            'delivery_id' => $deliveryId,
            'driver_id' => $driver,
            'driver_name' => 'Pak Bambang',
            'destination' => 'Gudang Cabang Depok',
        ]);
    }

    public function test_driver_management_allows_registering_external_and_internal_drivers(): void
    {
        $admin = $this->admin();

        $employee = (int) DB::table('v2_parties')->insertGetId([
            'kind' => 'employee', 'code' => 'EMP-01', 'name' => 'Slamet Riyadi', 'is_active' => true,
        ]);

        // Register internal driver
        $resInternal = $this->actingAs($admin)->postJson(route('v2.relations.store', 'driver'), [
            'code' => 'DRV-INT-01',
            'kind' => 'internal',
            'employee_id' => $employee,
            'phone' => '08123456789',
        ]);
        $resInternal->assertOk();
        $intId = $resInternal->json('id');

        // Internal driver automatically takes employee's name
        $this->assertDatabaseHas('v2_drivers', [
            'id' => $intId,
            'kind' => 'internal',
            'employee_id' => $employee,
            'name' => 'Slamet Riyadi',
        ]);

        // Register external driver
        $resExternal = $this->actingAs($admin)->postJson(route('v2.relations.store', 'driver'), [
            'code' => 'DRV-EXT-01',
            'kind' => 'external',
            'name' => 'Hasan Bisri',
            'phone' => '08987654321',
        ]);
        $resExternal->assertOk();
        $extId = $resExternal->json('id');

        $this->assertDatabaseHas('v2_drivers', [
            'id' => $extId,
            'kind' => 'external',
            'employee_id' => null,
            'name' => 'Hasan Bisri',
        ]);
    }
}
