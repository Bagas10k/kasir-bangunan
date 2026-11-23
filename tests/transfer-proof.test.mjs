import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const source=readFileSync(new URL('../resources/views/v2/transfer-proof-script.blade.php',import.meta.url),'utf8').replace(/^<script[^>]*>\s*|\s*<\/script>\s*$/g,'');
function setup(){
    const file={name:'synthetic.png'},input={files:[file],disabled:false,required:false,value:''},field={hidden:true};
    const form={elements:{method:{value:'transfer'}},dataset:{direction:'receivable'},querySelector:s=>s==='[name=transfer_proof]'?input:s==='[data-transfer-proof-field]'?field:null};
    const listeners={};
    const window={uiLive:{onPatch(){}}};
    const document={querySelectorAll:()=>[form],addEventListener(t,fn){listeners[t]=fn;}};
    class FormData{constructor(){this.rows=[];}append(key,value){this.rows.push([key,value]);}}
    vm.runInNewContext(source,{window,document,FormData});
    return {window,form,input,field,file,listeners};
}
test('transfer uses multipart with original photo and nested checkout fields, preserving zero and booleans',()=>{
    const app=setup();
    const request=app.window.v2PaymentRequest({items:[{product_id:7,quantity:'1.50'}],confirm_duplicate:false,paid_amount:'0',customer_id:null},app.form);
    assert.equal(request.body.rows.find(([key])=>key==='items[0][quantity]')[1],'1.50');
    assert.equal(request.body.rows.find(([key])=>key==='transfer_proof')[1],app.file);
    assert.equal(request.body.rows.find(([key])=>key==='confirm_duplicate')[1],'0');
    assert.equal(request.headers['Content-Type'],undefined);
    assert.equal(app.input.required,true);
});
test('cash and supplier forms never send a previously selected transfer photo',()=>{
    const app=setup();app.form.elements.method.value='cash';
    app.listeners.change({target:{closest:()=>app.form}});
    assert.equal(app.field.hidden,true);assert.equal(app.input.disabled,true);
    const request=app.window.v2PaymentRequest({method:'cash'},app.form);
    assert.equal(request.headers['Content-Type'],'application/json');
    assert.deepEqual(JSON.parse(request.body),{method:'cash'});
});
test('credit transfer DP requires proof only when the deposit is positive',()=>{
    const app=setup();app.form.elements={payment_method:{value:'credit'},deposit_method:{value:'transfer'},paid_amount:{value:'25.50'}};
    app.listeners.change({target:{closest:()=>app.form}});
    assert.equal(app.input.required,true);
    app.form.elements.paid_amount.value='0.00';app.listeners.input({target:{closest:()=>app.form}});
    assert.equal(app.input.required,false);assert.equal(app.input.disabled,true);
});
