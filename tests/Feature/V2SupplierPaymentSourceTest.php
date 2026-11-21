<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\Ledger;
use App\Services\V2\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Sumber dana pembayaran: Kas Utama (1101) dan Bank Utama (1102) tidak menyentuh laci shift;
 * Laci shift tetap memakai uang laci kasir yang shiftnya terbuka. Data sintetis, SQLite :memory:.
 */
class V2SupplierPaymentSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('V2 supplier payment source tests require isolated SQLite :memory:.');
        }
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function books(string $cash = '1000000.00', string $bank = '5000000.00'): void
    {
        foreach ([['1101', 'Kas Utama (Tunai)', 'asset', 'debit'], ['1102', 'Bank Utama', 'asset', 'debit'], ['1103', 'Piutang', 'asset', 'debit'], ['1301', 'Persediaan', 'asset', 'debit'], ['2101', 'Utang', 'liability', 'credit'], ['3101', 'Modal', 'equity', 'credit']] as [$code, $name, $type, $normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore(['account_code' => $code, 'account_name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        Ledger::post('synthetic_opening', 1, 'Saldo awal sintetis', [
            ['account_code' => '1101', 'debit' => $cash, 'credit' => '0.00'],
            ['account_code' => '1102', 'debit' => $bank, 'credit' => '0.00'],
            ['account_code' => '3101', 'debit' => '0.00', 'credit' => bcadd($cash, $bank, 2)],
        ]);
    }

    private function invoice(string $direction = 'payable', string $amount = '2540000.00'): int
    {
        $kind = $direction === 'payable' ? 'supplier' : 'customer';
        $party = DB::table('v2_parties')->insertGetId(['kind' => $kind, 'code' => 'SYN-'.strtoupper($kind).'-'.random_int(100, 999), 'name' => 'Pihak Sintetis', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('v2_invoices')->insertGetId(['direction' => $direction, 'party_id' => $party, 'sale_id' => null, 'number' => ($direction === 'payable' ? 'AP-SYN-' : 'AR-SYN-').random_int(1000, 9999), 'amount' => $amount, 'paid' => '0.00', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
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

    private function pay(User $actor, int $invoice, string $amount, string $method, string $key): TestResponse
    {
        return $this->actingAs($actor)->postJson(route('v2.payments.store', ['id' => $invoice]), [
            'amount' => $amount, 'method' => $method, 'idempotency_key' => $key,
        ]);
    }

    public function test_admin_pays_from_main_cash_without_opening_a_shift(): void
    {
        $admin = $this->actor('admin');
        $this->books();
        $invoice = $this->invoice();

        $this->pay($admin, $invoice, '1000000', 'main', 'syn-main-1')->assertOk()->assertJson(['status' => 'posted']);

        $this->assertSame('-1000000.00', bcsub($this->balance('1101'), '1000000.00', 2), 'Kas Utama harus berkurang sebesar pembayaran');
        $this->assertSame('1000000.00', $this->balance('2101'), 'Utang usaha berkurang: 2101 didebit sebesar pembayaran');
        $this->assertSame(0, DB::table('v2_shift_movements')->count(), 'Kas Utama tidak boleh mengubah laci shift');
        $this->assertEquals(1000000.0, (float) DB::table('v2_invoices')->where('id', $invoice)->value('paid'));
        $this->assertSame('main', (string) DB::table('v2_payments')->value('method'));
    }

    public function test_cashier_main_cash_proposal_is_posted_by_admin_even_after_shift_closed(): void
    {
        $admin = $this->actor('admin');
        $cashier = $this->actor('cashier');
        $this->books();
        $invoice = $this->invoice();
        $shiftId = $this->ops()->openShift($admin->id, '200000.00', 'admin');

        $this->pay($cashier, $invoice, '500000', 'main', 'syn-main-2')->assertOk()->assertJson(['status' => 'proposed']);
        $this->ops()->closeShift($admin->id, $shiftId, '200000');
        $payment = (int) DB::table('v2_payments')->value('id');
        $this->ops()->approvePayment($admin->id, $payment);

        $this->assertSame('posted', (string) DB::table('v2_payments')->where('id', $payment)->value('status'));
        $this->assertSame('500000.00', $this->balance('2101'));
        $this->assertSame(500000.0, (float) bcsub('1000000.00', $this->balance('1101'), 2));
        $this->assertSame(0, DB::table('v2_shift_movements')->count(), 'Pembayaran dari Kas Utama tidak mengubah laci');
    }

    public function test_drawer_cash_still_needs_an_open_shift_and_moves_the_drawer(): void
    {
        $admin = $this->actor('admin');
        $this->books();
        $invoice = $this->invoice();

        $this->pay($admin, $invoice, '100000', 'cash', 'syn-drawer-1')
            ->assertStatus(422)
            ->assertJsonPath('errors.operations.0', 'Shift toko belum dibuka admin.');

        $shiftId = $this->ops()->openShift($admin->id, '2000000.00', 'admin');
        $this->pay($admin, $invoice, '100000', 'cash', 'syn-drawer-2')->assertOk()->assertJson(['status' => 'posted']);

        $movement = DB::table('v2_shift_movements')->where('shift_id', $shiftId)->first();
        $this->assertNotNull($movement);
        $this->assertSame('out', $movement->direction);
        $this->assertSame('supplier', $movement->kind);
        $this->assertEquals(100000.0, (float) $movement->amount);
        $this->assertSame(100000.0, (float) bcsub('1000000.00', $this->balance('1101'), 2), 'Laci shift mengurangi Kas sebesar pembayaran');
        $closed = $this->ops()->closeShift($admin->id, $shiftId, '1900000');
        $this->assertSame('0.00', (string) $closed['difference']);
    }

    public function test_main_cash_is_rejected_for_customer_receivables(): void
    {
        $admin = $this->actor('admin');
        $this->books();
        $invoice = $this->invoice('receivable', '100000.00');

        $this->pay($admin, $invoice, '100000', 'main', 'syn-ar-main-1')
            ->assertStatus(422)
            ->assertJsonPath('errors.operations.0', 'Kas Utama hanya untuk pembayaran ke supplier.');
    }

    public function test_main_cash_cannot_exceed_the_cash_balance(): void
    {
        $admin = $this->actor('admin');
        $this->books('100000.00', '5000000.00');
        $invoice = $this->invoice();

        $this->pay($admin, $invoice, '250000', 'main', 'syn-main-3')
            ->assertStatus(422)
            ->assertJsonPath('errors.operations.0', 'Saldo Kas Utama (Tunai) tidak cukup untuk pembayaran keluar.');
    }
}
