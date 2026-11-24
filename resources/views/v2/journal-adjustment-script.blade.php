<script id="journal-adjustment-script">
(()=>{
'use strict';
const selector='[data-journal-root]';
let root=document.querySelector(selector);
if(!root)return;

function feedback(message){
    const box=root.querySelector('[data-journal-feedback]');
    if(box){box.textContent=message;box.hidden=false;}
}

function bind(node){
    for(const id of ['#adjustment-form','#transfer-form']){
        const form=node.querySelector(id);
        if(!form)continue;
        form.addEventListener('submit',event=>{
            event.preventDefault();
            return save(form);
        });
    }
}

async function refresh(message){
    if(document.querySelector(selector)!==root)return;
    const url=new URL(root.dataset.refreshUrl,location.href);
    url.searchParams.set('partial','1');
    const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'text/html'}});
    if(!response.ok||response.redirected)throw Error('refresh');
    const doc=new DOMParser().parseFromString(await response.text(),'text/html');
    const next=doc.querySelector(selector);
    if(!next)throw Error('refresh');
    if(document.querySelector(selector)!==root)return;
    root.replaceWith(next);
    root=next;
    bind(root);
    feedback(message);
    window.enhanceSelects?.(root);
    window.uiMotion?.enter(root);
}

async function save(form){
    if(form.dataset.busy||!form.reportValidity())return;
    const error=form.querySelector('[data-adjustment-error]');
    const password=form.elements.namedItem('password');
    const buttons=Array.from(form.querySelectorAll('button[type=submit]'));
    form.dataset.busy='1';
    form.setAttribute('aria-busy','true');
    for(const button of buttons)button.disabled=true;
    if(error)error.hidden=true;
    window.v2FormFeedback?.clear(form);

    try{
        const meta=document.querySelector('meta[name=csrf-token]');
        const token=meta?.content||form.elements.namedItem('_token')?.value||'';
        const response=await fetch(form.action,{
            method:'POST',body:new FormData(form),credentials:'same-origin',
            headers:{Accept:'application/json','X-CSRF-TOKEN':token}
        });
        const data=await response.json().catch(()=>({}));
        if(!response.ok||response.redirected){
            const fields=Object.values(data.errors||{}).flat().join(' ');
            const message=response.status>=500?'Server belum dapat menyimpan penyesuaian. Coba lagi nanti.':
                fields||(response.status===419?'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.':
                (response.status===401||response.status===403||response.redirected?'Sesi atau izin tidak aktif. Masuk kembali sebagai admin.':'Penyesuaian belum dapat disimpan. Periksa isian.'));
            if(error){error.textContent=message;error.hidden=false;}
            window.v2FormFeedback?.errors(form,data.errors||{});
            if(password)password.value='';
            (error||form).focus?.();
            return;
        }

        document.dispatchEvent(new CustomEvent('ui-remote:changed',{detail:{url:root.dataset.refreshUrl}}));
        if(document.querySelector(selector)!==root)return;
        if(password)password.value='';
        const message=data.message||'Penyesuaian saldo tersimpan.';
        try{
            await refresh(message);
        }catch{
            feedback(message+' Daftar belum dapat diperbarui; buka kembali menu Jurnal umum.');
        }
    }catch{
        const message='Koneksi terputus. Periksa daftar jurnal sebelum mencoba menyimpan lagi.';
        if(error){error.textContent=message;error.hidden=false;}
        if(password)password.value='';
        (error||form).focus?.();
    }finally{
        delete form.dataset.busy;
        form.removeAttribute('aria-busy');
        for(const button of buttons)button.disabled=false;
    }
}

bind(root);
})();
</script>
