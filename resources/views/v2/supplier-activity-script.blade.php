<script>
(()=>{
'use strict';
const dialog=document.getElementById('insight-dialog'),form=document.getElementById('supplier-activity-form');if(!dialog||!form)return;
const results=dialog.querySelector('[data-supplier-results]'),error=dialog.querySelector('[data-supplier-error]'),print=dialog.querySelector('[data-supplier-print]');
const from=form.elements.from,to=form.elements.to,defaults={from:from.value,to:to.value};
let supplier=null,version=0,controller=null;
function endpoint(template,id){return new URL(template.replace('/suppliers/0/','/suppliers/'+id+'/'),location.href);}
async function load(url){
 const current=++version;controller?.abort();controller=new AbortController();
 results.setAttribute('aria-busy','true');error.hidden=true;print.hidden=true;
 try{
  const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});
  if(!response.ok||response.redirected)throw Error('read');
  const data=await response.json();if(current!==version||!dialog.open)return;
  if(typeof data.html!=='string'||typeof data.from!=='string'||typeof data.to!=='string')throw Error('shape');
  results.innerHTML=data.html;
  const address=endpoint(dialog.dataset.supplierPrintUrl,supplier);address.searchParams.set('from',data.from);address.searchParams.set('to',data.to);
  print.href=address.toString();print.hidden=false;
  window.uiMotion?.staggerRows(results);
 }catch(cause){
  if(current!==version||cause.name==='AbortError')return;
  error.textContent='Aktivitas belum dapat dimuat. Periksa koneksi dan rentang tanggal (maksimal 366 hari), lalu tekan Tampilkan.';
  error.hidden=false;
 }finally{if(current===version)results.removeAttribute('aria-busy');}
}
function rangeUrl(){
 const url=endpoint(dialog.dataset.supplierUrl,supplier);
 url.searchParams.set('from',from.value);url.searchParams.set('to',to.value);return url;
}
document.addEventListener('master-insight:open',event=>{
 version++;controller?.abort();results.removeAttribute('aria-busy');print.hidden=true;error.hidden=true;
 if(event.detail.kind!=='supplier'){supplier=null;return;}
 const id=String(event.detail.id);if(!/^[1-9]\d*$/.test(id))return;
 supplier=id;from.value=defaults.from;to.value=defaults.to;
 from.dispatchEvent(new Event('change',{bubbles:true}));to.dispatchEvent(new Event('change',{bubbles:true}));
 results.textContent='Memuat aktivitas supplier…';load(rangeUrl());
});
form.addEventListener('submit',event=>{event.preventDefault();if(supplier&&form.reportValidity())load(rangeUrl());});
results.addEventListener('click',event=>{
 if(event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;
 const link=event.target.closest('.supplier-activity-pages a[href]');if(!link||!supplier)return;
 const url=new URL(link.href,location.href);if(url.origin!==location.origin||url.pathname!==rangeUrl().pathname)return;
 event.preventDefault();load(url);
});
dialog.addEventListener('close',()=>{version++;controller?.abort();supplier=null;print.hidden=true;results.removeAttribute('aria-busy');});
})();
</script>
