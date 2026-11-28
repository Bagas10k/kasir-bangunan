<script id="journal-adjustment-script">
(()=>{
'use strict';
const selector='[data-journal-root]';
let root=document.querySelector(selector);
if(!root)return;

function feedback(message){
    const box=root.querySelector('[data-journal-feedback]');
    if(box){box.textContent=message;box.hidden=false;box.focus?.({preventScroll:true});}
}

function nextTransferKey(){
    if(typeof window.v2Uuid==='function')return window.v2Uuid();
    if(typeof crypto!=='undefined'&&typeof crypto.randomUUID==='function')return crypto.randomUUID();
    if(typeof crypto!=='undefined'&&typeof crypto.getRandomValues==='function'){
        const b=new Uint8Array(16);crypto.getRandomValues(b);
        b[6]=(b[6]&15)|64;b[8]=(b[8]&63)|128;
        const h=Array.from(b,v=>v.toString(16).padStart(2,'0')).join('');
        return h.slice(0,8)+'-'+h.slice(8,12)+'-'+h.slice(12,16)+'-'+h.slice(16,20)+'-'+h.slice(20);
    }
    return 'trf-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,12);
}

function ensureTransferKey(form,rotate=false){
    const field=form.elements?.namedItem?.('idempotency_key');
    if(!field)return;
    if(rotate||!field.value)field.value=nextTransferKey();
}

function transferDupBox(form){
    const node=form.querySelector?.('[data-transfer-duplicate]');
    return node?.hasAttribute?.('data-transfer-duplicate')?node:null;
}

function clearTransferDuplicate(form){
    delete form.dataset.confirmDuplicate;
    const confirmField=form.elements?.namedItem?.('confirm_duplicate');
    if(confirmField)confirmField.value='0';
    const dupBox=transferDupBox(form);
    if(dupBox)dupBox.hidden=true;
}

function bind(node,openFromHash=false){
    for(const id of ['#adjustment-form','#transfer-form']){
        const form=node.querySelector(id);
        if(!form)continue;
        ensureTransferKey(form,false);
        const onEdit=event=>{
            if(event?.target?.name==='password'||event?.target?.type==='password')return;
            clearTransferDuplicate(form);
            ensureTransferKey(form,true);
        };
        form.addEventListener?.('input',onEdit);
        form.addEventListener?.('change',onEdit);
        const rawDupBtn=form.querySelector?.('[data-transfer-confirm-duplicate]');
        const dupBtn=rawDupBtn?.hasAttribute?.('data-transfer-confirm-duplicate')?rawDupBtn:null;
        dupBtn?.addEventListener?.('click',()=>{
            form.dataset.confirmDuplicate='1';
            const confirmField=form.elements?.namedItem?.('confirm_duplicate');
            if(confirmField)confirmField.value='1';
            return save(form);
        });
        form.addEventListener('submit',event=>{
            event.preventDefault();
            return save(form);
        });
    }
    for(const dialog of node.querySelectorAll('dialog.journal-popup')){
        const opener=node.querySelector('[data-journal-open="'+dialog.id+'"]');
        const open=()=>{
            if(dialog.open)return;
            dialog.showModal();
            (dialog.querySelector('select:not([disabled])')||dialog.querySelector('.popup-close'))?.focus({preventScroll:true});
        };
        const close=()=>{
            if(!dialog.querySelector('form[data-busy]'))dialog.close();
        };
        opener?.addEventListener('click',open);
        for(const button of dialog.querySelectorAll('[data-journal-close]'))button.addEventListener('click',close);
        dialog.addEventListener('cancel',event=>{event.preventDefault();close();});
        dialog.addEventListener('close',()=>{
            const password=dialog.querySelector('input[type=password]');
            if(password)password.value='';
            opener?.focus({preventScroll:true});
        });
        if(openFromHash&&!node.closest('[data-remote-content]')&&location.hash==='#'+dialog.id)open();
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
    const dupBox=transferDupBox(form);
    const password=form.elements.namedItem('password');
    const confirmField=form.elements?.namedItem?.('confirm_duplicate');
    const buttons=Array.from(form.querySelectorAll('button[type=submit]'));
    form.dataset.busy='1';
    form.setAttribute('aria-busy','true');
    for(const button of buttons)button.disabled=true;
    if(error)error.hidden=true;
    if(dupBox)dupBox.hidden=true;
    window.v2FormFeedback?.clear(form);

    try{
        ensureTransferKey(form,false);
        if(confirmField)confirmField.value=form.dataset.confirmDuplicate==='1'?'1':'0';
        const meta=document.querySelector('meta[name=csrf-token]');
        const token=meta?.content||form.elements.namedItem('_token')?.value||'';
        const payload=new FormData(form);
        const keyField=form.elements?.namedItem?.('idempotency_key');
        if(keyField?.value&&typeof payload.set==='function')payload.set('idempotency_key',keyField.value);
        if(typeof payload.set==='function'&&confirmField)payload.set('confirm_duplicate',confirmField.value);
        const response=await fetch(form.action,{
            method:'POST',body:payload,credentials:'same-origin',
            headers:{Accept:'application/json','X-CSRF-TOKEN':token}
        });
        const data=await response.json().catch(()=>({}));
        if(!response.ok||response.redirected){
            const isDup=response.status===422&&Boolean(data.errors?.duplicate_transfer?.length);
            const fields=Object.values(data.errors||{}).flat().join(' ');
            const message=response.status>=500?'Server belum dapat menyimpan penyesuaian. Coba lagi nanti.':
                fields||(response.status===419?'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.':
                (response.status===401||response.status===403||response.redirected?'Sesi atau izin tidak aktif. Masuk kembali sebagai admin.':'Penyesuaian belum dapat disimpan. Periksa isian.'));
            if(error){error.textContent=message;error.hidden=false;}
            if(isDup&&dupBox){dupBox.hidden=false;}
            else{clearTransferDuplicate(form);}
            window.v2FormFeedback?.errors(form,data.errors||{});
            if(password)password.value='';
            (error||form).focus?.();
            return;
        }

        clearTransferDuplicate(form);
        ensureTransferKey(form,true);
        document.dispatchEvent(new CustomEvent('ui-remote:changed',{detail:{url:root.dataset.refreshUrl}}));
        if(document.querySelector(selector)!==root)return;
        if(password)password.value='';
        form.closest('dialog')?.close();
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

bind(root,true);
})();
</script>
