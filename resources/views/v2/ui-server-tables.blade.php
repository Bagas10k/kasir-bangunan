<script id="ui-server-tables-script">
/* Pagination, pencarian, dan saringan server-side untuk tabel operasional V2 ([data-server-table]).
   Menjaga beban muat halaman awal tetap ringan (10 baris) sambil membuka akses penuh ke seluruh riwayat di server. */
(()=>{
'use strict';
const containers=Array.from(document.querySelectorAll('[data-server-table]'));
if(!containers.length)return;

for(const root of containers){
  const tableKey=root.dataset.serverTable;
  const endpoint=root.dataset.tableUrl;
  if(!tableKey||!endpoint)continue;
  const section=root.closest('section')||root.parentElement;
  const toolbar=Array.from(section.querySelectorAll('[data-ui-toolbar]')).find(t=>t.compareDocumentPosition(root)&Node.DOCUMENT_POSITION_FOLLOWING)||null;
  const searchInput=toolbar?.querySelector('[data-ui-search]')||null;
  const chips=Array.from(toolbar?.querySelectorAll('[data-ui-chip]')||[]);
  const panel=root.closest('.task-panel')||document;
  const isFirstInPanel=panel.querySelector?.('[data-server-table],[data-ui-table]')===root;
  const tiles=isFirstInPanel?Array.from(panel.querySelectorAll('[data-ui-filter]')):[];
  const defaultFilter=root.dataset.tableDefaultFilter||'all';
  const errorBox=document.createElement('p');
  errorBox.className='err';errorBox.setAttribute('role','alert');errorBox.tabIndex=-1;errorBox.hidden=true;
  root.before(errorBox);

  const state={
    q:'',
    filter:chips.find(c=>c.getAttribute('aria-pressed')==='true')?.dataset.uiChip||defaultFilter,
    per_page:Number(root.querySelector('[data-st-size]')?.value||10),
    page:1,
  };
  let seq=0,controller=null,queued=null,loading=false,refreshAfter=false,debounce=null;

  function syncButtons(){
    for(const chip of chips)chip.setAttribute('aria-pressed',String(chip.dataset.uiChip===state.filter));
    for(const tile of tiles)tile.setAttribute('aria-pressed',String(state.filter!=='all'&&tile.dataset.uiFilter===state.filter));
  }

  async function load(nextState,announce=false){
    if(root.querySelector('dialog[open]')){queued={nextState,announce};return;}
    const version=++seq;
    controller?.abort();
    controller=new AbortController();
    const signal=controller.signal;
    const url=new URL(endpoint,location.href);
    if(url.origin!==location.origin)return;
    url.searchParams.set('q',String(nextState.q||''));
    url.searchParams.set('filter',String(nextState.filter||defaultFilter));
    url.searchParams.set('per_page',String(nextState.per_page||10));
    url.searchParams.set('page',String(Math.max(1,Number(nextState.page||1))));
    root.setAttribute('aria-busy','true');
    errorBox.hidden=true;
    loading=true;
    const timer=setTimeout(()=>{if(controller?.signal===signal)controller.abort();},15000);
    let painted=false;
    try{
      const res=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal});
      if(res.redirected)throw Error('Sesi login habis. Muat ulang halaman lalu masuk kembali.');
      const data=await res.json().catch(()=>({}));
      if(!res.ok){
        throw Error(res.status===401||res.status===419?'Sesi login habis. Muat ulang halaman lalu masuk kembali.':
          (res.status===403?'Akses ditolak untuk daftar ini.':
          (data.message||'Daftar belum dapat dimuat. Coba lagi.')));
      }
      if(typeof data.html!=='string'||version!==seq)return;
      if(root.querySelector('dialog[open]')){queued={nextState,announce};return;}
      root.innerHTML=data.html;
      state.q=data.filters?.q??nextState.q??'';
      state.filter=data.filters?.filter??nextState.filter??defaultFilter;
      state.per_page=Number(data.filters?.per_page??nextState.per_page??10);
      state.page=Number(data.page??nextState.page??1);
      queued=null;painted=true;
      syncButtons();
      window.uiLive?.runHooks?.(root);
      document.dispatchEvent(new CustomEvent('ui-server-table:render',{detail:{root,table:tableKey,page:state.page,total:data.total}}));
      root.dispatchEvent(new CustomEvent('ui-table:render',{detail:{page:state.page,pages:data.last_page||1,total:data.total||0}}));
      window.enhanceSelects?.(root);
      window.v2TableActions?.enhance?.(root);
      window.uiMotion?.staggerRows?.(root);
    }catch(err){
      if(version!==seq||err?.name==='AbortError')return;
      errorBox.textContent=err?.message||'Data belum dapat dimuat. Periksa koneksi lalu coba lagi.';
      errorBox.hidden=false;
      if(announce)errorBox.focus();
    }finally{
      clearTimeout(timer);
      if(version===seq){
        loading=false;
        root.removeAttribute('aria-busy');
        const again=refreshAfter;refreshAfter=false;
        if(again&&painted)load({...state},false);
      }
    }
  }

  searchInput?.addEventListener('input',()=>{
    clearTimeout(debounce);
    const term=searchInput.value.trim();
    debounce=setTimeout(()=>load({...state,q:term,page:1},false),280);
  });
  searchInput?.addEventListener('keydown',e=>{
    if(e.key==='Enter'){e.preventDefault();clearTimeout(debounce);load({...state,q:searchInput.value.trim(),page:1},true);}
  });
  searchInput?.closest('form')?.addEventListener('submit',e=>e.preventDefault());

  for(const chip of chips){
    chip.addEventListener('click',()=>load({...state,filter:chip.dataset.uiChip||'all',page:1},true));
  }
  for(const tile of tiles){
    tile.addEventListener('click',()=>{
      const next=tile.getAttribute('aria-pressed')==='true'?'all':(tile.dataset.uiFilter||'all');
      load({...state,filter:next,page:1},true);
    });
  }

  root.addEventListener('click',e=>{
    const btn=e.target.closest('button[data-st-page]');
    if(!btn||btn.disabled)return;
    const page=Number(btn.dataset.stPage||'1');
    if(Number.isInteger(page)&&page>=1&&page!==state.page)load({...state,page},true);
  });
  root.addEventListener('change',e=>{
    const sel=e.target.closest('select[data-st-size]');
    if(!sel)return;
    const size=Number(sel.value||'10');
    if([10,25,50].includes(size))load({...state,per_page:size,page:1},true);
  });

  document.addEventListener('ui-live:patched',()=>{
    if(loading){refreshAfter=true;return;}
    load({...state},false);
  });
  document.addEventListener('close',e=>{
    if(!queued||e.target.closest('[data-server-table]')!==root||root.querySelector('dialog[open]'))return;
    const next=queued;queued=null;load(next.nextState,next.announce);
  },true);

  syncButtons();
}
})();
</script>
