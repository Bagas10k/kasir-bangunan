<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\MasterData;
use App\Services\V2\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Harga kontraktor: kolom per produk (kosong = harga jual biasa), penanda Kontraktor di pelanggan,
 * dan pilihan harga di kasir. Data sintetis, SQLite :memory:.
 */
class V2ContractorPricingTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('V2 contractor pricing tests require isolated SQLite :memory:.');
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

    private function product(string $selling, ?string $contractor): int
    {
        return (int) DB::table('products')->insertGetId([
            'sku' => 'SYN-K-'.++$this->seq, 'name' => 'Material Sintetis '.$this->seq, 'unit' => 'sak', 'category' => 'Umum',
            'cost_price' => '50.0000', 'selling_price' => $selling, 'contractor_price' => $contractor,
            'stock_quantity' => '20.0000', 'min_stock' => '1.0000', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function customer(string $tier): int
    {
        return (int) DB::table('v2_parties')->insertGetId([
            'kind' => 'customer', 'code' => 'SYN-PLG-'.++$this->seq, 'name' => 'Pelanggan Sintetis '.$this->seq, 'price_tier' => $tier,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function sell(User $cashier, array $products, array $extra = []): object
    {
        $items = array_map(fn (int $id): array => ['product_id' => $id, 'quantity' => '2'], $products);
        $data = $extra + ['items' => $items, 'delivery_fee' => '0', 'payment_method' => 'cash', 'paid_amount' => '100000.00', 'idempotency_key' => 'syn-kontraktor-'.++$this->seq, 'fulfillment' => 'pickup'];
        $this->actingAs($cashier)->postJson(route('v2.sales.store'), $data)->assertOk();

        return DB::table('v2_sales')->orderByDesc('id')->first();
    }

    public function test_contractor_tier_uses_contractor_price_and_falls_back_to_selling_price(): void
    {
        $this->accounts();
        $cashier = $this->actor('cashier');
        app(Operations::class)->openShift($cashier->id, '0.00', 'admin');
        $withPrice = $this->product('100.00', '90.50');
        $without = $this->product('70.00', null);

        $sale = $this->sell($cashier, [$withPrice, $without], ['price_tier' => 'contractor']);
        $this->assertSame('contractor', $sale->price_tier);
        $this->assertSame('321.00', number_format((float) $sale->total, 2, '.', ''));   // 2×90,50 + 2×70
        $prices = DB::table('v2_sale_items')->where('sale_id', $sale->id)->pluck('unit_price', 'product_id');
        $this->assertEquals('90.50', $prices[$withPrice]);
        $this->assertEquals('70.00', $prices[$without]);
        // Pendapatan di jurnal mengikuti total nota.
        $credit = DB::table('journal_entry_lines')->where('account_code', '4101')->sum('credit');
        $this->assertEquals(321, $credit);

        $retail = $this->sell($cashier, [$withPrice], ['price_tier' => 'retail']);
        $this->assertSame('retail', $retail->price_tier);
        $this->assertSame('200.00', number_format((float) $retail->total, 2, '.', ''));
    }

    public function test_missing_tier_follows_customer_and_invalid_tier_is_rejected(): void
    {
        $this->accounts();
        $cashier = $this->actor('cashier');
        app(Operations::class)->openShift($cashier->id, '0.00', 'admin');
        $product = $this->product('100.00', '80.00');

        $contractorSale = $this->sell($cashier, [$product], ['customer_id' => $this->customer('contractor')]);
        $this->assertSame('contractor', $contractorSale->price_tier);
        $this->assertSame('160.00', number_format((float) $contractorSale->total, 2, '.', ''));

        // Kasir boleh mengganti: pelanggan kontraktor tetap bisa dijual dengan harga eceran.
        $override = $this->sell($cashier, [$product], ['customer_id' => $this->customer('contractor'), 'price_tier' => 'retail']);
        $this->assertSame('200.00', number_format((float) $override->total, 2, '.', ''));

        $general = $this->sell($cashier, [$product]);
        $this->assertSame('retail', $general->price_tier);

        $this->actingAs($cashier)->postJson(route('v2.sales.store'), [
            'items' => [['product_id' => $product, 'quantity' => '1']], 'delivery_fee' => '0', 'payment_method' => 'cash',
            'paid_amount' => '1000', 'idempotency_key' => 'syn-k-bad-tier', 'fulfillment' => 'pickup', 'price_tier' => 'grosir',
        ])->assertStatus(422);
    }

    public function test_master_saves_contractor_price_and_customer_tier(): void
    {
        $admin = $this->actor('admin');
        $master = app(MasterData::class);

        $blank = $master->store($admin->id, 'product', ['sku' => 'SYN-P-1', 'name' => 'Produk Tanpa Harga Kontraktor', 'unit' => 'sak', 'selling_price' => '100', 'contractor_price' => '']);
        $this->assertNull(DB::table('products')->where('id', $blank)->value('contractor_price'));

        $priced = $master->store($admin->id, 'product', ['sku' => 'SYN-P-2', 'name' => 'Produk Harga Kontraktor', 'unit' => 'sak', 'selling_price' => '100', 'contractor_price' => '92.5']);
        $this->assertEquals('92.50', DB::table('products')->where('id', $priced)->value('contractor_price'));

        // Ubah tanpa mengirim kolom: harga kontraktor dipertahankan; dikirim kosong: dihapus.
        $master->update($admin->id, 'product', $priced, ['sku' => 'SYN-P-2', 'name' => 'Produk Harga Kontraktor', 'unit' => 'sak', 'selling_price' => '100']);
        $this->assertEquals('92.50', DB::table('products')->where('id', $priced)->value('contractor_price'));
        $master->update($admin->id, 'product', $priced, ['sku' => 'SYN-P-2', 'name' => 'Produk Harga Kontraktor', 'unit' => 'sak', 'selling_price' => '100', 'contractor_price' => '']);
        $this->assertNull(DB::table('products')->where('id', $priced)->value('contractor_price'));

        foreach (['0', '-5', 'abc'] as $bad) {
            try {
                $master->store($admin->id, 'product', ['sku' => 'SYN-P-X'.++$this->seq, 'name' => 'Produk Salah', 'unit' => 'sak', 'selling_price' => '100', 'contractor_price' => $bad]);
                $this->fail('Harga kontraktor '.$bad.' seharusnya ditolak.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('contractor_price', $e->getMessage());
            }
        }

        $customer = $master->store($admin->id, 'customer', ['code' => 'PLG-0001', 'name' => 'CV Sintetis Bangun', 'price_tier' => 'contractor']);
        $this->assertSame('contractor', DB::table('v2_parties')->where('id', $customer)->value('price_tier'));
        $master->update($admin->id, 'customer', $customer, ['code' => 'PLG-0001', 'name' => 'CV Sintetis Bangun']);
        $this->assertSame('contractor', DB::table('v2_parties')->where('id', $customer)->value('price_tier'));
        $plain = $master->store($admin->id, 'customer', ['code' => 'PLG-0002', 'name' => 'Pembeli Sintetis']);
        $this->assertSame('retail', DB::table('v2_parties')->where('id', $plain)->value('price_tier'));

        $this->expectException(\RuntimeException::class);
        $master->store($admin->id, 'customer', ['code' => 'PLG-0003', 'name' => 'Salah', 'price_tier' => 'grosir']);
    }

    public function test_customer_search_reports_price_tier(): void
    {
        $cashier = $this->actor('cashier');
        $this->customer('contractor');
        $response = $this->actingAs($cashier)->getJson(route('v2.customers.search', ['q' => 'Sintetis']))->assertOk();
        $this->assertSame('contractor', $response->json('results.0.price_tier'));
    }
}
