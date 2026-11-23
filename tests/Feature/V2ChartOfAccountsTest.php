<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2ChartOfAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('COA tests require isolated SQLite :memory:.');
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

    private function payload(string $code = '6198'): array
    {
        return [
            'account_code' => $code, 'account_name' => 'Akun Sintetis',
            'account_type' => 'expense', 'normal_balance' => 'debit', 'is_active' => true,
        ];
    }

    private function account(string $code = '6198'): int
    {
        return DB::table('chart_of_accounts')->insertGetId($this->payload($code) + [
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_admin_can_create_update_and_archive_without_changing_journals(): void
    {
        config(['v2.enabled' => true]);
        $this->actingAs($this->actor());

        $this->postJson('/v2/chart-of-accounts', $this->payload())
            ->assertCreated()->assertJsonPath('message', 'Akun 6198 berhasil ditambahkan.');
        $id = DB::table('chart_of_accounts')->where('account_code', '6198')->value('id');
        $this->putJson('/v2/chart-of-accounts/'.$id, ['account_name' => 'Beban Sintetis Baru'] + $this->payload())
            ->assertOk();
        $this->assertDatabaseHas('chart_of_accounts', ['id' => $id, 'account_name' => 'Beban Sintetis Baru']);
        $this->patchJson('/v2/chart-of-accounts/'.$id.'/archive')->assertOk();
        $this->assertDatabaseHas('chart_of_accounts', ['id' => $id, 'is_active' => false]);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_entry_lines', 0);
    }

    public function test_routes_deny_guests_cashiers_and_accounts_without_a_role(): void
    {
        config(['v2.enabled' => true]);
        $id = $this->account();
        $this->postJson('/v2/chart-of-accounts', $this->payload('6197'))->assertUnauthorized();
        foreach ([$this->actor('cashier'), User::factory()->create()] as $user) {
            $this->actingAs($user);
            $this->postJson('/v2/chart-of-accounts', $this->payload('6197'))->assertForbidden();
            $this->putJson('/v2/chart-of-accounts/'.$id, $this->payload())->assertForbidden();
            $this->patchJson('/v2/chart-of-accounts/'.$id.'/archive')->assertForbidden();
        }
        $this->assertDatabaseCount('chart_of_accounts', 1);
        $this->assertDatabaseHas('chart_of_accounts', ['id' => $id, 'is_active' => true]);
    }

    public function test_native_form_method_override_reaches_update_and_archive_routes(): void
    {
        config(['v2.enabled' => true]);
        $id = $this->account();
        $this->actingAs($this->actor());

        $this->post('/v2/chart-of-accounts/'.$id, [
            '_method' => 'PUT', 'account_name' => 'Nama Dari Form',
        ] + $this->payload(), ['Accept' => 'application/json'])->assertOk();
        $this->assertDatabaseHas('chart_of_accounts', ['id' => $id, 'account_name' => 'Nama Dari Form']);
        $this->post('/v2/chart-of-accounts/'.$id.'/archive', ['_method' => 'PATCH'], ['Accept' => 'application/json'])->assertOk();
        $this->assertDatabaseHas('chart_of_accounts', ['id' => $id, 'is_active' => false]);
    }

    public function test_new_routes_still_require_admin_when_cutover_is_disabled(): void
    {
        config(['v2.enabled' => false]);
        $this->actingAs($this->actor('cashier'))
            ->postJson('/v2/chart-of-accounts', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('chart_of_accounts', 0);
    }

    public function test_duplicate_code_and_invalid_classification_are_rejected(): void
    {
        $this->actingAs($this->actor());
        $this->account();

        $this->postJson('/v2/chart-of-accounts', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('account_code');
        $this->postJson('/v2/chart-of-accounts', ['account_type' => 'asset'] + $this->payload('6197'))
            ->assertUnprocessable()->assertJsonValidationErrors('account_code');
        $this->postJson('/v2/chart-of-accounts', ['normal_balance' => 'credit'] + $this->payload('6197'))
            ->assertUnprocessable()->assertJsonValidationErrors('normal_balance');
        $this->assertDatabaseCount('chart_of_accounts', 1);
    }

    public function test_new_core_accounts_cannot_be_created_inactive_renumbered_or_archived(): void
    {
        $this->actingAs($this->actor());
        foreach ([['1191', 'asset', 'debit'], ['4102', 'revenue', 'credit'], ['6101', 'expense', 'debit']] as [$code, $type, $normal]) {
            $payload = ['account_code' => $code, 'account_type' => $type, 'normal_balance' => $normal] + $this->payload();
            $this->postJson('/v2/chart-of-accounts', ['is_active' => false] + $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('is_active');
            $this->postJson('/v2/chart-of-accounts', $payload)->assertCreated();
            $id = DB::table('chart_of_accounts')->where('account_code', $code)->value('id');
            $this->putJson('/v2/chart-of-accounts/'.$id, ['account_code' => $code.'9'] + $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('account_code');
            $this->putJson('/v2/chart-of-accounts/'.$id, ['is_active' => false] + $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('is_active');
            $this->patchJson('/v2/chart-of-accounts/'.$id.'/archive')
                ->assertUnprocessable()->assertJsonValidationErrors('is_active');
            $this->assertDatabaseHas('chart_of_accounts', ['id' => $id, 'account_code' => $code, 'is_active' => true]);
        }
    }

    public function test_used_account_keeps_its_identity_and_original_journal_names(): void
    {
        $this->actingAs($this->actor());
        $id = $this->account();
        $journal = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'synthetic', 'description' => 'Jurnal sintetis', 'transaction_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            ['journal_entry_id' => $journal, 'account_code' => '6198', 'account_name' => 'Nama Historis', 'debit' => '10.00', 'credit' => '0.00', 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $journal, 'account_code' => '1101', 'account_name' => 'Kas Historis', 'debit' => '0.00', 'credit' => '10.00', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->putJson('/v2/chart-of-accounts/'.$id, ['account_code' => '6197'] + $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('account_code');
        $this->putJson('/v2/chart-of-accounts/'.$id, ['account_name' => 'Nama Sekarang'] + $this->payload())->assertOk();
        $this->patchJson('/v2/chart-of-accounts/'.$id.'/archive')->assertOk();
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $journal, 'account_name' => 'Nama Historis', 'debit' => '10.00']);
        $this->assertDatabaseCount('journal_entry_lines', 2);
    }

    public function test_missing_accounts_return_not_found_and_deletion_has_no_operational_route(): void
    {
        $this->actingAs($this->actor());
        $this->putJson('/v2/chart-of-accounts/999', $this->payload())->assertNotFound();
        $this->patchJson('/v2/chart-of-accounts/999/archive')->assertNotFound();
        $this->deleteJson('/v2/chart-of-accounts/999')->assertMethodNotAllowed();
    }

    public function test_v2_page_is_read_only_until_explicit_save_and_uses_operational_actions(): void
    {
        config(['v2.enabled' => true]);
        $this->actingAs($this->actor());

        $this->get(route('coa.index', ['partial' => 1]))->assertOk()
            ->assertSee('id="coa-operational-script"', false)
            ->assertSee('/v2/chart-of-accounts', false);
        $this->assertDatabaseCount('chart_of_accounts', 0);
        $this->postJson('/coa', $this->payload())->assertForbidden();
        $this->postJson('/journals/adjustments', [])->assertForbidden();
    }

    public function test_v2_view_offers_archive_not_delete_for_unused_custom_accounts(): void
    {
        config(['v2.enabled' => true]);
        $id = $this->account();

        $this->actingAs($this->actor())->get(route('coa.index'))->assertOk()
            ->assertSee('Arsipkan')
            ->assertSee('/v2/chart-of-accounts/'.$id.'/archive', false)
            ->assertDontSee('Hapus akun ini dari Bagan Akun?');
    }

    public function test_inactive_custom_account_cannot_be_renamed_into_an_inactive_system_account(): void
    {
        $id = $this->account();
        DB::table('chart_of_accounts')->where('id', $id)->update(['is_active' => false]);

        $this->actingAs($this->actor())->putJson('/v2/chart-of-accounts/'.$id, [
            'account_code' => '4102', 'account_name' => 'Ongkir',
            'account_type' => 'revenue', 'normal_balance' => 'credit', 'is_active' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('is_active');
        $this->assertDatabaseHas('chart_of_accounts', ['id' => $id, 'account_code' => '6198', 'is_active' => false]);
        $this->assertDatabaseMissing('chart_of_accounts', ['account_code' => '4102']);
    }

    public function test_accounts_only_repair_preserves_existing_books_and_roles_and_is_read_only_by_default(): void
    {
        $admin = $this->actor();
        $this->account();
        $journal = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'synthetic', 'description' => 'Jurnal historis sintetis', 'transaction_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            ['journal_entry_id' => $journal, 'account_code' => '2101', 'account_name' => 'Utang Historis', 'debit' => '0.00', 'credit' => '10.00', 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $journal, 'account_code' => '1301', 'account_name' => 'Persediaan Historis', 'debit' => '10.00', 'credit' => '0.00', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $roles = DB::table('v2_user_roles')->get()->toJson();
        $lines = DB::table('journal_entry_lines')->get()->toJson();
        $options = ['--admin' => (string) $admin->id, '--accounts-only' => true];

        $this->assertSame(0, Artisan::call('v2:prepare', $options));
        $this->assertDatabaseCount('chart_of_accounts', 1);
        $this->assertSame(0, Artisan::call('v2:prepare', $options + ['--apply' => true]));
        $this->assertDatabaseHas('chart_of_accounts', ['account_code' => '4102', 'account_type' => 'revenue', 'normal_balance' => 'credit', 'is_active' => true]);
        $this->assertDatabaseCount('chart_of_accounts', 12);
        $this->assertSame(0, Artisan::call('v2:prepare', $options + ['--apply' => true]));
        $this->assertDatabaseCount('chart_of_accounts', 12);
        $this->assertSame($roles, DB::table('v2_user_roles')->get()->toJson());
        $this->assertSame($lines, DB::table('journal_entry_lines')->get()->toJson());
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_accounts_only_repair_refuses_wrong_role_and_bad_existing_account_without_writes(): void
    {
        $cashier = $this->actor('cashier');
        $this->assertSame(1, Artisan::call('v2:prepare', ['--admin' => (string) $cashier->id, '--accounts-only' => true, '--apply' => true]));
        $this->assertDatabaseCount('chart_of_accounts', 0);
        $admin = $this->actor();
        $this->account('4102');
        $this->assertSame(1, Artisan::call('v2:prepare', ['--admin' => (string) $admin->id, '--accounts-only' => true, '--apply' => true]));
        $this->assertDatabaseCount('chart_of_accounts', 1);
        $this->assertDatabaseHas('chart_of_accounts', ['account_code' => '4102', 'account_type' => 'expense']);
    }

    public function test_missing_ongkir_account_rolls_back_checkout_and_repair_allows_one_balanced_sale(): void
    {
        $admin = $this->actor();
        $options = ['--admin' => (string) $admin->id, '--accounts-only' => true, '--apply' => true];
        $this->assertSame(0, Artisan::call('v2:prepare', $options));
        DB::table('chart_of_accounts')->where('account_code', '4102')->delete();
        $product = DB::table('products')->insertGetId([
            'sku' => 'SYN-COA-ONGKIR', 'name' => 'Material Sintetis', 'unit' => 'sak', 'category' => 'Umum',
            'stock_quantity' => '5', 'cost_price' => '10', 'selling_price' => '20', 'min_stock' => 0,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(Operations::class)->openShift($admin->id, '100', 'admin');
        $payload = [
            'items' => [['product_id' => $product, 'quantity' => '1']], 'delivery_fee' => '5',
            'payment_method' => 'cash', 'paid_amount' => '25', 'fulfillment' => 'pickup', 'idempotency_key' => 'synthetic-coa-ongkir',
        ];
        $this->actingAs($admin);

        $this->postJson(route('v2.sales.store'), $payload)->assertUnprocessable()->assertSee('Akun 4102 belum ada');
        $this->assertDatabaseCount('v2_sales', 0);
        $this->assertDatabaseCount('v2_invoices', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseHas('products', ['id' => $product, 'stock_quantity' => '5']);
        $this->assertSame(0, Artisan::call('v2:prepare', $options));
        $this->postJson(route('v2.sales.store'), $payload)->assertOk();
        $this->postJson(route('v2.sales.store'), $payload)->assertOk();
        $this->assertDatabaseCount('v2_sales', 1);
        $this->assertDatabaseHas('products', ['id' => $product, 'stock_quantity' => '4']);
        $this->assertDatabaseHas('journal_entry_lines', ['account_code' => '4102', 'credit' => '5.00']);
        $debit = DB::table('journal_entry_lines')->sum('debit');
        $credit = DB::table('journal_entry_lines')->sum('credit');
        $this->assertSame((string) $debit, (string) $credit);
    }
}
