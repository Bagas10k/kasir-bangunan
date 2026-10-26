<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CoaManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Synthetic Cashier User',
            'email' => 'cashier.test@example.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_guest_is_redirected_to_login_for_coa_routes(): void
    {
        $this->get('/coa')->assertRedirect('/login');
        $this->post('/coa', [])->assertRedirect('/login');
        $this->put('/coa/1', [])->assertRedirect('/login');
        $this->delete('/coa/1')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_coa_and_auto_seeds_canonical_accounts(): void
    {
        $this->assertEquals(0, DB::table('chart_of_accounts')->count());

        $response = $this->actingAs($this->user)->get(route('coa.index'));
        $response->assertStatus(200);
        $response->assertSee('Bagan Akun (Chart of Accounts)');
        $response->assertSee('1101');
        $response->assertSee('Kas Toko (Tunai)');
        $response->assertSee('4101');
        $response->assertSee('Pendapatan Penjualan Material');

        // Verify that 17 default accounts were seeded
        $this->assertEquals(17, DB::table('chart_of_accounts')->count());
    }

    public function test_authenticated_user_can_store_new_coa_account(): void
    {
        $response = $this->actingAs($this->user)->post(route('coa.store'), [
            'account_code' => '1104',
            'account_name' => 'Kas Kecil Cabang Proyek',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
        ]);

        $response->assertRedirect(route('coa.index'));
        $response->assertSessionHas('success');

        $account = DB::table('chart_of_accounts')->where('account_code', '1104')->first();
        $this->assertNotNull($account);
        $this->assertEquals('Kas Kecil Cabang Proyek', $account->account_name);
        $this->assertEquals('asset', $account->account_type);
        $this->assertEquals('debit', $account->normal_balance);
    }

    public function test_coa_store_rejects_normal_balance_that_conflicts_with_account_type(): void
    {
        foreach ([
            ['1109', 'asset', 'credit'],
            ['2109', 'liability', 'debit'],
            ['3109', 'equity', 'debit'],
            ['4109', 'revenue', 'debit'],
            ['6109', 'expense', 'credit'],
        ] as [$code, $type, $normal]) {
            $this->actingAs($this->user)->post(route('coa.store'), [
                'account_code' => $code,
                'account_name' => 'Akun Sintetis',
                'account_type' => $type,
                'normal_balance' => $normal,
            ])->assertSessionHasErrors('normal_balance');

            $this->assertDatabaseMissing('chart_of_accounts', ['account_code' => $code]);
        }
    }

    public function test_coa_store_validation_rejects_duplicate_code_and_invalid_types(): void
    {
        DB::table('chart_of_accounts')->insert([
            'account_code' => '9999',
            'account_name' => 'Akun Uji',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Duplicate code
        $dupResponse = $this->actingAs($this->user)->post(route('coa.store'), [
            'account_code' => '9999',
            'account_name' => 'Duplikat',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
        ]);
        $dupResponse->assertSessionHasErrors(['account_code']);

        // Invalid account type
        $invalidType = $this->actingAs($this->user)->post(route('coa.store'), [
            'account_code' => '8888',
            'account_name' => 'Tipe Ngawur',
            'account_type' => 'invalid_type',
            'normal_balance' => 'debit',
        ]);
        $invalidType->assertSessionHasErrors(['account_type']);
    }

    public function test_coa_rejects_account_code_with_conflicting_financial_category(): void
    {
        foreach ([['7198', 'expense', 'debit'], ['1198', 'expense', 'debit'], ['6198', 'revenue', 'credit']] as [$code, $type, $normal]) {
            $this->actingAs($this->user)->post(route('coa.store'), [
                'account_code' => $code, 'account_name' => 'Akun Sintetis',
                'account_type' => $type, 'normal_balance' => $normal,
            ])->assertSessionHasErrors('account_code');
            $this->assertDatabaseMissing('chart_of_accounts', ['account_code' => $code]);
        }
    }

    public function test_authenticated_user_can_update_coa_account(): void
    {
        $id = DB::table('chart_of_accounts')->insertGetId([
            'account_code' => '6198',
            'account_name' => 'Beban Lama',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->put(route('coa.update', $id), [
            'account_code' => '6198',
            'account_name' => 'Beban Baru Diperbarui',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
        ]);

        $response->assertRedirect(route('coa.index'));
        $response->assertSessionHas('success');

        $updated = DB::table('chart_of_accounts')->where('id', $id)->first();
        $this->assertEquals('Beban Baru Diperbarui', $updated->account_name);
    }

    public function test_used_account_cannot_change_code_or_type_and_historical_journal_stays_immutable(): void
    {
        $this->startBooks();
        $id = DB::table('chart_of_accounts')->insertGetId([
            'account_code' => '6198', 'account_name' => 'Beban Sintetis',
            'account_type' => 'expense', 'normal_balance' => 'debit',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $journalId = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'synthetic', 'reference_id' => 1,
            'description' => 'Transaksi sintetis terisolasi', 'transaction_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            ['journal_entry_id' => $journalId, 'account_code' => '6198', 'account_name' => 'Beban Sintetis', 'debit' => '10.00', 'credit' => '0.00', 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $journalId, 'account_code' => '1101', 'account_name' => 'Kas Toko', 'debit' => '0.00', 'credit' => '10.00', 'created_at' => now(), 'updated_at' => now()],
        ]);
        foreach ([['7198', 'revenue', 'credit'], ['6197', 'expense', 'debit']] as [$code, $type, $normal]) {
            $this->actingAs($this->user)->put(route('coa.update', $id), [
                'account_code' => $code, 'account_name' => 'Beban Sintetis',
                'account_type' => $type, 'normal_balance' => $normal,
            ])->assertRedirect(route('coa.index'))->assertSessionHas('error');
            $this->assertDatabaseHas('chart_of_accounts', ['id' => $id, 'account_code' => '6198', 'account_type' => 'expense']);
            $this->assertDatabaseHas('journal_entry_lines', ['journal_entry_id' => $journalId, 'account_code' => '6198', 'debit' => '10.00']);
        }
    }

    public function test_coa_destroy_rejects_when_account_has_journal_transactions(): void
    {
        $accId = DB::table('chart_of_accounts')->insertGetId([
            'account_code' => '1101',
            'account_name' => 'Kas Toko',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jId = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'sale',
            'reference_id' => 1,
            'description' => 'Test Transaction',
            'transaction_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_entry_lines')->insert([
            'journal_entry_id' => $jId,
            'account_code' => '1101',
            'account_name' => 'Kas Toko',
            'debit' => '50000.00',
            'credit' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Try deleting locked account
        $response = $this->actingAs($this->user)->delete(route('coa.destroy', $accId));
        $response->assertRedirect(route('coa.index'));
        $response->assertSessionHas('error');

        // Ensure account is NOT deleted
        $this->assertDatabaseHas('chart_of_accounts', ['id' => $accId]);
    }

    public function test_coa_destroy_succeeds_when_account_has_no_journal_transactions(): void
    {
        $accId = DB::table('chart_of_accounts')->insertGetId([
            'account_code' => '8888',
            'account_name' => 'Beban Cadangan Bebas',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->delete(route('coa.destroy', $accId));
        $response->assertRedirect(route('coa.index'));
        $response->assertSessionHas('success');

        // Ensure account is deleted
        $this->assertDatabaseMissing('chart_of_accounts', ['id' => $accId]);
    }

    public function test_system_account_code_cannot_be_modified(): void
    {
        $acc = DB::table('chart_of_accounts')->where('account_code', '1101')->first();
        if (!$acc) {
            $this->actingAs($this->user)->get(route('coa.index'));
            $acc = DB::table('chart_of_accounts')->where('account_code', '1101')->first();
        }

        // Attempt to change code of core account 1101 to 9991
        $response = $this->actingAs($this->user)->put(route('coa.update', $acc->id), [
            'account_code' => '9991',
            'account_name' => 'Kas Toko Berubah',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
        ]);

        $response->assertRedirect(route('coa.index'));
        $response->assertSessionHas('error');

        // Code must stay 1101
        $fresh = DB::table('chart_of_accounts')->where('id', $acc->id)->first();
        $this->assertEquals('1101', $fresh->account_code);
    }

    public function test_system_account_cannot_be_deleted(): void
    {
        $acc = DB::table('chart_of_accounts')->where('account_code', '1101')->first();
        if (!$acc) {
            $this->actingAs($this->user)->get(route('coa.index'));
            $acc = DB::table('chart_of_accounts')->where('account_code', '1101')->first();
        }

        $response = $this->actingAs($this->user)->delete(route('coa.destroy', $acc->id));
        $response->assertRedirect(route('coa.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('chart_of_accounts', ['account_code' => '1101']);
    }

    public function test_dynamic_expense_booking_uses_selected_coa_account(): void
    {
        $this->startBooks();

        // 1. Create a custom COA expense account
        $this->actingAs($this->user)->post(route('coa.store'), [
            'account_code' => '6106',
            'account_name' => 'Beban Konsumsi & Lembur Kasir',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
        ])->assertSessionHas('success');

        // 2. Submit expense using that COA account code
        $expenseResponse = $this->actingAs($this->user)->post(route('expenses.store'), [
            'category' => '6106',
            'amount' => '75000.00',
            'description' => 'Makan siang shift lembur',
            'payment_method' => 'cash',
        ]);
        $expenseResponse->assertRedirect(route('expenses.index'));
        $expenseResponse->assertSessionHas('success');

        // 3. Verify journal entry line uses exact 6106 code and name
        $expense = DB::table('expenses')->where('category', '6106')->first();
        $this->assertNotNull($expense);

        $line = DB::table('journal_entry_lines')
            ->where('journal_entry_id', $expense->journal_entry_id)
            ->where('debit', '75000.00')
            ->first();

        $this->assertNotNull($line);
        $this->assertEquals('6106', $line->account_code);
        $this->assertEquals('Beban Konsumsi & Lembur Kasir', $line->account_name);
    }

    public function test_coa_pagination_preserves_reverse_proxy_subpath(): void
    {
        config(['app.url' => 'https://example.test/kasir-bangunan']);
        \Illuminate\Support\Facades\URL::forceRootUrl('https://example.test/kasir-bangunan');

        $response = $this->actingAs($this->user)->get('https://example.test/coa');

        $response->assertStatus(200);
        $nextPageUrl = $response->viewData('accounts')->nextPageUrl();
        $this->assertNotNull($nextPageUrl);
        $this->assertStringStartsWith('https://example.test/kasir-bangunan/coa?page=2', $nextPageUrl);
        $response->assertSee('https://example.test/kasir-bangunan/coa?page=2', false);
    }
}
