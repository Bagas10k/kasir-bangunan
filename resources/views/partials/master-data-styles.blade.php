@once
<style>
    @media (prefers-reduced-motion: reduce) {
        *, ::before, ::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: .01ms !important;
            scroll-behavior: auto !important;
        }
    }

    @keyframes masterSettle {
        from { opacity: 0; transform: translateY(3px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .master-page {
        display: grid;
        gap: 12px;
        animation: masterSettle .15s cubic-bezier(.2, 0, 0, 1) both;
    }

    .master-page .card {
        border-radius: 10px;
        border: 1px solid var(--border-control);
        box-shadow: none;
        margin-bottom: 0;
    }

    .master-page .card:hover {
        box-shadow: none;
        transform: none;
    }

    .master-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 14px 18px;
        border-radius: 10px;
        border: 1px solid var(--border-control);
        background: var(--surface);
        box-shadow: none;
    }

    .master-head-copy { max-width: 68ch; }
    .master-eyebrow {
        margin: 0 0 4px;
        color: var(--primary-hover);
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        line-height: 1.2;
    }
    .master-head h1 {
        margin: 0;
        color: var(--text-main);
        font-size: 19px;
        font-weight: 750;
        line-height: 1.2;
        letter-spacing: -.02em;
    }
    .master-head p {
        margin: 4px 0 0;
        color: var(--text-main);
        font-size: 12px;
        line-height: 1.45;
    }
    .master-head .btn {
        min-height: 36px;
        padding: 6px 14px;
        border-radius: 9999px;
        font-size: 12px;
        font-weight: 650;
        letter-spacing: .01em;
        background: var(--primary-hover);
        border: 1px solid var(--primary-hover);
        color: var(--surface);
        box-shadow: none;
        transform: none;
        white-space: nowrap;
    }
    .master-head .btn:hover {
        background: var(--text-main);
        border-color: var(--text-main);
        box-shadow: none;
        transform: none;
    }

    .master-summary-strip {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        overflow: hidden;
        padding: 0;
        border-radius: 10px;
        border: 1px solid var(--border-control);
        background: var(--surface);
        box-shadow: none;
    }
    .master-summary-item {
        min-width: 0;
        padding: 10px 14px;
        border-right: 1px solid var(--border-control);
        background: var(--surface);
    }
    .master-summary-item:last-child { border-right: 0; }
    .master-summary-label {
        display: block;
        color: var(--primary-hover);
        font-size: 9.5px;
        font-weight: 750;
        letter-spacing: .065em;
        text-transform: uppercase;
    }
    .master-summary-value {
        display: block;
        margin-top: 2px;
        color: var(--primary-hover);
        font-family: var(--font-mono);
        font-size: 20px;
        font-weight: 700;
        font-variant-numeric: tabular-nums lining-nums;
        letter-spacing: -.02em;
        line-height: 1.15;
    }

    .master-workspace {
        padding: 0;
        overflow: hidden;
        border-radius: 10px;
        border: 1px solid var(--border-control);
        background: var(--surface);
        box-shadow: none;
    }

    .master-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 12px;
        align-items: center;
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-control);
        background: #fbfdfc;
        border-radius: 12px 12px 0 0;
    }
    .master-toolbar.product-toolbar,
    .master-toolbar.vehicle-toolbar,
    .master-toolbar.coa-toolbar {
        display: flex;
        flex-wrap: wrap;
        grid-template-columns: none;
    }

    .master-control {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
        flex: 0 0 auto;
    }
    .master-control label {
        color: var(--text-caption, #526359);
        font-size: 11.5px;
        font-weight: 700;
        letter-spacing: .02em;
        text-transform: none;
        white-space: nowrap;
        margin: 0;
        line-height: 1.2;
        flex-shrink: 0;
    }
    .master-control-search {
        flex: 1 1 220px;
        min-width: 180px;
    }
    .master-control-search input {
        width: 100%;
        flex: 1 1 auto;
    }
    .master-control input, .master-control select {
        box-sizing: border-box;
        min-width: 0;
        min-height: 34px;
        height: 34px;
        padding: 5px 10px;
        font-size: 12px;
        line-height: 1.4;
        border-radius: 8px;
        border: 1px solid var(--border-control, #d9e2dc);
        background: #ffffff;
        color: var(--text-main, #1c2826);
        box-shadow: none;
        vertical-align: middle;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .master-control input:focus, .master-control select:focus {
        border-color: var(--primary, #2d6a4f);
        outline: none;
        box-shadow: 0 0 0 2px rgba(45, 106, 79, .12);
    }

    .master-toolbar-actions {
        display: inline-flex;
        gap: 8px;
        align-items: center;
        margin-left: auto;
        flex-shrink: 0;
    }
    .master-toolbar .btn, .master-toolbar .btn-secondary,
    .master-toolbar-actions .btn, .master-toolbar-actions .btn-secondary {
        box-sizing: border-box;
        min-height: 34px;
        height: 34px;
        padding: 5px 14px;
        font-size: 12px;
        font-weight: 600;
        border-radius: 9999px;
        box-shadow: none;
        transform: none;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        text-decoration: none;
    }
    .master-toolbar .btn {
        background: var(--primary, #2d6a4f);
        border: 1px solid var(--primary, #2d6a4f);
        color: #ffffff;
    }
    .master-toolbar .btn:hover {
        background: var(--primary-hover, #1b4332);
        border-color: var(--primary-hover, #1b4332);
        box-shadow: 0 2px 6px rgba(45, 106, 79, 0.2);
    }
    .master-toolbar .btn-secondary {
        background: var(--mint-surface, #e8f5e9);
        border: 1px solid var(--mint-border, #a3b18a);
        color: var(--primary-hover, #1b4332);
    }
    .master-toolbar .btn-secondary:hover {
        background: var(--primary-light, #d8f3dc);
        border-color: var(--accent, #52b788);
    }

    .master-table-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 14px 8px;
        border-bottom: 1px solid var(--border-control);
        background: var(--surface);
    }
    .master-table-head h2 {
        margin: 0;
        padding: 0;
        border: 0;
        font-size: 12.5px;
        font-weight: 750;
        letter-spacing: .03em;
        text-transform: uppercase;
        color: var(--text-main);
    }
    .master-table-head span {
        color: var(--primary-hover);
        font-size: 10.5px;
        font-family: var(--font-mono);
        font-variant-numeric: tabular-nums lining-nums;
    }

    .master-table-wrap {
        overflow-x: auto;
        overscroll-behavior-x: contain;
    }
    .master-table {
        width: 100%;
        min-width: 900px;
        border-collapse: collapse;
    }
    .master-table th {
        position: sticky;
        top: 0;
        z-index: 2;
        padding: 7px 12px;
        color: var(--text-main);
        font-size: 10px;
        font-weight: 750;
        letter-spacing: .065em;
        text-transform: uppercase;
        white-space: nowrap;
        background: var(--surface-hover);
        border-top: 1px solid var(--border-control);
        border-bottom: 1px solid var(--border-control);
        text-align: left;
    }
    .master-table td {
        padding: 7px 12px;
        vertical-align: middle;
        border-bottom: 1px solid var(--border-subtle);
        color: var(--text-main);
        font-size: 12.5px;
        line-height: 1.35;
    }
    .master-table tbody tr:hover td {
        background: var(--surface-hover);
    }
    .master-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .master-table .money, .master-table .quantity {
        text-align: right;
        font-family: var(--font-mono);
        font-variant-numeric: tabular-nums lining-nums;
        white-space: nowrap;
        font-size: 12px;
        font-weight: 600;
    }
    .master-table .quantity strong, .master-table .quantity small {
        font-family: var(--font-mono);
        font-variant-numeric: tabular-nums lining-nums;
    }

    .master-identity { display: grid; gap: 2px; }
    .master-identity strong {
        color: var(--text-main);
        font-weight: 650;
    }
    .master-identity small { color: var(--primary-hover); font-size: 11px; }

    .sku-code {
        display: inline-block;
        padding: 1px 5px;
        border: 1px solid var(--border-control);
        border-radius: 2px;
        background: var(--surface-hover);
        color: var(--text-main);
        font-family: var(--font-mono);
        font-size: 10.5px;
        font-weight: 600;
        font-variant-numeric: tabular-nums lining-nums;
        letter-spacing: .02em;
        line-height: 1.25;
    }

    .master-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 20px;
        padding: 1px 6px;
        border: 1px solid var(--border-control);
        border-radius: 2px;
        color: var(--text-main);
        background: var(--surface-hover);
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        white-space: nowrap;
        line-height: 1.2;
        box-shadow: none;
    }
    .master-status.active {
        border-color: var(--mint-border);
        color: var(--primary-hover);
        background: var(--mint-surface);
    }
    .master-status.warning {
        border-color: var(--primary-hover);
        color: var(--text-main);
        background: var(--surface);
    }
    .master-status.critical {
        border-color: var(--danger);
        color: var(--danger-text);
        background: var(--danger-bg);
    }
    .product-status-stack {
        display: flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
    }
    .master-status.product-lifecycle {
        border-color: var(--border-control);
        color: var(--text-main);
        background: var(--surface);
    }

    .master-row-actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
    }
    .master-row-actions .btn-action-edit,
    .master-row-actions .btn-action-delete,
    .master-row-actions .btn-action-locked,
    .master-row-actions .master-link {
        min-height: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 3px 8px;
        font-size: 11px;
        font-weight: 650;
        border-radius: 2px;
        box-shadow: none;
        transform: none;
    }
    .master-row-actions .btn-action-edit {
        background: var(--mint-surface);
        border: 1px solid var(--mint-border);
        color: var(--primary-hover);
    }
    .master-row-actions .btn-action-edit:hover {
        background: var(--primary-light);
        border-color: var(--primary);
    }
    .master-row-actions .btn-action-delete {
        background: var(--danger-bg);
        border: 1px solid var(--danger-border);
        color: var(--danger-text);
    }
    .master-row-actions .btn-action-delete:hover {
        background: var(--danger-border);
        color: var(--danger);
    }
    .master-row-actions .btn-action-locked {
        background: var(--surface);
        border: 1px dashed var(--border-control);
        color: var(--primary-hover);
        cursor: help;
    }
    .master-row-actions .btn-action-locked:hover {
        background: var(--border-subtle);
    }
    .master-link {
        color: var(--primary-hover);
        font-size: 11.5px;
        font-weight: 650;
        text-decoration: underline;
        text-underline-offset: 3px;
    }
    .master-link:hover {
        color: var(--primary);
    }

    .master-empty {
        padding: 30px 16px !important;
        color: var(--primary-hover);
        text-align: center;
        font-size: 12px;
    }
    .master-workspace .pagination {
        margin: 0 14px 12px;
        padding-top: 10px;
        border-top: 1px solid var(--border-control);
    }

    .master-modal-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .master-modal-grid .form-group { margin: 0; }
    .master-modal-grid .span-2 { grid-column: 1 / -1; }
    .master-help {
        padding: 9px 12px;
        border: 1px solid var(--mint-border);
        border-radius: 10px;
        background: var(--mint-surface);
        color: var(--primary-hover);
        font-size: 11.5px;
        line-height: 1.45;
    }

    @media (max-width: 1100px) {
        .master-toolbar, .master-toolbar.product-toolbar, .master-toolbar.coa-toolbar, .master-toolbar.vehicle-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 8px;
            align-items: center;
        }
        .master-control-search {
            flex: 1 1 200px;
        }
        .master-toolbar-actions {
            margin-left: auto;
        }
    }

    @media (max-width: 860px) {
        .master-control-search {
            flex: 1 1 100%;
            min-width: 100%;
        }
        .master-toolbar-actions {
            margin-left: auto;
        }
    }

    @media (max-width: 900px) {
        .app-sidebar:not(.open) {
            visibility: hidden;
            pointer-events: none;
        }
        .app-sidebar.open {
            visibility: visible;
            pointer-events: auto;
        }
    }

    @media (max-width: 720px) {
        .master-page { gap: 8px; }
        .master-head {
            flex-direction: column;
            align-items: stretch;
            padding: 12px;
            gap: 10px;
        }
        .master-head p { margin-top: 3px; }
        .master-head .btn {
            width: 100%;
            min-height: 44px;
            padding: 10px 14px;
            font-size: 13px;
        }

        .master-summary-strip { grid-template-columns: 1fr 1fr; }
        .master-summary-item {
            padding: 9px 12px;
            border-bottom: 1px solid var(--border-control);
        }
        .master-summary-item:nth-child(2n) { border-right: 0; }
        .master-summary-item:nth-last-child(-n+2) { border-bottom: 0; }

        .master-toolbar, .master-toolbar.product-toolbar, .master-toolbar.vehicle-toolbar, .master-toolbar.coa-toolbar {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            padding: 10px 12px;
            gap: 10px;
        }
        .master-control {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 4px;
            width: 100%;
            flex: 1 1 100%;
        }
        .master-control label {
            white-space: normal;
        }
        .master-control-search {
            flex: none;
            width: 100%;
        }
        .master-control:first-child { grid-column: auto; }
        .master-control input, .master-control select {
            box-sizing: border-box;
            min-height: 40px;
            height: 40px;
            padding: 8px 12px;
            font-size: 13px;
            width: 100%;
        }

        .master-toolbar-actions {
            display: flex;
            width: 100%;
            margin-left: 0;
            gap: 8px;
        }
        .master-toolbar .btn, .master-toolbar .btn-secondary,
        .master-toolbar-actions .btn, .master-toolbar-actions .btn-secondary,
        .master-toolbar-actions > * {
            flex: 1;
            min-height: 40px;
            height: 40px;
            min-width: 40px;
            padding: 8px 14px;
            font-size: 13px;
            box-sizing: border-box;
        }

        .master-table-head {
            align-items: flex-start;
            padding: 10px 12px;
        }
        .master-table-wrap {
            overflow: visible;
            padding: 0 10px max(16px, env(safe-area-inset-bottom));
        }
        .master-table {
            min-width: 0;
            border-collapse: separate;
            border-spacing: 0 6px;
        }
        .master-table thead { display: none; }
        .master-table tbody, .master-table tr, .master-table td {
            display: block;
            width: 100%;
        }
        .master-table tbody tr {
            padding: 10px 12px;
            border: 1px solid var(--border-control);
            border-radius: 4px;
            background: var(--surface);
            box-shadow: none;
            scroll-margin-top: 76px;
        }
        .master-table tbody td {
            display: grid;
            grid-template-columns: minmax(108px, 38%) minmax(0, 1fr);
            align-items: center;
            gap: 8px 10px;
            padding: 6px 0;
            border: 0;
            border-bottom: 1px solid var(--border-subtle);
            text-align: left !important;
            white-space: normal;
        }
        .master-table tbody td::before {
            content: attr(data-label);
            color: var(--primary-hover);
            font-size: 10.5px;
            font-weight: 750;
            letter-spacing: .04em;
            text-transform: uppercase;
            grid-column: 1;
            grid-row: 1 / span 4;
            align-self: start;
            padding-top: 2px;
        }
        .master-table tbody td > * {
            grid-column: 2;
        }
        .master-table tbody td:first-child { padding-top: 0; }
        .master-table tbody td:last-child {
            border-bottom: 0;
            padding-bottom: 0;
            grid-template-columns: 1fr;
        }
        .master-table tbody td:last-child::before {
            grid-column: 1;
            grid-row: auto;
            padding-top: 0;
        }
        .master-table tbody td:last-child > * { grid-column: 1; }

        .master-table .money, .master-table .quantity { text-align: left; }

        .master-row-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(76px, 1fr));
            white-space: normal;
            gap: 8px;
            width: 100%;
        }
        .master-row-actions .btn-action-edit,
        .master-row-actions .btn-action-delete,
        .master-row-actions .btn-action-locked,
        .master-row-actions .master-link {
            min-height: 44px !important;
            padding: 8px 6px;
            font-size: 12px;
            border-radius: 10px;
            width: 100%;
            white-space: nowrap;
        }
        .master-row-actions .master-link {
            border: 1px solid var(--border-control);
            background: var(--surface);
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .master-empty {
            display: block !important;
            border-bottom: 0 !important;
        }
        .master-empty::before { display: none; }

        .master-modal-grid { grid-template-columns: 1fr; }
        .master-modal-grid .span-2 { grid-column: auto; }
    }
</style>
@endonce