<style>
/* ============================================================
   TEMA HALAMAN KASIR (POS) — patokan untuk halaman lain.
   Dimuat sesudah v2/ui-minimal. Memakai token peran warna yang sama
   (lihat docs/rencana-penyeragaman-ui-v2.md, bagian 1.1).
   Aturan POS lama berselektor dua ID, jadi di sini dipakai !important
   HANYA untuk warna dan huruf, tidak pernah untuk tata letak.
   ============================================================ */

/* ---------- A. dashboard di kepala halaman ---------- */
body #panel-kasir #kasir .pd{min-width:0;margin:0 0 12px;background:#fff;border:1px solid var(--ui-line);border-radius:14px;overflow:hidden}
body #panel-kasir #kasir .pd-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:10px 16px;border-bottom:1px solid var(--ui-line-soft)}
body #panel-kasir #kasir .pd.is-folded .pd-head{border-bottom:0}
body #panel-kasir #kasir .pd-heading h2{margin:0;padding:0;border:0;font-size:1rem;font-weight:700;color:var(--ui-ink)}
body #panel-kasir #kasir .pd-heading h2::after{display:none}
body #panel-kasir #kasir .pd-sub{margin:1px 0 0;font-size:.8125rem;color:var(--ui-ink-3)}
body #panel-kasir #kasir .pd-controls{display:flex;align-items:center;gap:8px}
body #panel-kasir #kasir .pd-seg{display:inline-flex;gap:2px;padding:3px;background:var(--ui-head);border:1px solid var(--ui-line);border-radius:10px}
body #panel-kasir #kasir .pd-seg button{min-height:30px;padding:0 13px;border:0;border-radius:7px;background:transparent;color:var(--ui-ink-2);font-size:.875rem;font-weight:600;cursor:pointer}
body #panel-kasir #kasir .pd-seg button:hover{background:#fff;color:var(--ui-ink)}
body #panel-kasir #kasir .pd-seg button[aria-pressed=true]{background:var(--ui-green);color:#fff;box-shadow:0 1px 2px rgb(15 42 29 / .25)}
body #panel-kasir #kasir .pd-fold{min-height:32px;padding:0 12px;border:1px solid var(--ui-line);border-radius:8px;background:#fff;color:var(--ui-ink-2);font-size:.875rem;font-weight:600;cursor:pointer}
body #panel-kasir #kasir .pd-fold:hover{border-color:var(--ui-green);color:var(--ui-green)}

body #panel-kasir #kasir .pd-pane{display:grid;grid-template-columns:minmax(0,1fr) minmax(240px,.42fr);min-width:0}
body #panel-kasir #kasir .pd-pane[hidden],body #panel-kasir #kasir .pd-body[hidden]{display:none}
body #panel-kasir #kasir .pd-main{min-width:0;padding:12px 16px}
body #panel-kasir #kasir .pd-side{min-width:0;padding:12px 16px;border-left:1px solid var(--ui-line-soft);background:#fbfdfc}
body #panel-kasir #kasir .pd-side h3{margin:0 0 4px;font-size:.9375rem;font-weight:700;color:var(--ui-ink)}

/* angka kunci: empat kolom dipisah garis tipis, bukan empat kotak */
body #panel-kasir #kasir .pd-kpis{grid-column:1/-1;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));min-width:0;padding:12px 16px;border-bottom:1px solid var(--ui-line-soft)}
body #panel-kasir #kasir .pd-kpi{display:flex;flex-direction:column;gap:2px;min-width:0;padding:0 14px;border-left:1px solid var(--ui-line-soft)}
body #panel-kasir #kasir .pd-kpi:first-child{padding-left:0;border-left:0}
body #panel-kasir #kasir .pd-kpi-label{font-size:.8125rem;font-weight:600;color:var(--ui-ink-3)}
body #panel-kasir #kasir .pd-kpi-value{font-size:1.375rem;line-height:1.2;font-weight:700;color:var(--ui-ink);font-variant-numeric:tabular-nums;overflow-wrap:anywhere}
body #panel-kasir #kasir .pd-kpi--lead .pd-kpi-value{font-size:1.375rem;color:var(--ui-green)}
body #panel-kasir #kasir .pd-kpi-note{font-size:.8125rem;color:var(--ui-ink-3);line-height:1.3}
body #panel-kasir #kasir .pd-kpi--stock.is-warn .pd-kpi-value{color:var(--ui-warn-ink)}
body #panel-kasir #kasir .pd-kpi--stock.is-bad .pd-kpi-value{color:var(--ui-bad-ink)}
body #panel-kasir #kasir .pd-delta{display:inline-flex;align-items:center;gap:5px;font-size:.8125rem;font-weight:600;line-height:1.3}
body #panel-kasir #kasir .pd-delta::before{content:'';width:0;height:0;border-left:4px solid transparent;border-right:4px solid transparent}
body #panel-kasir #kasir .pd-delta--up{color:var(--ui-green)}body #panel-kasir #kasir .pd-delta--up::before{border-bottom:6px solid currentColor}
body #panel-kasir #kasir .pd-delta--down{color:var(--ui-bad-ink)}body #panel-kasir #kasir .pd-delta--down::before{border-top:6px solid currentColor}
body #panel-kasir #kasir .pd-delta--flat,body #panel-kasir #kasir .pd-delta--new{color:var(--ui-ink-3)}body #panel-kasir #kasir .pd-delta--flat::before,body #panel-kasir #kasir .pd-delta--new::before{width:8px;height:2px;border:0;background:currentColor}

