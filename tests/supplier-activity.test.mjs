import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const source=readFileSync(new URL('../resources/views/v2/supplier-activity-script.blade.php',import.meta.url),'utf8').replace(/<\/?script>/g,'');
const flush=async()=>{for(let i=0;i<12;i++)await Promise.resolve();};
function setup(){
    const events={},requests=[],attrs=new Map();
    const field=value=>({value,dispatchEvent(){}});
    const form={elements:{from:field('2026-11-01'),to:field('2026-11-23')},reportValidity:()=>true,addEventListener:(type,fn)=>events['form:'+type]=fn};
    const results={innerHTML:'',setAttribute:(k,v)=>attrs.set(k,v),removeAttribute:k=>attrs.delete(k),addEventListener:(type,fn)=>events['results:'+type]=fn};
    const error={hidden:true},print={hidden:true};
    const dialog={open:true,dataset:{supplierUrl:'http://example.test/v2/suppliers/0/activity',supplierPrintUrl:'http://example.test/v2/suppliers/0/statement'},
        querySelector:s=>({'[data-supplier-results]':results,'[data-supplier-error]':error,'[data-supplier-print]':print})[s],addEventListener:(type,fn)=>events['dialog:'+type]=fn};
    let respond=async()=>({ok:true,json:async()=>({html:'supplier activities',from:'2026-11-01',to:'2026-11-23'})});
    vm.runInNewContext(source,{document:{getElementById:id=>id==='insight-dialog'?dialog:form,addEventListener:(type,fn)=>events[type]=fn},
        window:{},URL,AbortController,Event,location:{href:'http://example.test/#master-supplier',origin:'http://example.test'},
        fetch:(url,options)=>{requests.push({url:String(url),options});return respond();}});
    return {events,requests,results,error,print,form,dialog,attrs,respond:fn=>respond=fn,open:id=>events['master-insight:open']({detail:{kind:'supplier',id}})};
}
test('supplier detail loads only its scoped range and print-all uses the applied range, not unsent edits',async()=>{
    const app=setup();app.open(12);await flush();
    assert.equal(new URL(app.requests[0].url).pathname,'/v2/suppliers/12/activity');
    assert.equal(app.requests[0].options.credentials,'same-origin');
    assert.equal(app.print.hidden,false);assert.equal(new URL(app.print.href).pathname,'/v2/suppliers/12/statement');
    app.form.elements.from.value='2025-01-01';
    assert.equal(new URL(app.print.href).searchParams.get('from'),'2026-11-01');
    assert.equal(new URL(app.print.href).searchParams.has('page'),false);
});
test('late responses and a closed dialog cannot paint another supplier',async()=>{
    const app=setup();let finish;
    app.respond(()=>new Promise(resolve=>finish=resolve));app.open(1);
    app.respond(async()=>({ok:true,json:async()=>({html:'second supplier',from:'2026-11-01',to:'2026-11-23'})}));app.open(2);await flush();
    finish({ok:true,json:async()=>({html:'stale supplier',from:'2026-11-01',to:'2026-11-23'})});await flush();
    assert.equal(app.results.innerHTML,'second supplier');
    app.events['dialog:close']();assert.equal(app.print.hidden,true);
});
test('failed activity reads retain dates, hide print and do not expose backend details',async()=>{
    const app=setup();app.respond(async()=>({ok:false,status:500,json:async()=>({message:'private SQL'})}));
    app.open(1);await flush();
    assert.equal(app.error.hidden,false);assert.doesNotMatch(app.error.textContent,/private SQL/);
    assert.equal(app.print.hidden,true);assert.equal(app.form.elements.from.value,'2026-11-01');
    assert.equal(app.attrs.has('aria-busy'),false);
});
test('the existing supplier name button opens its dialog without the undefined historyBlock failure',()=>{
    const blade=readFileSync(new URL('../resources/views/v2/master-insights.blade.php',import.meta.url),'utf8');
    const script=blade.match(/<script>([\s\S]*?)<\/script>/)[1],events={};
    const node=()=>({dataset:{},children:[],append(...children){this.children.push(...children);},replaceChildren(){this.children=[];},focus(){}});
    const nodes={};const dialog=node();dialog.querySelector=selector=>nodes[selector]??(nodes[selector]=node());dialog.showModal=()=>dialog.open=true;
    const data={supplier:{1:{outstanding:'Rp 0',settled:'Rp 0',awaiting:'Rp 0',invoice_count:0,products:[],history:[]}}};
    vm.runInNewContext(script,{document:{querySelector:s=>s==='#master-insights'?{textContent:JSON.stringify(data)}:dialog,
        createElement:node,addEventListener:(type,fn)=>events[type]=fn,dispatchEvent(){},activeElement:null},CustomEvent:class{}});
    events.click({preventDefault(){},target:{closest:()=>({dataset:{insight:'supplier',insightId:'1',insightName:'Supplier sintetis'}})}});
    assert.equal(dialog.open,true);
    assert.equal(nodes['[data-insight-history-block]'].hidden,true);
    assert.equal(nodes['[data-supplier-activity]'].hidden,false);
});
