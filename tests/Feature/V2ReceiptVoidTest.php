<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\SupplyTrips;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pembatalan penerimaan barang yang salah input: jurnal balik, stok dan HPP kembali, utang supplier ditutup.
 * Hanya admin, wajib alasan, dan hanya bila belum ada pembayaran maupun mutasi stok sesudahnya. Data sintetis, SQLite :memory:.
 */
class V2ReceiptVoidTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('V2 receipt void tests require isolated SQLite :memory:.');
        }
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    /** @return array{supplier:int, product:int} */
    private function setUpStock(): array
    {
        foreach ([['1101', 'Kas', 'asset', 'debit'], ['1102', 'Bank', 'asset', 'debit'], ['1103', 'Piutang', 'asset', 'debit'], ['1301', 'Persediaan', 'asset', 'debit'], ['2101', 'Utang', 'liability', 'credit'], ['3101', 'Modal', 'equity', 'credit'], ['4101', 'Penjualan', 'revenue', 'credit'], ['4102', 'Ongkir', 'revenue', 'credit'], ['5101', 'HPP', 'expense', 'debit'], ['6101', 'Biaya Perjalanan', 'expense', 'debit']] as [$code, $name, $type, $normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore(['account_code' => $code, 'account_name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $supplier = (int) DB::table('v2_parties')->insertGetId(['kind' => 'supplier', 'code' => 'SYN-SUP-1', 'name' => 'PT Pemasok Sintetis', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $product = (int) DB::table('products')->insertGetId([
            'sku' => 'SYN-SMN-1', 'name' => 'Semen Sintetis', 'unit' => 'sak', 'category' => 'Umum',
            'cost_price' => '50000.0000', 'selling_price' => '60000.00', 'stock_quantity' => '10.0000', 'min_stock' => '1.0000',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_product_suppliers')->insert(['product_id' => $product, 'supplier_id' => $supplier, 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()]);

        return ['supplier' => $supplier, 'product' => $product];
    }

    /** @return array{receipt_id:int, invoice_id:int, total:string} */
    private function receive(User $admin, array $stock, string $quantity, string $unitCost): array
    {
        return app(SupplyTrips::class)->receive($admin->id, [
            'supplier_id' => $stock['supplier'], 'receipt_date' => now()->toDateString(), 'idempotency_key' => 'syn-rcv-'.++$this->seq,
            'items' => [['product_id' => $stock['product'], 'quantity' => $quantity, 'unit_cost' => $unitCost]],
        ]);
    }

    private function balance(string $code): string
    {
        $lines = DB::table('journal_entry_lines')->where('account_code', $code)->get(['debit', 'credit']);
        $total = 0;
        foreach ($lines as $line) {
            $total += (float) $line->debit - (float) $line->credit;
        }

        return number_format($total, 2, '.', '');
    }

    public function test_admin_voids_a_mistyped_receipt_and_everything_returns_to_before(): void
    {
        $admin = $this->actor('admin');
        $stock = $this->setUpStock();
        // Salah ketik: 520.000 per sak, seharusnya 52.000.
        $receipt = $this->receive($admin, $stock, '20', '520000');
        $this->assertEquals(30, (float) DB::table('products')->where('id', $stock['product'])->value('stock_quantity'));

        $this->actingAs($admin)->postJson(route('v2.receipts.void', ['id' => $receipt['receipt_id']]), [
            'reason' => 'Harga satuan salah ketik', 'idempotency_key' => 'syn-void-1',
        ])->assertOk()->assertJson(['receipt_id' => $receipt['receipt_id'], 'status' => 'void']);

        $product = DB::table('products')->where('id', $stock['product'])->first();
        $this->assertEquals(10, (float) $product->stock_quantity);
        $this->assertEquals(50000, (float) $product->cost_price);
        $this->assertSame('0.00', $this->balance('1301'));
        $this->assertSame('0.00', $this->balance('2101'));

        $row = DB::table('inventory_receipts')->where('id', $receipt['receipt_id'])->first();
        $this->assertNotNull($row->voided_at);
        $this->assertSame($admin->id, (int) $row->voided_by);
        $this->assertSame('Harga satuan salah ketik', $row->void_reason);
        $this->assertNotNull($row->void_journal_entry_id);

        $invoice = DB::table('v2_invoices')->where('id', $receipt['invoice_id'])->first();
        $this->assertNotNull($invoice->voided_at);
        $this->assertEquals(0, (float) $invoice->amount - (float) $invoice->paid);
        $this->assertSame('paid', $invoice->status);

        $movement = DB::table('stock_movements')->where('reference_type', 'v2_receipt_void')->where('reference_id', $receipt['receipt_id'])->first();
        $this->assertEquals(-20, (float) $movement->quantity_delta);

        // Input ulang yang benar memakai HPP awal sebagai dasar rata-rata.
        $this->receive($admin, $stock, '20', '52000');
        $this->assertEquals(51333.3333, (float) DB::table('products')->where('id', $stock['product'])->value('cost_price'));

        // Ulangi kunci yang sama: hasil sama, tidak membatalkan dua kali.
        $this->actingAs($admin)->postJson(route('v2.receipts.void', ['id' => $receipt['receipt_id']]), [
            'reason' => 'Harga satuan salah ketik', 'idempotency_key' => 'syn-void-1',
        ])->assertOk();
        $this->assertSame(1, DB::table('stock_movements')->where('reference_type', 'v2_receipt_void')->count());
        // Kunci lain untuk penerimaan yang sudah batal ditolak.
        $this->actingAs($admin)->postJson(route('v2.receipts.void', ['id' => $receipt['receipt_id']]), [
            'reason' => 'Ulang', 'idempotency_key' => 'syn-void-2',
        ])->assertStatus(422);
    }

    public function test_void_is_refused_after_a_payment_or_a_later_stock_movement(): void
    {
        $admin = $this->actor('admin');
        $stock = $this->setUpStock();

        $paid = $this->receive($admin, $stock, '5', '52000');
        DB::table('v2_payments')->insert(['invoice_id' => $paid['invoice_id'], 'amount' => '1000.00', 'method' => 'transfer', 'status' => 'proposed', 'requested_by' => $admin->id, 'idempotency_key' => 'syn-pay-1', 'request_hash' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($admin)->postJson(route('v2.receipts.void', ['id' => $paid['receipt_id']]), ['reason' => 'Coba batal', 'idempotency_key' => 'syn-void-3'])
            ->assertStatus(422)->assertJsonFragment(['message' => 'Tagihan supplier penerimaan ini sudah punya pembayaran atau pengajuan pembayaran. Tolak atau selesaikan pembayarannya dulu.']);

        $stock2 = $stock;
        $first = $this->receive($admin, $stock2, '4', '52000');
        $this->receive($admin, $stock2, '1', '52000');
        $this->actingAs($admin)->postJson(route('v2.receipts.void', ['id' => $first['receipt_id']]), ['reason' => 'Coba batal', 'idempotency_key' => 'syn-void-4'])
            ->assertStatus(422)->assertJsonFragment(['message' => 'Semen Sintetis sudah punya transaksi stok sesudah penerimaan ini, jadi stok dan HPP tidak bisa dikembalikan dengan tepat.']);

        $this->assertSame(0, DB::table('inventory_receipts')->whereNotNull('voided_at')->count());
    }

    public function test_history_offers_void_only_while_unpaid_and_marks_voided_receipts(): void
    {
        $admin = $this->actor('admin');
        $stock = $this->setUpStock();
        $open = $this->receive($admin, $stock, '2', '52000');

        $this->actingAs($admin)->get(route('v2.index'))->assertOk()
            ->assertSee(route('v2.receipts.void', ['id' => $open['receipt_id']]), false)
            ->assertSee('Periksa sebelum simpan');

        $this->actingAs($admin)->postJson(route('v2.receipts.void', ['id' => $open['receipt_id']]), ['reason' => 'Jumlah salah ketik', 'idempotency_key' => 'syn-void-7'])->assertOk();

        $this->actingAs($admin)->get(route('v2.index'))->assertOk()
            ->assertDontSee(route('v2.receipts.void', ['id' => $open['receipt_id']]), false)
            ->assertSee('Dibatalkan')
            ->assertSee('Jumlah salah ketik');
    }

    public function test_only_admin_with_a_reason_may_void(): void
    {
        $admin = $this->actor('admin');
        $stock = $this->setUpStock();
        $receipt = $this->receive($admin, $stock, '2', '52000');

        $this->actingAs($this->actor('cashier'))->postJson(route('v2.receipts.void', ['id' => $receipt['receipt_id']]), ['reason' => 'Salah input', 'idempotency_key' => 'syn-void-5'])
            ->assertForbidden();
        $this->actingAs($admin)->postJson(route('v2.receipts.void', ['id' => $receipt['receipt_id']]), ['reason' => 'abc', 'idempotency_key' => 'syn-void-6'])
            ->assertStatus(422);
        $this->assertNull(DB::table('inventory_receipts')->where('id', $receipt['receipt_id'])->value('voided_at'));
    }
}
