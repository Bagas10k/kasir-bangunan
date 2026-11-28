import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../resources/views/v2/mobile-quick-actions.blade.php', import.meta.url), 'utf8');
const posMobile = readFileSync(new URL('../resources/views/v2/pos-mobile.blade.php', import.meta.url), 'utf8');
const workspace = readFileSync(new URL('../resources/views/v2/workspace.blade.php', import.meta.url), 'utf8');

test('kapsul navigasi mobile dimuat di workspace dengan empat tujuan halaman per peran', () => {
    assert.match(workspace, /@include\('v2\.mobile-quick-actions'\)/);
    const admin = source.slice(source.indexOf("role==='admin'?["), source.indexOf(']:['));
    const cashier = source.slice(source.indexOf(']:['), source.indexOf('];', source.indexOf(']:[')));
    for (const [block, labels] of [
        [admin, ['Ringkasan', 'Masuk', 'Biaya', 'Kasir']],
        [cashier, ['Riwayat', 'Tagihan', 'Bayar', 'Kasir']],
    ]) {
        for (const label of labels) assert.ok(block.includes(`label:'${label}'`), label);
        assert.equal((block.match(/\{key:/g) || []).length, 4);
    }
});

test('klik kapsul hanya berpindah halaman tanpa membuka form otomatis atau memicu fokus input', () => {
    assert.match(source, /task:'ringkasan'/);
    assert.match(source, /task:'penerimaan'/);
    assert.match(source, /task:'pengeluaran'/);
    assert.match(source, /task:'kasir'/);
    assert.match(source, /task:'riwayat-penjualan'/);
    assert.match(source, /task:'tagihan'/);
    assert.match(source, /task:'persetujuan'/);
    assert.doesNotMatch(source, /click:'\[data-receipt-new\]'/);
    assert.doesNotMatch(source, /click:'\.expenses-page/);
    assert.doesNotMatch(source, /focus:'#pos-product-search'/);
    assert.match(source, /location\.hash='#'\+action\.task/);
});

test('tampilan kapsul bergaya tombol bulat nonaktif + kapsul limau meluncur murni GPU tanpa animasi lebar tombol', () => {
    assert.match(source, /\.mqa-shell\{[^}]*background:var\(--ui-side,#0f2a1d\)/);
    assert.match(source, /\.mqa-spot\{[^}]*background:var\(--ui-lime,#c8f26b\)/);
    assert.match(source, /\.mqa-indicator\{[^}]*transition:transform 340ms cubic-bezier\(\.22,1,\.36,1\)/);
    assert.match(source, /\.mqa button\.is-after\{transform:translate3d\(var\(--mqa-expand\),0,0\)\}/);
    assert.doesNotMatch(source, /\.mqa button\{[^}]*transition:[^;}]*\bwidth\b/);
    assert.doesNotMatch(source, /transition:[^;}]*max-inline-size/);
    assert.doesNotMatch(source, /filter:drop-shadow/);
    assert.doesNotMatch(source, /cloneNode/);
    assert.match(source, /\.mqa button\.is-active \.mqa-label\{[^}]*opacity:1/);
    assert.match(source, /spot\.animate/);
    assert.match(source, /button\.classList\.toggle\('is-active',on\)/);
    assert.match(source, /if\(on\)button\.setAttribute\('aria-current','page'\);else button\.removeAttribute\('aria-current'\)/);
});

test('opsi 2: satu cangkang pil solid bersama (.mqa-shell) melebar/menyempit mulus antara kapsul dan bar keranjang', () => {
    assert.match(source, /task!=='kasir'\|\|!hasCart\|\|peeking\|\|landing/);
    assert.match(source, /\.mqa-shell\.is-cart\{width:min\(356px,calc\(100vw - 24px\)\)\}/);
    assert.match(source, /document\.body\.classList\.add\('mqa-landing'\)/);
    assert.match(source, /document\.addEventListener\('mqa:peek'/);
    assert.match(source, /class="mqa-badge"/);
    assert.match(posMobile, /class="pm-nav-toggle"/);
    assert.match(posMobile, /new CustomEvent\('mqa:peek'\)/);
    assert.match(posMobile, /html body\.mqa-on \.pm-bar\{background:transparent;box-shadow:none\}/);
    assert.match(posMobile, /html body\[data-active-task="kasir"\] \.pm-bar\[hidden\]\{display:grid;pointer-events:none;visibility:hidden/);
    assert.match(posMobile, /body\.mqa-peek \.pm-bar,body\.mqa-landing \.pm-bar\{visibility:hidden;pointer-events:none/);
});

test('kapsul hilang saat modal, lembar keranjang, sidebar, keyboard, dan desktop', () => {
    assert.match(source, /@media\(width > 900px\)\{\.mqa-dock,\.mqa-scrim,\.mqa-shell\{display:none!important\}\}/);
    assert.match(source, /body:has\(dialog\[open\]\) \.mqa-dock/);
    assert.match(source, /body:has\(\.modal-overlay\.open\) \.mqa-dock/);
    assert.match(source, /body\.pm-cart-open \.mqa-dock/);
    assert.match(source, /body\.sidebar-open :is\(\.mqa-dock,\.mqa-scrim\)\{transform:translateY\(100%\)\}/);
    assert.match(source, /bar\.inert=isCartMode\|\|document\.body\.classList\.contains\('sidebar-open'\)/);
    assert.match(source, /body:has\(main :is\(input,select,textarea\):focus\) \.mqa-dock/);
    assert.doesNotMatch(source, /body\.mqa-on>main\.main-content\{[^}]*overflow:auto/);
    assert.match(source, /body\.mqa-on>main\.wrap\.main-content,html body\.mqa-on>main\.main-content\{padding-bottom:calc\(112px \+ env\(safe-area-inset-bottom\)\)!important\}/);
    assert.match(source, /\.mqa-dock\{[^}]*position:fixed;[^}]*env\(safe-area-inset-bottom\)/);
});
