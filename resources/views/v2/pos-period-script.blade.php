<script>
(()=>{
const root=document.getElementById('pos-dashboard');if(!root)return;
const buttons=[...root.querySelectorAll('.pd-seg button')],sub=document.getElementById('pd-sub');
const form=document.getElementById('pd-custom-form'),from=document.getElementById('pd-from'),to=document.getElementById('pd-to'),status=document.getElementById('pd-custom-status');
const data=JSON.parse(document.getElementById('pos-monitor-precomputed').textContent||'{}');
let version=0,controller=null,selected='7d',appliedRange=null;
function numberSnapshot(){
    if(!globalThis.uiNumbers)return new Map();
    const pane=root.querySelector('.pd-pane:not([hidden])');
    return new Map([...pane?.querySelectorAll('[data-number-key],[data-number-text]')||[]].map(el=>[el.dataset.numberKey??'text:'+el.dataset.numberText,globalThis.uiNumbers.get(el)]));
}
function show(period,previous=numberSnapshot()){
    selected=period;
    for(const button of buttons)button.setAttribute('aria-pressed',String(button.dataset.period===period));
    for(const pane of root.querySelectorAll('.pd-pane'))pane.hidden=pane.dataset.pane!==period;
    if(globalThis.uiNumbers){
        const pane=root.querySelector('.pd-pane[data-pane="'+period+'"]');
        for(const el of pane?.querySelectorAll('[data-number-key]')||[]){
            globalThis.uiNumbers.set(el,globalThis.uiNumbers.get(el),{from:previous.get(el.dataset.numberKey)});
        }
        for(const el of pane?.querySelectorAll('[data-number-text]')||[]){
            globalThis.uiNumbers.text(el,globalThis.uiNumbers.get(el),{from:previous.get('text:'+el.dataset.numberText)});
        }
    }
    if(sub&&data[period])sub.textContent=data[period].sub_label;
    try{localStorage.setItem('pd-period',period);}catch{}
}
async function load(range={from:from.value,to:to.value}){
    const current=++version;controller?.abort();controller=new AbortController();
    form.setAttribute('aria-busy','true');status.textContent='Memuat rentang khusus…';
    const url=new URL(form.action,location.href);url.searchParams.set('from',range.from);url.searchParams.set('to',range.to);
    try{
        const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});
        if(!response.ok||response.redirected)throw Error('read');
        const result=await response.json();if(current!==version)return;
        const parsed=new DOMParser().parseFromString(result.html,'text/html');
        const pane=parsed.querySelector('.pd-pane[data-pane="custom"]');if(!pane)throw Error('shape');
        const previous=numberSnapshot();
        root.querySelector('.pd-pane[data-pane="custom"]')?.remove();
        root.querySelector('.pd-body').append(document.importNode(pane,true));
        appliedRange={...range};data.custom={sub_label:range.from+' – '+range.to};show('custom',previous);
        status.textContent='Rentang diterapkan. Stok tetap kondisi saat ini.';
        try{localStorage.setItem('pd-custom-range',JSON.stringify(range));}catch{}
    }catch(error){
        if(current!==version||error.name==='AbortError')return;
        status.textContent='Rentang gagal dimuat. Pastikan tanggal lengkap, tidak terbalik, tidak di masa depan, dan maksimal 366 hari. Hasil sebelumnya tetap tersedia.';
    }finally{if(current===version)form.removeAttribute('aria-busy');}
}
form.addEventListener('submit',event=>{event.preventDefault();load();});
for(const button of buttons)button.addEventListener('click',()=>{
    version++;controller?.abort();form.removeAttribute('aria-busy');status.textContent='';show(button.dataset.period);
});
document.addEventListener('ui-live:patched',()=>{if(selected==='custom'&&appliedRange)load(appliedRange);});
try{
    const range=JSON.parse(localStorage.getItem('pd-custom-range')||'null');
    if(range&&typeof range.from==='string'&&typeof range.to==='string'){from.value=range.from;to.value=range.to;}
    const saved=localStorage.getItem('pd-period');
    if(saved==='custom'&&from.value&&to.value)load();
    else if(saved&&data[saved])show(saved);
    localStorage.removeItem('pd-folded');
}catch{}
})();
</script>
