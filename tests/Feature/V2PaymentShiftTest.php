<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class V2PaymentShiftTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('V2 tests require isolated SQLite :memory:.');
        }
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function accounts(): void
    {
        foreach ([['1101', 'Kas', 'asset', 'debit'], ['1102', 'Bank', 'asset', 'debit'], ['1103', 'Piutang', 'asset', 'debit'], ['1301', 'Persediaan', 'asset', 'debit'], ['2101', 'Utang', 'liability', 'credit'], ['3101', 'Modal', 'equity', 'credit'], ['4101', 'Penjualan', 'revenue', 'credit'], ['4102', 'Ongkir', 'revenue', 'credit'], ['5101', 'HPP', 'expense', 'debit'], ['6101', 'Biaya Perjalanan', 'expense', 'debit']] as [$code, $name, $type, $normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore(['account_code' => $code, 'account_name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function product(): int
    {
        return DB::table('products')->insertGetId(['sku' => 'SYN-S2-A-001', 'name' => 'Semen Sintetis Alpha', 'unit' => 'sak', 'category' => 'Umum', 'cost_price' => '60.0000', 'selling_price' => '100.00', 'stock_quantity' => '10.0000', 'min_stock' => '1.0000', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function party(string $kind, string $code): int
    {
        return DB::table('v2_parties')->insertGetId(['kind' => $kind, 'code' => $code, 'name' => 'Mitra Sintetis Alpha', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function ops(): Operations
    {
        return app(Operations::class);
    }

    private function creditPayload(int $product, int $customer, string $paid, ?string $deposit = null): array
    {
        $data = [
            'items' => [['product_id' => $product, 'quantity' => '1']],
            'customer_id' => $customer,
            'payment_method' => 'credit',
            'paid_amount' => $paid,
            'idempotency_key' => 'synthetic-v2-credit-'.bin2hex(random_bytes(4)),
            'fulfillment' => 'pickup',
        ];
        if ($deposit !== null) {
            $data['deposit_method'] = $deposit;
        }

        return $data;
    }

    private function expectValidation(callable $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('operations', $e->errors());

            return;
        }
        $this->fail('Validasi diharapkan gagal.');
    }

    private function assertForbidden(callable $callback): void
    {
        try {
            $callback();
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());

            return;
        }
        $this->fail('Akses ditolak diharapkan.');
    }

    public function test_credit_deposit_needs_explicit_method_and_cash_dp_posts_on_drawer(): void
    {
        $cashier = $this->actor('cashier');
        $this->accounts();
        $product = $this->product();
        $customer = $this->party('customer', 'SYN-C-A-001');
        $shift = $this->ops()->openShift($cashier->id, '100.00', 'admin');

        $this->expectValidation(fn () => $this->ops()->checkout($cashier->id, $this->creditPayload($product, $customer, '20.00')));
        $this->assertDatabaseCount('v2_sales', 0);

        $result = $this->ops()->checkout($cashier->id, $this->creditPayload($product, $customer, '20.00', 'cash'));
        $payment = DB::table('v2_payments')->first();
        $this->assertSame('posted', $payment->status);
        $this->assertSame('cash', $payment->method);
        $this->assertSame($shift, (int) $payment->shift_id);
        $this->assertSame('20.00', $result['paid']);
        $this->assertSame('partial', $result['status']);
        $this->assertEquals(20, DB::table('v2_invoices')->where('id', $result['invoice_id'])->value('paid'));
        $this->assertEquals(20, DB::table('v2_shift_movements')->where('reference_type', 'v2_payment')->sum('amount'));
    }

    public function test_credit_transfer_dp_stays_pending_until_admin_approves(): void
    {
        $cashier = $this->actor('cashier');
        $admin = $this->actor('admin');
        $this->accounts();
        $product = $this->product();
        $customer = $this->party('customer', 'SYN-C-A-002');
        $this->ops()->openShift($cashier->id, '100.00', 'admin');

        $result = $this->ops()->checkout($cashier->id, $this->creditPayload($product, $customer, '20.00', 'transfer'));
        $payment = DB::table('v2_payments')->first();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('transfer', $payment->method);
        $this->assertNull($payment->shift_id);
        $this->assertSame('unpaid', $result['status']);
        $this->assertEquals(0, DB::table('v2_invoices')->where('id', $result['invoice_id'])->value('paid'));
        $this->assertEquals(0, DB::table('v2_shift_movements')->count());
        $this->assertEquals(0, DB::table('journal_entry_lines')->where('account_code', '1102')->sum('debit'));

        $this->ops()->approvePayment($admin->id, (int) $payment->id);
        $this->assertSame('posted', DB::table('v2_payments')->where('id', $payment->id)->value('status'));
        $this->assertEquals(20, DB::table('journal_entry_lines')->where('account_code', '1102')->sum('debit'));
        $this->assertEquals(20, DB::table('v2_invoices')->where('id', $result['invoice_id'])->value('paid'));
        $this->assertSame('partial', DB::table('v2_sales')->where('id', $result['sale_id'])->value('status'));
    }

    public function test_direct_credit_without_deposit_field_is_still_valid(): void
    {
        $cashier = $this->actor('cashier');
        $this->accounts();
        $product = $this->product();
        $customer = $this->party('customer', 'SYN-C-A-003');
        $this->ops()->openShift($cashier->id, '100.00', 'admin');

        $result = $this->ops()->checkout($cashier->id, $this->creditPayload($product, $customer, '0.00'));
        $this->assertSame('unpaid', $result['status']);
        $this->assertEquals(0, DB::table('v2_invoices')->where('id', $result['invoice_id'])->value('paid'));
        $this->assertDatabaseCount('v2_payments', 0);
    }

    public function test_resolve_shift_admin_only_keeps_values_and_posts_no_journal(): void
    {
        $cashier = $this->actor('cashier');
        $admin = $this->actor('admin');
        $this->accounts();
        $shift = $this->ops()->openShift($cashier->id, '100.00', 'admin');
        $this->ops()->closeShift($cashier->id, $shift, '90.00');

        $before = DB::table('v2_shifts')->where('id', $shift)->first();
        $this->assertSame('review', $before->status);
        $journals = DB::table('journal_entry_lines')->count();

        $this->assertForbidden(fn () => $this->ops()->resolveShift($cashier->id, $shift, 'Selisih kas disetujui manajer'));
        $this->expectValidation(fn () => $this->ops()->resolveShift($admin->id, $shift, 'ok'));

        $this->ops()->resolveShift($admin->id, $shift, 'Selisih kas disetujui manajer');
        $after = DB::table('v2_shifts')->where('id', $shift)->first();
        $this->assertSame('closed', $after->status);
        $this->assertSame($admin->id, (int) $after->reviewed_by);
        $this->assertSame('Selisih kas disetujui manajer', $after->review_note);
        $this->assertNotNull($after->reviewed_at);
        $this->assertSame($before->expected_cash, $after->expected_cash);
        $this->assertSame($before->counted_cash, $after->counted_cash);
        $this->assertSame($before->difference, $after->difference);
        $this->assertSame($journals, DB::table('journal_entry_lines')->count());
        $this->assertSame(0, DB::table('journal_entries')->count());

        $this->ops()->resolveShift($admin->id, $shift, 'Selisih kas disetujui manajer');
        $this->assertSame('Selisih kas disetujui manajer', DB::table('v2_shifts')->where('id', $shift)->value('review_note'));
        $this->expectValidation(fn () => $this->ops()->resolveShift($admin->id, $shift, 'Alasan berbeda setelah resolusi'));
        $this->assertSame('Selisih kas disetujui manajer', DB::table('v2_shifts')->where('id', $shift)->value('review_note'));
    }

    public function test_both_source_splits_carry_and_admin_amount_with_validation(): void
    {
        $cashier = $this->actor('cashier');
        $this->accounts();

        $prev1 = $this->ops()->openShift($cashier->id, '40.00', 'admin');
        $this->ops()->closeShift($cashier->id, $prev1, '40.00');

        $bothId = $this->ops()->openShift($cashier->id, '100.00', 'both', $prev1);
        $both = DB::table('v2_shifts')->where('id', $bothId)->first();
        $this->assertEquals(100, $both->opening_cash);
        $this->assertEquals(40, $both->carry_amount);
        $this->assertEquals(60, $both->admin_amount);
        $this->ops()->closeShift($cashier->id, $bothId, '100.00');

        $prev2 = $this->ops()->openShift($cashier->id, '40.00', 'admin');
        $this->ops()->closeShift($cashier->id, $prev2, '40.00');

        $this->expectValidation(fn () => $this->ops()->openShift($cashier->id, '30.00', 'both', $prev2));
        $carryId = $this->ops()->openShift($cashier->id, '30.00', 'carry', $prev2);
        $carry = DB::table('v2_shifts')->where('id', $carryId)->first();
        $this->assertEquals(30, $carry->carry_amount);
        $this->assertEquals(0, $carry->admin_amount);
        $this->ops()->closeShift($cashier->id, $carryId, '30.00');

        $this->expectValidation(fn () => $this->ops()->openShift($cashier->id, '50.00', 'both', $prev1));
        $this->expectValidation(fn () => $this->ops()->openShift($cashier->id, '10.00', 'admin', $prev2));
        $this->expectValidation(fn () => $this->ops()->openShift($cashier->id, '10.00', 'both'));
        $this->assertDatabaseCount('v2_shifts', 4);
    }

    public function test_supplier_cash_proposal_requires_open_shift_and_reject_is_audited(): void
    {
        $cashier = $this->actor('cashier');
        $admin = $this->actor('admin');
        $this->accounts();
        $supplier = $this->party('supplier', 'SYN-SUP-A-001');
        $invoiceId = DB::table('v2_invoices')->insertGetId(['direction' => 'payable', 'party_id' => $supplier, 'sale_id' => null, 'number' => 'AP-SYN-001', 'amount' => '50.00', 'paid' => '0.00', 'due_date' => null, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);

        $this->expectValidation(fn () => $this->ops()->collect($cashier->id, $invoiceId, '50.00', 'cash', 'synthetic-supplier-001'));
        $this->assertDatabaseCount('v2_payments', 0);

        $shift = $this->ops()->openShift($cashier->id, '100.00', 'admin');
        $paymentId = $this->ops()->collect($cashier->id, $invoiceId, '50.00', 'cash', 'synthetic-supplier-001');
        $payment = DB::table('v2_payments')->where('id', $paymentId)->first();
        $this->assertSame('proposed', $payment->status);
        $this->assertSame($shift, (int) $payment->shift_id);

        $journals = DB::table('journal_entry_lines')->count();
        $this->assertForbidden(fn () => $this->ops()->rejectPayment($cashier->id, $paymentId, 'Harga salah input dari supplier'));
        $this->ops()->rejectPayment($admin->id, $paymentId, 'Harga salah input dari supplier');

        $rejected = DB::table('v2_payments')->where('id', $paymentId)->first();
        $this->assertSame('rejected', $rejected->status);
        $this->assertSame('Harga salah input dari supplier', $rejected->resolution_note);
        $this->assertSame($admin->id, (int) $rejected->resolved_by);
        $this->assertNotNull($rejected->resolved_at);
        $this->assertSame($journals, DB::table('journal_entry_lines')->count());
        $this->assertEquals(0, DB::table('v2_invoices')->where('id', $invoiceId)->value('paid'));

        $this->ops()->rejectPayment($admin->id, $paymentId, 'Harga salah input dari supplier');
        $this->assertSame('rejected', DB::table('v2_payments')->where('id', $paymentId)->value('status'));
        $this->expectValidation(fn () => $this->ops()->approvePayment($admin->id, $paymentId));
    }
}