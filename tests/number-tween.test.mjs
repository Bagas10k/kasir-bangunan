import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
function setup(reduced=false){
    const source=readFileSync(new URL('../resources/views/v2/ui-number-tween.blade.php',import.meta.url),'utf8');
    let time=0,id=0;
    const frames=new Map();
    const node=(text='')=>({textContent:text,dataset:{},isConnected:true,matches:()=>false,
        getClientRects:()=>[{}],setAttribute(k,v){this[k]=v;},replaceChildren(...children){this.children=children;}});
    const context={window:{},document:{hidden:false,documentElement:{},createElement:()=>node()},
        matchMedia:()=>({matches:reduced}),getComputedStyle:()=>({getPropertyValue:()=> '300ms'}),
        performance:{now:()=>time},requestAnimationFrame:fn=>{frames.set(++id,fn);return id;},cancelAnimationFrame:key=>frames.delete(key)};
    vm.runInNewContext(source.match(/<script>([\s\S]*?)<\/script>/)[1],context);
    return {api:context.window.uiNumbers,node,frames,step:t=>{time=t;const batch=[...frames.values()];frames.clear();batch.forEach(fn=>fn(t));}};
}
test('money tweens visually while its authoritative and accessible value is final immediately',()=>{
    const app=setup(),el=app.node('Rp 100');
    app.api.set(el,'Rp 200');
    assert.equal(app.api.get(el),'Rp 200');
    assert.equal(el.children[0].textContent,'Rp 200');
    assert.equal(el.children[1]['aria-hidden'],'true');
    app.step(150);assert.equal(el.children[1].textContent,'Rp 150');
    app.step(300);assert.equal(el.children[1].textContent,'Rp 200');assert.equal(app.frames.size,0);
});
test('large amounts retain cent precision without Number conversion',()=>{
    const app=setup(),el=app.node('Rp 99.999.999.999.999.999,98');
    app.api.set(el,'Rp 99.999.999.999.999.999,99');app.step(300);
    assert.equal(el.children[1].textContent,'Rp 99.999.999.999.999.999,99');
});
test('rapid changes resume from the displayed value and cancel the previous frame',()=>{
    const app=setup(),el=app.node('10');
    app.api.set(el,'30');app.step(150);
    assert.equal(el.children[1].textContent,'20');
    app.api.set(el,'40');assert.equal(app.frames.size,1);
    assert.equal(el.children[1].textContent,'20');
    app.step(450);assert.equal(el.children[1].textContent,'40');
});
test('reduced motion, invalid labels and input fields never get a tween',()=>{
    const app=setup(true),el=app.node('10');
    app.api.set(el,'20');assert.equal(el.textContent,'20');assert.equal(app.frames.size,0);
    app.api.set(el,'Periksa isian');assert.equal(el.textContent,'Periksa isian');
    const input=app.node('');input.matches=()=>true;input.value='7';
    app.api.set(input,'99');assert.equal(input.value,'7');assert.equal(input.textContent,'');
});
test('fractional and decreasing values finish with the exact target formatting',()=>{
    const app=setup(),el=app.node('1,25');
    app.api.set(el,'0,25');app.step(150);assert.equal(el.children[1].textContent,'0,75');
    app.step(300);assert.equal(el.children[1].textContent,'0,25');
    app.api.set(el,'−Rp 25,50');assert.equal(app.api.get(el),'−Rp 25,50');
});
test('an unchanged target does not restart an animation and removed nodes stop scheduling frames',()=>{
    const app=setup(),el=app.node('10');
    app.api.set(el,'20');const first=[...app.frames.keys()][0];
    app.api.set(el,'20');assert.equal([...app.frames.keys()][0],first);
    el.isConnected=false;app.step(20);assert.equal(app.frames.size,0);
    assert.equal(el.children[1].textContent,'20');
});
test('payment review reads final values, not intermediate or duplicated accessible text',()=>{
    const cashier=readFileSync(new URL('../resources/views/v2/pos-cashier-script.blade.php',import.meta.url),'utf8');
    const review=cashier.slice(cashier.indexOf('function renderReview()'),cashier.indexOf("document.querySelector('#review-payment').addEventListener"));
    assert.match(review,/uiNumbers\.get\(row\.querySelector\('\.item-subtotal'\)\)/);
    assert.match(review,/uiNumbers\.get\(document\.querySelector\('#display-change'\)\)/);
});
test('quantity summaries tween every number without changing labels or losing decimal quantities',()=>{
    const app=setup(),el=app.node('1 jenis · 1,25 barang');
    app.api.text(el,'1 jenis · 1,25 barang');
    app.api.text(el,'2 jenis · 3,25 barang');
    app.step(150);
    assert.equal(app.api.get(el),'2 jenis · 3,25 barang');
    const parts=el.children.filter(child=>typeof child!=='string');
    assert.equal(parts[1].children[1].textContent,'2,25');
    app.step(300);assert.equal(parts[1].children[1].textContent,'3,25');
});
test('a mixed-text counter can start from the corresponding previous-period value',()=>{
    const app=setup(),el=app.node('dari 30 nota');
    app.api.text(el,'dari 30 nota',{from:'dari 10 nota'});
    app.step(150);
    assert.equal(el.children[1].children[1].textContent,'20');
    app.step(300);assert.equal(app.api.get(el),'dari 30 nota');
});