/* grafik batang: HTML/CSS, tidak melar seperti SVG */
body #panel-kasir #kasir .pd-chart{min-width:0;margin:0;padding:0}
body #panel-kasir #kasir .pd-chart figcaption{margin-top:6px;font-size:.8125rem;color:var(--ui-ink-3)}
body #panel-kasir #kasir .pd-chart figcaption strong{color:var(--ui-ink);font-weight:650}
body #panel-kasir #kasir .pd-chart-empty{display:flex;align-items:center;justify-content:center;min-height:112px;border:1px dashed var(--ui-line);border-radius:10px;background:#fbfdfc;color:var(--ui-ink-3);font-size:.875rem}
body #panel-kasir #kasir .pd-plot.is-empty .pd-col i{display:none}
body #panel-kasir #kasir .pd-plot-note{position:absolute;inset:0 0 22px;display:grid;place-items:center;margin:0;padding:12px;text-align:center;font-size:.875rem;color:var(--ui-ink-3);pointer-events:none}
body #panel-kasir #kasir .pd-plot-note::before{display:none}
body #panel-kasir #kasir .pd-plot{display:grid;grid-template-columns:52px minmax(0,1fr);min-width:0;gap:8px}

body #panel-kasir #kasir .pd-y{display:flex;flex-direction:column;justify-content:space-between;align-items:flex-end;height:120px;font-size:.8125rem;line-height:1;color:var(--ui-ink-3);font-variant-numeric:tabular-nums}
body #panel-kasir #kasir .pd-area{min-width:0}
body #panel-kasir #kasir .pd-grid{position:absolute;inset:0;display:flex;flex-direction:column;justify-content:space-between;pointer-events:none}
body #panel-kasir #kasir .pd-grid i{display:block;height:0;border-top:1px dashed var(--ui-line)}
body #panel-kasir #kasir .pd-grid i:last-child{border-top:1px solid var(--ui-line)}
body #panel-kasir #kasir .pd-bars{position:relative;display:flex;align-items:flex-end;gap:4px;height:120px}
body #panel-kasir #kasir .pd-area{position:relative}
body #panel-kasir #kasir .pd-area .pd-grid{height:120px}
body #panel-kasir #kasir .pd-col{position:relative;z-index:1;flex:1 1 0;min-width:0;height:100%;display:flex;align-items:flex-end}
body #panel-kasir #kasir .pd-col i{display:block;width:100%;max-width:44px;margin-inline:auto;min-height:0;background:var(--ui-green);opacity:.62;border-radius:4px 4px 0 0;transition:opacity .12s}
body #panel-kasir #kasir .pd-col:hover i,body #panel-kasir #kasir .pd-col.is-peak i{opacity:1}
body #panel-kasir #kasir .pd-col.is-peak i{background:var(--ui-green-2)}
body #panel-kasir #kasir .pd-x{display:flex;gap:4px;margin-top:5px}
body #panel-kasir #kasir .pd-x span{flex:1 1 0;min-width:0;display:flex;justify-content:center;white-space:nowrap;font-size:.8125rem;line-height:1.1;color:var(--ui-ink-3)}

