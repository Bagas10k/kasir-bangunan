<style>
    .product-option-picker { position:relative; }
    .product-option-picker > summary { display:flex; align-items:center; justify-content:space-between; gap:8px; min-height:36px; padding:6px 10px; box-sizing:border-box; border:1px solid var(--border-control); border-radius:8px; background:var(--surface); font-size:12.5px; cursor:pointer; list-style:none; }
    .product-option-chevron { display:flex; align-items:center; flex-shrink:0; color:var(--text-muted); transition:transform .18s ease; }
    .product-option-picker[open] > summary .product-option-chevron { transform:rotate(180deg); }
    .product-option-picker > summary::-webkit-details-marker { display:none; }
    .product-option-picker > summary:focus-visible { outline:2px solid var(--primary); outline-offset:2px; }
    .product-option-panel { position:absolute; top:calc(100% + 4px); left:0; right:0; z-index:20; padding:8px; background:var(--surface); border:1px solid var(--border-control); border-radius:10px; box-shadow:0 8px 24px #1b43321f; }
    .product-option-list { max-height:176px; overflow:auto; }
    .product-option-row { display:flex; align-items:center; gap:4px; }
    .product-option-row button { min-height:44px; border:0; border-radius:6px; background:transparent; color:var(--text-main); cursor:pointer; }
    .product-option-row .product-option-choice { flex:1; min-width:0; padding:8px; text-align:left; overflow-wrap:anywhere; }
    .product-option-row button:hover, .product-option-row button[aria-pressed="true"] { background:var(--mint-surface); }
    .product-option-row .product-option-remove { width:44px; flex:0 0 44px; font-size:20px; color:var(--danger,#b91c1c); }
    .product-option-add-toggle { display:grid; place-items:center; width:44px; height:44px; margin:8px 0 0 auto; padding:0; border:0; border-radius:10px; background:var(--primary); color:#fff; cursor:pointer; }
    .product-option-add-toggle:hover { background:var(--primary-hover); }
    .product-option-add-toggle:focus-visible, .product-option-add-button:focus-visible { outline:2px solid var(--primary); outline-offset:2px; }
    .product-option-add-toggle svg, .product-option-add-button svg { display:block; }
    .product-option-add[hidden] { display:none; }
    .product-option-add { display:flex; gap:6px; padding-top:8px; margin-top:4px; border-top:1px solid var(--border-card); }
    .product-option-add input { width:100%; min-width:0; }
    .product-option-add .btn { min-height:44px; padding:6px 10px; font-size:12px; }
    .product-option-add .product-option-add-button { display:grid; place-items:center; width:44px; flex:0 0 44px; padding:0; border-radius:10px; }
    .product-option-error { margin:6px 0 0; font-size:12px; color:var(--danger,#b91c1c); }
    .product-option-error:empty { display:none; }
    @media(max-width:720px), (pointer:coarse) { .product-option-picker > summary { min-height:44px; font-size:14px; } }
</style>
<script>
(function () {
    const options = @json($productOptions ?? ['unit' => [], 'category' => []]);
    const storeUrl = @json(route('products.options.store'));
    const deleteUrl = @json(route('products.options.destroy', ['id' => 0]));
    const fields = [...document.querySelectorAll('.product-option-field')];
    async function send(url, method, data) {
        const response = await fetch(url, {
            method,
            headers: {'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN':document.querySelector('#product-form input[name="_token"]').value},
            body: data ? JSON.stringify(data) : undefined,
        });
        const result = await response.json();
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Pilihan tidak dapat disimpan.');
        return result;
    }
    function render(field) {
        const type = field.dataset.optionType;
        const input = document.getElementById(type);
        if (!options[type].some(option => option.name === input.value)) input.value = '';
        document.getElementById(type + '-picker-value').textContent = input.value || 'Pilih ' + (type === 'unit' ? 'satuan' : 'kelompok produk');
        const list = field.querySelector('.product-option-list');
        list.replaceChildren();
        options[type].forEach(option => {
            const row = document.createElement('div'); row.className = 'product-option-row';
            const choice = document.createElement('button'); choice.type = 'button'; choice.className = 'product-option-choice'; choice.textContent = option.name;
            choice.setAttribute('aria-pressed', String(input.value === option.name));
            choice.addEventListener('click', () => { input.value = option.name; input.dispatchEvent(new Event('change', {bubbles:true})); render(field); field.querySelector('details').open = false; field.querySelector('summary').focus(); });
            row.append(choice);
            if (!option.used && option.id) {
                const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'product-option-remove'; remove.textContent = '×';
                remove.setAttribute('aria-label', 'Hapus ' + option.name); remove.title = 'Hapus ' + option.name;
                remove.addEventListener('click', async () => {
                    remove.disabled = true;
                    try {
                        await send(deleteUrl.replace(/0$/, option.id), 'DELETE');
                        options[type] = options[type].filter(item => item.id !== option.id);
                        if (input.value === option.name) input.value = '';
                        render(field); field.querySelector('summary').focus();
                    } catch (error) { field.querySelector('.product-option-error').textContent = error.message; remove.disabled = false; }
                });
                row.append(remove);
            }
            list.append(row);
        });
    }
    fields.forEach(field => {
        const details = field.querySelector('details');
        const addToggle = field.querySelector('.product-option-add-toggle');
        const addPanel = field.querySelector('.product-option-add');
        const entry = field.querySelector('.product-option-add input');
        const add = field.querySelector('.product-option-add button');
        const error = field.querySelector('.product-option-error');
        addToggle.addEventListener('click', () => {
            const expanded = addPanel.hidden;
            addPanel.hidden = !expanded;
            addToggle.setAttribute('aria-expanded', String(expanded));
            if (expanded) entry.focus();
        });
        async function createOption() {
            if (add.disabled) return;
            error.textContent = '';
            if (!entry.value.trim()) { error.textContent = 'Isi nama pilihan terlebih dahulu.'; entry.focus(); return; }
            add.disabled = true;
            try {
                const option = await send(storeUrl, 'POST', {type:field.dataset.optionType, name:entry.value.trim()});
                options[field.dataset.optionType].push(option);
                document.getElementById(field.dataset.optionType).value = option.name;
                entry.value = ''; addPanel.hidden = true; addToggle.setAttribute('aria-expanded', 'false'); render(field);
            } catch (failure) { error.textContent = failure.message; }
            finally { add.disabled = false; }
        }
        add.addEventListener('click', createOption);
        entry.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); createOption(); } });
        details.addEventListener('toggle', () => { if (details.open) { fields.forEach(other => { if (other !== field) other.querySelector('details').open = false; }); render(field); } });
        field.addEventListener('keydown', event => { if (event.key === 'Escape' && details.open) { event.preventDefault(); event.stopPropagation(); details.open = false; field.querySelector('summary').focus(); } });
        field.addEventListener('focusout', event => { if (!field.contains(event.relatedTarget)) details.open = false; });
        render(field);
    });
    document.addEventListener('pointerdown', event => fields.forEach(field => { if (!field.contains(event.target)) field.querySelector('details').open = false; }));
    window.refreshProductOptionPickers = () => fields.forEach(field => { field.querySelector('details').open = false; field.querySelector('.product-option-add').hidden = true; field.querySelector('.product-option-add-toggle').setAttribute('aria-expanded', 'false'); field.querySelector('.product-option-error').textContent = ''; render(field); });
    document.getElementById('product-form').addEventListener('submit', event => {
        for (const field of fields) {
            if (!document.getElementById(field.dataset.optionType).value) {
                event.preventDefault(); field.querySelector('.product-option-error').textContent = 'Pilih salah satu atau tambahkan pilihan baru.'; field.querySelector('details').open = true; field.querySelector('summary').focus(); break;
            }
        }
    });
})();
</script>
