import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
function setup(left=600,width=1024){
    const source=readFileSync(new URL('../resources/views/v2/pos-period-popover.blade.php',import.meta.url),'utf8'),listeners={},windowEvents={};
    const node=()=>({style:{},events:{},attrs:{},hidden:false,setAttribute(k,v){this.attrs[k]=v;},
        addEventListener(type,fn){this.events[type]=fn;},focus(){this.focused=true;},contains:()=>false,querySelectorAll:()=>[]});
    const trigger=node(),panel=node(),close=node(),root=node();
    trigger.getClientRects=()=>[{}];trigger.getBoundingClientRect=()=>({left,right:left+120,top:100,bottom:144});
    panel.hidden=true;panel.offsetWidth=400;panel.offsetHeight=240;panel.showPopover=()=>panel.shown=true;
    panel.hidePopover=()=>panel.shown=false;panel.matches=()=>panel.shown;
    const nodes={'pd-custom':root,'pd-custom-trigger':trigger,'pd-custom-panel':panel,'pd-custom-close':close};
    let onMenu;
    vm.runInNewContext(source.replace(/<\/?script>/g,''),{document:{getElementById:id=>nodes[id],body:{dataset:{activeTask:'kasir'}},
        addEventListener:(name,fn)=>listeners[name]=fn,querySelector:()=>null},window:{addEventListener:(name,fn)=>windowEvents[name]=fn},
        innerWidth:width,innerHeight:768,getComputedStyle:()=>({columnGap:'8px'}),
        MutationObserver:class{constructor(fn){onMenu=fn;}observe(){}}});
    return {trigger,panel,close,listeners,windowEvents,onMenu};
}
test('date controls open to the side without expanding the dashboard layout and close with focus restoration',()=>{
    const app=setup();app.trigger.events.click();
    assert.equal(app.panel.style.left,'192px');assert.equal(app.panel.style.top,'100px');
    assert.equal(app.panel.hidden,false);assert.equal(app.trigger.attrs['aria-expanded'],'true');
    app.close.events.click();assert.equal(app.panel.hidden,true);assert.equal(app.trigger.focused,true);
});
test('mobile positions below the trigger only when neither side has room',()=>{
    const app=setup(200,420);app.panel.offsetWidth=400;app.trigger.events.click();
    assert.equal(app.panel.style.left,'8px');assert.equal(app.panel.style.top,'152px');
    assert.equal(app.panel.style.maxWidth,'404px');
});
test('narrow space clamps the panel into the viewport and date picker children do not dismiss it',()=>{
    const app=setup(20);app.trigger.events.click();
    assert.equal(app.panel.style.left,'148px');
    app.listeners.pointerdown({target:{closest:()=>({})}});
    assert.equal(app.panel.hidden,false);
    app.listeners.pointerdown({target:{closest:()=>null}});
    assert.equal(app.panel.hidden,true);
});
