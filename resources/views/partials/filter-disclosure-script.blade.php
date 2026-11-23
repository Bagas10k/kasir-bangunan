<script>
(()=>{
'use strict';
if(window.uiTableFilters)return;

const SOURCES='[data-ui-toolbar],.master-toolbar,.ledger-filter,.receivables-filter,.ritase-history-toolbar,.table-filters,.sales-filter-grid,.catalog-filter-row,.report-toolbar,#laporan>form.ui-toolbar';
const CHILD_PICKERS='.select-options,.app-calendar,.ui-pick-list';
const FOCUSABLE='input:not([type=hidden]),select,button,a[href],summary,[tabindex]';
let serial=0,active=null;
const instances=new WeakMap();
const element=(tag,className,text)=>{
    const node=document.createElement(tag);
    if(className)node.className=className;
    if(text)node.textContent=text;
    return node;
};
const childPicker=target=>!!target?.closest?.(CHILD_PICKERS);
const visible=node=>!node.hidden&&!node.disabled&&node.tabIndex>=0&&node.getClientRects().length>0;

function position(instance){
    if(!instance.open)return;
    const {trigger,panel}=instance;
    if(!trigger.getClientRects().length){close(instance);return;}
    const rect=trigger.getBoundingClientRect(),width=Math.min(384,window.innerWidth-24);
    const below=Math.max(0,window.innerHeight-rect.bottom-20),above=Math.max(0,rect.top-20);
    panel.style.width=width+'px';
    panel.style.maxHeight=Math.max(below,above)+'px';
    const upwards=panel.offsetHeight>below&&above>below;
    panel.style.maxHeight=(upwards?above:below)+'px';
    const left=Math.max(12,Math.min(rect.right-width,window.innerWidth-width-12));
    const top=upwards?Math.max(12,rect.top-panel.offsetHeight-8):rect.bottom+8;
    panel.style.left='0px';panel.style.top='0px';
    // Fallback tanpa Popover API: ancestor ber-transform dapat mengubah origin fixed.
    const origin=panel.showPopover?{left:0,top:0}:panel.getBoundingClientRect();
    panel.style.left=(left-origin.left)+'px';panel.style.top=(top-origin.top)+'px';
    panel.style.transformOrigin=upwards?'bottom right':'top right';
}

function close(instance,restore=false){
    if(!instance?.open)return;
    const focused=document.activeElement;
    for(const control of instance.body.querySelectorAll('[aria-controls][aria-expanded=true]')){
        const child=document.getElementById(control.getAttribute('aria-controls'));
        if(childPicker(child)&&!child.hidden)child.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
    }
    instance.open=false;
    if(instance.panel.hidePopover&&instance.panel.matches(':popover-open'))instance.panel.hidePopover();
    instance.panel.hidden=true;
    instance.trigger.setAttribute('aria-expanded','false');
    if(active===instance)active=null;
    if(instance.trigger.isConnected){
        if(restore||instance.source.contains(focused)||childPicker(focused))instance.trigger.focus({preventScroll:true});
        else if(focused?.isConnected&&focused!==document.activeElement)focused.focus({preventScroll:true});
    }
}

function open(instance,focus=true){
    if(instance.open){close(instance,true);return;}
    if(active)close(active);
    instance.open=true;active=instance;
    instance.panel.hidden=false;
    if(instance.panel.showPopover)instance.panel.showPopover();
    instance.trigger.setAttribute('aria-expanded','true');
    position(instance);
    if(focus){
        const field=Array.from(instance.body.querySelectorAll(FOCUSABLE)).find(visible);
        (field||instance.panel).focus({preventScroll:true});
    }
}

function update(instance){
    const selected=instance.body.querySelector('[data-ui-chip][aria-pressed=true]');
    const filters=new Set();
    if(selected&&selected.dataset.uiChip!=='all')filters.add('status');
    for(const field of instance.body.querySelectorAll('select,input[type=date],input[type=checkbox],input[type=radio]')){
        if(field.disabled||field.matches('[type=checkbox],[type=radio]')&&!field.checked)continue;
        const name=field.name||field.id,value=String(field.value||'');
        if(!value||value==='all'||/per.?page|page.?size/.test(name))continue;
        if(field.tagName==='SELECT'&&value===field.options[0]?.value)continue;
        if(field.type==='date'){
            const today=instance.source.closest('[data-history-today]')?.dataset.historyToday;
            if(today===value)continue;
            filters.add('date');
        }else filters.add(name);
    }
    instance.count.hidden=!filters.size;
    instance.count.textContent=String(filters.size);
    instance.trigger.dataset.hasFilters=String(filters.size>0);
    instance.trigger.setAttribute('aria-label',filters.size?'Filter tabel, '+filters.size+' pilihan':'Filter tabel');
    instance.current.textContent=selected&&selected.dataset.uiChip!=='all'?selected.textContent.trim():'';
    instance.current.hidden=!instance.current.textContent;
    if(instance.open)position(instance);
}

function enhance(source){
    if(instances.has(source)){update(instances.get(source));return;}
    if(source.closest('[data-table-filter-panel],template'))return;
    const isChips=source.hasAttribute('data-ui-toolbar');
    const chips=isChips?source.querySelector(':scope>.ui-chips'):null;
    if(isChips&&!chips)return;
    const children=Array.from(source.children);
    const search=children.find(node=>node.matches('.ui-search,.table-search,.master-control-search')||node.querySelector('input[type=search],input[name=search],input[name=q]'));
    const parts=isChips?[chips]:children.filter(node=>node!==search&&!node.matches('input[type=hidden],script,style'));
    if(!parts.length)return;

    const id='table-filter-'+(++serial),panel=element('div','ui-filter-panel');
    panel.id=id;panel.hidden=true;panel.tabIndex=-1;
    panel.setAttribute('data-table-filter-panel','');
    panel.setAttribute('role','region');panel.setAttribute('aria-labelledby',id+'-title');
    if(panel.showPopover)panel.setAttribute('popover','manual');
    const owner=element('div','ui-filter-owner');
    const trigger=element('button','ui-filter-trigger');trigger.type='button';
    trigger.setAttribute('aria-expanded','false');trigger.setAttribute('aria-controls',id);
    const icon=element('span','ui-filter-icon');icon.setAttribute('aria-hidden','true');
    const count=element('span','ui-filter-count');count.hidden=true;count.setAttribute('aria-hidden','true');
    const current=element('span','ui-filter-current');current.hidden=true;
    trigger.append(icon,element('span','','Filter'),count);
    owner.append(trigger,current,panel);
    if(isChips)chips.before(owner);else source.append(owner);

    const heading=element('div','ui-filter-heading'),titles=element('div','ui-filter-titles');
    const title=element('strong','','Filter tabel');title.id=id+'-title';
    titles.append(title,element('p','','Pilih data yang ingin ditampilkan.'));
    const dismiss=element('button','ui-filter-close','×');dismiss.type='button';dismiss.setAttribute('aria-label','Tutup filter');
    heading.append(titles,dismiss);
    const body=element('div','ui-filter-fields'),footer=element('div','ui-filter-footer');
    const instance={source,trigger,panel,body,count,current,open:false};
    instances.set(source,instance);
    for(const part of parts){
        // Buka lapisan lama, bukan menyalin kontrol: nilai, form owner dan listener tetap sama.
        if(part.matches('details.history-options')){
            const fields=part.querySelector('.history-options-fields');
            if(fields)body.append(...Array.from(fields.children));
            part.remove();
        }else if(part.matches('.sales-filter-actions,.master-toolbar-actions,.ledger-filter-actions,.receivables-filter-actions,.ui-toolbar-actions,.table-reset,#catalog-reset')){
            footer.append(part);
        }else body.append(part);
    }
    if(chips){
        const label=element('p','ui-filter-label',chips.getAttribute('aria-label')||'Status');
        body.prepend(label);
        const reset=element('button','ui-filter-reset','Atur ulang');reset.type='button';
        reset.addEventListener('click',()=>chips.querySelector('[data-ui-chip=all]')?.click());
        footer.append(reset);
    }
    if(!footer.querySelector('button[type=submit],button:not([type]),.report-apply')&&!body.querySelector('button[type=submit],button:not([type]),.report-apply')){
        const done=element('button','ui-filter-done','Selesai');done.type='button';
        done.addEventListener('click',()=>close(instance,true));footer.append(done);
    }
    panel.append(heading,body);
    if(footer.children.length)panel.append(footer);
    source.setAttribute('data-filter-ready','');
    if(search){
        search.setAttribute('data-filter-search','');
        if(source.tagName==='FORM'&&source.querySelector('button[type=submit]')){
            const submit=element('button','ui-filter-search-submit','Cari');submit.type='submit';
            submit.setAttribute('aria-label','Terapkan pencarian');
            search.after(submit);
        }
    }
    trigger.addEventListener('click',()=>open(instance));
    dismiss.addEventListener('click',()=>close(instance,true));
    source.addEventListener('input',()=>update(instance));
    source.addEventListener('change',()=>update(instance));
    source.addEventListener('ui-refresh',()=>update(instance));
    source.addEventListener('click',()=>queueMicrotask(()=>update(instance)));
    source.addEventListener('reset',()=>queueMicrotask(()=>update(instance)));
    source.addEventListener('ui-filter:applied',()=>{update(instance);close(instance);});
    source.addEventListener('invalid',()=>{if(!instance.open)open(instance,false);},true);
    new MutationObserver(()=>update(instance)).observe(body,{subtree:true,attributes:true,attributeFilter:['aria-pressed','selected','disabled']});
    update(instance);
}

function scan(root){
    if(root.matches?.(SOURCES))enhance(root);
    root.querySelectorAll?.(SOURCES).forEach(enhance);
}
function start(){
    scan(document);
    new MutationObserver(records=>{
        if(active&&!active.source.isConnected)close(active);
        for(const record of records)for(const node of record.addedNodes){
            if(node.nodeType===1&&!node.closest('[data-table-filter-panel]'))scan(node);
        }
    }).observe(document.body,{subtree:true,childList:true});
    new MutationObserver(()=>{if(active)close(active);}).observe(document.body,{attributes:true,attributeFilter:['data-active-task']});
    window.uiLive?.onPatch(scan);
}

document.addEventListener('pointerdown',event=>{
    if(active&&!active.source.contains(event.target)&&!childPicker(event.target))close(active);
});
document.addEventListener('focusin',event=>{
    if(active&&!active.source.contains(event.target)&&!childPicker(event.target))close(active);
});
document.addEventListener('keydown',event=>{
    if(!active||event.key!=='Escape'||event.defaultPrevented||childPicker(event.target))return;
    if(document.querySelector('.app-calendar:not([hidden]),.select-options:not([hidden])'))return;
    event.preventDefault();close(active,true);
});
window.addEventListener('resize',()=>{if(active)position(active);});
window.addEventListener('scroll',event=>{
    if(active&&!active.panel.contains(event.target)&&!childPicker(event.target))position(active);
},true);
window.uiTableFilters={scan,close:()=>{if(active)close(active);}};
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
})();
</script>
