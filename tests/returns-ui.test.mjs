import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const purchaseReturnBlade = readFileSync(new URL('../resources/views/v2/purchase-returns.blade.php', import.meta.url), 'utf8');
const stockOpnameBlade = readFileSync(new URL('../resources/views/v2/stock-opname.blade.php', import.meta.url), 'utf8');
const saleDetailScript = readFileSync(new URL('../resources/views/v2/sale-detail-script.blade.php', import.meta.url), 'utf8')
    .replace(/^<script[^>]*>\s*|\s*<\/script>\s*$/g, '');
const purchaseReturnScript = (purchaseReturnBlade.match(/<script>([\s\S]*?)<\/script>/) || [])[1] || '';

function makeClassList() {
    return { add() {}, remove() {}, toggle() {} };
}

function extractBalancedTag(source, startMarker, tagName) {
    const start = source.indexOf(startMarker);
    assert.notEqual(start, -1, `Expected marker ${startMarker}`);
    let depth = 0;
    const re = new RegExp(`<\\/?${tagName}\\b[^>]*>`, 'g');
    re.lastIndex = start;
    let m;
    while ((m = re.exec(source))) {
        if (m[0].startsWith('</')) {
            depth--;
            if (depth === 0) {
                return source.slice(start, re.lastIndex);
            }
        } else {
            depth++;
        }
    }
    assert.fail(`Unbalanced <${tagName}> starting at ${startMarker}`);
}

test('purchase-return-dialog and stock-opname-dialog reside inside their workspace sections (not trapped inside hidden #admin)', () => {
    const prSection = extractBalancedTag(purchaseReturnBlade, '<section class="card" id="purchase-returns-history"', 'section');
    assert.match(prSection, /id="purchase-return-dialog"/, '#purchase-return-dialog must stay inside #purchase-returns-history');

    const opSection = extractBalancedTag(stockOpnameBlade, '<section class="card has-head-action" id="stock-opname-history"', 'section');
    assert.match(opSection, /id="stock-opname-dialog"/, '#stock-opname-dialog must stay inside #stock-opname-history');
});

