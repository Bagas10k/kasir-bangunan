<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2HistoryFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('V2 tests require isolated SQLite :memory:.');
        }
    }

    private function setupBaseData(): array
    {
        $cashier1 = User::factory()->create(['name' => 'Cashier Alpha']);
        $cashier2 = User::factory()->create(['name' => 'Cashier Beta']);
        $admin = User::factory()->create(['name' => 'Admin Boss']);

        foreach ([[$cashier1, 'cashier'], [$cashier2, 'cashier'], [$admin, 'admin']] as [$user, $role]) {
            DB::table('v2_user_roles')->insert([
                'user_id' => $user->id,
                'role' => $role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ([
            ['1101', 'Kas', 'asset', 'debit'],
            ['1102', 'Bank', 'asset', 'debit'],
            ['1103', 'Piutang', 'asset', 'debit'],
            ['1301', 'Persediaan', 'asset', 'debit'],
            ['2101', 'Utang', 'liability', 'credit'],
            ['4101', 'Penjualan', 'revenue', 'credit'],
            ['4102', 'Ongkir', 'revenue', 'credit'],
            ['5101', 'HPP', 'expense', 'debit'],
        ] as [$code, $name, $type, $normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore([
                'account_code' => $code,
                'account_name' => $name,
                'account_type' => $type,
                'normal_balance' => $normal,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $product = DB::table('products')->insertGetId([
            'sku' => 'SYN-PROD-001',
            'name' => 'Material Sintetis Gamma',
            'unit' => 'sak',
            'category' => 'Material',
            'cost_price' => '50000.0000',
            'selling_price' => '65000.00',
            'stock_quantity' => '1000',
            'min_stock' => '10',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ops = app(Operations::class);
        $shift1 = $ops->openShift($cashier1->id, '100000', 'admin');
        $shift2 = $ops->openShift($cashier2->id, '100000', 'admin');

        return compact('cashier1', 'cashier2', 'admin', 'shift1', 'shift2', 'product');
    }

    private function insertSale(int $userId, int $shiftId, string $number, string $method, string $createdAt, string $total = '65000.00'): int
    {
        return DB::table('v2_sales')->insertGetId([
            'number' => $number,
            'user_id' => $userId,
            'shift_id' => $shiftId,
            'subtotal' => $total,
            'line_discount' => 0,
            'invoice_discount' => 0,
            'delivery_fee' => 0,
            'total' => $total,
            'cogs' => '50000.00',
            'tendered' => $total,
            'change_amount' => 0,
            'request_hash' => hash('sha256', $number),
            'payment_method' => $method,
            'status' => 'paid',
            'fulfillment' => 'pickup',
            'idempotency_key' => 'idem-' . $number,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function test_validation_guards_against_arrays_and_malformed_inputs(): void
    {
        $data = $this->setupBaseData();
        $cashier = $data['cashier1'];

        // Array/nested inputs fail 422
        $this->actingAs($cashier)->get(route('v2.index', ['sale_search' => ['sub' => '1']]))->assertStatus(422);
        $this->actingAs($cashier)->get(route('v2.index', ['sale_method' => ['cash']]))->assertStatus(422);
        $this->actingAs($cashier)->get(route('v2.index', ['start_date' => ['2026-10-01']]))->assertStatus(422);
        $this->actingAs($cashier)->get(route('v2.index', ['end_date' => ['2026-10-30']]))->assertStatus(422);
        $this->actingAs($cashier)->get(route('v2.index', ['history_per_page' => [10]]))->assertStatus(422);
        $this->actingAs($cashier)->get(route('v2.index', ['history_page' => [1]]))->assertStatus(422);

        // Invalid values
        $this->actingAs($cashier)->get(route('v2.index', ['sale_search' => str_repeat('a', 101)]))->assertStatus(422);
        $this->actingAs($cashier)->get(route('v2.index', ['sale_method' => 'bitcoin']))->assertStatus(422);
        $this->actingAs($cashier)->get(route('v2.index', ['start_date' => 'invalid-date']))->assertStatus(422);
        $this->actingAs($cashier)->get(route('v2.index', ['end_date' => '2026-02-31']))->assertStatus(422);
        $this->actingAs($cashier)->get(route('v2.index', ['start_date' => '2026-10-20', 'end_date' => '2026-10-10']))->assertStatus(422);
        $this->actingAs($cashier)->get(route('v2.index', ['history_per_page' => '15']))->assertStatus(422);
        $this->actingAs($cashier)->get(route('v2.index', ['history_page' => '0']))->assertStatus(422);
        $this->actingAs($cashier)->get(route('v2.index', ['history_page' => '-3']))->assertStatus(422);
        $this->actingAs($cashier)->get(route('v2.index', ['history_page' => 'abc']))->assertStatus(422);

        // End-only date is valid
        $this->actingAs($cashier)->get(route('v2.index', ['end_date' => '2026-10-30']))->assertOk();
    }

    public function test_history_sales_queries_whole_v2_sales_before_pagination_and_preserves_latest30(): void
    {
        $data = $this->setupBaseData();
        $cashier = $data['cashier1'];

        // Insert 35 sales for Cashier 1 spanning dates
        for ($i = 1; $i <= 35; $i++) {
            $num = sprintf('SL-C1-%03d', $i);
            $day = str_pad((string)(($i % 28) + 1), 2, '0', STR_PAD_LEFT);
            $createdAt = "2026-10-{$day} 10:00:00";
            $method = $i % 2 === 0 ? 'cash' : 'transfer';
            $this->insertSale($cashier->id, $data['shift1'], $num, $method, $createdAt);
        }

        $res = $this->actingAs($cashier)->get(route('v2.index'));
        $res->assertOk();

        /** @var LengthAwarePaginator $historySales */
        $historySales = $res->viewData('historySales');
        $this->assertInstanceOf(LengthAwarePaginator::class, $historySales);
        $this->assertSame(35, $historySales->total());
        $this->assertSame(10, $historySales->perPage());
        $this->assertSame(10, $historySales->count());
        $this->assertSame(4, $historySales->lastPage());
        $this->assertSame('kasir', $historySales->fragment());
        $this->assertStringContainsString('#kasir', $historySales->url(1));

        // Search the oldest sale (#001) which would be excluded if limited to 30 first
        $searchRes = $this->actingAs($cashier)->get(route('v2.index', ['sale_search' => 'SL-C1-001']));
        $searchRes->assertOk();
        /** @var LengthAwarePaginator $searchedHistory */
        $searchedHistory = $searchRes->viewData('historySales');
        $this->assertSame(1, $searchedHistory->total());
        $this->assertSame('SL-C1-001', $searchedHistory->items()[0]->number);

        // Test custom history_per_page
        $res25 = $this->actingAs($cashier)->get(route('v2.index', ['history_per_page' => '25']));
        $res25->assertOk();
        $h25 = $res25->viewData('historySales');
        $this->assertSame(25, $h25->perPage());
        $this->assertSame(25, $h25->count());
        $this->assertSame(2, $h25->lastPage());

        // Existing $sales remains exactly 30
        $recentSales = $res->viewData('sales');
        $this->assertCount(30, $recentSales);
    }

    public function test_role_scoping_cashier_excludes_other_cashier_admin_shows_all(): void
    {
        $data = $this->setupBaseData();
        $c1 = $data['cashier1'];
        $c2 = $data['cashier2'];
        $admin = $data['admin'];

        $this->insertSale($c1->id, $data['shift1'], 'SL-C1-X1', 'cash', '2026-10-15 10:00:00');
        $this->insertSale($c1->id, $data['shift1'], 'SL-C1-X2', 'cash', '2026-10-16 10:00:00');
        $this->insertSale($c2->id, $data['shift2'], 'SL-C2-Y1', 'transfer', '2026-10-15 11:00:00');

        // Cashier 1 sees only their 2 sales
        $resC1 = $this->actingAs($c1)->get(route('v2.index'));
        $resC1->assertOk();
        $hC1 = $resC1->viewData('historySales');
        $this->assertSame(2, $hC1->total());
        $numbersC1 = collect($hC1->items())->pluck('number')->all();
        $this->assertContains('SL-C1-X1', $numbersC1);
        $this->assertContains('SL-C1-X2', $numbersC1);
        $this->assertNotContains('SL-C2-Y1', $numbersC1);

        // Admin sees all 3 sales
        $resAdmin = $this->actingAs($admin)->get(route('v2.index'));
        $resAdmin->assertOk();
        $hAdmin = $resAdmin->viewData('historySales');
        $this->assertSame(3, $hAdmin->total());
        $numbersAdmin = collect($hAdmin->items())->pluck('number')->all();
        $this->assertContains('SL-C1-X1', $numbersAdmin);
        $this->assertContains('SL-C1-X2', $numbersAdmin);
        $this->assertContains('SL-C2-Y1', $numbersAdmin);
    }

    public function test_filters_independent_and_combined_and_reset(): void
    {
        $data = $this->setupBaseData();
        $cashier = $data['cashier1'];

        $this->insertSale($cashier->id, $data['shift1'], 'SL-FLT-001', 'cash', '2026-10-10 10:00:00');
        $this->insertSale($cashier->id, $data['shift1'], 'SL-FLT-002', 'transfer', '2026-10-12 10:00:00');
        $this->insertSale($cashier->id, $data['shift1'], 'SL-FLT-003', 'credit', '2026-10-15 10:00:00');
        $this->insertSale($cashier->id, $data['shift1'], 'SL-FLT-004', 'cash', '2026-10-20 10:00:00');

        // Filter by method only
        $resCash = $this->actingAs($cashier)->get(route('v2.index', ['sale_method' => 'cash']));
        $this->assertSame(2, $resCash->viewData('historySales')->total());

        // Filter by date range only
        $resDates = $this->actingAs($cashier)->get(route('v2.index', ['start_date' => '2026-10-11', 'end_date' => '2026-10-16']));
        $this->assertSame(2, $resDates->viewData('historySales')->total());

        // Combined: cash + date range
        $resComb = $this->actingAs($cashier)->get(route('v2.index', [
            'sale_method' => 'cash',
            'start_date' => '2026-10-19',
            'end_date' => '2026-10-21',
        ]));
        $this->assertSame(1, $resComb->viewData('historySales')->total());
        $this->assertSame('SL-FLT-004', $resComb->viewData('historySales')->items()[0]->number);

        // Reset: empty strings
        $resReset = $this->actingAs($cashier)->get(route('v2.index', [
            'sale_search' => '',
            'sale_method' => '',
            'start_date' => '',
            'end_date' => '',
        ]));
        $this->assertSame(4, $resReset->viewData('historySales')->total());

        // Normalized historyFilters verification
        $filters = $resComb->viewData('historyFilters');
        $this->assertSame('cash', $filters['sale_method']);
        $this->assertSame('2026-10-19', $filters['start_date']);
        $this->assertSame('2026-10-21', $filters['end_date']);
    }

    public function test_has_delivery_subquery_and_no_duplicate_rows(): void
    {
        $data = $this->setupBaseData();
        $cashier = $data['cashier1'];

        $saleId1 = $this->insertSale($cashier->id, $data['shift1'], 'SL-DELIV-YES', 'cash', '2026-10-25 10:00:00');
        $saleId2 = $this->insertSale($cashier->id, $data['shift1'], 'SL-DELIV-NO', 'cash', '2026-10-25 11:00:00');

        DB::table('v2_deliveries')->insert([
            'sale_id' => $saleId1,
            'number' => 'DLV-001',
            'destination' => 'Alamat Proyek Sentral',
            'status' => 'prepared',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->actingAs($cashier)->get(route('v2.index'));
        $res->assertOk();
        $items = collect($res->viewData('historySales')->items())->keyBy('id');

        $this->assertTrue((bool)$items[$saleId1]->has_delivery);
        $this->assertFalse((bool)$items[$saleId2]->has_delivery);
        $this->assertSame(2, $res->viewData('historySales')->total());
    }

    public function test_sale_search_is_substring_parameterized_and_prevents_sql_injection(): void
    {
        $data = $this->setupBaseData();
        $cashier = $data['cashier1'];

        $this->insertSale($cashier->id, $data['shift1'], 'SL-SUB-TARGET-01', 'cash', '2026-10-25 10:00:00');
        $this->insertSale($cashier->id, $data['shift1'], 'SL-OTHER-99', 'cash', '2026-10-25 10:00:00');

        // Substring match
        $res = $this->actingAs($cashier)->get(route('v2.index', ['sale_search' => 'TARGET']));
        $res->assertOk();
        $this->assertSame(1, $res->viewData('historySales')->total());
        $this->assertSame('SL-SUB-TARGET-01', $res->viewData('historySales')->items()[0]->number);

        // SQL injection probe safely parameterized without error
        $resSql = $this->actingAs($cashier)->get(route('v2.index', ['sale_search' => "' OR '1'='1"]));
        $resSql->assertOk();
        $this->assertSame(0, $resSql->viewData('historySales')->total());
    }
}
