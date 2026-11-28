<style>
/* ============================================================
   SIDEBAR V2 — rail ikon (tertutup) / daftar ringkas (terbuka tetap)
   Rail: hover/fokus ikon grup memunculkan tooltip nama + kartu sub-menu (JS: .rail-tip, .rail-flyout).
   Terbuka: sub-menu berupa pohon dengan siku melengkung. Warna ada di ui-theme.
   ============================================================ */
:root{--side-rail:68px;--side-open:252px}
#workspace-sidebar{--side-ink:#33483b;--side-mute:#5d7064}
#workspace-sidebar .sidebar-brand{min-height:60px;padding:10px 12px 10px 14px;gap:10px;border-bottom:1px solid var(--ui-line-soft)}
#workspace-sidebar .brand-mark{width:40px;height:40px;flex:0 0 40px;border-radius:11px}
#workspace-sidebar .sidebar-brand-text strong{font-size:.9375rem}
#workspace-sidebar .sidebar-brand-text small{font-size:.8125rem}
#workspace-sidebar .sidebar-nav{display:flex;flex-direction:column;gap:2px;padding:10px;overflow-x:hidden}

/* grup */
#workspace-sidebar .workspace-menu-group{border:0;padding:0;margin:0}
#workspace-sidebar .workspace-menu-group+.workspace-menu-group{margin-top:2px}
#workspace-sidebar .workspace-menu-group>summary{position:relative;display:flex;align-items:center;gap:12px;height:40px;min-height:40px;padding:0 10px 0 14px;border-radius:10px;font-size:.8125rem;font-weight:700;color:var(--side-ink);background:transparent;white-space:nowrap}
#workspace-sidebar .workspace-menu-group>summary:hover{background:var(--ui-hover)}
#workspace-sidebar .menu-group-mark{width:20px;height:20px;flex:0 0 20px;color:var(--side-mute)}
#workspace-sidebar .workspace-menu-group:has([aria-current=page])>summary{color:var(--ui-green);background:transparent}
#workspace-sidebar .workspace-menu-group:has([aria-current=page])>summary .menu-group-mark{color:var(--ui-green)}
#workspace-sidebar .workspace-menu-group>summary::after{content:'';width:7px;height:7px;margin-left:auto;border-right:1.6px solid currentColor;border-bottom:1.6px solid currentColor;transform:rotate(-45deg);opacity:.55;font-size:0;transition:transform .2s ease,opacity .15s}
#workspace-sidebar .workspace-menu-group[open]>summary::after{transform:rotate(45deg)}

/* sub-menu: pohon dengan siku melengkung (satu garis pemandu per butir, bukan satu garis panjang) */
#workspace-sidebar .sidebar-nav .workspace-submenu{position:relative;display:flex;flex-direction:column;gap:2px;margin:2px 0 8px!important;padding:0!important;border:0!important}
#workspace-sidebar .sidebar-nav .workspace-submenu::before{display:none!important}
#workspace-sidebar .sidebar-nav .workspace-submenu a{position:relative;display:flex;align-items:center;gap:0;min-height:34px;height:auto;margin-left:38px;padding:6px 12px;border-radius:9px;font-size:.84375rem;font-weight:500;line-height:1.3;color:var(--side-ink);text-decoration:none;white-space:nowrap;background:transparent}
#workspace-sidebar .sidebar-nav .workspace-submenu a::before{content:''!important;display:block!important;position:absolute;left:-15px;top:calc(-50% - 2px);width:14px;height:calc(100% + 2px);flex:none;margin:0;border:0;border-left:1px solid var(--ui-side-tree,#c9d6cd);border-bottom:1px solid var(--ui-side-tree,#c9d6cd);border-radius:0 0 0 10px;background:none}
#workspace-sidebar .sidebar-nav .workspace-submenu a>svg{display:none}
#workspace-sidebar .sidebar-nav .workspace-submenu a:hover{background:var(--ui-hover);color:var(--ui-ink)}
#workspace-sidebar .sidebar-nav .workspace-submenu a[aria-current=page]{background:var(--ui-green-soft);color:var(--ui-green);font-weight:650}
#workspace-sidebar .sidebar-nav .workspace-submenu a>span{opacity:1!important;visibility:visible!important;overflow:hidden;text-overflow:ellipsis}

