<?php

namespace Tests\Feature;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2OperatingExpensesTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Pengeluaran V2 hanya diuji pada SQLite :memory:.');
        }
    }

    private function actor(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function book(): void
    {
        foreach ([
            ['1101', 'Kas Tunai', 'asset', 'debit'],
            ['1102', 'Bank', 'asset', 'debit'],
            ['3101', 'Modal', 'equity', 'credit'],
            ['6101', 'Beban Operasional', 'expense', 'debit'],
            ['5101', 'HPP', 'expense', 'debit'],
        ] as [$code, $name, $type, $normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore([
                'account_code' => $code, 'account_name' => $name, 'account_type' => $type,
                'normal_balance' => $normal, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('opening_balances')->insert([
            'singleton_key' => 1, 'transaction_date' => now()->toDateString(),
            'cash_amount' => '1000000.00', 'bank_amount' => '1000000.00',
            'status' => 'posted', 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $journal = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'synthetic_opening', 'reference_id' => null,
            'description' => 'Saldo awal sintetis', 'transaction_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([['1101', '1000000.00', '0.00'], ['1102', '1000000.00', '0.00'], ['3101', '0.00', '2000000.00']] as [$code, $debit, $credit]) {
            DB::table('journal_entry_lines')->insert([
                'journal_entry_id' => $journal, 'account_code' => $code, 'account_name' => $code,
                'debit' => $debit, 'credit' => $credit, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_admin_can_post_an_operating_expense_and_it_creates_a_balanced_journal(): void
    {
        $this->book();
        $admin = $this->actor();

        $response = $this->actingAs($admin)->post(route('v2.expenses.store'), [
            'date' => now()->toDateString(),
            'account_code' => '6101',
            'source' => 'cash',
            'amount' => '50000',
            'description' => 'Token listrik toko',
        ]);

        $response->assertRedirect(route('expenses.index'));
        $expense = DB::table('expenses')->where('description', 'Token listrik toko')->first();
        $this->assertNotNull($expense);
        $this->assertStringStartsWith('EXP-', (string) $expense->expense_number);
        $this->assertNotNull($expense->journal_entry_id);
        $this->assertSame('6101', $expense->category);
        $this->assertSame('cash', $expense->payment_method);

        $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $expense->journal_entry_id)->orderBy('account_code')->get();
        $this->assertCount(2, $lines);
        $this->assertSame('1101', $lines[0]->account_code);
        $this->assertSame(0, BigDecimal::of((string) $lines[0]->credit)->compareTo(BigDecimal::of('50000.00')));
        $this->assertSame('6101', $lines[1]->account_code);
        $this->assertSame(0, BigDecimal::of((string) $lines[1]->debit)->compareTo(BigDecimal::of('50000.00')));
    }

    public function test_bank_source_credits_bank_and_rejects_insufficient_balance(): void
    {
        $this->book();
        $admin = $this->actor();

        $this->actingAs($admin)->postJson(route('v2.expenses.store'), [
            'date' => now()->toDateString(), 'account_code' => '6101', 'source' => 'bank', 'amount' => '125000',
        ])->assertCreated();

        $expense = DB::table('expenses')->where('payment_method', 'transfer')->first();
        $this->assertNotNull($expense);
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $expense->journal_entry_id, 'account_code' => '1102', 'credit' => '125000.00',
        ]);

        $this->actingAs($admin)->postJson(route('v2.expenses.store'), [
            'date' => now()->toDateString(), 'account_code' => '6101', 'source' => 'cash', 'amount' => '2000000',
        ])->assertStatus(422)->assertJsonValidationErrors('amount');
    }

    public function test_hpp_and_future_dates_are_rejected(): void
    {
        $this->book();
        $admin = $this->actor();

        $this->actingAs($admin)->postJson(route('v2.expenses.store'), [
            'date' => now()->toDateString(), 'account_code' => '5101', 'source' => 'cash', 'amount' => '1000',
        ])->assertStatus(422)->assertJsonValidationErrors('account_code');

        $this->actingAs($admin)->postJson(route('v2.expenses.store'), [
            'date' => now()->addDay()->toDateString(), 'account_code' => '6101', 'source' => 'cash', 'amount' => '1000',
        ])->assertStatus(422)->assertJsonValidationErrors('date');
    }

    public function test_cashier_cannot_post_an_expense(): void
    {
        $this->book();

        $this->actingAs($this->actor('cashier'))->postJson(route('v2.expenses.store'), [
            'date' => now()->toDateString(), 'account_code' => '6101', 'source' => 'cash', 'amount' => '1000',
        ])->assertForbidden();
    }

    public function test_admin_can_post_expense_when_bookkeeping_started_via_v2_without_legacy_opening_balance_row(): void
    {
        foreach ([
            ['1101', 'Kas Tunai', 'asset', 'debit'],
            ['1102', 'Bank', 'asset', 'debit'],
            ['3101', 'Modal', 'equity', 'credit'],
            ['6102', 'Beban Listrik & Air', 'expense', 'debit'],
        ] as [$code, $name, $type, $normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore([
                'account_code' => $code, 'account_name' => $name, 'account_type' => $type,
                'normal_balance' => $normal, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $journal = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'v2_opening', 'reference_id' => 1,
            'description' => 'Saldo awal V2 sintetis', 'transaction_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([['1102', '500000.00', '0.00'], ['3101', '0.00', '500000.00']] as [$code, $debit, $credit]) {
            DB::table('journal_entry_lines')->insert([
                'journal_entry_id' => $journal, 'account_code' => $code, 'account_name' => $code,
                'debit' => $debit, 'credit' => $credit, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $admin = $this->actor();

        DB::table('v2_opening_batches')->insert([
            'singleton_key' => 1, 'date' => now()->toDateString(),
            'cash_amount' => '0.00', 'bank_amount' => '500000.00', 'inventory_amount' => '0.00',
            'ar_amount' => '0.00', 'ap_amount' => '0.00', 'equity_amount' => '500000.00',
            'equity_direction' => 'credit', 'journal_entry_id' => $journal,
            'idempotency_key' => 'v2-opening-syn-1', 'request_hash' => str_repeat('a', 64),
            'created_by' => $admin->id, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertDatabaseCount('opening_balances', 0);

        $this->actingAs($admin)->postJson(route('v2.expenses.store'), [
            'date' => now()->toDateString(), 'account_code' => '6102', 'source' => 'bank', 'amount' => '100000',
        ])->assertCreated()->assertJsonPath('amount', '100000.00');
    }

    public function test_uninitialized_books_without_any_opening_or_journal_still_rejects_expense(): void
    {
        foreach ([
            ['1101', 'Kas Tunai', 'asset', 'debit'],
            ['6101', 'Beban Operasional', 'expense', 'debit'],
        ] as [$code, $name, $type, $normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore([
                'account_code' => $code, 'account_name' => $name, 'account_type' => $type,
                'normal_balance' => $normal, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($this->actor())->postJson(route('v2.expenses.store'), [
            'date' => now()->toDateString(), 'account_code' => '6101', 'source' => 'cash', 'amount' => '1000',
        ])->assertStatus(422)->assertJsonValidationErrors('amount');
    }
}
