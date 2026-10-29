<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\OpeningBalanceService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AccountAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'transaction_date' => '2026-10-29', 'account_code' => '1101',
            'counter_account_code' => '3101', 'target_balance' => '1500.25',
            'description' => 'Saldo awal sintetis', 'password' => 'password',
        ], $overrides);
    }

    public function test_guest_cannot_post_adjustments(): void
    {
        $this->post(route('journals.adjustments.store'), $this->payload())->assertRedirect('/login');
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_posting_starts_bookkeeping_and_later_adjustments_only_post_the_difference(): void
    {
        $user = User::factory()->create();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->actingAs($user)->post(route('journals.adjustments.store'), $this->payload())
            ->assertRedirect(route('journals.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('opening_balances', ['status' => 'posted']);
        $this->assertDatabaseHas('journal_entry_lines', ['account_code' => '1101', 'debit' => 1500.25, 'credit' => 0]);
        $this->assertDatabaseHas('journal_entry_lines', ['account_code' => '3101', 'debit' => 0, 'credit' => 1500.25]);
        $this->actingAs($user)->post(route('journals.adjustments.store'), $this->payload(['target_balance' => '1000.10']))
            ->assertRedirect(route('journals.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('journal_entries', 2);
        $this->assertDatabaseHas('journal_entry_lines', ['account_code' => '1101', 'debit' => 0, 'credit' => 500.15]);
        $this->assertSame('1000.10', (string) app(AccountBalanceService::class)->balance('1101'));
        $this->assertSame('-1000.10', (string) app(AccountBalanceService::class)->balance('3101'));
        $this->get(route('journals.index'))->assertOk()->assertSee('Jurnal Penyesuaian Saldo');
    }

    public function test_credit_normal_account_posts_on_the_credit_side(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $this->actingAs(User::factory()->create())->post(route('journals.adjustments.store'), $this->payload([
            'account_code' => '3101', 'counter_account_code' => '1101',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('-1500.25', (string) app(AccountBalanceService::class)->balance('3101'));
        $this->assertSame('1500.25', (string) app(AccountBalanceService::class)->balance('1101'));
    }

    #[TestWith(['password', 'wrong', 'password'])]
    #[TestWith(['counter_account_code', '1101', 'counter_account_code'])]
    #[TestWith(['target_balance', '1.001', 'target_balance'])]
    #[TestWith(['transaction_date', 'invalid', 'transaction_date'])]
    #[TestWith(['description', '', 'description'])]
    #[TestWith(['account_code', 'missing', 'adjustment'])]
    #[TestWith(['target_balance', '0', 'adjustment'])]
    #[TestWith(['target_balance', '-10', 'adjustment'])]
    public function test_invalid_adjustments_do_not_write_any_journal(string $field, string $value, string $error): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $this->actingAs(User::factory()->create())->from(route('journals.index'))
            ->post(route('journals.adjustments.store'), $this->payload([$field => $value]))
            ->assertSessionHasErrors($error)->assertSessionMissing('_old_input.password');
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_entry_lines', 0);
        $this->assertDatabaseCount('opening_balances', 0);
    }

    public function test_inactive_account_cannot_be_adjusted(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        DB::table('chart_of_accounts')->where('account_code', '3101')->update(['is_active' => false]);
        $this->actingAs(User::factory()->create())->post(route('journals.adjustments.store'), $this->payload())
            ->assertSessionHasErrors('adjustment');
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_adjustment_replaces_unposted_opening_draft_without_double_counting(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        app(OpeningBalanceService::class)->saveDraft('2026-10-28', '999', '500');
        $this->actingAs(User::factory()->create())->post(route('journals.adjustments.store'), $this->payload())
            ->assertRedirect(route('journals.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('opening_balances', ['cash_amount' => 1500.25, 'bank_amount' => 0, 'status' => 'posted']);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_counter_account_cannot_make_cash_negative(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $this->actingAs(User::factory()->create())->post(route('journals.adjustments.store'), $this->payload([
            'account_code' => '1102', 'counter_account_code' => '1101',
        ]))->assertSessionHasErrors('adjustment');
        $this->assertDatabaseCount('journal_entries', 0);
    }
}
