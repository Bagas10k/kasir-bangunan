import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/views/v2/invoice-script.blade.php', import.meta.url), 'utf8')
    .replace(/^<script[^>]*>\s*|\s*<\/script>\s*$/g, '');

function setup() {
    const values = { invoice_direction: 'receivable', invoice_status: 'unpaid', invoice_search: '', invoice_start: '', invoice_end: '', invoice_per_page: '10' };
    const fields = Object.fromEntries(Object.entries(values).map(([key, value]) => [key, { value, dispatchEvent() {} }]));
    const button = { disabled: false };
    const form = {
        dataset: { invoiceUrl: 'http://example.test/v2/invoices' }, elements: fields, listeners: {},
        reportValidity: () => true, querySelector: () => button,
        addEventListener(type, fn) { this.listeners[type] = fn; }, dispatchEvent() {},
    };
    const tabs = ['receivable', 'payable'].map(direction => ({
        dataset: { invoiceTab: direction }, attrs: {}, classList: { toggle() {} },
        setAttribute(name, value) { this.attrs[name] = value; },
    }));
    const results = {
        dataset: { invoicePage: '1' }, innerHTML: 'original', busy: false, open: false,
        setAttribute() { this.busy = true; }, removeAttribute() { this.busy = false; },
        querySelector(selector) { return selector === 'dialog[open]' && this.open ? {} : null; },
        querySelectorAll() { return []; },
    };
    const error = { hidden: true, textContent: '', focus() { this.focused = true; } };
    const root = {
        listeners: {}, addEventListener(type, fn) { this.listeners[type] = fn; },
        querySelector(selector) { return ({ '#invoice-filter-form': form, '#invoice-results': results, '#invoice-error': error })[selector] ?? null; },
        querySelectorAll: () => tabs,
    };
    const document = {
        events: [], listeners: {}, getElementById: () => root,
        addEventListener(type, fn) { this.listeners[type] = fn; },
        dispatchEvent(event) { this.events.push(event); },
    };
    const requests = [];
    let respond = async url => {
        const params = new URL(url).searchParams;
        return { ok: true, json: async () => ({ html: 'fresh', filters: { ...values, ...Object.fromEntries(params), invoice_page: params.get('invoice_page') || 1 } }) };
    };
    const location = { href: 'http://example.test/?start_date=2026-11-21#tagihan', origin: 'http://example.test' };
    vm.runInNewContext(source, {
        document, location, history: { replaceState(_state, _title, url) { location.href = String(url); } },
        window: { uiMotion: { staggerRows() {} }, enhanceSelects() {} }, URL, AbortController,
        setTimeout: () => 1, clearTimeout() {},
        Event: class { constructor(type, options) { this.type = type; Object.assign(this, options); } },
        CustomEvent: class { constructor(type, options) { this.type = type; Object.assign(this, options); } },
        FormData: class { constructor() { this.values = Object.entries(fields).map(([key, field]) => [key, field.value]); } [Symbol.iterator]() { return this.values[Symbol.iterator](); } },
        fetch: (url, options) => { requests.push({ url: String(url), options }); return respond(String(url)); },
    });
    const click = match => root.listeners.click({
        button: 0, target: { closest: selector => match[selector] || null }, preventDefault() {},
    });
    return {
        form, fields, tabs, button, results, error, document, requests, location,
        submit: () => form.listeners.submit({ preventDefault() {} }),
        tab: direction => click({ '[data-invoice-tab]': tabs.find(tab => tab.dataset.invoiceTab === direction) }),
        page: number => click({ '.pagination a[href]': { href: 'http://example.test/?invoice_page=' + number } }),
        reset: () => click({ '#invoice-reset': {} }),
        respond: fn => { respond = fn; },
    };
}

test('tab change fetches the selected direction and preserves unrelated menu dates', async () => {
    const app = setup();
    await app.tab('payable');
    const url = new URL(app.requests[0].url);
    assert.equal(url.searchParams.get('invoice_direction'), 'payable');
    assert.equal(url.searchParams.get('invoice_status'), 'unpaid');
    assert.equal(url.searchParams.has('start_date'), false);
    assert.match(app.location.href, /start_date=2026-11-21/);
    assert.equal(app.fields.invoice_direction.value, 'payable');
    assert.equal(app.tabs[1].attrs['aria-selected'], 'true');
    assert.equal(app.results.innerHTML, 'fresh');
    assert.equal(app.document.events[0].type, 'ui-invoices:render');
    assert.equal(app.requests[0].options.cache, 'no-store');
});

test('pagination and uiLive use applied filters without overwriting unsent drafts', async () => {
    const app = setup();
    app.fields.invoice_search.value = 'AP-SYN';
    await app.submit();
    app.fields.invoice_search.value = 'draft not applied';
    await app.page(2);
    assert.equal(new URL(app.requests[1].url).searchParams.get('invoice_search'), 'AP-SYN');
    assert.equal(new URL(app.requests[1].url).searchParams.get('invoice_page'), '2');
    assert.equal(app.fields.invoice_search.value, 'draft not applied');
    await app.document.listeners['ui-live:patched']();
    assert.equal(new URL(app.requests[2].url).searchParams.get('invoice_page'), '2');
});

