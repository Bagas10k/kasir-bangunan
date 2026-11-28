<script>
(() => {
    // Saran pencarian: daftar artikel yang boleh diakses sudah difilter server sesuai peran.
    const input = document.getElementById('hc-search');
    const list = document.getElementById('hc-suggest');
    if (input && list) {
        let pending = null;
        let timer = 0;
        let options = [];
        let activeIndex = -1;
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', list.id);
        input.setAttribute('aria-expanded', 'false');

        const close = () => {
            list.hidden = true;
            activeIndex = -1;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
        };
        const highlight = next => {
            options.forEach((option, i) => option.setAttribute('aria-selected', String(i === next)));
            activeIndex = next;
            if (next < 0) {
                input.removeAttribute('aria-activedescendant');
                return;
            }
            input.setAttribute('aria-activedescendant', options[next].id);
            options[next].scrollIntoView({ block: 'nearest' });
        };
        const option = (id, html, action, className = '') => {
            const item = document.createElement('li');
            item.id = id;
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', 'false');
            if (className) item.className = className;
            item.append(...html);
            item.addEventListener('mousedown', event => event.preventDefault());
            item.addEventListener('click', action);
            return item;
        };
        const textNode = (tag, text) => {
            const node = document.createElement(tag);
            node.textContent = text;
            return node;
        };
        const show = (results, term) => {
            const items = results.map((entry, i) => option('hc-opt-' + i, [textNode('strong', entry.title), textNode('small', entry.group)], () => location.assign(entry.url)));
            if (!items.length) {
                const empty = textNode('li', 'Belum ada panduan yang cocok. Coba kata lain, misalnya nota, kas, atau transfer.');
                empty.className = 'hc-suggest-empty';
                empty.setAttribute('role', 'presentation');
                list.replaceChildren(empty);
            } else {
                items.push(option('hc-opt-all', [document.createTextNode('Lihat semua hasil untuk “' + term + '”')], () => input.form.requestSubmit(), 'hc-suggest-all'));
                list.replaceChildren(...items);
            }
            options = items;
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            highlight(-1);
        };
        // Saran memakai pencarian server yang sama dengan halaman hasil, jadi urutan dan hak akses tetap sama.
        const render = () => {
            clearTimeout(timer);
            pending?.abort();
            const term = input.value.trim();
            if (term.length < 2) {
                close();
                return;
            }
            timer = setTimeout(async () => {
                pending = new AbortController();
                try {
                    const url = new URL(input.form.action);
                    url.searchParams.set('q', term);
                    const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: pending.signal });
                    if (!response.ok) throw new Error('search failed');
                    const data = await response.json();
                    if (input.value.trim() === term && document.activeElement === input) show(data.results, term);
                } catch (error) {
                    if (error.name !== 'AbortError') close();
                }
            }, 150);
        };
        input.addEventListener('input', render);
        input.addEventListener('focus', () => { if (input.value.trim()) render(); });
        input.addEventListener('blur', close);
        input.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !list.hidden) {
                event.preventDefault();
                close();
                return;
            }
            if (list.hidden || !options.length) return;
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                // Urutan putar: kolom cari (-1) → saran pertama … saran terakhir → kolom cari.
                let next = activeIndex + (event.key === 'ArrowDown' ? 1 : -1);
                if (next >= options.length) next = -1;
                if (next < -1) next = options.length - 1;
                highlight(next);
            } else if (event.key === 'Enter' && activeIndex >= 0) {
                event.preventDefault();
                options[activeIndex].click();
            }
        });

        // Pintasan "/" atau Ctrl/Cmd+K memfokuskan pencarian, kecuali saat sedang mengetik di kolom lain.
        document.addEventListener('keydown', event => {
            const typing = event.target.closest?.('input,textarea,select,[contenteditable="true"]');
            const shortcut = (event.key === '/' && !typing) || ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k');
            if (!shortcut) return;
            event.preventDefault();
            input.focus();
            input.select();
        });
    }

    // Cetak per artikel: hanya artikel terpilih, semua jawaban kendala dibuka.
    let openedForPrint = [];
    for (const button of document.querySelectorAll('[data-print-article]')) {
        button.hidden = false;
        button.addEventListener('click', () => {
            document.querySelectorAll('.is-print-target').forEach(node => node.classList.remove('is-print-target'));
            button.closest('.hc-article').classList.add('is-print-target');
            document.body.classList.add('hc-print-one');
            window.print();
        });
    }
    window.addEventListener('beforeprint', () => {
        openedForPrint = [...document.querySelectorAll('.hc-faq-item:not([open])')];
        openedForPrint.forEach(item => { item.open = true; });
    });
    window.addEventListener('afterprint', () => {
        openedForPrint.forEach(item => { item.open = false; });
        openedForPrint = [];
        document.body.classList.remove('hc-print-one');
        document.querySelectorAll('.is-print-target').forEach(node => node.classList.remove('is-print-target'));
    });
})();
</script>
