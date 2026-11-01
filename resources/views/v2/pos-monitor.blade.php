@php
// Same monitoring surface as the incumbent; read-only, actor-scoped V2 data.
$monitorAdmin = ($role ?? '') === 'admin';
$monitorNow = now('Asia/Jakarta');
$monitorStart = $monitorNow->copy()->subDays(29)->startOfDay();
$monitorSales = \Illuminate\Support\Facades\DB::table('v2_sales')->where('created_at','>=',$monitorStart)->where('created_at','<=',$monitorNow->copy()->endOfDay())->when(!$monitorAdmin,fn($q)=>$q->where('user_id',auth()->id()))->get(['id','total','created_at']);
$monitorItems = $monitorSales->isEmpty() ? collect() : \Illuminate\Support\Facades\DB::table('v2_sale_items')->whereIn('sale_id',$monitorSales->pluck('id'))->get(['sale_id','product_id','name','quantity']);
$decimal = fn($v) => \Brick\Math\BigDecimal::of((string)$v);
$formatRupiah = function($v) use($decimal) { $s=(string)$decimal($v)->toScale(2,\Brick\Math\RoundingMode::HalfUp);[$i,$f]=explode('.',$s);return 'Rp '.preg_replace('/\B(?=(\d{3})+(?!\d))/','.',$i).','.$f; };
$formatQty = function($v) use($decimal) { $s=(string)$decimal($v)->toScale(4,\Brick\Math\RoundingMode::HalfUp);[$i,$f]=explode('.',$s);$f=rtrim($f,'0');return preg_replace('/\B(?=(\d{3})+(?!\d))/','.',$i).($f!==''?','.$f:''); };
$catalogProducts=collect($products);$productUnits=$catalogProducts->pluck('unit','id');
$lowProducts=$catalogProducts->filter(fn($p)=>$decimal($p->stock_quantity)->isGreaterThan(0)&&$decimal($p->stock_quantity)->isLessThanOrEqualTo(10))->sort(fn($a,$b)=>$decimal($a->stock_quantity)->compareTo($b->stock_quantity));
$stockSummary=['products'=>$catalogProducts->count(),'low'=>$lowProducts->count(),'out'=>$catalogProducts->filter(fn($p)=>$decimal($p->stock_quantity)->isLessThanOrEqualTo(0))->count()];
$lowStockList=$lowProducts->take(5)->map(fn($p)=>['name'=>$p->name,'formatted_qty'=>$formatQty($p->stock_quantity),'unit'=>$p->unit])->values()->all();
$precomputedData=[];
foreach(['today'=>[1,'Hari ini'],'7d'=>[7,'7 hari terakhir'],'30d'=>[30,'30 hari terakhir']] as $key=>[$days,$label]) {
 $start=$monitorNow->copy()->subDays($days-1)->startOfDay();$periodSales=$monitorSales->filter(fn($s)=>\Illuminate\Support\Carbon::parse($s->created_at)->greaterThanOrEqualTo($start));
 $sum=$decimal('0');$buckets=[];$units=[];$ids=$periodSales->pluck('id')->flip();
 $count=$key==='today'?24:$days;
 for($i=0;$i<$count;$i++) { $date=$key==='today'?$start->copy()->addHours($i):$start->copy()->addDays($i);$bucketKey=$date->format($key==='today'?'Y-m-d H':'Y-m-d');$buckets[$bucketKey]=['label'=>$date->format($key==='today'?'H:00':'d/m'),'total'=>'0.00']; }
 foreach($periodSales as $sale){$sum=$sum->plus((string)$sale->total);$bucketKey=\Illuminate\Support\Carbon::parse($sale->created_at)->format($key==='today'?'Y-m-d H':'Y-m-d');if(isset($buckets[$bucketKey]))$buckets[$bucketKey]['total']=(string)$decimal($buckets[$bucketKey]['total'])->plus((string)$sale->total)->toScale(2);}
 foreach($monitorItems as $item){if(!$ids->has($item->sale_id))continue;$id=$item->product_id;if(!isset($units[$id]))$units[$id]=['name'=>$item->name,'qty'=>$decimal('0'),'unit'=>$productUnits[$id]??''];$units[$id]['qty']=$units[$id]['qty']->plus((string)$item->quantity);}
 uasort($units,fn($a,$b)=>$b['qty']->compareTo($a['qty']));$top=[];foreach(array_slice($units,0,5) as $unit)$top[]=['name'=>$unit['name'],'formatted_qty'=>$formatQty($unit['qty']),'unit'=>$unit['unit']];
 $max=$decimal('0');foreach($buckets as $bucket)if($decimal($bucket['total'])->isGreaterThan($max))$max=$decimal($bucket['total']);
 foreach($buckets as &$bucket){$bucket['height']=$max->isZero()?0:$decimal($bucket['total'])->multipliedBy(85)->dividedBy($max,0,\Brick\Math\RoundingMode::HalfUp)->toInt();$bucket['formatted_total']=$formatRupiah($bucket['total']);}unset($bucket);
 $precomputedData[$key]=['period'=>$key,'sub_label'=>$label,'formatted_revenue'=>$formatRupiah($sum),'transaction_count'=>$periodSales->count(),'buckets'=>array_values($buckets),'top_products'=>$top];
}
$initialPeriod=$precomputedData['7d'];$topProducts7d=$initialPeriod['top_products'];$slotW=420/count($initialPeriod['buckets']);$barW=32;$chartBaseY=115;
@endphp

