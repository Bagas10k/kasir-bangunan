import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const path = new URL('../resources/views/partials/numeric-input-guard.blade.php', import.meta.url);
const script = existsSync(path) ? readFileSync(path, 'utf8').replace(/<script\b[^>]*>|<\/script>/g, '') : '';

function fixture() {
    const listeners = new Map();
    const document = { addEventListener(type, fn, capture) {
        const entries = listeners.get(type) || [];
        entries.push({ fn, capture: capture === true || capture?.capture === true });
        listeners.set(type, entries);
    } };
    runInNewContext(script, { document, window: {} });
    function field(attrs = {}, value = '12') {
        return {
            tagName: 'INPUT', type: attrs.type || 'text', value,
            disabled: false, readOnly: false, selectionStart: value.length, selectionEnd: value.length,
            getAttribute(name) { return attrs[name] ?? null; },
            hasAttribute(name) { return name in attrs; },
            setSelectionRange(start, end) { this.selectionStart = start; this.selectionEnd = end; },
        };
    }
    function fire(type, target, extra = {}) {
        const event = { target, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...extra };
        for (const entry of [...(listeners.get(type) || [])].sort((a, b) => Number(b.capture) - Number(a.capture))) entry.fn(event);
        return event;
    }
    return { field, fire, document, listeners };
}

test('beforeinput rejects entire invalid insertions on delegated decimal fields', () => {
    const f = fixture();
    for (const data of ['b', '1e3', '12abc', '🙂', '1 000', '+']) {
        const field = f.field({ inputmode: 'decimal' });
        assert.equal(f.fire('beforeinput', field, { data, inputType: 'insertText' }).defaultPrevented, true, data);
        assert.equal(field.value, '12');
    }
});

test('valid decimal typing, deletions and grouped money stay allowed', () => {
    const f = fixture();
    const field = f.field({ inputmode: 'decimal' }, '12.500');
    for (const data of ['5', ',', '.']) {
        assert.equal(f.fire('beforeinput', field, { data, inputType: 'insertText' }).defaultPrevented, false, data);
    }
    assert.equal(f.fire('beforeinput', field, { data: null, inputType: 'deleteContentBackward' }).defaultPrevented, false);
});

test('paste is rejected whole, never stripped into another number', () => {
    const f = fixture();
    for (const text of ['1e3', '0 hjbjhbk', 'Rp 5.000']) {
        const field = f.field({ inputmode: 'decimal' }, '');
        const event = f.fire('paste', field, { clipboardData: { getData: () => text } });
        assert.equal(event.defaultPrevented, true, text);
        assert.equal(field.value, '');
    }
    const ok = f.fire('paste', f.field({ inputmode: 'decimal' }, ''), { clipboardData: { getData: () => '12.500,50' } });
    assert.equal(ok.defaultPrevented, false);
});

test('input fallback (autofill, drop, IME) restores the last valid value and caret', () => {
    const f = fixture();
    const field = f.field({ 'data-currency-input': '' }, '15.000');
    f.fire('focusin', field);
    field.value = '15.000abc';
    f.fire('input', field);
    assert.equal(field.value, '15.000');
    assert.equal(field.selectionStart, 6);
});

test('integer, native signed and native unsigned rules', () => {
    const f = fixture();
    const qty = f.field({ inputmode: 'numeric' }, '3');
    assert.equal(f.fire('beforeinput', qty, { data: ',', inputType: 'insertText' }).defaultPrevented, true);
    const signed = f.field({ type: 'number', step: '0.01' }, '');
    assert.equal(f.fire('beforeinput', signed, { data: '-', inputType: 'insertText' }).defaultPrevented, false, 'saldo jurnal boleh negatif');
    const unsigned = f.field({ type: 'number', step: '0.0001', min: '0' }, '');
    assert.equal(f.fire('beforeinput', unsigned, { data: '-', inputType: 'insertText' }).defaultPrevented, true);
    assert.equal(f.fire('beforeinput', unsigned, { data: 'e', inputType: 'insertText' }).defaultPrevented, true);
});

test('text, hidden, readonly and disabled fields are untouched', () => {
    const f = fixture();
    const fields = [f.field({}, 'Jl. Mawar'), f.field({ type: 'hidden', inputmode: 'decimal' }, '1'), Object.assign(f.field({ inputmode: 'decimal' }), { readOnly: true }), Object.assign(f.field({ inputmode: 'decimal' }), { disabled: true })];
    for (const field of fields) {
        assert.equal(f.fire('beforeinput', field, { data: 'abc', inputType: 'insertText' }).defaultPrevented, false);
    }
});
