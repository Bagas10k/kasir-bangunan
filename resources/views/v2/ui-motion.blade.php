{{--
    Sistem gerak bersama (ui-dna bagian 21). Kasir, Operasional, dan Master memakai fungsi yang sama:
    - enter(panel): kartu panel naik bertahap saat pindah menu, ubin ringkasan menyusul, baris tabel ikut bertahap.
    - baris tabel muncul bertahap setiap halaman/saringan tabel berganti (event ui-table:render dari ui-tables).
    - markSaved(form): dipanggil tepat sebelum muat ulang setelah simpan; baris yang disimpan disorot setelah halaman dimuat.
    Hanya tampilan: tidak mengubah data, urutan, atau penangan formulir. Dimatikan saat prefers-reduced-motion.
--}}
<script>
(()=>{
'use strict';
const reduce=()=>matchMedia('(prefers-reduced-motion: reduce)').matches;
const KEY='ui-saved-row',MAX_ROWS=12;
const visibleRows=wrap=>Array.from(wrap.querySelectorAll('tbody tr')).filter(r=>!r.hidden&&!r.hasAttribute('data-ui-empty')&&!r.hasAttribute('data-ui-empty-static')&&r.getClientRects().length);
function staggerRows(wrap,base=0){
 if(reduce()||!wrap||!wrap.getClientRects().length)return;
 const rows=visibleRows(wrap).slice(0,MAX_ROWS);
 for(const [i,row]of rows.entries()){row.classList.remove('ui-row-in');row.style.setProperty('--ui-r',i);row.style.setProperty('--ui-r0',base+'ms');}
 void wrap.offsetWidth;
 for(const row of rows)row.classList.add('ui-row-in');
 clearTimeout(wrap._uiRowTimer);
 wrap._uiRowTimer=setTimeout(()=>{for(const row of rows)row.classList.remove('ui-row-in');},base+MAX_ROWS*26+400);
}
const shown=el=>!el.matches('dialog,script,template,style,[data-remote-modals],.modal-overlay')&&!el.hidden&&el.getClientRects().length>0;
const TILE_SEL='.as-tiles>.as-tile,.as-todo-list>li,.ui-monitor-grid>.ui-tile,.statement-summary>.summary-item,.balance-strip>.balance-tile,.master-summary-strip>.master-summary-item';
const LIST_SEL='.report-table tbody tr,.master-table tbody tr,.journal-stack>.journal-entry';
function staggerItems(items,base=0){
 items=items.filter(el=>el.getClientRects().length).slice(0,MAX_ROWS);if(!items.length)return;
 for(const [i,el]of items.entries()){el.classList.remove('ui-row-in');el.style.setProperty('--ui-r',i);el.style.setProperty('--ui-r0',base+'ms');}
 void items[0].offsetWidth;for(const el of items)el.classList.add('ui-row-in');
 setTimeout(()=>{for(const el of items)el.classList.remove('ui-row-in');},base+MAX_ROWS*26+400);
}
function enter(panel){
 if(!panel||reduce())return;
 // Isi panel jarak jauh (Keuangan) dibungkus satu elemen: turun ke pembungkus itu agar kartu di dalamnya tetap bertahap.
 let root=panel;
 for(let depth=0;depth<3;depth++){const kids=Array.from(root.children).filter(shown);if(kids.length===1&&kids[0].children.length>1)root=kids[0];else break;}
 const parts=Array.from(root.children).filter(shown);
 panel.querySelectorAll('.ui-enter-part').forEach(el=>el.classList.remove('ui-enter-part'));
 parts.forEach((el,i)=>{el.classList.add('ui-enter-part');el.style.setProperty('--ui-i',i);});
 panel.querySelectorAll(TILE_SEL).forEach((tile,i,all)=>{tile.classList.add('ui-enter-tile');tile.style.setProperty('--ui-t',Array.prototype.indexOf.call(tile.parentElement.children,tile));});
 panel.classList.remove('ui-enter');void panel.offsetWidth;panel.classList.add('ui-enter');
 clearTimeout(panel._uiEnterTimer);
 panel._uiEnterTimer=setTimeout(()=>panel.classList.remove('ui-enter'),parts.length*70+700);
 const tables=panel.querySelectorAll('[data-ui-table]');
 tables.forEach(wrap=>staggerRows(wrap,180));
 if(!tables.length)staggerItems(Array.from(panel.querySelectorAll(LIST_SEL)),220);
}
// Baris tabel bertahap saat halaman/saringan berganti. Event ui-table:render tidak menggelembung: dengarkan di fase tangkap.
let booting=true;
document.addEventListener('ui-table:render',e=>{if(!booting)staggerRows(e.target);},true);
function markSaved(form){
 try{
  const panel=form.closest('.task-panel');if(!panel)return;
  const row=form.closest('tr[data-record-id]');
  const field=name=>(form.elements[name]?.value||'').trim();
  const data=row?{id:row.dataset.recordId}:{name:field('name')||field('license_plate'),code:field('sku')||field('code')||field('license_plate')};
  sessionStorage.setItem(KEY,JSON.stringify({panel:panel.id,at:Date.now(),...data}));
 }catch{}
}
function restoreSaved(){
 let data;try{data=JSON.parse(sessionStorage.getItem(KEY)||'null');sessionStorage.removeItem(KEY);}catch{return;}
 if(!data||Date.now()-data.at>60000)return;
 const panel=document.getElementById(data.panel);if(!panel)return;
 const rows=Array.from(panel.querySelectorAll('[data-ui-table] tbody tr'));
 const cellIs=(r,text)=>text&&Array.from(r.cells).some(c=>c.textContent.trim()===text);
 const row=data.id?panel.querySelector('tr[data-record-id="'+CSS.escape(String(data.id))+'"]'):(rows.find(r=>cellIs(r,data.name))||rows.find(r=>cellIs(r,data.code)));
 if(!row)return;
 // Urutan: buka halaman tabel yang memuat baris, gulir ke baris setelah kartu selesai naik, lalu sorot (agar sorotan terlihat penuh).
 row.closest('[data-ui-table]')?.dispatchEvent(new CustomEvent('ui-table:reveal',{detail:{row}}));
 const calm=reduce();
 setTimeout(()=>row.scrollIntoView({block:'center',behavior:calm?'auto':'smooth'}),calm?0:380);
 setTimeout(()=>{row.classList.remove('ui-row-in');row.classList.add('ui-row-saved');setTimeout(()=>row.classList.remove('ui-row-saved'),2600);},calm?0:820);
}
window.uiMotion={enter,staggerRows,markSaved};
// Halaman penuh tanpa panel workspace (mis. Keuangan dibuka langsung): isi main yang dianimasikan.
const start=()=>{booting=false;enter(document.querySelector('.task-panel:not([hidden])')||document.querySelector('body[data-finance-page] main.main-content'));restoreSaved();};
// setTimeout, bukan requestAnimationFrame: rAF tidak berjalan di tab latar sehingga sorotan bisa hilang.
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>setTimeout(start,0));else setTimeout(start,0);
})();
</script>
