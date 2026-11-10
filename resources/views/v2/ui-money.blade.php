{{-- Kolom nominal uang: pemisah ribuan saat mengetik (1.000.000; desimal pakai koma), tanpa isian 0 bawaan.
     Sasaran: input[data-money] (V2), input[data-currency-input] (halaman lama yang dimuat di ruang kerja), input[data-money-display] (hanya format, mis. Dibayar di Kasir).
     Nilai yang dibaca skrip (.value) dan yang dikirim (FormData/submit biasa) tetap angka mentah: 1000000 atau 1000000.5.
     Kolom yang semula berisi 0 bawaan menjadi kosong dengan placeholder 0; bila dibiarkan kosong, nilainya tetap 0. --}}
<script id="ui-money">
(() => {
    if (window.uiMoney) { return; }
    const native = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
    const SEL = 'input[data-money],input[data-currency-input],input[data-money-display]';
    const scaleOf = el => { const v = parseInt(el.dataset.moneyScale ?? el.dataset.currencyScale, 10); return Number.isNaN(v) ? 2 : v; };
    const group = int => int.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    /* Teks ketikan: titik selalu pemisah ribuan (dibuang), koma = desimal. */
    function fromTyped(text, scale) {
        const s = String(text ?? '').replace(/[^\d,]/g, '');
        if (s === '') { return ''; }
        const at = s.indexOf(',');
        const int = (at < 0 ? s : s.slice(0, at)).replace(/^0+(?=\d)/, '') || '0';
        const dec = at < 0 ? '' : s.slice(at + 1).replace(/,/g, '').slice(0, scale);
        return group(int) + (at >= 0 && scale > 0 ? ',' + dec : '');
    }
    /* Nilai mentah (titik = desimal), mis. dari server atau skrip: 52000.0000 -> 52.000. */
    function fromRaw(raw, scale) {
        const s = String(raw ?? '').trim();
        if (s === '') { return ''; }
        const m = s.match(/^(\d*)(?:\.(\d*))?$/);
        if (!m) { return fromTyped(s, scale); }
        const int = m[1].replace(/^0+(?=\d)/, '') || '0';
        const dec = (m[2] || '').slice(0, scale).replace(/0+$/, '');
        return group(int) + (dec ? ',' + dec : '');
    }
    function toRaw(display) {
        const s = String(display ?? '').trim();
        if (s === '') { return ''; }
        const [int = '', dec = ''] = s.replace(/\./g, '').split(',');
        const i = int.replace(/\D/g, ''), d = dec.replace(/\D/g, '');
        if (i === '' && d === '') { return ''; }
        return (i.replace(/^0+(?=\d)/, '') || '0') + (d ? '.' + d : '');
    }
    const isZero = value => /^\s*0*([.,]0*)?\s*$/.test(String(value ?? '')) && /0/.test(String(value ?? ''));

    function enhance(el) {
        if (el.dataset.moneyReady || el.type === 'hidden') { return; }
        el.dataset.moneyReady = '1';
        const scale = scaleOf(el);
        el.removeAttribute('pattern');
        el.setAttribute('inputmode', scale > 0 ? 'decimal' : 'numeric');
        el.setAttribute('autocomplete', 'off');
        if (!el.placeholder || isZero(el.placeholder)) { el.placeholder = '0'; }
        const current = native.get.call(el);
        if (el.hasAttribute('data-money-display')) {
            native.set.call(el, isZero(current) ? '' : fromTyped(current, scale));
            return;
        }
        // Isian 0 bawaan: kosongkan; nilai kosong tetap berarti 0 (kolom tidak lagi wajib diketik).
        if (isZero(el.getAttribute('value'))) {
            el.dataset.moneyZero = '1';
            el.required = false;
            el.setAttribute('value', '');
        }
        native.set.call(el, el.dataset.moneyZero && isZero(current) ? '' : fromRaw(current.includes(',') ? toRaw(current) : current, scale));
        Object.defineProperty(el, 'value', {
            configurable: true,
            get() { const raw = toRaw(native.get.call(this)); return raw === '' && this.dataset.moneyZero ? '0' : raw; },
            set(v) { native.set.call(this, this.dataset.moneyZero && isZero(v) ? '' : fromRaw(v, scaleOf(this))); }
        });
    }
    const scan = root => {
        if (root.matches?.(SEL)) { enhance(root); }
        root.querySelectorAll?.(SEL).forEach(enhance);
    };

    // Format saat mengetik; posisi kursor dijaga menurut jumlah angka di kirinya.
    document.addEventListener('input', e => {
        const el = e.target;
        if (!el.matches?.(SEL)) { return; }
        if (!el.dataset.moneyReady) { enhance(el); }
        const text = native.get.call(el);
        const caret = el.selectionStart ?? text.length;
        const keep = text.slice(0, caret).replace(/[^\d,]/g, '').length;
        const next = fromTyped(text, scaleOf(el));
        if (next === text) { return; }
        native.set.call(el, next);
        let pos = 0, seen = 0;
        while (pos < next.length && seen < keep) { if (/[\d,]/.test(next[pos])) { seen++; } pos++; }
        try { el.setSelectionRange(pos, pos); } catch {}
    }, true);
    // FormData (fetch) dan submit biasa membaca nilai tampilan; ganti dengan angka mentah.
    document.addEventListener('formdata', e => {
        for (const el of e.target.elements || []) {
            if (el.name && el.dataset?.moneyReady && !el.disabled && el.matches(SEL) && !el.hasAttribute('data-money-display')) { e.formData.set(el.name, el.value); }
        }
    }, true);
    new MutationObserver(records => {
        for (const record of records) { for (const node of record.addedNodes) { if (node.nodeType === 1) { scan(node); } } }
    }).observe(document.documentElement, {childList: true, subtree: true});
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', () => scan(document)); } else { scan(document); }

    window.uiMoney = { fromTyped, fromRaw, toRaw, enhance };
})();
</script>
