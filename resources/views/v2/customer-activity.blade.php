<dialog id="customer-detail" class="input-dialog uf-dialog uf-dialog-wide" aria-labelledby="customer-detail-title">
    <div class="popup-heading">
        <div><h2 id="customer-detail-title">Detail pelanggan</h2><p class="uf-dialog-sub" data-customer-identity></p></div>
        <button type="button" class="popup-close secondary" data-close-dialog aria-label="Tutup detail pelanggan">×</button>
    </div>
    <div class="popup-content detail-content">
        <section class="detail-section" aria-labelledby="customer-activity-title">
            <h3 id="customer-activity-title">Riwayat transaksi</h3>
            <div class="detail-actions" role="group" aria-label="Periode transaksi pelanggan">
                @foreach(['day'=>'Harian','week'=>'Mingguan','month'=>'Bulanan'] as $period => $label)
                    <button type="button" class="secondary" data-customer-period="{{ $period }}" aria-pressed="{{ $period === 'week' ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="detail-results" data-customer-results aria-live="polite"></div>
        </section>
    </div>
</dialog>
<script>
(()=>{
    const dialog=document.getElementById('customer-detail');
    if(!dialog)return;
    const results=dialog.querySelector('[data-customer-results]');
    let endpoint='',period='week',request;
    async function load(page=1){
        request?.abort();
        const active=request=new AbortController();
        const url=new URL(endpoint,location.href);
        url.searchParams.set('history_period',period);
        url.searchParams.set('history_page',page);
        results.textContent='Memuat transaksi…';
        for(const button of dialog.querySelectorAll('[data-customer-period]')){
            button.setAttribute('aria-pressed',String(button.dataset.customerPeriod===period));
        }
        try{
            const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'},signal:active.signal});
            if(!response.ok||response.redirected)throw Error('load');
            const {html}=await response.json();
            if(request===active&&!active.signal.aborted&&dialog.open)results.innerHTML=html;
        }catch(error){
            if(request===active&&error.name!=='AbortError')results.textContent='Riwayat belum bisa dimuat. Pilih periode untuk mencoba lagi.';
        }
    }
    document.addEventListener('click',event=>{
        const trigger=event.target.closest('[data-customer-detail]');
        if(!trigger)return;
        endpoint=trigger.dataset.customerDetail;
        period='week';
        dialog.querySelector('[data-customer-identity]').textContent=trigger.dataset.detailName+' · Kode '+trigger.dataset.detailCode;
        dialog._openerTrigger=trigger.closest('td')?.querySelector('.ui-actions-toggle')||trigger;
        if(!dialog.open)dialog.showModal();
        dialog.querySelector('.popup-close').focus({preventScroll:true});
        load();
    });
    dialog.addEventListener('click',event=>{
        const range=event.target.closest('[data-customer-period]'),page=event.target.closest('[data-customer-page]');
        if(range){period=range.dataset.customerPeriod;load();}
        else if(page)load(page.dataset.customerPage);
    });
    dialog.addEventListener('close',()=>request?.abort());
})();
</script>
