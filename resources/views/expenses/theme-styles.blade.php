{{-- Tema halaman Pengeluaran: diselaraskan penuh dengan DNA Operasional & Keuangan V2 (kartu putih, ubin hero hijau tua + ubin abu muda, tabel kepala hijau tua, kontrol 44px, modal seragam). --}}
<style>
:is(.expenses-page,#exp#exp){display:grid;gap:14px;min-width:0;color:#102a1d;font-family:'Inter Kasir',-apple-system,'Segoe UI',sans-serif;font-variant-numeric:tabular-nums}
:is(.expenses-page,#exp#exp) *{box-sizing:border-box}
:is(.expenses-page,#exp#exp) .exp-card{padding:20px !important;border:1px solid #e4ebe6 !important;border-radius:12px !important;background:#fff !important;box-shadow:none !important;min-width:0}
:is(.expenses-page,#exp#exp) .exp-summary-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin:0 0 16px}
:is(.expenses-page,#exp#exp) .exp-summary-head h2,:is(.expenses-page,#exp#exp) .exp-head h2{display:flex;align-items:center;gap:4px;margin:0 !important;padding:0 !important;border:0 !important;font-family:'Inter Kasir',sans-serif !important;font-size:18px !important;line-height:24px !important;font-weight:700 !important;color:#102a1d !important;letter-spacing:-.01em}
:is(.expenses-page,#exp#exp) .exp-summary-sub{margin:4px 0 0 !important;font-size:13px !important;line-height:18px !important;color:#5f7468 !important;font-weight:400 !important;max-width:64ch}
:is(.expenses-page,#exp#exp) .exp-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:0 0 14px}

/* Tombol halaman */
:is(.expenses-page,#exp#exp) .exp-btn{display:inline-flex !important;align-items:center;justify-content:center;gap:6px;min-height:44px !important;height:44px;padding:0 18px !important;border:1px solid #d5ded8 !important;border-radius:10px !important;background:#fff !important;color:#14532d !important;font:inherit;font-size:14px !important;font-weight:700 !important;text-decoration:none !important;box-shadow:none !important;white-space:nowrap;cursor:pointer}
:is(.expenses-page,#exp#exp) .exp-btn:hover{border-color:#14532d !important;background:#f1f7f3 !important}
:is(.expenses-page,#exp#exp) .exp-btn--primary{border-color:#14532d !important;background:#14532d !important;color:#fff !important}
:is(.expenses-page,#exp#exp) .exp-btn--primary:hover{background:#0f3f22 !important;border-color:#0f3f22 !important}

/* Ubin ringkasan: ubin pertama hijau tua (#14532D, teks lime + nilai putih), sisanya abu muda berbingkai putih */
:is(.expenses-page,#exp#exp) .exp-facts{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
:is(.expenses-page,#exp#exp) .exp-fact{min-width:0;display:flex;flex-direction:column;gap:2px;min-height:84px;padding:12px 14px !important;border:1px solid #e4ebe6 !important;border-radius:10px !important;background:#f4f6f5 !important;box-shadow:inset 0 0 0 5px #fff !important}
:is(.expenses-page,#exp#exp) .exp-fact>span{display:block;font-size:13px !important;font-weight:600 !important;line-height:1.3;color:#3c4a42 !important}
:is(.expenses-page,#exp#exp) .exp-fact>strong{display:block;margin-top:2px;font-size:21px !important;line-height:28px !important;font-weight:700 !important;color:#102a1d !important;font-variant-numeric:tabular-nums;letter-spacing:-.01em}
:is(.expenses-page,#exp#exp) .exp-fact>small{display:block;margin-top:2px;font-size:13px !important;color:#5f7468 !important}
:is(.expenses-page,#exp#exp) .exp-fact--primary{padding:14px 16px !important;border:0 !important;background:#14532d !important;box-shadow:none !important}
:is(.expenses-page,#exp#exp) .exp-fact--primary>span{color:#c8f26b !important}
:is(.expenses-page,#exp#exp) .exp-fact--primary>strong{font-size:24px !important;line-height:30px !important;color:#fff !important}
:is(.expenses-page,#exp#exp) .exp-fact--primary>small{color:#c8f26b !important;opacity:.9}

/* Saringan: memakai panel Filter bersama (uiTableFilters); fallback grid saat tanpa JS */
:is(.expenses-page,#exp#exp) .exp-filter:not([data-filter-ready]){display:grid;grid-template-columns:repeat(3,minmax(0,1fr)) auto;gap:10px 12px;align-items:end;margin:0}
:is(.expenses-page,#exp#exp) .exp-filter[data-filter-ready]{margin:0}
:is(.expenses-page,#exp#exp) .exp-filter:not([data-filter-ready]) .field{display:grid;gap:5px;min-width:0;margin:0}
:is(.expenses-page,#exp#exp) .exp-filter:not([data-filter-ready]) label{font-size:13px !important;font-weight:600 !important;color:#3c4a42 !important}
:is(.expenses-page,#exp#exp) .exp-filter:not([data-filter-ready]) :is(input,select,.date-trigger,.select-shell>.select-trigger){width:100% !important;min-height:44px !important;height:44px !important;padding:0 12px !important;border:1px solid #dfe6e1 !important;border-radius:8px !important;background:#fff !important;color:#102a1d !important;font:inherit;font-size:14px !important;box-shadow:none !important}
:is(.expenses-page,#exp#exp) .exp-filter:not([data-filter-ready]) .exp-filter-actions{display:flex;gap:8px;align-items:center}

/* Tabel berkepala hijau tua (#14532D) teks putih — bobot selektor menang atas ui-minimal */
:is(.expenses-page,#exp#exp) .exp-table-wrap{overflow:auto;border:1px solid #e4ebe6 !important;border-radius:10px !important;background:#fff !important}
:is(.expenses-page,#exp#exp) .exp-table{width:100%;border-collapse:separate !important;border-spacing:0 !important;margin:0 !important}
:is(.expenses-page,#exp#exp) .exp-table thead th{position:static;height:44px !important;padding:0 16px !important;vertical-align:middle !important;border:0 !important;border-radius:0 !important;background:#14532d !important;color:#fff !important;font-size:13px !important;font-weight:600 !important;text-align:left;text-transform:none !important;letter-spacing:0 !important;white-space:nowrap}
:is(.expenses-page,#exp#exp) .exp-table tbody td{padding:12px 16px !important;border:0 !important;border-bottom:1px solid #eef2ef !important;vertical-align:middle;font-size:14px !important;line-height:20px;color:#3c4a42 !important;background:#fff !important}
:is(.expenses-page,#exp#exp) .exp-table tbody tr:last-child td{border-bottom:0 !important}
:is(.expenses-page,#exp#exp) .exp-table tbody tr:hover td{background:#f8faf9 !important}
:is(.expenses-page,#exp#exp) .exp-table .exp-amount{text-align:right;font-weight:700 !important;color:#102a1d !important;font-variant-numeric:tabular-nums;white-space:nowrap}
:is(.expenses-page,#exp#exp) .exp-table .exp-code{font-size:13px !important;font-weight:600 !important;color:#102a1d !important;white-space:nowrap;font-variant-numeric:tabular-nums}
:is(.expenses-page,#exp#exp) .exp-badge{display:inline-flex;align-items:center;min-height:28px;padding:0 12px !important;border:1px solid transparent;border-radius:999px !important;font-size:13px !important;font-weight:700 !important;white-space:nowrap}
:is(.expenses-page,#exp#exp) .exp-badge--mute{background:#f1f4f2 !important;border-color:#dfe6e1 !important;color:#5f7468 !important}
:is(.expenses-page,#exp#exp) .exp-badge--ok{background:#e4f0e8 !important;border-color:#bfd8c7 !important;color:#14532d !important}
:is(.expenses-page,#exp#exp) .exp-badge--warn{background:#fef3c7 !important;border-color:#f4d47c !important;color:#7a4b00 !important}
:is(.expenses-page,#exp#exp) .exp-empty{padding:28px 16px !important;text-align:center;color:#5f7468 !important}
:is(.expenses-page,#exp#exp) .exp-empty strong{display:block;margin-bottom:4px;font-size:15px;color:#102a1d}
:is(.expenses-page,#exp#exp) .exp-alert{padding:10px 14px;border-radius:10px;font-size:14px;line-height:1.45}
:is(.expenses-page,#exp#exp) .exp-alert[hidden]{display:none !important}
:is(.expenses-page,#exp#exp) .exp-alert--ok{border:1px solid #bfd8c7;background:#e4f0e8;color:#14532d;font-weight:600}

/* Modal overlay & form: selaras penuh dengan popup Operasional/Master (kepala hijau tua, judul lime, affix Rp, segmen 2 opsi, Batal kiri & Simpan lime kanan) */
#modal-expense.modal-overlay{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(9,30,18,.55);opacity:0;visibility:hidden;pointer-events:none;transition:opacity .18s ease,visibility .18s ease;backdrop-filter:none;-webkit-backdrop-filter:none}
#modal-expense.modal-overlay.open{opacity:1;visibility:visible;pointer-events:auto}
#modal-expense .modal-dialog{width:min(560px,calc(100vw - 32px));max-width:none;max-height:calc(100dvh - 32px);display:flex;flex-direction:column;padding:0;border:0;border-radius:14px;overflow:hidden;background:#fff;box-shadow:0 24px 60px -20px rgba(4,12,8,.45);font-family:'Inter Kasir',-apple-system,'Segoe UI',sans-serif;color:#102a1d;transform:translateY(14px) scale(.97);opacity:0;transition:transform .22s cubic-bezier(.22,.8,.24,1),opacity .2s ease}
#modal-expense.modal-overlay.open .modal-dialog{transform:none;opacity:1}
#modal-expense .modal-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin:0;padding:18px 20px 16px 24px;background:#14532d;border:0;flex-shrink:0}
#modal-expense .modal-title-wrapper{display:flex;align-items:center;gap:12px;min-width:0}
#modal-expense .modal-title{margin:0;padding:0;border:0;font-family:'Bricolage Kasir','Inter Kasir',sans-serif;font-size:22px;line-height:28px;font-weight:700;color:#c8f26b;letter-spacing:-.01em}
#modal-expense .exp-modal-sub{font-size:13px;line-height:18px;color:#d6e8dc;margin-top:2px;font-weight:500}
#modal-expense .modal-close-btn{display:grid;place-items:center;flex:0 0 auto;width:36px;height:36px;min-width:36px;min-height:36px;padding:0;border:0;border-radius:8px;background:transparent;color:#fff;font-size:22px;line-height:1;cursor:pointer;box-shadow:none}
#modal-expense .modal-close-btn:hover{background:rgba(255,255,255,.12)}
#modal-expense .modal-close-btn:focus-visible{outline:2px solid #c8f26b;outline-offset:2px}
#modal-expense .modal-body{overflow:auto;padding:20px 24px;flex:1 1 auto;min-height:0}
#modal-expense .exp-alert--bad{margin:0 0 14px;padding:10px 12px;border:1px solid #f3b7b1;border-radius:8px;background:#fde7e5;color:#9b1c1c;font-size:14px;font-weight:600}
#modal-expense .exp-alert--bad[hidden]{display:none !important}
#modal-expense .form-group{display:flex;flex-direction:column;gap:6px;margin:0 0 16px}
#modal-expense .form-group>label{display:flex;align-items:center;gap:6px;font-size:14px;font-weight:600;color:#102a1d;margin:0}
#modal-expense .exp-opt{line-height:18px;padding:0 8px;border-radius:999px;background:#eef2ef;font-size:11.5px;font-weight:600;color:#5f7468}
#modal-expense .form-control,#modal-expense .select-shell>.select-trigger{width:100%;min-height:46px;height:46px;padding:0 12px;border:1px solid #dfe6e1;border-radius:8px;background:#fff;color:#102a1d;font:inherit;font-size:15px;box-shadow:none}
#modal-expense .form-control:focus,#modal-expense .select-shell>.select-trigger:focus{outline:none;border-color:#14532d;box-shadow:0 0 0 3px rgba(20,83,45,.16)}
#modal-expense .exp-affix{display:flex;align-items:stretch;height:46px;border:1px solid #dfe6e1;border-radius:8px;background:#fff;overflow:hidden}
#modal-expense .exp-affix:hover{border-color:#b5c8bc}
#modal-expense .exp-affix:focus-within{border-color:#14532d;box-shadow:0 0 0 3px rgba(20,83,45,.16)}
#modal-expense .exp-pre{display:flex;align-items:center;flex:none;padding:0 12px;background:#f4f6f5;border-right:1px solid #e4ebe6;color:#5f7468;font-size:14px;font-weight:600}
#modal-expense .exp-affix input{flex:1 1 auto;min-width:0;height:44px;min-height:44px;padding:0 12px;border:0 !important;border-radius:0 !important;box-shadow:none !important;text-align:right;font-size:15px;font-weight:600;color:#102a1d;font-variant-numeric:tabular-nums}
#modal-expense .exp-source-native{position:absolute !important;width:1px !important;height:1px !important;overflow:hidden !important;clip-path:inset(50%) !important;opacity:0;pointer-events:none}
#modal-expense .exp-seg{display:grid;grid-auto-flow:column;grid-auto-columns:1fr;gap:4px;padding:4px;border-radius:10px;background:#eef2ef}
#modal-expense .exp-seg button{min-height:38px;height:38px;padding:0 12px;border:0;border-radius:7px;background:transparent;color:#3c4a42;font:inherit;font-size:14px;font-weight:600;cursor:pointer;box-shadow:none}
#modal-expense .exp-seg button[aria-checked=true]{background:#fff;color:#14532d;font-weight:700;box-shadow:0 1px 2px rgba(16,42,29,.12)}
#modal-expense .exp-seg button:focus-visible{outline:2px solid #14532d;outline-offset:1px}
#modal-expense .exp-balance-hint{font-size:12.5px;line-height:17px;color:#5f7468;margin:0}
#modal-expense .exp-balance-hint strong{color:#102a1d;font-variant-numeric:tabular-nums}
#modal-expense .modal-footer{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:16px 24px 20px;border-top:1px solid #eef2ef;background:#fff;flex-shrink:0}
#modal-expense .modal-footer .btn{display:inline-flex;align-items:center;justify-content:center;min-height:46px;height:46px;padding:0 20px;border-radius:10px;font:inherit;font-size:15px;font-weight:700;cursor:pointer;box-shadow:none}
#modal-expense .modal-footer .btn.btn-secondary{min-width:120px;border:1px solid #d5ded8;background:#fff;color:#14532d}
#modal-expense .modal-footer .btn.btn-secondary:hover{border-color:#14532d;background:#f1f7f3}
#modal-expense .modal-footer .btn:not(.btn-secondary){min-width:200px;border:0;background:#c8f26b;color:#14532d}
#modal-expense .modal-footer .btn:not(.btn-secondary):hover{background:#b8e557}
#modal-expense .modal-footer .btn:disabled{opacity:.6;cursor:progress}

@media(prefers-reduced-motion:reduce){
 #modal-expense.modal-overlay,#modal-expense .modal-dialog{transition:none!important}
}
@media(max-width:900px){
 :is(.expenses-page,#exp#exp) .exp-facts{grid-template-columns:repeat(2,minmax(0,1fr))}
 :is(.expenses-page,#exp#exp) .exp-facts>.exp-fact--primary{grid-column:1/-1}
 :is(.expenses-page,#exp#exp) .exp-filter:not([data-filter-ready]){grid-template-columns:repeat(2,minmax(0,1fr))}
 :is(.expenses-page,#exp#exp) .exp-filter:not([data-filter-ready]) .exp-filter-actions{grid-column:1/-1}
}
@media(max-width:760px){
 :is(.expenses-page,#exp#exp) .exp-card{padding:16px !important}
 :is(.expenses-page,#exp#exp) .exp-summary-head .exp-btn{width:100%}
 :is(.expenses-page,#exp#exp) .exp-filter:not([data-filter-ready]){grid-template-columns:1fr}
 :is(.expenses-page,#exp#exp) .exp-filter:not([data-filter-ready]) .exp-filter-actions{grid-column:auto}
 :is(.expenses-page,#exp#exp) .exp-filter:not([data-filter-ready]) .exp-filter-actions>.exp-btn{flex:1 1 0}
 #modal-expense.modal-overlay{padding:8px}
 #modal-expense .modal-dialog{width:calc(100vw - 16px);max-height:calc(100dvh - 16px)}
 #modal-expense .modal-header{padding:14px 12px 14px 18px}
 #modal-expense .modal-title{font-size:20px;line-height:26px}
 #modal-expense .modal-close-btn{width:44px;height:44px;min-width:44px;min-height:44px}
 #modal-expense .modal-body{padding:16px 18px}
 #modal-expense .modal-footer{padding:12px 18px 16px}
 #modal-expense .modal-footer .btn.btn-secondary{min-width:0}
 #modal-expense .modal-footer .btn:not(.btn-secondary){min-width:0;flex:1 1 auto}
}
</style>