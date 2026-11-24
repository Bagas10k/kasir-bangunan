<script>
(()=>{
'use strict';
function sync(form){
    if(!form)return;
    const method=form.elements.payment_method?.value||form.elements.method?.value||form.elements.refund_method?.value||form.elements.freight_method?.value;
    const dp=String(form.elements.paid_amount?.value||'0');
    const dpTransfer=method==='credit'&&form.elements.deposit_method?.value==='transfer'&&!/^0+(?:\.0+)?$/.test(dp);
    const bankField=form.querySelector('[data-bank-account-field]');
    const bankSelect=bankField?.querySelector('select');
    if(bankField&&bankSelect){
        const showBank=method==='transfer'||method==='bank'||dpTransfer;
        bankField.hidden=!showBank;bankSelect.disabled=!showBank;bankSelect.required=showBank;
    }
    const input=form.querySelector('[name=transfer_proof]'),field=form.querySelector('[data-transfer-proof-field]');
    if(!input||!field)return;
    const transfer=method==='transfer'||dpTransfer;
    field.hidden=!transfer;input.disabled=!transfer;input.required=transfer;
    if(!transfer)input.value='';
}
function scan(root){for(const form of root.querySelectorAll('form'))sync(form);}
scan(document);window.uiLive?.onPatch(scan);
for(const type of ['change','input'])document.addEventListener(type,event=>sync(event.target.closest('form')));
document.addEventListener('ui-invoices:render',event=>scan(event.detail.root));
window.v2PaymentRequest=(payload,form)=>{
    sync(form);
    const photo=form.querySelector('[name=transfer_proof]');
    if(!photo||photo.disabled||!photo.files?.length)return {headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)};
    const body=new FormData();
    function append(key,value){
        if(value===null||value===undefined)return;
        if(typeof value==='object'){for(const [part,child]of Object.entries(value))append(key+'['+part+']',child);}
        else body.append(key,typeof value==='boolean'?(value?'1':'0'):String(value));
    }
    for(const [key,value]of Object.entries(payload))append(key,value);
    body.append('transfer_proof',photo.files[0]);
    return {headers:{},body};
};
})();
</script>
