import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const serverTablesBlade = readFileSync(new URL('../resources/views/v2/ui-server-tables.blade.php', import.meta.url), 'utf8');
const serverTablesScript = serverTablesBlade.replace(/^<script[^>]*>\s*|\s*<\/script>\s*$/g, '');
const expenseView = readFileSync(new URL('../resources/views/expenses/index.blade.php', import.meta.url), 'utf8');
const accountsView = readFileSync(new URL('../resources/views/v2/accounts.blade.php', import.meta.url), 'utf8');
const driverView = readFileSync(new URL('../resources/views/v2/driver-operations.blade.php', import.meta.url), 'utf8');
const workspaceView = readFileSync(new URL('../resources/views/v2/workspace.blade.php', import.meta.url), 'utf8');
const uiLive = readFileSync(new URL('../resources/views/v2/ui-live.blade.php', import.meta.url), 'utf8');

test('all operational tables and expenses include pagination markup cleanly', () => {
    assert.match(expenseView, /@include\('partials\.pagination', \['paginator' => \$expenses\]\)/);
    assert.match(expenseView, /name="per_page"/);
    assert.match(accountsView, /id="accounts-pager"/);
    assert.match(accountsView, /id="accounts-page-size"/);
    assert.match(driverView, /data-server-table="deliveries"/);
    assert.match(driverView, /data-ui-table data-ui-noun="uang muka"/);
    assert.match(workspaceView, /data-server-table="shifts"/);
    assert.match(workspaceView, /data-server-table="payments"/);
    assert.match(workspaceView, /@include\('v2\.ui-server-tables'\)/);
    assert.match(uiLive, /window\.uiLive=\{refresh,saved,runHooks,onPatch/);
});

test('server table controller fetches page changes, filters, and rebinds hooks without full reload', async () => {
    const requests = [];
    const hookCalls = [];
    const docEvents = [];
    const rootEvents = [];

    const chipPending = {
        dataset: { uiChip: 'status:pending,proposed' },
        attrs: { 'aria-pressed': 'true' },
        listeners: {},
        getAttribute(k) { return this.attrs[k] ?? null; },
        setAttribute(k, v) { this.attrs[k] = v; },
        addEventListener(t, fn) { this.listeners[t] = fn; },
    };
    const chipPosted = {
        dataset: { uiChip: 'status:posted' },
        attrs: { 'aria-pressed': 'false' },
        listeners: {},
        getAttribute(k) { return this.attrs[k] ?? null; },
        setAttribute(k, v) { this.attrs[k] = v; },
        addEventListener(t, fn) { this.listeners[t] = fn; },
    };
    const searchInput = {
        value: '',
        listeners: {},
        addEventListener(t, fn) { this.listeners[t] = fn; },
        closest: () => null,
    };
    const toolbar = {
        compareDocumentPosition: () => 4,
        querySelector: (s) => (s === '[data-ui-search]' ? searchInput : null),
        querySelectorAll: (s) => (s === '[data-ui-chip]' ? [chipPending, chipPosted] : []),
    };
    const section = {
        querySelectorAll: (s) => (s === '[data-ui-toolbar]' ? [toolbar] : []),
    };
    const errorBox = {
        className: '', hidden: true, textContent: '', tabIndex: 0, attrs: {},
        setAttribute(k, v) { this.attrs[k] = v; },
        focus() { this.focused = true; },
    };
    const root = {
        dataset: {
            serverTable: 'payments',
            tableUrl: 'http://example.test/v2/tables/payments',
            tableDefaultFilter: 'status:pending,proposed',
        },
        innerHTML: '',
        attrs: {},
        listeners: {},
        closest: (s) => (s === 'section' ? section : null),
        before() {},
        setAttribute(k, v) { this.attrs[k] = v; },
        removeAttribute(k) { delete this.attrs[k]; },
        querySelector: () => null,
        addEventListener(t, fn) { this.listeners[t] = fn; },
        dispatchEvent(ev) { rootEvents.push(ev); },
    };

    const document = {
        docListeners: {},
        querySelector: (s) => (s === '[data-server-table],[data-ui-table]' ? root : null),
        querySelectorAll: (s) => (s === '[data-server-table]' ? [root] : []),
        createElement: () => errorBox,
        addEventListener(t, fn) { this.docListeners[t] = fn; },
        dispatchEvent(ev) { docEvents.push(ev); },
    };
    const window = {
        uiLive: { runHooks: (el) => hookCalls.push(el) },
        enhanceSelects() {},
        v2TableActions: { enhance() {} },
        uiMotion: { staggerRows() {} },
    };

    vm.runInNewContext(serverTablesScript, {
        window,
        document,
        Node: { DOCUMENT_POSITION_FOLLOWING: 4 },
        location: { href: 'http://example.test/#persetujuan', origin: 'http://example.test' },
        URL,
        AbortController,
        setTimeout: (fn, ms) => {
            if (ms < 1000) { fn(); }
            return 1;
        },
        clearTimeout() {},
        CustomEvent: class { constructor(type, opts) { this.type = type; Object.assign(this, opts); } },
        fetch: async (url) => {
            requests.push(String(url));
            const parsed = new URL(String(url));
            return {
                ok: true,
                redirected: false,
                status: 200,
                json: async () => ({
                    html: '<table><tbody><tr><td>Halaman ' + parsed.searchParams.get('page') + '</td></tr></tbody></table>',
                    filters: {
                        q: parsed.searchParams.get('q') || '',
                        filter: parsed.searchParams.get('filter') || 'all',
                        per_page: Number(parsed.searchParams.get('per_page') || 10),
                    },
                    total: 24,
                    page: Number(parsed.searchParams.get('page') || 1),
                    last_page: 3,
                }),
            };
        },
    });

    const flush = () => new Promise((resolve) => setImmediate(resolve));

    // Click page 2 button.
    root.listeners.click({
        target: { closest: (sel) => (sel === 'button[data-st-page]' ? { disabled: false, dataset: { stPage: '2' } } : null) },
    });
    await flush();
    assert.equal(requests.length, 1);
    assert.equal(new URL(requests[0]).searchParams.get('page'), '2');
    assert.equal(new URL(requests[0]).searchParams.get('filter'), 'status:pending,proposed');
    assert.match(root.innerHTML, /Halaman 2/);
    assert.equal(hookCalls.length, 1);
    assert.equal(docEvents[0].type, 'ui-server-table:render');
    assert.equal(rootEvents[0].type, 'ui-table:render');

    // Switch filter chip to posted -> resets to page 1.
    chipPosted.listeners.click();
    await flush();
    assert.equal(requests.length, 2);
    assert.equal(new URL(requests[1]).searchParams.get('filter'), 'status:posted');
    assert.equal(new URL(requests[1]).searchParams.get('page'), '1');
    assert.equal(chipPosted.attrs['aria-pressed'], 'true');
    assert.equal(chipPending.attrs['aria-pressed'], 'false');

    // Change page size to 25 -> resets to page 1.
    root.listeners.change({
        target: { closest: (sel) => (sel === 'select[data-st-size]' ? { value: '25' } : null) },
    });
    await flush();
    assert.equal(requests.length, 3);
    assert.equal(new URL(requests[2]).searchParams.get('per_page'), '25');
    assert.equal(new URL(requests[2]).searchParams.get('page'), '1');
});
