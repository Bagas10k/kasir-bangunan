import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/views/partials/filter-disclosure-script.blade.php', import.meta.url), 'utf8').replace(/^<script>\s*|\s*<\/script>\s*$/g, '');
const styles = readFileSync(new URL('../resources/views/partials/table-filter-styles.blade.php', import.meta.url), 'utf8');

/** DOM minimum untuk menguji pemindahan node dan event, bukan pembuktian layout browser. */
function setup({ width = 1440, height = 900, nativePopover = true } = {}) {
    const observers = [], hooks = [], listeners = {};
    let document;
    class Element {
        constructor(tag, attrs = {}, text = '') {
            this.tagName = tag.toUpperCase();
            this.nodeType = 1;
            this.attrs = {};
            this.dataset = {};
            this.children = [];
            this.events = {};
            this.style = {};
            this.textContent = text;
            this.tabIndex = /^(INPUT|SELECT|BUTTON|A)$/.test(this.tagName) ? 0 : -1;
            this.hidden = false;
            this.rect = { left: 500, right: 600, top: 100, bottom: 144 };
            this.offsetHeight = 360;
            this.options = [];
            for (const [key, value] of Object.entries(attrs)) this.setAttribute(key, value);
            if (!nativePopover) { this.showPopover = undefined; this.hidePopover = undefined; }
        }
        setAttribute(key, value) {
            this.attrs[key] = String(value);
            if (key.startsWith('data-')) this.dataset[key.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase())] = String(value);
            if (['id', 'name', 'type', 'value'].includes(key)) this[key] = String(value);
        }
        getAttribute(key) { return this.attrs[key] ?? null; }
        hasAttribute(key) { return key === 'hidden' ? this.hidden : Object.hasOwn(this.attrs, key); }
        get className() { return this.attrs.class ?? ''; }
        set className(value) { this.attrs.class = value; }
        get isConnected() { return this === document.body || Boolean(this.parentElement?.isConnected); }
        get form() { return this.closest('form'); }
        matches(selector) {
            return selector.split(',').some(part => {
                let s = part.trim();
                if (s === ':popover-open') return !!this.popoverOpen;
                if (s.startsWith(':scope>')) s = s.slice(7);
                for (const negative of [...s.matchAll(/:not\(([^)]+)\)/g)]) {
                    if (this.matches(negative[1])) return false;
                }
                s = s.replace(/:not\([^)]+\)/g, '');
                for (const [, key, , value] of s.matchAll(/\[([\w-]+)(=(?:"([^"]*)"|([^\]]+)))?\]/g)) {
                    const expected = value ?? s.match(new RegExp(`\\[${key}=([^\\]]+)\\]`))?.[1];
                    if (!this.hasAttribute(key) || expected !== undefined && this.getAttribute(key) !== expected) return false;
                }
                s = s.replace(/\[[^\]]+\]/g, '');
                for (const [, cls] of s.matchAll(/\.([\w-]+)/g)) if (!this.className.split(/\s+/).includes(cls)) return false;
                for (const [, id] of s.matchAll(/#([\w-]+)/g)) if (this.id !== id) return false;
                const tag = s.match(/^[a-z]+/i)?.[0];
                return !tag || this.tagName === tag.toUpperCase();
            });
        }
        closest(selector) { return this.matches(selector) ? this : this.parentElement?.closest(selector) ?? null; }
        contains(node) { return node === this || this.children.some(child => child.contains(node)); }
        querySelectorAll(selector) {
            const list = [];
            const direct = selector.startsWith(':scope>');
            for (const child of this.children) {
                if (child.matches(selector)) list.push(child);
                if (!direct) list.push(...child.querySelectorAll(selector));
            }
            return list;
        }
        querySelector(selector) { return this.querySelectorAll(selector)[0] ?? null; }
        append(...nodes) { for (const node of nodes) { node.remove(); this.children.push(node); node.parentElement = this; } }
        prepend(...nodes) { for (const node of [...nodes].reverse()) { node.remove(); this.children.unshift(node); node.parentElement = this; } }
        before(...nodes) { const parent = this.parentElement; for (const node of nodes) { node.remove(); parent.children.splice(parent.children.indexOf(this), 0, node); node.parentElement = parent; } }
        after(...nodes) { const parent = this.parentElement; let at = parent.children.indexOf(this) + 1; for (const node of nodes) { node.remove(); parent.children.splice(at++, 0, node); node.parentElement = parent; } }
        remove() { if (this.parentElement) this.parentElement.children.splice(this.parentElement.children.indexOf(this), 1); this.parentElement = null; }
        addEventListener(type, handler) { (this.events[type] ??= []).push(handler); }
        dispatchEvent(event) {
            event.target ??= this;
            for (const fn of this.events[event.type] ?? []) fn(event);
            if (event.bubbles) this.parentElement?.dispatchEvent(event);
        }
        click() { this.dispatchEvent({ type: 'click', bubbles: true, preventDefault() { this.defaultPrevented = true; } }); }
        focus() { document.activeElement = this; }
        getClientRects() { return this.closest('[data-test-hidden]') || this.hidden || this.parentElement?.hidden ? [] : [this.rect]; }
        getBoundingClientRect() { return this.rect; }
        showPopover() { this.popoverOpen = true; }
        hidePopover() { this.popoverOpen = false; }
    }
    const make = (tag, attrs = {}, text = '') => new Element(tag, attrs, text);
    const body = make('body');
    document = {
        body, readyState: 'complete', activeElement: null,
        createElement: tag => make(tag),
        querySelectorAll: selector => body.querySelectorAll(selector),
        querySelector: selector => body.querySelector(selector),
        getElementById: id => body.querySelector('#' + id),
        addEventListener(type, fn) { (listeners[type] ??= []).push(fn); },
    };
    const window = { innerWidth: width, innerHeight: height, uiLive: { onPatch: fn => hooks.push(fn) }, addEventListener(type, fn) { (listeners[type] ??= []).push(fn); } };
    const run = () => vm.runInNewContext(source, { window, document, queueMicrotask, KeyboardEvent: class { constructor(type, options) { this.type = type; Object.assign(this, options); } }, MutationObserver: class { constructor(fn) { this.fn = fn; observers.push(this); } observe(target, options) { this.target = target; this.options = options; } } });
    const emit = (type, target, extra = {}) => {
        const event = { type, target, preventDefault() { this.defaultPrevented = true; }, ...extra };
        for (const fn of listeners[type] ?? []) fn(event);
        return event;
    };
    const chipsToolbar = () => {
        const toolbar = make('div', { 'data-ui-toolbar': '' }), search = make('div', { class: 'ui-search' });
        search.append(make('input', { type: 'search', 'data-ui-search': '' }));
        const chips = make('div', { class: 'ui-chips', 'aria-label': 'Status' });
        const all = make('button', { type: 'button', 'data-ui-chip': 'all', 'aria-pressed': 'true' }, 'Semua');
        const paid = make('button', { type: 'button', 'data-ui-chip': 'status:paid', 'aria-pressed': 'false' }, 'Lunas');
        chips.append(all, paid);
        const actions = make('div', { class: 'ui-toolbar-actions' });
        actions.append(make('button', { type: 'button' }, 'Tambah'));
        toolbar.append(search, chips, actions); body.append(toolbar);
        for (const chip of [all, paid]) chip.addEventListener('click', () => { all.setAttribute('aria-pressed', String(chip === all)); paid.setAttribute('aria-pressed', String(chip === paid)); });
        return { toolbar, search, chips, actions, all, paid };
    };
    return { make, body, document, window, run, emit, observers, hooks, chipsToolbar };
}

