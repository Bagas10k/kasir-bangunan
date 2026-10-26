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

    #armada-fields[hidden] { display: none; }
    /* --- PRIMARY PRODUCT QUICK-SEARCH BAR --- */
    .pos-search-wrapper {
        position: relative;
        margin-bottom: 18px;
    }

    .pos-search-box {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #ffffff;
        border: 2px solid var(--mint-border, #a3b18a);
        border-radius: 12px;
        padding: 8px 14px;
        box-shadow: 0 3px 10px rgba(27, 67, 50, 0.06);
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .pos-search-box:focus-within {
        border-color: var(--primary, #2d6a4f);
        box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.15);
    }

    .pos-search-icon {
        color: var(--primary, #2d6a4f);
        flex-shrink: 0;
    }

    .pos-search-input {
        flex: 1;
        min-width: 0;
        border: none;
        outline: none;
        font-size: 14.5px;
        font-weight: 500;
        color: var(--text-main, #1f2937);
        background: transparent;
        font-family: inherit;
    }

    .pos-search-input::placeholder {
        color: var(--text-muted);
        font-size: 13.5px;
    }

    .search-kbd-hint {
        font-size: 11px;
        font-weight: 600;
        color: var(--text-caption, #6b7280);
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 3px 8px;
        white-space: nowrap;
        flex-shrink: 0;
    }

    /* Dropdown suggestions */
    .search-dropdown {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12);
        max-height: 320px;
        overflow-y: auto;
        z-index: 50;
        display: none;
        list-style: none;
        margin: 0;
        padding: 6px;
    }

    .search-dropdown.active {
        display: block;
        animation: dropdownIn .26s cubic-bezier(.34, 1.56, .64, 1);
    }

    .search-dropdown-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        border-radius: 8px;
        cursor: pointer;
        transition: background-color 0.1s ease;
        gap: 12px;
    }

    .search-dropdown-item:hover, .search-dropdown-item.focused {
        background-color: var(--mint-surface, #e8f5e9);
    }

    .search-item-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }

    .search-item-title {
        font-size: 13.5px;
        font-weight: 700;
        color: var(--text-main, #1f2937);
    }

    .search-item-meta {
        font-size: 11.5px;
        color: var(--text-caption, #6b7280);
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .search-item-price {
        font-size: 14px;
        font-weight: 700;
        color: var(--primary-hover, #1b4332);
        font-variant-numeric: tabular-nums;
        text-align: right;
        flex-shrink: 0;
    }

    .search-dropdown-empty {
        padding: 16px;
        text-align: center;
        font-size: 13px;
        color: var(--text-caption, #6b7280);
    }

    /* --- Loading states (lazy-load animation) --- */
    .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(45, 106, 79, .22); border-top-color: var(--primary, #2d6a4f); border-radius: 50%; animation: spinIn .7s linear infinite; flex-shrink: 0; }
    @keyframes spinIn { to { transform: rotate(360deg); } }
    .search-dropdown-loading { padding: 14px; display: flex; align-items: center; gap: 9px; justify-content: center; font-size: 12.5px; color: var(--text-caption, #6b7280); }
    .receipt-loading { padding: 18px; display: flex; align-items: center; gap: 10px; justify-content: center; color: var(--text-caption, #6b7280); font-size: 13.5px; }
    .skeleton { position: relative; overflow: hidden; background: #dfe7e1; border-radius: 8px; }
    .skeleton::after { content: ""; position: absolute; inset: 0; transform: translateX(-100%); background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .85), transparent); animation: shimmerIn 1.4s infinite; }
    @keyframes shimmerIn { 100% { transform: translateX(100%); } }
    @media (prefers-reduced-motion: reduce) { .spinner, .skeleton::after { animation-duration: .01ms !important; } }
    .sales-skeleton { display: flex; flex-direction: column; gap: 14px; padding: 6px 2px 10px; }

    /* --- CART TABLE --- */
    #cart-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-top: 8px;
    }

    #cart-table th {
        font-size: 11.5px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--text-caption, #6b7280);
        padding: 8px 10px;
        border-bottom: 2px solid var(--border-card, #e5e7eb);
        text-align: left;
    }

    #cart-table td {
        padding: 10px;
        border-bottom: 1px solid var(--border-card, #e5e7eb);
        font-size: 13.5px;
        vertical-align: middle;
    }

    .cart-product-title {
        font-size: 13.5px;
        font-weight: 700;
        color: var(--text-main, #1f2937);
        line-height: 1.3;
    }

    .cart-product-meta {
        font-size: 11px;
        color: var(--text-caption, #6b7280);
        margin-top: 2px;
        display: flex;
        gap: 6px;
        align-items: center;
    }

    .item-qty {
        width: 100%;
        min-width: 44px;
        max-width: 64px;
        padding: 6px 4px;
        border: 1px solid var(--border-control, #d9e2dc);
        border-radius: 6px;
        font-size: 13.5px;
        font-weight: 600;
        text-align: center;
        font-variant-numeric: tabular-nums;
        -moz-appearance: textfield;
    }
    .item-qty::-webkit-inner-spin-button,
    .item-qty::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .item-qty:focus {
        border-color: var(--primary, #2d6a4f);
        outline: none;
    }

    .qty-stepper {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .qty-stepper .qty-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        min-width: 36px;
        min-height: 36px;
        border: 1px solid var(--border-control, #d9e2dc);
        border-radius: 8px;
        background: var(--surface, #ffffff);
        color: var(--text-main, #1c2826);
        font-size: 18px;
        font-weight: 600;
        line-height: 1;
        cursor: pointer;
        user-select: none;
        -webkit-user-select: none;
        transition: background .12s ease, border-color .12s ease;
        padding: 0;
    }
    .qty-stepper .qty-btn:hover {
        background: var(--mint-surface, #e8f5e9);
        border-color: var(--accent, #52b788);
    }
    .qty-stepper .qty-btn:active {
        background: var(--primary-light, #d8f3dc);
        transform: scale(.95);
    }
    /* --- Cart: always card layout (works in narrow POS panel + mobile) --- */
    #cart-table:not(:has(#cart-empty-row)) { min-width: 0 !important; }
    #cart-table thead { display: none; }
    #cart-table .cart-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px 10px;
        padding: 10px 0;
        border-bottom: 1px solid var(--border-subtle, #f0f4f1);
    }
    #cart-table .cart-row td {
        display: block;
        padding: 0;
        border: none;
    }
    /* Product name: full width top row */
    #cart-table .cart-row td:nth-child(1) {
        flex: 1 1 100%;
        min-width: 0;
    }
    /* Qty stepper: left on second row */
    #cart-table .cart-row td:nth-child(2) {
        flex: 0 0 auto;
    }
    /* Hide unit price — subtotal is enough in card layout */
    #cart-table .cart-row td.item-price {
        display: none;
    }
    /* Subtotal: push right */
    #cart-table .cart-row td.item-subtotal {
        flex: 1 1 auto;
        text-align: right;
        font-size: 13.5px;
        font-weight: 700;
    }
    /* Delete btn: far right */
    #cart-table .cart-row td:nth-child(5) {
        flex: 0 0 auto;
    }
    #cart-table .cart-row:last-child { border-bottom: none; }

    @media (max-width: 720px) {
        .qty-stepper .qty-btn {
            width: 36px;
            height: 36px;
            min-width: 36px;
            min-height: 36px;
            font-size: 18px;
            border-radius: 6px;
        }
        .qty-stepper { gap: 3px; }
        .item-qty { min-width: 40px; max-width: 52px; font-size: 13px; padding: 4px 2px; }
        /* Scroll area for many items */
        .cart-table-scroll {
            max-height: 260px;
            overflow-y: auto;
            overflow-x: hidden;
        }
    }

    .item-price, .item-subtotal {
        font-variant-numeric: tabular-nums;
        text-align: right;
        font-size: 13.5px;
    }

    .item-subtotal {
        font-weight: 700;
        color: var(--primary-hover, #1b4332);
    }

    .btn-remove-row {
        background: #fee2e2;
        color: #b91c1c;
        border: 1px solid #fecaca;
        border-radius: 6px;
        width: 28px;
        height: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: bold;
        cursor: pointer;
        transition: background-color 0.15s ease;
    }

    .btn-remove-row:hover, .btn-remove-row:focus-visible {
        background: #fca5a5;
        outline: none;
    }

    .cart-empty-message {
        text-align: center;
        color: var(--text-caption, #6b7280);
        padding: 24px 12px !important;
        font-style: normal;
        background: #f9fafb;
        border-radius: 8px;
    }

    /* --- STOCK BADGES --- */
    .stock-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 7px;
        border-radius: 9999px;
        font-size: 10.5px;
        font-weight: 700;
    }

    .stock-badge.in-stock {
        background: #dcfce7;
        color: #15803d;
    }

    .stock-badge.low-stock {
        background: #fef9c3;
        color: #854d0e;
    }

    .stock-badge.out-of-stock {
        background: #fee2e2;
        color: #b91c1c;
    }

    /* --- SUMMARY BOX & INPUTS --- */
    .summary-box {
        background-color: var(--mint-surface, #e8f5e9);
        border: 1px solid var(--mint-border, #a3b18a);
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
        color: var(--primary-hover, #1b4332);
        border-top: 2px solid var(--primary, #2d6a4f);
        padding-top: 10px;
        margin-top: 10px;
    }

    #display-total {
        font-size: 24px;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        color: var(--primary-hover, #1b4332);
    }

    #display-change {
        font-size: 16px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    .sku-code {
        font-family: var(--font-mono, 'JetBrains Mono', monospace);
        font-size: 11.5px;
        font-weight: 600;
        color: #4b5563;
        background: #f3f4f6;
        padding: 1px 5px;
        border-radius: 4px;
    }

    .btn-pill-submit {
        width: 100%;
        min-height: 48px;
        background-color: var(--primary, #2d6a4f);
        color: #ffffff;
        border: 1px solid var(--primary, #2d6a4f);
        border-radius: 9999px;
        font-family: inherit;
        font-size: 14.5px;
        font-weight: 700;
        cursor: pointer;
        padding: 12px 24px;
        box-shadow: 0 4px 14px rgba(45, 106, 79, 0.25);
        transition: background-color 0.15s ease, transform 0.15s ease;
    }

    .btn-pill-submit:hover {
        background-color: var(--primary-hover, #1b4332);
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(45, 106, 79, 0.3);
    }

    .btn-table-action {
        background-color: var(--mint-surface, #e8f5e9);
        color: var(--primary-hover, #1b4332);
        border: 1px solid var(--mint-border, #a3b18a);
        border-radius: 9999px;
        padding: 4px 14px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.15s ease, border-color 0.15s ease;
    }

    .btn-table-action:hover, .btn-table-action:focus-visible {
        background-color: var(--primary-light, #d8f3dc);
        border-color: var(--accent, #52b788);
        outline: none;
    }

    .grid-2 > div { min-width: 0; }
    .cart-table-scroll, .catalog-table-scroll { overflow-x: auto; }
    #cart-table { table-layout: fixed; }
    #cart-table th:first-child { min-width: 0 !important; width: 34%; }
    #cart-table th:nth-child(2) { width: 17% !important; }
    #cart-table th:nth-child(3) { width: 18% !important; }
    #cart-table th:nth-child(4) { width: 23% !important; }
    #cart-table th:last-child { width: 8% !important; }
    .cart-product-title { overflow-wrap: anywhere; }
    #cart-table:has(#cart-empty-row) thead { display: none; }

    @media (max-width: 900px) {
        #cart-table:not(:has(#cart-empty-row)) { min-width: 560px; }
        #products-table { min-width: 620px; }
    }
    @media (max-width: 1600px) and (min-width: 901px) {
        .monitor-grid { grid-template-columns: minmax(250px, 1.3fr) minmax(180px, 1fr) minmax(140px, .7fr); gap: 8px; }
    }
    @media (max-width: 768px) { .search-kbd-hint { display: none; } }

    .pos-page { display: flex; flex-direction: column; min-width: 0; }
    .monitor-grid { display: grid; grid-template-columns: minmax(300px, 1.5fr) minmax(210px, 1fr) minmax(160px, .65fr); gap: 12px; min-width: 0; margin-bottom: 18px; }
    .pos-workspace { order: 1; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; gap: 18px; }
    .pos-history-full { order: 2; margin-top: 18px; }
    .pos-catalog-pane, .pos-cart-pane, .pos-primary-pane, .pos-side-pane, .pos-catalog-pane > .card, .pos-cart-pane > .card, .pos-primary-pane > .card, .pos-side-pane > .card { min-width: 0; }
    .pos-cart-pane .search-kbd-hint, .pos-primary-pane .search-kbd-hint { display: none; }
    .sales-filter-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; align-items: end; margin-bottom: 12px; }
    .sales-filter-grid > div { min-width: 0; }
    .sales-filter-grid label { display: block; margin-bottom: 5px; font-size: 11px; font-weight: 700; color: var(--text-caption, #526359); }
    .sales-filter-grid .form-control { box-sizing: border-box; width: 100%; min-height: 40px; padding: 7px 10px; }
    .sales-filter-actions { display: grid; grid-template-columns: minmax(64px, .7fr) minmax(72px, auto); gap: 8px; align-items: center; min-height: 40px; }
    .sales-filter-actions .btn-table-action { min-height: 40px; white-space: nowrap; }
    .catalog-filter-row { display: grid; grid-template-columns: minmax(150px, 1.3fr) minmax(130px, 1fr) auto; align-items: end; gap: 10px; margin-bottom: 12px; }
    .catalog-filter-field { display: grid; gap: 5px; min-width: 0; }
    .catalog-filter-field label { color: var(--text-caption, #6b7280); font-size: 11px; font-weight: 700; }
    .catalog-filter-row .form-control { width: 100%; min-height: 40px; box-sizing: border-box; }
    .catalog-filter-actions { display: flex; gap: 8px; align-items: center; }
    .catalog-filter-actions .btn-table-action { min-height: 40px; white-space: nowrap; }

    @media (min-width: 1100px) and (min-height: 720px) {
        .pos-cart-pane > .card { margin-bottom: 0; padding: 18px; display: flex; flex-direction: column; }
        .pos-cart-pane h1 { flex: 0 0 auto; }
        #checkout-form { display: flex; flex-direction: column; }
        .pos-cart-section { flex: 1 1 auto; min-height: 110px; margin-bottom: 0; display: flex; flex-direction: column; overflow: hidden; }
        .pos-cart-section .cart-table-scroll { flex: 1; min-height: 0; overflow: auto; }
        .pos-cart-pane .form-group { margin-bottom: 10px; }
        .pos-cart-pane .summary-box { flex: 0 0 auto; margin-top: 10px; padding: 12px 14px; }
        .pos-cart-pane #display-total { font-size: 21px; }
        .pos-checkout-action { flex: 0 0 auto; margin-top: 10px !important; }
        .pos-catalog-pane { display: block; }
        .pos-catalog-card { margin: 0; padding: 18px; display: flex; flex-direction: column; }
        .monitor-grid > .dash-card { min-height: 0; margin: 0; overflow: hidden; }
        .monitor-grid > .sales-dashboard-card { padding: 0; }
        .monitor-grid .sales-dashboard-head { gap: 8px; padding: 12px 12px 0; }
        .monitor-grid .sales-period-btn { padding-inline: 7px; }
        .monitor-grid .sales-dashboard-body { grid-template-columns: minmax(108px, .8fr) minmax(0, 1.2fr); gap: 8px; min-height: 164px; padding: 8px 12px 12px; }
        .monitor-grid .sales-chart-shell, .monitor-grid .sales-chart { height: 118px; }
        .monitor-grid .dash-toggle { gap: 4px; }
        .monitor-grid .dash-toggle button { padding-inline: 4px; }
        .monitor-grid .dash-panel { min-height: 0; overflow-y: auto; }
        .monitor-grid .dash-panel.active { flex: 1; }
        .monitor-grid .dash-list li { gap: 4px; padding-block: 6px; }
        .pos-catalog-card .pagination { flex: 0 0 auto; margin-top: 10px; padding-top: 8px; }
        .pos-history-full .sales-filter-grid { grid-template-columns: minmax(180px, 1.4fr) repeat(4, minmax(120px, 1fr)) auto; }
    }
    @media (max-width: 1320px) {
        .monitor-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
        .sales-filter-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 900px), (max-height: 719px) {
        .pos-page, .pos-workspace { display: block; }
        .pos-catalog-pane > .card, .pos-cart-pane > .card, .pos-primary-pane > .card, .pos-side-pane > .card { height: auto; overflow: visible; }
        .pos-catalog-pane, .pos-side-pane { display: block; }
        .monitor-grid { display: block; }
        .monitor-grid > .dash-card { margin-bottom: 12px; }
        .sales-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 560px) { .sales-filter-grid { grid-template-columns: 1fr; } }

    /* --- DASHBOARD SUMMARY BAND --- */
    .dash-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; align-items: stretch; }
    .dash-card { background: #fff; border: 1px solid var(--border-card, #e5e7eb); border-radius: 14px; padding: 16px; min-width: 0; }
    .dash-label { font-size: 11.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--text-caption, #6b7280); margin: 0 0 6px; }
    .dash-value { font-size: 22px; font-weight: 800; color: var(--primary-hover, #1b4332); font-variant-numeric: tabular-nums; line-height: 1.1; }
    .dash-sub { font-size: 12px; color: var(--text-caption, #6b7280); margin-top: 4px; }
    .dash-list { list-style: none; margin: 0; padding: 0; }
    .dash-list li { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 7px 0; border-bottom: 1px solid #f0f4f1; font-size: 12.5px; }
    .dash-list li:last-child { border-bottom: none; }
    .dash-item-name { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dash-item-num { flex-shrink: 0; font-weight: 700; color: var(--primary-hover, #1b4332); font-variant-numeric: tabular-nums; }
    .dash-toggle { display: flex; gap: 4px; padding: 3px; background: var(--canvas, #f3f6f4); border-radius: 9999px; margin-bottom: 10px; }
    .dash-toggle button { flex: 1; font-size: 12px; font-weight: 600; padding: 7px 12px; border-radius: 9999px; border: none; background: transparent; color: var(--text-caption, #6b7280); cursor: pointer; transition: background-color .22s ease, color .22s ease, box-shadow .22s ease; }
    .dash-toggle button:hover:not(.active) { color: var(--text-main, #1c2826); background: rgba(255,255,255,.5); }
    .dash-toggle button.active { background: var(--surface, #ffffff); color: var(--primary-hover, #1b4332); box-shadow: 0 1px 4px rgba(0,0,0,.08); }
    .dash-panel { display: none; }
    .dash-panel.active { display: block; animation: panelIn .2s ease-out; }

    .sales-dashboard-card { padding: 0; min-height: 218px; overflow: hidden; }
    .sales-dashboard-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 14px 16px 0; }
    .sales-dashboard-head .dash-label { margin-bottom: 2px; }
    .sales-period-control { position: relative; display: inline-grid; grid-template-columns: repeat(3, 1fr); gap: 3px; padding: 3px; border: none; border-radius: 9999px; background: var(--canvas, #f3f6f4); flex-shrink: 0; }
    .sales-period-pill { position: absolute; top: 3px; bottom: 3px; left: 0; width: 0; border-radius: 9999px; background: #1b4332; box-shadow: 0 1px 4px rgba(27, 67, 50, .2); transition: transform .28s cubic-bezier(.2, 0, 0, 1), width .28s cubic-bezier(.2, 0, 0, 1); z-index: 0; pointer-events: none; }
    .sales-period-btn { position: relative; z-index: 1; min-height: 30px; padding: 5px 12px; border: 0; border-radius: 9999px; background: transparent; color: #53645a; font-family: inherit; font-size: 11.5px; font-weight: 600; white-space: nowrap; cursor: pointer; transition: color .28s cubic-bezier(.2, 0, 0, 1), transform .14s cubic-bezier(.2, 0, 0, 1); }
    .sales-period-btn:hover:not(:disabled):not([aria-pressed="true"]) { color: #163c2d; }
    .sales-period-btn:focus-visible { outline: 2px solid #1b4332; outline-offset: 2px; }
    .sales-period-btn[aria-pressed="true"],
    .sales-period-btn[aria-pressed="true"]:hover,
    .sales-period-btn[aria-pressed="true"]:focus { color: #fff; -webkit-text-fill-color: #fff; }
    .sales-period-btn:disabled { cursor: default; }
    .sales-period-btn:active { transform: scale(.96); }
    .sales-dashboard-body { display: grid; grid-template-columns: minmax(128px, .44fr) minmax(0, 1fr); align-items: end; gap: 14px; padding: 10px 16px 14px; min-height: 170px; }
    .sales-metric { align-self: center; min-width: 0; }
    .sales-metric .dash-value { font-size: clamp(20px, 2vw, 29px); overflow-wrap: anywhere; }
    .sales-status { min-height: 18px; margin-top: 10px; font-size: 11.5px; color: #596d61; }
    .sales-status.is-error { color: #991b1b; }
    .sales-chart-shell { position: relative; min-width: 0; height: 150px; }
    .sales-chart { width: 100%; height: 150px; display: block; overflow: visible; }
    .sales-chart-skeleton { position: absolute; inset: 8px 0 13px; }
    .sales-bar { transform-box: fill-box; transform-origin: center bottom; animation: salesBarIn .24s cubic-bezier(.2, 0, 0, 1) both; }
    @keyframes salesBarIn { from { transform: scaleY(.06); opacity: .4; } to { transform: scaleY(1); opacity: 1; } }
    .metric-swap { animation: metricSwapIn .24s cubic-bezier(.2, 0, 0, 1); }
    @keyframes metricSwapIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
    .metric-swap-out { animation: metricSwapOut .13s ease-in forwards; }
    @keyframes metricSwapOut { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(-4px); } }
    @keyframes rowIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
    .row-in { animation: rowIn .22s ease-out; }
    @keyframes rowOut { to { opacity: 0; transform: translateX(8px); } }
    .row-out { animation: rowOut .16s ease-in forwards; }
    @keyframes panelIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes listIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
    .list-in { animation: listIn .24s ease-out; }

    @media (max-width: 1100px) {
        .dash-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .sales-dashboard-card { grid-column: 1 / -1; }
    }
    @media (max-width: 640px) {
        .dash-grid { grid-template-columns: 1fr; }
        .sales-dashboard-card { grid-column: auto; }
        .sales-dashboard-head { align-items: stretch; flex-direction: column; gap: 10px; }
        #sales-period-label { display: none; }
        .sales-period-control { width: 100%; grid-template-columns: repeat(3, 1fr); }
        .sales-dashboard-body { grid-template-columns: 1fr; align-items: stretch; gap: 4px; }
        .sales-metric { display: grid; grid-template-columns: 1fr auto; align-items: end; gap: 4px 12px; }
        .sales-metric .dash-value { font-size: 24px; }
        .sales-metric .dash-sub { text-align: right; }
        .sales-status { grid-column: 1 / -1; margin-top: 2px; }
    }

    /* --- RECEIPT MODAL --- */
    .receipt-modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, .45); z-index: 100000; display: none; align-items: flex-start; justify-content: center; padding: 24px; overflow-y: auto; }
    .receipt-modal-overlay.open { display: flex; }
    #payment-modal-overlay, #checkout-confirm-overlay { align-items: center; }
    #payment-modal, #checkout-confirm-modal { max-height: calc(100dvh - 48px); overflow-y: auto; }
    .receipt-modal { background: #fff; border-radius: 18px; border: 1px solid var(--border-card, #e5e7eb); max-width: 520px; width: 100%; box-shadow: 0 24px 60px rgba(0, 0, 0, .25); padding: 24px; animation: dropdownIn .26s cubic-bezier(.34, 1.56, .64, 1); }
    .receipt-modal-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
    .receipt-modal-head h3 { margin: 0; font-size: 16px; font-weight: 800; color: var(--primary-hover, #1b4332); }
    .receipt-close { background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 8px; width: 30px; height: 30px; font-size: 16px; line-height: 1; cursor: pointer; color: #4b5563; }
    .rmeta { width: 100%; font-size: 12.5px; border-collapse: collapse; margin-bottom: 12px; }
    .rmeta td { padding: 3px 0; }
    .rmeta td.rlabel { width: 110px; color: var(--text-caption, #6b7280); }
    .ritems { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    .ritems th { background: var(--mint-surface, #e8f5e9); color: var(--primary-hover, #1b4332); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; padding: 7px 8px; text-align: left; }
    .ritems td { padding: 7px 8px; border-bottom: 1px solid #f0f4f1; }
    .rsum { margin-top: 12px; background: var(--mint-surface, #e8f5e9); border: 1px solid var(--mint-border, #a3b18a); border-radius: 12px; padding: 12px 14px; }
    .rsum-line { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 5px; }
    .rsum-line.rgt { font-weight: 800; font-size: 15px; color: var(--primary-hover, #1b4332); border-top: 1px solid var(--mint-border, #a3b18a); padding-top: 7px; margin-top: 7px; margin-bottom: 0; }
    @media print {
        body * { visibility: hidden !important; }
        #receipt-modal, #receipt-modal * { visibility: visible !important; }
        #receipt-modal { position: absolute !important; inset: auto !important; left: 0; top: 0; width: 100% !important; box-shadow: none !important; border: none !important; }
        .receipt-modal-overlay { position: static !important; padding: 0 !important; overflow: visible !important; display: block !important; background: none !important; }
        .no-print { display: none !important; }
    }
</style>

<div class="pos-page">
<!-- 3. Work Area: Product Catalog / Monitor (Left) & POS Cashier / Cart (Right) -->
        <div class="monitor-grid">
        <section class="dash-card sales-dashboard-card" id="sales-dashboard-card" aria-labelledby="sales-dashboard-title">
            <div class="sales-dashboard-head">
                <div>
                    <div class="dash-label" id="sales-dashboard-title">Penjualan</div>
                    <div class="dash-sub" id="sales-period-label">7 hari terakhir</div>
                </div>
                <div class="sales-period-control" id="sales-period-control" role="group" aria-label="Pilih rentang waktu penjualan">
                    <span class="sales-period-pill" aria-hidden="true"></span>
                    <button type="button" class="sales-period-btn" data-period="today" aria-pressed="false" onclick="loadSalesDashboard('today')">Hari Ini</button>
                    <button type="button" class="sales-period-btn" data-period="7d" aria-pressed="true" onclick="loadSalesDashboard('7d')">7 Hari</button>
                    <button type="button" class="sales-period-btn" data-period="30d" aria-pressed="false" onclick="loadSalesDashboard('30d')">30 Hari</button>
                </div>
            </div>
            <div class="sales-dashboard-body" id="sales-dashboard-body" aria-live="polite" aria-busy="true">
                <div class="sales-metric">
                    <div class="dash-value" id="sales-revenue"><span class="skeleton" style="display:block;height:29px;width:136px;"></span></div>
                    <div class="dash-sub" id="sales-transactions"><span class="skeleton" style="display:block;height:12px;width:82px;margin-top:7px;"></span></div>
                    <div class="sales-status" id="sales-status">Memuat data penjualan&hellip;</div>
                </div>
                <div class="sales-chart-shell">
                    <div class="skeleton sales-chart-skeleton" id="sales-chart-skeleton"></div>
                    <svg class="sales-chart" id="sales-chart" viewBox="0 0 420 150" preserveAspectRatio="none" role="img" aria-label="Grafik penjualan 7 hari terakhir"></svg>
                </div>
            </div>
        </section>
        <div class="dash-card">
            <div class="dash-label">Produk Unggulan</div>
            <div class="dash-toggle">
                <button type="button" id="dash-toggle-best" class="active" onclick="switchDashList('best')">Terlaris</button>
                <button type="button" id="dash-toggle-low" onclick="switchDashList('low')">Stok Menipis</button>
            </div>
            <div id="dash-list-best" class="dash-panel active">
                <ul class="dash-list">
                    @forelse($topProducts as $tp)
                    <li><span class="dash-item-name" title="{{ $tp->name }}">{{ $tp->name }}</span><span class="dash-item-num">{{ number_format((float) $tp->total_qty, 0, ',', '.') }} {{ $tp->unit }}</span></li>
                    @empty
                    <li style="color: var(--text-caption, #6b7280);">Belum ada penjualan.</li>
                    @endforelse
                </ul>
            </div>
            <div id="dash-list-low" class="dash-panel">
                <ul class="dash-list">
                    @forelse($lowStock as $ls)
                    <li><span class="dash-item-name" title="{{ $ls->name }}">{{ $ls->name }}</span><span class="dash-item-num">{{ rtrim(rtrim(number_format((float) $ls->stock_quantity, 2, ',', '.'), '0'), ',') }} {{ $ls->unit }}</span></li>
                    @empty
                    <li style="color: var(--text-caption, #6b7280);">Stok aman.</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="dash-card">
            <div class="dash-label">Ringkasan Produk</div>
            <ul class="dash-list">
                <li><span>Total Produk</span><span class="dash-item-num">{{ $stockSummary['products'] }}</span></li>
                <li><span>Stok Menipis</span><span class="dash-item-num" style="{{ $stockSummary['low'] > 0 ? 'color: #854d0e;' : '' }}">{{ $stockSummary['low'] }}</span></li>
                <li><span>Stok Habis</span><span class="dash-item-num" style="{{ $stockSummary['out'] > 0 ? 'color: #b91c1c;' : '' }}">{{ $stockSummary['out'] }}</span></li>
            </ul>
        </div>
        </div>

<div class="pos-workspace">
    <div class="pos-catalog-pane">
        <div class="card pos-catalog-card">
            <h2>Katalog Produk Material</h2>
            <div class="pos-search-wrapper" role="search">
                <label for="pos-product-search" style="display:block; margin-bottom:6px;">Cari produk di katalog</label>
                <div class="pos-search-box">
                    <svg class="pos-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="search" id="pos-product-search" class="pos-search-input" placeholder="Ketik minimal 2 huruf nama atau SKU" autocomplete="off" aria-controls="products-tbody" aria-describedby="product-search-status" oninput="onSearchInput(this.value)" onkeydown="onSearchKeyDown(event)">
                    <span class="search-kbd-hint">Pintasan: [/]</span>
                </div>
                <div id="product-search-status" aria-live="polite" style="font-size:11.5px; color:var(--text-caption,#6b7280); margin-top:5px;">Ketik 2 huruf atau lebih untuk mencari produk.</div>
            </div>
            <form method="GET" action="{{ route('pos.index') }}" class="catalog-filter-row">
                <div class="catalog-filter-field">
                    <label for="product-category-filter">Kelompok</label>
                    <select id="product-category-filter" name="product_category" class="form-control" onchange="this.form.requestSubmit()">
                        <option value="">Semua kelompok</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}" {{ $productCategory === $category ? 'selected' : '' }}>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="catalog-filter-field">
                    <label for="product-stock-filter">Kondisi stok</label>
                    <select id="product-stock-filter" name="product_stock" class="form-control">
                        <option value="all" {{ $productStock === 'all' ? 'selected' : '' }}>Semua Stok</option>
                        <option value="available" {{ $productStock === 'available' ? 'selected' : '' }}>Tersedia</option>
                        <option value="low" {{ $productStock === 'low' ? 'selected' : '' }}>Menipis</option>
                        <option value="out" {{ $productStock === 'out' ? 'selected' : '' }}>Habis</option>
                    </select>
                </div>
                <div class="catalog-filter-actions">
                    <button type="submit" class="btn-table-action">Terapkan</button>
                    @if($productQ !== '' || $productCategory !== '' || $productStock !== 'all')
                        <a href="{{ route('pos.index') }}" class="btn-table-action" style="text-decoration: none;">Reset</a>
                    @endif
                </div>
            </form>
            <div class="catalog-table-scroll" style="max-height: 320px; overflow-y: auto;">
                <table id="products-table">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Nama</th>
                            <th>Kelompok</th>
                            <th>Stok</th>
                            <th style="text-align: right; padding-right: 18px;">Harga</th>
                            <th style="width: 65px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="products-tbody">
                        @forelse($products as $p)
                        <tr class="product-item-row" data-id="{{ $p->id }}" data-sku="{{ $p->sku }}" data-name="{{ $p->name }}">
                            <td><span class="sku-code">{{ $p->sku }}</span></td>
                            <td>{{ $p->name }}</td>
                            <td><span class="stock-badge">{{ $p->category ?: 'Umum' }}</span></td>
                            <td>
                                @if($p->stock_quantity > 10)
                                    <span class="stock-badge in-stock">{{ rtrim(rtrim(number_format((float)$p->stock_quantity, 4, ',', '.'), '0'), ',') }} {{ $p->unit }}</span>
                                @elseif($p->stock_quantity > 0)
                                    <span class="stock-badge low-stock">{{ rtrim(rtrim(number_format((float)$p->stock_quantity, 4, ',', '.'), '0'), ',') }} {{ $p->unit }}</span>
                                @else
                                    <span class="stock-badge out-of-stock">Habis</span>
                                @endif
                            </td>
                            <td style="text-align: right; font-variant-numeric: tabular-nums; padding-right: 18px; font-weight: 600;">Rp {{ number_format($p->selling_price, 2, ',', '.') }}</td>
                            <td style="text-align: center;">
                                <button type="button" class="btn-table-action" onclick="addToCart({{ $p->id }})">+ Pilih</button>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" style="text-align: center; padding: 18px; color: #6b7280;">Belum ada data produk material.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('partials.pagination', ['paginator' => $products])
        </div>
    </div>

    <div class="pos-cart-pane">
        <div class="card">
            <h1 style="font-size: 20px; margin-bottom: 12px;">Kasir</h1>

            <form action="{{ route('pos.checkout') }}" method="POST" id="checkout-form" onsubmit="return validateCheckout(event)">
                @csrf
                <div class="form-group pos-cart-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label style="margin: 0;">Keranjang Belanja</label>
                        <span id="cart-item-count" style="font-size: 11.5px; color: var(--text-caption, #6b7280);">0 jenis item</span>
                    </div>
                    <div class="cart-table-scroll"><table id="cart-table">
                        <thead>
                            <tr>
                                <th style="min-width: 190px;">Produk &amp; Stok</th>
                                <th style="width: 120px;">Qty</th>
                                <th style="width: 105px; text-align: right;">Harga</th>
                                <th style="width: 115px; text-align: right;">Subtotal</th>
                                <th style="width: 40px; text-align: center;"></th>
                            </tr>
                        </thead>
                        <tbody id="cart-tbody">
                            <tr id="cart-empty-row">
                                <td colspan="5" class="cart-empty-message">
                                    Keranjang belanja kosong. Cari produk di katalog lalu pilih item untuk dimasukkan ke keranjang.
                                </td>
                            </tr>
                        </tbody>
                    </table></div>
                </div>

                <div class="summary-box">
                    <div class="summary-row summary-total">
                        <span>Total Belanja:</span>
                        <span id="display-total">Rp 0,00</span>
                    </div>
                </div>

                <div class="pos-checkout-action" style="margin-top: 14px;">
                    <button type="button" class="btn-pill-submit" onclick="openPaymentModal()">Checkout &amp; Pembayaran</button>
                </div>

                <div class="receipt-modal-overlay" id="payment-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="payment-modal-title">
                    <div class="receipt-modal" id="payment-modal">
                        <div class="receipt-modal-head">
                            <h3 id="payment-modal-title">Pembayaran</h3>
                            <button type="button" class="receipt-close no-print" onclick="closePaymentModal()" aria-label="Tutup">&times;</button>
                        </div>
                        <div class="rsum" style="margin-top:0;margin-bottom:14px;">
                            <div class="rsum-line rgt" style="border-top:0;padding-top:0;margin-top:0;">
                                <span>Total Tagihan</span><span id="payment-modal-total" class="tabular-nums">Rp 0,00</span>
                            </div>
                        </div>
                        <div class="grid-2" style="gap:12px;">
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
                        @if($fleetReady)
                        <div class="form-group">
                            <label for="use_armada">Pengiriman dengan Armada</label>
                            <select name="use_armada" id="use_armada" class="form-control" onchange="syncArmadaFields()">
                                <option value="0" {{ old('use_armada', '0') === '0' ? 'selected' : '' }}>Tidak menggunakan armada</option>
                                <option value="1" {{ old('use_armada') === '1' ? 'selected' : '' }}>Gunakan armada toko</option>
                            </select>
                        </div>
                        <div id="armada-fields" class="grid-2" style="gap:12px;margin-bottom:14px;" hidden>
                            <div class="form-group" style="margin-bottom:0;">
                                <label for="vehicle_id">Pilih Armada</label>
                                <select name="vehicle_id" id="vehicle_id" class="form-control" disabled>
                                    <option value="">-- Pilih armada aktif --</option>
                                    @foreach($vehicles as $vehicle)
                                        <option value="{{ $vehicle->id }}" {{ old('vehicle_id') == $vehicle->id ? 'selected' : '' }}>{{ $vehicle->license_plate }} ({{ strtoupper($vehicle->vehicle_type) }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label for="ritase_amount">Uang Ritase (Rp)</label>
                                <input type="text" inputmode="decimal" name="ritase_amount" id="ritase_amount" value="{{ old('ritase_amount') }}" placeholder="0,00" class="form-control tabular-nums" data-currency-input data-currency-scale="2" disabled>
                            </div>
                        </div>
                        @endif
                        <div class="form-group" style="margin-bottom:8px;">
                            <label for="paid_amount_display">Nominal Pembayaran (Rp)</label>
                            <input type="text" inputmode="decimal" id="paid_amount_display" autocomplete="off" placeholder="0,00" class="form-control tabular-nums" oninput="onPaidInput(this.value)" onblur="onPaidBlur()" onfocus="this.select()">
                            <input type="hidden" name="paid_amount" id="paid_amount" value="{{ old('paid_amount', '0') }}">
                        </div>
                        <div class="summary-row" id="change-row" style="margin-bottom:12px;">
                            <span>Kembalian:</span>
                            <span id="display-change" style="font-weight:bold;">Rp 0,00</span>
                        </div>
                        <div style="display:flex;gap:10px;justify-content:flex-end;">
                            <button type="button" class="btn-table-action" style="min-height:42px;padding:8px 18px;" onclick="closePaymentModal()">Kembali ke Keranjang</button>
                            <button type="submit" class="btn-pill-submit" style="width:auto;min-height:42px;padding:8px 20px;">Tinjau Pembayaran</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="card pos-recent-card pos-history-full">
    <h2>Riwayat Transaksi</h2>
    <div class="sales-filter-grid">
        <div><label for="sales-search">Cari invoice</label><input type="search" id="sales-search" placeholder="Nomor invoice" class="form-control" onkeyup="if(event.key==='Enter') loadSalesHistory(1)"></div>
        <div><label for="sales-method">Pembayaran</label><select id="sales-method" class="form-control" onchange="loadSalesHistory(1)"><option value="">Semua</option><option value="cash">Tunai</option><option value="transfer">Transfer</option><option value="credit">Kredit</option></select></div>
        <div><label for="sales-customer">Pelanggan</label><select id="sales-customer" class="form-control" onchange="loadSalesHistory(1)"><option value="">Semua</option><option value="retail">Retail</option><option value="contractor">Kontraktor</option></select></div>
        <div><label for="sales-start">Dari</label><input type="date" id="sales-start" class="form-control" onchange="loadSalesHistory(1)"></div>
        <div><label for="sales-end">Sampai</label><input type="date" id="sales-end" class="form-control" onchange="loadSalesHistory(1)"></div>
        <div class="sales-filter-actions"><select id="sales-perpage" class="form-control" aria-label="Jumlah riwayat per halaman" onchange="loadSalesHistory(1)">
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
        </select><button type="button" class="btn-table-action" onclick="resetSalesFilters()">Reset</button></div>
    </div>
    <div id="recent-sales-wrap" aria-live="polite">
        <div class="sales-skeleton">
            <div class="skeleton" style="height: 14px; width: 100%;"></div>
            <div class="skeleton" style="height: 14px; width: 92%;"></div>
            <div class="skeleton" style="height: 14px; width: 96%;"></div>
            <div class="skeleton" style="height: 14px; width: 88%;"></div>
        </div>
    </div>
    <div id="recent-sales-pagination"></div>
</div>
</div>

<!-- Receipt Modal -->
<div class="receipt-modal-overlay" id="receipt-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="receipt-modal-title">
    <div class="receipt-modal" id="receipt-modal">
        <div class="receipt-modal-head">
            <h3 id="receipt-modal-title">Transaksi Tersimpan — Periksa Sebelum Cetak</h3>
            <button type="button" class="receipt-close no-print" onclick="closeReceipt()" aria-label="Tutup">&times;</button>
        </div>
        <div id="receipt-modal-body"></div>
        <div style="margin-top: 16px; text-align: right;" class="no-print">
            <button type="button" id="receipt-print" class="btn-pill-submit" style="width: auto; min-height: 40px; padding: 8px 20px;" onclick="window.print()" hidden disabled>Cetak Struk</button>
        </div>
    </div>
</div>

<!-- Checkout Confirmation Modal (commit gate) -->
<div class="receipt-modal-overlay" id="checkout-confirm-overlay" role="dialog" aria-modal="true" aria-labelledby="checkout-confirm-title">
    <div class="receipt-modal" id="checkout-confirm-modal">
        <div class="receipt-modal-head">
            <h3 id="checkout-confirm-title">Tinjau Sebelum Menyimpan</h3>
            <button type="button" class="receipt-close no-print" onclick="cancelCheckout()" aria-label="Tutup">&times;</button>
        </div>
        <div id="checkout-confirm-body"></div>
        <div style="margin-top: 16px; display: flex; gap: 10px; justify-content: flex-end;" class="no-print">
            <button type="button" class="btn-table-action" style="padding: 9px 18px;" onclick="cancelCheckout()">Batal</button>
            <button type="button" id="checkout-confirm-submit" class="btn-pill-submit" style="width: auto; min-height: 40px; padding: 8px 20px;" onclick="confirmCheckout()">Konfirmasi &amp; Simpan Transaksi</button>
        </div>
    </div>
</div>

<script>
const productsCatalog = @json($products->items());
const productMap = {};
productsCatalog.forEach(p => { productMap[p.id] = p; });
const CART_KEY = 'kasirCart';
const receiptUrl = @json(route('pos.receipt', ['id' => '__ID__']));
const searchUrl = @json(route('pos.products.search'));
const salesUrl = @json(route('pos.sales.history'));
const salesDashboardUrl = @json(route('pos.dashboard.sales'));
let currentSearchMatches = [];
let cartTotal = 0;
let searchTimer = null;
let salesDashboardRequest = null;
let checkoutSubmitting = false;

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
}

function formatRupiah(amount) {
    const num = parseFloat(amount) || 0;
    return 'Rp ' + num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function getStockBadgeHtml(stock, unit) {
    const qty = parseFloat(stock) || 0;
    if (qty > 10) {
        return `<span class="stock-badge in-stock">${qty} ${escapeHtml(unit)}</span>`;
    } else if (qty > 0) {
        return `<span class="stock-badge low-stock">${qty} ${escapeHtml(unit)}</span>`;
    } else {
        return `<span class="stock-badge out-of-stock">Habis (0 ${escapeHtml(unit)})</span>`;
    }
}

const catalogTbody = document.getElementById('products-tbody');
const initialCatalogRows = catalogTbody.innerHTML;
let searchRequestId = 0;

function onSearchInput(query) {
    clearTimeout(searchTimer);
    const trimmed = query.trim();
    const status = document.getElementById('product-search-status');
    const pagination = document.querySelector('.pos-catalog-card .pagination');
    const requestId = ++searchRequestId;
    currentSearchMatches = [];

    if (trimmed.length < 2) {
        catalogTbody.innerHTML = initialCatalogRows;
        status.textContent = 'Ketik 2 huruf atau lebih untuk mencari produk.';
        if (pagination) pagination.hidden = false;
        return;
    }

    if (pagination) pagination.hidden = true;
    status.textContent = 'Mencari produk…';
    catalogTbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:18px;color:#6b7280;">Memuat hasil pencarian…</td></tr>';
    searchTimer = setTimeout(() => fetchSearchProducts(trimmed, requestId), 180);
}

function fetchSearchProducts(q, requestId) {
    const category = document.getElementById('product-category-filter')?.value || '';
    const params = new URLSearchParams({ q });
    if (category) params.set('category', category);
    fetch(searchUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
        .then(r => {
            if (!r.ok) throw new Error('Pencarian produk gagal.');
            return r.json();
        })
        .then(res => {
            if (requestId !== searchRequestId) return;
            currentSearchMatches = (res.data || []).map(p => ({ id: p.id, sku: p.sku, name: p.name, unit: p.unit, selling_price: p.selling_price, stock_quantity: p.stock_quantity }));
            currentSearchMatches.forEach(p => { productMap[p.id] = p; });
            renderCatalogResults(currentSearchMatches, q);
        })
        .catch(() => {
            if (requestId !== searchRequestId) return;
            catalogTbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:18px;color:#6b7280;">Pencarian gagal dimuat. Coba lagi.</td></tr>';
            document.getElementById('product-search-status').textContent = 'Pencarian gagal dimuat.';
        });
}

function renderCatalogResults(products, query) {
    const status = document.getElementById('product-search-status');
    if (products.length === 0) {
        catalogTbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:18px;color:#6b7280;">Produk tidak ditemukan.</td></tr>';
        status.textContent = 'Tidak ada produk yang cocok dengan “' + query + '”.';
        return;
    }

    catalogTbody.innerHTML = products.map(p => `
        <tr class="product-item-row" data-id="${p.id}" data-sku="${escapeHtml(p.sku)}" data-name="${escapeHtml(p.name)}">
            <td><span class="sku-code">${escapeHtml(p.sku)}</span></td>
            <td>${escapeHtml(p.name)}</td>
            <td><span class="stock-badge">${escapeHtml(p.category || 'Umum')}</span></td>
            <td>${getStockBadgeHtml(p.stock_quantity, p.unit)}</td>
            <td style="text-align:right;font-variant-numeric:tabular-nums;padding-right:18px;font-weight:600;">${formatRupiah(p.selling_price)}</td>
            <td style="text-align:center;"><button type="button" class="btn-table-action" onclick="addToCart(${p.id})">+ Pilih</button></td>
        </tr>
    `).join('');
    status.textContent = `${products.length} hasil untuk “${query}”. Pilih produk untuk menambahkannya ke keranjang.`;
}

function onSearchKeyDown(e) {
    if (e.key === 'Enter' && currentSearchMatches.length > 0) {
        e.preventDefault();
        addToCart(currentSearchMatches[0].id);
    }
}

function selectSearchItem(productId) {
    addToCart(productId);
    document.getElementById('pos-product-search').focus();
}

function addToCart(productId) {
    const p = productMap[productId] || productsCatalog.find(x => Number(x.id) === Number(productId));
    if (!p) return;

    const tbody = document.getElementById('cart-tbody');
    const existingRow = tbody.querySelector(`.cart-row[data-product-id="${p.id}"]`);
    if (existingRow) {
        const qtyInput = existingRow.querySelector('.item-qty');
        const currentQty = parseFloat(qtyInput.value) || 0;
        qtyInput.value = currentQty + 1;
        qtyInput.focus();
    } else {
        addCartRow(p, 1);
    }

    syncCartFormNames();
    calculateCart();
}

function addCartRow(p, qty) {
    const tbody = document.getElementById('cart-tbody');
    const emptyRow = document.getElementById('cart-empty-row');
    if (emptyRow) emptyRow.remove();

    const stockHtml = getStockBadgeHtml(p.stock_quantity, p.unit);
    const newRow = document.createElement('tr');
    newRow.className = 'cart-row';
    newRow.dataset.productId = p.id;
    newRow.dataset.price = p.selling_price;
    newRow.dataset.stock = p.stock_quantity;
    newRow.dataset.unit = p.unit;
    newRow.innerHTML = `
        <td>
            <input type="hidden" class="item-product-id" value="${p.id}">
            <div class="cart-product-title">${escapeHtml(p.name)}</div>
            <div class="cart-product-meta">
                <span class="sku-code">${escapeHtml(p.sku)}</span>
                ${stockHtml}
            </div>
        </td>
        <td>
            <div class="qty-stepper">
                <button type="button" class="qty-btn" aria-label="Kurangi" onclick="stepQty(this, -1)">&minus;</button>
                <input type="number" step="0.0001" min="0.0001" value="${qty}" class="form-control item-qty" aria-label="Jumlah barang" required oninput="calculateCart()">
                <button type="button" class="qty-btn" aria-label="Tambah" onclick="stepQty(this, 1)">+</button>
            </div>
        </td>
        <td class="item-price" style="text-align: right; vertical-align: middle;">${formatRupiah(p.selling_price)}</td>
        <td class="item-subtotal" style="text-align: right; vertical-align: middle;">${formatRupiah(p.selling_price)}</td>
        <td style="text-align: center; vertical-align: middle;">
            <button type="button" class="btn-remove-row" title="Hapus item dari keranjang" aria-label="Hapus item" onclick="removeRow(this)">&times;</button>
        </td>
    `;
    newRow.classList.add('row-in');
    tbody.appendChild(newRow);
}

function stepQty(btn, delta) {
    const stepper = btn.closest('.qty-stepper');
    if (!stepper) return;
    const input = stepper.querySelector('.item-qty');
    if (!input) return;
    let val = parseFloat(input.value) || 0;
    val = Math.max(0.0001, val + delta);
    input.value = parseFloat(val.toFixed(4));
    calculateCart();
}

function removeRow(btn) {
    const row = btn.closest('tr');
    if (!row) return;
    row.classList.add('row-out');
    setTimeout(() => {
        row.remove();

        const tbody = document.getElementById('cart-tbody');
        if (tbody.querySelectorAll('.cart-row').length === 0) {
            const emptyTr = document.createElement('tr');
            emptyTr.id = 'cart-empty-row';
            emptyTr.className = 'row-in';
            emptyTr.innerHTML = `
                <td colspan="5" class="cart-empty-message">
                    Keranjang belanja kosong. Cari produk di katalog lalu pilih item untuk dimasukkan ke keranjang.
                </td>
            `;
            tbody.appendChild(emptyTr);
        }

        syncCartFormNames();
        calculateCart();
    }, 150);
}

function syncCartFormNames() {
    const rows = document.querySelectorAll('#cart-tbody .cart-row');
    rows.forEach((r, index) => {
        const idInput = r.querySelector('.item-product-id');
        const qtyInput = r.querySelector('.item-qty');
        if (idInput) idInput.name = `items[${index}][product_id]`;
        if (qtyInput) qtyInput.name = `items[${index}][quantity]`;
    });

    const countElem = document.getElementById('cart-item-count');
    if (countElem) {
        animateSwap(countElem, () => { countElem.textContent = `${rows.length} jenis item`; });
    }
}

function calculateCart() {
    const rows = document.querySelectorAll('#cart-tbody .cart-row');
    let grandTotal = 0;

    rows.forEach(r => {
        const unitPrice = parseFloat(r.dataset.price) || 0;
        const qtyInput = r.querySelector('.item-qty');
        const qty = parseFloat(qtyInput ? qtyInput.value : 0) || 0;
        const subtotal = unitPrice * qty;
        grandTotal += subtotal;

        const priceCell = r.querySelector('.item-price');
        const subtotalCell = r.querySelector('.item-subtotal');
        if (priceCell) priceCell.textContent = formatRupiah(unitPrice);
        if (subtotalCell) subtotalCell.textContent = formatRupiah(subtotal);
    });

    cartTotal = grandTotal;
    animateSwap(document.getElementById('display-total'), () => { document.getElementById('display-total').textContent = formatRupiah(grandTotal); });
    const paymentTotal = document.getElementById('payment-modal-total');
    if (paymentTotal) paymentTotal.textContent = formatRupiah(grandTotal);

    const method = document.getElementById('payment_method').value;
    if (method === 'transfer') setPaidValue(grandTotal);
    calculateChange();
    saveCart();
}

function saveCart() {
    const rows = document.querySelectorAll('#cart-tbody .cart-row');
    const items = [];
    rows.forEach(r => {
        const p = productMap[r.dataset.productId];
        if (!p) return;
        items.push({ id: p.id, sku: p.sku, name: p.name, unit: p.unit, selling_price: p.selling_price, stock_quantity: p.stock_quantity, quantity: parseFloat(r.querySelector('.item-qty').value) || 0 });
    });
    localStorage.setItem(CART_KEY, JSON.stringify(items));
}

function restoreCart() {
    let items = [];
    try { items = JSON.parse(localStorage.getItem(CART_KEY) || '[]') || []; } catch (e) { items = []; }
    items.forEach(it => {
        productMap[it.id] = { id: it.id, sku: it.sku, name: it.name, unit: it.unit, selling_price: it.selling_price, stock_quantity: it.stock_quantity };
        addCartRow(productMap[it.id], it.quantity);
    });
    syncCartFormNames();
}

function formatPaidPlain(n) {
    return (parseFloat(n) || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function setPaidValue(numeric) {
    const n = parseFloat(numeric) || 0;
    document.getElementById('paid_amount').value = n.toFixed(2);
    document.getElementById('paid_amount_display').value = formatPaidPlain(n);
}

function onPaidInput(raw) {
    const digits = raw.replace(/[^\d,]/g, '');
    document.getElementById('paid_amount_display').setCustomValidity('');
    const commaIdx = digits.lastIndexOf(',');
    let norm;
    if (commaIdx >= 0) {
        const intPart = digits.slice(0, commaIdx).replace(/,/g, '') || '0';
        const dec = (digits.slice(commaIdx + 1) + '00').slice(0, 2);
        norm = intPart + '.' + dec;
    } else {
        norm = (digits || '0') + '.00';
    }
    document.getElementById('paid_amount').value = (parseFloat(norm) || 0).toFixed(2);
    calculateChange();
}

function onPaidBlur() {
    setPaidValue(document.getElementById('paid_amount').value);
}

function syncPaymentMethod() {
    const method = document.getElementById('payment_method').value;
    document.getElementById('paid_amount_display').setCustomValidity('');
    if (method === 'credit') {
        setPaidValue(0);
    } else if (method === 'transfer') {
        setPaidValue(cartTotal);
    }
    calculateChange();
}

function calculateChange() {
    const paid = parseFloat(document.getElementById('paid_amount').value) || 0;
    const change = document.getElementById('payment_method').value === 'credit' ? 0 : Math.max(0, paid - cartTotal);
    animateSwap(document.getElementById('display-change'), () => { document.getElementById('display-change').textContent = formatRupiah(change); });
}

function compactAmount(value) {
    const amount = Number(value) || 0;
    if (amount >= 1000000000) return (amount / 1000000000).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' M';
    if (amount >= 1000000) return (amount / 1000000).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt';
    if (amount >= 1000) return (amount / 1000).toLocaleString('id-ID', { maximumFractionDigits: 0 }) + ' rb';
    return amount.toLocaleString('id-ID', { maximumFractionDigits: 0 });
}

function svgNode(name, attributes, text) {
    const node = document.createElementNS('http://www.w3.org/2000/svg', name);
    Object.entries(attributes || {}).forEach(([key, value]) => node.setAttribute(key, value));
    if (text !== undefined) node.textContent = text;
    return node;
}

function moveSalesPeriodPill(period) {
    const control = document.getElementById('sales-period-control');
    const pill = control ? control.querySelector('.sales-period-pill') : null;
    if (!control || !pill) return;
    const active = control.querySelector('.sales-period-btn[data-period="' + period + '"]') || control.querySelector('.sales-period-btn[aria-pressed="true"]');
    if (!active) return;
    pill.style.width = active.offsetWidth + 'px';
    pill.style.transform = 'translateX(' + active.offsetLeft + 'px)';
}

function setSalesDashboardLoading(period) {
    const body = document.getElementById('sales-dashboard-body');
    const skeleton = document.getElementById('sales-chart-skeleton');
    body.setAttribute('aria-busy', 'true');
    skeleton.hidden = false;
    document.getElementById('sales-status').classList.remove('is-error');
    document.getElementById('sales-status').textContent = 'Memuat data penjualan…';
    document.querySelectorAll('.sales-period-btn').forEach(button => {
        const active = button.dataset.period === period;
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
        button.disabled = active;
    });
    moveSalesPeriodPill(period);
}

function renderSalesChart(buckets, label) {
    const chart = document.getElementById('sales-chart');
    chart.replaceChildren();
    chart.setAttribute('aria-label', 'Grafik penjualan ' + label.toLowerCase());

    const width = 420;
    const baseline = 116;
    const chartHeight = 88;
    const plotLeft = 8;
    const plotWidth = 404;
    const maxValue = Math.max(1, ...buckets.map(bucket => Number(bucket.total) || 0));

    [28, 57, 86, baseline].forEach((y, index) => {
        chart.appendChild(svgNode('line', {
            x1: plotLeft,
            y1: y,
            x2: width - plotLeft,
            y2: y,
            stroke: index === 3 ? '#ccd9ce' : '#e9efe9',
            'stroke-width': '1'
        }));
    });

    if (buckets.length === 0) {
        chart.appendChild(svgNode('text', {
            x: width / 2,
            y: 78,
            'text-anchor': 'middle',
            fill: '#596d61',
            'font-size': '12'
        }, 'Belum ada penjualan pada periode ini'));
        return;
    }

    const slot = plotWidth / buckets.length;
    const barWidth = Math.max(3, Math.min(24, slot * .58));
    buckets.forEach((bucket, index) => {
        const total = Number(bucket.total) || 0;
        const height = total > 0 ? Math.max(3, (total / maxValue) * chartHeight) : 2;
        const x = plotLeft + (slot * index) + ((slot - barWidth) / 2);
        const y = baseline - height;
        const isLast = index === buckets.length - 1;
        const highlight = isLast && total > 0;
        const bar = svgNode('rect', {
            class: 'sales-bar',
            x: x.toFixed(2),
            y: y.toFixed(2),
            width: barWidth.toFixed(2),
            height: height.toFixed(2),
            rx: Math.min(4, barWidth / 2).toFixed(2),
            fill: highlight ? '#1b4332' : '#3d9970',
            opacity: total > 0 ? '1' : '.42',
            style: `animation-delay:${Math.min(index * 26, 550)}ms`
        });
        bar.appendChild(svgNode('title', {}, bucket.label + ': ' + formatRupiah(total)));
        chart.appendChild(bar);

        const showLabel = buckets.length <= 8 || index % 5 === 0 || isLast;
        if (showLabel) {
            chart.appendChild(svgNode('text', {
                x: (x + (barWidth / 2)).toFixed(2),
                y: '137',
                'text-anchor': 'middle',
                fill: highlight ? '#1b4332' : '#42554a',
                'font-size': buckets.length > 12 ? '8.5' : '9.5',
                'font-weight': highlight ? '700' : '500'
            }, bucket.label));
        }

        if (total > 0 && buckets.length <= 8) {
            chart.appendChild(svgNode('text', {
                x: (x + (barWidth / 2)).toFixed(2),
                y: Math.max(12, y - 6).toFixed(2),
                'text-anchor': 'middle',
                fill: highlight ? '#1b4332' : '#42554a',
                'font-size': '9',
                'font-weight': highlight ? '700' : '500'
            }, compactAmount(total)));
        }
    });
}

function animateSwap(el, apply) {
    if (!el) return;
    el.classList.remove('metric-swap');
    void el.offsetWidth;
    apply();
    el.classList.add('metric-swap');
}

function swapWithOut(el, apply) {
    if (!el) return;
    el.classList.remove('metric-swap', 'metric-swap-out');
    void el.offsetWidth;
    el.classList.add('metric-swap-out');
    setTimeout(() => {
        apply();
        el.classList.remove('metric-swap-out');
        void el.offsetWidth;
        el.classList.add('metric-swap');
    }, 130);
}

function fadeIn(el) {
    if (!el) return;
    el.classList.remove('list-in');
    void el.offsetWidth;
    el.classList.add('list-in');
}

function renderSalesDashboard(data) {
    const body = document.getElementById('sales-dashboard-body');
    swapWithOut(document.getElementById('sales-period-label'), () => { document.getElementById('sales-period-label').textContent = data.label; });
    swapWithOut(document.getElementById('sales-revenue'), () => { document.getElementById('sales-revenue').textContent = formatRupiah(data.revenue); });
    swapWithOut(document.getElementById('sales-transactions'), () => { document.getElementById('sales-transactions').textContent = Number(data.transaction_count || 0).toLocaleString('id-ID') + ' transaksi'; });
    swapWithOut(document.getElementById('sales-status'), () => {
        document.getElementById('sales-status').textContent = 'Nominal dan grafik untuk periode yang sama';
        document.getElementById('sales-status').classList.remove('is-error');
    });
    document.getElementById('sales-chart-skeleton').hidden = true;
    renderSalesChart(Array.isArray(data.buckets) ? data.buckets : [], data.label || 'periode terpilih');
    body.setAttribute('aria-busy', 'false');
}

function loadSalesDashboard(period = '7d') {
    const allowed = ['today', '7d', '30d'];
    const selected = allowed.includes(period) ? period : '7d';
    if (salesDashboardRequest) salesDashboardRequest.abort();
    salesDashboardRequest = new AbortController();
    setSalesDashboardLoading(selected);

    fetch(salesDashboardUrl + '?period=' + encodeURIComponent(selected), {
        headers: { 'Accept': 'application/json' },
        signal: salesDashboardRequest.signal
    })
        .then(response => {
            if (!response.ok) throw new Error('http');
            return response.json();
        })
        .then(renderSalesDashboard)
        .catch(error => {
            if (error.name === 'AbortError') return;
            document.getElementById('sales-dashboard-body').setAttribute('aria-busy', 'false');
            document.getElementById('sales-chart-skeleton').hidden = true;
            document.getElementById('sales-status').textContent = 'Data penjualan gagal dimuat. Pilih timeline untuk mencoba lagi.';
            document.getElementById('sales-status').classList.add('is-error');
        })
        .finally(() => {
            document.querySelectorAll('.sales-period-btn').forEach(button => { button.disabled = false; });
        });
}

function switchDashList(kind) {
    document.getElementById('dash-list-best').classList.toggle('active', kind === 'best');
    document.getElementById('dash-list-low').classList.toggle('active', kind === 'low');
    document.getElementById('dash-toggle-best').classList.toggle('active', kind === 'best');
    document.getElementById('dash-toggle-low').classList.toggle('active', kind === 'low');
}

function openReceipt(id, clearCart = false) {
    const url = receiptUrl.replace('__ID__', id);
    const body = document.getElementById('receipt-modal-body');
    const printButton = document.getElementById('receipt-print');
    printButton.hidden = true;
    printButton.disabled = true;
    body.innerHTML = '<div class="receipt-loading"><span class="spinner"></span> Memuat struk&hellip;</div>';
    document.getElementById('receipt-modal-overlay').classList.add('open');
    fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(r => { if (!r.ok) throw new Error('http'); return r.json(); })
        .then(res => {
            renderReceipt(res);
            if (clearCart) {
                localStorage.removeItem(CART_KEY);
                renderCart();
                calculateCart();
            }
        })
        .catch(() => {
            body.replaceChildren(Object.assign(document.createElement('div'), {
                className: 'receipt-loading',
                textContent: 'Gagal memuat struk. Keranjang tetap tersimpan.',
            }));
        });
}

function renderReceipt(res) {
    const s = res.sale;
    const items = res.items || [];
    let rowsHtml = '';
    items.forEach(it => {
        rowsHtml += `<tr><td><div style="font-weight:600;">${escapeHtml(it.name)}</div><div style="font-size:11px;color:var(--text-caption,#6b7280);">${escapeHtml(it.unit)}</div></td><td style="text-align:center;font-variant-numeric:tabular-nums;">${parseFloat(it.quantity)}</td><td style="text-align:right;font-variant-numeric:tabular-nums;">${formatRupiah(it.unit_price)}</td><td style="text-align:right;font-weight:700;font-variant-numeric:tabular-nums;">${formatRupiah(it.subtotal)}</td></tr>`;
    });
    document.getElementById('receipt-modal-body').innerHTML = `
        <table class="rmeta">
            <tr><td class="rlabel">No. Invoice</td><td><strong class="sku-code">${escapeHtml(s.invoice_number)}</strong></td></tr>
            <tr><td class="rlabel">Waktu</td><td>${escapeHtml(s.created_at)}</td></tr>
            <tr><td class="rlabel">Pelanggan</td><td style="text-transform:uppercase;">${escapeHtml(s.customer_type)}</td></tr>
            <tr><td class="rlabel">Metode Bayar</td><td style="text-transform:uppercase;">${escapeHtml(s.payment_method)}</td></tr>
        </table>
        <table class="ritems"><thead><tr><th>Item</th><th style="text-align:center;">Qty</th><th style="text-align:right;">Harga</th><th style="text-align:right;">Subtotal</th></tr></thead><tbody>${rowsHtml}</tbody></table>
        <div class="rsum">
            <div class="rsum-line"><span>Total Tagihan</span><span class="tabular-nums" style="font-weight:700;">${formatRupiah(s.total_amount)}</span></div>
            <div class="rsum-line"><span>Uang Tunai</span><span class="tabular-nums">${formatRupiah(s.paid_amount)}</span></div>
            <div class="rsum-line rgt"><span>Uang Kembalian</span><span class="tabular-nums">${formatRupiah(s.change_amount)}</span></div>
        </div>`;
    const printButton = document.getElementById('receipt-print');
    printButton.hidden = false;
    printButton.disabled = false;
}

function closeReceipt() {
    document.getElementById('receipt-modal-overlay').classList.remove('open');
}

function syncArmadaFields() {
    if (!document.getElementById('use_armada')) return;
    const enabled = document.getElementById('use_armada').value === '1';
    const fields = document.getElementById('armada-fields');
    fields.hidden = !enabled;
    for (const id of ['vehicle_id', 'ritase_amount']) {
        const input = document.getElementById(id);
        input.disabled = !enabled;
        input.required = enabled;
        if (!enabled) input.value = '';
    }
}

function openPaymentModal() {
    if (document.querySelectorAll('#cart-tbody .cart-row').length === 0) {
        alert('Keranjang masih kosong. Pilih produk dari katalog terlebih dahulu.');
        document.getElementById('pos-product-search').focus();
        return;
    }
    document.getElementById('payment-modal-overlay').classList.add('open');
    document.getElementById('payment_method').focus();
}

function closePaymentModal() {
    document.getElementById('payment-modal-overlay').classList.remove('open');
}

function openCheckoutConfirm() {
    closePaymentModal();
    const usingArmada = document.getElementById('use_armada')?.value === '1';
    const vehicle = document.getElementById('vehicle_id');
    const ritase = document.getElementById('ritase_amount');
    const fleetSummary = usingArmada ? `<div class="rsum-line"><span>Armada</span><span>${escapeHtml(vehicle.selectedOptions[0]?.textContent || '')}</span></div><div class="rsum-line"><span>Biaya ritase toko</span><span>${formatRupiah(Number(displayToRaw(ritase.value)))}</span></div>` : '';
    const rows = [...document.querySelectorAll('#cart-tbody .cart-row')];
    const method = document.getElementById('payment_method').value;
    const paid = parseFloat(document.getElementById('paid_amount').value) || 0;
    const change = method === 'credit' ? 0 : Math.max(0, paid - cartTotal);
    const methodLabel = { cash: 'Tunai', transfer: 'Transfer Bank', credit: 'Kredit (Piutang Usaha)' }[method] || method;
    let rowsHtml = '';
    rows.forEach(r => {
        const name = r.querySelector('.cart-product-title') ? r.querySelector('.cart-product-title').textContent : '';
        const qty = r.querySelector('.item-qty') ? r.querySelector('.item-qty').value : '';
        const price = r.querySelector('.item-price') ? r.querySelector('.item-price').textContent : '';
        const subtotal = r.querySelector('.item-subtotal') ? r.querySelector('.item-subtotal').textContent : '';
        rowsHtml += `<tr><td style="font-weight:600;">${escapeHtml(name)}</td><td style="text-align:center;font-variant-numeric:tabular-nums;">${escapeHtml(qty)}</td><td style="text-align:right;font-variant-numeric:tabular-nums;">${escapeHtml(price)}</td><td style="text-align:right;font-weight:700;font-variant-numeric:tabular-nums;">${escapeHtml(subtotal)}</td></tr>`;
    });
    document.getElementById('checkout-confirm-body').innerHTML = `
        <table class="ritems"><thead><tr><th>Item</th><th style="text-align:center;">Qty</th><th style="text-align:right;">Harga</th><th style="text-align:right;">Subtotal</th></tr></thead><tbody>${rowsHtml}</tbody></table>
        <div class="rsum">
            <div class="rsum-line"><span>Total Tagihan</span><span class="tabular-nums" style="font-weight:700;">${formatRupiah(cartTotal)}</span></div>
            <div class="rsum-line"><span>Metode</span><span>${escapeHtml(methodLabel)}</span></div>
            ${fleetSummary}
            <div class="rsum-line"><span>Uang Tunai</span><span class="tabular-nums">${formatRupiah(paid)}</span></div>
            <div class="rsum-line rgt"><span>Uang Kembalian</span><span class="tabular-nums">${formatRupiah(change)}</span></div>
        </div>
        <p style="font-size:12px;color:var(--text-caption,#6b7280);margin:12px 0 0;">Transaksi belum tersimpan. Periksa kembali, lalu konfirmasi untuk menyimpan. Cetak struk baru tersedia setelah transaksi berhasil. Pilih Batal untuk kembali; keranjang tetap tersimpan.</p>`;
    document.getElementById('checkout-confirm-overlay').classList.add('open');
}

function confirmCheckout() {
    if (checkoutSubmitting) return;
    checkoutSubmitting = true;
    const submitButton = document.getElementById('checkout-confirm-submit');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Menyimpan transaksi…';
    }
    document.getElementById('checkout-confirm-overlay').classList.remove('open');
    // form.submit() bypasses the global submit listener that normalizes currency inputs.
    const ritase = document.getElementById('ritase_amount');
    if (ritase && !ritase.disabled) ritase.value = displayToRaw(ritase.value);
    document.getElementById('checkout-form').submit();
}

function cancelCheckout() {
    if (checkoutSubmitting) return;
    document.getElementById('checkout-confirm-overlay').classList.remove('open');
    openPaymentModal();
}

function validateCheckout(e) {
    e.preventDefault();
    const rows = document.querySelectorAll('#cart-tbody .cart-row');
    if (rows.length === 0) {
        alert('Keranjang belanja masih kosong! Silakan cari dan pilih minimal 1 item material sebelum proses checkout.');
        document.getElementById('pos-product-search').focus();
        return false;
    }
    const paidInput = document.getElementById('paid_amount');
    const paidDisplay = document.getElementById('paid_amount_display');
    const paid = Number(paidInput.value);
    const method = document.getElementById('payment_method').value;
    if (!Number.isFinite(paid) || paid < 0 || (method !== 'credit' && paid < Math.round(cartTotal * 100) / 100) || (method === 'credit' && paid > Math.round(cartTotal * 100) / 100)) {
        paidDisplay.setCustomValidity(method === 'credit' ? 'Uang muka tidak boleh melebihi total.' : 'Nominal pembayaran harus menutup total tagihan.');
        paidDisplay.reportValidity();
        paidDisplay.focus();
        return false;
    }
    paidDisplay.setCustomValidity('');
    if (document.getElementById('use_armada')?.value === '1') {
        const vehicle = document.getElementById('vehicle_id');
        const ritase = document.getElementById('ritase_amount');
        const rawAmount = displayToRaw(ritase.value);
        const amount = Number(rawAmount);
        if (!vehicle.value) {
            vehicle.reportValidity();
            vehicle.focus();
            return false;
        }
        if (!Number.isFinite(amount) || amount <= 0 || !/^\d{1,17}(\.\d{1,2})?$/.test(rawAmount)) {
            ritase.setCustomValidity('Isi uang ritase positif maksimal dua angka desimal.');
            ritase.reportValidity();
            ritase.focus();
            return false;
        }
        ritase.setCustomValidity('');
    }
    openCheckoutConfirm();
    return false;
}

// Global keyboard shortcut: [/] focuses search bar
document.addEventListener('keydown', (e) => {
    if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        const searchInput = document.getElementById('pos-product-search');
        if (searchInput) searchInput.focus();
    }
});

function loadSalesHistory(page) {
    const wrap = document.getElementById('recent-sales-wrap');
    const perPage = parseInt(document.getElementById('sales-perpage').value) || 10;
    const search = document.getElementById('sales-search').value.trim();
    const method = document.getElementById('sales-method').value;
    const customer = document.getElementById('sales-customer').value;
    const startDate = document.getElementById('sales-start').value;
    const endDate = document.getElementById('sales-end').value;
    const targetPage = page || 1;
    wrap.innerHTML = '<div class="sales-skeleton">' + [1, 2, 3, 4].map(() => '<div class="skeleton" style="height:14px;width:100%;"></div>').join('') + '</div>';
    const params = new URLSearchParams({ page: targetPage, per_page: perPage });
    if (search) params.set('search', search);
    if (method) params.set('payment_method', method);
    if (customer) params.set('customer_type', customer);
    if (startDate) params.set('start_date', startDate);
    if (endDate) params.set('end_date', endDate);
    fetch(salesUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(res => renderSalesHistory(res))
        .catch(() => { wrap.innerHTML = '<div style="text-align:center;padding:18px;color:var(--text-caption,#6b7280);">Gagal memuat transaksi.</div>'; });
}

function resetSalesFilters() {
    ['sales-search', 'sales-method', 'sales-customer', 'sales-start', 'sales-end'].forEach(id => {
        document.getElementById(id).value = '';
    });
    document.getElementById('sales-perpage').value = '10';
    loadSalesHistory(1);
}

function renderSalesHistory(res) {
    const wrap = document.getElementById('recent-sales-wrap');
    const rows = res.data || [];
    if (rows.length === 0) {
        wrap.innerHTML = '<div style="text-align:center;padding:18px;color:var(--text-caption,#6b7280);">Belum ada transaksi.</div>';
    } else {
        let html = '<table><thead><tr><th>Invoice</th><th>Metode</th><th style="text-align:right;">Total</th><th>Struk</th></tr></thead><tbody>';
        rows.forEach((s, i) => {
            const method = String(s.payment_method || '').toUpperCase();
            const cust = String(s.customer_type || '').toUpperCase();
            const total = 'Rp ' + (parseFloat(s.total_amount) || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            html += `<tr class="row-in" style="animation-delay:${Math.min(i * 40, 480)}ms"><td><span class="sku-code">${escapeHtml(s.invoice_number)}</span></td><td>${escapeHtml(method + ' (' + cust + ')')}</td><td style="text-align:right;font-variant-numeric:tabular-nums;">${escapeHtml(total)}</td><td><button type="button" class="btn-table-action" onclick="openReceipt(${s.id})">Lihat Struk</button></td></tr>`;
        });
        html += '</tbody></table>';
        wrap.innerHTML = html;
    }
    const pg = res.pagination || {};
    const pagEl = document.getElementById('recent-sales-pagination');
    let out = '<div style="font-size:11.5px;color:var(--text-caption,#6b7280);margin:10px 0 6px;">Menampilkan ' + (pg.from || 0) + '&ndash;' + (pg.to || 0) + ' dari ' + (pg.total || 0) + ' data</div>';
    out += '<div style="display:flex;gap:8px;align-items:center;justify-content:space-between;">';
    out += (pg.current_page > 1) ? `<button type="button" class="btn-table-action" onclick="loadSalesHistory(${pg.current_page - 1})">&lsaquo; Sebelumnya</button>` : '<span class="btn-table-action" style="opacity:.4;cursor:default;">&lsaquo; Sebelumnya</span>';
    out += '<span style="font-size:12px;font-weight:600;color:var(--primary-hover,#1b4332);">Halaman ' + (pg.current_page || 1) + ' / ' + (pg.last_page || 1) + '</span>';
    out += (pg.current_page < pg.last_page) ? `<button type="button" class="btn-table-action" onclick="loadSalesHistory(${pg.current_page + 1})">Berikutnya &rsaquo;</button>` : '<span class="btn-table-action" style="opacity:.4;cursor:default;">Berikutnya &rsaquo;</span>';
    out += '</div>';
    pagEl.innerHTML = out;
}

window.addEventListener('resize', () => {
    const active = document.querySelector('.sales-period-btn[aria-pressed="true"]');
    if (active) moveSalesPeriodPill(active.dataset.period);
});

document.addEventListener('DOMContentLoaded', () => {
    syncArmadaFields();
    const receiptId = @json(request('receipt'));
    if (receiptId) {
        openReceipt(receiptId, true);
    } else {
        restoreCart();
    }
    calculateCart();
    setPaidValue(document.getElementById('paid_amount').value || 0);
    loadSalesDashboard('7d');
    loadSalesHistory(1);
});

const receiptOverlay = document.getElementById('receipt-modal-overlay');
if (receiptOverlay) {
    receiptOverlay.addEventListener('click', (e) => { if (e.target === e.currentTarget) closeReceipt(); });
}
const confirmOverlay = document.getElementById('checkout-confirm-overlay');
if (confirmOverlay) {
    confirmOverlay.addEventListener('click', (e) => { if (e.target === e.currentTarget) cancelCheckout(); });
}
const paymentOverlay = document.getElementById('payment-modal-overlay');
if (paymentOverlay) {
    paymentOverlay.addEventListener('click', (e) => { if (e.target === e.currentTarget) closePaymentModal(); });
}
</script>
@endsection
