@extends('layouts.app')
@section('content')
<style>
    @media (prefers-reduced-motion: reduce) {
        *, ::before, ::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
            scroll-behavior: auto !important;
        }
    }
    input:focus-visible, select:focus-visible, textarea:focus-visible, button:focus-visible, a:focus-visible {
        outline: 2px solid #2d6a4f;
        outline-offset: 2px;
    }
    .alert-error {
        background: #fde8e8;
        border: 1px solid #f5c2c7;
        color: #842029;
        padding: 10px 14px;
        margin-bottom: 16px;
        font-size: 13px;
        border-radius: 6px;
    }
    .alert-success {
        background: #e8f5e9;
        border: 1px solid #badbcc;
        color: #0f5132;
        padding: 10px 14px;
        margin-bottom: 16px;
        font-size: 13px;
        border-radius: 6px;
    }
    .form-group {
        margin-bottom: 12px;
    }
    .form-group label {
        display: block;
        margin-bottom: 4px;
        font-weight: bold;
        font-size: 12px;
        text-transform: uppercase;
        color: #2d6a4f;
    }
    .form-control {
        width: 100%;
        box-sizing: border-box;
    }
    .btn {
        background: #2d6a4f;
        color: #ffffff;
        border: 1px solid #2d6a4f;
        border-radius: 6px;
    }
    .btn:hover {
        background: #1b4332;
        border-color: #1b4332;
    }
    .btn-secondary {
        background: #e8f5e9;
        color: #1b4332;
        border: 1px solid #cbe3d3;
        border-radius: 6px;
    }
    .btn-secondary:hover {
        background: #d8f3dc;
        color: #1b4332;
    }
    .summary-box {
        background: #f0f7f3;
        border: 1px solid #d8f3dc;
        border-radius: 6px;
        padding: 14px;
        margin-top: 14px;
    }
    .summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 6px;
        font-size: 13px;
    }
    .summary-total {
        font-size: 16px;
        font-weight: bold;
        color: #1b4332;
        border-top: 2px solid #2d6a4f;
        padding-top: 8px;
        margin-top: 8px;
    }
</style>

@if(session('success'))
    <div class="alert-success" role="status">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert-error" role="alert">{{ session('error') }}</div>
@endif