test('purchase-return flow validates clearly, recovers from hidden #admin and fetch failures, and saves cleanly', async () => {
    const bodyChildren = [];
    const hiddenAdmin = { id: 'admin', hidden: false, parentElement: null };
    const errEl = { textContent: '', hidden: true, focused: false, focus() { this.focused = true; } };
    const submitBtn = { disabled: false };
    const subEl = { textContent: '' };
    const noteEl = { textContent: '' };
    const refundWrap = { hidden: true };
    const bankWrap = { hidden: true };
    const bankSel = { disabled: true, value: '1102' };
    const methodSel = { value: 'main', addEventListener() {} };
    const idempInput = { value: '' };
    const dateInput = { value: '2026-11-26', defaultValue: '2026-11-26', min: '', max: '2026-11-26', focused: false, focus() { this.focused = true; }, dispatchEvent() {} };
    const reasonInput = { value: '', focused: false, focus() { this.focused = true; } };
    const qtyInputs = [];

    const dialog = {
        id: 'purchase-return-dialog',
        open: false,
        parentElement: hiddenAdmin,
        listeners: {},
        showModal() {
            if (this.parentElement === hiddenAdmin) {
                throw new Error('Dialog remained inside #admin');
            }
            this.open = true;
        },
        close() { this.open = false; },
        addEventListener(type, fn) { this.listeners[type] = fn; },
        querySelector(sel) {
            if (sel === '[data-busy="1"]') { return form.dataset.busy ? form : null; }
            return null;
        },
        getBoundingClientRect() { return { left: 0, right: 100, top: 0, bottom: 100 }; },
    };

    const rowsBox = {
        children: [],
        replaceChildren() {
            this.children = [];
            qtyInputs.length = 0;
        },
        append(...nodes) {
            this.children.push(...nodes);
            for (const n of nodes) {
                for (const input of n._inputs || []) {
                    qtyInputs.push(input);
                }
            }
        },
    };

    const form = {
        action: '',
        dataset: {},
        attrs: {},
        listeners: {},
        elements: {
            idempotency_key: idempInput,
            return_date: dateInput,
            reason: reasonInput,
        },
        setAttribute(k, v) { this.attrs[k] = v; },
        removeAttribute(k) { delete this.attrs[k]; },
        addEventListener(type, fn) { this.listeners[type] = fn; },
        querySelector(sel) {
            if (sel === '[data-error]') { return errEl; }
            if (sel === 'button[type=submit]') { return submitBtn; }
            return null;
        },
    };

    const docListeners = {};
    const makeEl = tag => ({
        tag,
        _inputs: [],
        children: [],
        className: '',
        textContent: '',
        style: {},
        dataset: {},
        attrs: {},
        classList: makeClassList(),
        setAttribute(k, v) { this.attrs[k] = v; },
        append(...nodes) {
            this.children.push(...nodes);
            for (const n of nodes) {
                if (n?.tag === 'input') { this._inputs.push(n); }
                if (n?._inputs) { this._inputs.push(...n._inputs); }
            }
        },
        focus() { this.focused = true; },
    });

    const byId = {
        'purchase-return-dialog': dialog,
        'purchase-return-form': form,
        'purchase-return-rows': rowsBox,
        'purchase-return-sub': subEl,
        'purchase-return-note': noteEl,
        'purchase-return-refund-fields': refundWrap,
        'pr-method': methodSel,
        'pr-bank-wrap': bankWrap,
        'pr-bank': bankSel,
    };

    let savedForms = [];
    let fetchHandler;
    const document = {
        body: {
            append(node) {
                node.parentElement = this;
                bodyChildren.push(node);
            },
        },
        getElementById: id => byId[id] || null,
        querySelector: sel => sel === 'meta[name="csrf-token"]' ? { content: 'csrf-tok' } : null,
        createElement: tag => makeEl(tag),
        addEventListener(type, fn) { docListeners[type] = fn; },
    };

    rowsBox.querySelectorAll = sel => (sel === 'input[data-receipt-item-id]' ? qtyInputs : []);
    rowsBox.querySelector = sel => (sel === 'input[data-receipt-item-id]:not(:disabled)' ? qtyInputs.find(i => !i.disabled) || null : null);

    vm.runInNewContext(purchaseReturnScript, {
        document,
        window: {
            crypto: { randomUUID: () => 'uuid-1' },
            uiLive: { saved: async f => { savedForms.push(f); } },
        },
        crypto: { randomUUID: () => 'uuid-1' },
        getComputedStyle: node => ({ display: node.id === 'admin' ? 'none' : 'block' }),
        Event: class { constructor(t) { this.type = t; } },
        fetch: (url, opts) => fetchHandler(String(url), opts),
    });

    fetchHandler = async () => ({
        ok: true,
        redirected: false,
        json: async () => ({
            receipt_number: 'PB-001',
            supplier_name: 'PT Sumber',
            receipt_date: '2026-11-20',
            invoice_remaining: 50000,
            invoice_paid: 0,
            has_pending_payment: false,
            items: [{ receipt_item_id: 7, name: 'Semen 50kg', received_qty: 10, remaining_qty: 5, warehouse_stock: 5, max_returnable_qty: 5, unit: 'zak' }],
        }),
    });

    const triggerBtn = {
        dataset: {
            returnableUrl: 'http://example.test/v2/receipts/9/returnable',
            postUrl: 'http://example.test/v2/receipts/9/returns',
        },
        closest: () => null,
    };

    await docListeners.click({
        preventDefault() {},
        target: { closest: sel => sel === '[data-purchase-return]' ? triggerBtn : null },
    });

    assert.equal(dialog.parentElement, document.body, 'dialog inside hidden #admin must move to document.body');
    assert.equal(dialog.open, true);
    assert.equal(qtyInputs.length, 1);

    // 1) Submit with empty qty -> shows visible Indonesian error instead of silent fail
    await form.listeners.submit({ preventDefault() {} });
    assert.equal(errEl.hidden, false);
    assert.match(errEl.textContent, /jumlah retur/i);

    // 2) Submit with short reason (<5) -> shows visible Indonesian error
    qtyInputs[0].value = '2';
    reasonInput.value = 'abc';
    await form.listeners.submit({ preventDefault() {} });
    assert.equal(errEl.hidden, false);
    assert.match(errEl.textContent, /5 karakter/i);

    // 3) Network error -> shows visible error and releases busy lock
    reasonInput.value = 'Barang pecah saat kirim';
    fetchHandler = async () => { throw new Error('Koneksi terputus'); };
    await form.listeners.submit({ preventDefault() {} });
    assert.equal(errEl.hidden, false);
    assert.match(errEl.textContent, /Koneksi terputus/);
    assert.equal(form.dataset.busy, undefined);
    assert.equal(submitBtn.disabled, false);

    // 4) Success -> closes dialog and invokes uiLive.saved(form)
    let postedBody = null;
    fetchHandler = async (_url, opts) => {
        postedBody = JSON.parse(opts.body);
        return { ok: true, redirected: false, json: async () => ({ number: 'RTP-001' }) };
    };
    await form.listeners.submit({ preventDefault() {} });
    assert.equal(postedBody.reason, 'Barang pecah saat kirim');
    assert.deepEqual(postedBody.items, [{ receipt_item_id: 7, quantity: '2' }]);
    assert.equal(dialog.open, false);
    assert.equal(savedForms.length, 1);
});

