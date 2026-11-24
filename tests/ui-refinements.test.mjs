import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const read = (path) => readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');
const tooltip = read('resources/views/v2/ui-tooltip.blade.php');
const workspace = read('resources/views/v2/workspace.blade.php');
const layout = read('resources/views/layouts/app.blade.php');
const auth = read('app/Http/Controllers/AuthController.php');
const status = read('resources/views/components/ui/status.blade.php');
const master = read('resources/views/v2/master-page-item.blade.php');
const staff = read('resources/views/v2/master-staff.blade.php');
const relations = read('resources/views/v2/relation-master.blade.php');
const insights = read('resources/views/v2/master-insights.blade.php');
const uiWorkspace = read('resources/views/v2/ui-workspace.blade.php');

test('tooltip bersama dimuat di workspace, halaman lama, dan login', () => {
    assert.match(workspace, /@include\('v2\.ui-tooltip'\)/);
    assert.match(layout, /@include\('v2\.ui-tooltip'\)/);
    assert.match(auth, /view\('v2\.ui-tooltip'\)->render\(\)/);
    assert.match(tooltip, /role',\s*'tooltip'/);
    assert.match(tooltip, /aria-describedby/);
    assert.match(tooltip, /data-tip-truncate/);
    assert.match(tooltip, /prefers-reduced-motion/);
    assert.match(tooltip, /data-tip-skip/);
});

test('badge arsip menjelaskan cara mengaktifkan kembali', () => {
    assert.match(status, /'archived' => 'Diarsipkan: tidak muncul di pilihan baru\. Gunakan Aktifkan kembali/);
    assert.match(status, /data-tip="\{\{ \$tips\[\$status\] \}\}"/);
});

test('baris arsip menampilkan Aktifkan kembali dan baris aktif tetap Arsipkan', () => {
    for (const view of [master, staff, relations]) {
        assert.match(view, /@if\(\$[a-zA-Z]+(?:->is_active)?\)/);
        assert.match(view, /v2\.master\.activate|v2\.relations\.activate/);
        assert.match(view, /Aktifkan kembali/);
        assert.match(view, /Arsipkan/);
    }
    assert.doesNotMatch(master, /btn-action-archive"\s+@disabled/);
});

test('dialog rincian memakai daftar fakta, bukan kartu KPI, dan tanpa blok riwayat mati', () => {
    assert.match(insights, /class="insight-facts"/);
    assert.doesNotMatch(insights, /data-insight-history-block/);
    assert.ok(insights.indexOf('data-supplier-activity') < insights.indexOf('data-insight-list'));
    assert.match(insights, /class="insight-section"/);
    assert.match(insights, /class="insight-range"/);
});

test('halaman biaya legacy mendapat format Rupiah yang hilang di workspace', () => {
    const start = uiWorkspace.indexOf('// ui-currency-compat-start');
    const end = uiWorkspace.indexOf('// ui-currency-compat-end');
    assert.ok(start >= 0 && end > start, 'blok kompatibilitas mata uang tersedia');
    const el = {
        value: '', attrs: { 'data-currency-scale': '2' },
        getAttribute(name) { return this.attrs[name] ?? null; },
    };
    const context = { window: {}, Number, String, parseInt, isNaN };
    vm.createContext(context);
    vm.runInContext(uiWorkspace.slice(start, end), context);
    assert.equal(typeof context.window.setCurrencyInput, 'function');
    context.window.setCurrencyInput(el, '100000');
    assert.equal(el.value, '100.000');
    context.window.setCurrencyInput(el, '100000.5');
    assert.equal(el.value, '100.000,5');
    context.window.setCurrencyInput(el, '');
    assert.equal(el.value, '');
});
