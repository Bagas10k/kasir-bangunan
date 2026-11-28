@php
    $opnameQty = function ($value): string {
        [$int, $frac] = array_pad(explode('.', (string) $value), 2, '');
        $frac = rtrim($frac, '0');

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', ltrim($int, '0') === '' ? '0' : ltrim($int, '0')).($frac !== '' ? ','.$frac : '');
    };
@endphp
<style>
.opn-row{display:grid;grid-template-columns:minmax(0,1fr) 110px 130px 110px 44px;gap:8px;align-items:end;padding:8px 0;border-bottom:1px solid var(--ui-line,#dbe5de)}
.opn-row .field{margin:0}
.opn-stat{font-size:13px;font-variant-numeric:tabular-nums;color:var(--ui-muted,#5F7468)}
.opn-diff{font-size:13px;font-weight:700;font-variant-numeric:tabular-nums;text-align:right;white-space:nowrap}
.opn-diff.is-pos{color:#14532D}.opn-diff.is-neg{color:#991B1B}
.opn-foot{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:10px}
@media(max-width:720px){.opn-row{grid-template-columns:1fr 1fr}.opn-row>.field:first-child{grid-column:1/-1}}
</style>
<section class="card has-head-action" id="stock-opname-history" aria-labelledby="stock-opname-heading">
    <h2 id="stock-opname-heading">Stok opname <x-ui.info-tip text="Selisih kurang dicatat ke akun 5201; selisih lebih ke akun 7101 atau 4201." label="Keterangan jurnal stok opname" :data-tip-article="route('help.index', 'stok-opname')" /></h2>
    <p class="note">Cocokkan jumlah fisik barang dengan stok di sistem.</p>
    <button type="button" class="popup-action card-head-action" data-open-popup="stock-opname-dialog">+ Catat stok opname</button>
    <x-ui.toolbar search="Cari nomor, catatan, atau petugas" label="Cari stok opname" :chips="['Semua' => 'all', 'Ada selisih' => 'variance:yes', 'Cocok' => 'variance:no']"/>
    <div data-server-table="stock-opnames" data-table-url="{{ route('v2.tables.show', 'stock-opnames') }}" data-table-default-filter="all" data-live-skip aria-live="polite">@include('v2.tables.stock-opnames-results')</div>
<dialog class="input-dialog popup-wide" id="stock-opname-dialog" aria-labelledby="stock-opname-dialog-title">
    <div class="popup-heading">
        <div class="popup-titles">
            <h2 id="stock-opname-dialog-title">Catat stok opname</h2>
            <p class="popup-sub">Pilih barang, isi jumlah fisik hasil hitung gudang, lalu posting.</p>
        </div>
        <button type="button" class="popup-close secondary" data-close-dialog aria-label="Tutup stok opname">×</button>
    </div>
    <div class="popup-content">
        <form id="stock-opname-form" action="{{ route('v2.stock-opnames.store') }}" data-search-url="{{ route('v2.stock-opnames.products') }}" novalidate>
            <input type="hidden" name="idempotency_key" data-fresh-key>
            <div class="row">
                <div class="field">
                    <label for="opname-date">Tanggal opname</label>
                    <input id="opname-date" type="date" name="opname_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                </div>
                <div class="field">
                    <label for="opname-notes">Catatan (opsional)</label>
                    <input id="opname-notes" type="text" name="notes" maxlength="255" autocomplete="off" placeholder="Mis. Opname mingguan gudang depan">
                </div>
            </div>
            <div id="opname-items"></div>
            <div class="opn-foot">
                <button type="button" class="secondary" id="opname-add">+ Tambah barang</button>
                <p class="rc-total" role="status" aria-live="polite"><span id="opname-count">0 barang</span> · <strong id="opname-net">Rp 0</strong></p>
            </div>
            <p class="err" data-error role="alert" tabindex="-1" hidden></p>
            <p class="ok" data-ok hidden></p>
            <div class="popup-submit" style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;padding-top:12px;border-top:1px solid var(--ui-line,#E4EBE6)">
                <button type="button" class="secondary" data-close-dialog>Batal</button>
                <button type="submit">Posting stok opname</button>
            </div>
        </form>
    </div>
</dialog>
</section>

<template id="opname-row-tpl">
    <div class="opn-row">
        <div class="field"><label class="rc-field-label">Barang</label><select data-name="product_id" data-pick-placeholder="Cari nama atau SKU barang" required><option value="">— pilih barang —</option></select></div>
        <div class="opn-stat" data-opn-system>Sistem: —</div>
        <div class="field"><label class="rc-field-label">Jumlah fisik</label><input data-name="physical_qty" type="text" inputmode="decimal" maxlength="30" placeholder="0" required></div>
        <div class="opn-diff" data-opn-diff>Selisih: —</div>
        <button type="button" class="secondary rc-remove" data-remove aria-label="Hapus baris opname">×</button>
    </div>
</template>

<script>
(() => {
    const form = document.getElementById('stock-opname-form');
    const dialog = document.getElementById('stock-opname-dialog');
    const box = document.getElementById('opname-items');
    const tpl = document.getElementById('opname-row-tpl');
    if (!form || !dialog || !box || !tpl) { return; }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const searchUrl = form.dataset.searchUrl;
    const freshKey = () => (typeof window.crypto?.randomUUID === 'function') ? 'opn-' + window.crypto.randomUUID() : 'opn-' + Date.now() + '-' + Math.random().toString(16).slice(2);
    const num = v => { const n = parseFloat(String(v ?? '').replace(/\.(?=\d{3}\b)/g, '').replace(',', '.')); return Number.isFinite(n) ? n : NaN; };
    const rupiah = v => { const neg = v < 0; return (neg ? '-' : '') + 'Rp ' + Math.round(Math.abs(v)).toLocaleString('id-ID'); };
    const fmtQty = v => Number(v).toLocaleString('id-ID', { maximumFractionDigits: 4 });
    const taken = sel => Array.from(box.querySelectorAll('[data-name=product_id]')).filter(o => o !== sel).map(o => o.value).filter(Boolean);

    function recalc() {
        let count = 0, net = 0;
        for (const row of box.children) {
            const pid = row.querySelector('[data-name=product_id]').value;
            const sys = Number(row.dataset.stock || 0);
            const cost = Number(row.dataset.cost || 0);
            const phys = num(row.querySelector('[data-name=physical_qty]').value);
            const diffEl = row.querySelector('[data-opn-diff]');
            if (pid) { count++; }
            if (pid && Number.isFinite(phys)) {
                const diff = phys - sys;
                const val = diff * cost;
                net += val;
                diffEl.className = 'opn-diff ' + (diff > 0 ? 'is-pos' : (diff < 0 ? 'is-neg' : ''));
                diffEl.textContent = (diff > 0 ? '+' : '') + fmtQty(diff) + ' · ' + rupiah(val);
            } else {
                diffEl.className = 'opn-diff';
                diffEl.textContent = 'Selisih: —';
            }
        }
        document.getElementById('opname-count').textContent = count + ' barang';
        document.getElementById('opname-net').textContent = (net > 0 ? '+' : '') + rupiah(net);
    }

    function addRow(focus = false) {
        const node = tpl.content.firstElementChild.cloneNode(true);
        const sel = node.querySelector('[data-name=product_id]');
        const qtyInput = node.querySelector('[data-name=physical_qty]');
        const sysEl = node.querySelector('[data-opn-system]');
        node.querySelector('[data-remove]').addEventListener('click', () => {
            if (box.children.length > 1) { node.remove(); } else { sel.value = ''; qtyInput.value = ''; node.dataset.stock = ''; node.dataset.cost = ''; sysEl.textContent = 'Sistem: —'; sel._uiPickSync?.(); }
            recalc();
        });
        box.append(node);
        window.uiPick?.(sel, {
            source: async term => {
                const res = await fetch(searchUrl + '?q=' + encodeURIComponent(term.trim()), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                if (!res.ok) { throw new Error('gagal'); }
                const data = await res.json();
                const used = taken(sel);
                return {
                    total: data.total,
                    items: data.results.map(p => ({
                        value: String(p.id),
                        label: p.name + ' · ' + p.unit,
                        meta: p.sku + ' · sistem ' + fmtQty(p.stock) + ' ' + p.unit,
                        stock: p.stock,
                        cost: p.cost,
                        unit: p.unit,
                        disabled: used.includes(String(p.id))
                    }))
                };
            },
            onPick: item => {
                qtyInput.value = '';
                node.dataset.stock = item.stock;
                node.dataset.cost = item.cost;
                sysEl.textContent = 'Sistem: ' + fmtQty(item.stock) + ' ' + (item.unit || '');
                recalc();
            }
        });
        recalc();
        if (focus) { (node.querySelector('.ui-pick-input') || sel).focus(); }
    }

    box.addEventListener('input', recalc);
    document.getElementById('opname-add').addEventListener('click', () => addRow(true));
    if (!box.children.length) { addRow(false); }
    form.elements.idempotency_key.value = freshKey();

    dialog.addEventListener('cancel', e => { e.preventDefault(); if (!form.dataset.busy) { dialog.close(); } });
    dialog.addEventListener('click', e => {
        if (e.target !== dialog || form.dataset.busy) { return; }
        const r = dialog.getBoundingClientRect();
        if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) {
            dialog.close();
        }
    });
    form.addEventListener('submit', async e => {
        e.preventDefault();
        if (form.dataset.busy) { return; }
        const err = form.querySelector('[data-error]');
        const showError = (message, focusTarget) => {
            if (err) { err.textContent = message; err.hidden = false; }
            if (focusTarget && typeof focusTarget.focus === 'function') { focusTarget.focus(); } else { err?.focus(); }
        };
        if (err) { err.hidden = true; err.textContent = ''; }

        const dateInput = form.elements.opname_date;
        const opnameDate = String(dateInput?.value || '').trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(opnameDate)) {
            showError('Tanggal opname wajib diisi.', dateInput?._dateTrigger || dateInput);
            return;
        }
        if (dateInput?.max && opnameDate > dateInput.max) {
            showError('Tanggal opname tidak boleh melewati hari ini (' + dateInput.max + ').', dateInput?._dateTrigger || dateInput);
            return;
        }

        const items = [];
        for (const row of box.children) {
            const sel = row.querySelector('[data-name=product_id]');
            const qtyInput = row.querySelector('[data-name=physical_qty]');
            const productId = sel.value;
            const rawText = String(qtyInput.value ?? '').trim();
            if (!productId && rawText === '') { continue; }
            if (!productId) {
                showError('Pilih nama barang terlebih dahulu.', row.querySelector('.ui-pick-input') || sel);
                return;
            }
            if (rawText === '') {
                showError('Isi jumlah fisik untuk barang yang dipilih.', qtyInput);
                return;
            }
            const normalized = rawText.replace(/\.(?=\d{3}\b)/g, '').replace(',', '.');
            const parsed = Number(normalized);
            if (!/^\d+(\.\d+)?$/.test(normalized) || !Number.isFinite(parsed) || parsed < 0) {
                showError('Jumlah fisik harus berupa angka nol atau lebih.', qtyInput);
                return;
            }
            items.push({ product_id: Number(productId), physical_qty: normalized, expected_stock: row.dataset.stock });
        }
        if (!items.length) {
            const firstRow = box.children[0];
            showError('Pilih minimal satu barang dan isi jumlah fisiknya.', firstRow?.querySelector('.ui-pick-input') || firstRow?.querySelector('[data-name=product_id]'));
            return;
        }
        form.dataset.busy = '1';
        form.setAttribute('aria-busy', 'true');
        const btn = form.querySelector('button[type=submit]');
        if (btn) { btn.disabled = true; }
        let saved = false;
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({
                    opname_date: opnameDate,
                    notes: form.elements.notes.value.trim() || null,
                    idempotency_key: form.elements.idempotency_key.value || freshKey(),
                    items
                })
            });
            if (res.redirected) { throw new Error('Sesi berakhir. Muat ulang halaman lalu masuk kembali.'); }
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = Object.values(data.errors || {}).flat().join(' ') || data.message || 'Gagal menyimpan stok opname.';
                showError(msg);
                return;
            }
            saved = true;
            box.replaceChildren();
            addRow(false);
            form.elements.idempotency_key.value = freshKey();
            dialog.close();
            try { await window.uiLive?.saved(form); } catch {}
        } catch (ex) {
            showError(ex?.message || 'Gagal menghubungi server. Periksa koneksi Anda lalu coba lagi.');
        } finally {
            if (!saved) { form.elements.idempotency_key.value = form.elements.idempotency_key.value || freshKey(); }
            if (btn) { btn.disabled = false; }
            delete form.dataset.busy;
            form.removeAttribute('aria-busy');
        }
    });
})();
</script>
