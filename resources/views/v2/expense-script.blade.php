<script id="expense-operational-script">
(()=>{
'use strict';
const form=document.getElementById('expense-form');
let root=document.querySelector('[data-expense-root]');
if(!form||!root)return;
// Bersihkan salinan modal lama di <body> bila panel Pengeluaran dimuat ulang di workspace.
for(const stray of document.querySelectorAll('body > #modal-expense')){
    if(!root.contains(stray)&&stray!==form.closest('#modal-expense'))stray.remove();
}
const errorBox=form.querySelector('[data-expense-error]');
const sourceSelect=form.elements.namedItem?.('source');
const sourceSeg=form.querySelector('[data-expense-source-seg]');
const bankField=form.querySelector('[data-expense-bank-field]');
const bankSelect=form.elements.namedItem?.('bank_account_code');
if(sourceSelect&&sourceSeg){
    const buttons=Array.from(sourceSeg.querySelectorAll('button[data-source-value]'));
    const syncSource=()=>{
        for(const b of buttons){
            const on=b.dataset.sourceValue===sourceSelect.value;
            b.setAttribute('aria-checked',String(on));
            b.tabIndex=on?0:-1;
        }
        const isBank=sourceSelect.value==='bank';
        if(bankField)bankField.hidden=!isBank;
        if(bankSelect){bankSelect.disabled=!isBank;bankSelect.required=isBank;}
    };
    for(const b of buttons){
        b.addEventListener('click',()=>{
            if(sourceSelect.value!==b.dataset.sourceValue){
                sourceSelect.value=b.dataset.sourceValue;
                sourceSelect.dispatchEvent(new Event('change',{bubbles:true}));
            }
            syncSource();
        });
    }
    sourceSeg.addEventListener('keydown',e=>{
        if(!['ArrowLeft','ArrowRight'].includes(e.key))return;
        e.preventDefault();
        const i=buttons.findIndex(b=>b.dataset.sourceValue===sourceSelect.value);
        const next=buttons[(i+(e.key==='ArrowRight'?1:-1)+buttons.length)%buttons.length];
        next.click();next.focus();
    });
    sourceSelect.addEventListener('change',syncSource);
    syncSource();
}
function feedback(message){
    const box=root.querySelector('[data-expense-feedback]');
    if(box){box.textContent=message;box.hidden=false;}
}
function rawMoney(value){
    if(window.uiMoney?.toRaw)return window.uiMoney.toRaw(value);
    if(typeof window.displayToRaw==='function')return window.displayToRaw(value);
    const cleaned=String(value??'').trim().replace(/\./g,'').replace(',','.');
    return cleaned;
}
async function refresh(message){
    if(document.getElementById('expense-form')!==form)return;
    const url=new URL(root.dataset.refreshUrl,location.href);
    const filters=root.querySelector('form.exp-filter');
    const draft=filters?Array.from(new FormData(filters)):[];
    for(const [name,value]of draft){if(String(value).trim()!=='')url.searchParams.set(name,value);}
    url.searchParams.set('partial','1');
    const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'text/html'}});
    if(!response.ok||response.redirected)throw Error('refresh');
    const doc=new DOMParser().parseFromString(await response.text(),'text/html');
    const next=doc.querySelector('[data-expense-root]');
    if(!next)throw Error('refresh');
    if(document.getElementById('expense-form')!==form)return;
    // Buang modal yang sempat dipindah ke <body> agar tidak tersisa ID kembar.
    const oldModal=document.getElementById('modal-expense');
    if(oldModal&&!root.contains(oldModal))oldModal.remove();
    root.replaceWith(next);root=next;
    for(const [name,value]of draft){
        const field=root.querySelector('form.exp-filter')?.elements.namedItem(name);
        if(field)field.value=value;
    }
    window.uiTableFilters?.scan(root);
    window.enhanceSelects?.(root);
    feedback(message);
    window.uiMotion?.enter(root);
}
form.addEventListener('submit',async event=>{
    event.preventDefault();
    if(form.dataset.busy||!form.reportValidity())return;
    const submit=form.querySelector('button[type=submit]');
    form.dataset.busy='1';form.setAttribute('aria-busy','true');
    if(submit)submit.disabled=true;
    if(errorBox){errorBox.hidden=true;errorBox.textContent='';}
    try{
        const payload=new FormData(form);
        const amtInput=form.elements.namedItem('amount');
        if(amtInput)payload.set('amount',rawMoney(amtInput.value));
        const response=await fetch(form.action,{
            method:'POST',body:payload,credentials:'same-origin',
            headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]')?.content||''}
        });
        const data=await response.json().catch(()=>({}));
        if(!response.ok||response.redirected){
            const msg=response.status>=500?'Server belum dapat menyimpan pengeluaran. Coba lagi nanti.':
                Object.values(data.errors||{}).flat().join(' ')||data.message||
                (response.status===401||response.status===403||response.redirected?'Sesi atau izin tidak aktif. Masuk kembali sebagai admin.':'Pengeluaran belum dapat disimpan. Periksa isian.');
            if(errorBox){errorBox.textContent=msg;errorBox.hidden=false;errorBox.focus();}
            return;
        }
        document.dispatchEvent(new CustomEvent('ui-remote:changed',{detail:{url:root.dataset.refreshUrl}}));
        if(document.getElementById('expense-form')!==form)return;
        window.closeModal?.('modal-expense');
        const msg=data.expense_number?'Pengeluaran '+data.expense_number+' berhasil dicatat dan jurnalnya dikunci.':'Pengeluaran berhasil dicatat dan jurnalnya dikunci.';
        try{await refresh(msg);}catch{feedback(msg+' Daftar belum dapat diperbarui; buka kembali menu Pengeluaran.');}
    }catch{
        if(errorBox){errorBox.textContent='Koneksi terputus. Periksa daftar pengeluaran sebelum mencoba lagi.';errorBox.hidden=false;errorBox.focus();}
    }finally{
        delete form.dataset.busy;form.removeAttribute('aria-busy');
        if(submit)submit.disabled=false;
    }
});
})();
</script>