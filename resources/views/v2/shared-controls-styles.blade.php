<style>
/* Same incumbent controls, with top-layer placement for native V2 dialogs. */
.select-shell.is-enhanced>select{position:absolute!important;inline-size:1px!important;block-size:1px!important;width:1px!important;height:1px!important;min-height:0!important;opacity:0!important;padding:0!important;border:0!important;pointer-events:none}
.select-options[popover],.app-calendar[popover]{position:fixed;margin:0;inset:auto;overflow:auto;color:var(--text-main);background:#fff;max-height:calc(100dvh - 24px)}
.select-options[popover]{z-index:100100;border:1px solid var(--border-control);padding:4px;border-radius:9px;min-width:120px}
.select-options[hidden],.app-calendar[hidden]{display:none!important}
.select-options::backdrop,.app-calendar::backdrop{background:transparent;pointer-events:none}
.select-options .select-option{min-height:44px;font-size:13px;border:0;padding:10px;border-radius:6px;background:transparent;color:var(--text-main);font-weight:500;text-align:left;display:block;width:100%}
.select-options .select-option:hover,.select-options .select-option[aria-selected=true]{background:var(--mint-surface);color:var(--primary-hover)}
#kasir .select-shell>.select-trigger,.input-dialog .select-shell>.select-trigger{min-height:44px!important;height:auto!important;font-family:var(--font-sans);font-size:13px!important;padding:9px 12px!important;line-height:1.4;text-align:left;border-radius:9px!important;background:#fff;color:var(--text-main);font-weight:500;box-shadow:none}
#kasir .select-shell,.input-dialog .select-shell{min-width:0;width:100%}
.input-dialog .date-field>.date-trigger,.date-field>.date-trigger{min-height:44px!important;height:auto!important;color:var(--text-main);font-family:var(--font-sans);padding:9px 12px;font-size:13px!important}
.app-calendar button{min-height:36px;color:var(--text-main)}.app-calendar .calendar-days button{font-family:var(--font-sans);font-weight:500;font-size:12px;padding:0;border:0;box-shadow:none}.app-calendar .calendar-nav{padding:0;width:36px}.app-calendar .calendar-months{min-width:0}.app-calendar .calendar-head{margin-bottom:12px}.app-calendar .calendar-footer button{background:transparent;font-weight:600;color:var(--primary-hover);min-height:44px}
.app-calendar{font-family:var(--font-sans);max-height:calc(100dvh - 24px);overscroll-behavior:contain}
.input-dialog .popup-content{overflow-x:hidden}
.filter-disclosure .filter-popup{z-index:1000;box-sizing:border-box;overscroll-behavior:contain}
#history-filter-form.sales-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr));padding:16px;gap:14px;overflow:auto}
#history-filter-form .field{margin:0;min-width:0}#history-filter-form label{color:var(--text-caption);font-size:11px;font-weight:650}#history-filter-form .sales-filter-actions{grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px}#history-filter-form button,#history-filter-form .btn-table-action{min-height:44px;border-radius:999px;padding:8px 16px;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;background:var(--mint-surface);color:var(--primary-hover);border:1px solid var(--mint-border)}
#history-filter-form .select-shell,#history-filter-form .date-field,#history-filter-form input{width:100%;min-width:0}
#history-table .money{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}#history-table .sku-code{font:500 11.5px var(--font-mono);background:#f3f4f6;padding:3px 5px;border-radius:5px;overflow-wrap:anywhere}#history-table td,#history-table th{font-size:12px;padding:12px}
.page-btn{min-height:44px}.pagination .pagination-meta{margin:0}
.select-trigger:focus-visible,.date-trigger:focus-visible,.app-calendar button:focus-visible,.select-option:focus-visible{outline:2px solid var(--primary-hover)!important;outline-offset:2px!important}
@media(max-width:700px){.select-options .select-option{font-size:14px}.app-calendar button,.app-calendar .calendar-days button{min-height:44px}.app-calendar .calendar-months{grid-template-columns:minmax(0,1fr)}#history-filter-form.sales-filter-grid{grid-template-columns:minmax(0,1fr)}#history-filter-form .sales-filter-actions{grid-column:auto}.filter-disclosure .date-field{max-width:none}.input-dialog .select-trigger,.input-dialog .date-trigger{font-size:16px!important}#history-table{min-width:660px}}
@media(prefers-reduced-motion:reduce){.select-options,.app-calendar{animation:none!important;transition:none!important}}
</style>
