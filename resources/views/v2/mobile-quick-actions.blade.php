{{--
    Pil aksi cepat mobile (maks 3).
    Admin: Buat orderan, Barang masuk, Pengeluaran.
    Kasir: Kasir, Riwayat, Status bayar.
    Tidak tampil di halaman Kasir (bar keranjang sudah menjadi aksi utama).
--}}
<style>
.mqa{position:fixed;z-index:70;inset-inline:var(--ui-space-group);bottom:calc(var(--ui-space-group) + env(safe-area-inset-bottom));max-inline-size:var(--ui-quick-actions-max-width);margin-inline:auto;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:4px;padding:8px;border-radius:999px;background:var(--ui-green-2);box-shadow:0 16px 40px -18px var(--ui-cart-backdrop);font-family:var(--font-body,system-ui,sans-serif);box-sizing:border-box}
.mqa[hidden]{display:none!important}
.mqa button{display:grid;place-items:center;justify-self:center;width:48px;height:48px;min-width:48px;min-height:48px;padding:0;border:0;border-radius:50%;background:transparent;color:var(--ui-green-soft);font:inherit;line-height:0;cursor:pointer;touch-action:manipulation;transition:transform .12s ease;box-sizing:border-box}
.mqa button svg{width:22px;height:22px;flex:none;pointer-events:none}
.mqa button.is-active{background:var(--ui-lime);color:var(--ui-green-2)}
.mqa button:active{transform:scale(.94)}
.mqa button:focus-visible{outline:2px solid var(--ui-lime);outline-offset:3px}
@media(max-width:900px){body.mqa-on main.main-content{padding-bottom:calc(84px + env(safe-area-inset-bottom))}}
@media(min-width:901px){.mqa{display:none!important}}
@media(prefers-reduced-motion:reduce){.mqa button{transition:none!important}}
@media print{.mqa{display:none!important}}
@media(max-width:900px){
 body:has(dialog[open]) #mobile-quick-actions,
 body:has(.modal-overlay.open) #mobile-quick-actions,
 body:has(main :is(input,select,textarea):focus) #mobile-quick-actions,
 body.pm-cart-open #mobile-quick-actions,
 body.sidebar-open #mobile-quick-actions{display:none!important}
}
</style>
<div class="mqa" id="mobile-quick-actions" role="toolbar" aria-label="Aksi cepat" hidden></div>
<script>
(()=>{
'use strict';
const bar=document.getElementById('mobile-quick-actions');
const nav=document.querySelector('.nav');
if(!bar||!nav)return;
const role=nav.dataset.role==='admin'?'admin':'cashier';
const icons={
 cart:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.6 12.4a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21 7H6"/></svg>',
 box:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16v12H4z"/><path d="M8 7V5h8v2"/><path d="M12 10v5m-3-3 3 3 3-3"/></svg>',
 wallet:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 9h18M16 13h3"/></svg>',
 list:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/></svg>',
 receipt:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/></svg>'
};
const actions=role==='admin'?[
 {key:'order',label:'Buat orderan',aria:'Buat orderan baru di Kasir',task:'kasir',focus:'#pos-product-search',icon:'cart'},
 {key:'receipt',label:'Barang masuk',aria:'Catat penerimaan barang masuk',task:'penerimaan',click:'[data-receipt-new]',icon:'box'},
 {key:'expense',label:'Pengeluaran',aria:'Catat pengeluaran operasional',task:'pengeluaran',click:'.expenses-page .exp-btn--primary',icon:'wallet'}
]:[
 {key:'pos',label:'Kasir',aria:'Buka Kasir',task:'kasir',focus:'#pos-product-search',icon:'cart'},
 {key:'history',label:'Riwayat',aria:'Riwayat penjualan',task:'riwayat-penjualan',icon:'list'},
 {key:'status',label:'Status bayar',aria:'Status pembayaran',task:'persetujuan',icon:'receipt'}
];
const mobile=()=>matchMedia('(max-width:900px)').matches;
const reduced=()=>matchMedia('(prefers-reduced-motion: reduce)').matches;
for(const action of actions){
 const button=document.createElement('button');
 button.type='button';button.dataset.quickAction=action.key;button.setAttribute('aria-label',action.aria);
 button.innerHTML=icons[action.icon]||'';
 button.addEventListener('click',()=>run(action,button));
 bar.append(button);
}
function sync(){
 const task=document.body.dataset.activeTask||'';
 const show=mobile()&&task!==''&&task!=='kasir';
 bar.hidden=!show;document.body.classList.toggle('mqa-on',show);
 const match=actions.find(item=>item.task===task),activeKey=match?match.key:actions[0].key;
 for(const button of bar.querySelectorAll('button')){
  const on=button.dataset.quickAction===activeKey;
  button.classList.toggle('is-active',on);
  if(on&&match)button.setAttribute('aria-current','page');else button.removeAttribute('aria-current');
 }
}
function waitFor(selector,timeout=3000){
 return new Promise(resolve=>{
  const ready=()=>{const el=document.querySelector(selector);return el&&el.getClientRects().length&&!el.closest('[inert]')?el:null;};
  const found=ready();if(found){resolve(found);return;}
  const observer=new MutationObserver(()=>{const el=ready();if(el){observer.disconnect();clearTimeout(timer);resolve(el);}});
  observer.observe(document.body,{childList:true,subtree:true,attributes:true,attributeFilter:['hidden','inert','class','style']});
  const timer=setTimeout(()=>{observer.disconnect();resolve(null);},timeout);
 });
}
async function run(action,button){
 if(!mobile()||button.dataset.busy)return;
 button.dataset.busy='1';
 try{
  if(action.task&&document.body.dataset.activeTask!==action.task)location.hash='#'+action.task;
  const selector=action.focus||action.click;
  if(!selector)return;
  const target=await waitFor(selector);
  if(!target)return;
  if(action.focus){target.focus({preventScroll:true});target.scrollIntoView({block:'center',behavior:reduced()?'auto':'smooth'});}
  else target.click();
 }finally{setTimeout(()=>delete button.dataset.busy,600);}
}
new MutationObserver(sync).observe(document.body,{attributes:true,attributeFilter:['data-active-task']});
addEventListener('resize',sync);
addEventListener('hashchange',sync);
sync();
})();
</script>
