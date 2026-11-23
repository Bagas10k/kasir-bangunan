import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const source=readFileSync(new URL('../resources/views/v2/ui-workspace.blade.php',import.meta.url),'utf8');
for(const role of ['cashier','admin']){
    test(`${role} payments mount only inside their own animated workspace panel`,()=>{
        function element(){
            const classes=new Set();
            return {children:[],dataset:{},classList:{add:name=>classes.add(name),contains:name=>classes.has(name)},
                setAttribute(){},matches:selector=>selector==='section',
                append(child){
                    if(child.parentElement)child.parentElement.children=child.parentElement.children.filter(node=>node!==child);
                    this.children.push(child);child.parentElement=this;
                }};
        }
        const payments=element(),cashier=element(),shift=element(),leftover=element(),host=element();
        leftover.append(payments);
        const sections={'#h-verify':payments,'#kasir':cashier,'#h-shift':shift};
        const context={navigation:{dataset:{role}},host,
            document:{createElement:element,querySelector:selector=>sections[selector]||null},
            links:['kasir','shift','persetujuan'].map(task=>({dataset:{task},textContent:task,setAttribute(){}}))};
        vm.createContext(context);
        vm.runInContext(source.slice(source.indexOf('const groups='),source.indexOf('const defaultTask=')),context);
        vm.runInContext(source.slice(source.indexOf('const owner=new Map()'),source.indexOf('function tuckKeys(')),context);
        const panel=host.children.find(node=>node.id==='panel-persetujuan');

        assert.ok(panel);assert.equal(payments.parentElement,panel);
        assert.equal(leftover.children.length,0);
        assert.equal(panel.classList.contains('ui-dna-ops'),true);
        for(const other of host.children.filter(node=>node!==panel))assert.equal(other.children.includes(payments),false);
    });
}
