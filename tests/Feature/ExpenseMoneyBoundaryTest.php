<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ExpenseService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class ExpenseMoneyBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function beforeRefreshingDatabase(): void
    {
        $driver = DB::connection()->getDriverName();
        $database = DB::connection()->getDatabaseName();

        if ($driver !== 'sqlite' || $database !== ':memory:') {
            throw new \RuntimeException(
                sprintf(
                    'ExpenseMoneyBoundaryTest requires disposable SQLite :memory: database; got driver=%s, db=%s',
                    $driver,
                    $database
                )
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (class_exists(PreventRequestForgery::class)) {
            $this->withoutMiddleware(PreventRequestForgery::class);
        }

        $this->user = User::create([
            'name' => 'Cashier Test User',
            'email' => 'cashier_' . uniqid() . '@example.com',
            'password' => Hash::make('secret123'),
        ]);
    }

    public function test_controller_validates_payment_method_and_rejects_credit(): void
    {
        $response = $this->actingAs($this->user)->postJson('/expenses', [
            'category' => 'Operational',
            'amount' => '100.50',
            'payment_method' => 'credit',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_method']);
    }

    public function test_controller_accepts_valid_payment_methods(): void
    {
        $this->startBooks('1000.00', '1000.00');

        $responseCash = $this->actingAs($this->user)->postJson('/expenses', [
            'category' => 'Operational Cash',
            'amount' => '50.25',
            'payment_method' => 'cash',
        ]);
        $responseCash->assertStatus(200);

        $responseTransfer = $this->actingAs($this->user)->postJson('/expenses', [
            'category' => 'Operational Transfer',
            'amount' => '75.50',
            'payment_method' => 'transfer',
        ]);
        $responseTransfer->assertStatus(200);

        $responseDefault = $this->actingAs($this->user)->postJson('/expenses', [
            'category' => 'Operational Default',
            'amount' => '20.00',
        ]);
        $responseDefault->assertStatus(200);
        $this->assertDatabaseHas('expenses', [
            'category' => 'Operational Default',
            'payment_method' => 'cash',
        ]);
    }

    public function test_controller_rejects_amounts_with_more_than_two_decimals(): void
    {
        $response = $this->actingAs($this->user)->postJson('/expenses', [
            'category' => 'Operational',
            'amount' => '100.555',
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    public function test_controller_rejects_zero_or_negative_amounts(): void
    {
        $responseZero = $this->actingAs($this->user)->postJson('/expenses', [
            'category' => 'Operational',
            'amount' => '0',
            'payment_method' => 'cash',
        ]);
        $responseZero->assertStatus(422);
        $responseZero->assertJsonValidationErrors(['amount']);

        $responseNeg = $this->actingAs($this->user)->postJson('/expenses', [
            'category' => 'Operational',
            'amount' => '-10.50',
            'payment_method' => 'cash',
        ]);
        $responseNeg->assertStatus(422);
        $responseNeg->assertJsonValidationErrors(['amount']);
    }

    public function test_service_rejects_more_than_two_nonzero_decimals_on_direct_call(): void
    {
        $service = app(ExpenseService::class);

        $this->expectException(InvalidArgumentException::class);
        $service->recordExpense('Ops', '123.456', 'Direct call test');
    }

    public function test_service_accepts_exact_two_decimals_or_trailing_zeros(): void
    {
        $this->startBooks('99999999999999999.99', '0.00');
        $service = app(ExpenseService::class);

        $result1 = $service->recordExpense('Ops 1', '123.45', 'Exact 2 decimals');
        $this->assertEquals('123.45', $result1['amount']);

        $result2 = $service->recordExpense('Ops 2', '100.00', 'Trailing zeros');
        $this->assertEquals('100.00', $result2['amount']);

        $result3 = $service->recordExpense('Ops 3', '50.500', 'Trailing zeros 3 decimals');
        $this->assertEquals('50.50', $result3['amount']);
    }

    public function test_service_accepts_max_decimal_19_2_amount_without_integer_cent_overflow(): void
    {
        $this->startBooks('99999999999999999.99', '0.00');
        $result = app(ExpenseService::class)->recordExpense('Synthetic Cost', '99999999999999999.99');
        $this->assertSame('99999999999999999.99', $result['amount']);
        $this->assertSame(2, DB::table('journal_entry_lines')->where('journal_entry_id', $result['journal_entry_id'])->count());
    }

    public function test_service_rejects_amount_exceeding_decimal_19_2_before_any_writes(): void
    {
        try {
            app(ExpenseService::class)->recordExpense('Synthetic Cost', '100000000000000000.00');
            $this->fail('Expected amount outside DECIMAL(19,2) to be rejected');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('range', strtolower($e->getMessage()));
        }
        $this->assertSame(0, DB::table('expenses')->count());
        $this->assertSame(0, DB::table('journal_entries')->count());
    }

    public function test_service_exact_cents_and_balanced_journals_without_float(): void
    {
        $this->startBooks('0.00', '99999999999999999.99');
        $service = app(ExpenseService::class);

        $result = $service->recordExpense('Supplies', '49.99', 'Paper and ink', 'transfer');

        $this->assertArrayHasKey('expense_id', $result);
        $this->assertArrayHasKey('expense_number', $result);
        $this->assertArrayHasKey('amount', $result);
        $this->assertArrayHasKey('journal_entry_id', $result);
        $this->assertSame('49.99', $result['amount']);

        $this->assertDatabaseHas('expenses', [
            'id' => $result['expense_id'],
            'amount' => '49.99',
            'payment_method' => 'transfer',
        ]);

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $result['journal_entry_id'],
            'account_code' => '6101',
            'debit' => '49.99',
            'credit' => '0.00',
        ]);

        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $result['journal_entry_id'],
            'account_code' => '1102',
            'debit' => '0.00',
            'credit' => '49.99',
        ]);
    }
}
