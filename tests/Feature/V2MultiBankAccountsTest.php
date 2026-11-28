<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\AccountingReportService;
use App\Services\V2\AdminSummary;
use App\Services\V2\DeliveryTrips;
use App\Services\V2\Ledger;
use App\Services\V2\Operations;
use App\Services\V2\ReceiptDrafts;
use App\Services\V2\ReportsDocuments;
use App\Services\V2\SupplyTrips;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class V2MultiBankAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Multi-bank tests require isolated SQLite :memory:.');
        }
    }

    private function actor(string $role = 'admin'): User
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

    private function seedBaseAccounts(): void
    {
        $rows = [
            ['1101', 'Kas Utama (Tunai)', 'asset', 'debit', false],
            ['1102', 'Bank Utama', 'asset', 'debit', true],
            ['1103', 'Piutang Usaha', 'asset', 'debit', false],
            ['1191', 'Uang Muka Sopir', 'asset', 'debit', false],
            ['1301', 'Persediaan', 'asset', 'debit', false],
            ['2101', 'Utang Usaha', 'liability', 'credit', false],
            ['3101', 'Modal Awal', 'equity', 'credit', false],
            ['4101', 'Penjualan', 'revenue', 'credit', false],
            ['4102', 'Pendapatan Ongkir', 'revenue', 'credit', false],
            ['5101', 'HPP', 'expense', 'debit', false],
            ['6101', 'Beban Operasional Toko', 'expense', 'debit', false],
            ['6102', 'Beban Listrik & Air', 'expense', 'debit', false],
            ['6201', 'Beban Transportasi & Ritase Armada', 'expense', 'debit', false],
        ];
        foreach ($rows as [$code, $name, $type, $normal, $isBank]) {
            DB::table('chart_of_accounts')->insertOrIgnore([
                'account_code' => $code,
                'account_name' => $name,
                'account_type' => $type,
                'normal_balance' => $normal,
                'is_active' => true,
                'is_bank_account' => $isBank,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function fund(string $code, string $amount): void
    {
        Ledger::post('synthetic_opening', random_int(1, 999999), 'Dana sintetis '.$code, [
            ['account_code' => $code, 'debit' => $amount, 'credit' => '0.00'],
            ['account_code' => '3101', 'debit' => '0.00', 'credit' => $amount],
        ]);
    }

    public function test_admin_can_add_second_bank_account_in_coa_and_it_appears_across_payment_views(): void
    {
        config(['v2.enabled' => true]);
        $this->seedBaseAccounts();
        $this->fund('1102', '500000.00');
        $admin = $this->actor('admin');

        $this->actingAs($admin)->postJson('/v2/chart-of-accounts', [
            'account_code' => '1104',
            'account_name' => 'Bank BCA Operasional',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_bank_account' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('chart_of_accounts', [
            'account_code' => '1104',
            'is_bank_account' => true,
        ]);

        // Non-asset account cannot be flagged as bank account.
        $this->actingAs($admin)->postJson('/v2/chart-of-accounts', [
            'account_code' => '6190',
            'account_name' => 'Beban Bank',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_bank_account' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('is_bank_account');

        // Default bank 1102 cannot be unflagged.
        $bank1102Id = (int) DB::table('chart_of_accounts')->where('account_code', '1102')->value('id');
        $this->actingAs($admin)->putJson('/v2/chart-of-accounts/'.$bank1102Id, [
            'account_code' => '1102',
            'account_name' => 'Bank Utama',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_bank_account' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('is_bank_account');

        // Immediately visible in workspace POS/Tagihan/Pengiriman, Expenses, and Journals adjustment dropdowns.
        $this->actingAs($admin)->get(route('pos.index'))
            ->assertOk()
            ->assertSee('1104 · Bank BCA Operasional');

        $this->actingAs($admin)->get(route('expenses.index'))
            ->assertOk()
            ->assertSee('1104 - Bank BCA Operasional');

        $this->actingAs($admin)->get(route('journals.index'))
            ->assertOk()
            ->assertSee('1104 — Bank BCA Operasional');
    }

    public function test_pos_transfer_and_receivable_collection_post_to_selected_second_bank_account(): void
    {
        Storage::fake('local');
        config(['v2.enabled' => true]);
        $this->seedBaseAccounts();
        DB::table('chart_of_accounts')->insert([
            'account_code' => '1104',
            'account_name' => 'Bank Mandiri',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_bank_account' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = $this->actor('admin');
        $ops = app(Operations::class);
        $ops->openShift($admin->id, '100000.00', 'admin');

        $customerId = DB::table('v2_parties')->insertGetId([
            'kind' => 'customer', 'code' => 'PLG-0001', 'name' => 'Budi Proyek', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'sku' => 'SMN-01', 'name' => 'Semen 50kg', 'unit' => 'sak', 'category' => 'Semen',
            'selling_price' => '75000.00', 'cost_price' => '60000.0000', 'stock_quantity' => '20.0000',
            'min_stock' => '2.0000', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $photo = UploadedFile::fake()->image('bukti-mandiri.png', 200, 200);
        $response = $this->actingAs($admin)->post(route('v2.sales.store'), [
            'items' => [['product_id' => $productId, 'quantity' => '2']],
            'delivery_fee' => '0',
            'customer_id' => $customerId,
            'payment_method' => 'transfer',
            'bank_account_code' => '1104',
            'paid_amount' => '150000.00',
            'idempotency_key' => 'pos-bank-1104-key',
            'fulfillment' => 'pickup',
            'transfer_proof' => $photo,
        ], ['Accept' => 'application/json'])->assertOk();

        $payment = DB::table('v2_payments')->where('invoice_id', $response->json('invoice_id'))->first();
        $this->assertNotNull($payment);
        $this->assertSame('1104', $payment->bank_account_code);
        $this->assertSame('pending', $payment->status);

        $ops->approvePayment($admin->id, (int) $payment->id);

        $balances = app(AccountBalanceService::class);
        $this->assertSame('150000.00', (string) $balances->balance('1104'));
        $this->assertSame('0.00', (string) $balances->balance('1102'));
    }

    public function test_supplier_payment_and_operating_expense_debit_and_guard_selected_bank_account(): void
    {
        config(['v2.enabled' => true]);
        $this->seedBaseAccounts();
        DB::table('chart_of_accounts')->insert([
            'account_code' => '1104',
            'account_name' => 'Bank BRI',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_bank_account' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->fund('1102', '5000000.00');
        $this->fund('1104', '300000.00');

        $admin = $this->actor('admin');
        $supplierId = DB::table('v2_parties')->insertGetId([
            'kind' => 'supplier', 'code' => 'SUP-0001', 'name' => 'PT Bahan Jaya', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $invoiceId = DB::table('v2_invoices')->insertGetId([
            'direction' => 'payable', 'party_id' => $supplierId, 'sale_id' => null, 'number' => 'AP-000001',
            'amount' => '1000000.00', 'paid' => '0.00', 'status' => 'open',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Exceeding 1104 balance must fail even though 1102 has 5,000,000.
        $this->actingAs($admin)->postJson(route('v2.payments.store', ['id' => $invoiceId]), [
            'amount' => '400000.00',
            'method' => 'transfer',
            'bank_account_code' => '1104',
            'idempotency_key' => 'pay-1104-over',
        ])->assertUnprocessable();

        // Paying within 1104 balance succeeds and reduces only 1104.
        $this->actingAs($admin)->postJson(route('v2.payments.store', ['id' => $invoiceId]), [
            'amount' => '200000.00',
            'method' => 'transfer',
            'bank_account_code' => '1104',
            'idempotency_key' => 'pay-1104-ok',
        ])->assertOk()->assertJson(['status' => 'posted']);

        // Operating expense from 1104 reduces remaining 100,000 -> 25,000.
        $this->actingAs($admin)->postJson(route('v2.expenses.store'), [
            'date' => now()->toDateString(),
            'account_code' => '6102',
            'source' => 'bank',
            'bank_account_code' => '1104',
            'amount' => '75000.00',
            'description' => 'Token listrik dari BRI',
        ])->assertCreated();

        // Invalid non-bank COA code rejected on operating expense.
        $this->actingAs($admin)->postJson(route('v2.expenses.store'), [
            'date' => now()->toDateString(),
            'account_code' => '6102',
            'source' => 'bank',
            'bank_account_code' => '1301',
            'amount' => '10000.00',
        ])->assertUnprocessable()->assertJsonValidationErrors('bank_account_code');

        $balances = app(AccountBalanceService::class);
        $this->assertSame('25000.00', (string) $balances->balance('1104'));
        $this->assertSame('5000000.00', (string) $balances->balance('1102'));
        $this->assertDatabaseHas('expenses', ['bank_account_code' => '1104', 'amount' => '75000.00']);

        $this->actingAs($admin)->get(route('expenses.index'))
            ->assertOk()
            ->assertSee('1104 · Bank BRI');

        $this->get(route('journals.index', ['source' => 'operating_expense', 'partial' => 1]))
            ->assertOk()
            ->assertSee('value="operating_expense"', false)
            ->assertSee('Pengeluaran Operasional');
    }

    public function test_driver_advance_settlement_trip_and_receipt_freight_use_selected_bank_account(): void
    {
        config(['v2.enabled' => true]);
        $this->seedBaseAccounts();
        DB::table('chart_of_accounts')->insert([
            'account_code' => '1104',
            'account_name' => 'Bank BNI',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_bank_account' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->fund('1104', '1000000.00');

        $admin = $this->actor('admin');
        $vehicleId = DB::table('vehicles')->insertGetId([
            'license_plate' => 'B 1234 XYZ', 'vehicle_type' => 'truck', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $driverId = DB::table('v2_drivers')->insertGetId([
            'code' => 'SPR-0001', 'kind' => 'external', 'employee_id' => null, 'name' => 'Pak Joko',
            'phone' => '0812345678', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Driver advance 300k from 1104 -> 700k, settle with 200k expense + 100k refund to 1104 -> 800k.
        $deliveryTrips = app(DeliveryTrips::class);
        $adv = $deliveryTrips->advance($admin->id, [
            'vehicle_id' => $vehicleId,
            'driver_id' => $driverId,
            'destination' => 'Proyek Utara',
            'date' => now()->toDateString(),
            'amount' => '300000.00',
            'method' => 'transfer',
            'bank_account_code' => '1104',
            'idempotency_key' => 'adv-1104-1',
        ]);
        $deliveryTrips->settle($admin->id, $adv['advance_id'], [
            'fuel_cost' => '150000.00',
            'allowance' => '50000.00',
            'refund_method' => 'transfer',
            'refund_bank_account_code' => '1104',
            'refund_received' => true,
            'idempotency_key' => 'settle-1104-1',
        ]);

        // Direct trip 100k from 1104 -> 700k.
        $supplyTrips = app(SupplyTrips::class);
        $supplyTrips->trip($admin->id, [
            'vehicle_id' => $vehicleId,
            'driver_id' => $driverId,
            'date' => now()->toDateString(),
            'destination' => 'Proyek Timur',
            'fuel_cost' => '60000.00',
            'allowance' => '40000.00',
            'payment_method' => 'transfer',
            'bank_account_code' => '1104',
            'idempotency_key' => 'trip-1104-1',
        ]);

        // Receipt draft with freight 50k paid from 1104 -> 650k.
        $supplierId = DB::table('v2_parties')->insertGetId([
            'kind' => 'supplier', 'code' => 'SUP-0002', 'name' => 'CV Pasir', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'sku' => 'PSR-01', 'name' => 'Pasir Beton', 'unit' => 'm3', 'category' => 'Pasir',
            'selling_price' => '300000.00', 'cost_price' => '200000.0000', 'stock_quantity' => '5.0000',
            'min_stock' => '1.0000', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_product_suppliers')->insert([
            'product_id' => $productId, 'supplier_id' => $supplierId, 'is_primary' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $drafts = app(ReceiptDrafts::class);
        $draft = $drafts->save($admin->id, [
            'supplier_id' => $supplierId,
            'receipt_date' => now()->toDateString(),
            'items' => [['product_id' => $productId, 'quantity' => '2', 'unit_cost' => '200000']],
            'freight_amount' => '50000.00',
            'freight_method' => 'bank',
            'freight_account' => '6101',
            'freight_bank_account_code' => '1104',
            'idempotency_key' => 'draft-1104-1',
        ]);
        $drafts->confirm($admin->id, $draft['draft_id'], ['version' => $draft['version'], 'confirm_final' => true]);

        $balances = app(AccountBalanceService::class);
        $this->assertSame('650000.00', (string) $balances->balance('1104'));
        $this->assertSame('0.00', (string) $balances->balance('1102'));
    }

    public function test_adjustments_cash_flow_and_admin_summary_include_multiple_bank_accounts(): void
    {
        config(['v2.enabled' => true]);
        $this->seedBaseAccounts();
        DB::table('chart_of_accounts')->insert([
            'account_code' => '1104',
            'account_name' => 'Bank BCA',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_bank_account' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->fund('1101', '500000.00');
        $this->fund('1102', '1000000.00');

        $admin = $this->actor('admin');

        // Transfer 250,000 from 1102 to 1104 via cash/bank adjustment.
        $this->actingAs($admin)->postJson(route('v2.journals.adjustments.store'), [
            'transaction_date' => now()->toDateString(),
            'account_code' => '1104',
            'counter_account_code' => '1102',
            'target_balance' => '250000.00',
            'description' => 'Pindah dana antar bank ke BCA',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('difference', '250000.00');

        // Cash flow includes 1101, 1102, 1104; internal transfer 1102->1104 nets to 0 and reconciles.
        $report = app(AccountingReportService::class)->generateCashFlow();
        $this->assertTrue($report['is_reconciled']);
        $this->assertSame('1500000.00', $report['ending_cash']);
        $breakdown = collect($report['cash_breakdown'])->pluck('balance', 'code')->all();
        $this->assertSame('500000.00', $breakdown['1101']);
        $this->assertSame('750000.00', $breakdown['1102']);
        $this->assertSame('250000.00', $breakdown['1104']);

        // Admin summary totals all bank accounts and lists breakdown.
        $snapshot = app(AdminSummary::class)->snapshot(now()->toDateString());
        $this->assertSame('1000000.00', $snapshot['bank']);
        $this->assertCount(2, $snapshot['bank_accounts']);
        $this->assertSame(['1102', '1104'], array_column($snapshot['bank_accounts'], 'account_code'));
    }

    public function test_archived_bank_account_is_rejected_for_new_payments(): void
    {
        $this->seedBaseAccounts();
        DB::table('chart_of_accounts')->insert([
            'account_code' => '1104',
            'account_name' => 'Bank Lama',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => false,
            'is_bank_account' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = $this->actor('admin');
        $supplierId = DB::table('v2_parties')->insertGetId([
            'kind' => 'supplier', 'code' => 'SUP-9', 'name' => 'PT Uji', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $invoiceId = DB::table('v2_invoices')->insertGetId([
            'direction' => 'payable', 'party_id' => $supplierId, 'sale_id' => null, 'number' => 'AP-9',
            'amount' => '100000.00', 'paid' => '0.00', 'status' => 'open',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        app(Operations::class)->collect($admin->id, $invoiceId, '50000.00', 'transfer', 'archived-bank-key', null, null, false, '1104');
    }

    public function test_admin_can_transfer_between_cash_and_banks_with_zero_net_cash_flow(): void
    {
        config(['v2.enabled' => true]);
        $this->seedBaseAccounts();
        DB::table('chart_of_accounts')->insert([
            'account_code' => '1104',
            'account_name' => 'Bank BCA',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_bank_account' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->fund('1101', '800000.00');
        $this->fund('1102', '200000.00');
        $admin = $this->actor('admin');

        // Cashier cannot transfer.
        $this->actingAs($this->actor('cashier'))->postJson(route('v2.journals.transfers.store'), [
            'transaction_date' => now()->toDateString(),
            'from_account_code' => '1101',
            'to_account_code' => '1104',
            'amount' => '100000.00',
            'description' => 'Setor kasir ditolak',
            'password' => 'password',
        ])->assertForbidden();

        // Exceeding balance rejected.
        $this->actingAs($admin)->postJson(route('v2.journals.transfers.store'), [
            'transaction_date' => now()->toDateString(),
            'from_account_code' => '1101',
            'to_account_code' => '1104',
            'amount' => '900000.00',
            'description' => 'Melebihi saldo kas',
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');

        // Valid transfer 1101 -> 1104 (300k).
        $this->actingAs($admin)->postJson(route('v2.journals.transfers.store'), [
            'transaction_date' => now()->toDateString(),
            'from_account_code' => '1101',
            'to_account_code' => '1104',
            'amount' => '300000.00',
            'description' => 'Setor kas sore ke BCA',
            'password' => 'password',
        ])->assertCreated()->assertJsonPath('status', 'posted');

        $balances = app(AccountBalanceService::class);
        $this->assertSame('500000.00', (string) $balances->balance('1101'));
        $this->assertSame('300000.00', (string) $balances->balance('1104'));

        $report = app(AccountingReportService::class)->generateCashFlow();
        $this->assertTrue($report['is_reconciled']);
        $this->assertSame('1000000.00', $report['ending_cash']);
    }

    public function test_admin_can_correct_bank_account_during_approval_and_daily_report_breaks_down_by_bank(): void
    {
        Storage::fake('local');
        config(['v2.enabled' => true]);
        $this->seedBaseAccounts();
        DB::table('chart_of_accounts')->insert([
            'account_code' => '1104',
            'account_name' => 'Bank BCA',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_bank_account' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = $this->actor('admin');
        $ops = app(Operations::class);
        $ops->openShift($admin->id, '50000.00', 'admin');

        $customerId = DB::table('v2_parties')->insertGetId([
            'kind' => 'customer', 'code' => 'PLG-0099', 'name' => 'Pak Hendra', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'sku' => 'CAT-01', 'name' => 'Cat Tembok 5kg', 'unit' => 'kaleng', 'category' => 'Cat',
            'selling_price' => '120000.00', 'cost_price' => '90000.0000', 'stock_quantity' => '10.0000',
            'min_stock' => '2.0000', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Cashier selects 1102 by mistake, admin corrects to 1104 upon approval.
        $res = $this->actingAs($admin)->post(route('v2.sales.store'), [
            'items' => [['product_id' => $productId, 'quantity' => '1']],
            'delivery_fee' => '0',
            'customer_id' => $customerId,
            'payment_method' => 'transfer',
            'bank_account_code' => '1102',
            'paid_amount' => '120000.00',
            'idempotency_key' => 'pos-approve-override-1',
            'fulfillment' => 'pickup',
            'transfer_proof' => UploadedFile::fake()->image('bca.png', 100, 100),
        ], ['Accept' => 'application/json'])->assertOk();

        $paymentId = (int) DB::table('v2_payments')->where('invoice_id', $res->json('invoice_id'))->value('id');
        $this->actingAs($admin)->postJson(route('v2.payments.approve', ['id' => $paymentId]), [
            'bank_account_code' => '1104',
        ])->assertOk()->assertJsonPath('status', 'posted');

        $this->assertDatabaseHas('v2_payments', ['id' => $paymentId, 'bank_account_code' => '1104', 'status' => 'posted']);
        $this->assertSame('120000.00', (string) app(AccountBalanceService::class)->balance('1104'));
        $this->assertSame('0.00', (string) app(AccountBalanceService::class)->balance('1102'));

        $daily = app(ReportsDocuments::class)->daily($admin->id, now()->toDateString());
        $this->assertSame('120000.00', $daily['bank']['posted']);
        $this->assertCount(1, $daily['bank']['by_account']);
        $this->assertSame('1104', $daily['bank']['by_account'][0]['code']);
        $this->assertSame('120000.00', $daily['bank']['by_account'][0]['posted']);
    }

    public function test_credit_sale_stores_optional_due_date_and_expenses_filter_by_specific_bank(): void
    {
        config(['v2.enabled' => true]);
        $this->seedBaseAccounts();
        DB::table('chart_of_accounts')->insert([
            'account_code' => '1104',
            'account_name' => 'Bank BCA',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_bank_account' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->fund('1102', '500000.00');
        $this->fund('1104', '500000.00');

        $admin = $this->actor('admin');
        app(Operations::class)->openShift($admin->id, '50000.00', 'admin');

        $customerId = DB::table('v2_parties')->insertGetId([
            'kind' => 'customer', 'code' => 'PLG-0100', 'name' => 'CV Bangun', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'sku' => 'BSI-01', 'name' => 'Besi 10mm', 'unit' => 'batang', 'category' => 'Besi',
            'selling_price' => '85000.00', 'cost_price' => '70000.0000', 'stock_quantity' => '10.0000',
            'min_stock' => '2.0000', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Past due_date rejected.
        $this->actingAs($admin)->postJson(route('v2.sales.store'), [
            'items' => [['product_id' => $productId, 'quantity' => '1']],
            'delivery_fee' => '0',
            'customer_id' => $customerId,
            'payment_method' => 'credit',
            'due_date' => now()->subDay()->toDateString(),
            'paid_amount' => '0',
            'idempotency_key' => 'credit-due-past',
            'fulfillment' => 'pickup',
        ])->assertUnprocessable();

        // Valid future due_date saved on receivable invoice.
        $due = now()->addDays(14)->toDateString();
        $res = $this->actingAs($admin)->postJson(route('v2.sales.store'), [
            'items' => [['product_id' => $productId, 'quantity' => '2']],
            'delivery_fee' => '0',
            'customer_id' => $customerId,
            'payment_method' => 'credit',
            'due_date' => $due,
            'paid_amount' => '0',
            'idempotency_key' => 'credit-due-ok',
            'fulfillment' => 'pickup',
        ])->assertOk();

        $this->assertDatabaseHas('v2_invoices', ['id' => $res->json('invoice_id'), 'due_date' => $due]);

        // Expenses filter by specific bank account.
        $this->actingAs($admin)->postJson(route('v2.expenses.store'), [
            'date' => now()->toDateString(), 'account_code' => '6102', 'source' => 'bank',
            'bank_account_code' => '1102', 'amount' => '20000.00', 'description' => 'Biaya dari Bank Utama',
        ])->assertCreated();
        $this->actingAs($admin)->postJson(route('v2.expenses.store'), [
            'date' => now()->toDateString(), 'account_code' => '6102', 'source' => 'bank',
            'bank_account_code' => '1104', 'amount' => '35000.00', 'description' => 'Biaya dari Bank BCA',
        ])->assertCreated();

        $this->actingAs($admin)->get(route('expenses.index', ['payment_method' => 'bank:1104']))
            ->assertOk()
            ->assertSee('Biaya dari Bank BCA')
            ->assertDontSee('Biaya dari Bank Utama');
    }
}
