import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const css = readFileSync(new URL('../resources/views/v2/responsive-refinements.blade.php', import.meta.url), 'utf8');

test('pil status kepala monitor memakai warna badge tema tanpa bingkai tebal', () => {
    assert.match(css, /\.ui-monitor-head \.ui-pill\{[^}]*border:0/);
    assert.match(css, /\.ui-monitor-head \.ui-pill--ok\{background:var\(--ui-green-soft\);color:var\(--ui-green\)\}/);
    assert.match(css, /\.ui-monitor-head \.ui-pill--bad\{background:var\(--ui-bad-bg\);color:var\(--ui-bad-ink\)\}/);
    assert.match(css, /\.ui-monitor-head \.ui-pill::before\{[^}]*width:8px/);
});
