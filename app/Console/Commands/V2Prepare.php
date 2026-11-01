<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\V2\Money;

class V2Prepare extends Command
{
    protected $signature = 'v2:prepare {--admin= : Existing user ID} {--cashier=* : Existing user IDs} {--apply : Apply roles and missing system COA only}';
    protected $description = 'Read-only release preflight by default; never posts opening balances or copies legacy transactions.';

    public function handle(): int
    {
        $admin = filter_var($this->option('admin'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $cashiers = array_map(fn ($id) => filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]), $this->option('cashier'));
        if (!$admin || in_array(false,$cashiers,true) || in_array($admin,$cashiers,true)) { $this->error('Explicit, distinct existing user IDs are required.'); return self::FAILURE; }
        $ids = array_values(array_unique([$admin,...$cashiers]));
        if (DB::table('users')->whereIn('id',$ids)->count() !== count($ids)) { $this->error('A specified user does not exist.'); return self::FAILURE; }
        $required = [
            '1101'=>['Kas','asset','debit'],'1102'=>['Bank','asset','debit'],
            '1103'=>['Piutang usaha','asset','debit'],'1191'=>['Uang muka sopir','asset','debit'],
            '1301'=>['Persediaan','asset','debit'],'2101'=>['Utang usaha','liability','credit'],
            '3101'=>['Modal awal','equity','credit'],'4101'=>['Penjualan','revenue','credit'],
            '4102'=>['Pendapatan ongkir','revenue','credit'],'5101'=>['HPP','expense','debit'],
            '6101'=>['Biaya perjalanan','expense','debit'],
        ];
        $missing=[];
        foreach($required as $code=>[$name,$type,$normal]) {
            $a=DB::table('chart_of_accounts')->where('account_code',(string)$code)->first();
            if(!$a){$missing[]=(string)$code;continue;}
            if(!$a->is_active || $a->account_type!==$type || $a->normal_balance!==$normal){$this->error('Account classification/active mismatch: '.$code);return self::FAILURE;}
        }
        $bad=DB::table('journal_entry_lines')->select('journal_entry_id')->groupBy('journal_entry_id')->havingRaw('SUM(debit) <> SUM(credit)')->exists();
        if($bad){$this->error('Unbalanced legacy journal. No changes.');return self::FAILURE;}
        $outstanding=Money::value('0');
        foreach(DB::table('sales')->get(['total_amount','paid_amount']) as $sale){$left=Money::value((string)$sale->total_amount)->minus((string)$sale->paid_amount);if($left->isPositive())$outstanding=$outstanding->plus($left);}
        $ap=Money::value('0');$ar=Money::value('0');
        foreach(DB::table('journal_entry_lines')->whereIn('account_code',['1103','2101'])->get(['account_code','debit','credit']) as $line){
            if($line->account_code==='1103')$ar=$ar->plus((string)$line->debit)->minus((string)$line->credit);
            else $ap=$ap->plus((string)$line->credit)->minus((string)$line->debit);
        }
        if($outstanding->isPositive() || !$ar->isZero() || !$ap->isZero()) {
            $this->error('Legacy outstanding needs explicit per-party mapping. No synthetic conversion allowed.');
            $this->line(json_encode(['sales_remaining'=>(string)$outstanding,'legacy_ar'=>(string)$ar,'legacy_ap'=>(string)$ap]));return self::FAILURE;
        }
        foreach($ids as $id){$existing=DB::table('v2_user_roles')->where('user_id',$id)->value('role');$expected=$id===$admin?'admin':'cashier';if($existing!==null && $existing!==$expected){$this->error('Role conflict for existing user ID '.$id);return self::FAILURE;}}
        if($this->option('apply')) {
            DB::transaction(function()use($required,$missing,$ids,$admin){
                foreach($missing as $code){[$name,$type,$normal]=$required[$code];DB::table('chart_of_accounts')->insert(['account_code'=>$code,'account_name'=>$name,'account_type'=>$type,'normal_balance'=>$normal,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);}
                foreach($ids as $id)DB::table('v2_user_roles')->insertOrIgnore(['user_id'=>$id,'role'=>$id===$admin?'admin':'cashier','created_at'=>now(),'updated_at'=>now()]);
            });
        }
        $this->line(json_encode(['status'=>$this->option('apply')?'prepared':'preflight_ok','user_ids'=>$ids,'missing_accounts'=>$missing,'legacy_journals_unchanged'=>true,'legacy_stock_unchanged'=>true,'legacy_outstanding'=>'0.00','scope'=>'Shared stock/journal retained; old documents admin read-only; no second opening posted.']));
        return self::SUCCESS;
    }
}
