{{--
    Sistem keterangan & tooltip bersama semua halaman (Tooltip, Info Tooltip, Rich Tooltip, Toggletip, Hover Card).
    - data-tip="teks" (atau title lama): Tooltip singkat saat hover/fokus (dan tap di HP untuk badge status).
    - data-tip-key="kunci": merender judul + teks dari kamus terpusat (Rich Tooltip / Toggletip) agar DOM tetap ringan.
    - data-toggletip / .ui-info-btn: Info Tooltip + Toggletip (hover/fokus di desktop, klik/tap untuk mengunci terbuka di desktop & HP).
    - data-tip-truncate: hanya tampil bila teks benar-benar terpotong.
    - data-hover-card: Hover Card ringkas baca-saja untuk tautan entitas master di desktop.
    Memakai popover="manual" agar tampil di atas <dialog> modal dan tidak terpotong overflow. Tanpa dependensi.
--}}
<style>
.ui-tip{position:fixed;inset:auto;margin:0;z-index:100200;max-width:min(22rem,calc(100vw - 1.5rem));padding:8px 11px;border:1px solid rgb(200 242 107 / .22);border-radius:10px;background:var(--ui-green-2,#0f4224);color:var(--ui-surface,#fff);font:500 .8125rem/1.4 var(--font-body,system-ui,sans-serif);box-shadow:0 12px 28px rgb(15 42 29 / .32);pointer-events:none;opacity:0;white-space:normal;overflow-wrap:anywhere;text-align:left}
.ui-tip.is-rich{max-width:min(24rem,calc(100vw - 1.5rem));padding:10px 13px}
.ui-tip.is-pinned{pointer-events:auto}
.ui-tip.is-on{opacity:1;animation:var(--ui-float-in,ui-tip-in .18s cubic-bezier(.34,1.56,.64,1))}
.ui-tip[hidden]{display:none!important}
.ui-tip:not([hidden]):not(:popover-open){display:block}
.ui-tip-title{display:block;margin:0 0 3px;font-size:.8125rem;font-weight:700;line-height:1.3;color:#c8f26b}
.ui-tip-body{display:block;margin:0;font-size:.8125rem;font-weight:500;line-height:1.42;color:#f1f5f2}
.ui-tip-note{display:block;margin:5px 0 0;padding-top:5px;border-top:1px solid rgb(255 255 255 / .14);font-size:.75rem;font-weight:500;line-height:1.38;color:#d6e8dc}
@keyframes ui-tip-in{from{opacity:0;transform:translateY(-4px) scale(.96)}to{opacity:1;transform:none}}

/* Tombol ikon informasi (i): ringkas di samping label, target sentuh 44px di layar sempit */
.ui-info-btn{position:relative;display:inline-flex;align-items:center;justify-content:center;flex:none;width:20px!important;height:20px!important;min-width:20px!important;min-height:20px!important;margin:0 0 0 4px!important;padding:0!important;border:0!important;border-radius:999px!important;background:rgb(20 83 45 / .1)!important;color:var(--ui-green,#14532d)!important;vertical-align:middle;cursor:pointer;box-shadow:none!important;transition:background-color .14s ease,color .14s ease}
.ui-info-btn:hover,.ui-info-btn[aria-expanded=true]{background:var(--ui-green,#14532d)!important;color:#fff!important}
.ui-info-btn:focus-visible{outline:2px solid var(--ui-focus,#0b5cab)!important;outline-offset:2px}
.ui-info-btn svg{display:block;width:13px;height:13px;pointer-events:none}
.ui-info-btn--dark{background:rgb(255 255 255 / .16)!important;color:#e4f0e8!important}
.ui-info-btn--dark:hover,.ui-info-btn--dark[aria-expanded=true]{background:#c8f26b!important;color:#102a1d!important}
.ui-info-btn--dark:focus-visible{outline-color:#c8f26b!important}
@media(max-width:760px){
  .ui-info-btn::after{content:'';position:absolute;inset:-12px}
}

/* Hover Card ringkas untuk preview entitas master di desktop */
.ui-hover-card{position:fixed;inset:auto;margin:0;z-index:100190;width:min(19.5rem,calc(100vw - 1.5rem));padding:12px 14px;border:1px solid var(--ui-line,#d2ddd5);border-radius:var(--ui-radius,12px);background:var(--ui-surface,#fff);color:var(--ui-ink,#172b20);box-shadow:0 18px 40px -12px rgb(9 30 18 / .24);text-align:left;opacity:0}
.ui-hover-card.is-on{opacity:1;animation:var(--ui-float-in,ui-tip-in .18s ease-out)}
.ui-hover-card[hidden]{display:none!important}
.ui-hover-card:not([hidden]):not(:popover-open){display:block}
.ui-hc-head{display:flex;flex-direction:column;gap:2px;margin:0 0 8px;padding-bottom:8px;border-bottom:1px solid var(--ui-line-soft,#e6eee8)}
.ui-hc-head strong{font-size:.875rem;font-weight:700;line-height:1.3;color:var(--ui-ink,#172b20)}
.ui-hc-head small{font-size:.75rem;color:var(--ui-ink-2,#33483b)}
.ui-hc-rows{display:grid;gap:4px;margin:0;font-size:.8125rem}
.ui-hc-rows div{display:flex;justify-content:space-between;gap:12px}
.ui-hc-rows dt{color:var(--ui-ink-2,#33483b)}
.ui-hc-rows dd{margin:0;font-weight:700;color:var(--ui-ink,#172b20);font-variant-numeric:tabular-nums;text-align:right}
.ui-hc-foot{margin:8px 0 0;padding-top:6px;border-top:1px solid var(--ui-line-soft,#e6eee8);font-size:.75rem;font-weight:600;color:var(--ui-green,#14532d)}

@media(prefers-reduced-motion:reduce){.ui-tip,.ui-tip.is-on,.ui-hover-card,.ui-hover-card.is-on,.ui-info-btn{animation:none!important;transition:none!important}}
@media print{.ui-tip,.ui-hover-card,.ui-info-btn{display:none!important}}
</style>
<script>
(()=>{
'use strict';
if(window.uiTooltip)return;
const CATALOG={
 harga_modal:{title:'Harga Modal (HPP)',body:'Modal atau harga pokok per 1 satuan barang (bukan total modal seluruh stok).',note:'Otomatis dihitung ulang memakai rata-rata tertimbang saat penerimaan barang dikonfirmasi, dan terkunci jika produk sudah punya stok atau mutasi.'},
 margin_keuntungan:{title:'Margin Keuntungan',body:'Selisih harga jual terhadap harga modal per satuan barang.',note:'Persentase dihitung sebagai (Harga Jual − Harga Modal) ÷ Harga Modal × 100%. Ditampilkan terpisah untuk harga eceran dan harga kontraktor.'},
 harga_kontraktor:{title:'Harga Kontraktor',body:'Tarif khusus untuk pelanggan kontraktor atau proyek.',note:'Otomatis aktif di Kasir saat memilih pelanggan bertanda Kontraktor. Jika dikosongkan, sistem memakai harga jual biasa.'},
 stok_minimum:{title:'Stok Minimum',body:'Batas peringatan stok menipis untuk pengingat restok.',note:'Produk dengan stok di bawah atau sama dengan batas ini otomatis ditandai Menipis di katalog dan Ringkasan Admin.'},
 stok_saat_ini:{title:'Stok Saat Ini',body:'Jumlah fisik barang yang tercatat saat ini beserta satuannya.',note:'Stok hanya berubah melalui transaksi penjualan, penerimaan barang final, atau penyesuaian stok resmi.'},
 harga_tier_kasir:{title:'Tarif Eceran vs Kontraktor',body:'Menentukan daftar harga yang diterapkan ke seluruh barang di keranjang.',note:'Pelanggan bertanda Kontraktor otomatis mengaktifkan tarif ini, dan kasir tetap bisa menggantinya bila perlu.'},
 status_kas_harian:{title:'Kas Harian Toko',body:'Satu catatan laci bersama per tanggal yang dibuka dan ditutup oleh admin.',note:'Transaksi penjualan di Kasir hanya dapat disimpan selama kas harian berstatus aktif.'},
 metode_bayar_kasir:{title:'Metode Pembayaran Kasir',body:'Tunai langsung masuk uang laci. Transfer wajib foto bukti dan menunggu verifikasi admin. Kredit wajib memilih pelanggan terdaftar dan sisanya menjadi piutang.'},
 sumber_dana_supplier:{title:'Sumber Dana Pembayaran',body:'Kas Utama & Bank Utama memakai saldo toko tanpa mengurangi uang laci kasir.',note:'Laci Kas Harian mengurangi uang tunai di laci yang sedang terbuka.'},
 uang_muka_sopir:{title:'Uang Muka Sopir',body:'Dana perjalanan yang diserahkan ke sopir sebelum berangkat.',note:'Belum dicatat sebagai beban biaya sampai formulir realisasi (BBM, uang jalan, dan pengembalian sisa) disimpan.'},
 hpp_penerimaan:{title:'HPP Satuan Pembelian',body:'Harga beli per satuan sesuai faktur supplier.',note:'Saat draf dikonfirmasi, nilai ini memperbarui HPP rata-rata tertimbang produk dan mencatat utang supplier.'},
 ongkir_penerimaan:{title:'Ongkir Pembelian',body:'Biaya kirim yang dibebankan langsung ke akun beban operasional terpilih.',note:'Ongkir tidak menambah nilai HPP per satuan barang di persediaan.'},
 draf_penerimaan:{title:'Draf vs Konfirmasi Final',body:'Draf penerimaan dapat diubah atau dibatalkan dan belum memengaruhi stok, HPP, atau utang.',note:'Setelah dikonfirmasi dan dikunci, dokumen bersifat final.'},
 selisih_kas_riwayat:{title:'Selisih Bulan Ini (Riwayat)',body:'Total neto selisih seluruh kas harian yang sudah ditutup pada bulan berjalan, termasuk yang sudah selesai diperiksa admin.'},
 selisih_belum_selesai:{title:'Belum Diselesaikan',body:'Total selisih dan jumlah kas harian berstatus Perlu Diperiksa dari semua tanggal.',note:'Ini antrean pemeriksaan admin, bukan saldo uang kas.'},
 transfer_menunggu:{title:'Transfer Menunggu Verifikasi',body:'Total pembayaran transfer pelanggan yang bukti/mutasinya belum diverifikasi admin.',note:'Nominal ini belum masuk ke saldo Bank terverifikasi.'},
 laba_rugi_hpp:{title:'Beban Pokok Penjualan (HPP)',body:'Total harga modal dari barang-barang yang sudah terjual pada periode laporan.',note:'Berbeda dengan nilai pembelian stok baru yang masih tersimpan sebagai aset persediaan.'},
 neraca_seimbang:{title:'Keseimbangan Neraca',body:'Total Aset harus sama persis dengan Total Kewajiban + Ekuitas (termasuk laba/rugi berjalan).',note:'Jika muncul selisih, periksa kembali saldo awal atau entri jurnal.'},
 arus_kas_langsung:{title:'Metode Langsung Arus Kas',body:'Merangkum arus kas masuk dan keluar nyata pada Kas Tunai (1101) dan seluruh Rekening Bank yang aktif/tercatat selama periode laporan.'},
 coa_akun_inti:{title:'Akun Inti Sistem',body:'Akun bawaan pembukuan otomatis (Kas, Bank, Piutang, Persediaan, Utang, Penjualan, HPP).',note:'Kode dan status aktifnya dikunci agar jurnal otomatis tidak gagal. Akun Aset lain dapat ditandai sebagai Rekening Bank agar tampil di metode pembayaran.'},
 pengeluaran_operasional:{title:'Pengeluaran Operasional',body:'Biaya harian toko di luar penjualan dan pembelian barang dagangan (listrik, ATK, upah).',note:'Langsung memotong saldo Kas Tunai (1101) atau Rekening Bank terpilih dan mengunci jurnal.'},
 akun_login_peran:{title:'Hak Akses Peran',body:'Admin dapat mengelola semua menu termasuk master, penerimaan, dan keuangan.',note:'Kasir hanya mengakses operasional penjualan, tagihan, pengiriman, dan laporan miliknya.'}
};

const tip=document.createElement('div');
tip.className='ui-tip';tip.id='ui-tooltip';tip.setAttribute('role','tooltip');tip.hidden=true;
if(tip.showPopover)tip.setAttribute('popover','manual');

const card=document.createElement('div');
card.className='ui-hover-card';card.id='ui-hover-card';card.setAttribute('role','region');card.setAttribute('aria-label','Pratinjau ringkas');card.hidden=true;
if(card.showPopover)card.setAttribute('popover','manual');

let target=null,pinned=false,showTimer=0,hideTimer=0;
let hcTarget=null,hcShow=0,hcHide=0;
const clean=value=>String(value||'').replace(/\s+/g,' ').trim();
const skip='[data-tip-skip],.app-calendar,.rail-tip,.rail-flyout,.workspace-sidebar,.workspace-navigation,.pos-combo-list,.select-options';

function candidate(node){return node?.closest?.('[data-tip-key],[data-toggletip],[data-tip],[data-tip-truncate],[title]')||null;}
function isInteractiveToggletip(el){return Boolean(el?.hasAttribute('data-toggletip')||el?.classList?.contains('ui-info-btn'));}

const INFO_SVG='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>';
function hydrateInfoButtons(root){
 const list=[];
 if(root?.matches?.('.ui-info-btn'))list.push(root);
 if(root?.querySelectorAll)list.push(...root.querySelectorAll('.ui-info-btn'));
 for(const btn of list){
  if(!btn.hasAttribute('data-toggletip'))btn.setAttribute('data-toggletip','');
  if(!btn.hasAttribute('aria-expanded'))btn.setAttribute('aria-expanded','false');
  if(!btn.hasAttribute('aria-controls'))btn.setAttribute('aria-controls',tip.id);
  if(!btn.getAttribute('aria-label')){
   const entry=CATALOG[btn.dataset.tipKey];
   btn.setAttribute('aria-label',entry?.title?'Keterangan '+entry.title:'Keterangan');
  }
  if(!btn.firstElementChild)btn.innerHTML=INFO_SVG;
 }
}

function resolvePayload(el){
 if(!el)return null;
 const key=el.dataset.tipKey;
 if(key&&CATALOG[key])return CATALOG[key];
 if(el.dataset.tip){
  const text=clean(el.dataset.tip);
  if(!text)return null;
  return el.dataset.tipTitle?{title:clean(el.dataset.tipTitle),body:text}:text;
 }
 if(el.hasAttribute('data-tip-truncate')){
  return el.scrollWidth>el.clientWidth+1?clean(el.textContent)||null:null;
 }
 const title=el.getAttribute('title');
 return title?clean(title)||null:null;
}

function renderTip(payload){
 tip.replaceChildren();
 if(typeof payload==='string'){
  tip.classList.remove('is-rich');
  tip.textContent=payload;
  return;
 }
 tip.classList.add('is-rich');
 if(payload.title){
  const t=document.createElement('strong');t.className='ui-tip-title';t.textContent=payload.title;tip.append(t);
 }
 if(payload.body){
  const b=document.createElement('span');b.className='ui-tip-body';b.textContent=payload.body;tip.append(b);
 }
 if(payload.note){
  const n=document.createElement('span');n.className='ui-tip-note';n.textContent=payload.note;tip.append(n);
 }
}

function mount(node){
 if(!node.isConnected)document.body.append(node);
 // Pastikan elemen berada di top-layer (atau di atas dialog modal aktif) dengan re-append bila perlu
 const topDialog=document.querySelector('dialog[open]');
 if(topDialog&&!node.showPopover&&node.parentElement!==topDialog)topDialog.append(node);
 else if(!topDialog&&node.parentElement!==document.body)document.body.append(node);
}

function openLayer(node){
 mount(node);
 node.hidden=false;
 try{if(node.showPopover&&!node.matches(':popover-open'))node.showPopover();}catch{}
}
function closeLayer(node){
 try{if(node.hidePopover&&node.matches(':popover-open'))node.hidePopover();}catch{}
 node.hidden=true;
}

function placeBox(anchor,box,preferredSide='top'){
 const r=anchor.getBoundingClientRect(),t=box.getBoundingClientRect(),gap=8,margin=10;
 let side=preferredSide;
 let x=r.left+r.width/2-t.width/2,y=r.top-t.height-gap;
 if(side==='bottom'){x=r.left+r.width/2-t.width/2;y=r.bottom+gap;}
 else if(side==='left'){x=r.left-t.width-gap;y=r.top+r.height/2-t.height/2;}
 else if(side==='right'){x=r.right+gap;y=r.top+r.height/2-t.height/2;}
 if(side==='top'&&y<margin){y=r.bottom+gap;side='bottom';}
 else if(side==='bottom'&&y+t.height>innerHeight-margin){y=r.top-t.height-gap;side='top';}
 if(side==='left'&&x<margin)x=r.right+gap;
 if(side==='right'&&x+t.width>innerWidth-margin)x=r.left-t.width-gap;
 const clampedX=Math.round(Math.max(margin,Math.min(x,innerWidth-margin-t.width)));
 const clampedY=Math.round(Math.max(margin,Math.min(y,innerHeight-margin-t.height)));
 box.style.left=clampedX+'px';
 box.style.top=clampedY+'px';
 box.style.transformOrigin=(side==='bottom'?'top':'bottom')+' center';
}

function show(el,pin=false){
 if(!el||el.closest(skip))return;
 if(el===target&&pinned===pin)return;
 const payload=resolvePayload(el);if(!payload)return;
 clearTimeout(hideTimer);clearTimeout(showTimer);
 if(target&&target!==el)hide(true);
 target=el;pinned=Boolean(pin);
 renderTip(payload);
 tip.classList.toggle('is-pinned',pinned);
 openLayer(tip);
 el._tipDescribedby=el.getAttribute('aria-describedby');
 el.setAttribute('aria-describedby',[el._tipDescribedby,tip.id].filter(Boolean).join(' '));
 if(isInteractiveToggletip(el))el.setAttribute('aria-expanded','true');
 if(!el.dataset.tip&&!el.dataset.tipKey&&el.hasAttribute('title')){el._tipTitle=el.getAttribute('title');el.removeAttribute('title');}
 requestAnimationFrame(()=>{if(target!==el)return;tip.classList.add('is-on');placeBox(el,tip,el.dataset.tipPlacement||'top');});
}

function hide(immediate=false){
 if(!target)return;
 const el=target;target=null;pinned=false;clearTimeout(showTimer);clearTimeout(hideTimer);
 tip.classList.remove('is-on','is-pinned');
 if(el._tipDescribedby)el.setAttribute('aria-describedby',el._tipDescribedby);else el.removeAttribute('aria-describedby');
 delete el._tipDescribedby;
 if(isInteractiveToggletip(el))el.setAttribute('aria-expanded','false');
 if(el._tipTitle!==undefined){el.setAttribute('title',el._tipTitle);delete el._tipTitle;}
 if(immediate||matchMedia('(prefers-reduced-motion:reduce)').matches){closeLayer(tip);return;}
 hideTimer=setTimeout(()=>{if(!target)closeLayer(tip);},140);
}

// Hover Card (khusus desktop mouse, baca-saja)
function showHoverCard(el){
 if(!el||el.closest(skip))return;
 let rows=[];try{rows=JSON.parse(el.dataset.hcRows||'[]');}catch{}
 const title=el.dataset.hcTitle||el.textContent.trim();
 if(!title&&!rows.length)return;
 clearTimeout(hcHide);hcTarget=el;
 card.replaceChildren();
 const head=document.createElement('div');head.className='ui-hc-head';
 const strong=document.createElement('strong');strong.textContent=title;head.append(strong);
 if(el.dataset.hcMeta){const sm=document.createElement('small');sm.textContent=el.dataset.hcMeta;head.append(sm);}
 card.append(head);
 if(rows.length){
  const dl=document.createElement('dl');dl.className='ui-hc-rows';
  for(const [k,v]of rows){const row=document.createElement('div');const dt=document.createElement('dt');dt.textContent=k;const dd=document.createElement('dd');dd.textContent=v;row.append(dt,dd);dl.append(row);}
  card.append(dl);
 }
 if(el.dataset.hcFoot){const ft=document.createElement('p');ft.className='ui-hc-foot';ft.textContent=el.dataset.hcFoot;card.append(ft);}
 openLayer(card);
 requestAnimationFrame(()=>{if(hcTarget!==el)return;card.classList.add('is-on');placeBox(el,card,'bottom');});
}
function hideHoverCard(immediate=false){
 if(!hcTarget)return;
 hcTarget=null;clearTimeout(hcShow);clearTimeout(hcHide);
 card.classList.remove('is-on');
 if(immediate||matchMedia('(prefers-reduced-motion:reduce)').matches){closeLayer(card);return;}
 hcHide=setTimeout(()=>{if(!hcTarget)closeLayer(card);},120);
}

document.addEventListener('pointerover',event=>{
 if(event.pointerType&&event.pointerType!=='mouse')return;
 const hc=event.target.closest?.('[data-hover-card]');
 if(hc&&hc!==hcTarget){clearTimeout(hcShow);hcShow=setTimeout(()=>showHoverCard(hc),240);}
 if(card.contains(event.target))clearTimeout(hcHide);
 if(pinned)return;
 const el=candidate(event.target);if(!el||el===target)return;
 clearTimeout(showTimer);showTimer=setTimeout(()=>show(el,false),140);
});
document.addEventListener('pointerout',event=>{
 const hc=event.target.closest?.('[data-hover-card]');
 if(hc&&hc===hcTarget&&!hc.contains(event.relatedTarget)&&!card.contains(event.relatedTarget)){clearTimeout(hcShow);hcHide=setTimeout(()=>hideHoverCard(),120);}
 if(card.contains(event.target)&&!card.contains(event.relatedTarget)&&!hcTarget?.contains(event.relatedTarget)){hcHide=setTimeout(()=>hideHoverCard(),120);}
 if(pinned)return;
 const el=candidate(event.target);if(!el||el!==target)return;
 if(el.contains(event.relatedTarget))return;
 clearTimeout(showTimer);hide();
});
document.addEventListener('click',event=>{
 const el=candidate(event.target);
 if(el&&isInteractiveToggletip(el)){
  event.preventDefault();event.stopPropagation();
  if(target===el&&pinned){hide(true);}else{show(el,true);}
  return;
 }
 // Pada layar sentuh (atau klik badge status), izinkan tap membuka keterangan badge
 if(el&&el.matches('.ui-badge[data-tip]')&&matchMedia('(hover: none)').matches){
  event.stopPropagation();
  if(target===el&&pinned){hide(true);}else{show(el,true);}
  return;
 }
 if(target&&pinned&&!tip.contains(event.target)&&!target.contains(event.target))hide(true);
});
document.addEventListener('focusin',event=>{
 const el=candidate(event.target);
 if(!el||el.closest(skip))return;
 if(pinned&&target&&target!==el)hide(true);
 if(el.matches(':focus-visible')){clearTimeout(showTimer);show(el,false);}
});
document.addEventListener('focusout',event=>{if(target&&!pinned&&event.target===target)hide();});
document.addEventListener('keydown',event=>{
 if(event.key==='Escape'){
  const prev=pinned?target:null;
  hide(true);hideHoverCard(true);
  if(prev&&prev.isConnected)prev.focus({preventScroll:true});
 }
});
document.addEventListener('pointerdown',event=>{
 if(target&&!target.contains(event.target)&&!tip.contains(event.target))hide(true);
 if(hcTarget&&!hcTarget.contains(event.target)&&!card.contains(event.target))hideHoverCard(true);
},true);
addEventListener('scroll',()=>{if(!pinned)hide(true);hideHoverCard(true);},true);
addEventListener('resize',()=>{hide(true);hideHoverCard(true);});
hydrateInfoButtons(document);
if(typeof MutationObserver==='function'){new MutationObserver(records=>{for(const r of records){for(const n of r.addedNodes){if(n.nodeType===1)hydrateInfoButtons(n);}}}).observe(document.body,{childList:true,subtree:true});}
window.uiLive?.onPatch(hydrateInfoButtons);
window.uiTooltip={hide:()=>hide(true),show,hydrate:hydrateInfoButtons,catalog:CATALOG};
})();
</script>