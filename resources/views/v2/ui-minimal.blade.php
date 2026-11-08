<style>
/* ============================================================
   LAPIS MINIMALIS — dimuat PALING AKHIR, sesudah v2/ui-theme.
   Tiga aturan: isi halaman didominasi putih, warna mengikuti sidebar
   (hijau gelap) bukan hijau terang, dan tidak ada ruang kosong berlebih.
   Ukuran huruf tidak dikecilkan (tetap terbaca); yang dipangkas padding.
   ============================================================ */
:root{
  --ui-accent:#14532d;            /* aksen isi = hijau sidebar, bukan hijau terang */
  --ui-accent-2:#0f4224;
  --ui-accent-soft:#eef4f0;
  --ui-on-accent:#fff;
  --ui-line:#e2e9e4;
  --ui-line-soft:#eef2ef;
  --ui-head:#f7faf8;
  --ui-hover:#f6faf7;
  --ui-canvas:#fff;
  --ui-gap:12px;                  /* jarak antar blok dalam panel */
  --ui-pad:16px;                  /* padding dalam kartu */
  /* Token ukuran dipakai banyak aturan lama; mengubahnya di sini membuat
     seluruh menu ikut rapat tanpa menimpa satu per satu. */
  --ui-h1:1.3125rem;--ui-h2:1.0625rem;
  --ui-tap:36px;--ui-row:40px;
}
/* Jari tetap mendapat target 44px; hanya tetikus yang dirapatkan. */
@media (pointer:coarse){:root{--ui-tap:44px;--ui-row:48px}}

/* Aturan lama yang menulis 44px langsung (bukan lewat token) */
body .main-content .master-entity-card :is(.btn-table-action,.btn-action-pill){min-height:30px;padding:3px 10px;font-size:var(--ui-t-caption)}
body main .scroll td :is(a,button):not(.ui-chip){min-height:30px}
body .doc-links a,body .scroll td>a{min-height:30px}
body details>summary,body .technical summary{min-height:34px}
@media (pointer:coarse){
  body .main-content .master-entity-card :is(.btn-table-action,.btn-action-pill){min-height:36px}
  body main .scroll td :is(a,button):not(.ui-chip){min-height:36px}
}

