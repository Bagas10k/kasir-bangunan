<script>
(() => {
    let sequence = 0;
    function clear(form) {
        form.querySelectorAll('[data-field-error]').forEach(message => {
            form.querySelectorAll('[aria-describedby]').forEach(field => {
                const ids = field.getAttribute('aria-describedby').split(/\s+/).filter(id => id !== message.id);
                if (ids.length) { field.setAttribute('aria-describedby', ids.join(' ')); }
                else { field.removeAttribute('aria-describedby'); }
            });
            message.remove();
        });
        form.querySelectorAll('[data-server-invalid]').forEach(field => {
            field.removeAttribute('aria-invalid');
            field.removeAttribute('data-server-invalid');
        });
    }
    function errors(form, messages = {}) {
        clear(form);
        let first;
        Object.entries(messages || {}).forEach(([name, text]) => {
            const bracketName = name.replace(/\.([^.]*)/g, '[$1]');
            const fields = Array.from(form.elements).filter(field => field.name === name || field.name === bracketName || field.name === name + '[]');
            if (!fields.length && name.startsWith('items.')) {
                const [, index, key] = name.split('.');
                const fieldName = key === 'discount' ? 'discount_value' : key;
                const row = form.querySelector('#items')?.children[Number(index)];
                const field = row?.querySelector('[data-name="' + fieldName + '"]');
                if (field) { fields.push(field); }
            }
            if (!fields.length) { return; }
            const message = document.createElement('p');
            message.id = 'v2-field-error-' + (++sequence);
            message.className = 'err';
            message.dataset.fieldError = '';
            message.textContent = Array.isArray(text) ? text.join(' ') : String(text);
            const anchor = fields[fields.length - 1];
            anchor.insertAdjacentElement('afterend', message);
            fields.forEach(field => {
                field.setAttribute('aria-invalid', 'true');
                field.dataset.serverInvalid = '';
                field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), message.id].filter(Boolean).join(' '));
                if (!first && !field.disabled && field.type !== 'hidden' && field.getClientRects().length) { first = field; }
            });
        });
        return first;
    }
    function summary(form, text, messages = {}) {
        const first = errors(form, messages);
        const box = form.querySelector('[data-error]');
        if (box) {
            box.textContent = text;
            box.hidden = false;
            box.setAttribute('role', 'alert');
            box.setAttribute('tabindex', '-1');
        }
        (first || box)?.focus();
    }
    window.v2FormFeedback = { clear, errors, summary };
})();
</script>
