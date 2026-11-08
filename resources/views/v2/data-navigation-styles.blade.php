<style>
.monitoring-block{margin:0 0 22px;min-width:0}
.monitoring-scope{margin:0 0 10px;color:var(--text-caption);font-size:12px;line-height:1.6}
.monitoring-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:0}
.monitoring-card{padding:18px 20px;border:1px solid var(--border-card);border-radius:16px;background:var(--surface,#fff);box-shadow:0 4px 18px rgb(27 67 50 / .04);min-width:0}
.monitoring-card dt{font-size:12px;font-weight:600;color:var(--text-caption)}
.monitoring-card dd{margin:10px 0 0;font-size:26px;font-weight:700;color:var(--primary-hover);font-variant-numeric:tabular-nums;line-height:1.3}
.monitoring-card p{font-size:12px;color:var(--text-caption);margin:6px 0 0}
.task-panel>.card,.master-entity-card{border-radius:16px}
body:not([data-active-task=kasir]) .work-heading{align-items:flex-start;gap:16px;flex-wrap:wrap}
.master-page-header{padding-bottom:18px;border-bottom:1px solid var(--border-subtle);margin-bottom:20px}
.master-page-copy h2{font-size:21px;line-height:1.35}
.master-page-copy .note{max-width:72ch;line-height:1.6}
@media(max-width:720px){.monitoring-grid{grid-template-columns:1fr}.monitoring-card{padding:16px 18px}.monitoring-card dd{font-size:23px}}
@media print{.monitoring-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.monitoring-card{box-shadow:none;break-inside:avoid}}
.workspace-menu-group{min-width:0;border-bottom:1px solid var(--border-subtle);padding-bottom:6px}
.workspace-menu-group>summary{display:flex;align-items:center;gap:10px;min-height:44px;padding:10px 12px;border-radius:9px;cursor:pointer;list-style:none;font-size:12px;font-weight:700;color:var(--text-main);white-space:nowrap}
.workspace-menu-group>summary::-webkit-details-marker{display:none}
.menu-group-mark{width:18px;height:18px;flex:0 0 18px;color:var(--primary-hover);stroke-linecap:round;stroke-linejoin:round}
.workspace-menu-group>summary>span:not(.menu-group-mark){opacity:0}
.workspace-menu-group>summary::after{content:'›';margin-left:auto;font-size:18px;opacity:0;transition:transform 200ms ease,opacity 160ms ease}
.workspace-menu-group[open]>summary::after{transform:rotate(90deg)}
.workspace-menu-group>summary:hover,.workspace-menu-group:has([aria-current=page])>summary{background:var(--mint-surface)}
.workspace-submenu{display:flex;flex-direction:column;gap:3px}
.sidebar-nav .workspace-submenu a{min-height:44px;display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;color:var(--text-caption);font-size:13px;text-decoration:none}
.sidebar-nav .workspace-submenu a[aria-current=page]{background:var(--mint-surface);color:var(--primary-hover);font-weight:700}
.sidebar-nav .workspace-submenu a::before{content:'';width:5px;height:5px;flex:0 0 5px;border:1px solid currentColor;border-radius:50%}
.sidebar-nav .workspace-submenu a:has(svg)::before{display:none}
@media(min-width:901px){
 body:has(.workspace-sidebar),body.sidebar-pinned:has(.workspace-sidebar){padding-left:76px;transition:padding-left 220ms ease}
 .workspace-sidebar.app-sidebar,.workspace-sidebar.app-sidebar.pinned,.workspace-sidebar.app-sidebar:focus-within{width:76px;min-width:76px;transition:width 220ms ease,min-width 220ms ease}
 .workspace-sidebar.app-sidebar:is(:hover,:has(:focus-visible)){width:284px;min-width:284px}
 body:has(.workspace-sidebar:is(:hover,:has(:focus-visible))){padding-left:284px}
 body:has([data-unified-shell]) .app-sidebar,body:has([data-unified-shell]) .app-sidebar.collapsed{width:76px;min-width:76px;transition:width 220ms ease,min-width 220ms ease}
 body:has([data-unified-shell]) .app-sidebar:is(:hover,:has(:focus-visible)){width:284px;min-width:284px}
 body:has([data-unified-shell]) .app-main,body:has([data-unified-shell]) .app-sidebar.collapsed~.app-main{margin-left:76px;width:calc(100% - 76px);transition:margin-left 220ms ease,width 220ms ease}
 body:has([data-unified-shell] .app-sidebar:is(:hover,:has(:focus-visible))) .app-main{margin-left:284px;width:calc(100% - 284px)}
 .workspace-sidebar.app-sidebar:not(:hover):not(:has(:focus-visible)) .sidebar-brand-text,.workspace-sidebar.app-sidebar:not(:hover):not(:has(:focus-visible)) .nav a>span,.workspace-sidebar.app-sidebar:not(:hover):not(:has(:focus-visible)) .sidebar-footer button span{opacity:0}
 .app-sidebar:not(:hover):not(:has(:focus-visible)) .workspace-menu-group>summary>span,.app-sidebar:not(:hover):not(:has(:focus-visible)) .workspace-menu-group>summary::after{opacity:0}
 [data-unified-shell] .app-sidebar:not(:hover):not(:has(:focus-visible)) .sidebar-brand-text,[data-unified-shell] .app-sidebar:not(:hover):not(:has(:focus-visible)) .sidebar-nav a>span{opacity:0;visibility:hidden}
 .app-sidebar:not(:hover):not(:has(:focus-visible)) .workspace-submenu{margin-left:0;padding-left:0;border-left:0}
 .app-sidebar:is(:hover,:has(:focus-visible)) .sidebar-brand-text,.app-sidebar:is(:hover,:has(:focus-visible)) .sidebar-nav a>span{opacity:1;visibility:visible}
}
[data-unified-shell] .workspace-menu-group>summary>span,[data-unified-shell] .workspace-menu-group>summary::after{opacity:1}
[data-unified-shell] .workspace-submenu{margin-left:12px;padding-left:8px;border-left:1px solid var(--border-subtle)}
[data-unified-shell] .sidebar-nav{display:flex;flex-direction:column;gap:6px;padding:20px 14px}
[data-unified-shell] .main-topbar{height:64px;min-height:64px;padding:14px 28px}
.unified-topbar-inner{display:flex;align-items:center;gap:14px;width:100%}
[data-unified-shell] .top-context strong{font-size:22px;color:var(--primary-hover);font-weight:700}
[data-unified-shell] .header-state{display:flex;align-items:center;gap:12px;margin-left:auto;font-size:12px;color:var(--text-caption)}
[data-unified-shell] .header-state a{min-height:44px;display:inline-flex;align-items:center;font-weight:600;color:var(--primary-hover)}
@media(max-width:900px){[data-unified-shell] .main-topbar{padding:12px 18px}[data-unified-shell] .top-context strong{font-size:18px}}
.workspace-menu-group>summary{transition:background-color 160ms ease}
.workspace-menu-group>summary>span{transition:opacity 160ms ease}
@supports (interpolate-size:allow-keywords) and (transition-behavior:allow-discrete){
 .workspace-menu-group{interpolate-size:allow-keywords}
 .workspace-menu-group::details-content{height:0;overflow:clip;opacity:0;transition:height 220ms cubic-bezier(.2,0,0,1),opacity 160ms ease,content-visibility 220ms;transition-behavior:allow-discrete}
 .workspace-menu-group[open]::details-content{height:auto;opacity:1}
}
.workspace-sidebar .workspace-submenu a{font-size:12.5px}
.workspace-sidebar:is(.pinned,:hover,:focus-within) .workspace-menu-group>summary>span,.workspace-sidebar:is(.pinned,:hover,:focus-within) .workspace-menu-group>summary::after{opacity:1}
.workspace-sidebar:is(.pinned,:hover,:focus-within) .workspace-submenu{margin-left:12px;padding-left:8px;border-left:1px solid var(--border-subtle)}
main .scroll{border:1px solid var(--border-subtle);border-radius:12px;overscroll-behavior-inline:contain}
main .scroll:focus-visible{outline:2px solid var(--primary-hover);outline-offset:3px}
main .scroll table{width:100%;border-collapse:collapse}
main .scroll th{font-size:12px;font-weight:700;background:var(--mint-surface);color:var(--text-main);text-align:left}
main .scroll th,main .scroll td{padding:12px 14px;vertical-align:top;border-bottom:1px solid var(--border-subtle)}
main .scroll tbody tr:last-child td{border-bottom:0}
main .scroll tbody tr:focus-within,main .scroll tbody tr:hover{background:#f6faf7}
main .scroll .money,main .scroll .num{font-variant-numeric:tabular-nums;text-align:right;white-space:nowrap}
main .scroll td[colspan]{text-align:center;color:var(--text-caption);padding:24px 16px;white-space:normal}
main .scroll td[colspan]::before{display:none}
main .scroll td a,main .scroll td button{min-height:44px;display:inline-flex;align-items:center}
@media(max-width:900px){.workspace-menu-group>summary>span:not(.menu-group-mark),.workspace-menu-group>summary::after{opacity:1}.workspace-submenu{margin-left:12px;padding-left:8px;border-left:1px solid var(--border-subtle)}}
@media(max-width:720px){main .scroll .master-clean-table td[colspan]{display:block}main .scroll .master-clean-table td{overflow-wrap:anywhere}}
@media(prefers-reduced-motion:reduce){body,.app-sidebar,.app-main,.workspace-menu-group::details-content,.workspace-menu-group>summary,.workspace-menu-group>summary::after,.workspace-menu-group>summary>span{transition:none!important}}
</style>
