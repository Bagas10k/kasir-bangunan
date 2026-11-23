<style>
html body #input-popup-sale-detail #sale-detail-view{display:grid;gap:var(--ui-space-panel);color:var(--ui-ink);font-family:var(--font-body);font-size:var(--ui-t-label);line-height:var(--ui-leading-body)}
#input-popup-sale-detail #sale-detail-view :is(h3,h4,p,dl,dd,ol){margin:0}
#input-popup-sale-detail #sale-detail-view :is(.sd-identity,.sd-section-heading,.sd-payment-top){display:flex;align-items:flex-start;justify-content:space-between;gap:var(--ui-space-section);flex-wrap:wrap}
#input-popup-sale-detail #sale-detail-view .sd-eyebrow{font-size:var(--ui-t-caption);color:var(--ui-ink-2);margin-bottom:var(--ui-space-tight)}
#input-popup-sale-detail #sale-detail-view h3{font-size:var(--ui-h2);line-height:var(--ui-leading-tight);font-weight:var(--ui-weight-strong);overflow-wrap:anywhere}
#input-popup-sale-detail #sale-detail-view .sd-buyer{margin-top:var(--ui-space-tight);font-size:var(--ui-t-table)}
#input-popup-sale-detail #sale-detail-view :is(.sd-meta,.sd-summary,.sd-payment-meta){display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,var(--ui-detail-column-min)),1fr));gap:var(--ui-space-section)}
#input-popup-sale-detail #sale-detail-view .sd-meta{padding-bottom:var(--ui-space-section);border-bottom:var(--ui-stroke) solid var(--ui-line-soft)}
#input-popup-sale-detail #sale-detail-view dt{font-size:var(--ui-t-caption);color:var(--ui-ink-2);margin-bottom:var(--ui-space-tight)}
#input-popup-sale-detail #sale-detail-view dd{font-weight:var(--ui-weight-medium);overflow-wrap:anywhere}
#input-popup-sale-detail #sale-detail-view h4{font-size:var(--ui-t-table);font-weight:var(--ui-weight-strong);line-height:var(--ui-leading-tight)}
#input-popup-sale-detail #sale-detail-view .sd-section{display:grid;gap:var(--ui-space-group);border:0;padding:0;background:none}
#input-popup-sale-detail #sale-detail-view .sd-section-heading span{color:var(--ui-ink-2);font-size:var(--ui-t-caption)}
#input-popup-sale-detail #sale-detail-view .sd-table-scroll{overflow-x:auto;border:var(--ui-stroke) solid var(--ui-line);border-radius:var(--ui-radius)}
#input-popup-sale-detail #sale-detail-view .sd-items{width:100%;border-collapse:collapse;font-size:var(--ui-t-label)}
#input-popup-sale-detail #sale-detail-view .sd-items :is(th,td){padding:var(--ui-space-group);text-align:left;border-bottom:var(--ui-stroke) solid var(--ui-line-soft);vertical-align:top}
#input-popup-sale-detail #sale-detail-view .sd-items th{background:var(--ui-head);color:var(--ui-ink-2);font-weight:var(--ui-weight-medium);font-size:var(--ui-t-caption);white-space:nowrap}
#input-popup-sale-detail #sale-detail-view .sd-items tbody tr:last-child td{border-bottom:0}
#input-popup-sale-detail #sale-detail-view .sd-items td:first-child{min-width:var(--ui-detail-column-min);overflow-wrap:anywhere}
#input-popup-sale-detail #sale-detail-view .sd-items .sd-number{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
#input-popup-sale-detail #sale-detail-view .sd-emphasis{font-weight:var(--ui-weight-medium)}
#input-popup-sale-detail #sale-detail-view .sd-adjustments{display:grid;gap:var(--ui-space-control)}
#input-popup-sale-detail #sale-detail-view .sd-adjustments:empty{display:none}
#input-popup-sale-detail #sale-detail-view .sd-adjustments>div{display:flex;justify-content:flex-end;gap:var(--ui-space-panel)}
#input-popup-sale-detail #sale-detail-view .sd-summary{padding:var(--ui-space-section);border:var(--ui-stroke) solid var(--ui-line);border-radius:var(--ui-radius);background:var(--ui-head);grid-template-columns:repeat(auto-fit,minmax(min(100%,var(--ui-detail-summary-min)),1fr))}
#input-popup-sale-detail #sale-detail-view .sd-summary dd{font-size:var(--ui-t-body);font-weight:var(--ui-weight-strong);font-variant-numeric:tabular-nums}
#input-popup-sale-detail #sale-detail-view .sd-balance dd{color:var(--ui-green)}
#input-popup-sale-detail #sale-detail-view .sd-hint{color:var(--ui-ink-2);font-size:var(--ui-t-caption)}
#input-popup-sale-detail #sale-detail-view .sd-payments{display:grid;gap:var(--ui-space-group);padding:0;list-style:none}
#input-popup-sale-detail #sale-detail-view .sd-payments>li{display:grid;gap:var(--ui-space-group);padding:var(--ui-space-section);border:var(--ui-stroke) solid var(--ui-line);border-radius:var(--ui-radius);background:var(--ui-surface)}
#input-popup-sale-detail #sale-detail-view .sd-amount{font-size:var(--ui-t-body);font-weight:var(--ui-weight-strong);font-variant-numeric:tabular-nums}
#input-popup-sale-detail #sale-detail-view .sd-method{margin-left:var(--ui-space-group);color:var(--ui-ink-2);font-size:var(--ui-t-caption)}
#input-popup-sale-detail #sale-detail-view .sd-evidence{display:inline-flex;align-items:center;gap:var(--ui-space-control);min-height:var(--ui-tap);justify-self:start;color:var(--ui-green);font-weight:var(--ui-weight-medium)}
#input-popup-sale-detail #sale-detail-view .sd-rejection{padding:var(--ui-space-group);border-radius:var(--ui-radius);background:var(--ui-bad-bg);color:var(--ui-bad-ink)}
#input-popup-sale-detail #sale-detail-view .sd-empty{color:var(--ui-ink-2)}
#input-popup-sale-detail #sale-detail-view .sd-collection{padding-top:var(--ui-space-section);border-top:var(--ui-stroke) solid var(--ui-line)}
html body #input-popup-sale-detail .sd-footer{justify-content:space-between;flex-wrap:wrap}
html body #input-popup-sale-detail .sd-print-action a{display:inline-flex;align-items:center;justify-content:center;gap:var(--ui-space-control);min-height:var(--ui-tap);padding:var(--ui-space-control) var(--ui-space-section);border:var(--ui-stroke) solid var(--ui-green);border-radius:var(--ui-radius);background:var(--ui-green);color:var(--ui-surface);font-family:var(--font-body);font-size:var(--ui-t-label);font-weight:var(--ui-weight-medium);text-decoration:none}
html body #input-popup-sale-detail .sd-print-action a:hover{background:var(--ui-green-2)}
html body #input-popup-sale-detail .sd-print-action svg{width:var(--ui-t-body);height:var(--ui-t-body)}
html body #input-popup-sale-detail :is(.sd-print-action a,.sd-evidence,.sd-table-scroll):focus-visible{outline:var(--ui-stroke) solid var(--ui-focus);outline-offset:var(--ui-space-tight)}
</style>
