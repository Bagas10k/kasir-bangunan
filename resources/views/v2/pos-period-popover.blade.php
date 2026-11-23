<script>
(()=>{
'use strict';
const root=document.getElementById('pd-custom'),trigger=document.getElementById('pd-custom-trigger'),panel=document.getElementById('pd-custom-panel');
if(!root||!trigger||!panel)return;
const closeButton=document.getElementById('pd-custom-close'),childPicker='.app-calendar,.select-options,.ui-pick-list';
let open=false;
if(panel.showPopover)panel.setAttribute('popover','manual');
function position(){
 if(!open)return;
 if(!trigger.getClientRects().length){close();return;}
 const gap=parseFloat(getComputedStyle(panel).columnGap)||0;
 const edge=gap,rect=trigger.getBoundingClientRect();
 panel.style.maxWidth=Math.max(0,innerWidth-edge*2)+'px';
 panel.style.maxHeight=Math.max(0,innerHeight-edge*2)+'px';
 const width=panel.offsetWidth,height=panel.offsetHeight;
 const fitsLeft=rect.left-gap-width>=edge,fitsRight=rect.right+gap+width<=innerWidth-edge;
 const left=fitsLeft?rect.left-gap-width:fitsRight?rect.right+gap:Math.max(edge,Math.min(rect.right-width,innerWidth-width-edge));
 const top=fitsLeft||fitsRight?Math.max(edge,Math.min(rect.top,innerHeight-height-edge)):Math.max(edge,Math.min(rect.bottom+gap,innerHeight-height-edge));
 panel.style.left='0px';panel.style.top='0px';
 const origin=panel.showPopover?{left:0,top:0}:panel.getBoundingClientRect();
 panel.style.left=(left-origin.left)+'px';panel.style.top=(top-origin.top)+'px';
 panel.style.transformOrigin=fitsLeft?'top right':'top left';
}
function close(restore=false){
 if(!open)return;
 open=false;
 for(const control of panel.querySelectorAll('[aria-controls][aria-expanded=true]')){
  const child=document.getElementById(control.getAttribute('aria-controls'));
  if(child?.matches(childPicker)&&!child.hidden)child.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
 }
 if(panel.hidePopover&&panel.matches(':popover-open'))panel.hidePopover();
 panel.hidden=true;trigger.setAttribute('aria-expanded','false');
 if(restore)trigger.focus({preventScroll:true});
}
trigger.addEventListener('click',()=>{
 if(open){close(true);return;}
 window.uiTableFilters?.close();
 panel.hidden=false;open=true;
 if(panel.showPopover)panel.showPopover();
 trigger.setAttribute('aria-expanded','true');position();closeButton.focus({preventScroll:true});
});
closeButton.addEventListener('click',()=>close(true));
document.addEventListener('pointerdown',event=>{if(open&&!root.contains(event.target)&&!event.target.closest(childPicker))close();});
document.addEventListener('keydown',event=>{
 if(!open||event.key!=='Escape'||event.defaultPrevented||event.target.closest(childPicker))return;
 if(document.querySelector('.app-calendar:not([hidden]),.select-options:not([hidden])'))return;
 event.preventDefault();close(true);
});
window.addEventListener('resize',position);
window.addEventListener('scroll',event=>{if(open&&!panel.contains(event.target)&&!event.target.closest?.(childPicker))position();},true);
new MutationObserver(()=>{if(document.body.dataset.activeTask!=='kasir')close();}).observe(document.body,{attributes:true,attributeFilter:['data-active-task']});
})();
</script>
