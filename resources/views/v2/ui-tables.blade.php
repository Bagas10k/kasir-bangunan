<script>
/* Perilaku tabel standar V2: cari, saring (chip + ubin monitor), halaman, kondisi kosong.
   Pasang pada .ui-table-wrap[data-ui-table]. Baris memakai atribut data-* (mis. data-status) untuk disaring. */
(()=>{
'use strict';
const norm=s=>String(s||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/\s+/g,' ').trim();
const wrappers=Array.from(document.querySelectorAll('[data-ui-table]'));
if(!wrappers.length)return;
const parseFilter=spec=>{if(!spec||spec==='all')return null;const i=spec.indexOf(':');return i<0?null:{key:spec.slice(0,i),values:spec.slice(i+1).split(',')};};
const camel=k=>k.replace(/-([a-z])/g,(_,c)=>c.toUpperCase());

for(const wrap of wrappers){
  const table=wrap.querySelector('table');if(!table||!table.tBodies[0])continue;
  const body=table.tBodies[0];
  const pick=()=>Array.from(body.rows).filter(r=>!r.hasAttribute('data-ui-empty')&&!r.hasAttribute('data-ui-empty-static'));
  let rows=pick();
  const scope=wrap.closest('section')||wrap.parentElement;
  const toolbar=Array.from(scope.querySelectorAll('[data-ui-toolbar]')).find(t=>t.compareDocumentPosition(wrap)&Node.DOCUMENT_POSITION_FOLLOWING)||null;
  const searchInput=toolbar?.querySelector('[data-ui-search]')||null;
  const chips=Array.from(toolbar?.querySelectorAll('[data-ui-chip]')||[]);
  const panel=wrap.closest('.task-panel')||document;
  const isFirstInPanel=panel.querySelector('[data-ui-table]')===wrap;
  const tiles=isFirstInPanel?Array.from(panel.querySelectorAll('[data-ui-filter]')):[];
  // Teks yang dicari: isi sel tanpa formulir/dialog (agar kata di dalam popup tidak ikut cocok).
  const index=list=>new Map(list.map(r=>{let t='';for(const c of r.cells){const copy=c.cloneNode(true);copy.querySelectorAll('dialog,form,template').forEach(n=>n.remove());t+=' '+copy.textContent;}return [r,norm(t)];}));
  let text=index(rows);
  const state={q:'',filter:null,page:1,size:10};
  const colspan=table.tHead?.rows[0]?.cells.length||1;
  const emptyRow=document.createElement('tr');emptyRow.setAttribute('data-ui-empty','');emptyRow.hidden=true;
  const emptyCell=emptyRow.insertCell();emptyCell.colSpan=colspan;emptyCell.className='ui-empty';emptyCell.innerHTML='<strong>Tidak ada data yang cocok.</strong>Ubah kata kunci atau pilih saringan lain.';
  body.append(emptyRow);
  const pager=document.createElement('div');pager.className='ui-pager';
  const status=document.createElement('p');status.className='ui-status-line';status.setAttribute('role','status');status.setAttribute('aria-live','polite');status.style.margin='0';
  const controls=document.createElement('div');controls.className='ui-pager-controls';
  const sizeLabel=document.createElement('label');sizeLabel.textContent='Tampilkan ';const sizeSelect=document.createElement('select');sizeSelect.setAttribute('aria-label','Jumlah baris per halaman');
  for(const n of [10,25,50]){const o=document.createElement('option');o.value=n;o.textContent=n+' baris';sizeSelect.append(o);}
  sizeLabel.append(sizeSelect);
  const prev=document.createElement('button');prev.type='button';prev.textContent='Sebelumnya';
  const next=document.createElement('button');next.type='button';next.textContent='Lanjut';
  const pageInfo=document.createElement('span');pageInfo.className='ui-pages';pageInfo.setAttribute('role','group');
  controls.append(sizeLabel,prev,pageInfo,next);pager.append(status,controls);wrap.after(pager);

  const matches=row=>{
    if(state.q&&!text.get(row).includes(state.q))return false;
    if(state.filter){const raw=row.dataset[camel(state.filter.key)];if(!state.filter.values.includes(raw))return false;}
    return true;
  };
  function render(){
    const hit=rows.filter(matches),total=hit.length,pages=Math.max(1,Math.ceil(total/state.size));
    state.page=Math.min(Math.max(1,state.page),pages);
    const from=(state.page-1)*state.size,to=Math.min(from+state.size,total),visible=new Set(hit.slice(from,to));
    for(const r of rows)r.hidden=!visible.has(r);
    emptyRow.hidden=total!==0||rows.length===0;
    const filtering=Boolean(state.q||state.filter);
    const noun=wrap.dataset.uiNoun||'data';
    status.textContent=!rows.length?'':(total===0?'Tidak ada data yang cocok.':(filtering?total+' dari '+rows.length+' '+noun+' cocok. ':'')+'Menampilkan '+(from+1)+'–'+to+' dari '+total+' '+noun+'.');
    pager.hidden=!rows.length;controls.hidden=total<=10&&pages===1;
    wrap.dispatchEvent(new CustomEvent('ui-table:render',{detail:{page:state.page,pages,total}}));
    prev.disabled=state.page<=1;next.disabled=state.page>=pages;
    // Nomor halaman (maks. 5 di sekitar halaman aktif) agar lompat halaman cukup sekali ketuk.
    pageInfo.setAttribute('aria-label','Halaman '+state.page+' dari '+pages);pageInfo.replaceChildren();
    const first=Math.max(1,Math.min(state.page-2,pages-4)),last=Math.min(pages,first+4);
    for(let n=first;n<=last;n++){const b=document.createElement('button');b.type='button';b.textContent=String(n);b.setAttribute('aria-label','Halaman '+n);if(n===state.page)b.setAttribute('aria-current','page');b.addEventListener('click',()=>{state.page=n;render();});pageInfo.append(b);}
  }
  function setFilter(spec,source){
    state.filter=parseFilter(spec);state.page=1;
    for(const chip of chips)chip.setAttribute('aria-pressed',String(chip.dataset.uiChip===(spec||'all')));
    for(const tile of tiles)tile.setAttribute('aria-pressed',String(Boolean(spec)&&tile.dataset.uiFilter===spec));
    render();
  }
  searchInput?.addEventListener('input',()=>{state.q=norm(searchInput.value);state.page=1;render();});
  searchInput?.closest('form')?.addEventListener('submit',e=>e.preventDefault());
  for(const chip of chips)chip.addEventListener('click',()=>setFilter(chip.dataset.uiChip==='all'?null:chip.dataset.uiChip));
  for(const tile of tiles)tile.addEventListener('click',()=>setFilter(tile.getAttribute('aria-pressed')==='true'?null:tile.dataset.uiFilter));
  sizeSelect.addEventListener('change',()=>{state.size=Number(sizeSelect.value);state.page=1;render();});
  wrap.addEventListener('ui-table:reveal',e=>{const i=rows.filter(matches).indexOf(e.detail?.row);if(i>=0&&Math.floor(i/state.size)+1!==state.page){state.page=Math.floor(i/state.size)+1;render();}});
  prev.addEventListener('click',()=>{state.page--;render();});next.addEventListener('click',()=>{state.page++;render();});
  // uiLive mengganti isi <tbody>: baca ulang baris, pertahankan kata kunci, saringan, dan halaman.
  wrap._uiTableReload=()=>{rows=pick();text=index(rows);if(emptyRow.parentNode!==body)body.append(emptyRow);render();};
  const initial=chips.find(c=>c.getAttribute('aria-pressed')==='true');
  if(initial&&initial.dataset.uiChip!=='all')state.filter=parseFilter(initial.dataset.uiChip);
  render();
}
})();
</script>
