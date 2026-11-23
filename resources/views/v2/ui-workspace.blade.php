<script>
(()=>{
'use strict';
const navigation=document.querySelector('.nav');
const links=Array.from(navigation?.querySelectorAll('[data-task]')||[]);
const host=document.querySelector('#task-panels');
if(!host||!links.length)return;
const groups={kasir:['#kasir','#h-sales'],tagihan:['#tagihan','#h-verify','#reject-heading'],pengiriman:['#pengiriman','#trip-heading','#uang-muka-sopir','#h-deliveries'],'master-product':['#master-section-product'],'master-customer':['#master-section-customer'],'master-supplier':['#master-section-supplier'],'master-employee':['#master-section-staff','#master-section-employee','#master-section-driver'],'master-vehicle':['#master-section-vehicle'],penerimaan:['#receipt-heading','#receipt-drafts','#receipt-history'],shift:['#h-shift','#review-heading','#opening-heading'],laporan:['#laporan']};
const text={kasir:['Kasir','Pilih barang di katalog, lalu lanjutkan pembayaran.'],tagihan:['Tagihan & pembayaran','Piutang pelanggan, utang supplier, dan pemeriksaan pembayaran.'],pengiriman:['Pengiriman & sopir','Surat jalan, status pengiriman, dan biaya perjalanan.'],'master-product':['Produk','Katalog barang: satuan, kategori, harga jual, stok minimum, dan supplier.'],'master-customer':['Pelanggan','Opsional saat penjualan; kasir boleh mengetik nama pembeli langsung.'],'master-supplier':['Supplier','Pemasok barang. Tekan nama supplier untuk melihat produk dan riwayat transaksinya.'],'master-employee':['Karyawan & sopir','Satu daftar staf toko; kolom posisi menandai siapa yang bertugas sebagai sopir.'],'master-vehicle':['Mobil','Armada pengiriman. Tekan nomor polisi untuk melihat riwayat pemakaian dan sopirnya.'],penerimaan:['Penerimaan barang','Catat barang masuk dan tagihan supplier melalui formulir penerimaan.'],shift:['Kas harian','Satu catatan laci per tanggal, uang fisik, dan pemeriksaan selisih.'],laporan:['Laporan harian','Tunai, transfer terverifikasi, dan transfer menunggu dipisahkan.']};
groups.akun=['#login-accounts'];text.akun=['Akun Login','Kelola akun Admin dan Kasir; terpisah dari master karyawan.'];
const panels=new Map();
groups.kasir=['#kasir'];
groups['riwayat-penjualan']=['#h-sales'];
text['riwayat-penjualan']=['Riwayat penjualan','Seluruh nota, piutang penjualan, pembayaran cicilan, dan cetak nota.'];
groups['master-unit']=['#master-section-unit'];text['master-unit']=['Satuan','Satuan dipakai pada setiap produk; mengarsipkan tidak menghapus riwayat.'];
groups['master-category']=['#master-section-category'];text['master-category']=['Kategori','Kelompok produk untuk mempermudah pencarian di kasir.'];
groups.ringkasan=['#admin-overview'];text.ringkasan=['Ringkasan Admin','Pengawasan operasional seluruh kasir.'];
groups.persetujuan=['#h-verify','#reject-heading'];text.persetujuan=navigation.dataset.role==='admin'?['Verifikasi pembayaran','Periksa transfer pelanggan dan pengajuan pembayaran supplier.']:['Status pembayaran','Pantau pembayaran yang Anda ajukan dan hasil pemeriksaan admin.'];
groups.tagihan=['#tagihan'];
const defaultTask=navigation.dataset.defaultTask||'kasir';
const financialEntry=navigation.querySelector('[data-menu-group="keuangan"] a');
if(location.hash==='#keuangan'&&financialEntry){location.hash='#'+financialEntry.dataset.task;}
// Halaman keuangan (jurnal, laporan, bagan akun) dimuat ke panel saat dibuka, bukan ikut muatan awal.
const remoteState=new Map();const remoteLast=new Map();
const remoteCache=new Map();        // kunci -> {url, html, at}
const remoteFetching=new Map();     // mencegah permintaan ganda untuk alamat yang sama
const remoteVersions=new Map();
document.addEventListener('ui-remote:changed',event=>{
 const url=new URL(event.detail?.url||location.href,location.href);if(url.origin!==location.origin)return;
 for(const link of links){if(!link.dataset.remote||new URL(link.dataset.remote,location.href).pathname!==url.pathname)continue;
  const key=link.dataset.task;remoteVersions.set(key,(remoteVersions.get(key)||0)+1);remoteCache.delete(key);remoteFetching.delete(key);
 }
});
const CACHE_FRESH_MS=20000;         // isi selama ini dianggap masih baru
function partialUrl(url){const u=new URL(url,location.href);u.searchParams.set('partial','1');return u.toString();}
// Halaman Keuangan lama (Bagan akun) memanggil openModal/closeModal/showLockInfo milik layouts.app, yang tidak dimuat workspace.
// Tanpa pengganti ini, Tambah akun/Ubah/Terkunci di panel tidak bereaksi. Perilaku sama dengan versi layouts.app.
if(typeof window.openModal!=='function'){
 window.openModal=id=>{const m=document.getElementById(id);if(!m)return;if(m.parentElement!==document.body)document.body.appendChild(m);m.classList.add('open');document.body.style.overflow='hidden';const first=m.querySelector('input:not([type=hidden]):not([readonly]),.select-trigger,textarea');if(first)setTimeout(()=>first.focus(),80);};
 window.closeModal=id=>{const m=document.getElementById(id);if(!m)return;m.classList.remove('open');document.body.style.overflow='';};
}
if(typeof window.showLockInfo!=='function'){
 window.showLockInfo=(title,message)=>{
  let d=document.getElementById('lock-info-dialog');
  if(!d){d=document.createElement('dialog');d.id='lock-info-dialog';d.className='input-dialog uf-dialog';d.setAttribute('aria-labelledby','lock-info-dialog-title');
   d.innerHTML='<div class="popup-heading"><h2 id="lock-info-dialog-title"></h2><button type="button" class="popup-close" aria-label="Tutup">×</button></div><div class="popup-content"><p class="note"></p><div class="uf-actions"><span></span><button type="button" class="uf-primary">Mengerti</button></div></div>';
   d.addEventListener('click',e=>{if(e.target===d||e.target.closest('.popup-close,.uf-primary'))d.close();});document.body.append(d);}
  d.querySelector('h2').textContent=title;d.querySelector('.note').textContent=message;d.showModal();d.querySelector('.uf-primary').focus();
 };
}
const remoteCleanup=new Map(); // kunci -> fungsi penghenti
function runScripts(root,key){
 const timers=[],listeners=[];
 const realInterval=window.setInterval,realTimeout=window.setTimeout;
 const targets=[document,window];
 const realAdd=targets.map(target=>target.addEventListener);
 window.setInterval=function(...args){const id=realInterval.apply(window,args);timers.push(['i',id]);return id;};
 window.setTimeout=function(...args){const id=realTimeout.apply(window,args);timers.push(['t',id]);return id;};
 targets.forEach((target,i)=>{target.addEventListener=function(type,handler,options){listeners.push([target,type,handler,options]);return realAdd[i].call(target,type,handler,options);};});
 try{
  for(const old of root.querySelectorAll('script')){
   const copy=document.createElement('script');
   for(const attribute of old.attributes)copy.setAttribute(attribute.name,attribute.value);
   copy.textContent=old.textContent;old.replaceWith(copy);
  }
 }finally{
  window.setInterval=realInterval;window.setTimeout=realTimeout;
  targets.forEach((target,i)=>{target.addEventListener=realAdd[i];});
 }
 remoteCleanup.set(key,()=>{
  for(const [kind,id]of timers)kind==='i'?clearInterval(id):clearTimeout(id);
  for(const [target,type,handler,options]of listeners)target.removeEventListener(type,handler,options);
 });
}
function stopRemote(key){
 const stop=remoteCleanup.get(key);
 if(stop){stop();remoteCleanup.delete(key);}
}
function remoteBusy(panel,message){
  panel.replaceChildren();
  panel.removeAttribute('aria-busy');
  if(message==='Memuat halaman…'){
   const skeleton=document.getElementById('workspace-skeleton-template');
   if(skeleton){panel.setAttribute('aria-busy','true');panel.append(skeleton.content.cloneNode(true));return;}
  }
 const box=document.createElement('p');box.className='ui-status-line';box.setAttribute('role','status');box.textContent=message;panel.append(box);
}
function fetchRemote(key,url){
 const pending=remoteFetching.get(key);
 if(pending&&pending.url===url)return pending.promise;
 const version=remoteVersions.get(key)||0;
 const promise=fetch(partialUrl(url),{credentials:'same-origin',headers:{'X-Requested-With':'fetch'}})
  .then(response=>{if(!response.ok)throw new Error('HTTP '+response.status);return response.text();})
  .then(html=>{if((remoteVersions.get(key)||0)===version)remoteCache.set(key,{url,html,at:Date.now()});return html;})
  .finally(()=>{if(remoteFetching.get(key)?.promise===promise)remoteFetching.delete(key);});
 remoteFetching.set(key,{url,promise});
 return promise;
}
function paintRemote(key,url,html){
  const panel=panels.get(key);if(!panel)return;
  panel.removeAttribute('aria-busy');
 stopRemote(key);
 const holder=document.createElement('div');holder.innerHTML=html;
 panel.replaceChildren(...holder.childNodes);
 runScripts(panel,key);
 remoteState.set(key,url);remoteLast.set(key,url);
 bindRemote(key,panel);
}
/** Isi sedang dipakai bila ada dialog terbuka atau kolom isian sedang disentuh; jangan ditimpa diam-diam. */
function remoteBusyWithUser(panel){
 if(panel.querySelector('dialog[open]'))return true;
 const focused=document.activeElement;
 return !!(focused&&panel.contains(focused)&&focused.matches('input,select,textarea'));
}
async function loadRemote(key,url,{force=false}={}){
 const panel=panels.get(key);if(!panel)return;
 const version=remoteVersions.get(key)||0;
 if(!force&&remoteState.get(key)===url)return;
 const cached=remoteCache.get(key);
 const usable=cached&&cached.url===url;
 if(usable){
  paintRemote(key,url,cached.html);window.uiMotion?.enter(panel); // langsung tampil dari simpanan
  if(Date.now()-cached.at<=CACHE_FRESH_MS&&!force)return; // masih baru, tidak perlu diambil lagi
  try{
   const html=await fetchRemote(key,url);
   if((remoteVersions.get(key)||0)===version&&active===key&&html!==cached.html&&!remoteBusyWithUser(panel))paintRemote(key,url,html);
  }catch{}
  return;
 }
 remoteBusy(panel,'Memuat halaman…');
 try{
   const html=await fetchRemote(key,url);
   if((remoteVersions.get(key)||0)!==version)return;
   paintRemote(key,url,html);window.uiMotion?.enter(panel);
 }catch(error){
  remoteBusy(panel,'Halaman tidak dapat dimuat di sini.');
  const link=document.createElement('a');link.className='ui-btn';link.href=url;link.textContent='Buka halaman penuh';panel.append(link);
 }
}
/** Menyiapkan isi sebelum menu ditekan, selagi kursor masih di atasnya. */
function prefetchRemote(link){
 const key=link.dataset.task,url=remoteLast.get(key)||link.dataset.remote;
 if(!key||!url)return;
 const cached=remoteCache.get(key);
 if(cached&&cached.url===url&&Date.now()-cached.at<=CACHE_FRESH_MS)return;
 fetchRemote(key,url).catch(()=>{});
}
function bindRemote(key,panel){
 // Saringan (GET) tetap di dalam panel; simpan/hapus (POST) dan unduhan dibiarkan memuat halaman seperti biasa.
 for(const form of panel.querySelectorAll('form')){
  if((form.getAttribute('method')||'get').toLowerCase()!=='get')continue;
  form.addEventListener('submit',event=>{
   event.preventDefault();
   const data=new URLSearchParams(new FormData(form));
   const url=new URL(form.getAttribute('action')||location.href,location.href);
   for(const [field,value]of data)url.searchParams.set(field,value);
   remoteState.delete(key);loadRemote(key,url.toString(),{force:true});
  });
 }
 for(const link of panel.querySelectorAll('a[href]')){
  const url=new URL(link.getAttribute('href'),location.href);
  if(url.origin!==location.origin||link.hasAttribute('download')||/csv|print/i.test(url.pathname+url.search))continue;
  const owner=links.find(item=>item.dataset.remote&&new URL(item.dataset.remote,location.href).pathname===url.pathname);
  if(!owner)continue;
  link.addEventListener('click',event=>{event.preventDefault();select(owner.dataset.task);if(url.search)loadRemote(owner.dataset.task,url.toString(),{force:true});});
 }
}
// Menu gabungan (data-tabs) memiliki beberapa panel; satu tautan sidebar menjadi pemiliknya.
const owner=new Map(),tabsOf=new Map();
for(const link of links){
 let tabs=null;try{tabs=link.dataset.tabs?JSON.parse(link.dataset.tabs):null;}catch{}
 const remote=link.dataset.remote||null;
 if(remote){
  let warm=null;
  link.addEventListener('pointerenter',()=>{clearTimeout(warm);warm=setTimeout(()=>prefetchRemote(link),90);});
  link.addEventListener('pointerleave',()=>clearTimeout(warm));
  link.addEventListener('focus',()=>prefetchRemote(link));
 }
 const keys=tabs?Object.keys(tabs):[link.dataset.task];
 link.id='tab-'+link.dataset.task;link.setAttribute('aria-label',link.textContent.trim());link.title=link.textContent.trim();
 for(const key of keys){
  const panel=document.createElement('div');panel.id='panel-'+key;panel.className='task-panel';
  // Kelas lapis DNA (ui-dna bagian 18 dan 20): halaman Operasional dan Master.
  if(key.startsWith('master-')||['tagihan','riwayat-penjualan','pengiriman','penerimaan','shift','laporan','persetujuan'].includes(key))panel.classList.add('ui-dna-ops');if(key.startsWith('master-'))panel.classList.add('ui-dna-master');
  panel.setAttribute('role','region');panel.setAttribute('aria-labelledby',link.id);panel.tabIndex=0;
  if(tabs){
   const bar=document.createElement('div');bar.className='ui-subtabs';bar.setAttribute('role','tablist');bar.setAttribute('aria-label',link.textContent.trim());
   for(const [tabKey,tabLabel]of Object.entries(tabs)){const tab=document.createElement('a');tab.href='#'+tabKey;tab.className='ui-subtab';tab.setAttribute('role','tab');tab.setAttribute('aria-selected',String(tabKey===key));tab.textContent=tabLabel;tab.addEventListener('click',e=>{e.preventDefault();select(tabKey);});bar.append(tab);}
   panel.append(bar);
  }
  if(remote){host.append(panel);panels.set(key,panel);owner.set(key,link);tabsOf.set(key,null);text[key]=[link.textContent.trim(),'Halaman keuangan dimuat di sini; menu lain tetap siap.'];continue;}
  const monitoring=document.querySelector('[data-monitoring-task="'+key+'"]');if(monitoring)panel.append(monitoring);
  for(const selector of groups[key]||[]){const target=document.querySelector(selector);const section=target?.matches('section')?target:target?.closest('section');if(section)panel.append(section);}
  host.append(panel);panels.set(key,panel);owner.set(key,link);tabsOf.set(key,tabs);
 }
 link.setAttribute('aria-controls','panel-'+keys[0]);
}
function tuckKeys(root){for(const input of root.querySelectorAll('input[name=idempotency_key][readonly]')){
 const field=input.closest('.field');if(!field||field.closest('details.technical'))continue;const details=document.createElement('details');details.className='technical';details.hidden=true;const summary=document.createElement('summary');summary.textContent='Referensi transaksi otomatis';field.before(details);details.append(summary,field);
}}
tuckKeys(document);window.uiLive?.onPatch(tuckKeys);
const report=document.querySelector('#report-date')?.form;if(report)report.action=report.action.split('#')[0]+'#laporan';
// Label kolom untuk tampilan kartu di HP, peran pesan: dipasang saat muat dan setiap kali uiLive mengganti isi tabel.
function labelRows(root){
 const tables=root.matches?.('table')?[root]:root.closest?.('table')?[root.closest('table')]:Array.from(root.querySelectorAll('.scroll table'));
 for(const table of tables){
  if(!table.closest('.scroll'))continue;
  const heads=Array.from(table.querySelectorAll('thead th')).map(e=>{e.setAttribute('scope','col');return e.textContent.trim();});
  for(const row of table.querySelectorAll('tbody tr'))Array.from(row.cells).forEach((cell,i)=>{if(cell.colSpan===1&&!cell.dataset.label)cell.dataset.label=heads[i]||cell.dataset.field||'';});
 }
 for(const box of root.querySelectorAll('[data-error]')){box.setAttribute('role','alert');box.tabIndex=-1;}
 for(const box of root.querySelectorAll('[data-ok]')){box.setAttribute('role','status');box.setAttribute('aria-live','polite');}
}
for(const table of document.querySelectorAll('.scroll table')){
 const heading=table.closest('section')?.querySelector('h2')?.textContent.trim()||'Data';
 const scroll=table.closest('.scroll');scroll.tabIndex=0;scroll.setAttribute('role','region');if(!scroll.hasAttribute('aria-label'))scroll.setAttribute('aria-label','Tabel '+heading);
}
labelRows(document);
window.uiLive?.onPatch(labelRows);
// Move, never clone, real forms: values, listeners and row-scoped handlers survive.
let popupSerial=0;
function popup(node,title,label,{wide=false,toolbar=null}={}){
 if(!window.HTMLDialogElement||!HTMLDialogElement.prototype.showModal)return null;
 const button=document.createElement('button');button.type='button';button.className='popup-action';button.textContent=label;
 const dialog=document.createElement('dialog');dialog.className='input-dialog'+(wide?' popup-wide':'');dialog.id='input-popup-'+(++popupSerial);dialog.setAttribute('aria-labelledby',dialog.id+'-title');button.setAttribute('aria-haspopup','dialog');button.setAttribute('aria-controls',dialog.id);
 const header=document.createElement('div');header.className='popup-heading';const titles=document.createElement('div');titles.className='popup-titles';const heading=document.createElement('h2');heading.id=dialog.id+'-title';heading.textContent=title;titles.append(heading);const close=document.createElement('button');close.type='button';close.className='popup-close secondary';close.setAttribute('aria-label','Tutup '+title);close.textContent='×';header.append(titles,close);
 const content=document.createElement('div');content.className='popup-content';const footer=document.createElement('div');footer.className='popup-footer';const cancel=document.createElement('button');cancel.type='button';cancel.className='secondary';cancel.textContent='Batal';footer.append(cancel);dialog.append(header,content,footer);
 node.before(button,dialog);content.append(node);if(toolbar)toolbar.append(button);
 // Tombol kirim pindah ke baris aksi di dasar formulir (tetap di dalam form agar penangan submit/busy tidak berubah): Batal kiri, aksi utama kanan.
 const forms=node.matches('form')?[node]:Array.from(node.querySelectorAll('form'));
 if(forms.length===1){const form=forms[0];const submits=Array.from(form.querySelectorAll('button[type=submit],button:not([type])')).filter(b=>!b.closest('dialog dialog,[data-popup-skip]'));const submit=submits[submits.length-1];
  if(submit&&submits.length===1){const holder=submit.parentElement;const bar=document.createElement('div');bar.className='popup-submit';bar.append(cancel,submit);form.append(bar);footer.remove();dialog.classList.add('has-submit-bar');if(holder&&holder!==form&&!holder.children.length&&!holder.textContent.trim())holder.remove();}}
 let opener=null,closing=false;
 function hide(){if(closing||dialog.querySelector('[data-busy="1"]'))return;closing=true;const done=()=>{dialog.close();closing=false;opener?.focus({preventScroll:true});};if(matchMedia('(prefers-reduced-motion: reduce)').matches){done();return;}dialog.animate([{opacity:1,transform:'none'},{opacity:0,transform:'translateY(10px) scale(.98)'}],{duration:160,easing:'cubic-bezier(.4,0,1,1)'}).finished.then(done,done);}
 button.addEventListener('click',()=>{opener=button;dialog.showModal();const first=content.querySelector('input:not([type=hidden]):not([disabled]):not([readonly]),select:not([disabled]),textarea:not([disabled]),button');(first||close).focus({preventScroll:true});});
 close.addEventListener('click',hide);cancel.addEventListener('click',hide);dialog.addEventListener('cancel',e=>{e.preventDefault();hide();});
 dialog.addEventListener('keydown',e=>{if(e.key==='Escape'){e.preventDefault();hide();return;}if(e.key!=='Tab')return;const controls=Array.from(dialog.querySelectorAll('button,input,select,textarea,a[href],summary,[tabindex]')).filter(el=>!el.disabled&&el.tabIndex>=0&&el.getBoundingClientRect().height&&getComputedStyle(el).visibility!=='hidden');const first=controls[0],last=controls[controls.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}});
 dialog.addEventListener('click',e=>{if(e.target!==dialog)return;const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)hide();});
 return {dialog,button,content};
}
// POS keeps the approved catalogue/cart workflow; only its payment steps are popups.
const wholeSections=[['#receipt-heading','Catat barang masuk','Tambah penerimaan'],['#trip-heading','Biaya ritase sopir','Catat biaya ritase'],['#opening-heading','Saldo awal pembukuan','Isi saldo awal']];
for(const [selector,title,label]of wholeSections){const section=document.querySelector(selector)?.closest('section');if(!section)continue;
 // Penerimaan: tombol pembuka langsung di kepala kartu Riwayat (rancangan 'Penerimaan barang'), tanpa kartu terpisah.
 const history=selector==='#receipt-heading'?(document.querySelector('#receipt-drafts')||document.querySelector('#receipt-history')):null;
 if(history){history.classList.add('has-head-action');const opened=popup(section,title,'+ Catat penerimaan',{toolbar:history,wide:true});if(opened){opened.button.classList.add('card-head-action');opened.button.dataset.receiptNew='1';const sub=document.createElement('p');sub.className='popup-sub';sub.textContent=section.querySelector('.note')?.textContent.trim()||'';if(sub.textContent){opened.dialog.querySelector('.popup-titles').append(sub);const dup=section.querySelector('.note');if(dup)dup.hidden=true;}}continue;}const shell=document.createElement('section');shell.className='card';const h=document.createElement('h2');h.textContent=title;const note=section.querySelector('.note')?.cloneNode(true);section.before(shell);shell.append(h);if(note)shell.append(note);popup(section,title,label,{toolbar:shell});}
// Shared Dialog Management: semantics, focus restoration, reduced motion & busy guard
function closeCustomDialog(dialog) {
  if (!dialog || dialog.dataset.closing || dialog.querySelector('[data-busy="1"]') || dialog.dataset.busy) return;
  dialog.dataset.closing = '1';
  const finish = () => {
    dialog.close();
    delete dialog.dataset.closing;
    dialog._openerTrigger?.focus({preventScroll: true});
  };
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) { finish(); return; }
  dialog.animate([{opacity: 1, transform: 'none'}, {opacity: 0, transform: 'translateY(10px) scale(.98)'}], {duration: 160, easing: 'cubic-bezier(.4,0,1,1)'}).finished.then(finish, finish);
}

