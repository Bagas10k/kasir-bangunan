import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source=readFileSync(new URL('../resources/views/v2/pos-cashier-script.blade.php',import.meta.url),'utf8');
function setup(state){
    const cart=[{quantity:'2'}],error={hidden:true,focus(){this.focused=true;}};
    let opened=0;
    const reminder={textContent:'Belum bisa checkout: kas harian belum dibuka. Minta admin membuka kas harian. Keranjang tetap tersimpan.'};
    const context={rows:()=>cart,valid:true,saved:false,error,
        document:{getElementById:id=>id==='store-shift-state'?{dataset:{posShiftState:state}}:reminder},
        payment:{showModal(){opened++;}},refresh(){},showPaid(){},paidDisplay:{focus(){},select(){}}};
    vm.createContext(context);
    vm.runInContext(source.slice(source.indexOf('function openPay('),source.indexOf('function closePay(')),context);
    return {context,cart,error,opened:()=>opened};
}
test('checkout without an open shift explains why and preserves the cart',()=>{
    for(const state of ['closed','unknown']){
        const app=setup(state);
        app.context.openPay();
        assert.equal(app.error.hidden,false);
        assert.match(app.error.textContent,/Belum bisa checkout/);
        assert.equal(app.error.focused,true);
        assert.equal(app.opened(),0);
        assert.deepEqual(app.cart,[{quantity:'2'}]);
    }
});
test('checkout with an open shift still opens payment',()=>{
    const app=setup('open');app.context.openPay();
    assert.equal(app.opened(),1);
});
test('a valid nonempty cart can click checkout to receive the shift explanation',()=>{
    const line=source.match(/payButton\.disabled=([^;]+);/)[1];
    const context={payButton:{},rows:()=>[{}],valid:true,saved:false,
        document:{getElementById:()=>({dataset:{posShiftState:'closed'}})}};
    vm.runInNewContext('payButton.disabled='+line,context);
    assert.equal(context.payButton.disabled,false);
});
test('the checkout explanation is exposed instead of hidden by the old cashier theme',()=>{
    const styles=readFileSync(new URL('../resources/views/v2/responsive-refinements.blade.php',import.meta.url),'utf8');
    const view=readFileSync(new URL('../resources/views/v2/pos-cashier.blade.php',import.meta.url),'utf8');
    assert.match(styles,/#store-shift-reminder:not\(\[hidden\]\)\{display:block !important/);
    assert.match(styles,/#store-shift-reminder\[hidden\]\{display:none !important/);
    assert.match(view,/id="store-shift-reminder" role="status"/);
    assert.match(view,/id="open-payment"[^>]*aria-describedby="store-shift-reminder"/);
});
