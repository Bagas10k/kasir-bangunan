import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';

const dir = new URL('../resources/views/v2/', import.meta.url);

test('ruang kerja tidak memuat ulang halaman setelah simpan; hanya uiLive yang boleh sebagai cadangan', () => {
    const offenders = [];
    for (const name of readdirSync(dir).filter((file) => file.endsWith('.blade.php'))) {
        const text = readFileSync(new URL(name, dir), 'utf8');
        const hits = text.match(/location\.reload\(\)/g) ?? [];
        // ui-live: cadangan bila halaman gagal diambil; ui-result: cadangan bila skrip kasir tidak ada.
        const allowed = { 'ui-live.blade.php': 1, 'ui-result.blade.php': 1 }[name] ?? 0;
        if (hits.length > allowed) {
            offenders.push(`${name}: ${hits.length}`);
        }
    }
    assert.deepEqual(offenders, []);
});

test('ui-live dimuat sebelum skrip yang mendaftarkan pemasang perilaku', () => {
    const workspace = readFileSync(new URL('workspace.blade.php', dir), 'utf8');
    const live = workspace.indexOf("@include('v2.ui-live')");
    assert.ok(live > 0);
    for (const consumer of ["@include('v2.sprint2')", "@include('v2.ui-workspace')", "@include('v2.ui-forms')"]) {
        assert.ok(workspace.indexOf(consumer) > live, consumer);
    }
});
