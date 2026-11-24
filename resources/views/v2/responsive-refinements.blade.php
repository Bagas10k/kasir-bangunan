<style>
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor-head .ui-pill{display:inline-flex;align-items:center;gap:6px;min-height:0;padding:5px 10px;border:0;border-radius:999px;font-size:var(--ui-t-caption);font-weight:600;white-space:nowrap;background:var(--ui-mute-bg);color:var(--ui-mute-ink)}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor-head .ui-pill::before{content:"";flex:none;width:8px;height:8px;border-radius:50%;background:currentColor}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor-head .ui-pill--ok{background:var(--ui-green-soft);color:var(--ui-green)}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor-head .ui-pill--warn{background:var(--ui-warn-bg);color:var(--ui-warn-ink)}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor-head .ui-pill--info{background:var(--ui-info-bg);color:var(--ui-info-ink)}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor-head .ui-pill--bad{background:var(--ui-bad-bg);color:var(--ui-bad-ink)}
html body .product-detail-suppliers{margin:0 0 var(--ui-space-section);padding-left:var(--ui-space-section);font-size:var(--ui-t-label)}
html body .product-detail-suppliers li+li{margin-top:var(--ui-space-control)}
@media(min-width:1180px){
 html body[data-active-task=ringkasan] main.wrap.main-content{padding:var(--ui-space-control) var(--ui-space-section)}
 html body #admin-overview.as-page{gap:var(--ui-space-control)}
 html body #admin-overview .as-top{gap:var(--ui-space-control)}
 html body #admin-overview .as-seg button{min-height:var(--ui-tap) !important;height:auto;padding-inline:var(--ui-space-section) !important}
 html body[data-active-task=ringkasan] #admin-overview .as-card{padding:var(--ui-space-group) !important}
 html body[data-active-task=ringkasan] #admin-overview .as-card h2{font-size:var(--ui-t-body) !important;line-height:var(--ui-leading-tight) !important}
 html body #admin-overview .as-sub{margin:var(--ui-space-tight) 0 var(--ui-space-control);font-size:var(--ui-t-caption);line-height:var(--ui-leading-tight)}
 html body #admin-overview .as-todo{display:grid;grid-template-columns:auto minmax(0,1fr);align-items:center;gap:var(--ui-space-group)}
 html body #admin-overview .as-todo>.as-sub{display:none}
 html body #admin-overview .as-todo-list{grid-template-columns:repeat(auto-fit,minmax(var(--ui-detail-column-min),1fr));gap:var(--ui-space-control)}
 html body #admin-overview .as-todo-item{min-height:var(--ui-tap) !important;padding:var(--ui-space-control);row-gap:var(--ui-space-tight)}
 html body #admin-overview .as-todo-label{font-size:var(--ui-t-caption)}
 html body #admin-overview .as-todo-hint{font-size:var(--ui-t-caption)}
 html body #admin-overview .as-ok{margin:0}
 html body #admin-overview .as-kpi{display:grid;grid-template-columns:auto minmax(0,1fr);gap:var(--ui-space-control) var(--ui-space-section);align-items:center}
 html body #admin-overview .as-kpi>.as-sub{justify-self:end;margin:0}
 html body #admin-overview .as-tiles{grid-column:1/-1;grid-template-columns:repeat(4,minmax(0,1fr));gap:var(--ui-space-control)}
 html body #admin-overview .as-tile{padding:var(--ui-space-control) var(--ui-space-group)}
 html body #admin-overview .as-tile strong{line-height:var(--ui-leading-tight)}
 html body[data-active-task=ringkasan] #admin-overview .as-tile--hero strong{font-size:var(--ui-h2) !important}
 html body #admin-overview :is(.as-row2,.as-row3){gap:var(--ui-space-control)}
 html body #admin-overview .as-row2{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}
 html body #admin-overview .as-row3{grid-template-columns:repeat(3,minmax(0,1fr))}
 html body #admin-overview .as-plot{height:var(--ui-overview-plot-height);margin:var(--ui-space-control) 0 var(--ui-space-panel)}
 html body #admin-overview .as-best-list{column-gap:var(--ui-space-section)}
 html body #admin-overview .as-best-list li{min-height:calc(var(--ui-space-section) * 2);gap:var(--ui-space-tight)}
 html body #admin-overview :is(.as-name,.as-lines dt,.as-lines dd){font-size:var(--ui-t-caption)}
 html body #admin-overview .as-lines>div{min-height:calc(var(--ui-space-group) * 2);gap:var(--ui-space-control)}
 html body #admin-overview .as-link{top:var(--ui-space-control);right:var(--ui-space-group);min-height:var(--ui-tap);display:inline-flex;align-items:center}
 html body #admin-overview .as-status{gap:var(--ui-space-control);margin:var(--ui-space-tight) 0 var(--ui-space-control)}
 html body #admin-overview .as-status div{min-height:var(--ui-row);padding:var(--ui-space-tight)}
 html body #admin-overview .as-status strong{font-size:var(--ui-h2);line-height:var(--ui-leading-tight)}
 html body #admin-overview .as-watch li{min-height:calc(var(--ui-space-group) * 2);gap:var(--ui-space-tight) var(--ui-space-control)}
 html body #admin-overview .as-foot{margin:var(--ui-space-control) 0 0;padding:var(--ui-space-control);font-size:var(--ui-t-caption)}
}
@media(min-width:1180px) and (max-height:860px){
 html body #admin-overview .as-todo-list{grid-template-columns:repeat(auto-fit,minmax(var(--ui-detail-summary-min),1fr))}
 html body #admin-overview .as-fold>summary{align-items:center;min-height:var(--ui-tap) !important;cursor:pointer}
 html body #admin-overview .as-fold-val{display:block;font-size:var(--ui-t-caption);font-weight:var(--ui-weight-strong)}
 html body #admin-overview .as-fold>summary::after{content:'Rincian';font-size:var(--ui-t-caption);color:var(--ui-green)}
 html body #admin-overview .as-fold-body{padding-top:var(--ui-space-control)}
 html body #admin-overview .as-link{position:static;justify-content:flex-start}
}
html body .task-panel>[data-dialog-host]{display:contents !important}
/* Scrollbar mengikuti warna tema, tanpa mengganti perilaku gulir native. */
html body #kasir :is(#items,.cart-table-scroll,.catalog-table-scroll,.catalog-page-list){
    scrollbar-width:thin;
    scrollbar-color:var(--ui-green) var(--ui-green-soft);
}
html body #kasir :is(#items,.cart-table-scroll,.catalog-table-scroll,.catalog-page-list)::-webkit-scrollbar{
    width:var(--ui-space-control);height:var(--ui-space-control);
}
html body #kasir :is(#items,.cart-table-scroll,.catalog-table-scroll,.catalog-page-list)::-webkit-scrollbar-track{
    background:var(--ui-green-soft);border-radius:var(--ui-radius);
}
html body #kasir :is(#items,.cart-table-scroll,.catalog-table-scroll,.catalog-page-list)::-webkit-scrollbar-thumb{
    background:var(--ui-green);border-radius:var(--ui-radius);
}
html body #kasir :is(#items,.cart-table-scroll,.catalog-table-scroll,.catalog-page-list)::-webkit-scrollbar-thumb:hover{
    background:var(--ui-green-2);
}
html body #panel-kasir #kasir .pos-catalog-card{min-width:0;max-width:100%;box-sizing:border-box;box-shadow:none;background:var(--ui-surface);border:var(--ui-stroke) solid var(--ui-line-soft)}
html body #panel-kasir #kasir #products-tbody .catalog-stock{font-size:var(--ui-t-label);font-weight:var(--ui-weight-medium);color:var(--ui-ink-2);white-space:nowrap}
html body #panel-kasir #kasir #products-tbody .catalog-stock.out-of-stock{color:var(--ui-bad-ink)}
html body #panel-kasir #kasir #products-tbody .catalog-stock.low-stock{color:var(--ui-warn-ink)}
html body #panel-kasir #kasir #products-tbody tr[data-product]{box-shadow:none;border-color:var(--ui-line-soft)}
html body #panel-kasir #kasir #products-tbody tr[data-product]>td{padding:var(--ui-space-control) var(--ui-space-group)}
html body #panel-kasir #kasir #products-tbody .ui-primary .ui-sub{font-size:var(--ui-t-caption);color:var(--ui-ink-2)}
html body #panel-kasir #kasir #products-tbody .btn-table-action:disabled{background:var(--ui-mute-bg) !important;color:var(--ui-mute-ink) !important;border-color:var(--ui-line-soft) !important;box-shadow:none;cursor:not-allowed}
html body #panel-kasir #kasir #products-tbody .btn-table-action{min-height:var(--ui-tap) !important;min-width:var(--ui-tap);width:auto;height:auto !important;max-width:100%;box-sizing:border-box;padding:var(--ui-space-control) var(--ui-space-section) !important;white-space:nowrap}
html body #panel-kasir #kasir .pos-catalog-tools{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:var(--ui-space-control);min-width:0}
html body #panel-kasir #kasir .pos-catalog-tools .pos-search-box{width:100%;min-width:0;margin:0;height:var(--ui-tap);min-height:var(--ui-tap)}
html body #panel-kasir #kasir .pos-catalog-tools :is(.catalog-filter-row[data-filter-ready],#u#u#u){margin:0;padding:0;gap:0 !important;flex-wrap:nowrap !important;align-self:stretch;min-width:0;border:0;background:transparent}
html body #panel-kasir #kasir .pos-catalog-tools .ui-filter-owner{flex-wrap:nowrap}
html body #panel-kasir #kasir .pos-catalog-tools :is(.ui-filter-trigger,#u#u#u){min-height:var(--ui-tap);height:var(--ui-tap);margin:0;padding:0 var(--ui-space-group);border:var(--ui-stroke) solid var(--ui-line);border-radius:var(--ui-radius);background:var(--ui-surface);color:var(--ui-green);font-size:var(--ui-t-label)}
html body #panel-kasir #kasir :is(.pos-catalog-head,.pos-search-wrapper,.pos-search-box){min-width:0;box-sizing:border-box}
html body #panel-kasir #kasir .pos-search-box{max-width:100%}
html body #panel-kasir #kasir #pos-product-search{width:100%;min-width:0}
html body #task-panels #panel-persetujuan{gap:var(--ui-space-panel)}
html body #task-panels #panel-persetujuan>section{margin:0 !important}
html body #panel-persetujuan #payment-verification{min-width:0;max-width:100%;box-sizing:border-box}
html body #panel-persetujuan #payment-verification-help{margin-block:var(--ui-space-control) var(--ui-space-section) !important;line-height:var(--ui-leading-body);color:var(--ui-ink-2) !important;max-width:none}
html body #panel-persetujuan #payment-verification>[data-filter-ready]{margin-block:var(--ui-space-section) !important}
html body #panel-persetujuan #payment-verification .ui-table-wrap{margin-top:var(--ui-space-section)}
html body #sales-history #history-results>.scroll{margin-top:var(--ui-space-section);border:var(--ui-stroke) solid var(--ui-line-soft);border-radius:var(--ui-radius)}
html body #sales-history #history-table :is(th,td){padding:var(--ui-space-group) var(--ui-space-section);vertical-align:middle;line-height:var(--ui-leading-body)}
html body #sales-history #history-table .history-number{display:block;color:var(--ui-ink);font-size:var(--ui-t-label);font-weight:var(--ui-weight-strong);overflow-wrap:anywhere}
html body #sales-history #history-table :is(.history-reference,.history-buyer){min-width:var(--ui-detail-column-min)}
html body #sales-history #history-table .ui-sub{display:block;margin-top:var(--ui-space-tight);font-size:var(--ui-t-caption);color:var(--ui-ink-2);line-height:var(--ui-leading-body)}
html body #sales-history #history-table button[data-sale-detail]{display:inline-flex;align-items:center;min-height:var(--ui-tap);max-width:100%;padding:var(--ui-space-control) var(--ui-space-group) !important;border:var(--ui-stroke) solid var(--ui-line-soft) !important;border-radius:var(--ui-radius);background:var(--ui-surface) !important;color:var(--ui-green) !important;box-shadow:none !important;font-weight:var(--ui-weight-medium);overflow-wrap:anywhere}
html body #sales-history #history-table :is(button[data-sale-detail],.doc-links a):hover{background:var(--ui-green-soft) !important}
html body #sales-history #history-table .money{font-weight:var(--ui-weight-strong);color:var(--ui-ink)}
html body #sales-history #history-table .doc-links{display:flex !important;flex-wrap:wrap;gap:var(--ui-space-control)}
html body #sales-history #history-table .doc-links a{display:inline-flex;align-items:center;justify-content:center;min-height:var(--ui-tap);padding:var(--ui-space-control) var(--ui-space-group);border:var(--ui-stroke) solid var(--ui-line);border-radius:var(--ui-radius);color:var(--ui-green);text-decoration:none;white-space:nowrap;box-sizing:border-box}
html body #sales-history #history-table :is(button[data-sale-detail],.doc-links a):focus-visible{outline:var(--ui-stroke) solid var(--ui-focus);outline-offset:var(--ui-space-tight)}
html body #panel-kasir #kasir #products-tbody .pm-step{width:min(100%,calc(var(--ui-tap) * 2 + var(--ui-catalog-quantity-width)));min-width:0;height:auto;min-height:var(--ui-tap)}
html body #panel-kasir #kasir #products-tbody .pm-step button{flex:none;min-width:var(--ui-tap) !important;width:var(--ui-tap) !important;min-height:var(--ui-tap) !important}
html body #panel-kasir #kasir #products-tbody .pm-step [data-pm-quantity]{width:var(--ui-catalog-quantity-width);min-width:0;min-height:var(--ui-tap);height:auto;padding:0 var(--ui-space-tight) !important;margin:0;border:0 !important;border-radius:0 !important;text-align:center;background:var(--ui-green) !important;color:var(--ui-surface) !important;font:inherit;font-size:var(--ui-t-body);box-shadow:none !important;flex:1;box-sizing:border-box}
html body #panel-kasir #kasir #products-tbody .pm-step [data-pm-quantity]:focus-visible{outline:var(--ui-stroke) solid var(--ui-accent);outline-offset:calc(-1 * var(--ui-stroke))}
html body #panel-kasir #kasir #products-tbody .pm-step [data-pm-quantity][aria-invalid=true]{color:var(--ui-warn-ink) !important;background:var(--ui-warn-bg) !important}
html body #panel-kasir #kasir #pd-custom{min-width:0;position:relative}
html body #panel-kasir #kasir #pd-custom-trigger{min-height:var(--ui-tap);display:flex;align-items:center;cursor:pointer;font-size:var(--ui-t-label);padding:var(--ui-space-control) var(--ui-space-group);border:var(--ui-stroke) solid var(--ui-line);border-radius:var(--ui-radius);background:var(--ui-surface);color:var(--ui-green)}
html body #panel-kasir #kasir #store-shift-reminder:not([hidden]){display:block !important;margin:var(--ui-space-control) 0 0;padding:var(--ui-space-group);border-radius:var(--ui-radius);background:var(--ui-warn-bg);color:var(--ui-warn-ink);font-size:var(--ui-t-caption);line-height:var(--ui-leading-body)}
html body #panel-kasir #kasir #store-shift-reminder[hidden]{display:none !important}
html body #panel-kasir #kasir #pd-custom-form[hidden]{display:none !important}
html body #panel-kasir #kasir #pd-custom-status{font-size:var(--ui-t-caption);overflow-wrap:anywhere}
html body #panel-kasir #kasir #pd-custom-status:empty{display:none}
html body #history-filter-form .history-filter-bar{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--ui-space-control)}
html body #history-filter-form .history-periods{display:flex;gap:var(--ui-space-control);flex-wrap:wrap}
html body #history-filter-form .history-filter-bar .sales-filter-actions{margin:0}
html body #sales-history .history-scope{display:flex;justify-content:flex-end;align-items:center;margin-block:var(--ui-space-control)}
html body #sales-history .history-periods button{min-height:var(--ui-tap);padding:var(--ui-space-control) var(--ui-space-section);border:var(--ui-stroke) solid var(--ui-line);border-radius:var(--ui-radius);background:var(--ui-surface);color:var(--ui-green)}
html body #sales-history .history-periods button[aria-pressed=true]{background:var(--ui-green);color:var(--ui-surface)}
html body #insight-dialog .insight-content{display:grid;gap:var(--ui-space-section)}
html body dialog.input-dialog .popup-content.detail-content{display:grid;gap:var(--ui-space-section);padding:var(--ui-space-section) var(--ui-space-panel);min-width:0;text-align:start;white-space:normal;overflow-wrap:anywhere}
html body .detail-section{min-width:0;padding:var(--ui-space-section);border:var(--ui-stroke) solid var(--ui-line-soft);border-radius:var(--ui-radius);background:var(--ui-surface)}
html body :is(.detail-section,.delivery-actions) h3{margin:0 0 var(--ui-space-group);font-size:var(--ui-t-body);color:var(--ui-ink)}
.detail-facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,var(--ui-detail-summary-min)),1fr));gap:var(--ui-space-group);margin:0}
.detail-facts>div{min-width:0;padding:var(--ui-space-group);border-radius:var(--ui-radius);background:var(--ui-canvas);overflow-wrap:anywhere}
.detail-facts dt{color:var(--ui-ink-2);font-size:var(--ui-t-caption)}
.detail-facts dd{margin:var(--ui-space-tight) 0 0;font-weight:var(--ui-weight-strong);font-variant-numeric:tabular-nums}
.detail-facts .ui-sub{display:block;font-size:var(--ui-t-caption);font-weight:normal}
.detail-actions{display:flex;gap:var(--ui-space-control);flex-wrap:wrap;margin-block-end:var(--ui-space-group)}
.detail-actions button[aria-pressed=true]{background:var(--ui-green);color:var(--ui-surface)}
.detail-results .ui-table-wrap,.detail-section .ui-table-wrap{max-inline-size:100%;overflow:auto}
html body #panel-kasir #kasir #form-checkout>.pos-buyer-card{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,var(--ui-detail-column-min)),1fr));gap:var(--ui-space-group);align-items:start}
html body #panel-kasir #kasir #form-checkout>.pos-buyer-card .pos-combo-input{width:100%}
html body #panel-kasir #kasir #form-checkout .pos-tier-caption{margin-bottom:var(--ui-space-control);font-size:var(--ui-t-label);font-weight:var(--ui-weight-strong);color:var(--ui-green-soft)}
html body #panel-kasir #kasir #form-checkout .pos-tier-field .pos-tier{margin:0}
html body #insight-dialog .insight-facts{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));margin:0;border:var(--ui-stroke) solid var(--ui-line-soft);border-radius:var(--ui-radius);overflow:hidden;background:var(--ui-surface)}
html body #insight-dialog .insight-facts>div{min-width:0;padding:8px 10px;border-left:var(--ui-stroke) solid var(--ui-line-soft)}
html body #insight-dialog .insight-facts>div:first-child{border-left:0}
html body #insight-dialog .insight-facts>div.is-primary{background:var(--ui-green-soft)}
html body #insight-dialog .insight-facts dt{font-size:11px;font-weight:600;line-height:1.3;color:var(--ui-ink-2)}
html body #insight-dialog .insight-facts dd{margin:2px 0 0;font-size:.9375rem;font-weight:700;line-height:1.25;color:var(--ui-ink);font-variant-numeric:tabular-nums;overflow-wrap:anywhere}
html body #insight-dialog .insight-facts>div.is-primary dd{color:var(--ui-green)}
html body #insight-dialog .insight-section{margin:0;padding-top:var(--ui-space-section);border-top:var(--ui-stroke) solid var(--ui-line-soft)}
html body #insight-dialog #supplier-activity-form{display:flex;flex-wrap:wrap;align-items:flex-end;gap:var(--ui-space-group);margin:0 0 var(--ui-space-group)}
html body #insight-dialog .insight-range{display:grid;grid-template-columns:auto minmax(8rem,1fr) auto auto minmax(8rem,1fr);align-items:center;gap:var(--ui-space-tight);flex:1 1 24rem;min-width:0}
html body #insight-dialog #supplier-activity-form label{font-size:var(--ui-t-label);font-weight:600;color:var(--ui-ink-2);white-space:nowrap}
html body #insight-dialog #supplier-activity-form input[type=date]{min-height:var(--ui-tap);height:var(--ui-tap);min-width:0;width:100%;padding:0 var(--ui-space-control);border:var(--ui-stroke) solid var(--ui-line);border-radius:var(--ui-radius);background:var(--ui-surface);font-size:var(--ui-t-label);color:var(--ui-ink)}
html body #insight-dialog #supplier-activity-form button{min-height:var(--ui-tap);padding:var(--ui-space-control) var(--ui-space-section);border:0;border-radius:var(--ui-radius);background:var(--ui-green);color:var(--ui-surface);font-size:var(--ui-t-label);font-weight:700;cursor:pointer}
html body #insight-dialog :is(.supplier-print-link,[data-supplier-print],.supplier-activity-pages a){display:inline-flex;align-items:center;justify-content:center;min-height:var(--ui-tap);padding:var(--ui-space-control) var(--ui-space-group);border:var(--ui-stroke) solid var(--ui-line);border-radius:var(--ui-radius);background:var(--ui-surface);color:var(--ui-green);text-decoration:none;box-sizing:border-box;font-size:var(--ui-t-label);font-weight:600}
html body #insight-dialog [data-supplier-activity] [hidden],html body #insight-dialog [data-supplier-activity][hidden]{display:none !important}
html body #insight-dialog .supplier-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:var(--ui-space-group);margin:var(--ui-space-section) 0}
html body #insight-dialog .supplier-summary>div{padding:var(--ui-space-group);background:var(--ui-canvas);border:var(--ui-stroke) solid var(--ui-line-soft);border-radius:var(--ui-radius)}
html body #insight-dialog .supplier-summary dt{font-size:var(--ui-t-caption);color:var(--ui-ink-2)}
html body #insight-dialog .supplier-summary dd{margin:var(--ui-space-tight) 0 0;font-weight:var(--ui-weight-strong);font-variant-numeric:tabular-nums}
html body #insight-dialog [data-supplier-results]>.scroll{max-height:var(--ui-supplier-history-height);overflow:auto}
html body #insight-dialog .supplier-activity-table{width:100%;border-collapse:collapse}
html body #insight-dialog .supplier-activity-table thead th{position:sticky;top:0;z-index:1;background:var(--ui-head)}
html body #insight-dialog .supplier-activity-table :is(th,td){padding:var(--ui-space-control);vertical-align:top}
html body #insight-dialog .supplier-activity-table .ui-sub{display:block;font-size:var(--ui-t-caption);overflow-wrap:anywhere}
html body #insight-dialog .supplier-activity-pages{display:flex;align-items:center;justify-content:space-between;gap:var(--ui-space-control);margin-block:var(--ui-space-section);flex-wrap:wrap}
@media(max-width:640px){
    html body dialog.input-dialog .popup-content.detail-content{padding:var(--ui-space-group)}
    html body .detail-section{padding:var(--ui-space-group)}
    html body #insight-dialog .insight-facts{grid-template-columns:repeat(2,minmax(0,1fr))}
    html body #insight-dialog .insight-facts>div:nth-child(odd){border-left:0}
    html body #insight-dialog .insight-facts>div:nth-child(n+3){border-top:var(--ui-stroke) solid var(--ui-line-soft)}
    html body #insight-dialog .supplier-summary{grid-template-columns:1fr}
    html body #insight-dialog .insight-range{grid-template-columns:1fr 1fr;flex-basis:100%}
    html body #insight-dialog .insight-range :is(label,span){display:none}
    html body #insight-dialog #supplier-activity-form :is(button,[data-supplier-print]){flex:1 1 100%}
}
@media(min-width:47.5625rem){
    html body #panel-kasir #kasir #products-table{table-layout:fixed;width:100%}
    html body #panel-kasir #kasir #products-table th:first-child{width:var(--ui-catalog-product-width)}
    html body #panel-kasir #kasir #products-table th:not(:first-child){width:var(--ui-catalog-detail-width)}
    html body #panel-kasir #kasir #products-tbody td:first-child{overflow-wrap:anywhere;white-space:normal}
}

