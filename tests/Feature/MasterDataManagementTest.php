<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MasterDataManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Master data tests require isolated SQLite :memory:.');
        }
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    private function product(array $overrides = []): int
    {
        return DB::table('products')->insertGetId(array_merge([
            'sku' => 'SYN-MAT-001',
            'name' => 'Material Sintetis Alpha',
            'unit' => 'sak',
            'category' => 'Bahan Utama',
            'cost_price' => '12500.5500',
            'selling_price' => '15000.75',
            'stock_quantity' => '8.0000',
            'min_stock' => '10.0000',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    public function test_product_master_supports_search_filters_pagination_and_summary(): void
    {
        $this->product();
        $this->product([
            'sku' => 'SYN-MAT-002',
            'name' => 'Material Sintetis Beta',
            'unit' => 'batang',
            'category' => 'Logam',
            'stock_quantity' => '0.0000',
            'min_stock' => '2.0000',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->user())->get(route('products.index', [
            'search' => 'Alpha',
            'category' => 'Bahan Utama',
            'unit' => 'sak',
            'status' => 'active',
            'stock' => 'low',
            'per_page' => 10,
        ]));

        $response->assertOk()->assertSee('Material Sintetis Alpha')->assertDontSee('Material Sintetis Beta');
        $products = $response->viewData('products');
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $products);
        $this->assertSame(1, $products->total());
        $this->assertSame(2, $response->viewData('summary')['total']);
        $this->assertSame(1, $response->viewData('summary')['inactive']);
        $this->assertSame(0, $response->viewData('summary')['out']);
    }

    public function test_product_store_and_update_persist_metadata_and_exact_decimal_strings(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post(route('products.store'), [
            'sku' => 'SYN-EXACT-01',
            'name' => 'Material Presisi Sintetis',
            'unit' => 'kg',
            'category' => 'Agregat',
            'cost_price' => '123456789.9999',
            'selling_price' => '9876543210.99',
            'min_stock' => '2.5000',
            'is_active' => '1',
        ])->assertRedirect(route('products.index'));

        $row = DB::table('products')->where('sku', 'SYN-EXACT-01')->first();
        $this->assertNotNull($row);
        $this->assertSame('Agregat', $row->category);
        $this->assertEquals('2.5', (string) $row->min_stock);
        $this->assertSame(1, (int) $row->is_active);
        $this->assertSame('123456789.9999', (string) $row->cost_price);
        $this->assertSame('9876543210.99', (string) $row->selling_price);
    }

    public function test_inactive_product_is_hidden_and_rejected_from_new_operations(): void
    {
        $id = $this->product(['is_active' => false]);
        $user = $this->user();

        $this->actingAs($user)->get(route('pos.index'))->assertOk()->assertDontSee('Material Sintetis Alpha');
        $this->getJson(route('pos.products.search', ['q' => 'Sintetis Alpha']))
            ->assertOk()->assertExactJson(['data' => []]);
        $this->get(route('inventory.receipts'))->assertOk()->assertDontSee('Material Sintetis Alpha');
        $this->get(route('opname.index'))->assertOk()->assertDontSee('Material Sintetis Alpha');

        $this->postJson(route('pos.checkout'), [
            'items' => [['product_id' => $id, 'quantity' => '1']],
            'payment_method' => 'cash',
            'paid_amount' => '15000.75',
            'customer_type' => 'retail',
        ])->assertUnprocessable();
        $this->assertSame(0, DB::table('sales')->count());
    }

    public function test_coa_supports_search_status_usage_pagination_and_exact_balance_strings(): void
    {
        $this->actingAs($this->user())->get(route('coa.index'))->assertOk();
        $customId = DB::table('chart_of_accounts')->insertGetId([
            'account_code' => '6198',
            'account_name' => 'Beban Sintetis Arsip',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user())->get(route('coa.index', [
            'search' => 'Sintetis Arsip',
            'type' => 'expense',
            'status' => 'inactive',
            'usage' => 'unused',
            'per_page' => 10,
        ]));

        $response->assertOk()->assertSee('Beban Sintetis Arsip');
        $accounts = $response->viewData('accounts');
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $accounts);
        $this->assertSame(1, $accounts->total());
        $this->assertSame('0.00', $accounts->items()[0]->balance);
        $this->assertSame($customId, (int) $accounts->items()[0]->id);
    }

    public function test_system_account_cannot_be_archived_but_used_custom_account_can(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get(route('coa.index'))->assertOk();
        $system = DB::table('chart_of_accounts')->where('account_code', '1101')->first();

        $this->put(route('coa.update', $system->id), [
            'account_code' => '1101',
            'account_name' => $system->account_name,
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => '0',
        ])->assertSessionHas('error');
        $this->assertSame(1, (int) DB::table('chart_of_accounts')->where('id', $system->id)->value('is_active'));

        $customId = DB::table('chart_of_accounts')->insertGetId([
            'account_code' => '6198',
            'account_name' => 'Beban Sintetis Digunakan',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $journalId = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'synthetic',
            'reference_id' => 1,
            'description' => 'Jurnal sintetis terisolasi',
            'transaction_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            ['journal_entry_id' => $journalId, 'account_code' => '6198', 'account_name' => 'Beban Sintetis Digunakan', 'debit' => '10.00', 'credit' => '0.00', 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $journalId, 'account_code' => '1101', 'account_name' => 'Kas', 'debit' => '0.00', 'credit' => '10.00', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->put(route('coa.update', $customId), [
            'account_code' => '6198',
            'account_name' => 'Beban Sintetis Digunakan',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
            'is_active' => '0',
        ])->assertSessionHas('success');
        $this->assertSame(0, (int) DB::table('chart_of_accounts')->where('id', $customId)->value('is_active'));
        $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $journalId, 'account_code' => '6198']);
        $this->actingAs($user)->get(route('expenses.index'))->assertOk()->assertDontSee('Beban Sintetis Digunakan');
    }

    public function test_journal_can_be_filtered_from_account_drilldown(): void
    {
        $this->actingAs($this->user())->get(route('coa.index'))->assertOk();
        $journalId = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'synthetic',
            'reference_id' => 1,
            'description' => 'Jejak akun sintetis',
            'transaction_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            ['journal_entry_id' => $journalId, 'account_code' => '6101', 'account_name' => 'Beban Operasional', 'debit' => '10.00', 'credit' => '0.00', 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $journalId, 'account_code' => '1101', 'account_name' => 'Kas', 'debit' => '0.00', 'credit' => '10.00', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $unrelatedId = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'synthetic',
            'reference_id' => 2,
            'description' => 'Jurnal lain yang harus tersaring',
            'transaction_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            ['journal_entry_id' => $unrelatedId, 'account_code' => '1102', 'account_name' => 'Bank', 'debit' => '5.00', 'credit' => '0.00', 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $unrelatedId, 'account_code' => '3101', 'account_name' => 'Modal', 'debit' => '0.00', 'credit' => '5.00', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($this->user())->get(route('journals.index', ['account' => '6101']))
            ->assertOk()
            ->assertSee('name="account"', false)
            ->assertSee('value="6101"', false)
            ->assertSee('Jejak akun sintetis')
            ->assertSee('6101')
            ->assertDontSee('Jurnal lain yang harus tersaring');
    }
}
