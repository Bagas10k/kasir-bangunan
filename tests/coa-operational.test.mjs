import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/views/v2/coa-script.blade.php', import.meta.url), 'utf8').replace(/^<script[^>]*>\s*|\s*<\/script>\s*$/g, '');

function setup() {
    const button = { disabled: false }, error = { hidden: true, textContent: '', focus() { this.focused = true; } };
    const form = {
        dataset: {}, action: 'http://example.test/v2/chart-of-accounts',
        values: { account_name: 'Akun Sintetis', account_code: '6198', _method: 'POST' },
        listeners: {}, reportValidity: () => true,
        querySelector: () => error, querySelectorAll: () => [button],
        setAttribute() {}, removeAttribute() {},
        addEventListener(type, fn) { this.listeners[type] = fn; },
    };
    function makeRoot() {
        const box = { hidden: true, textContent: '' }, listeners = {};
        const field = { value: 'draft pencarian' };
        const filters = { values: { search: 'draft pencarian' }, elements: { namedItem: () => field } };
        return {
            dataset: { refreshUrl: 'http://example.test/coa?search=SYN&per_page=10' }, box, field, listeners,
            querySelector: selector => selector === 'form.master-toolbar' ? filters : box,
            replaceWith(next) { document.root = next; },
            addEventListener(type, fn) { listeners[type] = fn; },
        };
    }
    const document = {
        root: null, currentForm: form, events: [],
        getElementById: () => document.currentForm,
        querySelector: selector => selector === '[data-coa-root]' ? document.root : { content: 'synthetic-csrf' },
        dispatchEvent(event) { this.events.push(event); },
    };
    document.root = makeRoot();
    const window = { closeCalls: [], closeModal: id => window.closeCalls.push(id), uiTableFilters: { scan() {} }, uiMotion: { enter() {} } };
    const requests = [];
    let responder = async (_url, options) => options.method === 'POST'
        ? { ok: true, status: 201, json: async () => ({ message: 'Akun tersimpan.' }) }
        : { ok: true, text: async () => '<fixture>' };
    const context = {
        window, document, location: { href: 'http://example.test/#bagan-akun' }, URL,
        FormData: class { constructor(target) { this.target = target; } [Symbol.iterator]() { return Object.entries(this.target.values ?? {})[Symbol.iterator](); } },
        DOMParser: class { parseFromString() { return { querySelector: () => makeRoot() }; } },
        CustomEvent: class { constructor(type, options) { this.type = type; Object.assign(this, options); } },
        fetch: (url, options) => { requests.push({ url: String(url), options }); return responder(String(url), options); },
    };
    vm.runInNewContext(source, context);
    const submit = () => form.listeners.submit({ preventDefault() {} });
    return { form, button, error, document, window, requests, submit, respond: fn => { responder = fn; } };
}

test('COA save uses CSRF and original form data then refreshes only its content without navigation', async () => {
    const app = setup();
    await app.submit();
    assert.equal(app.requests.length, 2);
    assert.equal(app.requests[0].options.method, 'POST');
    assert.equal(app.requests[0].options.headers['X-CSRF-TOKEN'], 'synthetic-csrf');
    assert.equal(app.requests[0].options.body.target, app.form);
    assert.equal(new URL(app.requests[1].url).searchParams.get('partial'), '1');
    assert.equal(new URL(app.requests[1].url).searchParams.get('search'), 'SYN');
    assert.equal(app.document.root.field.value, 'draft pencarian');
    assert.equal(app.document.root.box.textContent, 'Akun tersimpan.');
    assert.deepEqual(app.window.closeCalls, ['modal-coa']);
    assert.equal(app.document.events[0].type, 'ui-remote:changed');
    assert.equal(app.button.disabled, false);
});

test('validation errors retain input, keep the modal open and unlock retry', async () => {
    const app = setup();
    app.respond(async () => ({ ok: false, status: 422, json: async () => ({ errors: { account_code: ['Kode sudah digunakan.'] } }) }));
    await app.submit();
    assert.equal(app.requests.length, 1);
    assert.equal(app.form.values.account_name, 'Akun Sintetis');
    assert.equal(app.error.textContent, 'Kode sudah digunakan.');
    assert.equal(app.error.hidden, false);
    assert.equal(app.window.closeCalls.length, 0);
    assert.equal(app.button.disabled, false);
});

