import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const expenseView = readFileSync(new URL('../resources/views/expenses/index.blade.php', import.meta.url), 'utf8');
const expenseStyles = readFileSync(new URL('../resources/views/expenses/theme-styles.blade.php', import.meta.url), 'utf8');
const expenseScriptRaw = readFileSync(new URL('../resources/views/v2/expense-script.blade.php', import.meta.url), 'utf8')
    .replace(/^<script[^>]*>\s*|\s*<\/script>\s*$/g, '');
const uiResult = readFileSync(new URL('../resources/views/v2/ui-result.blade.php', import.meta.url), 'utf8');
const uiWorkspace = readFileSync(new URL('../resources/views/v2/ui-workspace.blade.php', import.meta.url), 'utf8');
const filterScript = readFileSync(new URL('../resources/views/partials/filter-disclosure-script.blade.php', import.meta.url), 'utf8');
const filterStyles = readFileSync(new URL('../resources/views/partials/table-filter-styles.blade.php', import.meta.url), 'utf8');

function setupExpenseScript() {
    const button = { disabled: false };
    const error = { hidden: true, textContent: '', focus() { this.focused = true; } };
    const amountField = { value: '125.000,50' };
    const form = {
        dataset: {},
        action: 'http://example.test/v2/expenses',
        values: { date: '2026-11-24', account_code: '6101', source: 'cash', amount: '125.000,50', description: 'Token listrik' },
        listeners: {},
        elements: { namedItem: (name) => (name === 'amount' ? amountField : null) },
        reportValidity: () => true,
        closest: () => null,
        querySelector: (sel) => (sel === '[data-expense-error]' ? error : button),
        setAttribute() {},
        removeAttribute() {},
        addEventListener(type, fn) { this.listeners[type] = fn; },
    };
    function makeRoot() {
        const box = { hidden: true, textContent: '' };
        const filterField = { value: 'cash' };
        const filters = { values: { payment_method: 'cash' }, elements: { namedItem: () => filterField } };
        return {
            dataset: { refreshUrl: 'http://example.test/expenses' },
            box,
            filterField,
            contains: () => false,
            querySelector: (sel) => (sel === 'form.exp-filter' ? filters : box),
            replaceWith(next) { document.root = next; },
        };
    }
    const document = {
        root: null,
        currentForm: form,
        events: [],
        getElementById: (id) => (id === 'expense-form' ? document.currentForm : null),
        querySelector: (sel) => (sel === '[data-expense-root]' ? document.root : { content: 'synthetic-csrf' }),
        querySelectorAll: () => [],
        dispatchEvent(event) { this.events.push(event); },
    };
    document.root = makeRoot();
    const window = {
        closeCalls: [],
        closeModal: (id) => window.closeCalls.push(id),
        uiMoney: { toRaw: (v) => String(v).replace(/\./g, '').replace(',', '.') },
        uiTableFilters: { scan() {} },
        enhanceSelects() {},
        uiMotion: { enter() {} },
    };
    const requests = [];
    let responder = async (_url, options) => (options.method === 'POST'
        ? { ok: true, status: 201, json: async () => ({ expense_number: 'EXP-20261124-000001' }) }
        : { ok: true, text: async () => '<fixture>' });

    class FakeFormData {
        constructor(target) {
            this.map = new Map(Object.entries(target?.values ?? {}));
        }
        set(k, v) { this.map.set(k, v); }
        get(k) { return this.map.get(k); }
        [Symbol.iterator]() { return this.map.entries(); }
    }

    vm.runInNewContext(expenseScriptRaw, {
        window,
        document,
        location: { href: 'http://example.test/#pengeluaran' },
        URL,
        FormData: FakeFormData,
        DOMParser: class { parseFromString() { return { querySelector: () => makeRoot() }; } },
        CustomEvent: class { constructor(type, options) { this.type = type; Object.assign(this, options); } },
        fetch: (url, options) => { requests.push({ url: String(url), options }); return responder(String(url), options); },
    });

    return {
        form,
        button,
        error,
        document,
        window,
        requests,
        submit: () => form.listeners.submit({ preventDefault() {} }),
        respond: (fn) => { responder = fn; },
    };
}

