<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pemilih barang di form penerimaan: dicari di server, hanya barang aktif yang tertaut ke supplier aktif.
 * Data sintetis, SQLite :memory:.
 */
class V2ReceiptProductSearchTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('V2 receipt search tests require isolated SQLite :memory:.');
        }
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function supplier(string $name, bool $active = true): int
    {
        return (int) DB::table('v2_parties')->insertGetId([
            'kind' => 'supplier', 'code' => 'SYN-SUP-'.++$this->seq, 'name' => $name,
            'is_active' => $active, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function product(string $name, string $sku, bool $active = true): int
    {
        return (int) DB::table('products')->insertGetId([
            'sku' => $sku, 'name' => $name, 'unit' => 'sak', 'category' => 'Umum',
            'cost_price' => '52000.0000', 'selling_price' => '60000.00', 'stock_quantity' => '12.5000', 'min_stock' => '1.0000',
            'is_active' => $active, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function link(int $productId, int $supplierId): void
    {
        DB::table('v2_product_suppliers')->insert(['product_id' => $productId, 'supplier_id' => $supplierId, 'is_primary' => false, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_search_returns_only_active_products_linked_to_the_supplier_by_name_or_sku(): void
    {
        $admin = $this->actor('admin');
        $supplier = $this->supplier('PT Semen Sintetis');
        $other = $this->supplier('CV Pasir Sintetis');
        $cement = $this->product('Semen Sintetis 50kg', 'SYN-SMN-01');
        $sand = $this->product('Pasir Sintetis', 'SYN-PSR-01');
        $archived = $this->product('Semen Arsip', 'SYN-SMN-99', false);
        $unlinked = $this->product('Semen Milik Supplier Lain', 'SYN-SMN-77');
        $this->link($cement, $supplier);
        $this->link($sand, $supplier);
        $this->link($archived, $supplier);
        $this->link($unlinked, $other);

        $all = $this->actingAs($admin)->getJson(route('v2.suppliers.products', ['id' => $supplier]))->assertOk()->json();
        $this->assertSame(2, $all['total']);
        $this->assertSame(['Pasir Sintetis', 'Semen Sintetis 50kg'], array_column($all['results'], 'name'));
        $row = $all['results'][1];
        $this->assertSame([$cement, 'SYN-SMN-01', 'Semen Sintetis 50kg', 'sak'], [$row['id'], $row['sku'], $row['name'], $row['unit']]);
        $this->assertEquals([12.5, 52000], [$row['stock'], $row['cost']]);

        $byName = $this->actingAs($admin)->getJson(route('v2.suppliers.products', ['id' => $supplier, 'q' => 'semen']))->assertOk()->json();
        $this->assertSame([$cement], array_column($byName['results'], 'id'));

        $bySku = $this->actingAs($admin)->getJson(route('v2.suppliers.products', ['id' => $supplier, 'q' => 'PSR-01']))->assertOk()->json();
        $this->assertSame([$sand], array_column($bySku['results'], 'id'));

        $none = $this->actingAs($admin)->getJson(route('v2.suppliers.products', ['id' => $supplier, 'q' => 'tidak-ada']))->assertOk()->json();
        $this->assertSame(['total' => 0, 'results' => []], $none);
    }

    public function test_results_are_capped_but_total_reports_every_match(): void
    {
        $admin = $this->actor('admin');
        $supplier = $this->supplier('PT Banyak Barang');
        foreach (range(1, 35) as $n) {
            $this->link($this->product(sprintf('Paku Sintetis %02d', $n), 'SYN-PKU-'.$n), $supplier);
        }

        $page = $this->actingAs($admin)->getJson(route('v2.suppliers.products', ['id' => $supplier]))->assertOk()->json();

        $this->assertSame(35, $page['total']);
        $this->assertCount(30, $page['results']);
    }

    public function test_inactive_supplier_and_non_admin_roles_get_nothing(): void
    {
        $supplier = $this->supplier('PT Nonaktif', false);
        $this->link($this->product('Barang Nonaktif', 'SYN-OFF-01'), $supplier);

        $this->actingAs($this->actor('admin'))->getJson(route('v2.suppliers.products', ['id' => $supplier]))
            ->assertOk()->assertJson(['total' => 0, 'results' => []]);

        $this->actingAs($this->actor('cashier'))->getJson(route('v2.suppliers.products', ['id' => $supplier]))->assertForbidden();
    }
}
