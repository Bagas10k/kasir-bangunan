<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\DemoData;
use App\Services\V2\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2WorkspaceViewTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('View fixtures require isolated SQLite :memory:.');
        }
    }

    public function test_business_day_uses_configured_wib_timezone(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('2026-11-01', Carbon::parse('2026-10-31 18:00:00', 'UTC')->setTimezone(config('app.timezone'))->toDateString());
    }

    public function test_guest_is_redirected_and_unassigned_account_is_denied(): void
    {
        $this->get(route('v2.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('v2.index'))->assertForbidden();
    }

    public function test_real_workspace_route_renders_current_forms_and_role_scoped_data(): void
    {
        $cashier = User::factory()->create(['name' => 'Kasir Sintetis Alpha']);
        $admin = User::factory()->create(['name' => 'Admin Sintetis Beta']);
        foreach ([[$cashier, 'cashier'], [$admin, 'admin']] as [$user,$role]) {
            DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([['1101', 'Kas', 'asset', 'debit'], ['1102', 'Bank', 'asset', 'debit'], ['1103', 'Piutang', 'asset', 'debit'], ['1301', 'Persediaan', 'asset', 'debit'], ['2101', 'Utang', 'liability', 'credit'], ['4101', 'Penjualan', 'revenue', 'credit'], ['4102', 'Ongkir', 'revenue', 'credit'], ['5101', 'HPP', 'expense', 'debit']] as [$code,$name,$type,$normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore(['account_code' => $code, 'account_name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $product = DB::table('products')->insertGetId(['sku' => 'SYN-UI-001', 'name' => 'Material Sintetis Alpha', 'unit' => 'unit', 'category' => 'Umum', 'cost_price' => '60.1234', 'selling_price' => '100.00', 'stock_quantity' => '20', 'min_stock' => '1', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $customer = DB::table('v2_parties')->insertGetId(['kind' => 'customer', 'code' => 'SYN-C-UI', 'name' => 'Pelanggan Sintetis Alpha', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $supplier = DB::table('v2_parties')->insertGetId(['kind' => 'supplier', 'code' => 'SYN-S-UI', 'name' => 'Supplier Sintetis Alpha', 'is_active' => true]);
        DB::table('v2_parties')->insert(['kind' => 'supplier', 'code' => 'SYN-S-UI-B', 'name' => 'Supplier Sintetis Beta', 'is_active' => true]);
        $employee = DB::table('v2_parties')->insertGetId(['kind' => 'employee', 'code' => 'SYN-E-UI', 'name' => 'Karyawan Sintetis Alpha', 'is_active' => true]);
        $driver = DB::table('v2_drivers')->insertGetId(['code' => 'SYN-D-UI', 'name' => 'Karyawan Sintetis Alpha', 'kind' => 'internal', 'employee_id' => $employee, 'is_active' => true]);
        DB::table('v2_drivers')->insert(['code' => 'SYN-D-UI-B', 'name' => 'Sopir Sintetis Beta', 'kind' => 'external', 'is_active' => true]);
        $vehicle = DB::table('vehicles')->insertGetId(['license_plate' => 'SYN-UI-01', 'vehicle_type' => 'truck', 'status' => 'active']);
        DB::table('v2_product_suppliers')->insert(['product_id' => $product, 'supplier_id' => $supplier, 'is_primary' => true]);
        $unit = DB::table('product_options')->insertGetId(['type' => 'unit', 'name' => 'unit', 'is_active' => true]);
        DB::table('products')->where('id', $product)->update(['unit_id' => $unit, 'category_id' => DB::table('product_options')->where('type', 'category')->where('name', 'Umum')->value('id')]);
        $ops = app(Operations::class);
        $ops->openShift($cashier->id, '100', 'admin');
        $sale = $ops->checkout($cashier->id, ['items' => [['product_id' => $product, 'quantity' => '1']], 'delivery_fee' => '15', 'payment_method' => 'transfer', 'paid_amount' => '115', 'customer_id' => $customer, 'fulfillment' => 'delivery', 'destination' => 'Zona Sintetis A', 'idempotency_key' => 'synthetic-preview-sale-001']);
        DB::table('v2_deliveries')->where('sale_id', $sale['sale_id'])->update(['vehicle_id' => $vehicle, 'driver_id' => $driver, 'driver_name' => 'Karyawan Sintetis Alpha']);
        $otherCashier = User::factory()->create(['name' => 'Kasir Sintetis Gamma']);
        DB::table('v2_user_roles')->insert(['user_id' => $otherCashier->id, 'role' => 'cashier', 'created_at' => now(), 'updated_at' => now()]);
        $ops->openShift($otherCashier->id, '100', 'admin');
        $ops->checkout($otherCashier->id, ['items' => [['product_id' => $product, 'quantity' => '2']], 'delivery_fee' => '0', 'payment_method' => 'cash', 'paid_amount' => '200', 'fulfillment' => 'pickup', 'idempotency_key' => 'synthetic-other-cashier-sale']);
        if (getenv('V2_PREVIEW_DIR') && getenv('V2_PREVIEW_DEMO')) {
            // Fixture pratinjau saja: tambahkan data contoh master agar tampilan form dan relasi terlihat penuh.
            app(DemoData::class)->seed($admin->id);
            // Riwayat penerimaan contoh (per barang) agar tabel Penerimaan terlihat penuh.
            $demoProducts = DB::table('products')->where('sku', 'like', 'PRD-%')->orderBy('id')->limit(5)->get(['id', 'name']);
            foreach ($demoProducts as $i => $p) {
                [$qty, $cost] = [[120, '62500'], [200, '28000'], [8, '310000'], [24, '85000'], [3000, '440']][$i];
                $rid = DB::table('inventory_receipts')->insertGetId([
                    'receipt_number' => sprintf('RCV-DEMO-%03d', $i + 1), 'receipt_type' => $i === 4 ? 'opening' : 'purchase',
                    'credit_account' => $i === 4 ? '3101' : ($i === 2 ? '1101' : '2101'), 'receipt_date' => now()->subDays($i)->toDateString(),
                    'supplier_name' => $i === 4 ? null : 'Pemasok Sintetis '.chr(65 + $i), 'total_amount' => bcmul((string) $qty, $cost, 2),
                    'notes' => $i === 0 ? 'DO 88123' : null, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('inventory_receipt_items')->insert(['inventory_receipt_id' => $rid, 'product_id' => $p->id, 'quantity' => $qty, 'unit_cost' => $cost, 'subtotal' => bcmul((string) $qty, $cost, 2), 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        // Satu penerimaan sintetis dengan dua barang: tabel Riwayat menampilkan rincian per barang (khusus admin).
        $receiptId = DB::table('inventory_receipts')->insertGetId([
            'receipt_number' => 'RCV-SYN-001', 'receipt_type' => 'purchase', 'credit_account' => '2101', 'receipt_date' => now()->toDateString(),
            'supplier_name' => 'Pemasok Sintetis Beta', 'total_amount' => '1250.00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventory_receipt_items')->insert([
            ['inventory_receipt_id' => $receiptId, 'product_id' => $product, 'quantity' => '10', 'unit_cost' => '100.00', 'subtotal' => '1000.00', 'created_at' => now(), 'updated_at' => now()],
            ['inventory_receipt_id' => $receiptId, 'product_id' => $product, 'quantity' => '2.5', 'unit_cost' => '100.00', 'subtotal' => '250.00', 'created_at' => now(), 'updated_at' => now()],
        ]);
        foreach ([[$cashier, 'cashier'], [$admin, 'admin']] as [$user,$role]) {
            $response = $this->actingAs($user)->get(route('v2.index'))->assertOk()
                ->assertSee('Pratinjau V2 / belum menggantikan produksi')
                ->assertSee('Material Sintetis Alpha')
                ->assertSee('Pelanggan Sintetis Alpha')
                ->assertSee('<script id="numeric-input-guard">', false)
                ->assertSee('id="workspace-sidebar"', false)
                ->assertSee('data-menu-group="operasional"', false)
                ->assertSee('data-task="shift"', false)
                ->assertSee('data-monitoring-task="shift"', false)
                ->assertSee('data-monitoring-task="tagihan"', false)
                ->assertSee('data-monitoring-task="pengiriman"', false)
                ->assertSee('Katalog Produk Material')
                ->assertSee('id="open-payment"', false)
                ->assertSee('id="checkout-confirm-submit"', false)
                ->assertDontSee('Tambah penjualan')
                ->assertSee('HTMLDialogElement.prototype.showModal', false)
                ->assertSee('form-checkout', false)
                ->assertSee('form-collect', false)
                ->assertSee($sale['delivery_number']);
            preg_match('/<script id="pos-monitor-precomputed" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $match);
            $monitor = json_decode(trim($match[1] ?? ''), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($role === 'admin' ? 'Rp 315' : 'Rp 115', $monitor['today']['formatted_revenue']);
            $this->assertSame($role === 'admin' ? 2 : 1, $monitor['7d']['transaction_count']);
            $this->assertSame($role === 'admin' ? '3' : '1', $monitor['7d']['top_products'][0]['formatted_qty']);
            $this->assertStringNotContainsString('(float)', file_get_contents(resource_path('views/v2/pos-monitor.blade.php')));
            if ($role === 'admin') {
                $response->assertSee('data-default-task="ringkasan"', false)
                    ->assertSee('id="admin-overview"', false)
                    ->assertSee('data-task="persetujuan"', false)
                    ->assertSee('data-menu-group="keuangan"', false)
                    ->assertSee(route('reports.cash_flow'))
                    ->assertSee('data-task="kasir"', false);
                $response->assertSee('data-menu-group="pengawasan"', false)
                    ->assertSee('data-monitoring-task="persetujuan"', false)
                    ->assertSee('data-monitoring-task="penerimaan"', false)
                    ->assertSee('data-menu-group="master"', false)
                    ->assertSee('data-task="akun"', false)
                    ->assertDontSee('data-menu-group="akses"', false);
                // Menu gabungan: setiap panel tetap bisa dicapai lewat tautan sidebar atau tab di dalam menu.
                $this->assertEqualsCanonicalizing(
                    ['ringkasan', 'persetujuan', 'akun', 'kasir', 'tagihan', 'pengiriman', 'penerimaan', 'shift', 'laporan', 'master-product', 'master-supplier', 'master-customer', 'master-employee', 'master-vehicle', 'master-unit', 'master-category', 'jurnal', 'neraca', 'laba-rugi', 'arus-kas', 'bagan-akun'],
                    $this->reachableTasks($response->getContent())
                );
                // Supplier didahulukan karena pelanggan bersifat opsional saat penjualan.
                preg_match('/data-tabs="([^"]*master-supplier[^"]*)"/', $response->getContent(), $supplierTabs);
                $this->assertLessThan(strpos($supplierTabs[1], 'master-customer'), strpos($supplierTabs[1], 'master-supplier'));
                // Aturan selimut panel tidak boleh mengenai dialog: margin:0 membuat popup menempel ke pojok kiri atas.
                $response->assertSee('.task-panel:not(#panel-kasir)>:not(dialog){', false)
                    ->assertDontSee('.task-panel:not(#panel-kasir)>*{', false);

                // Menu keuangan dimuat ke panel lewat fetch, bukan ikut muatan awal halaman.
                $response->assertSee('data-remote="'.route('journals.index').'"', false)
                    ->assertSee('data-task="bagan-akun"', false)
                    ->assertDontSee('id="coa-form"', false);
                $response->assertSee('id="master-section-staff"', false)
                    ->assertSee('name="supplier_ids[]"', false)
                    ->assertSee('name="unit_id"', false)
                    ->assertSee('name="category_id"', false)
                    ->assertSee('data-pick-placeholder="Cari nama atau SKU barang"', false)
                    ->assertSee(str_replace('/', '\/', route('v2.suppliers.products', ['id' => 0])), false)
                    ->assertDontSee('data-suppliers=', false)
                    ->assertSee('data-driver="'.$driver.'"', false)
                    ->assertSee('Sopir Sintetis Beta');
                $response->assertSee('class="form-approve"', false)->assertDontSee('id="master-kind"', false);
                foreach (['product', 'customer', 'supplier', 'employee', 'vehicle'] as $kind) {
                    $response->assertSee('id="master-section-'.$kind.'"', false)->assertSee('id="popup-add-'.$kind.'"', false);
                }
            } else {
                $response->assertDontSee('class="form-approve"', false)
                    ->assertSee('data-default-task="kasir"', false)
                    ->assertSee('Shift saya')
                    ->assertSee('Laporan saya')
                    ->assertDontSee('id="admin-overview"', false)
                    ->assertDontSee('data-task="persetujuan"', false)
                    ->assertDontSee('data-task="keuangan"', false)
                    ->assertDontSee('data-task="akun"', false);
                $response->assertDontSee('data-menu-group="master"', false)
                    ->assertDontSee('data-monitoring-task="persetujuan"', false)
                    ->assertDontSee('data-monitoring-task="penerimaan"', false)
                    ->assertDontSee('data-menu-group="akses"', false)
                    ->assertDontSee('data-menu-group="pengawasan"', false);
                $this->assertEqualsCanonicalizing(['kasir', 'tagihan', 'pengiriman', 'shift', 'laporan'], $this->reachableTasks($response->getContent()));
                $response->assertDontSee('data-remote=', false);
            }
            $response->assertSee('name="driver_id"', false)->assertDontSee('name="driver_name"', false);
            if ($role === 'admin') {
                // Riwayat penerimaan per barang: kolom rancangan, dua barang dalam satu nota, qty desimal tanpa nol berlebih.
                $response->assertSeeInOrder(['Nomor dan pemasok', 'Produk', 'Tipe', 'Qty', 'HPP satuan', 'Total'])
                    ->assertSee('RCV-SYN-001')->assertSee('Pemasok: Pemasok Sintetis Beta')
                    ->assertSee('2,5 <small>unit</small>', false)->assertSee('Akun 2101 · Utang supplier')
                    ->assertSee('data-ui-noun="penerimaan"', false);
            }
            $previewDir = getenv('V2_PREVIEW_DIR');
            if ($previewDir) {
                if (! is_dir($previewDir)) {
                    mkdir($previewDir, 0700, true);
                }
                // Static synthetic fixture only. Network mutations are blocked by the preview server.
                file_put_contents($previewDir.'/'.$role.'.html', $response->getContent());
            }
        }
    }

    /** Panel yang bisa dibuka dari sidebar: data-task langsung plus tab di menu gabungan (data-tabs). */
    private function reachableTasks(string $html): array
    {
        preg_match_all('/data-task="([^"]+)"/', $html, $direct);
        preg_match_all('/data-tabs="([^"]+)"/', $html, $tabs);
        $keys = $direct[1];
        foreach ($tabs[1] as $json) {
            $keys = array_merge($keys, array_keys(json_decode(html_entity_decode($json), true, flags: JSON_THROW_ON_ERROR)));
        }

        return array_values(array_unique($keys));
    }
}
