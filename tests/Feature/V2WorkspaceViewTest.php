<?php

namespace Tests\Feature;

use App\Models\User;
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
        $ops = app(Operations::class);
        $ops->openShift($cashier->id, '100', 'admin');
        $sale = $ops->checkout($cashier->id, ['items' => [['product_id' => $product, 'quantity' => '1']], 'delivery_fee' => '15', 'payment_method' => 'transfer', 'paid_amount' => '115', 'customer_id' => $customer, 'fulfillment' => 'delivery', 'destination' => 'Zona Sintetis A', 'idempotency_key' => 'synthetic-preview-sale-001']);
        $otherCashier = User::factory()->create(['name' => 'Kasir Sintetis Gamma']);
        DB::table('v2_user_roles')->insert(['user_id' => $otherCashier->id, 'role' => 'cashier', 'created_at' => now(), 'updated_at' => now()]);
        $ops->openShift($otherCashier->id, '100', 'admin');
        $ops->checkout($otherCashier->id, ['items' => [['product_id' => $product, 'quantity' => '2']], 'delivery_fee' => '0', 'payment_method' => 'cash', 'paid_amount' => '200', 'fulfillment' => 'pickup', 'idempotency_key' => 'synthetic-other-cashier-sale']);
        foreach ([[$cashier, 'cashier'], [$admin, 'admin']] as [$user,$role]) {
            $response = $this->actingAs($user)->get(route('v2.index'))->assertOk()
                ->assertSee('Pratinjau V2 / belum menggantikan produksi')
                ->assertSee('Material Sintetis Alpha')
                ->assertSee('Pelanggan Sintetis Alpha')
                ->assertSee('id="workspace-sidebar"', false)
                ->assertSee('aria-orientation', false)
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
            $this->assertSame($role === 'admin' ? 'Rp 315,00' : 'Rp 115,00', $monitor['today']['formatted_revenue']);
            $this->assertSame($role === 'admin' ? 2 : 1, $monitor['7d']['transaction_count']);
            $this->assertSame($role === 'admin' ? '3' : '1', $monitor['7d']['top_products'][0]['formatted_qty']);
            $this->assertStringNotContainsString('(float)', file_get_contents(resource_path('views/v2/pos-monitor.blade.php')));
            if ($role === 'admin') {
                $response->assertSee('data-default-task="ringkasan"', false)
                    ->assertSee('id="admin-overview"', false)
                    ->assertSee('data-task="persetujuan"', false)
                    ->assertSee('data-task="keuangan"', false)
                    ->assertSee(route('reports.cash_flow'))
                    ->assertSee('data-task="kasir"', false);
                $response->assertSee('class="form-approve"', false)->assertDontSee('id="master-kind"', false);
                foreach (['product', 'customer', 'supplier', 'employee', 'vehicle'] as $kind) {
                    $response->assertSee('data-task="master-'.$kind.'"', false)->assertSee('id="master-section-'.$kind.'"', false)->assertSee('id="popup-add-'.$kind.'"', false);
                }
            } else {
                $response->assertDontSee('class="form-approve"', false)
                    ->assertSee('data-default-task="kasir"', false)
                    ->assertSee('Shift Saya')
                    ->assertSee('Laporan Saya')
                    ->assertDontSee('id="admin-overview"', false)
                    ->assertDontSee('data-task="persetujuan"', false)
                    ->assertDontSee('data-task="keuangan"', false)
                    ->assertDontSee('data-task="akun"', false);
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
}