/* produk terlaris */
body #panel-kasir #kasir .pd-top{margin:6px 0 0;padding:0;list-style:none}
body #panel-kasir #kasir .pd-top li{position:relative;display:grid;grid-template-columns:22px minmax(0,1fr) auto;gap:8px;align-items:baseline;padding:6px 0 9px;border-bottom:1px solid var(--ui-line-soft)}
body #panel-kasir #kasir .pd-top li:last-child{border-bottom:0}
body #panel-kasir #kasir .pd-rank{display:inline-grid;place-items:center;width:20px;height:20px;border-radius:6px;background:var(--ui-head);color:var(--ui-ink-2);font-size:.8125rem;font-weight:700}
body #panel-kasir #kasir .pd-top li:first-child .pd-rank{background:var(--ui-green);color:#fff}
body #panel-kasir #kasir .pd-top-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.9375rem;font-weight:600;color:var(--ui-ink)}
body #panel-kasir #kasir .pd-top-qty{font-size:.875rem;font-weight:600;color:var(--ui-ink-2);font-variant-numeric:tabular-nums;white-space:nowrap}
body #panel-kasir #kasir .pd-top-bar{position:absolute;left:30px;bottom:3px;height:3px;max-width:calc(100% - 30px);border-radius:2px;background:var(--ui-green);opacity:.35}
body #panel-kasir #kasir .pd-empty{margin:8px 0;font-size:.875rem;color:var(--ui-ink-3)}
body #panel-kasir #kasir .pd-link{display:inline-block;margin-top:6px;font-size:.875rem;font-weight:600;color:var(--ui-green);text-decoration:underline;text-underline-offset:3px}

@media (max-width:760px){
  body #panel-kasir #kasir .pd-pane{grid-template-columns:1fr}
  body #panel-kasir #kasir .pd-side{border-left:0;border-top:1px solid var(--ui-line-soft)}
}
@media (max-width:760px){
  body #panel-kasir #kasir .pd-kpis{grid-template-columns:1fr 1fr;gap:10px 0}
  body #panel-kasir #kasir .pd-kpi:nth-child(odd){padding-left:0;border-left:0}
  body #panel-kasir #kasir .pd-kpi--lead .pd-kpi-value{font-size:1.375rem}
  body #panel-kasir #kasir .pd-plot{grid-template-columns:44px minmax(0,1fr)}
}

/* ---------- B. susunan kerja: katalog kiri, keranjang kanan ---------- */
body #panel-kasir #kasir .pos-workspace{display:grid;grid-template-columns:minmax(0,1.12fr) minmax(0,1fr);gap:12px;align-items:start;min-width:0}
body #panel-kasir #kasir :is(.pos-workspace>*,.pos-catalog-card,.pos-cart-pane>.card,#form-checkout,.pos-cart-section,.cart-table-scroll,#items,.pos-buyer-card,.pos-combo){min-width:0;max-width:100%}
body #panel-kasir #kasir :is(button,input,select,summary):focus-visible{outline:2px solid var(--ui-green);outline-offset:2px}
body #panel-kasir #kasir .pos-catalog-card>h2,body #panel-kasir #kasir .pos-cart-pane h2{font-size:1.0625rem;margin:0 0 10px;padding:0;border:0}
body #panel-kasir #kasir .pos-catalog-card>h2::after,body #panel-kasir #kasir .pos-cart-pane h2::after{display:none}

/* pencarian */
body #panel-kasir #kasir .pos-search-wrapper{margin-bottom:0}
body #panel-kasir #kasir .pos-search-wrapper label{display:block;margin:0 0 4px;font-size:.875rem;font-weight:600;color:var(--ui-ink-2)}
body #panel-kasir #kasir .pos-search-box{display:flex;align-items:center;gap:8px;min-height:42px;padding:0 10px;border:1px solid var(--ui-line) !important;border-radius:10px;background:#fff !important;box-shadow:none !important}
body #panel-kasir #kasir .pos-search-box:focus-within{border-color:var(--ui-green) !important;outline:2px solid rgb(20 83 45 / .18);outline-offset:0}
body #panel-kasir #kasir .pos-search-box svg{flex:none;color:var(--ui-ink-3)}
body #panel-kasir #kasir .pos-search-box input{flex:1;min-width:0;min-height:38px;border:0 !important;outline:0 !important;box-shadow:none !important;background:transparent !important;font-size:1rem}
body #panel-kasir #kasir .search-kbd-hint{flex:none;padding:2px 8px;border:1px solid var(--ui-line);border-radius:6px;background:var(--ui-head) !important;color:var(--ui-ink-3) !important;font-size:.8125rem;font-weight:600}
body #panel-kasir #kasir #product-search-status{margin:6px 0 10px;font-size:.8125rem;color:var(--ui-ink-3)}
body #panel-kasir #kasir .catalog-filter-row{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) auto;gap:10px;align-items:end;margin:0 0 10px}
body #panel-kasir #kasir .catalog-filter-row label{display:block;margin:0 0 4px;font-size:.8125rem;font-weight:600;color:var(--ui-ink-3)}
body #panel-kasir #kasir #catalog-reset{min-height:38px;background:#fff !important;border:1px solid var(--ui-line) !important;color:var(--ui-green) !important}
body #panel-kasir #kasir #catalog-reset:hover{background:var(--ui-green-soft) !important;border-color:var(--ui-green) !important}

