{{-- Gaya form master (uf-*). Dimuat di <head> agar tidak ada kedipan tampilan lama saat halaman dibuka. --}}
<style>
/* ============================================================
   SISTEM FORM V2 (uf-*) — pasangan dari App\Support\V2\FormCatalog
   Ringkas: label 14px di atas kontrol 40px, jarak 10-12px, satu grid 6 kolom.
   ============================================================ */
/* dialog: semua popup lebih rapat atas-bawah */
dialog.input-dialog .popup-heading{padding:12px 14px 12px 20px;align-items:center;gap:12px}
dialog.input-dialog .popup-heading h2{font-size:1.0625rem;line-height:1.3;margin:0;padding:0;border:0;color:var(--ui-ink)}
dialog.input-dialog .popup-heading h2::after{display:none}
dialog.input-dialog .popup-close{width:36px;height:36px;min-height:36px;font-size:20px;line-height:1;border-radius:10px}
dialog.input-dialog .popup-content{padding:16px 20px}
dialog.input-dialog .popup-footer{padding:10px 20px}
dialog.input-dialog .popup-footer button{min-height:40px}
dialog.input-dialog.uf-dialog{width:min(580px,calc(100vw - 32px));max-width:min(580px,calc(100vw - 32px));max-height:min(calc(100dvh - 48px),860px);border-radius:14px}
dialog.input-dialog.uf-dialog-wide{width:min(780px,calc(100vw - 32px));max-width:min(780px,calc(100vw - 32px))}
.uf-dialog .popup-content{padding:14px 20px 0}
.uf-dialog-sub{margin:2px 0 0;font-size:var(--ui-t-label);color:var(--ui-ink-2);font-weight:500}

/* struktur */
.uf-form{display:flex;flex-direction:column;gap:12px;margin:0}
.uf-section{min-width:0}
.uf-section+.uf-section{border-top:1px solid var(--ui-line-soft);padding-top:12px}
.uf-section-title{font-size:var(--ui-t-label);font-weight:650;color:var(--ui-green);margin:0 0 8px;padding:0;border:0;letter-spacing:0;text-transform:none}
.uf-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px 12px;align-items:start}
.uf{min-width:0;display:flex;flex-direction:column;gap:4px;margin:0}
.uf[hidden]{display:none}
.uf-w-2{grid-column:span 2}.uf-w-3{grid-column:span 3}.uf-w-4{grid-column:span 4}.uf-w-6{grid-column:1/-1}
.uf-label{display:block;font-size:var(--ui-t-label);font-weight:600;line-height:1.3;color:var(--ui-ink);margin:0;padding:0}
.uf-req{color:var(--ui-bad-bar);margin-left:3px;font-weight:700}

