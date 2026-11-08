<style>
/* ============================================================
   HALAMAN LAMA DI CANGKANG V2 (jurnal, laporan, COA, dst. — layouts/app saat V2 aktif)
   Cangkang (sidebar, header, tema) dibagi dengan workspace lewat ui-tokens/ui-sidebar/ui-theme.
   Di sini: menetralkan tata letak lama + kulit tipis agar isi halaman lama sejalan dengan tema.
   ============================================================ */
html:has([data-unified-shell]){font-size:100%}
body:has([data-unified-shell]){font-size:1rem;line-height:1.55}
body:has([data-unified-shell]) .app-layout{display:block;min-height:100dvh}
body:has([data-unified-shell]) .app-main{margin-left:0!important;width:auto!important;max-width:none}
body:has([data-unified-shell]) #workspace-sidebar.app-sidebar{position:fixed;inset:0 auto 0 0;height:100dvh;display:flex;flex-direction:column;overflow-y:auto;scrollbar-width:none;padding:0;transform:none}
body:has([data-unified-shell]) #workspace-sidebar.app-sidebar::-webkit-scrollbar{display:none}
body:has([data-unified-shell]) #workspace-sidebar .sidebar-brand{display:flex;align-items:center;justify-content:flex-start;gap:10px}
body:has([data-unified-shell]) #workspace-sidebar .brand-mark{display:flex;align-items:center;justify-content:center;flex:0 0 40px;width:40px;height:40px;min-width:40px;border-radius:11px}
body:has([data-unified-shell]) #workspace-sidebar .sidebar-brand-text{display:flex;flex-direction:column;gap:1px;white-space:nowrap;min-width:164px}
body:has([data-unified-shell]) #workspace-sidebar .sidebar-brand-text strong{display:block;line-height:1.25}
body:has([data-unified-shell]) #workspace-sidebar .sidebar-brand-text small{display:block;line-height:1.25}
body:has([data-unified-shell]) #workspace-sidebar .nav.sidebar-nav{flex:1 1 auto;margin:0}
body:has([data-unified-shell]) #workspace-sidebar .sidebar-close{display:none;align-items:center;justify-content:center;margin-left:auto;width:40px;height:40px;padding:0;border-radius:10px;background:transparent;font-size:22px;line-height:1;cursor:pointer}
body:has([data-unified-shell]) .sidebar-overlay{display:none}

