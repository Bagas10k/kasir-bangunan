<section class="card" id="purchase-returns-history" aria-labelledby="h-purchase-returns">
    <h2 id="h-purchase-returns">Riwayat retur pembelian</h2>
    <p class="note">Pengembalian barang ke supplier dari penerimaan yang sudah dikonfirmasi. Mengurangi stok dan utang (atau masuk ke Kas/Bank bila sudah dibayar).</p>
    <x-ui.toolbar search="Cari nomor retur, penerimaan, atau supplier" label="Cari retur pembelian"/>
    <div data-server-table="purchase-returns" data-table-url="{{ route('v2.tables.show', 'purchase-returns') }}" data-table-default-filter="all" data-live-skip aria-live="polite">@include('v2.tables.purchase-returns-results')</div>
<dialog class="input-dialog popup-wide" id="purchase-return-dialog" aria-labelledby="purchase-return-title">
    <div class="popup-heading">
        <div class="popup-titles">
            <h2 id="purchase-return-title">Retur pembelian ke supplier</h2>
            <p class="popup-sub" id="purchase-return-sub"></p>
        </div>
        <button type="button" class="popup-close secondary" data-close-dialog aria-label="Tutup retur pembelian">×</button>
    </div>
    <div class="popup-content">
        <form id="purchase-return-form" novalidate>
            <input type="hidden" name="idempotency_key">
            <div class="row">
                <div class="field"><label for="pr-date">Tanggal retur</label><input id="pr-date" type="date" name="return_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
                <div class="field"><label for="pr-reason">Alasan retur</label><input id="pr-reason" type="text" name="reason" minlength="5" maxlength="255" required autocomplete="off" placeholder="Mis. Barang rusak / tidak sesuai pesanan"></div>
            </div>
            <div class="ui-table-wrap" tabindex="0" role="region" aria-label="Barang penerimaan yang dapat diretur">
                <table class="ui-table">
                    <thead><tr><th scope="col">Produk</th><th scope="col" class="ui-num">Diterima</th><th scope="col" class="ui-num">Sisa retur</th><th scope="col" class="ui-num">Stok gudang</th><th scope="col" class="ui-num">Jumlah retur</th></tr></thead>
                    <tbody id="purchase-return-rows"></tbody>
                </table>
            </div>
            <div class="row" id="purchase-return-refund-fields" style="margin-top:10px" hidden>
                <div class="field">
                    <label for="pr-method">Tujuan pengembalian dana supplier (bila melebihi sisa utang)</label>
                    <select id="pr-method" name="refund_method">
                        <option value="main">Kas Utama (1101)</option>
                        <option value="cash">Laci kas harian (shift aktif)</option>
                        <option value="transfer">Transfer bank</option>
                    </select>
                </div>
                <div class="field" id="pr-bank-wrap" hidden>
                    <label for="pr-bank">Rekening bank</label>
                    <select id="pr-bank" name="bank_account_code" disabled>
                        @foreach(($bankAccounts ?? collect([(object)['account_code' => '1102', 'account_name' => 'Rekening Bank']])) as $bankAcc)
                            <option value="{{ $bankAcc->account_code }}">{{ $bankAcc->account_code }} · {{ $bankAcc->account_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="note" id="purchase-return-note" style="margin-top:8px"></p>
            <p class="err" data-error role="alert" tabindex="-1" hidden></p>
            <div class="popup-submit" style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;padding-top:12px;border-top:1px solid var(--ui-line,#E4EBE6)">
                <button type="button" class="secondary" data-close-dialog>Batal</button>
                <button type="submit">Simpan retur pembelian</button>
            </div>
        </form>
    </div>
</dialog>
</section>

<script>
(() => {
    'use strict';
    const dialog = document.getElementById('purchase-return-dialog');
    const form = document.getElementById('purchase-return-form');
    const rowsBox = document.getElementById('purchase-return-rows');
    const subEl = document.getElementById('purchase-return-sub');
    const noteEl = document.getElementById('purchase-return-note');
    const refundWrap = document.getElementById('purchase-return-refund-fields');
    const methodSel = document.getElementById('pr-method');
    const bankWrap = document.getElementById('pr-bank-wrap');
    const bankSel = document.getElementById('pr-bank');
    if (!dialog || !form || !rowsBox) { return; }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const freshKey = () => (typeof window.crypto?.randomUUID === 'function') ? 'prt-' + window.crypto.randomUUID() : 'prt-' + Date.now() + '-' + Math.random().toString(16).slice(2);
    const fmtQty = v => Number(v).toLocaleString('id-ID', { maximumFractionDigits: 4 });
    const rupiah = v => 'Rp ' + Math.round(Number(v) || 0).toLocaleString('id-ID');
    const parseQty = value => {
        const raw = String(value ?? '').trim();
        if (!raw) { return { empty: true, raw: '', value: 0 }; }
        const normalized = raw.replace(/\.(?=\d{3}\b)/g, '').replace(',', '.');
        if (!/^\d+(\.\d+)?$/.test(normalized)) { return { empty: false, invalid: true, raw, value: NaN }; }
        const num = Number(normalized);
        return { empty: false, invalid: !Number.isFinite(num) || num < 0, raw: normalized, value: num };
    };

    function showError(message, focusTarget) {
        const err = form.querySelector('[data-error]');
        if (err) {
            err.textContent = message;
            err.hidden = false;
        }
        if (focusTarget && typeof focusTarget.focus === 'function') {
            focusTarget.focus();
        } else {
            err?.focus();
        }
    }

    function clearError() {
        const err = form.querySelector('[data-error]');
        if (err) {
            err.hidden = true;
            err.textContent = '';
        }
    }

    function ensureVisibleHost() {
        let node = dialog.parentElement;
        while (node && node !== document.body) {
            const style = typeof getComputedStyle === 'function' ? getComputedStyle(node) : null;
            if (node.id === 'admin' || node.hidden || (style && style.display === 'none')) {
                document.body.append(dialog);
                return;
            }
            node = node.parentElement;
        }
    }

    function syncBank() {
        const isBank = methodSel && methodSel.value === 'transfer' && !refundWrap.hidden;
        if (bankWrap) { bankWrap.hidden = !isBank; }
        if (bankSel) { bankSel.disabled = !isBank; }
    }
    methodSel?.addEventListener('change', syncBank);

    document.addEventListener('click', async e => {
        const btn = e.target.closest?.('[data-purchase-return]');
        if (!btn) { return; }
        e.preventDefault();
        if (form.dataset.busy) { return; }
        const url = btn.dataset.returnableUrl;
        const postUrl = btn.dataset.postUrl;
        if (!url || !postUrl) { return; }

        ensureVisibleHost();
        dialog._openerTrigger = btn.closest('td')?.querySelector('.ui-actions-toggle') || btn;
        form.action = postUrl;
        form.elements.idempotency_key.value = freshKey();
        form.elements.reason.value = '';
        form.elements.return_date.min = '';
        form.elements.return_date.value = form.elements.return_date.defaultValue || '';
        clearError();
        rowsBox.replaceChildren();
        if (subEl) { subEl.textContent = 'Memuat data penerimaan…'; }
        if (noteEl) { noteEl.textContent = ''; }
        if (refundWrap) { refundWrap.hidden = true; }
        syncBank();
        const submitBtn = form.querySelector('button[type=submit]');
        form.dataset.loading = '1';
        if (submitBtn) { submitBtn.disabled = true; }
        if (!dialog.open) { dialog.showModal(); }

        try {
            const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
            if (res.redirected) { throw new Error('Sesi berakhir. Muat ulang halaman lalu masuk kembali.'); }
            const data = await res.json().catch(() => ({}));
            if (!res.ok) { throw new Error(data.message || 'Gagal memuat rincian retur.'); }
            if (subEl) { subEl.textContent = (data.receipt_number || '') + ' · ' + (data.supplier_name || ''); }
            const receiptDate = data.receipt_date || '';
            form.elements.return_date.min = receiptDate;
            if (receiptDate && form.elements.return_date.value && form.elements.return_date.value < receiptDate) {
                form.elements.return_date.value = receiptDate;
                form.elements.return_date.dispatchEvent(new Event('change', { bubbles: true }));
            }
            const remainingPayable = Number(data.invoice_remaining || 0);
            const paidAmount = Number(data.invoice_paid || 0);
            if (refundWrap) { refundWrap.hidden = paidAmount <= 0; }
            syncBank();
            if (noteEl) {
                noteEl.textContent = data.has_pending_payment
                    ? 'Tagihan ini memiliki pengajuan pembayaran yang menunggu persetujuan. Selesaikan persetujuan terlebih dahulu.'
                    : ('Sisa utang supplier: ' + rupiah(remainingPayable) + (paidAmount > 0 ? ' (sudah dibayar ' + rupiah(paidAmount) + ').' : '. Retur memotong utang secara otomatis.'));
            }

            let firstQty = null;
            for (const item of data.items || []) {
                const tr = document.createElement('tr');
                const tdName = document.createElement('td'); tdName.textContent = item.name;
                const tdRec = document.createElement('td'); tdRec.className = 'ui-num'; tdRec.textContent = fmtQty(item.received_qty) + ' ' + item.unit;
                const tdRem = document.createElement('td'); tdRem.className = 'ui-num'; tdRem.textContent = fmtQty(item.remaining_qty) + ' ' + item.unit;
                const tdSt = document.createElement('td'); tdSt.className = 'ui-num'; tdSt.textContent = fmtQty(item.warehouse_stock) + ' ' + item.unit;
                const tdQty = document.createElement('td'); tdQty.className = 'ui-num';
                const input = document.createElement('input');
                input.type = 'text'; input.inputMode = 'decimal'; input.placeholder = '0';
                input.dataset.receiptItemId = String(item.receipt_item_id);
                input.setAttribute('aria-label', 'Jumlah retur ' + item.name);
                input.disabled = Number(item.max_returnable_qty || 0) <= 0 || Boolean(data.has_pending_payment);
                input.style.maxWidth = '110px'; input.style.textAlign = 'right';
                if (!input.disabled && !firstQty) { firstQty = input; }
                tdQty.append(input);
                tr.append(tdName, tdRec, tdRem, tdSt, tdQty);
                rowsBox.append(tr);
            }
            if (submitBtn) { submitBtn.disabled = Boolean(data.has_pending_payment) || !firstQty; }
            (firstQty || form.elements.reason)?.focus?.({ preventScroll: true });
        } catch (ex) {
            showError(ex?.message || 'Gagal memuat penerimaan.');
        } finally {
            delete form.dataset.loading;
            if (submitBtn && !rowsBox.querySelector('input[data-receipt-item-id]:not(:disabled)')) {
                submitBtn.disabled = true;
            }
        }
    });

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
        if (form.dataset.busy || form.dataset.loading) { return; }
        clearError();

        const dateInput = form.elements.return_date;
        const returnDate = String(dateInput?.value || '').trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(returnDate)) {
            showError('Tanggal retur wajib diisi.', dateInput?._dateTrigger || dateInput);
            return;
        }
        if (dateInput?.min && returnDate < dateInput.min) {
            showError('Tanggal retur tidak boleh sebelum tanggal penerimaan (' + dateInput.min + ').', dateInput?._dateTrigger || dateInput);
            return;
        }
        if (dateInput?.max && returnDate > dateInput.max) {
            showError('Tanggal retur tidak boleh melewati hari ini (' + dateInput.max + ').', dateInput?._dateTrigger || dateInput);
            return;
        }

        const items = [];
        let firstQty = null;
        for (const input of rowsBox.querySelectorAll('input[data-receipt-item-id]')) {
            if (!input.disabled && !firstQty) { firstQty = input; }
            const parsed = parseQty(input.value);
            if (parsed.empty) { continue; }
            if (parsed.invalid || parsed.value <= 0) {
                showError('Jumlah retur harus berupa angka lebih dari nol.', input);
                return;
            }
            items.push({ receipt_item_id: Number(input.dataset.receiptItemId), quantity: parsed.raw });
        }
        if (!items.length) {
            showError('Isi jumlah retur minimal untuk satu barang.', firstQty);
            return;
        }

        const reasonInput = form.elements.reason;
        const reason = String(reasonInput?.value || '').trim();
        if (reason.length < 5) {
            showError('Isi alasan retur minimal 5 karakter.', reasonInput);
            return;
        }

        if (!form.elements.idempotency_key.value) {
            form.elements.idempotency_key.value = freshKey();
        }

        form.dataset.busy = '1';
        form.setAttribute('aria-busy', 'true');
        const btn = form.querySelector('button[type=submit]');
        if (btn) { btn.disabled = true; }
        try {
            const payload = {
                return_date: returnDate,
                reason,
                idempotency_key: form.elements.idempotency_key.value,
                refund_method: refundWrap.hidden ? null : methodSel.value,
                bank_account_code: (!refundWrap.hidden && methodSel.value === 'transfer' && bankSel) ? bankSel.value : null,
                items
            };
            const res = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            });
            if (res.redirected) {
                throw new Error('Sesi berakhir. Muat ulang halaman lalu masuk kembali.');
            }
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = Object.values(data.errors || {}).flat().join(' ') || data.message || 'Gagal menyimpan retur pembelian.';
                showError(msg);
                return;
            }
            form.elements.idempotency_key.value = freshKey();
            dialog.close();
            try { await window.uiLive?.saved(form); } catch {}
        } catch (ex) {
            showError(ex?.message || 'Gagal menghubungi server. Periksa koneksi Anda lalu coba lagi.');
        } finally {
            if (btn) { btn.disabled = false; }
            delete form.dataset.busy;
            form.removeAttribute('aria-busy');
        }
    });
})();
</script>
