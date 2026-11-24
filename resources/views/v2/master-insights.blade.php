{{--
    Rincian relasi master: satu dialog dipakai bersama, datanya dibaca dari JSON per baris.
    Supplier: produk yang dipasok, utang berjalan, riwayat tagihan dan pembayaran.
    Mobil: riwayat pemakaian (surat jalan dan biaya ritase) beserta sopirnya.
--}}
@php
    $insightJson = [
        'supplier' => collect($supplierInsights ?? [])->map(function (array $row) {
            $row['billed'] = \App\Services\V2\Money::display($row['billed']);
            $row['outstanding'] = \App\Services\V2\Money::display($row['outstanding']);
            $row['settled'] = \App\Services\V2\Money::display($row['settled']);
            $row['awaiting'] = \App\Services\V2\Money::display($row['awaiting']);
            $row['history'] = array_map(function (array $item) {
                $item['amount'] = \App\Services\V2\Money::display($item['amount']);
                $item['date'] = $item['date'] ? \Carbon\Carbon::parse($item['date'])->locale('id')->translatedFormat('j M Y') : '—';
                return $item;
            }, $row['history']);
            return $row;
        }),
        'vehicle' => collect($vehicleInsights ?? [])->map(function (array $row) {
            $row['trip_cost'] = \App\Services\V2\Money::display($row['trip_cost']);
            $row['last_used'] = $row['last_used'] ? \Carbon\Carbon::parse($row['last_used'])->locale('id')->translatedFormat('j M Y') : null;
            $row['history'] = array_map(function (array $item) {
                $item['amount'] = $item['amount'] === null ? null : \App\Services\V2\Money::display($item['amount']);
                $item['date'] = $item['date'] ? \Carbon\Carbon::parse($item['date'])->locale('id')->translatedFormat('j M Y') : '—';
                return $item;
            }, $row['history']);
            return $row;
        }),
    ];
@endphp
<script type="application/json" id="master-insights">@json($insightJson)</script>
<dialog class="input-dialog uf-dialog uf-dialog-wide" id="insight-dialog" aria-labelledby="insight-title" data-supplier-url="{{ route('v2.suppliers.activity', 0) }}" data-supplier-print-url="{{ route('v2.suppliers.statement', 0) }}">
    <div class="popup-heading">
        <div><h2 id="insight-title">Rincian</h2><p class="uf-dialog-sub" data-insight-sub></p></div>
        <button type="button" class="popup-close secondary" data-close-dialog aria-label="Tutup rincian">×</button>
    </div>
    <div class="popup-content insight-content">
        <dl class="insight-facts" data-insight-figures></dl>
        <section class="insight-section" data-supplier-activity hidden>
            <h3 class="ui-subhead">Aktivitas tagihan dan pembayaran</h3>
            <form id="supplier-activity-form" method="get">
                <div class="insight-range">
                    <label for="supplier-activity-from">Dari</label>
                    <input type="date" id="supplier-activity-from" name="from" data-date-range-start="supplier-activity-to" value="{{ now()->subDays(29)->toDateString() }}" max="{{ now()->toDateString() }}" required>
                    <span class="range-sep" aria-hidden="true">–</span>
                    <label for="supplier-activity-to">Sampai</label>
                    <input type="date" id="supplier-activity-to" name="to" data-date-range-end value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                </div>
                <button type="submit">Tampilkan</button>
                <a data-supplier-print hidden target="_blank" rel="noopener">Cetak semua dalam rentang</a>
            </form>
            <p data-supplier-error class="err" role="alert" hidden></p>
            <div data-supplier-results aria-live="polite"></div>
        </section>
        <section class="insight-section" data-insight-list>
            <h3 class="ui-subhead" data-insight-list-title>Rincian</h3>
            <div class="ui-table-wrap scroll" tabindex="0" role="region" aria-label="Rincian relasi">
                <table class="ui-table"><thead data-insight-head></thead><tbody data-insight-body></tbody></table>
            </div>
        </section>
        <p class="note" data-insight-note></p>
    </div>
