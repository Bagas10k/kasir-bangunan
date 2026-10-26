<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VehiclesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function beforeRefreshingDatabase(): void
    {
        $driver = DB::connection()->getDriverName();
        $database = DB::connection()->getDatabaseName();

        if ($driver !== 'sqlite') {
            throw new \RuntimeException(
                sprintf('VehiclesCrudTest requires SQLite; got driver=%s, db=%s', $driver, $database)
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (class_exists(PreventRequestForgery::class)) {
            $this->withoutMiddleware(PreventRequestForgery::class);
        }

        $this->user = User::create([
            'name' => 'Cashier Operational Test',
            'email' => 'cashier.test@example.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_guest_cannot_access_vehicle_endpoints(): void
    {
        $this->get('/vehicles')->assertRedirect('/login');
        $this->put('/vehicles/1', ['license_plate' => 'B 1234 ABC', 'vehicle_type' => 'truck', 'status' => 'active'])
            ->assertRedirect('/login');
        $this->delete('/vehicles/1')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_list_vehicles_with_pagination_and_query_string(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            DB::table('vehicles')->insert([
                'license_plate' => sprintf('B %04d TST', $i),
                'vehicle_type' => 'truck',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->user)->get('/vehicles?per_page=10&page=2');
        $response->assertStatus(200);

        $vehicles = $response->viewData('vehicles');
        $this->assertNotNull($vehicles);
        $this->assertEquals(15, $vehicles->total());
        $this->assertEquals(2, $vehicles->currentPage());
        $this->assertEquals(5, $vehicles->count());
        $this->assertStringContainsString('per_page=10', $vehicles->url(1));
    }

    public function test_vehicles_list_validates_search_status_and_per_page(): void
    {
        // Invalid per_page (not in 10,25,50)
        $invalidPerPage = $this->actingAs($this->user)
            ->getJson('/vehicles?per_page=99');
        $invalidPerPage->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);

        // Invalid status
        $invalidStatus = $this->actingAs($this->user)
            ->getJson('/vehicles?status=destroyed');
        $invalidStatus->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        // Invalid non-scalar search (array passed)
        $invalidSearch = $this->actingAs($this->user)
            ->getJson('/vehicles?search[]=illegal_array');
        $invalidSearch->assertStatus(422)
            ->assertJsonValidationErrors(['search']);

        // Valid query
        $validResponse = $this->actingAs($this->user)
            ->getJson('/vehicles?search=B&status=active&per_page=25');
        $validResponse->assertStatus(200);
    }

    public function test_vehicles_search_is_case_insensitive_on_license_plate(): void
    {
        DB::table('vehicles')->insert([
            ['license_plate' => 'B 1234 XYZ', 'vehicle_type' => 'truck', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['license_plate' => 'D 5678 ABC', 'vehicle_type' => 'pickup', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $resLower = $this->actingAs($this->user)->getJson('/vehicles?search=b+1234');
        $resLower->assertStatus(200);
        $data = $resLower->json('data') ?? $resLower->json();
        $this->assertCount(1, $data);
        $this->assertEquals('B 1234 XYZ', $data[0]['license_plate']);

        $resMixed = $this->actingAs($this->user)->getJson('/vehicles?search=xYz');
        $resMixed->assertStatus(200);
        $dataMixed = $resMixed->json('data') ?? $resMixed->json();
        $this->assertCount(1, $dataMixed);
        $this->assertEquals('B 1234 XYZ', $dataMixed[0]['license_plate']);
    }

    public function test_vehicles_filter_by_status(): void
    {
        DB::table('vehicles')->insert([
            ['license_plate' => 'B 1111 ACT', 'vehicle_type' => 'truck', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['license_plate' => 'B 2222 MTC', 'vehicle_type' => 'pickup', 'status' => 'maintenance', 'created_at' => now(), 'updated_at' => now()],
            ['license_plate' => 'B 3333 INA', 'vehicle_type' => 'lainnya', 'status' => 'inactive', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $resMtc = $this->actingAs($this->user)->getJson('/vehicles?status=maintenance');
        $resMtc->assertStatus(200);
        $data = $resMtc->json('data') ?? $resMtc->json();
        $this->assertCount(1, $data);
        $this->assertEquals('B 2222 MTC', $data[0]['license_plate']);

        $resIna = $this->actingAs($this->user)->getJson('/vehicles?status=inactive');
        $resIna->assertStatus(200);
        $dataIna = $resIna->json('data') ?? $resIna->json();
        $this->assertCount(1, $dataIna);
        $this->assertEquals('B 3333 INA', $dataIna[0]['license_plate']);
    }

    public function test_vehicles_left_join_ritases_calculates_ritase_count(): void
    {
        $v1Id = DB::table('vehicles')->insertGetId([
            'license_plate' => 'B 8888 RIT',
            'vehicle_type' => 'truck',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $v2Id = DB::table('vehicles')->insertGetId([
            'license_plate' => 'B 9999 ZER',
            'vehicle_type' => 'pickup',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert 2 ritases for v1
        for ($i = 1; $i <= 2; $i++) {
            DB::table('ritases')->insert([
                'ritase_number' => "RIT-00{$i}",
                'vehicle_id' => $v1Id,
                'driver_name' => 'Driver A',
                'ritase_date' => now()->toDateString(),
                'destination' => 'Proyek A',
                'total_cost' => 100000,
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $res = $this->actingAs($this->user)->getJson('/vehicles');
        $res->assertStatus(200);
        $items = collect($res->json('data') ?? $res->json());

        $itemV1 = $items->firstWhere('id', $v1Id);
        $itemV2 = $items->firstWhere('id', $v2Id);

        $this->assertNotNull($itemV1);
        $this->assertNotNull($itemV2);
        $this->assertEquals(2, (int) $itemV1['ritase_count']);
        $this->assertEquals(0, (int) $itemV2['ritase_count']);
    }

    public function test_update_vehicle_with_uppercase_trim_and_allowlist(): void
    {
        $id = DB::table('vehicles')->insertGetId([
            'license_plate' => 'B 1000 OLD',
            'vehicle_type' => 'truck',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->putJson("/vehicles/{$id}", [
            'license_plate' => '   d 9999 zzz   ',
            'vehicle_type' => 'pickup',
            'status' => 'maintenance',
        ]);

        $response->assertStatus(200);

        $updated = DB::table('vehicles')->where('id', $id)->first();
        $this->assertEquals('D 9999 ZZZ', $updated->license_plate);
        $this->assertEquals('pickup', $updated->vehicle_type);
        $this->assertEquals('maintenance', $updated->status);
    }

    public function test_update_vehicle_unique_plate_ignores_current_id_but_rejects_duplicate_other(): void
    {
        $v1 = DB::table('vehicles')->insertGetId([
            'license_plate' => 'B 1111 AAA',
            'vehicle_type' => 'truck',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $v2 = DB::table('vehicles')->insertGetId([
            'license_plate' => 'B 2222 BBB',
            'vehicle_type' => 'pickup',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Same plate on same vehicle is valid
        $resSelf = $this->actingAs($this->user)->putJson("/vehicles/{$v1}", [
            'license_plate' => 'B 1111 AAA',
            'vehicle_type' => 'truck',
            'status' => 'active',
        ]);
        $resSelf->assertStatus(200);

        // Duplicate plate from another vehicle is rejected
        $resDup = $this->actingAs($this->user)->putJson("/vehicles/{$v1}", [
            'license_plate' => 'B 2222 BBB',
            'vehicle_type' => 'truck',
            'status' => 'active',
        ]);
        $resDup->assertStatus(422)
            ->assertJsonValidationErrors(['license_plate']);
    }

    public function test_update_vehicle_rejects_disallowed_type_or_status(): void
    {
        $id = DB::table('vehicles')->insertGetId([
            'license_plate' => 'B 1010 CHK',
            'vehicle_type' => 'truck',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resType = $this->actingAs($this->user)->putJson("/vehicles/{$id}", [
            'license_plate' => 'B 1010 CHK',
            'vehicle_type' => 'speedboat',
            'status' => 'active',
        ]);
        $resType->assertStatus(422)->assertJsonValidationErrors(['vehicle_type']);

        $resStatus = $this->actingAs($this->user)->putJson("/vehicles/{$id}", [
            'license_plate' => 'B 1010 CHK',
            'vehicle_type' => 'truck',
            'status' => 'destroyed',
        ]);
        $resStatus->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_delete_vehicle_without_ritase_succeeds(): void
    {
        $id = DB::table('vehicles')->insertGetId([
            'license_plate' => 'B 5555 DEL',
            'vehicle_type' => 'pickup',
            'status' => 'inactive',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/vehicles/{$id}");
        $response->assertStatus(200);

        $this->assertNull(DB::table('vehicles')->where('id', $id)->first());
    }

    public function test_delete_vehicle_with_historical_ritase_is_rejected_and_directed_to_deactivate(): void
    {
        $id = DB::table('vehicles')->insertGetId([
            'license_plate' => 'B 7777 HST',
            'vehicle_type' => 'truck',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ritases')->insert([
            'ritase_number' => 'RIT-HIST-01',
            'vehicle_id' => $id,
            'driver_name' => 'Driver B',
            'ritase_date' => now()->toDateString(),
            'destination' => 'Proyek B',
            'total_cost' => 150000,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/vehicles/{$id}");
        $response->assertStatus(422);

        $msg = strtolower($response->json('message') ?? '');
        $this->assertTrue(
            str_contains($msg, 'nonaktif') || str_contains($msg, 'inactive'),
            'Rejection message must direct user to deactivate vehicle.'
        );

        // Ensure record still exists
        $this->assertNotNull(DB::table('vehicles')->where('id', $id)->first());
    }
}
