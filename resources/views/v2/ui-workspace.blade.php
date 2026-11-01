<script>
(()=>{
'use strict';
const navigation=document.querySelector('.nav');
const links=Array.from(navigation?.querySelectorAll('[data-task]')||[]);
const host=document.querySelector('#task-panels');
if(!host||!links.length)return;
const groups={kasir:['#kasir','#h-sales'],tagihan:['#tagihan','#h-verify','#supplier-pay-heading','#reject-heading'],pengiriman:['#pengiriman','#trip-heading','#uang-muka-sopir','#h-deliveries'],'master-product':['#master-section-product'],'master-customer':['#master-section-customer'],'master-supplier':['#master-section-supplier'],'master-employee':['#master-section-employee'],'master-vehicle':['#master-section-vehicle'],penerimaan:['#receipt-heading'],shift:['#h-shift','#review-heading','#opening-heading'],laporan:['#laporan']};
const text={kasir:['Kasir','Pilih barang di katalog, lalu lanjutkan pembayaran.'],tagihan:['Tagihan & pembayaran','Piutang pelanggan, utang supplier, dan pemeriksaan pembayaran.'],pengiriman:['Pengiriman & sopir','Surat jalan, status pengiriman, dan biaya perjalanan.'],'master-product':['Master Produk','Katalog produk material, satuan, harga jual, dan stok minimum.'],'master-customer':['Master Pelanggan','Data pelanggan toko, kode langganan, dan catatan piutang.'],'master-supplier':['Master Supplier','Data pemasok barang material dan toko rekanan.'],'master-employee':['Master Karyawan','Data staf operasional dan karyawan toko.'],'master-vehicle':['Master Mobil','Armada kendaraan operasional pengiriman barang.'],penerimaan:['Penerimaan barang','Catat barang masuk dan tagihan supplier melalui formulir penerimaan.'],shift:['Kas & shift','Riwayat kas awal, uang fisik, dan pemeriksaan selisih.'],laporan:['Laporan harian','Tunai, transfer terverifikasi, dan transfer menunggu dipisahkan.']};
const paths={kasir:'M4 4h16v16H4z M8 8h8M8 12h3M8 16h8',tagihan:'M6 3h12v18l-3-2-3 2-3-2-3 2z M9 8h6M9 12h6',pengiriman:'M3 6h11v11H3z M14 10h4l3 4v3h-7 M8 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0 M20 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0','master-product':'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4','master-customer':'M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z','master-supplier':'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z M9 22V12h6v10','master-employee':'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z M23 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75','master-vehicle':'M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1 .4-1 1v7c0 .6.4 1 1 1h1 M5 17a2 2 0 1 0 4 0 2 2 0 1 0-4 0 M15 17a2 2 0 1 0 4 0 2 2 0 1 0-4 0',penerimaan:'M4 8l8-4 8 4v12H4z M4 8l8 4 8-4M12 12v8',shift:'M3 7h18v13H3z M3 11h18M7 16h3M7 3v4',laporan:'M4 3v18h17 M9 17V9M14 17V5M19 17v-5'};
groups.akun=['#login-accounts'];text.akun=['Akun Login','Kelola akun Admin dan Kasir; terpisah dari master karyawan.'];paths.akun='M12 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M4 21v-3a6 6 0 0 1 12 0v3 M19 13v8M16 17h6';
const panels=new Map();
for(const link of links){
 const key=link.dataset.task,panel=document.createElement('div');panel.id='panel-'+key;panel.className='task-panel';panel.setAttribute('role','tabpanel');panel.setAttribute('aria-labelledby','tab-'+key);panel.tabIndex=0;
 for(const selector of groups[key]||[]){const target=document.querySelector(selector);const section=target?.matches('section')?target:target?.closest('section');if(section)panel.append(section);}
 host.append(panel);panels.set(key,panel);link.id='tab-'+key;link.setAttribute('role','tab');link.setAttribute('aria-controls',panel.id);link.setAttribute('aria-label',link.textContent.trim());link.title=link.textContent.trim();
 const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');svg.setAttribute('viewBox','0 0 24 24');svg.setAttribute('fill','none');svg.setAttribute('stroke','currentColor');svg.setAttribute('aria-hidden','true');const path=document.createElementNS(svg.namespaceURI,'path');path.setAttribute('d',paths[key]);path.setAttribute('stroke-linecap','round');path.setAttribute('stroke-linejoin','round');svg.append(path);link.prepend(svg);
}
navigation.setAttribute('role','tablist');navigation.setAttribute('aria-orientation','vertical');
for(const input of document.querySelectorAll('input[name=idempotency_key][readonly]')){
 const field=input.closest('.field');if(!field)continue;const details=document.createElement('details');details.className='technical';const summary=document.createElement('summary');summary.textContent='Referensi transaksi otomatis';field.before(details);details.append(summary,field);
}
const report=document.querySelector('#report-date')?.form;if(report)report.action=report.action.split('#')[0]+'#laporan';
for(const table of document.querySelectorAll('.scroll table')){const heads=Array.from(table.querySelectorAll('thead th')).map(e=>e.textContent.trim());for(const row of table.querySelectorAll('tbody tr'))Array.from(row.cells).forEach((cell,i)=>{cell.dataset.label=heads[i]||cell.dataset.field||'';});}
for(const box of document.querySelectorAll('[data-error]')){box.setAttribute('role','alert');box.tabIndex=-1;}
for(const box of document.querySelectorAll('[data-ok]')){box.setAttribute('role','status');box.setAttribute('aria-live','polite');}
// Move, never clone, real forms: values, listeners and row-scoped handlers survive.
let popupSerial=0;
function popup(node,title,label,{wide=false,toolbar=null}={}){
 if(!window.HTMLDialogElement||!HTMLDialogElement.prototype.showModal)return null;
 const button=document.createElement('button');button.type='button';button.className='popup-action';button.textContent=label;
 const dialog=document.createElement('dialog');dialog.className='input-dialog'+(wide?' popup-wide':'');dialog.id='input-popup-'+(++popupSerial);dialog.setAttribute('aria-labelledby',dialog.id+'-title');button.setAttribute('aria-haspopup','dialog');button.setAttribute('aria-controls',dialog.id);
 const header=document.createElement('div');header.className='popup-heading';const heading=document.createElement('h2');heading.id=dialog.id+'-title';heading.textContent=title;const close=document.createElement('button');close.type='button';close.className='popup-close secondary';close.setAttribute('aria-label','Tutup '+title);close.textContent='×';header.append(heading,close);
 const content=document.createElement('div');content.className='popup-content';const footer=document.createElement('div');footer.className='popup-footer';const cancel=document.createElement('button');cancel.type='button';cancel.className='secondary';cancel.textContent='Batal / tutup';footer.append(cancel);dialog.append(header,content,footer);
 node.before(button,dialog);content.append(node);if(toolbar)toolbar.append(button);
 let opener=null,closing=false;
 function hide(){if(closing||dialog.querySelector('[data-busy="1"]'))return;closing=true;const done=()=>{dialog.close();closing=false;opener?.focus({preventScroll:true});};if(matchMedia('(prefers-reduced-motion: reduce)').matches){done();return;}dialog.animate([{opacity:1,transform:'translateY(0)'},{opacity:0,transform:'translateY(6px)'}],{duration:120,easing:'ease-out'}).finished.then(done,done);}
 button.addEventListener('click',()=>{opener=button;dialog.showModal();const first=content.querySelector('input:not([type=hidden]):not([disabled]):not([readonly]),select:not([disabled]),textarea:not([disabled]),button');(first||close).focus({preventScroll:true});});
 close.addEventListener('click',hide);cancel.addEventListener('click',hide);dialog.addEventListener('cancel',e=>{e.preventDefault();hide();});
 dialog.addEventListener('keydown',e=>{if(e.key==='Escape'){e.preventDefault();hide();return;}if(e.key!=='Tab')return;const controls=Array.from(dialog.querySelectorAll('button,input,select,textarea,a[href],summary,[tabindex]')).filter(el=>!el.disabled&&el.tabIndex>=0&&el.getBoundingClientRect().height&&getComputedStyle(el).visibility!=='hidden');const first=controls[0],last=controls[controls.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}});
 dialog.addEventListener('click',e=>{if(e.target!==dialog)return;const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)hide();});
 return {dialog,button,content};
}
// POS keeps the approved catalogue/cart workflow; only its payment steps are popups.
const wholeSections=[['#receipt-heading','Penerimaan barang','Tambah penerimaan'],['#trip-heading','Biaya ritase sopir','Catat biaya ritase'],['#opening-heading','Saldo awal pembukuan','Isi saldo awal']];
for(const [selector,title,label]of wholeSections){const section=document.querySelector(selector)?.closest('section');if(!section)continue;const shell=document.createElement('section');shell.className='card';const h=document.createElement('h2');h.textContent=title;const note=section.querySelector('.note')?.cloneNode(true);section.before(shell);shell.append(h);if(note)shell.append(note);popup(section,title,label,{toolbar:shell});}
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
  dialog.animate([{opacity: 1, transform: 'translateY(0)'}, {opacity: 0, transform: 'translateY(6px)'}], {duration: 120, easing: 'ease-out'}).finished.then(finish, finish);
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
      const first = dialog.querySelector('input:not([type=hidden]):not([disabled]):not([readonly]),select:not([disabled]),textarea:not([disabled]),button:not(.popup-close)');
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
    if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) closeCustomDialog(dialog);
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

