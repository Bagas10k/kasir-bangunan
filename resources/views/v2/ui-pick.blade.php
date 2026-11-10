{{--
    Pemilih dengan pencarian: kolom ketik + daftar saran di atas <select> yang sudah ada.
    Cocok untuk daftar panjang (produk): sumber pilihan diberikan lewat opts.source(term) dan dicari di server,
    jadi halaman tidak memuat seluruh daftar. Tanpa opts.source, pilihan diambil dari <option> select itu.
    Select aslinya tetap dipakai form: pilihan yang diambil dari server ditambahkan sebagai <option> terpilih.
--}}
<style>
.ui-pick{position:relative;display:block}
.ui-pick-native{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
.ui-pick-input{width:100%;min-height:44px;border:1px solid var(--ui-line);border-radius:9px;font:inherit;font-size:var(--ui-t-body);color:var(--ui-ink)}
html body .ui-pick>:is(.ui-pick-input,#u#u){padding:8px 12px 8px 38px !important;background-color:#fff !important;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%235F7468' stroke-width='2' stroke-linecap='round'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cpath d='m20 20-3.5-3.5'/%3E%3C/svg%3E") !important;background-repeat:no-repeat !important;background-position:12px 50% !important;background-size:17px !important}
html body .ui-pick>:is(.ui-pick-input,#u#u):disabled{background-color:#f3f6f4 !important}
.ui-pick-input::placeholder{color:#7C8F84}
.ui-pick-input:focus{outline:2px solid var(--ui-accent);outline-offset:1px;border-color:var(--ui-accent)}
.ui-pick-input:disabled{background-color:#f3f6f4;color:var(--ui-muted)}
.ui-pick-list{box-sizing:border-box;position:fixed;z-index:200;margin:0;padding:4px;list-style:none;overflow:auto;overscroll-behavior:contain;background:#fff;border:1px solid var(--ui-line);border-radius:11px;box-shadow:0 14px 32px rgb(15 42 29 / .18)}
.ui-pick-opt{display:flex;flex-direction:column;gap:1px;min-height:44px;justify-content:center;padding:7px 10px;border-radius:8px;cursor:pointer}
.ui-pick-opt strong{font-size:var(--ui-t-body);font-weight:600;line-height:1.35;color:var(--ui-ink)}
.ui-pick-opt small{font-size:13px;line-height:1.35;color:var(--ui-muted)}
.ui-pick-opt:hover,.ui-pick-opt.is-active{background:var(--ui-accent-soft)}
.ui-pick-opt[aria-disabled=true]{cursor:default;opacity:.55}
.ui-pick-opt[aria-disabled=true]:hover{background:none}
.ui-pick-none,.ui-pick-more{padding:10px;font-size:13px;line-height:1.4;color:var(--ui-muted)}
.ui-pick-more{border-top:1px solid var(--ui-line);margin-top:4px}
</style>
<script>
(()=>{
'use strict';
/**
 * Membungkus satu <select> menjadi kolom pencarian.
 * opts.source(term) -> Promise<{items:[{value,label,meta,disabled}], total?:number}>
 * opts.emptyText: saran bila tidak ada hasil. opts.offText: placeholder saat select nonaktif.
 * opts.onPick(item): dipanggil setelah pilihan diambil. Aman dipanggil dua kali pada select yang sama.
 */
window.uiPick=function(select,opts={}){
 if(!select||select.dataset.pickReady)return;
 select.dataset.pickReady='1';
 select.setAttribute('data-native-select','');
 const shell=select.closest('.select-shell');
 if(shell){shell.replaceWith(select);}
 const wrap=document.createElement('div');wrap.className='ui-pick';
 select.parentNode.insertBefore(wrap,select);wrap.append(select);select.classList.add('ui-pick-native');select.tabIndex=-1;
 const input=document.createElement('input');
 input.type='text';input.className='ui-pick-input';input.autocomplete='off';input.spellcheck=false;
 input.setAttribute('role','combobox');input.setAttribute('aria-autocomplete','list');input.setAttribute('aria-expanded','false');
 const list=document.createElement('ul');list.className='ui-pick-list';list.setAttribute('role','listbox');list.hidden=true;
 wrap.append(input,list);
 const hint=select.dataset.pickPlaceholder||'Cari…';
 let index=-1,items=[],ticket=0,timer=0;

 const local=async term=>{
  const needle=term.trim().toLowerCase();
  const rows=Array.from(select.options).filter(o=>o.value&&!o.hidden&&!o.disabled&&(!needle||o.textContent.toLowerCase().includes(needle)));
  return {items:rows.slice(0,60).map(o=>({value:o.value,label:o.textContent.trim()})),total:rows.length};
 };
 const source=opts.source||local;

 const sync=()=>{
  const chosen=select.selectedOptions[0];
  input.value=chosen&&chosen.value?chosen.textContent.trim():'';
  input.disabled=select.disabled;
  input.placeholder=select.disabled?(opts.offText||'Belum bisa dipilih'):hint;
  const label=select.getAttribute('aria-label');if(label)input.setAttribute('aria-label',label);
 };
 const place=()=>{
  if(list.hidden)return;
  const box=input.getBoundingClientRect(),gap=4;
  const below=innerHeight-box.bottom-12,above=box.top-12;
  const up=below<180&&above>below;
  const rows=list.querySelectorAll('.ui-pick-opt');
  const six=rows.length>6?rows[5].offsetTop+rows[5].offsetHeight+list.offsetHeight-list.clientHeight+4:Infinity;   // tepat 6 pilihan terlihat, selebihnya digulir
  const room=Math.max(120,Math.min(six,up?above:below));
  list.style.left=box.left+'px';list.style.width=box.width+'px';list.style.maxHeight=room+'px';
  list.style.top=up?'auto':(box.bottom+gap)+'px';
  list.style.bottom=up?(innerHeight-box.top+gap)+'px':'auto';
 };
 const close=()=>{ticket++;clearTimeout(timer);list.hidden=true;list.replaceChildren();items=[];index=-1;input.setAttribute('aria-expanded','false');};
 const pick=item=>{
  if(item.disabled)return;
  let option=Array.from(select.options).find(o=>o.value===String(item.value));
  if(!option){option=new Option(item.label,item.value);select.add(option);}
  select.value=String(item.value);
  select.dispatchEvent(new Event('change',{bubbles:true}));
  opts.onPick?.(item);
  close();sync();
 };
 const note=(cls,text)=>{const li=document.createElement('li');li.className=cls;li.textContent=text;list.append(li);};

 function show(result,term){
  list.replaceChildren();items=[];
  const found=result.items||[];
  if(found.length===0){note('ui-pick-none',term.trim()?'Tidak ada yang cocok dengan "'+term.trim()+'". '+(opts.emptyText||''):(opts.emptyText||'Belum ada pilihan.'));}
  for(const item of found){
   const row=document.createElement('li');row.className='ui-pick-opt';row.setAttribute('role','option');
   const title=document.createElement('strong');title.textContent=item.label;row.append(title);
   if(item.meta){const sub=document.createElement('small');sub.textContent=item.meta;row.append(sub);}
   if(item.disabled){row.setAttribute('aria-disabled','true');}
   row.addEventListener('mousedown',event=>{event.preventDefault();pick(item);});
   list.append(row);items.push({node:row,item});
  }
  if(result.total>found.length){note('ui-pick-more','Menampilkan '+found.length+' dari '+result.total+'. Ketik nama atau kode untuk mempersempit.');}
  index=-1;place();
 }
 async function render(term){
  const mine=++ticket;
  list.hidden=false;input.setAttribute('aria-expanded','true');
  if(!items.length){list.replaceChildren();note('ui-pick-none','Mencari…');}
  place();
  try{
   const result=await source(term);
   if(mine===ticket)show(result,term);
  }catch{
   if(mine===ticket){list.replaceChildren();items=[];note('ui-pick-none','Daftar gagal dimuat. Coba ketik lagi.');}
  }
 }

 input.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>render(input.value),opts.source?180:0);});
 input.addEventListener('focus',()=>{if(!select.disabled){input.select();render('');}});
 input.addEventListener('blur',()=>setTimeout(()=>{close();sync();},120));
 input.addEventListener('keydown',event=>{
  if(event.key==='ArrowDown'&&list.hidden){render(input.value);return;}
  if(list.hidden)return;
  const usable=items.filter(entry=>!entry.item.disabled);
  if(event.key==='ArrowDown'||event.key==='ArrowUp'){
   if(!usable.length)return;
   event.preventDefault();
   const at=usable.findIndex(entry=>entry===items[index]);
   const next=usable[(at+(event.key==='ArrowDown'?1:-1)+usable.length)%usable.length];
   index=items.indexOf(next);
   items.forEach((entry,i)=>entry.node.classList.toggle('is-active',i===index));
   next.node.scrollIntoView({block:'nearest'});
  }else if(event.key==='Enter'){
   event.preventDefault();
   const entry=items[index]||usable[0];if(entry)pick(entry.item);
  }else if(event.key==='Escape'){event.stopPropagation();close();}
 });
 addEventListener('resize',place);
 document.addEventListener('scroll',place,true);
 select.addEventListener('change',sync);
 new MutationObserver(sync).observe(select,{attributes:true,attributeFilter:['disabled']});
 select._uiPickSync=sync;
 sync();
};
})();
</script>
