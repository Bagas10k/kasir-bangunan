<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\Ledger;
use App\Services\V2\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Penjaga entri dobel bisnis: pembayaran tagihan yang sudah berubah sejak form dibuka, dan nomor nota supplier
 * yang sudah pernah dicatat. Keduanya meminta konfirmasi, bukan memblokir. Data sintetis, SQLite :memory:.
 */
class V2DuplicateEntryGuardTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('V2 duplicate guard tests require isolated SQLite :memory:.');
        }
    }

    private function actor(string $role, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function books(): void
    {
        foreach ([['1101', 'Kas', 'asset', 'debit'], ['1102', 'Bank', 'asset', 'debit'], ['1103', 'Piutang', 'asset', 'debit'], ['1301', 'Persediaan', 'asset', 'debit'], ['2101', 'Utang', 'liability', 'credit'], ['3101', 'Modal', 'equity', 'credit']] as [$code, $name, $type, $normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore(['account_code' => $code, 'account_name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        Ledger::post('synthetic_opening', 1, 'Saldo awal sintetis', [
            ['account_code' => '1101', 'debit' => '1000000.00', 'credit' => '0.00'],
            ['account_code' => '3101', 'debit' => '0.00', 'credit' => '1000000.00'],
        ]);
    }

    private function receivable(): int
    {
        $customer = DB::table('v2_parties')->insertGetId(['kind' => 'customer', 'code' => 'SYN-PLG-1', 'name' => 'Pelanggan Sintetis', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('v2_invoices')->insertGetId(['direction' => 'receivable', 'party_id' => $customer, 'number' => 'AR-SYN-1', 'amount' => '100000.00', 'paid' => '0.00', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_payment_on_an_invoice_changed_since_the_form_opened_needs_confirmation(): void
    {
        $admin = $this->actor('admin', 'Admin Sintetis');
        $cashier = $this->actor('cashier', 'Kasir Sintetis');
        $this->books();
        $invoice = $this->receivable();
        $ops = app(Operations::class);
        $ops->openShift($admin->id, '100000.00', 'admin');

        // Kasir mencatat cicilan; admin masih memakai form yang dibuka sebelumnya (belum ada pembayaran).
        $ops->collect($cashier->id, $invoice, '50000', 'cash', 'syn-dup-cashier-1');

        $this->actingAs($admin)->postJson(route('v2.payments.store', ['id' => $invoice]), ['amount' => '50000', 'method' => 'cash', 'idempotency_key' => 'syn-dup-admin-1', 'seen_payment_id' => '0'])
            ->assertStatus(422)
            ->assertJsonPath('errors.duplicate_payment.0', fn (string $m) => str_contains($m, 'Rp 50.000') && str_contains($m, 'Kasir Sintetis'));
        $this->assertEquals(50000, (float) DB::table('v2_invoices')->where('id', $invoice)->value('paid'));
        $this->assertSame(1, DB::table('v2_payments')->count());

        // Dikonfirmasi: tetap dicatat (memang cicilan kedua).
        $this->actingAs($admin)->postJson(route('v2.payments.store', ['id' => $invoice]), ['amount' => '50000', 'method' => 'cash', 'idempotency_key' => 'syn-dup-admin-1', 'seen_payment_id' => '0', 'confirm_duplicate' => true])
            ->assertOk();
        $this->assertSame('paid', DB::table('v2_invoices')->where('id', $invoice)->value('status'));
    }

    public function test_payment_with_an_up_to_date_form_or_a_rejected_newer_payment_needs_no_confirmation(): void
    {
        $admin = $this->actor('admin', 'Admin Sintetis');
        $cashier = $this->actor('cashier', 'Kasir Sintetis');
        $this->books();
        $invoice = $this->receivable();
        $ops = app(Operations::class);
        $ops->openShift($admin->id, '100000.00', 'admin');
        $first = $ops->collect($admin->id, $invoice, '10000', 'cash', 'syn-dup-admin-2');
        $transfer = $ops->collect($cashier->id, $invoice, '20000', 'transfer', 'syn-dup-cashier-2');
        $ops->rejectPayment($admin->id, $transfer, 'Transfer tidak ditemukan di mutasi');

        $this->actingAs($admin)->postJson(route('v2.payments.store', ['id' => $invoice]), ['amount' => '10000', 'method' => 'cash', 'idempotency_key' => 'syn-dup-admin-3', 'seen_payment_id' => (string) $first])
            ->assertOk();
        // Tanpa seen_payment_id (pemanggil lama): tidak diperiksa.
        $this->actingAs($admin)->postJson(route('v2.payments.store', ['id' => $invoice]), ['amount' => '10000', 'method' => 'cash', 'idempotency_key' => 'syn-dup-admin-4'])
            ->assertOk();
    }

    /** @return array{supplier:int, other:int, product:int} */
    private function supplyStock(): array
    {
        $supplier = DB::table('v2_parties')->insertGetId(['kind' => 'supplier', 'code' => 'SYN-SUP-1', 'name' => 'PT Pemasok Sintetis', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $other = DB::table('v2_parties')->insertGetId(['kind' => 'supplier', 'code' => 'SYN-SUP-2', 'name' => 'CV Lain Sintetis', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $product = DB::table('products')->insertGetId(['sku' => 'SYN-SMN-1', 'name' => 'Semen Sintetis', 'unit' => 'sak', 'category' => 'Umum', 'cost_price' => '50000.0000', 'selling_price' => '60000.00', 'stock_quantity' => '10.0000', 'min_stock' => '1.0000', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$supplier, $other] as $party) {
            DB::table('v2_product_suppliers')->insert(['product_id' => $product, 'supplier_id' => $party, 'is_primary' => $party === $supplier, 'created_at' => now(), 'updated_at' => now()]);
        }

        return ['supplier' => $supplier, 'other' => $other, 'product' => $product];
    }

    private function receipt(User $admin, int $supplier, int $product, ?string $nota, bool $confirm = false)
    {
        $saved = $this->actingAs($admin)->postJson(route('v2.receipts.store'), array_filter([
            'supplier_id' => $supplier, 'receipt_date' => now()->toDateString(), 'idempotency_key' => 'syn-rcv-'.++$this->seq,
            'supplier_invoice_number' => $nota, 'confirm_duplicate' => $confirm ?: null,
            'items' => [['product_id' => $product, 'quantity' => '2', 'unit_cost' => '52000']],
        ], fn ($value) => $value !== null));
        $saved->assertOk()->assertJsonPath('status', 'draft');

        return $this->postJson(route('v2.receipt-drafts.confirm', $saved->json('draft_id')), [
            'version' => $saved->json('version'), 'confirm_final' => true, 'confirm_duplicate' => $confirm,
        ]);
    }

    public function test_same_supplier_invoice_number_twice_needs_confirmation(): void
    {
        $admin = $this->actor('admin', 'Admin Sintetis');
        $this->books();
        $stock = $this->supplyStock();

        $first = $this->receipt($admin, $stock['supplier'], $stock['product'], 'NF-0042')->assertOk()->json('receipt_id');
        $this->assertSame('NF-0042', DB::table('inventory_receipts')->where('id', $first)->value('supplier_invoice_number'));

        $this->receipt($admin, $stock['supplier'], $stock['product'], ' nf-0042 ')
            ->assertStatus(422)
            ->assertJsonPath('errors.duplicate_receipt.0', fn (string $m) => str_contains($m, 'RCV-'.str_pad((string) $first, 6, '0', STR_PAD_LEFT)) && str_contains($m, 'Ya, tetap konfirmasi'));
        $this->assertSame(1, DB::table('inventory_receipts')->count());

        // Supplier lain dengan nomor sama dan penerimaan tanpa nomor: tidak diperiksa.
        $this->receipt($admin, $stock['other'], $stock['product'], 'NF-0042')->assertOk();
        $this->receipt($admin, $stock['supplier'], $stock['product'], null)->assertOk();
        // Dikonfirmasi: tetap disimpan.
        $this->receipt($admin, $stock['supplier'], $stock['product'], 'NF-0042', true)->assertOk();
        $this->assertSame(4, DB::table('inventory_receipts')->count());
    }

    public function test_voided_receipt_does_not_count_as_duplicate(): void
    {
        $admin = $this->actor('admin', 'Admin Sintetis');
        $this->books();
        $stock = $this->supplyStock();
        $first = $this->receipt($admin, $stock['supplier'], $stock['product'], 'NF-0099')->assertOk()->json('receipt_id');
        // Historical fixture only: new final receipts can no longer be voided.
        DB::table('inventory_receipts')->where('id', $first)->update(['voided_at' => now(), 'voided_by' => $admin->id, 'void_reason' => 'Batal sebelum kebijakan kunci final']);

        $this->receipt($admin, $stock['supplier'], $stock['product'], 'NF-0099')->assertOk();
    }

    public function test_forms_carry_the_guard_fields(): void
    {
        $admin = $this->actor('admin', 'Admin Sintetis');
        $this->books();
        $invoice = $this->receivable();
        $ops = app(Operations::class);
        $ops->openShift($admin->id, '100000.00', 'admin');
        $payment = $ops->collect($admin->id, $invoice, '10000', 'cash', 'syn-dup-view-1');

        $this->actingAs($admin)->get(route('v2.index'))->assertOk()
            ->assertSee('name="seen_payment_id" value="'.$payment.'"', false)
            ->assertSee('name="supplier_invoice_number"', false);

        $this->actingAs($admin)->get(route('expenses.index', ['partial' => 1]))->assertOk()
            ->assertSee('name="idempotency_key"', false)
            ->assertSee('data-expense-confirm-duplicate', false);

        config(['v2.enabled' => true]);
        $this->actingAs($admin)->get(route('journals.index', ['partial' => 1]))->assertOk()
            ->assertSee('name="idempotency_key"', false)
            ->assertSee('data-transfer-confirm-duplicate', false);
    }

    public function test_operating_expense_replays_same_idempotency_key_and_guards_duplicate_entries(): void
    {
        $admin = $this->actor('admin', 'Admin Sintetis');
        $otherAdmin = $this->actor('admin', 'Admin Kedua');
        $this->books();
        DB::table('chart_of_accounts')->insertOrIgnore([
            'account_code' => '6101', 'account_name' => 'Beban Operasional',
            'account_type' => 'expense', 'normal_balance' => 'debit',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $today = now()->toDateString();
        $payload = [
            'date' => $today,
            'account_code' => '6101',
            'source' => 'cash',
            'amount' => '75000.00',
            'description' => 'Token listrik gudang',
            'idempotency_key' => 'exp-syn-key-0001',
        ];

        // Kiriman pertama berhasil (1 pengeluaran, 1 jurnal pengeluaran).
        $first = $this->actingAs($admin)->postJson(route('v2.expenses.store'), $payload)->assertCreated();
        $expenseId = (int) $first->json('expense_id');
        $expenseNumber = (string) $first->json('expense_number');
        $this->assertSame(1, DB::table('expenses')->count());

        // Kirim ulang dengan idempotency_key + isi sama -> replay tanpa menggandakan catatan/jurnal.
        $this->actingAs($admin)->postJson(route('v2.expenses.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('expense_id', $expenseId)
            ->assertJsonPath('expense_number', $expenseNumber);
        $this->assertSame(1, DB::table('expenses')->count());

        // Kunci sama untuk isi berbeda -> 422 idempotency_key.
        $this->actingAs($admin)->postJson(route('v2.expenses.store'), array_merge($payload, ['amount' => '80000.00']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('idempotency_key');

        // Aktor lain memakai kunci idempotency yang sama -> 403.
        $this->actingAs($otherAdmin)->postJson(route('v2.expenses.store'), $payload)->assertForbidden();

        // Kunci baru tetapi isi identik (tanggal, akun, sumber, nominal, keterangan sama) -> tahan 422 duplicate_expense.
        $secondPayload = array_merge($payload, ['idempotency_key' => 'exp-syn-key-0002']);
        $this->actingAs($admin)->postJson(route('v2.expenses.store'), $secondPayload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.duplicate_expense.0', fn (string $m) => str_contains($m, $expenseNumber) && str_contains($m, 'Rp 75.000'));
        $this->assertSame(1, DB::table('expenses')->count());

        // Keterangan berbeda tidak dianggap dobel.
        $this->actingAs($admin)->postJson(route('v2.expenses.store'), array_merge($secondPayload, [
            'description' => 'Token listrik kantor',
            'idempotency_key' => 'exp-syn-key-0003',
        ]))->assertCreated();
        $this->assertSame(2, DB::table('expenses')->count());

        // Konfirmasi eksplisit (confirm_duplicate=true) dengan kunci kedua -> tetap tersimpan.
        $this->actingAs($admin)->postJson(route('v2.expenses.store'), array_merge($secondPayload, [
            'confirm_duplicate' => true,
        ]))->assertCreated();
        $this->assertSame(3, DB::table('expenses')->count());
    }

    public function test_cash_bank_transfer_replays_same_idempotency_key_and_guards_duplicate_entries(): void
    {
        $admin = $this->actor('admin', 'Admin Sintetis');
        $otherAdmin = $this->actor('admin', 'Admin Kedua');
        $this->books();
        DB::table('chart_of_accounts')->where('account_code', '1102')->update(['is_bank_account' => true]);

        $today = now()->toDateString();
        $payload = [
            'transaction_date' => $today,
            'from_account_code' => '1101',
            'to_account_code' => '1102',
            'amount' => '200000.00',
            'description' => 'Setor kas sore ke Bank',
            'password' => 'password',
            'idempotency_key' => 'trf-syn-key-0001',
        ];

        $first = $this->actingAs($admin)->postJson(route('v2.journals.transfers.store'), $payload)->assertCreated();
        $journalId = (int) $first->json('journal_entry_id');
        $this->assertSame(1, DB::table('journal_entries')->where('reference_type', 'cash_bank_transfer')->count());

        // Kirim ulang dengan kunci & isi sama -> replay exact journal ID, tanpa jurnal kedua.
        $this->actingAs($admin)->postJson(route('v2.journals.transfers.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('journal_entry_id', $journalId);
        $this->assertSame(1, DB::table('journal_entries')->where('reference_type', 'cash_bank_transfer')->count());

        // Kunci sama untuk nominal berbeda -> 422 idempotency_key.
        $this->actingAs($admin)->postJson(route('v2.journals.transfers.store'), array_merge($payload, ['amount' => '250000.00']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('idempotency_key');

        // Aktor lain dengan kunci sama -> 403.
        $this->actingAs($otherAdmin)->postJson(route('v2.journals.transfers.store'), $payload)->assertForbidden();

        // Kunci baru tetapi pindah dana identik (sumber, tujuan, nominal, tanggal) -> 422 duplicate_transfer.
        $secondPayload = array_merge($payload, ['idempotency_key' => 'trf-syn-key-0002']);
        $this->actingAs($admin)->postJson(route('v2.journals.transfers.store'), $secondPayload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.duplicate_transfer.0', fn (string $m) => str_contains($m, 'Jurnal #'.$journalId) && str_contains($m, 'Rp 200.000'));
        $this->assertSame(1, DB::table('journal_entries')->where('reference_type', 'cash_bank_transfer')->count());

        // Dikonfirmasi (confirm_duplicate=true) -> tersimpan jurnal kedua.
        $this->actingAs($admin)->postJson(route('v2.journals.transfers.store'), array_merge($secondPayload, [
            'confirm_duplicate' => true,
        ]))->assertCreated();
        $this->assertSame(2, DB::table('journal_entries')->where('reference_type', 'cash_bank_transfer')->count());
    }
}