/* tabel katalog: kolom tetap, angka tidak patah, gulir di dalam kartu */
body #panel-kasir #kasir .catalog-table-scroll{max-height:60vh;min-height:0;overflow:auto;border:1px solid var(--ui-line);border-radius:10px}
body #panel-kasir #kasir #products-table{table-layout:fixed;width:100%}
body #panel-kasir #kasir #products-table th:nth-child(2){width:116px}
body #panel-kasir #kasir #products-table th:nth-child(3){width:112px}
body #panel-kasir #kasir #products-table th:nth-child(4){width:84px}
body #panel-kasir #kasir #products-table thead th{position:sticky;top:0;z-index:2}
body #panel-kasir #kasir #products-table td{vertical-align:middle;padding:8px 10px}
body #panel-kasir #kasir #products-table td.ui-num{white-space:nowrap;text-align:right;font-weight:650;color:var(--ui-ink)}
body #panel-kasir #kasir #products-table td.ui-primary{overflow:hidden;text-overflow:ellipsis;font-weight:650;color:var(--ui-ink)}
body #panel-kasir #kasir #products-table tbody tr:hover{background:var(--ui-hover)}
body #panel-kasir #kasir .catalog-more{display:flex;justify-content:center;margin-top:10px}
body #panel-kasir #kasir .catalog-more button{min-height:36px;padding:0 16px}

/* ---------- C. warna: satu arti satu warna ---------- */
body #panel-kasir #kasir :is(.card,.pos-catalog-card,.pos-cart-card){background:#fff !important;border:1px solid var(--ui-line);box-shadow:none}
body #panel-kasir #kasir table th{background:var(--ui-head) !important;color:var(--ui-ink-2) !important;text-transform:none !important;letter-spacing:0 !important;font-size:.8125rem !important;font-weight:650;border-bottom:1px solid var(--ui-line)}
body #panel-kasir #kasir .stock-badge{display:inline-flex;align-items:center;gap:6px;padding:2px 9px;border:1px solid transparent;border-radius:999px;font-size:.8125rem;font-weight:650;white-space:nowrap}
body #panel-kasir #kasir .stock-badge::before{content:'';width:7px;height:7px;border-radius:50%;background:currentColor}
body #panel-kasir #kasir .stock-badge.in-stock{background:var(--ui-green-soft) !important;color:var(--ui-green) !important;border-color:#cfe3d6}
body #panel-kasir #kasir .stock-badge.low-stock{background:var(--ui-warn-bg) !important;color:var(--ui-warn-ink) !important;border-color:#ecd9a3}
body #panel-kasir #kasir .stock-badge.out-of-stock{background:var(--ui-bad-bg) !important;color:var(--ui-bad-ink) !important;border-color:#f0cdc7}
body #panel-kasir #kasir .btn-table-action{min-height:32px;padding:3px 12px;font-size:.875rem;font-weight:650;background:#fff !important;border:1px solid var(--ui-green) !important;color:var(--ui-green) !important;border-radius:8px}
body #panel-kasir #kasir .btn-table-action:hover{background:var(--ui-green) !important;color:#fff !important}
body #panel-kasir #kasir tr:has(.out-of-stock) .btn-table-action{border-color:var(--ui-line) !important;color:var(--ui-ink-3) !important}

