<style id="ui-table-filter-dna">
/* Satu pola filter untuk tabel V2, katalog, riwayat, dan halaman server. */
:root{--ui-filter-control-height:44px}
html body :is([data-filter-ready],#u#u#u){
    display:flex !important;flex-wrap:wrap !important;align-items:center !important;gap:10px !important;
    grid-template:none !important;position:relative;overflow:visible;
}
html body :is([data-filter-ready],#u#u#u)>[data-filter-search]{
    flex:1 1 200px;min-width:0;max-width:420px;margin:0 !important;padding:0;grid-area:auto !important;
}
html body :is([data-filter-ready],#u#u#u)>[data-filter-search]>label{
    position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap;
}
html body :is([data-filter-ready],#u#u#u)>[data-filter-search] input{
    box-sizing:border-box;width:100%;max-width:100%;min-height:var(--ui-filter-control-height) !important;height:var(--ui-filter-control-height) !important;
    padding:0 12px !important;border:1px solid #dce5de;border-radius:9px;background:#fff;color:#102a1d;font:inherit;font-size:14px !important;
}
html body :is([data-filter-ready],#u#u#u)>.table-search[data-filter-search] input{padding-left:38px !important}
html body :is([data-filter-ready],#u#u#u)>.ui-search[data-filter-search]{
    display:flex;align-items:center;gap:var(--ui-space-control);box-sizing:border-box;
    height:var(--ui-filter-control-height);min-height:var(--ui-filter-control-height);padding-inline:var(--ui-space-group);
    border:var(--ui-stroke) solid var(--ui-line);border-radius:var(--ui-radius);background:var(--ui-surface);
}
html body :is([data-filter-ready],#u#u#u)>.ui-search[data-filter-search]:focus-within{outline:var(--ui-stroke) solid var(--ui-focus)}
html body :is([data-filter-ready],#u#u#u)>.ui-search[data-filter-search]>svg{
    position:static;transform:none;flex:none;width:var(--ui-t-body);height:var(--ui-t-body);
}
html body :is([data-filter-ready],#u#u#u)>.ui-search[data-filter-search] input{
    flex:1;min-width:0;height:100% !important;min-height:0 !important;
    padding:0 !important;border:0 !important;background:transparent !important;box-shadow:none !important;
}
html body :is([data-filter-ready],#u#u#u)>.ui-toolbar-actions{margin-left:auto}
html body .ui-filter-owner{display:flex;align-items:center;flex-wrap:wrap;gap:8px;min-width:0;max-width:100%}
html body :is(.ui-filter-trigger,.ui-filter-search-submit,#u#u#u){
    display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:var(--ui-filter-control-height);height:var(--ui-filter-control-height);
    width:auto;padding:0 14px;border:1px solid #dce5de;border-radius:9px;background:#fff;color:#14532d;
    font-family:inherit;font-size:14px;font-weight:600;line-height:1.3;white-space:nowrap;cursor:pointer;box-shadow:0 1px 2px rgb(15 42 29 / .03);
}
html body .ui-filter-trigger:hover{background:#f3f7f4;border-color:#a9bfaf}
html body .ui-filter-trigger:is([aria-expanded=true],[data-has-filters=true]){background:#eef6ef;border-color:#87ac92;color:#14532d}
html body .ui-filter-icon{
    width:16px;height:16px;background:currentColor;flex:none;
    mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='1.8' stroke-linecap='round'%3E%3Cpath d='M4 7h16M4 17h16M8 4v6M16 14v6'/%3E%3C/svg%3E") center/contain no-repeat;
}
html body .ui-filter-count{display:grid;place-items:center;min-width:20px;height:20px;padding:0 5px;border-radius:6px;background:#14532d;color:#fff;font-size:12px;font-variant-numeric:tabular-nums}
html body .ui-filter-current{max-width:22ch;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px;color:#52665a}
html body :is([data-table-filter-panel],#u#u#u){
    position:fixed !important;inset:auto;margin:0 !important;padding:0 !important;
    display:flex !important;flex-direction:column;gap:0 !important;width:min(384px,calc(100vw - 24px));
    max-height:calc(100dvh - 24px);max-width:calc(100vw - 24px);z-index:9000;
    box-sizing:border-box;border:1px solid #dce5de !important;border-radius:14px !important;
    background:#fff !important;color:#102a1d;box-shadow:0 20px 60px rgb(15 42 29 / .16),0 3px 10px rgb(15 42 29 / .06) !important;
    overflow:hidden;animation:var(--ui-float-in);font-family:Inter,system-ui,sans-serif;
}
html body :is([data-table-filter-panel],#u#u#u) .ui-filter-heading{
    display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:16px 12px 14px 20px;
    border-bottom:1px solid #edf1ee;flex:none;
}
html body :is([data-table-filter-panel],#u#u#u) .ui-filter-titles{padding-top:3px}
html body :is([data-table-filter-panel],#u#u#u) .ui-filter-titles strong{font-size:16px;font-weight:700;line-height:1.5}
html body :is([data-table-filter-panel],#u#u#u) .ui-filter-titles p{margin:3px 0 0;font-size:13px;line-height:1.5;color:#5f7468}
html body :is([data-table-filter-panel],#u#u#u) .ui-filter-close{
    display:grid;place-items:center;flex:none;width:44px;min-width:44px;height:44px;min-height:44px;
    margin:-6px 0 0;padding:0;border:0;border-radius:8px;background:transparent;color:#52665a;font-size:25px;cursor:pointer;
}
html body :is([data-table-filter-panel],#u#u#u) .ui-filter-close:hover{background:#f2f6f3}
html body :is([data-table-filter-panel],#u#u#u) .ui-filter-fields{
    display:flex;flex-direction:column;gap:16px;min-height:0;overflow-x:hidden;overflow-y:auto;overscroll-behavior:contain;padding:18px 20px;
}
html body :is([data-table-filter-panel],#u#u#u) .ui-filter-fields :is(.field,.form-group,.master-control,.table-perpage,.filter-cluster,.preset-cluster,.custom-period,.date-cluster,.report-filter-form){
    display:flex !important;flex-direction:column !important;align-items:stretch !important;gap:7px !important;
    grid-area:auto !important;grid-column:auto !important;flex:none;width:100% !important;min-width:0;max-width:100% !important;
    margin:0 !important;padding:0 !important;border:0;background:none;box-shadow:none;
}
html body :is([data-table-filter-panel],#u#u#u) :is(label,.filter-caption,.ui-filter-label){
    display:block;position:static !important;width:auto !important;height:auto !important;clip-path:none !important;
    margin:0 0 6px;padding:0;color:#3c4a42;font-size:13px !important;font-weight:600;line-height:1.4;
    text-transform:none;letter-spacing:0;white-space:normal;
}
html body :is([data-table-filter-panel],#u#u#u) .ui-filter-label{margin:0 0 -8px}
html body :is([data-table-filter-panel],#u#u#u) :is(input:not([type=hidden]):not([type=checkbox]):not([type=radio]),select,.select-trigger,.date-trigger){
    box-sizing:border-box;width:100%;max-width:100%;height:44px !important;min-height:44px !important;padding:0 12px !important;
    border:1px solid #dce5de;border-radius:8px;background:#fff;color:#102a1d;font-size:14px !important;
}
html body :is([data-table-filter-panel],#u#u#u) :is(.date-field,.select-shell){width:100%;min-width:0;max-width:100%;margin:0}
html body :is([data-table-filter-panel],#u#u#u) .date-field--range>input[type=date],
html body :is([data-table-filter-panel],#u#u#u) .select-shell.is-enhanced>select{position:absolute;width:1px;height:1px !important;min-height:0 !important;padding:0 !important;opacity:0;pointer-events:none}
html body :is([data-table-filter-panel],#u#u#u) .range-sep{display:none}
html body :is([data-table-filter-panel],#u#u#u) :is(.ui-chips,.period-presets){display:grid !important;grid-template-columns:minmax(0,1fr) !important;gap:6px !important;width:100% !important;overflow:visible !important;padding:0 !important;margin:0 !important}
html body :is([data-table-filter-panel],#u#u#u) :is(.ui-chip,.preset-btn){
    display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;max-width:100%;min-height:44px;
    margin:0;padding:10px 12px;border:1px solid transparent;border-radius:8px;background:#f6f8f6;color:#3c4a42;
    font-size:14px;font-weight:500;line-height:1.4;text-align:left;white-space:normal;text-decoration:none;
}
html body :is([data-table-filter-panel],#u#u#u) :is(.ui-chip[aria-pressed=true],.preset-btn.active){background:#eaf4ec;border-color:#a9cbb3;color:#14532d;font-weight:600}
html body :is([data-table-filter-panel],#u#u#u) :is(.ui-chip[aria-pressed=true],.preset-btn.active)::after{content:' ';width:7px;height:12px;border:solid currentColor;border-width:0 2px 2px 0;transform:rotate(45deg);margin-right:3px;flex:none}
html body :is([data-table-filter-panel],#u#u#u) .ui-filter-footer{
    display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap;flex:none;padding:12px 20px;
    border-top:1px solid #edf1ee;background:#f8faf8;
}
html body :is([data-table-filter-panel],#u#u#u) :is(.sales-filter-actions,.master-toolbar-actions,.ledger-filter-actions,.receivables-filter-actions,.exp-filter-actions,.ui-toolbar-actions){
    display:flex !important;gap:8px !important;flex-wrap:wrap;width:100%;margin:0 !important;padding:0 !important;grid-area:auto !important;border:0 !important;
}
html body :is([data-table-filter-panel],#u#u#u) :is(.ui-filter-footer button,.ui-filter-footer a,.report-apply,.report-reset){
    display:inline-flex;align-items:center;justify-content:center;min-height:44px !important;height:auto !important;flex:1 1 auto;
    width:auto !important;max-width:100%;padding:10px 14px !important;border:1px solid #dce5de;border-radius:8px;
    background:#fff;color:#14532d;font-size:14px;font-weight:600;line-height:1.4;text-align:center;text-decoration:none;cursor:pointer;
}
html body :is([data-table-filter-panel],#u#u#u) :is(button[type=submit],.ui-filter-done,.report-apply){background:#14532d !important;border-color:#14532d !important;color:#fff !important}
html body :is([data-table-filter-panel],#u#u#u) .ui-filter-reset{margin-right:auto}
html body :is(.ui-filter-trigger,.ui-filter-search-submit,[data-table-filter-panel] button,[data-table-filter-panel] a):focus-visible{outline:2px solid #14532d;outline-offset:3px}
html body :is([data-table-filter-panel][hidden],#u#u#u),html body .ui-filter-count[hidden],html body .ui-filter-current[hidden]{display:none !important}
@media(max-width:760px){
    html body :is([data-filter-ready],#u#u#u)>[data-filter-search]{flex:1 1 100%;width:100%;max-width:none}
    html body :is([data-filter-ready],#u#u#u)>.ui-toolbar-actions{flex-basis:100%;margin:0}
    html body .ui-filter-current{max-width:18ch}
}
@media(prefers-reduced-motion:reduce){
    html body :is([data-table-filter-panel],#u#u#u){animation:none !important}
}
@media print{
    html body :is([data-filter-ready],#u#u#u),html body [data-table-filter-panel]{display:none !important}
}
</style>
