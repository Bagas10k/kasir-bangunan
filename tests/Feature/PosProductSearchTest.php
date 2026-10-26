<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PosProductSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function createProduct(string $sku, string $name, float $stock, float $price = 10000.0, string $unit = 'sak'): int
    {
        return DB::table('products')->insertGetId([
            'sku' => $sku,
            'name' => $name,
            'unit' => $unit,
            'cost_price' => 8000.00,
            'selling_price' => $price,
            'stock_quantity' => $stock,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_guest_cannot_access_search_endpoint(): void
    {
        $response = $this->getJson('/pos/products/search?q=semen');
        $response->assertStatus(401);
    }

    public function test_empty_or_single_char_query_returns_empty_data(): void
    {
        $this->createProduct('SKU-001', 'Semen Gresik', 50);

        $resEmpty = $this->actingAs($this->user)->getJson(route('pos.products.search'));
        $resEmpty->assertOk()->assertExactJson(['data' => []]);

        $resOneChar = $this->actingAs($this->user)->getJson(route('pos.products.search', ['q' => 's']));
        $resOneChar->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_search_matches_sku_and_name_case_compatible_and_ordered_by_name(): void
    {
        $this->createProduct('SKU-AAA', 'Cat Tembok Putih', 20, 50000.0, 'kaleng');
        $this->createProduct('SKU-SEMEN-B', 'Semen Tiga Roda', 10, 65000.0, 'sak');
        $this->createProduct('SKU-SEMEN-A', 'Semen Gresik', 25, 60000.0, 'sak');

        // Search by name (case-insensitive)
        $response = $this->actingAs($this->user)->getJson(route('pos.products.search', ['q' => 'semen']));
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals('Semen Gresik', $data[0]['name']);
        $this->assertEquals('Semen Tiga Roda', $data[1]['name']);

        // Check required fields format
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('sku', $data[0]);
        $this->assertArrayHasKey('name', $data[0]);
        $this->assertArrayHasKey('unit', $data[0]);
        $this->assertArrayHasKey('stock_quantity', $data[0]);
        $this->assertArrayHasKey('selling_price', $data[0]);
        $this->assertArrayNotHasKey('cost_price', $data[0]);

        // Search by SKU (case-insensitive)
        $skuResponse = $this->actingAs($this->user)->getJson(route('pos.products.search', ['q' => 'sku-aaa']));
        $skuResponse->assertOk();
        $this->assertCount(1, $skuResponse->json('data'));
        $this->assertEquals('Cat Tembok Putih', $skuResponse->json('data')[0]['name']);
    }

    public function test_search_caps_results_at_ten(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $sku = sprintf('SKU-B%02d', $i);
            $name = sprintf('Batu Bata Merah %02d', $i);
            $this->createProduct($sku, $name, 100);
        }

        $response = $this->actingAs($this->user)->getJson(route('pos.products.search', ['q' => 'Batu']));
        $response->assertOk();
        $this->assertCount(10, $response->json('data'));
    }

    public function test_pos_index_paginates_products_twenty_per_page_with_product_page_param(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $sku = sprintf('PRD-%02d', $i);
            $name = sprintf('Item %02d', $i);
            $this->createProduct($sku, $name, 50);
        }

        $response = $this->actingAs($this->user)->get(route('pos.index', ['product_page' => 2]));
        $response->assertOk();

        $products = $response->viewData('products');
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $products);
        $this->assertEquals(2, $products->currentPage());
        $this->assertEquals(20, $products->perPage());
        $this->assertEquals(25, $products->total());
        $this->assertCount(5, $products->items());
    }

    public function test_pos_index_filters_by_product_q_and_product_stock(): void
    {
        $this->createProduct('SKU-AVAIL', 'Besi Beton 10mm', 50);
        $this->createProduct('SKU-LOW', 'Besi Begel 8mm', 5);
        $this->createProduct('SKU-OUT', 'Kawat Bendrat', 0);

        // Filter product_q
        $resQ = $this->actingAs($this->user)->get(route('pos.index', ['product_q' => 'Besi']));
        $resQ->assertOk();
        $this->assertEquals(2, $resQ->viewData('products')->total());

        // Filter product_stock = available
        $resAvail = $this->actingAs($this->user)->get(route('pos.index', ['product_stock' => 'available']));
        $resAvail->assertOk();
        $this->assertEquals(2, $resAvail->viewData('products')->total());

        // Filter product_stock = low (<= 10 and > 0)
        $resLow = $this->actingAs($this->user)->get(route('pos.index', ['product_stock' => 'low']));
        $resLow->assertOk();
        $this->assertEquals(1, $resLow->viewData('products')->total());
        $this->assertEquals('Besi Begel 8mm', $resLow->viewData('products')->items()[0]->name);

        // Filter product_stock = out (<= 0)
        $resOut = $this->actingAs($this->user)->get(route('pos.index', ['product_stock' => 'out']));
        $resOut->assertOk();
        $this->assertEquals(1, $resOut->viewData('products')->total());
        $this->assertEquals('Kawat Bendrat', $resOut->viewData('products')->items()[0]->name);
    }

    public function test_pos_index_filters_by_custom_product_category(): void
    {
        $semenId = $this->createProduct('SKU-SEMEN', 'Semen Portland', 20);
        DB::table('products')->where('id', $semenId)->update(['category' => 'Semen']);
        $catId = $this->createProduct('SKU-CAT', 'Cat Tembok', 15);
        DB::table('products')->where('id', $catId)->update(['category' => 'Cat']);

        $response = $this->actingAs($this->user)->get(route('pos.index', ['product_category' => 'Semen']));

        $response->assertOk();
        $this->assertSame('Semen', $response->viewData('productCategory'));
        $this->assertSame(['Semen Portland'], collect($response->viewData('products')->items())->pluck('name')->all());
        $this->assertContains('Semen', $response->viewData('categories')->all());
        $this->assertContains('Cat', $response->viewData('categories')->all());

        $search = $this->actingAs($this->user)->getJson(route('pos.products.search', ['q' => 'e', 'category' => 'Semen']));
        $search->assertOk()->assertExactJson(['data' => []]);
        $search = $this->actingAs($this->user)->getJson(route('pos.products.search', ['q' => 'Sem', 'category' => 'Semen']));
        $search->assertOk()->assertJsonPath('data.0.name', 'Semen Portland');
        $search->assertJsonCount(1, 'data');
    }

    public function test_initial_pos_does_not_load_all_products_with_query_limit(): void
    {
        for ($i = 1; $i <= 35; $i++) {
            $sku = sprintf('P-%02d', $i);
            $name = sprintf('Produk Massal %02d', $i);
            $this->createProduct($sku, $name, 100);
        }

        DB::enableQueryLog();

        $response = $this->actingAs($this->user)->get(route('pos.index'));
        $response->assertOk();

        $queries = DB::getQueryLog();
        $productSelectQuery = null;
        foreach ($queries as $q) {
            $sql = strtolower($q['query']);
            if (str_contains($sql, 'products') && str_contains($sql, 'select') && str_contains($sql, 'limit')) {
                $productSelectQuery = $q;
                break;
            }
        }

        $this->assertNotNull($productSelectQuery, 'Expected a bounded SELECT query with LIMIT on products table');
        $this->assertCount(20, $response->viewData('products')->items());
        $this->assertEquals(35, $response->viewData('products')->total());
    }
}
