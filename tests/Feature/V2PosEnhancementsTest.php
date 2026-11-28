<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2PosEnhancementsTest extends TestCase
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
        $accounts = [
            ['1101', 'Kas', 'asset', 'debit'],
            ['1102', 'Bank', 'asset', 'debit'],
            ['1103', 'Piutang Usaha', 'asset', 'debit'],
            ['1301', 'Persediaan', 'asset', 'debit'],
            ['2101', 'Utang Usaha', 'liability', 'credit'],
            ['4101', 'Penjualan Barang', 'revenue', 'credit'],
            ['4102', 'Pendapatan Ongkir', 'revenue', 'credit'],
            ['4103', 'Diskon Penjualan', 'revenue', 'debit'],
            ['5101', 'HPP', 'expense', 'debit'],
        ];
        foreach ($accounts as [$code, $name, $type, $normal]) {
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

    private function product(array $overrides = []): int
    {
        return DB::table('products')->insertGetId(array_merge([
            'sku' => 'SYN-POS-'.bin2hex(random_bytes(3)),
            'name' => 'Semen Portland Sintetis 50kg',
            'unit' => 'sak',
            'category' => 'Semen',
            'cost_price' => '95000.0000',
            'selling_price' => '100000.00',
            'contractor_price' => '96000.00',
            'stock_quantity' => '25.0000',
            'min_stock' => '5.0000',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function customer(string $code, string $name, string $tier = 'retail'): int
    {
        return DB::table('v2_parties')->insertGetId([
            'kind' => 'customer',
            'code' => $code,
            'name' => $name,
            'price_tier' => $tier,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_pos_catalog_endpoint_returns_fresh_stock_and_hides_cost_price(): void
    {
        $cashier = $this->actor('cashier', 'Kasir Sintetis Katalog');
        $outsider = User::factory()->create(['name' => 'Tanpa Peran']);
        $activeId = $this->product(['sku' => 'SYN-CAT-01', 'name' => 'Pasir Beton Sintetis', 'cost_price' => '123456.0000', 'stock_quantity' => '12.5000', 'unit' => 'm3']);
        $this->product(['sku' => 'SYN-CAT-02', 'name' => 'Produk Arsip Sintetis', 'is_active' => false]);

        $response = $this->actingAs($cashier)->getJson(route('v2.pos.catalog'));
        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $items = $response->json('products');
        $this->assertCount(1, $items);
        $this->assertSame((string) $activeId, $items[0]['id']);
        $this->assertSame('12.5000', $items[0]['stock_quantity']);
        $this->assertSame('m3', $items[0]['unit']);
        $this->assertArrayNotHasKey('cost_price', $items[0]);
        $this->assertStringNotContainsString('123456', (string) $response->getContent());

        // Pembaruan stok di database langsung terlihat pada sinkronisasi berikutnya.
        DB::table('products')->where('id', $activeId)->update(['stock_quantity' => '9.2500']);
        $this->actingAs($cashier)->getJson(route('v2.pos.catalog'))
            ->assertOk()
            ->assertJsonPath('products.0.stock_quantity', '9.2500');

        $this->actingAs($outsider)->getJson(route('v2.pos.catalog'))->assertForbidden();
    }

    public function test_cashier_discount_limit_and_below_cost_guard_are_enforced_by_role(): void
    {
        $this->seedAccounts();
        $admin = $this->actor('admin', 'Admin Sintetis Margin');
        $cashier = $this->actor('cashier', 'Kasir Sintetis Margin');
        app(Operations::class)->openShift($admin->id, '500000.00', 'admin');

        $productId = $this->product([
            'name' => 'Cat Tembok Sintetis',
            'cost_price' => '93000.0000',
            'selling_price' => '100000.00',
            'contractor_price' => null,
        ]);

        config([
            'v2.pos.cashier_max_discount_percent' => '10',
            'v2.pos.block_cashier_below_cost' => true,
        ]);

        // 1. Kasir diskon baris 15% (> batas 10%) ditolak 422.
        $this->actingAs($cashier)->postJson(route('v2.sales.store'), [
            'items' => [['product_id' => $productId, 'quantity' => '1', 'discount' => ['type' => 'percent', 'value' => '15']]],
            'delivery_fee' => '0',
            'payment_method' => 'cash',
            'paid_amount' => '100000',
            'idempotency_key' => 'syn-margin-line-01',
            'fulfillment' => 'pickup',
        ])->assertUnprocessable()->assertSee('melebihi batas kasir maksimal 10%');

        // 2. Kasir diskon gabungan 8% (<= 10%, harga bersih 92.000 < HPP 93.000) ditolak 422 tanpa membocorkan HPP.
        $belowCost = $this->actingAs($cashier)->postJson(route('v2.sales.store'), [
            'items' => [['product_id' => $productId, 'quantity' => '1', 'discount' => ['type' => 'percent', 'value' => '8']]],
            'delivery_fee' => '0',
            'payment_method' => 'cash',
            'paid_amount' => '100000',
            'idempotency_key' => 'syn-margin-cost-02',
            'fulfillment' => 'pickup',
        ]);
        $belowCost->assertUnprocessable()->assertSee('batas margin kasir');
        $this->assertStringNotContainsString('93000', (string) $belowCost->getContent());
        $this->assertStringNotContainsString('93.000', (string) $belowCost->getContent());

        // 3. Kasir diskon 5% (harga bersih 95.000 >= HPP 93.000 dan <= 10%) berhasil.
        $this->actingAs($cashier)->postJson(route('v2.sales.store'), [
            'items' => [['product_id' => $productId, 'quantity' => '1', 'discount' => ['type' => 'percent', 'value' => '5']]],
            'delivery_fee' => '0',
            'payment_method' => 'cash',
            'paid_amount' => '100000',
            'idempotency_key' => 'syn-margin-ok-03',
            'fulfillment' => 'pickup',
        ])->assertOk()->assertJsonPath('total', '95000.00');

        // 4. Admin tetap diizinkan memberi diskon 30% (di bawah HPP).
        $this->actingAs($admin)->postJson(route('v2.sales.store'), [
            'items' => [['product_id' => $productId, 'quantity' => '1', 'discount' => ['type' => 'percent', 'value' => '30']]],
            'delivery_fee' => '0',
            'payment_method' => 'cash',
            'paid_amount' => '70000',
            'idempotency_key' => 'syn-margin-admin-04',
            'fulfillment' => 'pickup',
        ])->assertOk()->assertJsonPath('total', '70000.00');
    }

    public function test_customer_lookup_returns_open_and_overdue_receivable_summaries(): void
    {
        $cashier = $this->actor('cashier', 'Kasir Sintetis Piutang');
        $debtorId = $this->customer('CUS-SYN-101', 'Proyek Griya Sintetis', 'contractor');
        $cleanId = $this->customer('CUS-SYN-102', 'Proyek Bersih Sintetis');

        DB::table('v2_invoices')->insert([
            [
                'direction' => 'receivable', 'party_id' => $debtorId, 'number' => 'AR-SYN-001',
                'amount' => '300000.00', 'paid' => '100000.00', 'due_date' => now()->addDays(5)->toDateString(),
                'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'direction' => 'receivable', 'party_id' => $debtorId, 'number' => 'AR-SYN-002',
                'amount' => '500000.00', 'paid' => '0.00', 'due_date' => now()->subDays(3)->toDateString(),
                'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'direction' => 'receivable', 'party_id' => $debtorId, 'number' => 'AR-SYN-003',
                'amount' => '900000.00', 'paid' => '900000.00', 'due_date' => now()->subDays(10)->toDateString(),
                'status' => 'paid', 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'direction' => 'payable', 'party_id' => $debtorId, 'number' => 'AP-SYN-001',
                'amount' => '400000.00', 'paid' => '0.00', 'due_date' => now()->subDays(1)->toDateString(),
                'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($cashier)->getJson(route('v2.customers.search', ['q' => 'Proyek']));
        $response->assertOk();
        $results = collect($response->json('results'))->keyBy('id');

        $this->assertSame(2, $results[$debtorId]['open_invoices_count']);
        $this->assertSame('700000.00', $results[$debtorId]['open_balance']);
        $this->assertSame(1, $results[$debtorId]['overdue_count']);
        $this->assertSame('500000.00', $results[$debtorId]['overdue_balance']);

        $this->assertSame(0, $results[$cleanId]['open_invoices_count']);
        $this->assertSame('0.00', $results[$cleanId]['open_balance']);
        $this->assertSame(0, $results[$cleanId]['overdue_count']);
        $this->assertSame('0.00', $results[$cleanId]['overdue_balance']);
    }

    public function test_checkout_persists_notes_and_renders_production_receipt_and_delivery_documents(): void
    {
        $this->seedAccounts();
        config([
            'v2.store.name' => 'TB Sintetis Nusantara',
            'v2.store.address' => 'Jl. Pengujian No. 12',
            'v2.store.phone' => '0800-1111-2222',
            'v2.store.footer' => 'Terima kasih atas pesanan Anda.',
        ]);

        $admin = $this->actor('admin', 'Admin Sintetis Dokumen');
        $cashier = $this->actor('cashier', 'Kasir Sintetis Dokumen');
        app(Operations::class)->openShift($admin->id, '250000.00', 'admin');

        $productId = $this->product([
            'name' => 'Semen Sintetis Prima',
            'unit' => 'sak',
            'cost_price' => '55555.0000',
            'selling_price' => '75000.00',
            'contractor_price' => '70000.00',
        ]);
        $customerId = $this->customer('CUS-SYN-DOC', 'Kontraktor Mitra Sintetis', 'contractor');
        $dueDate = now()->addDays(14)->toDateString();

        // Catatan > 500 karakter ditolak.
        $this->actingAs($cashier)->postJson(route('v2.sales.store'), [
            'items' => [['product_id' => $productId, 'quantity' => '2']],
            'delivery_fee' => '25000',
            'customer_id' => $customerId,
            'payment_method' => 'credit',
            'paid_amount' => '0',
            'due_date' => $dueDate,
            'fulfillment' => 'delivery',
            'destination' => 'Proyek Blok C Sintetis',
            'price_tier' => 'contractor',
            'notes' => str_repeat('a', 501),
            'idempotency_key' => 'syn-notes-too-long',
        ])->assertUnprocessable();

        $checkout = $this->actingAs($cashier)->postJson(route('v2.sales.store'), [
            'items' => [['product_id' => $productId, 'quantity' => '2']],
            'delivery_fee' => '25000',
            'customer_id' => $customerId,
            'payment_method' => 'credit',
            'paid_amount' => '0',
            'due_date' => $dueDate,
            'fulfillment' => 'delivery',
            'destination' => 'Proyek Blok C Sintetis',
            'price_tier' => 'contractor',
            'notes' => 'Turunkan dekat gerbang samping, hubungi mandor.',
            'idempotency_key' => 'syn-notes-doc-001',
        ]);
        $checkout->assertOk();
        $saleId = (int) $checkout->json('sale_id');

        $this->assertSame('Turunkan dekat gerbang samping, hubungi mandor.', DB::table('v2_sales')->where('id', $saleId)->value('notes'));

        // Nota penjualan cetak: kop toko, kasir, satuan rapi, harga kontraktor, jatuh tempo, catatan, mode thermal, tanpa label pratinjau/HPP.
        $receipt = $this->actingAs($cashier)->get(route('v2.documents', ['id' => $saleId, 'kind' => 'receipt']));
        $receipt->assertOk()
            ->assertSee('TB Sintetis Nusantara')
            ->assertSee('Jl. Pengujian No. 12')
            ->assertSee('0800-1111-2222')
            ->assertSee('Kasir Sintetis Dokumen')
            ->assertSee('Harga Kontraktor')
            ->assertSee('2 sak')
            ->assertSee('Turunkan dekat gerbang samping, hubungi mandor.')
            ->assertSee('Struk 80mm')
            ->assertDontSee('Pratinjau V2')
            ->assertDontSee('Shift #')
            ->assertDontSee('2.0000')
            ->assertDontSee('55555');

        // Surat jalan: memuat catatan, satuan rapi, kolom tanda tangan, tanpa harga barang.
        $delivery = $this->actingAs($cashier)->get(route('v2.documents', ['id' => $saleId, 'kind' => 'delivery']));
        $delivery->assertOk()
            ->assertSee('Surat Jalan')
            ->assertSee('2 sak')
            ->assertSee('Turunkan dekat gerbang samping, hubungi mandor.')
            ->assertSee('Disiapkan / Pengirim')
            ->assertSee('Sopir / Armada')
            ->assertSee('Diterima oleh')
            ->assertDontSee('Rp 70.000')
            ->assertDontSee('Pratinjau V2');

        // Detail penjualan di workspace ikut menampilkan catatan nota.
        $this->actingAs($cashier)->getJson(route('v2.sales.detail', ['id' => $saleId]))
            ->assertOk()
            ->assertSee('Turunkan dekat gerbang samping, hubungi mandor.');
    }
}
