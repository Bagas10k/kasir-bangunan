import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const mobile = readFileSync(new URL('../resources/views/v2/pos-mobile.blade.php', import.meta.url), 'utf8');
const period = readFileSync(new URL('../resources/views/v2/pos-period-script.blade.php', import.meta.url), 'utf8');

test('kartu diskon baris membuka/menutup dengan morph tinggi dan menghormati reduced-motion', () => {
    assert.match(mobile, /details\.line-discount>summary/);
    assert.match(mobile, /details\.animate\(\[\{height:from\+'px'\},\{height:to\+'px'\}\]/);
    assert.match(mobile, /if\(reduce\(\)\|\|event\.metaKey/);
    assert.match(mobile, /discount-controls :is\(select,input\)\{min-height:40px/);
});

test('grafik Kasir dianimasikan saat periode berganti, bukan berganti mendadak', () => {
    assert.match(period, /function chartIn\(pane\)/);
    assert.match(period, /figure\.animate\(\[\{opacity:0,transform:'translateY\(8px\) scale\(\.99\)'\}/);
    assert.match(period, /if\(animate&&before!==period\)chartIn\(pane\)/);
});

test('panel tanggal khusus menampilkan rentang terpakai dan tidak memuat ulang rentang yang sama', () => {
    assert.match(period, /function paintTrigger\(\)/);
    assert.match(period, /trigger\.textContent=active\?rangeLabel\(appliedRange\):'Tanggal khusus'/);
    assert.match(period, /if\(!force&&appliedRange&&range\.from===appliedRange\.from/);
    assert.match(period, /status\.textContent='Rentang diterapkan\.'/);
    assert.doesNotMatch(period, /Stok tetap kondisi saat ini/);
});
