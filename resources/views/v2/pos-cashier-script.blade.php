<script>
// Exact preview arithmetic; the server remains the authority at final save.
window.KasirDecimal=(()=>{
 const pow=n=>10n**BigInt(n);
 function scaled(raw,places){const s=String(raw??'').trim().replace(',','.');if(!/^\d{1,17}(\.\d{1,6})?$/.test(s))throw Error('Isi angka positif tanpa pemisah ribuan.');const [i,f='']=s.split('.');const digits=(f+'0'.repeat(places+1));let n=BigInt(i)*pow(places)+BigInt(digits.slice(0,places)||'0');if(digits[places]>='5')n++;return n;}
 const divide=(n,d)=>(n+d/2n)/d;
 function plain(n,p=2){const s=n.toString().padStart(p+1,'0');return p?s.slice(0,-p)+'.'+s.slice(-p):s;}
 function money(n){const s=plain(n),[i,f]=s.split('.');return 'Rp '+i.replace(/\B(?=(\d{3})+(?!\d))/g,'.')+(f==='00'?'':','+f);}
 function discount(base,type,raw){if(!type||String(raw).trim()==='')return 0n;const v=scaled(raw,2);if(type==='percent'&&v>10000n)throw Error('Diskon persen maksimal 100.');const result=type==='percent'?divide(base*v,10000n):v;if(result>base)throw Error('Diskon tidak boleh melebihi harga barang.');return result;}
 function line(price,quantity,type='',value=''){const q=scaled(quantity,4);if(q<=0n)throw Error('Jumlah harus lebih dari 0.');const gross=divide(scaled(price,2)*q,10000n);return gross-discount(gross,type,value);}
 return {scaled,plain,money,discount,line,divide};
})();
(()=>{
'use strict';
const D=window.KasirDecimal,form=document.querySelector('#form-checkout');if(!form)return;
@php
    $posProducts = collect($products)->map(fn($p) => ['id'=>(string)$p->id,'sku'=>(string)($p->sku??''),'name'=>(string)$p->name,'category'=>(string)($p->category?:'Umum'),'selling_price'=>(string)$p->selling_price] + (($p->contractor_price ?? null) !== null ? ['contractor_price'=>(string)$p->contractor_price] : []) + ['stock_quantity'=>(string)$p->stock_quantity,'min_stock'=>(string)($p->min_stock ?? '0'),'unit'=>(string)$p->unit])->values();
@endphp
const products=@json($posProducts);
const catalogUrl=@json(route('v2.pos.catalog'), JSON_UNESCAPED_SLASHES);
const posPolicy=@json($posPolicy ?? ['role' => $role ?? 'cashier', 'cashier_max_discount_percent' => null, 'block_cashier_below_cost' => false]);
const map=new Map(products.map(p=>[p.id,p])),items=document.querySelector('#items'),payment=document.querySelector('#payment-modal-overlay'),review=document.querySelector('#checkout-confirm-overlay'),payButton=document.querySelector('#open-payment'),holdButton=document.querySelector('#hold-cart'),heldToggle=document.querySelector('#pos-held-toggle'),heldCountEl=document.querySelector('#pos-held-count'),heldDialog=document.querySelector('#pos-held-dialog'),heldList=document.querySelector('#pos-held-list'),refreshCatalogBtn=document.querySelector('#catalog-refresh'),confirm=document.querySelector('#checkout-confirm-submit'),paid=document.querySelector('#paid-amount'),paidDisplay=document.querySelector('#paid-amount-display'),error=document.querySelector('#cart-error'),quickCust=document.querySelector('#pos-customer-quick'),notesInput=document.querySelector('#checkout-notes'),notesCount=document.querySelector('#checkout-notes-count');
// Harga kontraktor: dipilih lewat tombol Eceran/Kontraktor (mengikuti pelanggan); produk tanpa harga kontraktor tetap memakai harga jual.
const tierSel=document.querySelector('#price-tier');
const contractorOn=()=>tierSel?.value==='contractor';
const priceOf=p=>contractorOn()&&p.contractor_price?p.contractor_price:p.selling_price;
const userIdKey=@json((string)auth()->id());
const cartKey='kasir-v2-cart:'+userIdKey;
const heldKey='kasir-v2-held:'+userIdKey;
const MAX_HELD=10;
let total=0n,lastGross=0n,lastLineDiscount=0n,lastInvDiscount=0n,lastDeliveryFee=0n,valid=true,submitting=false,saved=false;
const rows=()=>Array.from(items.children);
const normalize=raw=>String(raw??'').trim().replace(',','.');
const quantityText=n=>D.plain(n,4).replace(/0+$/,'').replace(/\.$/,'');
const safeUuid=()=>{if(typeof window.v2Uuid==='function')return window.v2Uuid();if(typeof window!=='undefined'&&window.crypto&&typeof window.crypto.randomUUID==='function')return window.crypto.randomUUID();if(typeof window!=='undefined'&&window.crypto&&typeof window.crypto.getRandomValues==='function'){const b=new Uint8Array(16);window.crypto.getRandomValues(b);return Array.from(b,x=>('0'+x.toString(16)).slice(-2)).join('');}return 'pos-'+Date.now()+'-'+Math.random().toString(16).slice(2);};
const cashierMaxDiscountScaled=(()=>{if(posPolicy?.role!=='cashier'||posPolicy.cashier_max_discount_percent===null||posPolicy.cashier_max_discount_percent==='')return null;try{const v=D.scaled(String(posPolicy.cashier_max_discount_percent),2);return v>=0n&&v<=10000n?v:null;}catch{return null;}})();
const cashierMaxDiscountLabel=cashierMaxDiscountScaled!==null?D.plain(cashierMaxDiscountScaled,2).replace(/0+$/,'').replace(/\.$/,''):'';
function showPaid(){try{const v=D.scaled(paid.value||'0',2);paidDisplay.value=v===0n?'':D.money(v).slice(3);}catch{}}
paidDisplay.addEventListener('input',()=>{paid.value=paidDisplay.value.replace(/\./g,'').replace(',','.')||'0';refresh();});paidDisplay.addEventListener('blur',showPaid);
function updateNotesCount(){if(notesInput&&notesCount)notesCount.textContent=String(notesInput.value.length);}
notesInput?.addEventListener('input',()=>{updateNotesCount();store();});
function snapshotCart(){
 const cid=form.elements.customer_id.value;
 const opt=cid?form.elements.customer_id.selectedOptions[0]:null;
 return {
  version:2,
  items:rows().map(row=>{const value=n=>row.querySelector('[data-name='+n+']').value;return {id:value('product_id'),quantity:value('quantity'),discount_type:value('discount_type'),discount_value:value('discount_value')};}),
  customer:cid?{id:cid,name:opt?.dataset.name||opt?.textContent?.trim()||'',code:opt?.dataset.code||'',tier:opt?.dataset.tier||'retail',open_invoices_count:Number(opt?.dataset.openCount||0),open_balance:opt?.dataset.openBalance||'0.00',overdue_count:Number(opt?.dataset.overdueCount||0),overdue_balance:opt?.dataset.overdueBalance||'0.00'}:null,
  price_tier:tierSel?.value||'retail',
  discount_type:document.querySelector('#inv-disc-type')?.value||'amount',
  discount_value:document.querySelector('#inv-disc-value')?.value||'',
  notes:notesInput?.value||'',
 };
}
function store(){try{localStorage.setItem(cartKey,JSON.stringify(snapshotCart()));}catch{}}
function applyCustomerOption(customer){
 const selects=[form.elements.customer_id,quickCust].filter(Boolean);
 if(!customer||!customer.id){
  for(const s of selects)s.value='';
  form.elements.customer_id.dispatchEvent(new Event('change',{bubbles:true}));
  return;
 }
 const id=String(customer.id);
 for(const s of selects){
  let opt=s.querySelector('option[value="'+id+'"]');
  if(!opt){opt=document.createElement('option');opt.value=id;s.append(opt);}
  opt.textContent=customer.name||'Pelanggan';
  opt.dataset.name=customer.name||'Pelanggan';
  opt.dataset.code=customer.code||'';
  opt.dataset.tier=customer.tier||'retail';
  opt.dataset.openCount=String(customer.open_invoices_count??0);
  opt.dataset.openBalance=String(customer.open_balance??'0.00');
  opt.dataset.overdueCount=String(customer.overdue_count??0);
  opt.dataset.overdueBalance=String(customer.overdue_balance??'0.00');
  s.value=id;
 }
 form.elements.customer_id.dispatchEvent(new Event('change',{bubbles:true}));
}
function formatMoneyText(raw){try{return D.money(D.scaled(String(raw||'0'),2));}catch{return 'Rp 0';}}
function syncBuyerState(){
 const cid = form.elements.customer_id.value;
 if (quickCust && quickCust.value !== cid) quickCust.value = cid;
 const method = form.elements.payment_method.value;
 const needsCustomer = method === 'credit' || method === 'transfer';
 const isRegistered = Boolean(cid);
 const badgeText = isRegistered ? 'Pelanggan Terdaftar' : 'Pelanggan Umum';
 const badgeCls = isRegistered ? 'badge-registered' : 'badge-general';
 const pBadge = document.querySelector('#pos-buyer-badge');
 const mBadge = document.querySelector('#modal-buyer-badge');
 if (pBadge) { pBadge.textContent = badgeText; pBadge.className = 'pos-buyer-badge ' + badgeCls; }
 if (mBadge) { mBadge.textContent = badgeText; mBadge.className = 'pos-buyer-badge ' + badgeCls; }
 const pReq = document.querySelector('#pos-buyer-req');
 const mReq = document.querySelector('#customer-required');
 if (pReq) pReq.hidden = !needsCustomer;
 if (mReq) mReq.textContent = needsCustomer ? '(wajib)' : '';
 const mWarn = document.querySelector('#modal-credit-warning');
 if (mWarn) mWarn.hidden = !needsCustomer || isRegistered;
 const pHint = document.querySelector('#pos-buyer-hint');
 const mHint = document.querySelector('#modal-buyer-hint');
 const opt = isRegistered ? form.elements.customer_id.selectedOptions[0] : null;
 if (isRegistered) {
  const name = opt ? (opt.dataset.name || opt.textContent.trim()) : 'Terdaftar';
  const code = opt && opt.dataset.code ? ' · [' + opt.dataset.code + ']' : '';
  const desc = name + code + ' · Mendukung transaksi tunai, transfer, dan piutang/tempo.';
  if (pHint) pHint.textContent = desc;
  if (mHint) mHint.textContent = desc;
 } else {
  const desc = needsCustomer
   ? 'Transfer dan kredit mewajibkan identitas pelanggan terdaftar. Silakan pilih pelanggan di atas.'
   : 'Penjualan tunai di toko. Untuk transaksi transfer atau tempo/kredit, pilih pelanggan terdaftar.';
  if (pHint) pHint.textContent = desc;
  if (mHint) mHint.textContent = desc;
 }
 const openCount = Number(opt?.dataset.openCount || 0);
 const overdueCount = Number(opt?.dataset.overdueCount || 0);
 const arrearsBanners = [document.querySelector('#pos-arrears-banner'), document.querySelector('#modal-arrears-banner')].filter(Boolean);
 for (const banner of arrearsBanners) {
  if (isRegistered && openCount > 0) {
   let msg = 'Piutang terbuka: ' + openCount + ' nota (' + formatMoneyText(opt.dataset.openBalance) + ').';
   if (overdueCount > 0) msg += ' Perhatian: ' + overdueCount + ' nota lewat jatuh tempo (' + formatMoneyText(opt.dataset.overdueBalance) + ').';
   banner.textContent = msg;
   banner.classList.toggle('is-overdue', overdueCount > 0);
   banner.hidden = false;
  } else {
   banner.hidden = true;
   banner.textContent = '';
   banner.classList.remove('is-overdue');
  }
 }
}
if (quickCust) {
 quickCust.addEventListener('change', () => {
  form.elements.customer_id.value = quickCust.value;
  syncBuyerState();
  refresh();
 });
}
form.elements.customer_id.addEventListener('change', () => {
 if (quickCust) quickCust.value = form.elements.customer_id.value;
 syncBuyerState();
 refresh();
});
// Tombol Eceran/Kontraktor (di keranjang dan di Pembayaran) menyetir select price_tier yang dikirim.
const tierGroups=Array.from(document.querySelectorAll('#kasir .pos-tier'));
function syncTier(){for(const g of tierGroups)for(const b of g.querySelectorAll('[data-tier]')){const on=b.dataset.tier===tierSel.value;b.setAttribute('aria-checked',String(on));b.tabIndex=on?0:-1;}}
function setTier(value){if(!tierSel||tierSel.value===value){syncTier();return;}tierSel.value=value;syncTier();refresh();if(typeof filter==='function')filter(true);}
for(const g of tierGroups){g.addEventListener('click',e=>{const b=e.target.closest('[data-tier]');if(b)setTier(b.dataset.tier);});g.addEventListener('keydown',e=>{if(!['ArrowLeft','ArrowRight'].includes(e.key))return;e.preventDefault();const next=tierSel.value==='retail'?'contractor':'retail';setTier(next);g.querySelector('[data-tier='+next+']').focus();});}
// Pelanggan bertanda Kontraktor otomatis memakai harga kontraktor; kasir tetap bisa mengganti.
function tierFromCustomer(){const opt=form.elements.customer_id.selectedOptions[0];setTier(form.elements.customer_id.value&&opt?.dataset.tier==='contractor'?'contractor':'retail');}
form.elements.customer_id.addEventListener('change',tierFromCustomer);quickCust?.addEventListener('change',tierFromCustomer);syncTier();
function refresh(){
 valid=true;error.hidden=true;let base=0n,gross=0n,qtySum=0n,invDisc=0n,fee=0n;
 try{
  for(const row of rows()){
   const p=map.get(row.querySelector('[data-name=product_id]').value),quantity=row.querySelector('[data-name=quantity]').value;
   if(D.scaled(quantity,4)>D.scaled(p.stock_quantity,4))throw Error('Stok '+p.name+' tidak mencukupi. Sesuaikan jumlah atau hapus barang.');
  }
 }catch(e){valid=false;error.textContent=e.message;error.hidden=false;}
 try{for(const row of rows()){const value=n=>row.querySelector('[data-name='+n+']').value,p=map.get(value('product_id')),unit=priceOf(p);const net=D.line(unit,value('quantity'),value('discount_type'),value('discount_value'));window.uiNumbers.set(row.querySelector('.item-subtotal'),D.money(net));base+=net;const q=D.scaled(value('quantity'),4),lineGross=D.divide(D.scaled(unit,2)*q,10000n),lineDisc=lineGross-net;if(cashierMaxDiscountScaled!==null&&lineDisc>0n&&lineDisc>D.divide(lineGross*cashierMaxDiscountScaled,10000n))throw Error('Diskon barang '+p.name+' melebihi batas kasir maksimal '+cashierMaxDiscountLabel+'%. Hubungi admin.');const up=row.querySelector('.cart-unit-price');if(up){window.uiNumbers.set(up,D.money(D.scaled(unit,2)));up.classList.toggle('is-contractor',unit!==p.selling_price);}qtySum+=q;gross+=lineGross;row.dataset.lineDiscount=String(lineDisc);}invDisc=D.discount(base,document.querySelector('#inv-disc-type').value,document.querySelector('#inv-disc-value').value);const totalDisc=gross-base+invDisc;if(cashierMaxDiscountScaled!==null&&totalDisc>0n&&totalDisc>D.divide(gross*cashierMaxDiscountScaled,10000n))throw Error('Total diskon melebihi batas kasir maksimal '+cashierMaxDiscountLabel+'%. Hubungi admin.');fee=D.scaled(document.querySelector('#delivery-fee').value||'0',2);total=base-invDisc+fee;lastGross=gross;lastLineDiscount=gross-base;lastInvDiscount=invDisc;lastDeliveryFee=fee;}
 catch(e){valid=false;error.textContent=e.message;error.hidden=false;}
 document.querySelector('#cart-empty-row').hidden=rows().length>0;window.uiNumbers.text(document.querySelector('#cart-item-count'),rows().length+' jenis item');
 window.uiNumbers.set(document.querySelector('#display-total'),valid?D.money(total):'Periksa isian');window.uiNumbers.set(document.querySelector('#payment-modal-total'),valid?D.money(total):'Periksa isian');
 if(form.elements.payment_method.value==='transfer'&&valid)paid.value=D.plain(total);
 let change=0n;try{const tender=D.scaled(paid.value||'0',2);if(form.elements.payment_method.value==='cash'&&tender>total)change=tender-total;}catch{}
  window.uiNumbers.set(document.querySelector('#display-change'),D.money(change));payButton.disabled=rows().length===0||!valid||saved;
 if(holdButton)holdButton.disabled=rows().length===0||saved||submitting;
 document.querySelector('#deposit-field').hidden=form.elements.payment_method.value!=='credit';
 {const df=document.querySelector('#due-date-field');if(df)df.hidden=form.elements.payment_method.value!=='credit';}
 syncBuyerState();
 // Ringkasan keranjang untuk lapisan tampilan HP (pos-mobile): hanya dibaca, tidak memengaruhi total yang dikirim.
 form.dispatchEvent(new CustomEvent('kasir:cart',{detail:{count:rows().length,valid,qty:quantityText(qtySum),gross,discount:valid?gross-base+invDisc:0n,total}}));
 document.querySelector('#delivery-fields').hidden=form.elements.fulfillment.value!=='delivery';if(document.activeElement!==paidDisplay)showPaid();store();
}
function add(id,state=null){const p=map.get(String(id));if(!p)return;
 if(!state&&D.scaled(p.stock_quantity,4)<=0n){error.textContent='Stok '+p.name+' habis. Barang tidak ditambahkan.';error.hidden=false;return;}
 let row=rows().find(r=>r.querySelector('[data-name=product_id]').value===p.id);if(row&&!state){const q=row.querySelector('[data-name=quantity]');try{q.value=quantityText(D.scaled(q.value,4)+10000n);}catch{q.value='1';}refresh();return;}
 row=window.v2AddCartRow(false);{const pick=row.querySelector('[data-name=product_id]');pick.replaceChildren(new Option(p.name,p.id));pick.value=p.id;}row.querySelector('.cart-product-title').textContent=p.name;{const meta=row.querySelector('.cart-product-meta'),stockQty=D.scaled(p.stock_quantity,4);meta.replaceChildren();const price=document.createElement('span');price.className='cart-unit-price';price.textContent=D.money(D.scaled(priceOf(p),2));const st=document.createElement('span');st.className='cart-stock'+(stockQty<=0n?' is-out':'');st.textContent=stockQty<=0n?'Stok habis':'Stok '+quantityText(stockQty)+' '+p.unit;meta.append(p.sku+' · ');if(price.textContent){meta.append(price,' / '+p.unit+' · ');}meta.append(st);}
 row.querySelector('[data-name=quantity]').value=state?.quantity||'1';row.querySelector('[data-name=discount_type]').value=state?.discount_type||'';row.querySelector('[data-name=discount_value]').value=state?.discount_value||'';
 for(const b of row.querySelectorAll('[data-step]'))b.addEventListener('click',()=>{const q=row.querySelector('[data-name=quantity]');try{const current=D.scaled(q.value,4),next=current+(b.dataset.step==='1'?10000n:-10000n);q.value=quantityText(next>0n?next:(current>0n?current:10000n));}catch{q.value='1';}refresh();});
 row.addEventListener('input',refresh);row.addEventListener('change',refresh);row.querySelector('[data-remove]').addEventListener('click',refresh);refresh();
}
// Katalog dirender bertahap dari data `products` (40 baris per halaman): ribuan produk tidak membebani DOM,
// sementara pencarian/penyaring tetap bekerja pada seluruh data.
const search=document.querySelector('#pos-product-search'),category=document.querySelector('#product-category-filter'),stock=document.querySelector('#product-stock-filter');
const PAGE=10;let catalogPage=1;const catPager=document.querySelector('#catalog-pager'),catRange=document.querySelector('#catalog-range'),catPages=document.querySelector('#catalog-pages'),catPrev=document.querySelector('#catalog-prev'),catNext=document.querySelector('#catalog-next'),tbody=document.querySelector('#products-tbody'),emptyRow=document.querySelector('#catalog-empty');
let prepared=products.map(p=>({p,skuLower:(p.sku||'').toLocaleLowerCase('id-ID'),hay:(p.name+' '+p.sku).toLocaleLowerCase('id-ID'),qty:D.scaled(p.stock_quantity,4),min:D.scaled(p.min_stock||'0',4)}));
const stockTone=e=>e.qty<=0n?'out-of-stock':(e.qty<=e.min?'low-stock':'in-stock');
function catalogRow(e){
 const p=e.p,tr=document.createElement('tr');tr.dataset.product=p.id;
 const name=document.createElement('td');name.dataset.label='Produk';name.className='ui-primary';name.append(p.name);
  const sub=document.createElement('span');sub.className='ui-sub';const sku=document.createElement('span');sku.className='sku-code';sku.textContent=p.sku;sub.append(sku);name.append(sub);
  const st=document.createElement('td');st.dataset.label='Stok';st.className='ui-num';const badge=document.createElement('span');badge.className='catalog-stock '+stockTone(e);badge.textContent=e.qty<=0n?'Habis':quantityText(e.qty)+' '+p.unit;st.append(badge);
 const price=document.createElement('td');price.dataset.label='Harga';price.className='money ui-num';price.textContent=D.money(D.scaled(priceOf(p),2));if(priceOf(p)!==p.selling_price){price.classList.add('is-contractor');const was=document.createElement('s');was.className='ui-sub';was.textContent=D.money(D.scaled(p.selling_price,2));price.append(was);}
  const act=document.createElement('td');act.className='ui-actions';const btn=document.createElement('button');btn.type='button';btn.className='btn-table-action';btn.dataset.addProduct=p.id;btn.disabled=e.qty<=0n;btn.textContent=btn.disabled?'Habis':'+ Tambah';btn.setAttribute('aria-label',btn.disabled?'Stok habis: '+p.name:'Tambah '+p.name);act.append(btn);
 tr.append(name,st,price,act);return tr;
}
tbody.addEventListener('click',e=>{const button=e.target.closest('[data-add-product]');if(button)add(button.dataset.addProduct);});
function filter(keep){
 if(keep!==true)catalogPage=1;
 const q=search.value.trim().toLocaleLowerCase('id-ID'),cat=category.value,mode=stock.value;
 const matches=[];
 for(const e of prepared){
  if(q.length>=2&&!e.hay.includes(q))continue;
  if(cat&&e.p.category!==cat)continue;
  if(mode==='available'&&!(e.qty>0n))continue;
  if(mode==='low'&&!(e.qty>0n&&e.min>0n&&e.qty<=e.min))continue;
  if(mode==='out'&&e.qty!==0n)continue;
  matches.push(e);
 }
 if(q.length>=2){matches.sort((a,b)=>(b.skuLower===q?1:0)-(a.skuLower===q?1:0));}
 const pages=Math.max(1,Math.ceil(matches.length/PAGE));if(catalogPage>pages)catalogPage=pages;const start=(catalogPage-1)*PAGE,shown=matches.slice(start,start+PAGE),frag=document.createDocumentFragment();
  for(const e of shown){const row=catalogRow(e);row.setAttribute('data-motion-item','');frag.append(row);}
 for(const row of Array.from(tbody.querySelectorAll('tr[data-product]')))row.remove();
 tbody.insertBefore(frag,emptyRow);
 emptyRow.hidden=matches.length>0;
  window.uiNumbers.text(document.querySelector('#product-search-status'),q.length===1?'Ketik minimal 2 huruf nama atau SKU.':(matches.length>shown.length?'Menampilkan '+shown.length+' dari '+matches.length+' produk. Ketik untuk menyaring.':matches.length+' produk ditampilkan.'));
  renderCatalogPager(matches.length,pages,start,shown.length);
  window.uiMotion?.dataChange(tbody);
}
function renderCatalogPager(total,pages,start,count){
 catPager.hidden=total===0;
 catRange.textContent=total?'Menampilkan '+(start+1)+'–'+(start+count)+' dari '+total+' produk':'';
 catPrev.disabled=catalogPage<=1;catNext.disabled=catalogPage>=pages;
 catPages.replaceChildren();
 const from=Math.max(1,Math.min(catalogPage-2,pages-4)),to=Math.min(pages,from+4);
 for(let n=from;n<=to;n++){const b=document.createElement('button');b.type='button';b.dataset.page=n;b.textContent=n;if(n===catalogPage)b.setAttribute('aria-current','page');catPages.append(b);}
}
catPrev.addEventListener('click',()=>{if(catalogPage>1){catalogPage-=1;filter(true);}});
catNext.addEventListener('click',()=>{catalogPage+=1;filter(true);});
catPages.addEventListener('click',e=>{const b=e.target.closest('[data-page]');if(b){catalogPage=Number(b.dataset.page);filter(true);}});
 search.addEventListener('input',filter);category.addEventListener('change',filter);stock.addEventListener('change',filter);document.querySelector('#catalog-reset').addEventListener('click',()=>{search.value='';category.value='';stock.value='available';filter();search.focus();});
search.addEventListener('keydown',e=>{
 if(e.key!=='Enter')return;
 const q=search.value.trim().toLocaleLowerCase('id-ID');
 const exact=q?prepared.find(item=>item.skuLower===q&&item.qty>0n):null;
 if(exact){e.preventDefault();add(exact.p.id);search.select();return;}
 const firstRow=document.querySelector('#products-tbody tr[data-product]:not([hidden])');
 const button=firstRow?.querySelector('[data-add-product]:not(:disabled)');
 if(button){e.preventDefault();add(firstRow.dataset.product);search.select();}
});
// Sinkronisasi stok & harga katalog ringan tanpa muat ulang halaman (dengan penjaga respons usang).
let catalogSyncSeq=0,lastCatalogSync=Date.now();
function syncCartRowMeta(){
 for(const row of rows()){
  const id=row.querySelector('[data-name=product_id]')?.value;
  const p=map.get(String(id));
  if(!p)continue;
  const stockQty=D.scaled(p.stock_quantity,4);
  const st=row.querySelector('.cart-stock');
  if(st){st.className='cart-stock'+(stockQty<=0n?' is-out':'');st.textContent=stockQty<=0n?'Stok habis':'Stok '+quantityText(stockQty)+' '+p.unit;}
 }
}
async function syncCatalogFromServer(force=false){
 if(!catalogUrl)return;
 const nowMs=Date.now();
 if(!force&&nowMs-lastCatalogSync<15000)return;
 const seq=++catalogSyncSeq;
 if(refreshCatalogBtn&&force)refreshCatalogBtn.disabled=true;
 try{
  const res=await fetch(catalogUrl,{credentials:'same-origin',headers:{Accept:'application/json'}});
  if(!res.ok||seq!==catalogSyncSeq)return;
  const payload=await res.json();
  if(!Array.isArray(payload?.products)||seq!==catalogSyncSeq)return;
  lastCatalogSync=Date.now();
  map.clear();
  for(const item of payload.products)map.set(String(item.id),item);
  prepared=payload.products.map(p=>({p,skuLower:(p.sku||'').toLocaleLowerCase('id-ID'),hay:(p.name+' '+p.sku).toLocaleLowerCase('id-ID'),qty:D.scaled(p.stock_quantity,4),min:D.scaled(p.min_stock||'0',4)}));
  syncCartRowMeta();
  filter(true);
  refresh();
  if(force){window.uiNumbers.text(document.querySelector('#product-search-status'),'Stok dan harga katalog diperbarui ('+payload.products.length+' produk aktif).');}
 }catch{
  if(force){window.uiNumbers.text(document.querySelector('#product-search-status'),'Gagal menyegarkan stok katalog. Data terakhir tetap dipertahankan.');}
 }finally{
  if(refreshCatalogBtn&&seq===catalogSyncSeq)refreshCatalogBtn.disabled=false;
 }
}
refreshCatalogBtn?.addEventListener('click',()=>syncCatalogFromServer(true));
document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible'&&!document.querySelector('#panel-kasir')?.hidden)syncCatalogFromServer(false);});
{const kasirPanel=document.querySelector('#panel-kasir');if(kasirPanel)new MutationObserver(()=>{if(!kasirPanel.hidden)syncCatalogFromServer(false);}).observe(kasirPanel,{attributes:true,attributeFilter:['hidden']});}
// Tahan / Hold transaksi sementara di peramban (tidak memotong stok sampai checkout).
function readHeld(){try{const raw=JSON.parse(localStorage.getItem(heldKey)||'[]');return Array.isArray(raw)?raw:[];}catch{return [];}}
function writeHeld(list){try{localStorage.setItem(heldKey,JSON.stringify(list.slice(0,MAX_HELD)));}catch{}}
function syncHeldUi(){
 const list=readHeld();
 if(heldCountEl)heldCountEl.textContent=String(list.length);
 if(heldToggle)heldToggle.hidden=list.length===0;
 if(!heldList)return;
 heldList.replaceChildren();
 if(!list.length){
  const empty=document.createElement('p');empty.className='note';empty.textContent='Belum ada transaksi yang ditahan.';
  heldList.append(empty);return;
 }
 for(const entry of list){
  const card=document.createElement('div');card.className='pos-held-item';
  const meta=document.createElement('div');meta.className='pos-held-meta';
  const title=document.createElement('strong');title.textContent=entry.customer?.name||'Pelanggan umum';
  const info=document.createElement('span');info.textContent=(entry.items?.length||0)+' jenis barang · '+(entry.total_display||'Rp 0')+(entry.held_time?' · '+entry.held_time:'');
  meta.append(title,info);
  const actions=document.createElement('div');actions.className='pos-held-actions';
  const resumeBtn=document.createElement('button');resumeBtn.type='button';resumeBtn.textContent='Lanjutkan';resumeBtn.addEventListener('click',()=>resumeHeld(entry.id));
  const delBtn=document.createElement('button');delBtn.type='button';delBtn.className='secondary';delBtn.textContent='Hapus';delBtn.addEventListener('click',()=>deleteHeld(entry.id));
  actions.append(resumeBtn,delBtn);
  card.append(meta,actions);
  heldList.append(card);
 }
}
function resetCartWorkspace(){
 form.reset();
 for(const row of rows())row.remove();
 const key=form.querySelector('#checkout-key');if(key)key.value=safeUuid();
 applyCustomerOption(null);
 setTier('retail');
 updateNotesCount();
 refresh();
}
function holdCurrentCart(){
 if(!rows().length||saved||submitting)return;
 const list=readHeld();
 if(list.length>=MAX_HELD){error.textContent='Maksimal '+MAX_HELD+' transaksi tertahan. Lanjutkan atau hapus salah satu terlebih dahulu.';error.hidden=false;error.focus();return;}
 const snap=snapshotCart();
 const now=new Date();
 const heldTime=String(now.getHours()).padStart(2,'0')+':'+String(now.getMinutes()).padStart(2,'0');
 list.unshift({...snap,id:safeUuid(),total_display:valid?D.money(total):'Periksa isian',held_time:heldTime});
 writeHeld(list);
 resetCartWorkspace();
 syncHeldUi();
 window.uiResult?.toast?.({title:'Transaksi ditahan',detail:'Keranjang disimpan sementara. Pilih Tertahan untuk melanjutkan.'});
}
function restoreSnapshot(snap){
 if(!snap)return;
 resetCartWorkspace();
 if(snap.customer?.id)applyCustomerOption(snap.customer);
 if(snap.price_tier==='contractor'||snap.price_tier==='retail')setTier(snap.price_tier);
 if(snap.discount_type&&document.querySelector('#inv-disc-type'))document.querySelector('#inv-disc-type').value=snap.discount_type;
 if(typeof snap.discount_value==='string'&&document.querySelector('#inv-disc-value'))document.querySelector('#inv-disc-value').value=snap.discount_value;
 if(notesInput&&typeof snap.notes==='string'){notesInput.value=snap.notes;updateNotesCount();}
 const entries=Array.isArray(snap)?snap:(Array.isArray(snap.items)?snap.items:[]);
 for(const entry of entries)if(map.has(String(entry.id)))add(entry.id,entry);
 refresh();
}
function resumeHeld(holdId){
 const list=readHeld();
 const idx=list.findIndex(item=>item.id===holdId);
 if(idx<0)return;
 const [chosen]=list.splice(idx,1);
 if(rows().length>0&&!saved&&!submitting&&list.length<MAX_HELD){
  const cur=snapshotCart();
  const now=new Date();
  const heldTime=String(now.getHours()).padStart(2,'0')+':'+String(now.getMinutes()).padStart(2,'0');
  list.unshift({...cur,id:safeUuid(),total_display:valid?D.money(total):'Periksa isian',held_time:heldTime});
 }
 writeHeld(list);
 heldDialog?.close();
 restoreSnapshot(chosen);
 syncHeldUi();
 syncCatalogFromServer(false);
}
function deleteHeld(holdId){
 writeHeld(readHeld().filter(item=>item.id!==holdId));
 syncHeldUi();
 if(!readHeld().length)heldDialog?.close();
}
holdButton?.addEventListener('click',holdCurrentCart);
heldToggle?.addEventListener('click',()=>{syncHeldUi();heldDialog?.showModal();});
for(const b of document.querySelectorAll('[data-close-held]'))b.addEventListener('click',()=>heldDialog?.close());
function openPay(){
 if(!rows().length||!valid||saved)return;
 if(document.getElementById('store-shift-state')?.dataset.posShiftState!=='open'){
  error.textContent=document.getElementById('store-shift-reminder')?.textContent||'Belum bisa checkout: kas harian belum aktif. Minta admin memeriksa kas harian. Keranjang tetap tersimpan.';
  error.hidden=false;error.focus();return;
 }
 payment.showModal();refresh();showPaid();paidDisplay.focus({preventScroll:true});paidDisplay.select();
}
function closePay(){if(submitting||form.dataset.busy)return;payment.close();payButton.focus({preventScroll:true});}
function cancelReview(){if(submitting||form.dataset.busy)return;review.close();payment.showModal();document.querySelector('#review-payment').focus({preventScroll:true});}
function trap(dialog,onEscape){dialog.addEventListener('cancel',e=>{e.preventDefault();onEscape();});dialog.addEventListener('keydown',e=>{if(e.key==='Escape'){e.preventDefault();onEscape();return;}if(e.key!=='Tab')return;const nodes=Array.from(dialog.querySelectorAll('button,input,select,textarea,summary,a[href]')).filter(el=>!el.disabled&&el.tabIndex>=0&&el.getBoundingClientRect().height);if(e.shiftKey&&document.activeElement===nodes[0]){e.preventDefault();nodes.at(-1).focus();}else if(!e.shiftKey&&document.activeElement===nodes.at(-1)){e.preventDefault();nodes[0].focus();}});}
trap(payment,closePay);trap(review,cancelReview);if(heldDialog)trap(heldDialog,()=>heldDialog.close());payButton.addEventListener('click',openPay);for(const b of document.querySelectorAll('[data-close-payment]'))b.addEventListener('click',closePay);for(const b of document.querySelectorAll('[data-cancel-review]'))b.addEventListener('click',cancelReview);
for(const el of form.querySelectorAll('input,select,textarea'))if(!el.closest('#items')){el.addEventListener('input',refresh);el.addEventListener('change',refresh);}
function fail(message){const box=form.querySelector('[data-error]');box.textContent=message;box.hidden=false;box.focus();}
 function renderReview(){const box=document.querySelector('#checkout-review');box.replaceChildren();const list=document.createElement('ul');list.className='plain';for(const row of rows()){const li=document.createElement('li');li.textContent=row.querySelector('.cart-product-title').textContent+' × '+row.querySelector('[data-name=quantity]').value+' · '+window.uiNumbers.get(row.querySelector('.item-subtotal'));list.append(li);}box.append(list);const cid=form.elements.customer_id.value;const custText=cid?form.elements.customer_id.selectedOptions[0].textContent.trim():'Pelanggan Umum (Walk-in)';const bankEl=form.elements.bank_account_code;const bankLine=bankEl&&!bankEl.disabled&&bankEl.selectedOptions[0]?[['Rekening bank',bankEl.selectedOptions[0].textContent.trim()]]:[];const dueEl=form.elements.due_date;const dueLine=dueEl&&!dueEl.disabled&&dueEl.value?[['Jatuh tempo',dueEl.value]]:[];const noteVal=notesInput?notesInput.value.trim():'';const noteLine=noteVal?[['Catatan nota',noteVal]]:[];const totalDisc=lastLineDiscount+lastInvDiscount;const breakdownLines=[];if(totalDisc>0n||lastDeliveryFee>0n){breakdownLines.push(['Subtotal barang',D.money(lastGross)]);}if(totalDisc>0n){breakdownLines.push(['Total diskon','−'+D.money(totalDisc)]);}if(form.elements.fulfillment.value!=='delivery'&&lastDeliveryFee>0n){breakdownLines.push(['Ongkir',D.money(lastDeliveryFee)]);}const lines=[['Pembeli',custText],...(contractorOn()?[['Harga','Harga kontraktor']]:[]),...noteLine,...breakdownLines,['Total',D.money(total)],['Metode',form.elements.payment_method.selectedOptions[0].textContent],...bankLine,...dueLine,['Dibayar sekarang',D.money(D.scaled(paid.value||'0',2))],['Kembalian',window.uiNumbers.get(document.querySelector('#display-change'))]];if(form.elements.fulfillment.value==='delivery'){lines.splice(2,0,['Pengiriman','Dikirim ke '+(form.elements.destination.value.trim()||'-')+' · Ongkir '+D.money(D.scaled(document.querySelector('#delivery-fee').value||'0',2))]);}for(const [label,value]of lines){const p=document.createElement('p');p.className='review-line';const title=document.createElement('strong'),v=document.createElement('span');title.textContent=label;v.textContent=value;p.append(title,v);box.append(p);}}
document.querySelector('#review-payment').addEventListener('click',()=>{refresh();if(!valid)return;try{const tender=D.scaled(paid.value||'0',2),method=form.elements.payment_method.value;if(method==='cash'&&tender<total)throw Error('Nominal pembayaran kurang dari total.');if(method==='credit'&&tender>total)throw Error('Uang muka tidak boleh melebihi total.');if((method==='credit'||method==='transfer')&&!form.elements.customer_id.value)throw Error('Pilih pelanggan terdaftar untuk transaksi '+(method==='transfer'?'transfer':'kredit')+'.');if(form.elements.fulfillment.value==='delivery'&&!form.elements.destination.value.trim())throw Error('Tujuan kirim wajib diisi.');const depositEl=form.elements.deposit_method;const needsProof=(method==='transfer'||(method==='credit'&&depositEl&&!depositEl.disabled&&depositEl.value==='transfer'))&&tender>0n;if(needsProof){const proofInput=form.querySelector('#checkout-transfer-proof');const file=proofInput&&!proofInput.disabled?proofInput.files?.[0]:null;if(!file)throw Error('Unggah foto bukti transfer pelanggan terlebih dahulu.');const allowedTypes=['image/jpeg','image/png','image/webp'];const extOk=/\.(jpe?g|png|webp)$/i.test(file.name||'');if((file.type&&!allowedTypes.includes(file.type))||(!file.type&&!extOk))throw Error('Foto bukti transfer harus berformat JPG, PNG, atau WebP.');if(typeof file.size==='number'&&file.size>5*1024*1024)throw Error('Ukuran foto bukti transfer maksimal 5 MB.');}renderReview();payment.close();review.showModal();confirm.focus({preventScroll:true});}catch(e){fail(e.message);}});
let sold=[];const payTitle=payment.querySelector('.popup-heading h2').textContent;
confirm.addEventListener('click',()=>{if(submitting||form.dataset.busy||saved)return;sold=rows().map(row=>({id:row.querySelector('[data-name=product_id]').value,qty:row.querySelector('[data-name=quantity]').value}));submitting=true;confirm.disabled=true;review.close();payment.showModal();for(const input of form.querySelectorAll('[inputmode=decimal]'))input.value=normalize(input.value);form.__finalConfirmed=true;form.requestSubmit(document.querySelector('#checkout-submit'));});
const busy=new MutationObserver(()=>{if(!form.dataset.busy&&submitting&&!saved){submitting=false;confirm.disabled=false;}});busy.observe(form,{attributes:true,attributeFilter:['data-busy']});
document.addEventListener('store-shift:changed',()=>{if(!submitting&&!saved)refresh();});
 form.addEventListener('v2:sale-saved',e=>{saved=true;submitting=false;try{localStorage.removeItem(cartKey);}catch{}payment.querySelector('.popup-heading h2').textContent='Transaksi Tersimpan — Periksa Sebelum Cetak';document.querySelector('#review-payment').hidden=true;payButton.disabled=true;if(holdButton)holdButton.disabled=true;document.querySelector('#cart-empty-row').hidden=false;window.uiNumbers.text(document.querySelector('#cart-item-count'),'0 jenis item');const link=document.querySelector('#checkout-result a');if(link)link.focus({preventScroll:true});const next=document.createElement('button');next.type='button';next.textContent='Penjualan berikutnya';next.addEventListener('click',()=>{document.querySelector('dialog.ur-dialog[open]')?.close();window.kasirNextSale();});document.querySelector('#checkout-result').append(document.createElement('br'),next);});
// Penjualan berikutnya tanpa memuat ulang: stok katalog dikurangi sesuai nota yang baru tersimpan (server sudah mengonfirmasi),
// keranjang dan form dikosongkan, lalu ringkasan/riwayat diperbarui lewat uiLive.
window.kasirNextSale=()=>{
 for(const line of sold){const e=prepared.find(x=>x.p.id===String(line.id));if(!e)continue;try{e.qty-=D.scaled(normalize(line.qty),4);e.p.stock_quantity=D.plain(e.qty,4);}catch{}}
 sold=[];saved=false;submitting=false;confirm.disabled=false;
 form.reset();for(const row of rows())row.remove();
 const key=form.querySelector('#checkout-key');if(key)key.value=safeUuid();
 updateNotesCount();
 payment.querySelector('.popup-heading h2').textContent=payTitle;document.querySelector('#review-payment').hidden=false;document.querySelector('#checkout-result')?.replaceChildren();
 for(const box of form.querySelectorAll('[data-ok],[data-error]'))box.hidden=true;
 form.elements.customer_id.dispatchEvent(new Event('change',{bubbles:true}));form.elements.payment_method.dispatchEvent(new Event('change',{bubbles:true}));
 refresh();filter(true);
 window.uiLive?.refresh();
 syncCatalogFromServer(true);
 search.focus({preventScroll:true});
};
document.addEventListener('keydown',e=>{if(e.key==='/'&&!['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)&&!document.querySelector('dialog[open]')&&!document.querySelector('#panel-kasir').hidden){e.preventDefault();search.focus();}});
try{const restored=JSON.parse(localStorage.getItem(cartKey)||'null');if(restored){if(Array.isArray(restored)){for(const entry of restored)if(map.has(String(entry.id)))add(entry.id,entry);}else{restoreSnapshot(restored);}}}catch{}
syncHeldUi();updateNotesCount();refresh();filter();
})();
</script>