/* pembeli */
body #panel-kasir #kasir .pos-buyer-card{padding:10px 12px;border:1px solid var(--ui-line);border-radius:12px;background:#fff !important}
body #panel-kasir #kasir .pos-buyer-header{display:flex;align-items:center;justify-content:space-between;gap:8px;margin:0 0 6px}
body #panel-kasir #kasir .pos-buyer-title{display:inline-flex;align-items:center;gap:6px;font-size:.875rem;font-weight:700;color:var(--ui-ink)}
body #panel-kasir #kasir .pos-buyer-badge{padding:2px 10px;border-radius:999px;font-size:.8125rem;font-weight:650}
body #panel-kasir #kasir .pos-buyer-badge.badge-general{background:var(--ui-mute-bg) !important;color:var(--ui-ink-2) !important}
body #panel-kasir #kasir .pos-buyer-badge.badge-registered{background:var(--ui-green-soft) !important;color:var(--ui-green) !important}
body #panel-kasir #kasir .pos-buyer-select-wrapper{margin:0}
body #panel-kasir #kasir .pos-buyer-card{padding:12px 14px;box-shadow:none}
body #panel-kasir #kasir .pos-buyer-card:focus-within{border-color:var(--ui-line);box-shadow:none}
body #panel-kasir #kasir .pos-buyer-header{flex-wrap:wrap;margin-bottom:10px}
body #panel-kasir #kasir .pos-combo-input{box-sizing:border-box;height:44px;min-height:44px;padding:10px 12px;border:1px solid var(--ui-line);border-radius:8px;font-size:.9375rem;line-height:1.4}
body #panel-kasir #kasir .pos-combo-input:focus{border-color:var(--ui-green);outline:2px solid var(--ui-green-soft);outline-offset:1px;box-shadow:none}
body #panel-kasir #kasir .pos-combo-note{margin:8px 0 0;font-size:.8125rem;line-height:1.4;color:var(--ui-ink-3)}
/* Anchor the menu to the input, not the helper text underneath it. */
body #panel-kasir #kasir .pos-combo-list{box-sizing:border-box;top:50px;z-index:45;padding:6px;border-radius:10px;max-height:240px;box-shadow:0 8px 24px rgb(15 42 29 / .12)}
body #panel-kasir #kasir .pos-combo-item{box-sizing:border-box;display:flex;flex-direction:column;align-items:flex-start;justify-content:center;gap:3px;min-height:52px;padding:8px 10px;border-radius:6px;line-height:1.35;overflow-wrap:anywhere}
body #panel-kasir #kasir .pos-combo-name{font-size:.9375rem;font-weight:600}
body #panel-kasir #kasir .pos-combo-hint{font-size:.8125rem;color:var(--ui-ink-3)}
body #panel-kasir #kasir .pos-combo-item[aria-selected=true]{background:var(--ui-green-soft);box-shadow:inset 3px 0 var(--ui-green)}
body #panel-kasir #kasir .pos-combo:has(.pos-combo-input[aria-expanded=true]) .pos-combo-note{visibility:hidden}
/* keterangan pelanggan sudah ada di bawah kolom ketik; aturan kredit tampil di jendela pembayaran */
body #panel-kasir #kasir .pos-buyer-meta{display:none}

