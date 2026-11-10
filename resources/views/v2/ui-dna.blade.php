<style>
/* ============================================================
   DNA TAMPILAN (acuan: referensi FlowAI dari pengguna, warna tetap hijau sidebar)
   Dimuat PALING AKHIR di kedua cangkang (v2/workspace dan layouts/app).
   Kontrak:
   - Kanvas abu sangat muda, kartu putih bergaris tipis + bayangan lembut.
   - Radius hanya tiga: kendali 8px, kartu 12px, dialog 16px. Tidak ada tombol kapsul;
     bentuk pil hanya untuk lencana status kecil.
   - Ubin angka: kartu luar + panel dalam berwarna lembut (label + angka), keterangan di bawah.
   - Tabel: tanpa bingkai, kepala kolom berupa pita lembut bersudut membulat, baris bergaris tipis.
   - Saringan: kolom cari + kontrol bersegmen (satu wadah), sama di semua tabel dan laporan.
   - Aksi baris tabel: tombol garis kecil, bukan tombol penuh.
   ============================================================ */
:root{
  --dna-canvas:#f6f8f7;
  --dna-card:#fff;
  --dna-line:#e4ebe6;
  --dna-line-2:#edf2ee;
  --dna-panel:#f5f8f6;
  --dna-head:#f1f5f2;
  --dna-hover:#f8fbf9;
  --dna-r-ctl:8px;
  --dna-r-card:12px;
  --dna-r-dlg:16px;
  --dna-shadow:0 1px 2px rgb(16 40 28 / .04),0 6px 18px rgb(16 40 28 / .04);
  --dna-ctl-h:38px;
}

/* ---------- 0. kanvas ---------- */
html body{background:var(--dna-canvas)}
html body main.wrap,html body .main-content,html body .app-main{background:transparent}
html body .main-topbar,html body:has([data-unified-shell]) .main-topbar{background:var(--ui-side);border-bottom-color:var(--ui-side-line);color:var(--ui-side-ink)}
html body .main-topbar :is(.top-context strong,.topbar-location,.header-state,.header-state>span){color:var(--ui-side-ink)}
html body .main-topbar .header-state a{color:var(--ui-side-active);border-color:var(--ui-side-line)}
html body .main-topbar .header-state a:hover{background:var(--ui-side-2);color:var(--ui-side-active)}
html body .main-topbar .mobile-menu-toggle,html body:has([data-unified-shell]) .main-topbar .mobile-menu-toggle{background:var(--ui-side-2);border-color:var(--ui-side-line);color:var(--ui-side-ink)}
html body .main-topbar :is(a,button):focus-visible{outline:2px solid var(--ui-side-active);outline-offset:3px}

/* ---------- 1. kepala halaman (judul kiri, aksi kanan) ---------- */
html body .ui-page-header{border-bottom:0;padding:2px 0 0;margin:0 0 16px}
html body .ui-page-header h1{font-size:1.375rem;letter-spacing:-.02em;margin:0}
html body .ui-page-header p{margin:4px 0 0;color:var(--ui-ink-3)}

/* ---------- 2. kartu ---------- */
html body main :is(.card,section.card),
html body [data-remote-content] .card{background:var(--dna-card);border:1px solid var(--dna-line);border-radius:var(--dna-r-card);box-shadow:var(--dna-shadow);padding:16px 18px}
html body main .card>h2,html body main section.card>h2{font-size:1rem;font-weight:700;letter-spacing:-.01em}
html body main .card .card{box-shadow:none}

