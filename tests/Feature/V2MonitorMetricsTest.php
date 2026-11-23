<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\MonitorMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2MonitorMetricsTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('V2 monitor metric tests require isolated SQLite :memory:.');
        }
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function party(string $kind, bool $active = true): int
    {
        return (int) DB::table('v2_parties')->insertGetId([
            'kind' => $kind, 'code' => 'SYN-'.++$this->seq, 'name' => 'Pihak Sintetis '.$this->seq,
            'is_active' => $active, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function shift(User $owner, string $status, ?string $difference, ?string $closedAt): int
    {
        return (int) DB::table('v2_shifts')->insertGetId([
            'user_id' => $owner->id, 'open_user_id' => $status === 'open' ? $owner->id : null, 'source' => 'admin', 'opening_cash' => '100.00',
            'difference' => $difference, 'status' => $status, 'opened_at' => '2026-11-01 08:00:00', 'closed_at' => $closedAt,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function delivery(int $shiftId, User $owner, string $status, ?string $receivedAt = null): void
    {
        $n = ++$this->seq;
        $sale = (int) DB::table('v2_sales')->insertGetId([
            'number' => 'SYN-SALE-'.$n, 'user_id' => $owner->id, 'shift_id' => $shiftId, 'subtotal' => '0.00', 'total' => '0.00',
            'request_hash' => str_repeat('a', 64), 'payment_method' => 'cash', 'status' => 'paid', 'fulfillment' => 'delivery',
            'idempotency_key' => 'syn-sale-'.$n, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_deliveries')->insert([
            'sale_id' => $sale, 'number' => 'SYN-DLV-'.$n, 'destination' => 'Zona Alpha', 'status' => $status,
            'received_at' => $receivedAt, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedFigures(): array
    {
        $admin = $this->user('admin');
        $cashier = $this->user('cashier');
        $customer = $this->party('customer');
        $supplier = $this->party('supplier');
        $this->party('supplier', false);
        $this->party('employee');

        $invoice = fn (string $direction, int $party, string $amount, string $paid, string $status) => DB::table('v2_invoices')->insertGetId([
            'direction' => $direction, 'party_id' => $party, 'number' => 'SYN-INV-'.++$this->seq, 'amount' => $amount,
            'paid' => $paid, 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $receivable = $invoice('receivable', $customer, '200.00', '60.00', 'open');
        $invoice('receivable', $customer, '75.25', '0.00', 'open');
        $invoice('receivable', $customer, '500.00', '500.00', 'paid'); // lunas: tidak dihitung
        $invoice('payable', $supplier, '50.00', '0.00', 'open');

        $shiftOwn = $this->shift($cashier, 'closed', '-1.50', '2026-11-03 17:00:00');
        $shiftOther = $this->shift($admin, 'review', '-3.00', '2026-11-04 17:00:00');
        $this->shift($admin, 'closed', '9.00', '2026-10-15 17:00:00'); // bulan lalu: tidak dihitung
        $this->shift($cashier, 'open', null, null);

        foreach ([['100.00', 'pending'], ['25.50', 'proposed'], ['60.00', 'posted'], ['10.00', 'rejected']] as $i => [$amount, $status]) {
            DB::table('v2_payments')->insert([
                'invoice_id' => $receivable, 'shift_id' => $shiftOwn, 'amount' => $amount, 'method' => 'transfer', 'status' => $status,
                'requested_by' => $cashier->id, 'idempotency_key' => 'syn-pay-'.$i, 'request_hash' => str_repeat('b', 64),
                'created_at' => '2026-11-03 10:00:00', 'updated_at' => '2026-11-03 10:00:00',
            ]);
        }

        $this->delivery($shiftOwn, $cashier, 'prepared');
        $this->delivery($shiftOwn, $cashier, 'sent');
        $this->delivery($shiftOwn, $cashier, 'received', '2026-11-05 09:00:00');
        $this->delivery($shiftOwn, $cashier, 'received', '2026-11-04 09:00:00'); // bukan hari ini

        $vehicle = (int) DB::table('vehicles')->insertGetId(['license_plate' => 'SYN-V-1', 'vehicle_type' => 'pickup', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('vehicles')->insert(['license_plate' => 'SYN-V-2', 'vehicle_type' => 'truk', 'status' => 'inactive', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('v2_driver_advances')->insert([
            'vehicle_id' => $vehicle, 'driver_name' => 'Sopir Alpha', 'destination' => 'Zona Alpha', 'date' => '2026-11-05', 'amount' => '100.00',
            'method' => 'cash', 'status' => 'open', 'requested_by' => $admin->id, 'idempotency_key' => 'syn-adv-1',
            'request_hash' => str_repeat('c', 64), 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([['2026-11-02', '1000.00'], ['2026-11-04', '250.50'], ['2026-10-31', '999.00']] as $i => [$date, $total]) {
            DB::table('inventory_receipts')->insert([
                'receipt_number' => 'SYN-RCP-'.$i, 'receipt_type' => 'purchase', 'receipt_date' => $date, 'total_amount' => $total,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach ([['SYN-P1', '0', '5', true], ['SYN-P2', '3', '5', true], ['SYN-P3', '9', '5', true], ['SYN-P4', '2', '5', false]] as [$sku, $stock, $min, $active]) {
            DB::table('products')->insert([
                'sku' => $sku, 'name' => 'Produk '.$sku, 'unit' => 'sak', 'category' => 'Umum', 'cost_price' => '1', 'selling_price' => '2',
                'stock_quantity' => $stock, 'min_stock' => $min, 'is_active' => $active, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return [$admin, $cashier];
    }

    public function test_admin_snapshot_matches_full_database_figures_not_a_truncated_list(): void
    {
        [$admin] = $this->seedFigures();

        $s = app(MonitorMetrics::class)->snapshot('2026-11-05', true, $admin->id);

        $this->assertSame(['total' => '215.25', 'count' => 2, 'billed' => '275.25', 'paid' => '60.00'], $s['receivable']);
        $this->assertSame(['total' => '50.00', 'count' => 1], $s['payable']);
        $this->assertSame(['total' => '125.50', 'count' => 2], $s['awaiting']);
        $this->assertSame(1, $s['rejected_30d']);
        $this->assertSame(['total' => '100.00', 'count' => 1], $s['advances']);
        $this->assertSame(['prepared' => 1, 'sent' => 1, 'received_today' => 1, 'unassigned' => 1], $s['deliveries']);
        $this->assertSame(['total' => '1250.50', 'count' => 2, 'purchase' => 2, 'opening' => 0], $s['receipts_month']);
        $this->assertSame(['open' => 1, 'review' => 1, 'difference_month' => '-4.50', 'difference_unresolved' => '-3.00'], $s['shifts']);
        $this->assertSame(['active' => 3, 'archived' => 1, 'out' => 1, 'low' => 1], $s['stock']);
        $this->assertSame(['active' => 1, 'archived' => 0], $s['parties']['customer']);
        $this->assertSame(['active' => 1, 'archived' => 1], $s['parties']['supplier']);
        $this->assertSame(['active' => 1, 'inactive' => 1], $s['vehicles']);
        $this->assertSame(['admin' => 1, 'cashier' => 1, 'inactive' => 0], $s['accounts']);
    }

    public function test_cashier_shift_figures_are_limited_to_own_shifts_and_accounts_are_hidden(): void
    {
        [, $cashier] = $this->seedFigures();

        $s = app(MonitorMetrics::class)->snapshot('2026-11-05', false, $cashier->id);

        $this->assertSame(['open' => 1, 'review' => 0, 'difference_month' => '-1.50', 'difference_unresolved' => '0.00'], $s['shifts']);
        $this->assertNull($s['accounts']);
    }

    public function test_snapshot_on_an_empty_database_is_zero_and_does_not_fail(): void
    {
        $admin = $this->user('admin');

        $s = app(MonitorMetrics::class)->snapshot('2026-11-05', true, $admin->id);

        $this->assertSame(['total' => '0.00', 'count' => 0, 'billed' => '0.00', 'paid' => '0.00'], $s['receivable']);
        $this->assertSame('0.00', $s['shifts']['difference_month']);
        $this->assertSame('0.00', $s['shifts']['difference_unresolved']);
        $this->assertSame(0, $s['stock']['active']);
        $this->assertSame(['admin' => 1, 'cashier' => 0, 'inactive' => 0], $s['accounts']);
    }

    public function test_snapshot_only_reads(): void
    {
        [$admin] = $this->seedFigures();
        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = strtolower(ltrim($query->sql));
        });

        app(MonitorMetrics::class)->snapshot('2026-11-05', true, $admin->id);

        $this->assertNotEmpty($statements);
        foreach ($statements as $sql) {
            $this->assertStringStartsWith('select', $sql, 'MonitorMetrics hanya boleh membaca: '.$sql);
        }
    }

    public function test_resolution_clears_unresolved_amount_without_erasing_monthly_history_or_posting_journals(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 24)->setTime(12, 0));
        $admin = $this->user('admin');
        $current = $this->shift($admin, 'review', '-10.25', '2026-11-03 17:00:00');
        $previous = $this->shift($admin, 'review', '2.10', '2026-10-31 17:00:00');
        $this->shift($admin, 'closed', '-1.50', '2026-11-02 17:00:00');
        $this->shift($admin, 'closed', '-999.00', '2026-10-01 17:00:00');
        $metrics = app(MonitorMetrics::class);

        $before = $metrics->snapshot('2026-11-24', true, $admin->id)['shifts'];
        $this->assertSame('-11.75', $before['difference_month']);
        $this->assertSame('-8.15', $before['difference_unresolved']);
        $this->assertSame(2, $before['review']);

        $this->actingAs($admin)->postJson(route('v2.shifts.resolve', $current), ['reason' => 'Pemeriksaan sintetis telah selesai.'])->assertOk();
        $after = $metrics->snapshot('2026-11-24', true, $admin->id)['shifts'];
        $this->assertSame('-11.75', $after['difference_month']);
        $this->assertSame('2.10', $after['difference_unresolved']);
        $this->assertSame(1, $after['review']);
        $this->assertDatabaseHas('v2_shifts', ['id' => $current, 'difference' => '-10.25', 'status' => 'closed', 'reviewed_by' => $admin->id]);

        $this->postJson(route('v2.shifts.resolve', $previous), ['reason' => 'Pemeriksaan sintetis bulan lalu selesai.'])->assertOk();
        $settled = $metrics->snapshot('2026-11-24', true, $admin->id)['shifts'];
        $this->assertSame('0.00', $settled['difference_unresolved']);
        $this->assertSame(0, $settled['review']);
        $this->assertSame('-11.75', $settled['difference_month']);
        $this->assertSame('0.00', $metrics->snapshot('2026-12-01', true, $admin->id)['shifts']['difference_month']);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_entry_lines', 0);
    }

    public function test_offsetting_unresolved_differences_still_have_two_cases_to_review(): void
    {
        $admin = $this->user('admin');
        $this->shift($admin, 'review', '-3.05', '2026-11-02 17:00:00');
        $this->shift($admin, 'review', '3.05', '2026-10-31 17:00:00');

        $shifts = app(MonitorMetrics::class)->snapshot('2026-11-24', true, $admin->id)['shifts'];

        $this->assertSame('0.00', $shifts['difference_unresolved']);
        $this->assertSame(2, $shifts['review']);
    }

    public function test_unresolved_cashier_figures_keep_the_existing_own_and_shared_visibility_boundary(): void
    {
        $cashier = $this->user('cashier');
        $other = $this->user('admin');
        $this->shift($cashier, 'review', '-1.25', '2026-11-03 17:00:00');
        $this->shift($other, 'review', '-999.00', '2026-11-03 17:00:00');
        $shared = $this->shift($other, 'review', '0.10', '2026-10-31 17:00:00');
        DB::table('v2_shifts')->where('id', $shared)->update(['is_shared' => true]);

        $shifts = app(MonitorMetrics::class)->snapshot('2026-11-24', false, $cashier->id)['shifts'];

        $this->assertSame('-1.15', $shifts['difference_unresolved']);
        $this->assertSame(2, $shifts['review']);
    }

    public function test_admin_can_find_an_unresolved_shift_older_than_the_latest_thirty_records(): void
    {
        $admin = $this->user('admin');
        $old = $this->shift($admin, 'review', '-5.25', '2026-10-31 17:00:00');
        for ($index = 0; $index < 31; $index++) {
            $this->shift($admin, 'closed', '0.00', '2026-11-03 17:00:00');
        }

        $this->actingAs($admin)->get(route('v2.index'))->assertOk()
            ->assertSee(route('v2.shifts.resolve', $old), false)
            ->assertSee('Belum diselesaikan')
            ->assertSee('Selisih bulan ini (riwayat)');
    }
}
