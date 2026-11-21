<script>
(()=>{
'use strict';
const root=document.getElementById('sales-history');if(!root)return;
const form=root.querySelector('#history-filter-form'),results=root.querySelector('#history-results'),error=root.querySelector('#history-error');
if(!form||!results||!error)return;
const defaults={sale_search:'',sale_method:'',start_date:'',end_date:'',history_per_page:'10',history_shift:'active'};
const keys=[...Object.keys(defaults),'history_page'];
let applied={...defaults,...Object.fromEntries(new FormData(form)),history_page:root.dataset.historyPage||'1'},sequence=0,controller=null;
function filtered(){form.dataset.filtered=Object.entries(defaults).some(([key,value])=>String(applied[key]??value)!==value)?'1':'0';}
function cleanAddress(){
 const url=new URL(location.href);for(const key of keys)url.searchParams.delete(key);
 if(url.toString()!==location.href)history.replaceState(history.state,'',url.toString());
}
function syncShifts(shifts){
 const select=form.elements.history_shift;if(!select)return;
 const selected=select.value,oldLabel=select.selectedOptions[0]?.textContent;
 const choices=[{id:'active',label:'Shift aktif'},{id:'all',label:'Semua shift'},...shifts];
 if(!choices.some(row=>String(row.id)===selected)&&oldLabel)choices.push({id:selected,label:oldLabel});
 select.replaceChildren(...choices.map(row=>{const option=document.createElement('option');option.value=String(row.id);option.textContent=row.label;return option;}));
 select.value=selected;select.dispatchEvent(new Event('ui-refresh',{bubbles:true}));
}
async function load(filters,announce=true){
 const version=++sequence;controller?.abort();controller=new AbortController();
 const signal=controller.signal,url=new URL(form.dataset.historyUrl,location.href);
 if(url.origin!==location.origin)return;
 for(const key of keys){const value=filters[key];if(value!==undefined&&value!==null&&String(value)!==''&&!(key==='history_page'&&String(value)==='1'))url.searchParams.set(key,String(value));}
 const button=form.querySelector('button[type=submit]');if(button)button.disabled=true;
 results.setAttribute('aria-busy','true');error.hidden=true;
 const timer=setTimeout(()=>controller?.signal===signal&&controller.abort(),15000);
 let message='';
 try{
  const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal});
  if(response.redirected)throw Error('session');
  const data=await response.json();
  if(!response.ok){message=Object.values(data.errors||{}).flat().join(' ')||data.message||'';throw Error('request');}
  if(typeof data.html!=='string'||!data.filters||!Array.isArray(data.shifts))throw Error('invalid');
  if(version!==sequence)return;
  results.innerHTML=data.html;applied={...defaults,...data.filters};syncShifts(data.shifts);filtered();cleanAddress();
 }catch(cause){
  if(version!==sequence)return;
  error.textContent=message||'Riwayat belum dapat dimuat. Periksa koneksi, lalu tekan Terapkan lagi.';
  error.hidden=false;if(announce)error.focus();
 }finally{
  clearTimeout(timer);
  if(version===sequence){if(button)button.disabled=false;results.removeAttribute('aria-busy');}
 }
}
form.addEventListener('submit',event=>{
 event.preventDefault();if(!form.reportValidity())return;
 return load({...defaults,...Object.fromEntries(new FormData(form)),history_page:'1'});
});
root.addEventListener('click',event=>{
 if(event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;
 const reset=event.target.closest('#history-reset'),page=event.target.closest('.pagination a[href]');
 if(reset){
  event.preventDefault();
  for(const [key,value]of Object.entries(defaults)){const field=form.elements[key];if(field){field.value=value;field.dispatchEvent(new Event('change',{bubbles:true}));}}
  return load({...defaults,history_page:'1'});
 }
 if(!page)return;
 const url=new URL(page.href,location.href),number=url.searchParams.get('history_page');
 if(url.origin!==location.origin||!/^\d+$/.test(number||'')||Number(number)<1)return;
 event.preventDefault();return load({...applied,history_page:number});
});
document.addEventListener('ui-live:patched',()=>load(applied,false));
filtered();cleanAddress();
})();
</script>
