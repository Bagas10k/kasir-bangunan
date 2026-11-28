import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const blade = readFileSync(new URL('../resources/views/partials/contextual-help.blade.php', import.meta.url), 'utf8');
const script = blade.split('<script>')[1].split('</script>')[0];
const navigation = readFileSync(new URL('../resources/views/partials/workspace-navigation.blade.php', import.meta.url), 'utf8');
const header = readFileSync(new URL('../resources/views/partials/workspace-header.blade.php', import.meta.url), 'utf8');

function setup({ task = 'kasir', route = '', role = 'admin', tooltipAvailable = true } = {}) {
    const trigger = {
        hidden: true, dataset: { route, helpBase: 'https://example.test/pos/panduan' }, attrs: {},
        setAttribute(key, value) { this.attrs[key] = value; },
    };
    let changed;
    let hides = 0;
    const document = {
        readyState: 'complete',
        body: { dataset: { activeTask: task } },
        getElementById: () => trigger,
        querySelector: () => ({ dataset: { role } }),
    };
    class MutationObserver {
        constructor(callback) { changed = callback; }
        observe() {}
    }
    const window = { uiTooltip: tooltipAvailable ? { hide() { hides++; } } : undefined };
    runInNewContext(script, { document, window, MutationObserver });
    return {
        trigger, get hides() { return hides; },
        select(task) { document.body.dataset.activeTask = task; changed(); },
    };
}

test('every menu supplies a compact title, description and note to the shared tooltip', () => {
    const keys = [...navigation.matchAll(/^\s*'([\w-]+)'\s*=>/gm)].map(match => match[1])
        .filter(key => !['pengawasan', 'operasional', 'master'].includes(key));
    keys.push('laporan', 'master-customer', 'master-category');
    const f = setup();
    for (const key of keys) {
        f.select(key);
        assert.equal(f.trigger.hidden, false, `Missing topic: ${key}`);
        assert.ok(f.trigger.dataset.tipTitle);
        assert.ok(f.trigger.dataset.tip.length > 20 && f.trigger.dataset.tip.length <= 180);
        assert.ok(f.trigger.dataset.tipNote.length > 20 && f.trigger.dataset.tipNote.length <= 180);
    }
});

test('header uses a small shared info icon next to the title, not a dialog launcher', () => {
    assert.match(header, /class="top-context"[^>]*><strong>[\s\S]*?<\/strong><x-ui.info-tip/);
    assert.match(header, /placement="bottom"/);
    assert.doesNotMatch(header, /aria-haspopup="dialog"|<span>Bantuan<\/span>/);
    assert.doesNotMatch(blade, /<dialog|showModal|Langkah penggunaan/);
});

test('page title and help keep a horizontal layout even when the base topbar stacks its children', () => {
    assert.match(blade, /\.main-topbar \.top-context\{[^}]*flex-direction:row/);
    assert.match(blade, /\.top-context>strong\{[^}]*text-overflow:ellipsis/);
    assert.match(blade, /\.contextual-help-trigger\{[^}]*flex:none;align-self:flex-start/);
});

test('menu changes dismiss old help and replace all tooltip content', () => {
    const f = setup();
    const hides = f.hides;
    f.select('penerimaan');
    assert.equal(f.hides, hides + 1);
    assert.equal(f.trigger.attrs['aria-label'], 'Bantuan: Penerimaan barang');
    assert.match(f.trigger.dataset.tipNote, /Draf belum menambah stok atau utang/);
    f.select('shift');
    assert.equal(f.trigger.dataset.tipTitle, 'Kas Harian Toko');
    assert.match(f.trigger.dataset.tipNote, /kas harian berstatus aktif/);
});

test('cashier help describes own payment monitoring, and does not leak into the next menu', () => {
    const f = setup({ task: 'persetujuan', role: 'kasir' });
    assert.equal(f.trigger.dataset.tipTitle, 'Status pembayaran');
    assert.match(f.trigger.dataset.tip, /Anda ajukan sendiri/);
    assert.doesNotMatch(f.trigger.dataset.tip, /menyetujui/);
    assert.equal(f.trigger.dataset.tipArticle, 'https://example.test/pos/panduan/persetujuan');
    f.select('kasir');
    assert.equal(f.trigger.dataset.tipTitle, 'Kasir');
    assert.equal(f.trigger.dataset.tipArticle, 'https://example.test/pos/panduan/kasir');
    assert.match(setup({ task: 'persetujuan' }).trigger.dataset.tipArticle, /\/verifikasi-pembayaran$/);
});

test('standalone finance pages resolve topics without relying on hash navigation', () => {
    for (const [route, title] of [
        ['reports.profit_loss', 'Laba rugi'], ['reports.balance_sheet', 'Neraca'],
        ['reports.cash_flow', 'Arus kas'], ['journals.index', 'Jurnal umum'],
        ['coa.index', 'Bagan akun'], ['expenses.index', 'Pengeluaran'],
    ]) {
        const f = setup({ task: '', route });
        assert.equal(f.trigger.hidden, false);
        assert.equal(f.trigger.dataset.tipTitle, title);
    }
});

test('unknown contexts clear stale content and an unavailable tooltip leaves no dead button', () => {
    const f = setup();
    for (const task of ['unknown', 'constructor', '__proto__']) {
        f.select(task);
        assert.equal(f.trigger.hidden, true);
        assert.equal(f.trigger.dataset.tip, undefined);
        assert.equal(f.trigger.dataset.tipNote, undefined);
    }
    assert.equal(setup({ tooltipAvailable: false }).trigger.hidden, true);
});