test('sale-return submit validates explicitly, handles fetch failure, and triggers uiLive.saved before dialog closes', async () => {
    const lifecycle = [];
    const errEl = { textContent: '', hidden: true, focused: false, focus() { this.focused = true; } };
    const submitBtn = { disabled: false };
    const keyInput = { value: '' };
    const qtyInput = { value: '', disabled: false, dataset: { returnSaleItem: '12' }, focus() {} };
    const dateInput = { value: '2026-11-26', min: '2026-11-20', max: '2026-11-26', focus() {} };
    const reasonInput = { value: '', focus() {} };
    const content = { innerHTML: '', textContent: '', querySelector: () => null };

    const saleForm = {
        action: 'http://example.test/v2/sales/15/returns',
        dataset: {},
        attrs: {},
        elements: { return_date: dateInput, reason: reasonInput },
        setAttribute(k, v) { this.attrs[k] = v; },
        removeAttribute(k) { delete this.attrs[k]; },
        querySelector(sel) {
            if (sel === '[data-error]') { return errEl; }
            if (sel === 'button[type=submit]') { return submitBtn; }
            if (sel === 'input[name=idempotency_key]') { return keyInput; }
            return null;
        },
        querySelectorAll(sel) {
            return sel === '[data-return-sale-item]' ? [qtyInput] : [];
        },
    };

    const dialog = {
        open: true,
        dataset: { url: 'http://example.test/v2/sales/__ID__/detail' },
        listeners: {},
        querySelector: s => s === '[data-sale-detail-content]' ? content : null,
        close() {
            lifecycle.push('close');
            this.open = false;
            saleForm.isConnected = false;
            this.listeners.close?.();
        },
        addEventListener(type, fn) { this.listeners[type] = fn; },
    };
    saleForm.isConnected = true;

    const docListeners = {};
    const document = {
        getElementById: () => dialog,
        querySelector: sel => sel === 'meta[name="csrf-token"]' ? { content: 'csrf-tok' } : null,
        addEventListener(type, fn) { docListeners[type] = fn; },
        dispatchEvent() {},
    };

    let fetchImpl = async () => { throw new Error('Jaringan putus'); };
    vm.runInNewContext(saleDetailScript, {
        document,
        window: {
            enhanceSelects() {},
            uiLive: {
                saved: async form => {
                    lifecycle.push('saved:' + String(form.isConnected));
                },
            },
        },
        location: { href: 'http://example.test/#riwayat-penjualan' },
        URL,
        AbortController,
        CustomEvent: class { constructor(type, opts) { this.type = type; Object.assign(this, opts); } },
        fetch: (...args) => fetchImpl(...args),
    });

    const dispatchSubmit = () => docListeners.submit({
        preventDefault() {},
        target: { closest: sel => sel === '#sale-return-form' ? saleForm : null },
    });

    // Empty qty -> explicit Indonesian error
    await dispatchSubmit();
    assert.equal(errEl.hidden, false);
    assert.match(errEl.textContent, /jumlah retur/i);

    // Invalid reason -> explicit Indonesian error
    qtyInput.value = '1';
    reasonInput.value = 'kur';
    await dispatchSubmit();
    assert.equal(errEl.hidden, false);
    assert.match(errEl.textContent, /5 karakter/i);

    // Fetch throws -> surfaces message, keeps form interactive
    reasonInput.value = 'Salah potong ukuran';
    await dispatchSubmit();
    assert.equal(errEl.hidden, false);
    assert.match(errEl.textContent, /Jaringan putus/);
    assert.equal(saleForm.dataset.busy, undefined);
    assert.equal(submitBtn.disabled, false);

    // Success -> saved runs while form is still connected, then dialog closes
    fetchImpl = async () => ({ ok: true, redirected: false, json: async () => ({ number: 'RET-001' }) });
    await dispatchSubmit();
    assert.deepEqual(lifecycle, ['saved:true', 'close']);
    assert.equal(dialog.open, false);
});
