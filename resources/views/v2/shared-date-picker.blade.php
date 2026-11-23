<style>
    .date-field { position:relative; min-width:0; width:100%; }
    .date-field > input[type="date"] { position:absolute !important; width:1px !important; height:1px !important; padding:0 !important; min-height:0 !important; opacity:0; pointer-events:none; }
    body .date-field > .date-trigger { display:flex; align-items:center; justify-content:space-between; gap:10px; width:100%; height:36px; padding:6px 10px; border:1px solid var(--border-control,#d9e2dc); border-radius:8px; background:var(--surface,#fff); color:var(--text-main); font:inherit; font-size:12.5px; cursor:pointer; text-align:left; }
    .date-trigger svg { color:var(--primary,#2d6a4f); flex-shrink:0; }
    .date-trigger:focus-visible, .app-calendar button:focus-visible { outline:2px solid var(--primary,#2d6a4f); outline-offset:2px; }
    .date-trigger:disabled { opacity:.55; cursor:not-allowed; }
    .date-field.is-invalid .date-trigger { border-color:var(--danger,#b91c1c); }
    .date-field--typed { display:flex; align-items:center; }
    .date-field--typed > .date-text { width:100%; padding-right:38px; }
    .date-field--typed > .date-text-icon { position:absolute; right:12px; top:50%; transform:translateY(-50%); color:var(--primary,#2d6a4f); pointer-events:none; }
    .date-field--typed.is-invalid > .date-text { border-color:var(--danger,#b91c1c) !important; }
    .app-calendar { position:fixed; z-index:10020; width:min(620px,calc(100vw - 24px)); box-sizing:border-box; padding:16px; background:var(--surface,#fff); color:var(--text-main,#1c2826); border:1px solid var(--border-control,#d9e2dc); border-radius:14px; box-shadow:0 12px 36px #1b433226; }
    .app-calendar[hidden] { display:none; }
    .calendar-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px; }
    .calendar-title { display:flex; flex-direction:column; gap:3px; min-width:0; }
    .calendar-heading { font-size:14px; font-weight:700; }
    .calendar-range-summary { color:var(--text-muted); font-size:11.5px; }
    .app-calendar button { border:0; background:transparent; color:inherit; cursor:pointer; font:inherit; font-size:12px; border-radius:8px; min-height:36px; }
    .calendar-nav { width:36px; font-size:20px !important; }
    .calendar-months { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:20px; }
    .app-calendar.is-single .calendar-months { grid-template-columns:minmax(0,1fr); }
    .app-calendar.is-single .calendar-month-title { display:none; }
    .app-calendar.is-single .calendar-heading { text-transform:capitalize; }
    .calendar-footer button { white-space:nowrap; }
    body .app-calendar.is-single .calendar-footer button { padding:0 8px; }
    body .app-calendar.is-single .calendar-footer .calendar-ok { min-width:68px; padding:0 14px; }
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
    .calendar-footer { align-items:center; gap:8px; }
    .calendar-actions { display:flex; align-items:center; gap:6px; }
    .calendar-footer button { padding:0 10px; color:var(--primary,#2d6a4f); font-weight:600; }
    body .app-calendar .calendar-footer .calendar-ok { min-width:88px; padding:0 18px; background:#14532D; color:#fff; border-radius:8px; }
    body .app-calendar .calendar-footer .calendar-ok:hover { background:#0f3f22; }
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
    calendar.setAttribute('popover','manual');
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
    function close(restore = false, commit = false) {
        if (!active) return;
        const {trigger, snapshot, range, input} = active;
        trigger.setAttribute('aria-expanded', 'false');
        if(calendar.matches(':popover-open'))calendar.hidePopover();
        calendar.hidden = true;
        active = null;
        // Tanpa Oke (Tutup, klik di luar, Esc, gulir) pilihan di kalender dibatalkan ke nilai saat dibuka.
        if (!commit && snapshot) {
            if (range) {
                if (range.startInput.value !== snapshot.start) setValue(range.startInput, snapshot.start);
                if (range.endInput.value !== snapshot.end) setValue(range.endInput, snapshot.end);
            } else if (input.value !== snapshot.value) setValue(input, snapshot.value);
        }
        if (restore) trigger.focus();
    }
    function commit() {
        if (!active) return;
        if (active.requireRange && (!active.range?.start || !active.range?.end)) {
            const summary = calendar.querySelector('.calendar-range-summary');
            if (summary) summary.textContent = 'Pilih tanggal awal dan tanggal akhir dahulu.';
            return;
        }
        const trigger = active.trigger;
        const onCommit = active.onCommit;
        close(false, true);
        trigger.focus();
        onCommit?.();
    }
    function footerWith(left) {
        const footer = document.createElement('div'); footer.className = 'calendar-footer';
        const leftGroup = document.createElement('div'); leftGroup.className = 'calendar-actions'; leftGroup.append(...left);
        const ok = button('Oke', 'Simpan tanggal yang dipilih', commit); ok.className = 'calendar-ok';
        const rightGroup = document.createElement('div'); rightGroup.className = 'calendar-actions';
        rightGroup.append(button('Tutup', 'Tutup tanpa menyimpan', () => close(true)), ok);
        footer.append(leftGroup, rightGroup);
        return footer;
    }
    function position() {
        if (!active) return;
        calendar.style.margin='0';calendar.style.inset='auto';
        const rect = active.trigger.getBoundingClientRect();
        const width = Math.min(active.range ? 620 : 344, innerWidth - 24);
        calendar.style.width = width + 'px';
        calendar.style.left = Math.max(12, Math.min(rect.left, innerWidth - width - 12)) + 'px';
        const above = rect.bottom + calendar.offsetHeight + 8 > innerHeight;
        calendar.style.top = Math.max(12, above ? rect.top - calendar.offsetHeight - 6 : rect.bottom + 6) + 'px';
        calendar.style.transformOrigin = (above ? 'bottom ' : 'top ') + Math.max(0, rect.left + rect.width / 2 - parseFloat(calendar.style.left)) + 'px';
    }
    function setValue(input, value) {
        input.value = value;
        input.dispatchEvent(new Event('input', {bubbles:true}));
        input.dispatchEvent(new Event('change', {bubbles:true}));
    }
    function chooseSingle(value) {
        if (!active) return;
        setValue(active.input, value);
        if (value) month = new Date(parse(value).getFullYear(), parse(value).getMonth(), 1);
        renderSingle(value || undefined);
        if (!value) calendar.querySelector('.calendar-ok')?.focus();
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
        renderRange(value);
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
                let delta = {ArrowLeft:-1, ArrowRight:1, ArrowUp:-7, ArrowDown:7,Home:-date.getDay(),End:6-date.getDay()}[event.key];
                if (event.key === 'PageUp' || event.key === 'PageDown') { event.preventDefault();const direction=event.key==='PageUp'?-1:1;const day=date.getDate();date.setDate(1);date.setMonth(date.getMonth()+direction*(event.shiftKey?12:1));date.setDate(Math.min(day,new Date(date.getFullYear(),date.getMonth()+1,0).getDate())); }
                else { if (delta === undefined) return;event.preventDefault();date.setDate(date.getDate()+delta); }
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
        calendar.classList.remove('is-single');
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
        const footer = footerWith([button('Hapus', 'Kosongkan rentang', () => { setValue(active.range.startInput, ''); setValue(active.range.endInput, ''); active.range.start = ''; active.range.end = ''; renderRange(); calendar.querySelector('.calendar-ok')?.focus(); })]);
        calendar.append(head, months, footer); position();
        if (focusDate) calendar.querySelector('[data-date="' + focusDate + '"]:not(:disabled)')?.focus();
    }
    function renderSingle(focusDate) {
        if (!active || active.range) return;
        calendar.replaceChildren();
        const head = document.createElement('div'); head.className = 'calendar-head';
        calendar.classList.add('is-single');
        const heading = document.createElement('span'); heading.className = 'calendar-heading'; heading.setAttribute('aria-live', 'polite');
        heading.textContent = month.toLocaleDateString('id-ID', {month:'long', year:'numeric'});
        const previous = button('‹', 'Bulan sebelumnya', () => { month.setMonth(month.getMonth() - 1); renderSingle(); }); previous.className = 'calendar-nav';
        const next = button('›', 'Bulan berikutnya', () => { month.setMonth(month.getMonth() + 1); renderSingle(); }); next.className = 'calendar-nav';
        head.append(previous, heading, next);
        const months = document.createElement('div'); months.className = 'calendar-months'; months.append(dayGrid(month, null));
        const today = iso(new Date());
        const clear=button('Hapus','Kosongkan tanggal',()=>chooseSingle(''));clear.disabled=active.input.required;const chooseToday=button('Hari ini',null,()=>chooseSingle(today));chooseToday.disabled=!validFor(active.input,today);
        const footer = footerWith([clear, chooseToday]);
        calendar.append(head, months, footer); position();
        if (focusDate) calendar.querySelector('[data-date="' + focusDate + '"]:not(:disabled)')?.focus();
    }
    function openDate(input, trigger, opts = {}) {
        if (active?.trigger === trigger) { if (opts.keepFocus) return; close(true); return; }
        close();
        const startInput = opts.endInput ? input : input.form?.querySelector('input[type="date"][name="start_date"]');
        const endInput = opts.endInput || input.form?.querySelector('input[type="date"][name="end_date"]');
        const isRange = startInput && endInput && startInput !== endInput;
        active = {input, trigger, onCommit:opts.onCommit, requireRange:opts.requireRange, snapshot: isRange ? {start: startInput.value, end: endInput.value} : {value: input.value}};
        if (isRange) {
            active.range = {startInput, endInput, start:startInput.value, end:endInput.value};
            month = new Date(parse(startInput.value || endInput.value || iso(new Date())).getFullYear(), parse(startInput.value || endInput.value || iso(new Date())).getMonth(), 1);
        } else {
            month = new Date(parse(input.value).getFullYear(), parse(input.value).getMonth(), 1);
        }
        (input.closest('dialog[open]') || document.body).append(calendar);
        calendar.hidden = false;
        if(calendar.showPopover)calendar.showPopover();
        trigger.setAttribute('aria-expanded', 'true');
        if (opts.keepFocus) { isRange ? renderRange() : renderSingle(); return; }
        isRange ? renderRange(startInput.value || iso(new Date())) : renderSingle(input.value || iso(new Date()));
    }
    // Mode ketik (data-date-typed): kolom dd/mm/yyyy yang bisa diisi manual; klik membuka kalender yang sama.
    const toText = value => /^\d{4}-\d{2}-\d{2}$/.test(value) ? value.slice(8, 10) + '/' + value.slice(5, 7) + '/' + value.slice(0, 4) : '';
    function fromText(text) {
        const m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(text);
        if (!m) return null;
        const date = new Date(+m[3], +m[2] - 1, +m[1], 12);
        return date.getFullYear() === +m[3] && date.getMonth() === +m[2] - 1 && date.getDate() === +m[1] ? iso(date) : null;
    }
    function refreshOpen() {
        if (!active) return;
        const value = active.range ? (active.range.startInput.value || active.range.endInput.value) : active.input.value;
        if (value) month = new Date(parse(value).getFullYear(), parse(value).getMonth(), 1);
        if (active.range) { active.range.start = active.range.startInput.value; active.range.end = active.range.endInput.value; renderRange(); }
        else renderSingle();
    }
    // Rentang satu kolom (data-date-range-start="<id input akhir>"): ketik dd/mm/yyyy – dd/mm/yyyy atau pilih di kalender rentang.
    function enhanceRangeTyped(startInput, shell) {
        const endInput = document.getElementById(startInput.dataset.dateRangeStart);
        if (!endInput) { enhanceTyped(startInput, shell); return; }
        endInput.dataset.themedDate = 'true';
        shell.classList.add('date-field--typed', 'date-field--range'); shell.append(endInput);
        const text = document.createElement('input');
        text.type = 'text'; text.className = 'date-text form-control'; text.placeholder = 'dd/mm/yyyy – dd/mm/yyyy';
        text.autocomplete = 'off'; text.inputMode = 'tel'; text.maxLength = 23;
        text.setAttribute('aria-haspopup', 'dialog'); text.setAttribute('aria-expanded', 'false'); text.setAttribute('aria-controls', calendar.id);
        const label = document.querySelector('label[for="' + CSS.escape(startInput.id) + '"]');
        text.id = startInput.id + '-range'; if (label) label.htmlFor = text.id; else text.setAttribute('aria-label', 'Rentang tanggal');
        text.setAttribute('aria-describedby', text.id + '-hint');
        const hint = document.createElement('span'); hint.id = text.id + '-hint'; hint.className = 'sr'; hint.textContent = 'Format tanggal awal sampai tanggal akhir, contoh 01/11/2026 – 05/11/2026.';
        const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); icon.setAttribute('width','16'); icon.setAttribute('height','16'); icon.setAttribute('viewBox','0 0 24 24'); icon.setAttribute('fill','none'); icon.setAttribute('stroke','currentColor'); icon.setAttribute('stroke-width','1.8'); icon.setAttribute('aria-hidden','true'); icon.classList.add('date-text-icon');
        icon.innerHTML = '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M16 3v4M8 3v4M3 11h18"/>';
        shell.append(text, icon, hint);
        startInput.tabIndex = -1; endInput.tabIndex = -1; startInput._dateTrigger = text; endInput._dateTrigger = text;
        const part = digits => { let out = digits.slice(0, 2); if (digits.length > 2) out += '/' + digits.slice(2, 4); if (digits.length > 4) out += '/' + digits.slice(4, 8); return out; };
        const mark = message => { text.setCustomValidity(message); shell.classList.toggle('is-invalid', !!message); };
        const open = () => { if (!text.disabled) openDate(startInput, text, {keepFocus:true, endInput}); };
        text.addEventListener('click', open);
        text.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown') { event.preventDefault(); open(); calendar.querySelector('.calendar-days button[aria-pressed="true"]:not(:disabled)')?.focus() || calendar.querySelector('.calendar-days button:not(:disabled)')?.focus(); }
            if (event.key === 'Tab' && active?.trigger === text) close();
        });
        text.addEventListener('input', () => {
            const digits = text.value.replace(/\D/g, '').slice(0, 16);
            text.value = digits.length > 8 ? part(digits.slice(0, 8)) + ' – ' + part(digits.slice(8)) : part(digits);
            mark('');
            const start = digits.length >= 8 ? fromText(part(digits.slice(0, 8))) : null;
            const end = digits.length === 16 ? fromText(part(digits.slice(8))) : null;
            if (digits.length >= 8 && !(start && validFor(startInput, start))) { mark('Tanggal awal tidak valid. Gunakan format dd/mm/yyyy.'); return; }
            if (digits.length === 16 && !(end && validFor(endInput, end))) { mark('Tanggal akhir tidak valid. Gunakan format dd/mm/yyyy.'); return; }
            if (start && end && end < start) { mark('Tanggal akhir harus sama atau setelah tanggal awal.'); return; }
            if ((start || '') !== startInput.value) setValue(startInput, start || '');
            if ((end || '') !== endInput.value) setValue(endInput, end || '');
            refreshOpen();
            if (active?.trigger === text && active.snapshot) { active.snapshot.start = startInput.value; active.snapshot.end = endInput.value; }
        });
        text.addEventListener('blur', () => {
            const count = text.value.replace(/\D/g, '').length;
            if (count && count !== 8 && count !== 16) mark('Lengkapi rentang dengan format dd/mm/yyyy – dd/mm/yyyy.');
        });
        function sync() {
            const count = text.value.replace(/\D/g, '').length;
            if (document.activeElement === text && count !== 8 && count !== 16) return;
            const start = toText(startInput.value), end = toText(endInput.value);
            text.value = start ? start + (end ? ' – ' + end : '') : '';
            text.disabled = startInput.disabled;
        }
        [startInput, endInput].forEach(input => { input.addEventListener('input', sync); input.addEventListener('change', sync); });
        startInput.form?.addEventListener('reset', () => { close(); requestAnimationFrame(() => { sync(); mark(''); }); });
        sync();
    }
    function enhanceTyped(input, shell) {
        shell.classList.add('date-field--typed');
        const text = document.createElement('input');
        text.type = 'text'; text.className = 'date-text form-control'; text.placeholder = 'dd/mm/yyyy';
        text.autocomplete = 'off'; text.inputMode = 'tel'; text.maxLength = 10;
        text.setAttribute('aria-haspopup', 'dialog'); text.setAttribute('aria-expanded', 'false'); text.setAttribute('aria-controls', calendar.id);
        const label = input.id ? document.querySelector('label[for="' + CSS.escape(input.id) + '"]') : null;
        if (label) { text.id = input.id + '-text'; label.htmlFor = text.id; }
        else text.setAttribute('aria-label', input.getAttribute('aria-label') || 'Tanggal');
        const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); icon.setAttribute('width','16'); icon.setAttribute('height','16'); icon.setAttribute('viewBox','0 0 24 24'); icon.setAttribute('fill','none'); icon.setAttribute('stroke','currentColor'); icon.setAttribute('stroke-width','1.8'); icon.setAttribute('aria-hidden','true'); icon.classList.add('date-text-icon');
        icon.innerHTML = '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M16 3v4M8 3v4M3 11h18"/>';
        shell.append(text, icon); input.tabIndex = -1; input._dateTrigger = text;
        const open = () => { if (!text.disabled && !text.readOnly) openDate(input, text, {keepFocus:true}); };
        const mark = message => { text.setCustomValidity(message); shell.classList.toggle('is-invalid', !!message); };
        text.addEventListener('click', open);
        text.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown') { event.preventDefault(); open(); calendar.querySelector('.calendar-days button[aria-pressed="true"]:not(:disabled)')?.focus() || calendar.querySelector('.calendar-days button:not(:disabled)')?.focus(); }
            if (event.key === 'Tab' && active?.trigger === text) close();
        });
        text.addEventListener('input', () => {
            const digits = text.value.replace(/\D/g, '').slice(0, 8);
            let out = digits.slice(0, 2);
            if (digits.length > 2) out += '/' + digits.slice(2, 4);
            if (digits.length > 4) out += '/' + digits.slice(4);
            text.value = out; mark('');
            if (out.length === 10) {
                const value = fromText(out);
                if (value && validFor(input, value)) { if (input.value !== value) setValue(input, value); refreshOpen(); }
                else mark('Tanggal tidak valid. Gunakan format dd/mm/yyyy.');
            } else if (!out && input.value) { setValue(input, ''); refreshOpen(); }
            if (active?.trigger === text && active.snapshot) active.snapshot.value = input.value;
        });
        text.addEventListener('blur', () => { if (text.value && text.value.length < 10) mark('Lengkapi tanggal dengan format dd/mm/yyyy.'); });
        function sync() {
            if (!(document.activeElement === text && text.value.length < 10)) text.value = toText(input.value);
            text.disabled = input.disabled; text.readOnly = input.readOnly;
        }
        input.addEventListener('input', sync); input.addEventListener('change', sync);
        input.form?.addEventListener('reset', () => { close(); requestAnimationFrame(() => { text.value = toText(input.value); mark(''); }); });
        new MutationObserver(sync).observe(input, {attributes:true, attributeFilter:['disabled','readonly','min','max','value']});
        sync();
    }
    function enhance() {
        const inputs = [...document.querySelectorAll('input[type="date"]:not([data-themed-date]):not([data-date-range-end])')];
        inputs.forEach(input => {
            input.dataset.themedDate = 'true';
            const shell = document.createElement('div'); shell.className = 'date-field';
            input.parentNode.insertBefore(shell, input); shell.append(input);
            if (input.hasAttribute('data-date-typed')) { enhanceTyped(input, shell); return; }
            if (input.hasAttribute('data-date-range-start')) { enhanceRangeTyped(input, shell); return; }
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
    document.addEventListener('close',event=>{if(active&&event.target.matches?.('dialog'))close();},true);
    window.addEventListener('scroll',event=>{if(active&&!calendar.contains(event.target))close();},true);
    window.uiDatePicker = {
        openRange(start, end, trigger, onCommit) { openDate(start, trigger, {endInput:end, onCommit, requireRange:true}); },
        closeFor(trigger) { if (active?.trigger === trigger) close(); }
    };
})();
</script>
