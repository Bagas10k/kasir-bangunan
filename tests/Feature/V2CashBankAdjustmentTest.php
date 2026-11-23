<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\V2\OpeningBalances;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class V2CashBankAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private function accounts(): void
    {
        $rows = [
            ['1101', 'Kas Utama (Tunai)', 'asset', 'debit'],
            ['1102', 'Rekening Bank', 'asset', 'debit'],
            ['3101', 'Modal Pemilik / Modal Awal', 'equity', 'credit'],
            ['4101', 'Pendapatan Penjualan Material', 'revenue', 'credit'],
            ['6101', 'Beban Operasional Toko', 'expense', 'debit'],
        ];

        foreach ($rows as [$code, $name, $type, $normal]) {
            DB::table('chart_of_accounts')->insert([
                'account_code' => $code,
                'account_name' => $name,
                'account_type' => $type,
                'normal_balance' => $normal,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert([
            'user_id' => $user->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    /**
     * Jurnal sintetis penanda pembukuan sudah dimulai: Kas 500, Bank 300, Modal 800.
     */
    private function initialized(): void
    {
        $journal = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'synthetic_opening',
            'reference_id' => null,
            'description' => 'Pembukuan sintetis sudah dimulai',
            'transaction_date' => '2026-11-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            ['journal_entry_id' => $journal, 'account_code' => '1101', 'account_name' => 'Kas Utama (Tunai)', 'debit' => '500.00', 'credit' => '0.00', 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $journal, 'account_code' => '1102', 'account_name' => 'Rekening Bank', 'debit' => '300.00', 'credit' => '0.00', 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $journal, 'account_code' => '3101', 'account_name' => 'Modal Pemilik / Modal Awal', 'debit' => '0.00', 'credit' => '800.00', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'transaction_date' => '2026-11-21',
            'account_code' => '1101',
            'counter_account_code' => '3101',
            'target_balance' => '1000.00',
            'description' => 'Koreksi hitung kas pagi',
            'password' => 'password',
        ], $overrides);
    }

    public function test_guest_cashier_and_unassigned_users_cannot_adjust_cash_or_bank(): void
    {
        $this->accounts();
        $this->initialized();
        $count = DB::table('journal_entries')->count();

        $this->postJson(route('v2.journals.adjustments.store'), $this->payload())->assertUnauthorized();

        foreach ([$this->actor('cashier'), User::factory()->create()] as $user) {
            $this->actingAs($user)->postJson(route('v2.journals.adjustments.store'), $this->payload())->assertForbidden();
        }

        $this->assertSame($count, DB::table('journal_entries')->count());
    }

    public function test_admin_reaches_the_target_cash_balance_with_a_balanced_journal_and_untouched_opening_record(): void
    {
        $this->accounts();
        $this->initialized();
        $admin = $this->actor('admin');

        $this->actingAs($admin)->postJson(route('v2.journals.adjustments.store'), $this->payload())
            ->assertOk()
            ->assertJsonPath('status', 'posted')
            ->assertJsonPath('account_code', '1101')
            ->assertJsonPath('target_balance', '1000.00')
            ->assertJsonPath('difference', '500.00');

        $this->assertSame('1000.00', (string) app(AccountBalanceService::class)->balance('1101'));
        $this->assertSame('-1300.00', (string) app(AccountBalanceService::class)->balance('3101'));
        $this->assertDatabaseHas('journal_entry_lines', ['account_code' => '1101', 'debit' => '500.00', 'credit' => '0.00']);
        $this->assertDatabaseHas('journal_entry_lines', ['account_code' => '3101', 'debit' => '0.00', 'credit' => '500.00']);
        $this->assertDatabaseCount('journal_entries', 2);
        $this->assertDatabaseCount('opening_balances', 0);
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'adjustment',
            'description' => 'Penyesuaian saldo 1101 oleh pengguna #'.$admin->id.': Koreksi hitung kas pagi',
        ]);
        $this->assertSame((string) DB::table('journal_entry_lines')->sum('debit'), (string) DB::table('journal_entry_lines')->sum('credit'));
    }

    public function test_reducing_cash_can_transfer_the_difference_to_bank(): void
    {
        $this->accounts();
        $this->initialized();
        $admin = $this->actor('admin');

        $this->actingAs($admin)->postJson(route('v2.journals.adjustments.store'), $this->payload([
            'target_balance' => '450.00', 'counter_account_code' => '1102', 'description' => 'Setor tunai ke bank',
        ]))->assertOk()->assertJsonPath('difference', '-50.00');

        $this->assertSame('450.00', (string) app(AccountBalanceService::class)->balance('1101'));
        $this->assertSame('350.00', (string) app(AccountBalanceService::class)->balance('1102'));
        $this->assertDatabaseHas('journal_entry_lines', ['account_code' => '1102', 'debit' => '50.00', 'credit' => '0.00']);
    }

    public function test_adjustment_cannot_make_cash_or_bank_negative(): void
    {
        $this->accounts();
        $this->initialized();
        $admin = $this->actor('admin');

        $this->actingAs($admin)->postJson(route('v2.journals.adjustments.store'), $this->payload([
            'account_code' => '1102', 'target_balance' => '801.00', 'counter_account_code' => '1101',
        ]))->assertUnprocessable()->assertJsonValidationErrors('adjustment');

        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertSame('500.00', (string) app(AccountBalanceService::class)->balance('1101'));
    }

    public function test_only_cash_and_bank_can_be_adjusted_and_the_counter_must_be_a_distinct_active_account(): void
    {
        $this->accounts();
        $this->initialized();
        $admin = $this->actor('admin');

        $this->actingAs($admin)->postJson(route('v2.journals.adjustments.store'), $this->payload(['account_code' => '4101']))
            ->assertUnprocessable()->assertJsonValidationErrors('account_code');
        $this->actingAs($admin)->postJson(route('v2.journals.adjustments.store'), $this->payload(['counter_account_code' => '1101']))
            ->assertUnprocessable()->assertJsonValidationErrors('counter_account_code');
        DB::table('chart_of_accounts')->where('account_code', '3101')->update(['is_active' => false]);
        $this->actingAs($admin)->postJson(route('v2.journals.adjustments.store'), $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('counter_account_code');
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_wrong_password_and_zero_difference_are_rejected_without_writes(): void
    {
        $this->accounts();
        $this->initialized();
        $admin = $this->actor('admin');

        $this->actingAs($admin)->postJson(route('v2.journals.adjustments.store'), $this->payload(['password' => 'salah']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->actingAs($admin)->postJson(route('v2.journals.adjustments.store'), $this->payload(['target_balance' => '500.00']))
            ->assertUnprocessable()->assertJsonValidationErrors('target_balance');
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_uninitialized_books_are_rejected_without_writes(): void
    {
        $this->accounts();
        $admin = $this->actor('admin');

        $this->actingAs($admin)->postJson(route('v2.journals.adjustments.store'), $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('adjustment');
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_entry_lines', 0);
    }

    public function test_invalid_fields_are_reported_and_password_is_never_echoed(): void
    {
        $this->accounts();
        $this->initialized();
        $admin = $this->actor('admin');

        $this->actingAs($admin)->postJson(route('v2.journals.adjustments.store'), $this->payload([
            'transaction_date' => '21-11-2026', 'description' => '', 'target_balance' => '1.001', 'password' => 'rahasia-salah',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['transaction_date', 'description', 'target_balance'])
            ->assertJsonMissingPath('password');

        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_posting_survives_when_the_log_writer_fails(): void
    {
        $this->accounts();
        $this->initialized();
        $admin = $this->actor('admin');

        Log::swap(new class
        {
            public function info($message = null, array $context = []): void
            {
                throw new \RuntimeException('media log tidak dapat ditulis');
            }
        });

        $this->actingAs($admin)->postJson(route('v2.journals.adjustments.store'), $this->payload())
            ->assertOk()
            ->assertJsonPath('status', 'posted');

        $this->assertSame('1000.00', (string) app(AccountBalanceService::class)->balance('1101'));
        $this->assertDatabaseHas('journal_entries', ['reference_type' => 'adjustment']);
        $this->assertSame((string) DB::table('journal_entry_lines')->sum('debit'), (string) DB::table('journal_entry_lines')->sum('credit'));
    }

    public function test_journal_page_uses_the_v2_write_path_and_limits_choices_to_cash_and_bank(): void
    {
        config(['v2.enabled' => true]);
        $this->accounts();
        $this->initialized();
        $admin = $this->actor('admin');

        $response = $this->actingAs($admin)->get(route('journals.index'))->assertOk();
        $response->assertSee('action="'.route('v2.journals.adjustments.store').'"', false);
        $response->assertDontSee('action="'.route('journals.adjustments.store').'"', false);
        $response->assertDontSee('action="'.route('journals.opening.save').'"', false);
        $response->assertSee('Saldo awal belum tercatat, tetapi pembukuan sudah berjalan');
        $response->assertSee('Sesuaikan saldo');
        $response->assertSee('data-journal-root', false);
    }

    public function test_empty_install_is_directed_to_the_single_opening_form(): void
    {
        config(['v2.enabled' => true]);
        $this->accounts();
        $admin = $this->actor('admin');

        $response = $this->actingAs($admin)->get(route('journals.index'))->assertOk();
        $response->assertSee('Saldo awal belum diposting');
        $response->assertSee('Kas harian');
        $response->assertDontSee('action="'.route('journals.opening.save').'"', false);
    }

    public function test_posted_v2_opening_is_shown_as_locked_and_is_not_offered_for_a_second_posting(): void
    {
        config(['v2.enabled' => true]);
        $this->accounts();
        $admin = $this->actor('admin');

        app(OpeningBalances::class)->post($admin->id, [
            'idempotency_key' => 'synthetic-v2-opening',
            'date' => '2026-11-01',
            'cash' => '250.00',
            'bank' => '125.00',
            'reference' => 'Pembukaan sintetis',
        ]);

        $response = $this->actingAs($admin)->get(route('journals.index'))->assertOk();
        $response->assertSee('Terkunci');
        $response->assertSee('Rp 250,00');
        $response->assertSee('Rp 125,00');
        $response->assertDontSee('action="'.route('journals.opening.save').'"', false);
        $response->assertDontSee('Mulai Pembukuan');
    }
}