test('double submit during an outstanding request sends one write', async () => {
    const app = setup();
    let release;
    app.respond(async (_url, options) => options.method === 'POST'
        ? new Promise(resolve => { release = resolve; })
        : { ok: true, text: async () => '' });
    const first = app.submit();
    await app.submit();
    assert.equal(app.requests.length, 1);
    assert.equal(app.button.disabled, true);
    release({ ok: true, json: async () => ({ message: 'Tersimpan' }) });
    await first;
    assert.equal(app.requests.filter(request => request.options.method === 'POST').length, 1);
});

test('a successful write followed by refresh failure is not reported as a failed save', async () => {
    const app = setup();
    app.respond(async (_url, options) => {
        if (options.method !== 'POST') throw Error('offline');
        return { ok: true, json: async () => ({ message: 'Akun tersimpan.' }) };
    });
    await app.submit();
    assert.match(app.document.root.box.textContent, /Akun tersimpan.*Daftar belum dapat diperbarui/);
    assert.equal(app.error.hidden, true);
    assert.equal(app.window.closeCalls.length, 1);
});

test('network loss warns to inspect the account list before retrying', async () => {
    const app = setup();
    app.respond(async () => { throw Error('offline'); });
    await app.submit();
    assert.match(app.error.textContent, /Periksa daftar akun sebelum mencoba/);
    assert.equal(app.requests.length, 1);
    assert.equal(app.window.closeCalls.length, 0);
    assert.equal(app.button.disabled, false);
});

test('an old save response cannot replace a finance panel that has been left', async () => {
    const app = setup();
    let release;
    app.respond(() => new Promise(resolve => { release = resolve; }));
    const first = app.submit();
    const original = app.document.root;
    app.document.currentForm = null;
    release({ ok: true, json: async () => ({ message: 'Tersimpan' }) });
    await first;
    assert.equal(app.requests.length, 1);
    assert.equal(app.document.root, original);
    assert.equal(app.window.closeCalls.length, 0);
    assert.equal(app.document.events[0].type, 'ui-remote:changed', 'Even a detached save must invalidate the stale cache.');
});

test('archive sends the existing PATCH-spoofed form, never a delete', async () => {
    const app = setup();
    const archive = {
        dataset: {}, values: { _method: 'PATCH' }, action: 'http://example.test/v2/chart-of-accounts/3/archive',
        matches: () => true, reportValidity: () => true,
        querySelectorAll: () => [], setAttribute() {}, removeAttribute() {},
    };
    await app.document.root.listeners.submit({ target: archive, preventDefault() {} });
    assert.equal(app.requests[0].options.body.target, archive);
    assert.equal(app.requests[0].options.body.target.values._method, 'PATCH');
    assert.equal(app.requests[0].options.method, 'POST');
    assert.equal(app.window.closeCalls.length, 0);
});

test('server internals are not exposed and auth failures leave input intact', async () => {
    for (const status of [401, 403, 500]) {
        const app = setup();
        app.respond(async () => ({ ok: false, status, json: async () => ({ message: 'private internal detail' }) }));
        await app.submit();
        assert.doesNotMatch(app.error.textContent, /private internal detail/);
        assert.equal(app.form.values.account_code, '6198');
        assert.equal(app.button.disabled, false);
    }
});

test('a finance fetch started before a save cannot overwrite the refreshed remote cache', async () => {
    const workspace = readFileSync(new URL('../resources/views/v2/ui-workspace.blade.php', import.meta.url), 'utf8');
    const start = workspace.indexOf('function fetchRemote('), end = workspace.indexOf('\nasync function loadRemote(', start);
    const pending = [], remoteFetching = new Map(), remoteCache = new Map(), remoteVersions = new Map();
    const context = {
        remoteFetching, remoteCache, remoteVersions,
        partialUrl: url => url,
        fetch: () => new Promise(resolve => pending.push(resolve)),
    };
    vm.runInNewContext(workspace.slice(start, end) + '\nglobalThis.readPanel=fetchRemote;', context);
    const old = context.readPanel('bagan-akun', '/coa');
    remoteVersions.set('bagan-akun', 1);
    remoteFetching.delete('bagan-akun');
    const fresh = context.readPanel('bagan-akun', '/coa');
    pending[1]({ ok: true, text: async () => 'fresh accounts' });
    await fresh;
    pending[0]({ ok: true, text: async () => 'old accounts' });
    await old;
    assert.equal(remoteCache.get('bagan-akun').html, 'fresh accounts');
});
