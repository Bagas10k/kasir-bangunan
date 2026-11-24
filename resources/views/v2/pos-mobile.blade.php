{{--
    Keranjang mengambang semua layar; HP memakai lembar bawah, tablet/desktop memakai panel kanan.
    - Bar keranjang menempel di bawah (jenis, jumlah barang, total, Lihat keranjang).
    - Baris katalog yang sudah di keranjang menampilkan stepper − qty + (memakai tombol stepper baris keranjang yang sama).
    - HP: Keranjang dan Pembayaran tampil sebagai lembar bawah (bottom sheet).
    - Tablet (761-999px): Keranjang menjadi laci dari kanan, Pembayaran tetap dialog di tengah.
    - Katalog tetap lebar penuh; panel hanya dibuka saat diperlukan.
    - Pembayaran: sakelar "Antar dengan armada toko" (mengisi Cara penyerahan) dan pilihan nominal cepat untuk tunai.
    Lapisan tampilan saja: semua perubahan jumlah, hapus, dan nominal lewat kontrol asli + event yang sudah ada,
    sehingga perhitungan dan penyimpanan tetap milik pos-cashier-script. Angka ringkasan dibaca dari event kasir:cart.
--}}
<style>
.pm-bar,.pm-backdrop,.pm-step,.pm-sheet-close,.pm-sheet-sub,.pm-sub-rows,.pm-deliver,.pm-quick{display:none}
@media all{
  body.pm-has-cart main.main-content{padding-bottom:112px !important}
  .pm-bar{position:fixed;left:12px;right:12px;bottom:calc(12px + env(safe-area-inset-bottom));z-index:60;display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:12px;padding:10px 10px 10px 12px;border-radius:16px;background:#102A1D;box-shadow:0 18px 40px -12px rgba(4,12,8,.55);font-family:'Inter Kasir',-apple-system,'Segoe UI',sans-serif;animation:ui-rise .3s var(--ui-ease-out,ease-out)}
  .pm-bar[hidden]{display:none}
  body.pm-cart-open .pm-bar{visibility:hidden;pointer-events:none}
  .pm-bar-icon{position:relative;display:grid;place-items:center;width:46px;height:46px;border-radius:12px;background:#14532D;color:#C8F26B}
  .pm-bar-badge{position:absolute;top:-7px;right:-7px;display:grid;place-items:center;min-width:22px;height:22px;padding:0 5px;border-radius:999px;background:#C8F26B;color:#102A1D;font-size:12px;font-weight:800}
  .pm-bar-meta{display:flex;flex-direction:column;min-width:0}
  .pm-bar-meta small{font-size:13px;color:#B9CFC1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .pm-bar-meta strong{font-size:20px;line-height:26px;font-weight:800;color:#fff;font-variant-numeric:tabular-nums;white-space:nowrap}
  html body .pm-bar .pm-bar-open{min-height:48px !important;height:48px;padding:0 16px !important;border:0 !important;border-radius:12px !important;background:#C8F26B !important;color:#102A1D !important;font-size:15px !important;font-weight:800 !important;box-shadow:none !important;white-space:nowrap}
  /* stepper di baris katalog */
  html body #products-tbody tr[data-in-cart] [data-add-product]{display:none !important}
  html body #products-tbody tr[data-in-cart] .pm-step{display:inline-flex}
  .pm-step{align-items:center;height:40px;border-radius:10px;background:#14532D;overflow:hidden}
  html body #products-tbody .pm-step button{display:grid !important;place-items:center;width:40px !important;min-width:40px !important;height:40px !important;min-height:40px !important;padding:0 !important;border:0 !important;border-radius:0 !important;background:transparent !important;color:#C8F26B !important;font-size:20px !important;font-weight:700 !important;box-shadow:none !important}
  /* lembar bawah keranjang */
  .pm-backdrop{display:block;position:fixed;inset:0;z-index:calc(var(--ui-cart-layer) - 1);background:var(--ui-cart-backdrop);opacity:0;visibility:hidden;pointer-events:none;transition:opacity var(--ui-morph-duration) var(--ui-morph-easing)}
  body.pm-cart-open .pm-backdrop{visibility:visible;opacity:1;pointer-events:auto}
  body.pm-cart-closing .pm-backdrop{opacity:0}
  body.pm-cart-open{overflow:hidden}
  /* main memakai view-transition-name (= konteks tumpukan sendiri); dilepas saat lembar terbuka agar lembar berada di atas latar gelap. */
  body.pm-cart-open main.main-content{view-transition-name:none !important}
  /* Di atas kepala aplikasi (z 90) agar judul lembar tidak tertutup. */
  html body #kasir .pos-cart-pane{position:fixed !important;left:0;right:0;bottom:0;top:auto;z-index:120;max-height:92dvh;margin:0 !important;overflow:auto;overscroll-behavior:contain;transform:translateY(105%);visibility:hidden;transition:transform .32s cubic-bezier(.22,.8,.24,1),visibility 0s .32s}
  body.pm-cart-open #kasir .pos-cart-pane{transform:none;visibility:visible;transition:transform .32s cubic-bezier(.22,.8,.24,1)}
  html body #kasir .pos-cart-pane>section.card{position:relative;margin:0 !important;padding:22px 16px calc(18px + env(safe-area-inset-bottom)) !important;border-radius:20px 20px 0 0 !important;min-height:0}
  html body #kasir .pos-cart-pane>section.card::before{content:'';position:absolute;top:8px;left:50%;width:44px;height:4px;margin-left:-22px;border-radius:4px;background:rgba(255,255,255,.35)}
  html body #kasir .pos-cart-pane #h-checkout{margin:0 !important;padding-right:48px}
  .pm-sheet-sub{display:block;margin:2px 0 14px;font-size:13px;color:#D6E8DC}
  html body #kasir .pm-sheet-close{position:absolute;top:16px;right:12px;display:grid !important;place-items:center;width:40px !important;height:40px !important;min-height:40px !important;padding:0 !important;border:0 !important;border-radius:10px !important;background:transparent !important;color:#fff !important;font-size:26px !important;line-height:1 !important;box-shadow:none !important}
  html body #kasir .pos-cart-pane .cart-label{display:none !important}
  html :is(body#ui-dna-strong,body) #kasir #form-checkout .cart-table-scroll{max-height:none !important;overflow:visible !important;margin:0 0 12px !important;padding:0 !important;border:0 !important;background:transparent !important;box-shadow:none !important}
  html body #kasir .pos-cart-pane #items{display:flex;flex-direction:column;gap:10px;background:transparent !important;border:0 !important;box-shadow:none !important}
  /* Diskon baris yang terisi tampil sebagai pil "Diskon −Rp x" + "Ubah diskon" (rancangan). */
  html body #kasir .pos-cart-pane details.line-discount>summary[data-pm-disc]{display:flex !important;align-items:center;justify-content:space-between;gap:8px}
  html :is(body#ui-dna-strong,body) #kasir #form-checkout details.line-discount>summary[data-pm-disc]::before{content:none !important;display:none !important}
  .pm-chip{display:none}
  html body #kasir .pos-cart-pane summary[data-pm-disc] .pm-chip{display:inline-flex;align-items:center;height:28px;padding:0 10px;border:1px solid #CFE3D6;border-radius:999px;background:#E4F0E8;color:#14532D;font-size:13px;font-weight:700;white-space:nowrap}
  html body #kasir .pos-cart-pane details.line-discount>summary[data-pm-disc]{font-size:0 !important}
  html body #kasir .pos-cart-pane details.line-discount>summary[data-pm-disc]::after{content:'Ubah diskon';font-size:13px;font-weight:700;color:#14532D}
  html body #kasir .pos-cart-pane #items>.item-row{margin:0 !important;padding:14px !important;border:0 !important;border-radius:12px !important;background:#fff !important;box-shadow:none !important}
  html body #kasir .pos-cart-pane .summary-box{display:block !important;padding:14px 16px !important;border:0 !important;border-radius:12px !important;background:#F4F7F5 !important}
  html body #kasir .pos-cart-pane .summary-box .summary-total{display:flex !important;align-items:baseline;justify-content:space-between;gap:12px;margin:0 !important;padding:0 !important}
  html body #kasir .pos-cart-pane .summary-total span{font-size:16px !important;font-weight:800 !important;color:#102A1D !important}
  html body #kasir .pos-cart-pane #display-total{font-size:24px !important;line-height:30px !important;font-weight:800 !important;color:#102A1D !important;white-space:nowrap}
  .pm-sub-rows{display:block;margin:0 0 10px;padding:0 0 10px;border-bottom:1px solid #DCE5DF}
  .pm-sub-rows[hidden]{display:none!important}
  .pm-sub-rows p{display:flex;justify-content:space-between;gap:12px;margin:0;white-space:nowrap;padding:3px 0;font-size:14px;color:#3C4A42;font-variant-numeric:tabular-nums}
  .pm-sub-rows p strong{color:#102A1D}
  .pm-sub-rows .pm-disc strong{color:#B42318}
  html body #panel-kasir #kasir .pos-cart-pane details.line-discount[open]{padding:10px 12px 12px;border-radius:10px;background:var(--ui-hover)}
  html body #panel-kasir #kasir .pos-cart-pane details.line-discount .discount-controls{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:8px}
  html body #panel-kasir #kasir .pos-cart-pane details.line-discount .discount-controls label{gap:4px;font-size:12px}
  html body #panel-kasir #kasir .pos-cart-pane details.line-discount .discount-controls :is(select,input){min-height:40px!important;height:40px!important;border-radius:8px!important;font-size:14px!important}
  .pm-deliver{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 14px;padding:12px 14px;border:1px solid #DFE6E1;border-radius:10px;background:#fff;cursor:pointer}
  .pm-deliver span{display:flex;flex-direction:column;font-size:15px;font-weight:700;color:#102A1D}
  .pm-deliver small{font-size:12.5px;font-weight:400;color:#5F7468}
  html body .pm-deliver input{appearance:none;flex:none;position:relative;width:46px !important;min-width:46px !important;height:28px !important;min-height:28px !important;margin:0 !important;padding:0 !important;border:0 !important;border-radius:999px !important;background:#C9D4CD !important;box-shadow:none !important;cursor:pointer;transition:background-color .2s}
  .pm-deliver input::after{content:'';position:absolute;top:4px;left:4px;width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.2);transition:transform .2s}
  html body .pm-deliver input:checked{background:#14532D !important}
  .pm-deliver input:checked::after{transform:translateX(18px)}
  .pm-deliver input:focus-visible{outline:2px solid #14532D;outline-offset:2px}
  .pm-quick{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin:-6px 0 12px}
  .pm-quick[hidden]{display:none}
  html body .pm-quick button{min-height:40px !important;height:40px;padding:0 6px !important;border:1px solid #DFE6E1 !important;border-radius:999px !important;background:#fff !important;color:#14532D !important;font-size:14px !important;font-weight:700 !important;box-shadow:none !important;font-variant-numeric:tabular-nums}
  html body .pm-quick button[aria-pressed=true]{border-color:#14532D !important;background:#14532D !important;color:#fff !important}
}
@media(max-width:380px){html body #panel-kasir #kasir .pos-cart-pane details.line-discount .discount-controls{grid-template-columns:1fr}}
@media (max-width:760px){
  /* lembar bawah pembayaran */
  html :is(body#ui-dna-strong,body) #payment-modal-overlay[open]{inset:auto 0 0 0 !important;width:100vw !important;max-width:none !important;height:auto !important;max-height:94dvh !important;margin:0 !important;border-radius:20px 20px 0 0 !important;animation:pm-sheet-up .32s cubic-bezier(.22,.8,.24,1) !important}
  html :is(body#ui-dna-strong,body) #payment-modal-overlay .popup-heading{padding:22px 12px 2px 18px !important;position:relative}
  html :is(body#ui-dna-strong,body) #payment-modal-overlay .popup-heading::before{content:'';position:absolute;top:8px;left:50%;width:44px;height:4px;margin-left:-22px;border-radius:4px;background:rgba(255,255,255,.35)}
  html :is(body#ui-dna-strong,body) #payment-modal-overlay .popup-content{padding-top:0 !important}
  html :is(body#ui-dna-strong,body) #payment-modal-overlay .summary-box{margin:0 -18px 16px !important;padding:2px 18px 16px !important;border:0 !important;border-radius:0 !important;background:#14532D !important}
  html :is(body#ui-dna-strong,body) #payment-modal-overlay .summary-box .summary-row{display:grid !important;grid-template-columns:1fr auto;align-items:end}
  html :is(body#ui-dna-strong,body) #payment-modal-overlay .summary-box .summary-row strong:first-child{grid-column:1;font-size:13px !important;font-weight:600 !important;color:#D6E8DC !important}
  html :is(body#ui-dna-strong,body) #payment-modal-overlay #payment-modal-total{grid-column:1;font-size:26px !important;line-height:32px !important;color:#fff !important}
  html :is(body#ui-dna-strong,body) #payment-modal-overlay .pm-pay-count{grid-column:2;grid-row:1 / span 2;align-self:center;font-size:13px;color:#D6E8DC}
  html :is(body#ui-dna-strong,body) #payment-modal-overlay .popup-content>.summary-row{margin:12px 0 0 !important;padding:14px 16px !important;border:1px solid #CFE3D6 !important;border-radius:10px;background:#E4F0E8}
}
/* Tablet: bar mengambang di kanan bawah, keranjang menjadi laci dari kanan setinggi layar. */
@media (min-width:761px){
  body.pm-has-cart main.main-content{padding-bottom:104px !important}
  .pm-bar{left:auto;right:20px;bottom:calc(20px + env(safe-area-inset-bottom));width:min(520px,calc(100% - 40px))}
  html body #kasir .pos-cart-pane{left:auto;right:0;top:0;bottom:0;width:min(460px,88vw);height:100dvh;max-height:none;transform:translateX(105%)}
  html body #kasir .pos-cart-pane>section.card{min-height:100%;padding:24px 20px calc(20px + env(safe-area-inset-bottom)) !important;border-radius:20px 0 0 20px !important}
  html body #kasir .pos-cart-pane>section.card::before{display:none}
  html body #kasir .pm-sheet-close{top:18px;right:14px}
}
html body #panel-kasir #kasir .pos-workspace{grid-template-columns:minmax(0,1fr)}
html body #panel-kasir #kasir .pos-cart-pane{position:fixed !important;inset:auto 0 0;z-index:var(--ui-cart-layer);max-height:92dvh;overflow:auto;border-radius:var(--ui-radius);transform:translateY(105%);visibility:hidden}
html body.pm-cart-open #panel-kasir #kasir .pos-cart-pane{transform:none;visibility:visible}
html body #panel-kasir #kasir .pos-cart-pane>section.card{min-height:0;padding:var(--ui-space-panel) var(--ui-space-section) !important}
html body #panel-kasir #kasir .pos-cart-pane #items>.item-row{padding:var(--ui-space-section) !important;border:var(--ui-stroke) solid var(--ui-line) !important;border-radius:var(--ui-radius) !important}
html body #panel-kasir #kasir .pos-cart-pane :is([data-step], [data-remove]){min-width:var(--ui-tap);min-height:var(--ui-tap)}
html body #panel-kasir #kasir .pos-cart-pane #h-checkout{padding-right:var(--ui-tap)}
html body #panel-kasir #kasir .pos-cart-pane dialog[open]{visibility:visible}
html body #panel-kasir #kasir .pos-cart-pane{height:92dvh}
html body #panel-kasir #kasir .pos-cart-pane>section.card{display:flex;flex-direction:column;height:100%;box-sizing:border-box}
html body #panel-kasir #kasir .pos-cart-pane #form-checkout{display:flex;flex-direction:column;flex:1;min-height:0}
html body #panel-kasir #kasir .pos-cart-pane #form-checkout> :not(.pos-cart-section){flex-shrink:0}
html body #panel-kasir #kasir .pos-cart-pane .pos-cart-section{display:flex;flex-direction:column;flex:1;min-height:var(--ui-cart-list-min)}
html :is(body#ui-dna-strong,body) #panel-kasir #kasir #form-checkout .cart-table-scroll{display:flex;flex-direction:column;flex:1;min-height:0;max-height:none !important;overflow:hidden !important}
html body #panel-kasir #kasir .pos-cart-pane #items{flex:1;min-height:0;overflow-y:auto;overflow-x:hidden;overscroll-behavior:contain;scrollbar-gutter:stable;padding-right:var(--ui-space-tight);scroll-padding-block:var(--ui-space-control)}
html body #panel-kasir #kasir .pos-cart-pane #items>.item-row{flex-shrink:0}
html body #panel-kasir #kasir .pos-cart-pane #items:focus-visible{outline:var(--ui-stroke) solid var(--ui-focus);outline-offset:var(--ui-space-tight)}
html body .pm-bar[inert]{display:none}
@media(min-width:761px){
  html body #panel-kasir #kasir .pos-cart-pane{inset:0 0 0 auto;width:min(var(--ui-cart-width),100vw);height:100dvh;max-height:100dvh;border-radius:0;transform:translateX(105%)}
  html body #panel-kasir #kasir .pos-cart-pane>section.card{min-height:100%;border-radius:0 !important;padding:var(--ui-space-panel) !important}
  html body .pm-bar{width:min(var(--ui-cart-bar-width),calc(100% - var(--ui-space-panel) * 2));right:var(--ui-space-panel);bottom:var(--ui-space-panel);font-family:var(--font-body)}
}
html body #panel-kasir #kasir .pos-cart-pane{transform:none !important;transition:none !important}
@keyframes pm-sheet-up{from{transform:translateY(100%)}to{transform:none}}
@media (prefers-reduced-motion:reduce){
  .pm-bar,html body #kasir .pos-cart-pane,.pm-backdrop{animation:none !important;transition:none !important}
  html :is(body#ui-dna-strong,body) #payment-modal-overlay[open]{animation:none !important}
}
</style>
<script>
(()=>{
'use strict';
const form=document.querySelector('#form-checkout'),kasir=document.querySelector('#kasir');if(!form||!kasir)return;
const D=window.KasirDecimal,reduce=()=>matchMedia('(prefers-reduced-motion: reduce)').matches;
const items=document.querySelector('#items'),tbody=document.querySelector('#products-tbody'),pane=kasir.querySelector('.pos-cart-pane'),sheet=pane?.querySelector('section.card'),payment=document.querySelector('#payment-modal-overlay'),payButton=document.querySelector('#open-payment');
if(!pane||!sheet||!items)return;
items.tabIndex=0;items.setAttribute('role','region');items.setAttribute('aria-label','Daftar barang keranjang, dapat digulir');
const rowOf=id=>Array.from(items.children).find(r=>r.querySelector('[data-name=product_id]')?.value===String(id));
const qtyText=v=>String(v||'0').replace('.',',');
let last={count:0,qty:'0',gross:0n,discount:0n,total:0n,valid:true};
// --- bar keranjang ---
const bar=document.createElement('div');bar.className='pm-bar';bar.hidden=true;bar.setAttribute('role','region');bar.setAttribute('aria-label','Ringkasan keranjang');
bar.innerHTML='<span class="pm-bar-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.6 12.4a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21 7H6"/></svg><span class="pm-bar-badge"></span></span><span class="pm-bar-meta"><small></small><strong aria-live="polite"></strong></span><button type="button" class="pm-bar-open" aria-haspopup="dialog">Lihat keranjang</button>';
document.body.append(bar);
const backdrop=document.createElement('div');backdrop.className='pm-backdrop';document.body.append(backdrop);
const openButton=bar.querySelector('.pm-bar-open'),panel=kasir.closest('.task-panel');
pane.id='pos-cart-drawer';pane.inert=true;
openButton.setAttribute('aria-controls',pane.id);openButton.setAttribute('aria-expanded','false');
function syncVisibility(){
 const active=!panel?.hidden;
 bar.inert=!active;
 if(!active)closeSheet(false);
}
if(panel)new MutationObserver(syncVisibility).observe(panel,{attributes:true,attributeFilter:['hidden']});
// --- kepala lembar keranjang: subjudul + tutup; baris diskon ---
const sub=document.createElement('p');sub.className='pm-sheet-sub';sheet.querySelector('#h-checkout')?.after(sub);
const close=document.createElement('button');close.type='button';close.className='pm-sheet-close';close.setAttribute('aria-label','Tutup keranjang');close.textContent='×';sheet.append(close);
const subRows=document.createElement('div');subRows.className='pm-sub-rows';subRows.innerHTML='<p class="pm-disc"><span>Diskon</span><strong></strong></p>';
form.querySelector('.summary-box')?.prepend(subRows);
function paint(){
 const has=last.count>0;
 bar.hidden=!has;document.body.classList.toggle('pm-has-cart',has);
 const meta=last.count+' jenis · '+qtyText(last.qty)+' barang';
 window.uiNumbers.set(bar.querySelector('.pm-bar-badge'),String(last.count));window.uiNumbers.text(bar.querySelector('small'),meta);
 window.uiNumbers.set(bar.querySelector('strong'),last.valid?D.money(last.total):'Periksa isian');
 window.uiNumbers.text(sub,has?meta:'Belum ada barang');
 subRows.hidden=!(last.discount>0n);window.uiNumbers.set(subRows.querySelector('.pm-disc strong'),'−'+D.money(last.discount));
 const count=payment?.querySelector('.pm-pay-count');if(count)window.uiNumbers.text(count,meta);
 if(!has&&document.body.classList.contains('pm-cart-open'))closeSheet(false);
 syncCatalog();syncQuick();syncLineDiscounts();
}
function syncLineDiscounts(){
 for(const row of items.children){
  const sum=row.querySelector('details.line-discount>summary');if(!sum)continue;
  let d=0n;try{d=BigInt(row.dataset.lineDiscount||'0');}catch{}
  let chip=sum.querySelector('.pm-chip');if(!chip){chip=document.createElement('span');chip.className='pm-chip';sum.prepend(chip);}
   if(last.valid&&d>0n){sum.setAttribute('data-pm-disc','');window.uiNumbers.text(chip,'Diskon −'+D.money(d));}else sum.removeAttribute('data-pm-disc');
 }
}
form.addEventListener('kasir:cart',e=>{last=e.detail;paint();});
// Kartu diskon baris: buka/tutup dengan morph tinggi + isi, bukan muncul mendadak.
document.addEventListener('click',event=>{
 const summary=event.target.closest?.('#items details.line-discount>summary');
 if(!summary)return;
 const details=summary.parentElement;
 if(details.dataset.morphing){event.preventDefault();return;}
 if(reduce()||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;
 event.preventDefault();
 const panel=details.querySelector('.discount-controls'),opening=!details.open,from=details.offsetHeight;
 details.style.overflow='hidden';details.dataset.morphing='1';
 const done=()=>{details.style.removeProperty('overflow');delete details.dataset.morphing;};
 const ease='cubic-bezier(.22,.8,.24,1)';
 if(opening){
  details.open=true;const to=details.offsetHeight;
  panel?.animate([{opacity:0},{opacity:1}],{duration:240,easing:'ease-in-out'});
  details.animate([{height:from+'px'},{height:to+'px'}],{duration:240,easing:ease}).finished.then(done,done);
 }else{
  const to=summary.offsetHeight;
  panel?.animate([{opacity:1},{opacity:0}],{duration:200,easing:'ease-in-out'});
  details.animate([{height:from+'px'},{height:to+'px'}],{duration:240,easing:ease}).finished.then(()=>{details.open=false;done();},done);
 }
});
// --- stepper katalog ---
function stepper(id){const s=document.createElement('span');s.className='pm-step';s.innerHTML='<button type="button" data-pm-step="-1">−</button><input type="text" inputmode="decimal" autocomplete="off" maxlength="23" data-pm-quantity><button type="button" data-pm-step="1">+</button><span class="sr-only" data-pm-qty-error role="status"></span>';s.dataset.product=id;const input=s.querySelector('[data-pm-quantity]'),error=s.querySelector('[data-pm-qty-error]');error.id='catalog-quantity-error-'+id;input.setAttribute('aria-describedby',error.id);return s;}
function syncCatalog(){
 if(!tbody)return;
 for(const tr of tbody.querySelectorAll('tr[data-product]')){
  const id=tr.dataset.product,row=rowOf(id),act=tr.querySelector('td.ui-actions');if(!act)continue;
  let s=act.querySelector('.pm-step');
  if(!row){tr.removeAttribute('data-in-cart');continue;}
  if(!s){s=stepper(id);act.append(s);}
  const q=row.querySelector('[data-name=quantity]').value;
   tr.setAttribute('data-in-cart','');const field=s.querySelector('[data-pm-quantity]');if(document.activeElement!==field)field.value=qtyText(q);
   const name=tr.querySelector('td.ui-primary')?.firstChild?.textContent?.trim()||'barang';
   field.setAttribute('aria-label','Jumlah '+name);
  s.querySelector('[data-pm-step="-1"]').setAttribute('aria-label',(q==='1'?'Hapus ':'Kurangi ')+name);s.querySelector('[data-pm-step="1"]').setAttribute('aria-label','Tambah '+name);
 }
}
function applyCatalogQuantity(field,restore=false){
 const step=field.closest('.pm-step'),row=rowOf(step.dataset.product);if(!row)return false;
 const original=row.querySelector('[data-name=quantity]'),error=step.querySelector('[data-pm-qty-error]');
 const raw=field.value.trim().replace(',','.');
 let valid=/^\d{1,17}(?:\.\d{1,4})?$/.test(raw);
 try{valid=valid&&D.scaled(raw,4)>0n;}catch{valid=false;}
 if(!valid){
  field.setAttribute('aria-invalid',String(!restore));field.setCustomValidity(restore?'':'Isi jumlah lebih dari nol, maksimal 4 desimal.');
  error.textContent=restore?'Jumlah tidak valid; nilai keranjang sebelumnya dipertahankan.':'Isi jumlah lebih dari nol, maksimal 4 desimal.';
  if(restore)field.value=qtyText(original.value);
  return false;
 }
 field.setAttribute('aria-invalid','false');field.setCustomValidity('');error.textContent='';
 if(original.value!==raw){original.value=raw;original.dispatchEvent(new Event('input',{bubbles:true}));}
 return true;
}
tbody?.addEventListener('input',event=>{if(event.target.matches('[data-pm-quantity]'))applyCatalogQuantity(event.target);});
tbody?.addEventListener('focusout',event=>{if(event.target.matches('[data-pm-quantity]'))applyCatalogQuantity(event.target,true);});
tbody?.addEventListener('keydown',event=>{
 const field=event.target;if(!field.matches('[data-pm-quantity]'))return;
 if(event.key==='Enter'){event.preventDefault();applyCatalogQuantity(field,true);field.blur();}
 else if(event.key==='Escape'){event.preventDefault();const row=rowOf(field.closest('.pm-step').dataset.product);if(row)field.value=qtyText(row.querySelector('[data-name=quantity]').value);applyCatalogQuantity(field,true);field.blur();}
});
tbody?.addEventListener('click',e=>{
 const b=e.target.closest('[data-pm-step]');if(!b)return;
 const row=rowOf(b.closest('.pm-step').dataset.product);if(!row)return;
 const q=row.querySelector('[data-name=quantity]');
 let one=false;try{one=D.scaled(q.value,4)<=10000n;}catch{}
 if(b.dataset.pmStep==='-1'&&one)row.querySelector('[data-remove]')?.click();
 else row.querySelector('[data-step="'+(b.dataset.pmStep==='1'?'1':'-1')+'"]')?.click();
});
if(tbody)new MutationObserver(()=>syncCatalog()).observe(tbody,{childList:true});
// --- buka/tutup lembar keranjang ---
let cartMotion=null;
let cartOrigin=null;
let cartClosing=false;
function openSheet(){
  if(panel?.hidden||last.count===0)return;
  const bounds=bar.getBoundingClientRect?.();
  const origin=bounds?.width&&bounds?.height?{left:bounds.left,top:bounds.top,width:bounds.width,height:bounds.height,borderRadius:getComputedStyle(bar).borderRadius,background:getComputedStyle(bar).backgroundColor}:cartOrigin;
  cartMotion?.cancel();
  document.body.classList.remove('pm-cart-closing');
  cartClosing=false;
  cartOrigin=origin||cartOrigin;
  pane.inert=false;openButton.setAttribute('aria-expanded','true');
 document.body.classList.add('pm-cart-open');pane.setAttribute('role','dialog');pane.setAttribute('aria-modal','true');pane.setAttribute('aria-labelledby','h-checkout');
  cartMotion=window.uiMotion?.morphFrom?.(pane,origin)||null;
  pane.scrollTop=0;setTimeout(()=>{if(!pane.inert)close.focus({preventScroll:true});},reduce()?0:120);
}
function closeSheet(restore=true){
  if(restore&&cartClosing)return;
  cartMotion?.cancel();cartMotion=null;
  const finish=()=>{
   cartClosing=false;
   document.body.classList.remove('pm-cart-closing');
   document.body.classList.remove('pm-cart-open');pane.removeAttribute('role');pane.removeAttribute('aria-modal');
   pane.inert=true;openButton.setAttribute('aria-expanded','false');
   if(restore&&!bar.hidden)bar.querySelector('.pm-bar-open').focus({preventScroll:true});
  };
  if(restore&&!pane.inert){
   document.body.classList.add('pm-cart-closing');
   cartMotion=window.uiMotion?.morphFrom?.(pane,cartOrigin,{closing:true,onFinish:finish})||null;
   if(cartMotion){cartClosing=true;pane.inert=true;return;}
  }
  finish();
}
bar.querySelector('.pm-bar-open').addEventListener('click',openSheet);
close.addEventListener('click',()=>closeSheet());backdrop.addEventListener('click',()=>closeSheet());
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&document.body.classList.contains('pm-cart-open')&&!document.querySelector('dialog[open]')){e.preventDefault();closeSheet();}});
document.addEventListener('keydown',e=>{
 if(e.key!=='Tab'||!document.body.classList.contains('pm-cart-open')||document.querySelector('dialog[open]'))return;
 const controls=[...pane.querySelectorAll('button,input,select,textarea,a[href],summary,[tabindex="0"]')].filter(el=>!el.disabled&&!el.closest('[hidden],[inert]')&&el.getClientRects().length);
 const first=controls[0],lastControl=controls.at(-1);
 if(e.shiftKey&&(document.activeElement===first||!pane.contains(document.activeElement))){e.preventDefault();lastControl?.focus();}
 else if(!e.shiftKey&&(document.activeElement===lastControl||!pane.contains(document.activeElement))){e.preventDefault();first?.focus();}
});
// --- pembayaran sebagai lembar bawah ---
if(payment){
 const head=payment.querySelector('.summary-box .summary-row');
 if(head){const label=head.querySelector('strong');if(label)label.textContent='Total tagihan';const c=document.createElement('span');c.className='pm-pay-count';head.append(c);}
 let wasSheet=false;
 new MutationObserver(()=>{
  if(payment.open){wasSheet=document.body.classList.contains('pm-cart-open');if(wasSheet)closeSheet(false);}
   else if(wasSheet&&last.count>0&&!document.querySelector('#checkout-confirm-overlay')?.open){wasSheet=false;openSheet();}
 }).observe(payment,{attributes:true,attributeFilter:['open']});
 // Sakelar pengiriman = isian "Cara penyerahan" yang sama.
 const fulfil=form.querySelector('#fulfillment'),method=payment.querySelector('.pay-method-field');
 if(fulfil&&method){
  const row=document.createElement('label');row.className='pm-deliver';row.innerHTML='<span>Antar dengan armada toko<small>Pilih mobil dan uang ritase</small></span><input type="checkbox" role="switch">';
  method.after(row);const sw=row.querySelector('input');
  const sync=()=>{sw.checked=fulfil.value==='delivery';};
  sw.addEventListener('change',()=>{fulfil.value=sw.checked?'delivery':'pickup';fulfil.dispatchEvent(new Event('change',{bubbles:true}));if(sw.checked){const d=payment.querySelector('details.checkout-options');if(d){d.open=true;setTimeout(()=>d.scrollIntoView({block:'nearest',behavior:reduce()?'auto':'smooth'}),60);}}});
  fulfil.addEventListener('change',sync);sync();
 }
}
// Nominal cepat (tunai): Uang pas, dibulatkan ke 50 ribu, dibulatkan ke 100 ribu.
const paidDisplay=document.querySelector('#paid-amount-display'),paid=document.querySelector('#paid-amount');
const quick=document.createElement('div');quick.className='pm-quick';quick.setAttribute('role','group');quick.setAttribute('aria-label','Nominal cepat');
paidDisplay?.closest('.field')?.after(quick);
const group=v=>(v/100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g,'.');
function syncQuick(){
 if(!paidDisplay)return;
 const cash=form.elements.payment_method?.value==='cash';quick.hidden=!cash||!last.valid||last.total<=0n;if(quick.hidden)return;
 const t=last.total,up=(n,step)=>((n+step-1n)/step)*step,list=[['Uang pas',t]];
 for(const step of [5000000n,10000000n]){const v=up(t,step);if(v>t&&!list.some(x=>x[1]===v))list.push([group(v),v]);}
 if(list.length<3)list.push([group(up(t,10000000n)+10000000n),up(t,10000000n)+10000000n]);
 let current=-1n;try{current=D.scaled(paid.value||'0',2);}catch{}
 quick.replaceChildren(...list.slice(0,3).map(([label,v])=>{const b=document.createElement('button');b.type='button';b.textContent=label;b.dataset.value=String(v);b.setAttribute('aria-pressed',String(v===current));return b;}));
}
quick.addEventListener('click',e=>{const b=e.target.closest('button[data-value]');if(!b)return;const v=BigInt(b.dataset.value);paidDisplay.value=group(v)+(v%100n?','+(v%100n).toString().padStart(2,'0'):'');paidDisplay.dispatchEvent(new Event('input',{bubbles:true}));paidDisplay.dispatchEvent(new Event('blur'));syncQuick();});
form.addEventListener('change',e=>{if(e.target.name==='payment_method')syncQuick();});
paidDisplay?.addEventListener('input',()=>setTimeout(syncQuick));
paint();
syncVisibility();
})();
</script>