test('form pengeluaran memakai nama kolom account_code dan source (cash/bank) sesuai controller V2', () => {
    assert.match(expenseView, /name="account_code"/);
    assert.match(expenseView, /name="source"/);
    assert.match(expenseView, /value="bank"/);
    assert.doesNotMatch(expenseView, /<select[^>]*name="category"[^>]*id="category"/);
    assert.doesNotMatch(expenseView, /<select[^>]*name="payment_method"[^>]*id="payment_method"/);
    assert.match(expenseView, /@include\('v2\.expense-script'\)/);
});

test('tema pengeluaran menyertakan gaya modal mandiri dan aturan toast V2', () => {
    assert.match(expenseStyles, /#modal-expense\.modal-overlay/);
    assert.match(expenseStyles, /#modal-expense\.modal-overlay\.open/);
    assert.match(uiResult, /\\\/v2\\\/expenses\$/);
    assert.match(uiWorkspace, /remoteState\.get\(owner\.dataset\.task\)!==url\.toString\(\)/);
    assert.match(uiWorkspace, /data-teleported-modal/);
    assert.match(filterScript, /\.exp-filter/);
    assert.match(filterScript, /\.exp-filter-actions/);
    assert.match(filterStyles, /\.exp-filter-actions/);
    assert.match(expenseView, /data-date-range-start="expense-filter-end"/);
});

test('simpan pengeluaran mengirim nominal mentah dengan CSRF lalu memperbarui panel tanpa reload', async () => {
    const app = setupExpenseScript();
    await app.submit();
    assert.equal(app.requests.length, 2);
    assert.equal(app.requests[0].options.method, 'POST');
    assert.equal(app.requests[0].options.headers['X-CSRF-TOKEN'], 'synthetic-csrf');
    assert.equal(app.requests[0].options.body.get('amount'), '125000.50');
    assert.equal(app.requests[0].options.body.get('account_code'), '6101');
    assert.equal(app.requests[0].options.body.get('source'), 'cash');
    assert.deepEqual(app.window.closeCalls, ['modal-expense']);
    assert.equal(app.document.events[0].type, 'ui-remote:changed');
    assert.equal(new URL(app.requests[1].url).searchParams.get('partial'), '1');
    assert.equal(new URL(app.requests[1].url).searchParams.get('payment_method'), 'cash');
    assert.match(app.document.root.box.textContent, /EXP-20261124-000001/);
    assert.equal(app.button.disabled, false);
});

test('galat validasi pengeluaran mempertahankan modal terbuka dan menampilkan pesan', async () => {
    const app = setupExpenseScript();
    app.respond(async () => ({ ok: false, status: 422, json: async () => ({ errors: { amount: ['Saldo Kas Tunai tidak cukup.'] } }) }));
    await app.submit();
    assert.equal(app.requests.length, 1);
    assert.equal(app.error.hidden, false);
    assert.equal(app.error.textContent, 'Saldo Kas Tunai tidak cukup.');
    assert.equal(app.error.focused, true);
    assert.equal(app.window.closeCalls.length, 0);
    assert.equal(app.button.disabled, false);
});

test('kirim ganda saat request berjalan hanya mengirim satu POST', async () => {
    const app = setupExpenseScript();
    let release;
    app.respond(async (_url, options) => (options.method === 'POST'
        ? new Promise((resolve) => { release = resolve; })
        : { ok: true, text: async () => '' }));
    const first = app.submit();
    await app.submit();
    assert.equal(app.requests.length, 1);
    assert.equal(app.button.disabled, true);
    release({ ok: true, status: 201, json: async () => ({ expense_number: 'EXP-01' }) });
    await first;
    assert.equal(app.button.disabled, false);
});
