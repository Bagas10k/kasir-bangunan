{{-- Gaya + perilaku halaman Pengawasan · Ringkasan (v2.admin-summary). Token warna tb01: hijau #14532D/#102A1D, lime #C8F26B, bingkai #E4EBE6, teks redup #5F7468. --}}
<style id="admin-summary-dna">
html body[data-active-task=ringkasan] .ui-page-header{position:absolute !important;width:1px;height:1px;margin:0;padding:0;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
#admin-overview.as-page{display:flex;flex-direction:column;gap:16px;margin:0 !important;padding:0 !important;border:0 !important;background:none !important;box-shadow:none !important;font-variant-numeric:tabular-nums}
#admin-overview:not([data-as-period=today]) [data-period=today],#admin-overview:not([data-as-period="7d"]) [data-period="7d"],#admin-overview:not([data-as-period="30d"]) [data-period="30d"]{display:none !important}
#admin-overview .as-mob,#admin-overview .as-tile.as-mob-tile,#admin-overview .as-more-btn,#admin-overview .as-fold-val{display:none}
#admin-overview .as-top{display:flex;align-items:center;justify-content:space-between;gap:16px}
#admin-overview .as-stamp{margin:0;font-size:13px;color:#5F7468}
#admin-overview .as-seg{display:inline-flex;gap:2px;padding:4px;border-radius:10px;background:#EAF0EC}
html body #admin-overview .as-seg button{min-height:38px !important;height:38px;padding:0 24px !important;border:0 !important;border-radius:8px !important;background:transparent !important;color:#3C4A42 !important;font-size:14px !important;font-weight:600 !important;box-shadow:none !important;transition:background-color .18s,color .18s}
html body #admin-overview .as-seg button[aria-checked=true]{background:#fff !important;color:#14532D !important;font-weight:700 !important;box-shadow:0 1px 2px rgba(16,42,29,.12) !important}
html body #admin-overview .as-seg button:focus-visible{outline:2px solid #14532D;outline-offset:2px}
html body #admin-overview .as-card{position:relative;margin:0 !important;padding:20px !important;border:1px solid #E4EBE6 !important;border-radius:14px !important;background:#fff !important;box-shadow:none !important}
html body #admin-overview .as-card h2{margin:0 !important;font-size:18px !important;line-height:24px !important;font-weight:700 !important;color:#102A1D !important;letter-spacing:0}
#admin-overview .as-sub{display:block;margin:2px 0 14px;font-size:13px;line-height:18px;font-weight:400;color:#5F7468}
#admin-overview .as-dot{display:inline-block;flex:none;width:7px;height:7px;margin-right:7px;border-radius:2px;vertical-align:middle;background:#A7B3AC}
#admin-overview .as-dot--warn{background:#D69E1E}
#admin-overview .as-dot--bad{background:#C0362C}
#admin-overview .as-empty{margin:8px 0;font-size:14px;color:#5F7468}
/* perlu ditindaklanjuti */
#admin-overview .as-todo-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin:0;padding:0;list-style:none}
#admin-overview .as-todo-item{display:grid;grid-template-columns:auto auto minmax(0,1fr) auto;grid-template-areas:"dot n label label" "hint hint hint go";column-gap:0;align-items:center;row-gap:10px;min-height:76px;padding:12px 14px;border:1px solid #E4EBE6;border-radius:10px;background:#fff;color:#102A1D;text-decoration:none;transition:border-color .18s,background-color .18s,transform .18s}
#admin-overview .as-todo-item:hover{border-color:#B9D3C2;background:#F7FAF8}
#admin-overview .as-todo-item:focus-visible{outline:2px solid #14532D;outline-offset:2px}
#admin-overview .as-todo-item .as-dot{grid-area:dot}
#admin-overview .as-todo-n{grid-area:n;margin-right:8px;font-size:20px;line-height:22px;font-weight:800}
#admin-overview .as-todo-label{grid-area:label;font-size:14px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#admin-overview .as-todo-hint{grid-area:hint;font-size:12.5px;color:#5F7468;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#admin-overview .as-todo-go{grid-area:go;justify-self:end;font-size:13px;font-weight:700;color:#14532D}
#admin-overview .as-ok{display:flex;align-items:center;gap:12px;margin-top:14px}
#admin-overview .as-ok-mark{display:grid;place-items:center;flex:none;width:36px;height:36px;border-radius:50%;background:#E4F0E8;color:#14532D}
#admin-overview .as-ok p{display:flex;flex-direction:column;margin:0}
#admin-overview .as-ok strong{font-size:15px;color:#14532D}
#admin-overview .as-ok span{font-size:13px;color:#5F7468}
/* ubin ringkasan */
#admin-overview .as-tiles{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
#admin-overview .as-tile{display:flex;flex-direction:column;gap:2px;min-width:0;padding:12px 14px;border:1px solid #E4EBE6;border-radius:12px;background:#F4F7F5}
#admin-overview .as-tile small{display:block;font-size:13px;font-weight:600;color:#5F7468}
#admin-overview .as-tile strong{font-size:21px;line-height:28px;font-weight:800;color:#102A1D;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#admin-overview .as-tile>span,#admin-overview .as-tile div>span{font-size:12.5px;color:#5F7468}
#admin-overview .as-tile--hero{border-color:#14532D;background:#14532D}
#admin-overview .as-tile--hero small{color:#C8F26B}
#admin-overview .as-tile--hero div{display:flex;flex-direction:column}
#admin-overview .as-tile--hero strong{font-size:25px;line-height:32px;color:#fff}
#admin-overview .as-tile--hero div>span{font-weight:600;color:#C8F26B}
/* grafik + terlaris */
#admin-overview .as-row2{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px}
#admin-overview .as-plot{position:relative;display:flex;align-items:stretch;gap:6px;height:190px;margin:24px 0 30px;border-bottom:1px solid #C9D4CD;background-image:linear-gradient(#E9EFEB 1px,transparent 1px);background-size:100% 33.333%}
#admin-overview .as-plot--7d{gap:clamp(12px,4%,40px);padding:0 2%}
#admin-overview .as-col{position:relative;flex:1;min-width:0}
#admin-overview .as-col i{position:absolute;left:50%;bottom:0;width:min(34px,78%);height:calc(var(--h) * 1%);border-radius:4px 4px 0 0;background:#3E9B6E;transform:translateX(-50%);transform-origin:bottom;animation:as-grow .5s cubic-bezier(.22,.8,.24,1) both;animation-delay:calc(var(--i) * 28ms)}
#admin-overview .as-col.is-peak i{background:#1B4332}
#admin-overview .as-col:hover i{filter:brightness(1.08)}
#admin-overview .as-col small{position:absolute;top:calc(100% + 9px);left:50%;transform:translateX(-50%);font-size:12px;color:#5F7468;white-space:nowrap}
@keyframes as-grow{from{transform:translateX(-50%) scaleY(0)}to{transform:translateX(-50%) scaleY(1)}}
#admin-overview .as-best-list{display:grid;grid-auto-flow:column;grid-template-rows:repeat(var(--rows),auto);grid-template-columns:repeat(2,minmax(0,1fr));column-gap:28px;margin:6px 0 0;padding:0;list-style:none}
#admin-overview .as-best-list li{display:grid;grid-template-columns:24px minmax(0,1fr) auto;align-items:center;gap:6px;min-height:46px;border-bottom:1px solid #EEF2EF}
#admin-overview .as-rank{font-size:12.5px;color:#7C8C83}
#admin-overview .as-name{font-size:14px;font-weight:600;color:#102A1D;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#admin-overview .as-best-list strong{font-size:13px;font-weight:700;color:#14532D;white-space:nowrap}
/* uang, pengiriman, stok */
#admin-overview .as-row3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;align-items:start}
html body #admin-overview .as-fold>summary{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin:0 !important;padding:0 !important;border:0 !important;background:none !important;list-style:none;cursor:default;min-height:0 !important;font:inherit}
html body #admin-overview .as-fold>summary::before{content:none !important;display:none !important}
html body #admin-overview .as-fold-h>*{border:0 !important;padding:0 !important}
#admin-overview .as-fold>summary::-webkit-details-marker{display:none}
#admin-overview .as-fold-h{display:flex;flex-direction:column;min-width:0}
#admin-overview .as-fold-h .as-sub{margin-bottom:6px}
#admin-overview .as-link{position:absolute;top:22px;right:20px;font-size:13px;font-weight:700;color:#14532D;text-decoration:none}
#admin-overview .as-link::after{content:' →'}
#admin-overview .as-link:hover{text-decoration:underline}
#admin-overview .as-lines{margin:0}
#admin-overview .as-lines>div{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:42px;border-bottom:1px solid #EEF2EF}
#admin-overview .as-lines>div:last-child{border-bottom:0}
#admin-overview .as-lines dt{display:block;font-size:14px;color:#3C4A42}
#admin-overview .as-lines dd{margin:0;font-size:14.5px;font-weight:700;color:#102A1D;white-space:nowrap}
#admin-overview .as-lines .is-warn dd{color:#9A6B00}
#admin-overview .as-lines .is-bad dd{color:#B42318}
#admin-overview .as-lines>div:not(.is-warn):not(.is-bad) .as-dot{display:none}
#admin-overview .as-status{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:4px 0 12px}
#admin-overview .as-status div{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:76px;padding:8px;border-radius:10px;text-align:center}
#admin-overview .as-status strong{font-size:24px;line-height:30px;font-weight:800}
#admin-overview .as-status span{font-size:12.5px;font-weight:600}
#admin-overview .as-status .is-ready{background:#FFF3CF;color:#6B4B00}
#admin-overview .as-status .is-sent{background:#E3ECF7;color:#1E4E8C}
#admin-overview .as-status .is-done{background:#E4F0E8;color:#14532D}
#admin-overview .as-alert{margin:0 0 6px;padding:10px 14px;border:1px solid #F1D68A;border-radius:8px;background:#FFF6D6;font-size:13px;font-weight:600;color:#6B4B00}
#admin-overview .as-watch{margin:0;padding:0;list-style:none}
#admin-overview .as-watch li{display:grid;grid-template-columns:auto minmax(0,1fr) auto minmax(56px,auto);align-items:center;gap:6px 10px;min-height:36px;border-bottom:1px solid #EEF2EF}
#admin-overview .as-watch li .as-dot{margin:0}
#admin-overview .as-watch strong{font-size:13.5px;font-weight:700;white-space:nowrap}
#admin-overview .as-watch .is-out strong{color:#B42318}
#admin-overview .as-watch .is-low strong{color:#9A6B00}
#admin-overview .as-watch small{justify-self:end;font-size:12px;color:#7C8C83;white-space:nowrap}
#admin-overview .as-foot{margin:12px 0 0;padding:10px 12px;border-radius:8px;background:#F4F7F5;font-size:13px;font-weight:600;color:#3C4A42}
@media (max-width:1180px){
  #admin-overview .as-row3{grid-template-columns:repeat(2,minmax(0,1fr))}
  #admin-overview .as-tiles{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media (max-width:980px){#admin-overview .as-row2{grid-template-columns:1fr}}
@media (max-width:760px){
  #admin-overview.as-page{gap:12px}
  #admin-overview .as-desk,#admin-overview .as-stamp-date{display:none !important}
  #admin-overview .as-mob{display:inline}
  #admin-overview .as-top{flex-direction:column-reverse;align-items:stretch;gap:8px}
  #admin-overview .as-seg{display:grid;grid-template-columns:repeat(3,1fr)}
  html body #admin-overview .as-seg button{padding:0 8px !important;min-height:44px !important;height:44px}
  html body #admin-overview .as-card{padding:16px !important;border-radius:12px !important}
  #admin-overview .as-todo-list{display:block}
  #admin-overview .as-todo-list li+li{border-top:1px solid #EEF2EF}
  #admin-overview .as-todo-item{grid-template-columns:auto minmax(0,1fr) auto;column-gap:0;grid-template-areas:"dot label go" ". hint go";row-gap:2px;min-height:52px;padding:8px 0;border:0;border-radius:0;background:none}
  #admin-overview .as-todo-item:hover{background:none}
  #admin-overview .as-todo-n{display:none}
  #admin-overview .as-todo-label{font-weight:700;white-space:normal}
  #admin-overview .as-todo-go{font-size:0;width:24px;height:24px;background:no-repeat center/18px url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233C4A42' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m9 6 6 6-6 6'/%3E%3C/svg%3E")}
  #admin-overview .as-tiles{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
  #admin-overview .as-tile--hero{grid-column:1 / -1}
  #admin-overview .as-tile.as-mob-tile{display:flex}
  #admin-overview .as-tile strong{font-size:18px;line-height:24px}
  #admin-overview .as-tile--hero strong{font-size:26px;line-height:32px}
  #admin-overview .as-row2,#admin-overview .as-row3{grid-template-columns:1fr;gap:12px}
  #admin-overview .as-plot{height:130px;margin:16px 0 28px}
  #admin-overview .as-plot--7d{gap:10px}
  #admin-overview .as-best-list{display:block}
  #admin-overview .as-best-list li.as-more{display:none}
  #admin-overview .is-all .as-best-list li.as-more{display:grid}
  html body #admin-overview .as-more-btn{display:block;width:100%;min-height:44px !important;margin:6px 0 0 !important;padding:0 !important;border:0 !important;background:transparent !important;color:#14532D !important;font-size:14px !important;font-weight:700 !important;box-shadow:none !important}
  html body #admin-overview .as-card.as-fold{padding:0 !important}
  html body #admin-overview .as-fold>summary{align-items:center;min-height:60px !important;padding:0 16px !important;cursor:pointer}
  #admin-overview .as-fold>summary::after{content:'';flex:none;width:20px;height:20px;background:no-repeat center/18px url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233C4A42' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m9 6 6 6-6 6'/%3E%3C/svg%3E");transition:transform .2s}
  #admin-overview .as-fold[open]>summary::after{transform:rotate(90deg)}
  #admin-overview .as-fold-h{flex:1}
  #admin-overview .as-fold-h .as-sub{display:none}
  #admin-overview .as-fold-val{display:block;font-size:14px;font-weight:700;color:#102A1D;white-space:nowrap}
  #admin-overview .as-fold-val.is-bad{color:#B42318}
  #admin-overview .as-fold-body{display:flex;flex-direction:column;padding:0 16px 14px;border-top:1px solid #EEF2EF}
  #admin-overview .as-link{position:static;order:9;align-self:flex-start;margin-top:10px;line-height:44px}
  #admin-overview .as-status{margin-top:12px}
}
@media (prefers-reduced-motion:reduce){
  #admin-overview .as-col i{animation:none}
  html body #admin-overview .as-seg button,#admin-overview .as-todo-item,#admin-overview .as-fold>summary::after{transition:none}
}
</style>
<script>
(()=>{
'use strict';
const page=document.querySelector('#admin-overview.as-page');if(!page)return;
const KEY='tb01-ringkasan-periode',mobile=matchMedia('(max-width:760px)');
const picks=Array.from(page.querySelectorAll('[data-as-pick]'));
function pick(period,save){
 if(!picks.some(b=>b.dataset.asPick===period))period='7d';
 page.dataset.asPeriod=period;
 for(const b of picks){const on=b.dataset.asPick===period;b.setAttribute('aria-checked',String(on));b.tabIndex=on?0:-1;}
 if(save)try{localStorage.setItem(KEY,period)}catch{}
}
page.querySelector('.as-seg').addEventListener('click',e=>{const b=e.target.closest('[data-as-pick]');if(b)pick(b.dataset.asPick,true);});
page.querySelector('.as-seg').addEventListener('keydown',e=>{
 const i=picks.findIndex(b=>b.getAttribute('aria-checked')==='true'),step=e.key==='ArrowRight'?1:e.key==='ArrowLeft'?-1:0;if(!step)return;
 e.preventDefault();const next=picks[(i+step+picks.length)%picks.length];pick(next.dataset.asPick,true);next.focus();
});
let saved=null;try{saved=localStorage.getItem(KEY)}catch{}
pick(saved||'7d',false);
// Kartu Uang/Pengiriman/Stok: selalu terbuka di desktop; di HP Uang terbuka, sisanya ringkas.
const folds=Array.from(page.querySelectorAll('[data-as-fold]'));
function layout(){folds.forEach((d,i)=>{d.open=!mobile.matches||i===0;});}
for(const d of folds)d.querySelector('summary').addEventListener('click',e=>{if(!mobile.matches)e.preventDefault();});
mobile.addEventListener('change',layout);layout();
// HP: produk terlaris 5 besar, tombol membuka 10 besar.
page.addEventListener('click',e=>{const b=e.target.closest('.as-more-btn');if(!b)return;const box=b.parentElement,all=box.classList.toggle('is-all');b.setAttribute('aria-expanded',String(all));b.textContent=all?'Tampilkan 5 besar':'Lihat '+box.querySelectorAll('.as-best-list li').length+' besar';});
})();
</script>
