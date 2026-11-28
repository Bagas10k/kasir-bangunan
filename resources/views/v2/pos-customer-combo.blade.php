{{--
    Kasir mengetik nama pembeli: yang sudah terdaftar muncul sebagai saran,
    yang belum terdaftar bisa langsung dibuat tanpa meninggalkan layar kasir.
    Kontrol ini menyetir <select> pelanggan yang sudah ada agar aturan kredit tetap berlaku.
--}}
<script>
(()=>{
'use strict';
const searchUrl=@json(route('v2.customers.search'), JSON_UNESCAPED_SLASHES);
const resolveUrl=@json(route('v2.customers.resolve'), JSON_UNESCAPED_SLASHES);
const token=document.querySelector('meta[name="csrf-token"]')?.content||'';
const combos=[];

function build(select){
 if(!select||select.dataset.comboReady)return;
 select.dataset.comboReady='1';
 select.setAttribute('data-native-select','');
 const oldShell=select.closest('.select-shell');if(oldShell)oldShell.replaceWith(select);
 const wrap=document.createElement('div');wrap.className='pos-combo';
 const input=document.createElement('input');
 input.type='text';input.className='pos-combo-input';input.autocomplete='off';input.spellcheck=false;
 input.setAttribute('role','combobox');input.setAttribute('aria-expanded','false');input.setAttribute('aria-autocomplete','list');
 input.placeholder='Nama';
 input.setAttribute('aria-label','Nama pembeli');
 const list=document.createElement('ul');list.className='pos-combo-list';list.setAttribute('role','listbox');list.hidden=true;
 const note=document.createElement('p');note.className='pos-combo-note';note.setAttribute('role','status');
 const listId='combo-list-'+select.id;list.id=listId;input.setAttribute('aria-controls',listId);
 select.parentNode.insertBefore(wrap,select);wrap.append(input,list,note);select.classList.add('pos-combo-native');
 const combo={select,input,list,note,items:[],index:-1};combos.push(combo);

 const labelOf=id=>{const option=select.querySelector('option[value="'+id+'"]');return option?(option.dataset.name||option.textContent.trim()):'';};
 const sync=()=>{input.value=select.value?labelOf(select.value):'';note.textContent=select.value?'Pelanggan terdaftar '+(select.selectedOptions[0]?.dataset.code||''):'Kosongkan untuk pelanggan umum.';};
 const close=()=>{list.hidden=true;list.replaceChildren();combo.index=-1;input.setAttribute('aria-expanded','false');};

 function applyMeta(option,name,tier,meta){
  option.dataset.name=name;
  option.dataset.tier=tier||'retail';
  option.dataset.code=meta?.code||'';
  option.dataset.openCount=String(meta?.open_invoices_count??0);
  option.dataset.openBalance=String(meta?.open_balance??'0.00');
  option.dataset.overdueCount=String(meta?.overdue_count??0);
  option.dataset.overdueBalance=String(meta?.overdue_balance??'0.00');
  option.textContent=name;
 }
 function choose(id,name,tier,meta){
  if(id){
   let option=select.querySelector('option[value="'+id+'"]');
   if(!option){option=document.createElement('option');option.value=id;select.append(option);}
   applyMeta(option,name,tier,meta);
   for(const other of combos)if(other.select!==select){
    let otherOpt=other.select.querySelector('option[value="'+id+'"]');
    if(!otherOpt){otherOpt=document.createElement('option');otherOpt.value=id;other.select.append(otherOpt);}
    applyMeta(otherOpt,name,tier,meta);
   }
   select.value=String(id);
  }else{select.value='';}
  select.dispatchEvent(new Event('change',{bubbles:true}));
  close();sync();
 }

 function formatRp(raw){
  const parts=String(raw||'0').split('.');
  const intPart=(parts[0]||'0').replace(/\B(?=(\d{3})+(?!\d))/g,'.');
  const dec=(parts[1]||'').replace(/0+$/,'');
  return 'Rp '+intPart+(dec?','+dec:'');
 }
 function render(results,term){
  list.replaceChildren();combo.items=[];
  const add=(label,hint,action,kind)=>{
   const item=document.createElement('li');item.setAttribute('role','option');item.className='pos-combo-item'+(kind?' pos-combo-'+kind:'');
   item.append(Object.assign(document.createElement('span'),{textContent:label,className:'pos-combo-name'}));
   if(hint)item.append(Object.assign(document.createElement('span'),{textContent:hint,className:'pos-combo-hint'}));
   if(kind==='general')item.setAttribute('aria-selected',String(!select.value));
   item.addEventListener('mousedown',event=>{event.preventDefault();action();});
   list.append(item);combo.items.push({node:item,action});
  };
  add('Pelanggan umum','Tanpa memilih data pelanggan',()=>choose('',''),'general');
  for(const row of results){
   const parts=[row.code];
   if(row.price_tier==='contractor')parts.push('Kontraktor');
   if(Number(row.open_invoices_count)>0){
    let debt='Piutang '+formatRp(row.open_balance);
    if(Number(row.overdue_count)>0)debt+=' ('+row.overdue_count+' lewat tempo)';
    parts.push(debt);
   }
   add(row.name,parts.join(' · '),()=>choose(row.id,row.name,row.price_tier,row));
  }
  const exact=results.some(row=>row.name.toLowerCase()===term.toLowerCase());
  if(term.length>=2&&!exact)add('Buat pelanggan baru: '+term,'disimpan ke data pelanggan',()=>create(term),'create');
  list.hidden=false;input.setAttribute('aria-expanded','true');combo.index=-1;
 }

 async function create(name){
  note.textContent='Menyimpan pelanggan baru…';
  try{
   const response=await fetch(resolveUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token},body:JSON.stringify({name})});
   const payload=await response.json();
   if(!response.ok)throw new Error(payload.message||'Pelanggan gagal disimpan.');
   choose(payload.id,payload.name,payload.price_tier,payload);
   note.textContent=payload.created?'Pelanggan baru '+payload.code+' dibuat.':'Memakai pelanggan terdaftar '+payload.code+'.';
  }catch(error){note.textContent=error.message;close();}
 }

 let timer=null;
 input.addEventListener('input',()=>{
  const term=input.value.trim();
  clearTimeout(timer);
  if(term===''){choose('','');note.textContent='Kosongkan untuk pelanggan umum.';render([],'');return;}
  timer=setTimeout(async()=>{
   try{
    const response=await fetch(searchUrl+'?q='+encodeURIComponent(term),{credentials:'same-origin',headers:{'Accept':'application/json'}});
    const payload=await response.json();
    render(payload.results||[],term);
   }catch{render([],term);}
  },180);
 });
 input.addEventListener('focus',()=>{if(input.value.trim()==='')render([],'');});
 input.addEventListener('blur',()=>setTimeout(close,120));
 input.addEventListener('keydown',event=>{
  if(list.hidden)return;
  if(event.key==='ArrowDown'||event.key==='ArrowUp'){
   event.preventDefault();
   const step=event.key==='ArrowDown'?1:-1;
   combo.index=(combo.index+step+combo.items.length)%combo.items.length;
   combo.items.forEach((item,i)=>item.node.classList.toggle('is-active',i===combo.index));
  }else if(event.key==='Enter'){
   event.preventDefault();
   (combo.items[combo.index]||combo.items[combo.items.length-1])?.action();
  }else if(event.key==='Escape'){close();}
 });
 select.addEventListener('change',sync);
 sync();
}

build(document.querySelector('#pos-customer-quick'));
build(document.querySelector('#customer-id'));
})();
</script>
