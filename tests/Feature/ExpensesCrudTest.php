<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExpensesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('RefreshDatabase is only permitted against isolated SQLite :memory:.');
        }
    }

    private function user(): User
    {
        return User::create([
            'name' => 'Expense Tester',
            'email' => 'expense_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    private function record(string $category = 'Operasional Toko', string $amount = '50000.00', string $method = 'cash'): int
    {
        $this->startBooks();
        return app(ExpenseService::class)->recordExpense($category, $amount, 'Test', $method)['expense_id'];
    }

    public function test_guest_is_blocked_from_update_and_delete(): void
    {
        $id = $this->record();

        $this->put(route('expenses.update', $id), ['category' => 'Listrik & Air', 'amount' => '1.00'])
            ->assertStatus(302);
        $this->delete(route('expenses.destroy', $id))
            ->assertStatus(302);
    }

    public function test_update_posted_expense_is_rejected_and_preserves_journal_lines(): void
    {
        $id = $this->record('Operasional Toko', '50000.00', 'cash');

        $response = $this->actingAs($this->user())->put(route('expenses.update', $id), [
            'category' => 'Listrik & Air',
            'amount' => '75000.00',
            'payment_method' => 'transfer',
            'description' => 'Updated',
        ]);

        $response->assertSessionHasErrors('error');
        $this->assertEquals('50000.00', DB::table('expenses')->where('id', $id)->value('amount'));
        $this->assertEquals('Operasional Toko', DB::table('expenses')->where('id', $id)->value('category'));

        $jid = DB::table('expenses')->where('id', $id)->value('journal_entry_id');
        $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $jid)->orderBy('id')->get();
        $this->assertCount(2, $lines);
        $this->assertEquals('6101', $lines[0]->account_code);
        $this->assertEquals('50000.00', $lines[0]->debit);
        $this->assertEquals('1101', $lines[1]->account_code);
        $this->assertEquals('50000.00', $lines[1]->credit);
    }

    public function test_delete_posted_expense_is_rejected_and_preserves_journal(): void
    {
        $id = $this->record('Operasional Toko', '50000.00', 'cash');
        $jid = DB::table('expenses')->where('id', $id)->value('journal_entry_id');
        $this->assertNotNull($jid);

        $response = $this->actingAs($this->user())->delete(route('expenses.destroy', $id));
        $response->assertSessionHasErrors('error');

        $this->assertTrue(DB::table('expenses')->where('id', $id)->exists());
        $this->assertTrue(DB::table('journal_entries')->where('id', $jid)->exists());
        $this->assertEquals(2, DB::table('journal_entry_lines')->where('journal_entry_id', $jid)->count());
    }

    public function test_update_rejects_nonpositive_amount(): void
    {
        $id = $this->record();

        $this->actingAs($this->user())
            ->put(route('expenses.update', $id), ['category' => 'Operasional Toko', 'amount' => '0'])
            ->assertSessionHasErrors('amount');

        $this->assertEquals('50000.00', DB::table('expenses')->where('id', $id)->value('amount'));
    }

    public function test_asset_category_cannot_create_or_reclassify_expense(): void
    {
        $id = $this->record();
        DB::table('chart_of_accounts')->insert([
            'account_code' => '1101', 'account_name' => 'Kas Toko',
            'account_type' => 'asset', 'normal_balance' => 'debit',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $expenseCount = DB::table('expenses')->count();
        $journalCount = DB::table('journal_entries')->count();
        $lineCount = DB::table('journal_entry_lines')->count();

        $this->actingAs($this->user())->post(route('expenses.store'), [
            'category' => '1101', 'amount' => '750000.00', 'payment_method' => 'cash',
        ])->assertSessionHasErrors();
        $this->actingAs($this->user())->put(route('expenses.update', $id), [
            'category' => '1101', 'amount' => '750000.00', 'payment_method' => 'cash',
        ])->assertSessionHasErrors();
        try {
            app(ExpenseService::class)->recordExpense('1101', '1.00');
            $this->fail('Asset account was accepted as an expense.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('akun beban', strtolower($e->getMessage()));
        }
        try {
            app(ExpenseService::class)->updateExpense($id, '1101', '1.00');
            $this->fail('Posted expense was reclassified.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('terkunci', strtolower($e->getMessage()));
        }

        $this->assertSame($expenseCount, DB::table('expenses')->count());
        $this->assertSame($journalCount, DB::table('journal_entries')->count());
        $this->assertSame($lineCount, DB::table('journal_entry_lines')->count());
        $this->assertSame('Operasional Toko', DB::table('expenses')->where('id', $id)->value('category'));
    }

    public function test_expenses_index_renders_action_column(): void
    {
        $this->record();

        $response = $this->actingAs($this->user())->get(route('expenses.index'));

        $response->assertOk();
        $response->assertSee('Aksi', false);
        $response->assertSee('Terkunci', false);
        $response->assertDontSee('>Ubah<', false);
        $response->assertDontSee('>Hapus<', false);
    }
}