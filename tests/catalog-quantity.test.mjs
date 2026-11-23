import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const source=readFileSync(new URL('../resources/views/v2/pos-mobile.blade.php',import.meta.url),'utf8');
function setup(){
    const value={value:'1',dispatchEvent(){this.events=(this.events||0)+1;}},error={textContent:''};
    const field={value:'12',setAttribute(){},setCustomValidity(v){this.invalid=v;},closest:()=>({dataset:{product:'7'},querySelector:()=>error})};
    const decimal=readFileSync(new URL('../resources/views/v2/pos-cashier-script.blade.php',import.meta.url),'utf8'),shared={window:{}};
    vm.runInNewContext(decimal.slice('<script>'.length,decimal.indexOf('})();')+5),shared);
    const context={rowOf:()=>({querySelector:()=>value}),qtyText:v=>String(v).replace('.',','),D:shared.window.KasirDecimal,Event:class{}};
    const start=source.indexOf('function applyCatalogQuantity(');
    assert.notEqual(start,-1,'catalog quantities must support typed editing');
    vm.createContext(context);
    vm.runInContext(source.slice(start,source.indexOf("tbody?.addEventListener('input'",start)),context);
    return {field,value,error,apply:restore=>context.applyCatalogQuantity(field,restore)};
}
test('typed quantities update the existing cart input, including fractional units',()=>{
    const app=setup();assert.equal(app.apply(),true);assert.equal(app.value.value,'12');
    app.field.value='1,25';assert.equal(app.apply(),true);assert.equal(app.value.value,'1.25');assert.equal(app.value.events,2);
});
test('invalid drafts do not alter cart quantity and are restored on blur',()=>{
    const app=setup();
    for(const invalid of ['', '0','-1','1x','1,12345']){
        app.field.value=invalid;assert.equal(app.apply(),false);assert.equal(app.value.value,'1');
        app.apply(true);assert.equal(app.field.value,'1');
    }
});
