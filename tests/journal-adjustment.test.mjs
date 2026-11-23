import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/views/v2/journal-adjustment-script.blade.php', import.meta.url), 'utf8')
    .replace(/^<script[^>]*>\s*|\s*<\/script>\s*$/g, '');

function setup() {
    const error = { hidden: true, textContent: '', focus() { this.focused = true; } };
    const password = { value: 'password' };
    const button = { disabled: false };
    const form = {
        dataset: {}, action: 'http://example.test/v2/journals/adjustments',
        values: { account_code: '1101', target_balance: '1000.00', description: 'Koreksi kas', password: 'password', _token: 'synthetic-csrf' },
        listeners: {}, reportValidity: () => true, focused: false,
        elements: { namedItem: name => name === 'password' ? password : (name === '_token' ? { value: 'synthetic-csrf' } : null) },
        querySelector: () => error, querySelectorAll: () => [button],
        addEventListener(type, fn) { this.listeners[type] = fn; },
        setAttribute() {}, removeAttribute() {}, focus() { this.focused = true; },
    };
    function makeRoot() {
        const box = { hidden: true, textContent: '' };
        return {
            dataset: { refreshUrl: 'http://example.test/journals?start_date=2026-11-01&per_page=25' },
            box,
            querySelector: selector => selector === '#adjustment-form' ? document.currentForm : (selector === '[data-journal-feedback]' ? box : null),
            replaceWith(next) { document.root = next; },
        };
    }
    const document = {
        root: null, currentForm: form, events: [],
        querySelector: selector => selector === '[data-journal-root]' ? document.root
            : (selector === 'meta[name=csrf-token]' ? { content: 'synthetic-csrf' } : document.currentForm),
        dispatchEvent(event) { this.events.push(event); },
    };
    document.root = makeRoot();
    const window = { enhanceSelects() {}, uiMotion: { enter() {} } };
    const requests = [];
    let responder = async (_url, options) => options.method === 'POST'
        ? { ok: true, status: 200, json: async () => ({ message: 'Penyesuaian tersimpan.' }) }
        : { ok: true, text: async () => '<fixture>' };
    const context = {
        window, document, location: { href: 'http://example.test/#jurnal' }, URL,
        FormData: class { constructor(target) { this.target = target; } },
        DOMParser: class { parseFromString() { return { querySelector: () => makeRoot() }; } },
        CustomEvent: class { constructor(type, options) { this.type = type; Object.assign(this, options); } },
        fetch: (url, options) => { requests.push({ url: String(url), options }); return responder(String(url), options); },
    };
    vm.runInNewContext(source, context);
    return {
        form, button, error, password, document, requests,
        submit: () => form.listeners.submit({ preventDefault() {} }),
        respond: fn => { responder = fn; },
    };
}

test('adjustment saves with CSRF then refreshes only the journal summary without losing the applied filter', async () => {
    const app = setup();
    await app.submit();
    assert.equal(app.requests.length, 2);
    assert.equal(app.requests[0].options.method, 'POST');
    assert.equal(app.requests[0].options.headers['X-CSRF-TOKEN'], 'synthetic-csrf');
    assert.equal(app.requests[0].options.body.target, app.form);
    const refresh = new URL(app.requests[1].url);
    assert.equal(refresh.searchParams.get('partial'), '1');
    assert.equal(refresh.searchParams.get('start_date'), '2026-11-01');
    assert.equal(app.document.root.box.textContent, 'Penyesuaian tersimpan.');
    assert.equal(app.document.root.box.hidden, false);
    assert.equal(app.password.value, '');
    assert.equal(app.document.events[0].type, 'ui-remote:changed');
    assert.equal(app.button.disabled, false);
    assert.equal(app.form.dataset.busy, undefined);
});

test('validation errors keep other inputs, clear the password and do not refresh', async () => {
    const app = setup();
    app.respond(async () => ({ ok: false, status: 422, json: async () => ({ errors: { password: ['Password konfirmasi tidak sesuai.'] } }) }));
    await app.submit();
    assert.equal(app.requests.length, 1);
    assert.equal(app.form.values.target_balance, '1000.00');
    assert.equal(app.error.textContent, 'Password konfirmasi tidak sesuai.');
    assert.equal(app.error.hidden, false);
    assert.equal(app.password.value, '');
    assert.equal(app.document.root.box.hidden, true);
    assert.equal(app.button.disabled, false);
});

test('double submit sends one write while a request is outstanding', async () => {
    const app = setup();
    let release;
    app.respond((_url, options) => options.method === 'POST'
        ? new Promise(resolve => { release = resolve; })
        : { ok: true, text: async () => '<fixture>' });
    const first = app.submit();
    await app.submit();
    assert.equal(app.requests.length, 1);
    assert.equal(app.button.disabled, true);
    release({ ok: true, status: 200, json: async () => ({ message: 'Tersimpan' }) });
    await first;
    assert.equal(app.requests.filter(request => request.options.method === 'POST').length, 1);
});

test('a successful write followed by refresh failure still says the journal was saved', async () => {
    const app = setup();
    app.respond(async (_url, options) => {
        if (options.method !== 'POST') throw Error('offline');
        return { ok: true, status: 200, json: async () => ({ message: 'Penyesuaian tersimpan.' }) };
    });
    await app.submit();
    assert.match(app.document.root.box.textContent, /Penyesuaian tersimpan.*Daftar belum dapat diperbarui/);
    assert.equal(app.error.hidden, true);
});

test('network loss warns to inspect the journal before retrying', async () => {
    const app = setup();
    app.respond(async () => { throw Error('offline'); });
    await app.submit();
    assert.match(app.error.textContent, /Periksa daftar jurnal sebelum mencoba/);
    assert.equal(app.password.value, '');
    assert.equal(app.requests.length, 1);
    assert.equal(app.button.disabled, false);
});

test('a late response cannot replace a finance panel that was already left', async () => {
    const app = setup();
    let release;
    app.respond(() => new Promise(resolve => { release = resolve; }));
    const first = app.submit();
    const replacement = { box: { hidden: true, textContent: '' }, dataset: {}, querySelector: () => null, replaceWith() {} };
    app.document.root = replacement;
    release({ ok: true, status: 200, json: async () => ({ message: 'Tersimpan' }) });
    await first;
    assert.equal(app.requests.length, 1, 'A stale response must not trigger a refresh request.');
    assert.equal(app.document.root, replacement);
    assert.equal(replacement.box.hidden, true);
    assert.equal(app.document.events[0].type, 'ui-remote:changed', 'The stale cache is still invalidated.');
});

test('server internals are not shown for auth or server failures', async () => {
    for (const status of [401, 403, 419, 500]) {
        const app = setup();
        app.respond(async () => ({ ok: false, status, json: async () => ({ message: 'private internal detail' }) }));
        await app.submit();
        assert.doesNotMatch(app.error.textContent, /private internal detail/);
        assert.equal(app.password.value, '');
        assert.equal(app.button.disabled, false);
        assert.equal(app.error.hidden, false);
    }
});
