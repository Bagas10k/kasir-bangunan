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
        const printAction=content.querySelector('[data-sale-actions]');
        if(printAction&&actions)actions.append(printAction);
        document.dispatchEvent(new CustomEvent('ui-invoices:render',{detail:{root:content}}));
        window.enhanceSelects?.(content);
        content.querySelector('[data-sale-detail-heading]')?.focus();
    }catch{
        if(version!==sequence||!dialog.open)return;
        content.textContent='Detail belum dapat dimuat. Periksa koneksi/sesi, lalu buka kembali nama pembeli.';
    }
});
dialog.addEventListener('close',()=>{sequence++;controller?.abort();content.textContent='';actions?.replaceChildren();});
dialog.addEventListener('cancel',event=>{if(dialog.querySelector('[data-busy="1"]'))event.preventDefault();});
})();
</script>
