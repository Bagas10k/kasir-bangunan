import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/views/v2/ui-money.blade.php', import.meta.url), 'utf8');
const script = source.slice(source.indexOf('<script id="ui-money">') + 22, source.lastIndexOf('</script>'));

/** Lingkungan DOM minimum: satu input dengan nilai internal, cukup untuk enhance(), getter/setter, dan formdata. */
function load() {
    const listeners = {};
    class Input {
        constructor(attrs) { this._v = attrs.value ?? ''; this.attrs = { ...attrs }; this.dataset = {}; this.type = 'text'; this.placeholder = attrs.placeholder ?? ''; this.required = 'required' in attrs; this.name = attrs.name; }
        getAttribute(n) { return n in this.attrs ? this.attrs[n] : null; }
        setAttribute(n, v) { this.attrs[n] = v; }
        removeAttribute(n) { delete this.attrs[n]; }
        hasAttribute(n) { return n in this.attrs; }
        matches() { return true; }
    }
    Object.defineProperty(Input.prototype, 'value', { configurable: true, get() { return this._v; }, set(v) { this._v = String(v); } });
    const window = {};
    const context = {
        window, HTMLInputElement: Input,
        document: { readyState: 'complete', documentElement: {}, addEventListener: (t, f) => { listeners[t] = f; }, querySelectorAll: () => [] },
        MutationObserver: class { observe() {} },
    };
    vm.runInNewContext(script, context);
    return { money: window.uiMoney, Input, listeners };
}

test('format ketikan memakai titik ribuan dan koma desimal', () => {
    const { money } = load();
    assert.equal(money.fromTyped('1000000', 2), '1.000.000');
    assert.equal(money.fromTyped('1.0005', 2), '10.005');
    assert.equal(money.fromTyped('0050', 2), '50');
    assert.equal(money.fromTyped('1500,758', 2), '1.500,75');
    assert.equal(money.fromTyped('1500,', 2), '1.500,');
    assert.equal(money.fromTyped('1500,5', 0), '1.500');
    assert.equal(money.toRaw('1.500,75'), '1500.75');
    assert.equal(money.toRaw(''), '');
    assert.equal(money.fromRaw('52000.0000', 4), '52.000');
    assert.equal(money.fromRaw('1250000.50', 2), '1.250.000,5');
});

test('isian 0 bawaan dikosongkan, kosong tetap bernilai 0, dan nilai yang dibaca skrip selalu mentah', () => {
    const { money, Input, listeners } = load();
    const fee = new Input({ value: '0.00', name: 'cash_amount', required: '' });
    money.enhance(fee);
    assert.equal(fee._v, '');
    assert.equal(fee.placeholder, '0');
    assert.equal(fee.required, false);
    assert.equal(fee.value, '0');

    fee._v = '2.500.000';
    assert.equal(fee.value, '2500000');
    fee.value = '0';
    assert.equal(fee._v, '');

    const amount = new Input({ name: 'amount', required: '', pattern: '[0-9]+' });
    money.enhance(amount);
    assert.equal(amount.required, true, 'kolom tanpa 0 bawaan tetap wajib');
    assert.equal(amount.hasAttribute('pattern'), false);
    assert.equal(amount.value, '');
    amount.value = '35000';
    assert.equal(amount._v, '35.000');

    const entries = new Map([['amount', '35.000']]);
    listeners.formdata({ target: { elements: [amount] }, formData: { set: (k, v) => entries.set(k, v) } });
    assert.equal(entries.get('amount'), '35000');
});
