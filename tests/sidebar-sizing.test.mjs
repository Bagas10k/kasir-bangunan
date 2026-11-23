import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
test('submenu heights are measured from content rather than snapping between display modes',()=>{
    const source=readFileSync(new URL('../resources/views/v2/ui-sidebar-js.blade.php',import.meta.url),'utf8'),sizes=[];
    const start=source.indexOf('function measureSubmenus(');
    assert.notEqual(start,-1);
    const context={menuGroups:[84,160].map(scrollHeight=>({querySelector:()=>({scrollHeight,style:{setProperty:(name,value)=>sizes.push([name,value])}})}))};
    vm.createContext(context);vm.runInContext(source.slice(start,source.indexOf('function setPinned',start)),context);
    context.measureSubmenus();
    assert.deepEqual(sizes,[['--side-submenu-height','84px'],['--side-submenu-height','160px']]);
});
