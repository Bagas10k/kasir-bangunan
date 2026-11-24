<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\MasterData;
use App\Services\V2\MasterRelations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PharData;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class V2MasterRelationsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new RuntimeException('V2 relation tests require isolated SQLite :memory:.');
        }
    }

    private function actor(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert([
            'user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }

    private function party(User $admin, string $kind, string $code): int
    {
        return app(MasterData::class)->store($admin->id, $kind, ['code' => $code, 'name' => 'Pihak Sintetis '.$code]);
    }

    private function product(array $overrides = []): array
    {
        return array_replace([
            'sku' => 'SYN-REL-01', 'name' => 'Material Sintetis', 'unit' => 'sak', 'category' => 'Umum',
            'cost_price' => '10000', 'selling_price' => '12000', 'min_stock' => '0',
        ], $overrides);
    }

    #[TestWith(['driver', ['code' => 'SYN-DRV-AUTH', 'kind' => 'external', 'name' => 'Sopir Sintetis']])]
    #[TestWith(['unit', ['name' => 'palet']])]
    #[TestWith(['category', ['name' => 'Bahan Sintetis']])]
    public function test_new_masters_require_authentication_and_admin_role(string $kind, array $payload): void
    {
        $this->post(route('v2.relations.store', $kind), $payload)->assertRedirect(route('login'));
        $admin = $this->actor();
        $id = app(MasterRelations::class)->store($admin->id, $kind, $payload);
        $table = $kind === 'driver' ? 'v2_drivers' : 'product_options';
        $before = (array) DB::table($table)->where('id', $id)->first();

        $this->actingAs($this->actor('cashier'));
        $this->postJson(route('v2.relations.store', $kind), $payload)->assertForbidden();
        $this->putJson(route('v2.relations.update', [$kind, $id]), $payload)->assertForbidden();
        $this->patchJson(route('v2.relations.archive', [$kind, $id]))->assertForbidden();

        $this->assertSame($before, (array) DB::table($table)->where('id', $id)->first());
    }

    public function test_internal_driver_uses_current_employee_name_and_is_archived_without_deletion(): void
    {
        $admin = $this->actor();
        $this->actingAs($admin);
        $this->postJson(route('v2.master.store', 'employee'), [
            'code' => 'SYN-EMP-01', 'name' => 'Pihak Sintetis SYN-EMP-01',
        ])->assertOk();
        $employee = (int) DB::table('v2_parties')->where('kind', 'employee')->where('code', 'SYN-EMP-01')->value('id');

        $id = $this->postJson(route('v2.relations.store', 'driver'), [
            'code' => 'SYN-DRV-01', 'kind' => 'internal', 'employee_id' => $employee,
            'name' => 'Nama yang tidak dipercaya', 'notes' => 'Rute sintetis',
        ])->assertOk()->json('id');
        $this->assertDatabaseHas('v2_drivers', [
            'id' => $id, 'employee_id' => $employee, 'name' => 'Pihak Sintetis SYN-EMP-01', 'is_active' => true,
        ]);

        app(MasterData::class)->update($admin->id, 'employee', $employee, ['code' => 'SYN-EMP-01', 'name' => 'Pegawai Sintetis Baru']);
        $this->assertSame('Pegawai Sintetis Baru', app(MasterRelations::class)->records($admin->id, 'driver')->first()->name);
        $resolved = DB::transaction(fn () => app(MasterRelations::class)->driver($id));
        $this->assertSame('Pegawai Sintetis Baru', $resolved->name);
        $this->assertSame($id, (int) $resolved->id);

        $this->patchJson(route('v2.relations.archive', ['driver', $id]))->assertOk()->assertJsonPath('status', 'archived');
        $this->putJson(route('v2.relations.update', ['driver', $id]), [
            'code' => 'SYN-DRV-01', 'kind' => 'internal', 'employee_id' => $employee, 'is_active' => true,
        ])->assertOk();
        $this->assertDatabaseHas('v2_drivers', ['id' => $id, 'is_active' => false, 'notes' => 'Rute sintetis']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sopir tidak aktif atau tidak ditemukan.');
        DB::transaction(fn () => app(MasterRelations::class)->driver($id));
    }

    public function test_external_driver_can_be_created_renamed_and_resolved_without_employee(): void
    {
        $admin = $this->actor();
        $this->actingAs($admin);
        $id = $this->postJson(route('v2.relations.store', 'driver'), [
            'code' => 'SYN-EXT-01', 'kind' => 'external', 'name' => 'Sopir Eksternal Sintetis',
        ])->assertOk()->json('id');

        $this->putJson(route('v2.relations.update', ['driver', $id]), [
            'code' => 'SYN-EXT-01', 'kind' => 'external', 'name' => 'Sopir Eksternal Baru',
        ])->assertOk();

        $resolved = DB::transaction(fn () => app(MasterRelations::class)->driver($id));
        $this->assertSame('Sopir Eksternal Baru', $resolved->name);
        $this->assertNull($resolved->employee_id);
    }

    public function test_driver_kind_switch_clears_or_requires_employee_link_explicitly(): void
    {
        $admin = $this->actor();
        $employee = $this->party($admin, 'employee', 'SYN-EMP-SWITCH');
        $this->actingAs($admin);
        $id = $this->postJson(route('v2.relations.store', 'driver'), [
            'code' => 'SYN-DRV-SWITCH', 'kind' => 'internal', 'employee_id' => $employee,
        ])->assertOk()->json('id');

        // UI mengirim employee_id lama saat jenis diganti: server harus melepasnya, bukan gagal.
        $this->putJson(route('v2.relations.update', ['driver', $id]), [
            'code' => 'SYN-DRV-SWITCH', 'kind' => 'external', 'name' => 'Sopir Luar Sintetis', 'employee_id' => $employee,
        ])->assertOk();
        $this->assertDatabaseHas('v2_drivers', ['id' => $id, 'kind' => 'external', 'employee_id' => null, 'name' => 'Sopir Luar Sintetis']);

        // Kembali ke internal wajib menyebut pegawai; tanpa itu ditolak dan baris tetap external.
        $this->putJson(route('v2.relations.update', ['driver', $id]), [
            'code' => 'SYN-DRV-SWITCH', 'kind' => 'internal',
        ])->assertUnprocessable();
        $this->assertDatabaseHas('v2_drivers', ['id' => $id, 'kind' => 'external', 'employee_id' => null]);

        $this->putJson(route('v2.relations.update', ['driver', $id]), [
            'code' => 'SYN-DRV-SWITCH', 'kind' => 'internal', 'employee_id' => $employee,
        ])->assertOk();
        $this->assertDatabaseHas('v2_drivers', ['id' => $id, 'kind' => 'internal', 'employee_id' => $employee, 'name' => 'Pihak Sintetis SYN-EMP-SWITCH']);
    }

    #[TestWith(['missing'])]
    #[TestWith(['wrong-kind'])]
    #[TestWith(['inactive'])]
    #[TestWith(['unknown'])]
    #[TestWith(['malformed'])]
    public function test_internal_driver_rejects_invalid_employee_relation(string $case): void
    {
        $admin = $this->actor();
        $employee = $this->party($admin, $case === 'wrong-kind' ? 'supplier' : 'employee', 'SYN-EMP-INVALID');
        if ($case === 'inactive') {
            app(MasterData::class)->archive($admin->id, 'employee', $employee);
        }
        $employeeId = match ($case) {
            'missing' => null,
            'unknown' => 999999,
            'malformed' => $employee.'x',
            default => $employee,
        };

        $this->actingAs($admin)->postJson(route('v2.relations.store', 'driver'), [
            'code' => 'SYN-DRV-INVALID', 'kind' => 'internal', 'employee_id' => $employeeId,
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('v2_drivers', ['code' => 'SYN-DRV-INVALID']);
    }

    public function test_driver_rejects_external_employee_duplicate_employee_and_duplicate_code(): void
    {
        $admin = $this->actor();
        $employee = $this->party($admin, 'employee', 'SYN-EMP-UNIQUE');
        $this->actingAs($admin);
        $payload = ['code' => 'SYN-DRV-UNIQUE', 'kind' => 'internal', 'employee_id' => $employee];
        $this->postJson(route('v2.relations.store', 'driver'), $payload)->assertOk();

        $this->postJson(route('v2.relations.store', 'driver'), array_replace($payload, [
            'code' => 'SYN-DRV-OTHER', 'kind' => 'external', 'name' => 'Sopir Sintetis',
        ]))->assertUnprocessable()->assertJsonPath('message', 'Sopir eksternal tidak boleh terhubung ke pegawai.');
        $this->postJson(route('v2.relations.store', 'driver'), array_replace($payload, [
            'code' => 'SYN-DRV-OTHER',
        ]))->assertUnprocessable()->assertJsonPath('message', 'Pegawai sudah terdaftar sebagai sopir.');
        $this->postJson(route('v2.relations.store', 'driver'), [
            'code' => 'SYN-DRV-UNIQUE', 'kind' => 'external', 'name' => 'Sopir Lain',
        ])->assertUnprocessable()->assertJsonPath('message', 'Kode sopir sudah terdaftar.');
        $this->assertDatabaseCount('v2_drivers', 1);
    }

    public function test_driver_resolver_rejects_an_archived_employee(): void
    {
        $admin = $this->actor();
        $employee = $this->party($admin, 'employee', 'SYN-EMP-ARCHIVE');
        $driver = app(MasterRelations::class)->store($admin->id, 'driver', [
            'code' => 'SYN-DRV-ARCHIVE', 'kind' => 'internal', 'employee_id' => $employee,
        ]);
        app(MasterData::class)->archive($admin->id, 'employee', $employee);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Pegawai sopir tidak aktif atau tidak ditemukan.');
        DB::transaction(fn () => app(MasterRelations::class)->driver($driver));
    }

    public function test_product_http_writes_normalize_options_and_manage_multiple_suppliers(): void
    {
        $admin = $this->actor();
        $service = app(MasterData::class);
        $unit = app(MasterRelations::class)->store($admin->id, 'unit', ['name' => 'palet']);
        $category = app(MasterRelations::class)->store($admin->id, 'category', ['name' => 'Bahan Sintetis']);
        $first = $this->party($admin, 'supplier', 'SYN-SUP-A');
        $second = $this->party($admin, 'supplier', 'SYN-SUP-B');
        $this->actingAs($admin);
        $payload = $this->product([
            'unit_id' => $unit, 'category_id' => $category, 'unit' => 'teks tidak dipercaya',
            'category' => 'teks tidak dipercaya', 'supplier_ids' => [$first, $second], 'primary_supplier_id' => $second,
        ]);

        $id = $this->postJson(route('v2.master.store', 'product'), $payload)->assertOk()->json('id');

        $this->assertDatabaseHas('products', ['id' => $id, 'unit_id' => $unit, 'unit' => 'palet', 'category_id' => $category, 'category' => 'Bahan Sintetis']);
        $this->assertDatabaseHas('v2_product_suppliers', ['product_id' => $id, 'supplier_id' => $first, 'is_primary' => false]);
        $this->assertDatabaseHas('v2_product_suppliers', ['product_id' => $id, 'supplier_id' => $second, 'is_primary' => true]);
        $this->getJson(route('v2.master', 'product'))->assertOk()
            ->assertJsonPath('records.0.supplier_ids', [$first, $second])->assertJsonPath('records.0.primary_supplier_id', $second);

        unset($payload['supplier_ids'], $payload['primary_supplier_id']);
        $this->putJson(route('v2.master.update', ['product', $id]), $payload)->assertOk();
        $this->assertDatabaseCount('v2_product_suppliers', 2);
        $this->assertDatabaseHas('v2_product_suppliers', ['product_id' => $id, 'supplier_id' => $second, 'is_primary' => true]);

        $this->putJson(route('v2.master.update', ['product', $id]), $payload + ['primary_supplier_id' => $first])->assertOk();
        $this->assertDatabaseHas('v2_product_suppliers', ['product_id' => $id, 'supplier_id' => $first, 'is_primary' => true]);
        $this->assertDatabaseHas('v2_product_suppliers', ['product_id' => $id, 'supplier_id' => $second, 'is_primary' => false]);
        $this->putJson(route('v2.master.update', ['product', $id]), $payload + ['supplier_ids' => []])->assertOk();
        $this->assertDatabaseCount('v2_product_suppliers', 0);
    }

    #[TestWith(['unit', 'wrong-type'])]
    #[TestWith(['category', 'wrong-type'])]
    #[TestWith(['unit', 'inactive'])]
    #[TestWith(['category', 'inactive'])]
    #[TestWith(['unit', 'unknown'])]
    #[TestWith(['unit', 'malformed'])]
    public function test_product_rejects_invalid_option_ids_without_creating_options(string $kind, string $case): void
    {
        $admin = $this->actor();
        $service = app(MasterData::class);
        $type = $case === 'wrong-type' ? ($kind === 'unit' ? 'category' : 'unit') : $kind;
        $option = app(MasterRelations::class)->store($admin->id, $type, ['name' => 'Opsi Sintetis']);
        if ($case === 'inactive') {
            app(MasterRelations::class)->archive($admin->id, $kind, $option);
        }
        $count = DB::table('product_options')->count();
        $id = match ($case) {
            'unknown' => 999999,
            'malformed' => $option.'x',
            default => $option,
        };

        $this->actingAs($admin)->postJson(route('v2.master.store', 'product'), $this->product([
            $kind.'_id' => $id, 'unit' => 'opsi harus rollback', 'category' => 'kategori harus rollback',
        ]))->assertUnprocessable();

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_options', $count);
    }

    #[TestWith(['wrong-kind'])]
    #[TestWith(['inactive'])]
    #[TestWith(['unknown'])]
    #[TestWith(['malformed'])]
    #[TestWith(['not-array'])]
    #[TestWith(['primary-not-member'])]
    public function test_invalid_supplier_mapping_rolls_back_product_and_new_options(string $case): void
    {
        $admin = $this->actor();
        $supplier = $this->party($admin, $case === 'wrong-kind' ? 'customer' : 'supplier', 'SYN-SUP-INVALID');
        if ($case === 'inactive') {
            app(MasterData::class)->archive($admin->id, 'supplier', $supplier);
        }
        $count = DB::table('product_options')->count();
        $supplierIds = match ($case) {
            'unknown' => [999999],
            'malformed' => [$supplier.'x'],
            'not-array' => (string) $supplier,
            default => [$supplier],
        };

        $this->actingAs($admin)->postJson(route('v2.master.store', 'product'), $this->product([
            'unit' => 'karung sintetis', 'category' => 'Material sintetis', 'supplier_ids' => $supplierIds,
            'primary_supplier_id' => $case === 'primary-not-member' ? 999999 : null,
        ]))->assertUnprocessable();

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('v2_product_suppliers', 0);
        $this->assertDatabaseCount('product_options', $count);
    }

    public function test_invalid_supplier_update_preserves_existing_product_and_links(): void
    {
        $admin = $this->actor();
        $supplier = $this->party($admin, 'supplier', 'SYN-SUP-PRESERVE');
        $service = app(MasterData::class);
        $id = $service->store($admin->id, 'product', $this->product(['supplier_ids' => [$supplier], 'primary_supplier_id' => $supplier]));
        $before = (array) DB::table('products')->where('id', $id)->first();

        $this->actingAs($admin)->putJson(route('v2.master.update', ['product', $id]), $this->product([
            'name' => 'Tidak boleh tersimpan', 'supplier_ids' => [999999],
        ]))->assertUnprocessable();

        $this->assertSame($before, (array) DB::table('products')->where('id', $id)->first());
        $this->assertDatabaseHas('v2_product_suppliers', ['product_id' => $id, 'supplier_id' => $supplier, 'is_primary' => true]);
    }

    public function test_product_edit_keeps_already_linked_archived_masters_but_rejects_new_archived_ones(): void
    {
        $admin = $this->actor();
        $relations = app(MasterRelations::class);
        $unit = $relations->store($admin->id, 'unit', ['name' => 'unit lama']);
        $category = $relations->store($admin->id, 'category', ['name' => 'kategori lama']);
        $kept = $this->party($admin, 'supplier', 'SYN-SUP-KEPT');
        $active = $this->party($admin, 'supplier', 'SYN-SUP-ACTIVE');
        $payload = $this->product([
            'unit_id' => $unit, 'category_id' => $category, 'supplier_ids' => [$kept, $active], 'primary_supplier_id' => $kept,
        ]);
        $id = app(MasterData::class)->store($admin->id, 'product', $payload);

        $relations->archive($admin->id, 'unit', $unit);
        $relations->archive($admin->id, 'category', $category);
        app(MasterData::class)->archive($admin->id, 'supplier', $kept);
        $this->actingAs($admin);

        // Edit biasa (harga jual) tidak boleh dipaksa mengganti master yang sudah diarsip, dan tidak mengaktifkannya lagi.
        $this->putJson(route('v2.master.update', ['product', $id]), ['name' => 'Nama Baru Sintetis', 'selling_price' => '13000'] + $payload)->assertOk();
        $this->assertDatabaseHas('products', ['id' => $id, 'name' => 'Nama Baru Sintetis', 'unit_id' => $unit, 'category_id' => $category]);
        $this->assertDatabaseHas('v2_product_suppliers', ['product_id' => $id, 'supplier_id' => $kept, 'is_primary' => true]);
        $this->assertDatabaseHas('v2_product_suppliers', ['product_id' => $id, 'supplier_id' => $active, 'is_primary' => false]);
        $this->assertDatabaseHas('product_options', ['id' => $unit, 'is_active' => false]);
        $this->assertDatabaseHas('v2_parties', ['id' => $kept, 'is_active' => false]);

        // Pilihan baru yang diarsip tetap ditolak tanpa mengubah apa pun.
        $before = (array) DB::table('products')->where('id', $id)->first();
        $newUnit = $relations->store($admin->id, 'unit', ['name' => 'unit arsip baru']);
        $relations->archive($admin->id, 'unit', $newUnit);
        $this->putJson(route('v2.master.update', ['product', $id]), ['unit_id' => $newUnit] + $payload)->assertUnprocessable();

        $newSupplier = $this->party($admin, 'supplier', 'SYN-SUP-NEW-ARCHIVED');
        app(MasterData::class)->archive($admin->id, 'supplier', $newSupplier);
        $this->putJson(route('v2.master.update', ['product', $id]), ['supplier_ids' => [$kept, $active, $newSupplier]] + $payload)->assertUnprocessable();

        $this->assertSame($before, (array) DB::table('products')->where('id', $id)->first());
        $this->assertDatabaseCount('v2_product_suppliers', 2);
    }

    public function test_legacy_product_text_resolves_shared_options_and_renames_catalog_mirrors(): void
    {
        $admin = $this->actor();
        $service = app(MasterData::class);
        $first = $service->store($admin->id, 'product', $this->product(['unit' => '  lembar  ', 'category' => ' Panel ']));
        $second = $service->store($admin->id, 'product', $this->product(['sku' => 'SYN-REL-02', 'unit' => 'lembar', 'category' => 'Panel']));
        $row = DB::table('products')->where('id', $first)->first();
        $this->assertDatabaseHas('products', ['id' => $second, 'unit_id' => $row->unit_id, 'category_id' => $row->category_id]);
        $this->assertSame(1, DB::table('product_options')->where('type', 'unit')->where('name', 'lembar')->count());

        $this->actingAs($admin)->putJson(route('v2.relations.update', ['unit', $row->unit_id]), ['name' => 'lembar baru'])->assertOk();

        $this->assertDatabaseHas('products', ['id' => $first, 'unit' => 'lembar baru']);
        $this->assertDatabaseHas('products', ['id' => $second, 'unit' => 'lembar baru']);
        $this->putJson(route('v2.relations.update', ['category', $row->unit_id]), ['name' => 'Salah jenis'])
            ->assertUnprocessable()->assertJsonPath('message', 'Data tidak ditemukan.');
        $this->patchJson(route('v2.relations.archive', ['category', $row->unit_id]))->assertUnprocessable();
        $this->patchJson(route('v2.relations.archive', ['unit', $row->unit_id]))->assertOk();
        $this->assertDatabaseHas('product_options', ['id' => $row->unit_id, 'is_active' => false]);
        $this->assertDatabaseHas('products', ['id' => $first, 'unit_id' => $row->unit_id]);
    }

    public function test_product_records_bulk_load_supplier_links(): void
    {
        $admin = $this->actor();
        $service = app(MasterData::class);
        $supplier = $this->party($admin, 'supplier', 'SYN-SUP-BULK');
        foreach (['A', 'B', 'C'] as $suffix) {
            $service->store($admin->id, 'product', $this->product(['sku' => 'SYN-BULK-'.$suffix, 'supplier_ids' => [$supplier]]));
        }
        DB::enableQueryLog();
        DB::flushQueryLog();

        try {
            $records = $service->records($admin->id, 'product');
            $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'v2_product_suppliers'));
            $this->assertCount(1, $queries);
            $this->assertCount(3, $records);
            foreach ($records as $record) {
                $this->assertSame([$supplier], $record->supplier_ids);
                $this->assertNull($record->primary_supplier_id);
            }
        } finally {
            DB::disableQueryLog();
        }
    }

    #[TestWith(['driver'])]
    #[TestWith(['unit'])]
    #[TestWith(['category'])]
    public function test_new_master_kinds_explicitly_refuse_xlsx(string $kind): void
    {
        $admin = $this->actor();
        $service = app(MasterData::class);
        foreach ([fn () => $service->template($admin->id, $kind), fn () => $service->preview($admin->id, $kind, 'not-a-file.xlsx')] as $action) {
            try {
                $action();
                $this->fail('Master relasi tidak boleh menerima XLSX.');
            } catch (RuntimeException $e) {
                $this->assertSame('XLSX tidak tersedia untuk master '.$kind.'.', $e->getMessage());
            }
        }
        $this->assertDatabaseCount('v2_import_batches', 0);
    }

    public function test_product_xlsx_preview_has_no_master_side_effects_and_commit_resolves_options(): void
    {
        $admin = $this->actor();
        $service = app(MasterData::class);
        $path = rtrim(getenv('TMPDIR') ?: storage_path('app/private'), '/\\').'/v2_relation_'.bin2hex(random_bytes(8)).'.zip';
        file_put_contents($path, $service->template($admin->id, 'product'));
        $archive = new PharData($path);
        $rows = [
            $service->columns('product'),
            ['SYN-XLSX-REL', 'Material Impor Sintetis', 'rol sintetis', 'Panel sintetis', '100', '150', '0'],
        ];
        $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $index => $row) {
            $xml .= '<row r="'.($index + 1).'">';
            foreach ($row as $column => $value) {
                $xml .= '<c r="'.chr(65 + $column).($index + 1).'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1, 'UTF-8').'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $archive['xl/worksheets/sheet1.xml'] = $xml.'</sheetData></worksheet>';
        unset($archive);
        $before = DB::table('product_options')->count();

        try {
            $preview = $service->preview($admin->id, 'product', $path);
        } finally {
            unlink($path);
        }

        $this->assertSame([], $preview['errors']);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_options', $before);
        $this->assertDatabaseCount('v2_product_suppliers', 0);
        $service->commit($admin->id, $preview['batch_id']);
        $product = DB::table('products')->where('sku', 'SYN-XLSX-REL')->first();
        $this->assertNotNull($product->unit_id);
        $this->assertNotNull($product->category_id);
        $this->assertDatabaseHas('product_options', ['id' => $product->unit_id, 'type' => 'unit', 'name' => 'rol sintetis']);
        $this->assertDatabaseHas('product_options', ['id' => $product->category_id, 'type' => 'category', 'name' => 'Panel sintetis']);
    }
}
