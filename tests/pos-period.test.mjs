import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const source=readFileSync(new URL('../resources/views/v2/pos-period-script.blade.php',import.meta.url),'utf8').replace(/<\/?script>/g,'');
const flush=async()=>{for(let n=0;n<10;n++)await Promise.resolve();};
function setup(withNumbers=false){
    const events={},formEvents={},attrs=new Map(),panes=[{dataset:{pane:'7d'},hidden:false}],requests=[],storage=new Map([['history-date','2025-01-01']]);
    const buttons=['today','7d','30d'].map(period=>({dataset:{period},setAttribute(){},addEventListener(type,fn){this.click=fn;}}));
    const tweens=[];
    if(withNumbers){
        panes[0].querySelectorAll=selector=>selector==='[data-number-text]'?[]:[{dataset:{numberKey:'revenue'},textContent:'Rp 100'}];
        panes.push({dataset:{pane:'today'},hidden:true,querySelectorAll:selector=>selector==='[data-number-text]'?[]:[{dataset:{numberKey:'revenue'},textContent:'Rp 30'}]});
    }
    const body={append:p=>panes.push(p)},root={querySelectorAll:s=>s==='.pd-seg button'?buttons:panes,querySelector:s=>{
        if(s==='.pd-body')return body;
        if(s==='.pd-pane:not([hidden])')return panes.find(p=>!p.hidden);
        return panes.find(p=>s.includes('"'+p.dataset.pane+'"'));
    }};
    const form={action:'http://example.test/v2/sales/dashboard',setAttribute:(k,v)=>attrs.set(k,v),removeAttribute:k=>attrs.delete(k),addEventListener:(type,fn)=>formEvents[type]=fn};
    const nodes={'pos-dashboard':root,'pd-custom-form':form,'pd-from':{value:'2025-01-10'},'pd-to':{value:'2025-01-11'},'pd-custom-status':{textContent:''},'pd-sub':{},'pos-monitor-precomputed':{textContent:JSON.stringify({'7d':{sub_label:'7 hari'}})}};
    let respond=async()=>({ok:true,json:async()=>({html:'safe server fragment'})});
    vm.runInNewContext(source,{uiMotion:withNumbers?{
        dataSnapshot:root=>root.querySelector('.pd-pane:not([hidden])'),
        dataChange:(root,previous)=>tweens.push({target:root.querySelector('.pd-pane:not([hidden])').querySelectorAll('[data-number-key]')[0].textContent,from:previous?.querySelectorAll('[data-number-key]')[0].textContent})
    }:undefined,
        document:{getElementById:id=>nodes[id],addEventListener:(type,fn)=>events[type]=fn,importNode:node=>node},
        localStorage:{getItem:k=>storage.get(k)||null,setItem:(k,v)=>storage.set(k,v),removeItem:k=>storage.delete(k)},URL,AbortController,
        location:{href:'http://example.test/#kasir'},fetch:(url,options)=>{requests.push({url,options});return respond();},
        DOMParser:class{parseFromString(){const pane={dataset:{pane:'custom'},hidden:true,remove(){panes.splice(panes.indexOf(pane),1);}};return {querySelector:()=>pane};}}});
    return {nodes,panes,requests,buttons,storage,events,tweens,respond:fn=>respond=fn,submit:()=>formEvents.submit({preventDefault(){}})};
}
test('custom period is a read and preserves unrelated menu dates',async()=>{
    const app=setup();app.submit();await flush();
    assert.equal(app.requests[0].url.searchParams.get('from'),'2025-01-10');
    assert.equal(app.panes.find(p=>p.dataset.pane==='custom').hidden,false);
    assert.equal(app.storage.get('history-date'),'2025-01-01');
    app.nodes['pd-from'].value='2024-01-01';
    app.events['ui-live:patched']();await flush();
    assert.equal(app.requests.at(-1).url.searchParams.get('from'),'2025-01-10');
    assert.equal(app.nodes['pd-from'].value,'2024-01-01');
    app.respond(async()=>({ok:false,status:422}));app.submit();await flush();
    assert.equal(app.panes.find(p=>p.dataset.pane==='custom').hidden,false);
    assert.match(app.nodes['pd-custom-status'].textContent,/Hasil sebelumnya/);
});
test('a late custom response cannot replace a newly selected quick period',async()=>{
    const app=setup();let finish;
    app.respond(()=>new Promise(resolve=>finish=resolve));app.submit();
    app.buttons[1].click();
    finish({ok:true,json:async()=>({html:'late'})});await flush();
    assert.equal(app.panes.length,1);assert.equal(app.panes[0].hidden,false);
    assert.equal(app.storage.get('pd-period'),'7d');
});
test('period changes tween the same KPI from the previous period instead of counting from zero',()=>{
    const app=setup(true);
    app.buttons[0].click();
    assert.equal(app.tweens[0].from,'Rp 100');
    assert.equal(app.tweens[0].target,'Rp 30');
    assert.equal(app.panes.find(p=>p.dataset.pane==='today').hidden,false);
});
