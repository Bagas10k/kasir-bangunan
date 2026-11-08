{{-- Perilaku form master. Gayanya ada di v2/ui-forms-styles (dimuat di <head>). --}}
<script>
(()=>{
'use strict';
// Angka tampil "12.500,5"; nilai yang dikirim tetap mentah "12500.5" di input tersembunyi.
const group=s=>s.replace(/\B(?=(\d{3})+(?!\d))/g,'.');
function readNumber(text,scale){
  const s=String(text||'').replace(/[^\d,]/g,'');const comma=s.indexOf(',');
  let int=(comma<0?s:s.slice(0,comma)).replace(/^0+(?=\d)/,'');const dec=comma<0?'':s.slice(comma+1).replace(/,/g,'').slice(0,scale);
  if(int===''&&(comma>=0||dec))int='0';
  return {int,dec,comma:comma>=0};
}
function hiddenFor(el){return el.closest('.uf-affix')?.querySelector('input[type=hidden]');}
function syncNumber(el){
  const n=readNumber(el.value,+el.dataset.scale||2);
  el.value=(n.int?group(n.int):'')+(n.dec?','+n.dec:(n.comma?',':''));
  const hidden=hiddenFor(el);if(!hidden)return;
  hidden.value=n.int===''?(el.hasAttribute('data-ui-blank')?'':'0'):n.int+(n.dec?'.'+n.dec:'');
  hidden.dispatchEvent(new Event('change',{bubbles:true}));
}
function plate(value){
  const s=String(value||'').toUpperCase().replace(/[^A-Z0-9]/g,'');
  const full=s.match(/^([A-Z]{1,2})(\d{1,4})([A-Z]{0,3})$/);if(full)return full[1]+' '+full[2]+(full[3]?' '+full[3]:'');
  const part=s.match(/^([A-Z]{1,2})(\d*)$/);if(part)return part[1]+(part[2]?' '+part[2]:'');
  return s;
}
document.addEventListener('input',e=>{
  const el=e.target;
  if(el.matches?.('[data-ui-number]')){syncNumber(el);try{el.setSelectionRange(el.value.length,el.value.length);}catch{}}
  else if(el.matches?.('[data-ui-plate]')){el.value=plate(el.value);}
});
// Satuan produk tampil sebagai akhiran stok minimum.
function syncUnit(form){
  const unit=form.querySelector('select[name=unit_id]');
  for(const suffix of form.querySelectorAll('[data-unit-suffix]'))suffix.textContent=unit?.value?(unit.selectedOptions[0]?.textContent.replace(/ \(arsip\)$/,'').trim()||'satuan'):'satuan';
}
// Untung per barang dihitung dengan bilangan bulat (skala 4), hanya untuk tampilan.
const SCALE=10000n;
function scaled(raw){const m=String(raw||'0').match(/^(\d+)(?:\.(\d{0,4})\d*)?$/);if(!m)return 0n;return BigInt(m[1])*SCALE+BigInt((m[2]||'').padEnd(4,'0'));}
function rupiah(v){const neg=v<0n;if(neg)v=-v;const int=v/SCALE,dec=(v%SCALE).toString().padStart(4,'0').replace(/0+$/,'').slice(0,2);return (neg?'-':'')+'Rp '+group(int.toString())+(dec?','+dec:'');}
function syncMargin(form){
  const cost=form.querySelector('input[type=hidden][name=cost_price]'),sell=form.querySelector('input[type=hidden][name=selling_price]');
  if(!cost||!sell)return;
  const c=scaled(cost.value);
  // Untung per satuan untuk harga jual dan (bila diisi) harga kontraktor.
  for(const price of [sell,form.querySelector('input[type=hidden][name=contractor_price]')]){
   if(!price)continue;
   const box=price.closest('.uf');let out=box?.querySelector('[data-ui-margin]');
   if(!out&&box){out=document.createElement('output');out.className='uf-margin';out.dataset.uiMargin='';out.setAttribute('aria-live','polite');box.append(out);}
   if(!out)continue;
   const s=price.value===''?0n:scaled(price.value);
   if(s===0n){out.textContent='';out.removeAttribute('data-tone');continue;}
   const diff=s-c;out.dataset.tone=diff<0n?'loss':'gain';
   const pct=c>0n?' · '+(Number(diff*1000n/c)/10).toLocaleString('id-ID',{maximumFractionDigits:1})+'%':'';
   out.textContent=(diff<0n?'Rugi ':'Untung ')+rupiah(diff<0n?-diff:diff)+pct;
  }
}
// Dropdown banyak pilihan: pilih dari daftar, muncul sebagai keping. Keping pertama = utama.
for(const box of document.querySelectorAll('[data-ui-multiref]')){
 const picker=box.querySelector('[data-multiref-picker]'),list=box.querySelector('[data-multiref-list]'),blank=box.querySelector('[data-multiref-empty]'),primary=box.querySelector('[data-multiref-primary]');
 const name=box.closest('.uf')?.dataset.multirefName||'supplier_ids[]';
 function sync(){
  const items=[...list.children];
  items.forEach((li,i)=>{const tag=li.querySelector('.uf-pick-main');if(i===0&&!tag){const m=document.createElement('span');m.className='uf-pick-main';m.textContent='Utama';li.querySelector('.uf-pick-x').before(m);}else if(i>0&&tag)tag.remove();});
  if(primary)primary.value=items[0]?.querySelector('input')?.value||'';
  if(blank)blank.hidden=items.length>0;
  if(picker.hasAttribute('data-required'))picker.required=items.length===0;
 }
 picker.addEventListener('change',()=>{
  const id=picker.value;if(!id)return;
  const label=picker.selectedOptions[0].textContent.trim();
  const li=document.createElement('li');li.className='uf-pick';
  li.innerHTML='<span class="uf-pick-name"></span><button type="button" class="uf-pick-x" data-multiref-remove aria-label="Hapus">×</button>';
  li.querySelector('.uf-pick-name').textContent=label;
  const hidden=document.createElement('input');hidden.type='hidden';hidden.name=name;hidden.value=id;li.append(hidden);
  list.append(li);picker.selectedOptions[0].disabled=true;picker.value='';
  picker.dispatchEvent(new Event('ui-refresh',{bubbles:true}));sync();
 });
 list.addEventListener('click',e=>{
  const button=e.target.closest('[data-multiref-remove]');if(!button)return;
  const li=button.closest('li'),id=li.querySelector('input')?.value;
  li.remove();const option=[...picker.options].find(o=>o.value===id);if(option)option.disabled=false;
  picker.dispatchEvent(new Event('ui-refresh',{bubbles:true}));sync();
 });
 sync();
}
// Pesan salah tampil di bawah kolomnya (bahasa Indonesia), bukan gelembung peramban.
function fieldMessage(el){
  const v=el.validity;
  if(v.valueMissing)return el.matches('select')?'Pilih salah satu.':(el.type==='file'?'Pilih berkas Excel dulu.':'Wajib diisi.');
  if(v.tooLong)return 'Terlalu panjang, maksimal '+el.maxLength+' karakter.';
  if(v.typeMismatch||v.patternMismatch)return 'Format belum sesuai.';
  return el.validationMessage||'Periksa isian ini.';
}
function setFieldError(el,message){
  const box=el.closest('.uf');if(!box)return;
  let out=box.querySelector(':scope>.uf-error');
  if(!message){box.removeAttribute('data-invalid');out?.remove();return;}
  box.setAttribute('data-invalid','');
  if(!out){out=document.createElement('p');out.className='uf-error';out.setAttribute('role','alert');box.append(out);}
  out.textContent=message;
}
function refreshCount(area){const out=area.closest('.uf')?.querySelector('[data-count-for="'+area.id+'"]');if(out){out.textContent=area.value.length+'/'+area.maxLength;out.dataset.near=String(area.value.length>=area.maxLength*0.9);}}
for(const form of document.querySelectorAll('form.uf-form')){
  form.addEventListener('invalid',e=>{
    const el=e.target;setFieldError(el,fieldMessage(el));
    if(el.matches('select'))return;               // select kustom memfokuskan dan membuka dirinya sendiri
    e.preventDefault();
    const first=[...form.elements].find(x=>x.willValidate&&!x.validity.valid);
    if(first===el){(el.type==='file'?el.closest('.uf-drop')??el:el).scrollIntoView({block:'center',behavior:'smooth'});el.focus({preventScroll:true});}
  },true);
  const clear=e=>{const el=e.target;if(el.closest?.('.uf[data-invalid]')&&el.validity?.valid)setFieldError(el,'');};
  form.addEventListener('input',e=>{form.setAttribute('data-dirty','');clear(e);if(e.target.matches('[data-ui-count]'))refreshCount(e.target);});
  form.addEventListener('change',clear);
  for(const area of form.querySelectorAll('[data-ui-count]'))refreshCount(area);
  // Jenis mobil: tombol pilihan cepat mengisi kolom; mengetik sendiri tetap boleh.
  for(const group of form.querySelectorAll('[data-suggest-for]')){
    const input=document.getElementById(group.dataset.suggestFor);if(!input)continue;
    const sync=()=>{for(const b of group.querySelectorAll('[data-suggest]'))b.setAttribute('aria-pressed',String(b.dataset.suggest.toLowerCase()===input.value.trim().toLowerCase()));};
    group.addEventListener('click',e=>{const b=e.target.closest('[data-suggest]');if(!b)return;input.value=b.dataset.suggest;input.dispatchEvent(new Event('input',{bubbles:true}));sync();input.focus();});
    input.addEventListener('input',sync);sync();
  }
  // Berkas impor: tampilkan nama dan ukuran, sorot saat diseret ke atas kotak.
  for(const drop of form.querySelectorAll('[data-ui-drop]')){
    const file=drop.querySelector('input[type=file]'),name=drop.querySelector('[data-drop-file]'),title=drop.querySelector('[data-drop-title]');
    const size=b=>b<1024*1024?Math.max(1,Math.round(b/1024))+' KB':(b/1024/1024).toLocaleString('id-ID',{maximumFractionDigits:1})+' MB';
    const show=()=>{const f=file.files?.[0];drop.classList.toggle('has-file',!!f);name.textContent=f?f.name+' · '+size(f.size):'';title.textContent=f?'Berkas siap diperiksa':'Pilih berkas Excel';if(f)setFieldError(file,'');};
    file.addEventListener('change',show);
    for(const type of ['dragenter','dragover'])drop.addEventListener(type,()=>drop.classList.add('is-over'));
    for(const type of ['dragleave','drop'])drop.addEventListener(type,()=>drop.classList.remove('is-over'));
    form.addEventListener('reset',()=>setTimeout(show));show();
  }
  for(const input of form.querySelectorAll('[data-ui-plate]'))input.value=plate(input.value);
  syncUnit(form);syncMargin(form);
  form.addEventListener('change',e=>{if(e.target.name==='unit_id')syncUnit(form);if(e.target.name==='cost_price'||e.target.name==='selling_price')syncMargin(form);});
  form.addEventListener('input',e=>{if(e.target.matches('[data-ui-number]'))syncMargin(form);});
  // Nilai tersembunyi diperbarui setelah event input; hitung ulang untung saat nilainya berubah.
  form.addEventListener('change',e=>{if(e.target.matches('input[type=hidden][name$=_price]'))syncMargin(form);});
}
})();
</script>
