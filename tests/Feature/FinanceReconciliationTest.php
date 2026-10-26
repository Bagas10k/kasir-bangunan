<?php

namespace Tests\Feature;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class FinanceReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = DB::connection();

        if (
            config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || $connection->getDriverName() !== 'sqlite'
            || $connection->getDatabaseName() !== ':memory:'
        ) {
            throw new RuntimeException('Finance reconciliation may only mutate isolated SQLite :memory:.');
        }
    }

    public function test_finance_flow_reconciles_opening_receipt_sale_ritase_journals_and_reports(): void
    {
        $today = now()->toDateString();
        $openingCash = BigDecimal::of('100000.11')->toScale(2);
        $openingBank = BigDecimal::of('50000.22')->toScale(2);
        $receiptQuantity = BigDecimal::of('3.0000');
        $unitCost = BigDecimal::of('10000.1234');
        $saleQuantity = BigDecimal::of('1.5000');
        $sellingPrice = BigDecimal::of('20000.25');
        $fuelCost = BigDecimal::of('1234.56')->toScale(2);
        $driverAllowance = BigDecimal::of('765.44')->toScale(2);
        $receiptTotal = $receiptQuantity->multipliedBy($unitCost)->toScale(2, RoundingMode::HalfUp);
        $saleTotal = $saleQuantity->multipliedBy($sellingPrice)->toScale(2, RoundingMode::HalfUp);
        $cogsTotal = $saleQuantity->multipliedBy($unitCost)->toScale(2, RoundingMode::HalfUp);
        $ritaseTotal = $fuelCost->plus($driverAllowance)->toScale(2);
        $netProfit = $saleTotal->minus($cogsTotal)->minus($ritaseTotal)->toScale(2);
        $expectedCash = $openingCash->minus($receiptTotal)->plus($saleTotal)->minus($ritaseTotal)->toScale(2);
        $expectedInventory = $receiptTotal->minus($cogsTotal)->toScale(2);
        $expectedAssets = $expectedCash->plus($openingBank)->plus($expectedInventory)->toScale(2);
        $expectedEquity = $openingCash->plus($openingBank)->plus($netProfit)->toScale(2);
        $assertMoney = function (BigDecimal $expected, mixed $actual, string $label): void {
            $actualMoney = BigDecimal::of((string) $actual)->toScale(2);
            $this->assertTrue(
                $expected->toScale(2)->isEqualTo($actualMoney),
                "{$label}: expected {$expected->toScale(2)}, got {$actualMoney}."
            );
        };

        $user = User::create([
            'name' => 'Synthetic Finance Operator',
            'email' => 'finance-reconciliation@example.test',
            'password' => bcrypt('synthetic-test-password'),
        ]);
        $productId = DB::table('products')->insertGetId([
            'sku' => 'SYN-FIN-E2E-01',
            'name' => 'Synthetic Finance Material',
            'unit' => 'unit',
            'cost_price' => '0.0000',
            'selling_price' => (string) $sellingPrice,
            'stock_quantity' => '0.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $vehicleId = DB::table('vehicles')->insertGetId([
            'license_plate' => 'B 4242 SYN',
            'vehicle_type' => 'truck',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->post(route('journals.opening.save'), [
            'transaction_date' => $today,
            'cash_amount' => (string) $openingCash,
            'bank_amount' => (string) $openingBank,
        ])->assertRedirect(route('journals.index'));
        $this->actingAs($user)->post(route('journals.opening.post'))
            ->assertRedirect(route('journals.index'));

        $receipt = $this->actingAs($user)->postJson(route('inventory.receipts.store'), [
            'receipt_type' => 'purchase',
            'credit_account' => '1101',
            'product_id' => $productId,
            'quantity' => (string) $receiptQuantity,
            'unit_cost' => (string) $unitCost,
            'supplier_name' => 'Synthetic Supplier',
            'receipt_date' => $today,
            'notes' => 'Synthetic end-to-end receipt',
        ])->assertCreated()->assertJsonPath('success', true);
        $assertMoney($receiptTotal, $receipt->json('receipt.total_amount'), 'Receipt total');

        $sale = $this->actingAs($user)->postJson(route('pos.checkout'), [
            'items' => [['product_id' => $productId, 'quantity' => (string) $saleQuantity]],
            'payment_method' => 'cash',
            'paid_amount' => (string) $saleTotal,
            'customer_type' => 'retail',
        ])->assertOk();
        $assertMoney($saleTotal, $sale->json('total_amount'), 'Sale total');
        $assertMoney($cogsTotal, $sale->json('cogs_amount'), 'Sale COGS');

        $ritase = $this->actingAs($user)->postJson(route('ritase.store'), [
            'vehicle_id' => $vehicleId,
            'sale_id' => $sale->json('sale_id'),
            'driver_name' => 'Synthetic Driver',
            'destination' => 'Synthetic Destination',
            'cargo_summary' => 'Synthetic Finance Material',
            'fuel_cost' => (string) $fuelCost,
            'driver_allowance' => (string) $driverAllowance,
        ])->assertOk();
        $assertMoney($ritaseTotal, $ritase->json('total_cost'), 'Ritase total');

        $journalResponse = $this->actingAs($user)->getJson(route('journals.feed', ['after_id' => 0]))
            ->assertOk();
        $entries = $journalResponse->json('entries');
        $this->assertCount(4, $entries);
        $this->assertSame(
            ['inventory_receipt', 'opening_balance', 'ritase', 'sale'],
            collect($entries)->pluck('reference_type')->sort()->values()->all()
        );
        foreach ($entries as $entry) {
            $debit = BigDecimal::zero();
            $credit = BigDecimal::zero();
            $this->assertNotEmpty($entry['lines'], "Journal {$entry['id']} must contain lines.");
            foreach ($entry['lines'] as $line) {
                $debit = $debit->plus(BigDecimal::of((string) $line['debit']));
                $credit = $credit->plus(BigDecimal::of((string) $line['credit']));
            }
            $this->assertTrue(
                $debit->isEqualTo($credit),
                "Journal {$entry['id']} is unbalanced: debit {$debit}, credit {$credit}."
            );
        }

        $profitLoss = $this->actingAs($user)->getJson(route('reports.profit_loss', [
            'start_date' => $today,
            'end_date' => $today,
        ]))->assertOk();
        $assertMoney($saleTotal, $profitLoss->json('total_revenue'), 'Report revenue');
        $assertMoney($cogsTotal, $profitLoss->json('total_cogs'), 'Report COGS');
        $assertMoney($ritaseTotal, $profitLoss->json('total_expense'), 'Report expense');
        $assertMoney($netProfit, $profitLoss->json('net_profit'), 'Report net profit');

        $balanceSheet = $this->actingAs($user)->getJson(route('reports.balance_sheet', [
            'as_of_date' => $today,
        ]))->assertOk()->assertJsonPath('is_balanced', true);
        $assertMoney($expectedAssets, $balanceSheet->json('total_assets'), 'Report assets');
        $assertMoney($expectedEquity, $balanceSheet->json('total_liabilities_and_equity'), 'Report liabilities and equity');
        $assertMoney($expectedAssets, $expectedEquity, 'Accounting equation');
    }
}
