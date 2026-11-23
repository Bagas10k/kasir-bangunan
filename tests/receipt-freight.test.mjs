import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
test('freight preview uses decimal arithmetic and separates supplier debt from bank expense',()=>{
    const window={},context=vm.createContext({window});
    const decimal=readFileSync(new URL('../resources/views/v2/pos-cashier-script.blade.php',import.meta.url),'utf8');
    vm.runInContext(decimal.slice('<script>'.length,decimal.indexOf('})();')+5),context);
    const target={textContent:''},listeners={};
    const form={elements:{freight_amount:{value:'25'},freight_method:{value:'supplier'}},
        querySelector:s=>s==='[data-freight-summary]'?target:null,
        querySelectorAll:()=>[{querySelector:s=>({value:s==='[data-name=quantity]'?'2.5':'100.125'})}],
        addEventListener:(type,fn)=>listeners[type]=fn};
    context.document={getElementById:()=>form};
    const source=readFileSync(new URL('../resources/views/v2/receipt-freight-script.blade.php',import.meta.url),'utf8').replace(/<\/?script>/g,'');
    vm.runInContext(source,context);
    assert.match(target.textContent,/Utang supplier Rp 275,31/);
    form.elements.freight_method.value='bank';listeners.change();
    assert.match(target.textContent,/Utang supplier Rp 250,31/);
    assert.match(target.textContent,/Pengeluaran kas\/bank Rp 25/);
});
