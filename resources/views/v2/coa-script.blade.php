<script id="coa-operational-script">
(()=>{
'use strict';
const form=document.getElementById('coa-form');
let root=document.querySelector('[data-coa-root]');
if(!form||!root)return;
const error=form.querySelector('[data-coa-error]');
function feedback(message){
    const box=root.querySelector('[data-coa-feedback]');
    if(box){box.textContent=message;box.hidden=false;}
}
async function refresh(message){
    if(document.getElementById('coa-form')!==form)return;
    const url=new URL(root.dataset.refreshUrl,location.href);url.searchParams.set('partial','1');
    const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'text/html'}});
    if(!response.ok||response.redirected)throw Error('refresh');
    const doc=new DOMParser().parseFromString(await response.text(),'text/html'),next=doc.querySelector('[data-coa-root]');
    if(!next)throw Error('refresh');
    if(document.getElementById('coa-form')!==form)return;
    const filters=root.querySelector('form.master-toolbar');
    const draft=filters?Array.from(new FormData(filters)):[];
    root.replaceWith(next);root=next;
    for(const [name,value]of draft){
        const field=root.querySelector('form.master-toolbar')?.elements.namedItem(name);
        if(field)field.value=value;
    }
    bindArchives(root);
    window.uiTableFilters?.scan(root);
    window.enhanceSelects?.(root);
    feedback(message);
    window.uiMotion?.enter(root);
}
async function save(target){
    if(target.dataset.busy||!target.reportValidity())return;
    const editing=target===form;
    const buttons=Array.from(target.querySelectorAll('button[type=submit]'));
    target.dataset.busy='1';target.setAttribute('aria-busy','true');
    for(const button of buttons)button.disabled=true;
    if(editing){error.hidden=true;window.v2FormFeedback?.clear(form);}
    try{
        const response=await fetch(target.action,{
            method:'POST',body:new FormData(target),credentials:'same-origin',
            headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}
        });
        const data=await response.json().catch(()=>({}));
        if(!response.ok||response.redirected){
            const message=response.status>=500?'Server belum dapat menyimpan akun. Coba lagi nanti.':
                Object.values(data.errors||{}).flat().join(' ')||
                (response.status===401||response.status===403||response.redirected?'Sesi atau izin tidak aktif. Masuk kembali sebagai admin.':'Akun belum dapat disimpan. Periksa isian.');
            if(editing){
                error.textContent=message;error.hidden=false;
                window.v2FormFeedback?.errors(form,data.errors||{});error.focus();
            }else feedback(message);
            return;
        }
        document.dispatchEvent(new CustomEvent('ui-remote:changed',{detail:{url:root.dataset.refreshUrl}}));
        window.uiLive?.refresh?.();
        if(document.getElementById('coa-form')!==form)return;
        if(editing)window.closeModal('modal-coa');
        const message=data.message||'Akun berhasil disimpan.';
        try{await refresh(message);}catch{feedback(message+' Daftar belum dapat diperbarui; buka kembali menu Bagan akun.');}
    }catch{
        const message='Koneksi terputus. Periksa daftar akun sebelum mencoba menyimpan lagi.';
        if(editing){error.textContent=message;error.hidden=false;error.focus();}else feedback(message);
    }finally{
        delete target.dataset.busy;target.removeAttribute('aria-busy');
        for(const button of buttons)button.disabled=false;
    }
}
function bindArchives(node){
    node.addEventListener('submit',event=>{
        if(!event.target.matches('[data-coa-archive]'))return;
        event.preventDefault();return save(event.target);
    });
}
form.addEventListener('submit',event=>{event.preventDefault();return save(form);});
bindArchives(root);
})();
</script>
