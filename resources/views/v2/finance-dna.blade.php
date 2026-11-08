{{--
    Lapis DNA halaman Keuangan (Jurnal umum, Neraca, Laba rugi, Arus kas, Bagan akun) = ui-dna bagian 22.
    Dimuat di akhir isi setiap halaman Keuangan, sehingga ikut terbawa saat halaman dimuat ke panel workspace (?partial=1)
    maupun saat dibuka penuh, dan tetap berada setelah <style> milik halaman. ':is(..., #f#f)' hanya menaikkan bobot selektor.
--}}
<style id="finance-dna">
/* ---------- 22. Keuangan (Jurnal umum, Neraca, Laba rugi, Arus kas, Bagan akun) ----------
   Halaman lama yang dimuat ke panel workspace (?partial=1) atau dibuka penuh (body[data-finance-page]).
   Mengikuti DNA Operasional: kartu putih berbingkai, ubin ringkasan dengan ubin pertama hijau tua, chip periode,
   tabel berkepala hijau tua, baris total hijau muda, angka Inter tabular (bukan monospace), popup berkepala hijau.
   Halaman membawa <style> sendiri yang dimuat setelah ui-dna, jadi aturan di sini memakai bobot ID + !important. */
:is([data-remote-content],[data-finance-page] main,#f#f){--font-mono:'Inter Kasir',-apple-system,'Segoe UI',sans-serif;font-family:'Inter Kasir',-apple-system,'Segoe UI',sans-serif;font-variant-numeric:tabular-nums;color:#102A1D}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(code,kbd,samp,pre,.sku-code,.tabular-nums,td.amount,td.money,td.quantity,td.code,.journal-date,.ledger-caption,.pagination-meta,.master-table-head small){font-family:'Inter Kasir',-apple-system,'Segoe UI',sans-serif !important;font-variant-numeric:tabular-nums}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.card,.report-shell,.master-head,.master-summary-strip,.master-workspace,.adjustment-panel){background:#fff !important;border:1px solid #E4EBE6 !important;border-radius:12px !important;box-shadow:none !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.report-shell,.master-workspace){padding:20px !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.breadcrumb,.master-eyebrow){display:none !important}
/* judul halaman/kartu */
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.ledger-heading h1,.statement-title h2,.master-head h1){margin:0 !important;font-family:'Inter Kasir',sans-serif !important;font-size:20px !important;line-height:28px !important;font-weight:700 !important;color:#102A1D !important;letter-spacing:-.01em;text-transform:none !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.ledger-heading p,.report-period,.master-head-copy p:not(.master-eyebrow),.section-copy,.adjustment-hint){margin:4px 0 0 !important;font-size:13px !important;line-height:18px;color:#5F7468 !important;font-weight:400 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.section-title,.master-table-head h2,.adjustment-summary strong,.ledger-caption strong){font-family:'Inter Kasir',sans-serif !important;font-size:18px !important;line-height:24px;font-weight:700 !important;color:#102A1D !important;letter-spacing:-.01em;border:0 !important;padding:0 !important;text-transform:none !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .ledger-status{display:inline-flex;align-items:center;min-height:32px;padding:0 14px !important;border:1px solid #BFD8C7 !important;border-radius:999px !important;background:#E4F0E8 !important;color:#14532D !important;font-size:13px !important;font-weight:700 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .ledger-status.is-offline{border-color:#F4D47C !important;background:#FEF3C7 !important;color:#7A4B00 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .statement-header,:is([data-remote-content],[data-finance-page] main,#f#f) .ledger-heading,:is([data-remote-content],[data-finance-page] main,#f#f) .master-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}
:is([data-remote-content],[data-finance-page] main,#f#f) .master-head{padding:20px !important}
/* ubin ringkasan: ubin pertama hijau tua, sisanya putih dengan panel abu muda */
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary,.balance-strip,.master-summary-strip){display:grid !important;grid-template-columns:repeat(auto-fit,minmax(200px,1fr)) !important;gap:12px !important;margin:16px 0 !important;padding:0 !important;background:none !important;border:0 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .master-summary-strip{padding:20px !important;margin:0 0 16px !important;background:#fff !important;border:1px solid #E4EBE6 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary>.summary-item,.balance-strip>.balance-tile,.master-summary-strip>.master-summary-item){position:relative;display:flex !important;flex-direction:column;gap:2px;min-height:84px;margin:0 !important;padding:12px 14px !important;border:1px solid #E4EBE6 !important;border-radius:10px !important;background:#F4F6F5 !important;box-shadow:inset 0 0 0 5px #fff !important;text-align:left;color:#102A1D}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary>.summary-item,.balance-strip>.balance-tile,.master-summary-strip>.master-summary-item)::before,:is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary>.summary-item,.balance-strip>.balance-tile,.master-summary-strip>.master-summary-item)::after{display:none !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary>.summary-item,.balance-strip>.balance-tile,.master-summary-strip>.master-summary-item)>:is(.summary-label,small,.master-summary-label){display:block;margin:0 !important;font-size:13px !important;font-weight:600 !important;color:#3C4A42 !important;text-transform:none !important;letter-spacing:0 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary>.summary-item,.balance-strip>.balance-tile,.master-summary-strip>.master-summary-item)>:is(.summary-value,strong,.master-summary-value){display:block;margin:2px 0 0 !important;font-family:'Inter Kasir',sans-serif !important;font-size:21px !important;line-height:28px !important;font-weight:700 !important;color:#102A1D !important;letter-spacing:-.01em;overflow-wrap:anywhere}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary>.summary-item,.balance-strip>.balance-tile,.master-summary-strip>.master-summary-item)>span:not(.summary-label):not(.master-summary-label){display:block;margin:2px 0 0 !important;font-size:13px !important;color:#5F7468 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary>.summary-item,.balance-strip>.balance-tile,.master-summary-strip>.master-summary-item).is-result{background:#E4F0E8 !important;border-color:#CFE3D6 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary>.summary-item,.balance-strip>.balance-tile,.master-summary-strip>.master-summary-item).is-result>:is(.summary-value,strong){color:#14532D !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary>.summary-item,.balance-strip>.balance-tile,.master-summary-strip>.master-summary-item).is-alert{background:#FDE7E5 !important;border-color:#F3B7B1 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary>.summary-item,.balance-strip>.balance-tile,.master-summary-strip>.master-summary-item).is-alert>:is(.summary-value,strong){color:#9B1C1C !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary>.summary-item,.balance-strip>.balance-tile,.master-summary-strip>.master-summary-item):first-child{padding:14px 16px !important;border:0 !important;background:#14532D !important;box-shadow:none !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary>.summary-item,.balance-strip>.balance-tile,.master-summary-strip>.master-summary-item):first-child>:is(.summary-label,small,.master-summary-label,span){color:#C8F26B !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary>.summary-item,.balance-strip>.balance-tile,.master-summary-strip>.master-summary-item):first-child>:is(.summary-value,strong,.master-summary-value){font-size:26px !important;line-height:32px !important;color:#fff !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary>.summary-item,.balance-strip>.balance-tile,.master-summary-strip>.master-summary-item):first-child>span:not(.summary-label):not(.master-summary-label){color:#C8F26B !important;opacity:.9}
:is([data-remote-content],[data-finance-page] main,#f#f) .adjustment-summary :is(span,small,p):not(.adjustment-toggle){font-size:13px !important;font-weight:400 !important;color:#5F7468 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .journal-entry table thead th{vertical-align:middle !important}
/* tombol */
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.btn,.report-action,.report-apply,.report-reset,.btn-secondary):not(.btn-action-edit):not(.btn-action-locked):not(.modal-close-btn){display:inline-flex !important;align-items:center;justify-content:center;gap:8px;min-height:44px !important;height:44px;padding:0 18px !important;border-radius:10px !important;font-family:'Inter Kasir',sans-serif !important;font-size:14px !important;font-weight:700 !important;text-decoration:none !important;box-shadow:none !important;white-space:nowrap}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.btn,.report-action,.report-apply):not(.btn-secondary):not(.btn-action-edit):not(.btn-action-locked):not(.modal-close-btn){border:1px solid #14532D !important;background:#14532D !important;color:#fff !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.btn,.report-action,.report-apply):not(.btn-secondary):not(.btn-action-edit):not(.btn-action-locked):not(.modal-close-btn):hover{background:#0F3F22 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.btn-secondary,.report-reset){border:1px solid #D5DED8 !important;background:#fff !important;color:#14532D !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.btn-secondary,.report-reset):hover{border-color:#14532D !important;background:#F1F7F3 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .master-head .btn:not(.btn-secondary)::before{content:'+';font-size:18px;font-weight:600;line-height:1}
/* saringan: tanpa kotak, isian 44px, chip periode */
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.report-toolbar,.ledger-filter,.master-toolbar,.report-filter-form){margin:16px 0 !important;padding:0 !important;border:0 !important;background:none !important;box-shadow:none !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.ledger-filter,.master-toolbar){display:flex !important;flex-wrap:wrap;align-items:flex-end;gap:12px !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.ledger-filter,.master-toolbar)>*{min-width:0}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.ledger-filter-actions,.master-toolbar-actions){display:flex;gap:8px;margin-left:auto}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(label,.filter-caption,.master-control>span){font-size:13px !important;font-weight:600 !important;color:#3C4A42 !important;text-transform:none !important;letter-spacing:0 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(input.form-control,select.form-control,.custom-period input,.select-shell>.select-trigger,.date-field input){min-height:44px !important;height:44px;border:1px solid #DFE6E1 !important;border-radius:8px !important;background-color:#fff !important;font-family:'Inter Kasir',sans-serif !important;font-size:14px !important;color:#102A1D !important;box-shadow:none !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(input.form-control,select.form-control,.custom-period input):focus{outline:none !important;border-color:#14532D !important;box-shadow:0 0 0 3px rgba(20,83,45,.16) !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .period-presets{display:flex !important;flex-wrap:wrap;gap:8px !important;padding:0 !important;border:0 !important;background:none !important;box-shadow:none !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .preset-btn{display:inline-flex !important;align-items:center;min-height:40px !important;height:40px;padding:0 16px !important;border:1px solid #DFE6E1 !important;border-radius:999px !important;background:#fff !important;color:#3C4A42 !important;font-size:13px !important;font-weight:600 !important;text-decoration:none !important;box-shadow:none !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .preset-btn:hover{border-color:#9FC2AC !important;color:#14532D !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .preset-btn.active{border-color:#14532D !important;background:#14532D !important;color:#fff !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .report-toolbar :is(.filter-cluster,.preset-cluster,.custom-period,.date-cluster){display:flex;flex-wrap:wrap;align-items:center;gap:10px 12px}
:is([data-remote-content],[data-finance-page] main,#f#f) .report-toolbar .filter-cluster{justify-content:space-between;width:100%}
/* tabel laporan */
:is([data-remote-content],[data-finance-page] main,#f#f) .ledger-caption{display:flex;justify-content:space-between;align-items:baseline;gap:12px;margin:4px 0 12px !important;font-size:13px !important;color:#5F7468 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.report-ledger,.master-table-wrap){border:1px solid #E4EBE6 !important;border-radius:10px !important;overflow:auto;background:#fff !important;box-shadow:none !important;padding:0 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.report-table,.master-table){width:100%;border-collapse:separate !important;border-spacing:0 !important;margin:0 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.report-table,.master-table) thead th{position:static;height:44px;padding:0 16px !important;vertical-align:middle !important;line-height:20px !important;border:0 !important;border-radius:0 !important;background:#14532D !important;color:#fff !important;font-size:13px !important;font-weight:600 !important;text-transform:none !important;letter-spacing:0 !important;white-space:nowrap}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.report-table,.master-table) tbody :is(td,th){padding:12px 16px !important;border:0 !important;border-bottom:1px solid #EEF2EF !important;border-radius:0 !important;font-size:14px !important;line-height:20px;color:#3C4A42;background:#fff;vertical-align:middle}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.report-table,.master-table) tbody tr:last-child>*{border-bottom:0 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .report-table tbody th.section-title{padding-top:16px !important;background:#fff !important;font-size:14px !important;line-height:20px;font-weight:700 !important;color:#14532D !important;text-align:left}
:is([data-remote-content],[data-finance-page] main,#f#f) .report-table td.code{width:96px;font-size:13px !important;color:#5F7468 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .report-table td.amount{text-align:right;font-weight:600;color:#102A1D !important;white-space:nowrap}
:is([data-remote-content],[data-finance-page] main,#f#f) .report-table tr.total>:is(td,th){background:#F4F6F5 !important;font-weight:700 !important;color:#102A1D !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .report-table tr.grand>:is(td,th){background:#E4F0E8 !important;border-top:1px solid #CFE3D6 !important;border-bottom:1px solid #CFE3D6 !important;font-weight:800 !important;color:#14532D !important;text-transform:none}
:is([data-remote-content],[data-finance-page] main,#f#f) .report-table tbody tr:not(.total):not(.grand):hover>td{background:#F8FAF9}
/* jurnal umum: setiap transaksi satu kartu */
:is([data-remote-content],[data-finance-page] main,#f#f) .journal-stack{display:grid;gap:12px}
:is([data-remote-content],[data-finance-page] main,#f#f) .journal-entry{border:1px solid #E4EBE6 !important;border-radius:10px !important;overflow:hidden;background:#fff !important;box-shadow:none !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .journal-head{display:grid !important;grid-template-columns:120px minmax(0,1fr) auto;align-items:center;gap:12px;padding:12px 16px !important;background:#F4F6F5 !important;border:0 !important;border-bottom:1px solid #E4EBE6 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .journal-date{font-size:13px !important;font-weight:600 !important;color:#5F7468 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .journal-desc :is(strong,b,h3){display:block;font-size:15px !important;font-weight:700 !important;color:#102A1D !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .journal-desc :is(small,span,code){font-size:12px !important;color:#7A8B81 !important;background:none !important;padding:0 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .balance-state{display:inline-flex;align-items:center;min-height:28px;padding:0 12px !important;border:1px solid #BFD8C7 !important;border-radius:999px !important;background:#E4F0E8 !important;color:#14532D !important;font-size:13px !important;font-weight:700 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .journal-entry table{width:100%;border-collapse:separate;border-spacing:0;margin:0 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .journal-entry table thead th{height:38px;padding:0 16px !important;background:#fff !important;color:#5F7468 !important;font-size:12px !important;font-weight:600 !important;border:0 !important;border-bottom:1px solid #EEF2EF !important;text-transform:none !important;letter-spacing:0 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .journal-entry table tbody td{padding:10px 16px !important;border:0 !important;border-bottom:1px solid #EEF2EF !important;font-size:14px !important;color:#3C4A42;background:#fff !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .journal-entry td.amount{text-align:right;font-weight:600;color:#102A1D !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .journal-entry tr.journal-total>*{background:#E4F0E8 !important;border-bottom:0 !important;font-weight:700 !important;color:#14532D !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .sku-code{display:inline;padding:0 !important;border:0 !important;background:none !important;font-size:13px !important;font-weight:600 !important;color:#5F7468 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) :is(.adjustment-summary,.opening-disclosure>summary){cursor:pointer}
:is([data-remote-content],[data-finance-page] main,#f#f) .adjustment-panel{padding:0 !important;margin:0 0 16px !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .adjustment-summary{display:flex !important;align-items:center;justify-content:space-between;gap:12px;padding:16px 20px !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .adjustment-toggle{display:grid !important;place-items:center;width:40px;height:40px;border:1px solid #D5DED8 !important;border-radius:10px !important;background:#fff !important;color:#14532D !important;font-weight:700}
:is([data-remote-content],[data-finance-page] main,#f#f) .adjustment-content{padding:0 20px 20px !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .opening-disclosure{margin:0 0 16px !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .opening-disclosure>summary{display:inline-flex;align-items:center;gap:8px;min-height:44px;font-size:14px !important;font-weight:700 !important;color:#14532D !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .opening-row{border-bottom:1px solid #EEF2EF !important;padding:10px 0 !important}
/* bagan akun */
:is([data-remote-content],[data-finance-page] main,#f#f) .master-table .master-identity :is(a,strong){font-weight:700 !important;color:#102A1D !important;text-decoration:none}
:is([data-remote-content],[data-finance-page] main,#f#f) .master-table td.money{text-align:right;font-weight:600;color:#102A1D !important;white-space:nowrap}
:is([data-remote-content],[data-finance-page] main,#f#f) .master-table td.quantity{text-align:right;color:#5F7468 !important;white-space:nowrap}
:is([data-remote-content],[data-finance-page] main,#f#f) .master-status{display:inline-flex;align-items:center;min-height:28px;padding:0 12px !important;border:1px solid #BFD8C7 !important;border-radius:999px !important;background:#E4F0E8 !important;color:#14532D !important;font-size:13px !important;font-weight:700 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .master-status:not(.active){border-color:#DFE6E1 !important;background:#F1F4F2 !important;color:#5F7468 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .master-status::before{display:none !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .master-row-actions{display:flex;justify-content:flex-end;gap:8px}
:is([data-remote-content],[data-finance-page] main,#f#f) .master-row-actions :is(.master-link,.btn-action-edit,.btn-action-locked){display:inline-flex !important;align-items:center;justify-content:center;min-height:36px !important;height:36px;padding:0 14px !important;border:1px solid #D5DED8 !important;border-radius:8px !important;background:#fff !important;color:#14532D !important;font-size:13px !important;font-weight:700 !important;text-decoration:none !important;box-shadow:none !important;white-space:nowrap}
:is([data-remote-content],[data-finance-page] main,#f#f) .master-row-actions :is(.master-link,.btn-action-edit):hover{border-color:#14532D !important;background:#F1F7F3 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .master-row-actions .btn-action-locked{border-style:dashed !important;color:#7A8B81 !important;cursor:not-allowed}
:is([data-remote-content],[data-finance-page] main,#f#f) .master-table-head{display:flex;justify-content:space-between;align-items:baseline;gap:12px;margin:0 0 12px !important;padding:0 !important;border:0 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .pagination{display:flex !important;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:14px 0 0 !important;padding:0 !important;border:0 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .pagination-meta{font-size:13px !important;color:#5F7468 !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .pagination-controls{display:flex;gap:6px}
:is([data-remote-content],[data-finance-page] main,#f#f) .page-btn{display:inline-flex !important;align-items:center;justify-content:center;min-width:36px;min-height:36px !important;height:36px;padding:0 12px !important;border:1px solid #DFE6E1 !important;border-radius:8px !important;background:#fff !important;color:#14532D !important;font-size:13px !important;font-weight:700 !important;text-decoration:none !important;box-shadow:none !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .page-btn.active{border-color:#14532D !important;background:#14532D !important;color:#fff !important}
:is([data-remote-content],[data-finance-page] main,#f#f) .page-btn.disabled{opacity:.45}
/* popup Tambah/Ubah akun: tema popup Operasional */
:is(#modal-coa,#f#f).modal-overlay{background:rgba(9,30,18,.55) !important;backdrop-filter:none !important}
:is(#modal-coa,#f#f).modal-overlay.open .modal-dialog{animation:var(--ui-dialog-in)}
:is(#modal-coa,#f#f) .modal-dialog{width:min(560px,calc(100vw - 32px)) !important;max-width:none !important;max-height:calc(100dvh - 32px) !important;display:flex;flex-direction:column;padding:0 !important;border:0 !important;border-radius:14px !important;overflow:hidden;background:#fff !important;box-shadow:0 24px 60px -20px rgba(4,12,8,.45) !important;font-family:'Inter Kasir',-apple-system,'Segoe UI',sans-serif;color:#102A1D}
:is(#modal-coa,#f#f) .modal-header{display:flex !important;align-items:flex-start !important;justify-content:space-between;gap:12px;margin:0 !important;padding:18px 20px 16px 24px !important;background:#14532D !important;border:0 !important}
:is(#modal-coa,#f#f) .modal-icon-badge{display:none !important}
:is(#modal-coa,#f#f) .modal-title::after{display:none !important}
:is(#modal-coa,#f#f) .modal-title{margin:0 !important;padding:0 !important;border:0 !important;font-family:'Bricolage Kasir','Inter Kasir',sans-serif !important;font-size:22px !important;line-height:28px !important;font-weight:700 !important;color:#C8F26B !important;letter-spacing:-.01em}
:is(#modal-coa,#f#f) #form-subtitle{font-size:13px !important;color:#D6E8DC !important}
:is(#modal-coa,#f#f) .modal-close-btn{display:grid !important;place-items:center;width:36px !important;height:36px !important;min-height:36px !important;padding:0 !important;border:0 !important;border-radius:8px !important;background:transparent !important;color:#fff !important;font-size:24px !important;line-height:1 !important;box-shadow:none !important}
:is(#modal-coa,#f#f) .modal-close-btn:hover{background:rgba(255,255,255,.12) !important}
:is(#modal-coa,#f#f) form{display:flex;flex-direction:column;min-height:0;flex:1 1 auto}
:is(#modal-coa,#f#f) .modal-body{overflow:auto;padding:20px 24px !important}
:is(#modal-coa,#f#f) .master-help{padding:10px 12px !important;border:1px solid #E4EBE6 !important;border-radius:8px !important;background:#F4F6F5 !important;font-size:13px !important;color:#3C4A42 !important}
:is(#modal-coa,#f#f) label{font-size:14px !important;font-weight:600 !important;color:#102A1D !important}
:is(#modal-coa,#f#f) .form-control{min-height:46px !important;border:1px solid #DFE6E1 !important;border-radius:8px !important;font-size:15px !important;color:#102A1D !important;box-shadow:none !important}
:is(#modal-coa,#f#f) .form-control:focus{outline:none !important;border-color:#14532D !important;box-shadow:0 0 0 3px rgba(20,83,45,.16) !important}
:is(#modal-coa,#f#f) .modal-footer{display:flex !important;justify-content:space-between !important;align-items:center;gap:12px;padding:16px 24px 20px !important;border-top:1px solid #EEF2EF !important;background:#fff !important}
:is(#modal-coa,#f#f) .modal-footer .btn{display:inline-flex !important;align-items:center;justify-content:center;min-height:46px !important;padding:0 20px !important;border-radius:10px !important;font-size:15px !important;font-weight:700 !important;box-shadow:none !important}
:is(#modal-coa,#f#f) .modal-footer .btn.btn-secondary{min-width:120px;border:1px solid #D5DED8 !important;background:#fff !important;color:#14532D !important}
:is(#modal-coa,#f#f) .modal-footer .btn:not(.btn-secondary){min-width:200px;border:0 !important;background:#C8F26B !important;color:#14532D !important}
:is(#modal-coa,#f#f) .modal-footer .btn:not(.btn-secondary):hover{background:#B8E557 !important}
@media (max-width:760px){
  :is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary,.balance-strip,.master-summary-strip){grid-template-columns:repeat(2,minmax(0,1fr)) !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary>.summary-item,.balance-strip>.balance-tile,.master-summary-strip>.master-summary-item):first-child{grid-column:1/-1}
  :is([data-remote-content],#f#f){padding:0 !important;margin:0 !important;max-width:none !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary>.summary-item,.balance-strip>.balance-tile,.master-summary-strip>.master-summary-item){text-align:left !important;align-items:stretch !important;min-height:0}
  :is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary>.summary-item,.balance-strip>.balance-tile,.master-summary-strip>.master-summary-item)>*{text-align:left !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary>.summary-item,.balance-strip>.balance-tile,.master-summary-strip>.master-summary-item)>:is(.summary-value,strong,.master-summary-value){font-size:15px !important;line-height:22px !important;overflow-wrap:break-word !important;word-break:normal !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) .report-table td:not(.amount):not(.code){overflow-wrap:anywhere}
  :is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-summary>.summary-item,.balance-strip>.balance-tile,.master-summary-strip>.master-summary-item):first-child>:is(.summary-value,strong,.master-summary-value){font-size:22px !important;line-height:28px !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) :is(.report-table,.master-table,.journal-entry table) :is(td,th){padding-left:10px !important;padding-right:10px !important;font-size:13px !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) .report-table td.amount{font-size:13px !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) .ledger-caption{flex-direction:column;align-items:flex-start;gap:2px}
  :is([data-remote-content],[data-finance-page] main,#f#f) section.card{padding:14px !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) .master-table-wrap{border:0 !important;overflow:visible !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) .master-table{display:block !important;width:100% !important;min-width:0 !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) .master-table thead{display:none !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) .master-table tbody{display:grid !important;gap:12px;width:100%}
  :is([data-remote-content],[data-finance-page] main,#f#f) .master-table tbody tr{display:block !important;width:auto !important;min-width:0 !important;margin:0 !important;padding:6px 0 !important;border:1px solid #E4EBE6 !important;border-radius:10px !important;background:#fff !important;box-shadow:none !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) .master-table tbody :is(td,th){display:grid !important;grid-template-columns:minmax(0,96px) minmax(0,1fr) !important;grid-template-rows:auto !important;grid-template-areas:none !important;grid-auto-rows:auto !important;align-items:center;column-gap:10px;row-gap:0 !important;padding:7px 14px !important;border:0 !important;min-height:0 !important;height:auto !important;text-align:right !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) .master-table tbody :is(td,th)::before{font-size:13px !important;font-weight:600 !important;color:#5F7468 !important;text-transform:none !important;letter-spacing:0 !important;text-align:left;width:auto !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) .master-table tbody :is(td,th)::after{display:none !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) .master-table tbody :is(td,th):first-child{display:block !important;grid-template-columns:1fr !important;text-align:left !important;padding-top:10px !important;padding-bottom:10px !important;border-bottom:1px solid #EEF2EF !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) .master-table tbody :is(td,th):first-child::before{display:none !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) .master-table tbody :is(td,th)>*{justify-self:end;min-width:0}
  :is([data-remote-content],[data-finance-page] main,#f#f) .master-table tbody :is(td,th):first-child>*{justify-self:start}
  :is([data-remote-content],[data-finance-page] main,#f#f) .master-status{width:auto !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) .master-table tbody :is(td,th):last-child{grid-template-columns:1fr !important;padding-top:10px !important;border-top:1px solid #EEF2EF !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) .master-table tbody :is(td,th):last-child::before{display:none !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) .master-row-actions{display:grid !important;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;justify-self:stretch !important;width:100%}
  :is([data-remote-content],[data-finance-page] main,#f#f) .master-row-actions>*{width:100%;min-height:44px !important;height:44px !important;padding:0 6px !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) .journal-entry table :is(td,th){padding-left:6px !important;padding-right:6px !important;font-size:12.5px !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) .journal-entry table :is(td,th):first-child{padding-left:10px !important;width:50px !important;min-width:0 !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) .journal-entry table{table-layout:auto !important;min-width:0 !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) .journal-entry td.amount{font-size:12px !important;letter-spacing:-.01em}
  :is([data-remote-content],[data-finance-page] main,#f#f) .journal-entry table :is(td,th):last-child{padding-right:12px !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) :is(.report-shell,.master-workspace,.master-head,.master-summary-strip){padding:16px !important}
  :is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-actions,.master-head>:last-child){display:flex;gap:8px;width:100%}
  :is([data-remote-content],[data-finance-page] main,#f#f) :is(.statement-actions,.master-head>:last-child)>*{flex:1 1 0}
  :is([data-remote-content],[data-finance-page] main,#f#f) :is(.ledger-filter,.master-toolbar)>*{flex:1 1 100%}
  :is([data-remote-content],[data-finance-page] main,#f#f) :is(.ledger-filter-actions,.master-toolbar-actions){margin-left:0}
  :is([data-remote-content],[data-finance-page] main,#f#f) :is(.ledger-filter-actions,.master-toolbar-actions)>*{flex:1 1 0}
  :is([data-remote-content],[data-finance-page] main,#f#f) .report-toolbar :is(.custom-period,.date-cluster){width:100%}
  :is([data-remote-content],[data-finance-page] main,#f#f) .journal-head{grid-template-columns:minmax(0,1fr) auto}
  :is([data-remote-content],[data-finance-page] main,#f#f) .journal-head .journal-desc{grid-column:1/-1;grid-row:2}
  :is([data-remote-content],[data-finance-page] main,#f#f) .report-table td.code{width:44px}
  :is([data-remote-content],[data-finance-page] main,#f#f) :is(.report-table,.master-table) :is(td,th){padding-left:12px !important;padding-right:12px !important}
  :is(#modal-coa,#f#f) .modal-dialog{width:calc(100vw - 16px) !important;max-height:calc(100dvh - 16px) !important}
  :is(#modal-coa,#f#f) .modal-footer .btn.btn-secondary{min-width:0}
  :is(#modal-coa,#f#f) .modal-footer .btn:not(.btn-secondary){min-width:0;flex:1 1 auto}
}
</style>
