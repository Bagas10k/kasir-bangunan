<script>
(()=>{
'use strict';
const root=document.getElementById('sales-history');if(!root)return;
const form=root.querySelector('#history-filter-form'),results=root.querySelector('#history-results'),error=root.querySelector('#history-error');
if(!form||!results||!error)return;
const defaults={history_period:'week',sale_search:'',sale_method:'',sale_status:'',start_date:root.dataset.historyDefaultStart,end_date:root.dataset.historyToday,history_per_page:'10'};
const periods=[...root.querySelectorAll('[data-history-period]')];
const keys=[...Object.keys(defaults),'history_page'];
let applied={...defaults,...Object.fromEntries(new FormData(form)),history_period:root.dataset.historySelected||'week',history_page:root.dataset.historyPage||'1'},sequence=0,controller=null;
function filtered(){form.dataset.filtered=Object.entries(defaults).some(([key,value])=>String(applied[key]??value)!==value)?'1':'0';for(const button of periods)button.setAttribute('aria-pressed',String(button.dataset.historyPeriod===applied.history_period));}
function cleanAddress(){
 const url=new URL(location.href);for(const key of [...keys,'history_shift'])url.searchParams.delete(key);
 if(url.toString()!==location.href)history.replaceState(history.state,'',url.toString());
}
async function load(filters,announce=true,reset=false){
 const version=++sequence;controller?.abort();controller=new AbortController();
 const signal=controller.signal,url=new URL(form.dataset.historyUrl,location.href);
 if(url.origin!==location.origin)return;
 for(const key of keys){const value=filters[key];if(value!==undefined&&value!==null&&(String(value)!==''||key==='start_date'||key==='end_date')&&!(key==='history_page'&&String(value)==='1'))url.searchParams.set(key,String(value));}
 const button=form.querySelector('button[type=submit]');if(button)button.disabled=true;
 results.setAttribute('aria-busy','true');error.hidden=true;
 const timer=setTimeout(()=>controller?.signal===signal&&controller.abort(),15000);
 let message='';
 try{
  const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal});
  if(response.redirected)throw Error('session');
  const data=await response.json();
  if(!response.ok){message=Object.values(data.errors||{}).flat().join(' ')||(response.status===422?'Filter riwayat tidak valid. Periksa rentang tanggal dan jumlah baris.':response.status===401||response.status===403?'Sesi tidak aktif. Masuk kembali untuk memuat riwayat.':'');throw Error('request');}
  if(typeof data.html!=='string'||!data.filters||typeof data.filters!=='object'||Array.isArray(data.filters))throw Error('invalid');
  if(version!==sequence)return;
  results.innerHTML=data.html;applied={...defaults,...data.filters};
   if(reset){if(applied.history_period==='week'){root.dataset.historyToday=applied.end_date;defaults.start_date=applied.start_date;defaults.end_date=applied.end_date;}for(const [key,value]of Object.entries(applied)){const field=form.elements[key];if(field){field.value=String(value);field.dispatchEvent(new Event('change',{bubbles:true}));}}}
   filtered();cleanAddress();form.dispatchEvent(new Event('ui-filter:applied',{bubbles:true}));
 }catch(cause){
  if(version!==sequence)return;
   error.textContent=message||'Riwayat belum dapat dimuat. Periksa koneksi, lalu tekan Tampilkan lagi.';
  error.hidden=false;if(announce)error.focus();
 }finally{
  clearTimeout(timer);
  if(version===sequence){if(button)button.disabled=false;results.removeAttribute('aria-busy');}
 }
}
form.addEventListener('submit',event=>{
 event.preventDefault();if(!form.reportValidity())return;
 const values=Object.fromEntries(new FormData(form));
 const sameDates=values.start_date===applied.start_date&&values.end_date===applied.end_date;
 return load({...defaults,...values,history_period:sameDates?applied.history_period:'custom',history_page:'1'});
});
root.addEventListener('click',event=>{
 if(event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;
 const period=event.target.closest('[data-history-period]');
 if(period){event.preventDefault();return load({...applied,history_period:period.dataset.historyPeriod,start_date:undefined,end_date:undefined,history_page:'1'},true,true);}
 const reset=event.target.closest('#history-reset'),page=event.target.closest('.pagination a[href]');
 if(reset){
  event.preventDefault();
   return load({history_period:'week',sale_search:'',sale_method:'',history_per_page:'10'},true,true);
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
