import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/views/v2/store-shift-script.blade.php', import.meta.url), 'utf8').replace(/^<script>\s*|\s*<\/script>\s*$/g, '');
function setup({ admin = false, initial = '', reply = { shift: null } } = {}) {
    const title = { textContent: '' }, link = { textContent: '' }, reminder = { hidden: false, textContent: '' };
    const state = { dataset: { storeShiftId: initial, manageShift: admin ? '1' : '0', statusUrl: '/v2/shifts/active', posShiftState: initial ? 'open' : 'closed' }, querySelector: (s) => s === 'strong' ? title : link };
    const listeners = {}, events = [], timers = new Map();
    let data = reply, requests = 0, refreshes = 0, timerId = 0, fail = false;
    const doc = { hidden: false, querySelector: () => null, getElementById: (id) => id === 'store-shift-state' ? state : reminder, addEventListener: (name, fn) => { listeners[name] = fn; }, dispatchEvent: (event) => events.push(event.type) };
    const window = { addEventListener: (name, fn) => { listeners[name] = fn; }, uiLive: { refresh: async () => { refreshes++; } } };
    vm.runInNewContext(source, { window, document: doc, AbortController, CustomEvent: class { constructor(type) { this.type = type; } }, fetch: async (_url, opts) => {
        requests++;
        assert.equal(opts.credentials, 'same-origin');
        assert.equal(opts.cache, 'no-store');
        if (fail) { throw new Error('offline'); }
        return { status: 200, ok: true, redirected: false, json: async () => data };
    }, setTimeout: (fn, ms) => { const id = ++timerId; timers.set(id, { fn, ms }); return id; }, clearTimeout: (id) => timers.delete(id) });
    return { state, title, reminder, link, doc, window, timers, listeners, events, setReply: (next) => { data = next; fail = false; }, offline: () => { fail = true; }, counts: () => ({ requests, refreshes }) };
}

test('shared shift changes update cashier state without navigating or clearing cart', async () => {
    const app = setup();
    await app.window.uiStoreShift.check();
    assert.match(app.title.textContent, /Menunggu admin/);
    app.setReply({ shift: { id: 42 } });
    await app.window.uiStoreShift.check();
    assert.equal(app.state.dataset.storeShiftId, '42');
    assert.equal(app.state.dataset.posShiftState, 'open');
    assert.equal(app.reminder.hidden, true);
    assert.equal(app.link.textContent, 'Lihat shift');
    assert.equal(app.counts().refreshes, 1);
    await app.window.uiStoreShift.check();
    assert.equal(app.counts().refreshes, 1);
    app.setReply({ shift: null });
    await app.window.uiStoreShift.check();
    assert.equal(app.state.dataset.posShiftState, 'closed');
    assert.equal(app.reminder.hidden, false);
    assert.equal(app.counts().refreshes, 2);
    assert.ok(app.events.includes('store-shift:changed'));
});

test('admin sees correct open action, hidden tabs do not poll and focus resumes', async () => {
    const app = setup({ admin: true });
    await app.window.uiStoreShift.check();
    assert.equal(app.link.textContent, 'Buka shift toko');
    app.doc.hidden = true;
    app.listeners.visibilitychange();
    const before = app.counts().requests;
    await app.window.uiStoreShift.check();
    assert.equal(app.counts().requests, before);
    assert.equal(app.timers.size, 0);
    app.doc.hidden = false;
    app.setReply({ shift: { id: 7 } });
    await app.listeners.focus();
    assert.equal(app.state.dataset.storeShiftId, '7');
});

test('offline and invalid status fail closed, preserve reminder, then recover', async () => {
    const app = setup({ initial: '1', reply: { shift: { id: 1 } } });
    await app.window.uiStoreShift.check();
    app.offline();
    assert.equal(await app.window.uiStoreShift.check(), false);
    assert.equal(app.window.uiStoreShift.isOpen(), false);
    assert.equal(app.reminder.hidden, false);
    assert.match(app.reminder.textContent, /keranjang tetap tersimpan/);
    app.setReply({ shift: { id: 'not-an-id' } });
    assert.equal(await app.window.uiStoreShift.check(), false);
    app.setReply({ shift: { id: 1 } });
    assert.equal(await app.window.uiStoreShift.check(), true);
    assert.equal(app.counts().refreshes, 0);
});

test('overlapping checks coalesce and polling uses the lightweight endpoint every five seconds', async () => {
    const app = setup();
    await Promise.all([app.window.uiStoreShift.check(), app.window.uiStoreShift.check()]);
    assert.equal(app.counts().requests, 1);
    assert.ok([...app.timers.values()].some(({ ms }) => ms === 5000));
    app.listeners.pagehide();
    const before = app.counts().requests;
    await app.window.uiStoreShift.check();
    assert.equal(app.counts().requests, before);
});
