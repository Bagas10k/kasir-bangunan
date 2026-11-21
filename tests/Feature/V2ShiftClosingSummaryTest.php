<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\Operations;
use App\Services\V2\ShiftClosingSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Rekap tutup shift: rincian penjualan dan laci dihitung otomatis, lalu dibandingkan
 * dengan uang fisik yang dihitung admin. Read-only. Data sintetis, SQLite :memory:.
 */
class V2ShiftClosingSummaryTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Shift closing summary tests require isolated SQLite :memory:.');
        }
    }

    private function actor(string $role, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function movement(int $shift, string $direction, string $kind, string $amount): void
    {
        DB::table('v2_shift_movements')->insert([
            'shift_id' => $shift, 'direction' => $direction, 'kind' => $kind, 'amount' => $amount,
            'reference_type' => 'syn-'.$direction.'-'.$kind.'-'.++$this->seq, 'reference_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function sale(int $shift, int $actor, string $method, string $total): void
    {
        DB::table('v2_sales')->insert([
            'number' => 'SYN-SALE-'.++$this->seq, 'user_id' => $actor, 'shift_id' => $shift,
            'subtotal' => $total, 'total' => $total, 'payment_method' => $method, 'status' => 'paid',
            'fulfillment' => 'pickup', 'request_hash' => str_repeat('a', 64), 'idempotency_key' => 'syn-sale-key-'.$this->seq,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function pendingDrawerPayment(int $shift, int $actor): int
    {
        $invoice = DB::table('v2_invoices')->insertGetId(['direction' => 'payable', 'party_id' => null, 'number' => 'AP-SYN-'.++$this->seq, 'amount' => '1000.00', 'paid' => '0.00', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('v2_payments')->insertGetId([
            'invoice_id' => $invoice, 'shift_id' => $shift, 'amount' => '100.00', 'method' => 'cash', 'status' => 'proposed',
            'requested_by' => $actor, 'reference' => null, 'idempotency_key' => 'syn-pay-key-'.++$this->seq,
            'request_hash' => str_repeat('b', 64), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_summary_breaks_down_sales_and_drawer_exactly_and_matches_close_expected(): void
    {
        $cashier = $this->actor('cashier', 'Kasir Sintetis');
        $ops = app(Operations::class);
        $admin = $this->storeShiftAdmin();
        $shift = $ops->openShift($admin->id, '100.10', 'admin');
        $other = DB::table('v2_shifts')->insertGetId(['user_id' => $this->actor('cashier', 'Kasir Lain')->id, 'source' => 'admin', 'opening_cash' => '999.00', 'status' => 'closed', 'opened_at' => now(), 'closed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->movement($shift, 'in', 'sale', '20.15');
        $this->movement($shift, 'in', 'collection', '7.05');
        $this->movement($shift, 'in', 'adjustment', '3.10');
        $this->movement($shift, 'out', 'supplier', '4.20');
        $this->movement($shift, 'out', 'adjustment', '1.01');
        $this->movement($other, 'in', 'sale', '999.99');
        $this->sale($shift, $cashier->id, 'cash', '20.15');
        $this->sale($shift, $cashier->id, 'transfer', '30.25');
        $this->sale($shift, $cashier->id, 'credit', '40.35');
        $this->sale($other, $cashier->id, 'cash', '999.00');

        DB::enableQueryLog();
        $summary = app(ShiftClosingSummary::class)->build($cashier->id, $shift);
        foreach (DB::getQueryLog() as $query) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|replace)\b/i', $query['query']);
        }
        DB::disableQueryLog();

        $this->assertSame($shift, $summary['shift_id']);
        $this->assertSame($admin->name, $summary['opened_by']);
        $this->assertIsString($summary['opened_at']);
        $this->assertSame('store', $summary['scope']);
        $this->assertSame(['count' => 3, 'total' => '90.75', 'cash' => '20.15', 'transfer' => '30.25', 'credit' => '40.35'], $summary['sales']);
        $this->assertSame(['opening' => '100.10', 'sales' => '20.15', 'collections' => '7.05', 'other_in' => '3.10', 'in' => '30.30', 'supplier_out' => '4.20', 'other_out' => '1.01', 'out' => '5.21', 'expected' => '125.19'], $summary['cash']);
        $this->assertSame(0, $summary['pending_drawer_payments']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $summary['fingerprint']);

        $this->assertSame($summary, app(ShiftClosingSummary::class)->build($cashier->id, $shift));
        $this->assertSame('125.19', $ops->closeShift($admin->id, $shift, '125.19')['expected']);
    }

    public function test_pending_drawer_payments_are_reported_and_fingerprint_tracks_changes(): void
    {
        $cashier = $this->actor('cashier', 'Kasir Sintetis');
        $ops = app(Operations::class);
        $shift = $ops->openShift($this->storeShiftAdmin()->id, '0.00', 'admin');
        $service = app(ShiftClosingSummary::class);

        $before = $service->build($cashier->id, $shift);
        $payment = $this->pendingDrawerPayment($shift, $cashier->id);

        $after = $service->build($cashier->id, $shift);
        $this->assertSame(1, $after['pending_drawer_payments']);
        $this->assertNotSame($before['fingerprint'], $after['fingerprint']);

        $this->movement($shift, 'in', 'sale', '7.50');
        $again = $service->build($cashier->id, $shift);
        $this->assertNotSame($after['fingerprint'], $again['fingerprint']);
        $this->assertSame('7.50', $again['cash']['expected']);
        $this->assertSame(1, $again['pending_drawer_payments']);
        $this->assertSame($payment, DB::table('v2_payments')->where('id', $payment)->value('id'));
    }

    public function test_scope_authorization_and_state_guards(): void
    {
        $cashierA = $this->actor('cashier', 'Kasir A');
        $cashierB = $this->actor('cashier', 'Kasir B');
        $admin = $this->actor('admin', 'Admin Sintetis');
        $ops = app(Operations::class);
        $shift = $ops->openShift($admin->id, '10.00', 'admin');
        $service = app(ShiftClosingSummary::class);

        $this->assertSame($shift, $service->build($cashierA->id, $shift)['shift_id']);
        $this->assertSame($shift, $service->build($admin->id, $shift)['shift_id']);

        $this->assertSame($shift, $service->build($cashierB->id, $shift)['shift_id']);
        $legacy = DB::table('v2_shifts')->insertGetId(['user_id' => $cashierA->id, 'source' => 'admin', 'opening_cash' => '1.00', 'status' => 'open', 'opened_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        try {
            $service->build($cashierB->id, $legacy);
            $this->fail('Shift pribadi historis tetap bukan milik kasir lain.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        try {
            $service->build($cashierA->id, 999999);
            $this->fail('Shift tidak ada harus 404.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }

        $ops->closeShift($admin->id, $shift, '10.00');
        $this->expectException(ValidationException::class);
        $service->build($cashierA->id, $shift);
    }

    public function test_closing_guard_rejects_a_stale_fingerprint(): void
    {
        $cashier = $this->actor('cashier', 'Kasir Sintetis');
        $ops = app(Operations::class);
        $admin = $this->storeShiftAdmin();
        $shift = $ops->openShift($admin->id, '0.00', 'admin');
        $service = app(ShiftClosingSummary::class);

        $stale = $service->build($cashier->id, $shift)['fingerprint'];
        $this->movement($shift, 'in', 'sale', '5.00');

        try {
            $ops->closeShift($admin->id, $shift, '0.00', $stale);
            $this->fail('Rekap yang kedaluwarsa harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('operations', $e->errors());
        }
        $this->assertSame('open', DB::table('v2_shifts')->where('id', $shift)->value('status'));

        $fresh = $service->build($cashier->id, $shift)['fingerprint'];
        $this->assertSame('5.00', $ops->closeShift($admin->id, $shift, '5.00', $fresh)['expected']);
    }

    public function test_http_preview_returns_recap_and_enforces_scope(): void
    {
        $cashierA = $this->actor('cashier', 'Kasir A');
        $cashierB = $this->actor('cashier', 'Kasir B');
        $ops = app(Operations::class);
        $shift = $ops->openShift($this->storeShiftAdmin()->id, '50.00', 'admin');
        $this->movement($shift, 'in', 'sale', '12.00');

        $url = route('v2.shifts.closing-summary', $shift);
        $this->actingAs($cashierA)->getJson($url)->assertOk()
            ->assertJsonPath('shift_id', $shift)
            ->assertJsonPath('cash.expected', '62.00')
            ->assertJsonPath('cash.sales', '12.00')
            ->assertJsonPath('pending_drawer_payments', 0)
            ->assertJsonPath('fingerprint', fn (string $f) => preg_match('/^[a-f0-9]{64}$/', $f) === 1);

        $this->actingAs($cashierB)->getJson($url)->assertOk()->assertJsonPath('scope', 'store');
        $legacy = DB::table('v2_shifts')->insertGetId(['user_id' => $cashierA->id, 'source' => 'admin', 'opening_cash' => '1.00', 'status' => 'open', 'opened_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson(route('v2.shifts.closing-summary', $legacy))->assertForbidden();
    }

    public function test_cashier_sales_remain_private_while_drawer_totals_are_shared(): void
    {
        $admin = $this->storeShiftAdmin();
        $a = $this->actor('cashier', 'Kasir A');
        $b = $this->actor('cashier', 'Kasir B');
        $shift = app(Operations::class)->openShift($admin->id, '100.00', 'admin');
        $this->sale($shift, $a->id, 'cash', '10.00');
        $this->sale($shift, $b->id, 'transfer', '20.00');
        $this->sale($shift, $b->id, 'credit', '30.00');
        $this->movement($shift, 'in', 'sale', '10.00');
        $url = route('v2.shifts.closing-summary', $shift);

        $this->actingAs($a)->getJson($url)->assertOk()
            ->assertJsonPath('sales_scope', 'own')
            ->assertJsonPath('sales.count', 1)->assertJsonPath('sales.total', '10.00')
            ->assertJsonPath('sales.transfer', '0.00')->assertJsonPath('sales.credit', '0.00')
            ->assertJsonPath('cash.expected', '110.00');
        $this->actingAs($b)->getJson($url)->assertOk()
            ->assertJsonPath('sales.count', 2)->assertJsonPath('sales.total', '50.00')
            ->assertJsonPath('sales.cash', '0.00')->assertJsonPath('cash.expected', '110.00');
        $this->actingAs($admin)->getJson($url)->assertOk()
            ->assertJsonPath('sales_scope', 'all')
            ->assertJsonPath('sales.count', 3)->assertJsonPath('sales.total', '60.00')
            ->assertJsonPath('cash.expected', '110.00');
    }
}
