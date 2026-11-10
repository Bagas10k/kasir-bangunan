import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const blade = readFileSync(new URL('../resources/views/v2/ui-result.blade.php', import.meta.url), 'utf8');
const script = blade.slice(blade.indexOf('<script>') + 8, blade.lastIndexOf('</script>'));

function setup(responses) {
    const toasts = [];
    const store = {};
    const node = () => {
        const el = {
            children: [], attrs: {}, className: '', textContent: '', classList: { add() {} },
            append(...items) { items.forEach((item) => this.children.push(item)); },
            setAttribute(key, value) { this.attrs[key] = value; },
            addEventListener() {}, remove() {}, matches: () => false,
        };
        return el;
    };
    const document = {
        createElement: node,
        createElementNS: node,
        getElementById: () => null,
        body: { append(el) { toasts.push(el); } },
    };
    const calls = [];
    const window = {
        location: { href: 'https://tb01.test/kasir-bangunan/' },
        matchMedia: () => ({ matches: true }),
        setTimeout: () => 0,
        clearTimeout() {},
        fetch(input, init) {
            calls.push({ input, init });
            const reply = responses.shift() ?? { ok: true, body: {} };
            return Promise.resolve({ ok: reply.ok, clone: () => ({ json: () => Promise.resolve(reply.body) }) });
        },
    };
    const sessionStorage = {
        getItem: (key) => store[key] ?? null,
        setItem: (key, value) => { store[key] = value; },
        removeItem: (key) => { delete store[key]; },
    };
    runInNewContext(script, { window, document, sessionStorage, URL, Date, JSON, String, Math });
    const saved = () => (store['ur-pending'] ? JSON.parse(store['ur-pending']) : null);
    return { window, saved, calls };
}

const tick = () => new Promise((resolve) => setImmediate(resolve));

test('shift close with a difference announces a warning with the amount', async () => {
    const f = setup([{ ok: true, body: { status: 'review', difference: '-10000.00' } }]);
    await f.window.fetch('/kasir-bangunan/v2/shifts/4/close', { method: 'POST' });
    await tick();
    assert.equal(f.saved().title, 'Shift ditutup, ada selisih kas');
    assert.equal(f.saved().tone, 'warn');
    assert.match(f.saved().detail, /Rp -10\.000/);
});

test('master create names the kind and archive says history stays intact', async () => {
    const f = setup([{ ok: true, body: {} }, { ok: true, body: {} }]);
    await f.window.fetch('/v2/master/product', { method: 'POST' });
    await tick();
    assert.equal(f.saved().title, 'Produk ditambahkan');
    await f.window.fetch('/v2/master/customer/9', { method: 'PATCH' });
    await tick();
    assert.equal(f.saved().title, 'Pelanggan diarsipkan');
    assert.match(f.saved().detail, /Riwayat transaksi tetap utuh/);
});

test('failed requests, reads and sales requests do not raise a toast', async () => {
    const f = setup([{ ok: false, body: {} }, { ok: true, body: {} }, { ok: true, body: {} }]);
    await f.window.fetch('/v2/shifts', { method: 'POST' });
    await tick();
    assert.equal(f.saved(), null);
    await f.window.fetch('/v2/master/product', { method: 'GET' });
    await f.window.fetch('/v2/sales', { method: 'POST' });
    await tick();
    assert.equal(f.saved(), null);
});

test('the original response is returned untouched to the caller', async () => {
    const f = setup([{ ok: true, body: { number: 'RCV-000045' } }]);
    const res = await f.window.fetch('/v2/receipts', { method: 'POST' });
    assert.equal(res.ok, true);
    await tick();
    assert.match(f.saved().detail, /RCV-000045/);
});
