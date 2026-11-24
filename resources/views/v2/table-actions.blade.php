{{--
    Kolom aksi tabel menjadi tombol tiga titik.
    Kontrol asli (tombol, tautan, form) dipindahkan apa adanya ke panel, bukan dikloning,
    sehingga listener, nilai form, idempotensi, dan dialog yang sudah ada tetap bekerja.
    Katalog Kasir dikecualikan: tombol tambah barang harus tetap satu klik.
--}}
<style>
html body main.wrap #task-panels td.ui-actions.has-row-actions{display:table-cell!important}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-toggle{display:inline-grid!important;place-items:center;width:36px!important;height:36px!important;min-height:36px!important;margin:0!important;padding:0!important;border:1px solid var(--ui-line)!important;border-radius:10px!important;background:var(--ui-surface)!important;color:var(--ui-ink-2)!important;box-shadow:none!important;cursor:pointer;line-height:0;font-size:0;box-sizing:border-box;vertical-align:middle}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-toggle:hover{background:var(--ui-hover)!important;border-color:var(--ui-line)!important;color:var(--ui-ink)!important}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-toggle[aria-expanded=true]{background:var(--ui-green-soft)!important;border-color:var(--ui-green-soft)!important;color:var(--ui-green)!important}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-toggle svg{display:block!important;width:18px!important;height:18px!important;pointer-events:none}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-toggle:focus-visible{outline:2px solid var(--ui-focus);outline-offset:2px}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-menu{position:fixed;z-index:150;display:grid;gap:2px;min-width:12.5rem;max-width:min(20rem,calc(100vw - 1rem));max-height:min(65vh,30rem);overflow:auto;margin:0!important;padding:var(--ui-space-tight);border:1px solid var(--ui-line);border-radius:var(--ui-radius);background:var(--ui-surface);box-shadow:0 18px 45px -18px rgba(9,30,18,.45);text-align:left;white-space:normal;box-sizing:border-box}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-menu[hidden]{display:none!important}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-menu :is(button,a[href],.btn-action-pill,.popup-action){display:flex!important;align-items:center!important;justify-content:flex-start!important;width:100%!important;height:auto!important;min-height:2.5rem!important;margin:0!important;padding:.5rem .625rem!important;border:0!important;border-radius:calc(var(--ui-radius) - 4px)!important;background:transparent!important;color:var(--ui-ink)!important;font:inherit;font-size:.875rem!important;font-weight:600!important;line-height:1.3!important;text-align:left!important;text-decoration:none!important;box-shadow:none!important;cursor:pointer;box-sizing:border-box}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-menu :is(button,a[href],.btn-action-pill,.popup-action):hover{background:var(--ui-hover)!important;color:var(--ui-ink)!important;border-color:transparent!important}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-menu :is(button,a[href],.btn-action-pill,.popup-action):focus-visible{outline:2px solid var(--ui-focus);outline-offset:-2px}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-menu .popup-action{background:var(--ui-green-soft)!important;color:var(--ui-green)!important}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-menu .btn-action-archive{background:transparent!important;color:var(--ui-bad-ink)!important}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-menu .btn-action-archive:hover{background:var(--ui-bad-bg)!important;color:var(--ui-bad-ink)!important}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-menu :is(button,a[href])[disabled]{opacity:.45;cursor:not-allowed}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-menu>form{display:grid;gap:var(--ui-space-control);margin:0;padding:var(--ui-space-control)}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-menu form .row{display:grid;grid-template-columns:1fr;gap:var(--ui-space-control)}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-menu label{font-size:.75rem;font-weight:600;color:var(--ui-ink-2)}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-menu :is(input:not([type=checkbox]):not([type=radio]),select,textarea){width:100%;min-height:38px;font-size:.875rem}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-menu form button[type=submit]:not(.btn-action-archive),html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-menu form button:not([type]):not(.btn-action-archive){justify-content:center!important;background:var(--ui-green)!important;color:var(--ui-surface)!important;font-weight:700!important;border-color:var(--ui-green)!important}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-menu form button[type=submit]:not(.btn-action-archive):hover,html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-menu form button:not([type]):not(.btn-action-archive):hover{background:var(--ui-green-2)!important;color:var(--ui-surface)!important}
html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-menu .note{font-size:.75rem;line-height:1.4;color:var(--ui-ink-2)}
@media(max-width:760px){
  html body main.wrap #task-panels td.ui-actions.has-row-actions{display:flex!important;flex-wrap:wrap;align-items:center;justify-content:flex-start;gap:var(--ui-space-control)}
  html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-toggle{width:44px!important;height:44px!important;min-height:44px!important}
}
@media print{html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-toggle,html body main.wrap #task-panels td.ui-actions.has-row-actions .ui-actions-menu{display:none!important}}
</style>
<script>
(()=>{
'use strict';
if(window.v2TableActions)return;
const reduce=()=>matchMedia('(prefers-reduced-motion: reduce)').matches;
const holders='dialog,template,script,style,noscript';
const actionable=el=>el.matches('button,a[href],form,input,select,textarea')||Boolean(el.querySelector('button,a[href],form,input,select,textarea'));
let open=null,serial=0,pending=false;
function rowLabel(cell){
  const first=cell.closest('tr')?.querySelector('td.ui-primary,td');
  return (first?.textContent||'').replace(/\s+/g,' ').trim().slice(0,48);
}
function close(restore){
  if(!open)return;
  const {toggle,menu}=open;open=null;
  menu.hidden=true;
  menu.style.removeProperty('left');menu.style.removeProperty('top');menu.style.removeProperty('visibility');
  toggle.setAttribute('aria-expanded','false');
  if(restore)toggle.focus({preventScroll:true});
}
function place(toggle,menu){
  menu.hidden=false;
  menu.style.visibility='hidden';
  menu.style.left='0px';menu.style.top='0px';
  const t=toggle.getBoundingClientRect(),m=menu.getBoundingClientRect(),gap=6,margin=8;
  let left=t.right-m.width,top=t.bottom+gap;
  if(left<margin)left=margin;
  if(left+m.width>innerWidth-margin)left=Math.max(margin,innerWidth-margin-m.width);
  if(top+m.height>innerHeight-margin)top=Math.max(margin,t.top-m.height-gap);
  menu.style.left=Math.round(left)+'px';
  menu.style.top=Math.round(top)+'px';
  menu.style.visibility='';
}
function show(toggle,menu){
  if(open?.menu===menu){close(true);return;}
  close(false);
  place(toggle,menu);
  toggle.setAttribute('aria-expanded','true');
  open={toggle,menu};
  if(!reduce())menu.animate([{opacity:0,transform:'translateY(-4px) scale(.98)'},{opacity:1,transform:'none'}],{duration:150,easing:'ease-out'});
  const first=menu.querySelector('button:not([disabled]),a[href],input:not([type=hidden]):not([disabled]),select:not([disabled]),textarea:not([disabled])');
  (first||menu).focus({preventScroll:true});
}
function enhance(root){
  const cells=[];
  if(root?.matches?.('td.ui-actions'))cells.push(root);
  if(root?.querySelectorAll)cells.push(...root.querySelectorAll('td.ui-actions'));
  for(const cell of cells){
    if(cell.dataset.actionsReady)continue;
    if(cell.closest('#products-table')){cell.dataset.actionsReady='keep';continue;}
    const movable=[...cell.children].filter(el=>!el.matches(holders)&&!el.matches('[data-actions-keep]')&&actionable(el));
    if(!movable.length){cell.dataset.actionsReady='empty';continue;}
    const id='ui-actions-menu-'+(++serial);
    const toggle=document.createElement('button');
    toggle.type='button';toggle.className='ui-actions-toggle';toggle.setAttribute('aria-haspopup','true');toggle.setAttribute('aria-expanded','false');toggle.setAttribute('aria-controls',id);
    const label=rowLabel(cell);
    toggle.setAttribute('aria-label',label?'Aksi untuk '+label:'Aksi baris');
    toggle.setAttribute('data-tip','Aksi baris');
    toggle.innerHTML='<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>';
    const menu=document.createElement('div');
    menu.className='ui-actions-menu';menu.id=id;menu.hidden=true;menu.setAttribute('role','group');menu.setAttribute('aria-label','Aksi baris');
    for(const el of movable)menu.append(el);
    cell.prepend(toggle,menu);
    cell.dataset.actionsReady='1';
    cell.classList.add('has-row-actions');
  }
}
function queue(){if(pending)return;pending=true;requestAnimationFrame(()=>{pending=false;enhance(document);});}
document.addEventListener('click',e=>{
  const toggle=e.target.closest?.('.ui-actions-toggle');
  if(toggle){
    const menu=toggle.parentElement.querySelector('.ui-actions-menu');
    if(menu){e.preventDefault();show(toggle,menu);}
    return;
  }
  if(open&&open.menu.contains(e.target)){
    if(e.target.closest('a[href],button'))setTimeout(()=>close(false),0);
    return;
  }
  if(open)close(false);
});
document.addEventListener('keydown',e=>{
  if(!open)return;
  if(e.key==='Escape'){e.preventDefault();close(true);return;}
  if(e.key==='ArrowDown'&&document.activeElement===open.toggle){e.preventDefault();(open.menu.querySelector('button:not([disabled]),a[href],input:not([type=hidden]),select,textarea')||open.toggle).focus();}
});
document.addEventListener('pointerdown',e=>{
  if(!open||open.menu.contains(e.target)||open.toggle.contains(e.target))return;
  close(false);
},true);
addEventListener('scroll',e=>{if(open&&open.menu.contains(e.target))return;close(false);},true);
addEventListener('resize',()=>close(false));
new MutationObserver(records=>{
  for(const r of records){for(const n of r.addedNodes){if(n.nodeType===1){queue();return;}}}
}).observe(document.body,{childList:true,subtree:true});
document.addEventListener('focusout',e=>{
  if(!open)return;
  if(open.menu.contains(e.target)&&!open.menu.contains(e.relatedTarget)&&e.relatedTarget!==open.toggle)close(false);
});
enhance(document);
window.v2TableActions={enhance,close:()=>close(false)};
})();
</script>
