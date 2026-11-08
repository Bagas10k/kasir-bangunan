{{-- Scoped POS V2 Cashier Styles - Legacy Green-White Incumbent Alignment --}}
<style>
/* --- SCOPED POS CASHIER CONTAINER & THEME OVERRIDES --- */
#kasir {
    font-size: 14px;
    line-height: 1.45;
    color: var(--text-main, #1c2826);
    --pos-primary: var(--primary, #2d6a4f);
    --pos-primary-hover: var(--primary-hover, #1b4332);
    --pos-primary-light: var(--primary-light, #d8f3dc);
    --pos-mint-surface: var(--mint-surface, #e8f5e9);
    --pos-mint-border: var(--mint-border, #a3b18a);
    --pos-surface: var(--surface, #ffffff);
    --pos-border-card: var(--border-card, #e7eee9);
    --pos-border-control: var(--border-control, #d9e2dc);
    --pos-border-subtle: var(--border-subtle, #f0f4f1);
    --pos-text-main: var(--text-main, #1c2826);
    --pos-text-caption: var(--text-caption, #526359);
}

/* Card density override from modern 24px padding to legacy 18px */
#kasir .card,
#kasir .pos-catalog-card,
#kasir .pos-cart-pane section.card {
    padding: 18px;
    border-radius: 16px;
    background: var(--pos-surface);
    border: 1px solid var(--pos-border-card);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

/* --- 2-COLUMN WORKSPACE GRID --- */
#kasir .pos-workspace {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: start;
    gap: 18px;
    width: 100%;
}

#kasir .pos-catalog-pane,
#kasir .pos-cart-pane {
    min-width: 0;
    display: flex;
    flex-direction: column;
}

/* --- CATALOG PANE & SEARCH --- */
#kasir .pos-catalog-card {
    display: flex;
    flex-direction: column;
    margin: 0;
}

#kasir .pos-search-wrapper {
    position: relative;
    margin-bottom: 12px;
}

#kasir .pos-search-box {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #ffffff;
    border: 2px solid var(--pos-mint-border);
    border-radius: 12px;
    padding: 8px 14px;
    box-shadow: 0 3px 10px rgba(27, 67, 50, 0.06);
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

#kasir .pos-search-box:focus-within {
    border-color: var(--pos-primary);
    box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.15);
}

#kasir input#pos-product-search {
    flex: 1;
    min-width: 0;
    border: none;
    outline: none;
    font-size: 14.5px;
    font-weight: 500;
    color: var(--pos-text-main);
    background: transparent;
    font-family: inherit;
}

#kasir input#pos-product-search::placeholder {
    color: var(--pos-text-caption);
    font-size: 13.5px;
}

/* Catalog filter row */
#kasir .catalog-filter-row {
    display: grid;
    grid-template-columns: minmax(140px, 1.2fr) minmax(130px, 1fr) auto;
    align-items: end;
    gap: 10px;
    margin-bottom: 12px;
}

#kasir .catalog-filter-row .form-control,
#kasir select#product-category-filter,
#kasir select#product-stock-filter {
    width: 100%;
    min-height: 38px;
    box-sizing: border-box;
    border: 1px solid var(--pos-border-control);
    border-radius: 8px;
    padding: 6px 10px;
    font-size: 13px;
    background: #ffffff;
    color: var(--pos-text-main);
}

/* Horizontal scroll isolation for catalog table */
#kasir .catalog-table-scroll {
    max-height: 480px;
    overflow-y: auto;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border: 1px solid var(--pos-border-subtle);
    border-radius: 10px;
}









/* Pill buttons */
#kasir .btn-table-action,
#kasir .pos-catalog-pane .btn-table-action {
    background-color: var(--pos-mint-surface);
    color: var(--pos-primary-hover);
    border: 1px solid var(--pos-mint-border);
    border-radius: 9999px;
    padding: 4px 12px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.15s ease, border-color 0.15s ease;
    white-space: nowrap;
}