<div class="monitor-grid">
    <section class="dash-card sales-dashboard-card" id="sales-dashboard-card" aria-labelledby="sales-dashboard-title">
        <div class="sales-dashboard-head">
            <div>
                <div class="dash-label" id="sales-dashboard-title">Penjualan</div>
                <div class="dash-sub" id="sales-period-label">{{ $initialPeriod['sub_label'] }}</div>
            </div>
            <div class="sales-period-control" id="sales-period-control" role="group" aria-label="Pilih rentang waktu penjualan">
                <span class="sales-period-pill" aria-hidden="true"></span>
                <button type="button" class="sales-period-btn" data-period="today" aria-pressed="false" onclick="loadSalesDashboard('today')">Hari Ini</button>
                <button type="button" class="sales-period-btn active" data-period="7d" aria-pressed="true" onclick="loadSalesDashboard('7d')">7 Hari</button>
                <button type="button" class="sales-period-btn" data-period="30d" aria-pressed="false" onclick="loadSalesDashboard('30d')">30 Hari</button>
            </div>
        </div>
        <div class="sales-dashboard-body" id="sales-dashboard-body" aria-live="polite" aria-busy="false">
            <div class="sales-metric">
                <div class="dash-value" id="sales-revenue">{{ $initialPeriod['formatted_revenue'] }}</div>
                <div class="dash-sub" id="sales-transactions">{{ number_format($initialPeriod['transaction_count'], 0, ',', '.') }} transaksi</div>
                <div class="sales-status" id="sales-status">Nominal dan grafik untuk periode yang sama</div>
            </div>
            <div class="sales-chart-shell">
                <div class="skeleton sales-chart-skeleton" id="sales-chart-skeleton" hidden></div>
                <svg class="sales-chart" id="sales-chart" viewBox="0 0 420 150" preserveAspectRatio="none" role="img" aria-label="Grafik penjualan {{ $initialPeriod['sub_label'] }}">
                    @foreach($initialPeriod['buckets'] as $idx => $bucket)
                        @php
                            $bHeight = max($bucket['height'], $bucket['total'] !== '0.00' ? 4 : 0);
                            $barX = (int) round($idx * $slotW + ($slotW - $barW) / 2);
                            $barY = $chartBaseY - $bHeight;
                            $labelX = (int) round($idx * $slotW + $slotW / 2);
                        @endphp
                        @if($bHeight > 0)
                            <rect x="{{ $barX }}" y="{{ $barY }}" width="{{ $barW }}" height="{{ $bHeight }}" rx="3" fill="var(--primary, #2563eb)"></rect>
                        @endif
                        <text x="{{ $labelX }}" y="135" text-anchor="middle" font-size="10" fill="var(--text-caption, #6b7280)">{{ $bucket['label'] }}</text>
                    @endforeach
                </svg>
            </div>
        </div>
    </section>

    <div class="dash-card">
        <div class="dash-label">Produk Unggulan</div>
        <div class="dash-toggle">
            <button type="button" id="dash-toggle-best" class="active" onclick="switchDashList('best')">Terlaris</button>
            <button type="button" id="dash-toggle-low" onclick="switchDashList('low')">Stok Menipis</button>
        </div>
        <div id="dash-list-best" class="dash-panel active">
            <ul class="dash-list">
                @forelse($topProducts7d as $tp)
                    <li>
                        <span class="dash-item-name" title="{{ $tp['name'] }}">{{ $tp['name'] }}</span>
                        <span class="dash-item-num">{{ $tp['formatted_qty'] }} {{ $tp['unit'] }}</span>
                    </li>
                @empty
                    <li style="color: var(--text-caption, #6b7280);">Belum ada penjualan.</li>
                @endforelse
            </ul>
        </div>
        <div id="dash-list-low" class="dash-panel">
            <ul class="dash-list">
                @forelse($lowStockList as $ls)
                    <li>
                        <span class="dash-item-name" title="{{ $ls['name'] }}">{{ $ls['name'] }}</span>
                        <span class="dash-item-num">{{ $ls['formatted_qty'] }} {{ $ls['unit'] }}</span>
                    </li>
                @empty
                    <li style="color: var(--text-caption, #6b7280);">Stok aman.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-label">Ringkasan Produk</div>
        <ul class="dash-list">
            <li><span>Total Produk</span><span class="dash-item-num">{{ $stockSummary['products'] }}</span></li>
            <li><span>Stok Menipis</span><span class="dash-item-num" style="{{ $stockSummary['low'] > 0 ? 'color: #854d0e;' : '' }}">{{ $stockSummary['low'] }}</span></li>
            <li><span>Stok Habis</span><span class="dash-item-num" style="{{ $stockSummary['out'] > 0 ? 'color: #b91c1c;' : '' }}">{{ $stockSummary['out'] }}</span></li>
        </ul>
    </div>
