<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('RefreshDatabase is only permitted against isolated SQLite :memory:.');
        }
    }

    public function test_guest_cannot_manage_options(): void
    {
        $id = DB::table('product_options')->where('type', 'unit')->value('id');
        $this->postJson(route('products.options.store'), ['type' => 'unit', 'name' => 'batang'])->assertUnauthorized();
        $this->deleteJson(route('products.options.destroy', $id))->assertUnauthorized();
        $this->assertDatabaseHas('product_options', ['id' => $id]);
    }

    public function test_options_persist_and_unused_options_can_be_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['unit' => 'batang', 'category' => 'Besi'] as $type => $name) {
            $response = $this->postJson(route('products.options.store'), ['type' => $type, 'name' => ' '.$name.' '])->assertCreated();
            $id = $response->json('id');
            $this->assertDatabaseHas('product_options', ['id' => $id, 'name' => $name, 'type' => $type]);
            $page = $this->get(route('products.index'))->assertOk();
            $this->assertContains($name, array_column($page->viewData('productOptions')[$type], 'name'));
            $this->deleteJson(route('products.options.destroy', $id))->assertOk();
            $this->assertDatabaseMissing('product_options', ['id' => $id]);
        }
    }

    public function test_used_options_including_inactive_products_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        DB::table('products')->insert([
            'sku' => 'SYN-OPTION', 'name' => 'Material Sintetis', 'unit' => 'sak', 'category' => 'Umum',
            'cost_price' => 0, 'selling_price' => 0, 'stock_quantity' => 0, 'min_stock' => 0, 'is_active' => false,
        ]);
        foreach (['unit', 'category'] as $type) {
            $id = DB::table('product_options')->where('type', $type)->value('id');
            $this->deleteJson(route('products.options.destroy', $id))->assertUnprocessable();
            $this->assertDatabaseHas('product_options', ['id' => $id]);
            $page = $this->get(route('products.index'))->assertOk();
            $this->assertTrue($page->viewData('productOptions')[$type][0]['used']);
        }
    }

    public function test_option_validation_and_missing_delete(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([
            [['type' => 'invalid', 'name' => 'test'], 'type'],
            [['type' => 'unit', 'name' => '   '], 'name'],
            [['type' => 'unit', 'name' => str_repeat('a', 21)], 'name'],
            [['type' => 'category', 'name' => str_repeat('a', 51)], 'name'],
            [['type' => 'unit', 'name' => 'sak'], 'name'],
        ] as [$data, $error]) {
            $this->postJson(route('products.options.store'), $data)->assertUnprocessable()->assertJsonValidationErrors($error);
        }
        $this->deleteJson(route('products.options.destroy', 99999))->assertNotFound();
    }
}
