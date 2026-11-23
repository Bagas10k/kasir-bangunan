<script>
(()=>{
const form=document.getElementById('receipt-form'),D=window.KasirDecimal;
if(!form||!D)return;
function summary(){
    const target=form.querySelector('[data-freight-summary]');if(!target)return;
    try{
        let goods=0n;
        for(const row of form.querySelectorAll('#receipt-items>.rc-row')){
            const value=name=>row.querySelector('[data-name='+name+']')?.value||'0';
            goods+=D.divide(D.scaled(value('quantity'),4)*D.scaled(value('unit_cost'),4),1000000n);
        }
        const fee=D.scaled(form.elements.freight_amount?.value||'0',2),method=form.elements.freight_method?.value;
        target.textContent='Ongkir '+D.money(fee)+' · Total biaya '+D.money(goods+fee)+' · Utang supplier '+D.money(goods+(method==='supplier'?fee:0n))+' · Pengeluaran kas/bank '+D.money(method==='supplier'?0n:fee)+'. Ongkir tidak menambah HPP.';
    }catch{target.textContent='Periksa jumlah, HPP satuan, dan ongkir sebelum menyimpan.';}
}
form.addEventListener('input',summary);form.addEventListener('change',summary);
form.querySelector('#receipt-review')&&new MutationObserver(summary).observe(form.querySelector('#receipt-review'),{attributes:true,attributeFilter:['hidden']});
summary();
})();
</script>
