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
const map=new Map(products.map(p=>[p.id,p])),items=document.querySelector('#items'),payment=document.querySelector('#payment-modal-overlay'),review=document.querySelector('#checkout-confirm-overlay'),payButton=document.querySelector('#open-payment'),confirm=document.querySelector('#checkout-confirm-submit'),paid=document.querySelector('#paid-amount'),paidDisplay=document.querySelector('#paid-amount-display'),error=document.querySelector('#cart-error'),quickCust=document.querySelector('#pos-customer-quick');
// Harga kontraktor: dipilih lewat tombol Eceran/Kontraktor (mengikuti pelanggan); produk tanpa harga kontraktor tetap memakai harga jual.
const tierSel=document.querySelector('#price-tier');
const contractorOn=()=>tierSel?.value==='contractor';
const priceOf=p=>contractorOn()&&p.contractor_price?p.contractor_price:p.selling_price;
const cartKey='kasir-v2-cart:'+@json((string)auth()->id());let total=0n,valid=true,submitting=false,saved=false;
const rows=()=>Array.from(items.children);
const normalize=raw=>String(raw??'').trim().replace(',','.');
const quantityText=n=>D.plain(n,4).replace(/0+$/,'').replace(/\.$/,'');
function showPaid(){try{const v=D.scaled(paid.value||'0',2);paidDisplay.value=v===0n?'':D.money(v).slice(3);}catch{}}
paidDisplay.addEventListener('input',()=>{paid.value=paidDisplay.value.replace(/\./g,'').replace(',','.')||'0';refresh();});paidDisplay.addEventListener('blur',showPaid);
function store(){try{localStorage.setItem(cartKey,JSON.stringify(rows().map(row=>{const value=n=>row.querySelector('[data-name='+n+']').value;return {id:value('product_id'),quantity:value('quantity'),discount_type:value('discount_type'),discount_value:value('discount_value')};})));}catch{}}
function syncBuyerState(){
 const cid = form.elements.customer_id.value;
 if (quickCust && quickCust.value !== cid) quickCust.value = cid;
 const isCredit = form.elements.payment_method.value === 'credit';
 const isRegistered = Boolean(cid);
 const badgeText = isRegistered ? 'Pelanggan Terdaftar' : 'Pelanggan Umum';
 const badgeCls = isRegistered ? 'badge-registered' : 'badge-general';
 const pBadge = document.querySelector('#pos-buyer-badge');
 const mBadge = document.querySelector('#modal-buyer-badge');
 if (pBadge) { pBadge.textContent = badgeText; pBadge.className = 'pos-buyer-badge ' + badgeCls; }
 if (mBadge) { mBadge.textContent = badgeText; mBadge.className = 'pos-buyer-badge ' + badgeCls; }
 const pReq = document.querySelector('#pos-buyer-req');
 const mReq = document.querySelector('#customer-required');
 if (pReq) pReq.hidden = !isCredit;
 if (mReq) mReq.textContent = isCredit ? '(wajib)' : '';
 const mWarn = document.querySelector('#modal-credit-warning');
 if (mWarn) mWarn.hidden = !isCredit || isRegistered;
 const pHint = document.querySelector('#pos-buyer-hint');
 const mHint = document.querySelector('#modal-buyer-hint');
 if (isRegistered) {
  const opt = form.elements.customer_id.selectedOptions[0];
  const name = opt ? (opt.dataset.name || opt.textContent.trim()) : 'Terdaftar';
  const code = opt && opt.dataset.code ? ' · [' + opt.dataset.code + ']' : '';
  const desc = name + code + ' · Mendukung transaksi tunai, transfer, dan piutang/tempo.';
  if (pHint) pHint.textContent = desc;
  if (mHint) mHint.textContent = desc;
 } else {
  const desc = isCredit
   ? 'Kredit mewajibkan identitas pelanggan terdaftar. Silakan pilih pelanggan di atas.'
   : 'Penjualan tunai / ambil sendiri di toko. Untuk transaksi tempo/kredit atau proyek, pilih pelanggan terdaftar.';
  if (pHint) pHint.textContent = desc;
  if (mHint) mHint.textContent = desc;
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
 valid=true;error.hidden=true;let base=0n,gross=0n,qtySum=0n,invDisc=0n;
 try{
  for(const row of rows()){
   const p=map.get(row.querySelector('[data-name=product_id]').value),quantity=row.querySelector('[data-name=quantity]').value;
   if(D.scaled(quantity,4)>D.scaled(p.stock_quantity,4))throw Error('Stok '+p.name+' tidak mencukupi. Sesuaikan jumlah atau hapus barang.');
  }
 }catch(e){valid=false;error.textContent=e.message;error.hidden=false;}
 try{for(const row of rows()){const value=n=>row.querySelector('[data-name='+n+']').value,p=map.get(value('product_id')),unit=priceOf(p);const net=D.line(unit,value('quantity'),value('discount_type'),value('discount_value'));window.uiNumbers.set(row.querySelector('.item-subtotal'),D.money(net));base+=net;const q=D.scaled(value('quantity'),4),lineGross=D.divide(D.scaled(unit,2)*q,10000n);const up=row.querySelector('.cart-unit-price');if(up){window.uiNumbers.set(up,D.money(D.scaled(unit,2)));up.classList.toggle('is-contractor',unit!==p.selling_price);}qtySum+=q;gross+=lineGross;row.dataset.lineDiscount=String(lineGross-net);}invDisc=D.discount(base,document.querySelector('#inv-disc-type').value,document.querySelector('#inv-disc-value').value);total=base-invDisc+D.scaled(document.querySelector('#delivery-fee').value||'0',2);}
 catch(e){valid=false;error.textContent=e.message;error.hidden=false;}
 document.querySelector('#cart-empty-row').hidden=rows().length>0;window.uiNumbers.text(document.querySelector('#cart-item-count'),rows().length+' jenis item');
 window.uiNumbers.set(document.querySelector('#display-total'),valid?D.money(total):'Periksa isian');window.uiNumbers.set(document.querySelector('#payment-modal-total'),valid?D.money(total):'Periksa isian');
 if(form.elements.payment_method.value==='transfer'&&valid)paid.value=D.plain(total);
 let change=0n;try{const tender=D.scaled(paid.value||'0',2);if(form.elements.payment_method.value==='cash'&&tender>total)change=tender-total;}catch{}
 window.uiNumbers.set(document.querySelector('#display-change'),D.money(change));payButton.disabled=rows().length===0||!valid||saved||document.getElementById('store-shift-state')?.dataset.posShiftState!=='open';
 document.querySelector('#deposit-field').hidden=form.elements.payment_method.value!=='credit';
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
const prepared=products.map(p=>({p,hay:(p.name+' '+p.sku).toLocaleLowerCase('id-ID'),qty:D.scaled(p.stock_quantity,4),min:D.scaled(p.min_stock||'0',4)}));
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
 const pages=Math.max(1,Math.ceil(matches.length/PAGE));if(catalogPage>pages)catalogPage=pages;const start=(catalogPage-1)*PAGE,shown=matches.slice(start,start+PAGE),frag=document.createDocumentFragment();
 for(const e of shown)frag.append(catalogRow(e));
 for(const row of Array.from(tbody.querySelectorAll('tr[data-product]')))row.remove();
 tbody.insertBefore(frag,emptyRow);
 emptyRow.hidden=matches.length>0;
 document.querySelector('#product-search-status').textContent=q.length===1?'Ketik minimal 2 huruf nama atau SKU.':(matches.length>shown.length?'Menampilkan '+shown.length+' dari '+matches.length+' produk. Ketik untuk menyaring.':matches.length+' produk ditampilkan.');
 renderCatalogPager(matches.length,pages,start,shown.length);
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
search.addEventListener('input',filter);category.addEventListener('change',filter);stock.addEventListener('change',filter);document.querySelector('#catalog-reset').addEventListener('click',()=>{search.value='';category.value='';stock.value='all';filter();search.focus();});search.addEventListener('keydown',e=>{if(e.key==='Enter'){const button=document.querySelector('#products-tbody tr:not([hidden]) [data-add-product]');if(button){e.preventDefault();button.click();search.select();}}});
function openPay(){if(!rows().length||!valid||saved||document.getElementById('store-shift-state')?.dataset.posShiftState!=='open')return;payment.showModal();refresh();showPaid();paidDisplay.focus({preventScroll:true});paidDisplay.select();}
function closePay(){if(submitting||form.dataset.busy)return;payment.close();payButton.focus({preventScroll:true});}
function cancelReview(){if(submitting||form.dataset.busy)return;review.close();payment.showModal();document.querySelector('#review-payment').focus({preventScroll:true});}
function trap(dialog,onEscape){dialog.addEventListener('cancel',e=>{e.preventDefault();onEscape();});dialog.addEventListener('keydown',e=>{if(e.key==='Escape'){e.preventDefault();onEscape();return;}if(e.key!=='Tab')return;const nodes=Array.from(dialog.querySelectorAll('button,input,select,textarea,summary,a[href]')).filter(el=>!el.disabled&&el.tabIndex>=0&&el.getBoundingClientRect().height);if(e.shiftKey&&document.activeElement===nodes[0]){e.preventDefault();nodes.at(-1).focus();}else if(!e.shiftKey&&document.activeElement===nodes.at(-1)){e.preventDefault();nodes[0].focus();}});}
trap(payment,closePay);trap(review,cancelReview);payButton.addEventListener('click',openPay);for(const b of document.querySelectorAll('[data-close-payment]'))b.addEventListener('click',closePay);for(const b of document.querySelectorAll('[data-cancel-review]'))b.addEventListener('click',cancelReview);
for(const el of form.querySelectorAll('input,select'))if(!el.closest('#items')){el.addEventListener('input',refresh);el.addEventListener('change',refresh);}
function fail(message){const box=form.querySelector('[data-error]');box.textContent=message;box.hidden=false;box.focus();}
 function renderReview(){const box=document.querySelector('#checkout-review');box.replaceChildren();const list=document.createElement('ul');list.className='plain';for(const row of rows()){const li=document.createElement('li');li.textContent=row.querySelector('.cart-product-title').textContent+' × '+row.querySelector('[data-name=quantity]').value+' · '+window.uiNumbers.get(row.querySelector('.item-subtotal'));list.append(li);}box.append(list);const cid=form.elements.customer_id.value;const custText=cid?form.elements.customer_id.selectedOptions[0].textContent.trim():'Pelanggan Umum (Walk-in)';const lines=[['Pembeli',custText],...(contractorOn()?[['Harga','Harga kontraktor']]:[]),['Total',D.money(total)],['Metode',form.elements.payment_method.selectedOptions[0].textContent],['Dibayar sekarang',D.money(D.scaled(paid.value||'0',2))],['Kembalian',window.uiNumbers.get(document.querySelector('#display-change'))]];if(form.elements.fulfillment.value==='delivery'){lines.splice(2,0,['Pengiriman','Dikirim ke '+(form.elements.destination.value.trim()||'-')+' · Ongkir '+D.money(D.scaled(document.querySelector('#delivery-fee').value||'0',2))]);}for(const [label,value]of lines){const p=document.createElement('p');p.className='review-line';const title=document.createElement('strong'),v=document.createElement('span');title.textContent=label;v.textContent=value;p.append(title,v);box.append(p);}}
document.querySelector('#review-payment').addEventListener('click',()=>{refresh();if(!valid)return;try{const tender=D.scaled(paid.value||'0',2),method=form.elements.payment_method.value;if(method==='cash'&&tender<total)throw Error('Nominal pembayaran kurang dari total.');if(method==='credit'&&tender>total)throw Error('Uang muka tidak boleh melebihi total.');if(method==='credit'&&!form.elements.customer_id.value)throw Error('Pilih pelanggan untuk transaksi kredit.');if(form.elements.fulfillment.value==='delivery'&&!form.elements.destination.value.trim())throw Error('Tujuan kirim wajib diisi.');renderReview();payment.close();review.showModal();confirm.focus({preventScroll:true});}catch(e){fail(e.message);}});
let sold=[];const payTitle=payment.querySelector('.popup-heading h2').textContent;
confirm.addEventListener('click',()=>{if(submitting||form.dataset.busy||saved)return;sold=rows().map(row=>({id:row.querySelector('[data-name=product_id]').value,qty:row.querySelector('[data-name=quantity]').value}));submitting=true;confirm.disabled=true;review.close();payment.showModal();for(const input of form.querySelectorAll('[inputmode=decimal]'))input.value=normalize(input.value);form.__finalConfirmed=true;form.requestSubmit(document.querySelector('#checkout-submit'));});
const busy=new MutationObserver(()=>{if(!form.dataset.busy&&submitting&&!saved){submitting=false;confirm.disabled=false;}});busy.observe(form,{attributes:true,attributeFilter:['data-busy']});
document.addEventListener('store-shift:changed',()=>{if(!submitting&&!saved)refresh();});
 form.addEventListener('v2:sale-saved',e=>{saved=true;submitting=false;try{localStorage.removeItem(cartKey);}catch{}payment.querySelector('.popup-heading h2').textContent='Transaksi Tersimpan — Periksa Sebelum Cetak';document.querySelector('#review-payment').hidden=true;payButton.disabled=true;document.querySelector('#cart-empty-row').hidden=false;window.uiNumbers.text(document.querySelector('#cart-item-count'),'0 jenis item');const link=document.querySelector('#checkout-result a');if(link)link.focus({preventScroll:true});const next=document.createElement('button');next.type='button';next.textContent='Penjualan berikutnya';next.addEventListener('click',()=>{document.querySelector('dialog.ur-dialog[open]')?.close();window.kasirNextSale();});document.querySelector('#checkout-result').append(document.createElement('br'),next);});
// Penjualan berikutnya tanpa memuat ulang: stok katalog dikurangi sesuai nota yang baru tersimpan (server sudah mengonfirmasi),
// keranjang dan form dikosongkan, lalu ringkasan/riwayat diperbarui lewat uiLive.
window.kasirNextSale=()=>{
 for(const line of sold){const e=prepared.find(x=>x.p.id===String(line.id));if(!e)continue;try{e.qty-=D.scaled(normalize(line.qty),4);e.p.stock_quantity=D.plain(e.qty,4);}catch{}}
 sold=[];saved=false;submitting=false;confirm.disabled=false;
 form.reset();for(const row of rows())row.remove();
 const key=form.querySelector('#checkout-key');if(key)key.value=(crypto.randomUUID?crypto.randomUUID():String(Date.now())+Math.random().toString(16).slice(2));
 payment.querySelector('.popup-heading h2').textContent=payTitle;document.querySelector('#review-payment').hidden=false;document.querySelector('#checkout-result')?.replaceChildren();
 for(const box of form.querySelectorAll('[data-ok],[data-error]'))box.hidden=true;
 form.elements.customer_id.dispatchEvent(new Event('change',{bubbles:true}));form.elements.payment_method.dispatchEvent(new Event('change',{bubbles:true}));
 refresh();filter(true);
 window.uiLive?.refresh();
 search.focus({preventScroll:true});
};
document.addEventListener('keydown',e=>{if(e.key==='/'&&!['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)&&!document.querySelector('dialog[open]')&&!document.querySelector('#panel-kasir').hidden){e.preventDefault();search.focus();}});
try{for(const entry of JSON.parse(localStorage.getItem(cartKey)||'[]'))if(map.has(String(entry.id)))add(entry.id,entry);}catch{}
refresh();filter();
})();
</script>
