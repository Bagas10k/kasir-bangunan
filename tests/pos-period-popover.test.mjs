import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
test('custom-date button opens the shared range calendar directly and submits only after calendar confirmation',()=>{
    const source=readFileSync(new URL('../resources/views/v2/pos-period-popover.blade.php',import.meta.url),'utf8');
    let click,commit,submitted=0,closed=0;
    const trigger={addEventListener:(name,fn)=>click=fn},form={requestSubmit:()=>submitted++},from={},to={};
    const nodes={'pd-custom-trigger':trigger,'pd-custom-form':form,'pd-from':from,'pd-to':to},body={dataset:{activeTask:'kasir'}};
    let onMenu;
    vm.runInNewContext(source.replace(/<\/?script>/g,''),{
        document:{getElementById:id=>nodes[id],body},
        window:{uiDatePicker:{openRange(a,b,owner,callback){assert.equal(a,from);assert.equal(b,to);assert.equal(owner,trigger);commit=callback;},closeFor(){closed++;}}},
        MutationObserver:class{constructor(fn){onMenu=fn;}observe(){}}
    });
    click();assert.equal(submitted,0);
    commit();assert.equal(submitted,1);
    body.dataset.activeTask='shift';onMenu();assert.equal(closed,1);
});
test('shared calendar requires two selected dates and commits once without rolling back the range',()=>{
    const blade=readFileSync(new URL('../resources/views/v2/shared-date-picker.blade.php',import.meta.url),'utf8');
    let committed=0,focused=0;
    const startInput={value:'2026-11-01'},endInput={value:''},summary={textContent:''};
    const context={active:{requireRange:true,range:{start:'2026-11-01',end:'',startInput,endInput},
        trigger:{setAttribute(){},focus(){focused++;}},snapshot:{start:'2026-10-01',end:'2026-10-02'},onCommit:()=>committed++},
        calendar:{hidden:false,matches:()=>false,querySelector:()=>summary},setValue:(input,value)=>input.value=value};
    vm.createContext(context);
    vm.runInContext(blade.slice(blade.indexOf('function close('),blade.indexOf('function footerWith(')),context);
    context.commit();assert.equal(committed,0);assert.equal(context.calendar.hidden,false);
    context.active.range.end='2026-11-03';endInput.value='2026-11-03';
    context.commit();assert.equal(committed,1);assert.equal(startInput.value,'2026-11-01');
    assert.equal(endInput.value,'2026-11-03');assert.equal(focused,1);
    context.commit();assert.equal(committed,1);
});
