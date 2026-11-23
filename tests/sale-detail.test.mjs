import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source=readFileSync(new URL('../resources/views/v2/sale-detail-script.blade.php',import.meta.url),'utf8').replace(/^<script[^>]*>\s*|\s*<\/script>\s*$/g,'');
function setup(){
    const printAction={tag:'print-action'};
    const actions={children:[],replaceChildren(){this.children=[];},append(node){this.children.push(node);}};
    const content={innerHTML:'',textContent:'',querySelector:s=>s==='[data-sale-actions]'?printAction:{focus(){}}};
    const dialog={dataset:{url:'http://example.test/v2/sales/__ID__/detail'},open:false,listeners:{},querySelector:s=>s==='[data-sale-detail-content]'?content:s==='[data-sale-footer-actions]'?actions:null,showModal(){this.open=true;},close(){this.open=false;this.listeners.close?.();},addEventListener(type,fn){this.listeners[type]=fn;}};
    const document={listeners:{},events:[],getElementById:()=>dialog,addEventListener(type,fn){this.listeners[type]=fn;},dispatchEvent(e){this.events.push(e);}};
    const requests=[];let respond=async()=>({ok:true,json:async()=>({sale_id:1,html:'sale one'})});
    vm.runInNewContext(source,{document,window:{enhanceSelects(){}},location:{href:'http://example.test/#kasir'},URL,AbortController,
        CustomEvent:class{constructor(type,options){this.type=type;Object.assign(this,options);}},
        fetch:(url,options)=>{requests.push({url:String(url),options});return respond(String(url));}
    });
    return {dialog,content,document,requests,actions,printAction,respond:fn=>respond=fn,open:id=>document.listeners.click({preventDefault(){},target:{closest:s=>s==='[data-sale-detail]'?{dataset:{saleDetail:String(id)},focus(){}}:null}})};
}
test('name opens a read-only detail fetch then binds the existing payment form',async()=>{
    const app=setup();await app.open(1);
    assert.equal(app.dialog.open,true);assert.equal(app.content.innerHTML,'sale one');
    assert.equal(app.requests[0].url,'http://example.test/v2/sales/1/detail');
    assert.equal(app.requests[0].options.cache,'no-store');
    assert.equal(app.document.events[0].type,'ui-invoices:render');
});
test('late result from another sale and a closed dialog cannot paint',async()=>{
    const app=setup();let release;
    app.respond(()=>new Promise(resolve=>release=resolve));const first=app.open(1);
    app.respond(async()=>({ok:true,json:async()=>({sale_id:2,html:'sale two'})}));await app.open(2);
    release({ok:true,json:async()=>({sale_id:1,html:'stale'})});await first;
    assert.equal(app.content.innerHTML,'sale two');
    app.respond(()=>new Promise(resolve=>release=resolve));const third=app.open(3);app.dialog.close();
    release({ok:true,json:async()=>({sale_id:3,html:'closed'})});await third;
    assert.notEqual(app.content.innerHTML,'closed');
});
test('failed detail does not insert server internals and can be retried',async()=>{
    const app=setup();app.respond(async()=>({ok:false,status:500,json:async()=>({message:'private trace'})}));
    await app.open(1);assert.doesNotMatch(app.content.textContent,/private trace/);
    app.respond(async()=>({ok:true,json:async()=>({sale_id:1,html:'retry'})}));
    await app.open(1);assert.equal(app.content.innerHTML,'retry');
});
test('print action belongs to the current detail footer and is cleared on close or a failed next request',async()=>{
    const app=setup();await app.open(1);
    assert.equal(app.actions.children[0],app.printAction);
    app.dialog.close();assert.equal(app.actions.children.length,0);
    await app.open(1);
    app.respond(async()=>({ok:false,status:500}));
    await app.open(2);assert.equal(app.actions.children.length,0);
});