test('status filters move real controls; search/actions stay outside and repeated scans do not duplicate', () => {
    const app = setup(), fixture = app.chipsToolbar();
    app.run();
    const panel = fixture.toolbar.querySelector('[data-table-filter-panel]');
    assert.ok(panel.contains(fixture.chips));
    assert.equal(fixture.search.parentElement, fixture.toolbar);
    assert.equal(fixture.actions.parentElement, fixture.toolbar);
    assert.equal(panel.hidden, true);
    fixture.paid.click();
    app.observers.find(observer => observer.options.attributeFilter?.includes('aria-pressed')).fn([]);
    assert.equal(fixture.paid.getAttribute('aria-pressed'), 'true');
    assert.equal(fixture.toolbar.querySelector('.ui-filter-count').textContent, '1');
    assert.equal(fixture.toolbar.querySelector('.ui-filter-current').textContent, 'Lunas');
    app.window.uiTableFilters.scan(app.body);
    assert.equal(fixture.toolbar.querySelectorAll('[data-table-filter-panel]').length, 1);
});

test('server form keeps form ownership, values, hidden inputs, and original change listeners', () => {
    const app = setup(), form = app.make('form', { class: 'master-toolbar', action: '/products' });
    const search = app.make('div', { class: 'master-control-search' }), input = app.make('input', { name: 'search', value: 'semen' });
    search.append(input);
    const field = app.make('div', { class: 'master-control' }), select = app.make('select', { name: 'status', value: 'active' });
    select.options = [{ value: '' }, { value: 'active' }];
    let changed = 0;
    select.addEventListener('change', () => changed++);
    field.append(select);
    const hidden = app.make('input', { type: 'hidden', name: 'date', value: '2026-11-21' });
    const actions = app.make('div', { class: 'master-toolbar-actions' }), submit = app.make('button', { type: 'submit' });
    actions.append(submit); form.append(search, field, hidden, actions); app.body.append(form);
    app.run();
    assert.equal(select.form, form);
    assert.equal(submit.form, form);
    assert.equal(input.value, 'semen');
    assert.equal(hidden.parentElement, form);
    assert.equal(form.getAttribute('action'), '/products');
    assert.equal(form.querySelector('.ui-filter-search-submit').type, 'submit');
    select.dispatchEvent({ type: 'change', bubbles: true });
    assert.equal(changed, 1);
});

