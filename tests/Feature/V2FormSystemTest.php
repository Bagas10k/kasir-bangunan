<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\DemoData;
use App\Services\V2\MasterData;
use App\Support\V2\FormCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionClassConstant;
use Tests\TestCase;

class V2FormSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('RefreshDatabase is only permitted against isolated SQLite :memory:.');
        }
    }

    private function admin(): User
    {
        $user = User::factory()->create(['name' => 'Admin Sintetis Form']);
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => 'admin', 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    public function test_catalog_limits_match_service_validation(): void
    {
        $serviceMax = (new ReflectionClassConstant(MasterData::class, 'MAX_TEXT'))->getValue();
        foreach (['sku', 'code', 'license_plate', 'name', 'notes', 'vehicle_type'] as $key) {
            $this->assertSame($serviceMax[$key], FormCatalog::FIELDS[$key]['max'], "Batas {$key} di katalog harus sama dengan service.");
        }
        $this->assertSame($serviceMax['unit'], FormCatalog::field('option_name', FormCatalog::layout('unit')[0][1][0][2])['max']);
        $this->assertSame($serviceMax['category'], FormCatalog::FIELDS['option_name']['max']);
    }

    public function test_every_master_form_uses_catalog_names_and_known_fields(): void
    {
        $expected = [
            'product' => ['sku', 'name', 'unit_id', 'category_id', 'cost_price', 'selling_price', 'contractor_price', 'min_stock', 'supplier_ids[]'],
            'customer' => ['code', 'name', 'price_tier', 'notes'], 'supplier' => ['code', 'name', 'notes'], 'employee' => ['code', 'name', 'notes'],
            'vehicle' => ['license_plate', 'vehicle_type'],
            'driver' => ['kind', 'employee_id', 'code', 'name', 'phone', 'notes'],
            'unit' => ['name'], 'category' => ['name'],
        ];
        foreach ($expected as $kind => $names) {
            $actual = [];
            foreach (FormCatalog::layout($kind) as $section) {
                foreach ($section[1] as $item) {
                    $actual[] = FormCatalog::field($item[0], $item[2] ?? [])['name'];
                }
            }
            $this->assertSame($names, $actual, "Susunan form {$kind}");
        }
    }

    public function test_next_code_continues_the_numbered_series(): void
    {
        $this->assertSame('PLG-0001', FormCatalog::nextCode('customer', []));
        $this->assertSame('PLG-0013', FormCatalog::nextCode('customer', ['PLG-0012', 'LAMA-99', 'PLG-0003']));
        $this->assertNull(FormCatalog::nextCode('vehicle', ['B 1 X']));
    }

    public function test_vehicle_type_is_a_dropdown_and_preserves_an_existing_custom_type(): void
    {
        $field = FormCatalog::field('vehicle_type');
        $html = $this->blade('<x-ui.field :field="$field" id="vehicle-type" value="Armada sintetis lama"/>', ['field' => $field]);

        $html->assertSee('<select id="vehicle-type" name="vehicle_type"', false)
            ->assertSee('value="Pick-up"', false)
            ->assertSee('value="Armada sintetis lama" selected', false)
            ->assertDontSee('<datalist', false);
    }

    public function test_rendered_master_dialogs_use_the_shared_field_templates(): void
    {
        $admin = $this->admin();
        app(DemoData::class)->seed($admin->id);
        $html = $this->actingAs($admin)->get(route('v2.index'))->assertOk()->getContent();

        $this->assertStringContainsString('value="PLG-0011"', $html, 'Kode pelanggan berikutnya terisi otomatis.');
        $this->assertStringContainsString('data-ui-number="selling_price"', $html);
        $this->assertStringContainsString('data-unit-suffix="unit_id"', $html);
        $this->assertStringContainsString('<input type="radio" name="kind" value="external"', $html);
        $this->assertMatchesRegularExpression('/<div class="uf-section"\s+data-product-suppliers\s*>/', $html);
        $this->assertStringNotContainsString('class="form-grid-2"', $html, 'Form master lama tidak boleh tersisa.');
        foreach (['employee', 'driver'] as $kind) {
            preg_match('/<section\b[^>]*id="master-section-'.$kind.'"[^>]*>/', $html, $host);
            $this->assertStringContainsString('data-dialog-host', $host[0] ?? '');
            $this->assertStringNotContainsString('class="card ', $host[0] ?? '');
            $this->assertStringContainsString('id="popup-add-'.$kind.'"', $html);
        }
        // Supplier produk dipilih lewat satu dropdown; yang sudah tertaut tampil sebagai keping, utama di depan.
        $this->assertStringContainsString('data-ui-multiref', $html);
        $this->assertStringNotContainsString('name="supplier_ids[]" value="" ', $html);
        $dialogOf = function (string $sku) use ($html): array {
            $id = DB::table('products')->where('sku', $sku)->value('id');
            $start = strpos($html, 'id="popup-edit-product-'.$id.'"');

            return [(int) $id, substr($html, $start, strpos($html, '</dialog>', $start) - $start)];
        };

        // Dua supplier: dua keping, dan keping pertama otomatis menjadi utama.
        [, $many] = $dialogOf('PRD-0019');
        preg_match_all('/name="supplier_ids\[\]" value="(\d+)"/', $many, $picked);
        $this->assertCount(2, $picked[1], 'Dua supplier produk tampil sebagai keping.');
        $this->assertStringContainsString('data-multiref-primary value="'.$picked[1][0].'"', $many);

        // Supplier utama selalu keping pertama dan mengisi kolom tersembunyi.
        [$single, $one] = $dialogOf('PRD-0001');
        $primary = (int) DB::table('v2_product_suppliers')->where('product_id', $single)->where('is_primary', true)->value('supplier_id');
        $this->assertGreaterThan(0, $primary);
        preg_match_all('/name="supplier_ids\[\]" value="(\d+)"/', $one, $onePicked);
        $this->assertSame($primary, (int) $onePicked[1][0], 'Supplier utama berada di urutan pertama.');
        $this->assertStringContainsString('data-multiref-primary value="'.$primary.'"', $one);
    }

    public function test_demo_data_is_idempotent_and_removable_without_touching_other_rows(): void
    {
        $admin = $this->admin();
        $own = DB::table('v2_parties')->insertGetId(['kind' => 'customer', 'code' => 'ASLI-1', 'name' => 'Pelanggan Asli', 'is_active' => true]);
        $demo = app(DemoData::class);

        $made = $demo->seed($admin->id);
        $this->assertCount(count(DemoData::PRODUCTS), $made['products']);
        $this->assertCount(count(DemoData::DRIVERS), $made['drivers']);
        $this->assertSame(0, (int) DB::table('products')->where('stock_quantity', '<>', 0)->count(), 'Data contoh tidak membuat stok.');
        $this->assertSame(0, DB::table('journal_entries')->count(), 'Data contoh tidak membuat jurnal.');
        $this->assertSame(2, DB::table('v2_product_suppliers')->where('product_id', DB::table('products')->where('sku', 'PRD-0019')->value('id'))->count());
        $this->assertSame('Budi Hartono', DB::table('v2_drivers')->where('code', 'SPR-0001')->value('name'));

        $again = $demo->seed($admin->id);
        $this->assertSame([], array_merge(...array_values($again)), 'Menjalankan ulang tidak menggandakan data.');

        $result = $demo->remove($made);
        $this->assertSame([], $result['kept']);
        $this->assertSame(0, DB::table('products')->count());
        $this->assertSame(0, DB::table('v2_drivers')->count());
        $this->assertSame([$own], DB::table('v2_parties')->pluck('id')->map(fn ($id) => (int) $id)->all());
    }
}
