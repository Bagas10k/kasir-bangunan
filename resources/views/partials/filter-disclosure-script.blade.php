<script>
document.addEventListener('DOMContentLoaded', function () {
    const pickers = [];
    const selector = '.master-toolbar, .ledger-filter, .receivables-filter, .ritase-history-toolbar, .table-filters, .sales-filter-grid, .report-toolbar';
    document.querySelectorAll(selector).forEach(function (panel, index) {
        if (panel.closest('.filter-disclosure')) return;
        const disclosure = document.createElement('details');
        disclosure.className = 'filter-disclosure';
        if (panel.classList.contains('no-print')) disclosure.classList.add('no-print');
        const summary = document.createElement('summary');
        summary.textContent = 'Filter';
        panel.id = panel.id || 'filter-panel-' + index;
        summary.setAttribute('aria-controls', panel.id);
        const parameters = new URLSearchParams(window.location.search);
        const active = [...panel.querySelectorAll('input[name], select[name]')].filter(function (control) {
            return !['page', 'per_page', '_token'].includes(control.name) && parameters.has(control.name) && parameters.get(control.name) !== '';
        }).length;
        if (active) {
            const count = document.createElement('span');
            count.className = 'filter-active-count';
            count.textContent = active + ' aktif';
            summary.appendChild(count);
        }
        panel.parentNode.insertBefore(disclosure, panel);
        disclosure.appendChild(summary);
        disclosure.appendChild(panel);
        panel.classList.add('filter-popup');
        panel.setAttribute('role', 'region');
        panel.setAttribute('aria-label', 'Pilihan filter');
        const position = function () {
            if (!disclosure.open) return;
            const rect = summary.getBoundingClientRect();
            const width = Math.min(440, window.innerWidth - 24);
            panel.style.setProperty('width', width + 'px', 'important');
            const gap = 4;
            const below = Math.max(0, window.innerHeight - rect.bottom - gap - 12);
            const above = Math.max(0, rect.top - gap - 12);
            panel.style.maxHeight = Math.max(above, below) + 'px';
            const upwards = panel.offsetHeight > below && above > below;
            panel.style.maxHeight = (upwards ? above : below) + 'px';
            const left = Math.max(12, Math.min(rect.left, window.innerWidth - width - 12));
            const top = upwards ? rect.top - panel.offsetHeight - gap : rect.bottom + gap;
            // A transformed ancestor can change the origin of a fixed-position panel.
            panel.style.left = '0px';
            panel.style.top = '0px';
            const origin = panel.getBoundingClientRect();
            panel.style.left = (left - origin.left) + 'px';
            panel.style.top = (top - origin.top) + 'px';
        };
        pickers.push({disclosure, summary, position});
        disclosure.addEventListener('toggle', function () {
            summary.setAttribute('aria-expanded', String(disclosure.open));
            if (disclosure.open) {
                pickers.forEach(function (picker) { if (picker.disclosure !== disclosure) picker.disclosure.open = false; });
                position();
            }
        });
        summary.setAttribute('aria-expanded', 'false');
        panel.addEventListener('invalid', function () { disclosure.open = true; }, true);
    });
    document.addEventListener('pointerdown', function (event) {
        if (event.target.closest('.select-options, .app-calendar')) return;
        pickers.forEach(function (picker) { if (!picker.disclosure.contains(event.target)) picker.disclosure.open = false; });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' || event.target.closest('.select-options, .app-calendar')) return;
        const picker = pickers.find(item => item.disclosure.open);
        if (picker) { picker.disclosure.open = false; picker.summary.focus(); }
    });
    document.addEventListener('focusin', function (event) {
        if (event.target.closest('.select-options, .app-calendar')) return;
        pickers.forEach(function (picker) { if (!picker.disclosure.contains(event.target)) picker.disclosure.open = false; });
    });
    window.addEventListener('resize', () => pickers.forEach(picker => picker.position()));
    window.addEventListener('scroll', () => pickers.forEach(picker => picker.position()), true);
});
</script>
