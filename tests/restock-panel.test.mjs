import {test} from 'node:test';
import assert from 'node:assert/strict';
import {existsSync,readFileSync} from 'node:fs';
import vm from 'node:vm';

const path=new URL('../resources/views/v2/receipt-restock-script.blade.php',import.meta.url);
const source=existsSync(path)?readFileSync(path,'utf8').replace(/^<script>\s*|\s*<\/script>\s*$/g,''):'';
class Element {
    constructor(){this.children=[];this.listeners={};this.dataset={};this.value='';this.checked=false;this.hidden=false;this.attributes={};this.textContent='';this.classList={contains:()=>false};}
    append(...nodes){this.children.push(...nodes);}
    replaceChildren(...nodes){this.children=nodes;}
    setAttribute(k,v){this.attributes[k]=v;}
    addEventListener(type,fn){this.listeners[type]=fn;}
    add(option){this.children.push(option);}
    dispatchEvent(){}
    focus(){this.focused=true;}
    closest(){return null;}
}
const flush=async()=>{for(let n=0;n<10;n++)await Promise.resolve();};
function setup(){
    const root=new Element(),form=new Element(),supplier=new Element(),items=new Element(),dialog=new Element();
    dialog.open=true;form.elements={supplier_id:supplier};form._addRestockProduct=()=> 'added';
    supplier.options=[];
    form._hasReceiptItems=()=>false;
    form._canUseReceiptSupplier=()=>true;
    form._selectReceiptSupplier=choice=>{supplier.value=String(choice.id);return true;};
    form.closest=()=>dialog;root.dataset.url='http://example.test/v2/receipts/restock-products';
    const names=['search','search-button','results','message','scope','prev','next','page','refresh','editor','supplier-title','supplier-choice','new-name','new-notes','new-fields','supplier-save','supplier-cancel','supplier-error'];
    const nodes=Object.fromEntries(names.map(name=>[name,new Element()]));
    const chips=['critical','out','low'].map(status=>Object.assign(new Element(),{dataset:{restockStatus:status}}));
    root.querySelector=s=>nodes[s.replace('[data-restock-','').replace(']','')];
    root.querySelectorAll=()=>chips;
    const document={getElementById:id=>id==='receipt-restock'?root:id==='receipt-form'?form:items,createElement:()=>new Element(),querySelector:()=>({content:'synthetic-csrf'})};
    const requests=[];let respond=async()=>({ok:true,json:async()=>({results:[],total:0,page:1,last_page:1,counts:{critical:0,out:0,low:0}})});
    vm.runInNewContext(source,{document,location:{href:'http://example.test/'},URL,AbortController,fetch:(url,options)=>{requests.push({url,options});return respond(url);},
        MutationObserver:class{observe(){}},queueMicrotask,window:{},crypto:{randomUUID:()=>String(requests.length)},Option:class{constructor(text,value){this.textContent=text;this.value=value;}},Event:class{}});
    return {root,form,supplier,items,nodes,chips,requests,respond:fn=>respond=fn,
        click:dataset=>root.listeners.click({target:{closest:()=>({dataset,focus(){}})}})};
}
const product={id:4,name:'<img onerror=bad()> Sintetis',sku:'SYN-4',unit:'sak',stock:'0',minimum:'2',cost:'20',status:'out',suppliers:[{id:7,name:'Supplier Sintetis'}]};
const data=(rows=[product],page=1)=>({results:rows,total:21,page,last_page:2,counts:{critical:21,out:20,low:1}});
test('list loads store-wide before supplier selection and search only issues a read',async()=>{
    const app=setup();await flush();
    assert.equal(app.requests.length,1);
    assert.equal(app.requests[0].url.searchParams.has('supplier_id'),false);
    app.respond(async()=>({ok:true,json:async()=>data()}));
    app.nodes.search.value='semen';
    await app.nodes['search-button'].listeners.click();
    assert.equal(app.requests.at(-1).url.searchParams.get('q'),'semen');
    assert.equal(app.form.elements.supplier_id.value,'');
});
test('one linked supplier fills automatically, but a populated receipt cannot switch suppliers',async()=>{
    const app=setup();await flush();
    app.respond(async()=>({ok:true,json:async()=>data()}));
    await app.nodes.refresh.listeners.click();
    let added=0;app.form._addRestockProduct=()=>{added++;return 'added';};
    app.click({restockProduct:'4'});assert.equal(added,1);assert.equal(app.supplier.value,'7');
    app.supplier.value='9';app.form._canUseReceiptSupplier=()=>false;
    app.click({restockProduct:'4'});assert.equal(added,1);assert.equal(app.supplier.value,'9');
    app.form._canUseReceiptSupplier=()=>true;
    app.supplier.value='7';app.click({restockProduct:'4'});assert.equal(added,2);
    assert.equal(app.supplier.value,'7');
    assert.equal(app.nodes.results.children[0].children[0].children[0].textContent,product.name);
});
test('late results and failed reads cannot replace the latest list or expose server internals',async()=>{
    const app=setup();await flush();
    let old;app.respond(()=>new Promise(resolve=>old=resolve));
    const first=app.nodes.refresh.listeners.click();
    app.respond(async()=>({ok:true,json:async()=>data([product])}));
    await app.nodes['search-button'].listeners.click();
    old({ok:true,json:async()=>data([])});await first;
    assert.equal(app.nodes.results.children.length,1);
    app.respond(async()=>({ok:false,status:500,json:async()=>({message:'secret SQL'})}));
    await app.nodes.refresh.listeners.click();
    assert.equal(app.nodes.results.children.length,1);
    assert.doesNotMatch(app.nodes.message.textContent,/secret SQL/);
    assert.match(app.nodes.message.textContent,/Gagal/);
    let added=0;app.form._addRestockProduct=()=>{added++;return 'added';};app.supplier.value='7';
    app.click({restockProduct:'4'});assert.equal(added,0);
});
test('status and pagination preserve applied search and never filter by supplier',async()=>{
    const app=setup();await flush();
    app.supplier.value='7';
    app.respond(async()=>({ok:true,json:async()=>data()}));
    app.nodes.search.value='SYN';
    await app.nodes['search-button'].listeners.click();
    assert.equal(app.requests.at(-1).url.searchParams.has('supplier_id'),false);
    app.nodes.search.value='draft not applied';
    app.respond(async()=>({ok:true,json:async()=>data([],2)}));
    await app.nodes.next.listeners.click();
    assert.equal(app.requests.at(-1).url.searchParams.get('q'),'SYN');
    assert.equal(app.requests.at(-1).url.searchParams.get('page'),'2');
    await app.click({restockStatus:'out'});
    assert.equal(app.requests.at(-1).url.searchParams.get('status'),'out');
    assert.equal(app.requests.at(-1).url.searchParams.get('page'),'1');
    assert.equal(app.nodes.search.value,'draft not applied');
    assert.equal(app.supplier.value,'7');
});
test('multiple suppliers require a choice, and cancelling does not mutate the receipt',async()=>{
    const app=setup();await flush();
    app.respond(async()=>({ok:true,json:async()=>data([{...product,suppliers:[{id:7,name:'Supplier A'},{id:8,name:'Supplier B'}]}])}));
    await app.nodes.refresh.listeners.click();
    let added=0;app.form._addRestockProduct=()=>{added++;return 'added';};
    app.click({restockProduct:'4'});
    assert.equal(added,0);assert.equal(app.nodes.editor.hidden,false);
    app.nodes['supplier-cancel'].listeners.click();
    assert.equal(app.supplier.value,'');
    app.click({restockProduct:'4'});app.nodes['supplier-choice'].value='8';
    await app.nodes['supplier-save'].listeners.click();
    assert.equal(added,1);assert.equal(app.supplier.value,'8');
    assert.equal(app.requests.length,2);
});
test('unlinked product needs explicit save; failed writes keep input and retries use the same key',async()=>{
    const app=setup();await flush();app.root.dataset.linkUrl='http://example.test/v2/receipts/product-supplier';
    app.respond(async()=>({ok:true,json:async()=>data([{...product,suppliers:[]}])}));
    await app.nodes.refresh.listeners.click();
    let added=0;app.form._addRestockProduct=()=>{added++;return 'added';};
    app.click({restockProduct:'4'});
    assert.equal(app.requests.length,2);
    app.nodes['supplier-choice'].value='new';app.nodes['new-name'].value='Supplier Baru Sintetis';
    app.respond(async()=>({ok:false,status:500}));
    await app.nodes['supplier-save'].listeners.click();
    const failed=JSON.parse(app.requests.at(-1).options.body);
    assert.equal(added,0);assert.equal(app.nodes['new-name'].value,'Supplier Baru Sintetis');
    app.respond(async()=>({ok:true,json:async()=>({supplier:{id:12,name:'Supplier Baru Sintetis',code:'SUP-0012'}})}));
    await app.nodes['supplier-save'].listeners.click();
    const retry=JSON.parse(app.requests.at(-1).options.body);
    assert.equal(retry.idempotency_key,failed.idempotency_key);
    assert.equal(retry.confirmed,true);
    assert.equal(app.requests.at(-1).options.headers['X-CSRF-TOKEN'],'synthetic-csrf');
    assert.equal(app.supplier.value,'12');assert.equal(added,1);
});
test('unlinked product can use the existing receipt supplier without creating a new supplier',async()=>{
    const app=setup();await flush();
    app.supplier.options=[{value:'7',textContent:'Supplier Sintetis'}];app.supplier.value='7';
    app.form._hasReceiptItems=()=>true;app.root.dataset.linkUrl='http://example.test/v2/receipts/product-supplier';
    app.respond(async()=>({ok:true,json:async()=>data([{...product,suppliers:[]}])}));
    await app.nodes.refresh.listeners.click();app.click({restockProduct:'4'});
    assert.equal(app.nodes['supplier-choice'].value,'7');
    app.respond(async()=>({ok:true,json:async()=>({supplier:{id:7,name:'Supplier Sintetis',code:'SUP-0007'}})}));
    await app.nodes['supplier-save'].listeners.click();
    const payload=JSON.parse(app.requests.at(-1).options.body);
    assert.equal(payload.supplier_id,'7');assert.equal('name' in payload,false);
    assert.equal(app.supplier.value,'7');
});
test('supplier writes coalesce double clicks and cannot be cancelled while pending',async()=>{
    const app=setup();await flush();app.root.dataset.linkUrl='http://example.test/v2/receipts/product-supplier';
    app.respond(async()=>({ok:true,json:async()=>data([{...product,suppliers:[]}])}));
    await app.nodes.refresh.listeners.click();app.click({restockProduct:'4'});
    app.nodes['supplier-choice'].value='new';app.nodes['new-name'].value='Supplier Tertunda Sintetis';
    let finish;app.respond(()=>new Promise(resolve=>finish=resolve));
    const first=app.nodes['supplier-save'].listeners.click();
    await app.nodes['supplier-save'].listeners.click();
    app.nodes['supplier-cancel'].listeners.click();
    assert.equal(app.requests.length,3);assert.equal(app.nodes.editor.hidden,false);
    finish({ok:true,json:async()=>({supplier:{id:14,name:'Supplier Tertunda Sintetis',code:'SUP-0014'}})});
    await first;
    assert.equal(app.supplier.value,'14');assert.equal(app.nodes.editor.hidden,true);
});
