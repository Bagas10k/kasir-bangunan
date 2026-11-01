<script>
// Exact preview arithmetic; the server remains the authority at final save.
window.KasirDecimal=(()=>{
 const pow=n=>10n**BigInt(n);
 function scaled(raw,places){const s=String(raw??'').trim().replace(',','.');if(!/^\d{1,17}(\.\d{1,6})?$/.test(s))throw Error('Isi angka positif tanpa pemisah ribuan.');const [i,f='']=s.split('.');const digits=(f+'0'.repeat(places+1));let n=BigInt(i)*pow(places)+BigInt(digits.slice(0,places)||'0');if(digits[places]>='5')n++;return n;}
 const divide=(n,d)=>(n+d/2n)/d;
 function plain(n,p=2){const s=n.toString().padStart(p+1,'0');return p?s.slice(0,-p)+'.'+s.slice(-p):s;}
 function money(n){const s=plain(n),[i,f]=s.split('.');return 'Rp '+i.replace(/\B(?=(\d{3})+(?!\d))/g,'.')+','+f;}
 function discount(base,type,raw){if(!type||String(raw).trim()==='')return 0n;const v=scaled(raw,2);if(type==='percent'&&v>10000n)throw Error('Diskon persen maksimal 100.');const result=type==='percent'?divide(base*v,10000n):v;if(result>base)throw Error('Diskon tidak boleh melebihi harga barang.');return result;}
 function line(price,quantity,type='',value=''){const q=scaled(quantity,4);if(q<=0n)throw Error('Jumlah harus lebih dari 0.');const gross=divide(scaled(price,2)*q,10000n);return gross-discount(gross,type,value);}
 return {scaled,plain,money,discount,line,divide};
})();
(()=>{
'use strict';
const D=window.KasirDecimal,form=document.querySelector('#form-checkout');if(!form)return;
@php
    $posProducts = collect($products)->map(fn($p) => ['id'=>(string)$p->id,'sku'=>(string)($p->sku??''),'name'=>(string)$p->name,'category'=>(string)($p->category?:'Umum'),'selling_price'=>(string)$p->selling_price,'stock_quantity'=>(string)$p->stock_quantity,'unit'=>(string)$p->unit])->values();
@endphp
const products=@json($posProducts);
const map=new Map(products.map(p=>[p.id,p])),items=document.querySelector('#items'),payment=document.querySelector('#payment-modal-overlay'),review=document.querySelector('#checkout-confirm-overlay'),payButton=document.querySelector('#open-payment'),confirm=document.querySelector('#checkout-confirm-submit'),paid=document.querySelector('#paid-amount'),paidDisplay=document.querySelector('#paid-amount-display'),error=document.querySelector('#cart-error');
const cartKey='kasir-v2-cart:'+@json((string)auth()->id());let total=0n,valid=true,submitting=false,saved=false;
const rows=()=>Array.from(items.children);
const normalize=raw=>String(raw??'').trim().replace(',','.');
const quantityText=n=>D.plain(n,4).replace(/0+$/,'').replace(/\.$/,'');
function showPaid(){try{paidDisplay.value=D.money(D.scaled(paid.value||'0',2)).slice(3);}catch{}}
paidDisplay.addEventListener('input',()=>{paid.value=paidDisplay.value.replace(/\./g,'').replace(',','.');refresh();});paidDisplay.addEventListener('blur',showPaid);
function store(){try{localStorage.setItem(cartKey,JSON.stringify(rows().map(row=>{const value=n=>row.querySelector('[data-name='+n+']').value;return {id:value('product_id'),quantity:value('quantity'),discount_type:value('discount_type'),discount_value:value('discount_value')};})));}catch{}}
function refresh(){
 valid=true;error.hidden=true;let base=0n;
 try{for(const row of rows()){const value=n=>row.querySelector('[data-name='+n+']').value,p=map.get(value('product_id'));const net=D.line(p.selling_price,value('quantity'),value('discount_type'),value('discount_value'));row.querySelector('.item-subtotal').textContent=D.money(net);base+=net;}total=base-D.discount(base,document.querySelector('#inv-disc-type').value,document.querySelector('#inv-disc-value').value)+D.scaled(document.querySelector('#delivery-fee').value||'0',2);}
 catch(e){valid=false;error.textContent=e.message;error.hidden=false;}
 document.querySelector('#cart-empty-row').hidden=rows().length>0;document.querySelector('#cart-item-count').textContent=rows().length+' jenis item';
 document.querySelector('#display-total').textContent=valid?D.money(total):'Periksa isian';document.querySelector('#payment-modal-total').textContent=valid?D.money(total):'Periksa isian';
 if(form.elements.payment_method.value==='transfer'&&valid)paid.value=D.plain(total);
 let change=0n;try{const tender=D.scaled(paid.value||'0',2);if(form.elements.payment_method.value==='cash'&&tender>total)change=tender-total;}catch{}
 document.querySelector('#display-change').textContent=D.money(change);payButton.disabled=rows().length===0||!valid||saved;
 document.querySelector('#deposit-field').hidden=form.elements.payment_method.value!=='credit';document.querySelector('#customer-required').textContent=form.elements.payment_method.value==='credit'?'(wajib)':'';
 document.querySelector('#delivery-fields').hidden=form.elements.fulfillment.value!=='delivery';if(document.activeElement!==paidDisplay)showPaid();store();
}
function add(id,state=null){const p=map.get(String(id));if(!p)return;let row=rows().find(r=>r.querySelector('[data-name=product_id]').value===p.id);if(row&&!state){const q=row.querySelector('[data-name=quantity]');try{q.value=quantityText(D.scaled(q.value,4)+10000n);}catch{q.value='1';}refresh();return;}
 row=window.v2AddCartRow(false);row.querySelector('[data-name=product_id]').value=p.id;row.querySelector('.cart-product-title').textContent=p.name;row.querySelector('.cart-product-meta').textContent=p.sku+' · stok '+p.stock_quantity+' '+p.unit;
 row.querySelector('[data-name=quantity]').value=state?.quantity||'1';row.querySelector('[data-name=discount_type]').value=state?.discount_type||'';row.querySelector('[data-name=discount_value]').value=state?.discount_value||'';
 for(const b of row.querySelectorAll('[data-step]'))b.addEventListener('click',()=>{const q=row.querySelector('[data-name=quantity]');try{const next=D.scaled(q.value,4)+(b.dataset.step==='1'?10000n:-10000n);q.value=quantityText(next>0n?next:1n);}catch{q.value='1';}refresh();});
 row.addEventListener('input',refresh);row.addEventListener('change',refresh);row.querySelector('[data-remove]').addEventListener('click',refresh);refresh();
}
for(const button of document.querySelectorAll('[data-add-product]'))button.addEventListener('click',()=>add(button.dataset.addProduct));
const search=document.querySelector('#pos-product-search'),category=document.querySelector('#product-category-filter'),stock=document.querySelector('#product-stock-filter');
function filter(){const q=search.value.trim().toLocaleLowerCase('id-ID'),cat=category.value;let count=0;for(const row of document.querySelectorAll('#products-tbody [data-product]')){const p=map.get(row.dataset.product),available=D.scaled(p.stock_quantity,4);const match=(q.length<2||(p.name+' '+p.sku).toLocaleLowerCase('id-ID').includes(q))&&(!cat||p.category===cat)&&(stock.value==='all'||stock.value==='available'&&available>0n||stock.value==='low'&&available>0n&&available<=100000n||stock.value==='out'&&available===0n);row.hidden=!match;if(match)count++;}document.querySelector('#catalog-empty').hidden=count>0;document.querySelector('#product-search-status').textContent=q.length===1?'Ketik minimal 2 huruf nama atau SKU.':count+' produk ditampilkan.';}
search.addEventListener('input',filter);category.addEventListener('change',filter);stock.addEventListener('change',filter);document.querySelector('#catalog-reset').addEventListener('click',()=>{search.value='';category.value='';stock.value='all';filter();search.focus();});search.addEventListener('keydown',e=>{if(e.key==='Enter'){const button=document.querySelector('#products-tbody tr:not([hidden]) [data-add-product]');if(button){e.preventDefault();button.click();search.select();}}});
function openPay(){if(!rows().length||!valid||saved)return;payment.showModal();refresh();showPaid();paidDisplay.focus({preventScroll:true});paidDisplay.select();}
function closePay(){if(submitting||form.dataset.busy)return;payment.close();payButton.focus({preventScroll:true});}
function cancelReview(){if(submitting||form.dataset.busy)return;review.close();payment.showModal();document.querySelector('#review-payment').focus({preventScroll:true});}
function trap(dialog,onEscape){dialog.addEventListener('cancel',e=>{e.preventDefault();onEscape();});dialog.addEventListener('keydown',e=>{if(e.key==='Escape'){e.preventDefault();onEscape();return;}if(e.key!=='Tab')return;const nodes=Array.from(dialog.querySelectorAll('button,input,select,textarea,summary,a[href]')).filter(el=>!el.disabled&&el.tabIndex>=0&&el.getBoundingClientRect().height);if(e.shiftKey&&document.activeElement===nodes[0]){e.preventDefault();nodes.at(-1).focus();}else if(!e.shiftKey&&document.activeElement===nodes.at(-1)){e.preventDefault();nodes[0].focus();}});}
trap(payment,closePay);trap(review,cancelReview);payButton.addEventListener('click',openPay);for(const b of document.querySelectorAll('[data-close-payment]'))b.addEventListener('click',closePay);for(const b of document.querySelectorAll('[data-cancel-review]'))b.addEventListener('click',cancelReview);
for(const el of form.querySelectorAll('input,select'))if(!el.closest('#items')){el.addEventListener('input',refresh);el.addEventListener('change',refresh);}
function fail(message){const box=form.querySelector('[data-error]');box.textContent=message;box.hidden=false;box.focus();}
function renderReview(){const box=document.querySelector('#checkout-review');box.replaceChildren();const list=document.createElement('ul');list.className='plain';for(const row of rows()){const li=document.createElement('li');li.textContent=row.querySelector('.cart-product-title').textContent+' × '+row.querySelector('[data-name=quantity]').value+' · '+row.querySelector('.item-subtotal').textContent;list.append(li);}box.append(list);for(const [label,value]of [['Total',D.money(total)],['Metode',form.elements.payment_method.selectedOptions[0].textContent],['Dibayar sekarang',D.money(D.scaled(paid.value,2))],['Kembalian',document.querySelector('#display-change').textContent]]){const p=document.createElement('p');p.className='review-line';const title=document.createElement('strong'),v=document.createElement('span');title.textContent=label;v.textContent=value;p.append(title,v);box.append(p);}}
document.querySelector('#review-payment').addEventListener('click',()=>{refresh();if(!valid)return;try{const tender=D.scaled(paid.value||'0',2),method=form.elements.payment_method.value;if(method==='cash'&&tender<total)throw Error('Nominal pembayaran kurang dari total.');if(method==='credit'&&tender>total)throw Error('Uang muka tidak boleh melebihi total.');if(method==='credit'&&!form.elements.customer_id.value)throw Error('Pilih pelanggan untuk transaksi kredit.');if(form.elements.fulfillment.value==='delivery'&&!form.elements.destination.value.trim())throw Error('Tujuan kirim wajib diisi.');renderReview();payment.close();review.showModal();confirm.focus({preventScroll:true});}catch(e){fail(e.message);}});
confirm.addEventListener('click',()=>{if(submitting||form.dataset.busy||saved)return;submitting=true;confirm.disabled=true;review.close();payment.showModal();for(const input of form.querySelectorAll('[inputmode=decimal]'))input.value=normalize(input.value);form.__finalConfirmed=true;form.requestSubmit(document.querySelector('#checkout-submit'));});
const busy=new MutationObserver(()=>{if(!form.dataset.busy&&submitting&&!saved){submitting=false;confirm.disabled=false;}});busy.observe(form,{attributes:true,attributeFilter:['data-busy']});
form.addEventListener('v2:sale-saved',e=>{saved=true;submitting=false;try{localStorage.removeItem(cartKey);}catch{}payment.querySelector('.popup-heading h2').textContent='Transaksi Tersimpan — Periksa Sebelum Cetak';document.querySelector('#review-payment').hidden=true;payButton.disabled=true;document.querySelector('#cart-empty-row').hidden=false;document.querySelector('#cart-item-count').textContent='0 jenis item';const link=document.querySelector('#checkout-result a');if(link)link.focus({preventScroll:true});const next=document.createElement('button');next.type='button';next.textContent='Penjualan berikutnya';next.addEventListener('click',()=>location.reload());document.querySelector('#checkout-result').append(document.createElement('br'),next);});
document.addEventListener('keydown',e=>{if(e.key==='/'&&!['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)&&!document.querySelector('dialog[open]')&&!document.querySelector('#panel-kasir').hidden){e.preventDefault();search.focus();}});
try{for(const entry of JSON.parse(localStorage.getItem(cartKey)||'[]'))if(map.has(String(entry.id)))add(entry.id,entry);}catch{}
refresh();filter();
})();
</script>
