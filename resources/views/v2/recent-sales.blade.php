<section class="pd-side" id="recent-sales" aria-labelledby="recent-sales-title">
    <div class="pd-card-head"><h3 class="pd-card-title" id="recent-sales-title">Penjualan terbaru</h3><p class="pd-card-sub">5 nota terakhir · seluruh toko</p></div>
    <ol class="pd-top" data-live-list>
        @forelse($recentSales as $recent)
        <li><span class="pd-rank" aria-hidden="true">{{ $loop->iteration }}</span><button type="button" class="secondary recent-sale" data-sale-detail="{{ $recent->id }}"><strong class="pd-top-name">{{ $recent->customer_name ?? 'Pelanggan umum' }}</strong><small>{{ $recent->number }}</small></button></li>
        @empty
        <li class="note">Belum ada penjualan.</li>
        @endforelse
    </ol>
    <a class="recent-sales-more" href="#riwayat-penjualan" data-task-link="riwayat-penjualan">Lihat semua penjualan</a>
</section>
<style>
html body #panel-kasir #kasir #pos-dashboard .pd-body{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,1fr) minmax(0,.9fr);grid-template-areas:"kpis kpis kpis" "main side recent";gap:16px;align-items:stretch}
html body #panel-kasir #kasir #pos-dashboard .pd-pane:not([hidden]){display:contents}
html body #panel-kasir #kasir #pos-dashboard :is(.pd-main,.pd-side){align-self:stretch;min-width:0;min-height:0;padding:16px 20px;background:#fff}
html body #panel-kasir #kasir #pos-dashboard .pd-main{display:flex;flex-direction:column}
html body #panel-kasir #kasir #pos-dashboard .pd-chart{flex:1;display:flex;flex-direction:column;min-height:0}
html body #panel-kasir #kasir #pos-dashboard .pd-chart figcaption{position:static;margin:10px 0 0;font-size:12px;line-height:18px}
html body #panel-kasir #kasir #pos-dashboard .pd-plot{flex:1;grid-template-rows:minmax(144px,1fr);padding-bottom:28px;margin-top:16px}
html body #panel-kasir #kasir #pos-dashboard .pd-y{height:auto;align-self:stretch;margin:0}
html body #panel-kasir #kasir #pos-dashboard .pd-area{height:auto;min-height:144px;margin:4px 0 0}
html body #panel-kasir #kasir #pos-dashboard .pd-grid{height:100%}
html body #panel-kasir #kasir #pos-dashboard .pd-side .pd-top{grid-template-columns:repeat(var(--pd-columns,2),minmax(0,1fr));column-gap:16px;margin-top:12px}
html body #panel-kasir #kasir #pos-dashboard .pd-side .pd-top li{min-height:44px}
html body #panel-kasir #kasir #pos-dashboard .pd-top-name{white-space:normal;overflow:visible;overflow-wrap:anywhere;line-height:18px}
html body #panel-kasir #kasir #recent-sales{grid-area:recent;margin:0}
html body #panel-kasir #kasir #recent-sales .pd-top{grid-auto-flow:row;grid-template-rows:none;grid-template-columns:minmax(0,1fr);gap:0}
html body #panel-kasir #kasir #recent-sales .pd-top li{grid-template-columns:18px minmax(0,1fr);padding:0}
html body #recent-sales .recent-sale{display:flex;flex-direction:column;justify-content:center;align-items:flex-start;gap:2px;width:100%;min-width:0;min-height:44px;text-align:left;white-space:normal;border:0;background:transparent;box-shadow:none;padding:4px 0;line-height:18px}
html body #recent-sales .recent-sale strong{max-width:100%;overflow-wrap:anywhere;font-weight:600}
html body #recent-sales .recent-sale:focus-visible{outline:2px solid #14532D;outline-offset:2px}
html body #panel-kasir #kasir #recent-sales small{color:#5F7468;font-size:12px;line-height:16px;overflow-wrap:anywhere}
html body #recent-sales .recent-sales-more{display:inline-flex;align-items:center;min-height:44px;margin-top:auto;padding-top:8px;font-size:13px;color:#14532D;font-weight:600}
@media(max-width:1100px){html body #panel-kasir #kasir #pos-dashboard .pd-body{grid-template-columns:repeat(2,minmax(0,1fr));grid-template-areas:"kpis kpis" "main main" "side recent"}}
@media(max-width:760px){html body #panel-kasir #kasir #pos-dashboard .pd-body{grid-template-columns:minmax(0,1fr);grid-template-areas:"kpis" "main" "side" "recent"}html body #panel-kasir #kasir #pos-dashboard .pd-side .pd-top{grid-auto-flow:row;grid-template-rows:none;grid-template-columns:minmax(0,1fr)}html body #panel-kasir #kasir #pos-dashboard :is(.pd-main,.pd-side){padding:16px}}
</style>
