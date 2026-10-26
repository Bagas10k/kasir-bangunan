<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PosWorkspaceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('POS workspace tests require isolated SQLite :memory:.');
        }
    }

    public function test_pos_exposes_viewport_workspace_and_review_then_print_contract(): void
    {
        $html = $this->actingAs(User::factory()->create())->get(route('pos.index'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('class="pos-workspace"', $html);
        $this->assertStringContainsString('class="pos-catalog-pane', $html);
        $this->assertStringContainsString('class="pos-cart-pane', $html);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr) minmax(360px, 400px)', $html);
        $this->assertStringContainsString('class="card pos-recent-card pos-history-full"', $html);
        $this->assertStringContainsString('class="monitor-grid"', $html);
        // Catalog pane (left) comes before cart pane (right) in HTML order
        $this->assertLessThan(strpos($html, 'class="pos-cart-pane'), strpos($html, 'class="pos-catalog-pane'));
        // Dashboard appears inside catalog pane
        $this->assertLessThan(strpos($html, '<section class="dash-card sales-dashboard-card"'), strpos($html, 'class="pos-catalog-pane'));
        $this->assertLessThan(strpos($html, '<div class="dash-label">Produk Unggulan</div>'), strpos($html, '<section class="dash-card sales-dashboard-card"'));
        $this->assertLessThan(strpos($html, '<div class="dash-label">Ringkasan Produk</div>'), strpos($html, '<div class="dash-label">Produk Unggulan</div>'));
        $this->assertLessThan(strpos($html, '<div class="card pos-catalog-card">'), strpos($html, '<div class="dash-label">Ringkasan Produk</div>'));
        $this->assertLessThan(strpos($html, 'class="card pos-recent-card pos-history-full"'), strpos($html, '<div class="card pos-catalog-card">'));
        $this->assertStringContainsString('Tinjau &amp; Konfirmasi', $html);
        $this->assertStringContainsString('Konfirmasi &amp; Simpan Transaksi', $html);
        $this->assertStringContainsString('Cetak Struk', $html);
        $this->assertStringNotContainsString('Konfirmasi &amp; Cetak Struk', $html);
        $this->assertStringContainsString('let checkoutSubmitting = false;', $html);
        $this->assertStringContainsString('id="receipt-print"', $html);
        $this->assertStringContainsString('hidden disabled', $html);
        $this->assertStringContainsString("if (clearCart) {\n                localStorage.removeItem(CART_KEY);", $html);
        $this->assertStringContainsString('openReceipt(receiptId, true);', $html);
        $this->assertStringNotContainsString("if (receiptId) {\n        localStorage.removeItem(CART_KEY);", $html);
    }

    public function test_sales_history_filters_method_customer_and_date_range(): void
    {
        $user = User::factory()->create();
        foreach ([
            ['INV-SYN-001', 'cash', 'retail', '2026-10-01 09:00:00'],
            ['INV-SYN-002', 'transfer', 'contractor', '2026-10-15 09:00:00'],
            ['INV-SYN-003', 'credit', 'contractor', '2026-10-20 09:00:00'],
        ] as [$invoice, $method, $customer, $created]) {
            DB::table('sales')->insert([
                'invoice_number' => $invoice, 'customer_type' => $customer,
                'payment_method' => $method, 'total_amount' => '100.00',
                'cogs_amount' => '50.00', 'paid_amount' => '100.00', 'change_amount' => '0.00',
                'created_at' => $created, 'updated_at' => $created,
            ]);
        }

        $this->actingAs($user)->getJson(route('pos.sales.history', [
            'payment_method' => 'transfer', 'customer_type' => 'contractor',
            'start_date' => '2026-10-10', 'end_date' => '2026-10-18',
        ]))->assertOk()->assertJsonPath('pagination.total', 1)
          ->assertJsonPath('data.0.invoice_number', 'INV-SYN-002');
    }

    public function test_sales_history_rejects_invalid_filter_shapes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson(route('pos.sales.history', ['payment_method' => 'cheque']))
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->actingAs($user)->getJson(route('pos.sales.history', ['search' => ['nested']]))
            ->assertUnprocessable()->assertJsonValidationErrors('search');
        $this->actingAs($user)->getJson(route('pos.sales.history', [
            'start_date' => '2026-10-20', 'end_date' => '2026-10-01',
        ]))->assertUnprocessable()->assertJsonValidationErrors('end_date');
    }
}
