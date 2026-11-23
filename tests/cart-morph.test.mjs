import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const source=readFileSync(new URL('../resources/views/v2/ui-motion.blade.php',import.meta.url),'utf8');
test('production tokens set a 300ms ease-in-out morph',()=>{
    const tokens=readFileSync(new URL('../resources/views/v2/ui-tokens.blade.php',import.meta.url),'utf8');
    assert.match(tokens,/--ui-morph-duration:\s*300ms;/);
    assert.match(tokens,/--ui-morph-easing:\s*ease-in-out;/);
});
function setup(calm=false){
    const nodes=[],animations=[];
    function animate(frames,options){
        const listeners={},motion={frames,options,addEventListener:(type,fn)=>listeners[type]=fn,cancel:()=>listeners.cancel?.(),finish:()=>listeners.finish?.()};
        animations.push(motion);return motion;
    }
    const surface={style:{visibility:'',pointerEvents:''},getBoundingClientRect:()=>({left:800,top:0,width:500,height:900}),querySelector:()=>null,animate};
    const context={reduce:()=>calm,EASE:'ease-out',getComputedStyle:()=>({backgroundColor:'green',borderRadius:'12px',getPropertyValue:key=>key==='--ui-morph-duration'?'300ms':'ease-in-out'}),
        document:{documentElement:{},body:{append:node=>nodes.push(node)},createElement:()=>({style:{},setAttribute(){},remove(){this.removed=true;},animate})}};
    const start=source.indexOf('function morphFrom(');
    assert.notEqual(start,-1,'cart needs a morph from its opening bar, not a slide');
    vm.createContext(context);vm.runInContext(source.slice(start,source.indexOf('// dismiss:',start)),context);
    return {...context,surface,nodes,animations};
}
test('morph starts at the bar bounds without cloning or scaling interactive contents',()=>{
    const app=setup();
    const handle=app.morphFrom(app.surface,{left:900,top:800,width:400,height:70,borderRadius:'20px',background:'darkgreen'});
    assert.equal(app.nodes.length,1);
    const shape=app.animations.find(a=>a.frames[0].width);
    assert.equal(shape.frames[0].left,'900px');assert.equal(shape.frames[0].width,'400px');
    assert.equal(shape.frames[0].borderRadius,'20px');assert.equal(shape.frames[0].backgroundColor,'darkgreen');
    assert.equal(shape.options.duration,300);assert.equal(shape.options.easing,'ease-in-out');
    assert.equal(shape.frames.at(-1).height,'900px');
    assert.equal(app.animations.some(a=>a.frames.some(frame=>'transform' in frame)),false);
    handle.cancel();assert.equal(app.nodes[0].removed,true);
});
test('closing reverses geometry, cleans up and calls completion only when finished',()=>{
    const app=setup();
    let finished=0;
    app.morphFrom(app.surface,{left:900,top:800,width:400,height:70},{closing:true,onFinish:()=>finished++});
    const shape=app.animations.find(a=>a.frames[0].width);
    assert.equal(shape.frames[0].width,'500px');
    assert.equal(shape.frames.at(-1).width,'400px');
    assert.equal(shape.options.duration,300);assert.equal(shape.options.easing,'ease-in-out');
    shape.finish();
    assert.equal(finished,1);
    assert.equal(app.nodes[0].removed,true);
    assert.equal(app.surface.style.visibility,'');
    assert.equal(app.surface.style.pointerEvents,'');
    const handle=app.morphFrom(app.surface,{left:900,top:800,width:400,height:70},{closing:true,onFinish:()=>finished++});
    handle.cancel();assert.equal(finished,1);assert.equal(app.nodes[1].removed,true);
});
test('reduced motion shows the final surface without a ghost animation',()=>{
    const app=setup(true);
    assert.equal(app.morphFrom(app.surface,{left:1,top:1,width:40,height:40}),null);
    assert.equal(app.nodes.length,0);
});
test('opening handoff never exposes the backdrop between the skin and the real panel',()=>{
    const app=setup();
    app.morphFrom(app.surface,{left:900,top:800,width:400,height:70});
    const [shape,content]=app.animations;
    const opacityAt=(animation,time)=>{
        const {duration,delay=0}=animation.options,progress=Math.max(0,Math.min(1,(time-delay)/duration));
        const frames=animation.frames.map((frame,index)=>({...frame,offset:frame.offset??index/(animation.frames.length-1)}));
        const right=frames.findIndex(frame=>frame.offset>=progress);
        if(right===0)return frames[0].opacity;
        const a=frames[right-1],b=frames[right],fraction=(progress-a.offset)/(b.offset-a.offset);
        return a.opacity+(b.opacity-a.opacity)*fraction;
    };
    for(let time=225;time<=300;time++){
        const g=opacityAt(shape,time),c=opacityAt(content,time);
        assert.ok(g+(1-g)*c>=.99999,`transparent handoff at ${time}ms`);
    }
});
test('closing keeps the morph skin opaque until the bar takes over',()=>{
    const app=setup();
    app.morphFrom(app.surface,{left:900,top:800,width:400,height:70},{closing:true});
    assert.equal(app.animations[0].frames.at(-1).opacity,1);
});