</div>

<script id="pos-monitor-precomputed" type="application/json">
{!! json_encode($precomputedData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
</script>

<script>
(()=>{
 const data=JSON.parse(document.querySelector('#pos-monitor-precomputed').textContent);
 const svg=document.querySelector('#sales-chart');
 const el=(name,attrs,text)=>{const n=document.createElementNS('http://www.w3.org/2000/svg',name);for(const[k,v]of Object.entries(attrs))n.setAttribute(k,v);if(text!==undefined)n.textContent=text;return n;};
 window.switchDashList=kind=>{for(const k of ['best','low']){document.querySelector('#dash-list-'+k).classList.toggle('active',k===kind);document.querySelector('#dash-toggle-'+k).classList.toggle('active',k===kind);}};
 window.loadSalesDashboard=period=>{const d=data[period];if(!d)return;for(const b of document.querySelectorAll('.sales-period-btn'))b.setAttribute('aria-pressed',String(b.dataset.period===period));document.querySelector('#sales-revenue').textContent=d.formatted_revenue;document.querySelector('#sales-transactions').textContent=d.transaction_count+' transaksi';document.querySelector('#sales-period-label').textContent=d.sub_label;svg.replaceChildren();svg.setAttribute('aria-label','Grafik penjualan '+d.sub_label);const w=420/d.buckets.length;d.buckets.forEach((b,i)=>{const height=Math.max(b.height,b.total!=='0.00'?3:0);svg.append(el('rect',{x:i*w+w*.2,y:115-height,width:w*.6,height,rx:3,fill:'#2d6a4f'}));if(d.buckets.length<=7||i%5===0)svg.append(el('text',{x:i*w+w/2,y:135,'text-anchor':'middle','font-size':10,fill:'#526359'},b.label));});const list=document.querySelector('#dash-list-best ul');list.replaceChildren();for(const p of d.top_products){const row=document.createElement('li'),name=document.createElement('span'),qty=document.createElement('span');name.className='dash-item-name';qty.className='dash-item-num';name.textContent=p.name;qty.textContent=p.formatted_qty+' '+p.unit;row.append(name,qty);list.append(row);}if(!d.top_products.length){const row=document.createElement('li');row.textContent='Belum ada penjualan.';list.append(row);}};
})();
</script>