/* kontrol */
.uf input:not([type=checkbox]):not([type=radio]):not([type=hidden]):not([type=file]),.uf select,.uf textarea{
  width:100%;max-width:none;height:40px;min-height:40px;margin:0;padding:0 12px;
  font:inherit;font-size:var(--ui-t-table);color:var(--ui-ink);
  background:#fff;border:1px solid #c5d3ca;border-radius:10px;box-shadow:none}
.uf textarea{height:auto;min-height:62px;padding:8px 12px;line-height:1.45;resize:vertical}
.uf :is(input,select,textarea):hover{border-color:#9db4a5}
.uf :is(input,select,textarea):focus{outline:none;border-color:var(--ui-green);box-shadow:0 0 0 3px rgb(20 83 45 / .14)}
.uf :is(input,textarea)[readonly]{background:#f1f5f2;color:var(--ui-ink-2);cursor:default}
.uf :is(input,textarea)::placeholder{color:#7f9286}
.uf-mono{font-family:'JetBrains Mono',ui-monospace,monospace!important;letter-spacing:.02em}
.uf .select-shell{width:100%;min-width:0}
.uf-dialog .uf .select-shell>.select-trigger{min-height:40px!important;height:40px!important;padding:0 12px!important;font-size:var(--ui-t-table)!important;border-radius:10px!important;border-color:#c5d3ca!important}
.uf input[type=file]{font-size:var(--ui-t-label);padding:8px 0}

/* angka dengan awalan/akhiran (Rp, satuan) */
.uf-affix{display:flex;align-items:stretch;height:40px;background:#fff;border:1px solid #c5d3ca;border-radius:10px;overflow:hidden}
.uf-affix:hover{border-color:#9db4a5}
.uf-affix:focus-within{border-color:var(--ui-green);box-shadow:0 0 0 3px rgb(20 83 45 / .14)}
.uf .uf-affix input:not([type=hidden]){height:38px!important;min-height:38px!important;border:0!important;border-radius:0!important;box-shadow:none!important;text-align:right;font-variant-numeric:tabular-nums;padding:0 10px!important}
.uf .uf-affix input[readonly]{background:#f1f5f2}
.uf-pre,.uf-suf{display:flex;align-items:center;flex:none;padding:0 10px;background:#f1f5f2;color:var(--ui-ink-2);font-size:var(--ui-t-label);font-weight:600;white-space:nowrap}
.uf-pre{border-right:1px solid var(--ui-line-soft)}
.uf-suf{border-left:1px solid var(--ui-line-soft);max-width:48%;overflow:hidden;text-overflow:ellipsis}
.uf-lock{font-size:var(--ui-t-caption);color:var(--ui-warn-ink)}
.uf-margin{font-size:var(--ui-t-caption);font-weight:600;color:var(--ui-green);font-variant-numeric:tabular-nums}
.uf-margin[data-tone=loss]{color:var(--ui-bad-ink)}

/* pilihan tombol (segmented) */
.uf-group{border:0;margin:0;padding:0;min-width:0}
.uf-group>legend{float:left;width:100%;margin:0 0 4px}
.uf-group>legend+*{clear:both}
.uf-seg{display:inline-flex;flex-wrap:wrap;gap:2px;padding:3px;background:#eaf1ec;border-radius:11px}
.uf-seg-item{position:relative;display:flex;margin:0}
.uf-seg-item input{position:absolute;inset:0;width:100%;height:100%;margin:0;opacity:0;cursor:pointer}
.uf-seg-item span{display:flex;align-items:center;height:34px;padding:0 16px;border-radius:8px;font-size:var(--ui-t-label);font-weight:600;color:var(--ui-ink-2);transition:background-color .15s,color .15s}
.uf-seg-item input:checked+span{background:#fff;color:var(--ui-green);box-shadow:0 1px 3px rgb(23 43 32 / .14)}
.uf-seg-item input:focus-visible+span{outline:3px solid var(--ui-focus);outline-offset:1px}

/* ceklis relasi (mis. supplier) */
.uf-checks{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:6px;max-height:168px;overflow:auto;padding:1px}
.uf-check{display:flex;align-items:center;gap:8px;min-height:36px;margin:0;padding:6px 10px;border:1px solid var(--ui-line);border-radius:9px;background:#fff;cursor:pointer;font-size:var(--ui-t-label);font-weight:500;line-height:1.3;color:var(--ui-ink)}
.uf-check:hover{border-color:#9db4a5}
.uf-check:has(input:checked){border-color:var(--ui-green);background:var(--ui-green-soft);color:var(--ui-green);font-weight:600}
.uf .uf-check input[type=checkbox]{flex:0 0 18px;width:18px!important;height:18px!important;min-width:18px;min-height:18px!important;margin:0!important;padding:0;accent-color:var(--ui-green)}
.uf-check>span{flex:1 1 auto;min-width:0}
.uf-archived{font-style:normal;font-weight:500;margin-left:6px;color:var(--ui-ink-2)}
.uf-none{margin:0;font-size:var(--ui-t-label);color:var(--ui-ink-2)}
.uf-link{align-self:flex-start;display:inline-flex;align-items:center;min-height:32px;font-size:var(--ui-t-label);font-weight:600;color:var(--ui-green)}

/* dropdown banyak pilihan (supplier produk) */
.uf-multi{display:flex;flex-direction:column;gap:8px;min-width:0}
.uf-picked{display:flex;flex-wrap:wrap;gap:6px;margin:0;padding:0;list-style:none}
.uf-picked:empty{display:none}
.uf-pick{display:inline-flex;align-items:center;gap:8px;min-height:34px;padding:4px 6px 4px 12px;border:1px solid var(--ui-line);border-radius:999px;background:#fff;font-size:var(--ui-t-label);color:var(--ui-ink)}
.uf-pick:first-child{border-color:var(--ui-accent-2,#36c46d);background:var(--ui-accent-soft,#e3f9ea)}
.uf-pick-name{font-weight:500}
.uf-pick-main{padding:1px 8px;border-radius:999px;background:var(--ui-accent,#4ade80);color:var(--ui-on-accent,#0f2a1d);font-size:var(--ui-t-caption);font-weight:700}
.uf-pick-x{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;padding:0;border:0;border-radius:50%;background:transparent;color:var(--ui-ink-2);font-size:17px;line-height:1;cursor:pointer}
.uf-pick-x:hover{background:#dfe8e2;color:var(--ui-ink)}
.uf-hint{margin:0;font-size:var(--ui-t-caption);color:var(--ui-ink-2)}

/* aksi: menempel di bawah saat isian panjang */
.uf-form .err,.uf-form .ok{margin:0}
.uf-form .err:empty,.uf-form .ok:empty{display:none}
.uf-actions{position:sticky;bottom:0;z-index:2;display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:8px;margin:2px -20px 0;padding:12px 20px;background:#fff;border-top:1px solid var(--ui-line-soft)}
.uf-actions button{white-space:nowrap;height:40px;min-height:40px;padding:0 18px;border-radius:10px;font-size:var(--ui-t-table);font-weight:600;cursor:pointer}
.uf-actions .secondary{background:#fff;color:var(--ui-ink);border:1px solid var(--ui-line)}
.uf-actions .secondary:hover{background:var(--ui-hover)}
.uf-primary{background:var(--ui-green)!important;color:#fff!important;border:1px solid var(--ui-green)!important;box-shadow:none!important}
.uf-primary:hover{background:var(--ui-green-2)!important}
.uf-primary[disabled]{opacity:.6;cursor:progress}

@media (max-width:639px){
  .uf-grid{grid-template-columns:1fr}
  .uf-w-2,.uf-w-3,.uf-w-4,.uf-w-6{grid-column:1/-1}
  .uf input:not([type=checkbox]):not([type=radio]):not([type=hidden]):not([type=file]),.uf select,.uf-affix{height:44px;min-height:44px}
  .uf .uf-affix input:not([type=hidden]){height:42px!important;min-height:42px!important}
  .uf-actions button{height:44px}
  .uf-actions .uf-primary{flex:1 1 auto}
  dialog.input-dialog.uf-dialog,dialog.input-dialog.uf-dialog-wide{width:calc(100vw - 16px);max-width:calc(100vw - 16px);max-height:calc(100dvh - 16px)}
}

/* Penerimaan barang: satu baris per barang supaya daftar panjang tetap terbaca. */
.rc-items{border:0;padding:0;margin:14px 0 0;min-width:0}
.rc-items .lbl{padding:0;font-size:var(--ui-t-label);font-weight:600;color:var(--ui-muted)}
.rc-head,.rc-row{display:grid;grid-template-columns:minmax(0,1fr) 92px 128px 112px 36px;gap:8px;align-items:center}
.rc-head{margin:8px 0 4px;font-size:var(--ui-t-caption);font-weight:600;color:var(--ui-muted)}
.rc-head>span:nth-child(2),.rc-head>span:nth-child(3),.rc-head>span:nth-child(4){text-align:right}
.rc-row{padding:4px 0;border-top:1px solid var(--ui-line)}
.rc-row .field{margin:0;min-width:0}
.rc-row input,.rc-row select,.rc-row .ui-pick-input{min-height:38px;margin:0}
.rc-row input[data-name=quantity],.rc-row input[data-name=unit_cost]{text-align:right}
.rc-value{justify-self:end;font-variant-numeric:tabular-nums;font-size:var(--ui-t-body);color:var(--ui-ink);white-space:nowrap}
.rc-remove{width:36px;min-width:36px;height:36px;min-height:36px;padding:0;border-radius:9px;line-height:1}
.rc-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:10px;padding-top:10px;border-top:1px solid var(--ui-line)}
.rc-total{display:flex;align-items:baseline;gap:10px;margin:0}
.rc-total strong{font-size:1.125rem;font-variant-numeric:tabular-nums}
#rc-count{font-size:var(--ui-t-label);color:var(--ui-muted)}
@media (max-width:720px){
  .rc-head{display:none}
  .rc-row{grid-template-columns:1fr 1fr;grid-template-areas:'barang barang' 'jumlah hpp' 'nilai hapus';padding:10px 0}
  .rc-row .field:nth-child(1){grid-area:barang}
  .rc-row .field:nth-child(2){grid-area:jumlah}
  .rc-row .field:nth-child(3){grid-area:hpp}
  .rc-value{grid-area:nilai;justify-self:start}
  .rc-remove{grid-area:hapus;justify-self:end}
}
</style>
