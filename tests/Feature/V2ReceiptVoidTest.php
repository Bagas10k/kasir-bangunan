<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\SupplyTrips;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Penerimaan yang sudah diposting adalah final: jalur pembatalan lama harus ditolak tanpa efek stok/jurnal.
 * Riwayat pembatalan lama tetap dipertahankan. Data sintetis, SQLite :memory:.
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

    public function test_confirmed_receipt_cannot_be_voided_even_when_unpaid_and_unused(): void
    {
        $admin = $this->actor('admin');
        $stock = $this->setUpStock();
        $receipt = $this->receive($admin, $stock, '2', '52000');
        $journalCount = DB::table('journal_entries')->count();

        $this->actingAs($admin)->postJson(route('v2.receipts.void', ['id' => $receipt['receipt_id']]), [
            'reason' => 'Harga salah ketik', 'idempotency_key' => 'syn-locked-void',
        ])->assertUnprocessable()->assertJsonValidationErrors('supply');

        $this->assertEquals(12, DB::table('products')->where('id', $stock['product'])->value('stock_quantity'));
        $this->assertEquals(104000, DB::table('v2_invoices')->where('id', $receipt['invoice_id'])->value('amount'));
        $this->assertNull(DB::table('inventory_receipts')->where('id', $receipt['receipt_id'])->value('voided_at'));
        $this->assertSame($journalCount, DB::table('journal_entries')->count());
        $this->assertDatabaseMissing('stock_movements', ['reference_type' => 'v2_receipt_void']);
    }

    public function test_final_receipts_remain_locked_after_payments_or_later_stock_movements(): void
    {
        $admin = $this->actor('admin');
        $stock = $this->setUpStock();
        $paid = $this->receive($admin, $stock, '5', '52000');
        DB::table('v2_payments')->insert(['invoice_id' => $paid['invoice_id'], 'amount' => '1000.00', 'method' => 'transfer', 'status' => 'proposed', 'requested_by' => $admin->id, 'idempotency_key' => 'syn-pay-1', 'request_hash' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now()]);
        $this->receive($admin, $stock, '1', '52000');

        $this->actingAs($admin)->postJson(route('v2.receipts.void', ['id' => $paid['receipt_id']]), ['reason' => 'Coba batal', 'idempotency_key' => 'syn-void-3'])
            ->assertUnprocessable()->assertJsonPath('message', 'Penerimaan sudah dikonfirmasi dan terkunci. Pembatalan biasa tidak diizinkan; gunakan proses koreksi atau retur.');
        $this->assertDatabaseCount('inventory_receipts', 2);
        $this->assertDatabaseMissing('inventory_receipts', ['void_reason' => 'Coba batal']);
    }

    public function test_history_has_no_void_action_for_final_receipts_and_keeps_historical_void_audit(): void
    {
        $admin = $this->actor('admin');
        $stock = $this->setUpStock();
        $open = $this->receive($admin, $stock, '2', '52000');
        $legacy = $this->receive($admin, $stock, '1', '52000');
        // Historical fixture: an already-voided document predating the final-lock policy.
        DB::table('inventory_receipts')->where('id', $legacy['receipt_id'])->update(['voided_at' => now(), 'voided_by' => $admin->id, 'void_reason' => 'Koreksi historis']);

        $this->actingAs($admin)->get(route('v2.index'))->assertOk()
            ->assertDontSee(route('v2.receipts.void', ['id' => $open['receipt_id']]), false)
            ->assertSee('Dikonfirmasi')->assertSee('Terkunci')->assertSee('Dibatalkan')->assertSee('Koreksi historis');
    }

    public function test_cashiers_cannot_void_and_admins_cannot_bypass_final_lock(): void
    {
        $admin = $this->actor('admin');
        $stock = $this->setUpStock();
        $receipt = $this->receive($admin, $stock, '2', '52000');
        $url = route('v2.receipts.void', ['id' => $receipt['receipt_id']]);
        $this->actingAs($this->actor('cashier'))->postJson($url, ['reason' => 'Salah input', 'idempotency_key' => 'syn-void-5'])->assertForbidden();
        $this->actingAs($admin)->postJson($url, ['reason' => 'abc', 'idempotency_key' => 'syn-void-6'])->assertUnprocessable();
        $this->assertNull(DB::table('inventory_receipts')->where('id', $receipt['receipt_id'])->value('voided_at'));
    }
}
