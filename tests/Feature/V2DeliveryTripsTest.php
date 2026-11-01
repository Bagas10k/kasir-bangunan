<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\DeliveryTrips;
use App\Services\V2\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class V2DeliveryTripsTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private static int $funding = 0;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('V2 delivery/trip tests require isolated SQLite :memory:.');
        }
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert([
            'user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }

    private function accounts(): void
    {
        foreach ([
            ['1101', 'Kas', 'asset', 'debit'],
            ['1102', 'Bank', 'asset', 'debit'],
            ['1191', 'Uang Muka Karyawan', 'asset', 'debit'],
            ['3101', 'Modal', 'equity', 'credit'],
            ['6101', 'Biaya Perjalanan', 'expense', 'debit'],
        ] as [$code, $name, $type, $normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore([
                'account_code' => $code, 'account_name' => $name, 'account_type' => $type,
                'normal_balance' => $normal, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function vehicle(string $status = 'active'): int
    {
        return (int) DB::table('vehicles')->insertGetId([
            'license_plate' => 'SYN-DLV-0001', 'vehicle_type' => 'pickup', 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function shift(User $user, string $opening = '1000.00'): int
    {
        return (int) DB::table('v2_shifts')->insertGetId([
            'user_id' => $user->id, 'open_user_id' => $user->id, 'source' => 'admin',
            'opening_cash' => $opening, 'status' => 'open', 'opened_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function fund(string $account, string $amount): void
    {
        Ledger::post('test_funding', ++self::$funding, 'Saldo awal uji', [
            ['account_code' => $account, 'debit' => $amount, 'credit' => '0.00'],
            ['account_code' => '3101', 'debit' => '0.00', 'credit' => $amount],
        ]);
    }

    private function delivery(User $owner): int
    {
        $id = ++self::$seq;
        $shiftId = $this->shift($owner, '0.00');
        $saleId = (int) DB::table('v2_sales')->insertGetId([
            'number' => 'SYN-SALE-'.$id, 'user_id' => $owner->id, 'shift_id' => $shiftId,
            'subtotal' => '0.00', 'total' => '0.00', 'request_hash' => str_repeat('a', 64),
            'payment_method' => 'cash', 'status' => 'paid', 'fulfillment' => 'delivery',
            'idempotency_key' => 'syn-sale-'.$id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('v2_deliveries')->insertGetId([
            'sale_id' => $saleId, 'number' => 'SYN-DLV-'.$id, 'destination' => 'Zona Beta',
            'status' => 'prepared', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function advancePayload(int $vehicleId, array $overrides = []): array
    {
        return $overrides + [
            'vehicle_id' => $vehicleId,
            'driver_name' => 'Sopir Sintetis',
            'destination' => 'Zona Beta',
            'date' => '2026-10-30',
            'amount' => '100.00',
            'method' => 'cash',
            'idempotency_key' => 'syn-advance-0001',
        ];
    }

    private function assertForbidden(callable $call): void
    {
        try {
            $call();
            $this->fail('Aksi tanpa hak seharusnya ditolak 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    private function assertInvalid(callable $call): void
    {
        try {
            $call();
            $this->fail('Muatan tidak sah seharusnya ditolak validasi.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    private function assertGloballyBalanced(): void
    {
        $rows = DB::table('journal_entry_lines')->get(['debit', 'credit']);
        $debit = 0.0;
        $credit = 0.0;
        foreach ($rows as $line) {
            $debit += (float) $line->debit;
            $credit += (float) $line->credit;
        }
        $this->assertSame(number_format($debit, 2, '.', ''), number_format($credit, 2, '.', ''), 'Jurnal tidak seimbang.');
    }

    private function accountNet(string $code): string
    {
        $debit = (float) DB::table('journal_entry_lines')->where('account_code', $code)->sum('debit');
        $credit = (float) DB::table('journal_entry_lines')->where('account_code', $code)->sum('credit');

        return number_format($debit - $credit, 2, '.', '');
    }

    public function test_dispatch_and_receive_lifecycle_scoped_without_stock_or_journal(): void
    {
        $this->accounts();
        $cashier = $this->actor('cashier');
        $other = $this->actor('cashier');
        $admin = $this->actor('admin');
        $vehicle = $this->vehicle();
        $delivery = $this->delivery($cashier);
        $product = (int) DB::table('products')->insertGetId([
            'sku' => 'SYN-DLV-001', 'name' => 'Material Sintetis Gamma', 'unit' => 'unit',
            'category' => 'Umum', 'cost_price' => '10.0000', 'selling_price' => '20.00',
            'stock_quantity' => '10.0000', 'min_stock' => '1.0000', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = app(DeliveryTrips::class);

        $this->assertForbidden(fn () => $service->dispatch($other->id, $delivery, ['vehicle_id' => $vehicle, 'driver_name' => 'Penyusup']));

        $service->dispatch($cashier->id, $delivery, ['vehicle_id' => $vehicle, 'driver_name' => 'Sopir Sintetis']);

        $row = DB::table('v2_deliveries')->where('id', $delivery)->first();
        $this->assertSame('sent', $row->status);
        $this->assertSame($vehicle, (int) $row->vehicle_id);
        $this->assertSame('Sopir Sintetis', $row->driver_name);
        $this->assertSame($cashier->id, (int) $row->dispatched_by);
        $this->assertNotNull($row->dispatched_at);
        $this->assertNull($row->received_at);

        $this->assertSame(0, DB::table('journal_entries')->count());
        $this->assertSame(0, DB::table('stock_movements')->count());
        $this->assertSame('10.0000', number_format((float) DB::table('products')->where('id', $product)->value('stock_quantity'), 4, '.', ''));

        // Kirim ulang identik: tanpa efek samping, stempel audit tidak berubah.
        $stamp = (string) $row->dispatched_at;
        $service->dispatch($cashier->id, $delivery, ['vehicle_id' => $vehicle, 'driver_name' => 'Sopir Sintetis']);
        $this->assertSame('sent', DB::table('v2_deliveries')->where('id', $delivery)->value('status'));
        $this->assertSame($stamp, (string) DB::table('v2_deliveries')->where('id', $delivery)->value('dispatched_at'));
        $this->assertSame(0, DB::table('journal_entries')->count());

        $this->assertForbidden(fn () => $service->receive($other->id, $delivery));

        $service->receive($cashier->id, $delivery);
        $row = DB::table('v2_deliveries')->where('id', $delivery)->first();
        $this->assertSame('received', $row->status);
        $this->assertSame($cashier->id, (int) $row->received_by);
        $this->assertNotNull($row->received_at);

        $service->receive($cashier->id, $delivery);
        $this->assertSame('received', DB::table('v2_deliveries')->where('id', $delivery)->value('status'));

        $this->assertInvalid(fn () => $service->dispatch($admin->id, $delivery, ['vehicle_id' => $vehicle, 'driver_name' => 'Sopir Sintetis']));
        $this->assertSame('received', DB::table('v2_deliveries')->where('id', $delivery)->value('status'));
        $this->assertSame(0, DB::table('journal_entries')->count());
        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_receive_requires_sent_first(): void
    {
        $this->accounts();
        $cashier = $this->actor('cashier');
        $delivery = $this->delivery($cashier);

        $this->assertInvalid(fn () => app(DeliveryTrips::class)->receive($cashier->id, $delivery));
        $this->assertSame('prepared', DB::table('v2_deliveries')->where('id', $delivery)->value('status'));
    }

    public function test_cash_advance_and_settlement_are_balanced_with_single_expense(): void
    {
        $this->accounts();
        $this->fund('1101', '1000.00');
        $admin = $this->actor('admin');
        $shift = $this->shift($admin, '1000.00');
        $vehicle = $this->vehicle();
        $service = app(DeliveryTrips::class);

        $advance = $service->advance($admin->id, $this->advancePayload($vehicle));

        $this->assertSame('100.00', $advance['amount']);
        $this->assertSame('open', $advance['status']);
        $this->assertSame('100.00', $this->accountNet('1191'));
        $this->assertSame('900.00', $this->accountNet('1101'));
        $movement = DB::table('v2_shift_movements')->where('reference_type', 'v2_driver_advance')->first();
        $this->assertSame('out', $movement->direction);
        $this->assertSame('100.00', number_format((float) $movement->amount, 2, '.', ''));

        $settlement = $service->settle($admin->id, $advance['advance_id'], [
            'fuel_cost' => '40.00',
            'allowance' => '10.00',
            'refund_method' => 'cash',
            'refund_received' => true,
            'idempotency_key' => 'syn-settle-0001',
        ]);

        $this->assertSame('settled', $settlement['status']);
        $this->assertSame('50.00', $settlement['expense_total']);
        $this->assertSame('50.00', $settlement['refund_amount']);

        $this->assertSame('0.00', $this->accountNet('1191'));
        $this->assertSame('50.00', $this->accountNet('6101'));
        $this->assertSame('950.00', $this->accountNet('1101'));
        $this->assertSame('settled', DB::table('v2_driver_advances')->where('id', $advance['advance_id'])->value('status'));

        $this->assertSame(1, DB::table('journal_entry_lines')->where('account_code', '6101')->count());
        $this->assertSame(0, DB::table('v2_trips')->count());
        $this->assertSame(2, DB::table('v2_shift_movements')->where('shift_id', $shift)->count());
        $this->assertGloballyBalanced();
    }

    public function test_advance_and_settlement_idempotency_replays_without_duplicate_journal(): void
    {
        $this->accounts();
        $this->fund('1102', '500.00');
        $admin = $this->actor('admin');
        $vehicle = $this->vehicle();
        $service = app(DeliveryTrips::class);

        $payload = $this->advancePayload($vehicle, ['method' => 'transfer', 'idempotency_key' => 'syn-advance-idem']);
        $first = $service->advance($admin->id, $payload);
        $second = $service->advance($admin->id, $payload);
        $this->assertSame($first['advance_id'], $second['advance_id']);
        $this->assertSame(1, DB::table('v2_driver_advances')->count());
        $this->assertSame(2, DB::table('journal_entries')->count()); // 1 funding + 1 advance

        $this->assertInvalid(fn () => $service->advance($admin->id, array_replace($payload, ['amount' => '60.00'])));

        $settlePayload = [
            'fuel_cost' => '100.00', 'allowance' => '0.00',
            'refund_method' => 'transfer', 'refund_received' => false,
            'idempotency_key' => 'syn-settle-idem',
        ];
        $settled = $service->settle($admin->id, $first['advance_id'], $settlePayload);
        $again = $service->settle($admin->id, $first['advance_id'], $settlePayload);
        $this->assertSame($settled['settlement_id'], $again['settlement_id']);
        $this->assertSame(1, DB::table('v2_driver_advance_settlements')->count());
        $this->assertSame(3, DB::table('journal_entries')->count()); // funding + advance + settlement
        $this->assertGloballyBalanced();
    }

    public function test_excess_and_unconfirmed_refund_roll_back_without_partial_writes(): void
    {
        $this->accounts();
        $this->fund('1102', '500.00');
        $admin = $this->actor('admin');
        $vehicle = $this->vehicle();
        $service = app(DeliveryTrips::class);
        $advance = $service->advance($admin->id, $this->advancePayload($vehicle, [
            'method' => 'transfer', 'idempotency_key' => 'syn-advance-rollback',
        ]));

        $this->assertInvalid(fn () => $service->settle($admin->id, $advance['advance_id'], [
            'fuel_cost' => '120.00', 'allowance' => '30.00',
            'refund_method' => 'transfer', 'refund_received' => false,
            'idempotency_key' => 'syn-settle-excess',
        ]));
        $this->assertSame(0, DB::table('v2_driver_advance_settlements')->count());
        $this->assertSame('open', DB::table('v2_driver_advances')->where('id', $advance['advance_id'])->value('status'));
        $this->assertSame(2, DB::table('journal_entries')->count()); // hanya funding + advance
        $this->assertSame('0.00', $this->accountNet('6101'));

        // Sisa kembali wajib dikonfirmasi eksplisit dan metodenya jelas.
        $this->assertInvalid(fn () => $service->settle($admin->id, $advance['advance_id'], [
            'fuel_cost' => '40.00', 'allowance' => '10.00',
            'refund_method' => 'transfer', 'idempotency_key' => 'syn-settle-noconfirm',
        ]));
        $this->assertInvalid(fn () => $service->settle($admin->id, $advance['advance_id'], [
            'fuel_cost' => '40.00', 'allowance' => '10.00',
            'refund_received' => true, 'idempotency_key' => 'syn-settle-nomethod',
        ]));
        $this->assertSame(0, DB::table('v2_driver_advance_settlements')->count());
        $this->assertSame(2, DB::table('journal_entries')->count());

        $ok = $service->settle($admin->id, $advance['advance_id'], [
            'fuel_cost' => '40.00', 'allowance' => '10.00',
            'refund_method' => 'transfer', 'refund_received' => true,
            'idempotency_key' => 'syn-settle-ok',
        ]);
        $this->assertSame('settled', $ok['status']);

        $this->assertInvalid(fn () => $service->settle($admin->id, $advance['advance_id'], [
            'fuel_cost' => '10.00', 'allowance' => '0.00',
            'refund_method' => 'transfer', 'refund_received' => true,
            'idempotency_key' => 'syn-settle-twice',
        ]));
        $this->assertSame(1, DB::table('v2_driver_advance_settlements')->count());
        $this->assertGloballyBalanced();
    }

    public function test_cash_advance_requires_open_shift_sufficient_drawer_and_ledger_balance(): void
    {
        $this->accounts();
        $admin = $this->actor('admin');
        $vehicle = $this->vehicle();
        $service = app(DeliveryTrips::class);

        $this->assertInvalid(fn () => $service->advance($admin->id, $this->advancePayload($vehicle, ['idempotency_key' => 'syn-noshift'])));
        $this->assertSame(0, DB::table('v2_driver_advances')->count());

        $this->shift($admin, '50.00');
        $this->assertInvalid(fn () => $service->advance($admin->id, $this->advancePayload($vehicle, ['idempotency_key' => 'syn-thindrawer'])));

        DB::table('v2_shifts')->where('user_id', $admin->id)->update(['opening_cash' => '1000.00']);
        $this->fund('1101', '30.00');
        $this->assertInvalid(fn () => $service->advance($admin->id, $this->advancePayload($vehicle, ['idempotency_key' => 'syn-thinledger'])));

        $this->assertSame(0, DB::table('v2_driver_advances')->count());
        $this->assertSame(0, DB::table('v2_shift_movements')->count());
        $this->assertSame(0, DB::table('journal_entry_lines')->where('account_code', '1191')->count());

        // Bank guard: tanpa saldo bank, uang muka transfer ditolak walau ada shift.
        $this->assertInvalid(fn () => $service->advance($admin->id, $this->advancePayload($vehicle, ['method' => 'transfer', 'idempotency_key' => 'syn-nobank'])));
        $this->assertSame(0, DB::table('v2_driver_advances')->count());

        $this->fund('1101', '200.00');
        $ok = $service->advance($admin->id, $this->advancePayload($vehicle, ['idempotency_key' => 'syn-cash-ok']));
        $this->assertSame('100.00', $ok['amount']);
        $this->assertSame(1, DB::table('v2_driver_advances')->count());
        $this->assertGloballyBalanced();
    }

    public function test_zero_actual_settlement_returns_full_advance_without_expense(): void
    {
        $this->accounts();
        $this->fund('1102', '500.00');
        $admin = $this->actor('admin');
        $vehicle = $this->vehicle();
        $service = app(DeliveryTrips::class);
        $advance = $service->advance($admin->id, $this->advancePayload($vehicle, [
            'method' => 'transfer', 'idempotency_key' => 'syn-advance-zero',
        ]));

        $settlement = $service->settle($admin->id, $advance['advance_id'], [
            'fuel_cost' => '0', 'allowance' => '0',
            'refund_method' => 'transfer', 'refund_received' => true,
            'idempotency_key' => 'syn-settle-zero',
        ]);

        $this->assertSame('0.00', $settlement['expense_total']);
        $this->assertSame('100.00', $settlement['refund_amount']);
        $this->assertSame(0, DB::table('journal_entry_lines')->where('account_code', '6101')->count());
        $this->assertSame('0.00', $this->accountNet('1191'));
        $this->assertSame('500.00', $this->accountNet('1102'));
        $this->assertSame(0, DB::table('v2_trips')->count());
        $this->assertGloballyBalanced();
    }

    public function test_cashier_cannot_advance_or_settle_and_settlement_needs_the_1191_account(): void
    {
        $this->accounts();
        $cashier = $this->actor('cashier');
        $admin = $this->actor('admin');
        $vehicle = $this->vehicle();
        $service = app(DeliveryTrips::class);

        $this->assertForbidden(fn () => $service->advance($cashier->id, $this->advancePayload($vehicle)));
        $this->assertForbidden(fn () => $service->settle($cashier->id, 1, ['fuel_cost' => '1.00', 'allowance' => '0', 'refund_method' => 'transfer', 'idempotency_key' => 'syn-cashier-settle']));

        $this->fund('1102', '500.00');
        $advance = $service->advance($admin->id, $this->advancePayload($vehicle, ['method' => 'transfer']));

        DB::table('chart_of_accounts')->where('account_code', '1191')->update(['is_active' => false]);
        $this->assertInvalid(fn () => $service->settle($admin->id, $advance['advance_id'], [
            'fuel_cost' => '10.00', 'allowance' => '0',
            'refund_method' => 'transfer', 'refund_received' => true,
            'idempotency_key' => 'syn-settle-no1191',
        ]));
        $this->assertSame(0, DB::table('v2_driver_advance_settlements')->count());
        $this->assertSame('open', DB::table('v2_driver_advances')->where('id', $advance['advance_id'])->value('status'));
    }
}
