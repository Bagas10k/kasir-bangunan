import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const source=readFileSync(new URL('../resources/views/v2/login-loading-script.blade.php',import.meta.url),'utf8').replace(/<\/?script>/g,'');
test('native login shows a skeleton, coalesces submit, and restores the form on back navigation',()=>{
    const events={},attributes=new Map(),loading={hidden:true},button={textContent:'Memeriksa',removeAttribute(){}};
    const form={addEventListener:(type,fn)=>events[type]=fn,setAttribute:(k,v)=>attributes.set(k,v),removeAttribute:k=>attributes.delete(k),querySelector:()=>button};
    const window={addEventListener:(type,fn)=>events[type]=fn};
    vm.runInNewContext(source,{document:{getElementById:id=>id==='login-form'?form:loading},window});
    let prevented=0;
    events.submit({preventDefault(){prevented++;}});
    assert.equal(loading.hidden,false);assert.equal(prevented,0);assert.equal(form.inert,true);
    events.submit({preventDefault(){prevented++;}});
    assert.equal(prevented,1);
    events.pageshow();
    assert.equal(loading.hidden,true);assert.equal(button.textContent,'Masuk');assert.equal(attributes.has('aria-busy'),false);assert.equal(form.inert,false);
});
