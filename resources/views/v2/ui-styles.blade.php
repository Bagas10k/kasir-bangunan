<style>
/* ============================================================
   KASIR V2 — incumbent look & feel
   1. tokens  2. base  3. shell (sidebar / header)  4. nav
   5. components  6. panels  7. popup dialog  8. responsive
   ============================================================ */

/* 1. tokens */
:root{
  --green:#14532d;--green-dark:#123c26;--green-soft:#edf4ef;
  --line:#d7e2da;--ink:#172b20;--muted:#3c5545;--bg:#f3f6f4;
  --danger:#8d2118;--warn-ink:#624300;--warn-bg:#fff9e9;--warn-line:#dcc998;
  --ease:cubic-bezier(.16,1,.3,1);
  --sidebar-w:260px;
}

/* 2. base */
*{box-sizing:border-box}
html{-webkit-text-size-adjust:100%;scroll-padding-top:80px}
body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}
::selection{background:#cfe5d6;color:#123c26}
input,textarea{caret-color:var(--green)}
a{color:var(--green);text-underline-offset:4px}
button,input,select,textarea,a,summary{-webkit-tap-highlight-color:transparent}
h1,h2,h3,p{overflow-wrap:anywhere}
h1{font-size:1.2rem;line-height:1.2;letter-spacing:-.025em;margin:0;font-weight:750}
h2{font-size:1.1rem;line-height:1.35;margin:0 0 8px;letter-spacing:-.015em}
h3{font-size:.94rem;margin:0 0 8px}
p{margin:8px 0 16px}
button,summary,a,input,select,textarea{touch-action:manipulation}

/* 3. shell */
.wrap{max-width:1320px;margin:auto;padding:24px 32px}

/* 3a. left sidebar */
.workspace-sidebar{
  position:fixed;inset:0 auto 0 0;z-index:40;
  width:var(--sidebar-w);
  display:flex;flex-direction:column;
  background:#fff;border-right:1px solid var(--line);
  padding:18px 14px 16px;
  overflow-y:auto;overscroll-behavior:contain;
}
.sidebar-brand{display:flex;align-items:baseline;justify-content:space-between;gap:8px;padding:0 6px 14px}
.sidebar-brand strong{display:block;font-size:.95rem;font-weight:750;letter-spacing:-.015em;color:var(--green-dark)}
.sidebar-brand small{display:block;font-size:.72rem;line-height:1.35;color:var(--muted);margin-top:2px}
.sidebar-close{
  display:none;align-items:center;justify-content:center;flex:0 0 auto;
  width:44px;height:44px;min-height:44px;padding:0;
  border-radius:10px;background:#fff;color:var(--ink);border:1px solid var(--line);
}
.sidebar-close svg{width:20px;height:20px}
.sidebar-footer{
  margin:auto 0 0;padding:14px 6px 0;border-top:1px solid var(--line);
  display:flex;flex-direction:column;gap:6px;
  font-size:.78rem;line-height:1.45;color:var(--muted);
}
.sidebar-footer a{display:inline-flex;align-items:center;min-height:44px;font-weight:650;text-decoration:none}
.sidebar-overlay{display:none}

/* 3b. top header (dense) */
.top{background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:20}
.top>.wrap{max-width:none;padding:10px 32px;display:flex;flex-wrap:wrap;align-items:center;gap:8px 16px}
.top form{margin:0 0 0 auto}
.top .badge{font-size:.75rem;color:var(--muted);font-weight:650}
.top p{margin:0}
.top-context{display:flex;flex-direction:column;gap:1px;min-width:0;flex:1 1 auto}
.top-context strong{font-size:.92rem;font-weight:700;line-height:1.25;color:var(--ink)}
.top-context span{font-size:.75rem;line-height:1.25;color:var(--muted)}
.mobile-menu-toggle{
  display:none;align-items:center;justify-content:center;flex:0 0 auto;
  width:44px;height:44px;min-height:44px;padding:0;
  border-radius:10px;background:#fff;color:var(--ink);border:1px solid var(--line);
}
.mobile-menu-toggle svg{width:20px;height:20px}

/* 4. nav (vertical, incumbent white + palegreen active) */
.nav{display:flex;flex-direction:column;gap:2px;margin:4px 0 0;flex:1 0 auto}
.nav a{
  display:flex;align-items:center;gap:10px;
  min-height:44px;padding:9px 10px;margin:0;
  border:0;border-radius:10px;
  text-decoration:none;color:var(--muted);background:#fff;
  font-size:.84rem;line-height:1.3;font-weight:600;letter-spacing:-.005em;
  white-space:nowrap;
  transition:background-color 160ms var(--ease),color 160ms var(--ease);
}
.nav a svg{width:20px;height:20px;flex:0 0 20px;stroke-width:1.75;color:currentColor}
.nav a:hover{background:#f1f5f2;color:var(--ink)}
.nav a[aria-selected=true]{background:#e6efe9;color:var(--green);font-weight:700}
.nav a:active{transform:translateY(1px)}

/* 5. components */
.preview{font-size:.8rem;color:var(--warn-ink);background:var(--warn-bg);border:1px solid var(--warn-line);border-radius:10px;padding:8px 14px;margin:0 0 16px;font-weight:550}
.preview.preview-live{color:var(--muted);background:transparent;border:0;padding:0}
.work-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin:0 0 20px}
.work-heading h2{font-size:1.7rem;letter-spacing:-.035em;margin-bottom:4px}
.work-heading p{margin:0;max-width:64ch;color:var(--muted);font-size:.9rem}
.work-state{display:flex;flex-direction:column;align-items:flex-end;gap:4px;font-size:.8rem;white-space:nowrap}
.work-state strong{font-size:.88rem}
.work-state a{min-height:44px;display:inline-flex;align-items:center;font-weight:650}
.task-panel{min-width:0}
.task-panel[hidden]{display:none!important}
.task-panel:focus{outline:none}
@keyframes task-in{from{opacity:.4;transform:translateY(5px)}to{opacity:1;transform:translateY(0)}}

section.card{min-width:0;background:#fff;border:1px solid var(--line);border-radius:16px;padding:24px;margin:0 0 20px}
.section-title{font-size:1.1rem;margin:32px 0 12px}
.note{font-size:.85rem;line-height:1.55;color:var(--muted);margin:4px 0 16px;max-width:75ch}
.lbl,label{display:block;font-size:.83rem;line-height:1.4;font-weight:650;margin-bottom:7px;color:var(--ink)}
.field{min-width:0;margin-bottom:16px}
.row{display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end}
.row>.field{flex:1 1 160px;margin-bottom:0}
.row+p{margin-top:20px}

input,select,textarea,button{font:inherit;min-height:46px;max-width:100%;border:1px solid #9eafa4;border-radius:9px;padding:10px 12px;background:#fff;color:var(--ink)}
input:not([type=checkbox]):not([type=radio]):not([type=hidden]),select,textarea{width:100%;font-size:.95rem}
input[inputmode=decimal]{font-variant-numeric:tabular-nums}
input::placeholder,textarea::placeholder{color:#4a5d50;opacity:1}
input:hover,select:hover,textarea:hover{border-color:#647e6d}
input[readonly],input:disabled,select:disabled{background:#f1f5f2;color:var(--muted)}
input[type=checkbox]{min-height:22px;width:22px;height:22px;accent-color:var(--green);vertical-align:middle;margin-right:8px}
textarea{min-height:96px;resize:vertical}
button{background:var(--green);color:white;border-color:var(--green);border-radius:999px;padding:11px 20px;font-size:.875rem;font-weight:700;cursor:pointer;transition:background-color 160ms var(--ease),transform 120ms var(--ease)}
button:hover{background:#0d3c20}
button:active{transform:scale(.98)}
button.secondary{background:#fff;color:var(--green);border-color:#9eafa4}
button.secondary:hover{background:var(--green-soft)}
button:disabled{opacity:1;background:#e7ece8!important;border-color:#c9d3cc!important;color:#405346!important;cursor:not-allowed}
button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible,a:focus-visible,summary:focus-visible,[tabindex]:focus-visible{outline:3px solid var(--green);outline-offset:3px}

.err,.ok{display:block;border-radius:10px;padding:12px 14px;margin:14px 0 0;font-size:.875rem;line-height:1.5}
.err{color:var(--danger);background:#fff3f0;border:1px solid #d8ada6}
.ok{color:var(--green);background:var(--green-soft);border:1px solid #a9c2b1}
.err[hidden],.ok[hidden]{display:none!important}
[aria-busy=true]{cursor:progress}
.row,.stack,fieldset,.split{min-width:0}
fieldset{border:0;padding:0;margin:0}
ul.plain{list-style:none;padding:0;margin:0}
ul.plain>li{padding:18px 0;border-bottom:1px solid var(--line)}
ul.plain>li:first-child{padding-top:8px}
ul.plain>li:last-child{border:0}
.status{display:inline-flex;font-size:.76rem;font-weight:700;color:var(--green);background:var(--green-soft);border-radius:999px;padding:3px 9px;letter-spacing:0;text-transform:none}

/* tables — overflow stays inside .scroll */
.scroll{overflow:auto;max-width:100%;border:1px solid var(--line);border-radius:10px;scrollbar-color:#6b8474 #edf2ee;scrollbar-width:thin}
table{border-collapse:collapse;width:100%;font-size:.85rem}
th,td{text-align:left;padding:12px;border-bottom:1px solid var(--line);vertical-align:top}
th{background:#f4f7f5;font-size:.77rem;font-weight:700;white-space:nowrap;color:var(--green)}
td{font-variant-numeric:tabular-nums}
.doc-links{display:flex;flex-wrap:wrap;gap:8px}
.doc-links a,.scroll td>a{min-height:44px;display:inline-flex;align-items:center;white-space:nowrap;font-weight:650}

.item-row{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px;align-items:end;border-bottom:1px solid var(--line);padding:16px 0;min-width:0}
.item-row>*{min-width:0}
.item-row .field{margin:0}
.item-row .field:first-child{grid-column:1/-1}
.item-row .sr{position:static;width:auto;height:auto;margin:0 0 7px;overflow:visible;clip:auto;white-space:normal}
.item-row [data-remove]{justify-self:end;min-width:46px;padding:10px 16px}
.item-row:last-of-type{border:0}
.sr{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}

.technical{margin:16px 0;font-size:.8rem}
.technical summary{font-size:.8rem;color:var(--muted);min-height:44px;display:flex;align-items:center;cursor:pointer}
details>summary{min-height:44px;cursor:pointer;font-weight:650;padding-block:8px}
details[open]>summary{margin-bottom:12px}
pre.result{white-space:pre-wrap;word-break:break-word;border:1px solid var(--line);border-radius:10px;padding:14px;background:#f5f8f6;font-size:.8rem}
pre.result:empty{display:none}
#checkout-result:not(:empty){border-radius:12px;border:1px solid #a9c2b1;background:var(--green-soft);padding:16px;margin-top:16px}
#checkout-result a{display:inline-flex;align-items:center;min-height:44px;font-weight:700}

.totals{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;margin-top:20px}
.tile{border-top:1px solid var(--line);padding-top:16px;min-width:0}
.tile dl{margin:0}
.tile dl>div{display:flex;justify-content:space-between;gap:16px;padding:8px 0;border-bottom:1px solid #edf1ee}
.tile dt{font-size:.83rem;color:var(--muted)}
.tile dd{margin:0;font-weight:650;font-variant-numeric:tabular-nums;text-align:right}
.tile.pending{color:var(--warn-ink)}

.driver-form{display:grid;gap:12px;max-width:760px}
.driver-form label{margin:0}
.driver-form label input,.driver-form label select{margin-top:7px}
.driver-form button{justify-self:start}

.master-records{overflow:visible}
.master-records th{color:var(--green)!important}
.master-records td{padding:12px!important}
.master-records button{border-radius:999px!important;min-height:44px;padding:10px 18px!important}
.master-records summary{min-height:44px}
.master-records .mr-field input,.master-records .mr-field textarea{max-width:100%!important;min-height:46px;border-radius:9px!important;padding:10px 12px!important}
.master-records .mr-hint{color:var(--muted)!important}

#form-checkout>fieldset>p:has(>button[type=submit]){display:flex;justify-content:flex-end;border-top:1px solid var(--line);padding-top:20px;margin-top:24px}
.workspace-enhanced .split,.workspace-enhanced .stack:empty{display:contents}
.workspace-enhanced #admin{display:none}

/* 6. panels — list first, single column */
#panel-kasir{display:block;min-width:0}
#panel-kasir>section{width:100%}
#panel-kasir .scroll{overflow:auto;max-width:100%}
#panel-kasir table{display:table;width:100%}
#panel-kasir thead{display:table-header-group}
#panel-kasir tbody{display:table-row-group}
#panel-kasir tr{display:table-row}
#panel-kasir td,#panel-kasir th{display:table-cell}
#panel-shift{max-width:960px}

/* 7. popup dialog */
dialog.input-dialog{
  width:min(840px,calc(100vw - 32px));
  max-width:min(840px,calc(100vw - 32px));
  max-height:calc(100vh - 48px);
  max-height:calc(100dvh - 48px);
  margin:auto;padding:0;
  border:1px solid #cfdcd4;border-radius:16px;
  background:#fff;color:var(--ink);
  overflow:hidden;box-shadow:0 24px 64px rgb(18 60 38 / .18);
}
dialog.input-dialog[open]{display:flex;flex-direction:column;animation:popup-in 160ms var(--ease)}
dialog.input-dialog.popup-wide{width:min(1000px,calc(100vw - 32px));max-width:min(1000px,calc(100vw - 32px))}
dialog.input-dialog::backdrop{background:rgb(23 43 32 / .35)}
@keyframes popup-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}

.popup-heading{
  flex:0 0 auto;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;
  padding:24px;margin:0;border-bottom:1px solid var(--line);background:#fff;
}
.popup-heading h2{margin:0;font-size:1.05rem;line-height:1.35;letter-spacing:-.015em;color:var(--green-dark);font-weight:750}
.popup-close{
  flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;
  width:44px;height:44px;min-height:44px;padding:0;
  border-radius:10px;background:#fff;color:var(--muted);border:1px solid var(--line);
}
.popup-close svg{width:20px;height:20px}
.popup-close:hover{background:var(--green-soft);color:var(--green)}
.popup-content{flex:1 1 auto;min-height:0;overflow-y:auto;overscroll-behavior:contain;padding:24px;background:#fff}
.popup-content>:first-child{margin-top:0}
.popup-content>section.card{border:0;padding:0;margin:0;border-radius:0;box-shadow:none}
.popup-content>section.card>h2{display:none}
.popup-actions{margin-bottom:20px}
#panel-kasir>.popup-actions{justify-content:flex-end}
.master-records .popup-action{border-radius:999px;min-height:44px;padding:10px 16px}
.input-dialog .mr-field input,.input-dialog .mr-field textarea{max-width:none;width:100%;border-radius:9px}
.input-dialog .mr-field{margin:0 0 16px}
@media(max-width:700px){.work-heading{display:block}.work-state{margin-top:12px;max-width:none;display:flex;justify-content:space-between;align-items:center;white-space:normal}.work-state a{min-height:44px}.popup-heading,.popup-content,.popup-footer{padding:20px}.input-dialog .row>.field{flex-basis:100%}}

.popup-content p:last-child{margin-bottom:0}
.popup-footer{
  flex:0 0 auto;display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:12px;
  padding:24px;border-top:1px solid var(--line);background:#fff;
}
.popup-footer button{min-height:44px}
.popup-actions{display:flex;flex-wrap:wrap;align-items:center;gap:8px;min-width:0}
.popup-action{min-height:44px}

/* form rows inside a popup: product first line, qty/discount second */
.input-dialog .item-row{grid-template-columns:minmax(0,1fr) minmax(0,1fr);align-items:end}
.input-dialog .item-row .field:first-child{grid-column:1/-1}
.input-dialog .row>.field{flex:1 1 150px}
.input-dialog .scroll,.input-dialog table{max-width:100%}
.input-dialog .totals{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}
@media(min-width:700px){
  .input-dialog .item-row{grid-template-columns:minmax(0,1.6fr) minmax(0,1fr) auto;gap:14px}
  .input-dialog .item-row .field:first-child{grid-column:1/-1}
  .input-dialog .item-row [data-remove]{grid-column:3}
}

/* 8. responsive */
@media(min-width:901px){
  body{padding-left:var(--sidebar-w)}
  .mobile-menu-toggle{display:none}
  .sidebar-overlay{display:none!important}
  #panel-tagihan,#panel-pengiriman{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;align-items:start}
  #panel-tagihan>section,#panel-pengiriman>section{margin:0}
}

@media(max-width:900px){
  html{scroll-padding-top:76px}
  .workspace-sidebar{
    width:min(300px,84vw);
    transform:translateX(-100%);
    transition:transform 160ms var(--ease);
    box-shadow:0 0 0 1px rgb(18 60 38 / .04),0 18px 48px rgb(18 60 38 / .16);
  }
  body.sidebar-open .workspace-sidebar{transform:none}
  body.sidebar-open{overflow:hidden}
  .sidebar-close{display:inline-flex}
  .mobile-menu-toggle{display:inline-flex}
  .sidebar-overlay{
    display:none;position:fixed;inset:0;z-index:30;
    width:100%;height:100%;min-height:0;padding:0;border:0;border-radius:0;
    background:rgb(23 43 32 / .35);cursor:pointer;
  }
  body.sidebar-open .sidebar-overlay{display:block}
}

@media(max-width:700px){
  .wrap{padding:18px 16px 40px}
  .top>.wrap{padding:10px 16px;gap:8px 12px}
  .top .badge{font-size:.72rem}
  .top-context strong{font-size:.88rem}
  .work-heading{margin-bottom:16px;align-items:start}
  .work-heading h2{font-size:1.4rem}
  .work-heading p{font-size:.83rem}
  .work-state{font-size:.72rem;max-width:130px;white-space:normal}
  .preview{margin-bottom:14px;font-size:.76rem}
  section.card{padding:18px;border-radius:14px;margin-bottom:16px}
  .row{gap:12px}
  .row>.field{flex-basis:135px}
  .item-row{gap:12px}
  .totals{grid-template-columns:minmax(0,1fr)}
  .tile dd{font-size:.92rem}
  #form-checkout>fieldset>p:has(>button[type=submit])>button{width:100%}
  .workspace-enhanced #panel-kasir>section:last-child{margin-top:20px}

  .master-records table,.master-records tbody,.master-records tr,.master-records td{display:block;width:100%}
  .master-records thead{display:none}
  .master-records tr{padding:12px;border-bottom:1px solid var(--line)}
  .master-records td{border:0!important;padding:4px 0!important;overflow-wrap:anywhere}
  .master-records td[data-field]::before{content:attr(data-label);display:block;font-size:.72rem;color:var(--muted);font-weight:650}
  .master-records .mr-edit{padding-top:8px}
  .master-records .mr-edit input{font-size:16px!important}
  .task-panel .scroll:has(.master-records){border:0}

  dialog.input-dialog,
  dialog.input-dialog.popup-wide{width:calc(100vw - 24px);max-width:calc(100vw - 24px);max-height:calc(100dvh - 24px)}
  .popup-footer{justify-content:stretch}
  .popup-footer button{flex:1 1 auto}
  .input-dialog .item-row{grid-template-columns:repeat(2,minmax(0,1fr))}
}

@media(prefers-reduced-motion:reduce){
  *,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}
}
</style>
