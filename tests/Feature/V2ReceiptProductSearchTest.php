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

    public function test_restock_list_shows_store_wide_critical_stock_without_a_supplier(): void
    {
        $admin = $this->actor('admin');
        $low = $this->product('A Menipis Sintetis', 'SYN-LOW');
        $out = $this->product('Z Habis Sintetis', 'SYN-OUT');
        $zero = $this->product('Y Nol Sintetis', 'SYN-ZERO');
        $this->product('Normal Sintetis', 'SYN-OK');
        $inactive = $this->product('Arsip Sintetis', 'SYN-ARCH', false);
        foreach ([$low => '1', $out => '0', $zero => '0', $inactive => '0'] as $id => $stock) {
            DB::table('products')->where('id', $id)->update(['stock_quantity' => $stock]);
        }
        DB::table('products')->where('id', $zero)->update(['min_stock' => '0']);
        $supplier = $this->supplier('Supplier Sintetis Restok');
        $this->link($low, $supplier);

        $response = $this->actingAs($admin)->getJson('/v2/receipts/restock-products')
            ->assertOk()->assertJsonPath('total', 3)
            ->assertJsonPath('counts', ['critical' => 3, 'out' => 2, 'low' => 1])
            ->assertJsonPath('results.0.status', 'out')
            ->assertJsonPath('results.2.status', 'low');

        $this->assertSame([$zero, $out, $low], array_column($response->json('results'), 'id'));
        $this->assertSame([], $response->json('results.0.suppliers'));
        $this->assertSame([['id' => $supplier, 'name' => 'Supplier Sintetis Restok']], $response->json('results.2.suppliers'));
        $this->assertSame('1', $response->json('results.2.minimum'));
        $this->assertDatabaseCount('inventory_receipts', 0);
        $this->assertDatabaseCount('v2_invoices', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_inactive_supplier_and_non_admin_roles_get_nothing(): void
    {
        $supplier = $this->supplier('PT Nonaktif', false);
        $this->link($this->product('Barang Nonaktif', 'SYN-OFF-01'), $supplier);

        $this->actingAs($this->actor('admin'))->getJson(route('v2.suppliers.products', ['id' => $supplier]))
            ->assertOk()->assertJson(['total' => 0, 'results' => []]);

        $this->actingAs($this->actor('cashier'))->getJson(route('v2.suppliers.products', ['id' => $supplier]))->assertForbidden();
    }

    public function test_restock_filters_search_and_supplier_without_duplicate_products(): void
    {
        $admin = $this->actor('admin');
        $supplier = $this->supplier('Supplier A Sintetis');
        $other = $this->supplier('Supplier B Sintetis');
        $inactive = $this->supplier('Supplier Arsip Sintetis', false);
        $low = $this->product('Semen Sintetis', 'SYN-REST-LOW');
        $out = $this->product('Semen Habis Sintetis', 'SYN-REST-OUT');
        $unlinked = $this->product('Barang Bebas Sintetis', 'SYN-REST-FREE');
        foreach ([$low => '0.5', $out => '0', $unlinked => '0'] as $id => $stock) {
            DB::table('products')->where('id', $id)->update(['stock_quantity' => $stock]);
        }
        $this->link($low, $supplier);
        $this->link($low, $other);
        $this->link($low, $inactive);
        $this->link($out, $supplier);

        $response = $this->actingAs($admin)->getJson(route('v2.receipts.restock-products', [
            'supplier_id' => $supplier, 'status' => 'low', 'q' => 'SYN-REST',
        ]))->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('counts', ['critical' => 2, 'out' => 1, 'low' => 1])
            ->assertJsonPath('results.0.stock', '0.5');

        $this->assertSame([$low], array_column($response->json('results'), 'id'));
        $this->assertSame([$supplier, $other], array_column($response->json('results.0.suppliers'), 'id'));
        $this->actingAs($admin)->getJson(route('v2.receipts.restock-products', ['status' => 'out', 'q' => 'Habis']))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('results.0.id', $out);
    }

    public function test_restock_pagination_reaches_all_results_and_clamps_a_stale_page(): void
    {
        $admin = $this->actor('admin');
        foreach (range(1, 35) as $n) {
            $id = $this->product(sprintf('Barang Sintetis %02d', $n), 'SYN-PAGE-'.$n);
            DB::table('products')->where('id', $id)->update(['stock_quantity' => '0']);
        }
        $first = $this->actingAs($admin)->getJson(route('v2.receipts.restock-products'))->assertOk()
            ->assertJsonPath('total', 35)->assertJsonPath('last_page', 2)->json('results');
        $second = $this->getJson(route('v2.receipts.restock-products', ['page' => 2]))->assertOk()->json('results');

        $this->assertCount(20, $first);
        $this->assertCount(15, $second);
        $this->assertSame([], array_intersect(array_column($first, 'id'), array_column($second, 'id')));
        $this->getJson(route('v2.receipts.restock-products', ['page' => 999]))->assertOk()->assertJsonPath('page', 2);
    }

    public function test_restock_requires_an_active_admin_and_valid_bounded_filters(): void
    {
        $url = route('v2.receipts.restock-products');
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($this->actor('cashier'))->getJson($url)->assertForbidden();
        $admin = $this->actor('admin');
        $this->actingAs($admin)->getJson($url.'?'.http_build_query([
            'q' => str_repeat('x', 101), 'status' => 'all', 'page' => 0, 'supplier_id' => 999999,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['q', 'status', 'page', 'supplier_id']);
        DB::table('users')->where('id', $admin->id)->update(['is_active' => false]);
        $this->actingAs($admin->fresh())->getJson($url)->assertUnauthorized();
    }

    public function test_supplier_picker_includes_stock_status_and_minimum_without_widening_scope(): void
    {
        $supplier = $this->supplier('Supplier Pemilih Sintetis');
        $id = $this->product('Barang Menipis Sintetis', 'SYN-PICK-LOW');
        DB::table('products')->where('id', $id)->update(['stock_quantity' => '0.25']);
        $this->link($id, $supplier);

        $this->actingAs($this->actor('admin'))->getJson(route('v2.suppliers.products', ['id' => $supplier]))
            ->assertOk()->assertJsonPath('results.0.status', 'low')->assertJsonPath('results.0.minimum', '1');
    }
}