/* keranjang: satu barang = dua baris ringkas (nama + hapus, lalu jumlah + diskon + subtotal) */
body #panel-kasir #kasir .pos-cart-section{container-type:inline-size;container-name:pos-cart;margin:14px 0 0;padding:0;border:0 !important;background:transparent !important}
body #panel-kasir #kasir .cart-label{display:flex;align-items:baseline;justify-content:space-between;margin:0 0 4px;padding:0 2px}
body #panel-kasir #kasir .cart-label strong{font-size:.9375rem;color:var(--ui-ink);text-transform:none !important;letter-spacing:0 !important}
body #panel-kasir #kasir #cart-item-count{font-size:.8125rem;color:var(--ui-ink-3)}
body #panel-kasir #kasir .cart-table-scroll{max-height:clamp(260px,42vh,420px);min-height:0;overflow:auto;overflow-x:hidden;margin:0;padding:0;scrollbar-width:thin;scrollbar-gutter:stable}
body #panel-kasir #kasir .cart-column-header{display:none}
body #panel-kasir #kasir .cart-column-header .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
body #panel-kasir #kasir #items>.item-row:nth-child(even){background:var(--ui-mute-bg)}
body #panel-kasir #kasir #items>.item-row:hover,body #panel-kasir #kasir #items>.item-row:focus-within{background:var(--ui-green-soft)}
body #panel-kasir #kasir .cart-empty-message,body #panel-kasir #kasir .cart-empty-row td{padding:18px 12px;border:1px dashed var(--ui-line);border-radius:10px;background:#fbfdfc !important;color:var(--ui-ink-3);font-size:.875rem;text-align:center}
body #panel-kasir #kasir #items>.item-row{display:grid !important;grid-template-columns:auto auto minmax(0,1fr) 30px;grid-template-areas:"head head head del" "qty disc sub sub";min-width:0;gap:6px 10px;align-items:center;margin:0;padding:10px 2px;border:0;border-bottom:1px solid var(--ui-line-soft);border-radius:0;background:transparent;box-shadow:none}
body #panel-kasir #kasir #items>.item-row:last-child{border-bottom:0}
body #panel-kasir #kasir .cart-product-heading{grid-area:head;min-width:0;width:auto}
body #panel-kasir #kasir .cart-product-title{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.9375rem;font-weight:650;line-height:1.3;color:var(--ui-ink)}
body #panel-kasir #kasir .cart-product-meta{display:block;margin-top:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.8125rem;line-height:1.3;color:var(--ui-ink-3)}
body #panel-kasir #kasir .cart-unit-price{color:var(--ui-ink-2);font-weight:600;font-variant-numeric:tabular-nums}
body #panel-kasir #kasir .cart-stock{white-space:nowrap}
body #panel-kasir #kasir .cart-stock.is-out{color:var(--ui-bad-ink);font-weight:600}
body #panel-kasir #kasir .item-subtotal{grid-area:sub;min-width:0;text-align:right;font-size:1rem;font-weight:700;color:var(--ui-ink);font-variant-numeric:tabular-nums;white-space:nowrap}
body #panel-kasir #kasir .qty-stepper{grid-area:qty;justify-self:start;min-width:0;max-width:100%;display:inline-flex;align-items:center;height:34px;border:1px solid var(--ui-line);border-radius:8px;overflow:visible;background:#fff}
body #panel-kasir #kasir .qty-stepper button{min-height:32px;height:32px;min-width:32px;width:32px;flex:none;padding:0;border:0 !important;border-radius:0 !important;background:#f5f8f6 !important;color:var(--ui-ink-2) !important;font-size:1rem;line-height:1}
body #panel-kasir #kasir .qty-stepper button:hover{background:var(--ui-green-soft) !important;color:var(--ui-green) !important}
body #panel-kasir #kasir .qty-stepper input[data-name=quantity]{width:7ch;min-width:3ch;max-width:96px;height:32px;min-height:32px;padding:0 4px;font-size:.9375rem;border:0 !important;border-inline:1px solid var(--ui-line-soft) !important;border-radius:0 !important;box-shadow:none !important;text-align:center;font-weight:650;font-variant-numeric:tabular-nums}
body #panel-kasir #kasir .item-row [data-remove]{grid-area:del;justify-self:end;align-self:start;width:30px;min-width:30px;height:30px;min-height:30px;padding:0;border:0 !important;border-radius:8px !important;background:transparent !important;color:var(--ui-ink-3) !important;font-size:1.125rem;line-height:1;box-shadow:none !important}
body #panel-kasir #kasir .item-row [data-remove]:hover{background:var(--ui-bad-bg) !important;color:var(--ui-bad-ink) !important}
/* diskon: tertutup = tautan kecil di sebelah jumlah; terbuka = isian penuh di baris sendiri */
body #panel-kasir #kasir .line-discount{grid-area:disc;min-width:0;margin:0;padding:0;border:0;background:transparent}
body #panel-kasir #kasir .line-discount[open]{grid-area:auto;grid-column:1/-1;grid-row:3;padding:8px 10px 10px;border:1px solid var(--ui-line-soft);border-radius:8px;background:#f8fbf9}
body #panel-kasir #kasir .line-discount summary{display:inline-flex;align-items:center;min-height:28px;padding:0 2px;font-size:.8125rem;font-weight:600;color:var(--ui-green);cursor:pointer;list-style:none;white-space:nowrap}
body #panel-kasir #kasir .line-discount summary::-webkit-details-marker{display:none}
body #panel-kasir #kasir .line-discount summary::before{content:'+';margin-right:4px;font-weight:700}
body #panel-kasir #kasir .line-discount[open] summary::before{content:'\2212'}
body #panel-kasir #kasir .discount-controls{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:6px}
body #panel-kasir #kasir .discount-controls label{display:flex;flex-direction:column;gap:3px;min-width:0;font-size:.8125rem;font-weight:600;color:var(--ui-ink-3)}
body #panel-kasir #kasir .discount-controls :is(input,select,.select-trigger){min-width:0;max-width:100%;min-height:34px;height:34px;font-size:.875rem}

