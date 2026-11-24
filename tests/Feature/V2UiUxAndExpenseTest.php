<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class V2UiUxAndExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Pengujian hanya diuji pada SQLite :memory:.');
        }
    }

    private function actor(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert([
            'user_id' => $user->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    public function test_expense_partial_contains_v2_fields_modal_styles_and_ajax_script(): void
    {
        $admin = $this->actor('admin');

        $html = $this->actingAs($admin)
            ->get(route('expenses.index', ['partial' => 1]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-expense-root', $html);
        $this->assertStringContainsString('name="account_code"', $html);
        $this->assertStringContainsString('name="source"', $html);
        $this->assertStringContainsString('<option value="bank"', $html);
        $this->assertStringContainsString('#modal-expense.modal-overlay', $html);
        $this->assertStringContainsString('expense-operational-script', $html);
        $this->assertStringContainsString('data-tip-key="pengeluaran_operasional"', $html);
    }

    public function test_product_detail_partial_displays_bigdecimal_margin_for_retail_and_contractor(): void
    {
        view()->share('errors', new ViewErrorBag);
        $rp = fn ($v): string => Money::display($v);
        $decimalText = fn ($v): string => rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');

        $record = (object) [
            'id' => 101,
            'sku' => 'PRD-SYN-101',
            'name' => 'Semen Sintetis 50kg',
            'category' => 'Semen',
            'unit' => 'sak',
            'cost_price' => '50000.0000',
            'selling_price' => '62500.00',
            'contractor_price' => '57500.00',
            'stock_quantity' => '15.0000',
            'min_stock' => '5.0000',
            'is_active' => true,
        ];

        $html = view('v2.master-product-detail', [
            'record' => $record,
            'isActive' => true,
            'stock' => 'ok',
            'lockedCostIds' => [101],
            'productSupplierLinks' => [101 => []],
            'rp' => $rp,
            'decimalText' => $decimalText,
        ])->render();

        $this->assertStringContainsString('Margin keuntungan', $html);
        $this->assertStringContainsString('Untung Rp 12.500 (25%)', $html);
        $this->assertStringContainsString('Kontraktor: Untung Rp 7.500 (15%)', $html);
        $this->assertStringContainsString('data-tip-key="harga_modal"', $html);
        $this->assertStringContainsString('data-tip-key="margin_keuntungan"', $html);
    }

    public function test_product_detail_handles_zero_cost_and_negative_margin_without_division_by_zero(): void
    {
        view()->share('errors', new ViewErrorBag);
        $rp = fn ($v): string => Money::display($v);
        $decimalText = fn ($v): string => (string) $v;

        $zeroCost = (object) [
            'id' => 102, 'sku' => 'PRD-SYN-102', 'name' => 'Pasir Sintetis', 'category' => 'Pasir',
            'unit' => 'm3', 'cost_price' => '0.0000', 'selling_price' => '100000.00',
            'contractor_price' => null, 'stock_quantity' => '0.0000', 'min_stock' => '0.0000', 'is_active' => true,
        ];
        $loss = (object) [
            'id' => 103, 'sku' => 'PRD-SYN-103', 'name' => 'Bata Sintetis', 'category' => 'Bata',
            'unit' => 'buah', 'cost_price' => '1000.0000', 'selling_price' => '800.00',
            'contractor_price' => null, 'stock_quantity' => '10.0000', 'min_stock' => '2.0000', 'is_active' => true,
        ];

        $zeroHtml = view('v2.master-product-detail', [
            'record' => $zeroCost, 'isActive' => true, 'stock' => 'out',
            'lockedCostIds' => [], 'productSupplierLinks' => [], 'rp' => $rp, 'decimalText' => $decimalText,
        ])->render();
        $lossHtml = view('v2.master-product-detail', [
            'record' => $loss, 'isActive' => true, 'stock' => 'ok',
            'lockedCostIds' => [], 'productSupplierLinks' => [], 'rp' => $rp, 'decimalText' => $decimalText,
        ])->render();

        $this->assertStringContainsString('Untung Rp 100.000', $zeroHtml);
        $this->assertStringContainsString('Rugi Rp 200 (-20%)', $lossHtml);
    }
}
