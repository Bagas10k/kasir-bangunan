{{--
    Pemilih dari <select> yang sudah ada: mengetik untuk menyaring, tanpa permintaan ke server.
    Dipakai saat pilihan bisa banyak (misal daftar produk). Select aslinya tetap dipakai form.
--}}
<style>
.ui-pick{position:relative;display:block}
.ui-pick-native{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
.ui-pick-input{width:100%;min-height:40px;padding:8px 30px 8px 11px;border:1px solid var(--ui-line);border-radius:9px;background:#fff;font:inherit;font-size:var(--ui-t-body);color:var(--ui-ink)}
.ui-pick-input:focus{outline:2px solid var(--ui-accent);outline-offset:1px;border-color:var(--ui-accent)}
.ui-pick-input:disabled{background:#f3f6f4;color:var(--ui-muted)}
.ui-pick-caret{position:absolute;right:12px;top:50%;width:8px;height:8px;border-right:2px solid var(--ui-muted);border-bottom:2px solid var(--ui-muted);transform:translateY(-70%) rotate(45deg);pointer-events:none}
.ui-pick-list{position:absolute;z-index:60;inset-inline:0;top:calc(100% + 4px);margin:0;padding:4px;list-style:none;max-height:240px;overflow:auto;background:#fff;border:1px solid var(--ui-line);border-radius:11px;box-shadow:0 14px 32px rgb(15 42 29 / .16)}
.ui-pick-opt{display:flex;justify-content:space-between;gap:10px;min-height:38px;align-items:center;padding:7px 10px;border-radius:7px;font-size:var(--ui-t-body);cursor:pointer}
.ui-pick-opt:hover,.ui-pick-opt.is-active{background:var(--ui-accent-soft)}
.ui-pick-none{padding:10px;color:var(--ui-muted);font-size:var(--ui-t-label)}
</style>
<script>
(()=>{
'use strict';
/** Membungkus satu <select> menjadi kolom ketik + daftar saran. Aman dipanggil dua kali. */
window.uiPick=function(select){
 if(!select||select.dataset.pickReady)return;
 select.dataset.pickReady='1';
 select.setAttribute('data-native-select','');   // hiasan select lama melewati select ini
 const shell=select.closest('.select-shell');     // lepaskan bila terlanjur dipasang
 if(shell){shell.replaceWith(select);}
 const wrap=document.createElement('div');wrap.className='ui-pick';
 select.parentNode.insertBefore(wrap,select);wrap.append(select);select.classList.add('ui-pick-native');select.tabIndex=-1;
 const input=document.createElement('input');
 input.type='text';input.className='ui-pick-input';input.autocomplete='off';input.spellcheck=false;
 input.setAttribute('role','combobox');input.setAttribute('aria-autocomplete','list');input.setAttribute('aria-expanded','false');
 const caret=document.createElement('span');caret.className='ui-pick-caret';caret.setAttribute('aria-hidden','true');
 const list=document.createElement('ul');list.className='ui-pick-list';list.setAttribute('role','listbox');list.hidden=true;
 wrap.append(input,caret,list);
 let index=-1,items=[];

 const usable=()=>Array.from(select.options).filter(o=>o.value&&!o.hidden&&!o.disabled);
 const sync=()=>{
  const chosen=select.selectedOptions[0];
  input.value=chosen&&chosen.value?chosen.textContent.trim():'';
  input.placeholder=select.options[0]?.textContent.trim()||'Pilih';
  input.disabled=select.disabled;
  const label=select.getAttribute('aria-label');if(label)input.setAttribute('aria-label',label);
 };
 const close=()=>{list.hidden=true;list.replaceChildren();items=[];index=-1;input.setAttribute('aria-expanded','false');};
 const pick=option=>{select.value=option.value;select.dispatchEvent(new Event('change',{bubbles:true}));close();sync();};

 function render(term=''){
  const needle=term.trim().toLowerCase();
  const found=usable().filter(o=>!needle||o.textContent.toLowerCase().includes(needle));
  list.replaceChildren();items=[];
  if(found.length===0){
   const empty=document.createElement('li');empty.className='ui-pick-none';
   empty.textContent=select.disabled?'Pilih supplier dahulu.':'Tidak ada yang cocok.';
   list.append(empty);
  }
  for(const option of found.slice(0,60)){
   const row=document.createElement('li');row.className='ui-pick-opt';row.setAttribute('role','option');
   row.textContent=option.textContent.trim();
   row.addEventListener('mousedown',event=>{event.preventDefault();pick(option);});
   list.append(row);items.push({node:row,option});
  }
  list.hidden=false;input.setAttribute('aria-expanded','true');index=-1;
 }

 input.addEventListener('input',()=>render(input.value));
 input.addEventListener('focus',()=>{if(!select.disabled)render('');});
 input.addEventListener('blur',()=>setTimeout(()=>{close();sync();},120));
 input.addEventListener('keydown',event=>{
  if(event.key==='ArrowDown'&&list.hidden){render('');return;}
  if(list.hidden||items.length===0)return;
  if(event.key==='ArrowDown'||event.key==='ArrowUp'){
   event.preventDefault();
   index=(index+(event.key==='ArrowDown'?1:-1)+items.length)%items.length;
   items.forEach((item,i)=>item.node.classList.toggle('is-active',i===index));
   items[index].node.scrollIntoView({block:'nearest'});
  }else if(event.key==='Enter'){
   event.preventDefault();pick((items[index]||items[0]).option);
  }else if(event.key==='Escape'){close();}
 });
 select.addEventListener('change',sync);
 new MutationObserver(sync).observe(select,{attributes:true,attributeFilter:['disabled']});
 select._uiPickSync=sync;
 sync();
};
})();
</script>
