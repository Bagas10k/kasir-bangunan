<script>
(()=>{
'use strict';
const state=document.getElementById('store-shift-state');
if(!state||window.uiStoreShift)return;
const reminder=document.getElementById('store-shift-reminder');
const admin=state.dataset.manageShift==='1';
let id=state.dataset.storeShiftId||'',todayStatus=state.dataset.todayStatus||'',busy=null,timer=null,stopped=false;
function paint(shift,failed=false,today=null){
 const next=shift?String(shift.id):'';
 const date=String((shift||today)?.opened_at||'').slice(0,10),closedToday=!!today&&today.status!=='open';
 state.dataset.storeShiftId=next;
 state.dataset.posShiftState=failed?'unknown':next?'open':'closed';
 state.querySelector('strong').textContent=failed?'Status kas harian belum dapat diperiksa':next?'Kas harian'+(date?' '+date:'')+' aktif':closedToday?'Kas harian '+date+' sudah ditutup':admin?'Kas harian belum dibuka':'Menunggu admin membuka kas harian';
 state.querySelector('a span').textContent=next||closedToday||!admin?'Lihat kas harian':'Buka kas harian';
 const header=document.querySelector('[data-store-shift-header]');if(header)header.textContent=next||closedToday?'Kas harian '+date:admin?'Kelola kas harian':'Lihat kas harian';
 if(reminder){reminder.hidden=!!next&&!failed;reminder.textContent=failed?'Status kas harian belum dapat diperiksa. Periksa koneksi; keranjang tetap tersimpan.':closedToday?'Kas harian sudah ditutup. Penjualan baru menunggu kas hari berikutnya; keranjang tetap tersimpan.':admin?'Buka kas harian sebelum menyimpan penjualan.':'Menunggu admin membuka kas harian. Keranjang tetap tersimpan.';}
 const openForm=document.getElementById('form-open');
 if(openForm){
  const allowed=!failed&&!next&&!closedToday,opener=document.querySelector('[data-cash-day-open]');
  openForm.dataset.openAllowed=allowed?'1':'0';openForm.querySelector('fieldset').disabled=!allowed;
  if(opener){opener.hidden=!allowed;opener.disabled=!allowed;if(allowed)opener.style.removeProperty('display');else opener.style.setProperty('display','none','important');}
  const help=document.getElementById('cash-day-open-help');if(help)help.textContent=failed?'Status kas harian belum dapat diperiksa. Periksa koneksi.':next?'Kas harian '+date+' masih terbuka. Selesaikan penghitungan dan penutupannya sebelum memulai hari berikutnya.':closedToday?'Kas harian '+date+' sudah ditutup dan tidak dapat dibuka lagi. Catatan baru tersedia pada hari berikutnya.':'Buka satu catatan kas harian untuk tanggal hari ini.';
 }
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
   const next=data.shift?String(data.shift.id):'',dayStatus=data.today?String(data.today.opened_at).slice(0,10)+':'+data.today.status:'',changed=next!==id||dayStatus!==todayStatus;
   id=next;todayStatus=dayStatus;paint(data.shift,false,data.today);
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
