<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\ReportsDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class V2ReportsDocumentsTest extends TestCase
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

    private function shift(int $user, string $opening, string $status = 'open', array $counts = []): int
    {
        return DB::table('v2_shifts')->insertGetId([
            'user_id' => $user,
            'open_user_id' => $status === 'open' ? $user : null,
            'source' => 'admin',
            'opening_cash' => $opening,
            'carry_amount' => '0.00',
            'admin_amount' => $opening,
            'expected_cash' => $counts['expected'] ?? null,
            'counted_cash' => $counts['counted'] ?? null,
            'difference' => $counts['difference'] ?? null,
            'status' => $status,
            'opened_at' => '2026-10-30 08:00:00',
            'closed_at' => $status === 'open' ? null : '2026-10-30 17:00:00',
            'created_at' => '2026-10-30 08:00:00',
            'updated_at' => '2026-10-30 08:00:00',
        ]);
    }

    private function product(string $sku, string $stock = '10.0000'): int
    {
        return DB::table('products')->insertGetId([
            'sku' => $sku, 'name' => 'Produk Sintetis '.$sku, 'unit' => 'sak', 'category' => 'Umum',
            'cost_price' => '600.0000', 'selling_price' => '500.00', 'stock_quantity' => $stock,
            'min_stock' => '1.0000', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function sale(int $user, int $shift, array $amounts, string $method = 'cash', string $fulfillment = 'pickup'): int
    {
        return DB::table('v2_sales')->insertGetId($amounts + [
            'number' => 'SYN-'.$shift.'-'.bin2hex(random_bytes(3)),
            'user_id' => $user,
            'shift_id' => $shift,
            'cogs' => '850.00',
            'tendered' => '1000.00',
            'change_amount' => '35.00',
            'request_hash' => str_repeat('a', 64),
            'payment_method' => $method,
            'status' => 'paid',
            'fulfillment' => $fulfillment,
            'idempotency_key' => 'syn-'.bin2hex(random_bytes(6)),
            'created_at' => '2026-10-30 10:00:00',
            'updated_at' => '2026-10-30 10:00:00',
        ]);
    }

    private function report(): ReportsDocuments
    {
        return app(ReportsDocuments::class);
    }

    private function assertForbidden(callable $callback): void
    {
        try {
            $callback();
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());

            return;
        }
        $this->fail('Akses 403 diharapkan.');
    }

    public function test_admin_daily_separates_revenue_cash_and_bank(): void
    {
        $admin = $this->actor('admin');
        $cashierA = $this->actor('cashier');
        $cashierB = $this->actor('cashier');

        // Shift A ditutup dengan selisih -5.00; shift B masih terbuka.
        $shiftA = $this->shift($cashierA->id, '500.00', 'closed', [
            'expected' => '1495.00', 'counted' => '1490.00', 'difference' => '-5.00',
        ]);
        $shiftB = $this->shift($cashierB->id, '100.00');

        $product = $this->product('SYN-RPT-001');
        $customer = DB::table('v2_parties')->insertGetId([
            'kind' => 'customer', 'code' => 'CUS-SYN-1', 'name' => 'Pelanggan Sintetis',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Sale A: subtotal 1000 (sebelum diskon baris 50), diskon faktur 25, ongkir 40 -> total 965.
        $saleA = $this->sale($cashierA->id, $shiftA, [
            'customer_id' => $customer,
            'subtotal' => '1000.00', 'line_discount' => '50.00', 'invoice_discount' => '25.00',
            'delivery_fee' => '40.00', 'total' => '965.00',
        ]);
        DB::table('v2_sale_items')->insert([
            'sale_id' => $saleA, 'product_id' => $product, 'name' => 'Produk Sintetis SYN-RPT-001',
            'quantity' => '2.0000', 'unit_price' => '500.00', 'cost_price' => '425.0000',
            'discount' => '50.00', 'subtotal' => '950.00', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Sale B: transfer kredit 200 tanpa diskon/ongkir, shift kasir B.
        $saleB = $this->sale($cashierB->id, $shiftB, [
            'subtotal' => '200.00', 'line_discount' => '0.00', 'invoice_discount' => '0.00',
            'delivery_fee' => '0.00', 'total' => '200.00',
        ], 'transfer');

        // Uang laci shift A: penjualan tunai 965 + penagihan 50 - keluar 20.
        DB::table('v2_shift_movements')->insert([
            ['shift_id' => $shiftA, 'direction' => 'in', 'kind' => 'sale', 'amount' => '965.00', 'reference_type' => 'v2_sale', 'reference_id' => $saleA, 'created_at' => now(), 'updated_at' => now()],
            ['shift_id' => $shiftA, 'direction' => 'in', 'kind' => 'collection', 'amount' => '50.00', 'reference_type' => 'v2_payment', 'reference_id' => 9001, 'created_at' => now(), 'updated_at' => now()],
            ['shift_id' => $shiftA, 'direction' => 'out', 'kind' => 'adjustment', 'amount' => '20.00', 'reference_type' => 'v2_trip', 'reference_id' => 9002, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Transfer piutang: satu pending 100, satu posted 60.
        $invoice = DB::table('v2_invoices')->insertGetId([
            'direction' => 'receivable', 'party_id' => $customer, 'sale_id' => $saleB,
            'number' => 'INV-SYN-1', 'amount' => '200.00', 'paid' => '60.00', 'status' => 'open',
            'created_at' => '2026-10-30 11:00:00', 'updated_at' => '2026-10-30 11:00:00',
        ]);
        $postedJournal = DB::table('journal_entries')->insertGetId(['reference_type'=>'v2_payment','reference_id'=>1,'description'=>'Pembayaran sintetis','transaction_date'=>'2026-10-31','created_at'=>now(),'updated_at'=>now()]);
        foreach ([['100.00', 'pending', $cashierB->id], ['60.00', 'posted', $cashierB->id]] as $i => [$amount, $status, $user]) {
            DB::table('v2_payments')->insert([
                'invoice_id' => $invoice, 'shift_id' => $shiftB, 'amount' => $amount, 'method' => 'transfer',
                'status' => $status, 'requested_by' => $user, 'approved_by' => $status === 'posted' ? $admin->id : null,
                'idempotency_key' => 'syn-pay-'.$i, 'request_hash' => str_repeat('b', 64), 'journal_entry_id' => $status === 'posted' ? $postedJournal : null,
                'created_at' => '2026-10-30 11:00:00', 'updated_at' => '2026-10-30 11:00:00',
            ]);
        }

        $report = $this->report()->daily($admin->id, '2026-10-30');

        $this->assertSame('all', $report['scope']);
        $this->assertSame(2, $report['sales_count']);
        $this->assertSame('1200.00', $report['revenue']['goods_gross']);
        $this->assertSame('75.00', $report['revenue']['discount']);
        $this->assertSame('1125.00', $report['revenue']['goods_net']);
        $this->assertSame('40.00', $report['revenue']['delivery']);
        $this->assertSame('1165.00', $report['revenue']['total_sales']);

        $this->assertSame('600.00', $report['cash']['opening_float']);
        $this->assertSame('1015.00', $report['cash']['collections']);
        $this->assertSame('20.00', $report['cash']['out']);
        $this->assertSame('1595.00', $report['cash']['expected']);
        $this->assertSame('1490.00', $report['cash']['counted']);
        $this->assertSame('-5.00', $report['cash']['difference']);

        $this->assertSame('0.00', $report['bank']['posted']);
        $this->assertSame('100.00', $report['bank']['pending']);
        $this->assertSame(2, $report['shifts']['count']);
        $this->assertSame(1, $report['shifts']['open']);
        $this->assertSame(1, $report['shifts']['closed']);
        $this->assertSame('0.00',$this->report()->daily($admin->id,'2026-10-30')['bank']['posted']);
        $this->assertSame('60.00',$this->report()->daily($admin->id,'2026-10-31')['bank']['posted']);
    }

    public function test_cashier_sees_only_own_shift_and_is_denied_other_documents(): void
    {
        $admin = $this->actor('admin');
        $cashierA = $this->actor('cashier');
        $cashierB = $this->actor('cashier');

        $shiftA = $this->shift($cashierA->id, '500.00', 'closed', [
            'expected' => '1495.00', 'counted' => '1490.00', 'difference' => '-5.00',
        ]);
        $shiftB = $this->shift($cashierB->id, '100.00');

        $saleA = $this->sale($cashierA->id, $shiftA, [
            'subtotal' => '1000.00', 'line_discount' => '50.00', 'invoice_discount' => '25.00',
            'delivery_fee' => '40.00', 'total' => '965.00',
        ]);
        $saleB = $this->sale($cashierB->id, $shiftB, [
            'subtotal' => '200.00', 'line_discount' => '0.00', 'invoice_discount' => '0.00',
            'delivery_fee' => '0.00', 'total' => '200.00',
        ], 'transfer');

        DB::table('v2_shift_movements')->insert([
            ['shift_id' => $shiftA, 'direction' => 'in', 'kind' => 'sale', 'amount' => '965.00', 'reference_type' => 'v2_sale', 'reference_id' => $saleA, 'created_at' => now(), 'updated_at' => now()],
            ['shift_id' => $shiftA, 'direction' => 'in', 'kind' => 'collection', 'amount' => '50.00', 'reference_type' => 'v2_payment', 'reference_id' => 9101, 'created_at' => now(), 'updated_at' => now()],
            ['shift_id' => $shiftA, 'direction' => 'out', 'kind' => 'adjustment', 'amount' => '20.00', 'reference_type' => 'v2_trip', 'reference_id' => 9102, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $own = $this->report()->daily($cashierA->id, '2026-10-30');

        $this->assertSame('own', $own['scope']);
        $this->assertSame(1, $own['sales_count']);
        $this->assertSame('965.00', $own['revenue']['total_sales']);
        $this->assertSame('925.00', $own['revenue']['goods_net']);
        $this->assertSame('40.00', $own['revenue']['delivery']);
        $this->assertSame('500.00', $own['cash']['opening_float']);
        $this->assertSame('1015.00', $own['cash']['collections']);
        $this->assertSame('1495.00', $own['cash']['expected']);
        $this->assertSame('0.00', $own['bank']['posted']);
        $this->assertSame('0.00', $own['bank']['pending']);
        $this->assertSame(1, $own['shifts']['count']);

        // Kasir A tidak boleh membaca nota kasir B; kasir B tidak boleh membaca nota A.
        $this->assertForbidden(fn () => $this->report()->document($cashierA->id, $saleB));
        $this->assertForbidden(fn () => $this->report()->document($cashierB->id, $saleA));
        $this->assertSame($saleB, $this->report()->document($admin->id, $saleB)['sale']['id']);
    }

    public function test_documents_are_immutable_reads_and_delivery_requires_data(): void
    {
        $admin = $this->actor('admin');
        $cashier = $this->actor('cashier');
        $shift = $this->shift($cashier->id, '500.00');
        $product = $this->product('SYN-RPT-DOC');
        $customer = DB::table('v2_parties')->insertGetId([
            'kind' => 'customer', 'code' => 'CUS-SYN-2', 'name' => 'Pelanggan Dokumen',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $sale = $this->sale($cashier->id, $shift, [
            'customer_id' => $customer,
            'subtotal' => '1000.00', 'line_discount' => '50.00', 'invoice_discount' => '25.00',
            'delivery_fee' => '40.00', 'total' => '965.00',
        ], 'cash', 'delivery');

        DB::table('v2_sale_items')->insert([
            'sale_id' => $sale, 'product_id' => $product, 'name' => 'Produk Sintetis SYN-RPT-DOC',
            'quantity' => '2.0000', 'unit_price' => '500.00', 'cost_price' => '425.0000',
            'discount' => '50.00', 'subtotal' => '950.00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $delivery = DB::table('v2_deliveries')->insertGetId([
            'sale_id' => $sale, 'number' => 'SJ-SYN-1', 'driver_name' => 'Kurir Sintetis',
            'destination' => 'Alamat Sintetis', 'status' => 'prepared',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Sale tanpa pengiriman: dokumen delivery harus gagal jelas (tanpa mengubah snapshot read-only).
        $plain = $this->sale($cashier->id, $shift, [
            'subtotal' => '100.00', 'line_discount' => '0.00', 'invoice_discount' => '0.00',
            'delivery_fee' => '0.00', 'total' => '100.00',
        ]);

        $stockBefore = (string) DB::table('products')->where('id', $product)->value('stock_quantity');
        $snapshot = [
            'sales' => DB::table('v2_sales')->count(),
            'items' => DB::table('v2_sale_items')->count(),
            'journals' => DB::table('journal_entries')->count(),
            'journal_lines' => DB::table('journal_entry_lines')->count(),
            'movements' => DB::table('v2_shift_movements')->count(),
        ];

        $receipt = $this->report()->document($cashier->id, $sale);
        $this->assertSame('receipt', $receipt['kind']);
        $this->assertSame('965.00', $receipt['sale']['total']);
        $this->assertSame('1000.00', $receipt['sale']['subtotal']);
        $this->assertSame('50.00', $receipt['sale']['line_discount']);
        $this->assertSame('25.00', $receipt['sale']['invoice_discount']);
        $this->assertSame('40.00', $receipt['sale']['delivery_fee']);
        $this->assertSame('925.00', $receipt['totals']['goods_net']);
        $this->assertSame('40.00', $receipt['totals']['delivery']);
        $this->assertSame('965.00', $receipt['totals']['total_sales']);
        $this->assertCount(1, $receipt['items']);
        $this->assertSame('2.0000', $receipt['items'][0]['quantity']);
        $this->assertSame('500.00', $receipt['items'][0]['unit_price']);
        $this->assertSame('50.00', $receipt['items'][0]['discount']);
        $this->assertSame('950.00', $receipt['items'][0]['subtotal']);
        $this->assertSame('Pelanggan Dokumen', $receipt['customer']['name']);

        $suratJalan = $this->report()->document($admin->id, $sale, 'delivery');
        $this->assertSame('delivery', $suratJalan['kind']);
        $this->assertSame('SJ-SYN-1', $suratJalan['delivery']['number']);
        $this->assertSame('Alamat Sintetis', $suratJalan['delivery']['destination']);
        $this->assertSame($delivery, $suratJalan['delivery']['id']);

        // Sale tanpa pengiriman dibuat sebelum snapshot: delivery harus gagal jelas.
        try {
            $this->report()->document($cashier->id, $plain, 'delivery');
            $this->fail('Dokumen delivery tanpa data pengiriman harus gagal.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('documents', $e->errors());
        }

        // Dokumen read-only: stok, jurnal, movement, dan baris penjualan tidak berubah.
        $this->assertSame($stockBefore, (string) DB::table('products')->where('id', $product)->value('stock_quantity'));
        $this->assertSame($snapshot['sales'], DB::table('v2_sales')->count());
        $this->assertSame($snapshot['items'], DB::table('v2_sale_items')->count());
        $this->assertSame($snapshot['journals'], DB::table('journal_entries')->count());
        $this->assertSame($snapshot['journal_lines'], DB::table('journal_entry_lines')->count());
        $this->assertSame($snapshot['movements'], DB::table('v2_shift_movements')->count());
    }
}