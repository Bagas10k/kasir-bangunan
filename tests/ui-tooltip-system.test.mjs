import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const tooltipBlade = readFileSync(new URL('../resources/views/v2/ui-tooltip.blade.php', import.meta.url), 'utf8');
const infoComponent = readFileSync(new URL('../resources/views/components/ui/info-tip.blade.php', import.meta.url), 'utf8');
const uiForms = readFileSync(new URL('../resources/views/v2/ui-forms.blade.php', import.meta.url), 'utf8');
const masterDetail = readFileSync(new URL('../resources/views/v2/master-product-detail.blade.php', import.meta.url), 'utf8');
const masterItem = readFileSync(new URL('../resources/views/v2/master-page-item.blade.php', import.meta.url), 'utf8');

function extractScript(blade) {
    const match = blade.match(/<script>([\s\S]*?)<\/script>/);
    assert.ok(match, 'script block found');
    return match[1];
}

function createFakeElement(tag) {
    const attrs = new Map();
    const classes = new Set();
    const children = [];
    return {
        tagName: tag.toUpperCase(),
        id: '',
        hidden: false,
        dataset: {},
        style: {},
        children,
        isConnected: false,
        parentElement: null,
        _textContent: '',
        get className() { return [...classes].join(' '); },
        set className(v) { classes.clear(); String(v).split(/\s+/).filter(Boolean).forEach((c) => classes.add(c)); },
        classList: {
            add: (...xs) => xs.forEach((x) => classes.add(x)),
            remove: (...xs) => xs.forEach((x) => classes.delete(x)),
            toggle: (x, force) => (force === undefined ? (classes.has(x) ? classes.delete(x) : classes.add(x)) : (force ? classes.add(x) : classes.delete(x))),
            contains: (x) => classes.has(x),
        },
        setAttribute(k, v) { attrs.set(k, String(v)); },
        getAttribute(k) { return attrs.get(k) ?? null; },
        hasAttribute(k) { return attrs.has(k); },
        removeAttribute(k) { attrs.delete(k); },
        append(...nodes) { for (const n of nodes) { children.push(n); if (typeof n === 'object') { n.parentElement = this; n.isConnected = true; } } },
        replaceChildren(...nodes) { children.length = 0; this.append(...nodes); },
        get textContent() {
            if (children.length) return children.map((c) => (typeof c === 'string' ? c : c.textContent)).join(' ');
            return this._textContent;
        },
        set textContent(v) { children.length = 0; this._textContent = String(v); },
        matches(sel) {
            if (sel === ':popover-open') return this._popoverOpen === true;
            if (sel === ':focus-visible') return this._focusVisible === true;
            if (sel.includes('ui-info-btn')) return classes.has('ui-info-btn');
            return false;
        },
        closest(sel) {
            if (sel.includes('[data-tip-skip]')) return null;
            if (sel.includes('[data-tip-key]')) return (this.dataset.tipKey || attrs.has('data-toggletip') || attrs.has('data-tip')) ? this : null;
            if (sel === '[data-hover-card]') return attrs.has('data-hover-card') ? this : null;
            return null;
        },
        contains(other) { return other === this || children.includes(other); },
        showPopover() { this._popoverOpen = true; },
        hidePopover() { this._popoverOpen = false; },
        getBoundingClientRect: () => ({ left: 120, right: 140, top: 200, bottom: 220, width: 20, height: 20 }),
        focus() { this._focused = true; },
    };
}

function setupTooltipRuntime() {
    const docListeners = {};
    const winListeners = {};
    const body = createFakeElement('body');
    body.isConnected = true;
    const document = {
        body,
        createElement: (tag) => createFakeElement(tag),
        querySelector: () => null,
        addEventListener: (type, fn) => { docListeners[type] = fn; },
    };
    const window = {};
    const context = {
        window,
        document,
        innerWidth: 1280,
        innerHeight: 800,
        matchMedia: () => ({ matches: true }),
        requestAnimationFrame: (fn) => fn(),
        addEventListener: (type, fn) => { winListeners[type] = fn; },
        setTimeout,
        clearTimeout,
    };
    context.globalThis = context;
    vm.runInNewContext(extractScript(tooltipBlade), context);
    return { window, document, docListeners, winListeners };
}

test('komponen info-tip memakai tombol native, SVG inline, dan popover manual pada ui-tooltip', () => {
    assert.match(infoComponent, /<button[\s\S]*type="button"/);
    assert.match(infoComponent, /data-toggletip/);
    assert.match(infoComponent, /<svg\b/);
    assert.match(infoComponent, /aria-expanded="false"/);
    assert.match(tooltipBlade, /setAttribute\('popover','manual'\)/);
    assert.match(tooltipBlade, /@media\(max-width:760px\)[\s\S]*\.ui-info-btn::after/);
});

test('kamus terpusat mencakup Harga Modal, Margin Keuntungan, dan seluruh domain utama', () => {
    const { window } = setupTooltipRuntime();
    for (const key of [
        'harga_modal', 'margin_keuntungan', 'harga_kontraktor', 'stok_minimum',
        'harga_tier_kasir', 'status_kas_harian', 'metode_bayar_kasir',
        'sumber_dana_supplier', 'uang_muka_sopir', 'hpp_penerimaan', 'ongkir_penerimaan',
        'selisih_kas_riwayat', 'selisih_belum_selesai', 'laba_rugi_hpp', 'neraca_seimbang',
        'arus_kas_langsung', 'coa_akun_inti', 'pengeluaran_operasional', 'akun_login_peran',
    ]) {
        assert.ok(window.uiTooltip.catalog[key]?.title, `catalog title missing: ${key}`);
        assert.ok(window.uiTooltip.catalog[key]?.body, `catalog body missing: ${key}`);
    }
});

test('toggletip membuka rich tooltip saat diklik dan Escape menutup serta mengembalikan fokus', () => {
    const { docListeners, document } = setupTooltipRuntime();
    const btn = createFakeElement('button');
    btn.className = 'ui-info-btn';
    btn.setAttribute('data-toggletip', '');
    btn.dataset.tipKey = 'margin_keuntungan';
    btn.isConnected = true;

    docListeners.click({ target: btn, preventDefault() {}, stopPropagation() {} });
    const tip = document.body.children.find((el) => el.id === 'ui-tooltip');
    assert.ok(tip);
    assert.equal(tip.hidden, false);
    assert.equal(tip._popoverOpen, true);
    assert.equal(tip.classList.contains('is-rich'), true);
    assert.equal(tip.classList.contains('is-pinned'), true);
    assert.equal(btn.getAttribute('aria-expanded'), 'true');
    assert.match(tip.textContent, /Margin Keuntungan/);
    assert.match(tip.textContent, /Harga Jual/);

    docListeners.keydown({ key: 'Escape' });
    assert.equal(tip.hidden, true);
    assert.equal(btn.getAttribute('aria-expanded'), 'false');
    assert.equal(btn._focused, true);
});

test('master produk menampilkan info-tip pada form margin, header tabel, detail produk, dan hover card relasi', () => {
    assert.match(uiForms, /data-ui-margin-tip/);
    assert.match(uiForms, /margin_keuntungan/);
    assert.match(masterDetail, /Margin keuntungan/);
    assert.match(masterDetail, /key="harga_modal"/);
    assert.match(masterDetail, /key="margin_keuntungan"/);
    assert.match(masterItem, /data-hover-card/);
    assert.match(masterItem, /data-hc-rows/);
});
