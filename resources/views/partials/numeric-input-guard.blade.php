<script id="numeric-input-guard">
(() => {
    const snapshots = new WeakMap();
    function mode(field) {
        if (!field || field.tagName !== 'INPUT' || field.disabled || field.readOnly || field.type === 'hidden') return null;
        const inputmode = field.getAttribute('inputmode');
        const native = field.type === 'number';
        if (!native && inputmode !== 'decimal' && inputmode !== 'numeric' && !field.hasAttribute('data-ui-number') && !field.hasAttribute('data-currency-input')) return null;
        const min = field.getAttribute('min');
        const step = field.getAttribute('step');
        return { native, signed: native && (min === null || Number(min) < 0), integer: inputmode === 'numeric' || (native && step !== 'any' && (step === null || (Number(step) > 0 && Number.isInteger(Number(step))))) };
    }
    function valid(value, settings) {
        if (settings.native) {
            const pattern = settings.integer ? /^-?\d*$/ : /^-?\d*(?:\.\d*)?$/;
            return pattern.test(value) && (settings.signed || !value.includes('-'));
        }
        return (settings.integer ? /^\d*$/ : /^[\d.,]*$/).test(value);
    }
    function remember(field, settings) {
        if (valid(field.value, settings) && !field.validity?.badInput) snapshots.set(field, { value: field.value, start: field.selectionStart, end: field.selectionEnd });
    }
    function insertionAllowed(field, text, settings) {
        if (!valid(text, settings)) return false;
        if (typeof field.selectionStart !== 'number') return !text.includes('-') || (settings.signed && (field.value === '' || text === field.value));
        const next = field.value.slice(0, field.selectionStart) + text + field.value.slice(field.selectionEnd);
        return valid(next, settings);
    }
    for (const type of ['focusin', 'keydown', 'compositionstart']) {
        document.addEventListener(type, event => {
            const settings = mode(event.target);
            if (settings) remember(event.target, settings);
        }, true);
    }
    document.addEventListener('beforeinput', event => {
        const field = event.target;
        const settings = mode(field);
        if (!settings) return;
        remember(field, settings);
        if (event.inputType?.startsWith('delete') || event.data === null || event.data === undefined) return;
        if (!insertionAllowed(field, event.data, settings)) event.preventDefault();
    }, true);
    document.addEventListener('paste', event => {
        const field = event.target;
        const settings = mode(field);
        if (!settings) return;
        remember(field, settings);
        const text = event.clipboardData?.getData('text/plain');
        if (text !== undefined && !insertionAllowed(field, text, settings)) event.preventDefault();
    }, true);
    document.addEventListener('input', event => {
        const field = event.target;
        const settings = mode(field);
        if (!settings) return;
        if (valid(field.value, settings) && !field.validity?.badInput) {
            remember(field, settings);
            return;
        }
        const previous = snapshots.get(field);
        field.value = previous?.value ?? '';
        if (previous && typeof previous.start === 'number') {
            try { field.setSelectionRange(previous.start, previous.end); } catch (_) { /* Native number inputs do not expose selection. */ }
        }
    }, true);
})();
</script>