/* kaki: keluar */
#workspace-sidebar .sidebar-footer{display:flex;flex-direction:column;align-items:stretch;gap:4px;padding:8px 10px 12px;border-top:1px solid var(--ui-line-soft)}
#workspace-sidebar .sidebar-footer .role-label{display:none}
#workspace-sidebar .sidebar-footer form{margin:0}
#workspace-sidebar :is(.sidebar-footer form button,.sidebar-footer .sidebar-help){text-decoration:none;box-sizing:border-box;display:flex;align-items:center;gap:12px;width:100%;height:40px;min-height:40px;padding:0 14px;border:0;border-radius:10px;background:transparent;color:var(--side-ink);font-size:.8125rem;font-weight:600;white-space:nowrap;cursor:pointer;justify-content:flex-start}
#workspace-sidebar .sidebar-footer form button:hover{background:var(--ui-hover)}
#workspace-sidebar :is(.sidebar-footer form button,.sidebar-footer .sidebar-help) svg{width:20px;height:20px;flex:0 0 20px}

/* tombol buka/ciutkan di tepi sidebar */
.sidebar-edge{display:none}

/* tab untuk menu gabungan */
.ui-subtabs{display:inline-flex;align-self:flex-start;gap:2px;padding:3px;background:#e6eee8;border-radius:12px;max-width:100%;overflow-x:auto}
body .task-panel>.ui-subtabs{width:auto!important}
.ui-subtab{display:flex;align-items:center;height:36px;padding:0 18px;border-radius:9px;font-size:var(--ui-t-label);font-weight:600;color:var(--ui-ink-2);text-decoration:none;white-space:nowrap}
.ui-subtab:hover{color:var(--ui-ink)}
.ui-subtab[aria-selected=true]{background:#fff;color:var(--ui-green);box-shadow:0 1px 3px rgb(23 43 32 / .14)}

/* tooltip dan kartu sub-menu rail (elemen JS di <body>) */
.rail-tip,.rail-flyout{position:fixed}
.rail-tip{z-index:120;transform:translateY(-50%);padding:7px 13px;border-radius:10px;background:var(--ui-side,#0f2a1d);color:#fff;font:650 .8125rem/1.2 var(--font-display,system-ui);white-space:nowrap;pointer-events:none;box-shadow:0 8px 22px rgb(15 42 29 / .28)}
.rail-flyout{z-index:119;min-width:220px;padding:8px 8px 8px 8px;display:flex;flex-direction:column;gap:2px;background:#fff;border:1px solid var(--ui-line);border-radius:16px;box-shadow:0 20px 48px rgb(15 42 29 / .24);animation:rail-fly-in .24s cubic-bezier(.22,.8,.24,1)}
@keyframes rail-fly-in{from{opacity:0;transform:translateX(-8px) scale(.94)}to{opacity:1;transform:none}}
/* Grup aktif berganti warna halus (bukan meloncat). */
#workspace-sidebar .workspace-menu-group>summary,#workspace-sidebar .menu-group-mark{transition:color .2s ease,background-color .16s ease}
.rail-tip[hidden],.rail-flyout[hidden]{display:none}
/* Judul grup di dalam kartu, sebaris ikon. Jembatan tak terlihat menutup celah 8px antara rail dan kartu agar kursor tidak lepas. */
.rail-flyout-title{display:flex;align-items:center;padding:0 12px;margin:0 0 2px;font:700 .875rem/1.2 var(--font-display,system-ui);color:#0f2a1d;white-space:nowrap}
.rail-flyout::before{content:'';position:absolute;right:100%;top:0;bottom:0;width:12px}
.rail-flyout-title+a::before{top:-6px;height:calc(50% + 6px)}
.rail-flyout a{position:relative;display:flex;align-items:center;min-height:38px;margin-left:24px;padding:7px 12px 7px 14px;border-radius:10px;font:500 .875rem/1.3 var(--font-body,system-ui);color:#51655a;text-decoration:none;white-space:nowrap}
.rail-flyout a::before{content:'';position:absolute;left:-15px;top:calc(-50% - 2px);width:14px;height:calc(100% + 2px);border-left:1px solid #ccd8d0;border-bottom:1px solid #ccd8d0;border-radius:0 0 0 10px}
.rail-flyout a:first-child::before{top:-10px;height:calc(50% + 10px)}
.rail-flyout a::after{content:'';width:7px;height:7px;margin-left:auto;padding-left:0;border-right:1.6px solid currentColor;border-top:1.6px solid currentColor;transform:rotate(45deg) translateX(-2px);opacity:0;flex:none}
.rail-flyout a:hover,.rail-flyout a:focus-visible{background:#f1f5f2;color:#0f2a1d}
.rail-flyout a:hover::after,.rail-flyout a:focus-visible::after,.rail-flyout a[aria-current=page]::after{opacity:.7}
.rail-flyout a[aria-current=page]{background:rgb(200 242 107 / .4);color:#0f2a1d;font-weight:650}
.rail-flyout a:focus-visible{outline:3px solid var(--ui-focus,#0b5cab);outline-offset:-2px}

@media (min-width:901px){
  body:has(#workspace-sidebar){padding-left:var(--side-rail)!important;transition:padding-left .2s cubic-bezier(.2,0,0,1)}
  body.sidebar-pinned:has(#workspace-sidebar.pinned){padding-left:var(--side-open)!important}
  #workspace-sidebar.app-sidebar,#workspace-sidebar.app-sidebar:hover,#workspace-sidebar.app-sidebar:focus-within{width:var(--side-rail)!important;min-width:var(--side-rail)!important;overflow-x:hidden;z-index:100;transition:width .2s cubic-bezier(.2,0,0,1),min-width .2s cubic-bezier(.2,0,0,1)}
  #workspace-sidebar.app-sidebar.pinned,#workspace-sidebar.app-sidebar.pinned:hover,#workspace-sidebar.app-sidebar.pinned:focus-within{width:var(--side-open)!important;min-width:var(--side-open)!important}
  #workspace-sidebar .sidebar-close{display:none}
  /* rail: hanya ikon grup; sub-menu tampil lewat kartu hover */
  #workspace-sidebar:not(.pinned) .workspace-submenu{display:none!important}
  #workspace-sidebar:not(.pinned) :is(.workspace-menu-group>summary>span,.workspace-menu-group>summary::after,.sidebar-brand-text,.sidebar-footer form button span,.sidebar-footer .sidebar-help span){opacity:0!important}
  #workspace-sidebar:not(.pinned) .workspace-menu-group:has([aria-current=page])>summary{background:var(--ui-green-soft)}
  #workspace-sidebar:not(.pinned) .workspace-menu-group:has([aria-current=page])>summary::before{content:'';position:absolute;left:-10px;top:9px;bottom:9px;width:3px;border-radius:0 3px 3px 0;background:var(--ui-green)}
  #workspace-sidebar.pinned :is(.workspace-menu-group>summary>span,.sidebar-brand-text,.sidebar-footer form button span,.sidebar-footer .sidebar-help span){opacity:1!important;visibility:visible!important}
  .sidebar-edge{display:flex;position:fixed;top:19px;left:calc(var(--side-rail) - 14px);z-index:110;width:28px;height:28px;padding:0;align-items:center;justify-content:center;border-radius:50%;background:#fff;color:#0f2a1d;border:1px solid var(--ui-line);box-shadow:0 2px 10px rgb(15 42 29 / .22);cursor:pointer;transition:left .2s cubic-bezier(.2,0,0,1),background-color .15s}
  body.sidebar-pinned .sidebar-edge{left:calc(var(--side-open) - 14px)}
  .sidebar-edge:hover{background:var(--ui-accent-soft,#e3f9ea)}
  .sidebar-edge svg{width:14px;height:14px;transition:transform .2s}
  body.sidebar-pinned .sidebar-edge svg{transform:rotate(180deg)}
}
@media (max-width:900px){
  .rail-tip,.rail-flyout{display:none!important}
  #workspace-sidebar .workspace-menu-group>summary,#workspace-sidebar .sidebar-nav .workspace-submenu a,#workspace-sidebar :is(.sidebar-footer form button,.sidebar-footer .sidebar-help){min-height:44px}
  #workspace-sidebar .workspace-menu-group>summary>span{opacity:1!important}
  html body #sidebar-overlay.sidebar-overlay{
    display:block;position:fixed;inset:0;z-index:95;width:100%;height:100%;min-height:0;
    padding:0;border:0;border-radius:0;box-shadow:none;background:var(--ui-sidebar-backdrop);
    opacity:0;visibility:hidden;pointer-events:none;cursor:pointer;
    transition:opacity var(--ui-sidebar-duration) var(--ui-ease-out),visibility 0s var(--ui-sidebar-duration);
  }
  html body.sidebar-open #sidebar-overlay.sidebar-overlay{
    opacity:1;visibility:visible;pointer-events:auto;transition-delay:0s;
  }
}
@media (prefers-reduced-motion:reduce){#workspace-sidebar.app-sidebar,body:has(#workspace-sidebar),.sidebar-edge,.rail-flyout{transition:none!important;animation:none!important}}
/* Sidebar yang dikunci terbuka langsung lebar sejak gambar pertama, tanpa melompat. */
@media (min-width:901px){
  html body:has(#workspace-sidebar){transition:padding-left var(--ui-sidebar-duration) var(--ui-ease-out)}
  html body #workspace-sidebar.app-sidebar{transition:width var(--ui-sidebar-duration) var(--ui-ease-out),min-width var(--ui-sidebar-duration) var(--ui-ease-out)}
  html body #workspace-sidebar :is(.sidebar-nav,.sidebar-brand,.sidebar-footer){width:var(--side-open);min-width:var(--side-open);box-sizing:border-box;flex-shrink:0}
  html body #workspace-sidebar :is(.sidebar-brand-text,.workspace-menu-group>summary>span,.sidebar-footer button span,.sidebar-footer .sidebar-help span){white-space:nowrap;transition:opacity var(--ui-sidebar-text-duration) var(--ui-ease-out)}
  html body #workspace-sidebar .sidebar-nav .workspace-submenu{display:flex !important;max-height:var(--side-submenu-height,none);overflow:hidden;opacity:1;visibility:visible;transition:max-height var(--ui-sidebar-duration) var(--ui-ease-out),opacity var(--ui-sidebar-text-duration) var(--ui-ease-out)}
  html body #workspace-sidebar:not(.pinned) .sidebar-nav .workspace-submenu{display:flex !important;max-height:0;opacity:0;visibility:hidden;margin-block:0 !important;pointer-events:none}
  html body .rail-flyout{width:var(--side-open);box-sizing:border-box}
  html body .rail-flyout :is(.rail-flyout-title,a){flex-shrink:0;white-space:nowrap}
  html.pre-pinned body:has(#workspace-sidebar){padding-left:var(--side-open)!important;transition:none}
  html.pre-pinned body #workspace-sidebar.app-sidebar{width:var(--side-open)!important}
}
@media(prefers-reduced-motion:reduce){
 html body #sidebar-overlay.sidebar-overlay{transition:none}
 html body #workspace-sidebar :is(.sidebar-brand-text,.workspace-menu-group>summary>span,.sidebar-footer button span,.sidebar-footer .sidebar-help span,.sidebar-nav .workspace-submenu){transition:none !important}
}
</style>
