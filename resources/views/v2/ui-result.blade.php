<style id="ui-result-dna">
/* Hasil proses: popup penjualan berhasil + pemberitahuan singkat (toast). Token: hijau gelap #14532D, lime #C8F26B, bingkai #E4EBE6. */
html body .ur-toasts{position:fixed;inset:auto;top:84px;right:16px;margin:0;padding:0;border:0;background:none;overflow:visible;width:min(380px,calc(100vw - 32px));display:flex;flex-direction:column;gap:10px;color:#0f2a1d}
html body .ur-toasts:not(:popover-open):not(:empty){display:flex}
html body .ur-toast{display:grid;grid-template-columns:36px minmax(0,1fr) 44px;align-items:start;gap:12px;padding:14px 6px 14px 14px;border:1px solid #E4EBE6;border-radius:16px;background:#fff;box-shadow:0 14px 34px rgba(15,42,29,.16);animation:ur-toast-in .28s cubic-bezier(.22,.8,.24,1)}
html body .ur-toast.is-instant{animation:none}
html body .ur-toast.is-out{opacity:0;transform:translateX(18px);transition:opacity .18s ease,transform .18s ease}
html body .ur-toast.is-warn{border-color:#F0D58C;background:#FFF9E8}
html body .ur-ico{display:grid;place-items:center;width:36px;height:36px;border-radius:50%;background:#C8F26B;color:#14532D}
html body .is-warn .ur-ico{background:#F6D77A;color:#6B4200}
html body .ur-ico svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2.6;stroke-linecap:round;stroke-linejoin:round}
html body .ur-toast strong{display:block;font-size:14px;line-height:1.35;font-weight:700;color:#0f2a1d}
html body .ur-toast p{margin:2px 0 0;font-size:13px;line-height:1.45;color:#5F7468}
html body .is-warn p{color:#6B4200}
html body .ur-x{width:44px;height:44px;margin:-8px 0 0;border:0;border-radius:12px;background:transparent;color:#5F7468;font-size:20px;line-height:1;cursor:pointer}
html body .ur-x:hover{background:#EEF4F0;color:#0f2a1d}
html body .ur-x:focus-visible,html body .ur-btn:focus-visible{outline:3px solid #14532D;outline-offset:2px}
@keyframes ur-toast-in{from{opacity:0;transform:translateX(22px)}to{opacity:1;transform:none}}
@keyframes ur-pop{0%{transform:scale(.4);opacity:0}60%{transform:scale(1.12);opacity:1}100%{transform:none}}
@keyframes ur-draw{to{stroke-dashoffset:0}}
html body dialog.ur-dialog{margin:auto;width:min(420px,calc(100vw - 32px));max-height:calc(100dvh - 32px);padding:0;border:1px solid #E4EBE6;border-radius:22px;background:#fff;color:#0f2a1d;box-shadow:0 30px 70px rgba(15,42,29,.28);overflow:auto;animation:var(--ui-dialog-in,none)}
html body dialog.ur-dialog::backdrop{background:rgba(15,42,29,.52)}
html body .ur-card{display:flex;flex-direction:column;align-items:center;gap:6px;padding:28px 24px 22px;text-align:center}
html body .ur-badge{display:grid;place-items:center;width:68px;height:68px;border-radius:50%;background:#C8F26B;color:#14532D;animation:ur-pop .5s cubic-bezier(.34,1.56,.64,1)}
html body .ur-badge svg{width:36px;height:36px;fill:none;stroke:currentColor;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}
html body .ur-badge path{stroke-dasharray:24;stroke-dashoffset:24;animation:ur-draw .4s .25s ease-out forwards}
html body .ur-card h2{margin:10px 0 0;padding:0;border:0;font-size:20px;line-height:1.25;font-weight:800;color:#0f2a1d}
html body .ur-no{margin:0;font-size:13px;font-weight:600;letter-spacing:.02em;color:#5F7468}
html body .ur-total{margin:8px 0 0;font-size:30px;line-height:1.1;font-weight:800;color:#14532D;font-variant-numeric:tabular-nums}
html body .ur-rows{width:100%;margin:14px 0 0;padding:4px 14px;border:1px solid #E4EBE6;border-radius:14px;background:#F7FAF8;text-align:left}
html body .ur-rows div{display:flex;justify-content:space-between;gap:16px;padding:10px 0;font-size:14px;line-height:1.4;border-top:1px solid #E4EBE6}
html body .ur-rows div:first-child{border-top:0}
html body .ur-rows dt{color:#5F7468}
html body .ur-rows dd{margin:0;font-weight:700;text-align:right;font-variant-numeric:tabular-nums}
html body .ur-pill{display:inline-block;padding:3px 10px;border-radius:999px;font-size:13px;font-weight:700;background:#E4F0E8;color:#14532D}
html body .ur-pill.is-partial{background:#FFF1CC;color:#6B4200}
html body .ur-pill.is-unpaid{background:#FBE4E1;color:#8A2B20}
html body .ur-note{width:100%;margin:10px 0 0;padding:10px 14px;border-radius:12px;background:#FFF9E8;border:1px solid #F0D58C;font-size:13px;line-height:1.45;color:#6B4200;text-align:left}
html body .ur-actions{display:grid;gap:10px;width:100%;margin-top:18px}
html body .ur-actions.has-docs{grid-template-columns:1fr 1fr}
html body .ur-actions .ur-go{grid-column:1/-1}
html body .ur-btn{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 18px;border:1px solid #14532D;border-radius:14px;background:#fff;color:#14532D;font:inherit;font-size:14px;font-weight:700;text-decoration:none;cursor:pointer}
html body .ur-btn:hover{background:#EEF4F0}
html body .ur-btn.is-primary{background:#14532D;color:#fff}
html body .ur-btn.is-primary:hover{background:#0f2a1d}
@media(max-width:760px){
 html body .ur-toasts{top:auto;bottom:calc(92px + env(safe-area-inset-bottom,0px));left:16px;right:16px;width:auto}
 @keyframes ur-toast-in{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
 html body dialog.ur-dialog{margin:auto auto 0;width:100%;max-width:none;border-radius:22px 22px 0 0}
}
@media(prefers-reduced-motion:reduce){
 html body .ur-toast,html body .ur-badge,html body dialog.ur-dialog{animation:none !important}
 html body .ur-badge path{animation:none !important;stroke-dashoffset:0}
 html body .ur-toast.is-out{transition:none}
}
</style>
<script>
(() => {
    if (window.uiResult) { return; }
    var KEY = 'ur-pending';
    var NS = 'http://www.w3.org/2000/svg';
    var host = null;

    function rupiah(value) {
        var parts = String(value === undefined || value === null || value === '' ? '0' : value).split('.');
        var cents = parts[1] && !/^0+$/.test(parts[1]) ? ',' + parts[1].replace(/0+$/, '') : '';
        return 'Rp ' + parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.') + cents;
    }

    function icon(kind) {
        var svg = document.createElementNS(NS, 'svg');
        var path = document.createElementNS(NS, 'path');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('aria-hidden', 'true');
        path.setAttribute('d', kind === 'warn' ? 'M12 6.5v7M12 17.5h.01' : 'M5.5 12.5l4.2 4.2 8.8-9.4');
        svg.append(path);
        return svg;
    }

    function ensureHost() {
        if (host && host.isConnected) { return host; }
        host = document.createElement('div');
        host.className = 'ur-toasts';
        host.setAttribute('popover', 'manual');
        document.body.append(host);
        return host;
    }

    function openHost() {
        try {
            if (host.showPopover && !host.matches(':popover-open')) { host.showPopover(); }
        } catch (e) { /* tanpa popover: elemen fixed biasa */ }
    }

    function closeHostIfEmpty() {
        if (host && !host.children.length) {
            try { if (host.hidePopover && host.matches(':popover-open')) { host.hidePopover(); } } catch (e) { /* abaikan */ }
        }
    }

    function toast(o) {
        var warn = o.tone === 'warn';
        var h = ensureHost();
        var box = document.createElement('div');
        var ico = document.createElement('span');
        var text = document.createElement('div');
        var title = document.createElement('strong');
        var close = document.createElement('button');
        var remaining = o.ms || (warn ? 8000 : 5200);
        var timer = 0;
        var startedAt = 0;
        var gone = false;

        box.className = 'ur-toast' + (warn ? ' is-warn' : '') + (o.instant ? ' is-instant' : '');
        box.setAttribute('role', warn ? 'alert' : 'status');
        ico.className = 'ur-ico';
        ico.append(icon(warn ? 'warn' : 'ok'));
        title.textContent = o.title;
        text.append(title);
        if (o.detail) {
            var p = document.createElement('p');
            p.textContent = o.detail;
            text.append(p);
        }
        close.type = 'button';
        close.className = 'ur-x';
        close.setAttribute('aria-label', 'Tutup pemberitahuan');
        close.textContent = '\u00d7';
        box.append(ico, text, close);
        h.append(box);
        openHost();

        function dismiss() {
            if (gone) { return; }
            gone = true;
            window.clearTimeout(timer);
            try { sessionStorage.removeItem(KEY); } catch (e) { /* abaikan */ }
            box.classList.add('is-out');
            window.setTimeout(function () { box.remove(); closeHostIfEmpty(); }, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 200);
        }
        function start() { startedAt = Date.now(); timer = window.setTimeout(dismiss, remaining); }
        function pause() { window.clearTimeout(timer); remaining -= Date.now() - startedAt; }
        close.addEventListener('click', dismiss);
        box.addEventListener('pointerenter', pause);
        box.addEventListener('pointerleave', function () { if (!gone) { start(); } });
        box.addEventListener('focusin', pause);
        box.addEventListener('focusout', function () { if (!gone) { start(); } });
        start();
        return dismiss;
    }

    var SHOP = { product: 'Produk', customer: 'Pelanggan', supplier: 'Supplier', employee: 'Karyawan', vehicle: 'Mobil', driver: 'Sopir', unit: 'Satuan', category: 'Kategori' };
    var NO_TOAST = null;
    var RULES = [
        ['POST', /\/v2\/sales$/, NO_TOAST],
        ['POST', /\/v2\/shifts$/, function () { return { title: 'Kas harian dibuka', detail: 'Kasir siap berjualan.' }; }],
        ['POST', /\/v2\/shifts\/\d+\/close$/, function (d) {
            return d && d.status === 'review'
                ? { title: 'Kas harian ditutup, ada selisih kas', detail: 'Selisih ' + rupiah(d.difference) + '. Menunggu pemeriksaan admin.', tone: 'warn' }
                : { title: 'Kas harian ditutup', detail: 'Uang laci cocok dengan catatan.' };
        }],
        ['POST', /\/v2\/shifts\/\d+\/resolve$/, function () { return { title: 'Selisih kas harian diselesaikan', detail: 'Alasan pemeriksaan tersimpan.' }; }],
        ['POST', /\/v2\/invoices\/\d+\/payments$/, function (d) {
            return { title: d && d.status === 'proposed' ? 'Pengajuan pembayaran dikirim' : 'Pembayaran dicatat', detail: d && d.status === 'pending' ? 'Transfer menunggu verifikasi admin.' : (d && d.status === 'proposed' ? 'Utang berkurang setelah admin menyetujui.' : 'Sisa tagihan sudah diperbarui.') };
        }],
        ['POST', /\/v2\/payments\/\d+\/approve$/, function () { return { title: 'Pembayaran disetujui', detail: 'Sudah masuk ke pembukuan.' }; }],
        ['POST', /\/v2\/payments\/\d+\/reject$/, function () { return { title: 'Pembayaran ditolak', detail: 'Tidak masuk pembukuan.', tone: 'warn' }; }],
        ['POST', /\/v2\/deliveries\/\d+\/dispatch$/, function () { return { title: 'Pengiriman diberangkatkan', detail: 'Status surat jalan: sedang dikirim.' }; }],
        ['POST', /\/v2\/deliveries\/\d+\/receive$/, function () { return { title: 'Pengiriman diterima', detail: 'Surat jalan ditandai selesai.' }; }],
        ['POST', /\/v2\/driver-advances$/, function () { return { title: 'Uang muka sopir dicatat', detail: 'Uang keluar sudah masuk pembukuan.' }; }],
        ['POST', /\/v2\/driver-advances\/\d+\/settle$/, function () { return { title: 'Uang muka diselesaikan', detail: 'Biaya perjalanan sudah dicatat.' }; }],
        ['POST', /\/v2\/trips$/, function () { return { title: 'Biaya ritase dicatat', detail: 'Beban perjalanan masuk pembukuan.' }; }],
        ['POST', /\/v2\/receipt-drafts\/\d+\/confirm$/, function () { return { title: 'Penerimaan dikonfirmasi', detail: 'Stok dan utang supplier sudah dicatat. Dokumen terkunci.' }; }],
        ['POST', /\/v2\/receipt-drafts\/\d+\/cancel$/, function () { return { title: 'Draf penerimaan dibatalkan', detail: 'Stok, HPP, utang, kas, dan bank tidak berubah.', tone: 'warn' }; }],
        ['POST', /\/v2\/receipt-drafts\/\d+$/, function () { return { title: 'Draf penerimaan diperbarui', detail: 'Belum mengubah stok, HPP, atau utang. Periksa lalu konfirmasi.' }; }],
        ['POST', /\/v2\/receipts$/, function () { return { title: 'Draf penerimaan disimpan', detail: 'Belum mengubah stok, HPP, atau utang. Periksa lalu konfirmasi.' }; }],
        ['POST', /\/v2\/opening-balances$/, function () { return { title: 'Saldo awal diposting', detail: 'Jurnal saldo awal sudah dibuat.' }; }],
        ['POST', /\/v2\/import-batches\/\d+\/commit$/, function () { return { title: 'Impor selesai', detail: 'Semua baris tersimpan.' }; }],
        ['POST', /\/v2\/(?:master|relations)\/([a-z]+)$/, function (d, m) { return { title: (SHOP[m[1]] || 'Data') + ' ditambahkan', detail: 'Data baru sudah tersimpan.' }; }],
        ['PUT', /\/v2\/(?:master|relations)\/([a-z]+)\/\d+$/, function (d, m) { return { title: 'Perubahan ' + (SHOP[m[1]] || 'data').toLowerCase() + ' disimpan' }; }],
        ['PATCH', /\/v2\/(?:master|relations)\/([a-z]+)\/\d+$/, function (d, m) { return { title: (SHOP[m[1]] || 'Data') + ' diarsipkan', detail: 'Riwayat transaksi tetap utuh.' }; }],
        ['POST', /\/v2\/accounts$/, function () { return { title: 'Akun login dibuat' }; }],
        ['PUT', /\/v2\/accounts\/\d+$/, function () { return { title: 'Akun login diperbarui' }; }],
        ['POST', /\/v2\/expenses$/, function (d) { return { title: 'Pengeluaran dicatat', detail: (d && d.expense_number ? d.expense_number + ' · ' : '') + 'Jurnal seimbang sudah dikunci.' }; }],
        ['POST', /\/v2\/journals\/transfers$/, function (d) { return { title: 'Pindah dana tercatat', detail: (d && d.from_account_code && d.to_account_code ? d.from_account_code + ' → ' + d.to_account_code + ' · ' : '') + 'Jurnal seimbang sudah dikunci.' }; }]
    ];

    function persist(o) {
        try { sessionStorage.setItem(KEY, JSON.stringify({ title: o.title, detail: o.detail || '', tone: o.tone || '', ms: o.ms || 0, at: Date.now() })); } catch (e) { /* abaikan */ }
    }

    function announce(o) { persist(o); return toast(o); }

    // Pemberitahuan yang dibuat sesaat sebelum halaman dimuat ulang muncul lagi tanpa animasi masuk.
    (function restore() {
        var saved = null;
        try { saved = JSON.parse(sessionStorage.getItem(KEY) || 'null'); } catch (e) { saved = null; }
        if (!saved || !saved.title || Date.now() - saved.at > 3000) { try { sessionStorage.removeItem(KEY); } catch (e) { /* abaikan */ } return; }
        var base = saved.tone === 'warn' ? 8000 : 5200;
        toast({ title: saved.title, detail: saved.detail, tone: saved.tone, instant: true, ms: Math.max(1800, (saved.ms || base) - (Date.now() - saved.at)) });
        persist(saved);
    })();

    var nativeFetch = window.fetch;
    if (typeof nativeFetch === 'function') {
        window.fetch = function (input, init) {
            var promise = nativeFetch.apply(this, arguments);
            var method = String((init && init.method) || (input && input.method) || 'GET').toUpperCase();
            if (method === 'GET') { return promise; }
            var path = '';
            try { path = new URL(typeof input === 'string' ? input : input.url, window.location.href).pathname; } catch (e) { return promise; }
            var rule = null;
            var hit = null;
            for (var i = 0; i < RULES.length; i += 1) {
                if (RULES[i][0] === method && (hit = RULES[i][1].exec(path))) { rule = RULES[i]; break; }
            }
            if (!rule || !rule[2]) { return promise; }
            return promise.then(function (res) {
                if (res && res.ok) {
                    res.clone().json().catch(function () { return {}; }).then(function (data) { announce(rule[2](data || {}, hit)); });
                }
                return res;
            });
        };
    }

    function saleDialog(data, form) {
        var methods = { cash: 'Tunai', transfer: 'Transfer', credit: 'Kredit' };
        var states = { paid: ['Lunas', ''], partial: ['Dibayar sebagian', 'is-partial'], unpaid: ['Belum lunas', 'is-unpaid'] };
        var link = document.querySelector('#checkout-result a');
        var receipt = link ? link.getAttribute('href') : '';
        var customer = form.elements.customer_id && form.elements.customer_id.value
            ? form.elements.customer_id.selectedOptions[0].textContent.trim()
            : 'Pelanggan umum';
        var methodValue = form.elements.payment_method ? form.elements.payment_method.value : '';
        var dlg = document.createElement('dialog');
        var card = document.createElement('div');
        var badge = document.createElement('div');
        var h2 = document.createElement('h2');
        var number = document.createElement('p');
        var total = document.createElement('p');
        var rows = document.createElement('dl');
        var actions = document.createElement('div');
        var reloaded = false;

        dlg.className = 'ur-dialog';
        dlg.setAttribute('aria-labelledby', 'ur-sale-title');
        card.className = 'ur-card';
        badge.className = 'ur-badge';
        badge.append(icon('ok'));
        h2.id = 'ur-sale-title';
        h2.textContent = 'Penjualan berhasil';
        number.className = 'ur-no';
        number.textContent = data.number || '';
        total.className = 'ur-total';
        total.textContent = rupiah(data.total);
        rows.className = 'ur-rows';

        function row(label, value, pill) {
            var wrap = document.createElement('div');
            var dt = document.createElement('dt');
            var dd = document.createElement('dd');
            dt.textContent = label;
            if (pill) {
                var span = document.createElement('span');
                span.className = 'ur-pill ' + pill[1];
                span.textContent = pill[0];
                dd.append(span);
            } else { dd.textContent = value; }
            wrap.append(dt, dd);
            rows.append(wrap);
        }
        row('Pembeli', customer);
        row('Metode', methods[methodValue] || methodValue);
        row('Dibayar', rupiah(data.paid));
        if (data.change && !/^0+(\.0+)?$/.test(String(data.change))) { row('Kembalian', rupiah(data.change)); }
        row('Status', '', states[data.status] || [String(data.status || ''), '']);
        if (data.delivery_number) { row('Surat jalan', data.delivery_number); }

        actions.className = 'ur-actions' + (receipt ? ' has-docs' : '');
        function anchor(text, href) {
            var a = document.createElement('a');
            a.className = 'ur-btn';
            a.href = href;
            a.target = '_blank';
            a.rel = 'noopener';
            a.textContent = text;
            return a;
        }
        if (receipt) {
            actions.append(anchor('Cetak nota', receipt));
            if (data.delivery_number) { actions.append(anchor('Surat jalan', receipt.replace(/\/receipt$/, '/delivery'))); }
        }
        var next = document.createElement('button');
        next.type = 'button';
        next.className = 'ur-btn is-primary ur-go';
        next.textContent = 'Penjualan berikutnya';
        next.autofocus = true;
        actions.append(next);

        card.append(badge, h2, number, total, rows);
        if (methodValue === 'transfer' && data.paid && !/^0+(\.0+)?$/.test(String(data.paid))) {
            var note = document.createElement('p');
            note.className = 'ur-note';
            note.textContent = 'Transfer menunggu verifikasi admin sebelum masuk ke bank.';
            card.append(note);
        }
        card.append(actions);
        dlg.append(card);
        document.body.append(dlg);

        function finish() { if (reloaded) { return; } reloaded = true; dlg.remove(); if (window.kasirNextSale) { window.kasirNextSale(); } else { window.location.reload(); } }
        next.addEventListener('click', function () { dlg.close(); });
        dlg.addEventListener('close', finish);
        dlg.addEventListener('click', function (ev) { if (ev.target === dlg) { dlg.close(); } });
        var pay = document.getElementById('payment-modal-overlay');
        if (pay && pay.open) { pay.close(); }
        dlg.showModal();
        next.focus({ preventScroll: true });
    }

    var checkout = document.getElementById('form-checkout');
    if (checkout) {
        checkout.addEventListener('v2:sale-saved', function (ev) { saleDialog(ev.detail || {}, checkout); });
    }

    window.uiResult = { toast: toast, announce: announce, sale: saleDialog };
})();
</script>
