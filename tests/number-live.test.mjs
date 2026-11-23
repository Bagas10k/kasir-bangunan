import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
test('uiLive preserves the KPI node and calls the counter instead of replacing a passive summary',()=>{
    const source=readFileSync(new URL('../resources/views/v2/ui-live.blade.php',import.meta.url),'utf8'),calls=[];
    function element(text,metric=false,children=[]){
        return {textContent:text,tagName:metric?'STRONG':'DIV',children,childNodes:[],
            matches:()=>false,hasAttribute:key=>metric&&key==='data-number-key',
            querySelector:()=>children.find(child=>child.hasAttribute('data-number-key'))||null,
            replaceChildren(){throw Error('summary replaced');}};
    }
    const oldMetric=element('Rp 100',true),newMetric=element('Rp 200',true);
    const context={window:{uiNumbers:{set:(el,text)=>calls.push({el,text})}},OPAQUE:'opaque',INTERACTIVE:'interactive',
        syncAttrs(){},passive:()=>true,meaningful:()=>false,keyOf:el=>el.tagName};
    vm.createContext(context);
    vm.runInContext(source.slice(source.indexOf('function syncNode('),source.indexOf('function patch(')),context);
    context.syncNode(element('Rp 100',false,[oldMetric]),element('Rp 200',false,[newMetric]));
    assert.equal(calls.length,1);assert.equal(calls[0].el,oldMetric);assert.equal(calls[0].text,'Rp 200');
});
