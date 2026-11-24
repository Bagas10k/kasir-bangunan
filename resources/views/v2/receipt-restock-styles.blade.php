<style>
html body #receipt-restock{display:grid;gap:var(--ui-space-control);padding:var(--ui-space-group);margin-bottom:var(--ui-space-section);border:var(--ui-stroke) solid var(--ui-line);border-radius:var(--ui-radius);background:var(--ui-head);min-width:0}
html body #receipt-restock :is(h3,p){margin:0}
html body #receipt-restock h3{font-size:var(--ui-t-body);font-weight:var(--ui-weight-strong)}
html body #receipt-restock :is(.rr-heading,.rr-tools,.rr-search,.rr-statuses,.rr-pager){display:flex;align-items:center;gap:var(--ui-space-control);flex-wrap:wrap;min-width:0}
html body #receipt-restock .rr-heading{justify-content:space-between;align-items:flex-start}
html body #receipt-restock .rr-heading>div{flex:1;min-width:0}
html body #receipt-restock .rr-search{flex:1}
html body #receipt-restock .rr-search input{flex:1;min-width:0;width:100%;min-height:var(--ui-tap);box-sizing:border-box}
html body #receipt-restock .rr-editor{padding:var(--ui-space-section);border:var(--ui-stroke) solid var(--ui-line);border-radius:var(--ui-radius);background:var(--ui-surface)}
html body #receipt-restock .rr-editor[hidden]{display:none}
html body #receipt-restock .rr-editor h4{margin:0 0 var(--ui-space-control);font-size:var(--ui-t-body)}
html body #receipt-restock .rr-editor label{display:block;margin-top:var(--ui-space-group)}
html body #receipt-restock .rr-editor :is(input,select){width:100%;min-height:var(--ui-tap);box-sizing:border-box}
html body #receipt-restock .rr-editor-actions{display:flex;justify-content:flex-end;flex-wrap:wrap;gap:var(--ui-space-control);margin-top:var(--ui-space-group)}
html body #receipt-restock button{min-height:var(--ui-tap);white-space:normal;font-size:var(--ui-t-caption)}
html body #receipt-restock button[data-restock-product][aria-disabled=true]{opacity:.75;cursor:default}
html body #receipt-restock .rr-statuses button[aria-pressed=true]{background:var(--ui-green);color:var(--ui-surface);border-color:var(--ui-green)}
html body #receipt-restock .rr-results{display:grid;gap:var(--ui-space-control);padding:0;margin:0;list-style:none;max-height:min(50dvh,var(--ui-restock-list-max));overflow:auto;overscroll-behavior:contain;scrollbar-gutter:stable;scrollbar-width:thin;scrollbar-color:var(--ui-green) var(--ui-green-soft)}
html body #receipt-restock .rr-results:empty{display:none}
html body #receipt-restock .rr-results>li{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr) minmax(0,1fr) auto;gap:var(--ui-space-group);align-items:center;padding:var(--ui-space-group);background:var(--ui-surface);border:var(--ui-stroke) solid var(--ui-line);border-radius:var(--ui-radius)}
html body #receipt-restock .rr-results :is(strong,small){display:block;overflow-wrap:anywhere}
html body #receipt-restock .rr-results strong{font-size:var(--ui-t-label);font-weight:var(--ui-weight-medium)}
html body #receipt-restock .rr-results small{color:var(--ui-ink-2);font-size:var(--ui-t-caption)}
html body #receipt-restock .rr-pager{justify-content:space-between}
html body #receipt-restock :is(button,input,.rr-results):focus-visible{outline:var(--ui-stroke) solid var(--ui-focus);outline-offset:var(--ui-space-tight)}
@container (max-width:40rem){
    html body #receipt-restock .rr-tools{flex-direction:column;align-items:stretch}
    html body #receipt-restock .rr-search{flex:none}
    html body #receipt-restock .rr-results>li{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}
    html body #receipt-restock .rr-results>li>div:first-child{grid-column:1/-1}
    html body #receipt-restock .rr-results>li>button{grid-column:1/-1;justify-self:start}
}
</style>