</dialog>
<script>
(()=>{
'use strict';
const node=document.querySelector('#master-insights');const dialog=document.querySelector('#insight-dialog');
if(!node||!dialog)return;
let data={};try{data=JSON.parse(node.textContent)||{};}catch{}
const title=dialog.querySelector('#insight-title'),sub=dialog.querySelector('[data-insight-sub]'),figures=dialog.querySelector('[data-insight-figures]'),
 listTitle=dialog.querySelector('[data-insight-list-title]'),head=dialog.querySelector('[data-insight-head]'),body=dialog.querySelector('[data-insight-body]'),
 note=dialog.querySelector('[data-insight-note]'),supplierActivity=dialog.querySelector('[data-supplier-activity]');
const el=(tag,text,cls)=>{const n=document.createElement(tag);if(text!==undefined&&text!==null)n.textContent=text;if(cls)n.className=cls;return n;};
function row(cells,labels){const tr=document.createElement('tr');cells.forEach((value,i)=>{const td=el('td',value===null||value===''?'—':value);if(labels)td.dataset.label=labels[i];if(/^Rp|^-?\d/.test(String(value||''))&&i>0)td.className='ui-num';tr.append(td);});return tr;}
function headRow(labels){const tr=document.createElement('tr');labels.forEach((label,i)=>{const th=el('th',label);th.scope='col';if(i>0&&/jumlah|nilai|biaya/i.test(label))th.className='ui-num';tr.append(th);});return tr;}
function fact(label,value,primary){const box=el('div','',primary?'is-primary':'');box.append(el('dt',label));box.append(el('dd',value));return box;}
function open(kind,id,name,meta){
 const info=(data[kind]||{})[id];if(!info)return;
 figures.replaceChildren();body.replaceChildren();head.replaceChildren();
 title.textContent=name;sub.textContent=meta||'';
 supplierActivity.hidden=kind!=='supplier';
 if(kind==='supplier'){
  figures.append(fact('Utang berjalan',info.outstanding,true),fact('Sudah dibayar',info.settled),fact('Menunggu verifikasi',info.awaiting),fact('Produk dipasok',String(info.products.length)));
  listTitle.textContent='Produk yang dipasok';
  head.append(headRow(['SKU','Produk','Peran']));
  if(!info.products.length)body.append(row(['—','Belum ada produk yang menautkan supplier ini.','—'],['SKU','Produk','Peran']));
  for(const product of info.products){
   const tr=row([product.sku,product.name,''],['SKU','Produk','Peran']),cell=tr.cells[2];
   cell.append(el('span',product.primary?'Supplier utama':'Alternatif','ui-badge ui-badge--'+(product.primary?'ok':'mute')));
   if(!product.active)cell.append(el('span','Produk diarsipkan','ui-sub'));
   body.append(tr);
  }
  note.textContent='Deposit (uang muka) ke supplier belum dicatat sistem; yang tampil adalah tagihan dan pembayaran.';
 }else{
  figures.append(fact('Surat jalan',String(info.deliveries),true),fact('Ritase tercatat',String(info.trips)),fact('Biaya ritase',info.trip_cost),fact('Terakhir dipakai',info.last_used||'—'));
  listTitle.textContent='Riwayat pemakaian';
  head.append(headRow(['Tanggal','Kegiatan','Tujuan','Sopir']));
  if(!info.history.length)body.append(row(['—','Mobil belum pernah dipakai.','—','—'],['Tanggal','Kegiatan','Tujuan','Sopir']));
  for(const item of info.history)body.append(row([item.date,item.label,item.destination,item.driver],['Tanggal','Kegiatan','Tujuan','Sopir']));
  note.textContent='Sopir diambil dari surat jalan dan catatan ritase; biaya dari ritase yang sudah dibukukan.';
 }
 dialog._openerTrigger=document.activeElement;dialog.showModal();dialog.querySelector('.popup-close').focus({preventScroll:true});
 document.dispatchEvent(new CustomEvent('master-insight:open',{detail:{kind,id}}));
}
document.addEventListener('click',e=>{
 const trigger=e.target.closest('[data-insight]');if(!trigger)return;
 e.preventDefault();open(trigger.dataset.insight,trigger.dataset.insightId,trigger.dataset.insightName||trigger.textContent.trim(),trigger.dataset.insightMeta);
});
})();
</script>
@include('v2.supplier-activity-script')
