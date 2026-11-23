<style>
html body .ui-number-accessible{position:absolute;width:var(--ui-stroke);height:var(--ui-stroke);padding:0;margin:calc(-1 * var(--ui-stroke));overflow:hidden;clip-path:inset(50%);white-space:nowrap;border:0}
html body .ui-number-visual{font-variant-numeric:tabular-nums}
</style>
<script>
(()=>{
'use strict';
const states=new WeakMap();
const textStates=new WeakMap();
function parse(text){
 const match=String(text).trim().match(/^([−-]?)(Rp\s*)?(\d+|\d{1,3}(?:\.\d{3})+)(?:,(\d{1,4}))?$/);
 if(!match)return null;
 const places=(match[4]||'').length;
 return {value:BigInt(match[3].replace(/\./g,'')+(match[4]||''))*(match[1]?-1n:1n),places,prefix:match[2]?'Rp ':'',minus:match[1]||'−'};
}
function get(el){return textStates.get(el)?.target??states.get(el)?.target??el.textContent;}
function set(el,target,{from}={}){
 if(!el||el.matches('input,select,textarea,[contenteditable]'))return;
 target=String(target);
 const previous=states.get(el);
 if(previous?.target===target&&from===undefined)return;
 const old=parse(from??previous?.visual?.textContent??get(el)),next=parse(target);
 if(previous?.frame)cancelAnimationFrame(previous.frame);
 const state={target,frame:null};states.set(el,state);
 const duration=parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--ui-number-duration'))||0;
 if(!old||!next||old.prefix!==next.prefix||!duration||matchMedia('(prefers-reduced-motion: reduce)').matches||document.hidden||!el.getClientRects().length){
  el.textContent=target;return;
 }
 const places=Math.max(old.places,next.places),scale=10n**BigInt(places);
 const start=old.value*10n**BigInt(places-old.places),end=next.value*10n**BigInt(places-next.places);
 if(start===end){el.textContent=target;return;}
 const accessible=document.createElement('span');accessible.className='ui-number-accessible';accessible.textContent=target;
 const visual=document.createElement('span');visual.className='ui-number-visual';visual.setAttribute('aria-hidden','true');visual.textContent=from??previous?.visual?.textContent??el.textContent;
 el.replaceChildren(accessible,visual);state.visual=visual;
 const started=performance.now();
 function tick(now){
  if(states.get(el)!==state)return;
  const progress=Math.min(1,Math.max(0,(now-started)/duration));
  if(progress===1||!el.isConnected||document.hidden||matchMedia('(prefers-reduced-motion: reduce)').matches){
   visual.textContent=target;state.frame=null;return;
  }
  const eased=progress<.5?2*progress*progress:1-((-2*progress+2)**2)/2;
  const value=start+(end-start)*BigInt(Math.round(eased*10000))/10000n,absolute=value<0n?-value:value;
  const integer=(absolute/scale).toString().replace(/\B(?=(\d{3})+(?!\d))/g,'.');
  const fraction=places?(absolute%scale).toString().padStart(places,'0').replace(/0+$/,''):'';
  visual.textContent=(value<0n?next.minus:'')+next.prefix+integer+(fraction?','+fraction:'');
  state.frame=requestAnimationFrame(tick);
 }
 state.frame=requestAnimationFrame(tick);
}
function text(el,target,{from}={}){
 if(!el||el.matches('input,select,textarea,[contenteditable]'))return;
 target=String(target);
 const pieces=target.split(/(\d+(?:\.\d{3})*(?:,\d{1,4})?)/g),shape=pieces.filter((_,i)=>i%2===0).join('\u0000');
 let state=textStates.get(el);
 if(state?.target===target&&from===undefined)return;
 if(!state||state.shape!==shape){
  const previous=String(get(el)).split(/(\d+(?:\.\d{3})*(?:,\d{1,4})?)/g);
  const sameShape=previous.filter((_,i)=>i%2===0).join('\u0000')===shape;
  const numbers=[];
  el.replaceChildren(...pieces.map((part,index)=>{
   if(index%2===0)return part;
   const span=document.createElement('span');span.textContent=sameShape?previous[index]:part;numbers.push(span);return span;
  }));
  state={shape,numbers,target};textStates.set(el,state);
 }
 state.target=target;
 const oldPieces=from===undefined?null:String(from).split(/(\d+(?:\.\d{3})*(?:,\d{1,4})?)/g);
 const sameShape=oldPieces?.filter((_,i)=>i%2===0).join('\u0000')===shape;
 pieces.filter((_,i)=>i%2===1).forEach((value,index)=>set(state.numbers[index],value,{from:sameShape?oldPieces[index*2+1]:undefined}));
}
window.uiNumbers={set,get,text};
})();
</script>