/* total dan tombol bayar */
body #panel-kasir #kasir .summary-box{margin:10px 0 0;padding:12px 14px;border:1px solid var(--ui-line) !important;border-radius:12px;background:#f6faf7 !important;box-shadow:none}
body #panel-kasir #kasir .summary-box::before,body #panel-kasir #kasir .summary-box::after{display:none}
body #panel-kasir #kasir .summary-row{display:flex;flex-wrap:wrap;gap:6px 12px;min-width:0;align-items:baseline;justify-content:space-between;border:0 !important}
body #panel-kasir #kasir .summary-row span{font-size:.9375rem;font-weight:600;color:var(--ui-ink-2)}
body #panel-kasir #kasir .summary-total strong,body #panel-kasir #kasir #display-total{font-size:1.375rem !important;overflow-wrap:anywhere;font-weight:800;color:var(--ui-green) !important;font-variant-numeric:tabular-nums}
body #panel-kasir #kasir .pos-shift-notice{display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin:0 0 14px;padding:12px;border:1px solid var(--ui-line);border-radius:12px;background:var(--ui-mute-bg);color:var(--ui-ink)}
body #panel-kasir #kasir .pos-shift-reminder{margin:8px 0 0;font-size:.8125rem;line-height:1.4;color:var(--ui-ink-3)}
body #panel-kasir #kasir .pos-shift-copy{display:flex;flex:1 1 230px;flex-direction:column;gap:4px;min-width:0;line-height:1.4}
body #panel-kasir #kasir .pos-shift-copy strong{font-size:.875rem;font-weight:700}
body #panel-kasir #kasir .pos-shift-copy>span{font-size:.8125rem;color:var(--ui-ink-3)}
body #panel-kasir #kasir .pos-shift-action{display:inline-flex;align-items:center;justify-content:center;gap:10px;flex:0 0 auto;box-sizing:border-box;min-height:44px;padding:10px 16px;border:1px solid var(--ui-green);border-radius:8px;background:#fff;color:var(--ui-green) !important;font-size:.875rem;font-weight:700;line-height:1.4;text-decoration:none;white-space:nowrap}
body #panel-kasir #kasir .pos-shift-action:hover{background:var(--ui-green-soft);text-decoration:none}
body #panel-kasir #kasir .pos-shift-action:focus-visible{outline:2px solid var(--ui-green);outline-offset:3px}
body #panel-kasir #kasir .pos-shift-action svg{flex:none}
@media(max-width:520px){body #panel-kasir #kasir .pos-shift-action{width:100%}}
body #panel-kasir #kasir .pos-checkout-action{margin:10px 0 0}
body #panel-kasir #kasir .btn-pill-submit{width:100%;min-height:48px;border-radius:12px;background:var(--ui-green) !important;border:1px solid var(--ui-green) !important;color:#fff !important;font-size:1.0625rem;font-weight:700;box-shadow:none}
body #panel-kasir #kasir .btn-pill-submit:hover:not(:disabled){background:var(--ui-green-2) !important}
body #panel-kasir #kasir .btn-pill-submit:disabled{background:#eef2ef !important;border-color:var(--ui-line) !important;color:#7b8a81 !important;cursor:not-allowed}
body #panel-kasir #kasir .cart-error:not(:empty){margin:8px 0 0;padding:8px 10px;border-radius:8px;background:var(--ui-bad-bg);color:var(--ui-bad-ink);font-size:.875rem}

@media (max-width:1100px){
  body #panel-kasir #kasir .pos-workspace{grid-template-columns:minmax(0,1fr)}
  body #panel-kasir #kasir .catalog-table-scroll{max-height:60vh}
}
@media (max-width:520px){
  body #panel-kasir #kasir .pd-controls{width:100%;flex-wrap:wrap}
  body #panel-kasir #kasir .pd-seg button{white-space:nowrap;min-height:36px;padding-inline:10px}
  body #panel-kasir #kasir .pd-kpi-value,
  body #panel-kasir #kasir .pd-kpi--lead .pd-kpi-value{font-size:1.125rem}
  body #panel-kasir #kasir .pd-pane[data-pane="7d"] .pd-x span:nth-child(even){visibility:hidden}
  body #panel-kasir #kasir .pd-pane[data-pane="30d"] .pd-x span:nth-child(5n + 1){visibility:hidden}
  body #panel-kasir #kasir .catalog-filter-row{grid-template-columns:1fr 1fr}
  body #panel-kasir #kasir .catalog-filter-row>:last-child{grid-column:1/-1}
  body #panel-kasir #kasir .item-subtotal{white-space:nowrap;font-size:.9375rem}
  body #panel-kasir #kasir .cart-product-meta{white-space:normal}
  body #panel-kasir #kasir #items>.item-row{grid-template-columns:auto minmax(0,1fr) 30px;grid-template-areas:"head head del" "qty sub sub" "disc disc disc";column-gap:8px;row-gap:4px}
  body #panel-kasir #kasir .line-discount[open]{grid-area:disc}
  body #panel-kasir #kasir .qty-stepper input[data-name=quantity]{min-width:3ch;width:auto}
}
/* Cart width, not viewport width, decides between cards and aligned columns. */
@container pos-cart (max-width:539px){
  body #panel-kasir #kasir #items>.item-row{grid-template-columns:minmax(0,1fr) auto 30px;grid-template-areas:"head head del" "qty sub del" "disc disc disc";gap:4px 8px;padding:8px 2px}
  /* Stepper ringkas: ukuran mengikuti isi, angka tidak pernah terpotong walau font sistem diperbesar. */
  body #panel-kasir #kasir .qty-stepper{width:auto;min-width:102px;max-width:min(132px,100%);box-sizing:border-box}
  body #panel-kasir #kasir .qty-stepper button{width:36px;min-width:36px;height:36px;min-height:36px;background:transparent !important}
  body #panel-kasir #kasir .qty-stepper input[data-name=quantity]{flex:1 1 auto;width:auto;min-width:3ch;max-width:none;border-inline:0 !important;padding-inline:2px}
  body #panel-kasir #kasir .item-subtotal{white-space:normal;overflow-wrap:anywhere;font-size:.875rem}
  body #panel-kasir #kasir .line-discount summary{font-size:.8125rem;padding:0;min-height:28px}
  body #panel-kasir #kasir .line-discount[open]{grid-area:auto;grid-column:1/-1;grid-row:3}
}
@container pos-cart (min-width:540px){
  body #panel-kasir #kasir .cart-column-header,
  body #panel-kasir #kasir #items>.item-row{grid-template-columns:minmax(0,1fr) 124px 120px 30px;column-gap:10px;box-sizing:border-box;padding-inline:8px}
  body #panel-kasir #kasir .cart-column-header{display:grid;align-items:center;position:sticky;top:0;z-index:3;min-height:34px;padding-block:8px;border-bottom:1px solid var(--ui-line);background:var(--ui-mute-bg);color:var(--ui-ink-2);font-size:.8125rem;font-weight:650;line-height:1.2}
  body #panel-kasir #kasir .cart-column-quantity{text-align:center}
  body #panel-kasir #kasir .cart-column-subtotal{text-align:right}
  body #panel-kasir #kasir #items>.item-row{grid-template-areas:"head qty sub del" "disc qty sub del";grid-template-rows:auto 18px;row-gap:0;min-height:76px;padding-block:8px;align-items:center}
  body #panel-kasir #kasir .cart-product-heading{align-self:end}
  body #panel-kasir #kasir .cart-product-title{font-size:.9375rem;line-height:1.25}
  /* Hide repeated SKU/unit text only in the compact desktop presentation. */
  body #panel-kasir #kasir .cart-product-meta{display:flex;gap:8px;align-items:center;font-size:0;line-height:1.3;color:var(--ui-ink-3)}
  body #panel-kasir #kasir .cart-product-meta :is(.cart-unit-price,.cart-stock){font-size:.8125rem;flex:none}
  body #panel-kasir #kasir .cart-unit-price{font-weight:500;color:var(--ui-ink-2)}
  body #panel-kasir #kasir .qty-stepper{width:124px;justify-self:center;box-sizing:border-box}
  body #panel-kasir #kasir .qty-stepper input[data-name=quantity]{width:58px;min-width:0;max-width:58px;flex:1 1 0}
  body #panel-kasir #kasir .item-subtotal{font-size:.875rem;white-space:normal;overflow-wrap:anywhere;line-height:1.3}
  body #panel-kasir #kasir .item-row [data-remove]{align-self:center}
  body #panel-kasir #kasir .line-discount{align-self:start}
  body #panel-kasir #kasir .line-discount summary{min-height:18px;padding:0;font-size:.8125rem;font-weight:500;line-height:18px}
  body #panel-kasir #kasir .line-discount[open]{grid-area:auto;grid-column:1/-1;grid-row:3;margin-top:6px;align-self:auto}
}
</style>
