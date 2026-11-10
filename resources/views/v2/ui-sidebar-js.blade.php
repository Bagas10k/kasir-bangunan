<script>
// Perilaku sidebar bersama (workspace dan halaman lama): grup tersimpan, drawer mobile,
// rail ikon dengan kartu sub-menu, tombol tepi buka/ciutkan. Tidak tahu apa pun tentang panel.
(()=>{
'use strict';
const sidebar=document.querySelector('#workspace-sidebar');if(!sidebar)return;
const navigation=sidebar.querySelector('.sidebar-nav');if(!navigation)return;
const menuGroups=Array.from(navigation.querySelectorAll('[data-menu-group]'));
const menuStorageKey='v2-workspace-groups:'+navigation.dataset.role;
try{const stored=JSON.parse(sessionStorage.getItem(menuStorageKey)||'null');if(stored&&typeof stored==='object'){for(const group of menuGroups){if(typeof stored[group.dataset.menuGroup]==='boolean')group.open=stored[group.dataset.menuGroup];}}}catch{}
for(const group of menuGroups){group.addEventListener('toggle',()=>{try{sessionStorage.setItem(menuStorageKey,JSON.stringify(Object.fromEntries(menuGroups.map(item=>[item.dataset.menuGroup,item.open]))));}catch{}});}
const currentLink=navigation.querySelector('[aria-current="page"]');if(currentLink)currentLink.closest('details').open=true;
const menu=document.querySelector('.mobile-menu-toggle'),overlay=document.querySelector('#sidebar-overlay')||(()=>{/* Halaman kerja tidak memuat shell-sidebar: buat latar laci agar halaman meredup dan ketuk di luar menutup menu. */const o=document.createElement('button');o.type='button';o.id='sidebar-overlay';o.className='sidebar-overlay';o.tabIndex=-1;o.setAttribute('aria-label','Tutup menu');document.body.append(o);return o})(),top=document.querySelector('header.top,header.main-topbar'),main=document.querySelector('main');
function mobile(){return matchMedia('(max-width:900px)').matches;}
function drawer(open,{restore=true}={}){open=mobile()&&open;document.body.classList.toggle('sidebar-open',open);menu?.setAttribute('aria-expanded',String(open));sidebar.inert=mobile()&&!open;if(main)main.inert=open;if(top)top.inert=open;if(overlay)overlay.tabIndex=-1;if(open)sidebar.querySelector('.sidebar-close')?.focus({preventScroll:true});else if(restore&&mobile())menu?.focus({preventScroll:true});}
const edge=document.createElement('button');edge.type='button';edge.className='sidebar-edge';edge.setAttribute('aria-controls','workspace-sidebar');edge.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>';
document.body.append(edge);
// Rail tertutup: tooltip nama grup + kartu sub-menu di bawah ikon (hover, fokus, atau Enter).
const tip=document.createElement('div');tip.className='rail-tip';tip.hidden=true;tip.setAttribute('aria-hidden','true');
const fly=document.createElement('div');fly.className='rail-flyout';fly.hidden=true;fly.setAttribute('role','group');
document.body.append(tip,fly);
const isRail=()=>!mobile()&&!sidebar.classList.contains('pinned');
let flyGroup=null,hideTimer=0,mute=false,flyExit=null;
function hideFly({instant=false}={}){clearTimeout(hideTimer);hideTimer=0;tip.hidden=true;flyGroup=null;
 if(fly.hidden||flyExit)return;
 if(instant||!window.uiMotion){fly.hidden=true;return;}
 flyExit=window.uiMotion.dismiss(fly,()=>{fly.hidden=true;flyExit=null;});}
function scheduleHide(){clearTimeout(hideTimer);hideTimer=setTimeout(hideFly,170);}
function showFly(group,{focus=false}={}){
 if(!isRail())return;clearTimeout(hideTimer);
 // Sedang keluar lalu disentuh lagi: batalkan keluarnya, kartu dianggap masih terbuka (asal untuk morph).
 if(flyExit){flyExit.cancel();flyExit=null;}
 if(flyGroup===group&&!fly.hidden){if(focus)fly.querySelector('a')?.focus({preventScroll:true});return;}
 const fill=()=>buildFly(group);
 if(!fly.hidden&&window.uiMotion){window.uiMotion.morphBox(fly,fill);}else{fill();}
 if(focus)fly.querySelector('a')?.focus({preventScroll:true});
}
function buildFly(group){
 const summary=group.querySelector('summary'),r=summary.getBoundingClientRect(),name=summary.querySelector('span').textContent.trim();
 flyGroup=group;fly.replaceChildren();fly.setAttribute('aria-label',name);
 // Judul grup ada di dalam kartu, sebaris dan setinggi ikon rail: kursor bergerak lurus ke kanan langsung masuk kartu.
 const head=document.createElement('div');head.className='rail-flyout-title';head.setAttribute('aria-hidden','true');head.textContent=name;head.style.minHeight=Math.round(r.height)+'px';fly.append(head);
 for(const item of group.querySelectorAll('.workspace-submenu a')){
  const a=document.createElement('a');a.href=item.getAttribute('href');a.textContent=item.textContent.trim();if(item.getAttribute('aria-current'))a.setAttribute('aria-current','page');
  a.addEventListener('click',e=>{e.preventDefault();hideFly({instant:true});if(item.dataset.task)item.click();else location.href=item.href;});fly.append(a);
 }
 fly.hidden=false;
 const h=fly.offsetHeight,rail=sidebar.getBoundingClientRect();
 // Kartu sub-menu selalu di kanan rail (kalau menutupi ikon, grup lain tidak bisa ditekan), dan baris judulnya sejajar ikon.
 // Padding atas kartu = jarak judul ke tepi kartu, jadi kartu dimulai sedikit di atas ikon. Dekat dasar layar, kartu naik secukupnya.
 const pad=parseFloat(getComputedStyle(fly).paddingTop)||0;
 const top=Math.round(Math.max(8,Math.min(r.top-pad,innerHeight-8-h)));
 tip.hidden=true;fly.style.left=Math.round(rail.right+8)+'px';fly.style.top=top+'px';
 // Titik asal animasi masuk = tengah ikon grup, sehingga kartu terasa keluar dari ikon.
 fly.style.transformOrigin='0 '+Math.round(r.top+r.height/2-top)+'px';
}
for(const group of menuGroups){
 const summary=group.querySelector('summary');summary.removeAttribute('title');
 summary.addEventListener('mouseenter',()=>showFly(group));summary.addEventListener('mouseleave',scheduleHide);
 summary.addEventListener('focus',()=>{if(!mute&&isRail())showFly(group);});
 summary.addEventListener('click',e=>{if(!isRail())return;e.preventDefault();showFly(group,{focus:true});});
 summary.addEventListener('keydown',e=>{if(e.key==='Escape'&&!fly.hidden)hideFly();if(isRail()&&e.key==='ArrowRight'){e.preventDefault();showFly(group,{focus:true});}});
}
fly.addEventListener('mouseenter',()=>clearTimeout(hideTimer));fly.addEventListener('mouseleave',scheduleHide);
fly.addEventListener('focusout',e=>{if(!fly.contains(e.relatedTarget))scheduleHide();});
fly.addEventListener('keydown',e=>{
 const items=[...fly.querySelectorAll('a')],i=items.indexOf(document.activeElement);
 if(e.key==='Escape'){e.preventDefault();const g=flyGroup;hideFly();mute=true;g?.querySelector('summary').focus();mute=false;}
 else if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();items[(i+(e.key==='ArrowDown'?1:-1)+items.length)%items.length].focus();}
 else if(e.key==='Tab'){e.preventDefault();items[(i+(e.shiftKey?-1:1)+items.length)%items.length].focus();}
});
document.addEventListener('pointerdown',e=>{if(!fly.hidden&&!fly.contains(e.target)&&!sidebar.contains(e.target))hideFly();});
window.addEventListener('resize',()=>hideFly({instant:true}));
function setPinned(on){hideFly({instant:true});document.body.classList.toggle('sidebar-pinned',on);sidebar.classList.toggle('pinned',on);edge.setAttribute('aria-pressed',String(on));edge.setAttribute('aria-label',on?'Ciutkan menu':'Buka menu tetap');edge.title=on?'Ciutkan menu':'Buka menu tetap';try{localStorage.setItem('v2-sidebar-pinned',on?'1':'0');}catch{}}
edge.addEventListener('click',()=>setPinned(!sidebar.classList.contains('pinned')));
// Logo menjadi tombol: klik melebarkan atau menciutkan sidebar (desktop). Di HP sidebar tetap laci dari tombol menu.
const brand=sidebar.querySelector('.brand-mark');
if(brand){brand.setAttribute('role','button');brand.tabIndex=0;brand.removeAttribute('aria-hidden');brand.setAttribute('aria-controls','workspace-sidebar');
 const syncBrand=()=>{const on=sidebar.classList.contains('pinned');brand.setAttribute('aria-expanded',String(on));brand.setAttribute('aria-label',on?'Ciutkan menu':'Tampilkan menu');brand.title=on?'Ciutkan menu':'Tampilkan menu';};
 const toggle=()=>{if(matchMedia('(max-width:900px)').matches)return;setPinned(!sidebar.classList.contains('pinned'));syncBrand();};
 brand.addEventListener('click',toggle);brand.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();toggle();}});syncBrand();}
// Sidebar selalu rail ikon (tombol lebar dihapus sesuai desain); status lebar lama dibuang.
setPinned(false);try{localStorage.removeItem('v2-sidebar-pinned');}catch{}document.documentElement.classList.remove('pre-pinned');
menu?.addEventListener('click',()=>drawer(true));sidebar.querySelector('.sidebar-close')?.addEventListener('click',()=>drawer(false));overlay?.addEventListener('click',()=>drawer(false));
window.addEventListener('resize',()=>drawer(false,{restore:false}));
document.addEventListener('keydown',e=>{if(!document.body.classList.contains('sidebar-open'))return;if(e.key==='Escape'){e.preventDefault();drawer(false);}if(e.key==='Tab'){const nodes=Array.from(sidebar.querySelectorAll('button,a,summary')).filter(el=>el.tabIndex>=0&&el.getBoundingClientRect().height);const first=nodes[0],last=nodes[nodes.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}});
window.v2Sidebar={close(){drawer(false);}};
drawer(false,{restore:false});
})();
</script>