for(const form of document.querySelectorAll('main form')){
 if(form.id==='form-checkout'||form.closest('dialog')||(form.getAttribute('method')||'').toLowerCase()==='get')continue;
 if(!form.querySelector('input:not([type=hidden]),select,textarea'))continue;
 const submit=form.querySelector('button[type=submit],button:not([type])');const label=form.id==='form-open'?'Buka shift':(submit?.textContent.trim()||'Isi formulir');const context=form.querySelector('h3,p:not([data-error]):not([data-ok])')?.textContent.trim();const result=popup(form,label,label);if(result&&context){const note=document.createElement('p');note.className='note';note.textContent=context;result.content.prepend(note);}
}
// List filter remains a GET, not a mutation; labels keep their stored enum values.
const kinds={customer:'Pelanggan',supplier:'Supplier',employee:'Karyawan / staf',product:'Produk',vehicle:'Kendaraan'};
for(const option of document.querySelectorAll('select[name=master_kind] option'))option.textContent=kinds[option.value]||option.textContent;
const sidebar=document.querySelector('#workspace-sidebar'),menu=document.querySelector('.mobile-menu-toggle'),overlay=document.querySelector('#sidebar-overlay'),top=document.querySelector('.top'),main=document.querySelector('main');
function mobile(){return matchMedia('(max-width:900px)').matches;}
function drawer(open,{restore=true}={}){open=mobile()&&open;document.body.classList.toggle('sidebar-open',open);menu.setAttribute('aria-expanded',String(open));sidebar.inert=mobile()&&!open;main.inert=open;top.inert=open;overlay.tabIndex=-1;if(open)sidebar.querySelector('.sidebar-close').focus({preventScroll:true});else if(restore&&mobile())menu.focus({preventScroll:true});}
sidebar.querySelector('.sidebar-brand').addEventListener('dblclick',()=>{if(mobile())return;const pinned=document.body.classList.toggle('sidebar-pinned');sidebar.classList.toggle('pinned',pinned);});
menu.addEventListener('click',()=>drawer(true));sidebar.querySelector('.sidebar-close').addEventListener('click',()=>drawer(false));overlay.addEventListener('click',()=>drawer(false));
window.addEventListener('resize',()=>drawer(false,{restore:false}));
document.addEventListener('keydown',e=>{if(!document.body.classList.contains('sidebar-open'))return;if(e.key==='Escape'){e.preventDefault();drawer(false);}if(e.key==='Tab'){const nodes=Array.from(sidebar.querySelectorAll('button,a')).filter(el=>el.tabIndex>=0&&el.getBoundingClientRect().height);const first=nodes[0],last=nodes[nodes.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}});
function store(value){try{sessionStorage.setItem('v2-workspace-task',value);}catch{}}
let saved;try{saved=sessionStorage.getItem('v2-workspace-task');}catch{}
const requested=()=>({admin:'master-product',master:'master-product'}[location.hash.slice(1)]||location.hash.slice(1));let active=null;
function select(key,{focus=false,updateURL=true}={}){
 if(!panels.has(key))key='kasir';const changed=active!==key;
 for(const [id,panel]of panels){panel.hidden=id!==key;panel.inert=id!==key;}
 for(const link of links){const selected=link.dataset.task===key;link.setAttribute('aria-selected',String(selected));link.tabIndex=selected?0:-1;}
 active=key;store(key);document.body.dataset.activeTask=key;document.querySelector('#workspace-title').textContent=text[key][0];document.querySelector('#workspace-description').textContent=text[key][1];document.querySelector('.top-context strong').textContent=text[key][0];
 if(updateURL&&changed)history.replaceState(null,'','#'+key);if(focus)links.find(l=>l.dataset.task===key).focus({preventScroll:true});
 if(changed&&document.body.classList.contains('workspace-enhanced')){if(!matchMedia('(prefers-reduced-motion: reduce)').matches)panels.get(key).animate([{opacity:.5,transform:'translateY(4px)'},{opacity:1,transform:'translateY(0)'}],{duration:160,easing:'cubic-bezier(.16,1,.3,1)'});window.scrollTo({top:0,behavior:'instant'});}
}
for(const link of links){link.addEventListener('click',e=>{e.preventDefault();select(link.dataset.task);if(mobile())drawer(false);});link.addEventListener('keydown',e=>{const i=links.indexOf(link);let n;if(['ArrowDown','ArrowRight'].includes(e.key))n=(i+1)%links.length;if(['ArrowUp','ArrowLeft'].includes(e.key))n=(i-1+links.length)%links.length;if(e.key==='Home')n=0;if(e.key==='End')n=links.length-1;if(n!==undefined){e.preventDefault();select(links[n].dataset.task,{focus:true});}});}
for(const link of document.querySelectorAll('[data-task-link]'))link.addEventListener('click',e=>{e.preventDefault();select(link.dataset.taskLink);panels.get(active).querySelector('.popup-action,input,button')?.focus({preventScroll:true});});
window.addEventListener('hashchange',()=>select(requested(),{updateURL:false}));
select(panels.has(requested())?requested():(panels.has(saved)?saved:'kasir'),{updateURL:false});document.body.classList.add('workspace-enhanced');drawer(false,{restore:false});window.scrollTo({top:0,behavior:'instant'});
const busyWatch=new MutationObserver(records=>{for(const record of records){const form=record.target;if(form.matches('form'))form.setAttribute('aria-busy',form.dataset.busy?'true':'false');}});busyWatch.observe(main,{subtree:true,attributes:true,attributeFilter:['data-busy']});
})();
</script>