#kasir .btn-table-action:hover,
#kasir .btn-table-action:focus-visible {
    background-color: var(--pos-primary-light);
    border-color: var(--accent, #52b788);
    outline: none;
}

/* --- CART PANE & ITEMS --- */
#kasir .cart-table-scroll {
    max-height: 380px;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 4px;
}

#kasir #items {
    display: flex;
    flex-direction: column;
}

#kasir #items > .item-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px 10px;
    padding: 10px 0;
    border-bottom: 1px solid var(--pos-border-subtle);
}

#kasir #items > .item-row:last-child {
    border-bottom: none;
}

/* Product header full width on first line */
#kasir .item-row .cart-product-title,
#kasir .item-row .cart-product-heading {
    flex: 1 1 100%;
    min-width: 0;
}

#kasir .cart-product-title {
    font-size: 13.5px;
    font-weight: 700;
    color: var(--pos-text-main);
    line-height: 1.3;
    overflow-wrap: anywhere;
}

#kasir .cart-product-meta {
    font-size: 11px;
    color: var(--pos-text-caption);
    margin-top: 2px;
    display: flex;
    gap: 6px;
    align-items: center;
}

/* Controls on second row: Qty stepper (left), Subtotal (right), Delete (far right) */
#kasir .item-row .qty-stepper {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    flex: 0 0 auto;
}

#kasir .qty-stepper button[data-step] {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    min-width: 36px;
    min-height: 36px;
    border: 1px solid var(--pos-border-control);
    border-radius: 8px;
    background: var(--pos-surface);
    color: var(--pos-text-main);
    font-size: 18px;
    font-weight: 600;
    line-height: 1;
    cursor: pointer;
    user-select: none;
    -webkit-user-select: none;
    transition: background 0.12s ease, border-color 0.12s ease;
    padding: 0;
}

#kasir .qty-stepper button[data-step]:hover {
    background: var(--pos-mint-surface);
    border-color: var(--accent, #52b788);
}

#kasir .qty-stepper button[data-step]:active {
    background: var(--pos-primary-light);
    transform: scale(0.96);
}

#kasir .qty-stepper input[data-name="quantity"] {
    width: 44px;
    min-width: 40px;
    max-width: 56px;
    height: 36px;
    box-sizing: border-box;
    padding: 4px 2px;
    border: 1px solid var(--pos-border-control);
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    text-align: center;
    font-variant-numeric: tabular-nums;
    -moz-appearance: textfield;
}

#kasir .qty-stepper input[data-name="quantity"]::-webkit-inner-spin-button,
#kasir .qty-stepper input[data-name="quantity"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

#kasir .qty-stepper input[data-name="quantity"]:focus {
    border-color: var(--pos-primary);
    outline: none;
}

#kasir .item-row .item-subtotal {
    flex: 1 1 auto;
    text-align: right;
    font-size: 13.5px;
    font-weight: 700;
    color: var(--pos-text-main);
    font-variant-numeric: tabular-nums;
}

#kasir .item-row button[data-remove] {
    flex: 0 0 auto;
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    border-radius: 6px;
    width: 32px;
    height: 32px;
    min-width: 32px;
    min-height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.15s ease;
    padding: 0;
}

#kasir .item-row button[data-remove]:hover {
    background: #fca5a5;
}

/* Optional per-row discount details */
#kasir .item-row details.line-discount {
    flex: 1 1 100%;
    margin-top: 4px;
    font-size: 11.5px;
    color: var(--pos-text-caption);
}

#kasir .item-row details.line-discount summary {
    cursor: pointer;
    user-select: none;
    color: var(--pos-primary);
    font-weight: 600;
}

#kasir .item-row details.line-discount .discount-controls {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 6px;
}

