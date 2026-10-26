<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RitaseHistoryFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Ritase history tests require isolated SQLite :memory:.');
        }
    }

    private function vehicle(string $plate, string $status = 'active'): int
    {
        return DB::table('vehicles')->insertGetId([
            'license_plate' => $plate, 'vehicle_type' => 'pickup', 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function trip(int $vehicleId, string $number, string $date, string $amount, string $status = 'completed'): void
    {
        DB::table('ritases')->insert([
            'ritase_number' => $number, 'vehicle_id' => $vehicleId,
            'driver_name' => $status === 'pending_details' ? null : 'Sopir Sintetis',
            'destination' => $status === 'pending_details' ? null : 'Zona Sintetis',
            'ritase_date' => $date, 'fuel_cost' => '0.00', 'driver_allowance' => '0.00',
            'total_cost' => $amount, 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_vehicle_period_and_status_filters_match_history_and_totals_without_page_truncation(): void
    {
        $first = $this->vehicle('SYN-CAR-A');
        $second = $this->vehicle('SYN-CAR-B', 'inactive');
        for ($i = 1; $i <= 17; $i++) {
            $this->trip($first, sprintf('SYN-RIT-%02d', $i), '2026-10-20', '1.25');
        }
        $this->trip($first, 'SYN-PENDING', '2026-10-20', '3.50', 'pending_details');
        $this->trip($first, 'SYN-OLDER', '2026-09-20', '90.00');
        $this->trip($second, 'SYN-OTHER', '2026-10-20', '700.00');

        $this->actingAs(User::factory()->create())->get(route('ritase.index', [
            'vehicle_id' => $first, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31',
            'status' => 'completed', 'per_page' => 10,
        ]))->assertOk()->assertViewHas('summary', function ($summary) {
            return $summary['count'] === 17 && $summary['total'] === '21.25';
        })->assertViewHas('ritases', function ($page) {
            return $page->total() === 17 && $page->count() === 10;
        })->assertSee('SYN-CAR-A')->assertDontSee('SYN-OTHER')->assertDontSee('SYN-OLDER')
          ->assertDontSee('SYN-PENDING');
    }

    public function test_vehicle_totals_include_inactive_fleet_and_pending_trips(): void
    {
        $first = $this->vehicle('SYN-CAR-A', 'inactive');
        $second = $this->vehicle('SYN-CAR-B');
        $this->trip($first, 'SYN-001', '2026-10-20', '12.50');
        $this->trip($first, 'SYN-002', '2026-10-21', '0.25', 'pending_details');
        $this->trip($second, 'SYN-003', '2026-10-21', '5.00');
        $this->actingAs(User::factory()->create())->get(route('ritase.index'))
            ->assertOk()->assertViewHas('vehicleTotals', function ($rows) use ($first) {
                $row = $rows->firstWhere('vehicle_id', $first);
                return $row && $row->trip_count === 2 && $row->total_cost === '12.75';
            })->assertViewHas('summary', function ($summary) {
                return $summary['count'] === 3 && $summary['total'] === '17.75';
            });
    }

    public function test_history_view_keeps_large_decimal_cents_exact(): void
    {
        $amount = '90071992547409.93';
        $trip = (object) [
            'id' => 1, 'ritase_number' => 'SYN-LARGE', 'ritase_date' => '2026-10-20',
            'vehicle_id' => 1, 'vehicle_plate' => 'SYN-CAR-A', 'driver_name' => 'Sopir Sintetis',
            'destination' => 'Zona Sintetis', 'cargo_summary' => null, 'linked_invoice_number' => null,
            'sale_id' => null, 'linked_receipt_number' => null, 'status' => 'completed', 'total_cost' => $amount,
        ];
        $html = view('ritase.index', [
            'vehicles' => collect(), 'activeVehicles' => collect(), 'sales' => collect(), 'ritases' => new \Illuminate\Pagination\LengthAwarePaginator([$trip], 1, 10),
            'search' => '', 'perPage' => 10, 'sort' => 'ritase_date', 'dir' => 'desc', 'filters' => [],
            'errors' => new \Illuminate\Support\ViewErrorBag(),
            'summary' => ['count' => 1, 'total' => $amount],
            'vehicleTotals' => collect([(object) ['vehicle_id' => 1, 'license_plate' => 'SYN-CAR-A', 'trip_count' => 1, 'total_cost' => $amount]]),
        ])->render();
        $this->assertSame(3, substr_count($html, '90.071.992.547.409,93'));
    }

    public function test_inactive_vehicle_cannot_be_used_for_new_trip_but_remains_in_history_filter(): void
    {
        $inactive = $this->vehicle('SYN-INACTIVE', 'inactive');
        $active = $this->vehicle('SYN-ACTIVE');
        $page = $this->actingAs(User::factory()->create())->get(route('ritase.index'));
        $page->assertOk()->assertSee('SYN-INACTIVE (nonaktif)');
        $modalOptions = explode('</select>', explode('id="vehicle_id"', $page->getContent(), 2)[1], 2)[0];
        $this->assertStringContainsString('value="'.$active.'"', $modalOptions);
        $this->assertStringNotContainsString('value="'.$inactive.'"', $modalOptions);
        $this->expectException(\InvalidArgumentException::class);
        try {
            app(\App\Services\RitaseService::class)->recordRitase($inactive, 'Sopir Sintetis', 'Zona Sintetis', null);
        } finally {
            $this->assertSame(0, DB::table('ritases')->count());
        }
    }

    public function test_controller_rejects_inactive_vehicle_before_writing_ritase(): void
    {
        $inactive = $this->vehicle('SYN-INACTIVE', 'inactive');
        $this->actingAs(User::factory()->create());
        $response = $this->post(route('ritase.store'), [
            'vehicle_id' => $inactive, 'driver_name' => 'Sopir Sintetis',
            'destination' => 'Zona Sintetis', 'fuel_cost' => '0', 'driver_allowance' => '0',
        ]);
        $response->assertSessionHasErrors('vehicle_id');
        $this->assertSame(0, DB::table('ritases')->count());
    }

    public function test_default_page_size_matches_selected_filter_and_search_filters_totals(): void
    {
        $vehicle = $this->vehicle('SYN-FILTER-01');
        $this->trip($vehicle, 'SYN-SEARCH-HIT', '2026-10-20', '12.50');
        $this->trip($vehicle, 'SYN-OTHER', '2026-10-20', '25.00');
        $this->actingAs(User::factory()->create());
        $page = $this->get(route('ritase.index'))->assertOk()
            ->assertViewHas('ritases', fn ($rows) => $rows->perPage() === 15);
        $select = explode('</select>', explode('id="history-page-size"', $page->getContent(), 2)[1], 2)[0];
        $this->assertStringContainsString('value="15" selected', $select);
        $this->get(route('ritase.index', ['search' => 'SYN-SEARCH-HIT']))
            ->assertOk()->assertViewHas('summary', fn ($summary) => $summary['count'] === 1 && $summary['total'] === '12.50')
            ->assertSee('SYN-SEARCH-HIT')->assertDontSee('SYN-OTHER');
    }

    public function test_array_query_parameters_are_rejected_instead_of_causing_server_error(): void
    {
        $user = User::factory()->create();
        foreach (['search', 'per_page', 'sort', 'dir'] as $key) {
            $this->actingAs($user)->getJson(route('ritase.index', [$key => ['nested']]))
                ->assertUnprocessable()->assertJsonValidationErrors($key);
        }
    }

    public function test_invalid_dates_and_status_are_rejected_without_writing_history(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson(route('ritase.index', ['start_date' => '2026-99-20']))
            ->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->actingAs($user)->getJson(route('ritase.index', [
            'start_date' => '2026-10-31', 'end_date' => '2026-10-01', 'status' => 'removed',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['end_date', 'status']);
        $this->assertSame(0, DB::table('ritases')->count());
    }
}
