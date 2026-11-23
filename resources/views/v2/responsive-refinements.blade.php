<style>
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
html body #panel-kasir #kasir #pd-custom-panel{position:fixed;inset:auto;margin:0;padding:var(--ui-space-section);width:var(--ui-period-panel-width);max-width:calc(100vw - var(--ui-space-section));box-sizing:border-box;overflow:auto;gap:var(--ui-space-control);border:var(--ui-stroke) solid var(--ui-line);border-radius:var(--ui-radius);background:var(--ui-surface);color:var(--ui-ink);z-index:var(--ui-loading-layer);animation:var(--ui-float-in)}
html body #panel-kasir #kasir #pd-custom-panel[hidden]{display:none !important}
html body #panel-kasir #kasir .pd-custom-heading{display:flex;align-items:center;justify-content:space-between;gap:var(--ui-space-control);margin-bottom:var(--ui-space-control)}
html body #panel-kasir #kasir #pd-custom-close{min-width:var(--ui-tap);min-height:var(--ui-tap);padding:var(--ui-space-control);border:0;background:transparent;color:var(--ui-ink);font-size:var(--ui-h2)}
html body #panel-kasir #kasir #pd-custom-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--ui-space-control);padding:0;border:0;background:var(--ui-surface);max-width:100%}
html body #panel-kasir #kasir #pd-custom-form>.pd-date-field{min-width:0}
html body #panel-kasir #kasir #pd-custom-form>.pd-date-field label{display:block;margin-bottom:var(--ui-space-tight);font-size:var(--ui-t-label)}
html body #panel-kasir #kasir #pd-custom-form>:is(button,p){grid-column:1/-1}
html body #panel-kasir #kasir #pd-custom-form :is(input,button){width:100%;min-width:0;min-height:var(--ui-tap);box-sizing:border-box}
html body #panel-kasir #kasir #pd-custom-status{font-size:var(--ui-t-caption);overflow-wrap:anywhere}
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
@media(prefers-reduced-motion:reduce){
    html body #panel-kasir #kasir #pd-custom-panel{animation:none !important}
}
</style>
