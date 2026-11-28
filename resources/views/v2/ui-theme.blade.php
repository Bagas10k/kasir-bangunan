@include('v2.ui-font-tokens')
<style>
/* ============================================================
   TEMA V2 — latar putih, sidebar gelap kehijauan, aksen hijau terang + tipografi
   Dimuat PALING AKHIR. Ubah warna/huruf lewat token di :root saja.
   Aksen terang (#4ade80) dipakai sebagai latar dengan teks gelap (kontras >= 9:1);
   di atas putih, teks/tautan hijau memakai --ui-green (#14532d).
   ============================================================ */
:root{
  --ui-side:#0f2a1d;--ui-side-2:#143826;--ui-side-line:rgb(255 255 255 / .12);
  --ui-side-tree:rgb(255 255 255 / .24);--ui-side-ink:#e7f3ec;--ui-side-mute:#9fb8a9;
  --ui-side-active:#c8f26b;--ui-side-active-bg:rgb(200 242 107 / .15);
  --ui-sidebar-backdrop:rgb(4 12 8 / .6);
  --ui-accent:#4ade80;--ui-accent-2:#36c46d;--ui-accent-soft:#e3f9ea;--ui-on-accent:#0f2a1d;
  --ui-canvas:#fff;--bg:#fff;--canvas:#fff;
  --ui-line:#dbe5de;--ui-line-soft:#eaf0ec;--ui-head:#f3f7f4;--ui-hover:#f6faf7;
  /* --font-display dan --font-body ada di v2/ui-font-tokens (dipakai juga oleh Pusat Bantuan). */
  --font-sans:var(--font-body);
}

