const opening = document.getElementById('opening-form');
if (opening) {
    const edit = opening.querySelector('[data-opening-edit]');
    const review = opening.querySelector('[data-opening-review]');
    const editButton = edit.querySelector('button[type=submit]');
    const postButton = review.querySelector('button[type=submit]');
    const groups = [...opening.querySelectorAll('[data-opening-group]')];
    let reviewed = null, posted = false;
    const idempotencyKey = key();
    editButton.disabled = false;

    function nameOpeningRows(group) {
        [...group.querySelector('[data-opening-rows]').children].forEach((row, index) => {
            row.querySelectorAll('[data-opening-field]').forEach(field => {
                field.name = `${group.dataset.openingGroup}[${index}][${field.dataset.openingField}]`;
                field.id = `ob-${group.dataset.openingGroup}-${index}-${field.dataset.openingField}`;
                field.parentElement.querySelector('label').htmlFor = field.id;
            });
        });
    }
    function showOpeningEdit() {
        reviewed = null;
        review.hidden = true;
        edit.hidden = false;
        editButton.focus();
    }
    opening.addEventListener('click', event => {
        if (posted || opening.dataset.busy) { return; }
        const button = event.target.closest('button');
        if (!button) { return; }
        if (button.matches('[data-opening-back]')) {
            showOpeningEdit();
            return;
        }
        const group = button.closest('[data-opening-group]');
        if (!group) { return; }
        if (button.matches('[data-opening-add]')) {
            const row = group.querySelector('template').content.firstElementChild.cloneNode(true);
            group.querySelector('[data-opening-rows]').append(row);
            nameOpeningRows(group);
            window.enhanceSelects?.(row);
            (row.querySelector('.select-trigger') || row.querySelector('select')).focus();
        } else if (button.matches('[data-opening-remove]')) {
            button.closest('.ob-row').remove();
            nameOpeningRows(group);
            window.v2FormFeedback?.clear(opening);
            group.querySelector('[data-opening-add]').focus();
        }
    });
    opening.addEventListener('invalid', event => {
        const group = event.target.closest('[data-opening-group]');
        if (group) { group.open = true; }
    }, true);
    function openingPayload() {
        const fields = opening.elements;
        const payload = {
            idempotency_key: idempotencyKey,
            date: fields.date.value,
            cash: fields.cash.value.trim() || '0',
            bank: fields.bank.value.trim() || '0',
            reference: fields.reference.value.trim() || null,
        };
        for (const group of groups) {
            payload[group.dataset.openingGroup] = [...group.querySelector('[data-opening-rows]').children].map(row =>
                Object.fromEntries([...row.querySelectorAll('[data-opening-field]')].map(field => [
                    field.dataset.openingField, field.value.trim() || null,
                ])));
        }
        return payload;
    }
    opening.addEventListener('submit', async event => {
        event.preventDefault();
        if (opening.dataset.busy || posted) { return; }
        const payload = openingPayload(), fingerprint = JSON.stringify(payload);
        if (!review.hidden && (event.submitter !== postButton || reviewed !== fingerprint)) {
            showOpeningEdit();
            return;
        }
        if (!opening.reportValidity()) { return; }
        if (review.hidden) {
            const preview = await send(opening, opening.dataset.previewUrl, payload, false, editButton);
            if (!preview?.summary || JSON.stringify(openingPayload()) !== fingerprint) { return; }
            reviewed = fingerprint;
            for (const amount of review.querySelectorAll('[data-opening-amount]')) {
                const field = amount.dataset.openingAmount;
                const direction = field === 'equity' && preview.summary.equity_direction === 'debit' ? ' (debit)' : '';
                amount.textContent = 'Rp ' + (window.uiMoney.fromRaw(preview.summary[field], 2) || '0') + direction;
            }
            const date = payload.date.split('-').reverse().join('/');
            review.querySelector('[data-opening-reference]').textContent = date + (payload.reference ? ' · ' + payload.reference : '');
            edit.hidden = true;
            review.hidden = false;
            review.querySelector('h3').focus();
            return;
        }
        opening._lastError = null;
        const saved = await send(opening, opening.action, payload, false, postButton);
        const failure = opening._lastError;
        if (failure?.errors && Object.keys(failure.errors).some(field =>
            [...opening.elements].some(el => el.name === field || el.name === field.replace(/\.([^.]*)/g, '[$1]')))) {
            showOpeningEdit();
            for (const group of groups) {
                if (Object.keys(failure.errors).some(field => field.startsWith(group.dataset.openingGroup + '.'))) { group.open = true; }
            }
            window.v2FormFeedback.summary(opening, failure.message, failure.errors);
        }
        if (!saved?.batch_id) { return; }
        posted = true;
        review.hidden = true;
        const status = opening.querySelector('[data-ok]');
        status.textContent = 'Saldo awal sudah diposting. Referensi: ' + saved.reference + ' · Batch #' + saved.batch_id + '.';
        status.hidden = false;
        status.focus();
        const cardStatus = [...document.querySelectorAll('[data-opening-card-status]')].find(node => !node.closest('dialog'));
        if (cardStatus) { cardStatus.textContent = status.textContent; }
        const dialog = opening.closest('dialog');
        const opener = dialog && document.querySelector(`[aria-controls="${dialog.id}"]`);
        if (opener && cardStatus) {
            dialog.addEventListener('close', () => { opener.hidden = true; cardStatus.focus(); }, {once: true});
        }
    });
}