test('failed tab request retains old results, selected tab, and draft inputs', async () => {
    const app = setup();
    app.fields.invoice_search.value = 'draft';
    app.respond(async () => { throw Error('offline'); });
    await app.tab('payable');
    assert.equal(app.results.innerHTML, 'original');
    assert.equal(app.fields.invoice_direction.value, 'receivable');
    assert.equal(app.fields.invoice_search.value, 'draft');
    assert.equal(app.error.hidden, false);
    assert.equal(app.button.disabled, false);
    assert.equal(app.results.busy, false);
});

test('late responses cannot replace a newer tab or page', async () => {
    const app = setup();
    let first, second;
    app.respond(() => new Promise(resolve => { if (!first) first = resolve; else second = resolve; }));
    const old = app.tab('payable');
    const current = app.tab('receivable');
    second({ ok: true, json: async () => ({ html: 'current', filters: { invoice_direction: 'receivable', invoice_status: 'unpaid' } }) });
    await current;
    first({ ok: true, json: async () => ({ html: 'stale', filters: { invoice_direction: 'payable', invoice_status: 'unpaid' } }) });
    await old;
    assert.equal(app.results.innerHTML, 'current');
    assert.equal(app.tabs[0].attrs['aria-selected'], 'true');
});

test('reset keeps the current direction and restores unpaid with no date range', async () => {
    const app = setup();
    await app.tab('payable');
    app.fields.invoice_status.value = 'paid';
    await app.submit();
    await app.reset();
    const url = new URL(app.requests[2].url);
    assert.equal(url.searchParams.get('invoice_direction'), 'payable');
    assert.equal(url.searchParams.get('invoice_status'), 'unpaid');
    assert.equal(app.fields.invoice_status.value, 'unpaid');
    assert.equal(app.fields.invoice_start.value, '');
});

test('background refresh waits for an open payment/detail dialog to close', async () => {
    const app = setup();
    app.results.open = true;
    await app.document.listeners['ui-live:patched']();
    assert.equal(app.requests.length, 0);
    app.results.open = false;
    await app.document.listeners.close({ target: { closest: () => app.results } });
    assert.equal(app.requests.length, 1);
});

test('auth and server failures do not expose internal messages', async () => {
    for (const status of [401, 403, 500]) {
        const app = setup();
        app.respond(async () => ({ ok: false, status, json: async () => ({ message: 'private trace', errors: { internal: ['private trace'] } }) }));
        await app.submit();
        assert.equal(app.error.hidden, false);
        assert.doesNotMatch(app.error.textContent, /private trace/);
        assert.equal(app.results.innerHTML, 'original');
    }
});

test('Lunasi uses the exact decimal balance rather than digits from the displayed Rupiah', () => {
    const workspace = readFileSync(new URL('../resources/views/v2/ui-workspace.blade.php', import.meta.url), 'utf8');
    const enhance = workspace.slice(workspace.indexOf('function enhanceCollect('), workspace.indexOf('// Form isian di halaman'));
    let fill;
    const amount = { id: 'amt-1', value: '', dispatchEvent() {}, focus() {}, closest: () => ({ append(button) { fill = button; } }) };
    const node = () => ({ append() {}, before() {}, prepend() {}, addEventListener(type, callback) { this[type] = callback; } });
    const context = {
        document: { createElement: node }, segmentSelect() {},
        cellText: (_row, label) => ({ Nomor: 'AR-SYN', Nama: 'Sintetis', Sisa: 'Rp 1.250,50' })[label] || '',
        Event: class {}, 
    };
    vm.createContext(context);
    vm.runInContext(enhance + '\nthis.enhanceCollect=enhanceCollect;', context);
    const form = { dataset: {}, prepend() {}, querySelector: selector => selector === 'input[name=amount]' ? amount : null };
    context.enhanceCollect(form, { dialog: { closest: () => ({ dataset: { remaining: '1250.50' } }), querySelector: () => null } });
    fill.click();
    assert.equal(amount.value, '1250.50');
});

test('background updates do not cancel a tab selection still in flight', async () => {
    const app = setup();
    let release;
    app.respond(() => new Promise(resolve => { release = resolve; }));
    const change = app.tab('payable');
    app.document.listeners['ui-live:patched']();
    assert.equal(app.requests.length, 1);
    app.respond(async url => ({ ok: true, json: async () => ({ html: 'current', filters: Object.fromEntries(new URL(url).searchParams) }) }));
    release({ ok: true, json: async () => ({ html: 'payables', filters: { invoice_direction: 'payable', invoice_status: 'unpaid' } }) });
    await change;
    assert.equal(app.fields.invoice_direction.value, 'payable');
    assert.equal(app.requests.length, 2);
    assert.equal(new URL(app.requests[1].url).searchParams.get('invoice_direction'), 'payable');
});
