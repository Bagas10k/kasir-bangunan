<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MasterDataViewTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Master data view tests require isolated SQLite :memory:.');
        }
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    public function test_products_page_exposes_dense_management_controls_and_exact_values(): void
    {
        DB::table('products')->insert([
            'sku' => 'SYN-VIEW-001',
            'name' => 'Material Sintetis Presisi',
            'unit' => 'kg',
            'category' => 'Agregat',
            'cost_price' => '123456789.9999',
            'selling_price' => '9876543210.99',
            'stock_quantity' => '2.5000',
            'min_stock' => '5.0000',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user())->get(route('products.index'));

        $response->assertOk()
            ->assertSee('master-summary-strip', false)
            ->assertSee('Total Katalog')
            ->assertSee('Produk Aktif')
            ->assertSee('Stok Menipis')
            ->assertSee('Stok Habis')
            ->assertSee('name="search"', false)
            ->assertSee('name="category"', false)
            ->assertSee('name="unit"', false)
            ->assertSee('name="status"', false)
            ->assertSee('name="stock"', false)
            ->assertSee('name="per_page"', false)
            ->assertSee('Kategori')
            ->assertSee('Minimum Stok')
            ->assertSee('Status')
            ->assertSee('123.456.789,9999')
            ->assertSee('9.876.543.210,99')
            ->assertSee('data-category="Agregat"', false)
            ->assertSee('data-min-stock="5"', false)
            ->assertSee('data-label="Produk"', false)
            ->assertSee('data-label="Stok / Minimum"', false)
            ->assertSee('data-active="1"', false);
    }

    public function test_vehicles_page_exposes_operational_summary_filters_history_and_actions(): void
    {
        $vehicleId = DB::table('vehicles')->insertGetId([
            'license_plate' => 'SYN 1001 AA',
            'vehicle_type' => 'truck',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ritases')->insert([
            'ritase_number' => 'SYN-RIT-001',
            'vehicle_id' => $vehicleId,
            'driver_name' => 'Pengemudi Sintetis',
            'ritase_date' => now()->toDateString(),
            'destination' => 'Zona Sintetis',
            'total_cost' => '100.00',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user())->get(route('vehicles.index'));

        $response->assertOk()
            ->assertSee('master-summary-strip', false)
            ->assertSee('Total Armada')
            ->assertSee('Siap Jalan')
            ->assertSee('Dalam Perawatan')
            ->assertSee('Nonaktif')
            ->assertSee('name="search"', false)
            ->assertSee('name="status"', false)
            ->assertSee('name="per_page"', false)
            ->assertSee('Total Ritase')
            ->assertSee('Aksi')
            ->assertSee('Ubah')
            ->assertSee(route('ritase.index', ['vehicle_id' => $vehicleId]), false)
            ->assertSee('data-label="Kendaraan"', false)
            ->assertSee('data-label="Total Ritase"', false)
            ->assertSee('data-ritase-count="1"', false);
    }

    public function test_coa_page_exposes_summary_filters_status_exact_balance_and_journal_drilldown(): void
    {
        $this->actingAs($this->user())->get(route('coa.index'))->assertOk();
        $journalId = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'synthetic',
            'reference_id' => 1,
            'description' => 'Jurnal master data sintetis',
            'transaction_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            ['journal_entry_id' => $journalId, 'account_code' => '1101', 'account_name' => 'Kas', 'debit' => '123456789.99', 'credit' => '0.00', 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $journalId, 'account_code' => '3101', 'account_name' => 'Modal', 'debit' => '0.00', 'credit' => '123456789.99', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($this->user())->get(route('coa.index', ['search' => '1101']));

        $response->assertOk()
            ->assertSee('master-summary-strip', false)
            ->assertSee('Total Akun')
            ->assertSee('Akun Aktif')
            ->assertSee('Akun Arsip')
            ->assertSee('Sudah Digunakan')
            ->assertSee('name="search"', false)
            ->assertSee('name="status"', false)
            ->assertSee('name="usage"', false)
            ->assertSee('name="per_page"', false)
            ->assertSee('123.456.789,99')
            ->assertSee('Lihat Jurnal')
            ->assertSee(route('journals.index', ['account' => '1101']), false)
            ->assertSee('data-label="Akun"', false)
            ->assertSee('data-label="Saldo (Rp)"', false)
            ->assertSee('data-system="1"', false)
            ->assertSee('data-active="1"', false);
    }
}
