<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2PaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('V2 tests require isolated SQLite :memory:.');
        }
    }

    private function actor(string $role, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        DB::table('v2_user_roles')->insert([
            'user_id' => $user->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function seedAccounts(): void
    {
        foreach ([
            ['1101', 'Kas', 'asset', 'debit'],
            ['1102', 'Bank', 'asset', 'debit'],
            ['2101', 'Utang Usaha', 'liability', 'credit'],
            ['6101', 'Operasional Toko', 'expense', 'debit'],
            ['6102', 'Listrik dan Air', 'expense', 'debit'],
        ] as [$code, $name, $type, $normal]) {
            DB::table('chart_of_accounts')->updateOrInsert(
                ['account_code' => $code],
                [
                    'account_name' => $name,
                    'account_type' => $type,
                    'normal_balance' => $normal,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function test_expenses_index_paginates_beyond_ten_records_and_preserves_filters(): void
    {
        $this->seedAccounts();
        $admin = $this->actor('admin', 'Admin Sintetis Pengeluaran');

        for ($i = 1; $i <= 15; $i++) {
            DB::table('expenses')->insert([
                'expense_number' => sprintf('EXP-SYN-%04d', $i),
                'expense_date' => now()->subDays(16 - $i)->toDateString(),
                'category' => $i <= 12 ? '6101' : '6102',
                'amount' => '10000.00',
                'payment_method' => 'cash',
                'description' => 'Biaya Sintetis '.$i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $page1 = $this->actingAs($admin)->get(route('expenses.index'));
        $page1->assertOk()
            ->assertSee('Menampilkan 1–10 dari 15 pengeluaran')
            ->assertSee('EXP-SYN-0015')
            ->assertDontSee('EXP-SYN-0001')
            ->assertSee('Halaman 1 / 2');

        $page2 = $this->actingAs($admin)->get(route('expenses.index', ['page' => 2, 'partial' => 1]));
        $page2->assertOk()
            ->assertSee('EXP-SYN-0001')
            ->assertDontSee('EXP-SYN-0015')
            ->assertDontSee('partial=1');

        $this->actingAs($admin)->get(route('expenses.index', ['per_page' => 25]))
            ->assertOk()
            ->assertSee('EXP-SYN-0001')
            ->assertSee('EXP-SYN-0015');

        $this->actingAs($admin)->get(route('expenses.index', ['category' => '6102']))
            ->assertOk()
            ->assertSee('Menampilkan 1–3 dari 3 pengeluaran')
            ->assertSee('EXP-SYN-0013')
            ->assertDontSee('EXP-SYN-0012');
    }

    public function test_payments_table_never_truncates_pending_queue_behind_posted_payments(): void
    {
        $admin = $this->actor('admin', 'Admin Sintetis Bayar');
        $cashierA = $this->actor('cashier', 'Kasir A Sintetis');
        $cashierB = $this->actor('cashier', 'Kasir B Sintetis');

        $customerId = DB::table('v2_parties')->insertGetId([
            'kind' => 'customer', 'code' => 'CUS-PG-1', 'name' => 'Pelanggan Paging',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $invoiceId = DB::table('v2_invoices')->insertGetId([
            'direction' => 'receivable', 'party_id' => $customerId, 'number' => 'AR-PG-0001',
            'amount' => '1000000.00', 'paid' => '0.00', 'status' => 'open',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // 1 pembayaran transfer pending lama (ID paling kecil).
        DB::table('v2_payments')->insert([
            'invoice_id' => $invoiceId, 'amount' => '75000.00', 'method' => 'transfer',
            'status' => 'pending', 'requested_by' => $cashierA->id, 'reference' => 'PENDING-LAMA-001',
            'idempotency_key' => 'pg-pay-pending-1', 'request_hash' => str_repeat('a', 64),
            'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5),
        ]);

        // 55 pembayaran posted lebih baru (dulu menggeser antrean pending karena limit 50).
        for ($i = 1; $i <= 55; $i++) {
            DB::table('v2_payments')->insert([
                'invoice_id' => $invoiceId, 'amount' => '10000.00', 'method' => 'cash',
                'status' => 'posted', 'requested_by' => $cashierB->id, 'approved_by' => $admin->id,
                'reference' => 'POSTED-'.$i, 'idempotency_key' => 'pg-pay-posted-'.$i,
                'request_hash' => str_repeat('b', 64),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Antrean default (pending/proposed) tetap menemukan pembayaran pending lama.
        $pendingRes = $this->actingAs($admin)->getJson(route('v2.tables.show', 'payments'));
        $pendingRes->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('filters.filter', 'status:pending,proposed');
        $this->assertStringContainsString('75.000', (string) $pendingRes->json('html'));
        $this->assertStringContainsString('no-store', (string) $pendingRes->headers->get('Cache-Control'));

        // Semua pembayaran dapat ditelusuri sampai halaman ke-6 (total 56 baris).
        $this->actingAs($admin)->getJson(route('v2.tables.show', ['table' => 'payments', 'filter' => 'all', 'per_page' => 10, 'page' => 6]))
            ->assertOk()
            ->assertJsonPath('total', 56)
            ->assertJsonPath('page', 6)
            ->assertJsonPath('last_page', 6);

        // Kasir A hanya melihat pengajuannya sendiri (1 baris), bukan 55 baris milik Kasir B.
        $this->actingAs($cashierA)->getJson(route('v2.tables.show', ['table' => 'payments', 'filter' => 'all']))
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_deliveries_table_preserves_old_shipments_despite_many_pickup_sales(): void
    {
        $admin = $this->actor('admin', 'Admin Sintetis Kirim');
        $cashierA = $this->actor('cashier', 'Kasir Kirim A');
        $cashierB = $this->actor('cashier', 'Kasir Kirim B');
        $shiftId = DB::table('v2_shifts')->insertGetId([
            'user_id' => $admin->id, 'is_shared' => true, 'store_open_key' => 1, 'open_user_id' => $admin->id,
            'source' => 'admin', 'opening_cash' => '100000.00', 'carry_amount' => '0.00', 'admin_amount' => '100000.00',
            'status' => 'open', 'business_date' => now()->toDateString(), 'opened_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // 1 penjualan pengiriman lama milik Kasir A.
        $deliverySaleId = DB::table('v2_sales')->insertGetId([
            'number' => 'INV-PG-DLV-001', 'user_id' => $cashierA->id, 'shift_id' => $shiftId,
            'subtotal' => '100000.00', 'total' => '100000.00', 'cogs' => '50000.00',
            'tendered' => '100000.00', 'change_amount' => '0.00', 'request_hash' => str_repeat('c', 64),
            'payment_method' => 'cash', 'status' => 'paid', 'fulfillment' => 'delivery',
            'idempotency_key' => 'pg-sale-dlv-1', 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
        ]);
        DB::table('v2_deliveries')->insert([
            'sale_id' => $deliverySaleId, 'number' => 'SJ-LAMA-0001', 'destination' => 'Proyek Utara Sintetis',
            'status' => 'prepared', 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
        ]);

        // 35 penjualan tunai ambil sendiri lebih baru (dulu membuat SJ-LAMA-0001 hilang karena limit 30).
        for ($i = 1; $i <= 35; $i++) {
            DB::table('v2_sales')->insert([
                'number' => sprintf('INV-PG-PKP-%03d', $i), 'user_id' => $cashierB->id, 'shift_id' => $shiftId,
                'subtotal' => '50000.00', 'total' => '50000.00', 'cogs' => '25000.00',
                'tendered' => '50000.00', 'change_amount' => '0.00', 'request_hash' => str_repeat('d', 64),
                'payment_method' => 'cash', 'status' => 'paid', 'fulfillment' => 'pickup',
                'idempotency_key' => 'pg-sale-pkp-'.$i, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Tambah 12 surat jalan yang sudah diterima (received) milik Kasir B.
        for ($i = 1; $i <= 12; $i++) {
            $sid = DB::table('v2_sales')->insertGetId([
                'number' => sprintf('INV-PG-RCV-%03d', $i), 'user_id' => $cashierB->id, 'shift_id' => $shiftId,
                'subtotal' => '50000.00', 'total' => '50000.00', 'cogs' => '25000.00',
                'tendered' => '50000.00', 'change_amount' => '0.00', 'request_hash' => str_repeat('e', 64),
                'payment_method' => 'cash', 'status' => 'paid', 'fulfillment' => 'delivery',
                'idempotency_key' => 'pg-sale-rcv-'.$i, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('v2_deliveries')->insert([
                'sale_id' => $sid, 'number' => sprintf('SJ-RCV-%04d', $i), 'destination' => 'Tujuan Selesai '.$i,
                'status' => 'received', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Admin halaman 1 memprioritaskan SJ-LAMA-0001 (prepared) di atas surat jalan received, total 13, 2 halaman.
        $res = $this->actingAs($admin)->getJson(route('v2.tables.show', 'deliveries'));
        $res->assertOk()->assertJsonPath('total', 13)->assertJsonPath('last_page', 2);
        $this->assertStringContainsString('SJ-LAMA-0001', (string) $res->json('html'));

        // Kasir A hanya melihat 1 surat jalan miliknya; Kasir B melihat 12 miliknya.
        $this->actingAs($cashierA)->getJson(route('v2.tables.show', 'deliveries'))
            ->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($cashierB)->getJson(route('v2.tables.show', ['table' => 'deliveries', 'page' => 2]))
            ->assertOk()->assertJsonPath('total', 12)->assertJsonPath('page', 2);
    }

    public function test_operational_tables_paginate_and_enforce_role_boundaries(): void
    {
        $admin = $this->actor('admin', 'Admin Sintetis Tabel');
        $cashier = $this->actor('cashier', 'Kasir Sintetis Tabel');
        $outsider = User::factory()->create(['name' => 'Tanpa Peran']);

        $supplierId = DB::table('v2_parties')->insertGetId([
            'kind' => 'supplier', 'code' => 'SUP-PG-1', 'name' => 'Pemasok Paging',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'sku' => 'SYN-PG-P1', 'name' => 'Produk Paging', 'unit' => 'sak', 'category' => 'Umum',
            'cost_price' => '50000.0000', 'selling_price' => '60000.00', 'stock_quantity' => '100.0000',
            'min_stock' => '5.0000', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Seed 12 shift, 12 penerimaan, 12 draf, 12 opname, 12 retur jual, 12 retur beli.
        $firstShiftId = null;
        $firstReceiptId = null;
        for ($i = 1; $i <= 12; $i++) {
            $day = now()->subDays(15 - $i)->toDateString();
            $shiftId = DB::table('v2_shifts')->insertGetId([
                'user_id' => $admin->id, 'is_shared' => true, 'source' => 'admin',
                'opening_cash' => '100000.00', 'carry_amount' => '0.00', 'admin_amount' => '100000.00',
                'expected_cash' => '100000.00', 'counted_cash' => '100000.00', 'difference' => '0.00',
                'status' => 'closed', 'business_date' => $day, 'opened_at' => $day.' 08:00:00',
                'closed_at' => $day.' 17:00:00', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $firstShiftId ??= $shiftId;

            $receiptId = DB::table('inventory_receipts')->insertGetId([
                'receipt_number' => sprintf('RCV-PG-%04d', $i), 'receipt_type' => 'purchase',
                'credit_account' => '2101', 'receipt_date' => $day, 'supplier_name' => 'Pemasok Paging',
                'total_amount' => '100000.00', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $firstReceiptId ??= $receiptId;

            DB::table('v2_receipt_drafts')->insert([
                'supplier_id' => $supplierId, 'created_by' => $admin->id, 'receipt_date' => $day,
                'items' => json_encode([['product_id' => $productId, 'quantity' => '2', 'unit_cost' => '50000']]),
                'total_amount' => '100000.00', 'status' => 'draft', 'version' => 1,
                'idempotency_key' => 'pg-drf-'.$i, 'request_hash' => str_repeat('a', 64),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            DB::table('v2_stock_opnames')->insert([
                'number' => sprintf('OPN-PG-%04d', $i), 'opname_date' => $day,
                'notes' => 'Opname Paging '.$i, 'gain_total' => '0.00', 'loss_total' => '0.00',
                'created_by' => $admin->id, 'idempotency_key' => 'pg-opn-'.$i,
                'request_hash' => str_repeat('b', 64),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $saleId = DB::table('v2_sales')->insertGetId([
                'number' => sprintf('INV-PG-SR-%04d', $i), 'user_id' => $admin->id, 'shift_id' => $firstShiftId,
                'subtotal' => '60000.00', 'total' => '60000.00', 'cogs' => '50000.00',
                'tendered' => '60000.00', 'change_amount' => '0.00', 'request_hash' => str_repeat('f', 64),
                'payment_method' => 'cash', 'status' => 'paid', 'fulfillment' => 'pickup',
                'idempotency_key' => 'pg-sale-sr-'.$i, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('v2_sale_returns')->insert([
                'number' => sprintf('RET-PG-%04d', $i), 'sale_id' => $saleId, 'return_date' => $day,
                'reason' => 'Alasan Retur Jual '.$i, 'total_amount' => '60000.00', 'cogs_amount' => '50000.00',
                'ar_reduction' => '0.00', 'refund_amount' => '60000.00', 'refund_method' => 'main',
                'created_by' => $admin->id, 'idempotency_key' => 'pg-sr-'.$i,
                'request_hash' => str_repeat('c', 64),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            DB::table('v2_purchase_returns')->insert([
                'number' => sprintf('RTP-PG-%04d', $i), 'receipt_id' => $firstReceiptId, 'return_date' => $day,
                'reason' => 'Alasan Retur Beli '.$i, 'total_amount' => '50000.00', 'inventory_credit' => '50000.00',
                'ap_reduction' => '50000.00', 'refund_amount' => '0.00', 'refund_method' => 'payable_adjustment',
                'created_by' => $admin->id, 'idempotency_key' => 'pg-pr-'.$i,
                'request_hash' => str_repeat('d', 64),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Admin mengakses semua 6 tabel pada halaman 2 dengan total 12.
        foreach (['shifts', 'receipts', 'receipt-drafts', 'stock-opnames', 'sale-returns', 'purchase-returns'] as $table) {
            $this->actingAs($admin)->getJson(route('v2.tables.show', ['table' => $table, 'page' => 2]))
                ->assertOk()
                ->assertJsonPath('total', 12)
                ->assertJsonPath('page', 2)
                ->assertJsonPath('last_page', 2);
        }

        // Pencarian server pada penerimaan dan retur bekerja tepat sasaran.
        $this->actingAs($admin)->getJson(route('v2.tables.show', ['table' => 'receipts', 'q' => 'RCV-PG-0001']))
            ->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($admin)->getJson(route('v2.tables.show', ['table' => 'sale-returns', 'q' => 'RET-PG-0002']))
            ->assertOk()->assertJsonPath('total', 1);

        // Kasir boleh mengakses shifts, namun ditolak 403 pada tabel khusus admin.
        $this->actingAs($cashier)->getJson(route('v2.tables.show', 'shifts'))->assertOk()->assertJsonPath('total', 12);
        foreach (['receipts', 'receipt-drafts', 'stock-opnames', 'sale-returns', 'purchase-returns'] as $adminTable) {
            $this->actingAs($cashier)->getJson(route('v2.tables.show', $adminTable))->assertForbidden();
        }

        // Akun tanpa peran ditolak 403.
        $this->actingAs($outsider)->getJson(route('v2.tables.show', 'shifts'))->assertForbidden();
    }
}