/* ---------- tipografi ---------- */
html{-webkit-text-size-adjust:100%;text-rendering:optimizeLegibility}
body{font-family:var(--font-body);font-size:1rem;line-height:1.55;color:var(--ui-ink);-webkit-font-smoothing:antialiased;font-feature-settings:'cv05','cv11','ss03'}
body :is(button,input,select,textarea,.select-trigger){font-family:var(--font-body)}
body :is(h1,h2,h3,.popup-heading h2,.uf-section-title,.ui-subhead){font-family:var(--font-display)}
.ui-page-header h1{font-size:1.75rem;line-height:1.2;font-weight:700;letter-spacing:-.025em}
.ui-page-header p{font-size:1rem;line-height:1.55;color:var(--ui-ink-2);max-width:64ch}
.ui-eyebrow{font-family:var(--font-display);font-size:.8125rem;font-weight:600;letter-spacing:.02em;color:var(--ui-green)}
body main h2{font-size:1.25rem;line-height:1.3;font-weight:700;letter-spacing:-.015em;color:var(--ui-ink)}
body main h3{font-size:1.0625rem;line-height:1.35;font-weight:650;letter-spacing:-.01em}
body .note,body .ui-sub,body .ui-muted{line-height:1.5}
body :is(.ui-num,.money,td.ui-num,.ui-tile strong,.ui-kv dd,.uf-affix input,[data-ui-number]){font-variant-numeric:tabular-nums lining-nums;font-feature-settings:'tnum','lnum','cv05','cv11','ss03'}
body .ui-table th{font-family:var(--font-body);font-size:.8125rem;font-weight:650;letter-spacing:.01em;color:#33483b;background:var(--ui-head)}
body .ui-table td{font-size:.9375rem;line-height:1.45;color:var(--ui-ink)}
body .ui-table td.ui-primary{font-weight:600}
.uf-label{font-weight:600;letter-spacing:0}
.uf-mono,.sku-code{font-family:'JetBrains Mono',ui-monospace,monospace}

/* ---------- latar putih: kartu dibedakan oleh garis halus, bukan warna latar ---------- */
body,.wrap,.main-content{background:#fff}
body .card,body .ui-tile,body .monitoring-card{background:#fff;border-color:var(--ui-line);box-shadow:none}
body .top,body header.main-topbar{background:#fff;border-bottom:1px solid var(--ui-line)}

/* ---------- aksen terang: tombol utama, chip aktif, tab aktif ---------- */
body :is(.ui-btn,.main-content .popup-action,.main-content .btn-add-master),
body :is(.uf-primary,.btn-action-pill.is-primary){background:var(--ui-accent)!important;border-color:var(--ui-accent)!important;color:var(--ui-on-accent)!important;font-weight:650}
body :is(.ui-btn,.main-content .popup-action,.main-content .btn-add-master,.uf-primary):hover{background:var(--ui-accent-2)!important;border-color:var(--ui-accent-2)!important;color:var(--ui-on-accent)!important}
body :is(.ui-btn--quiet,.main-content .btn-import-master,.main-content .btn-template-download,.main-content .btn-action-pill){background:#fff!important;color:var(--ui-green)!important;border-color:#b7cfbf!important}
body :is(.ui-btn--quiet,.main-content .btn-import-master,.main-content .btn-template-download,.main-content .btn-action-pill):hover{background:var(--ui-accent-soft)!important;border-color:var(--ui-green)!important}
body .main-content .btn-action-archive{color:var(--ui-bad-ink)!important}
body .main-content .btn-action-archive:hover{background:var(--ui-bad-bg)!important}
body .ui-chip[aria-pressed=true]{background:var(--ui-accent);border-color:var(--ui-accent);color:var(--ui-on-accent);font-weight:650}
.ui-subtabs{background:var(--ui-head);border:1px solid var(--ui-line)}
.ui-subtab[aria-selected=true]{background:var(--ui-accent);color:var(--ui-on-accent);box-shadow:none;font-weight:650}
.ui-subtab:hover:not([aria-selected=true]){background:#fff}
.ui-tile--good::before{background:var(--ui-accent-2)}
.uf-seg{background:var(--ui-head);border:1px solid var(--ui-line)}
.uf-seg-item input:checked+span{background:var(--ui-accent);color:var(--ui-on-accent);box-shadow:none;font-weight:650}
.uf-check:has(input:checked){border-color:var(--ui-accent-2);background:var(--ui-accent-soft);color:var(--ui-on-accent)}
.uf-check input[type=checkbox]{accent-color:var(--ui-accent-2)}
body :is(.uf-section-title){color:var(--ui-green)}
body :focus-visible{outline-color:#0b5cab}

/* ---------- sidebar gelap kehijauan ---------- */
#workspace-sidebar.app-sidebar{background:var(--ui-side)!important;border-right:0!important;color:var(--ui-side-ink);isolation:isolate}
#workspace-sidebar.app-sidebar>*{position:relative;z-index:1}
#workspace-sidebar.app-sidebar::before{content:'';position:absolute;inset:0;z-index:0;pointer-events:none;background-image:radial-gradient(rgba(231,243,236,.07) 1px,transparent 1.6px),linear-gradient(rgba(200,242,107,.065) 1px,transparent 1px),linear-gradient(90deg,rgba(200,242,107,.065) 1px,transparent 1px),radial-gradient(125% 65% at 18% 100%,rgba(200,242,107,.16),transparent 62%);background-size:24px 24px,32px 32px,32px 32px,100% 100%;background-position:left bottom;-webkit-mask-image:linear-gradient(to bottom,transparent 0%,transparent 48%,rgba(0,0,0,.22) 66%,rgba(0,0,0,.78) 86%,#000 100%);mask-image:linear-gradient(to bottom,transparent 0%,transparent 48%,rgba(0,0,0,.22) 66%,rgba(0,0,0,.78) 86%,#000 100%)}
#workspace-sidebar .sidebar-brand{background:transparent;border-bottom:1px solid var(--ui-side-line)}
#workspace-sidebar .brand-mark{background:var(--ui-accent);color:var(--ui-on-accent);box-shadow:none}
#workspace-sidebar .sidebar-brand-text strong{color:#fff;font-family:var(--font-display);font-weight:700;letter-spacing:0;word-spacing:.08em}
#workspace-sidebar .sidebar-brand-text small{color:var(--ui-side-mute);text-transform:none;letter-spacing:0;font-weight:500}
#workspace-sidebar .sidebar-close{background:transparent;color:#fff;border-color:var(--ui-side-line)}
#workspace-sidebar .workspace-menu-group>summary{color:var(--ui-side-ink);font-family:var(--font-display);font-weight:650;letter-spacing:.005em}
#workspace-sidebar .workspace-menu-group>summary:hover{background:rgb(255 255 255 / .07)}
#workspace-sidebar .menu-group-mark{color:var(--ui-side-mute)}
#workspace-sidebar .workspace-menu-group:has([aria-current=page])>summary{color:var(--ui-side-active);background:transparent}
#workspace-sidebar .workspace-menu-group:has([aria-current=page])>summary .menu-group-mark{color:var(--ui-side-active)}
#workspace-sidebar .sidebar-nav .workspace-submenu a{color:var(--ui-side-ink);font-weight:500}
#workspace-sidebar .sidebar-nav .workspace-submenu a:hover{background:rgb(255 255 255 / .07);color:#fff}
#workspace-sidebar .sidebar-nav .workspace-submenu a[aria-current=page]{background:var(--ui-side-active-bg);color:var(--ui-side-active);font-weight:650}
html body #workspace-sidebar .sidebar-footer{border-top:1px solid var(--ui-side-line);background:#0a2015;padding:12px 10px calc(12px + env(safe-area-inset-bottom))}
html body #workspace-sidebar .sidebar-footer form button{color:var(--ui-side,#0f2a1d) !important;background:var(--ui-side-active,#c8f26b) !important;font-weight:700;border-radius:10px;box-shadow:0 6px 16px -8px rgba(200,242,107,.45);transition:background-color .16s ease,width var(--ui-sidebar-duration,.2s) var(--ui-ease-out,ease-out),transform .12s ease}
html body #workspace-sidebar .sidebar-footer form button :is(svg,span){color:inherit !important}
html body #workspace-sidebar .sidebar-footer form button svg{margin-left:0}
html body #workspace-sidebar .sidebar-footer form button:hover{background:#d8f890 !important;color:var(--ui-side,#0f2a1d) !important}
html body #workspace-sidebar .sidebar-footer form button:active{transform:scale(.98)}
html body #workspace-sidebar .sidebar-footer form button:focus-visible{outline:2px solid #fff;outline-offset:2px}
#workspace-sidebar .sidebar-footer .sidebar-help{color:color-mix(in srgb,var(--ui-side-active) 60%,var(--ui-side-mute))}
#workspace-sidebar .sidebar-footer .sidebar-help:is(:hover,:focus-visible){background:transparent}
#workspace-sidebar .sidebar-footer .sidebar-help:is(:hover,:focus-visible) span{color:var(--ui-side-active)}
#workspace-sidebar :focus-visible{outline-color:var(--ui-side-active)}
#workspace-sidebar{scrollbar-width:thin;scrollbar-color:rgb(255 255 255 / .25) transparent}
@media (min-width:901px){
  #workspace-sidebar:not(.pinned) .workspace-menu-group:has([aria-current=page])>summary{background:var(--ui-side-active-bg)}
  #workspace-sidebar:not(.pinned) .workspace-menu-group:has([aria-current=page])>summary::before{background:var(--ui-side-active)}
  #workspace-sidebar:not(.pinned) .sidebar-footer form button{width:48px}
}
</style>
