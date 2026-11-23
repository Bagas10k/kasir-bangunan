<script>
(()=>{
'use strict';
const root=document.getElementById('receipt-restock'),form=document.getElementById('receipt-form');
if(!root||!form)return;
const get=name=>root.querySelector('[data-restock-'+name+']');
const search=get('search'),results=get('results'),message=get('message'),scope=get('scope');
const prev=get('prev'),next=get('next'),pageLabel=get('page'),chips=[...root.querySelectorAll('[data-restock-status]')];
const supplier=form.elements.supplier_id,items=document.getElementById('receipt-items'),dialog=form.closest('dialog');
const labels={critical:'Perlu restok',out:'Stok habis',low:'Stok menipis'};
let current={q:'',status:'critical',page:1},records=[],actions=new Map(),lastPage=1,version=0,controller=null,fresh=false,pending=false;
const editor=get('editor'),choice=get('supplier-choice'),newName=get('new-name'),newNotes=get('new-notes'),save=get('supplier-save'),cancel=get('supplier-cancel'),editorError=get('supplier-error');
let editing=null,manual=false,writing=false,writeRequest=null,editorVersion=0,opener=null;
const text=(tag,value)=>{const node=document.createElement(tag);node.textContent=value;return node;};
const quantity=value=>String(value).replace('.',',');
function updateActions(){
    for(const [id,button] of actions){
        const product=records.find(row=>String(row.id)===id);
        const added=[...items.children].some(row=>row.querySelector('[data-name=product_id]')?.value===id);
        const matches=product.suppliers.some(row=>String(row.id)===supplier.value);
        button.textContent=added?'Sudah ditambahkan':!product.suppliers.length?'Isi supplier':product.suppliers.length>1&&!matches?'Pilih supplier':'Tambahkan';
        button.disabled=pending||!fresh||added||writing||Boolean(form.dataset.busy)||form.classList.contains('is-reviewing');
    }
}
function render(data){
    records=data.results;actions=new Map();results.replaceChildren();
    for(const product of records){
        const row=document.createElement('li'),identity=document.createElement('div'),stock=document.createElement('div'),suppliers=document.createElement('div');
        identity.append(text('strong',product.name),text('small',product.sku+' · '+product.unit));
        const badge=text('span',labels[product.status]);badge.className='ui-badge ui-badge--'+(product.status==='out'?'bad':'warn');
        identity.append(badge);
        stock.append(text('strong',quantity(product.stock)+' '+product.unit),text('small','Stok saat ini · minimum '+quantity(product.minimum)));
        suppliers.append(text('small','Supplier'),text('strong',product.suppliers.map(item=>item.name).join(', ')||'Belum terhubung'));
        const button=text('button','Tambahkan');button.type='button';button.className='secondary';button.dataset.restockProduct=String(product.id);
        actions.set(String(product.id),button);row.append(identity,stock,suppliers,button);results.append(row);
    }
    for(const chip of chips){
        chip.textContent=labels[chip.dataset.restockStatus]+' ('+data.counts[chip.dataset.restockStatus]+')';
        chip.setAttribute('aria-pressed',String(chip.dataset.restockStatus===current.status));
    }
    scope.textContent='Seluruh toko · satu penerimaan untuk satu supplier'+(current.q?' · pencarian: '+current.q:'');
    message.textContent=data.total?data.total+' barang ditemukan. Isi jumlah sesuai barang yang diterima.':'Tidak ada barang yang sesuai filter ini.';
    lastPage=data.last_page;pageLabel.textContent='Halaman '+current.page+' dari '+lastPage;
}
async function load(wanted={...current}){
    const request=++version;controller?.abort();controller=new AbortController();pending=true;fresh=false;
    updateActions();prev.disabled=true;next.disabled=true;results.setAttribute('aria-busy','true');
    message.textContent='Memuat kebutuhan restok…';
    const url=new URL(root.dataset.url,location.href);
    url.searchParams.set('q',wanted.q);url.searchParams.set('status',wanted.status);url.searchParams.set('page',String(wanted.page));
    try{
        const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});
        if(!response.ok||response.redirected)throw Error('read');
        const data=await response.json();
        if(request!==version)return;
        if(!Array.isArray(data.results)||!Number.isInteger(data.page)||!data.counts)throw Error('shape');
        current={...wanted,page:data.page};fresh=true;render(data);
    }catch(error){
        if(request!==version||error.name==='AbortError')return;
        message.textContent='Gagal memuat daftar restok. Tekan Perbarui untuk mencoba lagi. Isian penerimaan tetap tersimpan.';
    }finally{
        if(request===version){
            pending=false;results.setAttribute('aria-busy','false');updateActions();
            prev.disabled=!fresh||current.page<=1;next.disabled=!fresh||current.page>=lastPage;
        }
    }
}
get('search-button').addEventListener('click',()=>load({...current,q:search.value.trim(),page:1}));
search.addEventListener('keydown',event=>{
    if(event.key==='Enter'){event.preventDefault();load({...current,q:search.value.trim(),page:1});}
});
get('refresh').addEventListener('click',()=>load());
prev.addEventListener('click',()=>load({...current,page:Math.max(1,current.page-1)}));
next.addEventListener('click',()=>load({...current,page:Math.min(lastPage,current.page+1)}));
supplier.addEventListener('change',updateActions);
form.addEventListener('change',updateActions);
form.addEventListener('reset',()=>queueMicrotask(()=>{closeEditor(false);updateActions();if(dialog?.open)load({...current,page:1});}));
if(items)new MutationObserver(updateActions).observe(items,{childList:true,subtree:true});
function closeEditor(restore=true){
    if(writing)return;
    editor.hidden=true;editing=null;writeRequest=null;editorVersion++;
    if(restore)opener?.focus();
}
function applySupplier(product,selected){
    if(!form._canUseReceiptSupplier?.(selected.id)){
        message.textContent='Barang ini berasal dari supplier lain. Simpan/selesaikan draf saat ini terlebih dahulu; isian tidak dihapus.';return;
    }
    if(!form._selectReceiptSupplier?.(selected))return;
    const outcome=form._addRestockProduct?.(product);
    message.textContent=outcome==='added'?product.name+' ditambahkan ke daftar penerimaan. Isi jumlah; HPP saat ini diisi jika tersedia dan dapat diubah.'
        :outcome==='duplicate'?'Barang sudah ditambahkan.'
        :outcome==='limit'?'Maksimal 100 baris per penerimaan.':'Barang belum ditambahkan. Periksa formulir penerimaan.';
    updateActions();
}
function openEditor(product,button){
    if(writing)return;
    editing=product;manual=product.suppliers.length===0;opener=button;writeRequest=null;editorVersion++;
    choice.replaceChildren(new Option('Pilih supplier',''));
    const options=manual?[...supplier.options].filter(option=>option.value).map(option=>({id:option.value,name:option.textContent})):product.suppliers;
    for(const option of options)choice.add(new Option(option.name,String(option.id)));
    if(manual)choice.add(new Option('Tambah supplier baru…','new'));
    choice.value=manual&&options.some(option=>String(option.id)===supplier.value)?supplier.value:'';
    newName.value='';newNotes.value='';get('new-fields').hidden=true;editorError.hidden=true;
    save.textContent=manual?'Simpan dan hubungkan ke barang':'Gunakan supplier';
    get('supplier-title').textContent='Supplier untuk '+product.name;
    editor.hidden=false;window.enhanceSelects?.(editor);choice.dispatchEvent(new Event('ui-refresh'));
    get('supplier-title').focus();
}
choice.addEventListener('change',()=>{get('new-fields').hidden=choice.value!=='new';});
cancel.addEventListener('click',()=>closeEditor());
save.addEventListener('click',async()=>{
    if(!editing||writing||form.dataset.busy||form.classList.contains('is-reviewing'))return;
    const selected=choice.value,product=editing,revision=editorVersion;
    const fail=text=>{editorError.textContent=text;editorError.hidden=false;};
    if(!selected){fail('Pilih supplier terlebih dahulu.');return;}
    if(!manual){
        const linked=product.suppliers.find(option=>String(option.id)===selected);
        if(linked){closeEditor(false);applySupplier(product,linked);}return;
    }
    if((selected==='new'&&form._hasReceiptItems?.())||(selected!=='new'&&!form._canUseReceiptSupplier?.(selected))){
        fail('Selesaikan penerimaan supplier saat ini dahulu. Isian yang sudah ada tidak dihapus.');return;
    }
    if(selected==='new'&&!newName.value.trim()){fail('Isi nama supplier baru.');return;}
    const payload={product_id:product.id,confirmed:true,...(selected==='new'?{name:newName.value.trim(),notes:newNotes.value.trim()||null}:{supplier_id:selected})};
    const fingerprint=JSON.stringify(payload);
    if(!writeRequest||writeRequest.fingerprint!==fingerprint)writeRequest={fingerprint,key:crypto.randomUUID()};
    payload.idempotency_key=writeRequest.key;
    writing=true;form.dataset.busy='1';editor.dataset.busy='1';save.disabled=true;cancel.disabled=true;editorError.hidden=true;updateActions();
    let saved=null;
    try{
        const response=await fetch(root.dataset.linkUrl,{method:'POST',credentials:'same-origin',headers:{
            Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||'',
        },body:JSON.stringify(payload)});
        if(!response.ok||response.redirected){
            if(response.status===422){const data=await response.json();fail(data.message||'Supplier belum disimpan. Periksa pilihan dan nama supplier.');}
            else fail('Gagal menyimpan supplier. Isian tetap tersimpan; periksa daftar supplier lalu coba lagi.');
            return;
        }
        const data=await response.json();
        if(!data.supplier?.id||typeof data.supplier.name!=='string')throw Error('shape');
        saved=data.supplier;
    }catch{
        fail('Koneksi terputus atau jawaban tidak valid. Periksa daftar supplier; ulangi konfirmasi yang sama untuk mencoba kembali dengan aman.');
    }finally{
        writing=false;delete form.dataset.busy;delete editor.dataset.busy;save.disabled=false;cancel.disabled=false;updateActions();
    }
    if(saved&&revision===editorVersion&&(!dialog||dialog.open)){
        if(![...supplier.options].some(option=>option.value===String(saved.id)))supplier.add(new Option(saved.name,String(saved.id)));
        product.suppliers=[saved];closeEditor(false);applySupplier(product,saved);
    }
});
root.addEventListener('click',event=>{
    const button=event.target.closest('[data-restock-status],[data-restock-product]');if(!button)return;
    if(button.dataset.restockStatus){return load({...current,status:button.dataset.restockStatus,page:1});}
    if(pending||!fresh||writing||button.disabled)return;
    const product=records.find(row=>String(row.id)===button.dataset.restockProduct);if(!product)return;
    const matching=product.suppliers.find(row=>String(row.id)===supplier.value);
    if(matching||product.suppliers.length===1){closeEditor(false);applySupplier(product,matching||product.suppliers[0]);}
    else openEditor(product,button);
});
if(dialog)new MutationObserver(()=>{
    if(dialog.open)load();
    else{version++;controller?.abort();pending=false;fresh=false;closeEditor(false);updateActions();}
}).observe(dialog,{attributes:true,attributeFilter:['open']});
if(!dialog||dialog.open)load();
updateActions();
})();
</script>