@if($errors->any())
    <div class="alert-error" role="alert">
        <strong>Terjadi kesalahan input:</strong>
        <ul style="margin: 6px 0 0 18px; padding: 0;">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid-2">
    <div>
        <div class="card">
            <h2>Kasir Transaksi Baru</h2>
            <form action="{{ route('pos.checkout') }}" method="POST" id="checkout-form">
                @csrf
                <div class="grid-2" style="margin-bottom: 12px;">
                    <div class="form-group">
                        <label for="customer_type">Tipe Pelanggan</label>
                        <select name="customer_type" id="customer_type" class="form-control">
                            <option value="retail" {{ old('customer_type') === 'retail' ? 'selected' : '' }}>Retail</option>
                            <option value="contractor" {{ old('customer_type') === 'contractor' ? 'selected' : '' }}>Kontraktor</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="payment_method">Metode Pembayaran</label>
                        <select name="payment_method" id="payment_method" class="form-control" onchange="syncPaymentMethod()">
                            <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Tunai (Cash)</option>
                            <option value="transfer" {{ old('payment_method') === 'transfer' ? 'selected' : '' }}>Transfer Bank</option>
                            <option value="credit" {{ old('payment_method') === 'credit' ? 'selected' : '' }}>Kredit (Piutang Usaha)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Item Penjualan</label>
                    <table id="cart-table">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th style="width: 80px;">Qty</th>
                                <th style="width: 100px;">Harga</th>
                                <th style="width: 110px;">Subtotal</th>
                                <th style="width: 40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="cart-tbody">
                            <tr class="cart-row" data-index="0">
                                <td>
                                    <select name="items[0][product_id]" class="form-control item-product" required onchange="calculateCart()">
                                        <option value="">-- Pilih Produk --</option>
                                        @foreach($products as $p)
                                            <option value="{{ $p->id }}" data-price="{{ $p->selling_price }}" data-stock="{{ $p->stock_quantity }}" data-unit="{{ $p->unit }}">
                                                {{ $p->name }} [Stok: {{ $p->stock_quantity }} {{ $p->unit }}]
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.0001" min="0.0001" name="items[0][quantity]" value="1" class="form-control item-qty" required oninput="calculateCart()">
                                </td>
                                <td class="item-price" style="text-align: right; vertical-align: middle;">Rp 0</td>
                                <td class="item-subtotal" style="text-align: right; vertical-align: middle; font-weight: bold;">Rp 0</td>
                                <td style="text-align: center; vertical-align: middle;">
                                    <button type="button" class="btn btn-secondary" style="padding: 2px 6px;" onclick="removeRow(this)">x</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div style="margin-top: 8px;">
                        <button type="button" class="btn btn-secondary" onclick="addRow()">+ Tambah Baris</button>
                    </div>
                </div>

                <div class="summary-box">
                    <div class="summary-row summary-total">
                        <span>Total Tagihan:</span>
                        <span id="display-total">Rp 0.00</span>
                    </div>
                    <div class="form-group" style="margin-top: 10px;">
                        <label for="paid_amount">Nominal Pembayaran (Rp)</label>
                        <input type="number" step="0.01" min="0" name="paid_amount" id="paid_amount" value="{{ old('paid_amount', '0') }}" class="form-control" required oninput="calculateChange()">
                    </div>
                    <div class="summary-row" id="change-row">
                        <span>Kembalian:</span>
                        <span id="display-change" style="font-weight: bold;">Rp 0.00</span>
                    </div>
                </div>

                <div style="margin-top: 16px;">
                    <button type="submit" class="btn" style="width: 100%; padding: 10px; font-weight: bold;">Proses Checkout (Simpan)</button>
                </div>
            </form>
        </div>
    </div>

    <div>
        <div class="card">
            <h2>Katalog Produk Material</h2>
            <div style="margin-bottom: 10px;">
                <input type="text" id="filter-product" placeholder="Cari SKU atau nama..." class="form-control" onkeyup="filterProducts()">
            </div>
            <div style="max-height: 280px; overflow-y: auto;">
                <table id="products-table">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Nama</th>
                            <th>Stok</th>
                            <th>Harga</th>
                            <th style="width: 50px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $p)
                        <tr class="product-item-row">
                            <td>{{ $p->sku }}</td>
                            <td>{{ $p->name }}</td>
                            <td>{{ $p->stock_quantity }} {{ $p->unit }}</td>
                            <td>Rp {{ number_format($p->selling_price, 2) }}</td>
                            <td>
                                <button type="button" class="btn btn-secondary" style="padding: 2px 6px; font-size: 11px;" onclick="addToCart({{ $p->id }})">Pilih</button>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5">Belum ada data produk material.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>Transaksi Terakhir</h2>
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Metode</th>
                        <th>Total</th>
                        <th>Struk</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentSales as $s)
                    <tr>
                        <td><strong>{{ $s->invoice_number }}</strong></td>
                        <td>{{ strtoupper($s->payment_method) }} ({{ strtoupper($s->customer_type) }})</td>
                        <td>Rp {{ number_format($s->total_amount, 2) }}</td>
                        <td>
                            <a href="{{ route('pos.receipt', $s->id) }}" class="btn btn-secondary" style="padding: 2px 8px; text-decoration: none; display: inline-block;">Lihat Struk</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4">Belum ada transaksi recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let rowIndex = 1;

function addRow() {
    const tbody = document.getElementById('cart-tbody');
    const firstSelect = tbody.querySelector('.item-product');
    const optionsHtml = firstSelect ? firstSelect.innerHTML : '';
    
    const tr = document.createElement('tr');
    tr.className = 'cart-row';
    tr.dataset.index = rowIndex;
    tr.innerHTML = `
        <td>
            <select name="items[${rowIndex}][product_id]" class="form-control item-product" required onchange="calculateCart()">
                ${optionsHtml}
            </select>
        </td>
        <td>
            <input type="number" step="0.0001" min="0.0001" name="items[${rowIndex}][quantity]" value="1" class="form-control item-qty" required oninput="calculateCart()">
        </td>
        <td class="item-price" style="text-align: right; vertical-align: middle;">Rp 0</td>
        <td class="item-subtotal" style="text-align: right; vertical-align: middle; font-weight: bold;">Rp 0</td>
        <td style="text-align: center; vertical-align: middle;">
            <button type="button" class="btn btn-secondary" style="padding: 2px 6px;" onclick="removeRow(this)">x</button>
        </td>
    `;
    tbody.appendChild(tr);
    rowIndex++;
}

