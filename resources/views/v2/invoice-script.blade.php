<script>
(()=>{
'use strict';
const root=document.getElementById('tagihan');if(!root)return;
const form=root.querySelector('#invoice-filter-form'),results=root.querySelector('#invoice-results'),error=root.querySelector('#invoice-error');
if(!form||!results||!error)return;
const defaults={invoice_direction:'receivable',invoice_status:'unpaid',invoice_search:'',invoice_start:'',invoice_end:'',invoice_per_page:'10'};
const keys=[...Object.keys(defaults),'invoice_page'];
const tabs=Array.from(root.querySelectorAll('[data-invoice-tab]'));
let applied={...defaults,...Object.fromEntries(new FormData(form)),invoice_page:results.dataset.invoicePage||'1'};
let sequence=0,controller=null,queued=null,loading=false,refreshAfter=false;
function cleanAddress(){
    const url=new URL(location.href);for(const key of keys)url.searchParams.delete(key);
    if(url.toString()!==location.href)history.replaceState(null,'',url.toString());
}
function selected(){
    for(const tab of tabs){
        const active=tab.dataset.invoiceTab===applied.invoice_direction;
        tab.setAttribute('aria-selected',String(active));tab.tabIndex=active?0:-1;
    }
    results.setAttribute('aria-labelledby','invoice-tab-'+applied.invoice_direction);
    form.dataset.filtered=Object.entries(defaults).some(([key,value])=>key!=='invoice_direction'&&String(applied[key]??value)!==value)?'1':'0';
}
async function load(filters,announce=true,reset=false){
    if(results.querySelector('dialog[open]')){queued={filters,announce,reset};return;}
    const version=++sequence;controller?.abort();controller=new AbortController();
    const signal=controller.signal,url=new URL(form.dataset.invoiceUrl,location.href);
    if(url.origin!==location.origin)return;
    for(const key of keys)if(filters[key]!==undefined)url.searchParams.set(key,String(filters[key]));
    const button=form.querySelector('button[type=submit]');if(button)button.disabled=true;
    results.setAttribute('aria-busy','true');error.hidden=true;loading=true;
    const timer=setTimeout(()=>{if(controller?.signal===signal)controller.abort();},15000);
    let message='',painted=false;
    try{
        const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal});
        if(response.redirected)throw Error('session');
        const data=await response.json();
        if(!response.ok){
            message=response.status===422?'Filter tagihan tidak valid. Periksa status, tanggal, dan jumlah baris.':
                response.status===401||response.status===403?'Sesi tidak aktif. Masuk kembali untuk memuat tagihan.':'';
            throw Error('request');
        }
        if(typeof data.html!=='string'||!data.filters||typeof data.filters!=='object'||Array.isArray(data.filters))throw Error('invalid');
        if(version!==sequence)return;
        if(results.querySelector('dialog[open]')){queued={filters,announce,reset};return;}
        results.innerHTML=data.html;applied={...defaults,...data.filters};queued=null;painted=true;
        form.elements.invoice_direction.value=applied.invoice_direction;
        if(reset)for(const [key,value]of Object.entries(applied)){
            const field=form.elements[key];if(field){field.value=String(value);field.dispatchEvent(new Event('change',{bubbles:true}));}
        }
        selected();cleanAddress();
        document.dispatchEvent(new CustomEvent('ui-invoices:render',{detail:{root:results}}));
        window.enhanceSelects?.(results);
        window.uiMotion?.staggerRows(results);
        form.dispatchEvent(new Event('ui-filter:applied',{bubbles:true}));
    }catch(cause){
        if(version!==sequence)return;
        error.textContent=message||'Tagihan belum dapat dimuat. Periksa koneksi, lalu tekan Tampilkan lagi.';
        error.hidden=false;if(announce)error.focus();
    }finally{
        clearTimeout(timer);
        if(version===sequence){
            loading=false;if(button)button.disabled=false;results.removeAttribute('aria-busy');
            const again=refreshAfter;refreshAfter=false;
            if(again&&painted)return load(applied,false);
        }
    }
}
form.addEventListener('submit',event=>{
    event.preventDefault();if(!form.reportValidity())return;
    return load({...defaults,...Object.fromEntries(new FormData(form)),invoice_page:'1'});
});
root.addEventListener('click',event=>{
    if(event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;
    const tab=event.target.closest('[data-invoice-tab]'),reset=event.target.closest('#invoice-reset'),page=event.target.closest('.pagination a[href]');
    if(tab){event.preventDefault();return load({...applied,invoice_direction:tab.dataset.invoiceTab,invoice_page:'1'});}
    if(reset){event.preventDefault();return load({...defaults,invoice_direction:applied.invoice_direction,invoice_page:'1'},true,true);}
    if(!page)return;
    const url=new URL(page.href,location.href),number=url.searchParams.get('invoice_page');
    if(url.origin!==location.origin||!/^\d+$/.test(number||'')||Number(number)<1)return;
    event.preventDefault();return load({...applied,invoice_page:number});
});
root.addEventListener('keydown',event=>{
    const tab=event.target.closest('[data-invoice-tab]');if(!tab)return;
    if(event.key===' '){event.preventDefault();tab.click();return;}
    if(!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;
    event.preventDefault();
    const index=tabs.indexOf(tab),next=event.key==='Home'?tabs[0]:event.key==='End'?tabs[tabs.length-1]:tabs[(index+(event.key==='ArrowRight'?1:-1)+tabs.length)%tabs.length];
    next.focus();next.click();
});
document.addEventListener('ui-live:patched',()=>{
    if(loading){refreshAfter=true;return;}
    return load(applied,false);
});
document.addEventListener('close',event=>{
    if(!queued||event.target.closest('#invoice-results')!==results||results.querySelector('dialog[open]'))return;
    const next=queued;queued=null;return load(next.filters,next.announce,next.reset);
},true);
document.addEventListener('click',event=>{
    const tile=event.target.closest('[data-monitoring-task="tagihan"] [data-ui-filter]');
    if(!tile)return;
    const direction=tile.dataset.uiFilter?.split(':')[1];
    if(['receivable','payable'].includes(direction))return load({...applied,invoice_direction:direction,invoice_status:'unpaid',invoice_page:'1'});
});
selected();cleanAddress();
})();
</script>
