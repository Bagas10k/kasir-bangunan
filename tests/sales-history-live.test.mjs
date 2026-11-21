import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import vm from 'node:vm';

const file = new URL('../resources/views/v2/sales-history-script.blade.php', import.meta.url);
const source = existsSync(file) ? readFileSync(file, 'utf8').replace(/^<script>\s*|\s*<\/script>\s*$/g, '') : '';
function setup() {
    const fields = Object.fromEntries(Object.entries({ sale_search: '', sale_method: '', start_date: '2026-11-20', end_date: '', history_per_page: '10', history_shift: 'active' }).map(([name, value]) => [name, { name, value, dispatchEvent() {} }]));
    fields.history_shift.options = [];
    fields.history_shift.replaceChildren = (...options) => { fields.history_shift.options = options; };
    Object.defineProperty(fields.history_shift, 'selectedOptions', { get: () => fields.history_shift.options.filter(o => o.value === fields.history_shift.value) });
    const formListeners = {}, handlers = {}, documentHandlers = {}, button = { disabled: false };
    const attributes = new Map();
    const form = { dataset: { historyUrl: '/v2/sales/history' }, elements: fields, reportValidity: () => true, addEventListener: (name, fn) => { formListeners[name] = fn; }, querySelector: () => button, querySelectorAll: () => [button] };
    const results = { innerHTML: 'old results', setAttribute: (k, v) => attributes.set(k, v), removeAttribute: k => attributes.delete(k) };
    const error = { hidden: true, textContent: '', focus() {} };
    const root = { dataset: {}, querySelector: s => s === '#history-filter-form' ? form : s === '#history-results' ? results : error, addEventListener: (name, fn) => { handlers[name] = fn; }, setAttribute: (k, v) => attributes.set(k, v), removeAttribute: k => attributes.delete(k) };
    const location = { href: 'http://example.test/?start_date=2026-11-20&sale_search=&date=2026-11-19#kasir', origin: 'http://example.test' };
    const history = { state: null, replaceState: (_s, _t, url) => { location.href = String(url); } };
    const requests = [];
    let responder = async url => ({ ok: true, redirected: false, json: async () => ({ html: '<p>new results</p>', filters: Object.fromEntries(new URL(url).searchParams), shifts: [{ id: 7, label: '#7 · sintetis' }] }) });
    const document = { getElementById: id => ({ 'sales-history': root, 'history-filter-form': form, 'history-results': results, 'history-error': error })[id], querySelector: () => root, createElement: () => ({ value: '', textContent: '' }), addEventListener: (name, fn) => { documentHandlers[name] = fn; } };
    const context = { window: { history, location }, document, location, history, URL, URLSearchParams, AbortController, Event, console, setTimeout, clearTimeout, FormData: class { constructor(f) { this.entries = Object.entries(f.elements).map(([key, field]) => [key, field.value]); } [Symbol.iterator]() { return this.entries[Symbol.iterator](); } }, fetch: (url, options) => { requests.push({ url: String(url), options }); return responder(String(url)); } };
    vm.runInNewContext(source, context);
    const submit = async () => { let prevented = false; const event = { target: form, preventDefault: () => { prevented = true; } }; await (formListeners.submit ?? handlers.submit)?.(event); return prevented; };
    const click = async ({ reset = false, page = 2, modified = false } = {}) => {
        let prevented = false;
        const link = { id: reset ? 'history-reset' : '', href: `http://example.test/?history_page=${page}`, closest: selector => selector.includes('pagination') ? (!reset ? link : null) : link };
        const event = { target: { closest: selector => selector.includes('history-reset') ? (reset ? link : null) : selector.includes('pagination') ? (!reset ? link : null) : link }, button: 0, metaKey: modified, preventDefault: () => { prevented = true; } };
        await handlers.click?.(event);
        return prevented;
    };
    return { fields, form, results, error, root, button, location, requests, documentHandlers, submit, click, respond: fn => { responder = fn; } };
}

test('apply filters uses a background GET, clean URL and only replaces history results', async () => {
    const app = setup();
    assert.equal(await app.submit(), true, 'Native navigation must be prevented.');
    const request = app.requests.at(-1);
    assert.equal(new URL(request.url).pathname, '/v2/sales/history');
    assert.equal(new URL(request.url).searchParams.get('start_date'), '2026-11-20');
    assert.equal(new URL(request.url).searchParams.has('sale_search'), false);
    assert.equal(request.options.credentials, 'same-origin');
    assert.equal(request.options.cache, 'no-store');
    assert.equal(app.results.innerHTML, '<p>new results</p>');
    assert.equal(app.location.href, 'http://example.test/?date=2026-11-19#kasir');
    assert.equal(app.fields.start_date.value, '2026-11-20');
    assert.equal(app.error.hidden, true);
});

test('pagination uses applied filters and reset returns to the active shift without navigation', async () => {
    const app = setup();
    app.fields.sale_search.value = 'INV-SYN';
    await app.submit();
    app.fields.sale_search.value = 'unsaved draft';
    assert.equal(await app.click(), true);
    assert.equal(new URL(app.requests.at(-1).url).searchParams.get('sale_search'), 'INV-SYN');
    assert.equal(new URL(app.requests.at(-1).url).searchParams.get('history_page'), '2');
    const count = app.requests.length;
    assert.equal(await app.click({ modified: true }), false);
    assert.equal(app.requests.length, count);
    assert.equal(await app.click({ reset: true }), true);
    assert.equal(app.fields.start_date.value, '');
    assert.equal(app.fields.sale_search.value, '');
    assert.equal(new URL(app.requests.at(-1).url).searchParams.get('history_shift'), 'active');
    assert.equal(new URL(app.requests.at(-1).url).searchParams.has('history_page'), false);
});

test('failed validation/network retains old results, input and usable controls', async () => {
    const app = setup();
    app.respond(async () => ({ ok: false, redirected: false, json: async () => ({ errors: { end_date: ['Rentang tanggal tidak sah.'] } }) }));
    await app.submit();
    assert.equal(app.results.innerHTML, 'old results');
    assert.equal(app.fields.start_date.value, '2026-11-20');
    assert.equal(app.error.hidden, false);
    assert.match(app.error.textContent, /Rentang tanggal tidak sah/);
    assert.equal(app.button.disabled, false);
    app.respond(async () => { throw new Error('offline'); });
    await app.submit();
    assert.equal(app.results.innerHTML, 'old results');
    assert.equal(app.button.disabled, false);
});

test('a late stale response cannot overwrite a newer filter result', async () => {
    const app = setup(), pending = [];
    app.respond(url => new Promise(resolve => pending.push({ url, resolve })));
    const first = app.submit();
    app.fields.sale_search.value = 'latest';
    const second = app.submit();
    const response = (html, url) => ({ ok: true, redirected: false, json: async () => ({ html, filters: Object.fromEntries(new URL(url).searchParams), shifts: [] }) });
    pending[1].resolve(response('latest results', pending[1].url));
    await second;
    pending[0].resolve(response('stale results', pending[0].url));
    await first;
    assert.equal(app.results.innerHTML, 'latest results');
});

test('live refresh retains applied filters and preserves unsubmitted form edits', async () => {
    const app = setup();
    app.fields.sale_search.value = 'applied';
    await app.submit();
    app.fields.sale_search.value = 'draft edit';
    await app.documentHandlers['ui-live:patched']?.();
    assert.equal(app.requests.length, 2);
    assert.equal(new URL(app.requests.at(-1).url).searchParams.get('sale_search'), 'applied');
    assert.equal(app.fields.sale_search.value, 'draft edit');
});
