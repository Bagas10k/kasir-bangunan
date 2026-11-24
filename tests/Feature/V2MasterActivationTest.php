<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\MasterData;
use App\Services\V2\MasterRelations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class V2MasterActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new RuntimeException('Aktivasi master hanya diuji pada SQLite :memory: terisolasi.');
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

    public function test_archived_master_records_can_be_activated_without_losing_history(): void
    {
        $admin = $this->actor();
        $data = app(MasterData::class);

        $supplier = $data->store($admin->id, 'supplier', ['code' => 'SUP-ACT-1', 'name' => 'Supplier Sintetis Aktif']);
        $data->archive($admin->id, 'supplier', $supplier);
        $this->assertSame(0, (int) DB::table('v2_parties')->where('id', $supplier)->value('is_active'));
        $data->activate($admin->id, 'supplier', $supplier);
        $data->activate($admin->id, 'supplier', $supplier);
        $this->assertSame(1, (int) DB::table('v2_parties')->where('id', $supplier)->value('is_active'));

        $vehicle = $data->store($admin->id, 'vehicle', ['license_plate' => 'B 9911 AKT', 'vehicle_type' => 'truck']);
        $data->archive($admin->id, 'vehicle', $vehicle);
        $this->assertSame('inactive', DB::table('vehicles')->where('id', $vehicle)->value('status'));
        $data->activate($admin->id, 'vehicle', $vehicle);
        $this->assertSame('active', DB::table('vehicles')->where('id', $vehicle)->value('status'));
    }

    public function test_relations_can_be_activated_again(): void
    {
        $admin = $this->actor();
        $relations = app(MasterRelations::class);

        foreach (['unit' => 'dus aktivasi', 'category' => 'Kategori aktivasi'] as $kind => $name) {
            $id = $relations->store($admin->id, $kind, ['name' => $name]);
            $relations->archive($admin->id, $kind, $id);
            $this->assertSame(0, (int) DB::table('product_options')->where('id', $id)->value('is_active'));
            $relations->activate($admin->id, $kind, $id);
            $this->assertSame(1, (int) DB::table('product_options')->where('id', $id)->value('is_active'));
        }

        $driver = $relations->store($admin->id, 'driver', ['kind' => 'external', 'code' => 'DRV-ACT-1', 'name' => 'Sopir Aktivasi']);
        $relations->archive($admin->id, 'driver', $driver);
        $relations->activate($admin->id, 'driver', $driver);
        $this->assertSame(1, (int) DB::table('v2_drivers')->where('id', $driver)->value('is_active'));
    }

    public function test_activation_is_admin_only_and_rejects_unknown_rows(): void
    {
        $cashier = $this->actor('cashier');

        $this->expectException(RuntimeException::class);
        app(MasterData::class)->activate($cashier->id, 'supplier', 999999);
    }

    public function test_unknown_master_row_is_rejected(): void
    {
        $admin = $this->actor();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Data tidak ditemukan.');
        app(MasterData::class)->activate($admin->id, 'supplier', 999999);
    }

    public function test_workspace_offers_activate_for_archived_rows_and_archive_for_active_rows(): void
    {
        $admin = $this->actor();
        $data = app(MasterData::class);

        $archived = $data->store($admin->id, 'supplier', ['code' => 'SUP-ARC-1', 'name' => 'Supplier Arsip Sintetis']);
        $data->archive($admin->id, 'supplier', $archived);
        $data->store($admin->id, 'supplier', ['code' => 'SUP-ACT-2', 'name' => 'Supplier Aktif Sintetis']);

        $html = $this->actingAs($admin)->get(route('v2.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Aktifkan kembali', $html);
        $this->assertStringContainsString('data-master-form', $html);
        $this->assertStringContainsString(route('v2.master.activate', ['kind' => 'supplier', 'id' => $archived]), $html);
    }
}