document.addEventListener('click', e => {
  const trigger = e.target.closest('[data-open-popup]');
  if (trigger) {
    e.preventDefault();
    const id = trigger.getAttribute('data-open-popup');
    const dialog = document.getElementById(id);
    if (dialog && typeof dialog.showModal === 'function') {
      dialog._openerTrigger = trigger;
      dialog.showModal();
      // Fokus ke kolom wajib pertama yang masih kosong (kode sudah terisi otomatis), bukan kolom pertama.
      const fields = Array.from(dialog.querySelectorAll('input:not([type=hidden]):not([type=radio]):not([type=file]):not([disabled]):not([readonly]),textarea:not([disabled])')).filter(el => el.getClientRects().length);
      const first = fields.find(el => el.required && !el.value) || dialog.querySelector('input:not([type=hidden]):not([disabled]):not([readonly]),select:not([disabled]),textarea:not([disabled]),button:not(.popup-close)');
      (first || dialog.querySelector('.popup-close') || dialog).focus({preventScroll: true});
    }
    return;
  }
  const closer = e.target.closest('[data-close-dialog]');
  if (closer) {
    e.preventDefault();
    const dialog = closer.closest('dialog');
    if (dialog) closeCustomDialog(dialog);
    return;
  }
});

document.querySelectorAll('.master-entity-card dialog.input-dialog').forEach(dialog => {
  dialog.addEventListener('cancel', e => { e.preventDefault(); closeCustomDialog(dialog); });
  dialog.addEventListener('click', e => {
    if (e.target !== dialog) return;
    const r = dialog.getBoundingClientRect();
    if (!(e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom)) return;
    // Isian yang sudah diketik tidak hilang karena salah klik di luar popup: popup bergetar halus, tetap terbuka.
    if (dialog.querySelector('form[data-dirty]')) { if (!matchMedia('(prefers-reduced-motion: reduce)').matches) dialog.animate([{transform: 'none'}, {transform: 'scale(1.015)'}, {transform: 'none'}], {duration: 220, easing: 'ease-out'}); return; }
    closeCustomDialog(dialog);
  });
  dialog.addEventListener('keydown', e => {
    if (e.key === 'Escape') { e.preventDefault(); closeCustomDialog(dialog); return; }
    if (e.key !== 'Tab') return;
    const controls = Array.from(dialog.querySelectorAll('button,input,select,textarea,a[href],[tabindex]')).filter(el => !el.disabled && el.tabIndex >= 0 && el.getBoundingClientRect().height && getComputedStyle(el).visibility !== 'hidden');
    if (!controls.length) return;
    const first = controls[0], last = controls[controls.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });
});

// Popup Catat pembayaran: ringkasan tagihan (dibaca dari baris tabel), jumlah terisi sisa, metode sebagai tombol pilihan.
function cellText(row,label){return Array.from(row?.cells||[]).find(c=>c.dataset.label===label)?.textContent.trim()||'';}
function segmentSelect(select){
 if(!select||select.dataset.segmented)return;select.dataset.segmented='1';
 const group=document.createElement('div');group.className='ui-segment';group.setAttribute('role','radiogroup');
 const label=select.id?document.querySelector('label[for="'+select.id+'"]'):null;if(label){label.id=label.id||select.id+'-label';group.setAttribute('aria-labelledby',label.id);}
 const buttons=Array.from(select.options).map(o=>{const b=document.createElement('button');b.type='button';b.textContent=o.textContent;b.dataset.value=o.value;b.setAttribute('role','radio');b.addEventListener('click',()=>{select.value=o.value;select.dispatchEvent(new Event('change',{bubbles:true}));sync();});group.append(b);return b;});
 function sync(){for(const b of buttons){const on=b.dataset.value===select.value;b.setAttribute('aria-checked',String(on));b.tabIndex=on?0:-1;}}
 group.addEventListener('keydown',e=>{if(!['ArrowLeft','ArrowRight'].includes(e.key))return;e.preventDefault();const i=buttons.findIndex(b=>b.dataset.value===select.value);const n=buttons[(i+(e.key==='ArrowRight'?1:-1)+buttons.length)%buttons.length];n.click();n.focus();});
 select.addEventListener('change',sync);
 const shell=select.closest('.select-shell')||select;shell.after(group);shell.classList.add('ui-segment-source');sync();
}
function enhanceCollect(form,result){
 const row=result.dialog.closest('tr');if(!row)return;
 const number=cellText(row,'Nomor'),name=cellText(row,'Nama'),rest=cellText(row,'Sisa');
 const sub=result.dialog.querySelector('.popup-sub');if(sub)sub.textContent=[name,number].filter(Boolean).join(' · ');
 const summary=document.createElement('dl');summary.className='popup-summary';
 for(const [label,value,strong] of [['Total tagihan',cellText(row,'Total')],['Terbayar',cellText(row,'Terbayar')],['Sisa',rest,true]]){const d=document.createElement('div');if(strong)d.className='is-due';const t=document.createElement('dt');t.textContent=label;const v=document.createElement('dd');v.textContent=value||'—';d.append(t,v);summary.append(d);}
 const srHead=form.querySelector('h3.sr');if(srHead)srHead.after(summary);else form.prepend(summary);
  const amount=form.querySelector('input[name=amount]');const balance=row.dataset.remaining;
 if(amount){const l=form.querySelector('label[for="'+amount.id+'"]');if(l)l.textContent='Jumlah dibayar';
  // Nominal tidak diisi otomatis; tombol cepat mengisi sisa tagihan dengan satu klik.
   if(balance&&/^\d+(\.\d{1,2})?$/.test(balance)&&!form.querySelector('.popup-fill')){const fill=document.createElement('button');fill.type='button';fill.className='secondary popup-fill';fill.textContent='Lunasi '+rest;fill.addEventListener('click',()=>{amount.value=balance;amount.dispatchEvent(new Event('input',{bubbles:true}));amount.focus();});amount.closest('.field')?.append(fill);}}
 const ref=form.querySelector('input[name=reference]');if(ref&&!ref.placeholder)ref.placeholder='No. transfer / catatan';
 segmentSelect(form.querySelector('select[name=method]'));
 const hint=document.createElement('p');hint.className='note popup-hint';hint.textContent=form.dataset.hint||'Pilih sumber dana pembayaran.';form.querySelector('.popup-submit')?.before(hint);
}
// Form isian di halaman menjadi popup. Dipasang saat muat dan pada baris/daftar baru dari uiLive (form di dalam dialog dilewati).
function popupForms(root){for(const form of root.querySelectorAll(root===document?'main form':'form')){
 if(form.id==='form-checkout'||form.closest('dialog')||(form.getAttribute('method')||'').toLowerCase()==='get')continue;
 if(!form.querySelector('input:not([type=hidden]),select,textarea'))continue;
 const submit=form.querySelector('button[type=submit],button:not([type])');const label=form.dataset.popupTitle||(form.id==='form-open'?'Buka shift':(submit?.textContent.trim()||'Isi formulir'));const contextEl=form.querySelector('h3,p:not([data-error]):not([data-ok])');const context=contextEl?.tagName==='H3'&&contextEl.textContent.trim()!==label?contextEl.textContent.trim():'';if(contextEl?.tagName==='H3'&&contextEl.textContent.trim()===label)contextEl.classList.add('sr');const result=popup(form,label,label);if(result&&context){const note=document.createElement('p');note.className='popup-sub';note.textContent=context;result.dialog.querySelector('.popup-titles').append(note);}
 if(result&&form.id==='form-open'){result.button.dataset.cashDayOpen='1';result.button.hidden=form.dataset.openAllowed==='0';if(result.button.hidden)result.button.style.setProperty('display','none','important');}
 if(result&&form.matches('.form-collect'))enhanceCollect(form,result);
}}
function invoiceDetails(root){
 for(const dialog of root.querySelectorAll('[data-invoice-detail]')){
  if(dialog.dataset.bound)continue;dialog.dataset.bound='1';
  dialog.addEventListener('cancel',event=>{event.preventDefault();closeCustomDialog(dialog);});
  dialog.addEventListener('click',event=>{
   if(event.target!==dialog)return;
   const rect=dialog.getBoundingClientRect();
   if(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom)closeCustomDialog(dialog);
  });
 }
}
popupForms(document);invoiceDetails(document);
window.uiLive?.onPatch(popupForms);
document.addEventListener('ui-invoices:render',event=>{tuckKeys(event.detail.root);labelRows(event.detail.root);popupForms(event.detail.root);invoiceDetails(event.detail.root);});
// List filter remains a GET, not a mutation; labels keep their stored enum values.
const kinds={customer:'Pelanggan',supplier:'Supplier',employee:'Karyawan / staf',product:'Produk',vehicle:'Kendaraan'};
for(const option of document.querySelectorAll('select[name=master_kind] option'))option.textContent=kinds[option.value]||option.textContent;
const main=document.querySelector('main');
const storageKey='v2-workspace-task:'+navigation.dataset.role;
function store(value){try{sessionStorage.setItem(storageKey,value);}catch{}}
let saved;try{saved=sessionStorage.getItem(storageKey);}catch{}
const requested=()=>({admin:'master-product',master:'master-product'}[location.hash.slice(1)]||location.hash.slice(1));let active=null;
function select(key,{focus=false,updateURL=true}={}){
  if(!panels.has(key))key=defaultTask;const changed=active!==key;
 for(const [id,panel]of panels){panel.hidden=id!==key;panel.inert=id!==key;}
  for(const link of links){const selected=owner.get(key)===link;if(selected){link.setAttribute('aria-current','page');const group=link.closest('[data-menu-group]');if(group)group.open=true;}else{link.removeAttribute('aria-current');}link.tabIndex=0;}
 active=key;store(key);document.body.dataset.activeTask=key;
 document.querySelector('main.wrap')?.setAttribute('data-ready','1');
 // Panel keuangan yang ditinggalkan dibongkar agar pembaruan otomatisnya berhenti; alamat terakhir diingat.
 for(const [other,link]of owner)if(other!==key&&link.dataset.remote&&remoteState.has(other)){stopRemote(other);panels.get(other)?.replaceChildren();remoteState.delete(other);}
 const remoteUrl=owner.get(key)?.dataset.remote;
 document.body.toggleAttribute('data-remote-task',!!remoteUrl);
 if(remoteUrl)loadRemote(key,remoteLast.get(key)||remoteUrl);const own=owner.get(key);document.querySelector('#workspace-title').textContent=tabsOf.get(key)?own.textContent.trim():text[key][0];document.querySelector('#workspace-description').textContent=text[key][1];document.querySelector('.top-context strong').textContent=key==='kasir'?'Kasir':(own?.textContent.trim()||text[key][0]);
 if(updateURL&&changed)history.replaceState(null,'','#'+key);if(focus)own?.focus({preventScroll:true});
 if(changed&&document.body.classList.contains('workspace-enhanced')){window.uiMotion?.enter(panels.get(key));window.uiMotion?.textIn(document.querySelector('#workspace-title'));window.uiMotion?.textIn(document.querySelector('#workspace-description'));window.scrollTo({top:0,behavior:'instant'});}
}
for(const link of links){link.addEventListener('click',e=>{e.preventDefault();select(link.dataset.task);if(matchMedia('(max-width:900px)').matches)window.v2Sidebar?.close();});link.addEventListener('keydown',e=>{const visible=links.filter(item=>item.closest('[data-menu-group]')?.open!==false),i=visible.indexOf(link);let n;if(e.key==='ArrowDown')n=(i+1)%visible.length;if(e.key==='ArrowUp')n=(i-1+visible.length)%visible.length;if(e.key==='Home')n=0;if(e.key==='End')n=visible.length-1;if(n!==undefined){e.preventDefault();visible[n].focus({preventScroll:true});}});}
document.addEventListener('click',e=>{const link=e.target.closest?.('[data-task-link]');if(!link)return;e.preventDefault();select(link.dataset.taskLink);panels.get(active).querySelector('.popup-action,input,button')?.focus({preventScroll:true});});
window.addEventListener('hashchange',()=>select(requested(),{updateURL:false}));
select(panels.has(requested())?requested():(panels.has(saved)?saved:defaultTask),{updateURL:false});document.body.classList.add('workspace-enhanced');window.scrollTo({top:0,behavior:'instant'});
const busyWatch=new MutationObserver(records=>{for(const record of records){const form=record.target;if(form.matches('form'))form.setAttribute('aria-busy',form.dataset.busy?'true':'false');}});busyWatch.observe(main,{subtree:true,attributes:true,attributeFilter:['data-busy']});
})();
</script>