function removeRow(btn) {
    const rows = document.querySelectorAll('.cart-row');
    if (rows.length > 1) {
        btn.closest('tr').remove();
        calculateCart();
    }
}

function addToCart(productId) {
    const rows = document.querySelectorAll('.cart-row');
    let emptyRow = null;
    for (let r of rows) {
        const sel = r.querySelector('.item-product');
        if (sel && (!sel.value || parseInt(sel.value) === productId)) {
            emptyRow = r;
            break;
        }
    }
    if (emptyRow) {
        const sel = emptyRow.querySelector('.item-product');
        const qty = emptyRow.querySelector('.item-qty');
        if (sel.value && parseInt(sel.value) === productId) {
            qty.value = parseFloat(qty.value || 0) + 1;
        } else {
            sel.value = productId;
            qty.value = 1;
        }
    } else {
        addRow();
        const allRows = document.querySelectorAll('.cart-row');
        const lastRow = allRows[allRows.length - 1];
        lastRow.querySelector('.item-product').value = productId;
    }
    calculateCart();
}

function calculateCart() {
    const rows = document.querySelectorAll('.cart-row');
    let grandTotal = 0;
    rows.forEach(r => {
        const select = r.querySelector('.item-product');
        const qtyInput = r.querySelector('.item-qty');
        const priceCell = r.querySelector('.item-price');
        const subtotalCell = r.querySelector('.item-subtotal');
        
        let unitPrice = 0;
        if (select && select.selectedIndex >= 0) {
            const opt = select.options[select.selectedIndex];
            unitPrice = parseFloat(opt.getAttribute('data-price') || 0);
        }
        const qty = parseFloat(qtyInput ? qtyInput.value : 0) || 0;
        const subtotal = unitPrice * qty;
        grandTotal += subtotal;
        
        if (priceCell) priceCell.textContent = 'Rp ' + unitPrice.toLocaleString('id-ID', { minimumFractionDigits: 2 });
        if (subtotalCell) subtotalCell.textContent = 'Rp ' + subtotal.toLocaleString('id-ID', { minimumFractionDigits: 2 });
    });
    
    document.getElementById('display-total').textContent = 'Rp ' + grandTotal.toLocaleString('id-ID', { minimumFractionDigits: 2 });
    
    const method = document.getElementById('payment_method').value;
    const paidInput = document.getElementById('paid_amount');
    if (method !== 'cash' && (!paidInput.value || parseFloat(paidInput.value) === 0)) {
        paidInput.value = (method === 'credit') ? 0 : grandTotal.toFixed(2);
    }
    calculateChange();
}

function syncPaymentMethod() {
    const method = document.getElementById('payment_method').value;
    const paidInput = document.getElementById('paid_amount');
    const totalText = document.getElementById('display-total').textContent.replace(/[^0-9,-]/g, '').replace(',', '.');
    const total = parseFloat(totalText) || 0;
    
    if (method === 'credit') {
        paidInput.value = 0;
    } else if (method === 'transfer') {
        paidInput.value = total;
    }
    calculateChange();
}

function calculateChange() {
    const totalText = document.getElementById('display-total').textContent.replace(/[^0-9,-]/g, '').replace(',', '.');
    const total = parseFloat(totalText) || 0;
    const paid = parseFloat(document.getElementById('paid_amount').value) || 0;
    const change = Math.max(0, paid - total);
    document.getElementById('display-change').textContent = 'Rp ' + change.toLocaleString('id-ID', { minimumFractionDigits: 2 });
}

function filterProducts() {
    const filter = document.getElementById('filter-product').value.toLowerCase();
    const rows = document.querySelectorAll('.product-item-row');
    rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        r.style.display = text.includes(filter) ? '' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', () => {
    calculateCart();
});
</script>
@endsection
