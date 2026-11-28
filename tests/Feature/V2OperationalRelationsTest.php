<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\Ledger;
use App\Services\V2\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class V2OperationalRelationsTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('V2 operational relation tests require isolated SQLite :memory:.');
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

    private function fund(): void
    {
        foreach ([
            ['1101', 'Kas', 'asset', 'debit'],
            ['1102', 'Bank', 'asset', 'debit'],
            ['1191', 'Uang Muka Karyawan', 'asset', 'debit'],
            ['1301', 'Persediaan', 'asset', 'debit'],
            ['2101', 'Utang Usaha', 'liability', 'credit'],
            ['3101', 'Modal', 'equity', 'credit'],
            ['6101', 'Beban Operasional Toko', 'expense', 'debit'],
            ['6201', 'Beban Transportasi & Ritase Armada', 'expense', 'debit'],
        ] as [$code, $name, $type, $normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore([
                'account_code' => $code, 'account_name' => $name, 'account_type' => $type,
                'normal_balance' => $normal, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Ledger::post('synthetic_funding', 1, 'Modal sintetis', [
            ['account_code' => '1101', 'debit' => '1000.00', 'credit' => '0.00'],
            ['account_code' => '1102', 'debit' => '1000.00', 'credit' => '0.00'],
            ['account_code' => '3101', 'debit' => '0.00', 'credit' => '2000.00'],
        ]);
    }

    private function party(string $kind, bool $active = true): int
    {
        return (int) DB::table('v2_parties')->insertGetId([
            'kind' => $kind, 'code' => 'SYN-PARTY-'.++$this->sequence,
            'name' => $kind === 'employee' ? 'Pegawai Sintetis Alpha' : 'Pemasok Sintetis Alpha',
            'is_active' => $active, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function driver(?int $employeeId = null, bool $active = true): int
    {
        return (int) DB::table('v2_drivers')->insertGetId([
            'code' => 'SYN-DRIVER-'.++$this->sequence, 'name' => 'Sopir Sintetis Alpha',
            'kind' => $employeeId === null ? 'external' : 'internal',
            'employee_id' => $employeeId, 'is_active' => $active,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function vehicle(): int
    {
        return (int) DB::table('vehicles')->insertGetId([
            'license_plate' => 'SYN-VEH-'.++$this->sequence, 'vehicle_type' => 'pickup', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function delivery(User $owner): int
    {
        $sequence = ++$this->sequence;
        $shiftId = app(Operations::class)->openShift($this->storeShiftAdmin()->id, '1000.00', 'admin');
        $saleId = (int) DB::table('v2_sales')->insertGetId([
            'number' => 'SYN-SALE-'.$sequence, 'user_id' => $owner->id, 'shift_id' => $shiftId,
            'subtotal' => '0.00', 'total' => '0.00', 'request_hash' => str_repeat('a', 64),
            'payment_method' => 'cash', 'status' => 'paid', 'fulfillment' => 'delivery',
            'idempotency_key' => 'syn-sale-'.$sequence, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('v2_deliveries')->insertGetId([
            'sale_id' => $saleId, 'number' => 'SYN-DLV-'.$sequence, 'destination' => 'Zona Pengiriman Alpha',
            'status' => 'prepared', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array{url:string, payload:array, table:string, status:string} */
    private function operation(string $operation, int $driver, int $vehicle, int $delivery): array
    {
        $payload = [
            'driver_id' => $driver, 'vehicle_id' => $vehicle, 'delivery_id' => $delivery,
            'driver_name' => 'Nama Klien Tidak Dipercaya', 'destination' => 'Tujuan Klien Tidak Dipercaya',
            'date' => '2026-11-04', 'idempotency_key' => 'syn-operation-'.$operation,
        ];

        return match ($operation) {
            'dispatch' => ['url' => route('v2.deliveries.dispatch', $delivery), 'payload' => $payload, 'table' => 'v2_deliveries', 'status' => 'sent'],
            'trip' => ['url' => route('v2.trips.store'), 'payload' => $payload + ['fuel_cost' => '40.00', 'allowance' => '10.00', 'payment_method' => 'cash'], 'table' => 'v2_trips', 'status' => 'prepared'],
            'advance' => ['url' => route('v2.driver-advances.store'), 'payload' => $payload + ['amount' => '100.00', 'method' => 'cash'], 'table' => 'v2_driver_advances', 'status' => 'prepared'],
        };
    }

    private function assertNoOperationalPosting(int $delivery, int $journalCount = 1): void
    {
        $this->assertDatabaseHas('v2_deliveries', [
            'id' => $delivery, 'status' => 'prepared', 'driver_id' => null, 'driver_name' => null,
            'vehicle_id' => null, 'dispatched_at' => null,
        ]);
        $this->assertDatabaseCount('v2_trips', 0);
        $this->assertDatabaseCount('v2_driver_advances', 0);
        $this->assertDatabaseCount('v2_requests', 0);
        $this->assertDatabaseCount('v2_shift_movements', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('journal_entries', $journalCount);
    }

    #[DataProvider('operationProvider')]
    public function test_operations_succeed_with_registered_driver_and_active_vehicle(string $op): void
    {
        $this->fund();
        $admin = $this->actor('admin');
        $driver = $this->driver();
        $vehicle = $this->vehicle();
        $delivery = $this->delivery($admin);

        $opData = $this->operation($op, $driver, $vehicle, $delivery);
        $this->actingAs($admin)->postJson($opData['url'], $opData['payload'])->assertOk();

        if ($op === 'dispatch') {
            $this->assertDatabaseHas('v2_deliveries', [
                'id' => $delivery,
                'status' => 'sent',
                'driver_id' => $driver,
                'vehicle_id' => $vehicle,
                'driver_name' => 'Sopir Sintetis Alpha',
            ]);
        } elseif ($op === 'trip') {
            $this->assertDatabaseHas('v2_trips', [
                'driver_id' => $driver,
                'vehicle_id' => $vehicle,
                'driver_name' => 'Sopir Sintetis Alpha',
                'status' => 'posted',
            ]);
        } elseif ($op === 'advance') {
            $this->assertDatabaseHas('v2_driver_advances', [
                'driver_id' => $driver,
                'vehicle_id' => $vehicle,
                'driver_name' => 'Sopir Sintetis Alpha',
                'status' => 'open',
            ]);
        }
    }

    #[DataProvider('operationProvider')]
    public function test_operations_reject_inactive_or_missing_driver(string $op): void
    {
        $this->fund();
        $admin = $this->actor('admin');
        $driver = $this->driver(active: false);
        $vehicle = $this->vehicle();
        $delivery = $this->delivery($admin);

        $opData = $this->operation($op, $driver, $vehicle, $delivery);
        $this->actingAs($admin)->postJson($opData['url'], $opData['payload'])->assertUnprocessable();

        $opData['payload']['driver_id'] = 99999;
        $this->actingAs($admin)->postJson($opData['url'], $opData['payload'])->assertUnprocessable();

        $this->assertNoOperationalPosting($delivery);
    }

    #[DataProvider('operationProvider')]
    public function test_operations_reject_inactive_or_missing_vehicle(string $op): void
    {
        $this->fund();
        $admin = $this->actor('admin');
        $driver = $this->driver();
        $vehicle = (int) DB::table('vehicles')->insertGetId([
            'license_plate' => 'SYN-VEH-INACTIVE', 'vehicle_type' => 'pickup', 'status' => 'inactive',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $delivery = $this->delivery($admin);

        $opData = $this->operation($op, $driver, $vehicle, $delivery);
        $this->actingAs($admin)->postJson($opData['url'], $opData['payload'])->assertUnprocessable();

        $opData['payload']['vehicle_id'] = 99999;
        $this->actingAs($admin)->postJson($opData['url'], $opData['payload'])->assertUnprocessable();

        $this->assertNoOperationalPosting($delivery);
    }

    #[DataProvider('operationProvider')]
    public function test_operations_reject_mismatched_driver_or_vehicle_on_linked_delivery(string $op): void
    {
        $this->fund();
        $admin = $this->actor('admin');
        $driver1 = $this->driver();
        $driver2 = $this->driver();
        $vehicle1 = $this->vehicle();
        $vehicle2 = $this->vehicle();
        $delivery = $this->delivery($admin);

        DB::table('v2_deliveries')->where('id', $delivery)->update([
            'driver_id' => $driver1,
            'driver_name' => 'Sopir Satu',
            'vehicle_id' => $vehicle1,
        ]);

        $opData = $this->operation($op, $driver2, $vehicle1, $delivery);
        $this->actingAs($admin)->postJson($opData['url'], $opData['payload'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Sopir dan kendaraan harus sesuai penugasan pengiriman.');

        $opData = $this->operation($op, $driver1, $vehicle2, $delivery);
        $this->actingAs($admin)->postJson($opData['url'], $opData['payload'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Sopir dan kendaraan harus sesuai penugasan pengiriman.');
    }

    #[DataProvider('operationProvider')]
    public function test_operations_refuse_to_guess_identity_for_delivery_with_legacy_driver_name_only(string $op): void
    {
        $this->fund();
        $admin = $this->actor('admin');
        $driver = $this->driver();
        $vehicle = $this->vehicle();
        $delivery = $this->delivery($admin);
        DB::table('v2_deliveries')->where('id', $delivery)->update(['driver_name' => 'Sopir Teks Lama']);

        $opData = $this->operation($op, $driver, $vehicle, $delivery);
        $this->actingAs($admin)->postJson($opData['url'], $opData['payload'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Pengiriman ini hanya punya nama sopir historis tanpa master sopir. Rekonsiliasi manual diperlukan; identitas tidak ditebak dari nama.');

        $this->assertDatabaseHas('v2_deliveries', [
            'id' => $delivery, 'status' => 'prepared', 'driver_id' => null, 'driver_name' => 'Sopir Teks Lama', 'vehicle_id' => null,
        ]);
        $this->assertDatabaseCount('v2_trips', 0);
        $this->assertDatabaseCount('v2_driver_advances', 0);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_operations_reserve_unassigned_prepared_delivery(): void
    {
        $this->fund();
        $admin = $this->actor('admin');
        $driver = $this->driver();
        $vehicle = $this->vehicle();
        $delivery = $this->delivery($admin);

        $this->assertDatabaseHas('v2_deliveries', [
            'id' => $delivery,
            'driver_id' => null,
            'vehicle_id' => null,
        ]);

        $opData = $this->operation('advance', $driver, $vehicle, $delivery);
        $this->actingAs($admin)->postJson($opData['url'], $opData['payload'])->assertOk();

        $this->assertDatabaseHas('v2_deliveries', [
            'id' => $delivery,
            'driver_id' => $driver,
            'vehicle_id' => $vehicle,
            'driver_name' => 'Sopir Sintetis Alpha',
        ]);
    }

    public function test_driver_name_is_snapshotted_from_registered_driver_ignoring_client_name(): void
    {
        $this->fund();
        $admin = $this->actor('admin');
        $driver = $this->driver();
        $vehicle = $this->vehicle();
        $delivery = $this->delivery($admin);

        $opData = $this->operation('dispatch', $driver, $vehicle, $delivery);
        $opData['payload']['driver_name'] = 'Penyerang Palsu';
        $this->actingAs($admin)->postJson($opData['url'], $opData['payload'])->assertOk();

        $this->assertDatabaseHas('v2_deliveries', [
            'id' => $delivery,
            'driver_name' => 'Sopir Sintetis Alpha',
        ]);
        $this->assertDatabaseMissing('v2_deliveries', [
            'id' => $delivery,
            'driver_name' => 'Penyerang Palsu',
        ]);
    }

    public function test_internal_driver_uses_live_employee_name_at_the_time_of_operation(): void
    {
        $this->fund();
        $admin = $this->actor('admin');
        $employee = $this->party('employee');
        DB::table('v2_parties')->where('id', $employee)->update(['name' => 'Budi Staf Pengiriman']);
        $driver = $this->driver($employee);
        $vehicle = $this->vehicle();
        $delivery = $this->delivery($admin);

        $opData = $this->operation('dispatch', $driver, $vehicle, $delivery);
        $this->actingAs($admin)->postJson($opData['url'], $opData['payload'])->assertOk();

        $this->assertDatabaseHas('v2_deliveries', [
            'id' => $delivery,
            'driver_name' => 'Budi Staf Pengiriman',
        ]);
    }

    public static function operationProvider(): array
    {
        return [
            'dispatch' => ['dispatch'],
            'trip' => ['trip'],
            'advance' => ['advance'],
        ];
    }
}