/* Pencarian tetap satu kontrol setelah filter dipindahkan ke popover. */
html body #panel-shift :is([data-filter-ready],#u#u#u)>.ui-search[data-filter-search]{
    display:flex;align-items:center;gap:var(--ui-space-control);min-width:0;
    box-sizing:border-box;height:var(--ui-tap);padding:0 var(--ui-space-group);
}
html body #panel-shift :is([data-filter-ready],#u#u#u)>.ui-search[data-filter-search] svg{
    position:static !important;transform:none !important;flex:none;width:var(--ui-t-body);height:var(--ui-t-body);
}
html body #panel-shift :is([data-filter-ready],#u#u#u)>.ui-search[data-filter-search] input{
    flex:1;min-width:0;width:100%;height:100% !important;min-height:0 !important;
    padding:0 !important;border:0 !important;background:transparent !important;box-shadow:none !important;
}
@media(max-width:47.5rem){
    html body #sales-history #history-results>.scroll{border:0;border-radius:0;overflow:visible}
    html body #sales-history #history-table{min-width:0 !important}
    html body #sales-history #history-table :is(.history-reference,.history-buyer){min-width:0;flex-wrap:wrap}
    html body #sales-history #history-table .history-reference>.history-number{margin-left:auto;max-width:100%}
    html body #sales-history #history-table .history-buyer>button{margin-left:auto}
    html body #sales-history #history-table td.ui-actions:has(.doc-links){display:flex !important}
    html body #sales-history #history-table .doc-links{width:100%}
    html body #sales-history #history-table .doc-links a{flex:1}
    html body #panel-kasir #kasir .pos-catalog-card{width:100%}
    html body #panel-kasir #kasir .pos-catalog-head{padding:var(--ui-space-section)}
    html body #panel-kasir #kasir .pos-catalog-head>h2{font-size:var(--ui-h2);line-height:var(--ui-leading-tight);overflow-wrap:anywhere}
    html body #panel-kasir #kasir .pos-catalog-head .pos-search-box{margin:0;min-height:var(--ui-tap);height:var(--ui-tap)}
    html body #panel-kasir #kasir .pos-catalog-head .search-kbd-hint{display:none}
    html body #panel-kasir #kasir .catalog-table-scroll{max-height:none !important;min-width:0}
    html body #panel-kasir #kasir #products-tbody tr{
        grid-template-columns:minmax(0,1fr) minmax(0,1fr);
        grid-template-areas:"name name" "stock price" "act act";
        gap:var(--ui-space-control) var(--ui-space-group);
    }
    html body #panel-kasir #kasir #products-tbody td{min-width:0;overflow-wrap:anywhere}
    html body #panel-kasir #kasir #products-tbody td:nth-child(3){white-space:normal}
    html body #panel-kasir #kasir #products-tbody td:nth-child(4){justify-self:stretch}
    html body #panel-kasir #kasir #products-tbody :is(.btn-table-action,.pm-step){
        min-height:var(--ui-tap) !important;height:auto !important;
    }
    html body #panel-kasir #kasir #products-tbody .btn-table-action{width:100% !important}
    html body #panel-kasir #kasir #products-tbody .pm-step{justify-content:space-between;width:100%}
    html body #panel-kasir #kasir #products-tbody .pm-step button{min-width:var(--ui-tap) !important;min-height:var(--ui-tap) !important}
    html body #panel-kasir #kasir .catalog-pager-controls{min-width:0;gap:var(--ui-space-control)}
    html body #panel-kasir #kasir .catalog-pager-controls>button{flex:none;min-height:var(--ui-tap) !important}
    html body #panel-kasir #kasir .catalog-page-list{display:flex;flex:1;min-width:0;overflow-x:auto;gap:var(--ui-space-tight)}
    html body #panel-kasir #kasir .catalog-page-list button{flex:none;min-height:var(--ui-tap)}
    html body #panel-shift .ui-toolbar{flex-direction:column;align-items:stretch !important;min-width:0;max-width:100%;box-sizing:border-box}
    html body #panel-shift :is([data-filter-ready],#u#u#u)>.ui-search[data-filter-search]{
        flex:0 0 var(--ui-tap);width:100%;max-width:100%;min-width:0;
    }
}
@media(forced-colors:active){
    html body #kasir :is(#items,.cart-table-scroll,.catalog-table-scroll,.catalog-page-list){scrollbar-color:auto}
}
</style>
