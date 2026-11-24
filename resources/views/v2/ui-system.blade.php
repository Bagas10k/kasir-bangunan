<style>
/* ============================================================
   SISTEM TAMPILAN V2 — token + komponen standar (dimuat TERAKHIR)
   Prinsip: teks >= 13px, isi tabel >= 15px, target sentuh 44px,
   satu aksen hijau, status = teks + penanda (bukan warna saja).
   Komponen berawalan ui- agar tidak bentrok dengan CSS lama.
   ============================================================ */
html{font-size:calc(100% * var(--ui-scale,1))}
body{font-size:1rem;line-height:1.5}
body :where(a,button,input,select,textarea,summary,[tabindex]):focus-visible{outline:3px solid var(--ui-focus);outline-offset:2px}

/* ---------- panel: satu kolom bertumpuk (Kasir/POS tetap dengan tata letaknya sendiri) ---------- */
body .task-panel:not(#panel-kasir){display:flex;flex-direction:column;gap:16px;align-items:stretch;max-width:none}
body .task-panel:not(#panel-kasir)>:not(dialog){margin:0;min-width:0;width:100%}
/* Dialog dikecualikan dari aturan selimut di atas: showModal memusatkan lewat margin:auto, jadi margin:0 membuatnya menempel ke pojok kiri atas. */
body .task-panel:not(#panel-kasir)[hidden]{display:none!important}
body .task-panel:not(#panel-kasir)>.ui-monitor{margin:0}
.ui-subhead{font-size:1.0625rem;font-weight:700;margin:20px 0 10px;color:var(--ui-ink)}
.ui-neg{color:var(--ui-bad-ink);font-weight:650}

.ui-eyebrow{margin:0;font-size:var(--ui-t-label);font-weight:600;color:var(--ui-green)}
/* sel tabel: kata tidak dipotong di tengah (aturan lama memakai overflow-wrap:anywhere) */
body .ui-table :is(th,td){overflow-wrap:normal;word-break:normal}
body .ui-table td.ui-primary{min-width:12ch}
body .ui-pager-controls .select-shell>.select-trigger{font-size:var(--ui-t-label)!important;min-height:40px!important}

body .task-panel .sr-section{padding:0;margin:0;border:0;background:none;box-shadow:none}

body .ui-link{display:inline-flex;align-items:center;gap:6px;min-height:28px;padding:0;border:0;background:none;color:var(--ui-green);font:inherit;font-weight:650;text-align:left;cursor:pointer;text-decoration:underline;text-underline-offset:3px;text-decoration-thickness:1px;text-decoration-color:rgb(20 83 45 / .35)}
body .ui-link:hover{text-decoration-color:currentColor}
#insight-dialog .ui-monitor-grid{grid-template-columns:repeat(auto-fit,minmax(170px,1fr));margin-bottom:4px}
#insight-dialog .ui-subhead{margin:16px 0 8px}
#insight-dialog .ui-table-wrap{max-height:230px}

/* Halaman keuangan membawa judulnya sendiri; kepala workspace disembunyikan agar tidak dobel. */
body[data-remote-task] .ui-page-header{display:none}
body[data-remote-task] .task-panel{gap:0}
body .ui-status-line{padding:24px 4px;color:var(--ui-muted);font-size:var(--ui-t-body)}

/* Komboboks pelanggan di kasir: ketik untuk mencari, buat baru bila belum ada. */
body .pos-combo{position:relative;display:block}
body .pos-combo-native{display:none}
body .pos-combo-input{width:100%;min-height:var(--ui-tap);padding:9px 12px;border:1px solid var(--ui-line);border-radius:10px;background:#fff;font-size:var(--ui-t-body);font-family:inherit;color:var(--ui-ink)}
body .pos-combo-input:focus{outline:2px solid var(--ui-accent);outline-offset:1px;border-color:var(--ui-accent)}
body .pos-combo-list{position:absolute;z-index:40;inset-inline:0;top:calc(100% + 4px);margin:0;padding:4px;list-style:none;max-height:260px;overflow:auto;background:#fff;border:1px solid var(--ui-line);border-radius:12px;box-shadow:0 14px 32px rgb(15 42 29 / .16)}
body .pos-combo-item{display:flex;align-items:baseline;justify-content:space-between;gap:10px;min-height:var(--ui-tap);padding:8px 10px;border-radius:8px;cursor:pointer}
body .pos-combo-item:hover,body .pos-combo-item.is-active{background:var(--ui-accent-soft)}
body .pos-combo-name{font-size:var(--ui-t-body);font-weight:600;color:var(--ui-ink)}
body .pos-combo-hint{font-size:var(--ui-t-label);color:var(--ui-muted)}
body .pos-combo-create .pos-combo-name{color:var(--ui-green)}
body .pos-combo-general .pos-combo-name{font-weight:500}
body .pos-combo-note{margin:6px 0 0;font-size:var(--ui-t-label);color:var(--ui-muted)}

/* ---------- kepala halaman ---------- */
.ui-page-header{margin:0 0 18px}
.ui-page-header h1{font-size:var(--ui-h1);line-height:1.25;font-weight:700;letter-spacing:-.015em;margin:0;color:var(--ui-ink)}
.ui-page-header p{font-size:var(--ui-t-body);line-height:1.5;color:var(--ui-ink-2);margin:4px 0 0;max-width:70ch}

/* ---------- monitor ---------- */
.ui-monitor{margin:0 0 20px;min-width:0}
.ui-monitor-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px;margin:0;padding:0}
.ui-tile{position:relative;display:flex;flex-direction:column;gap:2px;margin:0;min-width:0;text-align:left;
  background:var(--ui-surface);border:1px solid var(--ui-line);border-radius:var(--ui-radius);padding:14px 16px 14px 20px;
  font:inherit;color:inherit;box-shadow:none}
.ui-tile::before{content:"";position:absolute;left:0;top:12px;bottom:12px;width:4px;border-radius:0 4px 4px 0;background:transparent}
.ui-tile--good::before{background:var(--ui-green)}
.ui-tile--attention::before{background:var(--ui-warn-bar)}
.ui-tile--problem::before{background:var(--ui-bad-bar)}
.ui-tile-label{font-size:var(--ui-t-table);font-weight:600;line-height:1.35;color:var(--ui-ink-2);margin:0}
.ui-tile-value{font-size:1.5rem;font-weight:700;line-height:1.25;font-variant-numeric:tabular-nums;color:var(--ui-ink);margin:0;overflow-wrap:anywhere}
.ui-tile-hint{font-size:var(--ui-t-caption);line-height:1.4;color:var(--ui-ink-2);margin:0}
button.ui-tile{cursor:pointer;width:100%;min-height:var(--ui-tap)}
button.ui-tile:hover{background:var(--ui-hover)}
button.ui-tile[aria-pressed=true]{border-color:var(--ui-green);background:var(--ui-green-soft);box-shadow:inset 0 0 0 1px var(--ui-green)}

/* ---------- toolbar, chip, pencarian ---------- */
.ui-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin:0 0 12px}
.ui-search{position:relative;flex:1 1 260px;max-width:420px;min-width:0}
.ui-search svg{position:absolute;left:12px;top:50%;width:18px;height:18px;transform:translateY(-50%);color:var(--ui-ink-2);pointer-events:none}
body .ui-search input{width:100%;min-height:var(--ui-tap);padding:0 14px 0 40px;border:1px solid var(--ui-line);border-radius:10px;
  background:#fff;font-size:var(--ui-t-body);color:var(--ui-ink)}
.ui-chips{display:flex;flex-wrap:wrap;gap:8px;margin:0;padding:0;border:0}
body .ui-chip{min-height:var(--ui-tap);padding:0 16px;border-radius:999px;border:1px solid var(--ui-line);background:#fff;
  font-size:var(--ui-t-label);font-weight:600;color:var(--ui-ink);cursor:pointer}
body .ui-chip:hover{background:var(--ui-hover)}
body .ui-chip[aria-pressed=true]{background:var(--ui-green);border-color:var(--ui-green);color:#fff}
.ui-toolbar-actions{margin-left:auto;display:flex;flex-wrap:wrap;gap:8px}
.ui-status-line{font-size:var(--ui-t-label);color:var(--ui-ink-2);margin:0 0 8px}

/* ---------- tabel ---------- */
.ui-table-wrap{border:1px solid var(--ui-line);border-radius:var(--ui-radius);background:#fff;overflow:auto;max-width:100%}
body table.ui-table{width:100%;border-collapse:separate;border-spacing:0;font-size:var(--ui-t-table);line-height:1.45;margin:0}
body table.ui-table caption{text-align:left;padding:0}
body table.ui-table th{background:var(--ui-head);color:var(--ui-ink);font-size:var(--ui-t-label);font-weight:650;line-height:1.35;
  text-align:left;padding:12px 16px;border-bottom:1px solid var(--ui-line);white-space:nowrap;vertical-align:middle;text-transform:none;letter-spacing:0}
body table.ui-table td{font-size:var(--ui-t-table);line-height:1.45;padding:10px 16px;height:var(--ui-row);border-bottom:1px solid var(--ui-line-soft);vertical-align:middle;
  color:var(--ui-ink);font-variant-numeric:tabular-nums;white-space:normal}
body table.ui-table tbody tr:last-child td{border-bottom:0}
body table.ui-table tbody tr:hover td{background:var(--ui-hover)}
body table.ui-table .ui-num{text-align:right;white-space:nowrap}
body table.ui-table .ui-primary{font-weight:650}
body table.ui-table .ui-nowrap{white-space:nowrap}
body table.ui-table td.ui-actions,body table.ui-table th.ui-actions{text-align:right;white-space:nowrap}
body table.ui-table td.ui-actions>:not(dialog){vertical-align:middle;margin:0 0 0 8px}
body table.ui-table td.ui-actions form{display:inline-block}
.ui-muted{color:var(--ui-ink-2)}
.ui-sub{display:block;font-size:var(--ui-t-caption);color:var(--ui-ink-2);font-weight:400}

/* ---------- laporan: label kiri, angka kanan ---------- */
.ui-report-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin-top:8px}
.ui-report-card{border:1px solid var(--ui-line);border-radius:var(--ui-radius);padding:16px 18px;background:#fff;min-width:0}
.ui-report-card h3{font-size:1.0625rem;font-weight:700;margin:0 0 6px;color:var(--ui-ink)}
.ui-kv{margin:0;padding:0}
.ui-kv>div{display:flex;justify-content:space-between;align-items:baseline;gap:16px;padding:10px 0;border-bottom:1px solid var(--ui-line-soft)}
.ui-kv>div:last-child{border-bottom:0}
.ui-kv dt{font-size:var(--ui-t-table);color:var(--ui-ink-2);margin:0}
.ui-kv dd{margin:0;font-size:var(--ui-t-table);font-weight:600;font-variant-numeric:tabular-nums;text-align:right;color:var(--ui-ink)}
.ui-kv .is-total dt{color:var(--ui-ink);font-weight:650}
.ui-kv .is-total dd{font-size:1.125rem;font-weight:700}

/* ---------- Kasir/POS: ukuran teks minimum (tata letak POS tidak diubah) ---------- */
body .main-content :is(.sku-code,.stock-badge,.pos-buyer-badge,.search-kbd-hint,.page-btn,.pagination-meta,.dash-label,.dash-sub,.dash-item-name,.dash-item-num,.sales-status,.sales-period-btn,.doc-links a,.cart-product-sku,.field-hint){font-size:var(--ui-t-label)}
body .main-content #sales-chart text,body .main-content .sales-chart text,body .main-content svg text{font-size:13px}
body .main-content .doc-links{display:inline-flex;flex-wrap:wrap;gap:8px;justify-content:flex-end}
body .main-content .doc-links a{display:inline-flex;align-items:center;min-height:var(--ui-tap);padding:0 14px;border:1px solid var(--ui-green);border-radius:10px;color:var(--ui-green);font-weight:600;text-decoration:none;background:#fff}
body .main-content .doc-links a:hover{background:var(--ui-green-soft)}

/* Selektor ID milik POS lebih kuat dari kelas: ditimpa dengan awalan #panel-kasir (susunan POS tidak berubah). */
body #panel-kasir :is(#dash-toggle-best,#dash-toggle-low,.dash-list li span,.search-kbd-hint,#product-search-status,.catalog-filter-row label,#catalog-reset,.sku-code,.stock-badge,.pos-buyer-badge,#pos-buyer-hint,.cart-label strong,#cart-item-count,#cart-empty-row,.filter-disclosure summary,.btn-table-action,.dash-label,.dash-sub){font-size:var(--ui-t-label)}
body #panel-kasir :is(.dash-label,.pos-buyer-badge,.cart-label strong,th){text-transform:none;letter-spacing:0}
body #panel-kasir :is(#products-table,#history-table) th{font-size:var(--ui-t-label);padding:12px 16px}
body #panel-kasir :is(#products-table,#history-table) td{font-size:var(--ui-t-table);padding:10px 16px}
body #panel-kasir .btn-table-action{min-height:var(--ui-tap);padding:0 16px;border-radius:10px}
body #panel-kasir #products-table :is(th,td){padding:10px 12px}
body #panel-kasir #products-table td.ui-nowrap{font-size:var(--ui-t-label)}
body #panel-kasir #products-table .sku-code{display:inline-block;white-space:nowrap;font-size:var(--ui-t-label)}
body #panel-kasir #products-table td.money{font-size:var(--ui-t-table);white-space:nowrap}
body #panel-kasir #products-table .stock-badge{white-space:nowrap}

/* ---------- tombol ---------- */
body .ui-btn,body .main-content .popup-action,body .main-content .btn-add-master,body .main-content .btn-import-master,body .main-content .btn-template-download,body .main-content .btn-action-pill{
  display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:var(--ui-tap);padding:0 16px;border-radius:10px;
  border:1px solid var(--ui-green);background:var(--ui-green);color:#fff;font-size:var(--ui-t-table);font-weight:600;line-height:1.2;
  text-decoration:none;cursor:pointer;box-shadow:none;white-space:nowrap}
body .ui-btn:hover,body .main-content .popup-action:hover,body .main-content .btn-add-master:hover{background:var(--ui-green-2);border-color:var(--ui-green-2)}
body .ui-btn--quiet,body .main-content .btn-import-master,body .main-content .btn-template-download,body .main-content .btn-action-pill{background:#fff;color:var(--ui-green)}
body .ui-btn--quiet:hover,body .main-content .btn-import-master:hover,body .main-content .btn-template-download:hover,body .main-content .btn-action-pill:hover{background:var(--ui-green-soft);color:var(--ui-green)}
body .main-content .btn-action-archive{color:var(--ui-bad-ink);border-color:var(--ui-line)}
body .main-content .btn-action-archive:hover{background:var(--ui-bad-bg);color:var(--ui-bad-ink)}
body .main-content .btn-action-pill:disabled{opacity:.5;cursor:not-allowed}

/* ---------- badge status ---------- */
.ui-badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:3px 10px;font-size:var(--ui-t-label);
  font-weight:600;line-height:1.4;white-space:nowrap;background:var(--ui-mute-bg);color:var(--ui-mute-ink)}
.ui-badge::before{content:"";flex:0 0 auto;width:8px;height:8px;border-radius:50%;background:currentColor}
.ui-badge--ok{background:var(--ui-green-soft);color:var(--ui-green)}
.ui-badge--warn{background:var(--ui-warn-bg);color:var(--ui-warn-ink)}
.ui-badge--bad{background:var(--ui-bad-bg);color:var(--ui-bad-ink)}
.ui-badge--info{background:var(--ui-info-bg);color:var(--ui-info-ink)}

/* ---------- kosong + halaman ---------- */
.ui-empty{padding:28px 20px;text-align:center;color:var(--ui-ink-2);font-size:var(--ui-t-body)}
.ui-empty strong{display:block;font-size:1.0625rem;color:var(--ui-ink);margin-bottom:4px}
.ui-pager{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;font-size:var(--ui-t-label);color:var(--ui-ink-2)}
.ui-pager-controls{display:flex;flex-wrap:wrap;align-items:center;gap:8px}
body .ui-pager button{min-height:var(--ui-tap);min-width:var(--ui-tap);padding:0 14px;border-radius:10px;border:1px solid var(--ui-line);background:#fff;
  font-size:var(--ui-t-label);font-weight:600;color:var(--ui-ink);cursor:pointer}
body .ui-pager button:disabled{opacity:.5;cursor:not-allowed}
body .ui-pager select{min-height:var(--ui-tap);border:1px solid var(--ui-line);border-radius:10px;padding:0 10px;font-size:var(--ui-t-label);background:#fff}

/* ---------- judul kartu seragam ---------- */
body .main-content .card>h2,body .main-content .card-title{font-size:var(--ui-h2);line-height:1.3;font-weight:700;margin:0 0 6px;padding:0;border:0;letter-spacing:-.01em}
body .main-content .card>.note,body .main-content .card-lead{font-size:var(--ui-t-label);line-height:1.5;color:var(--ui-ink-2);margin:0 0 14px;max-width:75ch}

/* ---------- tabel menjadi kartu di layar sempit ---------- */
body table.ui-table tr[hidden],body table.ui-table tr[data-ui-empty][hidden]{display:none!important}
@media (max-width:639px){
  .ui-table-wrap{border:0;background:transparent;overflow:visible}
  body table.ui-table,body table.ui-table tbody,body table.ui-table tr,body table.ui-table td{display:block;width:100%}
  body table.ui-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
  body table.ui-table tbody tr{background:#fff;border:1px solid var(--ui-line);border-radius:var(--ui-radius);padding:10px 14px;margin:0 0 10px}
  body table.ui-table td{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;height:auto;min-height:0;padding:7px 0;border-bottom:0;text-align:right}
  body table.ui-table td::before{content:attr(data-label);flex:0 0 38%;max-width:42%;text-align:left;font-size:var(--ui-t-label);font-weight:600;color:var(--ui-ink-2)}
  body table.ui-table td.ui-primary{display:block;text-align:left;font-size:1.0625rem;padding:2px 0 8px;margin-bottom:4px;border-bottom:1px solid var(--ui-line-soft)}
  body table.ui-table td.ui-primary::before{display:none}
  body table.ui-table td.ui-actions{display:flex;flex-wrap:wrap;justify-content:flex-start;gap:8px;padding-top:10px;margin-top:4px;border-top:1px solid var(--ui-line-soft)}
  body table.ui-table td.ui-actions::before{display:none}
  body table.ui-table td.ui-actions>:not(dialog){margin:0}
  .ui-monitor-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
  .ui-tile{padding:10px 12px 10px 16px}
  .ui-tile-value{font-size:1.25rem}
  .ui-search{max-width:none;flex-basis:100%}
}
@media (prefers-reduced-motion:reduce){.ui-tile,.ui-chip{transition:none}}
</style>