test('one panel at a time, collision-aware position, Escape restores focus, and outside click dismisses', () => {
    const app = setup({ width: 390, height: 700 }), first = app.chipsToolbar(), second = app.chipsToolbar();
    app.run();
    const trigger = first.toolbar.querySelector('.ui-filter-trigger'), panel = first.toolbar.querySelector('[data-table-filter-panel]');
    trigger.rect = { left: 290, right: 378, top: 610, bottom: 654 };
    trigger.click();
    assert.equal(trigger.getAttribute('aria-expanded'), 'true');
    assert.equal(panel.popoverOpen, true);
    assert.equal(panel.style.width, '366px');
    assert.equal(panel.style.left, '12px');
    assert.equal(panel.style.transformOrigin, 'bottom right');
    assert.equal(app.document.activeElement, first.all);
    second.toolbar.querySelector('.ui-filter-trigger').click();
    assert.equal(panel.hidden, true);
    app.emit('keydown', app.document.activeElement, { key: 'Escape' });
    assert.equal(second.toolbar.querySelector('[data-table-filter-panel]').hidden, true);
    assert.equal(app.document.activeElement, second.toolbar.querySelector('.ui-filter-trigger'));
    trigger.click();
    app.emit('pointerdown', app.body);
    assert.equal(panel.hidden, true);
});

test('calendar/select children are not dismissed or swallowed by the parent filter', () => {
    const app = setup(), fixture = app.chipsToolbar();
    app.run();
    const trigger = fixture.toolbar.querySelector('.ui-filter-trigger'), panel = fixture.toolbar.querySelector('[data-table-filter-panel]');
    const calendar = app.make('div', { class: 'app-calendar' }), day = app.make('button');
    calendar.append(day); app.body.append(calendar);
    trigger.click();
    app.emit('pointerdown', day); app.emit('focusin', day);
    const escape = app.emit('keydown', day, { key: 'Escape' });
    assert.equal(panel.hidden, false);
    assert.equal(escape.defaultPrevented, undefined);
    calendar.hidden = true;
    app.emit('keydown', trigger, { key: 'Escape' });
    assert.equal(panel.hidden, true);
});

test('remote and uiLive scans enhance new toolbars once; removed panels and menu changes close cleanly', () => {
    const app = setup();
    app.run();
    const fixture = app.chipsToolbar();
    const watch = app.observers.find(observer => observer.options.childList);
    watch.fn([{ addedNodes: [fixture.toolbar] }]);
    app.hooks[0](fixture.toolbar);
    const trigger = fixture.toolbar.querySelector('.ui-filter-trigger'), panel = fixture.toolbar.querySelector('[data-table-filter-panel]');
    assert.equal(fixture.toolbar.querySelectorAll('[data-table-filter-panel]').length, 1);
    trigger.click();
    fixture.toolbar.remove();
    watch.fn([{ addedNodes: [] }]);
    assert.equal(panel.hidden, true);
    app.body.append(fixture.toolbar); trigger.click();
    app.observers.find(observer => observer.options.attributeFilter?.includes('data-active-task')).fn([]);
    assert.equal(panel.hidden, true);
});

