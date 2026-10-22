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

    /* ─── HERO GREETING BANNER (Like Hireism "Good Morning Sara") ─── */
    .hero-banner {
        background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 65%, #40916c 100%);
        border-radius: 20px;
        padding: 26px 30px;
        color: #ffffff;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        box-shadow: 0 10px 25px -5px rgba(27, 67, 50, 0.25);
        position: relative;
        overflow: hidden;
    }

    .hero-banner::after {
        content: "";
        position: absolute;
        right: -30px;
        bottom: -40px;
        width: 180px;
        height: 180px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.06);
        pointer-events: none;
    }

    .hero-content {
        max-width: 620px;
        z-index: 1;
    }

    .hero-badge-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(255, 255, 255, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.25);
        padding: 4px 12px;
        border-radius: 9999px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin-bottom: 10px;
    }

    .hero-title {
        font-size: 22px;
        font-weight: 800;
        letter-spacing: -0.02em;
        margin: 0 0 6px 0;
        line-height: 1.25;
    }

    .hero-subtitle {
        font-size: 13.5px;
        color: #d8f3dc;
        line-height: 1.5;
        margin: 0;
    }

    .hero-actions {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
        z-index: 1;
        flex-shrink: 0;
    }

    .hero-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(0, 0, 0, 0.18);
        border: 1px solid rgba(255, 255, 255, 0.18);
        padding: 5px 12px;
        border-radius: 9999px;
        font-size: 11.5px;
        font-weight: 600;
        color: #ffffff;
    }

    /* ─── QUICK CATEGORIES ROW (Like Hireism "You Need to Hire") ─── */
    .quick-categories-section {
        margin-bottom: 24px;
    }

    .section-headline-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 14px;
    }

    .section-headline {
        font-size: 14px;
        font-weight: 700;
        color: var(--primary-hover);
        letter-spacing: -0.01em;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .section-headline::before {
        content: "";
        width: 4px;
        height: 14px;
        background-color: var(--accent);
        border-radius: 2px;
        display: inline-block;
    }

    .quick-categories-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 14px;
    }

    .category-chip-card {
        background: #ffffff;
        border: 1px solid var(--border-card);
        border-radius: 16px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        transition: all 0.15s ease;
        box-shadow: 0 4px 14px rgba(45, 106, 79, 0.04);
        text-align: left;
    }

    .category-chip-card:hover {
        border-color: var(--accent);
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(45, 106, 79, 0.08);
    }

    .category-icon-box {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background-color: var(--mint-surface);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .category-meta {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .category-name {
        font-size: 13px;
        font-weight: 700;
        color: var(--text-main);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .category-desc {
        font-size: 11px;
        color: var(--text-caption);
    }

    /* ─── SUMMARY BOX & INPUTS ─── */
    .summary-box {
        background-color: var(--mint-surface);
        border: 1px solid var(--mint-border);
        border-radius: 16px;
        padding: 18px;
        margin-top: 18px;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        font-size: 13.5px;
    }

    .summary-total {
        font-size: 15px;
        font-weight: 700;
        color: var(--primary-hover);
        border-top: 2px solid var(--primary);
        padding-top: 10px;
        margin-top: 10px;
    }

    #display-total {
        font-size: 24px;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        color: var(--primary-hover);
    }

    #display-change {
        font-size: 16px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    #cart-table th, #products-table th, .card table th {
        font-size: 11.5px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    #cart-table td, #products-table td, .card table td {
        font-size: 13.5px;
    }

    .item-price, .item-subtotal, td.item-price, td.item-subtotal {
        font-variant-numeric: tabular-nums;
        text-align: right;
    }

    .sku-code {
        font-family: var(--font-mono, 'JetBrains Mono', monospace);
        font-size: 12px;
        font-weight: 600;
    }

    .btn-pill-submit {
        width: 100%;
        min-height: 48px;
        background-color: var(--primary);
        color: #ffffff;
        border: 1px solid var(--primary);
        border-radius: 9999px;
        font-family: inherit;
        font-size: 14.5px;
        font-weight: 700;
        cursor: pointer;
        padding: 12px 24px;
        box-shadow: 0 4px 14px rgba(45, 106, 79, 0.25);
        transition: all 0.15s ease;
    }

    .btn-pill-submit:hover {
        background-color: var(--primary-hover);
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(45, 106, 79, 0.3);
    }

    .btn-table-action {
        background-color: var(--mint-surface);
        color: var(--primary-hover);
        border: 1px solid var(--mint-border);
        border-radius: 9999px;
        padding: 4px 14px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .btn-table-action:hover {
        background-color: var(--primary-light);
        border-color: var(--accent);
    }

    @media (max-width: 1024px) {
        .quick-categories-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 768px) {
        .hero-banner {
            flex-direction: column;
            align-items: flex-start;
            padding: 20px;
        }
        .hero-actions {
            align-items: flex-start;
        }
        .quick-categories-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<!-- 1. Hero Greeting Banner (Like Hireism "Good Morning Sara") -->
<div class="hero-banner">
    <div class="hero-content">
        <span class="hero-badge-pill">Terminal Kasir Aktif &bull; Loket 01</span>
        <h1 class="hero-title">Kasir Bahan Bangunan</h1>
        <p class="hero-subtitle">Pencatatan kasir, kalkulasi muatan armada, dan persediaan material bangunan tersinkronisasi otomatis dalam buku besar akuntansi.</p>
    </div>
    <div class="hero-actions">
        <span class="hero-chip"><span class="status-dot"></span> Shift Pagi Aktif</span>
        <span class="hero-chip">Laci Kasir Seimbang</span>
    </div>
</div>

<!-- 2. Quick Material Categories (Like Hireism "You Need to Hire") -->
<div class="quick-categories-section">
    <div class="section-headline-row">
        <div class="section-headline">Kategori Cepat Material</div>
        <span class="badge">Siap Transaksi</span>
    </div>
    <div class="quick-categories-grid">
        <div class="category-chip-card" onclick="filterQuick('semen')">
            <div class="category-icon-box">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path>
                    <path d="m3.3 7 8.7 5 8.7-5"></path>
                    <path d="M12 22V12"></path>
                </svg>
            </div>
            <div class="category-meta">
                <span class="category-name">Semen &amp; Mortar</span>
                <span class="category-desc">Sak &bull; Zak</span>
            </div>
        </div>

        <div class="category-chip-card" onclick="filterQuick('besi')">
            <div class="category-icon-box">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="4" y1="21" x2="4" y2="14"></line>
                    <line x1="4" y1="10" x2="4" y2="3"></line>
                    <line x1="12" y1="21" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12" y2="3"></line>
                    <line x1="20" y1="21" x2="20" y2="16"></line>
                    <line x1="20" y1="12" x2="20" y2="3"></line>
                </svg>
            </div>
            <div class="category-meta">
                <span class="category-name">Besi &amp; Baja</span>
                <span class="category-desc">Batang &bull; SNI</span>
            </div>
        </div>

        <div class="category-chip-card" onclick="filterQuick('pasir')">
            <div class="category-icon-box">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2 2 7l10 5 10-5-10-5Z"></path>
                    <path d="m2 17 10 5 10-5"></path>
                    <path d="m2 12 10 5 10-5"></path>
                </svg>
            </div>
            <div class="category-meta">
                <span class="category-name">Pasir &amp; Agregat</span>
                <span class="category-desc">Kubik &bull; Truk</span>
            </div>
        </div>

        <div class="category-chip-card" onclick="filterQuick('hebel')">
            <div class="category-icon-box">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="3" y1="9" x2="21" y2="9"></line>
                    <line x1="3" y1="15" x2="21" y2="15"></line>
                    <line x1="9" y1="3" x2="9" y2="21"></line>
                    <line x1="15" y1="3" x2="15" y2="21"></line>
                </svg>
            </div>
            <div class="category-meta">
                <span class="category-name">Bata &amp; Hebel</span>
                <span class="category-desc">AAC &bull; Kubik</span>
            </div>
        </div>

        <div class="category-chip-card" onclick="filterQuick('')">
            <div class="category-icon-box">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
            <div class="category-meta">
                <span class="category-name">Semua Material</span>
                <span class="category-desc">Lihat Semua</span>
            </div>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-error">{{ session('error') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-error">
        <strong>Terjadi kesalahan input:</strong>
        <ul style="margin: 6px 0 0 18px; padding: 0;">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<!-- 3. Work Area: POS Cashier Form (Left) & Product Catalog / Invoices (Right) -->
<div class="grid-2">
    <div>
        <div class="card">
            <h2>Kasir Transaksi Baru</h2>
            <form action="{{ route('pos.checkout') }}" method="POST" id="checkout-form">
                @csrf
                <div class="grid-2" style="margin-bottom: 14px;">
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
                                <th style="min-width: 200px;">Produk</th>
                                <th style="width: 85px;">Qty</th>
                                <th style="width: 105px; text-align: right;">Harga</th>
                                <th style="width: 115px; text-align: right;">Subtotal</th>
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
                                    <button type="button" class="btn btn-secondary" style="min-height: 28px; padding: 2px 8px; font-size: 11px;" onclick="removeRow(this)">x</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div style="margin-top: 10px;">
                        <button type="button" class="btn btn-secondary" onclick="addRow()">+ Tambah Baris</button>
                    </div>
                </div>

                <div class="summary-box">
                    <div class="summary-row summary-total">
                        <span>Total Tagihan:</span>
                        <span id="display-total">Rp 0.00</span>
                    </div>
                    <div class="form-group" style="margin-top: 12px; margin-bottom: 8px;">
                        <label for="paid_amount">Nominal Pembayaran (Rp)</label>
                        <input type="number" step="0.01" min="0" name="paid_amount" id="paid_amount" value="{{ old('paid_amount', '0') }}" class="form-control tabular-nums" required oninput="calculateChange()">
                    </div>
                    <div class="summary-row" id="change-row">
                        <span>Kembalian:</span>
                        <span id="display-change" style="font-weight: bold;">Rp 0.00</span>
                    </div>
                </div>

                <div style="margin-top: 18px;">
                    <button type="submit" class="btn-pill-submit">Proses Checkout (Simpan)</button>
                </div>
            </form>
        </div>
    </div>

    <div>
        <div class="card">
            <h2>Katalog Produk Material</h2>
            <div style="margin-bottom: 12px;">
                <input type="text" id="filter-product" placeholder="Cari SKU atau nama material..." class="form-control" onkeyup="filterProducts()">
            </div>
            <div style="max-height: 300px; overflow-y: auto;">
                <table id="products-table">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Nama</th>
                            <th>Stok</th>
                            <th style="text-align: right; padding-right: 18px;">Harga</th>
                            <th style="width: 55px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $p)
                        <tr class="product-item-row">
                            <td><span class="sku-code">{{ $p->sku }}</span></td>
                            <td>{{ $p->name }}</td>
                            <td>{{ $p->stock_quantity }} {{ $p->unit }}</td>
                            <td style="text-align: right; font-variant-numeric: tabular-nums; padding-right: 18px; font-weight: 600;">Rp {{ number_format($p->selling_price, 2) }}</td>
                            <td style="text-align: center;">
                                <button type="button" class="btn-table-action" onclick="addToCart({{ $p->id }})">Pilih</button>
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
                        <th style="text-align: right;">Total</th>
                        <th>Struk</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentSales as $s)
                    <tr>
                        <td><span class="sku-code">{{ $s->invoice_number }}</span></td>
                        <td>{{ strtoupper($s->payment_method) }} ({{ strtoupper($s->customer_type) }})</td>
                        <td style="text-align: right; font-variant-numeric: tabular-nums;">Rp {{ number_format($s->total_amount, 2) }}</td>
                        <td>
                            <a href="{{ route('pos.receipt', $s->id) }}" class="btn-table-action" style="text-decoration: none; display: inline-block;">Lihat Struk</a>
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
            <button type="button" class="btn btn-secondary" style="min-height: 28px; padding: 2px 8px; font-size: 11px;" onclick="removeRow(this)">x</button>
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

function filterQuick(keyword) {
    const filterInput = document.getElementById('filter-product');
    filterInput.value = keyword;
    filterProducts();
}

document.addEventListener('DOMContentLoaded', () => {
    calculateCart();
});
</script>
@endsection
