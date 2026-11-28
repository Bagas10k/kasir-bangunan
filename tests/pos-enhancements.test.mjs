import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const workspace = readFileSync(new URL('../resources/views/v2/workspace.blade.php', import.meta.url), 'utf8');
const cashierScript = readFileSync(new URL('../resources/views/v2/pos-cashier-script.blade.php', import.meta.url), 'utf8');
const cashierView = readFileSync(new URL('../resources/views/v2/pos-cashier.blade.php', import.meta.url), 'utf8');
const restockScript = readFileSync(new URL('../resources/views/v2/receipt-restock-script.blade.php', import.meta.url), 'utf8');
const driverScript = readFileSync(new URL('../resources/views/v2/driver-operations.blade.php', import.meta.url), 'utf8');
const uiResult = readFileSync(new URL('../resources/views/v2/ui-result.blade.php', import.meta.url), 'utf8');
const docView = readFileSync(new URL('../resources/views/v2/document.blade.php', import.meta.url), 'utf8');

test('idempotency UUID generators have safe non-HTTPS fallbacks across POS, restock, and driver operations', () => {
    assert.match(workspace, /window\.v2Uuid\s*=\s*uuid/);
    assert.doesNotMatch(restockScript, /key:crypto\.randomUUID\(\)/);
    assert.doesNotMatch(driverScript, /keys\.set\(f,crypto\.randomUUID\(\)\)/);
    assert.match(cashierScript, /const safeUuid=\(\)=>\{/);

    // Execute the exact safeUuid function in a non-HTTPS context (crypto.randomUUID unavailable) and legacy context (no crypto).
    const match = cashierScript.match(/const safeUuid=\(\)=>\{[\s\S]*?\};/);
    assert.ok(match, 'safeUuid helper harus ada di pos-cashier-script');

    const nonHttpsCtx = {
        window: {
            crypto: {
                getRandomValues(arr) {
                    for (let i = 0; i < arr.length; i += 1) arr[i] = (i * 17 + 5) & 255;
                    return arr;
                },
            },
        },
        Uint8Array, Array, Date, Math,
    };
    const keyNonHttps = vm.runInNewContext(match[0] + ' safeUuid();', nonHttpsCtx);
    assert.equal(keyNonHttps.length, 32);
    assert.match(keyNonHttps, /^[0-9a-f]{32}$/);

    const legacyCtx = { window: {}, Date, Math };
    const keyLegacy = vm.runInNewContext(match[0] + ' safeUuid();', legacyCtx);
    assert.match(keyLegacy, /^pos-\d+-[0-9a-f]+$/);
});

test('cashier UI enforces customer requirement for transfer and credit, and fail-fast transfer proof checks', () => {
    assert.match(cashierScript, /const needsCustomer = method === 'credit' \|\| method === 'transfer';/);
    assert.match(cashierScript, /if\(\(method==='credit'\|\|method==='transfer'\)&&!form\.elements\.customer_id\.value\)/);
    assert.match(cashierScript, /Unggah foto bukti transfer pelanggan terlebih dahulu\./);
    assert.match(cashierScript, /Foto bukti transfer harus berformat JPG, PNG, atau WebP\./);
    assert.match(cashierScript, /Ukuran foto bukti transfer maksimal 5 MB\./);
    assert.match(cashierView, /Transfer dan kredit wajib memilih pelanggan terdaftar/);
});

test('cashier script includes exact SKU Enter priority, review breakdown, catalog sync, hold queue, and notes', () => {
    assert.match(cashierScript, /prepared\.find\(item=>item\.skuLower===q&&item\.qty>0n\)/);
    assert.match(cashierScript, /'Subtotal barang'/);
    assert.match(cashierScript, /'Total diskon'/);
    assert.match(cashierScript, /'Catatan nota'/);
    assert.match(cashierScript, /async function syncCatalogFromServer\(/);
    assert.match(cashierScript, /function holdCurrentCart\(\)/);
    assert.match(cashierScript, /function resumeHeld\(/);
    assert.match(cashierView, /id="catalog-refresh"/);
    assert.match(cashierView, /id="hold-cart"/);
    assert.match(cashierView, /id="pos-held-dialog"/);
    assert.match(cashierView, /id="checkout-notes"/);
    assert.match(cashierView, /id="pos-arrears-banner"/);
    assert.match(workspace, /link\.target = '_blank'/);
    assert.match(workspace, /Buka surat jalan/);
    assert.match(workspace, /payload\.notes = notesVal/);
});

test('sale success dialog shows pending transfer verification note for transfer and credit transfer DP', () => {
    const script = uiResult.slice(uiResult.indexOf('<script>') + 8, uiResult.lastIndexOf('</script>'));
    const runDialog = (method, paidAmount, depositMethod = 'cash') => {
        const created = [];
        const makeEl = (tag) => ({
            tag, children: [], attrs: {}, className: '', textContent: '',
            classList: { add() {} },
            append(...items) { this.children.push(...items); },
            setAttribute(k, v) { this.attrs[k] = v; },
            addEventListener() {}, remove() {}, showModal() {}, close() {}, focus() {},
            matches: () => false,
        });
        const document = {
            createElement(tag) { const el = makeEl(tag); created.push(el); return el; },
            createElementNS(ns, tag) { return makeEl(tag); },
            getElementById: () => null,
            querySelector: () => null,
            body: { append() {} },
        };
        const window = {
            location: { href: 'https://tb01.test/kasir-bangunan/' },
            matchMedia: () => ({ matches: true }),
            setTimeout: () => 0,
            clearTimeout() {},
        };
        const sessionStorage = { getItem: () => null, setItem() {}, removeItem() {} };
        vm.runInNewContext(script, { window, document, sessionStorage, URL, Date, JSON, String, Math });
        const form = {
            elements: {
                customer_id: { value: '1', selectedOptions: [{ textContent: 'Pelanggan Sintetis' }] },
                payment_method: { value: method },
                deposit_method: { value: depositMethod, disabled: method !== 'credit' },
                paid_amount: { value: paidAmount },
            },
        };
        window.uiResult.sale({ number: 'INV-SYN-1', total: '100000.00', paid: '0.00', status: 'unpaid' }, form);
        return created.some((el) => el.className === 'ur-note' && /menunggu verifikasi admin/i.test(el.textContent));
    };

    assert.equal(runDialog('transfer', '100000.00'), true, 'Transfer penuh harus menampilkan catatan menunggu verifikasi');
    assert.equal(runDialog('credit', '25000.00', 'transfer'), true, 'Kredit dengan DP transfer harus menampilkan catatan menunggu verifikasi');
    assert.equal(runDialog('credit', '25000.00', 'cash'), false, 'Kredit dengan DP tunai tidak menampilkan catatan transfer');
    assert.equal(runDialog('cash', '100000.00'), false, 'Tunai tidak menampilkan catatan transfer');
});

test('printable document view removes preview labels and supports A4/thermal 80mm print layouts', () => {
    assert.doesNotMatch(docView, /Pratinjau V2/);
    assert.doesNotMatch(docView, /Shift #/);
    assert.match(docView, /data-doc-layout="thermal"/);
    assert.match(docView, /body\.is-thermal/);
    assert.match(docView, /sign-grid/);
    assert.match(docView, /Catatan \/ Instruksi/);
});