/* ---------- 3. ubin angka: kartu luar + panel dalam ---------- */
html body .ui-monitor-grid{gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))}
html body .ui-tile{display:flex;flex-direction:column;gap:0;padding:6px;border:1px solid var(--dna-line);border-radius:var(--dna-r-card);background:#fff;box-shadow:var(--dna-shadow);text-align:left}
html body .ui-tile::before{display:none}
html body .ui-tile-body{display:flex;flex-direction:column;gap:4px;padding:10px 12px 11px;border:1px solid var(--dna-line-2);border-radius:var(--dna-r-ctl);background:var(--dna-panel)}
html body .ui-tile-label{display:flex;align-items:center;gap:7px;font-size:.8125rem;font-weight:600;color:var(--ui-ink-3);line-height:1.25}
html body .ui-tile-label::before{content:'';width:7px;height:7px;border-radius:2px;background:#9fb3a6;flex:none}
html body .ui-tile-value{font-size:1.5rem;font-weight:700;line-height:1.15;letter-spacing:-.02em;color:var(--ui-ink);margin:0}
html body .ui-tile-hint{display:block;padding:7px 6px 2px;font-size:.8125rem;line-height:1.3;color:var(--ui-ink-3)}
html body .ui-tile--good .ui-tile-label::before{background:#1f8a55}
html body .ui-tile--good .ui-tile-hint{color:#17663f}
html body .ui-tile--attention .ui-tile-label::before{background:#c58a00}
html body .ui-tile--attention .ui-tile-hint{color:#7a5300}
html body .ui-tile--problem .ui-tile-label::before{background:#b3261e}
html body .ui-tile--problem .ui-tile-hint{color:#9a2a22}
html body button.ui-tile{cursor:pointer;font:inherit;border-radius:var(--dna-r-card) !important;min-height:0 !important;padding:6px !important}
html body button.ui-tile:hover{border-color:#c9d8ce}
html body .ui-tile[aria-pressed=true]{border-color:var(--ui-green);background:#fff;box-shadow:0 0 0 1px var(--ui-green) inset,var(--dna-shadow)}
html body .ui-tile[aria-pressed=true] .ui-tile-body{background:var(--ui-green-soft);border-color:#cfe3d6}

/* ---------- 4. tabel: tanpa bingkai, kepala berupa pita ---------- */
html body .ui-table-wrap{border:0;border-radius:0;background:transparent;overflow-x:auto}
html body .ui-table,
html body :is([data-remote-content],.main-content) table:not(.report-table){border-collapse:separate;border-spacing:0;width:100%}
html body .ui-table thead th,
html body :is([data-remote-content],.main-content) table:not(.report-table) thead th{background:var(--dna-head);border:0;color:var(--ui-ink-3);font-size:.8125rem;font-weight:600;padding:10px 12px;height:auto}
html body .ui-table thead th:first-child,
html body :is([data-remote-content],.main-content) table:not(.report-table) thead th:first-child{border-radius:var(--dna-r-ctl) 0 0 var(--dna-r-ctl)}
html body .ui-table thead th:last-child,
html body :is([data-remote-content],.main-content) table:not(.report-table) thead th:last-child{border-radius:0 var(--dna-r-ctl) var(--dna-r-ctl) 0}
html body .ui-table tbody tr{border:0}
html body .ui-table tbody td,
html body :is([data-remote-content],.main-content) table:not(.report-table) tbody td{border:0;border-bottom:1px solid var(--dna-line-2);padding:12px;vertical-align:middle}
html body .ui-table tbody tr:last-child td,
html body :is([data-remote-content],.main-content) table:not(.report-table) tbody tr:last-child td{border-bottom:0}
html body .ui-table tbody tr:hover,
html body :is([data-remote-content],.main-content) table:not(.report-table) tbody tr:hover{background:var(--dna-hover)}
/* aksi baris: tombol garis kecil */
html body main table td.ui-actions :is(button,.btn,a.btn,.popup-action):not(.popup-close):not(.ui-chip),
html body main table td :is(.btn-action-pill,.btn-table-action){min-height:32px;padding:4px 12px;border-radius:var(--dna-r-ctl);background:#fff !important;border:1px solid var(--dna-line) !important;color:var(--ui-green) !important;font-size:.875rem;font-weight:600;box-shadow:none !important}
html body main table td.ui-actions :is(button,.btn,a.btn,.popup-action):not(.popup-close):not(.ui-chip):hover,
html body main table td :is(.btn-action-pill,.btn-table-action):hover{background:var(--ui-green-soft) !important;border-color:#bcd6c5 !important;color:var(--ui-green-2) !important}

/* ---------- 5. saringan: cari + kontrol bersegmen ---------- */
html body .ui-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:2px 0 12px}
html body .ui-toolbar .ui-search{display:flex;align-items:center;gap:8px;flex:0 1 300px;min-width:200px;height:var(--dna-ctl-h);padding:0 11px;border:1px solid var(--dna-line);border-radius:var(--dna-r-ctl);background:#fff}
html body .ui-toolbar .ui-search:focus-within{border-color:var(--ui-green);box-shadow:0 0 0 3px rgb(20 83 45 / .10)}
html body .ui-toolbar .ui-search svg{width:16px;height:16px;flex:none;color:var(--ui-ink-3)}
html body .ui-toolbar .ui-search input{flex:1;min-width:0;min-height:0;height:100%;padding:0;border:0;outline:0;box-shadow:none;background:transparent;font-size:.9375rem}
html body .ui-toolbar .ui-search input:focus{outline:0;box-shadow:none}
html body main .ui-toolbar .ui-search input[type=search]{min-height:0 !important;height:100%;padding:0 !important;border:0 !important;border-radius:0 !important;box-shadow:none !important;background:transparent !important}
html body .ui-toolbar .ui-search svg{position:static !important;transform:none !important}
html body .ui-chips,
html body .pd-seg,
html body .period-presets{display:inline-flex;flex-wrap:nowrap;align-items:center;gap:2px;padding:3px;max-width:100%;overflow-x:auto;scrollbar-width:none;background:var(--dna-head);border:1px solid var(--dna-line);border-radius:10px}
html body .ui-chips::-webkit-scrollbar,html body .period-presets::-webkit-scrollbar{display:none}
html body .ui-chip,
html body .pd-seg button,
html body .preset-btn{flex:none;display:inline-flex;align-items:center;justify-content:center;min-height:32px;height:32px;padding:0 12px;border:0;border-radius:7px;background:transparent;color:var(--ui-ink-2);font-size:.875rem;font-weight:600;white-space:nowrap;text-decoration:none;box-shadow:none;cursor:pointer}
html body .ui-chip:hover,html body .pd-seg button:hover,html body .preset-btn:hover{background:rgb(255 255 255 / .7);color:var(--ui-ink);border:0}
html body .ui-chip[aria-pressed=true],
html body .pd-seg button[aria-pressed=true],
html body .preset-btn.active{background:#fff;color:var(--ui-green);border:0;box-shadow:0 1px 2px rgb(16 40 28 / .12),0 0 0 1px var(--dna-line)}
html body .ui-toolbar-actions{margin-left:auto;display:flex;gap:8px}

/* ---------- 6. tombol: satu radius, dua jenis ---------- */
html body main :is(button,.btn,a.btn,.popup-action,.btn-add-master,.btn-import-master,input[type=submit],.report-action):not(.ui-chip):not(.preset-btn):not(.rc-remove):not(.ui-tile):not([data-step]):not([data-remove]):not(.date-trigger):not(.select-trigger):not(.select-option):not(.calendar-nav){border-radius:var(--dna-r-ctl)}
html body main :is(button[type=submit],.btn-add-master,.popup-action,.btn-pill-submit,.btn:not(.btn-secondary):not(.secondary),.report-apply):not(.ui-chip):not(td *){background:var(--ui-green);border:1px solid var(--ui-green);color:#fff;box-shadow:0 1px 2px rgb(16 40 28 / .18)}
html body main :is(button[type=submit],.btn-add-master,.popup-action,.btn-pill-submit,.btn:not(.btn-secondary):not(.secondary),.report-apply):not(.ui-chip):not(td *):hover{background:var(--ui-green-2);border-color:var(--ui-green-2)}
html body main :is(button.secondary,.btn-secondary,.btn-import-master):not(.ui-chip):not(.popup-close){background:#fff;border:1px solid var(--dna-line);color:var(--ui-ink);box-shadow:0 1px 1px rgb(16 40 28 / .04)}
html body main :is(button.secondary,.btn-secondary,.btn-import-master):not(.ui-chip):not(.popup-close):hover{background:var(--dna-hover);border-color:#c9d8ce}
html body .btn-action-pill{border-radius:var(--dna-r-ctl)}

/* ---------- 7. isian ---------- */
html body main :is(input,select,textarea):not([type=checkbox]):not([type=radio]):not([type=hidden]):not(.ui-pick-native),
html body main .select-trigger,html body main .date-trigger{border-radius:var(--dna-r-ctl);border-color:var(--dna-line)}
html body main :is(input,select,textarea):not([type=checkbox]):not([type=radio]):focus{outline:0;border-color:var(--ui-green);box-shadow:0 0 0 3px rgb(20 83 45 / .12)}

/* ---------- 8. dialog ---------- */
html body dialog.input-dialog{border:1px solid var(--dna-line);border-radius:var(--dna-r-dlg);box-shadow:0 24px 64px rgb(16 40 28 / .18),0 4px 16px rgb(16 40 28 / .08)}
html body dialog.input-dialog .popup-heading{padding:14px 16px 12px 20px;border-bottom:1px solid var(--dna-line-2)}
html body dialog.input-dialog .popup-content{padding:16px 20px}
html body dialog.input-dialog .popup-footer{padding:12px 20px;border-top:1px solid var(--dna-line-2);gap:8px}
html body dialog.input-dialog .popup-close{border-radius:var(--dna-r-ctl)}

/* ---------- 9. saringan lama (jurnal, COA, dst.): sama dengan tombol garis ---------- */
html body .filter-disclosure>summary{display:inline-flex;align-items:center;gap:8px;min-height:var(--dna-ctl-h);padding:0 14px;border:1px solid var(--dna-line);border-radius:var(--dna-r-ctl);background:#fff;color:var(--ui-ink);font-size:.875rem;font-weight:600;box-shadow:0 1px 1px rgb(16 40 28 / .04);cursor:pointer;list-style:none}
html body .filter-disclosure>summary::-webkit-details-marker{display:none}
html body .filter-disclosure>summary::before{content:'';width:14px;height:14px;background:currentColor;-webkit-mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round'%3E%3Cpath d='M3 5h18M6 12h12M10 19h4'/%3E%3C/svg%3E") center/contain no-repeat;mask:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round'%3E%3Cpath d='M3 5h18M6 12h12M10 19h4'/%3E%3C/svg%3E") center/contain no-repeat}
html body .filter-disclosure>summary::after{display:none}
html body .filter-disclosure[open]>summary{border-color:var(--ui-green);color:var(--ui-green)}
html body .filter-popup{border:1px solid var(--dna-line)!important;border-radius:var(--dna-r-card)!important;box-shadow:0 18px 44px rgb(16 40 28 / .14)!important}

/* ---------- 10. laporan keuangan (laba rugi, neraca, arus kas) ---------- */
/* ubin saldo jurnal: kartu luar + panel dalam, sama dengan ubin lain */
html body .balance-strip{gap:12px}
html body .balance-tile{position:relative;padding:18px 18px 17px;border:1px solid var(--dna-line);border-radius:var(--dna-r-card);background:var(--dna-panel);box-shadow:inset 0 0 0 6px #fff,var(--dna-shadow)}
html body .balance-tile::after{content:'';position:absolute;inset:6px;border:1px solid var(--dna-line-2);border-radius:var(--dna-r-ctl);pointer-events:none}
html body .balance-tile small{display:flex;align-items:center;gap:7px;margin:0 0 4px;font-size:.8125rem;font-weight:600;color:var(--ui-ink-3)}
html body .balance-tile small::before{content:'';width:7px;height:7px;border-radius:2px;background:#9fb3a6;flex:none}
html body .balance-tile strong{font-family:inherit;font-size:1.5rem;font-weight:700;letter-spacing:-.02em;color:var(--ui-ink)}
html body .balance-tile span{font-size:.8125rem;color:var(--ui-ink-3)}
html body .ledger-status{font-family:inherit;font-size:.8125rem;font-weight:600;border-radius:var(--dna-r-ctl)}
html body .report-shell,html body main .card.report-shell{padding:0;border:0;border-radius:0;background:transparent;box-shadow:none;overflow:visible;animation:none}
html body .report-shell .statement-header{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:12px 20px;padding:2px 0 16px;border:0}
html body .report-shell .breadcrumb{display:none}
html body .report-shell .statement-title h2{font-size:1.375rem;font-weight:700;letter-spacing:-.02em;line-height:1.25;color:var(--ui-ink)}
html body .report-shell .report-period{margin:4px 0 0;font-size:.875rem;color:var(--ui-ink-3)}
html body .report-shell .statement-actions{display:flex;gap:8px}
html body .report-shell .report-action{display:inline-flex;align-items:center;gap:8px;min-height:var(--dna-ctl-h);padding:0 14px;font-size:.875rem;font-weight:600;text-decoration:none}
html body .report-shell .report-action.btn-secondary{background:#fff;border:1px solid var(--dna-line);color:var(--ui-ink)}

html body .report-shell .statement-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:0 0 14px;border:0;background:transparent}
/* kartu luar + panel dalam tanpa ubah markup: bayangan ke dalam 6px menjadi bingkai putih */
html body .report-shell .summary-item{position:relative;padding:18px 18px 17px;border:1px solid var(--dna-line);border-radius:var(--dna-r-card);background:var(--dna-panel);box-shadow:inset 0 0 0 6px #fff,var(--dna-shadow)}
html body .report-shell .summary-item::after{content:'';position:absolute;inset:6px;border:1px solid var(--dna-line-2);border-radius:var(--dna-r-ctl);pointer-events:none}
html body .report-shell .summary-item:last-child{border-right:1px solid var(--dna-line)}
html body .report-shell .summary-label{display:flex;align-items:center;gap:7px;margin:0 0 4px;font-size:.8125rem;font-weight:600;color:var(--ui-ink-3)}
html body .report-shell .summary-label::before{content:'';width:7px;height:7px;border-radius:2px;background:#9fb3a6;flex:none}
html body .report-shell .summary-value{font-family:inherit;font-size:1.5rem;font-weight:700;letter-spacing:-.02em;color:var(--ui-ink)}
html body .report-shell .summary-item.is-result{background:var(--ui-green-soft)}
html body .report-shell .summary-item.is-result .summary-label::before{background:#1f8a55}
html body .report-shell .summary-item.is-result .summary-value{color:var(--ui-green)}
html body .report-shell .summary-item.is-alert{background:var(--ui-bad-bg)}
html body .report-shell .summary-item.is-alert .summary-label::before{background:#b3261e}
html body .report-shell .summary-item.is-alert .summary-value{color:var(--ui-bad-ink)}

/* saringan periode: selalu terlihat, satu baris */
html body .report-shell .report-toolbar{display:block;margin:0 0 14px;padding:10px 12px;border:1px solid var(--dna-line);border-radius:var(--dna-r-card);background:#fff;box-shadow:var(--dna-shadow)}
html body .report-shell .report-filter-form,
html body .report-shell .filter-cluster{display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-start;gap:10px 14px;width:100%}
html body .report-shell .preset-cluster{display:flex;align-items:center;gap:10px;min-width:0;max-width:100%}
html body .report-shell .filter-caption{display:inline;font-size:.8125rem;font-weight:600;color:var(--ui-ink-3);margin:0}
html body .report-shell .custom-period{display:flex;align-items:center;margin-left:auto;position:static}
html body .report-shell .custom-period .date-cluster{position:static;display:flex;flex-wrap:wrap;align-items:center;gap:8px;min-width:0;padding:0;border:0;border-radius:0;background:transparent;box-shadow:none}
html body .report-shell .custom-period .field{flex-direction:row;align-items:center;gap:6px;margin:0}
html body .report-shell .custom-period .field label{margin:0;font-size:.8125rem;font-weight:600;color:var(--ui-ink-3)}
html body .report-shell .custom-period .field :is(input[type=date],.date-field){width:auto;min-width:150px}
html body .report-shell .custom-period :is(input[type=date],.date-trigger){min-height:var(--dna-ctl-h);height:var(--dna-ctl-h);border-radius:var(--dna-r-ctl);border:1px solid var(--dna-line);font-size:.875rem}
html body .report-shell .range-sep{font-size:.8125rem;color:var(--ui-ink-3)}
html body .report-shell .report-apply{min-height:var(--dna-ctl-h);padding:0 16px;font-size:.875rem;font-weight:600}
html body .report-shell .report-reset{min-height:var(--dna-ctl-h);font-size:.875rem;color:var(--ui-ink-3)}

/* rincian akun: satu panel kartu */
html body .report-shell .ledger-caption{margin:0;padding:14px 18px 10px;border:1px solid var(--dna-line);border-bottom:0;border-radius:var(--dna-r-card) var(--dna-r-card) 0 0;background:#fff}
html body .report-shell .ledger-caption strong{font-size:1rem;font-weight:700;color:var(--ui-ink)}
html body .report-shell .ledger-caption span{font-size:.8125rem;color:var(--ui-ink-3)}
html body .report-shell .report-ledger{padding:0 12px 12px;border:1px solid var(--dna-line);border-top:0;border-radius:0 0 var(--dna-r-card) var(--dna-r-card);background:#fff}
html body .report-shell .report-table{border-collapse:separate;border-spacing:0}
html body .report-shell .report-table thead th{position:static;padding:10px 12px;border:0;background:var(--dna-head);color:var(--ui-ink-3);font-size:.8125rem;font-weight:600}
html body .report-shell .report-table thead th:first-child{border-radius:var(--dna-r-ctl) 0 0 var(--dna-r-ctl)}
html body .report-shell .report-table thead th:last-child{border-radius:0 var(--dna-r-ctl) var(--dna-r-ctl) 0}
html body .report-shell .report-table th.section-title{padding:18px 12px 6px;border:0;background:transparent;color:var(--ui-ink);font-size:.9375rem;font-weight:700}
html body .report-shell .report-table td{padding:10px 12px;border:0;border-bottom:1px solid var(--dna-line-2);font-size:.9375rem;color:var(--ui-ink-2);line-height:1.35}
html body .report-shell .report-table td.code{font-size:.8125rem;color:var(--ui-ink-3)}
html body .report-shell .report-table td.amount{font-family:inherit;font-size:.9375rem;font-weight:600;color:var(--ui-ink)}
html body .report-shell .report-table tr.total td{padding-top:11px;padding-bottom:11px;border-top:0;border-bottom:1px solid var(--dna-line);background:var(--dna-panel);color:var(--ui-ink);font-weight:700}
html body .report-shell .report-table tr.grand td{padding-top:12px;padding-bottom:12px;border:0;background:var(--ui-green-soft);color:var(--ui-green);font-weight:800}
html body .report-shell .report-table tr.grand td:first-child{border-radius:var(--dna-r-ctl) 0 0 var(--dna-r-ctl)}
html body .report-shell .report-table tr.grand td:last-child{border-radius:0 var(--dna-r-ctl) var(--dna-r-ctl) 0}
html body .report-shell .report-table tbody tr:not(.grand):not(.total):not(:has(.section-title)):hover td{background:var(--dna-hover)}

/* ---------- 11. kasir (selektor lama dua ID) ---------- */
html body #panel-kasir #kasir .pd,
html body #panel-kasir #kasir :is(.card,.pos-catalog-card,.pos-cart-card){border:1px solid var(--dna-line);border-radius:var(--dna-r-card);box-shadow:var(--dna-shadow)}
html body #panel-kasir #kasir .pd-kpis{gap:10px;padding:12px 16px}
html body #panel-kasir #kasir .pd-kpi{padding:10px 12px;border:1px solid var(--dna-line-2);border-radius:var(--dna-r-ctl);background:var(--dna-panel)}
html body #panel-kasir #kasir .pd-kpi:first-child{padding-left:12px;border-left:1px solid var(--dna-line-2)}
html body #panel-kasir #kasir .pd-seg button{min-height:32px}
html body #panel-kasir #kasir .pd-seg button[aria-pressed=true]{background:#fff;color:var(--ui-green);box-shadow:0 1px 2px rgb(16 40 28 / .12),0 0 0 1px var(--dna-line)}
html body #panel-kasir #kasir .pd-controls{flex-wrap:wrap}
html body #panel-kasir #kasir .pd-head .pos-shift-notice{display:flex;align-items:center;flex-wrap:nowrap;gap:8px;margin:0;padding:0;border:0;border-radius:0;background:transparent;box-shadow:none}
html body #panel-kasir #kasir .pd-head .pos-shift-copy{flex:0 1 auto;min-width:0}
html body #panel-kasir #kasir .pd-head .pos-shift-copy strong{font-size:.8125rem;line-height:1.4}
html body #panel-kasir #kasir .pd-head .pos-shift-action{width:auto;min-height:44px;padding:8px 10px;font-size:.8125rem;gap:6px}
@media(max-width:520px){html body #panel-kasir #kasir .pd-head .pos-shift-notice{width:100%;justify-content:space-between}}
html body #panel-kasir #kasir .pd-fold{border-radius:var(--dna-r-ctl)}
html body #panel-kasir #kasir .btn-pill-submit{border-radius:10px}
html body #panel-kasir #kasir :is(.pos-search-box,.pos-buyer-card,.summary-box,.catalog-table-scroll){border-radius:var(--dna-r-card)}
html body #panel-kasir #kasir #products-table thead th{border:0;background:var(--dna-head) !important}
html body #panel-kasir #kasir .btn-table-action{border-radius:var(--dna-r-ctl)}
html body #panel-kasir #kasir .summary-box{padding:12px 16px}
html body #panel-kasir #kasir .summary-box .summary-total{padding:0;margin:0;min-height:0}
/* Bentuk kartu mengikuti katalog; warna kasir tetap hijau gelap sidebar. */
html body #panel-kasir #kasir .pos-cart-pane>section.card{background:var(--ui-side) !important;border-color:var(--ui-side-line);color:var(--ui-side-ink)}
html body #panel-kasir #kasir :is(#h-checkout,.cart-label strong){color:var(--ui-side-ink)}
html body #panel-kasir #kasir :is(#cart-item-count,.pos-shift-reminder){color:var(--ui-side-ink)}
html body #panel-kasir #kasir #open-payment:not(:disabled){background:var(--ui-side-active) !important;border-color:var(--ui-side-active) !important;color:var(--ui-side) !important}
html body #panel-kasir #kasir #open-payment:hover:not(:disabled){filter:brightness(.95)}
html body #panel-kasir #kasir #open-payment:focus-visible{outline-color:var(--ui-side-active)}
html body #panel-kasir #kasir #open-payment:disabled{background:var(--ui-side-2) !important;border-color:var(--ui-side-line) !important;color:var(--ui-side-mute) !important}
html body #panel-kasir #kasir .cart-table-scroll{background:var(--ui-surface);border-radius:var(--dna-r-ctl)}
html body #panel-kasir #kasir .cart-column-header{background:var(--ui-green-soft);color:var(--ui-green)}
html body #panel-kasir #kasir #form-checkout>.summary-box{background:var(--ui-green) !important;border-color:var(--ui-green) !important}
html body #panel-kasir #kasir #form-checkout>.summary-box .summary-total :is(span,strong){color:var(--ui-surface) !important}

/* Pelanggan = field ringkas, bukan kartu bersarang di dalam checkout. */
html body #panel-kasir #kasir #form-checkout>.pos-buyer-card{padding:0;margin:0;border:0;border-radius:0;background:transparent !important;box-shadow:none}
html body #panel-kasir #kasir #form-checkout>.pos-buyer-card .pos-buyer-header{margin:0 0 6px;gap:6px}
html body #panel-kasir #kasir #form-checkout>.pos-buyer-card .pos-buyer-title{font-size:.8125rem;font-weight:600;color:var(--ui-side-ink)}
html body #panel-kasir #kasir #form-checkout>.pos-buyer-card .pos-buyer-title svg{display:none}
html body #panel-kasir #kasir #form-checkout>.pos-buyer-card .pos-buyer-badge{padding:2px 8px;border:1px solid var(--ui-side-line);border-radius:6px;background:var(--ui-side-2) !important;color:var(--ui-side-ink) !important;font-weight:500;line-height:1.4}
html body #panel-kasir #kasir #form-checkout>.pos-buyer-card .pos-combo-note{margin:4px 0 0;color:var(--ui-side-ink)}
html body #panel-kasir #kasir #form-checkout>.pos-buyer-card .pos-combo-input{background:var(--ui-head);color:var(--ui-ink);border-color:var(--ui-line)}
html body #panel-kasir #kasir #form-checkout>.pos-buyer-card .pos-combo-input::placeholder{color:var(--ui-ink-3);opacity:1}
html body #panel-kasir #kasir #form-checkout>.pos-buyer-card .pos-combo-input:focus{background:var(--ui-surface);outline-color:var(--ui-side-active);border-color:var(--ui-side-active)}
html body #panel-kasir #kasir #form-checkout>.pos-buyer-card .pos-combo-list{color:var(--ui-ink)}


/* ---------- 12. layar sempit dan jari ---------- */
@media (pointer:coarse){
  html body .ui-chip,html body .pd-seg button,html body .preset-btn{min-height:40px;height:40px}
  html body main td.ui-actions :is(button,.btn,a.btn,.popup-action):not(.popup-close){min-height:40px}
}
@media (max-width:760px){
  html body .ui-toolbar .ui-search{flex:1 1 100%}
  html body .report-shell .statement-summary{grid-template-columns:1fr}
  html body .report-shell .custom-period{margin-left:0;width:100%}
  html body .report-shell .statement-actions{width:100%}
  html body .report-shell .statement-actions>*{flex:1 1 0;justify-content:center}
  html body .report-shell .report-ledger{padding:0 6px 8px}
  html body .report-shell,html body main .card.report-shell{width:auto;margin-inline:0}
  html body .report-shell .filter-caption{display:none}
  html body .report-shell .preset-cluster{width:100%}
  html body .report-shell .ledger-caption{flex-wrap:wrap;gap:2px 12px}
  html body #panel-kasir #kasir .pd-kpis{padding:10px 12px}
}
/* ---------- 13. kasir mengikuti rancangan FigCraft "Kasir Clone" ----------
   Header gelap: pembungkus .wrap di dalam header tidak boleh menutupinya dengan latar putih. */
html body .main-topbar>.wrap,html body .top>.wrap{background:transparent !important}
/* Katalog: kepala tabel pita hijau muda, SKU teks biasa, tombol garis bersudut 8px. */
html body #panel-kasir #kasir .pos-catalog-pane .ui-table thead th{background:var(--ui-green-soft);color:var(--ui-green);font-size:13px;font-weight:600;border-bottom:0}
html body #panel-kasir #kasir .pos-catalog-pane .ui-sub{font-size:13px;color:var(--ui-ink-3)}
html body #panel-kasir #kasir .pos-catalog-pane .sku-code{font:inherit;background:none;border:0;padding:0;color:inherit}
html body #panel-kasir #kasir .pos-catalog-pane .stock-badge{border-radius:999px;padding:4px 10px;font-size:13px;font-weight:600;border:1px solid transparent}
html body #panel-kasir #kasir .pos-catalog-pane .stock-badge.out-of-stock{background:#fdecec;border-color:#f6caca;color:#9b1c1c}
html body #panel-kasir #kasir .pos-catalog-pane .stock-badge.low-stock{background:#fdf6e3;border-color:#f1dfae;color:#7a5a00}
html body #panel-kasir #kasir .pos-catalog-pane .stock-badge.in-stock{background:var(--ui-green-soft);border-color:#c9e2d1;color:var(--ui-green)}
html body #panel-kasir #kasir .pos-catalog-pane .btn-table-action{border-radius:var(--dna-r-ctl);background:var(--ui-surface);border:1px solid var(--ui-line);color:var(--ui-green);font-weight:600}
/* Keranjang kosong: kotak putih rapi tanpa garis putus-putus. */
html body #panel-kasir #kasir .cart-table-scroll,html body #panel-kasir #kasir .cart-empty-message{border:0 !important;border-style:none !important}
html body #panel-kasir #kasir .cart-empty-message{background:var(--ui-surface);color:var(--ui-ink-3)}
/* ---------- 14. desain Kasir final dari FigCraft (disetujui pengguna) ---------- */
@php($kasirFontBase = rtrim(request()->getBasePath(), '/').'/fonts/kasir/')
@font-face{font-family:'Inter Kasir';src:url('{{ $kasirFontBase }}inter-latin-400-normal.woff2') format('woff2');font-weight:400;font-display:swap}
@font-face{font-family:'Inter Kasir';src:url('{{ $kasirFontBase }}inter-latin-500-normal.woff2') format('woff2');font-weight:500;font-display:swap}
@font-face{font-family:'Inter Kasir';src:url('{{ $kasirFontBase }}inter-latin-600-normal.woff2') format('woff2');font-weight:600;font-display:swap}
@font-face{font-family:'Inter Kasir';src:url('{{ $kasirFontBase }}inter-latin-700-normal.woff2') format('woff2');font-weight:700;font-display:swap}
@font-face{font-family:'Inter Kasir';src:url('{{ $kasirFontBase }}inter-latin-800-normal.woff2') format('woff2');font-weight:800;font-display:swap}
@font-face{font-family:'Bricolage Kasir';src:url('{{ $kasirFontBase }}bricolage-grotesque-latin-600-normal.woff2') format('woff2');font-weight:600;font-display:swap}
@font-face{font-family:'Bricolage Kasir';src:url('{{ $kasirFontBase }}bricolage-grotesque-latin-700-normal.woff2') format('woff2');font-weight:700;font-display:swap}
/* Header 72px, judul "Kasir" Bricolage 36px. */
html body:has(#kasir) .main-topbar{height:72px;min-height:72px}
html body:has(#kasir) .main-topbar .top-context strong{font-family:'Bricolage Kasir',sans-serif;font-size:36px;font-weight:700;line-height:1.2}
/* Huruf Inter untuk seluruh Kasir. */
html body #panel-kasir #kasir,html body #panel-kasir #kasir :is(input,select,button,textarea){font-family:'Inter Kasir',sans-serif}
/* Judul katalog Bricolage 24px. Warna menunggu konfirmasi pengguna. */
html body #panel-kasir #kasir .pos-catalog-card h2{font-family:'Bricolage Kasir',sans-serif;font-size:24px;font-weight:700}
/* Kartu Keranjang: hijau #14532D, judul Bricolage 36px lime. */
html body #panel-kasir #kasir .pos-cart-pane>section.card{background:#14532D !important;border-color:#1F3D2D}
html body #panel-kasir #kasir #h-checkout{font-family:'Bricolage Kasir',sans-serif;font-size:36px;font-weight:600;line-height:1.2;color:#C8F26B}
html body #panel-kasir #kasir .cart-column-header{background:#14532D;color:#E7F3EC;border-radius:4px}
/* Nama pembeli: label 15px tebal, kolom input lebar 220px dan tinggi 41px sesuai desain. */
html body #panel-kasir #kasir #form-checkout>.pos-buyer-card .pos-buyer-title{font-size:15px;font-weight:700;color:#E7F3EC}
html body #panel-kasir #kasir #form-checkout>.pos-buyer-card .pos-combo-input{width:220px;max-width:100%;height:41px;background:#F7FAF8}
/* Fitur yang dihapus pengguna dari desain. */
html body #panel-kasir #kasir .catalog-filter-row,html body #panel-kasir #kasir .pos-shift-reminder{display:none !important}
html body #panel-kasir #kasir #form-checkout>.pos-buyer-card :is(.pos-buyer-badge,.pos-buyer-meta,.pos-combo-note,.pos-combo-hint){display:none !important}
/* Total dan tombol checkout. */
html body #panel-kasir #kasir #form-checkout>.summary-box{background:#F5F8F6;border-radius:8px}
html body #panel-kasir #kasir #form-checkout>.summary-box .summary-total span{font-size:24px;font-weight:700;color:#102A1D}
html body #panel-kasir #kasir #display-total{font-size:22px;font-weight:800;color:#102A1D !important}
html body #panel-kasir #kasir #form-checkout #open-payment,html body #panel-kasir #kasir #form-checkout #open-payment:disabled{background:#C8F26B !important;border-color:#C8F26B !important;color:#102A1D !important;opacity:1 !important;font-size:17px;font-weight:700}
/* KPI: kartu pertama hijau, sisanya abu muda, nilai 22px. */
html body #panel-kasir #kasir .pd-kpi:not(.pd-kpi--lead){background:#F5F8F6}
html body #panel-kasir #kasir .pd-kpi--lead{background:#14532D}
html body #panel-kasir #kasir .pd-kpi--lead .pd-kpi-label{color:#C8F26B;font-size:13px;font-weight:700}
html body #panel-kasir #kasir .pd-kpi--lead .pd-kpi-value{color:#F1F5F2;font-size:24px;font-weight:800}
html body #panel-kasir #kasir .pd-kpi .pd-kpi-value{font-size:22px;font-weight:800;color:#102A1D}
html body #panel-kasir #kasir .pd-kpi.pd-kpi--stock .pd-kpi-value{color:#FF3434 !important}
html body #panel-kasir #kasir .pd-kpi--lead .pd-delta{color:#C8F26B !important}
/* Pagination katalog 10 per halaman. */
html body #panel-kasir #kasir .catalog-pager{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:12px}
html body #panel-kasir #kasir .catalog-pager[hidden]{display:none}
html body #panel-kasir #kasir .catalog-pager .note{margin:0;font-size:13px}
html body #panel-kasir #kasir .catalog-pager-controls{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
html body #panel-kasir #kasir .catalog-page-list{display:flex;gap:6px}
html body #panel-kasir #kasir .catalog-page-list button{min-width:36px;height:36px;padding:0 8px;border-radius:8px;border:1px solid var(--ui-line);background:#fff;color:var(--ui-ink);font-weight:600}
html body #panel-kasir #kasir .catalog-page-list button[aria-current=page]{background:#14532D;border-color:#14532D;color:#fff}
html body #panel-kasir #kasir .catalog-pager-controls>button{min-height:36px;padding:0 12px;border-radius:8px;font-weight:600}
html body #panel-kasir #kasir .catalog-pager-controls>button:disabled{opacity:.45}
/* Sembunyikan tombol "Sembunyikan" dan teks jumlah katalog (tidak ada di desain; status tetap dibaca pembaca layar). */
html body #panel-kasir #kasir .pd-fold{display:none !important}
html body #panel-kasir #kasir #product-search-status{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
/* Tabel riwayat 14px sesuai desain. */
html body #panel-kasir #history-table td{font-size:14px}
html body #panel-kasir #history-table th{font-size:13px;font-weight:600}
/* ---------- 15. Kasir: ukuran dan tata letak persis rancangan FigCraft "Kasir — Halaman penuh" ---------- */
/* Kepala aplikasi + sidebar rail (tombol lebar dihapus). */
@media (min-width:901px){html body .sidebar-edge{display:none !important}html body #workspace-sidebar .brand-mark{cursor:pointer;transition:background-color .15s}html body #workspace-sidebar .brand-mark:hover{filter:brightness(1.15)}html body #workspace-sidebar .brand-mark:focus-visible{outline:2px solid #C8F26B;outline-offset:2px}}
html body:has(#kasir) .main-topbar .top-context{margin-left:19px}
html body:has(#kasir) .main-topbar .header-state{gap:14px;font-size:13px}
html body:has(#kasir) .main-topbar .header-state>span{font-size:13px;font-weight:400;color:#E7F3EC}
html body:has(#kasir) .main-topbar .header-state a{min-height:32px;height:32px;padding:0 15px;border:1px solid #2C4A3A;border-radius:8px;background:#C8F26B;color:#102A1D !important;font-size:13px;font-weight:600;align-items:center}
html body:has(#kasir) .main-topbar .header-state a:hover{background:#d6f78c;color:#102A1D !important}
/* Judul halaman di bawah header tidak ada di desain: tetap dibaca pembaca layar. */
html body[data-active-task="kasir"] .ui-page-header{position:absolute !important;width:1px;height:1px;margin:0;padding:0;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
html body[data-active-task="kasir"] main.wrap{padding-top:8px}
html body #panel-kasir #kasir{display:flex;flex-direction:column;gap:16px}
html body #panel-kasir #kasir>*{margin:0}

/* Ringkasan: tiga kartu (ringkasan + KPI, penjualan harian, produk terlaris). */
html body #panel-kasir #kasir #pos-dashboard{background:transparent !important;border:0 !important;box-shadow:none !important;padding:0 !important;margin:0 !important;border-radius:0}
html body #panel-kasir #kasir .pd-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin:0;padding:18px 20px 0;min-height:70px;background:#fff;border:1px solid #E4EBE6;border-bottom:0;border-radius:12px 12px 0 0}
html body #panel-kasir #kasir #pd-title{font-family:'Inter Kasir',sans-serif;font-size:18px;font-weight:700;line-height:22px;color:#102A1D;margin:0}
html body #panel-kasir #kasir #pd-sub{font-size:13px;line-height:16px;color:#5F7468;margin:4px 0 0}
html body #panel-kasir #kasir .pd-controls{display:flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:flex-end}
html body #panel-kasir #kasir .pd-seg{order:1;display:flex;gap:2px;height:36px;padding:4px;background:#F1F5F2;border:0;border-radius:8px}
html body #panel-kasir #kasir .pd-seg button{width:80px;height:28px;min-height:28px;padding:0;border:1px solid transparent;border-radius:6px;background:transparent;color:#3C4A42;font-size:13px;font-weight:600;box-shadow:none}
html body #panel-kasir #kasir .pd-seg button[aria-pressed=true]{background:#fff;border-color:#DFE6E1;color:#14532D;font-weight:700;box-shadow:none}
html body #panel-kasir #kasir .pd-head .pos-shift-notice{order:2;display:flex;align-items:center;gap:10px;height:36px;padding:0 6px 0 14px;background:#E4F0E8 !important;border:0;border-radius:8px}
html body #panel-kasir #kasir .pd-head .pos-shift-notice strong{font-size:13px;font-weight:700;color:#14532D}
html body #panel-kasir #kasir .pd-head .pos-shift-action{min-height:28px;height:28px;padding:0 10px;border:1px solid #C9E2D1;border-radius:6px;background:#fff;color:#14532D;font-size:13px;font-weight:600}
html body #panel-kasir #kasir .pd-head .pos-shift-action svg{width:14px;height:14px}
html body #panel-kasir #kasir .pd-body{display:block;margin:0;padding:0;border:0;background:transparent}
html body #panel-kasir #kasir .pd-pane:not([hidden]){display:grid;grid-template-columns:minmax(0,830fr) minmax(0,455fr);grid-template-areas:"kpis kpis" "main side";column-gap:16px;row-gap:16px;margin:0;padding:0;border:0;background:transparent}
html body #panel-kasir #kasir .pd-kpis{grid-area:kpis;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:0;padding:0 20px 18px;background:#fff;border:1px solid #E4EBE6;border-top:0;border-radius:0 0 12px 12px}
html body #panel-kasir #kasir .pd-kpi .pd-kpi-label{font-size:13px;line-height:16px;font-weight:500;color:#5F7468;text-transform:none;letter-spacing:0}
html body #panel-kasir #kasir .pd-kpi .pd-kpi-value{margin-top:4px;font-size:22px;line-height:27px;font-weight:800;color:#102A1D}
html body #panel-kasir #kasir .pd-kpi :is(.pd-kpi-note,.pd-delta){margin-top:7px;font-size:13px;line-height:16px;font-weight:400;color:#5F7468}
html body #panel-kasir #kasir .pd-kpi.pd-kpi--lead .pd-kpi-label{font-weight:700;color:#C8F26B}
html body #panel-kasir #kasir .pd-kpi.pd-kpi--lead .pd-kpi-value{font-size:24px;line-height:29px;color:#F1F5F2 !important}
html body #panel-kasir #kasir .pd-kpi.pd-kpi--lead .pd-delta{margin-top:5px;color:#C8F26B !important}
html body #panel-kasir #kasir .pd-delta::before{display:none !important}
html body #panel-kasir #kasir :is(.pd-main,.pd-side){position:relative;min-height:230px;margin:0;padding:16px 20px 14px;background:#fff;border:1px solid #E4EBE6;border-radius:12px;box-shadow:none}
html body #panel-kasir #kasir .pd-main{grid-area:main}
html body #panel-kasir #kasir .pd-side{grid-area:side;display:flex;flex-direction:column}
html body #panel-kasir #kasir .pd-card-title{font-family:'Inter Kasir',sans-serif;font-size:15px;line-height:18px;font-weight:700;color:#102A1D;margin:0}
html body #panel-kasir #kasir .pd-card-sub{font-size:13px;line-height:16px;color:#5F7468;margin:6px 0 0}
html body #panel-kasir #kasir .pd-chart{margin:0}
html body #panel-kasir #kasir .pd-chart figcaption strong{font-weight:600;color:#14532D}
html body #panel-kasir #kasir .pd-plot{display:grid;grid-template-columns:40px minmax(0,1fr);column-gap:10px;margin-top:12px;height:auto;min-height:0}
html body #panel-kasir #kasir .pd-y{display:flex;flex-direction:column;justify-content:space-between;height:146px;margin-top:-4px;padding:0;text-align:right;font-size:13px;line-height:16px;color:#5F7468}
html body #panel-kasir #kasir .pd-area{position:relative;height:130px;margin:4px 0 26px}
html body #panel-kasir #kasir .pd-grid{position:absolute;inset:0;display:flex;flex-direction:column;justify-content:space-between}
html body #panel-kasir #kasir .pd-grid i{display:block;height:1px;background:#EEF2EF;border:0}
html body #panel-kasir #kasir .pd-bars{position:absolute;inset:0;display:flex;align-items:flex-end;gap:0}
html body #panel-kasir #kasir .pd-col{flex:1 1 0;display:flex;align-items:flex-end;justify-content:center;height:100%}
html body #panel-kasir #kasir .pd-col i{width:56px;max-width:72%;border-radius:4px 4px 0 0;background:#B9D9C4 !important}
html body #panel-kasir #kasir .pd-col.is-peak i{background:#14532D !important}
html body #panel-kasir #kasir .pd-x{position:absolute;left:0;right:0;top:calc(100% + 10px);display:flex}
html body #panel-kasir #kasir .pd-x span{flex:1 1 0;text-align:center;font-size:13px;line-height:16px;color:#5F7468;white-space:nowrap}
html body #panel-kasir #kasir .pd-rank{display:none !important}
html body #panel-kasir #kasir .pd-top{list-style:none;margin:20px 0 0;padding:0;display:flex;flex-direction:column;gap:25px}
html body #panel-kasir #kasir .pd-top li{position:relative;display:grid;grid-template-columns:minmax(0,1fr) auto;row-gap:5px;padding:0 0 0 16px;margin:0;border:0;background:none}
html body #panel-kasir #kasir .pd-top li::after{content:'';grid-column:1/-1;height:8px;border-radius:4px;background:#EEF2EF}
html body #panel-kasir #kasir .pd-top-name{font-size:14px;line-height:17px;font-weight:600;color:#102A1D;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
html body #panel-kasir #kasir .pd-top-qty{font-size:14px;line-height:17px;font-weight:700;color:#102A1D;text-align:right}
html body #panel-kasir #kasir .pd-top-bar{position:absolute;left:16px;bottom:0;height:8px;max-width:calc(100% - 16px);border-radius:4px;background:#14532D !important;margin:0;opacity:1 !important}
html body #panel-kasir #kasir .pd-top li:nth-child(n+2) .pd-top-bar{background:#7FB294 !important}
html body #panel-kasir #kasir .pd-link{margin-top:auto;font-size:13px;line-height:16px;font-weight:600;color:#14532D;text-decoration:none}

/* Area kerja: katalog 680 dan keranjang 608. */
@media (min-width:1101px){html body #panel-kasir #kasir .pos-workspace{grid-template-columns:minmax(0,680fr) minmax(0,608fr);gap:13px;align-items:start;margin:0}}
html body #panel-kasir #kasir .pos-catalog-card{padding:15px 16px 18px;border:1px solid #E4EBE6;border-radius:12px;background:#fff}
html body #panel-kasir #kasir .pos-catalog-head{padding:17px 15px 19px;border-radius:13.66px;background:#14532D}
html body #panel-kasir #kasir .pos-catalog-head>h2{font-family:'Bricolage Kasir',sans-serif;font-size:24px;line-height:29px;font-weight:700;color:#C8F26B !important;margin:0 0 5px;padding:0 !important;border:0 !important}
html body #panel-kasir #kasir .pos-catalog-head>h2::after{display:none !important}
html body #panel-kasir #kasir .pos-catalog-head .pos-search-wrapper{margin:0;padding:0;background:transparent;border:0}
html body #panel-kasir #kasir .pos-catalog-head .pos-search-wrapper>label{display:block;font-size:14px;line-height:17px;font-weight:500;color:#F1F5F2;margin:0 0 13px}
html body #panel-kasir #kasir .pos-catalog-head .pos-search-box{height:42px;min-height:42px;margin:0 28px;padding:0 10px 0 13px;gap:9px;border:1px solid #DFE6E1;border-radius:8px;background:#fff;box-shadow:none}
html body #panel-kasir #kasir .pos-catalog-head .pos-search-box svg{width:14px;height:14px;color:#3C4A42;flex:0 0 14px}
html body #panel-kasir #kasir .pos-catalog-head #pos-product-search{height:40px;min-height:40px;padding:0;border:0;background:transparent;font-size:14px;color:#102A1D;box-shadow:none}
html body #panel-kasir #kasir .pos-catalog-head #pos-product-search::placeholder{color:#3C4A42;opacity:1}
html body #panel-kasir #kasir .pos-catalog-head .search-kbd-hint{display:inline-flex;align-items:center;height:26px;padding:0 9px;border:1px solid #DFE6E1;border-radius:6px;background:#F1F5F2;font-size:13px;font-weight:500;color:#4B5A52}
html body #panel-kasir #kasir .catalog-pager{margin:9px 5px 0}
html body #panel-kasir #kasir .catalog-pager .note{font-size:13px;color:#5F7468}
html body #panel-kasir #kasir .catalog-pager-controls{gap:8px}
html body #panel-kasir #kasir .catalog-pager-controls>button{height:36px;min-height:36px;padding:0 10px;border:1px solid #DFE6E1;border-radius:8px;background:#fff;color:#14532D;font-size:13px;font-weight:600}
html body #panel-kasir #kasir .catalog-page-list{gap:8px}
html body #panel-kasir #kasir .catalog-page-list button{width:36px;min-width:36px;height:36px;padding:0;border:1px solid #DFE6E1;font-size:14px;font-weight:600;color:#102A1D}
html body #panel-kasir #kasir .catalog-page-list button[aria-current=page]{background:#14532D;border-color:#14532D;color:#fff}

/* Keranjang. */
html body #panel-kasir #kasir .pos-cart-pane>section.card{padding:19px 19px 36px;border-radius:12px}
html body #panel-kasir #kasir #h-checkout{margin:0 0 13px;padding:0;border:0}
html body #panel-kasir #kasir #form-checkout>.pos-buyer-card .pos-buyer-header{margin:0 0 7px}
html body #panel-kasir #kasir #form-checkout>.pos-buyer-card .pos-buyer-title{font-size:15px;line-height:18px;font-weight:700;color:#E7F3EC}
html body #panel-kasir #kasir #form-checkout>.pos-buyer-card .pos-combo-input{width:220px;height:41px;min-height:41px;padding:0 12px 0 39px;border:1px solid #DFE6E1;border-radius:8px;background:#F7FAF8 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 14 14' fill='none' stroke='%233C4A42' stroke-width='1.6'%3E%3Ccircle cx='6' cy='6' r='5'/%3E%3Cpath d='m10 10 3 3'/%3E%3C/svg%3E") no-repeat 12px center;font-size:15px;color:#102A1D}
html body #panel-kasir #kasir #form-checkout>.pos-buyer-card .pos-combo-input::placeholder{color:#5F7468}
html body #panel-kasir #kasir .pos-cart-section{margin-top:12px}
html body #panel-kasir #kasir .cart-label{align-items:baseline;margin:0 0 6px}
html body #panel-kasir #kasir .cart-label strong{font-size:15px;line-height:18px;font-weight:700;color:#E7F3EC !important;text-transform:none;letter-spacing:0}
html body #panel-kasir #kasir #cart-item-count{font-size:13px;color:#E7F3EC}
html body #panel-kasir #kasir .cart-table-scroll{padding:8px;border-radius:8px !important;background:#fff !important}
html body #panel-kasir #kasir .cart-column-header{min-height:34px;height:34px;align-items:center;padding:0 8px;border-radius:4px;background:#14532D !important;color:#F5F8F6 !important;font-size:13px;font-weight:600}
html body #panel-kasir #kasir .cart-column-header span{color:#F5F8F6 !important}
html body #panel-kasir #kasir .cart-empty-message{padding:22px 42px 20px;background:#fff !important;font-size:14px;line-height:17px;color:#5F7468;text-align:center}
html body #panel-kasir #kasir #form-checkout>.summary-box{display:flex;align-items:center;min-height:59px;margin:14px 0 0;padding:0 18px 0 16px;border:0;border-radius:10px;background:#F5F8F6 !important}
html body #panel-kasir #kasir #form-checkout>.summary-box .summary-total{width:100%;display:flex;justify-content:space-between;align-items:center}
html body #panel-kasir #kasir #form-checkout>.summary-box .summary-total span{font-size:24px;line-height:29px;font-weight:700;color:#102A1D !important}
html body #panel-kasir #kasir #form-checkout>.summary-box #display-total{font-size:22px;line-height:27px;font-weight:800;color:#102A1D !important}
html body #panel-kasir #kasir .pos-checkout-action{margin:22px 0 0 !important}
html body #panel-kasir #kasir #form-checkout #open-payment,html body #panel-kasir #kasir #form-checkout #open-payment:disabled{width:100%;height:47px;min-height:47px;border:1px solid #2C4A3A !important;border-radius:8px;box-shadow:none}
html body #panel-kasir #kasir #form-checkout #open-payment:disabled{cursor:not-allowed}

/* Riwayat transaksi. */
html body #panel-kasir .pos-history-full{margin:26px 0 0 !important;padding:18px 20px 20px;border:1px solid #E4EBE6;border-radius:12px;background:#fff;box-shadow:none;font-family:'Inter Kasir',sans-serif}
html body #panel-kasir #h-sales{font-family:'Inter Kasir',sans-serif;font-size:18px;line-height:22px;font-weight:700;color:#102A1D;margin:0;padding:0 !important;border:0 !important}
html body #panel-kasir #h-sales::after{display:none}
html body #panel-kasir .pos-history-sub{margin:4px 0 0;font-size:13px;line-height:16px;color:#5F7468}
html body #panel-kasir #history-filter-form{display:grid !important;grid-template-columns:minmax(0,300fr) minmax(0,200fr) minmax(0,352fr) minmax(0,130fr) auto;align-items:start;gap:12px;margin:10px 0 0;padding:0;border:0;background:transparent;box-shadow:none;position:static;overflow:visible}
html body #panel-kasir #history-filter-form .field{margin:0;min-width:0}
html body #panel-kasir #history-filter-form label{display:block;margin:0 0 4px;font-size:13px;line-height:16px;font-weight:600;color:#3C4A42}
html body #panel-kasir #history-filter-form :is(input.form-control,.select-trigger,.date-trigger,select.form-control){height:44px;min-height:44px;border:1px solid #DFE6E1;border-radius:8px;background:#fff;font-size:14px;color:#102A1D;box-shadow:none}
html body #panel-kasir #history-filter-form input::placeholder{color:#5F7468;opacity:1}
html body #panel-kasir #history-filter-form .date-text{padding:0 38px 0 14px}
html body #panel-kasir #history-filter-form :is(input.form-control:not([type=date]),.select-trigger){height:44px !important;min-height:44px !important}
html body #panel-kasir #history-filter-form .sales-filter-actions{display:flex;gap:12px;grid-column:auto;margin-top:20px}
html body #panel-kasir #history-filter-form :is(.select-trigger,.date-trigger){width:100%;justify-content:space-between;padding:0 12px;text-align:left;font-weight:400}
html body #panel-kasir #history-filter-form :is(.date-field,.select-shell){margin:0}
html body #panel-kasir #history-filter-form .sales-filter-actions>*{width:110px;height:44px;min-height:44px;padding:0;justify-content:center;border-radius:8px;font-size:14px;font-weight:600}
html body #panel-kasir #history-filter-form .sales-filter-actions>button{background:#14532D;border:1px solid #14532D;color:#fff}
html body #panel-kasir #history-filter-form .sales-filter-actions>a{background:#fff;border:1px solid #DFE6E1;color:#14532D}
html body #panel-kasir .pos-history-full .pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin:12px 0 0;padding:0;border:0}
html body #panel-kasir .pos-history-full .pagination-meta{margin:0;font-size:13px;color:#5F7468}
html body #panel-kasir .pos-history-full .pagination-controls{display:flex;align-items:center;gap:8px}
html body #panel-kasir .pos-history-full .page-list{display:flex;gap:6px}
html body #panel-kasir .pos-history-full .page-btn{display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;min-height:36px;padding:0 10px;border:1px solid #DFE6E1;border-radius:8px;background:#fff;color:#14532D;font-size:13px;font-weight:600;text-decoration:none}
html body #panel-kasir .pos-history-full .page-list .page-btn{width:36px;padding:0;font-size:14px;color:#102A1D}
html body #panel-kasir .pos-history-full .page-btn.active{background:#14532D;border-color:#14532D;color:#fff}
html body #panel-kasir .pos-history-full .page-btn.disabled{opacity:.45}

/* Ukuran tabel dan tile dari desain hanya untuk layar lebar; di HP tabel tetap menjadi daftar kartu. */
html body #panel-kasir #kasir #products-tbody td.ui-primary{font-size:15px;line-height:18px;font-weight:600;color:#102A1D}
html body #panel-kasir #kasir #products-tbody .ui-sub{display:block;margin-top:6px;font-size:13px;line-height:16px;font-weight:400;color:#5F7468}
html body #panel-kasir #kasir #products-tbody .stock-badge{display:inline;padding:0 !important;border:0 !important;background:none !important;font-size:13px;font-weight:600;margin-left:6px}
html body #panel-kasir #kasir #products-tbody .stock-badge::before{display:none !important}
html body #panel-kasir #kasir #products-tbody .stock-badge.in-stock{color:#14532D !important}
html body #panel-kasir #kasir #products-tbody .stock-badge.low-stock{color:#A15C07 !important}
html body #panel-kasir #kasir #products-tbody .stock-badge.out-of-stock{color:#B42318 !important}
html body #panel-kasir #kasir #products-tbody td.money{font-size:15px;font-weight:700;color:#102A1D}
html body #panel-kasir #kasir #products-tbody .btn-table-action{width:64px;height:32px;min-height:32px;padding:0;justify-content:center;border:1px solid #DFE6E1;border-radius:8px;background:#fff;color:#14532D;font-size:13px;font-weight:600}
html body #panel-kasir #history-table td .sku-code{font:inherit;font-weight:600;color:#102A1D;background:none;border:0;padding:0}
html body #panel-kasir #history-table td.money{font-weight:700;color:#102A1D}
html body #panel-kasir #history-table .ui-badge{display:inline-flex;align-items:center;justify-content:center;min-width:70px;height:26px;padding:0 12px;border:1px solid #C9E2D1;border-radius:13px;background:#E4F0E8;color:#14532D;font-size:13px;font-weight:600}
html body #panel-kasir #history-table .ui-badge::before{display:none !important}
html body #panel-kasir #history-table .doc-links{display:flex;gap:8px;justify-content:flex-start}
html body #panel-kasir #history-table .doc-links a{display:inline-flex;align-items:center;justify-content:center;min-width:64px;height:32px;padding:0 12px;border:1px solid #DFE6E1;border-radius:8px;background:#fff;color:#14532D;font-size:13px;font-weight:600;text-decoration:none}
html body #panel-kasir #kasir .catalog-table-scroll{max-height:none !important}
@media (min-width:761px){
  html body #panel-kasir #kasir .pd-kpi{display:flex;flex-direction:column;justify-content:flex-start;gap:0;min-height:84px;height:84px;padding:8px 14px;border:0;border-radius:8px;box-shadow:none}
  html body #panel-kasir #kasir .pd-chart figcaption{position:absolute;top:16px;right:20px;margin:0;font-size:13px;line-height:16px;font-weight:600;color:#14532D}
  html body #panel-kasir #kasir .catalog-table-scroll{max-height:none !important;overflow:hidden !important;margin:11px 5px 0;border:1px solid #E4EBE6 !important;border-radius:8px;background:#fff}
  html body #panel-kasir #kasir #products-table{width:100%;border-collapse:collapse}
  html body #panel-kasir #kasir #products-table thead th{height:38px;padding:0 10px;background:#14532D !important;color:#F1F5F2 !important;font-size:13px;font-weight:600;border:0;border-radius:0 !important;text-transform:none;letter-spacing:0;position:static}
  html body #panel-kasir #kasir #products-tbody td{height:61px;padding:0 10px;border:0;border-bottom:1px solid #EEF2EF;vertical-align:middle}
  html body #panel-kasir #kasir #products-tbody tr:last-child td{border-bottom:0}
  html body #panel-kasir .pos-history-full>.scroll{margin:18px 0 0;border:1px solid #E4EBE6;border-radius:8px;overflow:hidden}
  html body #panel-kasir #history-table{width:100%;border-collapse:collapse;font-family:'Inter Kasir',sans-serif}
  html body #panel-kasir #history-table thead th{height:38px;padding:0 15px;background:#14532D !important;color:#fff !important;font-size:13px;font-weight:600;border:0;border-radius:0 !important;text-transform:none;letter-spacing:0;text-align:left}
  html body #panel-kasir #history-table td{height:56px;padding:0 15px;border:0;border-bottom:1px solid #EEF2EF;font-size:14px;font-weight:400;color:#3C4A42;vertical-align:middle}
  html body #panel-kasir #history-table tbody tr:last-child td{border-bottom:0}
  html body #panel-kasir #history-table :is(th,td):nth-child(1){width:264px}
  html body #panel-kasir #history-table :is(th,td):nth-child(2){width:180px}
  html body #panel-kasir #history-table :is(th,td):nth-child(3){width:160px}
  html body #panel-kasir #history-table :is(th,td):nth-child(4){width:171px;text-align:right}
  html body #panel-kasir #history-table :is(th,td):nth-child(5){width:290px;padding-left:135px}
  html body #panel-kasir #history-table :is(th,td):nth-child(6){text-align:left}
}
/* Layar sempit: kartu menumpuk, saringan riwayat membungkus. */
@media (max-width:1100px){
  html body #panel-kasir #kasir .pd-pane:not([hidden]){grid-template-columns:minmax(0,1fr);grid-template-areas:"kpis" "main" "side"}
  html body #panel-kasir #history-filter-form{grid-template-columns:repeat(2,minmax(0,1fr))}
  html body #panel-kasir #history-filter-form .sales-filter-actions{grid-column:1/-1}
  html body #panel-kasir #history-table :is(th,td):nth-child(5){padding-left:15px}
}
@media (max-width:760px){
  html body #panel-kasir #kasir .pd-head{flex-direction:column}
  html body #panel-kasir #kasir .pd-controls{justify-content:flex-start}
  html body #panel-kasir #kasir .pd-kpis{grid-template-columns:repeat(2,minmax(0,1fr));padding:0 12px 12px}
  html body #panel-kasir #kasir .pos-catalog-head .pos-search-box{margin:0}
  html body #panel-kasir #kasir .pos-catalog-head .search-kbd-hint{display:none}
  html body #panel-kasir #history-filter-form{grid-template-columns:minmax(0,1fr)}
  html body #panel-kasir #kasir .pd-kpi{height:auto;min-height:84px;padding:10px 12px}
  html body #panel-kasir #kasir .pd-kpi .pd-kpi-value,html body #panel-kasir #kasir .pd-kpi.pd-kpi--lead .pd-kpi-value{font-size:18px;line-height:22px;overflow-wrap:anywhere}
  html body #panel-kasir #kasir .pd-chart figcaption{position:static;margin:8px 0 0}
  html body #panel-kasir .pos-history-full .pagination-controls{flex-wrap:wrap}
}
/* ---------- 17. Kasir: Produk terlaris 10 besar + tata letak HP (rancangan "Kasir — Mobile" yang disempurnakan) ---------- */
html body #panel-kasir #kasir .pd-long{display:inline}
html body #panel-kasir #kasir .pd-short{display:none}
html body #panel-kasir #kasir .pd-kpi--stock{position:relative}
html body #panel-kasir #kasir .pd-kpi-link{position:absolute;top:8px;right:12px;display:inline-flex;align-items:center;min-height:24px;font-size:13px;font-weight:600;color:#14532D;text-decoration:underline;text-underline-offset:3px}
html body #panel-kasir #kasir .pd-kpi-link:hover{color:#0f3f22}
/* Produk terlaris: peringkat bernomor, dua kolom, tanpa batang. */
html body #panel-kasir #kasir .pd-side{min-height:0}
@media (min-width:1101px){html body #panel-kasir #kasir .pd-pane:not([hidden]){grid-template-columns:minmax(0,1.1fr) minmax(0,1fr)}}
html body #panel-kasir #kasir .pd-top{display:grid !important;grid-auto-flow:column;grid-template-rows:repeat(var(--pd-rows,5),auto);grid-template-columns:repeat(2,minmax(0,1fr));column-gap:28px;row-gap:0;margin:12px 0 0;padding:0}
html body #panel-kasir #kasir .pd-top li{display:grid;grid-template-columns:22px minmax(0,1fr) auto;column-gap:8px;align-items:center;min-height:32px;padding:6px 0;margin:0;border:0;border-bottom:1px solid #EEF2EF;background:none}
html body #panel-kasir #kasir .pd-top li::after{display:none !important;content:none}
html body #panel-kasir #kasir .pd-top .pd-rank{display:block !important;width:auto;height:auto;padding:0;border-radius:0;background:none !important;font-size:13px;line-height:16px;font-weight:600;color:#8A9B91;font-variant-numeric:tabular-nums}
html body #panel-kasir #kasir .pd-top li:nth-child(-n+3) .pd-rank{color:#14532D;font-weight:800}
html body #panel-kasir #kasir .pd-top .pd-top-name{font-size:13px;line-height:16px;font-weight:600;color:#102A1D}
html body #panel-kasir #kasir .pd-top .pd-top-qty{font-size:13px;line-height:16px;font-weight:700;color:#14532D;font-variant-numeric:tabular-nums;white-space:nowrap}
html body #panel-kasir #kasir .pd-top .pd-top-bar{display:none !important}

@media (max-width:760px){
  /* Kepala aplikasi. */
  html body:has(#kasir) .main-topbar>.wrap{min-height:64px;gap:8px;padding:0 12px 0 8px}
  html body:has(#kasir) .main-topbar .mobile-menu-toggle{width:44px;height:44px;min-width:44px;min-height:44px;padding:0;border:0 !important;border-radius:10px;background:transparent !important;color:#E7F3EC !important;box-shadow:none}
  html body:has(#kasir) .main-topbar .mobile-menu-toggle svg{width:24px;height:24px}
  html body:has(#kasir) .main-topbar .top-context{margin-left:2px}
  html body:has(#kasir) .main-topbar .top-context strong{font-size:28px;line-height:34px}
  html body:has(#kasir) .main-topbar .header-state{display:flex !important;align-items:center;gap:12px;margin-left:auto}
  html body:has(#kasir) .main-topbar .header-state>span{display:inline !important}
  html body:has(#kasir) .main-topbar .header-state a{height:36px;min-height:36px;padding:0 14px;border-radius:18px;border:0}
  html body[data-active-task="kasir"] main.wrap{padding:12px 16px 24px}
  html body #panel-kasir #kasir{gap:16px}

  /* Ringkasan: judul + status shift, segmen penuh, Penjualan lebar, tiga angka sebaris. */
  html body #panel-kasir #kasir .pd-kpi:not(.pd-kpi--stock) .pd-long{display:none}
  html body #panel-kasir #kasir .pd-kpi:not(.pd-kpi--stock) .pd-short{display:inline}
  html body #panel-kasir #kasir .pd-kpi--stock .pd-short{display:none}
  html body #panel-kasir #kasir .pd-head{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:start;column-gap:12px;row-gap:14px;min-height:0;padding:16px 16px 0}
  html body #panel-kasir #kasir .pd-controls{display:contents}
  html body #panel-kasir #kasir .pd-head .pos-shift-notice{grid-column:2;grid-row:1;position:relative;height:36px;padding:0 12px 0 14px;gap:6px;border-radius:18px}
  html body #panel-kasir #kasir .pd-head .pos-shift-action{position:static;min-height:0;height:auto;padding:0;border:0;background:transparent;color:#14532D}
  html body #panel-kasir #kasir .pd-head .pos-shift-action span{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
  html body #panel-kasir #kasir .pd-head .pos-shift-action::after{content:'';position:absolute;inset:-4px 0}
  html body #panel-kasir #kasir .pd-seg{grid-column:1/-1;grid-row:2;width:100%;height:44px}
  html body #panel-kasir #kasir .pd-seg button{flex:1 1 0;width:auto;height:36px;min-height:36px}
  html body #panel-kasir #kasir .pd-kpis{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;padding:14px 16px 16px}
  html body #panel-kasir #kasir .pd-kpi.pd-kpi--stock{grid-column:1/-1}
  html body #panel-kasir #kasir .pd-kpi{height:auto !important;min-height:0 !important;padding:10px !important}
  html body #panel-kasir #kasir .pd-kpi.pd-kpi--lead{grid-column:1/-1;padding:14px 16px !important}
  html body #panel-kasir #kasir .pd-kpi.pd-kpi--lead .pd-kpi-value{font-size:26px !important;line-height:31px !important}
  html body #panel-kasir #kasir .pd-kpi .pd-kpi-value{font-size:16px !important;line-height:20px !important;margin-top:6px;overflow-wrap:anywhere}
  html body #panel-kasir #kasir .pd-kpi .pd-kpi-note{margin-top:6px}
  html body #panel-kasir #kasir .pd-kpi-link{top:6px;right:10px;min-height:32px}

  /* Grafik: puncak di bawah judul, tanpa sumbu Y agar batang lebih lebar. */
  html body #panel-kasir #kasir :is(.pd-main,.pd-side){min-height:0;padding:16px}
  html body #panel-kasir #kasir .pd-main .pd-card-sub{display:none}
  html body #panel-kasir #kasir .pd-chart{display:flex;flex-direction:column}
  html body #panel-kasir #kasir .pd-chart figcaption{order:-1;position:static !important;margin:6px 0 0 !important;font-size:13px;font-weight:600;color:#14532D}
  html body #panel-kasir #kasir .pd-plot{grid-template-columns:minmax(0,1fr);margin-top:12px}
  html body #panel-kasir #kasir .pd-y{display:none}
  html body #panel-kasir #kasir .pd-area{height:112px;margin:0 0 26px}
  html body #panel-kasir #kasir .pd-col i{max-width:64%}
  html body #panel-kasir #kasir .pd-pane[data-pane="7d"] .pd-x span:nth-child(even){visibility:visible}

  /* Produk terlaris: satu kolom. */
  html body #panel-kasir #kasir .pd-top{grid-auto-flow:row;grid-template-rows:none;grid-template-columns:minmax(0,1fr)}
  html body #panel-kasir #kasir .pd-top li{min-height:36px}
  html body #panel-kasir #kasir .pd-top li:last-child{border-bottom:0}
  html body #panel-kasir #kasir .pd-top .pd-top-name{font-size:14px}
  html body #panel-kasir #kasir .pd-top .pd-top-qty{font-size:14px}

  /* Katalog: kepala hijau penuh, baris berupa daftar dua kolom. */
  html body #panel-kasir #kasir .pos-catalog-card{padding:0;overflow:hidden}
  html body #panel-kasir #kasir .pos-catalog-head{padding:16px;border-radius:0}
  html body #panel-kasir #kasir .pos-catalog-head>h2{font-size:22px;line-height:27px}
  html body #panel-kasir #kasir .pos-catalog-head .pos-search-wrapper>label{margin:0 0 10px;font-size:13px}
  html body #panel-kasir #kasir .pos-catalog-head .pos-search-box{height:44px;min-height:44px}
  html body #panel-kasir #kasir .catalog-table-scroll{margin:0 16px !important;padding:0;border:0 !important;overflow:visible !important;background:transparent}
  html body #panel-kasir #kasir #products-table,html body #panel-kasir #kasir #products-table tbody{display:block !important;width:100% !important;min-width:0 !important}
  html body #panel-kasir #kasir #products-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)}
  html body #panel-kasir #kasir #products-tbody tr{display:grid !important;grid-template-columns:minmax(0,1fr) auto;grid-template-areas:"name price" "stock act";column-gap:12px;row-gap:6px;width:auto !important;margin:0 !important;padding:12px 0 !important;border:0 !important;border-bottom:1px solid #EEF2EF !important;border-radius:0 !important;background:transparent !important;box-shadow:none !important}
  html body #panel-kasir #kasir #products-tbody tr:last-child{border-bottom:0 !important}
  html body #panel-kasir #kasir #products-tbody tr[hidden]{display:none !important}
  html body #panel-kasir #kasir #products-tbody td{display:block !important;width:auto !important;height:auto !important;min-height:0 !important;margin:0 !important;padding:0 !important;border:0 !important;background:transparent !important;text-align:left}
  html body #panel-kasir #kasir #products-tbody td::before{display:none !important;content:none !important}
  html body #panel-kasir #kasir #products-tbody td:nth-child(1){grid-area:name;font-size:15px;line-height:19px}
  html body #panel-kasir #kasir #products-tbody td:nth-child(1) .ui-sub{margin-top:3px}
  html body #panel-kasir #kasir #products-tbody td:nth-child(2){grid-area:stock;align-self:center;text-align:left !important}
  html body #panel-kasir #kasir #products-tbody td:nth-child(2) .stock-badge{margin:0}
  html body #panel-kasir #kasir #products-tbody td:nth-child(3){grid-area:price;text-align:right;white-space:nowrap}
  html body #panel-kasir #kasir #products-tbody td:nth-child(4){grid-area:act;justify-self:end;align-self:end}
  html body #panel-kasir #kasir #products-tbody .btn-table-action{width:auto !important;min-width:76px;height:36px !important;min-height:36px !important;padding:0 14px !important;border:0 !important;background:#E4F0E8 !important;color:#14532D !important;font-weight:700}
  html body #panel-kasir #kasir #products-tbody .btn-table-action:hover{background:#d3e8da}
  html body #panel-kasir #kasir .catalog-pager{display:grid;gap:10px;margin:4px 16px 0;padding:12px 0 16px;border-top:1px solid #EEF2EF}
  html body #panel-kasir #kasir .catalog-pager-controls{display:flex;justify-content:space-between;align-items:center;width:100%}
  html body #panel-kasir #kasir .catalog-pager-controls>button{width:44px;min-width:44px;height:40px !important;min-height:40px !important;padding:0 !important;font-size:0 !important}
  html body #panel-kasir #kasir #catalog-prev::before{content:'\2039';font-size:22px;line-height:1}
  html body #panel-kasir #kasir #catalog-next::before{content:'\203A';font-size:22px;line-height:1}
  html body #panel-kasir #kasir .catalog-page-list button{width:40px;min-width:40px;height:40px}

  /* Keranjang. */
  html body #panel-kasir #kasir .pos-cart-pane>section.card{padding:16px 16px 20px}
  html body #panel-kasir #kasir #h-checkout{font-size:26px;line-height:31px}
  html body #panel-kasir #kasir #form-checkout>.pos-buyer-card .pos-combo-input{width:100%;height:44px;min-height:44px}
  html body #panel-kasir #kasir #form-checkout>.summary-box .summary-total span{font-size:18px;line-height:22px}
  html body #panel-kasir #kasir #form-checkout>.summary-box #display-total{font-size:20px}
  html body #panel-kasir #kasir #form-checkout #open-payment{height:52px;min-height:52px;font-size:16px}

  /* Riwayat: cari, rentang, metode + Terapkan; baris berupa daftar. */
  html body #panel-kasir .pos-history-full{padding:16px;margin-top:16px !important}
  html body #panel-kasir #history-filter-form{grid-template-columns:minmax(0,1fr) auto !important;grid-template-areas:"search search" "date date" "method act";gap:10px;margin-top:14px}
  html body #panel-kasir #history-filter-form label{position:absolute;width:1px;height:1px;margin:0;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
  html body #panel-kasir #history-filter-form .field:has(#sales-search){grid-area:search}
  html body #panel-kasir #history-filter-form .field:has(#sales-method){grid-area:method}
  html body #panel-kasir #history-filter-form .field-date-range{grid-area:date}
  html body #panel-kasir #history-filter-form .field:has(#sales-perpage){display:none}
  html body #panel-kasir #history-filter-form .sales-filter-actions{grid-area:act;grid-column:auto !important;margin:0 !important;gap:8px}
  html body #panel-kasir #history-filter-form .sales-filter-actions>button{width:auto !important;padding:0 20px !important}
  html body #panel-kasir #history-filter-form .sales-filter-actions>a{display:none}
  html body #panel-kasir #history-filter-form:has(#sales-search:not([value=""]),#sales-start:not([value=""]),#sales-method option[selected]:not([value=""])) .sales-filter-actions>a{display:inline-flex;width:auto !important;padding:0 14px !important}
  html body #panel-kasir .pos-history-full>.scroll{margin:14px 0 0 !important;padding:0;border:0 !important;overflow:visible !important}
  html body #panel-kasir #history-table,html body #panel-kasir #history-table tbody,html body #panel-kasir #history-table thead{display:block !important;width:100% !important;min-width:0 !important}
  html body #panel-kasir #history-table thead{position:static !important;width:auto !important;height:auto !important;overflow:visible !important;clip:auto !important;clip-path:none !important;border-bottom:1px solid #E4EBE6}
  html body #panel-kasir #history-table tr{display:grid !important;grid-template-columns:auto minmax(0,1fr) auto 72px;grid-template-areas:"inv inv total status" "date method total doc";column-gap:10px;row-gap:4px;width:auto !important;margin:0 !important;padding:12px 0 !important;border:0 !important;border-bottom:1px solid #EEF2EF !important;border-radius:0 !important;background:transparent !important;box-shadow:none !important}
  html body #panel-kasir #history-table thead tr{grid-template-areas:"inv inv total status";padding:0 0 8px !important;border-bottom:0 !important}
  html body #panel-kasir #history-table tbody tr:last-child{border-bottom:0 !important}
  html body #panel-kasir #history-table tr[hidden]{display:none !important}
  html body #panel-kasir #history-table :is(th,td){display:block !important;width:auto !important;height:auto !important;min-height:0 !important;margin:0 !important;padding:0 !important;border:0 !important;background:transparent !important;text-align:left}
  html body #panel-kasir #history-table td::before{display:none !important;content:none !important}
  html body #panel-kasir #history-table th{font-size:13px !important;font-weight:500 !important;color:#5F7468 !important;background:transparent !important}
  html body #panel-kasir #history-table :is(th,td):nth-child(1){grid-area:inv;min-width:0}
  html body #panel-kasir #history-table td:nth-child(1){font-size:15px;line-height:19px}
  html body #panel-kasir #history-table td:nth-child(1) .sku-code{font-weight:700;overflow-wrap:anywhere}
  html body #panel-kasir #history-table :is(th,td):nth-child(2){grid-area:date}
  html body #panel-kasir #history-table :is(th,td):nth-child(3){grid-area:method}
  html body #panel-kasir #history-table td:is(:nth-child(2),:nth-child(3)){font-size:13px;line-height:16px;color:#5F7468;white-space:nowrap}
  html body #panel-kasir #history-table td:nth-child(3){margin-left:-6px !important}
  html body #panel-kasir #history-table td:nth-child(3)::before{display:inline !important;content:'· ' !important}
  html body #panel-kasir #history-table :is(th,td):nth-child(4){grid-area:total;text-align:right !important;align-self:start}
  html body #panel-kasir #history-table td:nth-child(4){font-size:15px;line-height:19px}
  html body #panel-kasir #history-table :is(th,td):nth-child(5){grid-area:status;text-align:center !important;justify-self:stretch}
  html body #panel-kasir #history-table :is(th,td):nth-child(6){grid-area:doc;text-align:center !important}
  html body #panel-kasir #history-table thead th:is(:nth-child(2),:nth-child(3),:nth-child(6)){display:none !important}
  html body #panel-kasir #history-table .ui-badge{min-width:64px;height:24px;padding:0 10px}
  html body #panel-kasir #history-table .doc-links{justify-content:center}
  html body #panel-kasir #history-table .doc-links a{min-width:0;height:28px;padding:0 6px;border:0;background:transparent;text-decoration:underline;text-underline-offset:3px}
  html body #panel-kasir .pos-history-full .pagination{display:grid;gap:10px;margin-top:4px;padding-top:12px;border-top:1px solid #EEF2EF}
  html body #panel-kasir .pos-history-full .pagination-controls{display:flex;justify-content:space-between;flex-wrap:nowrap !important;width:100%}
  html body #panel-kasir .pos-history-full .pagination-controls>.page-btn{width:44px;min-width:44px;height:40px;min-height:40px;padding:0;font-size:0}
  html body #panel-kasir .pos-history-full .pagination-controls>.page-btn:first-child::before{content:'\2039';font-size:22px;line-height:1}
  html body #panel-kasir .pos-history-full .pagination-controls>.page-btn:last-child::before{content:'\203A';font-size:22px;line-height:1}
  html body #panel-kasir .pos-history-full .page-list .page-btn{width:40px;height:40px;min-height:40px}
}
@media (max-width:380px){
  html body:has(#kasir) .main-topbar .header-state>span{display:none !important}
}

/* Laci menu HP: lebar 300, judul "Menu", grup aktif terbuka dengan garis cabang. */
@media (max-width:900px){
  html body:has(#kasir) #workspace-sidebar.app-sidebar{width:300px;min-width:300px;max-width:86vw;background:#0f2a1d}
  html body:has(#kasir) #workspace-sidebar .sidebar-brand{position:relative;flex-wrap:wrap;align-items:center;gap:12px;min-height:0;padding:76px 16px 16px;border-bottom:1px solid #2C4438}
  html body:has(#kasir) #workspace-sidebar .sidebar-brand::before{content:'Menu';position:absolute;left:64px;top:20px;font-size:20px;line-height:24px;font-weight:700;color:#fff}
  html body:has(#kasir) #workspace-sidebar .sidebar-brand::after{content:'';position:absolute;left:0;right:0;top:64px;height:1px;background:#2C4438}
  html body:has(#kasir) #workspace-sidebar .sidebar-close{position:absolute;left:10px;top:10px;width:44px;height:44px;min-height:44px;padding:0;border:0;border-radius:10px;background:transparent;color:#E7F3EC;font-size:30px;line-height:1;font-weight:300}
  html body:has(#kasir) #workspace-sidebar .sidebar-brand .brand-mark{display:grid !important;place-items:center;width:40px;height:40px;flex:0 0 40px;border-radius:11px;background:#1D5A3A;color:#E7F3EC}
  html body:has(#kasir) #workspace-sidebar .sidebar-brand-text{opacity:1 !important;visibility:visible !important}
  html body:has(#kasir) #workspace-sidebar .sidebar-brand-text strong{font-size:15px;color:#fff}
  html body:has(#kasir) #workspace-sidebar .sidebar-brand-text small{font-size:13px;color:#9FB8A9}
  html body:has(#kasir) #workspace-sidebar .sidebar-nav{padding:12px 10px}
  html body:has(#kasir) #workspace-sidebar .workspace-menu-group>summary{min-height:44px;padding:0 10px;gap:12px;border-radius:10px;font-size:14px;font-weight:700;color:#E7F3EC}
  html body:has(#kasir) #workspace-sidebar .workspace-menu-group>summary>span{opacity:1 !important;visibility:visible !important}
  html body:has(#kasir) #workspace-sidebar .workspace-menu-group>summary svg{color:#9FB8A9}
  html body:has(#kasir) #workspace-sidebar .workspace-menu-group:has([aria-current=page])>summary,html body:has(#kasir) #workspace-sidebar .workspace-menu-group:has([aria-current=page])>summary svg{color:#C8F26B}
  html body:has(#kasir) #workspace-sidebar .workspace-menu-group .workspace-submenu{position:relative;margin:2px 0 8px 20px !important;padding:0 0 0 14px !important;border-left:1px solid #4B5F55 !important;width:auto !important}
  html body:has(#kasir) #workspace-sidebar .workspace-submenu a{position:relative;display:flex;align-items:center;min-height:40px;margin:0 6px 2px 0;padding:0 14px;border-radius:9px;font-size:14px;font-weight:500;color:#E7F3EC}
  html body:has(#kasir) #workspace-sidebar .workspace-submenu a::before{content:'';position:absolute;left:-14px;top:50%;width:12px;height:1px;background:#4B5F55}
  html body:has(#kasir) #workspace-sidebar .workspace-submenu a[aria-current=page]{background:#2B4829;color:#C8F26B;font-weight:700}
  html body:has(#kasir) #workspace-sidebar .sidebar-footer{border-top:1px solid #2C4438}
  html body:has(#kasir) .sidebar-overlay{position:fixed;inset:0;z-index:95;width:100%;height:100%;padding:0;border:0;border-radius:0;background:rgba(4,12,8,.6) !important;cursor:pointer;animation:none !important}
}
/* ---------- 16. kontrak gerak permukaan mengambang (desain.md bagian 6) ----------
   Satu animasi untuk semua yang muncul di atas halaman: dropdown pilihan, combobox, kalender,
   flyout sidebar, popup filter, dan dialog form. Nilai sama dengan dropdown pilihan (dropdownIn). */
@keyframes ui-float-in{from{opacity:0;transform:translateY(-12px) scale(.85)}to{opacity:1;transform:none}}
:root{--ui-float-in:ui-float-in .26s cubic-bezier(.34,1.56,.64,1)}
@keyframes ui-dialog-in{from{opacity:0;transform:translateY(18px) scale(.97)}to{opacity:1;transform:none}}
@keyframes ui-fade-in{from{opacity:0}to{opacity:1}}
:root{--ui-dialog-in:ui-dialog-in .26s cubic-bezier(.22,.8,.24,1);--ui-ease-out:cubic-bezier(.22,.8,.24,1)}
html body :is(.select-options,.app-calendar,.pos-combo-list,.ui-pick-list,.filter-disclosure[open]>.filter-popup):not([hidden]){animation:var(--ui-float-in) !important;transform-origin:top center}
html body dialog[open]{animation:var(--ui-dialog-in) !important;transform-origin:center bottom}
html body dialog[open]::backdrop{animation:ui-fade-in .22s ease-out}
@media (prefers-reduced-motion:reduce){
  html body :is(.select-options,.app-calendar,.pos-combo-list,.ui-pick-list,.rail-flyout,.filter-popup,dialog){animation:none !important}
}
/* ---------- 18. Operasional (Tagihan, Pengiriman, Penerimaan, Kas) + popup formulir ----------
   Rancangan FigCraft 'Tagihan', 'Pengiriman', 'Penerimaan barang', 'Kas & laporan' yang disempurnakan.
   Judul halaman pindah ke topbar; ringkasan berupa kartu dengan ubin pertama hijau tua;
   tabel berkepala hijau; status berupa pil berbingkai; popup berkepala hijau dengan aksi di dasar. */
html body:is([data-active-task=tagihan],[data-active-task=pengiriman],[data-active-task=penerimaan],[data-active-task=shift],[data-active-task=laporan],[data-active-task=persetujuan],[data-active-task=master-product],[data-active-task=master-supplier],[data-active-task=master-customer],[data-active-task=master-employee],[data-active-task=master-vehicle],[data-active-task=master-unit],[data-active-task=master-category]) .ui-page-header{position:absolute !important;width:1px;height:1px;margin:0;padding:0;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
html body:is([data-active-task=tagihan],[data-active-task=pengiriman],[data-active-task=penerimaan],[data-active-task=shift],[data-active-task=laporan],[data-active-task=persetujuan],[data-active-task=master-product],[data-active-task=master-supplier],[data-active-task=master-customer],[data-active-task=master-employee],[data-active-task=master-vehicle],[data-active-task=master-unit],[data-active-task=master-category]) main.wrap{padding-top:8px}
html body :is(.ui-dna-ops,#ui-dna-ops){font-family:'Inter Kasir',-apple-system,'Segoe UI',sans-serif;color:#102A1D}
html body :is(.ui-dna-ops,#ui-dna-ops) :is(section.card,.ui-monitor--card){background:#fff !important;border:1px solid #E4EBE6 !important;border-radius:12px !important;padding:20px !important;box-shadow:none !important;margin:0 0 16px !important}
html body :is(.ui-dna-ops,#ui-dna-ops) :is(section.card,.ui-monitor--card) h2{font-family:'Inter Kasir',sans-serif;font-size:18px !important;line-height:24px;font-weight:700 !important;color:#102A1D !important;letter-spacing:-.01em;margin:0 !important}
html body :is(.ui-dna-ops,#ui-dna-ops) :is(section.card,.ui-monitor--card)>.note,html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor-head .note{margin:4px 0 16px !important;font-size:13px !important;line-height:18px;color:#5F7468 !important;max-width:none}
/* kartu ringkasan */
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor-head .note{margin-bottom:14px !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-pill{display:inline-flex;align-items:center;min-height:32px;padding:0 14px;border-radius:999px;border:1px solid;font-size:13px;font-weight:700;white-space:nowrap}
html body .ui-pill--ok{background:#E4F0E8;border-color:#BFD8C7;color:#14532D}
html body .ui-pill--warn{background:#FEF3C7;border-color:#F4D47C;color:#7A4B00}
html body .ui-pill--info{background:#E0ECFA;border-color:#B9D0EE;color:#1E4E8C}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-monitor-grid{display:grid !important;grid-template-columns:none !important;grid-auto-flow:column;grid-auto-columns:minmax(0,1fr);gap:12px !important;margin:0 !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile{position:relative;display:flex !important;flex-direction:column;align-items:stretch;justify-content:flex-start;gap:2px;min-height:84px;margin:0;padding:6px !important;border:1px solid #E4EBE6 !important;border-radius:10px !important;background:#fff !important;box-shadow:none !important;text-align:left;font:inherit;color:#102A1D;cursor:default}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile .ui-tile-body{display:flex;flex-direction:column;gap:2px;padding:10px 12px 0;border-radius:8px 8px 0 0;background:#F4F6F5}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile .ui-tile-hint{display:block;padding:2px 12px 10px;border-radius:0 0 8px 8px;background:#F4F6F5;font-size:13px !important;font-weight:500;color:#5F7468 !important;margin:0 !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile .ui-tile-label{display:flex;align-items:center;gap:7px;font-size:13px !important;font-weight:600 !important;color:#3C4A42 !important;text-transform:none !important;letter-spacing:0 !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile .ui-tile-label::before{content:'';width:7px;height:7px;border-radius:2px;background:#9AA8A0;flex:0 0 auto}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile--problem .ui-tile-label::before{background:#D92D20}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile--attention .ui-tile-label::before{background:#D97706}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile .ui-tile-value{font-family:'Inter Kasir',sans-serif;font-size:21px !important;line-height:28px;font-weight:700 !important;color:#102A1D !important;font-variant-numeric:tabular-nums;letter-spacing:-.01em;overflow-wrap:anywhere}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile--hero{padding:0 !important;border:0 !important;background:#14532D !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile--hero :is(.ui-tile-body,.ui-tile-hint){background:transparent !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile--hero .ui-tile-body{padding:14px 16px 0}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile--hero .ui-tile-hint{padding:2px 16px 14px;color:#C8F26B !important;font-weight:600}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile--hero .ui-tile-label{color:#C8F26B !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile--hero .ui-tile-label::before{display:none}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile--hero .ui-tile-value{font-size:26px !important;line-height:32px;color:#fff !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card button.ui-tile{cursor:pointer;transition:border-color .15s,box-shadow .15s}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card button.ui-tile:hover{border-color:#9FC2AC !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card button.ui-tile[aria-pressed=true]{box-shadow:0 0 0 2px #C8F26B,0 0 0 4px #14532D !important}
/* toolbar: kolom cari + chip pil (pola 'Riwayat shift') */
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-toolbar{display:flex !important;flex-wrap:wrap;align-items:center;gap:10px 12px !important;margin:0 0 16px !important;padding:0 !important;background:none !important;border:0 !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-search{flex:0 1 320px;min-width:220px;height:44px !important;border:1px solid #DFE6E1 !important;border-radius:8px !important;background:#fff !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-search input{font-size:14px !important;color:#102A1D}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-search input::placeholder{color:#7A8B81}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-chips{display:flex !important;flex-wrap:wrap;gap:8px !important;padding:0 !important;border:0 !important;background:none !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-chip{min-height:40px !important;height:40px;padding:0 16px !important;border:1px solid #DFE6E1 !important;border-radius:999px !important;background:#fff !important;color:#3C4A42 !important;font-size:13px !important;font-weight:600 !important;box-shadow:none !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-chip:hover{border-color:#9FC2AC !important;color:#14532D !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-chip[aria-pressed=true]{background:#14532D !important;border-color:#14532D !important;color:#fff !important}
/* tabel */
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table-wrap{border:1px solid #E4EBE6 !important;border-radius:10px !important;overflow:auto;background:#fff;box-shadow:none !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table{border-collapse:separate !important;border-spacing:0;width:100%}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table thead th{position:static;height:44px;padding:0 16px !important;background:#14532D !important;color:#fff !important;font-size:13px !important;font-weight:600 !important;text-transform:none !important;letter-spacing:0 !important;border:0 !important;white-space:nowrap}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table tbody td{padding:14px 16px !important;border-bottom:1px solid #EEF2EF !important;font-size:14px !important;line-height:20px;color:#3C4A42;vertical-align:middle;background:#fff}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table tbody tr:last-child>td{border-bottom:0 !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table tbody tr:not([data-ui-empty]):not([data-ui-empty-static]):hover>td{background:#F8FAF9}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table td.ui-primary{font-weight:700 !important;color:#102A1D !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table td.ui-num{font-variant-numeric:tabular-nums;color:#3C4A42}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table td.ui-num strong{color:#102A1D;font-weight:700}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table .ui-sub{display:block;margin-top:2px;font-size:13px;font-weight:400;color:#5F7468}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table td.ui-actions{text-align:right;white-space:nowrap}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table td.ui-actions :is(.popup-action,button:not(.popup-close),a.btn,a.btn-action-pill):not(dialog *){display:inline-flex !important;align-items:center;justify-content:center;text-decoration:none !important;line-height:1 !important;min-height:36px !important;height:36px;padding:0 16px !important;border:1px solid #D5DED8 !important;border-radius:8px !important;background:#fff !important;color:#14532D !important;font-size:13px !important;font-weight:700 !important;box-shadow:none !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table td.ui-actions :is(.popup-action,button:not(.popup-close),a.btn,a.btn-action-pill):not(dialog *):hover{border-color:#14532D !important;background:#F1F7F3 !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table td.ui-actions{display:table-cell}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table td.ui-actions>*+*{margin-left:8px}
/* aksi utama baris (mis. Kirim barang pada surat jalan siap dikirim) berwarna penuh */
html body #panel-pengiriman .ui-table tr[data-status=prepared] td.ui-actions>.popup-action:first-of-type{background:#14532D !important;border-color:#14532D !important;color:#fff !important}
html body #panel-pengiriman .ui-table tr[data-status=prepared] td.ui-actions>.popup-action:first-of-type:hover{background:#0F3F22 !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table td.ui-actions .ui-muted{font-size:13px;color:#7A8B81}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-empty{padding:28px 16px !important;text-align:center;color:#5F7468}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-empty strong{display:block;margin-bottom:4px;font-size:15px;color:#102A1D}
/* status: pil berbingkai, tanpa titik */
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-badge{display:inline-flex;align-items:center;min-height:28px;padding:0 12px !important;border:1px solid transparent;border-radius:999px !important;font-size:13px !important;font-weight:700 !important;white-space:nowrap}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-badge::before{display:none !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-badge--ok{background:#E4F0E8 !important;border-color:#BFD8C7 !important;color:#14532D !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-badge--warn{background:#FEF3C7 !important;border-color:#F4D47C !important;color:#7A4B00 !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-badge--bad{background:#FDE7E5 !important;border-color:#F3B7B1 !important;color:#9B1C1C !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-badge--info{background:#E0ECFA !important;border-color:#B9D0EE !important;color:#1E4E8C !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-badge--mute{background:#F1F4F2 !important;border-color:#DFE6E1 !important;color:#5F7468 !important}
/* halaman */
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-pager{display:flex !important;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:14px 0 0 !important;padding:0 !important;border:0 !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-pager .ui-status-line{font-size:13px !important;color:#5F7468 !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-pager-controls{display:flex !important;align-items:center;gap:6px}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-pager-controls>label{display:none !important}
html body :is(.ui-dna-ops,#ui-dna-ops) :is(.ui-pager-controls,.ui-pager)[hidden]{display:none !important}
html body :is(.ui-dna-ops,#ui-dna-ops) :is(section.card,.ui-monitor--card) h2{border:0 !important;padding:0 !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile :is(.ui-tile-body,.ui-tile-hint){border:0 !important;box-shadow:none !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-pager-controls button,html body :is(.ui-dna-ops,#ui-dna-ops) .ui-pages button{min-width:36px;min-height:36px !important;height:36px;padding:0 10px !important;border:1px solid #DFE6E1 !important;border-radius:8px !important;background:#fff !important;color:#14532D !important;font-size:13px !important;font-weight:700 !important;box-shadow:none !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-pages{display:inline-flex;gap:6px}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-pages button[aria-current=page]{background:#14532D !important;border-color:#14532D !important;color:#fff !important}
html body :is(.ui-dna-ops,#ui-dna-ops) .ui-pager-controls button:disabled{opacity:.45;cursor:not-allowed}
/* laporan harian: kartu rincian abu muda, baris total bersorot hijau, cakupan berupa pil */
html body #panel-laporan .ui-report-card{background:#F7F9F8 !important;border:1px solid #E4EBE6 !important;border-radius:10px !important;padding:16px !important;box-shadow:none !important}
html body #panel-laporan .ui-report-card h3{margin:0 0 10px !important;font-size:16px !important;font-weight:700 !important;color:#102A1D !important}
html body #panel-laporan .ui-kv>div{display:flex;justify-content:space-between;gap:12px;padding:10px 0 !important;border-bottom:1px solid #E8EEEA !important;font-size:14px}
html body #panel-laporan .ui-kv>div:last-child{border-bottom:0 !important}
html body #panel-laporan .ui-kv dt{color:#3C4A42 !important;font-weight:400 !important}
html body #panel-laporan .ui-kv dd{margin:0;font-weight:700 !important;color:#102A1D !important;font-variant-numeric:tabular-nums}
html body #panel-laporan .ui-kv>div.is-total{margin:4px -8px;padding:10px 8px !important;border:1px solid #CFE3D6 !important;border-radius:8px;background:#E4F0E8}
html body #panel-laporan .ui-kv>div.is-total :is(dt,dd){color:#14532D !important;font-weight:700 !important;font-size:14px !important}
html body #panel-laporan section.card>.ui-status-line{display:inline-flex;align-items:center;min-height:32px;margin:6px 0 16px !important;padding:0 16px;border:1px solid #CFE3D6;border-radius:999px;background:#E4F0E8;color:#14532D !important;font-size:13px !important;font-weight:700}
html body #panel-laporan :is(input[type=text],input[type=date],.date-field,.ui-date){min-height:44px}
html body #panel-laporan section.card form button[type=submit]{min-height:44px !important;padding:0 22px !important;border-radius:10px !important}
/* tombol pembuka popup di kepala kartu (Tambah penerimaan, Catat biaya ritase, ...) */
html body #panel-penerimaan section.has-head-action{position:relative}
html body #panel-penerimaan section.has-head-action>h2,html body #panel-penerimaan section.has-head-action>.note{padding-right:220px !important}
html body #panel-penerimaan section.has-head-action>.card-head-action{position:absolute;top:20px;right:20px;margin:0 !important}
html body :is(.ui-dna-ops,#ui-dna-ops) section.card>.popup-action,html body :is(.ui-dna-ops,#ui-dna-ops) .popup-actions>.popup-action{min-height:44px !important;padding:0 20px !important;border:0 !important;border-radius:10px !important;background:#14532D !important;color:#fff !important;font-size:14px !important;font-weight:700 !important}
html body :is(.ui-dna-ops,#ui-dna-ops) section.card>.popup-action:hover{background:#0F3F22 !important}

/* popup formulir buatan popup() (dialog#input-popup-*: Operasional). Dialog Kasir dan master punya gaya sendiri. ':is(body#ui-dna-strong,body)' hanya menaikkan bobot selektor
   (bobot ID) agar menang atas aturan tombol tabel/halaman lama yang ikut mengenai dialog di dalam sel tabel. */

html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"][open]{position:fixed !important;inset:0 !important;margin:auto !important;height:fit-content !important;max-height:calc(100dvh - 32px) !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] :is(.popup-content,.popup-content *){white-space:normal}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"]{width:min(520px,calc(100vw - 32px)) !important;max-width:none !important;padding:0 !important;border:0 !important;border-radius:14px !important;overflow:hidden;background:#fff !important;box-shadow:0 24px 60px -20px rgba(4,12,8,.45) !important;font-family:'Inter Kasir',-apple-system,'Segoe UI',sans-serif;color:#102A1D}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"].popup-wide{width:min(860px,calc(100vw - 32px)) !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"]::backdrop{background:rgba(9,30,18,.55) !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-heading{display:flex !important;align-items:flex-start;justify-content:space-between;gap:12px;margin:0 !important;padding:18px 20px 16px 24px !important;background:#14532D !important;border:0 !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-titles{text-align:left !important;min-width:0}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-heading h2{margin:0 !important;font-family:'Bricolage Kasir','Inter Kasir',sans-serif !important;font-size:22px !important;line-height:28px !important;font-weight:700 !important;color:#C8F26B !important;letter-spacing:-.01em}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-sub{margin:2px 0 0 !important;font-size:13px !important;line-height:18px;color:#D6E8DC !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-close{display:grid !important;place-items:center;flex:0 0 auto;width:36px !important;height:36px !important;min-width:36px !important;min-height:36px !important;padding:0 !important;border:0 !important;border-radius:8px !important;background:transparent !important;color:#fff !important;font-size:22px !important;line-height:1 !important;box-shadow:none !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-close:hover{background:rgba(255,255,255,.12) !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-content{max-height:calc(100dvh - 180px);overflow:auto;padding:20px 24px 0 !important;text-align:left !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"]:not(.has-submit-bar) .popup-content{padding-bottom:20px !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-content>.note{margin:0 0 14px !important;font-size:13px;color:#5F7468}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] :is(form,.field,.row,label){text-align:left !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .row{display:grid !important;grid-template-columns:repeat(auto-fit,minmax(180px,1fr)) !important;gap:16px !important;align-items:start}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .field{display:flex !important;flex-direction:column;gap:6px;margin:0 0 16px !important;min-width:0}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .row>.field{margin-bottom:0 !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-content>section.card{margin:0 !important;padding:0 !important;border:0 !important;border-radius:0 !important;background:none !important;box-shadow:none !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .select-shell .select-trigger{white-space:nowrap !important;overflow:hidden;text-overflow:ellipsis}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .row{margin-bottom:16px !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] label{font-size:14px !important;font-weight:600 !important;color:#102A1D !important;justify-content:flex-start !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] :is(input:not([type=checkbox]):not([type=radio]):not([type=hidden]),textarea,.select-shell .select-trigger){width:100% !important;min-height:46px;border:1px solid #DFE6E1 !important;border-radius:8px !important;background:#fff !important;font-size:15px !important;color:#102A1D !important;padding:0 14px !important;box-shadow:none !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] input[readonly]{background:#F4F6F5 !important;color:#5F7468 !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] :is(input,textarea):focus{outline:none !important;border-color:#14532D !important;box-shadow:0 0 0 3px rgba(20,83,45,.16) !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] details.technical{margin:0 0 12px}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] details.technical>summary{font-size:13px;font-weight:600;color:#5F7468;cursor:pointer}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:0 0 18px;padding:12px 14px;border:1px solid #E4EBE6;border-radius:10px;background:#F4F6F5}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-summary dt{font-size:13px;color:#5F7468}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-summary dd{margin:2px 0 0;font-size:16px;font-weight:700;color:#102A1D;font-variant-numeric:tabular-nums}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-summary .is-due dd{color:#14532D}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-hint{margin:-4px 0 0 !important;font-size:13px !important;color:#5F7468 !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] :is(.select-shell:has(select[data-segmented]),select[data-segmented]){display:none !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] :is(.popup-submit>button,.ui-segment button){display:inline-flex !important;align-items:center;justify-content:center !important;text-align:center}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .ui-segment-source{position:absolute !important;width:1px !important;height:1px !important;overflow:hidden !important;clip-path:inset(50%) !important;opacity:0}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .ui-segment{display:grid;grid-auto-flow:column;grid-auto-columns:1fr;gap:4px;padding:4px;border-radius:10px;background:#EEF2EF}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .ui-segment button{min-height:38px !important;height:38px;padding:0 12px !important;border:0 !important;border-radius:7px !important;background:transparent !important;color:#3C4A42 !important;font-size:14px !important;font-weight:600 !important;box-shadow:none !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .ui-segment button[aria-checked=true]{background:#fff !important;color:#14532D !important;font-weight:700 !important;box-shadow:0 1px 2px rgba(16,42,29,.12) !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-submit{display:grid;grid-template-columns:minmax(120px,.42fr) 1fr;gap:12px;margin:20px -24px 0;padding:16px 24px 20px;border-top:1px solid #EEF2EF;background:#fff;position:sticky;bottom:0}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-submit>button{min-height:46px !important;height:46px;margin:0 !important;border-radius:10px !important;font-size:15px !important;font-weight:700 !important;box-shadow:none !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-submit>button[type=button]{border:1px solid #D5DED8 !important;background:#fff !important;color:#14532D !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-submit>button:not([type=button]){border:0 !important;background:#C8F26B !important;color:#14532D !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-submit>button:not([type=button]):hover{background:#B8E557 !important}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-footer{display:flex;justify-content:flex-end;gap:12px;padding:14px 24px 18px !important;border-top:1px solid #EEF2EF !important;background:#fff}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-footer>button{min-height:44px;padding:0 20px;border:1px solid #D5DED8 !important;border-radius:10px !important;background:#fff !important;color:#14532D !important;font-weight:700}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] :is(.err,[data-error]):not([hidden]){margin:0 0 12px;padding:10px 12px;border-radius:8px;background:#FDE7E5;color:#9B1C1C;font-size:14px}
html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] :is(.ok,[data-ok]):not([hidden]){margin:0 0 12px;padding:10px 12px;border-radius:8px;background:#E4F0E8;color:#14532D;font-size:14px}
/* Operasional di HP (<=760px): ringkasan 2 kolom dengan ubin utama selebar kartu, chip bisa digeser,
   baris tabel menjadi kartu label-nilai yang rapat, aksi selebar kartu, popup hampir layar penuh. */
@media (max-width:760px){
  html body:is([data-active-task=tagihan],[data-active-task=pengiriman],[data-active-task=penerimaan],[data-active-task=shift],[data-active-task=laporan],[data-active-task=persetujuan],[data-active-task=master-product],[data-active-task=master-supplier],[data-active-task=master-customer],[data-active-task=master-employee],[data-active-task=master-vehicle],[data-active-task=master-unit],[data-active-task=master-category]) main.wrap{padding:12px 12px 24px !important}
  html body :is(.ui-dna-ops,#ui-dna-ops) :is(section.card,.ui-monitor--card){padding:16px !important;border-radius:12px !important}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor-head{flex-wrap:wrap;gap:8px}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor-head .ui-pill{order:3}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-monitor-grid{grid-auto-flow:row !important;grid-template-columns:repeat(2,minmax(0,1fr)) !important;grid-auto-columns:auto !important;gap:8px !important}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile--hero{grid-column:1/-1}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile--hero~.ui-tile:last-child:nth-child(even){grid-column:1/-1}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table tbody td:has(>.ui-sub){flex-wrap:wrap;row-gap:0}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table tbody td>.ui-sub{flex:0 0 100%;text-align:right}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile{min-height:0}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile .ui-tile-value{font-size:18px !important;line-height:24px}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile--hero .ui-tile-value{font-size:24px !important;line-height:30px}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-monitor--card .ui-tile .ui-tile-label{font-size:13px !important}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-toolbar{flex-direction:column;align-items:stretch !important}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-search{flex:0 0 auto;width:100%;min-width:0}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-chips{flex-wrap:nowrap !important;overflow-x:auto;scrollbar-width:none;margin:0 -16px;padding:0 16px 2px !important}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-chips::-webkit-scrollbar{display:none}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-chip{flex:0 0 auto}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table-wrap{border:0 !important;border-radius:0 !important;overflow:visible;background:transparent}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table thead{display:none !important}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table tbody tr:not([hidden]){display:block !important;margin:0 0 10px;padding:12px 14px !important;border:1px solid #E4EBE6 !important;border-radius:10px;background:#fff}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table tbody tr[hidden]{display:none !important}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table tbody td{display:flex !important;justify-content:space-between;align-items:center;gap:12px;padding:5px 0 !important;border:0 !important;font-size:14px !important;text-align:right !important;background:transparent !important}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table tbody td::before{flex:0 0 auto;font-size:13px !important;font-weight:500 !important;color:#5F7468 !important;text-align:left;text-transform:none !important;letter-spacing:0 !important}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table tbody td.ui-primary{justify-content:flex-start;padding:0 0 8px !important;margin-bottom:4px;border-bottom:1px solid #EEF2EF !important;font-size:15px !important;text-align:left !important}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table tbody td.ui-primary::before{display:none !important}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table tbody td.ui-actions{display:flex !important;flex-wrap:wrap;justify-content:stretch;gap:8px;padding:10px 0 0 !important;margin-top:6px;border-top:1px solid #EEF2EF !important}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table tbody td.ui-actions::before{display:none !important}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table tbody td.ui-actions>*+*{margin-left:0}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table tbody td.ui-actions>:is(.popup-action,a.btn-action-pill,form){flex:1 1 0;min-height:44px !important;height:44px}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table tbody td.ui-actions>.ui-muted{display:none}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table tbody td.ui-actions:not(:has(>:is(.popup-action,a,form))){display:none !important}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table tbody td.ui-empty{display:block !important;text-align:center !important}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-table tbody td.ui-empty::before{display:none !important}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-pager{justify-content:center}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-pager .ui-status-line{width:100%;text-align:center}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-pager-controls>button{font-size:0 !important;width:44px;min-width:44px;height:44px;min-height:44px !important}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-pager-controls>button:first-of-type::before{content:'‹';font-size:20px}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-pager-controls>button:last-of-type::before{content:'›';font-size:20px}
  html body :is(.ui-dna-ops,#ui-dna-ops) .ui-pages button{min-width:44px;height:44px;min-height:44px !important}
  html body #panel-penerimaan section.has-head-action>:is(h2,.note){padding-right:0 !important}
  html body #panel-penerimaan section.has-head-action>.card-head-action{position:static;width:100%;margin:0 0 14px !important}
  html body #panel-laporan .ui-report-grid,html body #panel-laporan :has(>.ui-report-card){grid-template-columns:1fr !important}
  html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"][open]{width:calc(100vw - 16px) !important;max-height:calc(100dvh - 16px) !important;border-radius:14px !important}
  html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-heading{padding:14px 12px 12px 18px !important}
  html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-heading h2{font-size:20px !important;line-height:26px !important}
  html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-content{max-height:calc(100dvh - 120px);padding:16px 18px 0 !important}
  html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-submit{margin:16px -18px 0;padding:12px 18px 16px}
  html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-summary{grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;padding:10px 12px}
  html :is(body#ui-dna-strong,body) dialog.input-dialog[id^="input-popup-"] .popup-summary dd{font-size:14px}
}
/* Riwayat penerimaan per barang (rancangan 'Penerimaan barang'): satu baris per nota, barang ditumpuk per baris. */
html body #panel-penerimaan #receipt-history>.note strong{color:#102A1D;font-weight:700}
html body #panel-penerimaan #receipt-history .ui-table .rh-date{color:#5F7468 !important;white-space:nowrap}
html body #panel-penerimaan #receipt-history .ui-table .rh-number{min-width:200px}
html body #panel-penerimaan #receipt-history .ui-table .rh-ref{color:#7A8B81}
html body #panel-penerimaan #receipt-history .ui-table .rh-lines>span{display:block;white-space:nowrap}
html body #panel-penerimaan #receipt-history .ui-table .rh-lines>span+span{margin-top:6px}
html body #panel-penerimaan #receipt-history .ui-table .rh-product{font-weight:700;color:#102A1D !important}
html body #panel-penerimaan #receipt-history .ui-table .rh-product>span{white-space:normal}
html body #panel-penerimaan #receipt-history .ui-table .rh-lines small{font-size:13px;color:#7A8B81}
html body #panel-penerimaan #receipt-history .ui-table .rh-type .ui-badge{min-height:26px;padding:0 10px !important}
html body #panel-penerimaan #receipt-history .ui-table .rh-type .ui-sub{margin-top:4px;font-size:12px !important;color:#7A8B81 !important;white-space:nowrap}
html body #panel-penerimaan #receipt-history .ui-table .rh-total strong{color:#14532D !important;font-weight:700}
@media (max-width:760px){
  html body #panel-penerimaan #receipt-history .ui-table .rh-number{min-width:0}
  html body #panel-penerimaan #receipt-history .ui-table tbody td.rh-lines{display:grid !important;grid-template-columns:auto minmax(0,1fr);column-gap:12px;row-gap:4px;align-items:start}
  html body #panel-penerimaan #receipt-history .ui-table tbody td.rh-lines::before{grid-column:1;grid-row:1}
  html body #panel-penerimaan #receipt-history .ui-table tbody td.rh-lines>span{grid-column:2;margin:0 !important;text-align:right;white-space:normal}
  html body #panel-penerimaan #receipt-history .ui-table tbody td.ui-primary{flex-wrap:wrap}
  html body #panel-penerimaan #receipt-history .ui-table tbody td.ui-primary>.ui-sub{flex:0 0 100%;text-align:left !important}
  /* Topbar HP: judul, Admin, dan tombol shift tetap satu baris; judul panjang dipotong dengan elipsis. */
  html body .main-topbar>.wrap{flex-wrap:nowrap !important}
  html body .main-topbar .top-context{flex:1 1 auto !important;min-width:0 !important;width:auto !important}
  html body .main-topbar .header-state{flex:0 0 auto;margin-left:8px}
  html body:not([data-active-task=kasir]) .main-topbar .top-context strong{font-size:22px !important;line-height:28px !important;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;display:block}
  html body #panel-penerimaan #receipt-history .ui-table tbody td.rh-type{flex-wrap:wrap}
  html body #panel-penerimaan #receipt-history .ui-table tbody td.rh-type>.ui-sub{flex:0 0 100%;text-align:right}
}
/* ---------- 19. Popup Kasir (Pembayaran, Tinjau Pembayaran) mengikuti tema popup bagian 18 ----------
   Kepala hijau tua + judul lime rata kiri, isi putih berjarak 24px, aksi di dasar: kembali (garis) kiri, lanjut (lime) kanan.
   Hanya tampilan; id, name, dan urutan tombol yang dipakai skrip kasir tidak berubah. */
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay){width:min(520px,calc(100vw - 32px)) !important;max-width:none !important;padding:0 !important;border:0 !important;border-radius:14px !important;overflow:hidden;background:#fff !important;box-shadow:0 24px 60px -20px rgba(4,12,8,.45) !important;font-family:'Inter Kasir',-apple-system,'Segoe UI',sans-serif;color:#102A1D}
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay)[open]{position:fixed !important;inset:0 !important;margin:auto !important;height:fit-content !important;transform:none;display:flex !important;flex-direction:column;align-items:stretch !important;max-height:calc(100dvh - 32px) !important}
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay)>*{width:auto !important;align-self:stretch !important}
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay)::backdrop{background:rgba(9,30,18,.55) !important}
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) .popup-heading{display:flex !important;align-items:center;justify-content:space-between !important;gap:12px;margin:0 !important;padding:16px 16px 16px 24px !important;background:#14532D !important;border:0 !important;text-align:left !important}
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) .popup-heading h2{flex:1 1 auto;margin:0 !important;font-family:'Bricolage Kasir','Inter Kasir',sans-serif !important;font-size:22px !important;line-height:28px !important;font-weight:700 !important;color:#C8F26B !important;text-align:left !important;letter-spacing:-.01em}
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) .popup-close{display:grid !important;place-items:center;flex:0 0 auto;width:36px !important;height:36px !important;min-width:36px !important;min-height:36px !important;padding:0 !important;border:0 !important;border-radius:8px !important;background:transparent !important;color:#fff !important;font-size:22px !important;line-height:1 !important;box-shadow:none !important}
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) .popup-close:hover{background:rgba(255,255,255,.12) !important}
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) .popup-content{flex:1 1 auto;overflow:auto;padding:20px 24px 4px !important;text-align:left !important}
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) :is(label,.field>label){font-size:14px !important;font-weight:600 !important;color:#102A1D !important}
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) .field{margin:0 0 14px !important}
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) :is(input:not([type=hidden]):not([type=checkbox]),.select-shell .select-trigger,select){min-height:46px;border:1px solid #DFE6E1 !important;border-radius:8px !important;background:#fff !important;font-size:15px !important;color:#102A1D !important;box-shadow:none !important}
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) :is(input,.select-trigger):focus{outline:none !important;border-color:#14532D !important;box-shadow:0 0 0 3px rgba(20,83,45,.16) !important}
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) .note{font-size:13px !important;color:#5F7468 !important}
/* Total tagihan: panel hijau muda, angka besar */
html :is(body#ui-dna-strong,body) #payment-modal-overlay .summary-box{margin:0 0 16px !important;padding:14px 16px !important;border:1px solid #CFE3D6 !important;border-radius:10px !important;background:#E4F0E8 !important;box-shadow:none !important}
html :is(body#ui-dna-strong,body) #payment-modal-overlay .summary-box .summary-row{display:flex;justify-content:space-between;align-items:center;margin:0}
html :is(body#ui-dna-strong,body) #payment-modal-overlay .summary-box .summary-row strong:first-child{font-size:14px;font-weight:600;color:#14532D}
html :is(body#ui-dna-strong,body) #payment-modal-overlay #payment-modal-total{font-size:22px !important;line-height:28px;font-weight:800 !important;color:#14532D !important;font-variant-numeric:tabular-nums}
/* Informasi pembeli: panel abu muda */
html :is(body#ui-dna-strong,body) #payment-modal-overlay .modal-buyer-block{margin:0 0 16px !important;padding:14px 16px !important;border:1px solid #E4EBE6 !important;border-radius:10px !important;background:#F4F6F5 !important;box-shadow:none !important}
html :is(body#ui-dna-strong,body) #payment-modal-overlay .modal-buyer-block .field{margin:8px 0 0 !important}
html :is(body#ui-dna-strong,body) #payment-modal-overlay .pos-buyer-title{font-size:14px !important;font-weight:700 !important;color:#102A1D !important}
html :is(body#ui-dna-strong,body) #payment-modal-overlay .pos-buyer-title svg{color:#14532D}
html :is(body#ui-dna-strong,body) #payment-modal-overlay .pos-buyer-badge{min-height:28px;padding:0 12px !important;border:1px solid #DFE6E1 !important;border-radius:999px !important;background:#fff !important;color:#3C4A42 !important;font-size:13px !important;font-weight:700 !important}
html :is(body#ui-dna-strong,body) #payment-modal-overlay .pos-buyer-meta{margin:8px 0 0 !important}
/* Metode pembayaran: tiga tombol pilihan (select asli disembunyikan, tetap dikirim) */
html :is(body#ui-dna-strong,body) #payment-modal-overlay .pay-method-native{position:absolute !important;width:1px !important;height:1px !important;min-height:0 !important;overflow:hidden !important;clip-path:inset(50%) !important;opacity:0;pointer-events:none}
html :is(body#ui-dna-strong,body) #payment-modal-overlay .pay-method{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:4px;margin-top:6px;padding:4px;border-radius:10px;background:#EEF2EF}
html :is(body#ui-dna-strong,body) #payment-modal-overlay .pay-method button{display:flex !important;flex-direction:column;align-items:center;justify-content:center;gap:0;min-height:44px !important;height:auto !important;margin:0 !important;padding:4px 8px !important;border:0 !important;border-radius:7px !important;background:transparent !important;color:#3C4A42 !important;font-size:15px !important;font-weight:600 !important;line-height:1.2 !important;box-shadow:none !important;cursor:pointer}
html :is(body#ui-dna-strong,body) #payment-modal-overlay .pay-method button small{font-size:12px;font-weight:500;color:#7A8B81}
html :is(body#ui-dna-strong,body) #payment-modal-overlay .pay-method button:hover{color:#14532D !important}
html :is(body#ui-dna-strong,body) #payment-modal-overlay .pay-method button[aria-checked=true]{background:#fff !important;color:#14532D !important;font-weight:700 !important;box-shadow:0 1px 2px rgba(16,42,29,.14) !important}
html :is(body#ui-dna-strong,body) #payment-modal-overlay .pay-method button[aria-checked=true] small{color:#5F7468}
html :is(body#ui-dna-strong,body) #payment-modal-overlay .pay-method button:focus-visible{outline:2px solid #14532D !important;outline-offset:1px}
/* Kembalian: baris ringkasan bergaris */
html :is(body#ui-dna-strong,body) #payment-modal-overlay .popup-content>.summary-row{display:flex;justify-content:space-between;align-items:center;margin:4px 0 0;padding:12px 0;border-top:1px solid #EEF2EF;border-bottom:1px solid #EEF2EF;font-size:15px;font-weight:600;color:#3C4A42}
html :is(body#ui-dna-strong,body) #payment-modal-overlay #display-change{font-size:18px;font-weight:800;color:#14532D;font-variant-numeric:tabular-nums}
html :is(body#ui-dna-strong,body) #payment-modal-overlay .checkout-options{margin:12px 0 8px !important}
html :is(body#ui-dna-strong,body) #payment-modal-overlay .checkout-options>summary{min-height:44px;display:flex;align-items:center;font-size:14px;font-weight:700;color:#14532D;cursor:pointer}
/* Tinjau: daftar label-nilai */
html :is(body#ui-dna-strong,body) #checkout-confirm-overlay #checkout-review{margin:0 0 12px;padding:4px 16px;border:1px solid #E4EBE6;border-radius:10px;background:#F4F6F5}
html :is(body#ui-dna-strong,body) #checkout-confirm-overlay .review-line{display:flex;justify-content:space-between;gap:16px;margin:0 !important;padding:10px 0;border-bottom:1px solid #E8EEEA;font-size:14px}
html :is(body#ui-dna-strong,body) #checkout-confirm-overlay .review-line:last-child{border-bottom:0}
html :is(body#ui-dna-strong,body) #checkout-review>ul{margin:0 !important;padding:6px 0 !important;list-style:none;border-bottom:1px solid #E8EEEA}
html :is(body#ui-dna-strong,body) #checkout-review>ul>li{margin:0 !important;padding:6px 0 !important;border:0 !important;font-size:14px;font-weight:600;color:#102A1D}
html :is(body#ui-dna-strong,body) #checkout-confirm-overlay .review-line strong{font-weight:600;color:#3C4A42}
html :is(body#ui-dna-strong,body) #checkout-confirm-overlay .review-line span{font-weight:700;color:#102A1D;text-align:right;font-variant-numeric:tabular-nums}
/* Aksi di dasar */
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) .popup-footer{display:grid !important;grid-template-columns:minmax(0,1fr) minmax(0,1.3fr);gap:12px;margin:0 !important;padding:16px 24px 20px !important;border-top:1px solid #EEF2EF !important;background:#fff !important}
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) .popup-footer>button:not([hidden]){display:inline-flex !important;align-items:center;justify-content:center;width:100% !important;min-height:46px !important;height:auto;margin:0 !important;padding:8px 14px !important;border-radius:10px !important;font-size:15px !important;font-weight:700 !important;line-height:1.25 !important;text-align:center;box-shadow:none !important}
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) .popup-footer>button.secondary{border:1px solid #D5DED8 !important;background:#fff !important;color:#14532D !important}
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) .popup-footer>button.secondary:hover{border-color:#14532D !important;background:#F1F7F3 !important}
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) .popup-footer>button:not(.secondary){border:0 !important;background:#C8F26B !important;color:#14532D !important}
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) .popup-footer>button:not(.secondary):hover{background:#B8E557 !important}
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) .popup-footer>button[hidden]{display:none !important}
html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) :is(.err,[data-error]):not([hidden]){margin:0 0 12px;padding:10px 12px;border-radius:8px;background:#FDE7E5;color:#9B1C1C;font-size:14px}
@media (max-width:760px){
  html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay)[open]{width:calc(100vw - 16px) !important;max-height:calc(100dvh - 16px) !important}
  html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) .popup-heading{padding:14px 12px 14px 18px !important}
  html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) .popup-heading h2{font-size:20px !important;line-height:26px !important}
  html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) .popup-content{padding:16px 18px 4px !important}
  html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) .popup-footer{padding:12px 18px 16px !important;grid-template-columns:minmax(0,.85fr) minmax(0,1.15fr);gap:10px}
  html :is(body#ui-dna-strong,body) :is(#payment-modal-overlay,#checkout-confirm-overlay) .popup-footer>button:not([hidden]){font-size:14px !important;padding:6px 10px !important}
}
/* ---------- 20. Master (Produk, Supplier & pelanggan, Karyawan & sopir, Mobil, Satuan & kategori) ----------
   Mengikuti DNA Operasional (bagian 18, cakupannya sudah diperluas ke panel master): ringkasan berubin hijau tua,
   tabel berkepala hijau, chip pil. Di sini hanya yang khas master: tombol kepala tabel, kolom stok, aksi Ubah/Arsipkan, popup form. */
html body :is(.ui-dna-master,#ui-dna-master) .ui-toolbar-actions{display:flex;flex-wrap:wrap;gap:8px;margin-left:auto}
html body :is(.ui-dna-master,#ui-dna-master) .ui-toolbar-actions>:is(button,a){display:inline-flex !important;align-items:center;justify-content:center;min-height:44px !important;height:44px;padding:0 18px !important;border-radius:10px !important;font-size:14px !important;font-weight:700 !important;text-decoration:none !important;white-space:nowrap;box-shadow:none !important}
html body :is(.ui-dna-master,#ui-dna-master) .ui-toolbar-actions>.btn-add-master{border:1px solid #14532D !important;background:#14532D !important;color:#fff !important}
html body :is(.ui-dna-master,#ui-dna-master) .ui-toolbar-actions>.btn-add-master::before{content:'+';margin-right:8px;font-size:18px;font-weight:600;line-height:1}
html body :is(.ui-dna-master,#ui-dna-master) .ui-toolbar-actions>.btn-add-master:hover{background:#0F3F22 !important}
html body :is(.ui-dna-master,#ui-dna-master) .ui-toolbar-actions>:is(.btn-import-master,.btn-template-download,.secondary){border:1px solid #D5DED8 !important;background:#fff !important;color:#14532D !important}
html body :is(.ui-dna-master,#ui-dna-master) .ui-toolbar-actions>:is(.btn-import-master,.btn-template-download,.secondary):hover{border-color:#14532D !important;background:#F1F7F3 !important}
html body :is(.ui-dna-master,#ui-dna-master) .ui-subtabs{margin:0 0 16px !important}
html body :is(.ui-dna-master,#ui-dna-master) .ui-table td:is([data-field=sku],[data-field=code],[data-label=Kode]){font-size:13px !important;color:#5F7468 !important;font-variant-numeric:tabular-nums;letter-spacing:.01em}
html body :is(.ui-dna-master,#ui-dna-master) .ui-table td[data-field=name]{font-weight:700 !important;color:#102A1D !important}
html body :is(.ui-dna-master,#ui-dna-master) .ui-table td[data-field=stock_quantity]{white-space:nowrap}
html body :is(.ui-dna-master,#ui-dna-master) .ui-table td[data-field=stock_quantity] .ui-figure{font-size:15px;font-weight:700;color:#102A1D}
html body :is(.ui-dna-master,#ui-dna-master) .ui-table td[data-field=stock_quantity] .ui-sub{margin-top:4px !important}
html body :is(.ui-dna-master,#ui-dna-master) .ui-table td[data-field=suppliers]>div+div{margin-top:2px}
html body :is(.ui-dna-master,#ui-dna-master) .ui-table .ui-link{padding:0 !important;border:0 !important;background:none !important;min-height:0 !important;height:auto !important;color:#14532D !important;font-weight:700 !important;text-decoration:underline;text-decoration-color:#BFD8C7;text-underline-offset:3px;box-shadow:none !important}
html body :is(.ui-dna-master,#ui-dna-master) .ui-table .ui-link:hover{text-decoration-color:#14532D}
html body :is(.ui-dna-master,#ui-dna-master) .ui-table td.ui-actions>form{display:inline-flex;align-items:center;margin:0}
html body :is(.ui-dna-master,#ui-dna-master) .ui-table td.ui-actions .mr-msg:empty{display:none}
html :is(body#ui-dna-strong,body) :is(.ui-dna-master,#ui-dna-master) .ui-table td.ui-actions .btn-action-archive:not(dialog *){color:#9B1C1C !important;border-color:#EBD3CF !important}
html :is(body#ui-dna-strong,body) :is(.ui-dna-master,#ui-dna-master) .ui-table td.ui-actions .btn-action-archive:not(dialog *):hover{border-color:#9B1C1C !important;background:#FDF1EF !important}
html :is(body#ui-dna-strong,body) :is(.ui-dna-master,#ui-dna-master) .ui-table td.ui-actions .btn-action-archive:disabled{opacity:.4;cursor:not-allowed}
/* popup tambah/ubah/impor master: tema popup Operasional (kepala hijau, judul lime, Batal kiri, simpan lime kanan) */
:is(dialog.uf-dialog,#u#u){padding:0 !important;border:0 !important;border-radius:14px !important;overflow:hidden;background:#fff !important;box-shadow:0 24px 60px -20px rgba(4,12,8,.45) !important;font-family:'Inter Kasir',-apple-system,'Segoe UI',sans-serif;color:#102A1D}
:is(dialog.uf-dialog[open],#u#u#u){position:fixed !important;inset:0 !important;margin:auto !important;height:fit-content !important;max-height:calc(100dvh - 32px) !important;display:flex !important;flex-direction:column;align-items:stretch !important}
:is(dialog.uf-dialog,#u#u)>*{align-self:stretch !important}
:is(dialog.uf-dialog,#u#u)::backdrop{background:rgba(9,30,18,.55) !important}
:is(dialog.uf-dialog,#u#u) .popup-heading{display:flex !important;align-items:flex-start !important;justify-content:space-between !important;gap:12px;margin:0 !important;padding:18px 20px 16px 24px !important;background:#14532D !important;border:0 !important;text-align:left !important}
:is(dialog.uf-dialog,#u#u) .popup-heading h2{margin:0 !important;font-family:'Bricolage Kasir','Inter Kasir',sans-serif !important;font-size:22px !important;line-height:28px !important;font-weight:700 !important;color:#C8F26B !important;letter-spacing:-.01em}
:is(dialog.uf-dialog,#u#u) .uf-dialog-sub{margin:2px 0 0 !important;font-size:13px !important;line-height:18px;color:#D6E8DC !important;font-weight:500}
:is(dialog.uf-dialog,#u#u) .popup-close{display:grid !important;place-items:center;flex:0 0 auto;width:36px !important;height:36px !important;min-width:36px !important;min-height:36px !important;padding:0 !important;border:0 !important;border-radius:8px !important;background:transparent !important;color:#fff !important;font-size:22px !important;line-height:1 !important;box-shadow:none !important}
:is(dialog.uf-dialog,#u#u) .popup-close:hover{background:rgba(255,255,255,.12) !important}
:is(dialog.uf-dialog,#u#u) .popup-content{flex:1 1 auto;min-height:0;overflow:auto;padding:20px 24px 0 !important;text-align:left !important}
:is(dialog.uf-dialog,#u#u) .uf-section-title{margin:0 0 10px !important;font-size:14px !important;font-weight:700 !important;color:#14532D !important}
:is(dialog.uf-dialog,#u#u) label{font-size:14px !important;font-weight:600 !important;color:#102A1D !important}
:is(dialog.uf-dialog,#u#u) :is(input:not([type=checkbox]):not([type=radio]):not([type=hidden]):not([type=file]):not([data-ui-number]),textarea,.select-shell>.select-trigger,.ui-pick-input){min-height:46px !important;height:auto !important;border:1px solid #DFE6E1 !important;border-radius:8px !important;background:#fff !important;font-size:15px !important;color:#102A1D !important;box-shadow:none !important}
:is(dialog.uf-dialog,#u#u) input[readonly]{background:#F4F6F5 !important;color:#5F7468 !important}
:is(dialog.uf-dialog,#u#u) :is(input,textarea,.select-trigger):focus{outline:none !important;border-color:#14532D !important;box-shadow:0 0 0 3px rgba(20,83,45,.16) !important}
:is(dialog.uf-dialog,#u#u) .uf-actions{position:sticky;bottom:0;z-index:2;display:flex !important;justify-content:space-between !important;align-items:center;gap:12px;margin:8px -24px 0 !important;padding:16px 24px 20px !important;border-top:1px solid #EEF2EF !important;background:#fff !important}
:is(dialog.uf-dialog,#u#u) .uf-actions>button{display:inline-flex !important;align-items:center;justify-content:center;min-height:46px !important;height:46px !important;padding:0 20px !important;border-radius:10px !important;font-size:15px !important;font-weight:700 !important;box-shadow:none !important}
:is(dialog.uf-dialog,#u#u) .uf-actions>button.secondary{min-width:120px;border:1px solid #D5DED8 !important;background:#fff !important;color:#14532D !important}
:is(dialog.uf-dialog,#u#u) .uf-actions>button.secondary:hover{border-color:#14532D !important;background:#F1F7F3 !important}
:is(dialog.uf-dialog,#u#u) .uf-actions>button:not(.secondary){min-width:200px;border:0 !important;background:#C8F26B !important;color:#14532D !important}
:is(dialog.uf-dialog,#u#u) .uf-actions>button:not(.secondary):hover{background:#B8E557 !important}
:is(dialog.uf-dialog,#u#u) .uf-actions>button:not(.secondary):disabled{opacity:.5}
:is(dialog.uf-dialog,#u#u) :is(.err,[data-error]):not([hidden]){margin:12px 0 0;padding:10px 12px;border-radius:8px;background:#FDE7E5;color:#9B1C1C;font-size:14px}
/* form master: bagian bernomor (kartu abu muda bila lebih dari satu bagian), label + Opsional, petunjuk, angka berawalan Rp,
   pesan salah per kolom, pilihan cepat, penghitung karakter, kotak unggah berkas */
:is(dialog.uf-dialog,#u#u){width:min(620px,calc(100vw - 32px)) !important}
:is(dialog.uf-dialog-wide,#u#u#u){width:min(820px,calc(100vw - 32px)) !important}
:is(dialog.uf-dialog,#u#u) .popup-heading>div{min-width:0}
:is(dialog.uf-dialog,#u#u) .uf-form{gap:16px !important}
:is(dialog.uf-dialog,#u#u) .uf-section>h3.uf-section-title{margin:0 0 12px !important}
:is(dialog.uf-dialog,#u#u) form:has(.uf-section+.uf-section) .uf-section+.uf-section{margin-top:14px !important}
:is(dialog.uf-dialog,#u#u) form:has(.uf-section+.uf-section) .uf-section{padding:16px 16px 18px !important;border:1px solid #E4EBE6 !important;border-radius:12px;background:#F7F9F8}
:is(dialog.uf-dialog,#u#u) .uf-section+.uf-section{border-top:1px solid #E4EBE6 !important}
:is(dialog.uf-dialog,#u#u) .uf-section-head{display:flex;align-items:flex-start;gap:10px;margin:0 0 14px}
:is(dialog.uf-dialog,#u#u) .uf-step{display:inline-grid;place-items:center;flex:none;width:26px;height:26px;border-radius:999px;background:#14532D;color:#C8F26B;font-size:13px;font-weight:700}
:is(dialog.uf-dialog,#u#u) .uf-section-title{margin:0 !important;font-size:16px !important;line-height:22px;font-weight:700 !important;color:#102A1D !important}
:is(dialog.uf-dialog,#u#u) .uf-section-note{margin:1px 0 0 !important;font-size:13px;line-height:18px;color:#5F7468}
:is(dialog.uf-dialog,#u#u) [data-product-suppliers] .uf-group>legend{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)}
:is(dialog.uf-dialog,#u#u) .uf-grid{gap:16px 14px !important}
:is(dialog.uf-dialog,#u#u) .uf{gap:0 !important}
:is(dialog.uf-dialog,#u#u) .uf-label{display:flex !important;align-items:center;gap:6px;min-height:22px;margin:0 0 6px !important;line-height:20px !important}
:is(dialog.uf-dialog,#u#u) .uf-req{color:#B42318 !important;margin-left:0 !important}
:is(dialog.uf-dialog,#u#u) .uf-opt{line-height:18px;padding:0 8px;border-radius:999px;background:#EEF2EF;font-size:11.5px;font-weight:600;color:#5F7468}
:is(dialog.uf-dialog,#u#u) .uf-hint{margin:6px 0 0 !important;font-size:12.5px !important;line-height:17px;color:#5F7468 !important}
:is(dialog.uf-dialog,#u#u) .uf-hint-top{margin:-2px 0 8px !important}
:is(dialog.uf-dialog,#u#u) input.uf-code{font-family:'Inter Kasir',sans-serif !important;font-variant-numeric:tabular-nums;font-weight:600;letter-spacing:.02em;color:#14532D !important}
:is(dialog.uf-dialog,#u#u) :is(input,textarea)::placeholder{color:#8A9A90 !important;font-weight:400}
:is(dialog.uf-dialog,#u#u) textarea{min-height:72px !important;padding:10px 12px !important;line-height:1.45}
:is(dialog.uf-dialog,#u#u) .uf-affix{height:46px !important;border:1px solid #DFE6E1 !important;border-radius:8px !important;background:#fff !important;overflow:hidden}
:is(dialog.uf-dialog,#u#u) .uf-affix:hover{border-color:#B5C8BC !important}
:is(dialog.uf-dialog,#u#u) .uf-affix:focus-within{border-color:#14532D !important;box-shadow:0 0 0 3px rgba(20,83,45,.16) !important}
:is(dialog.uf-dialog,#u#u) .uf-affix input[data-ui-number]{flex:1 1 auto;min-width:0;height:44px !important;min-height:44px !important;margin:0 !important;padding:0 12px !important;border:0 !important;border-radius:0 !important;box-shadow:none !important;background:transparent !important;font-size:15px !important;font-weight:600;color:#102A1D !important;text-align:right}
:is(dialog.uf-dialog,#u#u) .uf-affix input[data-ui-number][readonly]{background:#F4F6F5 !important;color:#5F7468 !important}
:is(dialog.uf-dialog,#u#u) :is(.uf-pre,.uf-suf){display:flex;align-items:center;padding:0 12px !important;background:#F4F6F5 !important;color:#5F7468 !important;font-size:14px !important;font-weight:600}
:is(dialog.uf-dialog,#u#u) .uf-margin{display:inline-flex;align-self:flex-start;margin-top:8px;padding:3px 10px;border-radius:999px;background:#E4F0E8;color:#14532D !important;font-size:12.5px !important;font-weight:700 !important}
:is(dialog.uf-dialog,#u#u) .uf-margin[data-tone=loss]{background:#FDE7E5;color:#9B1C1C !important}
:is(dialog.uf-dialog,#u#u) .uf-margin:empty{display:none}
:is(dialog.uf-dialog,#u#u) .uf-lock{margin-top:6px;font-size:12.5px;font-weight:600;color:#7A4B00}
:is(dialog.uf-dialog,#u#u) .uf-seg{display:inline-grid !important;grid-auto-flow:column;grid-auto-columns:minmax(0,1fr);gap:4px !important;padding:4px !important;border-radius:10px !important;background:#EEF2EF !important}
:is(dialog.uf-dialog,#u#u) .uf-seg-item span{display:flex;align-items:center;justify-content:center;height:40px !important;padding:0 20px !important;border:0 !important;border-radius:7px !important;background:transparent !important;font-size:14px !important;font-weight:600 !important;color:#3C4A42 !important;box-shadow:none !important}
:is(dialog.uf-dialog,#u#u) .uf-seg-item input:checked+span{background:#fff !important;color:#14532D !important;font-weight:700 !important;box-shadow:0 1px 2px rgba(16,42,29,.14) !important}
:is(dialog.uf-dialog,#u#u) .uf-seg-item input:focus-visible+span{outline:2px solid #14532D !important;outline-offset:1px}
:is(dialog.uf-dialog,#u#u) .uf-pick{min-height:36px !important;border:1px solid #DFE6E1 !important;background:#fff !important;font-size:14px !important}
:is(dialog.uf-dialog,#u#u) .uf-pick:first-child{border-color:#BFD8C7 !important;background:#E4F0E8 !important}
:is(dialog.uf-dialog,#u#u) .uf-pick-main{background:#14532D !important;color:#C8F26B !important;font-size:11.5px !important}
:is(dialog.uf-dialog,#u#u) .uf-pick-x{min-height:26px !important;height:26px;width:26px;padding:0 !important;border:0 !important;background:transparent !important;color:#5F7468 !important}
:is(dialog.uf-dialog,#u#u) .uf-none{margin:0 !important;padding:10px 12px;border:1px dashed #CFDAD3;border-radius:8px;background:#fff;font-size:13px !important;color:#5F7468 !important}
:is(dialog.uf-dialog,#u#u) .uf-multi{gap:10px !important}
:is(dialog.uf-dialog,#u#u) .uf-quick{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
:is(dialog.uf-dialog,#u#u) .uf-quick>button{display:inline-flex !important;align-items:center;min-height:34px !important;height:34px;padding:0 14px !important;border:1px solid #DFE6E1 !important;border-radius:999px !important;background:#fff !important;color:#3C4A42 !important;font-size:13px !important;font-weight:600 !important;box-shadow:none !important}
:is(dialog.uf-dialog,#u#u) .uf-quick>button:hover{border-color:#9FC2AC !important;color:#14532D !important}
:is(dialog.uf-dialog,#u#u) .uf-quick>button[aria-pressed=true]{border-color:#14532D !important;background:#14532D !important;color:#fff !important}
:is(dialog.uf-dialog,#u#u) .uf-count{align-self:flex-end;margin-top:4px;font-size:12px;color:#8A9A90;font-variant-numeric:tabular-nums}
:is(dialog.uf-dialog,#u#u) .uf-count[data-near=true]{color:#B45309;font-weight:600}
:is(dialog.uf-dialog,#u#u) .uf[data-invalid] .uf-label{color:#9B1C1C !important}
:is(dialog.uf-dialog,#u#u) .uf[data-invalid] :is(input:not([type=hidden]):not([data-ui-number]):not([type=file]),textarea,.select-trigger,.uf-affix,.ui-pick-input,.uf-drop){border-color:#D92D20 !important;box-shadow:0 0 0 3px rgba(217,45,32,.12) !important}
:is(dialog.uf-dialog,#u#u) .uf-error{display:flex;align-items:center;gap:6px;margin:6px 0 0 !important;font-size:13px;font-weight:600;color:#B42318;animation:ui-row-in .2s var(--ui-ease-out)}
:is(dialog.uf-dialog,#u#u) .uf-error::before{content:'!';display:inline-grid;place-items:center;flex:none;width:16px;height:16px;border-radius:999px;background:#D92D20;color:#fff;font-size:11px;font-weight:800}
:is(dialog.uf-dialog,#u#u) .uf-steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin:0 0 16px !important;padding:0 !important;list-style:none;counter-reset:uf-step}
:is(dialog.uf-dialog,#u#u) .uf-steps>li{counter-increment:uf-step;display:flex;align-items:flex-start;gap:8px;margin:0;padding:10px 12px;border:1px solid #E4EBE6;border-radius:10px;background:#F7F9F8;font-size:13px;line-height:18px;color:#3C4A42}
:is(dialog.uf-dialog,#u#u) .uf-steps>li::before{content:counter(uf-step);display:inline-grid;place-items:center;flex:none;width:22px;height:22px;border-radius:999px;background:#14532D;color:#C8F26B;font-size:12px;font-weight:700}
:is(dialog.uf-dialog,#u#u) .uf-steps a{color:#14532D !important;font-weight:700;text-decoration:underline;text-underline-offset:3px}
:is(dialog.uf-dialog,#u#u) .uf-drop{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;min-height:150px;padding:20px;border:2px dashed #BFD8C7;border-radius:12px;background:#F7FBF8;text-align:center;cursor:pointer;transition:border-color .16s,background-color .16s}
:is(dialog.uf-dialog,#u#u) .uf-drop:is(:hover,.is-over){border-color:#14532D;background:#EEF7F1}
:is(dialog.uf-dialog,#u#u) .uf-drop:focus-within{outline:2px solid #14532D;outline-offset:2px}
:is(dialog.uf-dialog,#u#u) .uf-drop.has-file{border-style:solid;border-color:#14532D;background:#E4F0E8}
:is(dialog.uf-dialog,#u#u) .uf-drop input[type=file]{position:absolute;inset:0;width:100%;height:100%;margin:0;opacity:0;cursor:pointer}
:is(dialog.uf-dialog,#u#u) .uf-drop-icon{display:grid;place-items:center;width:44px;height:44px;margin-bottom:8px;border-radius:12px;background:#14532D;color:#C8F26B}
:is(dialog.uf-dialog,#u#u) .uf-drop-title{font-size:15px;font-weight:700;color:#102A1D}
:is(dialog.uf-dialog,#u#u) .uf-drop-sub{font-size:13px;color:#5F7468}
:is(dialog.uf-dialog,#u#u) .uf-drop-file{display:none;margin-top:8px;padding:4px 12px;border:1px solid #BFD8C7;border-radius:999px;background:#fff;font-size:13px;font-weight:700;color:#14532D;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
:is(dialog.uf-dialog,#u#u) .uf-drop.has-file .uf-drop-file{display:inline-block}
:is(dialog.uf-dialog,#u#u) .import-preview-status:empty{display:none}
@media (max-width:760px){
  :is(dialog.uf-dialog,#u#u) form:has(.uf-section+.uf-section) .uf-section{padding:14px 12px 16px !important}
  :is(dialog.uf-dialog,#u#u) .uf-steps{grid-template-columns:1fr}
  :is(dialog.uf-dialog,#u#u) .uf-seg{display:grid !important;width:100%}
  :is(dialog.uf-dialog,#u#u) .uf-drop{min-height:120px}
}
html body .ui-pill--bad{background:#FDE7E5;border-color:#F3B7B1;color:#9B1C1C}
@media (max-width:760px){
  html body :is(.ui-dna-master,#ui-dna-master) .ui-toolbar-actions{width:100%;margin-left:0}
  html body :is(.ui-dna-master,#ui-dna-master) .ui-toolbar-actions>:is(button,a){flex:1 1 auto}
  html body :is(.ui-dna-master,#ui-dna-master) .ui-table td[data-field=stock_quantity]{flex-wrap:wrap;justify-content:space-between !important}
  html body :is(.ui-dna-master,#ui-dna-master) .ui-table td[data-field=stock_quantity]>.ui-sub{flex:0 0 100%;text-align:right}
  html body :is(.ui-dna-master,#ui-dna-master) .ui-table tbody td.ui-actions{display:grid !important;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
  html body :is(.ui-dna-master,#ui-dna-master) .ui-table td.ui-actions>:is(form,.btn-action-edit){width:100%;min-width:0 !important;margin-left:0 !important}
  html :is(body#ui-dna-strong,body) :is(.ui-dna-master,#ui-dna-master) .ui-table td.ui-actions :is(.btn-action-edit,form>button):not(dialog *){width:100%;height:44px !important;min-height:44px !important}
  html body :is(.ui-dna-master,#ui-dna-master) .ui-table td.ui-actions>form>button{width:100%}
  :is(dialog.uf-dialog[open],#u#u#u){width:calc(100vw - 16px) !important;max-width:none !important;max-height:calc(100dvh - 16px) !important}
  :is(dialog.uf-dialog,#u#u) .popup-heading{padding:14px 12px 14px 18px !important}
  :is(dialog.uf-dialog,#u#u) .popup-heading h2{font-size:20px !important;line-height:26px !important}
  :is(dialog.uf-dialog,#u#u) .popup-content{padding:16px 18px 0 !important}
  :is(dialog.uf-dialog,#u#u) .uf-actions{margin:8px -18px 0 !important;padding:12px 18px 16px !important}
  :is(dialog.uf-dialog,#u#u) .uf-actions>button.secondary{min-width:0;flex:0 0 auto}
  :is(dialog.uf-dialog,#u#u) .uf-actions>button:not(.secondary){min-width:0;flex:1 1 auto}
}

/* ---------- 22. Keuangan: lihat v2/finance-dna.blade.php (dimuat bersama isi halaman Keuangan agar HTML halaman lain tidak membengkak). */
/* ---------- 21. Sistem gerak bersama (Kasir, Operasional, Master) ----------
   Halus tapi terasa, 180-320 ms: kartu panel muncul bertahap saat pindah menu, baris tabel muncul bertahap saat
   halaman/saringan berganti, popup naik dan memudar (bagian 16), tombol menekan, baris yang baru disimpan disorot.
   Dipicu ui-motion.blade.php. Hanya opacity/transform/scale/translate; animation-fill-mode backwards agar tidak ada
   transform yang tertinggal setelah selesai (posisi fixed/sticky di dalam panel tetap aman). */
@keyframes ui-rise{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
@keyframes ui-row-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
@keyframes ui-saved{0%,30%{background-color:#E3F5C2}}
@keyframes ui-saved-bar{0%,60%{box-shadow:inset 4px 0 0 #14532D}}
html body .ui-enter .ui-enter-part{animation:ui-rise .32s var(--ui-ease-out) backwards;animation-delay:calc(var(--ui-i,0) * 70ms)}
html body .ui-enter .ui-enter-tile{animation:ui-rise .3s var(--ui-ease-out) backwards;animation-delay:calc(100ms + var(--ui-t,0) * 50ms)}
html body tr.ui-row-in>td{animation:ui-row-in .26s var(--ui-ease-out) backwards;animation-delay:calc(var(--ui-r0,0ms) + var(--ui-r,0) * 26ms)}
html body .ui-row-in:not(tr){animation:ui-row-in .26s var(--ui-ease-out) backwards;animation-delay:calc(var(--ui-r0,0ms) + var(--ui-r,0) * 26ms)}
html body tr.ui-row-saved>td{animation:ui-saved 2.2s ease-out}
html body tr.ui-row-saved>td:first-child{animation:ui-saved 2.2s ease-out,ui-saved-bar 2.2s ease-out}
html body :is(main,dialog) :is(button,a.btn,.btn,.btn-template-download,.ui-subtab,.btn-action-pill):not(:disabled){transition:scale .12s var(--ui-ease-out),translate .18s var(--ui-ease-out),background-color .16s ease,border-color .16s ease,color .16s ease,box-shadow .16s ease}
html body :is(main,dialog) :is(button,a.btn,.btn,.btn-template-download,.ui-subtab,.btn-action-pill):not(:disabled):not(.ui-tile):active{scale:.96}
html body main button.ui-tile:not(:disabled):hover{translate:0 -2px}
html body main button.ui-tile:not(:disabled):active{scale:.985;translate:0}
@media (prefers-reduced-motion:reduce){
  html body .ui-enter :is(.ui-enter-part,.ui-enter-tile),html body tr:is(.ui-row-in,.ui-row-saved)>td,html body .ui-row-in,html body dialog[open]::backdrop,html body #modal-coa .modal-dialog{animation:none !important}
  html body tr.ui-row-saved>td{background-color:#EEF8DD !important}
  html body dialog[open]{animation:none !important}
  html body :is(main,dialog) :is(button,a.btn,.btn,.btn-template-download,.ui-subtab,.btn-action-pill){transition:none !important;scale:none !important;translate:none !important}
}
/* 23. Harga kontraktor di Kasir: tombol Eceran/Kontraktor (keranjang hijau tua + Pembayaran putih), penanda harga. */
:is(#kasir,#k#k) .pos-tier{display:flex;align-items:center;gap:4px;margin:10px 0 0;padding:4px;border-radius:10px;background:rgba(255,255,255,.1)}
:is(#kasir,#k#k) .pos-tier-label{flex:1;padding:0 8px;font-size:13px;font-weight:600;color:#D6E8DC}
html body :is(#kasir,#k#k) .pos-tier button{min-height:40px !important;height:40px;margin:0 !important;padding:0 14px !important;border:0 !important;border-radius:7px !important;background:transparent !important;color:#E4F0E8 !important;font-size:14px !important;font-weight:600 !important;box-shadow:none !important}
html body :is(#kasir,#k#k) .pos-tier button[aria-checked=true]{background:#C8F26B !important;color:#102A1D !important;font-weight:800 !important}
html body :is(#kasir,#k#k) .pos-tier button:focus-visible{outline:2px solid #C8F26B !important;outline-offset:1px}
:is(#kasir,#k#k) .pos-tier--light{margin:10px 0 14px;background:#EEF2EF}
:is(#kasir,#k#k) .pos-tier--light .pos-tier-label{color:#3C4A42}
html body :is(#kasir,#k#k) .pos-tier--light button{color:#3C4A42 !important}
html body :is(#kasir,#k#k) .pos-tier--light button[aria-checked=true]{background:#14532D !important;color:#fff !important}
html body :is(#kasir,#k#k) .pos-tier--light button:focus-visible{outline-color:#14532D !important}
:is(#kasir,#k#k) .cart-unit-price.is-contractor{padding:0 5px;border-radius:5px;background:#EEF8DD;color:#14532D}
:is(#kasir,#k#k) td.money.is-contractor{color:#14532D;font-weight:800}
:is(#kasir,#k#k) td.money.is-contractor s{display:block;font-size:12px;font-weight:500;color:#7C8C83}
/* 24. Kasir di tablet (761-1100px): ubin ringkasan tidak meluap, kepala dashboard rapi.
   1000-1100px (tablet mendatar): katalog + keranjang berdampingan, keranjang menempel saat digulir.
   761-999px: keranjang lewat bar + laci (pos-mobile); di bawah 1000px kolom nama barang terlalu sempit untuk dua kolom. */
@media (min-width:761px) and (max-width:1100px){
  html body #panel-kasir #kasir .pd-kpi{height:auto;min-height:84px;padding:10px 14px}
  html body #panel-kasir #kasir .pd-kpi .pd-kpi-value{font-size:20px;line-height:26px;white-space:nowrap}
  html body #panel-kasir #kasir .pd-kpi.pd-kpi--lead .pd-kpi-value{font-size:22px;line-height:28px}
  html body #panel-kasir #kasir .pd-kpi--stock .pd-kpi-link{position:static;order:9;align-self:flex-start;margin-top:4px}
  /* Katalog: lebar kolom tetap untuk angka dan aksi, sisa lebar untuk nama barang (keterangan dipotong satu baris). */
  html body #panel-kasir #kasir #products-table{table-layout:fixed}
  html body #panel-kasir #kasir #products-table :is(th,td){padding-left:8px;padding-right:8px}
  html body #panel-kasir #kasir #products-table th:nth-child(2){width:96px}
  html body #panel-kasir #kasir #products-table th:nth-child(3){width:108px}
  html body #panel-kasir #kasir #products-table th:nth-child(4){width:var(--kasir-act,84px)}
  html body #panel-kasir #kasir #products-tbody td:not(:first-child){white-space:nowrap}
  html body #panel-kasir #kasir #products-tbody td.ui-primary .ui-sub{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  html body #panel-kasir #kasir #products-tbody .btn-table-action{padding:0 10px !important}
}
@media (min-width:761px) and (max-width:900px){
  html body #panel-kasir #kasir .pd-head{flex-wrap:wrap;row-gap:12px;padding-bottom:14px}
  html body #panel-kasir #kasir .pd-head>.pd-controls{flex:1 1 100%;justify-content:space-between}
  html body #panel-kasir #kasir .pd-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
}
/* kolom Aksi cukup untuk stepper − jumlah + (stepper hanya tampil saat keranjang berupa laci) */
@media (min-width:761px) and (max-width:999px){html body #panel-kasir #kasir #products-table{--kasir-act:136px}}
@media (min-width:1000px) and (max-width:1100px){
  html body #panel-kasir #kasir .pos-workspace{grid-template-columns:minmax(0,1fr) 360px;gap:12px;align-items:start}
  html body #panel-kasir #kasir .pos-cart-pane .summary-total{flex-wrap:nowrap !important;gap:12px}
  html body #panel-kasir #kasir .pos-cart-pane .summary-total span{font-size:17px !important;white-space:nowrap}
  html body #panel-kasir #kasir .pos-cart-pane #display-total{font-size:22px !important;white-space:nowrap}
  html body #panel-kasir #kasir .pos-cart-pane{position:sticky;top:var(--kasir-sticky,80px);max-height:calc(100dvh - var(--kasir-sticky,80px) - 12px);overflow:auto;overscroll-behavior:contain;border-radius:12px}
  html body #panel-kasir #kasir .pos-catalog-head .pos-search-box{margin:0}
}
@media print{
  html body{background:#fff}
  html body .report-shell :is(.summary-item,.ledger-caption,.report-ledger){box-shadow:none}
}
</style>

