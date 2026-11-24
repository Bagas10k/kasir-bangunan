<script>
(()=>{
const root=document.getElementById('pos-dashboard');if(!root)return;
const buttons=[...root.querySelectorAll('.pd-seg button')],sub=document.getElementById('pd-sub');
const form=document.getElementById('pd-custom-form'),from=document.getElementById('pd-from'),to=document.getElementById('pd-to'),status=document.getElementById('pd-custom-status'),trigger=document.getElementById('pd-custom-trigger');
const data=JSON.parse(document.getElementById('pos-monitor-precomputed').textContent||'{}');
let version=0,controller=null,selected='7d',appliedRange=null;
const shortDate=value=>{const date=new Date(value+'T00:00:00');return Number.isNaN(date.getTime())?value:new Intl.DateTimeFormat('id-ID',{day:'numeric',month:'short'}).format(date);};
const rangeLabel=range=>range?shortDate(range.from)+' – '+shortDate(range.to):'';
function paintTrigger(){
    if(!trigger)return;
    const active=selected==='custom'&&appliedRange;
    trigger.textContent=active?rangeLabel(appliedRange):'Tanggal khusus';
    trigger.setAttribute('aria-label',active?'Tanggal khusus '+rangeLabel(appliedRange):'Pilih tanggal khusus');
}
function chartIn(pane){
    if(!pane||typeof matchMedia!=='function'||matchMedia('(prefers-reduced-motion: reduce)').matches)return;
    const figure=pane.querySelector?.('.pd-chart');
    if(!figure||typeof figure.getClientRects!=='function'||!figure.getClientRects().length||typeof figure.animate!=='function'||typeof getComputedStyle!=='function')return;
    const style=getComputedStyle(document.documentElement);
    const duration=parseFloat(style.getPropertyValue('--ui-morph-duration'))||300;
    const easing=style.getPropertyValue('--ui-morph-easing').trim()||'ease-in-out';
    figure.animate([{opacity:0,transform:'translateY(8px) scale(.99)'},{opacity:1,transform:'none'}],{duration,easing});
}
function show(period,previous=globalThis.uiMotion?.dataSnapshot(root),animate=true){
    const before=root.querySelector('.pd-pane:not([hidden])')?.dataset.pane;
    selected=period;
    for(const button of buttons)button.setAttribute('aria-pressed',String(button.dataset.period===period));
    for(const pane of root.querySelectorAll('.pd-pane'))pane.hidden=pane.dataset.pane!==period;
    globalThis.uiMotion?.dataChange(root,previous);
    const pane=root.querySelector('.pd-pane:not([hidden])');
    if(animate&&before!==period)chartIn(pane);
    if(sub&&data[period])sub.textContent=data[period].sub_label;
    paintTrigger();
    try{localStorage.setItem('pd-period',period);}catch{}
}
async function load(range={from:from.value,to:to.value},force=false){
    if(!force&&appliedRange&&range.from===appliedRange.from&&range.to===appliedRange.to&&root.querySelector('.pd-pane[data-pane="custom"]')){
        show('custom');status.textContent='Rentang diterapkan.';return;
    }
    const current=++version;controller?.abort();controller=new AbortController();
    form.setAttribute('aria-busy','true');status.textContent='Memuat rentang khusus…';
    const url=new URL(form.action,location.href);url.searchParams.set('from',range.from);url.searchParams.set('to',range.to);
    try{
        const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});
        if(!response.ok||response.redirected)throw Error('read');
        const result=await response.json();if(current!==version)return;
        const parsed=new DOMParser().parseFromString(result.html,'text/html');
        const pane=parsed.querySelector('.pd-pane[data-pane="custom"]');if(!pane)throw Error('shape');
        const previous=globalThis.uiMotion?.dataSnapshot(root);
        root.querySelector('.pd-pane[data-pane="custom"]')?.remove();
        root.querySelector('.pd-body').append(document.importNode(pane,true));
        appliedRange={...range};data.custom={sub_label:range.from+' – '+range.to};show('custom',previous);
        status.textContent='Rentang diterapkan.';
        try{localStorage.setItem('pd-custom-range',JSON.stringify(range));}catch{}
    }catch(error){
        if(current!==version||error.name==='AbortError')return;
        status.textContent='Rentang gagal dimuat. Periksa tanggal, urutan, dan batas 366 hari. Hasil sebelumnya tetap tersedia.';
    }finally{if(current===version)form.removeAttribute('aria-busy');}
}
form.addEventListener('submit',event=>{event.preventDefault();load();});
for(const button of buttons)button.addEventListener('click',()=>{
    version++;controller?.abort();form.removeAttribute('aria-busy');status.textContent='';show(button.dataset.period);
});
document.addEventListener('ui-live:patched',()=>{if(selected==='custom'&&appliedRange)load(appliedRange,true);});
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
