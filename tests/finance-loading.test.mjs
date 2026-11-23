import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
test('finance cold load uses the shared skeleton and an error removes the busy state',()=>{
    const source=readFileSync(new URL('../resources/views/v2/ui-workspace.blade.php',import.meta.url),'utf8');
    const placeholder={skeleton:true},attributes=new Map(),children=[];
    const panel={replaceChildren(){children.length=0;},removeAttribute:k=>attributes.delete(k),setAttribute:(k,v)=>attributes.set(k,v),append:node=>children.push(node)};
    const context={document:{getElementById:()=>({content:{cloneNode:()=>placeholder}}),createElement:()=>({setAttribute(){}})}};
    vm.createContext(context);
    vm.runInContext(source.slice(source.indexOf('function remoteBusy('),source.indexOf('function fetchRemote(')),context);
    context.remoteBusy(panel,'Memuat halaman…');
    assert.equal(children[0],placeholder);assert.equal(attributes.get('aria-busy'),'true');
    context.remoteBusy(panel,'Halaman tidak dapat dimuat di sini.');
    assert.equal(attributes.has('aria-busy'),false);
    assert.equal(children[0].textContent,'Halaman tidak dapat dimuat di sini.');
});