test('applied history closes without reset; invalid filter reopens without stealing validation focus', () => {
    const app = setup(), fixture = app.chipsToolbar();
    app.run();
    const trigger = fixture.toolbar.querySelector('.ui-filter-trigger'), panel = fixture.toolbar.querySelector('[data-table-filter-panel]');
    trigger.click(); fixture.paid.click();
    fixture.toolbar.dispatchEvent({ type: 'ui-filter:applied' });
    assert.equal(panel.hidden, true);
    assert.equal(fixture.paid.getAttribute('aria-pressed'), 'true');
    app.document.activeElement = fixture.search;
    fixture.toolbar.dispatchEvent({ type: 'invalid' });
    assert.equal(panel.hidden, false);
    assert.equal(app.document.activeElement, fixture.search);
});

test('without Popover API, fixed-position fallback still opens and closes', () => {
    const app = setup({ nativePopover: false }), fixture = app.chipsToolbar();
    app.run();
    fixture.toolbar.querySelector('.ui-filter-trigger').click();
    const panel = fixture.toolbar.querySelector('[data-table-filter-panel]');
    assert.equal(panel.hidden, false);
    app.window.uiTableFilters.close();
    assert.equal(panel.hidden, true);
});

test('closing the parent dismisses its child picker so a top-layer menu cannot be orphaned', () => {
    const app = setup(), fixture = app.chipsToolbar();
    app.run();
    const trigger = fixture.toolbar.querySelector('.ui-filter-trigger'), panel = fixture.toolbar.querySelector('[data-table-filter-panel]');
    const childTrigger = app.make('button', { 'aria-controls': 'child-select', 'aria-expanded': 'true' });
    panel.querySelector('.ui-filter-fields').append(childTrigger);
    const child = app.make('div', { id: 'child-select', class: 'select-options' });
    child.addEventListener('keydown', event => { if (event.key === 'Escape') child.hidden = true; });
    app.body.append(child); trigger.click();
    app.window.uiTableFilters.close();
    assert.equal(child.hidden, true);
    assert.equal(panel.hidden, true);
});

test('a programmatic catalog reset refreshes the filter count without replacing its controls', async () => {
    const app = setup(), catalog = app.make('div', { class: 'catalog-filter-row' });
    const field = app.make('div'), select = app.make('select', { id: 'product-stock-filter', value: 'low' });
    select.options = [{ value: 'all' }, { value: 'low' }];
    field.append(select);
    const reset = app.make('button', { id: 'catalog-reset', type: 'button' });
    reset.addEventListener('click', () => { select.value = 'all'; });
    catalog.append(field, reset); app.body.append(catalog); app.run();
    const badge = catalog.querySelector('.ui-filter-count');
    assert.equal(badge.hidden, false);
    reset.click();
    await Promise.resolve();
    assert.equal(badge.hidden, true);
    assert.equal(select.value, 'all');
});

test('shared floating animation, selected-option motion, and reduced-motion contract remain available', () => {
    assert.match(styles, /animation:var\(--ui-float-in\)/);
    assert.match(styles, /prefers-reduced-motion:reduce/);
    assert.doesNotMatch(styles, /@keyframes/);
    const motion = readFileSync(new URL('../resources/views/v2/ui-motion.blade.php', import.meta.url), 'utf8');
    assert.match(motion, /\.ui-chips/);
    assert.match(motion, /function slidePill/);
});

test('uiLive synchronizes moved filter controls by identity rather than rebuilding the old toolbar shape', () => {
    const live = readFileSync(new URL('../resources/views/v2/ui-live.blade.php', import.meta.url), 'utf8');
    const start = live.indexOf('function syncNode('), end = live.indexOf('\nfunction patch(', start);
    const calls = [];
    const old = { matches: () => false, hasAttribute: name => name === 'data-filter-ready' };
    const fresh = { tagName: 'DIV', hasAttribute: () => false };
    vm.runInNewContext(live.slice(start, end) + '\nsyncNode(old,fresh);', {
        OPAQUE: '', old, fresh, syncForm: (previous, next) => calls.push([previous, next]),
    });
    assert.equal(calls.length, 1);
    assert.equal(calls[0][0], old);
    assert.equal(calls[0][1], fresh);
});

test('kolom cari mengisi satu baris penuh di HP', () => {
    assert.match(styles, /@media\(max-width:760px\)\{[\s\S]*?\[data-filter-search\]\{flex:1 1 100%;width:100%;max-width:none\}/);
});