/* header sejajar dengan kepala sidebar (60px) */
body:has([data-unified-shell]) .main-topbar{position:sticky;top:0;z-index:90;display:flex;align-items:center;height:60px;min-height:60px;padding:0 28px;background:#fff;border-bottom:1px solid var(--ui-line)}
body:has([data-unified-shell]) .unified-topbar-inner{height:100%}
body:has([data-unified-shell]) .top-context strong{font-family:var(--font-display);font-size:1.25rem;font-weight:700;letter-spacing:-.015em;color:var(--ui-ink)}
body:has([data-unified-shell]) .header-state{font-size:.875rem;color:var(--ui-ink-2)}
body:has([data-unified-shell]) .header-state a{min-height:36px;color:var(--ui-green);font-weight:600;text-decoration:none}
body:has([data-unified-shell]) .main-topbar .mobile-menu-toggle{display:none;align-items:center;justify-content:center;width:44px;height:44px;padding:0;border:1px solid var(--ui-line);border-radius:10px;background:#fff;color:var(--ui-ink);cursor:pointer}
body:has([data-unified-shell]) .main-content{padding:24px 28px 40px;max-width:1480px}

@media (max-width:900px){
  body:has([data-unified-shell]),body:has([data-unified-shell]).sidebar-pinned{padding-left:0!important}
  body:has([data-unified-shell]) #workspace-sidebar.app-sidebar{width:min(300px,84vw)!important;min-width:0!important;transform:translateX(-100%);transition:transform .16s ease;box-shadow:0 18px 48px rgb(15 42 29 / .3)}
  body.sidebar-open:has([data-unified-shell]) #workspace-sidebar.app-sidebar{transform:none}
  body.sidebar-open:has([data-unified-shell]){overflow:hidden}
  body:has([data-unified-shell]) #workspace-sidebar .sidebar-close{display:inline-flex;color:#fff;border:1px solid var(--ui-side-line)}
  body:has([data-unified-shell]) .main-topbar{padding:0 16px}
  body:has([data-unified-shell]) .main-topbar .mobile-menu-toggle{display:inline-flex}
  body:has([data-unified-shell]) .main-content{padding:16px}
  body:has([data-unified-shell]) .sidebar-overlay{position:fixed;inset:0;z-index:95;width:100%;height:100%;padding:0;border:0;border-radius:0;background:rgb(15 42 29 / .5);cursor:pointer}
  body.sidebar-open:has([data-unified-shell]) .sidebar-overlay{display:block}
}

/* ---------- kulit isi halaman lama ---------- */
body:has([data-unified-shell]) .main-content *{text-transform:none}
body:has([data-unified-shell]) .main-content :is(th,.badge,[class*=label],[class*=caption],[class*=kicker],[class*=eyebrow]){letter-spacing:0}
body:has([data-unified-shell]) .main-content .btn:not(.btn-secondary):not(.btn-danger):not(.btn-action-edit):not(.btn-action-archive){
  background:var(--ui-accent);color:var(--ui-on-accent);border:1px solid var(--ui-accent);border-radius:10px;box-shadow:none;font-weight:650;min-height:44px}
body:has([data-unified-shell]) .main-content .btn:not(.btn-secondary):not(.btn-danger):not(.btn-action-edit):not(.btn-action-archive):hover{background:var(--ui-accent-2);border-color:var(--ui-accent-2);color:var(--ui-on-accent);transform:none}
body:has([data-unified-shell]) .main-content .btn-secondary{background:#fff;color:var(--ui-green);border:1px solid #b7cfbf;border-radius:10px;box-shadow:none;font-weight:600;min-height:44px}
body:has([data-unified-shell]) .main-content .btn-secondary:hover{background:var(--ui-accent-soft);border-color:var(--ui-green)}
body:has([data-unified-shell]) .main-content :is(.tabular-nums,.amount,.num,.money,td.num,.summary-value,.master-summary-value,.balance-tile strong,.journal-total){font-family:var(--font-body);font-variant-numeric:tabular-nums lining-nums}
body:has([data-unified-shell]) .main-content :is(.form-control,.master-control,select,input[type=text],input[type=date],input[type=number]){font-family:var(--font-body);border-color:#c5d3ca;border-radius:10px}
body:has([data-unified-shell]) .main-content :is(.form-control,.master-control):focus{border-color:var(--ui-green);box-shadow:0 0 0 3px rgb(20 83 45 / .14);outline:none}
body:has([data-unified-shell]) .main-content .card{border-color:var(--ui-line);box-shadow:none}

/* ---------- ukuran teks minimum (>= 13px; isi tabel 15px; tombol 14px) ---------- */
body:has([data-unified-shell]) .main-content :is(.btn,.btn-action-edit,.btn-action-delete,.btn-action-locked,.preset-btn,.report-reset,.report-apply,.page-btn,.select-trigger,.date-trigger,summary){font-size:.875rem}
body:has([data-unified-shell]) .main-content :is(label,.field label,.form-group label,.master-control label,.filter-caption,.range-sep,.ledger-status,.ledger-caption,.feed-note,.breadcrumb,.breadcrumb .sep,small,.master-summary-label,.summary-label,.master-status,.sku-code,.pagination-meta,.adjustment-hint,.section-copy,.master-table-head span,.opening-row span,.balance-tile span){font-size:.8125rem;line-height:1.45}
body:has([data-unified-shell]) .main-content :is(th,th.num,th.section-title){font-size:.8125rem;font-weight:650;letter-spacing:.01em}
body:has([data-unified-shell]) .main-content :is(td,td.amount,td.amount strong,.opening-row strong){font-size:.9375rem}
body:has([data-unified-shell]) .main-content :is(.master-table-head h2,.section-title,h2){font-size:1.125rem}
body:has([data-unified-shell]) .main-content :is(.balance-tile small,.master-identity small){font-size:.8125rem}

/* sisa: judul kolom/bagian tidak huruf besar, angka/teks bantu tidak monospace, dropdown 14px */
body:has([data-unified-shell]) .main-content :is(th,th.section-title){text-transform:none!important;letter-spacing:.01em}
body:has([data-unified-shell]) .main-content :is(.ledger-status,.ledger-caption,.ledger-caption span,.pagination-meta,.master-table-head span,.feed-note,.feed-note span,.opening-row strong,.statement-summary strong,.balance-tile strong,td.amount,td.num,.journal-total,.journal-total strong){font-family:var(--font-body)!important;font-variant-numeric:tabular-nums lining-nums}
body:has([data-unified-shell]) .main-content :is(.ledger-caption,.ledger-caption span,.feed-note,.feed-note span,.pagination-meta,.master-table-head span,.ledger-status){font-size:.8125rem}
body:has([data-unified-shell]) .main-content :is(.select-trigger,.select-shell>.select-trigger){font-size:.875rem!important}

/* kartu ringkasan, penanda urut, dan catatan di halaman lama lainnya */
body:has([data-unified-shell]) .main-content :is(.receivable-summary-card,.opname-summary-card,.inventory-summary-card,.card>div>span,.page-header-meta,.count-indicator,.table-perpage,.table-perpage span,.receivables-note,.badge,.th-arrow){font-size:.8125rem!important;line-height:1.45}
body:has([data-unified-shell]) .main-content :is(.receivable-summary-card,.opname-summary-card,.inventory-summary-card)>span{font-size:.8125rem!important;letter-spacing:0;font-weight:600}
body:has([data-unified-shell]) .main-content :is(.receivable-summary-card,.opname-summary-card,.inventory-summary-card) strong{font-family:var(--font-body);font-variant-numeric:tabular-nums lining-nums}
body:has([data-unified-shell]) .main-content .main-content *:where(span,div,strong):not([class]){font-size:max(1em,.8125rem)}

/* gaya inline kecil (mis. ringkasan pengeluaran, penerimaan) */
body:has([data-unified-shell]) .main-content :is([style*="font-size:10"],[style*="font-size: 10"],[style*="font-size:11"],[style*="font-size: 11"],[style*="font-size:12"],[style*="font-size: 12"],[style*="font-size:.7"],[style*="font-size: .7"]){font-size:.8125rem!important}

/* gaya inline: tanpa huruf besar semua, angka memakai font isi (tabular) */
body:has([data-unified-shell]) .main-content [style*="text-transform"]{text-transform:none!important;letter-spacing:0!important}
body:has([data-unified-shell]) .main-content :is(span,strong,div)[style*="mono"]{font-family:var(--font-body)!important;font-variant-numeric:tabular-nums lining-nums}
</style>