#kasir .item-row details.line-discount select[data-name="discount_type"],
#kasir .item-row details.line-discount input[data-name="discount_value"] {
    height: 30px;
    border: 1px solid var(--pos-border-control);
    border-radius: 6px;
    font-size: 12px;
    padding: 2px 6px;
    background: #ffffff;
}

/* --- SUMMARY BOX & FOOTER ACTIONS --- */
#kasir .summary-box {
    background-color: var(--pos-mint-surface);
    border: 1px solid var(--pos-mint-border);
    border-radius: 16px;
    padding: 14px 16px;
    margin-top: 14px;
    flex: 0 0 auto;
}

#kasir .summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-size: 13.5px;
    font-variant-numeric: tabular-nums;
}

#kasir .summary-total {
    font-size: 15px;
    font-weight: 700;
    color: var(--pos-primary-hover);
    border-top: 2px solid var(--pos-primary);
    padding-top: 10px;
    margin-top: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

#kasir #display-total {
    font-size: 21px;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    color: var(--pos-primary-hover);
}

#kasir .pos-checkout-action {
    margin-top: 14px;
}

#kasir button#open-payment {
    width: 100%;
    min-height: 44px;
    background: var(--pos-primary);
    color: #ffffff;
    font-size: 15px;
    font-weight: 700;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    padding: 10px 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: background 0.15s ease, transform 0.05s ease;
}

#kasir button#open-payment:hover {
    background: var(--pos-primary-hover);
}

#kasir button#open-payment:active {
    transform: scale(0.99);
}

/* Tabular figures for all financial values */
#kasir [data-currency],
#kasir .item-subtotal,
#kasir #display-total,
#kasir .summary-row span:last-child {
    font-variant-numeric: tabular-nums;
}

/* --- RESPONSIVE BREAKPOINTS (<=900px stacked, <=640px touch safety, 390px mobile) --- */
@media (max-width: 900px) {
    #kasir .pos-workspace {
        grid-template-columns: 1fr;
        gap: 14px;
    }

    #kasir .catalog-filter-row {
        grid-template-columns: 1fr 1fr auto;
    }
}

@media (max-width: 640px) {
    #kasir input#pos-product-search {
        font-size: 16px; /* Prevents auto-zoom on iOS */
    }

    #kasir .catalog-filter-row {
        grid-template-columns: 1fr;
    }

    #kasir .qty-stepper button[data-step] {
        width: 40px;
        height: 40px;
        min-width: 40px;
        min-height: 40px;
    }

    #kasir .item-row button[data-remove] {
        width: 36px;
        height: 36px;
        min-width: 36px;
        min-height: 36px;
    }
}
/* Preserve legacy density without clipping controls in the new shell. */
#kasir .pos-catalog-pane,#kasir .pos-cart-pane,#kasir .pos-cart-pane .card{min-width:0}
#kasir .catalog-filter-row{grid-template-columns:repeat(2,minmax(0,1fr)) auto}
#kasir .catalog-filter-row>div{min-width:0}









#kasir .pos-catalog-pane .btn-table-action{padding-inline:8px;font-size:12px}



