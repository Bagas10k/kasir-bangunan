<style>
    .date-field { position:relative; min-width:0; width:100%; }
    .date-field > input[type="date"] { position:absolute !important; width:1px !important; height:1px !important; padding:0 !important; min-height:0 !important; opacity:0; pointer-events:none; }
    body .date-field > .date-trigger { display:flex; align-items:center; justify-content:space-between; gap:10px; width:100%; height:36px; padding:6px 10px; border:1px solid var(--border-control,#d9e2dc); border-radius:8px; background:var(--surface,#fff); color:var(--text-main); font:inherit; font-size:12.5px; cursor:pointer; text-align:left; }
    .date-trigger svg { color:var(--primary,#2d6a4f); flex-shrink:0; }
    .date-trigger:focus-visible, .app-calendar button:focus-visible { outline:2px solid var(--primary,#2d6a4f); outline-offset:2px; }
    .date-trigger:disabled { opacity:.55; cursor:not-allowed; }
    .date-field.is-invalid .date-trigger { border-color:var(--danger,#b91c1c); }
    .app-calendar { position:fixed; z-index:10020; width:min(620px,calc(100vw - 24px)); box-sizing:border-box; padding:16px; background:var(--surface,#fff); color:var(--text-main,#1c2826); border:1px solid var(--border-control,#d9e2dc); border-radius:14px; box-shadow:0 12px 36px #1b433226; }
    .app-calendar[hidden] { display:none; }
    .calendar-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px; }
    .calendar-title { display:flex; flex-direction:column; gap:3px; min-width:0; }
    .calendar-heading { font-size:14px; font-weight:700; }
    .calendar-range-summary { color:var(--text-muted); font-size:11.5px; }
    .app-calendar button { border:0; background:transparent; color:inherit; cursor:pointer; font:inherit; font-size:12px; border-radius:8px; min-height:36px; }
    .calendar-nav { width:36px; font-size:20px !important; }
    .calendar-months { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:20px; }
    .calendar-month-title { padding-bottom:8px; border-bottom:1px solid var(--border-card,#e5e7eb); text-align:center; font-size:13px; font-weight:700; text-transform:capitalize; }
    .calendar-week, .calendar-days { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:2px; }
    .calendar-week { margin-top:7px; }
    .calendar-week span { text-align:center; padding:4px 0; font-size:10px; font-weight:600; color:var(--text-muted); }
    .calendar-days button { min-width:0; padding:0; }
    .calendar-days button:hover { background:var(--mint-surface,#e8f5e9); }
    .calendar-days button[aria-pressed="true"] { background:var(--primary,#2d6a4f); color:#fff; font-weight:700; }
    .calendar-days button[aria-current="date"] { box-shadow:inset 0 0 0 1px var(--primary,#2d6a4f); }
    .calendar-days button.is-in-range { background:var(--mint-surface,#e8f5e9); border-radius:0; color:var(--primary-hover,#1b4332); }
    .calendar-days button:disabled { opacity:.3; cursor:not-allowed; }
    .calendar-footer { display:flex; justify-content:space-between; margin-top:14px; padding-top:10px; border-top:1px solid var(--border-card,#e5e7eb); }
    .calendar-footer button { padding:0 10px; color:var(--primary,#2d6a4f); font-weight:600; }
    @media(max-width:720px), (pointer:coarse) {
        body .date-field > .date-trigger { height:44px; font-size:14px; }
        .app-calendar { width:calc(100vw - 24px); padding:12px; }
        .app-calendar button { min-height:44px; }
        .calendar-months { grid-template-columns:minmax(0,1fr); gap:14px; }
        .calendar-months .calendar-month:nth-child(2) { display:none; }
    }
</style>
<script>
(function () {
    let active = null;
    let month;
    const calendar = document.createElement('div');
    calendar.className = 'app-calendar';
    calendar.hidden = true;
    calendar.id = 'app-date-picker';
    calendar.setAttribute('role', 'dialog');
    calendar.setAttribute('aria-label', 'Pilih tanggal');
    const iso = date => date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    const parse = value => /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(value + 'T12:00:00') : new Date();
    const format = value => value ? parse(value).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'}) : 'Pilih tanggal';
    function button(text, label, action) {
        const node = document.createElement('button');
        node.type = 'button'; node.textContent = text;
        if (label) node.setAttribute('aria-label', label);
        node.addEventListener('click', action);
        return node;
    }
    function close(restore = false) {
        if (!active) return;
        const trigger = active.trigger;
        trigger.setAttribute('aria-expanded', 'false');
        calendar.hidden = true;
        active = null;
        if (restore) trigger.focus();
    }
    function position() {
        if (!active) return;
        const rect = active.trigger.getBoundingClientRect();
        const width = Math.min(620, innerWidth - 24);
        calendar.style.width = width + 'px';
        calendar.style.left = Math.max(12, Math.min(rect.left, innerWidth - width - 12)) + 'px';
        calendar.style.top = Math.max(12, rect.bottom + calendar.offsetHeight + 8 > innerHeight ? rect.top - calendar.offsetHeight - 6 : rect.bottom + 6) + 'px';
    }
    function setValue(input, value) {
        input.value = value;
        input.dispatchEvent(new Event('input', {bubbles:true}));
        input.dispatchEvent(new Event('change', {bubbles:true}));
    }
    function chooseSingle(value) {
        if (!active) return;
        setValue(active.input, value);
        const trigger = active.trigger;
        close();
        trigger.focus();
    }
    function chooseRange(value) {
        const range = active?.range;
        if (!range) return;
        if (!range.start || range.end) {
            range.start = value; range.end = '';
            setValue(range.startInput, value); setValue(range.endInput, '');
            renderRange();
            return;
        }
        if (value < range.start) {
            range.start = value;
            setValue(range.startInput, value);
            renderRange();
            return;
        }
        range.end = value;
        setValue(range.endInput, value);
        const trigger = active.trigger;
        close();
        trigger.focus();
    }
    function validFor(input, value) {
        return !(input.min && value < input.min) && !(input.max && value > input.max);
    }
    function dayGrid(targetMonth, range) {
        const wrap = document.createElement('div'); wrap.className = 'calendar-month';
        const title = document.createElement('div'); title.className = 'calendar-month-title';
        title.textContent = targetMonth.toLocaleDateString('id-ID', {month:'long', year:'numeric'});
        const week = document.createElement('div'); week.className = 'calendar-week';
        ['Min','Sen','Sel','Rab','Kam','Jum','Sab'].forEach(day => { const span = document.createElement('span'); span.textContent = day; week.append(span); });
        const days = document.createElement('div'); days.className = 'calendar-days';
        for (let i = 0; i < targetMonth.getDay(); i++) days.append(document.createElement('span'));
        const total = new Date(targetMonth.getFullYear(), targetMonth.getMonth() + 1, 0).getDate();
        const referenceInput = range ? range.startInput : active.input;
        for (let day = 1; day <= total; day++) {
            const date = new Date(targetMonth.getFullYear(), targetMonth.getMonth(), day, 12);
            const value = iso(date);
            const node = button(day, date.toLocaleDateString('id-ID', {dateStyle:'full'}), () => range ? chooseRange(value) : chooseSingle(value));
            node.dataset.date = value;
            node.disabled = !validFor(referenceInput, value) || (range?.endInput && !validFor(range.endInput, value));
            const start = range?.start || active.input.value;
            const end = range?.end || (range ? '' : active.input.value);
            node.setAttribute('aria-pressed', String(value === start || value === end));
            if (start && end && value > start && value < end) node.classList.add('is-in-range');
            if (value === iso(new Date())) node.setAttribute('aria-current', 'date');
            node.addEventListener('keydown', event => {
                const delta = {ArrowLeft:-1, ArrowRight:1, ArrowUp:-7, ArrowDown:7}[event.key];
                if (delta === undefined) return;
                event.preventDefault(); date.setDate(date.getDate() + delta);
                month = new Date(date.getFullYear(), date.getMonth(), 1);
                range ? renderRange(iso(date)) : renderSingle(iso(date));
            });
            days.append(node);
        }
        wrap.append(title, week, days);
        return wrap;
    }
    function renderRange(focusDate) {
        if (!active?.range) return;
        calendar.replaceChildren();
        const head = document.createElement('div'); head.className = 'calendar-head';
        const title = document.createElement('div'); title.className = 'calendar-title';
        const heading = document.createElement('span'); heading.className = 'calendar-heading'; heading.textContent = 'Pilih rentang tanggal';
        const summary = document.createElement('span'); summary.className = 'calendar-range-summary'; summary.textContent = format(active.range.start) + ' — ' + format(active.range.end);
        title.append(heading, summary);
        const previous = button('‹', 'Bulan sebelumnya', () => { month.setMonth(month.getMonth() - 1); renderRange(); }); previous.className = 'calendar-nav';
        const next = button('›', 'Bulan berikutnya', () => { month.setMonth(month.getMonth() + 1); renderRange(); }); next.className = 'calendar-nav';
        head.append(previous, title, next);
        const months = document.createElement('div'); months.className = 'calendar-months';
        months.append(dayGrid(month, active.range), dayGrid(new Date(month.getFullYear(), month.getMonth() + 1, 1), active.range));
        const footer = document.createElement('div'); footer.className = 'calendar-footer';
        footer.append(button('Hapus', null, () => { setValue(active.range.startInput, ''); setValue(active.range.endInput, ''); close(true); }), button('Tutup', null, () => close(true)));
        calendar.append(head, months, footer); position();
        if (focusDate) calendar.querySelector('[data-date="' + focusDate + '"]:not(:disabled)')?.focus();
    }
    function renderSingle(focusDate) {
        if (!active || active.range) return;
        calendar.replaceChildren();
        const head = document.createElement('div'); head.className = 'calendar-head';
        const heading = document.createElement('span'); heading.className = 'calendar-heading'; heading.textContent = 'Pilih tanggal';
        const previous = button('‹', 'Bulan sebelumnya', () => { month.setMonth(month.getMonth() - 1); renderSingle(); }); previous.className = 'calendar-nav';
        const next = button('›', 'Bulan berikutnya', () => { month.setMonth(month.getMonth() + 1); renderSingle(); }); next.className = 'calendar-nav';
        head.append(previous, heading, next);
        const months = document.createElement('div'); months.className = 'calendar-months'; months.append(dayGrid(month, null));
        const footer = document.createElement('div'); footer.className = 'calendar-footer';
        const today = iso(new Date());
        footer.append(button('Hapus', null, () => chooseSingle('')), button('Hari ini', null, () => chooseSingle(today)), button('Tutup', null, () => close(true)));
        calendar.append(head, months, footer); position();
        if (focusDate) calendar.querySelector('[data-date="' + focusDate + '"]:not(:disabled)')?.focus();
    }
    function openDate(input, trigger) {
        if (active?.trigger === trigger) { close(true); return; }
        close();
        const startInput = input.form?.querySelector('input[type="date"][name="start_date"]');
        const endInput = input.form?.querySelector('input[type="date"][name="end_date"]');
        const isRange = startInput && endInput && startInput !== endInput;
        active = {input, trigger};
        if (isRange) {
            active.range = {startInput, endInput, start:startInput.value, end:endInput.value};
            month = new Date(parse(startInput.value || endInput.value || iso(new Date())).getFullYear(), parse(startInput.value || endInput.value || iso(new Date())).getMonth(), 1);
        } else {
            month = new Date(parse(input.value).getFullYear(), parse(input.value).getMonth(), 1);
        }
        document.body.append(calendar);
        calendar.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        isRange ? renderRange() : renderSingle(input.value || iso(new Date()));
    }
    function enhance() {
        const inputs = [...document.querySelectorAll('input[type="date"]:not([data-themed-date])')];
        inputs.forEach(input => {
            input.dataset.themedDate = 'true';
            const shell = document.createElement('div'); shell.className = 'date-field';
            input.parentNode.insertBefore(shell, input); shell.append(input);
            const trigger = button('', null, () => openDate(input, trigger));
            trigger.className = 'date-trigger'; trigger.setAttribute('aria-haspopup', 'dialog'); trigger.setAttribute('aria-expanded', 'false'); trigger.setAttribute('aria-controls', calendar.id);
            const text = document.createElement('span'); trigger.append(text);
            const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); icon.setAttribute('width','16'); icon.setAttribute('height','16'); icon.setAttribute('viewBox','0 0 24 24'); icon.setAttribute('fill','none'); icon.setAttribute('stroke','currentColor'); icon.setAttribute('stroke-width','1.8'); icon.setAttribute('aria-hidden','true');
            icon.innerHTML = '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M16 3v4M8 3v4M3 11h18"/>'; trigger.append(icon);
            shell.append(trigger); input.tabIndex = -1; input._dateTrigger = trigger;
            const label = input.id ? document.querySelector('label[for="' + CSS.escape(input.id) + '"]') : null;
            function sync() {
                text.textContent = format(input.value);
                trigger.setAttribute('aria-label', (label?.textContent.trim() || input.getAttribute('aria-label') || 'Tanggal') + ': ' + text.textContent);
                trigger.disabled = input.disabled || input.readOnly;
                shell.classList.toggle('is-invalid', !input.validity.valid && shell.dataset.validated === 'true');
            }
            input.addEventListener('input', sync); input.addEventListener('change', sync);
            input.addEventListener('invalid', event => { event.preventDefault(); shell.dataset.validated = 'true'; sync(); trigger.focus(); });
            input.form?.addEventListener('reset', () => { close(); requestAnimationFrame(sync); });
            new MutationObserver(sync).observe(input, {attributes:true, attributeFilter:['disabled','readonly','min','max','value']});
            label?.addEventListener('click', event => { event.preventDefault(); trigger.focus(); });
            trigger.addEventListener('focus', sync); sync();
        });
    }
    document.addEventListener('DOMContentLoaded', () => { document.body.append(calendar); enhance(); new MutationObserver(enhance).observe(document.body, {childList:true,subtree:true}); });
    document.addEventListener('pointerdown', event => { if (active && !calendar.contains(event.target) && !active.trigger.contains(event.target)) close(); });
    document.addEventListener('keydown', event => {
        if (!active) return;
        if (event.key === 'Escape') { event.preventDefault(); event.stopImmediatePropagation(); close(true); }
        if (event.key === 'Tab' && calendar.contains(event.target)) {
            const buttons = [...calendar.querySelectorAll('button:not(:disabled)')];
            if ((!event.shiftKey && event.target === buttons.at(-1)) || (event.shiftKey && event.target === buttons[0])) { event.preventDefault(); close(true); }
        }
    }, true);
    window.addEventListener('resize', position);
    window.addEventListener('scroll', () => { if (active) close(); }, true);
})();
</script>