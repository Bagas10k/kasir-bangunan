<script>
(()=>{
'use strict';
const trigger=document.getElementById('pd-custom-trigger'),form=document.getElementById('pd-custom-form');
if(!trigger||!form)return;
trigger.addEventListener('click',()=>{
 window.uiTableFilters?.close();
 window.uiDatePicker?.openRange(document.getElementById('pd-from'),document.getElementById('pd-to'),trigger,()=>form.requestSubmit());
});
new MutationObserver(()=>{if(document.body.dataset.activeTask!=='kasir')window.uiDatePicker?.closeFor(trigger);}).observe(document.body,{attributes:true,attributeFilter:['data-active-task']});
})();
</script>
