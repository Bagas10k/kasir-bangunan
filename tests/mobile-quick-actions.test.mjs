import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../resources/views/v2/mobile-quick-actions.blade.php', import.meta.url), 'utf8');
const workspace = readFileSync(new URL('../resources/views/v2/workspace.blade.php', import.meta.url), 'utf8');

test('pil aksi mobile dimuat di workspace dan maksimal tiga aksi per peran', () => {
    assert.match(workspace, /@include\('v2\.mobile-quick-actions'\)/);
    const admin = source.slice(source.indexOf("role==='admin'?["), source.indexOf(']:['));
    const cashier = source.slice(source.indexOf(']:['), source.indexOf('];', source.indexOf(']:[')));
    for (const [block, labels] of [[admin, ['Buat orderan', 'Barang masuk', 'Pengeluaran']], [cashier, ['Kasir', 'Riwayat', 'Status bayar']]]) {
        for (const label of labels) assert.ok(block.includes(`label:'${label}'`), label);
        assert.equal((block.match(/\{key:/g) || []).length, 3);
    }
});

test('aksi mengarah ke kontrol yang sudah ada, bukan alur baru', () => {
    assert.match(source, /focus:'#pos-product-search'/);
    assert.match(source, /click:'\[data-receipt-new\]'/);
    assert.match(source, /click:'\.expenses-page \.exp-btn--primary'/);
    assert.match(source, /task:'riwayat-penjualan'/);
    assert.match(source, /task:'persetujuan'/);
});

test('tampilan mengikuti referensi: ikon saja, pil gelap, lingkaran lime untuk aksi aktif', () => {
    assert.match(source, /button\.innerHTML=icons\[action\.icon\]\|\|''/);
    assert.doesNotMatch(source, /'<span>'\+action\.label/);
    assert.match(source, /\.mqa\{[^}]*background:var\(--ui-green-2\)/);
    assert.match(source, /\.mqa-spot\{[^}]*background:var\(--ui-lime\)/);
    assert.match(source, /transition:transform var\(--ui-morph-duration\)/);
    assert.match(source, /spot\.animate/);
    assert.match(source, /button\.classList\.toggle\('is-active',on\)/);
    assert.match(source, /const match=actions\.find\(item=>item\.task===task\),activeKey=match\?match\.key:actions\[0\]\.key/);
    assert.match(source, /if\(on&&match\)button\.setAttribute\('aria-current','page'\)/);
});

test('pil hilang di Kasir, saat modal, keranjang, sidebar, dan keyboard', () => {
    assert.match(source, /task!=='kasir'/);
    assert.match(source, /@media\(width > 900px\)\{\.mqa-dock\{display:none!important\}\}/);
    assert.match(source, /body:has\(dialog\[open\]\) \.mqa-dock/);
    assert.match(source, /body:has\(\.modal-overlay\.open\) \.mqa-dock/);
    assert.match(source, /body\.pm-cart-open \.mqa-dock/);
    assert.match(source, /body\.sidebar-open \.mqa-dock/);
    assert.match(source, /body:has\(main :is\(input,select,textarea\):focus\) \.mqa-dock/);
    assert.match(source, /body\.mqa-on>main\.main-content\{[^}]*overflow:auto/);
    assert.match(source, /\.mqa-dock\{flex:none;[^}]*env\(safe-area-inset-bottom\)/);
});
