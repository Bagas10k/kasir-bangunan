<?php

namespace Tests\Feature;

use App\Services\V2\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Foundation tests require SQLite :memory:.');
        }
    }

    public function test_decimal_rounding_and_goods_discount_use_installed_math_api(): void
    {
        $this->assertSame('123.46', (string) Money::value('123.455'));
        $this->assertSame('60.1234', (string) Money::value('60.1234', 4));
        $this->assertSame('20.00', Money::discount('200.00', ['type'=>'percent','value'=>'10']));
        $this->assertSame('18.00', Money::discount('180.00', ['type'=>'percent','value'=>'10']));
    }

    public function test_money_above_ieee_safe_integer_retains_cents(): void
    {
        $this->assertSame('9999999999999999.99', (string) Money::value('9999999999999999.99'));
    }

    public function test_hpp_over_decimal_19_4_range_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::value('1000000000000000.0000', 4);
    }

    public function test_negative_invoice_is_rejected_by_database_not_just_service(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('v2_invoices')->insert(['direction'=>'receivable','number'=>'SYN-NEGATIVE','amount'=>'-1.00','paid'=>'0.00','status'=>'open','created_at'=>now(),'updated_at'=>now()]);
    }
}
