<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2CutoverTest extends TestCase
{
    use RefreshDatabase;
    protected function beforeRefreshingDatabase(): void
    {
        if(config('database.default')!=='sqlite'||config('database.connections.sqlite.database')!==':memory:')throw new \RuntimeException('Isolated SQLite only');
    }
    private function actor(string $role):User
    {
        $u=User::factory()->create();DB::table('v2_user_roles')->insert(['user_id'=>$u->id,'role'=>$role,'created_at'=>now(),'updated_at'=>now()]);return $u;
    }
    public function test_cutover_blocks_legacy_writes_and_cashier_bypass_but_keeps_admin_archive():void
    {
        config(['v2.enabled'=>true]);$cashier=$this->actor('cashier');$admin=$this->actor('admin');
        $this->actingAs($cashier)->get('/')->assertRedirect(route('v2.index'));
        $this->get('/products')->assertForbidden();
        $this->postJson('/products',['sku'=>'SYN-BYPASS'])->assertForbidden();
        $this->postJson('/v2/master/customer',['code'=>'SYN-DENIED','name'=>'Synthetic'])->assertForbidden();
        $this->actingAs($admin)->postJson('/products',['sku'=>'SYN-BYPASS'])->assertForbidden();
        $this->get('/products')->assertOk();
        $this->get('/v2')->assertOk()->assertSee('Operasional V2');
        $this->get('/demo-setup')->assertNotFound();
        $this->post('/demo-setup')->assertNotFound();
        $this->assertDatabaseCount('products',0);
    }
    public function test_explicit_prepare_is_read_only_by_default_and_adds_only_roles_and_accounts():void
    {
        $u=User::factory()->create();
        $this->assertSame(0,Artisan::call('v2:prepare',['--admin'=>(string)$u->id]));
        $this->assertDatabaseCount('chart_of_accounts',0);$this->assertDatabaseCount('v2_user_roles',0);
        $this->assertSame(0,Artisan::call('v2:prepare',['--admin'=>(string)$u->id,'--apply'=>true]));
        $this->assertDatabaseHas('v2_user_roles',['user_id'=>$u->id,'role'=>'admin']);
        $this->assertDatabaseCount('chart_of_accounts',11);$this->assertDatabaseCount('journal_entries',0);
        $this->assertSame(0,Artisan::call('v2:prepare',['--admin'=>(string)$u->id,'--apply'=>true]));
        $this->assertDatabaseCount('chart_of_accounts',11);
    }
    public function test_prepare_refuses_unmapped_legacy_receivable_without_writes():void
    {
        $u=User::factory()->create();DB::table('sales')->insert(['customer_type'=>'retail','payment_method'=>'credit','total_amount'=>'100','cogs_amount'=>'60','paid_amount'=>'20','change_amount'=>'0','created_at'=>now(),'updated_at'=>now()]);
        $this->assertSame(1,Artisan::call('v2:prepare',['--admin'=>(string)$u->id,'--apply'=>true]));
        $this->assertDatabaseCount('v2_user_roles',0);$this->assertDatabaseCount('chart_of_accounts',0);
    }
}
