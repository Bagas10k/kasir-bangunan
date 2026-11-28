<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2CutoverTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Isolated SQLite only');
        }
    }

    private function actor(string $role): User
    {
        $u = User::factory()->create();
        DB::table('v2_user_roles')->insert(['user_id' => $u->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return $u;
    }

    public function test_cutover_blocks_legacy_writes_and_cashier_bypass_but_keeps_admin_archive(): void
    {
        config(['v2.enabled' => true]);
        $cashier = $this->actor('cashier');
        $admin = $this->actor('admin');
        $this->actingAs($cashier)->get('/')->assertOk()->assertSee('Ruang kerja Kasir')->assertDontSee('data-task="akun"', false);
        $this->get('/v2?report_date=2026-11-05')->assertRedirect(route('pos.index', ['report_date' => '2026-11-05']));
        $this->get('/products')->assertForbidden();
        $this->postJson('/products', ['sku' => 'SYN-BYPASS'])->assertForbidden();
        $this->postJson('/v2/master/customer', ['code' => 'SYN-DENIED', 'name' => 'Synthetic'])->assertForbidden();
        $this->getJson(route('v2.accounts.index'))->assertForbidden();
        $this->postJson(route('v2.relations.store', 'unit'), ['name' => 'Satuan Sintetis'])->assertForbidden();
        $this->postJson(route('v2.payments.approve', 999))->assertForbidden();
        $this->postJson(route('v2.shifts.resolve', 999), ['reason' => 'Catatan sintetis pemeriksaan'])->assertForbidden();
        $this->actingAs($admin)->postJson('/products', ['sku' => 'SYN-BYPASS'])->assertForbidden();
        foreach ([
            ['POST', '/pos/checkout'],
            ['POST', '/stock-opname'],
            ['POST', '/expenses'],
            ['PUT', '/expenses/1'],
            ['DELETE', '/expenses/1'],
            ['POST', '/inventory/receipts'],
            ['POST', '/ritase'],
            ['PATCH', '/ritase/1/details'],
            ['POST', '/coa'],
            ['PUT', '/coa/1'],
            ['DELETE', '/coa/1'],
            ['POST', '/journals/adjustments'],
            ['POST', '/journals/opening-balance'],
            ['POST', '/journals/opening-balance/post'],
            ['POST', '/vehicles'],
            ['POST', '/product-options'],
            ['DELETE', '/product-options/1'],
        ] as [$method, $uri]) {
            $this->actingAs($admin)->json($method, $uri, [])->assertForbidden();
            $this->actingAs($cashier)->json($method, $uri, [])->assertForbidden();
        }
        foreach ([
            ['GET', route('v2.stock-opnames.products')],
            ['POST', route('v2.stock-opnames.store')],
            ['POST', route('v2.sales.returns.store', 999)],
            ['GET', route('v2.receipts.returnable', 999)],
            ['POST', route('v2.receipts.returns.store', 999)],
        ] as [$method, $uri]) {
            $this->actingAs($cashier)->json($method, $uri, [])->assertForbidden();
        }
        $this->actingAs($admin)->get('/products')->assertOk();
        $this->get('/v2')->assertRedirect(route('pos.index'));
        $this->get('/')->assertOk()->assertSee('Ruang kerja Admin')->assertSee('data-task="akun"', false);
        $this->get('/demo-setup')->assertNotFound();
        $this->post('/demo-setup')->assertNotFound();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_unified_workspace_requires_login_and_an_assigned_role(): void
    {
        config(['v2.enabled' => true]);
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/v2')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/')->assertForbidden();
        $this->get('/v2')->assertForbidden();
    }

    public function test_financial_pages_share_workspace_navigation_and_deny_cashiers(): void
    {
        config(['v2.enabled' => true]);
        $admin = $this->actor('admin');
        $cashier = $this->actor('cashier');
        foreach (['reports.profit_loss', 'reports.balance_sheet', 'reports.cash_flow', 'journals.index', 'coa.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk()
                ->assertSee('data-unified-shell', false)
                ->assertSee('data-menu-group="master"', false)
                ->assertSee('data-menu-group="keuangan"', false)
                ->assertSee('href="'.route($route).'"', false)
                ->assertSee('aria-current="page"', false)
                ->assertDontSee('class="nav-group-label"', false);
            $this->actingAs($cashier)->get(route($route))->assertForbidden();
        }
    }

    public function test_explicit_prepare_is_read_only_by_default_and_adds_only_roles_and_accounts(): void
    {
        $u = User::factory()->create();
        $this->assertSame(0, Artisan::call('v2:prepare', ['--admin' => (string) $u->id]));
        $this->assertDatabaseCount('chart_of_accounts', 0);
        $this->assertDatabaseCount('v2_user_roles', 0);
        $this->assertSame(0, Artisan::call('v2:prepare', ['--admin' => (string) $u->id, '--apply' => true]));
        $this->assertDatabaseHas('v2_user_roles', ['user_id' => $u->id, 'role' => 'admin']);
        $this->assertDatabaseCount('chart_of_accounts', 16);
        $this->assertDatabaseHas('chart_of_accounts', ['account_code' => '4103', 'normal_balance' => 'debit', 'is_contra' => true]);
        $this->assertDatabaseHas('chart_of_accounts', ['account_code' => '4104', 'normal_balance' => 'debit', 'is_contra' => true]);
        $this->assertDatabaseHas('chart_of_accounts', ['account_code' => '6201']);
        $this->assertDatabaseHas('chart_of_accounts', ['account_code' => '5201']);
        $this->assertDatabaseHas('chart_of_accounts', ['account_code' => '7101']);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertSame(0, Artisan::call('v2:prepare', ['--admin' => (string) $u->id, '--apply' => true]));
        $this->assertDatabaseCount('chart_of_accounts', 16);
    }

    public function test_prepare_refuses_unmapped_legacy_receivable_without_writes(): void
    {
        $u = User::factory()->create();
        DB::table('sales')->insert(['customer_type' => 'retail', 'payment_method' => 'credit', 'total_amount' => '100', 'cogs_amount' => '60', 'paid_amount' => '20', 'change_amount' => '0', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(1, Artisan::call('v2:prepare', ['--admin' => (string) $u->id, '--apply' => true]));
        $this->assertDatabaseCount('v2_user_roles', 0);
        $this->assertDatabaseCount('chart_of_accounts', 0);
    }
}
