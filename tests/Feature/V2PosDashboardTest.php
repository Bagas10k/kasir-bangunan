<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\DemoData;
use App\Services\V2\Operations;
use App\Services\V2\PosDashboard;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dashboard POS: angka toko yang sama untuk admin/kasir, dengan perhitungan BigDecimal.
 * Dengan V2_PREVIEW_DIR, HTML berisi data sintetis ditulis untuk pemeriksaan visual.
 */
class V2PosDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('RefreshDatabase is only permitted against isolated SQLite :memory:.');
        }
    }

    private function user(string $role, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function sale(User $user, int $shift, Carbon $at, string $total, array $items): int
    {
        static $n = 0;
        $n++;
        $id = DB::table('v2_sales')->insertGetId([
            'number' => 'INV-SYN-'.$n, 'user_id' => $user->id, 'shift_id' => $shift, 'subtotal' => $total, 'total' => $total,
            'tendered' => $total, 'payment_method' => 'cash', 'status' => 'paid', 'fulfillment' => 'pickup',
            'idempotency_key' => 'syn-dash-'.$n, 'request_hash' => str_repeat('a', 64), 'created_at' => $at, 'updated_at' => $at,
        ]);
        foreach ($items as [$product, $name, $qty]) {
            DB::table('v2_sale_items')->insert([
                'sale_id' => $id, 'product_id' => $product, 'name' => $name, 'quantity' => $qty, 'unit_price' => '1000.00',
                'subtotal' => '1000.00', 'created_at' => $at, 'updated_at' => $at,
            ]);
        }

        return $id;
    }

    private function seedHistory(User $admin, User $cashier): void
    {
        app(DemoData::class)->seed($admin->id);
        $semen = (int) DB::table('products')->where('sku', 'PRD-0001')->value('id');
        $besi = (int) DB::table('products')->where('sku', 'PRD-0005')->value('id');
        $pasir = (int) DB::table('products')->where('sku', 'PRD-0010')->value('id');
        // Stok: banyak, menipis (di bawah minimum), habis.
        DB::table('products')->where('id', $semen)->update(['stock_quantity' => '240.0000', 'min_stock' => '50.0000']);
        DB::table('products')->where('id', $besi)->update(['stock_quantity' => '12.0000', 'min_stock' => '30.0000']);
        DB::table('products')->where('id', $pasir)->update(['stock_quantity' => '0.0000', 'min_stock' => '10.0000']);
        foreach (DB::table('products')->whereNotIn('id', [$semen, $besi, $pasir])->pluck('id') as $i => $id) {
            DB::table('products')->where('id', $id)->update(['stock_quantity' => (string) (20 + $i * 7).'.0000', 'min_stock' => '0.0000']);
        }

        $shift = DB::table('v2_shifts')->insertGetId([
            'user_id' => $admin->id, 'open_user_id' => $admin->id, 'opening_cash' => '0.00', 'status' => 'open', 'source' => 'admin',
            'is_shared' => true, 'store_open_key' => 1,
            'opened_at' => now()->subDays(60), 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Barang lain ikut terjual supaya kartu Produk terlaris terisi penuh (10 besar) di pratinjau.
        $others = DB::table('products')->whereIn('sku', ['PRD-0026', 'PRD-0021', 'PRD-0013', 'PRD-0022', 'PRD-0020', 'PRD-0023', 'PRD-0025', 'PRD-0006', 'PRD-0012'])->orderBy('sku')->get(['id', 'name'])->values();

        $now = now();
        // 45 hari penjualan; hari ini ada beberapa nota dari jam berbeda.
        for ($d = 44; $d >= 1; $d--) {
            $day = $now->copy()->subDays($d)->setTime(9, 15);
            $count = 2 + ($d % 5);
            for ($k = 0; $k < $count; $k++) {
                $amount = (string) (350000 + (($d * 97 + $k * 53) % 40) * 31000).'.00';
                $this->sale($k % 3 === 0 ? $cashier : $admin, $shift, $day->copy()->addHours($k * 2), $amount, [[$semen, 'Semen Portland 50 kg', (string) (2 + $k).'.0000'], [$besi, 'Besi Beton Polos 10 mm', '1.0000'], [$others[($d + $k) % $others->count()]->id, $others[($d + $k) % $others->count()]->name, (string) (1 + ($d * 3 + $k) % 4).'.0000']]);
            }
        }
        $this->sale($admin, $shift, $now->copy()->setTime(8, 30), '1250000.00', [[$semen, 'Semen Portland 50 kg', '10.0000']]);
        $this->sale($cashier, $shift, $now->copy()->setTime(9, 5), '480000.00', [[$besi, 'Besi Beton Polos 10 mm', '4.0000']]);
    }

    public function test_service_sums_with_bigdecimal_and_compares_with_previous_period(): void
    {
        $admin = $this->user('admin', 'Admin Sintetis Dash');
        $cashier = $this->user('cashier', 'Kasir Sintetis Dash');
        DB::table('products')->insert(['sku' => 'D-1', 'name' => 'Produk D', 'unit' => 'sak', 'cost_price' => '500.00', 'selling_price' => '1000.00', 'stock_quantity' => '5.0000', 'is_active' => true]);
        $product = (int) DB::table('products')->where('sku', 'D-1')->value('id');
        $shift = DB::table('v2_shifts')->insertGetId(['user_id' => $admin->id, 'open_user_id' => $admin->id, 'opening_cash' => '0.00', 'status' => 'open', 'source' => 'admin', 'opened_at' => now()->subDays(10), 'created_at' => now(), 'updated_at' => now()]);
        $now = Carbon::parse('2026-11-06 12:00:00');

        // Hari ini 0,10 + 0,20 harus 0,30 persis (float menghasilkan 0.30000000000000004).
        $this->sale($admin, $shift, $now->copy()->setTime(9, 0), '0.10', [[$product, 'Produk D', '1.5000']]);
        $this->sale($cashier, $shift, $now->copy()->setTime(10, 0), '0.20', [[$product, 'Produk D', '2.2500']]);
        // Kemarin 1.000,00 sebagai pembanding.
        $this->sale($admin, $shift, $now->copy()->subDay()->setTime(10, 0), '1000.00', []);

        $all = (new PosDashboard)->build(null, $now);
        $this->assertSame('Rp 0,30', $all['today']['formatted_revenue']);
        $this->assertSame(2, $all['today']['transaction_count']);
        $this->assertSame('turun', $all['today']['delta']['direction'], 'Hari ini lebih kecil dari kemarin.');
        $this->assertSame(100, $all['today']['delta']['pct'], '0,30 vs 1.000 dibulatkan: turun ~100%.');
        $this->assertSame('3,75', $all['today']['top_products'][0]['formatted_qty'], 'Jumlah dijumlah persis: 1,5 + 2,25.');
        $this->assertSame('sak', $all['today']['top_products'][0]['unit']);
        $this->assertCount(24, $all['today']['buckets']);
        $this->assertCount(7, $all['7d']['buckets']);
        $this->assertCount(30, $all['30d']['buckets']);
        $this->assertSame(3, $all['7d']['transaction_count'], 'Tujuh hari = 2 nota hari ini + 1 kemarin.');

        $own = (new PosDashboard)->build($cashier->id, $now);
        $this->assertSame('Rp 0,20', $own['today']['formatted_revenue'], 'Filter petugas eksplisit tetap didukung layanan; dashboard POS memakai data toko.');
        $this->assertSame(1, $own['today']['transaction_count']);
    }

    public function test_empty_history_renders_without_errors_or_division_by_zero(): void
    {
        $data = (new PosDashboard)->build(null, Carbon::parse('2026-11-06 12:00:00'));
        foreach (['today', '7d', '30d'] as $key) {
            $this->assertNull($data[$key]['peak']);
            $this->assertSame('tetap', $data[$key]['delta']['direction']);
            $this->assertSame([], $data[$key]['top_products']);
            $this->assertSame('Rp 0', $data[$key]['formatted_revenue']);
        }
    }

    public function test_cashier_without_personal_sales_sees_the_same_store_dashboard_as_admin(): void
    {
        config(['v2.enabled' => true]);
        $this->freezeTime();
        $admin = $this->user('admin', 'Admin Sintetis Ringkasan');
        $cashier = $this->user('cashier', 'Kasir Sintetis Ringkasan');
        $shift = app(Operations::class)->openShift($admin->id, '0', 'admin');
        $product = DB::table('products')->insertGetId(['sku' => 'SYN-DASH-SHARED', 'name' => 'Material Sintetis Ringkasan', 'unit' => 'unit', 'cost_price' => '500', 'selling_price' => '1000', 'stock_quantity' => '5', 'is_active' => true]);
        $this->sale($admin, $shift, now(), '370000.00', [[$product, 'Material Sintetis Ringkasan', '1']]);

        $adminData = $this->actingAs($admin)->get(route('pos.index'))->assertOk()->viewData('posDashboard');
        $response = $this->actingAs($cashier)->get(route('pos.index'))->assertOk();
        $cashierData = $response->viewData('posDashboard');
        $this->assertSame('370000.00', $cashierData['today']['revenue']);
        $this->assertSame($adminData, $cashierData, 'Periode, KPI, pembanding, grafik, dan produk terlaris berasal dari data toko yang sama.');
        foreach (['today', '7d', '30d'] as $period) {
            $this->assertSame('370000.00', $cashierData[$period]['revenue']);
            $this->assertSame(1, $cashierData[$period]['transaction_count']);
            $this->assertSame('Material Sintetis Ringkasan', $cashierData[$period]['top_products'][0]['name']);
        }
        preg_match('/<script id="pos-monitor-precomputed" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $json);
        $this->assertSame($adminData, json_decode($json[1], true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(0, DB::table('v2_sales')->where('user_id', $cashier->id)->count());
        $this->actingAs($cashier)->getJson(route('v2.accounts.index'))->assertForbidden();
        $this->assertDatabaseHas('v2_sales', ['shift_id' => $shift, 'user_id' => $admin->id, 'total' => '370000.00']);
    }

    public function test_empty_workspace_keeps_summary_chart_and_checkout_structure(): void
    {
        config(['v2.enabled' => true]);
        $admin = $this->user('admin', 'Admin Tanpa Penjualan');
        app(DemoData::class)->seed($admin->id);

        $html = $this->actingAs($admin)->get(route('pos.index'))->assertOk()->getContent();
        $this->assertSame(3, preg_match_all('/class="pd-plot\s+is-empty\s*"/', $html));
        $this->assertStringContainsString('Belum ada penjualan pada periode ini.', $html);
        $this->assertStringContainsString('id="products-tbody"', $html);
        $this->assertStringContainsString('id="form-checkout"', $html);
        $this->assertStringContainsString('id="display-total"', $html);
        $this->assertShiftInDashboardHeader($html, 'closed', 'Shift toko belum dibuka', 'Buka shift toko');
        preg_match('/<form id="form-checkout".*?<\/form>/s', $html, $checkout);
        $this->assertStringNotContainsString('class="pos-shift-notice"', $checkout[0]);
        $this->assertStringContainsString('class="pos-shift-reminder"', $checkout[0]);
        $this->assertStringContainsString('class="cart-column-header"', $html);
        $this->assertStringContainsString('class="cart-column-quantity">Jumlah', $html);
        $this->assertStringContainsString('class="cart-column-subtotal">Subtotal', $html);

        if ($dir = getenv('V2_PREVIEW_DIR')) {
            if (! is_dir($dir)) {
                mkdir($dir, 0700, true);
            }
            file_put_contents($dir.'/pos-empty.html', $html);
        }
    }

    public function test_workspace_renders_dashboard_with_chart_scale_and_stock_alert(): void
    {
        config(['v2.enabled' => true]);
        $admin = $this->user('admin', 'Admin Sintetis Dash');
        $cashier = $this->user('cashier', 'Kasir Sintetis Dash');
        $this->seedHistory($admin, $cashier);

        $html = $this->actingAs($admin)->get(route('pos.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="pos-dashboard"', $html);
        $shift = (int) DB::table('v2_shifts')->where('store_open_key', 1)->value('id');
        $this->assertShiftInDashboardHeader($html, 'open', 'Shift toko #'.$shift.' aktif', 'Lihat shift');
        $this->assertStringNotContainsString('<p class="pos-shift-reminder">', $html);
        $this->assertSame(3, preg_match_all('/<div class="pd-pane" data-pane="/', $html), 'Tiga periode dirender sekaligus.');
        preg_match('/<script id="pos-monitor-precomputed" type="application\/json">(.*?)<\/script>/s', $html, $m);
        $data = json_decode(trim($m[1]), true, flags: JSON_THROW_ON_ERROR);
        $this->assertNotNull($data['30d']['peak']);
        $this->assertGreaterThan(0, $data['30d']['transaction_count']);
        $this->assertStringContainsString('1 habis · 1 menipis', $html, 'Stok habis dan menipis dihitung dari stok minimum tiap produk.');
        $this->assertStringContainsString('data-task-link="master-product"', $html);

        $cashierHtml = $this->actingAs($cashier)->get(route('pos.index'))->assertOk()->getContent();
        $this->assertShiftInDashboardHeader($cashierHtml, 'open', 'Shift toko #'.$shift.' aktif', 'Lihat shift');
        $this->assertStringNotContainsString('data-task-link="master-product"', $cashierHtml, 'Kasir tidak punya Master produk.');

        $dir = getenv('V2_PREVIEW_DIR');
        if ($dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0700, true);
            }
            file_put_contents($dir.'/pos-admin.html', $html);
            file_put_contents($dir.'/pos-cashier.html', $cashierHtml);
            // Pratinjau harga kontraktor: dua produk pertama punya harga kontraktor (hanya DB :memory:, sesudah semua assert).
            foreach (DB::table('products')->orderBy('name')->limit(2)->get(['id', 'selling_price']) as $product) {
                DB::table('products')->where('id', $product->id)->update(['contractor_price' => (string) BigDecimal::of((string) $product->selling_price)->multipliedBy('0.9')->toScale(2, RoundingMode::Down)]);
            }
            file_put_contents($dir.'/pos-contractor.html', $this->actingAs($admin)->get(route('pos.index'))->assertOk()->getContent());
        }
    }

    private function assertShiftInDashboardHeader(string $html, string $state, string $status, string $action): void
    {
        $this->assertSame(1, preg_match('/<section id="kasir"[^>]*>(.*?)<section class="pd" id="pos-dashboard"[^>]*>\s*<header class="pd-head">(.*?)<\/header>/s', $html, $dashboard));
        $this->assertStringNotContainsString('class="pos-shift-notice"', $dashboard[1]);
        $this->assertStringContainsString('Ringkasan penjualan', $dashboard[2]);
        $this->assertMatchesRegularExpression('/<div class="pd-controls">\s*<div class="pos-shift-notice"/', $dashboard[2]);
        $this->assertStringContainsString('data-pos-shift-state="'.$state.'"', $dashboard[2]);
        $this->assertStringContainsString('role="status" aria-live="polite" aria-label="Status shift toko"', $dashboard[2]);
        $this->assertStringContainsString('<strong>'.$status.'</strong>', $dashboard[2]);
        $this->assertStringContainsString('class="pos-shift-action" href="#shift" data-task-link="shift"><span>'.$action.'</span>', $dashboard[2]);
        $this->assertStringNotContainsString('pos-shift-copy', $dashboard[2]);
        $this->assertSame(1, substr_count($html, 'data-pos-shift-state="'));
        $this->assertSame(1, preg_match('/<form id="form-checkout".*?<\/form>/s', $html, $checkout));
        $this->assertStringNotContainsString('class="pos-shift-notice"', $checkout[0]);
        $this->assertStringNotContainsString('class="pos-shift-action"', $checkout[0]);
    }

    public function test_scale_thousands_of_products_and_sales_stay_fast_and_exact(): void
    {
        config(['v2.enabled' => true]);
        $admin = $this->user('admin', 'Admin Sintetis Skala');
        $shift = DB::table('v2_shifts')->insertGetId(['user_id' => $admin->id, 'open_user_id' => $admin->id, 'is_shared' => true, 'store_open_key' => 1, 'opening_cash' => '0.00', 'status' => 'open', 'source' => 'admin', 'opened_at' => now()->subDays(60), 'created_at' => now(), 'updated_at' => now()]);

        $products = [];
        for ($i = 1; $i <= 2500; $i++) {
            $products[] = ['sku' => sprintf('SC-%05d', $i), 'name' => 'Material Skala '.$i, 'unit' => 'sak', 'category' => 'Kelompok '.($i % 12), 'cost_price' => '1000.00', 'selling_price' => '1500.00', 'stock_quantity' => ($i % 9 === 0 ? '0.0000' : (string) ($i % 40).'.0000'), 'min_stock' => '5.0000', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($products, 200) as $chunk) {
            DB::table('products')->insert($chunk);
        }

        $sales = [];
        for ($n = 1; $n <= 5000; $n++) {
            $at = now()->subDays($n % 44)->setTime(8 + ($n % 10), 5);
            $sales[] = ['number' => 'INV-SC-'.$n, 'user_id' => $admin->id, 'shift_id' => $shift, 'subtotal' => '150000.00', 'total' => '150000.00', 'tendered' => '150000.00', 'payment_method' => 'cash', 'status' => 'paid', 'fulfillment' => 'pickup', 'idempotency_key' => 'sc-'.$n, 'request_hash' => str_repeat('b', 64), 'created_at' => $at, 'updated_at' => $at];
        }
        foreach (array_chunk($sales, 200) as $chunk) {
            DB::table('v2_sales')->insert($chunk);
        }
        $ids = DB::table('v2_sales')->orderBy('id')->pluck('created_at', 'id');
        $items = [];
        foreach ($ids as $id => $at) {
            $items[] = ['sale_id' => $id, 'product_id' => 1 + ($id % 2500), 'name' => 'Material Skala', 'quantity' => '2.0000', 'unit_price' => '75000.00', 'subtotal' => '150000.00', 'created_at' => $at, 'updated_at' => $at];
        }
        foreach (array_chunk($items, 200) as $chunk) {
            DB::table('v2_sale_items')->insert($chunk);
        }

        $t0 = microtime(true);
        $data = (new PosDashboard)->build(null);
        $service = microtime(true) - $t0;

        $t1 = microtime(true);
        $response = $this->actingAs($admin)->get(route('pos.index'))->assertOk();
        $page = microtime(true) - $t1;
        $html = $response->getContent();

        fwrite(STDERR, sprintf("\n[skala] dashboard %.0f ms, halaman %.0f ms, HTML %d KB, memori puncak %d MB\n", $service * 1000, $page * 1000, strlen($html) / 1024, memory_get_peak_usage(true) / 1048576));

        if ($dir = getenv('V2_PREVIEW_DIR')) {
            if (! is_dir($dir)) {
                mkdir($dir, 0700, true);
            }
            file_put_contents($dir.'/pos-scale.html', $html);
        }

        // Ketepatan: 5.000 nota x 150.000, hanya 44 hari terisi; 30 hari terakhir dijumlah persis.
        $thirty = 0;
        foreach (range(0, 29) as $d) {
            $thirty += count(array_filter(range(1, 5000), fn ($n) => ($n % 44) === $d));
        }
        $this->assertSame($thirty, $data['30d']['transaction_count']);
        $this->assertSame('Rp '.number_format($thirty * 150000, 0, ',', '.'), $data['30d']['formatted_revenue']);

        // Baris katalog tidak dirender server (dibuat bertahap oleh JS), jadi HTML tidak membengkak.
        $this->assertSame(0, substr_count($html, 'data-add-product='));
        $this->assertLessThan(2_500_000, strlen($html));
        $this->assertLessThan(8.0, $service, 'Ringkasan harus cepat walau transaksi ribuan.');
    }
}
