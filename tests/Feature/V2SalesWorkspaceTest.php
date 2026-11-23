<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2SalesWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Sales workspace fixtures require isolated SQLite :memory:.');
        }
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function sale(User $admin, User $cashier, string $key, string $method = 'credit', string $paid = '0'): array
    {
        foreach ([['1101', 'Kas', 'asset', 'debit'], ['1102', 'Bank', 'asset', 'debit'], ['1103', 'Piutang', 'asset', 'debit'], ['1301', 'Persediaan', 'asset', 'debit'], ['4101', 'Penjualan', 'revenue', 'credit'], ['5101', 'HPP', 'expense', 'debit']] as [$code,$name,$type,$normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore(['account_code' => $code, 'account_name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'is_active' => true]);
        }
        $product = DB::table('products')->insertGetId(['sku' => 'SYN-'.$key, 'name' => 'Material Sintetis '.$key, 'unit' => 'sak', 'category' => 'Umum', 'cost_price' => '60', 'selling_price' => '100', 'stock_quantity' => '10', 'min_stock' => '1', 'is_active' => true]);
        $customer = DB::table('v2_parties')->insertGetId(['kind' => 'customer', 'code' => 'CUS-'.$key, 'name' => 'Pembeli Sintetis '.$key, 'is_active' => true]);
        $ops = app(Operations::class);
        if (! $ops->activeStoreShift()) {
            $ops->openShift($admin->id, '1000', 'admin');
        }

        return $ops->checkout($cashier->id, ['items' => [['product_id' => $product, 'quantity' => '1']], 'customer_id' => $customer, 'payment_method' => $method, 'deposit_method' => 'cash', 'paid_amount' => $paid, 'delivery_fee' => '0', 'fulfillment' => 'pickup', 'idempotency_key' => 'synthetic-sale-'.$key]);
    }

    public function test_cashier_has_a_separate_history_menu_and_only_five_recent_names(): void
    {
        $admin = $this->actor('admin');
        $cashier = $this->actor('cashier');
        for ($i = 0; $i < 7; $i++) {
            $this->sale($admin, $cashier, 'list'.$i);
        }
        $response = $this->actingAs($cashier)->get(route('v2.index'))->assertOk();
        $response->assertSee('data-task="riwayat-penjualan"', false);
        $this->assertCount(5, $response->viewData('recentSales'));
        $this->assertSame(7, $response->viewData('historySales')->total());
        $html = $response->getContent();
        preg_match('/<section[^>]*id="recent-sales"[^>]*>(.*?)<\/section>/s', $html, $recent);
        $this->assertStringContainsString('Pembeli Sintetis list6', $recent[1] ?? '');
        $this->assertStringNotContainsString('Pembeli Sintetis list0', $recent[1] ?? '');
        $this->assertStringNotContainsString('<table', $recent[1] ?? '');
        $this->assertStringContainsString('data-sale-detail=', $recent[1] ?? '');
    }

    public function test_history_combines_customer_search_outstanding_balance_and_pending_transfer_without_duplicate_sales(): void
    {
        $admin = $this->actor('admin');
        $cashier = $this->actor('cashier');
        $sale = $this->sale($admin, $cashier, 'cicilan', paid: '20');
        $this->sale($admin, $cashier, 'lunas', 'cash', '100');
        app(Operations::class)->collect($cashier->id, $sale['invoice_id'], '30', 'transfer', 'synthetic-installment-pending');
        $response = $this->actingAs($cashier)->getJson(route('v2.sales.history', ['sale_search' => 'Pembeli Sintetis cicilan', 'sale_status' => 'unpaid', 'start_date' => '', 'end_date' => '']))->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('Pembeli Sintetis cicilan', $html);
        $this->assertStringContainsString('Rp 80', $html);
        $this->assertStringContainsString('Menunggu verifikasi', $html);
        $this->assertStringNotContainsString('Pembeli Sintetis lunas', $html);
        $this->assertSame(1, substr_count($html, 'data-history-sale='));
        $this->assertDatabaseCount('v2_sales', 2);
    }

    public function test_shared_sale_detail_has_items_installments_and_print_with_current_balance(): void
    {
        $admin = $this->actor('admin');
        $cashier = $this->actor('cashier');
        $sale = $this->sale($admin, $admin, 'detail', paid: '20');
        app(Operations::class)->collect($cashier->id, $sale['invoice_id'], '30', 'cash', 'synthetic-cash-installment');
        $this->actingAs($cashier)->getJson(route('v2.sales.detail', $sale['sale_id']))->assertOk()
            ->assertJsonPath('sale_id', $sale['sale_id']);
        $html = $this->getJson(route('v2.sales.detail', $sale['sale_id']))->json('html');
        foreach (['Material Sintetis detail', 'Riwayat pembayaran', 'Rp 50', 'Catat pembayaran', 'Cetak struk/nota', route('v2.documents', ['id' => $sale['sale_id'], 'kind' => 'receipt'])] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringContainsString('id="amt-'.$sale['invoice_id'].'-sale"', $html);
        $this->actingAs(User::factory()->create())->getJson(route('v2.sales.detail', $sale['sale_id']))->assertForbidden();
    }
}
