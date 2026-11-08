import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const script = readFileSync(new URL('../resources/views/v2/form-feedback.blade.php', import.meta.url), 'utf8').replace(/<\/?script>/g, '');

function fixture(names) {
    const messages = [];
    let focused;
    const element = (name) => ({
        name, dataset: {}, attributes: {}, type: 'text',
        setAttribute(key, value) { this.attributes[key] = value; },
        getAttribute(key) { return this.attributes[key] ?? null; },
        removeAttribute(key) { delete this.attributes[key]; if (key === 'data-server-invalid') { delete this.dataset.serverInvalid; } },
        insertAdjacentElement(position, message) { messages.push(message); },
        getClientRects() { return [1]; },
        focus() { focused = this; },
        remove() { messages.splice(messages.indexOf(this), 1); },
    });
    const fields = names.map(element);
    const box = element('summary');
    const form = {
        elements: fields,
        querySelector(selector) { return selector === '[data-error]' ? box : null; },
        querySelectorAll(selector) {
            if (selector === '[data-field-error]') { return [...messages]; }
            if (selector === '[aria-describedby]') { return fields.filter(field => field.attributes['aria-describedby']); }
            return fields.filter(field => 'serverInvalid' in field.dataset);
        },
    };
    const window = {};
    runInNewContext(script, { window, document: { createElement: () => element('message') } });
    return { form, fields, box, messages, feedback: window.v2FormFeedback, focused: () => focused };
}

test('server errors associate text with fields and preserve help and input values', () => {
    const f = fixture(['cash']);
    f.fields[0].value = 'invalid';
    f.fields[0].setAttribute('aria-describedby', 'cash-help');
    f.feedback.summary(f.form, 'Periksa isian', { cash: ['Nominal tidak valid.'] });
    assert.equal(f.fields[0].attributes['aria-invalid'], 'true');
    assert.match(f.fields[0].attributes['aria-describedby'], /^cash-help v2-field-error-/);
    assert.equal(f.messages[0].textContent, 'Nominal tidak valid.');
    assert.equal(f.focused(), f.fields[0]);
    assert.equal(f.fields[0].value, 'invalid');
    f.feedback.clear(f.form);
    assert.equal(f.messages.length, 0);
    assert.equal(f.fields[0].attributes['aria-describedby'], 'cash-help');
    assert.equal(f.fields[0].attributes['aria-invalid'], undefined);
});

test('nested and multi-select errors map to HTML field names', () => {
    const f = fixture(['lines[0][amount]', 'supplier_ids[]']);
    f.feedback.summary(f.form, 'Periksa isian', { 'lines.0.amount': ['Wajib diisi.'], supplier_ids: ['Pilih pemasok.'] });
    assert.equal(f.messages.length, 2);
    assert.equal(f.fields[1].attributes['aria-invalid'], 'true');
});

test('unmapped business and network errors remain accessible in the summary', () => {
    const f = fixture(['cash']);
    f.feedback.summary(f.form, 'Shift sudah dibuka.', { operation: ['Shift sudah dibuka.'] });
    assert.equal(f.focused(), f.box);
    assert.equal(f.box.attributes.role, 'alert');
    assert.equal(f.box.hidden, false);
    f.feedback.summary(f.form, 'Koneksi terputus.', null);
    assert.equal(f.box.textContent, 'Koneksi terputus.');
});
