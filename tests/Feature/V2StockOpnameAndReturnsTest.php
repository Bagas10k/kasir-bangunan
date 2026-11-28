<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccountingReportService;
use App\Services\V2\Ledger;
use App\Services\V2\Money;
use App\Services\V2\Operations;
use App\Services\V2\PurchaseReturns;
use App\Services\V2\SalesReturns;
use App\Services\V2\StockOpnames;
use App\Services\V2\SupplyTrips;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class V2StockOpnameAndReturnsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('V2 stock opname and return tests require isolated SQLite :memory:.');
        }
    }

    private function actor(string $role = 'admin'): User
    {
        $user = User::factory()->create(['is_active' => true]);
        DB::table('v2_user_roles')->insert([
            'user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }

    private function seedAccounts(bool $withReturnContra = true): void
    {
        $rows = [
            ['1101', 'Kas Utama (Tunai)', 'asset', 'debit', false],
            ['1102', 'Bank Utama', 'asset', 'debit', true],
            ['1103', 'Piutang Usaha', 'asset', 'debit', false],
            ['1301', 'Persediaan Material', 'asset', 'debit', false],
            ['2101', 'Utang Usaha', 'liability', 'credit', false],
            ['3101', 'Modal Pemilik', 'equity', 'credit', false],
            ['4101', 'Penjualan Material', 'revenue', 'credit', false],
            ['4102', 'Pendapatan Ongkir', 'revenue', 'credit', false],
            ['5101', 'Beban Pokok Penjualan', 'expense', 'debit', false],
            ['5201', 'Beban Selisih Opname', 'expense', 'debit', false],
            ['6101', 'Beban Operasional Toko', 'expense', 'debit', false],
            ['6201', 'Beban Transportasi & Ritase', 'expense', 'debit', false],
            ['7101', 'Pendapatan Lain-lain', 'revenue', 'credit', false],
        ];
        if ($withReturnContra) {
            $rows[] = ['4104', 'Retur Penjualan', 'revenue', 'debit', false];
        }
        foreach ($rows as [$code, $name, $type, $normal, $isBank]) {
            DB::table('chart_of_accounts')->insertOrIgnore([
                'account_code' => $code,
                'account_name' => $name,
                'account_type' => $type,
                'normal_balance' => $normal,
                'is_bank_account' => $isBank,
                'is_contra' => $code === '4104',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function fundBooks(): void
    {
        Ledger::post('opening', 1, 'Saldo awal buku', [
            ['account_code' => '1101', 'debit' => '500000.00', 'credit' => '0.00'],
            ['account_code' => '1102', 'debit' => '500000.00', 'credit' => '0.00'],
            ['account_code' => '3101', 'debit' => '0.00', 'credit' => '1000000.00'],
        ]);
    }

    private function product(string $sku, string $name, string $stock, string $cost, string $price): int
    {
        return (int) DB::table('products')->insertGetId([
            'sku' => $sku,
            'name' => $name,
            'unit' => 'sak',
            'category' => 'Umum',
            'cost_price' => $cost,
            'selling_price' => $price,
            'stock_quantity' => $stock,
            'min_stock' => '1.0000',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_stock_opname_v2_adjusts_stock_and_posts_balanced_variance_journal_idempotently(): void
    {
        $this->seedAccounts();
        $admin = $this->actor('admin');
        $cashier = $this->actor('cashier');

        $pLoss = $this->product('SYN-OPN-01', 'Semen Sintetis Susut', '10.0000', '50000.0000', '65000.00');
        $pGain = $this->product('SYN-OPN-02', 'Pasir Sintetis Lebih', '5.0000', '100000.0000', '125000.00');
        $pSame = $this->product('SYN-OPN-03', 'Bata Sintetis Cocok', '8.0000', '1000.0000', '1500.00');
        Ledger::post('opening', 1, 'Persediaan awal opname', [
            ['account_code' => '1301', 'debit' => '1008000.00', 'credit' => '0.00'],
            ['account_code' => '3101', 'debit' => '0.00', 'credit' => '1008000.00'],
        ]);

        // Kasir ditolak (403)
        $this->actingAs($cashier)->getJson(route('v2.stock-opnames.products', ['q' => 'Semen']))->assertForbidden();
        $this->actingAs($cashier)->postJson(route('v2.stock-opnames.store'), [
            'opname_date' => now()->toDateString(),
            'idempotency_key' => 'opn-cashier-denied',
            'items' => [['product_id' => $pLoss, 'physical_qty' => '9']],
        ])->assertForbidden();

        // Admin cari produk & posting opname
        $this->actingAs($admin)->getJson(route('v2.stock-opnames.products', ['q' => 'Semen']))
            ->assertOk()
            ->assertJsonPath('results.0.id', $pLoss);

        $payload = [
            'opname_date' => now()->toDateString(),
            'notes' => 'Opname bulanan gudang sintetik',
            'idempotency_key' => 'opn-admin-001',
            'items' => [
                ['product_id' => $pLoss, 'physical_qty' => '8.5000', 'expected_stock' => '10.0000'],
                ['product_id' => $pGain, 'physical_qty' => '6.0000', 'expected_stock' => '5.0000'],
                ['product_id' => $pSame, 'physical_qty' => '8.0000', 'expected_stock' => '8.0000'],
            ],
        ];
        $res = $this->postJson(route('v2.stock-opnames.store'), $payload)->assertOk();
        $res->assertJsonPath('loss_total', '75000.00')
            ->assertJsonPath('gain_total', '100000.00')
            ->assertJsonPath('item_count', 3);

        // Idempotent replay mengembalikan hasil yang sama tanpa jurnal kedua
        $this->postJson(route('v2.stock-opnames.store'), $payload)
            ->assertOk()
            ->assertJsonPath('replayed', true)
            ->assertJsonPath('opname_id', $res->json('opname_id'));
        $this->assertDatabaseCount('v2_stock_opnames', 1);
        $this->assertDatabaseCount('journal_entries', 2);

        // Payload berbeda dengan key sama ditolak
        $this->postJson(route('v2.stock-opnames.store'), [
            'opname_date' => now()->toDateString(),
            'idempotency_key' => 'opn-admin-001',
            'items' => [['product_id' => $pLoss, 'physical_qty' => '7', 'expected_stock' => '10.0000']],
        ])->assertUnprocessable();

        $this->assertDatabaseHas('products', ['id' => $pLoss, 'stock_quantity' => '8.5000']);
        $this->assertDatabaseHas('products', ['id' => $pGain, 'stock_quantity' => '6.0000']);
        // Mutasi stok hanya untuk barang yang berubah
        $this->assertDatabaseCount('stock_movements', 2);

        $history = $this->getJson(route('v2.tables.show', 'stock-opnames'))->assertOk()->json('html');
        $this->assertStringContainsString('class="btn-action-pill popup-action" data-open-popup="opname-detail-', $history);

        $journalId = $res->json('journal_entry_id');
        $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $journalId)->get()->keyBy('account_code');
        $this->assertSame('75000.00', (string) Money::value((string) $lines['5201']->debit));
        $this->assertSame('100000.00', (string) Money::value((string) $lines['7101']->credit));
        $this->assertSame('25000.00', (string) Money::value((string) $lines['1301']->debit)); // net +25.000

        $reports = app(AccountingReportService::class);
        $this->assertTrue($reports->generateBalanceSheet()['is_balanced']);

        // Tolak jumlah negatif
        $this->postJson(route('v2.stock-opnames.store'), [
            'opname_date' => now()->toDateString(),
            'idempotency_key' => 'opn-neg-test',
            'items' => [['product_id' => $pLoss, 'physical_qty' => '-1']],
        ])->assertUnprocessable();
    }

    public function test_stock_opname_rejects_backdated_entry_before_existing_movement(): void
    {
        $this->seedAccounts();
        $admin = $this->actor('admin');
        $product = $this->product('SYN-OPN-BD', 'Besi Sintetis', '10.0000', '10000.0000', '12000.00');
        DB::table('stock_movements')->insert([
            'product_id' => $product,
            'reference_type' => 'v2_opening_balance',
            'reference_id' => 1,
            'quantity_delta' => '10.0000',
            'balance_after' => '10.0000',
            'notes' => 'Saldo awal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        app(StockOpnames::class)->record($admin->id, [
            'opname_date' => now()->subDay()->toDateString(),
            'idempotency_key' => 'opn-backdate-test',
            'items' => [['product_id' => $product, 'physical_qty' => '9', 'expected_stock' => '10.0000']],
        ]);
    }

    public function test_stock_opname_rejects_missing_or_stale_stock_counts_without_writing(): void
    {
        $this->seedAccounts();
        $admin = $this->actor('admin');
        $product = $this->product('SYN-OPN-STALE', 'Besi Sintetis', '10.0000', '10000.0000', '12000.00');
        $snapshot = $this->actingAs($admin)->getJson(route('v2.stock-opnames.products', ['q' => 'SYN-OPN-STALE']))
            ->assertOk()->json('results.0.stock');
        DB::table('products')->where('id', $product)->update(['stock_quantity' => '8.0000']);
        $item = ['product_id' => $product, 'physical_qty' => '10.0000'];

        foreach ([$item, $item + ['expected_stock' => $snapshot]] as $count) {
            $this->postJson(route('v2.stock-opnames.store'), [
                'opname_date' => now()->toDateString(),
                'idempotency_key' => 'opn-stale-test',
                'items' => [$count],
            ])->assertUnprocessable()->assertJsonValidationErrors('stock_opname');
        }
        $this->assertDatabaseHas('products', ['id' => $product, 'stock_quantity' => '8.0000']);
        $this->assertDatabaseCount('v2_stock_opnames', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_partial_return_cogs_allocation_keeps_the_full_line_cost_for_the_last_unit(): void
    {
        $lineCogs = Money::value('1000.02');
        $originalQty = Money::value('4', 4);
        $returnedQty = Money::value('0', 4);
        $reversed = Money::value('0', 2);

        foreach (range(1, 4) as $unit) {
            $nextQty = $returnedQty->plus('1');
            $portion = Money::portionAt($lineCogs, $originalQty, $nextQty)
                ->minus(Money::portionAt($lineCogs, $originalQty, $returnedQty))
                ->toScale(2);
            $this->assertFalse($portion->isNegative());
            $reversed = $reversed->plus($portion);
            $returnedQty = $nextQty;
        }

        $this->assertSame('1000.02', (string) $reversed);
    }

    public function test_partial_return_rounding_preserves_the_last_cent_for_the_final_quantity(): void
    {
        $value = Money::value('0.03');
        $originalQty = Money::value('5', 4);

        $this->assertSame('0.00', (string) Money::returnPortion($value, $originalQty, Money::value('1', 4), Money::value('0')));
        $this->assertSame('0.01', (string) Money::returnPortion($value, $originalQty, Money::value('2', 4), Money::value('0')));
        $this->assertSame('0.01', (string) Money::returnPortion($value, $originalQty, Money::value('4', 4), Money::value('0.01')));
        $this->assertSame('0.01', (string) Money::returnPortion($value, $originalQty, $originalQty, Money::value('0.02')));
    }

    public function test_sales_return_reduces_receivable_refunds_excess_cash_and_posts_contra_revenue(): void
    {
        $this->seedAccounts(true);
        $this->fundBooks();
        $admin = $this->actor('admin');
        $cashier = $this->actor('cashier');

        $customerId = (int) DB::table('v2_parties')->insertGetId([
            'kind' => 'customer', 'code' => 'CUS-RET-01', 'name' => 'Kontraktor Sintetis', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = $this->product('SYN-SR-01', 'Cat Tembok Sintetis', '20.0000', '60000.0000', '100000.00');
        Ledger::post('opening', 2, 'Stok awal cat', [
            ['account_code' => '1301', 'debit' => '1200000.00', 'credit' => '0.00'],
            ['account_code' => '3101', 'debit' => '0.00', 'credit' => '1200000.00'],
        ]);

        $ops = app(Operations::class);
        $shiftId = $ops->openShift($admin->id, '200000.00', 'admin');

        // Jual 4 kaleng @ 100.000 = 400.000 kredit dengan DP tunai 250.000 -> sisa piutang 150.000
        $sale = $ops->checkout($cashier->id, [
            'items' => [['product_id' => $productId, 'quantity' => '4']],
            'customer_id' => $customerId,
            'payment_method' => 'credit',
            'deposit_method' => 'cash',
            'paid_amount' => '250000.00',
            'delivery_fee' => '0',
            'fulfillment' => 'pickup',
            'idempotency_key' => 'sale-for-return-001',
        ]);
        $saleItemId = (int) DB::table('v2_sale_items')->where('sale_id', $sale['sale_id'])->value('id');

        // Kasir tidak boleh memproses retur penjualan
        $this->actingAs($cashier)->postJson(route('v2.sales.returns.store', $sale['sale_id']), [
            'return_date' => now()->toDateString(),
            'reason' => 'Kasir mencoba retur tanpa hak akses',
            'refund_method' => 'cash',
            'idempotency_key' => 'sr-cashier-denied',
            'items' => [['sale_item_id' => $saleItemId, 'quantity' => '1']],
        ])->assertForbidden();

        // Admin retur 2 kaleng = 200.000: 150.000 memotong piutang, 50.000 refund tunai dari laci shift
        $res = $this->actingAs($admin)->postJson(route('v2.sales.returns.store', $sale['sale_id']), [
            'return_date' => now()->toDateString(),
            'reason' => 'Kelebihan order proyek pelanggan',
            'refund_method' => 'cash',
            'idempotency_key' => 'sr-admin-001',
            'items' => [['sale_item_id' => $saleItemId, 'quantity' => '2']],
        ])->assertOk();

        $res->assertJsonPath('total_amount', '200000.00')
            ->assertJsonPath('ar_reduction', '150000.00')
            ->assertJsonPath('refund_amount', '50000.00')
            ->assertJsonPath('refund_method', 'cash');

        // Idempotent replay
        $this->postJson(route('v2.sales.returns.store', $sale['sale_id']), [
            'return_date' => now()->toDateString(),
            'reason' => 'Kelebihan order proyek pelanggan',
            'refund_method' => 'cash',
            'idempotency_key' => 'sr-admin-001',
            'items' => [['sale_item_id' => $saleItemId, 'quantity' => '2']],
        ])->assertOk()->assertJsonPath('replayed', true);

        // Stok kembali dari 16 -> 18, invoice dari 400k/250k -> 200k/200k (lunas)
        $this->assertDatabaseHas('products', ['id' => $productId, 'stock_quantity' => '18.0000']);
        $this->assertDatabaseHas('v2_invoices', [
            'id' => $sale['invoice_id'],
            'amount' => '200000.00',
            'paid' => '200000.00',
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('v2_sales', ['id' => $sale['sale_id'], 'status' => 'paid']);
        $this->assertDatabaseHas('v2_shift_movements', [
            'shift_id' => $shiftId,
            'direction' => 'out',
            'reference_type' => 'v2_sale_return',
            'amount' => '50000.00',
        ]);

        // Jurnal retur: DR 4104 200k, CR 1103 150k, CR 1101 50k, DR 1301 120k, CR 5101 120k
        $journalLines = DB::table('journal_entry_lines')->where('journal_entry_id', $res->json('journal_entry_id'))->get()->keyBy('account_code');
        $this->assertSame('200000.00', (string) Money::value((string) $journalLines['4104']->debit));
        $this->assertSame('150000.00', (string) Money::value((string) $journalLines['1103']->credit));
        $this->assertSame('50000.00', (string) Money::value((string) $journalLines['1101']->credit));
        $this->assertSame('120000.00', (string) Money::value((string) $journalLines['1301']->debit));
        $this->assertSame('120000.00', (string) Money::value((string) $journalLines['5101']->credit));

        // Melebihi sisa retur (sisa 2, coba 3) ditolak
        $this->postJson(route('v2.sales.returns.store', $sale['sale_id']), [
            'return_date' => now()->toDateString(),
            'reason' => 'Coba retur melebihi sisa penjualan',
            'refund_method' => 'cash',
            'idempotency_key' => 'sr-over-qty',
            'items' => [['sale_item_id' => $saleItemId, 'quantity' => '3']],
        ])->assertUnprocessable();

        $reports = app(AccountingReportService::class);
        $pl = $reports->generateProfitAndLoss();
        $bs = $reports->generateBalanceSheet();
        $cf = $reports->generateCashFlow(now()->toDateString(), now()->toDateString());

        $this->assertSame('200000.00', $pl['total_revenue']); // 400k jual - 200k retur
        $this->assertSame('120000.00', $pl['total_cogs']);    // 240k HPP - 120k kembali
        $this->assertTrue($bs['is_balanced']);
        $this->assertTrue($cf['is_reconciled']);
        $outflows = collect($cf['operating_activities']['outflows'])->keyBy('label');
        $this->assertSame('50000.00', (string) ($outflows['Pengembalian Dana Retur Penjualan']['amount'] ?? '0.00'));

        // Detail penjualan memuat form retur untuk admin tetapi tidak untuk kasir
        $adminDetailHtml = (string) $this->actingAs($admin)->getJson(route('v2.sales.detail', $sale['sale_id']))
            ->assertOk()
            ->json('html');
        $this->assertStringContainsString('id="sale-return-form"', $adminDetailHtml);
        $this->assertStringContainsString('data-open-sale-return', $adminDetailHtml);

        $cashierDetailHtml = (string) $this->actingAs($cashier)->getJson(route('v2.sales.detail', $sale['sale_id']))
            ->assertOk()
            ->json('html');
        $this->assertStringNotContainsString('id="sale-return-form"', $cashierDetailHtml);
        $this->assertStringNotContainsString('data-open-sale-return', $cashierDetailHtml);
    }

    public function test_sales_return_falls_back_to_4101_when_4104_is_absent_and_blocks_pending_payment(): void
    {
        $this->seedAccounts(false); // Tanpa 4104
        $this->fundBooks();
        $admin = $this->actor('admin');
        $customerId = (int) DB::table('v2_parties')->insertGetId([
            'kind' => 'customer', 'code' => 'CUS-RET-02', 'name' => 'Pembeli Transfer', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = $this->product('SYN-SR-02', 'Keramik Sintetis', '10.0000', '50000.0000', '80000.00');
        Ledger::post('opening', 3, 'Stok awal keramik', [
            ['account_code' => '1301', 'debit' => '500000.00', 'credit' => '0.00'],
            ['account_code' => '3101', 'debit' => '0.00', 'credit' => '500000.00'],
        ]);

        $ops = app(Operations::class);
        $ops->openShift($admin->id, '100000.00', 'admin');
        $sale = $ops->checkout($admin->id, [
            'items' => [['product_id' => $productId, 'quantity' => '2']],
            'customer_id' => $customerId,
            'payment_method' => 'transfer',
            'paid_amount' => '160000.00',
            'delivery_fee' => '0',
            'fulfillment' => 'pickup',
            'idempotency_key' => 'sale-transfer-pending-ret',
        ]);

        // Pembayaran transfer masih pending -> retur harus ditolak
        try {
            app(SalesReturns::class)->store($admin->id, $sale['sale_id'], [
                'return_date' => now()->toDateString(),
                'reason' => 'Transfer belum diverifikasi',
                'idempotency_key' => 'sr-pending-block',
                'items' => [['product_id' => $productId, 'quantity' => '1']],
            ]);
            $this->fail('Seharusnya ditolak saat transfer masih menunggu verifikasi.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('menunggu verifikasi', $e->getMessage());
        }

        // Tolak transfer agar tagihan kembali menjadi piutang terbuka, lalu retur 1 unit ke 4101
        $paymentId = (int) DB::table('v2_payments')->where('invoice_id', $sale['invoice_id'])->value('id');
        $ops->rejectPayment($admin->id, $paymentId, 'Transfer dibatalkan pelanggan');

        $ret = app(SalesReturns::class)->store($admin->id, $sale['sale_id'], [
            'return_date' => now()->toDateString(),
            'reason' => 'Barang dikembalikan sebelum bayar',
            'idempotency_key' => 'sr-fallback-4101',
            'items' => [['product_id' => $productId, 'quantity' => '1']],
        ]);
        $this->assertSame('credit_adjustment', $ret['refund_method']);
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $ret['journal_entry_id'],
            'account_code' => '4101',
            'debit' => '80000.00',
        ]);
    }

    public function test_purchase_return_reduces_stock_payable_or_refunds_bank_and_keeps_books_balanced(): void
    {
        $this->seedAccounts();
        $this->fundBooks();
        $admin = $this->actor('admin');
        $cashier = $this->actor('cashier');

        $supplierId = (int) DB::table('v2_parties')->insertGetId([
            'kind' => 'supplier', 'code' => 'SUP-RET-01', 'name' => 'Distributor Sintetis', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = $this->product('SYN-PR-01', 'Besi Beton Sintetis', '0.0000', '50000.0000', '70000.00');
        DB::table('v2_product_suppliers')->insert([
            'product_id' => $productId, 'supplier_id' => $supplierId, 'is_primary' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Penerimaan 10 batang @ 50.000 = 500.000
        $received = app(SupplyTrips::class)->receive($admin->id, [
            'supplier_id' => $supplierId,
            'receipt_date' => now()->toDateString(),
            'idempotency_key' => 'rcv-for-return-001',
            'items' => [['product_id' => $productId, 'quantity' => '10', 'unit_cost' => '50000.00']],
        ]);
        $receiptId = $received['receipt_id'];
        $invoiceId = $received['invoice_id'];
        $receiptItemId = (int) DB::table('inventory_receipt_items')->where('inventory_receipt_id', $receiptId)->value('id');

        // Bayar 350.000 ke supplier via Bank 1102 -> sisa utang = 150.000
        app(Operations::class)->collect($admin->id, $invoiceId, '350000.00', 'transfer', 'pay-sup-ret-001');

        // Kasir ditolak (403)
        $this->actingAs($cashier)->getJson(route('v2.receipts.returnable', $receiptId))->assertForbidden();
        $this->actingAs($cashier)->postJson(route('v2.receipts.returns.store', $receiptId), [
            'return_date' => now()->toDateString(),
            'reason' => 'Kasir tidak berhak retur supplier',
            'refund_method' => 'transfer',
            'bank_account_code' => '1102',
            'idempotency_key' => 'pr-cashier-denied',
            'items' => [['receipt_item_id' => $receiptItemId, 'quantity' => '2']],
        ])->assertForbidden();

        // Admin retur 4 batang = 200.000: 150.000 melunasi sisa utang, 50.000 dikembalikan supplier ke Bank 1102
        $res = $this->actingAs($admin)->postJson(route('v2.receipts.returns.store', $receiptId), [
            'return_date' => now()->toDateString(),
            'reason' => 'Material bengkok tidak lolos inspeksi',
            'refund_method' => 'transfer',
            'bank_account_code' => '1102',
            'idempotency_key' => 'pr-admin-001',
            'items' => [['receipt_item_id' => $receiptItemId, 'quantity' => '4']],
        ])->assertOk();

        $res->assertJsonPath('total_amount', '200000.00')
            ->assertJsonPath('ap_reduction', '150000.00')
            ->assertJsonPath('refund_amount', '50000.00')
            ->assertJsonPath('refund_method', 'transfer');

        $this->assertDatabaseHas('products', ['id' => $productId, 'stock_quantity' => '6.0000']);
        $this->assertDatabaseHas('v2_invoices', [
            'id' => $invoiceId,
            'amount' => '300000.00',
            'paid' => '300000.00',
            'status' => 'paid',
        ]);

        // Jual 4 batang dari stok sisa 6 -> tersisa 2 di gudang. Coba retur 3 batang (> stok gudang 2) ditolak
        app(Operations::class)->openShift($admin->id, '50000.00', 'admin');
        app(Operations::class)->checkout($admin->id, [
            'items' => [['product_id' => $productId, 'quantity' => '4']],
            'payment_method' => 'cash',
            'paid_amount' => '280000.00',
            'delivery_fee' => '0',
            'fulfillment' => 'pickup',
            'idempotency_key' => 'sell-some-before-return-001',
        ]);

        try {
            app(PurchaseReturns::class)->store($admin->id, $receiptId, [
                'return_date' => now()->toDateString(),
                'reason' => 'Stok tidak cukup di gudang',
                'refund_method' => 'transfer',
                'bank_account_code' => '1102',
                'idempotency_key' => 'pr-over-warehouse-stock',
                'items' => [['receipt_item_id' => $receiptItemId, 'quantity' => '3']],
            ]);
            $this->fail('Seharusnya menolak retur melebihi stok gudang tersisa.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('tidak mencukupi untuk retur', $e->getMessage());
        }

        // Retur 2 batang sisa sampai stok habis 0 -> cost_price tetap aman, Neraca & Arus Kas seimbang
        app(PurchaseReturns::class)->store($admin->id, $receiptId, [
            'return_date' => now()->toDateString(),
            'reason' => 'Sisa barang juga dikembalikan',
            'refund_method' => 'main',
            'idempotency_key' => 'pr-remaining-two',
            'items' => [['receipt_item_id' => $receiptItemId, 'quantity' => '2']],
        ]);

        $this->assertDatabaseHas('products', ['id' => $productId, 'stock_quantity' => '0.0000', 'cost_price' => '50000.0000']);

        $reports = app(AccountingReportService::class);
        $this->assertTrue($reports->generateBalanceSheet()['is_balanced']);
        $cf = $reports->generateCashFlow(now()->toDateString(), now()->toDateString());
        $this->assertTrue($cf['is_reconciled']);
        $inflows = collect($cf['operating_activities']['inflows'])->keyBy('label');
        $this->assertSame('150000.00', (string) ($inflows['Pengembalian Dana Retur Pembelian']['amount'] ?? '0.00'));
    }
}
