import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const blade=readFileSync(new URL('../resources/views/v2/admin-summary-style.blade.php',import.meta.url),'utf8');
const source=blade.match(/<script>([\s\S]*?)<\/script>/)[1];
function setup({mobile=false,compact=false}={}){
    const picks=['today','7d','30d'].map(asPick=>({dataset:{asPick},setAttribute(){}}));
    const folds=Array.from({length:3},()=>({open:false}));
    for(const fold of folds)fold.querySelector=()=>({addEventListener(type,fn){fold.click=fn;}});
    const page={dataset:{},querySelectorAll:s=>s==='[data-as-pick]'?picks:folds,
        querySelector:()=>({addEventListener(){}}),addEventListener(){}};
    vm.runInNewContext(source,{document:{querySelector:()=>page},window:{},
        matchMedia:query=>({matches:query.includes('max-width')?mobile:compact,addEventListener(){}}),
        localStorage:{getItem:()=>null,setItem(){}}});
    return folds;
}
test('short desktop starts with informative disclosure summaries rather than an overflowing detail stack',()=>{
    const folds=setup({compact:true});
    assert.deepEqual(folds.map(f=>f.open),[false,false,false]);
    let prevented=false;folds[0].click({preventDefault(){prevented=true;}});
    assert.equal(prevented,false,'Details must remain accessible by click/keyboard.');
});
test('tall desktop retains visible details and mobile keeps its existing disclosure layout',()=>{
    const desktop=setup();assert.deepEqual(desktop.map(f=>f.open),[true,true,true]);
    let prevented=false;desktop[0].click({preventDefault(){prevented=true;}});
    assert.equal(prevented,true);
    assert.deepEqual(setup({mobile:true}).map(f=>f.open),[true,false,false]);
});
