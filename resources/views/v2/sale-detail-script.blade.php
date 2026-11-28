<script>
(()=>{
'use strict';
const dialog=document.getElementById('input-popup-sale-detail');if(!dialog)return;
const content=dialog.querySelector('[data-sale-detail-content]');
const actions=dialog.querySelector('[data-sale-footer-actions]');
let sequence=0,controller=null;
document.addEventListener('click',async event=>{
    const trigger=event.target.closest('[data-sale-detail]');if(!trigger)return;
    event.preventDefault();
    if(dialog.querySelector('[data-busy="1"]'))return;
    const id=trigger.dataset.saleDetail;
    if(!/^[1-9]\d*$/.test(id))return;
    const version=++sequence;controller?.abort();controller=new AbortController();
    dialog._openerTrigger=trigger;content.textContent='Memuat detail penjualan…';
    actions?.replaceChildren();
    if(!dialog.open)dialog.showModal();
    try{
        const url=new URL(dialog.dataset.url.replace('__ID__',id),location.href);
        const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});
        if(!response.ok||response.redirected)throw Error('request');
        const data=await response.json();
        if(String(data.sale_id)!==id||typeof data.html!=='string')throw Error('invalid');
        if(version!==sequence||!dialog.open)return;
        content.innerHTML=data.html;
        const retKey=content.querySelector('#sale-return-form input[name=idempotency_key]');
        if(retKey&&!retKey.value)retKey.value='srt-'+Date.now()+'-'+Math.random().toString(16).slice(2);
        const printAction=content.querySelector('[data-sale-actions]');
        if(printAction&&actions)actions.append(printAction);
        document.dispatchEvent(new CustomEvent('ui-invoices:render',{detail:{root:content}}));
        window.enhanceSelects?.(content);
        content.querySelector('[data-sale-detail-heading]')?.focus();
    }catch{
        if(version!==sequence||!dialog.open)return;
        content.textContent='Detail belum dapat dimuat. Periksa koneksi atau sesi, lalu pilih Detail lagi.';
    }
});
dialog.addEventListener('click',event=>{
    if(!event.target.closest('[data-open-sale-return]'))return;
    const section=content.querySelector('#sale-return-box');
    if(!section)return;
    section.open=true;
    section.querySelector('summary')?.focus();
});
dialog.addEventListener('close',()=>{sequence++;controller?.abort();content.textContent='';actions?.replaceChildren();});
dialog.addEventListener('cancel',event=>{if(dialog.querySelector('[data-busy="1"]'))event.preventDefault();});
document.addEventListener('change',event=>{
    const sel=event.target.closest?.('[data-sale-return-method]');if(!sel)return;
    const form=sel.closest('#sale-return-form');if(!form)return;
    const bankWrap=form.querySelector('[data-sale-return-bank]');const bankSel=bankWrap?.querySelector('select');
    const isBank=sel.value==='transfer';
    if(bankWrap){bankWrap.hidden=!isBank;}
    if(bankSel){bankSel.disabled=!isBank;}
});
const parseQty=value=>{
    const raw=String(value??'').trim();
    if(!raw)return {empty:true,raw:'',value:0};
    const normalized=raw.replace(/\.(?=\d{3}\b)/g,'').replace(',', '.');
    if(!/^\d+(\.\d+)?$/.test(normalized))return {empty:false,invalid:true,raw,value:NaN};
    const num=Number(normalized);
    return {empty:false,invalid:!Number.isFinite(num)||num<0,raw:normalized,value:num};
};
const showReturnError=(form,msg,focusTarget)=>{
    const err=form.querySelector('[data-error]');
    if(err){err.textContent=msg;err.hidden=false;}
    if(focusTarget&&typeof focusTarget.focus==='function')focusTarget.focus();
    else err?.focus?.();
};
document.addEventListener('submit',async event=>{
    const form=event.target.closest?.('#sale-return-form');if(!form)return;
    event.preventDefault();
    if(form.dataset.busy)return;
    const err=form.querySelector('[data-error]');
    if(err){err.hidden=true;err.textContent='';}

    const dateInput=form.elements?.return_date;
    const returnDate=String(dateInput?.value||'').trim();
    if(!/^\d{4}-\d{2}-\d{2}$/.test(returnDate)){showReturnError(form,'Tanggal retur wajib diisi.',dateInput?._dateTrigger||dateInput);return;}
    if(dateInput?.min&&returnDate<dateInput.min){showReturnError(form,'Tanggal retur tidak boleh sebelum tanggal nota ('+dateInput.min+').',dateInput?._dateTrigger||dateInput);return;}
    if(dateInput?.max&&returnDate>dateInput.max){showReturnError(form,'Tanggal retur tidak boleh melewati hari ini ('+dateInput.max+').',dateInput?._dateTrigger||dateInput);return;}

    const items=[];
    let firstQty=null;
    for(const input of form.querySelectorAll('[data-return-sale-item]')){
        if(!input.disabled&&!firstQty)firstQty=input;
        const parsed=parseQty(input.value);
        if(parsed.empty)continue;
        if(parsed.invalid||parsed.value<=0){showReturnError(form,'Jumlah retur harus berupa angka lebih dari nol.',input);return;}
        items.push({sale_item_id:Number(input.dataset.returnSaleItem),quantity:parsed.raw});
    }
    if(!items.length){showReturnError(form,'Isi jumlah retur minimal untuk satu barang.',firstQty);return;}

    const reasonInput=form.elements?.reason;
    const reason=String(reasonInput?.value||'').trim();
    if(reason.length<5){showReturnError(form,'Isi alasan retur minimal 5 karakter.',reasonInput);return;}

    form.dataset.busy='1';form.setAttribute('aria-busy','true');
    const btn=form.querySelector('button[type=submit]');if(btn)btn.disabled=true;
    const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
    const methodSel=form.querySelector('[data-sale-return-method]');
    const bankSel=form.querySelector('[data-sale-return-bank] select');
    const keyInput=form.querySelector('input[name=idempotency_key]');
    const keyVal=keyInput?.value||('srt-'+Date.now()+'-'+Math.random().toString(16).slice(2));
    if(keyInput&&!keyInput.value)keyInput.value=keyVal;
    try{
        const res=await fetch(form.action,{
            method:'POST',credentials:'same-origin',
            headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},
            body:JSON.stringify({
                return_date:returnDate,
                reason,
                idempotency_key:keyVal,
                refund_method:methodSel?methodSel.value:null,
                bank_account_code:(methodSel&&methodSel.value==='transfer'&&bankSel)?bankSel.value:null,
                items
            })
        });
        if(res.redirected)throw Error('Sesi berakhir. Muat ulang halaman lalu masuk kembali.');
        const data=await res.json().catch(()=>({}));
        if(!res.ok){
            const msg=Object.values(data.errors||{}).flat().join(' ')||data.message||'Gagal menyimpan retur penjualan.';
            showReturnError(form,msg);
            return;
        }
        try{await window.uiLive?.saved?.(form);}catch{}
        dialog.close();
    }catch(ex){
        showReturnError(form,ex?.message||'Gagal menghubungi server. Periksa koneksi Anda lalu coba lagi.');
    }finally{
        if(btn)btn.disabled=false;
        delete form.dataset.busy;form.removeAttribute('aria-busy');
    }
});
})();
</script>
