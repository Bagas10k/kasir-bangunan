import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const source=readFileSync(new URL('../resources/views/v2/ui-workspace.blade.php',import.meta.url),'utf8');
function setup(href='http://example.test/kasir-bangunan/?date=2026-11-21&master_kind=product&partial=1&utm_source=fixture#master-product'){
    const report={defaultValue:'2026-11-21',value:'2026-11-24'};
    const context={URL,location:{href},document:{getElementById:id=>id==='report-date'?report:null}};
    vm.createContext(context);
    const start=source.indexOf('function workspaceUrl(');
    if(start>=0)vm.runInContext(source.slice(start,source.indexOf('function select(',start)),context);
    return {context,report};
}
test('master URLs remove unrelated workspace parameters while preserving path, hash and unknown parameters',()=>{
    const {context,report}=setup();
    const url=context.workspaceUrl('master-product');
    assert.equal(url.pathname,'/kasir-bangunan/');
    assert.equal(url.hash,'#master-product');
    assert.equal(url.search,'?utm_source=fixture');
    assert.equal(report.value,'2026-11-24');
    assert.equal(report.defaultValue,'2026-11-21');
});
test('returning to reports restores the applied date rather than an unsubmitted draft',()=>{
    const {context,report}=setup();
    context.location.href=context.workspaceUrl('master-product').href;
    const url=context.workspaceUrl('laporan');
    assert.equal(url.searchParams.get('date'),'2026-11-21');
    assert.equal(report.value,'2026-11-24');
});
test('menus other than reports do not inherit the report date',()=>{
    for(const task of ['kasir','ringkasan','shift','master-supplier','riwayat-penjualan','tagihan','jurnal']){
        const {context}=setup();
        assert.equal(context.workspaceUrl(task).searchParams.has('date'),false,task);
    }
});
test('initial deep links and real menu switching both clean the address without touching draft inputs',()=>{
    const {context,report}=setup();
    const nodes={setAttribute(){},textContent:''};
    const own={dataset:{},textContent:'Menu sintetis'};
    context.document.querySelector=()=>nodes;
    context.document.body={dataset:{},toggleAttribute(){},classList:{contains:()=>false}};
    context.active=null;context.defaultTask='master-product';
    context.panels=new Map(['master-product','laporan'].map(key=>[key,{}]));
    context.owner=new Map(['master-product','laporan'].map(key=>[key,own]));
    context.tabsOf=new Map();context.links=[];context.remoteState=new Map();
    context.text={'master-product':['Produk','Katalog'],laporan:['Laporan','Laporan harian']};
    context.store=()=>{};context.history={state:{fixture:true},replaceState(state,title,url){assert.equal(state.fixture,true);context.location.href=String(url);}};
    const start=source.indexOf('function select(');
    vm.runInContext(source.slice(start,source.indexOf('for(const link of links){link.addEventListener(',start)),context);
    context.select('master-product',{updateURL:false});
    assert.equal(new URL(context.location.href).searchParams.has('date'),false);
    context.select('laporan');
    assert.equal(new URL(context.location.href).hash,'#laporan');
    assert.equal(new URL(context.location.href).searchParams.get('date'),'2026-11-21');
    context.select('master-product');
    assert.equal(new URL(context.location.href).hash,'#master-product');
    assert.equal(new URL(context.location.href).search,'?utm_source=fixture');
    assert.equal(report.value,'2026-11-24');
});
test('switching a subtab inside the same menu skips entrance animation and scroll reset',()=>{
    const {context}=setup();
    const nodes={setAttribute(){},textContent:''};
    const supplierLink={dataset:{},textContent:'Supplier & pelanggan'};
    const reportLink={dataset:{},textContent:'Laporan'};
    context.document.querySelector=()=>nodes;
    context.document.body={dataset:{},toggleAttribute(){},classList:{contains:()=>true}};
    context.active=null;context.defaultTask='master-supplier';
    context.panels=new Map(['master-supplier','master-customer','laporan'].map(key=>[key,{}]));
    context.owner=new Map([['master-supplier',supplierLink],['master-customer',supplierLink],['laporan',reportLink]]);
    context.tabsOf=new Map([['master-supplier',{masterSupplier:'Supplier'}],['master-customer',{masterCustomer:'Pelanggan'}]]);
    context.links=[];context.remoteState=new Map();
    context.text={'master-supplier':['Supplier','Pemasok'],'master-customer':['Pelanggan','Pembeli'],laporan:['Laporan','Laporan harian']};
    context.store=()=>{};context.history={state:{},replaceState(){}};
    let enters=0,scrolls=0,fades=0;
    context.window={uiMotion:{enter(){enters++;},textIn(){}},scrollTo(){scrolls++;}};
    context.panels.get('master-customer').children=[{classList:{contains:()=>false},animate(){fades++;}}];
    const start=source.indexOf('function select(');
    vm.runInContext(source.slice(start,source.indexOf('for(const link of links){link.addEventListener(',start)),context);
    context.select('master-supplier',{updateURL:false});
    enters=0;scrolls=0;fades=0;
    context.select('master-customer');
    assert.equal(enters,0,'subtab tidak memutar animasi masuk');
    assert.equal(scrolls,0,'subtab tidak mengembalikan gulir ke atas');
    assert.equal(fades,1,'subtab tetap memakai transisi ringan pada isi');
    context.select('laporan');
    assert.equal(enters,1,'pindah menu tetap memakai animasi masuk');
    assert.equal(scrolls,1,'pindah menu tetap kembali ke atas');
});
test('workspace refresh uses the applied report date without polluting the visible menu URL',async()=>{
    const live=readFileSync(new URL('../resources/views/v2/ui-live.blade.php',import.meta.url),'utf8');
    const href='http://example.test/kasir-bangunan/#master-product',requests=[];
    const context={URL,location:{href,reload(){throw Error('unexpected reload');}},running:null,again:false,
        document:{getElementById:()=>({defaultValue:'2026-11-21',value:'2026-11-24'}),dispatchEvent(){}},
        fetch:async(url,options)=>{requests.push({url:String(url),options});return {ok:true,redirected:false,text:async()=>'<main></main>'};},
        DOMParser:class{parseFromString(){return {querySelector:()=>({})};}},patch(){},window:{},CustomEvent:class{},console};
    vm.createContext(context);
    vm.runInContext(live.slice(live.indexOf('function refresh(){'),live.indexOf('/**\n *',live.indexOf('function refresh(){'))),context);
    assert.equal(await context.refresh(),true);
    assert.equal(new URL(requests[0].url).searchParams.get('date'),'2026-11-21');
    assert.equal(context.location.href,href);
    assert.equal(requests[0].options.credentials,'same-origin');
});
