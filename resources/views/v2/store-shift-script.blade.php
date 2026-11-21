<script>
(()=>{
'use strict';
const state=document.getElementById('store-shift-state');
if(!state||window.uiStoreShift)return;
const reminder=document.getElementById('store-shift-reminder');
const admin=state.dataset.manageShift==='1';
let id=state.dataset.storeShiftId||'',busy=null,timer=null,stopped=false;
function paint(shift,failed=false){
 const next=shift?String(shift.id):'';
 state.dataset.storeShiftId=next;
 state.dataset.posShiftState=failed?'unknown':next?'open':'closed';
 state.querySelector('strong').textContent=failed?'Status shift belum dapat diperiksa':next?'Shift toko #'+next+' aktif':admin?'Shift toko belum dibuka':'Menunggu admin membuka shift toko';
 state.querySelector('a span').textContent=next||!admin?'Lihat shift':'Buka shift toko';
 const header=document.querySelector('[data-store-shift-header]');if(header)header.textContent=next?'Shift toko #'+next:admin?'Kelola shift toko':'Lihat shift toko';
 if(reminder){reminder.hidden=!!next&&!failed;reminder.textContent=failed?'Status shift belum dapat diperiksa. Periksa koneksi; keranjang tetap tersimpan.':admin?'Buka shift toko sebelum menyimpan penjualan.':'Menunggu admin membuka shift toko. Keranjang tetap tersimpan.';}
 document.dispatchEvent(new CustomEvent('store-shift:changed'));
}
function schedule(){clearTimeout(timer);if(!stopped&&!document.hidden)timer=setTimeout(check,5000);}
async function check(){
 if(stopped||document.hidden)return false;
 if(busy)return busy;
 busy=(async()=>{
  const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),8000);
  try{
   const response=await fetch(state.dataset.statusUrl,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});
   if(response.status===401||response.status===403){stopped=true;throw Error('session');}
   if(!response.ok||response.redirected)throw Error('status');
   const data=await response.json();
   if(!Object.hasOwn(data,'shift')||(data.shift!==null&&(!Number.isSafeInteger(data.shift?.id)||data.shift.id<1)))throw Error('invalid');
   const next=data.shift?String(data.shift.id):'',changed=next!==id;
   id=next;paint(data.shift);
   if(changed)await window.uiLive?.refresh();
   return !!next;
  }catch{paint(null,true);return false;}
  finally{clearTimeout(timeout);busy=null;schedule();}
 })();
 return busy;
}
window.uiStoreShift={check,isOpen:()=>state.dataset.posShiftState==='open'};
document.addEventListener('ui-live:patched',check);
document.addEventListener('visibilitychange',()=>{if(document.hidden)clearTimeout(timer);else check();});
window.addEventListener('focus',check);
window.addEventListener('pagehide',()=>{stopped=true;clearTimeout(timer);});
window.addEventListener('pageshow',()=>{stopped=false;check();});
check();
})();
</script>
