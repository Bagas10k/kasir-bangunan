{{--
    Kapsul navigasi cepat mobile (4 halaman per peran).
    Admin: Ringkasan, Masuk (penerimaan), Biaya (pengeluaran), Kasir.
    Kasir: Riwayat, Tagihan, Bayar (status pembayaran), Kasir.
    - Hanya berpindah halaman (tidak membuka form otomatis) agar animasi kapsul terasa mulus.
    - Satu cangkang pil solid bersama (.mqa-shell) menopang kapsul navigasi dan bar keranjang mobile:
      cangkang tidak pernah memudar/berkedip, hanya melebar/menyempit mulus sambil isi di dalamnya berganti halus.
    - Di halaman Kasir: kapsul tetap tampil saat keranjang kosong (animasi 4 tab penuh).
      Saat keranjang terisi, cangkang melebar mulus menjadi bar keranjang 1 baris dengan tombol bulat
      di sisi kiri (.pm-nav-toggle) untuk memunculkan kapsul navigasi kembali sementara (mqa-peek).
--}}
<style>
.mqa-scrim{position:fixed;left:0;right:0;bottom:0;height:96px;z-index:55;pointer-events:none;background:linear-gradient(to bottom,rgb(246 248 247 / 0) 0%,rgb(246 248 247 / .18) 20%,rgb(246 248 247 / .58) 44%,rgb(246 248 247 / .9) 72%,var(--dna-canvas,#f6f8f7) 100%)}
.mqa-scrim[hidden],.mqa-shell[hidden],.mqa-dock[hidden]{display:none!important}
.mqa-shell,.mqa-dock{--mqa-btn:48px;--mqa-active:132px;--mqa-gap:6px;--mqa-pad:6px;--mqa-step:calc(var(--mqa-btn) + var(--mqa-gap));--mqa-expand:calc(var(--mqa-active) - var(--mqa-btn));--mqa-w:calc(var(--mqa-active) + 3 * var(--mqa-btn) + 3 * var(--mqa-gap) + 2 * var(--mqa-pad));--mqa-h:calc(var(--mqa-btn) + 2 * var(--mqa-pad))}
.mqa-shell{position:fixed;left:50%;bottom:calc(12px + env(safe-area-inset-bottom));z-index:59;width:min(var(--mqa-w),calc(100vw - 24px));height:var(--mqa-h);border-radius:999px;background:var(--ui-side,#0f2a1d);box-shadow:0 14px 32px -12px rgba(9,30,18,.52),0 4px 12px -4px rgba(9,30,18,.22);transform:translate3d(-50%,0,0);transition:width 320ms cubic-bezier(.22,1,.36,1),transform var(--ui-sidebar-duration) var(--ui-ease-out),opacity var(--ui-sidebar-duration) var(--ui-ease-out),visibility 0s;pointer-events:none;contain:strict}
.mqa-shell.is-cart{width:min(356px,calc(100vw - 24px))}
.mqa-dock{position:fixed;left:0;right:0;bottom:0;z-index:60;display:flex;justify-content:center;padding:0 var(--ui-space-group) calc(12px + env(safe-area-inset-bottom));pointer-events:none;contain:layout style}
.mqa-dock,.mqa-scrim{transition:transform var(--ui-sidebar-duration) var(--ui-ease-out),opacity var(--ui-sidebar-duration) var(--ui-ease-out),visibility 0s}
.mqa{position:relative;inline-size:var(--mqa-w);block-size:var(--mqa-h);max-inline-size:calc(100vw - 24px);border-radius:999px;background:transparent;font-family:var(--font-body,system-ui,sans-serif);box-sizing:border-box;isolation:isolate;pointer-events:auto;visibility:visible;transition:visibility 0s linear 0s;contain:layout size style}
.mqa-dock.is-cart .mqa{pointer-events:none;visibility:hidden;transition:visibility 0s linear 180ms}
.mqa-track{position:absolute;inset:var(--mqa-pad);pointer-events:none;z-index:0;opacity:1;transform:translate3d(0,0,0);transition:opacity 200ms ease 55ms,transform 280ms cubic-bezier(.22,1,.36,1) 30ms;will-change:transform,opacity}
.mqa-dock.is-cart .mqa-track{opacity:0;transform:translate3d(20px,0,0);transition:opacity 140ms ease,transform 220ms cubic-bezier(.22,1,.36,1)}
.mqa-indicator{position:absolute;left:0;top:0;inline-size:var(--mqa-active);block-size:var(--mqa-btn);transform:translate3d(calc(var(--mqa-slot,0) * var(--mqa-step)),0,0);opacity:1;transition:transform 340ms cubic-bezier(.22,1,.36,1),opacity 180ms ease;will-change:transform}
.mqa:not(.has-active) .mqa-indicator{opacity:0;transform:translate3d(calc(var(--mqa-slot,0) * var(--mqa-step)),0,0) scale(.86)}
.mqa-spot{display:block;inline-size:100%;block-size:100%;border-radius:999px;background:var(--ui-lime,#c8f26b)}
.mqa button{position:absolute;top:var(--mqa-pad);left:calc(var(--mqa-pad) + var(--idx,0) * var(--mqa-step));z-index:1;display:block;inline-size:var(--mqa-btn);block-size:var(--mqa-btn);min-inline-size:var(--mqa-btn);min-block-size:var(--mqa-btn);padding:0;border:0;border-radius:999px;background:transparent;color:#e7f3ec;font:650 13.5px/1 var(--font-body,system-ui,sans-serif);cursor:pointer;touch-action:manipulation;white-space:nowrap;opacity:1;transform:translate3d(0,0,0);transition:transform 320ms cubic-bezier(.22,1,.36,1),opacity 200ms ease 55ms,color 200ms ease;will-change:transform,opacity;box-sizing:border-box}
.mqa button.is-after{transform:translate3d(var(--mqa-expand),0,0)}
.mqa:not(.has-active) button{transform:translate3d(calc(var(--idx,0) * (var(--mqa-expand) / 3)),0,0)}
.mqa button.is-active{inline-size:var(--mqa-active);color:var(--ui-side,#0f2a1d);z-index:2}
.mqa-dock.is-cart .mqa button:not(.is-active){opacity:0;transform:translate3d(-12px,0,0) scale(.9);transition:opacity 120ms ease,transform 210ms cubic-bezier(.22,1,.36,1)}
.mqa-dock.is-cart .mqa button.is-active{opacity:0;transform:translate3d(20px,0,0);transition:opacity 140ms ease,transform 220ms cubic-bezier(.22,1,.36,1)}
.mqa-bg{position:absolute;left:0;top:0;inline-size:var(--mqa-btn);block-size:var(--mqa-btn);border-radius:999px;background:rgb(255 255 255 / .12);pointer-events:none;transition:opacity 200ms ease,transform 260ms cubic-bezier(.22,1,.36,1),background-color 160ms ease}
.mqa button:hover:not(.is-active) .mqa-bg{background:rgb(255 255 255 / .18)}
.mqa button.is-active .mqa-bg{opacity:0;transform:scale(.82)}
.mqa-ico{position:absolute;left:0;top:0;inline-size:var(--mqa-btn);block-size:var(--mqa-btn);display:inline-flex;align-items:center;justify-content:center;pointer-events:none;transition:transform 260ms cubic-bezier(.22,1,.36,1)}
.mqa-ico svg{width:20px;height:20px;flex:none;pointer-events:none}
.mqa button.is-active .mqa-ico{transform:translate3d(-2px,0,0) scale(1.03)}
.mqa button:active .mqa-ico,.mqa button:active .mqa-bg{transform:scale(.92)}
.mqa-label{position:absolute;left:calc(var(--mqa-btn) - 8px);top:0;bottom:0;display:inline-flex;align-items:center;pointer-events:none;opacity:0;transform:translate3d(-8px,0,0);transition:opacity 140ms ease,transform 260ms cubic-bezier(.22,1,.36,1)}
.mqa button.is-active .mqa-label{opacity:1;transform:translate3d(0,0,0);transition-delay:70ms,30ms}
.mqa-badge{position:absolute;top:50%;right:12px;transform:translateY(-50%);display:inline-flex;align-items:center;justify-content:center;min-inline-size:18px;block-size:18px;padding:0 5px;border-radius:999px;background:var(--ui-side,#0f2a1d);color:var(--ui-lime,#c8f26b);font-size:11px;font-weight:800;line-height:1;font-variant-numeric:tabular-nums;pointer-events:none}
.mqa button:not(.is-active) .mqa-badge{top:4px;right:auto;left:29px;transform:none;min-inline-size:16px;block-size:16px;padding:0 4px;background:var(--ui-lime,#c8f26b);color:var(--ui-side,#0f2a1d);font-size:10px}
.mqa-badge[hidden]{display:none!important}
.mqa button:focus-visible{outline:2px solid var(--ui-lime,#c8f26b);outline-offset:3px}
@media screen and (max-width:360px){
 .mqa-shell,.mqa-dock{--mqa-btn:44px;--mqa-active:118px;--mqa-gap:5px}
 .mqa button{font-size:12.5px}
 .mqa button:not(.is-active) .mqa-badge{left:26px}
 .mqa-badge{right:10px}
}
@media screen and (max-width:900px){
 html:has(body.mqa-on){scroll-padding-bottom:calc(112px + env(safe-area-inset-bottom))}
 html body.mqa-on>main.wrap.main-content,html body.mqa-on>main.main-content{padding-bottom:calc(112px + env(safe-area-inset-bottom))!important}
 body:has(dialog[open]) .mqa-dock,body:has(dialog[open]) .mqa-scrim,body:has(dialog[open]) .mqa-shell,
 body:has(.modal-overlay.open) .mqa-dock,body:has(.modal-overlay.open) .mqa-scrim,body:has(.modal-overlay.open) .mqa-shell,
 body:has(main :is(input,select,textarea):focus) .mqa-dock,body:has(main :is(input,select,textarea):focus) .mqa-scrim,body:has(main :is(input,select,textarea):focus) .mqa-shell,
 body.pm-cart-open .mqa-dock,body.pm-cart-open .mqa-scrim,body.pm-cart-open .mqa-shell{display:none!important}
 body.sidebar-open :is(.mqa-dock,.mqa-scrim,.mqa-shell){
   opacity:0;visibility:hidden;pointer-events:none;transition-delay:0s,0s,var(--ui-sidebar-duration)
 }
 body.sidebar-open :is(.mqa-dock,.mqa-scrim){transform:translateY(100%)}
 body.sidebar-open .mqa-shell{transform:translate3d(-50%,calc(100% + var(--ui-space-group) + env(safe-area-inset-bottom)),0);transition-delay:0s,0s,0s,var(--ui-sidebar-duration)}
 body.sidebar-open .mqa{pointer-events:none}
}
@media(width > 900px){.mqa-dock,.mqa-scrim,.mqa-shell{display:none!important}}
@media(prefers-reduced-motion:reduce){.mqa-shell,.mqa-dock,.mqa-scrim,.mqa,.mqa-track,.mqa button,.mqa-indicator,.mqa-label,.mqa-bg,.mqa-ico{transition:none!important}}
@media print{.mqa-dock,.mqa-scrim,.mqa-shell{display:none!important}}
</style>
<div class="mqa-scrim" aria-hidden="true" hidden></div>
<div class="mqa-shell" aria-hidden="true" hidden></div>
<div class="mqa-dock" hidden><div class="mqa" id="mobile-quick-actions" role="navigation" aria-label="Navigasi cepat mobile"><div class="mqa-track" aria-hidden="true"><span class="mqa-indicator"><span class="mqa-spot"></span></span></div></div></div>
<script>
(()=>{
'use strict';
const bar=document.getElementById('mobile-quick-actions');
const nav=document.querySelector('.nav');
if(!bar||!nav)return;
const dock=bar.parentElement,scrim=document.querySelector('.mqa-scrim'),shell=document.querySelector('.mqa-shell'),spot=bar.querySelector('.mqa-spot');
const role=nav.dataset.role==='admin'?'admin':'cashier';
const icons={
 home:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9.5"/><path d="M9.5 21v-6h5v6"/></svg>',
 box:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16v12H4z"/><path d="M8 7V5h8v2"/><path d="M12 10v5m-3-3 3 3 3-3"/></svg>',
 wallet:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 9h18M16 13h3"/></svg>',
 cart:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.6 12.4a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21 7H6"/></svg>',
 list:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/></svg>',
 receipt:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/></svg>'
};
const actions=role==='admin'?[
 {key:'home',label:'Ringkasan',aria:'Halaman Ringkasan',task:'ringkasan',icon:'home'},
 {key:'receipt',label:'Masuk',aria:'Halaman Penerimaan barang',task:'penerimaan',icon:'box'},
 {key:'expense',label:'Biaya',aria:'Halaman Pengeluaran operasional',task:'pengeluaran',icon:'wallet'},
 {key:'pos',label:'Kasir',aria:'Halaman Kasir',task:'kasir',icon:'cart'}
]:[
 {key:'history',label:'Riwayat',aria:'Halaman Riwayat penjualan',task:'riwayat-penjualan',icon:'list'},
 {key:'invoice',label:'Tagihan',aria:'Halaman Tagihan',task:'tagihan',icon:'wallet'},
 {key:'status',label:'Bayar',aria:'Halaman Status pembayaran',task:'persetujuan',icon:'receipt'},
 {key:'pos',label:'Kasir',aria:'Halaman Kasir',task:'kasir',icon:'cart'}
];
const mobile=()=>matchMedia('(max-width:900px)').matches;
const reduced=()=>matchMedia('(prefers-reduced-motion: reduce)').matches;
const EASE='cubic-bezier(.22,1,.36,1)';
let posBadge=null;
const buttons=[];
for(const [idx,action] of actions.entries()){
 const button=document.createElement('button');
 button.type='button';button.dataset.quickAction=action.key;button.setAttribute('aria-label',action.aria);
 button.style.setProperty('--idx',idx);
 button.innerHTML='<span class="mqa-bg" aria-hidden="true"></span><span class="mqa-ico" aria-hidden="true">'+(icons[action.icon]||'')+'</span><span class="mqa-label"></span>'+(action.task==='kasir'?'<span class="mqa-badge" hidden></span>':'');
 button.querySelector('.mqa-label').textContent=action.label;
 if(action.task==='kasir')posBadge=button.querySelector('.mqa-badge');
 button.addEventListener('click',()=>navigate(action));
 bar.append(button);
 buttons.push(button);
}
let activeTask='', activeIndex=-1, peeking=false, landing=false, landingTimer=null, cartCount=0;
function clearLanding(){
 if(landingTimer){clearTimeout(landingTimer);landingTimer=null;}
 if(landing){landing=false;document.body.classList.remove('mqa-landing');}
}
function applySlot(nextIndex,animate){
 const hasActive=nextIndex>=0;
 bar.classList.toggle('has-active',hasActive);
 if(hasActive){bar.style.setProperty('--mqa-slot',nextIndex);}
 if(animate&&hasActive&&activeIndex>=0&&nextIndex!==activeIndex&&!reduced()&&spot.animate){
  spot.getAnimations().forEach(a=>a.cancel());
  spot.animate(
   [{transform:'scaleX(1) scaleY(1)'},{transform:'scaleX(1.06) scaleY(.95)',offset:.4},{transform:'scaleX(1) scaleY(1)'}],
   {duration:340,easing:EASE}
  );
 }
 activeIndex=nextIndex;
 for(const [idx,button] of buttons.entries()){
  const on=idx===nextIndex;
  button.classList.toggle('is-active',on);
  button.classList.toggle('is-after',hasActive&&idx>nextIndex);
  if(on)button.setAttribute('aria-current','page');else button.removeAttribute('aria-current');
 }
}
function syncCartBadge(){
 if(!posBadge)return;
 posBadge.textContent=String(cartCount);
 posBadge.hidden=cartCount<=0;
}
function setPeek(next){
 clearLanding();
 peeking=Boolean(next);
 document.body.classList.toggle('mqa-peek',peeking);
 const toggle=document.querySelector('.pm-nav-toggle');
 if(toggle)toggle.setAttribute('aria-expanded',String(peeking));
 sync();
}
function sync(){
 const task=document.body.dataset.activeTask||'';
 const hasCart=document.body.classList.contains('pm-has-cart');
 if(!hasCart||(activeTask==='kasir'&&task!=='kasir')){clearLanding();}
 if(task!=='kasir'||!hasCart){
  if(peeking){peeking=false;document.body.classList.remove('mqa-peek');const t=document.querySelector('.pm-nav-toggle');if(t)t.setAttribute('aria-expanded','false');}
 }
 const mobileShell=mobile()&&task!=='';
 const showCapsule=mobileShell&&(task!=='kasir'||!hasCart||peeking||landing);
 const isCartMode=mobileShell&&!showCapsule;
 const wasVisible=!dock.hidden&&!dock.classList.contains('is-cart');
 dock.hidden=!mobileShell;
 dock.classList.toggle('is-cart',isCartMode);
 bar.inert=isCartMode||document.body.classList.contains('sidebar-open');
 if(scrim)scrim.hidden=!mobileShell;
 if(shell){shell.hidden=!mobileShell;shell.classList.toggle('is-cart',isCartMode);}
 document.body.classList.toggle('mqa-on',mobileShell);
 if(activeTask!==task){if(activeTask!=='')window.scrollTo(0,0);activeTask=task;}
 const nextIndex=actions.findIndex(item=>item.task===task);
 applySlot(nextIndex,wasVisible&&showCapsule);
 syncCartBadge();
}
function navigate(action){
 if(!mobile()||!action.task)return;
 const task=document.body.dataset.activeTask||'';
 const hasCart=document.body.classList.contains('pm-has-cart');
 if(action.task==='kasir'&&task==='kasir'&&hasCart&&peeking){
  setPeek(false);
  return;
 }
 const nextIndex=actions.findIndex(item=>item.key===action.key);
 applySlot(nextIndex,true);
 if(task!==action.task){
  // Jika menuju Kasir saat keranjang sudah terisi, beri jeda singkat agar pil limau mendarat dulu baru cangkang melebar mulus jadi bar keranjang.
  if(action.task==='kasir'&&hasCart&&!reduced()){
   clearLanding();
   landing=true;
   document.body.classList.add('mqa-landing');
   landingTimer=setTimeout(()=>{clearLanding();sync();},240);
  }else{
   clearLanding();
  }
  requestAnimationFrame(()=>{location.hash='#'+action.task;});
 }
}
const checkoutForm=document.querySelector('#form-checkout');
checkoutForm?.addEventListener('kasir:cart',event=>{
 const nextCount=Number(event.detail?.count||0);
 const changed=nextCount!==cartCount;
 cartCount=nextCount;
 syncCartBadge();
 if(changed&&peeking&&cartCount>0){
  setPeek(false);
 }else{
  sync();
 }
});
document.addEventListener('mqa:peek',()=>{
 if(!mobile()||document.body.dataset.activeTask!=='kasir')return;
 setPeek(true);
});
document.addEventListener('keydown',event=>{
 if(event.key!=='Escape'||!peeking||document.body.classList.contains('pm-cart-open')||document.querySelector('dialog[open]'))return;
 event.preventDefault();
 setPeek(false);
 document.querySelector('.pm-nav-toggle')?.focus({preventScroll:true});
});
new MutationObserver(sync).observe(document.body,{attributes:true,attributeFilter:['data-active-task','class']});
addEventListener('resize',sync);
addEventListener('hashchange',sync);
sync();
})();
</script>
