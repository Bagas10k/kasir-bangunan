<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2OperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('V2 tests require isolated SQLite :memory:.');
        }
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);
        return $user;
    }

    private function product(): int
    {
        foreach ([['1101','Kas','asset','debit'],['1102','Bank','asset','debit'],['1103','Piutang','asset','debit'],['1301','Persediaan','asset','debit'],['2101','Utang','liability','credit'],['3101','Modal','equity','credit'],['4101','Penjualan','revenue','credit'],['4102','Ongkir','revenue','credit'],['5101','HPP','expense','debit'],['6101','Biaya Perjalanan','expense','debit']] as [$code,$name,$type,$normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore(['account_code'=>$code,'account_name'=>$name,'account_type'=>$type,'normal_balance'=>$normal,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        }
        return DB::table('products')->insertGetId(['sku'=>'SYN-V2-001','name'=>'Material Sintetis Alpha','unit'=>'unit','category'=>'Umum','cost_price'=>'60.1234','selling_price'=>'100.00','stock_quantity'=>'10.0000','min_stock'=>'1.0000','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
    }

    private function customer(): int
    {
        return DB::table('v2_parties')->insertGetId(['kind'=>'customer','code'=>'SYN-C-001','name'=>'Pelanggan Sintetis Alpha','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
    }

    private function payload(int $product, ?int $customer = null): array
    {
        return ['items'=>[['product_id'=>$product,'quantity'=>'2','discount'=>['type'=>'percent','value'=>'10']]],'discount'=>['type'=>'percent','value'=>'10'],'delivery_fee'=>'15.00','customer_id'=>$customer,'payment_method'=>'cash','paid_amount'=>'200.00','idempotency_key'=>'synthetic-v2-checkout-001','fulfillment'=>'pickup'];
    }

    public function test_cashier_cannot_write_master_or_approve_payment(): void
    {
        $cashier=$this->actor('cashier');
        $this->actingAs($cashier)->postJson(route('v2.master.store','customer'),['code'=>'C-1','name'=>'Synthetic'])->assertForbidden();
        $this->actingAs($cashier)->postJson(route('v2.payments.approve',999))->assertForbidden();
        $this->assertDatabaseCount('v2_parties',0);
    }

    public function test_unassigned_account_fails_closed(): void
    {
        $user=User::factory()->create();
        $this->actingAs($user)->postJson(route('v2.shifts.open'),['cash'=>'100','source'=>'admin'])->assertForbidden();
        $this->assertDatabaseCount('v2_shifts',0);
    }

    public function test_stacked_discounts_exclude_delivery_and_repeat_does_not_post_twice(): void
    {
        $cashier=$this->actor('cashier');$product=$this->product();
        $ops=app(Operations::class);$ops->openShift($cashier->id,'100.00','admin');
        $data=$this->payload($product);
        $this->actingAs($cashier)->postJson(route('v2.sales.store'),$data)->assertOk();
        $sale=DB::table('v2_sales')->first();
        $this->assertSame('177.00',number_format((float)$sale->total,2,'.',''));
        $this->assertEquals(15,$sale->delivery_fee);
        $this->assertEquals(8,DB::table('products')->where('id',$product)->value('stock_quantity'));
        $this->actingAs($cashier)->postJson(route('v2.sales.store'),$data)->assertOk();
        $this->assertDatabaseCount('v2_sales',1);
        $this->assertDatabaseCount('v2_payments',1);
        $this->assertEquals(8,DB::table('products')->where('id',$product)->value('stock_quantity'));
        $this->assertEquals(177,DB::table('v2_invoices')->first()->paid);
    }

    public function test_transfer_sale_is_receivable_until_admin_confirms_and_stock_only_once(): void
    {
        $cashier=$this->actor('cashier');$admin=$this->actor('admin');$product=$this->product();$customer=$this->customer();
        app(Operations::class)->openShift($cashier->id,'100','admin');
        $data=$this->payload($product,$customer);$data['payment_method']='transfer';$data['paid_amount']='177.00';$data['fulfillment']='delivery';$data['destination']='Zona Sintetis A';
        $this->actingAs($cashier)->postJson(route('v2.sales.store'),$data)->assertOk();
        $payment=DB::table('v2_payments')->first();$invoice=DB::table('v2_invoices')->first();
        $this->assertSame('pending',$payment->status);
        $this->assertEquals(0,$invoice->paid);
        $this->assertDatabaseCount('v2_deliveries',1);
        $this->assertEquals(0,DB::table('journal_entry_lines')->where('account_code','1102')->sum('debit'));
        $this->actingAs($admin)->postJson(route('v2.payments.approve',$payment->id))->assertOk();
        $this->assertEquals(177,DB::table('v2_invoices')->where('id',$invoice->id)->value('paid'));
        $this->assertEquals(177,DB::table('journal_entry_lines')->where('account_code','1102')->sum('debit'));
        $this->assertEquals(8,DB::table('products')->where('id',$product)->value('stock_quantity'));
        $count=DB::table('journal_entries')->count();
        $this->actingAs($admin)->postJson(route('v2.payments.approve',$payment->id))->assertOk();
        $this->assertSame($count,DB::table('journal_entries')->count());
    }

    public function test_overdiscount_and_oversell_rollback(): void
    {
        $cashier=$this->actor('cashier');$product=$this->product();app(Operations::class)->openShift($cashier->id,'0','admin');
        $data=$this->payload($product);$data['discount']=['type'=>'amount','value'=>'100000'];
        $this->actingAs($cashier)->postJson(route('v2.sales.store'),$data)->assertUnprocessable();
        $data=$this->payload($product);$data['items'][0]['quantity']='11';
        $this->actingAs($cashier)->postJson(route('v2.sales.store'),$data)->assertUnprocessable();
        $this->assertDatabaseCount('v2_sales',0);$this->assertDatabaseCount('journal_entries',0);
        $this->assertEquals(10,DB::table('products')->where('id',$product)->value('stock_quantity'));
    }

    public function test_cash_and_transfer_installments_reduce_invoice_only_once(): void
    {
        $cashier=$this->actor('cashier');$admin=$this->actor('admin');$product=$this->product();$customer=$this->customer();
        $ops=app(Operations::class);$ops->openShift($cashier->id,'100','admin');
        $data=$this->payload($product,$customer);$data['payment_method']='credit';$data['paid_amount']='0';
        $sale=$ops->checkout($cashier->id,$data);
        $url=route('v2.payments.store',$sale['invoice_id']);
        $cash=['amount'=>'50.00','method'=>'cash','idempotency_key'=>'installment-cash-0001'];
        $this->actingAs($cashier)->postJson($url,$cash)->assertOk();
        $this->actingAs($cashier)->postJson($url,$cash)->assertOk();
        $this->assertEquals(50,DB::table('v2_invoices')->value('paid'));
        $this->assertSame('partial',DB::table('v2_sales')->value('status'));
        $cash['amount']='51.00';
        $this->actingAs($cashier)->postJson($url,$cash)->assertUnprocessable();
        $transfer=['amount'=>'127.00','method'=>'transfer','idempotency_key'=>'installment-bank-0001'];
        $payment=$this->actingAs($cashier)->postJson($url,$transfer)->assertOk()->json('id');
        $this->assertEquals(50,DB::table('v2_invoices')->value('paid'));
        $this->actingAs($admin)->postJson(route('v2.payments.approve',$payment))->assertOk();
        $this->assertEquals(177,DB::table('v2_invoices')->value('paid'));
        $this->assertSame('paid',DB::table('v2_sales')->value('status'));
        $this->assertDatabaseCount('v2_payments',2);
    }

    public function test_close_shift_carry_and_cash_discrepancy_do_not_create_revenue(): void
    {
        $cashier=$this->actor('cashier');$ops=app(Operations::class);
        $id=$ops->openShift($cashier->id,'100','admin');
        $this->assertSame('closed',$ops->closeShift($cashier->id,$id,'100')['status']);
        $next=$ops->openShift($cashier->id,'100','carry',$id);
        $this->assertDatabaseCount('journal_entries',0);
        $closed=$ops->closeShift($cashier->id,$next,'99');
        $this->assertSame('review',$closed['status']);
        $this->assertSame('-1.00',$closed['difference']);
        $this->assertDatabaseCount('journal_entries',0);
        $this->actingAs($cashier)->postJson(route('v2.shifts.open'),['cash'=>'100','source'=>'carry','carry_from'=>$id])->assertUnprocessable();
    }

    public function test_supplier_payment_is_proposed_until_admin_and_drawer_guard(): void
    {
        $cashier=$this->actor('cashier');$admin=$this->actor('admin');$this->product();$this->startBooks('1000','1000');
        $ops=app(Operations::class);$ops->openShift($cashier->id,'100','admin');
        $supplier=DB::table('v2_parties')->insertGetId(['kind'=>'supplier','code'=>'SYN-S-1','name'=>'Supplier Sintetis A','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $invoice=DB::table('v2_invoices')->insertGetId(['direction'=>'payable','party_id'=>$supplier,'number'=>'SYN-AP-001','amount'=>'200.00','paid'=>'0','status'=>'open','created_at'=>now(),'updated_at'=>now()]);
        $before=DB::table('journal_entries')->count();
        $payment=$ops->collect($cashier->id,$invoice,'50','cash','supplier-payment-0001');
        $this->assertEquals(0,DB::table('v2_invoices')->where('id',$invoice)->value('paid'));
        $this->assertSame($before,DB::table('journal_entries')->count());
        $this->actingAs($cashier)->postJson(route('v2.payments.approve',$payment))->assertForbidden();
        $this->actingAs($admin)->postJson(route('v2.payments.approve',$payment))->assertOk();
        $this->assertEquals(50,DB::table('v2_invoices')->where('id',$invoice)->value('paid'));
        $excess=$ops->collect($cashier->id,$invoice,'60','cash','supplier-payment-0002');
        $this->actingAs($admin)->postJson(route('v2.payments.approve',$excess))->assertUnprocessable();
        $this->assertEquals(50,DB::table('v2_invoices')->where('id',$invoice)->value('paid'));
        $this->assertSame('proposed',DB::table('v2_payments')->where('id',$excess)->value('status'));
    }

    public function test_same_checkout_key_with_changed_content_is_rejected_and_preserves_change(): void
    {
        $cashier=$this->actor('cashier');$product=$this->product();$ops=app(Operations::class);$ops->openShift($cashier->id,'100','admin');
        $data=$this->payload($product);$first=$ops->checkout($cashier->id,$data);$second=$ops->checkout($cashier->id,$data);
        $this->assertSame('23.00',$first['change']);$this->assertSame('23.00',$second['change']);
        $this->assertEquals(60.1234,DB::table('v2_sale_items')->value('cost_price'));
        $data['paid_amount']='300.00';
        $this->actingAs($cashier)->postJson(route('v2.sales.store'),$data)->assertUnprocessable();
        $this->assertDatabaseCount('v2_sales',1);
    }
}