#kasir [hidden]{display:none!important}
#kasir .money{white-space:nowrap!important;text-align:right;font-variant-numeric:tabular-nums}
#kasir .cart-label{display:flex;justify-content:space-between;gap:8px;margin-bottom:12px}
#kasir .cart-label span{font-size:12px;color:#3c5545}
#kasir .cart-empty-message{padding:24px 0;line-height:1.6}
#kasir .item-row>.cart-product-heading{width:100%}
#kasir .qty-stepper input[data-name=quantity]{width:76px;max-width:76px;min-width:0;font-size:14px}
#kasir .qty-stepper{max-width:100%}
#kasir .item-row button[data-remove]{width:44px;height:44px;min-width:44px;min-height:44px;border-radius:999px;background:#fff;color:#8d2118}
#kasir button#open-payment{border-radius:999px;background:#14532d}
#kasir .btn-table-action{min-height:44px;color:#123c26}
#kasir input:focus-visible,#kasir select:focus-visible,#kasir button:focus-visible{outline:3px solid #14532d;outline-offset:2px}
#kasir .summary-total{border-top:0;padding-top:0;margin-top:0}
/* --- INFORMASI PEMBELI (KASIR & MODAL) --- */
#kasir .pos-buyer-card {
    background: #f8faf8;
    border: 1px solid var(--pos-border-control);
    border-radius: 12px;
    padding: 12px 14px;
    margin-bottom: 14px;
    transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
}
#kasir .pos-buyer-card:focus-within {
    border-color: var(--pos-primary);
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.12);
}
#kasir .pos-buyer-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}
#kasir .pos-buyer-title {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 700;
    color: var(--pos-text-main);
    letter-spacing: -0.01em;
}
#kasir .pos-buyer-title svg {
    color: var(--pos-primary);
    flex-shrink: 0;
}
#kasir .pos-buyer-badge {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    padding: 3px 8px;
    border-radius: 999px;
    line-height: 1.2;
}
#kasir .pos-buyer-badge.badge-general {
    background: #e9ecef;
    color: #495057;
    border: 1px solid #dee2e6;
}
#kasir .pos-buyer-badge.badge-registered {
    background: var(--pos-mint-surface);
    color: var(--pos-primary);
    border: 1px solid var(--pos-mint-border);
}
#kasir select.pos-customer-select {
    width: 100%;
    min-height: 42px;
    padding: 8px 12px;
    font-size: 13.5px;
    font-family: inherit;
    font-weight: 500;
    color: var(--pos-text-main);
    background: #ffffff;
    border: 1px solid var(--pos-border-control);
    border-radius: 8px;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
#kasir select.pos-customer-select:focus {
    outline: none;
    border-color: var(--pos-primary);
    box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.2);
}
#kasir .pos-buyer-meta {
    margin-top: 6px;
    font-size: 12px;
    line-height: 1.4;
    color: var(--pos-text-caption);
}
#kasir .pos-req-indicator {
    color: #c92a2a;
    font-weight: 700;
    font-size: 11.5px;
    margin-left: 4px;
}
#kasir .modal-buyer-block {
    background: #f8faf8;
    border: 1px solid var(--pos-border-control);
    border-radius: 12px;
    padding: 12px 14px;
    margin-bottom: 14px;
}
#kasir .pos-credit-warning-box {
    margin-top: 8px;
    padding: 8px 10px;
    background: #fff5f5;
    border: 1px solid #ffc9c9;
    border-radius: 6px;
    color: #c92a2a;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.35;
}
#kasir .input-dialog{width:min(560px,calc(100vw - 24px));max-width:min(560px,calc(100vw - 24px))}
#kasir .input-dialog .summary-box{margin:0 0 18px;padding:14px}
#kasir .input-dialog .row{margin-top:12px}
#kasir .input-dialog .summary-row{margin-top:16px}
#kasir .input-dialog label{font-size:13px}
#kasir .checkout-options{margin-top:18px}
#kasir .checkout-options>summary{min-height:44px;cursor:pointer;color:#14532d;font-weight:650}
#kasir .review-line{display:flex;justify-content:space-between;gap:16px;font-variant-numeric:tabular-nums}
@media(max-width:700px){
 #kasir .input-dialog .row>.field{flex:1 1 100%}
}
@media(max-width:700px){#kasir .catalog-filter-row{grid-template-columns:repeat(2,minmax(0,1fr))}#kasir .catalog-filter-row>button{grid-column:1/-1}#kasir .catalog-table-scroll{max-height:280px}#kasir .search-kbd-hint{display:none}#kasir .cart-table-scroll{max-height:260px}#kasir .item-row .item-subtotal{font-size:12px}#kasir .qty-stepper input[data-name=quantity]{width:64px;max-width:64px}}
</style>
