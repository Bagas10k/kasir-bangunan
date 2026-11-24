import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source=readFileSync(new URL('../resources/views/v2/pos-mobile.blade.php',import.meta.url),'utf8');
test('opening hides the bar without display:none so its entrance animation cannot restart on close',()=>{
    assert.doesNotMatch(source,/body\.pm-cart-open\s+\.pm-bar\s*\{[^}]*display\s*:\s*none/);
    assert.match(source,/body\.pm-cart-open\s+\.pm-bar\s*\{[^}]*visibility\s*:\s*hidden/);
});
test('backdrop remains renderable and uses the same duration and easing as the cart',()=>{
    const css=source.slice(source.indexOf('<style>'),source.indexOf('</style>'));
    assert.match(css,/\.pm-backdrop\{[^}]*display:block[^}]*transition:opacity var\(--ui-morph-duration\) var\(--ui-morph-easing\)/);
    assert.match(css,/body\.pm-cart-closing \.pm-backdrop\{[^}]*opacity:0/);
});
function setup(){
    const classes=new Set(),attributes=new Map();
    const pane={inert:true,scrollTop:90,setAttribute:(k,v)=>attributes.set(k,v),removeAttribute:k=>attributes.delete(k)};
    let focused=0;
    const openButton={setAttribute:(k,v)=>attributes.set(k,v),focus(){focused++;}};
    const panel={hidden:false},last={count:2};
    const context={pane,openButton,panel,last,bar:{hidden:false,querySelector:()=>openButton},
        close:{focus(){focused++;}},reduce:()=>true,setTimeout:fn=>fn(),window:{},cartMotion:null,cartOrigin:null,cartClosing:false,
        document:{body:{classList:{add:v=>classes.add(v),remove:v=>classes.delete(v)}}}};
    vm.createContext(context);
    vm.runInContext(source.slice(source.indexOf('function openSheet(){'),source.indexOf("bar.querySelector('.pm-bar-open').addEventListener")),context);
    return {...context,attributes,classes,focused:()=>focused};
}
test('desktop cart opens as a modal panel without a viewport gate and closes without changing contents',()=>{
    const app=setup();
    app.openSheet();
    assert.equal(app.pane.inert,false);
    assert.equal(app.attributes.get('aria-expanded'),'true');
    assert.equal(app.attributes.get('aria-modal'),'true');
    assert.equal(app.pane.scrollTop,0);
    app.closeSheet();
    assert.equal(app.pane.inert,true);
    assert.equal(app.attributes.get('aria-expanded'),'false');
    assert.equal(app.last.count,2);
    assert.equal(app.focused(),2);
});
test('normal close waits for its morph, ignores duplicate close, and payment handoff cancels it',()=>{
    const app=setup();let finish,cancelled=0,calls=0;
    app.window.uiMotion={morphFrom:(pane,origin,options)=>{
        if(!options?.closing)return null;
        calls++;finish=options.onFinish;return {cancel(){cancelled++;}};
    }};
    app.openSheet();app.closeSheet();app.closeSheet();
    assert.equal(calls,1);assert.equal(app.classes.has('pm-cart-open'),true);
    assert.equal(app.classes.has('pm-cart-closing'),true);
    assert.equal(app.pane.inert,true);
    finish();assert.equal(app.classes.has('pm-cart-open'),false);
    assert.equal(app.classes.has('pm-cart-closing'),false);
    app.openSheet();app.closeSheet();app.closeSheet(false);
    assert.equal(app.classes.has('pm-cart-open'),false);
    assert.equal(app.classes.has('pm-cart-closing'),false);
    assert.ok(cancelled>0);
});
test('hidden cashier or an empty cart cannot open the panel',()=>{
    const app=setup();
    app.panel.hidden=true;app.openSheet();
    assert.equal(app.pane.inert,true);
    app.panel.hidden=false;app.last.count=0;app.openSheet();
    assert.equal(app.classes.size,0);
});
test('Tab wraps inside the drawer but leaves native payment dialogs alone',()=>{
    let handler,focus='',prevented=0;
    const control=name=>({disabled:false,closest:()=>null,getClientRects:()=>[{}],focus(){focus=name;}});
    const first=control('first'),last=control('last');
    const document={activeElement:last,body:{classList:{contains:()=>true}},querySelector:()=>null,
        addEventListener(type,fn){handler=fn;}};
    const pane={querySelectorAll:()=>[first,last],contains:()=>true};
    const start=source.indexOf("document.addEventListener('keydown',e=>{\n if(e.key!=='Tab'");
    vm.runInNewContext(source.slice(start,source.indexOf('// --- pembayaran sebagai lembar bawah ---',start)),{document,pane});
    handler({key:'Tab',shiftKey:false,preventDefault(){prevented++;}});
    assert.equal(focus,'first');
    document.activeElement=first;
    handler({key:'Tab',shiftKey:true,preventDefault(){prevented++;}});
    assert.equal(focus,'last');
    document.querySelector=()=>({open:true});
    handler({key:'Tab',shiftKey:true,preventDefault(){prevented++;}});
    assert.equal(prevented,2);
});

test('lembar keranjang HP tidak memuat tombol kosongkan massal atau baris subtotal', () => {
    assert.doesNotMatch(source, /pm-clear|pm-gross|Kosongkan keranjang/);
    assert.match(source, /subRows\.hidden=!\(last\.discount>0n\)/);
});
