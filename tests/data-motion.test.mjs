import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const source=readFileSync(new URL('../resources/views/v2/ui-motion.blade.php',import.meta.url),'utf8');
function setup({reduced=false,visible=true}={}){
    const animations=[],numbers=[];
    const make=(dataset={},textContent='')=>({dataset,textContent,hasAttribute:name=>name==='data-number-text'&&dataset.numberText!==undefined,getClientRects:()=>visible?[{}]:[],
        animate(frames,options){const animation={frames,options,cancel(){this.cancelled=true;}};animations.push(animation);return animation;}});
    const bar=make({motionKey:'sale-2026-11-23',motionBar:'70'}),item=make(),number=make({numberKey:'revenue'},'Rp 250');
    const root={querySelectorAll:selector=>selector==='[data-motion-bar]'?[bar]:selector==='[data-motion-item]'?[item]:[number]};
    const context={window:{uiNumbers:{get:el=>el.textContent,set:(el,target,options)=>numbers.push({el,target,options}),text(){}}},
        document:{documentElement:{},hidden:false},getComputedStyle:()=>({height:'35px',width:'20px',getPropertyValue:name=>name==='--ui-number-duration'?'300ms':'ease-in-out'}),
        reduce:()=>reduced,WeakMap,WeakSet,Map,MAX_ROWS:12};
    vm.createContext(context);
    const start=source.indexOf('const dataAnimations='),end=source.indexOf('function enter(',start);
    if(start>=0)vm.runInContext(source.slice(start,end),context);
    return {context,root,bar,number,animations,numbers};
}
test('charts use one 300ms data transition from previous displayed height',()=>{
    const app=setup(),previous=app.context.dataSnapshot(app.root);
    app.context.dataChange(app.root,previous);
    const animation=app.animations[0];
    assert.equal(animation.frames[0].height,'35px');
    assert.equal(animation.frames[1].height,'70%');
    assert.equal(animation.options.duration,300);
    assert.equal(animation.options.easing,'ease-in-out');
});
test('horizontal product bars grow from zero and overlapping animations cancel',()=>{
    const app=setup();app.bar.dataset.motionAxis='x';
    app.context.dataChange(app.root);
    assert.equal(app.animations[0].frames[0].width,'0px');
    assert.equal(app.animations[0].frames[1].width,'70%');
    app.context.dataChange(app.root);
    assert.equal(app.animations[0].cancelled,true);
});
test('numbers use the exact existing counter and retain their final canonical target',()=>{
    const app=setup(),previous=app.context.dataSnapshot(app.root);
    app.number.textContent='Rp 900.719.925.474.099,99';
    app.context.dataChange(app.root,previous);
    assert.equal(app.numbers[0].target,'Rp 900.719.925.474.099,99');
    assert.equal(app.numbers[0].options.from,'Rp 250');
});
test('reduced motion and hidden content never animate data',()=>{
    for(const options of [{reduced:true},{visible:false}]){
        const app=setup(options);app.context.dataChange(app.root);
        assert.equal(app.animations.length,0);
    }
});
test('hidden GET calendar form cannot generate an Isi formulir launcher',()=>{
    const monitor=readFileSync(new URL('../resources/views/v2/pos-monitor.blade.php',import.meta.url),'utf8');
    assert.match(monitor,/<form id="pd-custom-form" method="get"[^>]+hidden/);
    const workspace=readFileSync(new URL('../resources/views/v2/ui-workspace.blade.php',import.meta.url),'utf8');
    let popupCount=0;
    const form={hidden:true,id:'pd-custom-form',closest:()=>null,getAttribute:()=>null,querySelector:()=>({})};
    vm.runInNewContext(workspace.slice(workspace.indexOf('function popupForms('),workspace.indexOf('function invoiceDetails('))+'popupForms(root);',
        {root:{querySelectorAll:()=>[form]},document:{},popup:()=>popupCount++});
    assert.equal(popupCount,0);
});
test('uiLive replaces changed chart geometry once and skips identical data on later refreshes',()=>{
    const live=readFileSync(new URL('../resources/views/v2/ui-live.blade.php',import.meta.url),'utf8');
    let replaced=0,animated=0;
    const old={dataset:{motionVersion:'old'},matches:()=>false,querySelector:()=>null,
        replaceChildren(){replaced++;}};
    const next={dataset:{motionVersion:'new'},childNodes:[{}],hasAttribute:name=>name==='data-motion-region'};
    const context={window:{uiMotion:{dataSnapshot:()=>({bars:new Map()}),dataChange:()=>animated++}},
        OPAQUE:'opaque',syncAttrs:(a,b)=>Object.assign(a.dataset,b.dataset),imported:nodes=>nodes};
    vm.createContext(context);
    vm.runInContext(live.slice(live.indexOf('function syncNode('),live.indexOf('function patch(')),context);
    context.syncNode(old,next);context.syncNode(old,next);
    assert.equal(replaced,1);assert.equal(animated,1);
});
