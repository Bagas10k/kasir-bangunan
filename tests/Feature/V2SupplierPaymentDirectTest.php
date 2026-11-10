<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\Ledger;
use App\Services\V2\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Admin membayar utang supplier langsung tercatat (tunai dari laci shift-nya atau transfer dari bank);
 * kasir tetap mengajukan dan admin menyetujui. Data sintetis, SQLite :memory:.
 */
class V2SupplierPaymentDirectTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('V2 supplier payment tests require isolated SQLite :memory:.');
        }
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    /** Bagan akun + saldo kas dan bank awal lewat jurnal (sintetis). */
    private function books(string $cash = '1000000.00', string $bank = '5000000.00'): void
    {
        foreach ([['1101', 'Kas', 'asset', 'debit'], ['1102', 'Bank', 'asset', 'debit'], ['1103', 'Piutang', 'asset', 'debit'], ['1301', 'Persediaan', 'asset', 'debit'], ['2101', 'Utang', 'liability', 'credit'], ['3101', 'Modal', 'equity', 'credit']] as [$code, $name, $type, $normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore(['account_code' => $code, 'account_name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        Ledger::post('synthetic_opening', 1, 'Saldo awal sintetis', [
            ['account_code' => '1101', 'debit' => $cash, 'credit' => '0.00'],
            ['account_code' => '1102', 'debit' => $bank, 'credit' => '0.00'],
            ['account_code' => '3101', 'debit' => '0.00', 'credit' => bcadd($cash, $bank, 2)],
        ]);
    }

    private function payable(string $amount = '2540000.00'): int
    {
        $supplier = DB::table('v2_parties')->insertGetId(['kind' => 'supplier', 'code' => 'SYN-SUP-'.random_int(100, 999), 'name' => 'CV Pemasok Sintetis', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('v2_invoices')->insertGetId(['direction' => 'payable', 'party_id' => $supplier, 'sale_id' => null, 'number' => 'AP-SYN-'.random_int(1000, 9999), 'amount' => $amount, 'paid' => '0.00', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function ops(): Operations
    {
        return app(Operations::class);
    }

    private function balance(string $code): string
    {
        $total = '0.00';
        foreach (DB::table('journal_entry_lines')->where('account_code', $code)->get(['debit', 'credit']) as $line) {
            $total = bcsub(bcadd($total, (string) $line->debit, 2), (string) $line->credit, 2);
        }

        return $total;
    }

    public function test_admin_transfer_to_supplier_is_posted_immediately(): void
    {
        $admin = $this->actor('admin');
        $this->books();
        $invoice = $this->payable();

        $response = $this->actingAs($admin)->postJson(route('v2.payments.store', ['id' => $invoice]), [
            'amount' => '1000000', 'method' => 'transfer', 'idempotency_key' => 'syn-ap-transfer-1', 'reference' => 'TRF 0811',
        ])->assertOk()->assertJson(['status' => 'posted']);

        $payment = DB::table('v2_payments')->where('id', $response->json('id'))->first();
        $this->assertSame('posted', $payment->status);
        $this->assertSame($admin->id, (int) $payment->approved_by);
        $this->assertNotNull($payment->journal_entry_id);
        $row = DB::table('v2_invoices')->where('id', $invoice)->first();
        $this->assertEquals(1000000, (float) $row->paid);
        $this->assertSame('open', $row->status);
        $this->assertSame('4000000.00', $this->balance('1102'));
        // Tagihan sintetis tanpa jurnal penerimaan: sisi debit 2101 = jumlah yang dibayar.
        $this->assertSame('1000000.00', $this->balance('2101'));

        // Pelunasan sisa.
        $this->actingAs($admin)->postJson(route('v2.payments.store', ['id' => $invoice]), ['amount' => '1540000', 'method' => 'transfer', 'idempotency_key' => 'syn-ap-transfer-2'])->assertOk();
        $this->assertSame('paid', DB::table('v2_invoices')->where('id', $invoice)->value('status'));
        $this->assertSame('2540000.00', $this->balance('2101'));
        $this->assertSame('2460000.00', $this->balance('1102'));
    }

    public function test_admin_cash_to_supplier_leaves_the_admin_drawer_and_needs_enough_cash(): void
    {
        $admin = $this->actor('admin');
        $this->books();
        $invoice = $this->payable('300000.00');

        $this->actingAs($admin)->postJson(route('v2.payments.store', ['id' => $invoice]), ['amount' => '100000', 'method' => 'cash', 'idempotency_key' => 'syn-ap-cash-0'])
            ->assertStatus(422);

        $shift = $this->ops()->openShift($admin->id, '150000.00', 'admin');
        $this->actingAs($admin)->postJson(route('v2.payments.store', ['id' => $invoice]), ['amount' => '200000', 'method' => 'cash', 'idempotency_key' => 'syn-ap-cash-1'])
            ->assertStatus(422)->assertJsonFragment(['message' => 'Uang tunai laci tidak cukup untuk pembayaran.']);
        $this->assertSame(0, DB::table('v2_payments')->count());

        $id = $this->actingAs($admin)->postJson(route('v2.payments.store', ['id' => $invoice]), ['amount' => '100000', 'method' => 'cash', 'idempotency_key' => 'syn-ap-cash-2'])
            ->assertOk()->json('id');
        $movement = DB::table('v2_shift_movements')->where('reference_type', 'v2_payment')->where('reference_id', $id)->first();
        $this->assertSame($shift, (int) $movement->shift_id);
        $this->assertSame('out', $movement->direction);
        $this->assertEquals(100000, (float) $movement->amount);
        $this->assertSame('900000.00', $this->balance('1101'));
        $this->assertEquals(100000, (float) DB::table('v2_invoices')->where('id', $invoice)->value('paid'));

        // Kunci sama: tidak dicatat dua kali.
        $this->actingAs($admin)->postJson(route('v2.payments.store', ['id' => $invoice]), ['amount' => '100000', 'method' => 'cash', 'idempotency_key' => 'syn-ap-cash-2'])->assertOk();
        $this->assertSame(1, DB::table('v2_payments')->count());
        $this->assertEquals(100000, (float) DB::table('v2_invoices')->where('id', $invoice)->value('paid'));
    }

    public function test_cashier_still_proposes_and_an_admin_may_approve_an_old_own_proposal(): void
    {
        $cashier = $this->actor('cashier');
        $admin = $this->actor('admin');
        $this->books();
        $invoice = $this->payable('500000.00');

        $proposal = $this->ops()->collect($cashier->id, $invoice, '200000', 'transfer', 'syn-ap-proposal-1');
        $this->assertSame('proposed', DB::table('v2_payments')->where('id', $proposal)->value('status'));
        $this->assertEquals(0, (float) DB::table('v2_invoices')->where('id', $invoice)->value('paid'));
        $this->ops()->approvePayment($admin->id, $proposal);
        $this->assertSame('posted', DB::table('v2_payments')->where('id', $proposal)->value('status'));

        // Pengajuan lama milik admin sendiri (dibuat sebelum aturan bayar langsung) kini bisa disetujui olehnya.
        $old = DB::table('v2_payments')->insertGetId(['invoice_id' => $invoice, 'amount' => '50000.00', 'method' => 'transfer', 'status' => 'proposed', 'requested_by' => $admin->id, 'idempotency_key' => 'syn-ap-old-1', 'request_hash' => str_repeat('b', 64), 'created_at' => now(), 'updated_at' => now()]);
        $this->ops()->approvePayment($admin->id, $old);
        $this->assertEquals(250000, (float) DB::table('v2_invoices')->where('id', $invoice)->value('paid'));

        try {
            $this->ops()->collect($admin->id, $invoice, '300000', 'transfer', 'syn-ap-over');
            $this->fail('Nominal melebihi sisa harus ditolak.');
        } catch (ValidationException) {
            $this->assertEquals(250000, (float) DB::table('v2_invoices')->where('id', $invoice)->value('paid'));
        }
    }

    public function test_payable_rows_have_their_own_pay_form_in_the_invoice_table(): void
    {
        $admin = $this->actor('admin');
        $this->books();
        $invoice = $this->payable();

        $this->actingAs($admin)->get(route('v2.index'))->assertOk()
            ->assertSee('data-direction="payable"', false)
            ->assertSee(route('v2.payments.store', ['id' => $invoice]), false)
            ->assertSee('Bayar ke supplier')
            ->assertDontSee('Ajukan di bagian Pembayaran supplier')
            ->assertDontSee('supplier-pay-heading', false);
    }
}
