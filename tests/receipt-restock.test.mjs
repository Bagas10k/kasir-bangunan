import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source=readFileSync(new URL('../resources/views/v2/sprint2.blade.php',import.meta.url),'utf8');
function setup(){
    const row=(id='',qty='',cost='')=>{
        const select={value:id,add(option){this.value=option.value;},dispatchEvent(){},_uiPickSync(){}};
        const quantity={value:qty,focus(){this.focused=true;}},price={value:cost,dataset:{},dispatchEvent(){}};
        return {dataset:{},select,quantity,price,querySelector:s=>s==='[data-name=product_id]'?select:s==='[data-name=quantity]'?quantity:price};
    };
    const initial=row(),receipt={dataset:{},elements:{supplier_id:{value:'7'}},classList:{contains:()=>false}};
    const box={children:[initial]};
    const context={receipt,box,addRow(){const result=row();box.children.push(result);return result;},recalc(){},window:{},
        count:v=>String(v),rupiah:v=>'Rp '+v,Option:class{constructor(text,value){this.text=text;this.value=value;}},Event:class{}};
    vm.createContext(context);
    const costStart=source.indexOf('function fillReceiptCost(');
    if(costStart!==-1)vm.runInContext(source.slice(costStart,source.indexOf('const addRow =',costStart)),context);
    const start=source.indexOf('function addRestockProduct(product)');
    assert.notEqual(start,-1,'receipt must expose the existing-row restock integration');
    vm.runInContext(source.slice(start,source.indexOf('receipt._addRestockProduct',start)),context);
    return {...context,initial,row};
}
const product={id:42,name:'Barang Sintetis',sku:'SYN-42',unit:'sak',stock:'0',minimum:'2',cost:'123.4500',suppliers:[{id:7,name:'Supplier Sintetis'}]};
test('adding a restock item selects it immediately and fills its current cost while leaving quantity for the user',()=>{
    const app=setup();
    assert.equal(app.addRestockProduct(product),'added');
    assert.equal(app.box.children.length,1);
    assert.equal(app.initial.select.value,'42');
    assert.equal(app.initial.quantity.value,'');
    assert.equal(app.initial.price.value,'123.4500');
    assert.equal(app.initial.dataset.cost,'123.4500');
});
test('recommendations create the next row automatically without a manual Add item click',()=>{
    const app=setup();app.addRestockProduct(product);
    app.initial.quantity.value='8';app.initial.price.value='250';
    assert.equal(app.addRestockProduct({...product,id:43}),'added');
    assert.equal(app.box.children.length,2);
    assert.equal(app.box.children[1].select.value,'43');
    assert.equal(app.box.children[1].price.value,'123.4500');
    assert.equal(app.box.children[1].quantity.focused,true);
    assert.equal(app.initial.quantity.value,'8');assert.equal(app.initial.price.value,'250');
});
test('default cost uses all four decimals and never overwrites a manually entered cost',()=>{
    const app=setup();
    assert.equal(typeof app.fillReceiptCost,'function');
    app.fillReceiptCost(app.initial,'123.4567');
    assert.equal(app.initial.price.value,'123.4567');
    app.initial.price.value='222.0001';app.initial.price.dataset.costEdited='1';
    app.fillReceiptCost(app.initial,'444.4444');assert.equal(app.initial.price.value,'222.0001');
    const blank=app.row();app.fillReceiptCost(blank,'0.0000');assert.equal(blank.price.value,'');
});
test('a new product replaces only the untouched suggested cost, including after a cleared field',()=>{
    const app=setup();
    app.fillReceiptCost(app.initial,'100.0000');
    app.fillReceiptCost(app.initial,'200.1234');assert.equal(app.initial.price.value,'200.1234');
    app.initial.price.value='0';app.initial.price.dataset.costEdited='1';
    app.fillReceiptCost(app.initial,'300');assert.equal(app.initial.price.value,'0');
    app.initial.price.value='';app.fillReceiptCost(app.initial,'300');assert.equal(app.initial.price.value,'300');
});
test('duplicate, incompatible supplier and busy form cannot change receipt lines',()=>{
    const app=setup();
    app.initial.select.value='42';app.initial.quantity.value='8';app.initial.price.value='250';
    assert.equal(app.addRestockProduct(product),'duplicate');
    app.receipt.elements.supplier_id.value='9';
    assert.equal(app.addRestockProduct(product),'supplier');
    app.receipt.dataset.busy='1';
    assert.equal(app.addRestockProduct(product),'busy');
    assert.equal(app.box.children.length,1);
    assert.equal(app.initial.quantity.value,'8');
    assert.equal(app.initial.price.value,'250');
});
test('partially typed rows and the maximum receipt size are preserved',()=>{
    const app=setup();
    app.initial.quantity.value='3';
    assert.equal(app.addRestockProduct(product),'added');
    assert.equal(app.box.children.length,2);
    assert.equal(app.initial.quantity.value,'3');
    app.box.children=Array.from({length:100},()=>app.row('99','1','1'));
    assert.equal(app.addRestockProduct(product),'limit');
    assert.equal(app.box.children.length,100);
});
test('automatic and manual supplier changes preserve populated receipt rows',()=>{
    const app=setup(),listeners={},error={};
    const supplier={value:'7',options:[{value:'7'},{value:'8'}],add(){},dispatchEvent(event){listeners[event.type]?.();},addEventListener(type,fn){listeners[type]=fn;}};
    app.receipt.elements.supplier_id=supplier;
    app.receipt.querySelector=()=>error;app.receipt.addEventListener=()=>{};
    app.initial.select.value='42';app.initial.quantity.value='8';app.initial.price.value='250';
    const start=source.indexOf('let receiptSupplier =');
    assert.notEqual(start,-1,'supplier changes must be guarded before clearing rows');
    const context={receipt:app.receipt,box:app.box,filterProducts(){},Event:class{constructor(type){this.type=type;}},Option:class{},queueMicrotask};
    vm.runInNewContext(source.slice(start,source.indexOf('const renumber',start)),context);
    assert.equal(app.receipt._selectReceiptSupplier({id:8,name:'Supplier B'}),false);
    assert.equal(supplier.value,'7');
    supplier.value='8';supplier.dispatchEvent({type:'change'});
    assert.equal(supplier.value,'7');
    assert.equal(app.initial.quantity.value,'8');assert.equal(app.initial.price.value,'250');
    app.initial.select.value='';app.initial.quantity.value='';app.initial.price.value='';
    assert.equal(app.receipt._selectReceiptSupplier({id:8,name:'Supplier B'}),true);
    assert.equal(supplier.value,'8');
});