/* ---------- 1. kepala konten: satu baris, tidak makan tempat ---------- */
body main.wrap{padding-top:14px}
body .ui-eyebrow{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
body .ui-page-header{display:flex;flex-wrap:wrap;align-items:baseline;gap:4px 12px;margin:0 0 12px;padding:0 0 10px;border-bottom:1px solid var(--ui-line)}
body .ui-page-header h1{font-size:1.3125rem;line-height:1.25;font-weight:700;letter-spacing:-.015em;margin:0}
body .ui-page-header p{flex:1 1 320px;font-size:var(--ui-t-label);line-height:1.45;color:var(--ui-ink-2);margin:0;max-width:none}

/* topbar: tipis, putih, hanya konteks dan aksi */
body .main-topbar{background:#fff;border-bottom:1px solid var(--ui-line);box-shadow:none}
body .main-topbar .wrap{min-height:46px;padding-top:0;padding-bottom:0;gap:12px}
body .top-context strong{font-size:var(--ui-t-label);font-weight:600;letter-spacing:0;color:var(--ui-ink-2)}
body .header-state{gap:10px;font-size:var(--ui-t-label)}
body .header-state span{color:var(--ui-ink-2)}
body .header-state a{color:var(--ui-green);font-weight:600}

/* ---------- 2. kerapatan: panel, kartu, judul ---------- */
body .task-panel:not(#panel-kasir){gap:var(--ui-gap)}
body main .card,body main section.card{padding:var(--ui-pad);border:1px solid var(--ui-line);border-radius:12px;box-shadow:none;background:#fff}
body main .card>h2:first-child,body main section.card>h2:first-child{margin-top:0}
body main h2{font-size:1.0625rem;line-height:1.3;margin:0 0 2px}
body main h3,body .ui-subhead{font-size:.9375rem;margin:14px 0 6px}
body main .card>p.note{margin:2px 0 10px;font-size:var(--ui-t-label);line-height:1.45;color:var(--ui-ink-2)}
body main .card>p.note:last-of-type{margin-bottom:12px}
body main p{margin:0 0 8px}

/* monitor: ubin pendek, angka tetap besar supaya terbaca sekilas */
body .ui-monitor{margin:0 0 var(--ui-gap)}
body .ui-monitor-grid{grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px}
body .ui-tile{padding:10px 12px;border:1px solid var(--ui-line);border-radius:10px;background:#fff;box-shadow:none}
body .ui-tile::before{top:10px;bottom:10px;width:3px}
body .ui-tile-label{font-size:var(--ui-t-caption);font-weight:600;color:var(--ui-ink-2)}
body .ui-tile-value{font-size:1.375rem;line-height:1.2}
body .ui-tile-hint{font-size:var(--ui-t-caption);color:var(--ui-ink-2);line-height:1.3}
body .ui-tile{gap:1px}
body .ui-tile-label{line-height:1.25}
body .ui-tile-value{line-height:1.15;margin:1px 0}
/* keterangan panjang di bawah judul kartu tidak perlu selebar layar */
body main .card>p.note{max-width:78ch}
/* kolom cari dan chip sama tingginya, tidak menonjol */
body .ui-toolbar :is(input[type=search],input[type=text]){min-height:34px}
body .ui-toolbar .ui-search,body .ui-toolbar label:has(input){max-width:360px;flex:1 1 220px}
body .ui-tile[aria-pressed=true]{border-color:var(--ui-green);background:var(--ui-accent-soft)}

/* ---------- 3. tabel: garis tipis, baris rapat, huruf tetap 15px ---------- */
body .ui-table :is(th,td){padding:9px 12px}
body .ui-table thead th{background:var(--ui-head);color:var(--ui-ink-2);font-size:var(--ui-t-caption);font-weight:650;border-bottom:1px solid var(--ui-line)}
body .ui-table tbody tr{border-top:1px solid var(--ui-line-soft)}
body .ui-table tbody tr:hover{background:var(--ui-hover)}
body .ui-table td{font-size:var(--ui-t-table)}
body .ui-sub{font-size:var(--ui-t-caption);color:var(--ui-ink-2)}
body .ui-table-wrap{border:1px solid var(--ui-line);border-radius:10px}

/* ---------- 4. kendali: hijau gelap, isian ramping ---------- */
body .ui-toolbar{gap:8px;margin:0 0 10px}
body .ui-chip{min-height:34px;padding:0 13px;font-size:var(--ui-t-label)}
body .ui-chip[aria-pressed=true]{background:var(--ui-green);border-color:var(--ui-green);color:#fff}
body main :is(button,.btn,.popup-action,.btn-add-master,.btn-import-master,input[type=submit]):not(.ui-chip):not(.popup-close):not(.rc-remove):not(.btn-action-pill):not(.ui-pick-input){min-height:38px;padding:7px 14px;border-radius:9px;font-size:var(--ui-t-label);font-weight:600}
body main :is(button[type=submit],.btn-add-master,.popup-action,.btn-pill-submit){background:var(--ui-green);border:1px solid var(--ui-green);color:#fff}
body main :is(button[type=submit],.btn-add-master,.popup-action,.btn-pill-submit):hover{background:var(--ui-green-2);border-color:var(--ui-green-2)}
body main :is(button.secondary,.btn-import-master){background:#fff;border:1px solid var(--ui-line);color:var(--ui-ink)}
body main :is(button.secondary,.btn-import-master):hover{background:var(--ui-hover);border-color:var(--ui-green)}
body .btn-action-pill{min-height:30px;padding:3px 10px;font-size:var(--ui-t-caption);border-radius:7px}
body main :is(input,select,textarea):not([type=checkbox]):not([type=radio]):not(.ui-pick-native){min-height:38px;border-radius:9px;border:1px solid var(--ui-line);font-size:var(--ui-t-body)}
body main :is(input,select,textarea):focus{outline:2px solid var(--ui-green);outline-offset:1px;border-color:var(--ui-green)}
body .field{margin:0 0 10px}
body .field>label,body .lbl{font-size:var(--ui-t-label);font-weight:600;color:var(--ui-ink-2);margin:0 0 4px;display:block}

/* ---------- 5. dialog: kepala tipis, isi rapat ---------- */
body dialog.input-dialog{border:1px solid var(--ui-line);border-radius:13px;box-shadow:0 18px 44px rgb(15 42 29 / .16)}
body dialog.input-dialog .popup-heading{padding:10px 12px 10px 16px;border-bottom:1px solid var(--ui-line)}
body dialog.input-dialog .popup-heading h2{font-size:1rem}
body dialog.input-dialog .popup-content{padding:14px 16px}
body dialog.input-dialog .popup-footer{padding:8px 16px;border-top:1px solid var(--ui-line)}

/* Tema kasir (POS) ada di v2/pos-theme. */

/* ---------- 6. keuangan (halaman lama) ikut rapat dan tidak huruf besar semua ---------- */
body[data-remote-task] main.wrap{padding-top:8px}
body[data-remote-task] .task-panel{gap:10px}
body [data-remote-content]>*:first-child{margin-top:0}
body [data-remote-content] .card,body .main-content .card{padding:var(--ui-pad)}
body [data-remote-content] h1,body .main-content h1{font-size:1.3125rem;margin:0 0 6px}
body [data-remote-content] h2,body .main-content h2{font-size:1.0625rem}
/* Halaman lama banyak memakai huruf besar semua; seluruh isinya dinormalkan. */
body :is([data-remote-content],.main-content) *{text-transform:none !important;letter-spacing:normal !important}
body :is([data-remote-content],.main-content) table th{font-size:var(--ui-t-caption);font-weight:650;color:var(--ui-ink-2);background:var(--ui-head)}
body :is([data-remote-content],.main-content) table :is(td,th){padding:8px 12px}
body :is([data-remote-content],.main-content) :is(.btn,button,a.btn){min-height:32px;padding:6px 12px;font-size:var(--ui-t-label)}
body :is([data-remote-content],.main-content) :is(input,select):not([type=checkbox]):not([type=radio]){min-height:34px}

/* ---------- 7. sentuhan jari tetap 44px ---------- */
@media (pointer:coarse){
  body main :is(button,.btn,.popup-action,.btn-add-master,.btn-import-master,.ui-chip):not(.btn-action-pill){min-height:44px}
  body main :is(input,select,textarea):not([type=checkbox]):not([type=radio]){min-height:44px}
  body .btn-action-pill{min-height:36px}
}
@media (max-width:720px){
  body main.wrap{padding-top:10px}
  body main .card,body main section.card{padding:12px}
  body .ui-page-header{padding-bottom:8px;margin-bottom:10px}
}

/* ============================================================
   HIERARKI BACA — tiga tingkat saja supaya mata tidak bingung:
   1) judul/nilai utama: gelap, tebal     2) keterangan: abu, 14px
   3) penjelas kecil: abu muda, 12.5px    Angka selalu tabular dan rata kanan.
   ============================================================ */
:root{--ui-ink-3:#5f7468}

/* kepala halaman: judul di atas, keterangan di bawahnya */
body .ui-page-header{display:block}
body .ui-page-header h1{font-size:1.375rem;font-weight:700;color:var(--ui-ink)}
body .ui-page-header p{margin:3px 0 0;font-size:var(--ui-t-label);color:var(--ui-ink-3);max-width:76ch}

/* kepala kartu: judul, keterangan, lalu alat */
body main .card>h2{font-size:1.0625rem;font-weight:700;color:var(--ui-ink);margin:0}
body main .card>h2+p.note,body main .card>p.note:first-of-type{margin:3px 0 12px;font-size:var(--ui-t-label);color:var(--ui-ink-3);max-width:80ch}

/* tiga tingkat di dalam tabel */
body .ui-table td.ui-primary{font-size:var(--ui-t-table);font-weight:650;color:var(--ui-ink)}
body .ui-table .ui-sub{display:block;margin-top:2px;font-size:.8125rem;line-height:1.3;color:var(--ui-ink-3);font-weight:400}
body .ui-table .ui-muted{color:var(--ui-ink-3);font-size:.8125rem}
body .ui-table td{color:var(--ui-ink-2)}

/* angka: satu gaya untuk semua uang dan jumlah */
body :is(td,th).ui-num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums lining-nums}
body td.ui-num{font-weight:600;color:var(--ui-ink)}
body .ui-figure{font-size:var(--ui-t-table);font-weight:700;color:var(--ui-ink);font-variant-numeric:tabular-nums}
body .sku-code{font-size:.8125rem;color:var(--ui-ink-3);letter-spacing:.01em}

/* lencana status: penjelas, bukan tokoh utama */
body .ui-status{font-size:.8125rem;padding:1px 8px;min-height:20px;font-weight:600}
body .ui-table td.ui-num .ui-sub .ui-status{margin-top:1px}

/* ubin monitor: label kecil, angka besar, keterangan paling kecil */
body .ui-tile-label{font-size:.8125rem;font-weight:600;color:var(--ui-ink-3)}
body .ui-tile-value{font-size:1.5rem;font-weight:700;color:var(--ui-ink);font-variant-numeric:tabular-nums}
body .ui-tile-hint{font-size:.8125rem;color:var(--ui-ink-3)}

/* label isian dan keterangan kecil */
body .field>label,body .lbl,body .uf-label{color:var(--ui-ink-2)}
body :is(.note,.uf-hint,.ui-tile-hint,.rc-total #rc-count){color:var(--ui-ink-3)}

/* total yang harus terbaca sekilas */
body #display-total,body #rc-sum,body .summary-total strong{font-size:1.25rem;font-weight:700;color:var(--ui-green);font-variant-numeric:tabular-nums}
</style>
