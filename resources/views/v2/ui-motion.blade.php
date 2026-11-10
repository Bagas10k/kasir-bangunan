{{--
    Sistem gerak bersama (ui-dna bagian 21). Kasir, Operasional, dan Master memakai fungsi yang sama:
    - enter(panel): kartu panel naik bertahap saat pindah menu, ubin ringkasan menyusul, baris tabel ikut bertahap.
    - baris tabel muncul bertahap setiap halaman/saringan tabel berganti (event ui-table:render dari ui-tables).
    - markSaved(form): baris yang disimpan disorot setelah data diperbarui.
    - Kontinuitas (desain.md bagian 25): morphBox (kotak sama berpindah, isi berganti mulus), dismiss (keluar), reveal (muncul tanpa asal),
      penanda pilihan meluncur antar pilihan, popup memanjang/memendek halus saat isinya berubah.
    Hanya tampilan: tidak mengubah data, urutan, atau penangan formulir. Dimatikan saat prefers-reduced-motion.
--}}
<style id="ui-motion-kit">
.ui-pill-ghost{position:absolute;z-index:0;pointer-events:none}
.ui-pill-sliding>:not(.ui-pill-ghost){position:relative;z-index:1}
html body :is(.ui-pill-target,#u#u#u){background:transparent !important;box-shadow:none !important}
</style>
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
// ===== Kontinuitas gerak: tidak ada yang muncul atau berganti secara tiba-tiba. =====
// Aturan: elemen tanpa asal -> animasi masuk (reveal). Elemen yang punya asal -> morph (kotak yang sama berpindah/berubah ukuran,
// isinya berganti mulus; penanda pilihan meluncur dari pilihan lama ke pilihan baru).
const EASE='cubic-bezier(.22,.8,.24,1)',EASE_IN='cubic-bezier(.55,0,.75,.2)';
// morphBox: kotak tetap satu, posisi/ukurannya berpindah (FLIP), isi lama memudar di atas isi baru yang masuk bertahap.
function morphBox(box,mutate,{duration=300}={}){
 if(reduce()||box.hidden||!box.getClientRects().length){mutate();return;}
 const r0=box.getBoundingClientRect();
 box.querySelectorAll(':scope>.ui-morph-ghost').forEach(g=>g.remove());
 box.getAnimations().forEach(a=>a.cancel());
 const ghost=box.cloneNode(true);ghost.removeAttribute('id');ghost.classList.add('ui-morph-ghost');ghost.setAttribute('aria-hidden','true');ghost.inert=true;
 Object.assign(ghost.style,{position:'absolute',left:'0',top:'0',margin:'0',width:r0.width+'px',height:r0.height+'px',border:'0',boxShadow:'none',background:'transparent',animation:'none',pointerEvents:'none',transform:'none',boxSizing:'border-box'});
 mutate();
 const r1=box.getBoundingClientRect();
 box.append(ghost);
 Object.assign(box.style,{overflow:'hidden',boxSizing:'border-box'});
 const anim=box.animate([{transform:`translate(${r0.left-r1.left}px,${r0.top-r1.top}px)`,width:r0.width+'px',height:r0.height+'px'},{transform:'none',width:r1.width+'px',height:r1.height+'px'}],{duration,easing:EASE});
 ghost.animate([{opacity:1},{opacity:0}],{duration:110,easing:'ease-out',fill:'forwards'});
 Array.from(box.children).filter(c=>c!==ghost).forEach((c,i)=>c.animate([{opacity:0,transform:'translateY(6px)'},{opacity:1,transform:'none'}],{duration:220,delay:70+i*18,easing:EASE,fill:'backwards'}));
 const done=()=>{ghost.remove();box.style.overflow='';box.style.boxSizing='';};
 anim.addEventListener('finish',done);anim.addEventListener('cancel',done);
}
// dismiss: keluar lebih cepat dari masuk (ease-in), baru disembunyikan. Mengembalikan animasi agar bisa dibatalkan.
function dismiss(el,done,{duration=140}={}){
 if(reduce()||el.hidden||!el.getClientRects().length){done();return null;}
 const a=el.animate([{opacity:1,transform:'none'},{opacity:0,transform:'translateX(-4px) scale(.97)'}],{duration,easing:EASE_IN,fill:'forwards'});
 a.addEventListener('finish',()=>{done();a.cancel();});
 return a;
}
// reveal: elemen tanpa asal muncul (mis. pesan galat, bagian form yang baru tampil).
function reveal(el){
 if(reduce()||!el.getClientRects().length)return;
 el.animate([{opacity:0,transform:'translateY(-4px)'},{opacity:1,transform:'none'}],{duration:220,easing:EASE});
}
// Penanda pilihan meluncur (segmen, chip saringan, tab, metode bayar): lapisan sementara bergerak dari pilihan lama ke yang baru.
const PILL_GROUP='[role=radiogroup],[role=tablist],.ui-chips,.ui-segment,.ui-subtabs,.pd-seg,.period-presets';
const isOn=(el,attr,old)=>{if(attr==='class'){const v=old??el.className;return /(^|\s)(is-)?active(\s|$)/.test(String(v));}return String(old??el.getAttribute(attr))==='true';};
function slidePill(group,from,to){
 // Baca gaya akhir tanpa transisi (saat atribut baru berubah, nilai terhitung masih nilai awal transisi).
 const freeze=el=>{el.style.transition='none';};
 freeze(to);freeze(from);
 const cs=getComputedStyle(to),bg=cs.backgroundColor,onColor=cs.color,offColor=getComputedStyle(from).color;
 const thaw=()=>{to.style.transition='';from.style.transition='';to.style.color='';from.style.color='';};
 if(!bg||bg==='transparent'||bg==='rgba(0, 0, 0, 0)'){thaw();return;}
 const g=group.getBoundingClientRect(),a=from.getBoundingClientRect(),b=to.getBoundingClientRect();
 if(!a.width||!b.width||!g.width){thaw();return;}
 if(getComputedStyle(group).position==='static')group.style.position='relative';
 group.querySelectorAll(':scope>.ui-pill-ghost').forEach(p=>p.remove());
 const pill=document.createElement('span');pill.className='ui-pill-ghost';pill.setAttribute('aria-hidden','true');
 Object.assign(pill.style,{left:(b.left-g.left+group.scrollLeft-group.clientLeft)+'px',top:(b.top-g.top+group.scrollTop-group.clientTop)+'px',width:b.width+'px',height:b.height+'px',borderRadius:cs.borderRadius,background:bg,boxShadow:cs.boxShadow});
 group.prepend(pill);group.classList.add('ui-pill-sliding');to.classList.add('ui-pill-target');
 // Warna teks bertukar di tengah perjalanan: teks selalu terbaca di atas atau di luar penanda.
 to.style.color=offColor;from.style.color=onColor;void to.offsetWidth;
 to.style.transition='color .14s ease';from.style.transition='color .14s ease';
 const swap=setTimeout(()=>{to.style.color=onColor;from.style.color=offColor;},120);
 const anim=pill.animate([{transform:`translate(${a.left-b.left}px,${a.top-b.top}px)`,width:a.width+'px',height:a.height+'px'},{transform:'none',width:b.width+'px',height:b.height+'px'}],{duration:240,easing:EASE});
 const done=()=>{clearTimeout(swap);to.style.transition='none';to.classList.remove('ui-pill-target');pill.remove();void to.offsetWidth;thaw();if(!group.querySelector(':scope>.ui-pill-ghost'))group.classList.remove('ui-pill-sliding');};
 anim.addEventListener('finish',done);anim.addEventListener('cancel',done);
}
// Bagian yang muncul di dalam popup/form (atribut hidden dilepas) masuk halus; popup yang berubah tinggi ikut memanjang/memendek.
const NO_REVEAL='tr,tbody,thead,option,optgroup,dialog,template,script,.task-panel,.select-options,.ui-pick-list,.app-calendar,.rail-flyout,.rail-tip,.pos-combo-list,.ui-morph-ghost,[data-no-reveal]';
const watchDialog=new WeakSet();
const sizeWatch=new ResizeObserver(entries=>{
 for(const e of entries){const d=e.target,h=e.borderBoxSize?.[0]?.blockSize??d.getBoundingClientRect().height;
  const prev=d._uiH;d._uiH=h;
  if(prev==null||!d.open||d._uiMorph||reduce()||Date.now()-(d._uiOpenAt||0)<350||Math.abs(h-prev)<3)continue;
  // Tinggi popup ditetapkan tema dengan !important (fit-content), jadi WAAPI tidak berlaku: pakai transisi tinggi inline !important.
  d._uiMorph=true;const set=(k,v)=>d.style.setProperty(k,v,'important');
  set('overflow','hidden');set('transition','none');set('height',prev+'px');void d.offsetHeight;
  set('transition','height .24s '+EASE);set('height',h+'px');
  const end=()=>{clearTimeout(d._uiMorphT);['height','transition','overflow'].forEach(k=>d.style.removeProperty(k));d._uiMorph=false;d._uiH=d.getBoundingClientRect().height;};
  d._uiMorphT=setTimeout(end,300);}
});
function trackDialog(d){if(watchDialog.has(d))return;watchDialog.add(d);sizeWatch.observe(d);}
new MutationObserver(records=>{
 const groups=new Map();
 for(const r of records){
  const el=r.target;if(el.nodeType!==1)continue;
  if(r.attributeName==='open'&&el.tagName==='DIALOG'){if(el.open){el._uiOpenAt=Date.now();el._uiH=null;trackDialog(el);}continue;}
  if(r.attributeName==='hidden'){if(r.oldValue!==null&&!el.hidden&&!el.matches(NO_REVEAL)&&(el.closest('dialog[open]')||el.matches('[data-error],[data-ok],.err,.ok,.uf-error')))reveal(el);continue;}
  const group=el.parentElement?.closest(PILL_GROUP);if(!group||el.classList.contains('ui-pill-ghost'))continue;
  const was=isOn(el,r.attributeName,r.oldValue),now=isOn(el,r.attributeName);
  if(was===now)continue;const g=groups.get(group)||{};if(now)g.to=el;else g.from=el;groups.set(group,g);
 }
 if(reduce())return;
 for(const [group,{from,to}]of groups)if(from&&to&&from!==to)slidePill(group,from,to);
}).observe(document.documentElement,{subtree:true,attributes:true,attributeOldValue:true,attributeFilter:['hidden','open','aria-checked','aria-pressed','aria-selected','class']});
// Judul halaman di kepala berganti dengan pudar-naik, bukan meloncat.
function textIn(el){if(!el||reduce())return;el.animate([{opacity:0,transform:'translateY(4px)'},{opacity:1,transform:'none'}],{duration:220,easing:EASE});}
window.uiMotion={enter,staggerRows,markSaved,restoreSaved,morphBox,dismiss,reveal,slidePill,textIn};
// Halaman penuh tanpa panel workspace (mis. Keuangan dibuka langsung): isi main yang dianimasikan.
const start=()=>{booting=false;enter(document.querySelector('.task-panel:not([hidden])')||document.querySelector('body[data-finance-page] main.main-content'));restoreSaved();};
// setTimeout, bukan requestAnimationFrame: rAF tidak berjalan di tab latar sehingga sorotan bisa hilang.
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>setTimeout(start,0));else setTimeout(start,0);
})();
</script>
