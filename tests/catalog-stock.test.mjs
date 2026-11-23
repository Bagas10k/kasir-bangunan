import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const source=readFileSync(new URL('../resources/views/v2/pos-cashier-script.blade.php',import.meta.url),'utf8');
function setup(stock){
    const shared={window:{}};
    vm.runInNewContext(source.slice('<script>'.length,source.indexOf('})();')+5),shared);
    let touched=0;
    const context={D:shared.window.KasirDecimal,map:new Map([['7',{id:'7',name:'Material sintetis',stock_quantity:stock}]]),
        rows:()=>{touched++;throw Error('cart touched');},error:{hidden:true},window:{v2AddCartRow(){touched++;throw Error('new row');}}};
    vm.createContext(context);
    vm.runInContext(source.slice(source.indexOf('function add('),source.indexOf('// Katalog dirender')),context);
    return {context,touched:()=>touched};
}
test('out-of-stock products cannot be added even when the UI is bypassed',()=>{
    const app=setup('0.0000');
    app.context.add('7');assert.equal(app.touched(),0);
    assert.equal(app.context.error.hidden,false);assert.match(app.context.error.textContent,/habis/i);
});
test('in-stock products still reach the existing cart path',()=>{
    const app=setup('0.1250');assert.throws(()=>app.context.add('7'),/cart touched/);
});
test('catalog disables the add action for empty stock',()=>{
    const start=source.indexOf('function catalogRow('),end=source.indexOf("tbody.addEventListener('click'",start);
    const app=setup('0'),buttons=[];
    app.context.document={createElement:tag=>{
        const el={dataset:{},append(){},classList:{add(){}},setAttribute(k,v){this[k]=v;}};
        if(tag==='button')buttons.push(el);return el;
    }};
    app.context.quantityText=String;app.context.stockTone=()=> 'out-of-stock';app.context.priceOf=p=>p.selling_price;
    vm.runInContext(source.slice(start,end),app.context);
    app.context.catalogRow({p:{id:'7',name:'Material sintetis',sku:'SYN-7',selling_price:'10',unit:'sak'},qty:0n});
    assert.equal(buttons[0].disabled,true);assert.equal(buttons[0].textContent,'Habis');
    assert.match(buttons[0]['aria-label'],/Material sintetis/);
});
test('restored or typed quantities with insufficient stock are invalid without clearing the draft',()=>{
    const app=setup('0.0000'),quantity={value:'1.25'};
    app.context.rows=()=>[{querySelector:selector=>selector.includes('product_id')?{value:'7'}:quantity}];
    const start=source.indexOf('function refresh(){'),end=source.indexOf(' try{for(const row',start);
    vm.runInContext(source.slice(start,end)+'return valid;}',app.context);
    assert.equal(app.context.refresh(),false);assert.equal(quantity.value,'1.25');
    assert.match(app.context.error.textContent,/tidak mencukupi/);
});
