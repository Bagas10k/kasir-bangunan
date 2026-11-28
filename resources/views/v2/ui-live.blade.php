{{--
    Pembaruan tanpa muat ulang halaman (uiLive).
    Setelah data disimpan, halaman yang sama diambil di latar belakang lalu hanya ISI datanya yang diganti:
    - isi tabel (<tbody>) diganti utuh, lalu penyaring/halaman tabel dan perilaku baris dipasang ulang;
    - angka dan teks lain (ringkasan, kartu, status) disalin ke elemen yang sudah ada, tanpa membangun ulang
      kontrol yang sudah dipercantik (select, kalender, popup);
    - pilihan <select> ikut diperbarui (mis. supplier baru langsung bisa dipilih), nilai yang sedang dipilih tetap.
    Formulir, dialog, keranjang kasir, dan elemen ber-[data-live-skip] tidak ditimpa.
    Skrip lain mendaftarkan pemasang perilaku untuk elemen baru lewat uiLive.onPatch(fn(root)).
--}}
<script>
(()=>{
'use strict';
if(window.uiLive)return;
const hooks=[];
// tbody termasuk di sini agar tabel tidak ikut diganti utuh: isinya ditangani swapBody (tabel ui-tables menyimpan rujukan ke <tbody>).
const INTERACTIVE='form,input,select,textarea,button,dialog,details,tbody,[data-ui-table],[data-ui-filter],[popover],[data-live-skip]';
const OPAQUE='script,style,template,[data-live-skip]';
const meaningful=node=>node.nodeType===3&&node.nodeValue.trim()!=='';
const squash=text=>String(text||'').replace(/\s+/g,' ').trim();
const keyOf=el=>el.tagName+(el.id?'#'+el.id:'.'+((el.getAttribute('class')||'').trim().split(/\s+/)[0]||''));
const passive=el=>!el.matches(INTERACTIVE)&&!el.querySelector(INTERACTIVE);
const runHooks=root=>{for(const fn of hooks){try{fn(root);}catch(error){console.error(error);}}};

/** Kunci akar yang sama di halaman lama dan baru: id, aria-labelledby, data-monitoring-task, atau judul ber-id. */
function rootKey(el){
  if(el.id)return 'id:'+el.id;
  const label=el.getAttribute('aria-labelledby');if(label)return 'lb:'+label;
  const task=el.getAttribute('data-monitoring-task');if(task)return 'mt:'+task;
  const heading=el.querySelector(':scope>h2[id],:scope>h3[id],:scope>header h2[id]');
  return heading?'h:'+heading.id:null;
}
function findOld(key){
  const [kind,value]=[key.slice(0,key.indexOf(':')),key.slice(key.indexOf(':')+1)];
  if(kind==='id')return document.getElementById(value);
  if(kind==='lb')return document.querySelector('[aria-labelledby="'+CSS.escape(value)+'"]:is(section,div,article)');
  if(kind==='mt')return document.querySelector('[data-monitoring-task="'+CSS.escape(value)+'"]');
  const heading=document.getElementById(value);return heading?heading.closest('section,div[data-monitoring-task]'):null;
}
function syncAttrs(oldEl,newEl){
  for(const attr of newEl.attributes){
    if(!(attr.name.startsWith('data-')||attr.name==='href'||attr.name==='datetime'||attr.name==='value'&&oldEl.tagName==='DATA'))continue;
    if(oldEl.getAttribute(attr.name)!==attr.value)oldEl.setAttribute(attr.name,attr.value);
  }
}
function imported(nodes){return Array.from(nodes,node=>document.importNode(node,true));}
/** Isi tabel diganti utuh: baris lama (termasuk dialog per baris) dibuang, baris kosong buatan ui-tables dipertahankan. */
function swapBody(oldBody,newBody){
  if(oldBody.closest('[data-live-skip]'))return;
  for(const dialog of oldBody.querySelectorAll('dialog[open]'))dialog.close();
  const keep=Array.from(oldBody.rows).filter(row=>row.hasAttribute('data-ui-empty'));
  const fresh=imported(newBody.childNodes);
  oldBody.replaceChildren(...fresh,...keep);
  runHooks(oldBody);
  oldBody.closest('[data-ui-table]')?._uiTableReload?.();
}
/** Pilihan select disamakan dengan server; nilai yang sedang dipilih dipertahankan bila masih ada. */
function syncSelect(oldSel,newSel){
  if(oldSel.matches('.ui-pick-native,[data-live-skip] select')||oldSel===document.activeElement)return;
  const sign=sel=>Array.from(sel.options,o=>o.value+'\u0001'+o.textContent.trim()+'\u0001'+o.disabled+'\u0001'+JSON.stringify(o.dataset)).join('\u0002');
  if(sign(oldSel)===sign(newSel))return;
  const values=Array.from(oldSel.selectedOptions,o=>o.value);
  oldSel.replaceChildren(...imported(newSel.childNodes));
  for(const option of oldSel.options)option.selected=values.includes(option.value);
  if(!oldSel.multiple&&!values.some(v=>Array.from(oldSel.options).some(o=>o.value===v)))oldSel.selectedIndex=Array.from(oldSel.options).findIndex(o=>o.defaultSelected);
  oldSel.dispatchEvent(new Event('ui-refresh',{bubbles:true}));
}
/** Di dalam formulir hanya pilihan select dan nilai bawaan (mis. kode berikutnya) yang disamakan; isian pengguna tidak disentuh. */
function syncForm(oldForm,newForm){
  for(const newSel of newForm.querySelectorAll('select[id],select[name]')){
    const oldSel=newSel.id?oldForm.querySelector('#'+CSS.escape(newSel.id)):oldForm.querySelector('select[name="'+CSS.escape(newSel.getAttribute('name'))+'"]');
    if(oldSel)syncSelect(oldSel,newSel);
  }
  for(const newInput of newForm.querySelectorAll('input[name]:not([type=hidden]):not([type=file]):not([type=checkbox]):not([type=radio])')){
    const oldInput=oldForm.querySelector('input[name="'+CSS.escape(newInput.getAttribute('name'))+'"]:not([type=hidden])');
    if(oldInput&&oldInput.defaultValue!==newInput.defaultValue&&oldInput.value===oldInput.defaultValue&&oldInput!==document.activeElement){
      oldInput.defaultValue=newInput.defaultValue;oldInput.value=newInput.defaultValue;
    }
  }
}
function syncNode(oldEl,newEl){
  if(oldEl.matches(OPAQUE))return;
  if(newEl.hasAttribute('data-motion-region')){
    if(oldEl.dataset.motionVersion===newEl.dataset.motionVersion)return;
    const previous=window.uiMotion?.dataSnapshot(oldEl);
    syncAttrs(oldEl,newEl);
    if(newEl.hasAttribute('aria-label'))oldEl.setAttribute('aria-label',newEl.getAttribute('aria-label'));
    oldEl.replaceChildren(...imported(newEl.childNodes));
    const more=oldEl.querySelector('.as-more-btn');
    if(more&&oldEl.classList.contains('is-all')){more.setAttribute('aria-expanded','true');more.textContent='Tampilkan 5 besar';}
    window.uiMotion?.dataChange(oldEl,previous);
    return;
  }
  if(newEl.hasAttribute('data-number-key')&&window.uiNumbers){window.uiNumbers.set(oldEl,newEl.textContent);return;}
  if(newEl.hasAttribute('data-number-text')&&window.uiNumbers){window.uiNumbers.text(oldEl,newEl.textContent);return;}
  if(newEl.tagName==='TBODY'){swapBody(oldEl,newEl);return;}
  // Daftar form berulang (mis. tagihan supplier terbuka): isinya diganti utuh, penangan form dipasang di dokumen.
  if(newEl.hasAttribute('data-live-list')){oldEl.replaceChildren(...imported(newEl.childNodes));runHooks(oldEl);return;}
   // Filter bersama memindahkan kontrol ke popover; cocokkan lewat ID/nama, bukan susunan anak aslinya.
   if(newEl.tagName==='FORM'||oldEl.hasAttribute('data-filter-ready')){syncForm(oldEl,newEl);return;}
  if(newEl.tagName==='DIALOG'){for(const form of newEl.querySelectorAll('form[id],form[action]')){const old=form.id?oldEl.querySelector('#'+CSS.escape(form.id)):oldEl.querySelector('form[action="'+CSS.escape(form.getAttribute('action'))+'"]');if(old)syncForm(old,form);}return;}
  if(newEl.tagName==='SELECT'){syncSelect(oldEl,newEl);return;}
  if(newEl.matches('input,textarea'))return;
  syncAttrs(oldEl,newEl);
  if(passive(oldEl)&&passive(newEl)&&!newEl.querySelector('[data-number-key],[data-number-text],[data-motion-region]')){
    if(squash(oldEl.textContent)!==squash(newEl.textContent)||oldEl.childElementCount!==newEl.childElementCount)oldEl.replaceChildren(...imported(newEl.childNodes));
    return;
  }
  // Teks langsung di dalam elemen (mis. "Rp 1.200" di samping ikon) disalin bila susunannya sama.
  const oldText=Array.from(oldEl.childNodes).filter(meaningful),newText=Array.from(newEl.childNodes).filter(meaningful);
  if(oldText.length===newText.length)oldText.forEach((node,i)=>{if(node.nodeValue!==newText[i].nodeValue)node.nodeValue=newText[i].nodeValue;});
  // Anak dicocokkan berurutan menurut tag + id/kelas pertama. Elemen tambahan buatan skrip di halaman lama dilewati.
  const oldKids=Array.from(oldEl.children).filter(el=>!el.matches('script,style,template'));
  let at=0;
  for(const newKid of Array.from(newEl.children)){
    if(newKid.matches('script,style,template'))continue;
    if(oldEl.hasAttribute('data-dialog-host')&&newKid.matches('dialog[id]')){
      const existing=document.getElementById(newKid.id);
      if(existing){syncNode(existing,newKid);}
      else{const dialog=document.importNode(newKid,true);oldEl.append(dialog);runHooks(dialog);}
      continue;
    }
    const key=keyOf(newKid);
    let found=-1;for(let i=at;i<oldKids.length;i++){if(keyOf(oldKids[i])===key){found=i;break;}}
    if(found>=0){syncNode(oldKids[found],newKid);at=found+1;continue;}
    // Elemen baru yang hanya berisi teks/tautan (mis. butir daftar baru) disisipkan; kontrol tidak.
    if(passive(newKid)){const clone=document.importNode(newKid,true);oldKids[at]?oldKids[at].before(clone):oldEl.append(clone);}
  }
}
function patch(doc){
  const seen=new Set();
  for(const root of doc.querySelectorAll('section,[data-monitoring-task],header.main-topbar')){
    const key=root.matches('header.main-topbar')?'hd':rootKey(root);
    if(!key||seen.has(key))continue;
    seen.add(key);
    const old=key==='hd'?document.querySelector('header.main-topbar'):findOld(key);
    if(old&&old!==document.body)syncNode(old,root);
  }
}
let running=null,again=false;
/** Mengambil halaman ini lagi dan menyalin datanya. Mengembalikan true bila berhasil; bila gagal, halaman dimuat ulang. */
function refresh(){
  if(running){again=true;return running;}
  running=(async()=>{
    try{
      const url=new URL(location.href);url.hash='';
      const appliedReportDate=document.getElementById('report-date')?.defaultValue;
      if(appliedReportDate)url.searchParams.set('date',appliedReportDate);
      const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{'Accept':'text/html'}});
      if(!response.ok||response.redirected&&new URL(response.url).pathname!==url.pathname)throw new Error('HTTP '+response.status);
      const doc=new DOMParser().parseFromString(await response.text(),'text/html');
      if(!doc.querySelector('#task-panels'))throw new Error('bukan ruang kerja');
      patch(doc);
      window.uiMotion?.restoreSaved?.();
      document.dispatchEvent(new CustomEvent('ui-live:patched'));
      return true;
    }catch(error){
      console.warn('uiLive: muat ulang penuh', error);
      location.reload();
      return false;
    }finally{
      running=null;
      if(again){again=false;refresh();}
    }
  })();
  return running;
}
/**
 * Dipanggil setelah simpan berhasil: buka kunci form, tutup dialognya, kosongkan form tambah, lalu segarkan data.
 * opts.reset: kosongkan isian (default: true untuk form di dalam dialog tambah / form tanpa baris tabel).
 */
async function saved(form,opts={}){
  if(form){
    delete form.dataset.busy;form.removeAttribute('aria-busy');
    for(const button of form.querySelectorAll('button[type=submit],button:not([type])'))button.disabled=false;
    const dialog=form.closest('dialog');
    if(dialog?.open&&opts.close!==false)dialog.close();
    const reset=opts.reset??!form.closest('tr');
    if(reset){form.reset();form.removeAttribute('data-dirty');for(const box of form.querySelectorAll('[data-ok]'))box.hidden=true;}
    form.dispatchEvent(new CustomEvent('ui-live:saved',{bubbles:true}));
  }
  return refresh();
}
window.uiLive={refresh,saved,runHooks,onPatch:fn=>{hooks.push(fn);}};
})();
</script>
