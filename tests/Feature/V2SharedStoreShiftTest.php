<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\Ledger;
use App\Services\V2\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2SharedStoreShiftTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Shared shift tests require isolated SQLite :memory:.');
        }
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function product(): int
    {
        foreach ([['1101', 'asset', 'debit'], ['1102', 'asset', 'debit'], ['1103', 'asset', 'debit'], ['1301', 'asset', 'debit'], ['2101', 'liability', 'credit'], ['3101', 'equity', 'credit'], ['4101', 'revenue', 'credit'], ['4102', 'revenue', 'credit'], ['5101', 'expense', 'debit']] as [$code, $type, $normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore(['account_code' => $code, 'account_name' => 'Sintetis '.$code, 'account_type' => $type, 'normal_balance' => $normal, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        Ledger::post('synthetic_opening', 1, 'Saldo sintetis', [['account_code' => '1101', 'debit' => '1000', 'credit' => '0'], ['account_code' => '3101', 'debit' => '0', 'credit' => '1000']]);

        return DB::table('products')->insertGetId(['sku' => 'SYN-SHARED', 'name' => 'Material Sintetis', 'unit' => 'unit', 'category' => 'Umum', 'cost_price' => '60', 'selling_price' => '100', 'stock_quantity' => '10', 'min_stock' => '1', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function sale(int $product, string $key): array
    {
        return ['items' => [['product_id' => $product, 'quantity' => '1']], 'payment_method' => 'cash', 'paid_amount' => '100', 'idempotency_key' => $key, 'fulfillment' => 'pickup'];
    }

    private function invoice(string $direction): int
    {
        return DB::table('v2_invoices')->insertGetId(['direction' => $direction, 'number' => 'SYN-'.$direction, 'amount' => '100', 'paid' => '0', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_cashiers_share_admin_drawer_without_losing_actor_attribution(): void
    {
        $admin = $this->actor('admin');
        $a = $this->actor('cashier');
        $b = $this->actor('cashier');
        $product = $this->product();
        $ops = app(Operations::class);
        $shift = $ops->openShift($admin->id, '100', 'admin');
        $this->assertSame($shift, (int) $ops->activeStoreShift()->id);
        foreach ([$a, $b] as $cashier) {
            $sale = $ops->checkout($cashier->id, $this->sale($product, 'sale-'.$cashier->id));
            $this->assertDatabaseHas('v2_sales', ['id' => $sale['sale_id'], 'user_id' => $cashier->id, 'shift_id' => $shift]);
            $this->assertDatabaseHas('v2_payments', ['invoice_id' => $sale['invoice_id'], 'requested_by' => $cashier->id, 'shift_id' => $shift]);
        }
        $collection = $ops->collect($b->id, $this->invoice('receivable'), '50', 'cash', 'collection');
        $this->assertDatabaseHas('v2_payments', ['id' => $collection, 'shift_id' => $shift, 'requested_by' => $b->id]);
        $payment = $ops->collect($a->id, $this->invoice('payable'), '25', 'cash', 'supplier');
        $ops->approvePayment($admin->id, $payment);
        $this->assertDatabaseHas('v2_payments', ['id' => $payment, 'shift_id' => $shift, 'requested_by' => $a->id, 'status' => 'posted']);
        $this->assertSame('325.00', $ops->closeShift($admin->id, $shift, '325')['expected']);
        $this->assertDatabaseHas('v2_shifts', ['id' => $shift, 'user_id' => $admin->id, 'closed_by' => $admin->id, 'is_shared' => true, 'store_open_key' => null]);
        $this->assertNull($ops->activeStoreShift());
        $this->actingAs($a)->postJson(route('v2.sales.store'), $this->sale($product, 'closed'))->assertUnprocessable();
    }

    public function test_only_admin_can_open_or_close_and_second_admin_cannot_open(): void
    {
        $admin = $this->actor('admin');
        $cashier = $this->actor('cashier');
        $other = $this->actor('admin');
        $this->actingAs($cashier)->postJson(route('v2.shifts.open'), ['cash' => '100', 'source' => 'admin'])->assertForbidden();
        $shift = app(Operations::class)->openShift($admin->id, '100', 'admin');
        $this->actingAs($cashier)->postJson(route('v2.shifts.close', $shift), ['counted' => '100'])->assertForbidden();
        $this->actingAs($other)->postJson(route('v2.shifts.open'), ['cash' => '100', 'source' => 'admin'])->assertUnprocessable();
        $this->assertDatabaseCount('v2_shifts', 1);
    }

    public function test_pending_cash_payment_cannot_move_to_next_store_shift(): void
    {
        $admin = $this->actor('admin');
        $cashier = $this->actor('cashier');
        $this->product();
        $ops = app(Operations::class);
        $shift = $ops->openShift($admin->id, '100', 'admin');
        $payment = $ops->collect($cashier->id, $this->invoice('payable'), '25', 'cash', 'pending');
        $ops->closeShift($admin->id, $shift, '100');
        $next = $ops->openShift($admin->id, '100', 'admin');
        $this->actingAs($admin)->postJson(route('v2.payments.approve', $payment))->assertUnprocessable();
        $this->assertDatabaseHas('v2_payments', ['id' => $payment, 'shift_id' => $shift, 'status' => 'proposed']);
        $this->assertSame(0, DB::table('v2_shift_movements')->where('shift_id', $next)->count());
    }

    public function test_both_workspaces_show_same_active_shift_and_cashier_cannot_manage_it(): void
    {
        $admin = $this->actor('admin');
        $cashier = $this->actor('cashier');
        $ops = app(Operations::class);
        $shift = $ops->openShift($admin->id, '100', 'admin');
        foreach ([$admin, $cashier] as $actor) {
            $response = $this->actingAs($actor)->get(route('v2.index'))->assertOk();
            $this->assertStringContainsString('data-store-shift-id="'.$shift.'"', $response->getContent());
            $this->assertStringContainsString('data-pos-shift-state="open"', $response->getContent());
            $this->assertStringContainsString('Shift toko #'.$shift.' aktif', $response->getContent());
        }
        $this->assertStringNotContainsString('id="form-open"', $response->getContent());
        $this->assertStringNotContainsString('class="form-close"', $response->getContent());
        $ops->closeShift($admin->id, $shift, '100');
        $response = $this->actingAs($cashier)->get(route('v2.index'))->assertOk();
        $this->assertStringContainsString('data-pos-shift-state="closed"', $response->getContent());
        $this->assertStringContainsString('Menunggu admin membuka shift toko', $response->getContent());
    }

    public function test_active_shift_endpoint_is_authenticated_read_only_and_shared(): void
    {
        $this->getJson('/v2/shifts/active')->assertUnauthorized();
        $admin = $this->actor('admin');
        $cashier = $this->actor('cashier');
        $this->actingAs($cashier)->getJson('/v2/shifts/active')->assertOk()->assertJsonPath('shift', null);
        $shift = app(Operations::class)->openShift($admin->id, '100', 'admin');
        foreach ([$admin, $cashier] as $actor) {
            $response = $this->actingAs($actor)->getJson('/v2/shifts/active')->assertOk()
                ->assertJsonPath('shift.id', $shift)->assertJsonPath('shift.opened_by', $admin->name);
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertArrayNotHasKey('opening_cash', $response->json('shift'));
        }
        $this->assertDatabaseCount('v2_shifts', 1);
        $this->assertDatabaseCount('v2_shift_movements', 0);
    }

    /** @param list<User> $openers @return list<int> */
    private function upgradeLegacyShifts(array $openers): array
    {
        $migration = require database_path('migrations/2026_11_20_134340_add_shared_store_shift_to_v2_shifts.php');
        $migration->down();
        $ids = [];
        foreach ($openers as $opener) {
            $ids[] = DB::table('v2_shifts')->insertGetId([
                'user_id' => $opener->id, 'open_user_id' => $opener->id, 'source' => 'admin',
                'opening_cash' => '17.25', 'admin_amount' => '17.25', 'status' => 'open',
                'opened_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $migration->up();

        return $ids;
    }

    public function test_upgrade_reuses_a_single_admin_shift_without_changing_its_money(): void
    {
        $admin = $this->actor('admin');
        [$id] = $this->upgradeLegacyShifts([$admin]);

        $this->assertSame($id, (int) app(Operations::class)->activeStoreShift()->id);
        $this->assertDatabaseHas('v2_shifts', ['id' => $id, 'user_id' => $admin->id, 'is_shared' => true, 'store_open_key' => 1, 'opening_cash' => '17.25', 'admin_amount' => '17.25', 'status' => 'open']);
        $this->assertDatabaseCount('v2_shifts', 1);
        $this->assertDatabaseCount('v2_shift_movements', 0);
    }

    public function test_upgrade_does_not_merge_multiple_legacy_drawers(): void
    {
        $admin = $this->actor('admin');
        $cashier = $this->actor('cashier');
        $ids = $this->upgradeLegacyShifts([$admin, $cashier]);
        $ops = app(Operations::class);

        $this->assertNull($ops->activeStoreShift());
        $this->actingAs($admin)->postJson(route('v2.shifts.open'), ['cash' => '0', 'source' => 'admin'])->assertUnprocessable();
        foreach ($ids as $id) {
            $this->assertDatabaseHas('v2_shifts', ['id' => $id, 'is_shared' => false, 'opening_cash' => '17.25', 'status' => 'open']);
            $ops->closeShift($admin->id, $id, '17.25');
        }
        $shared = $ops->openShift($admin->id, '0', 'admin');
        $this->assertSame($shared, (int) $ops->activeStoreShift()->id);
        $this->assertDatabaseCount('v2_shifts', 3);
    }

    public function test_upgrade_keeps_a_legacy_cashier_drawer_until_admin_closes_it(): void
    {
        $admin = $this->actor('admin');
        $cashier = $this->actor('cashier');
        [$id] = $this->upgradeLegacyShifts([$cashier]);
        $ops = app(Operations::class);

        $this->assertNull($ops->activeStoreShift());
        $this->actingAs($admin)->postJson(route('v2.shifts.open'), ['cash' => '0', 'source' => 'admin'])->assertUnprocessable();
        $this->assertDatabaseHas('v2_shifts', ['id' => $id, 'user_id' => $cashier->id, 'is_shared' => false, 'opening_cash' => '17.25', 'status' => 'open']);
        $ops->closeShift($admin->id, $id, '17.25');
        $this->assertSame('closed', DB::table('v2_shifts')->where('id', $id)->value('status'));
        $this->assertNotSame($id, $ops->openShift($admin->id, '0', 'admin'));
        $this->assertDatabaseCount('v2_shifts', 2);
    }
}
