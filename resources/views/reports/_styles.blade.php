<style>
    @media (prefers-reduced-motion: reduce) {
        *, ::before, ::after { animation-duration: .01ms !important; transition-duration: .01ms !important; scroll-behavior: auto !important; }
    }

    @keyframes ledgerSettle { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

    .report-shell {
        padding: 0;
        overflow: hidden;
        border: 1px solid var(--border-card);
        border-radius: var(--radius-card);
        background: var(--surface);
        box-shadow: 0 4px 20px -2px rgba(27, 67, 50, .05);
        color: var(--text-main);
        animation: ledgerSettle .2s cubic-bezier(.2, 0, 0, 1) both;
    }

    .statement-header { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 24px; align-items: end; padding: 22px 24px 18px; border-bottom: 1px solid var(--primary-hover); }
    .breadcrumb { margin: 0 0 8px; color: var(--text-caption); font-size: 11px; font-weight: 650; letter-spacing: .07em; text-transform: uppercase; }
    .breadcrumb .sep { margin-inline: 7px; color: var(--border-control); }
    .statement-title h2 { margin: 0; padding: 0; border: 0; color: var(--text-main); font-size: clamp(24px, 2.2vw, 34px); font-weight: 750; letter-spacing: -.035em; line-height: 1.04; }
    .report-period { margin: 7px 0 0; color: var(--text-muted); font-size: 13px; }
    .statement-actions { display: flex; align-items: center; justify-content: flex-end; gap: 8px; }
    .report-action { min-height: 44px; padding: 8px 12px; font-size: 12.5px; }
    .report-action svg { width: 14px; height: 14px; flex: 0 0 auto; }

    .statement-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); border-bottom: 1px solid var(--border-card); background: var(--surface); }
    .summary-item { min-width: 0; padding: 14px 18px 13px; border-right: 1px solid var(--border-card); }
    .summary-item:last-child { border-right: 0; }
    .summary-item.is-result, .summary-item.is-alert { background: var(--mint-surface); }
    .summary-label { display: block; margin-bottom: 5px; color: var(--text-muted); font-size: 10.5px; font-weight: 750; letter-spacing: .075em; text-transform: uppercase; }
    .summary-value { display: block; overflow-wrap: anywhere; color: var(--text-main); font-family: var(--font-mono); font-size: clamp(17px, 1.65vw, 23px); font-weight: 700; font-variant-numeric: tabular-nums lining-nums; letter-spacing: -.035em; line-height: 1.15; }
    .summary-item.is-result .summary-value, .summary-item.is-alert .summary-value { color: var(--primary-hover); }

    .report-toolbar { padding: 12px 18px; border-bottom: 1px solid var(--border-card); background: var(--surface); }
    .report-filter-form { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
    .filter-cluster, .date-cluster, .preset-cluster { display: flex; align-items: end; gap: 8px; min-width: 0; }
    .filter-cluster { flex-wrap: wrap; }
    .filter-caption { align-self: center; margin-right: 2px; color: var(--text-caption); font-size: 10.5px; font-weight: 750; letter-spacing: .07em; text-transform: uppercase; }
    .period-presets { display: flex; align-items: center; gap: 4px; }
    .preset-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; padding: 6px 10px; border: 1px solid transparent; border-radius: var(--radius-control); color: var(--text-muted); font-size: 12px; font-weight: 650; text-decoration: none; white-space: nowrap; }
    .preset-btn:hover { border-color: var(--mint-border); background: var(--mint-surface); color: var(--primary-hover); }
    .preset-btn.active { border-color: var(--primary); background: var(--primary); color: #fff; }
    .custom-period { position: relative; }
    .custom-period > summary { display: inline-flex; align-items: center; min-height: 40px; padding: 7px 10px; border: 1px solid var(--border-control); border-radius: var(--radius-control); color: var(--text-muted); font-size: 12px; font-weight: 650; cursor: pointer; list-style: none; white-space: nowrap; }
    .custom-period > summary::-webkit-details-marker { display: none; }
    .custom-period > summary::after { content: ''; width: 6px; height: 6px; margin-left: 9px; border-right: 1.5px solid currentColor; border-bottom: 1.5px solid currentColor; transform: translateY(-2px) rotate(45deg); }
    .custom-period[open] > summary { border-color: var(--primary); color: var(--primary-hover); }
    .custom-period[open] > summary::after { transform: translateY(2px) rotate(225deg); }
    .custom-period .date-cluster { position: absolute; z-index: 5; top: calc(100% + 7px); right: 0; min-width: max-content; padding: 12px; border: 1px solid var(--border-control); border-radius: var(--radius-control); background: var(--surface); box-shadow: 0 14px 28px rgba(20, 53, 38, .16); }
    .field { display: flex; flex-direction: column; gap: 4px; }
    .field label { color: var(--text-caption); font-size: 10px; font-weight: 750; letter-spacing: .06em; text-transform: uppercase; }
    .field input[type="date"] { width: 145px; min-height: 40px; padding: 7px 9px; border: 1px solid var(--border-control); border-radius: var(--radius-control); background: var(--surface); color: var(--text-main); font-size: 12px; }
    .range-sep { align-self: center; color: var(--text-caption); font-size: 11px; }
    .report-apply { min-height: 40px; padding: 7px 14px; font-size: 12px; }
    .report-reset { display: inline-flex; align-items: center; min-height: 40px; padding: 7px 8px; color: var(--text-muted); font-size: 12px; font-weight: 650; text-decoration: underline; text-underline-offset: 3px; }
    .report-reset:hover { color: var(--primary-hover); }

    .ledger-caption { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; padding: 9px 18px 6px; }
    .ledger-caption strong { color: var(--text-main); font-size: 13px; font-weight: 750; }
    .ledger-caption span { color: var(--text-caption); font-size: 11px; }
    .report-ledger { width: 100%; overflow-x: auto; overscroll-behavior-x: contain; background: var(--surface); }
    .report-table { width: 100%; min-width: 640px; border-collapse: collapse; }
    .report-table thead th { position: sticky; z-index: 2; top: 0; padding: 7px 18px; border-top: 1px solid var(--primary-hover); border-bottom: 1px solid var(--primary-hover); background: var(--surface); color: var(--text-muted); font-size: 10.5px; font-weight: 750; letter-spacing: .075em; text-align: left; text-transform: uppercase; }
    .report-table thead th.num { text-align: right; }
    .report-table th.section-title { padding: 9px 18px 5px; border-top: 1px solid var(--border-control); border-bottom: 1px solid var(--border-card); background: transparent; color: var(--text-main); font-size: 11px; font-weight: 800; letter-spacing: .07em; text-align: left; text-transform: uppercase; }
    .report-table td { padding: 6px 18px; border-bottom: 1px solid var(--border-subtle); color: var(--text-main); font-size: 13px; line-height: 1.25; }
    .report-table td.code { width: 104px; color: var(--text-muted); font-family: var(--font-mono); font-size: 11.5px; font-variant-numeric: tabular-nums; }
    .report-table .sku-code { padding: 0; border: 0; background: transparent; color: inherit; font: inherit; }
    .report-table td.amount { width: 250px; color: var(--text-main); font-family: var(--font-mono); font-size: 12.5px; font-weight: 600; text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums lining-nums; }
    .report-table td.amount.neg { color: var(--text-main); font-weight: 700; }
    .report-table tr.total td { padding-top: 7px; padding-bottom: 7px; border-top: 1px solid var(--border-control); border-bottom: 3px double var(--border-control); background: var(--surface); font-weight: 750; }
    .report-table tr.grand td { padding-top: 8px; padding-bottom: 8px; border-top: 2px solid var(--primary-hover); border-bottom: 3px double var(--primary-hover); background: var(--mint-surface); color: var(--primary-hover); font-weight: 800; }
    .report-table tbody tr:not(.grand):not(.total):not(:has(.section-title)):hover td { background: var(--surface-hover); }

    @media (max-width: 1050px) {
        .report-filter-form { align-items: center; }
        .filter-cluster { width: 100%; }
    }

    @media (max-width: 720px) {
        .report-shell { width: calc(100% + 24px); margin-inline: -12px; border-inline: 0; border-radius: 0; box-shadow: none; }
        .statement-header { grid-template-columns: 1fr; gap: 12px; padding: 15px 16px 12px; }
        .statement-actions { justify-content: stretch; }
        .statement-actions > * { flex: 1 1 0; }
        .statement-summary { grid-template-columns: 1fr; }
        .summary-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: baseline; gap: 10px; padding: 9px 16px; border-right: 0; border-bottom: 1px solid var(--border-card); }
        .summary-item:last-child { border-bottom: 0; }
        .summary-label { margin: 0; }
        .summary-value { text-align: right; }
        .report-toolbar { padding: 12px 16px; }
        .report-filter-form { align-items: stretch; flex-direction: column; }
        .filter-cluster { display: flex; align-items: center; justify-content: space-between; width: 100%; }
        .preset-cluster { display: flex; align-items: center; min-width: 0; }
        .filter-caption { display: none; }
        .period-presets { overflow-x: auto; padding-bottom: 2px; }
        .preset-btn { min-height: 44px; }
        .custom-period { flex: 0 0 auto; }
        .custom-period > summary { min-height: 44px; }
        .custom-period .date-cluster { position: fixed; z-index: 30; inset: auto 12px 12px; display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); min-width: 0; padding: 14px; border-radius: 6px; }
        .field input[type="date"] { width: 100%; min-height: 44px; }
        .range-sep { display: none; }
        .report-apply, .report-reset { min-height: 44px; justify-content: center; }
        .ledger-caption { align-items: center; padding: 8px 16px 5px; }
        .ledger-caption strong { white-space: nowrap; }
        .ledger-caption span { font-size: 9.5px; text-align: right; }
        .report-table { min-width: 0; table-layout: fixed; }
        .report-table thead th, .report-table td, .report-table th.section-title { padding-inline: 10px; }
        .report-table thead th:first-child, .report-table td.code { width: 54px !important; }
        .report-table thead th.num, .report-table td.amount { width: 126px; }
        .report-table td { font-size: 11.5px; }
        .report-table td.code { font-size: 10px; }
        .report-table td.amount { font-size: 10.5px; }
    }

    @media print {
        header, nav, .sidebar-wrapper, .topbar, .report-toolbar, .breadcrumb, .no-print { display: none !important; }
        .app-layout { display: block !important; }
        .main-wrapper { margin: 0 !important; }
        main { max-width: 100% !important; padding: 0 !important; }
        .card, .report-shell { padding: 0 !important; border: 0 !important; box-shadow: none !important; }
        .report-shell { animation: none !important; }
        .statement-header { padding: 0 0 12px !important; }
        .statement-summary { border: 1px solid #777 !important; }
        .summary-item, .report-table tr { break-inside: avoid; page-break-inside: avoid; }
        .report-table thead { display: table-header-group; }
        .ledger-caption { padding-inline: 0 !important; }
        .report-ledger { overflow: visible !important; }
        .report-table { min-width: 0 !important; }
    }
</style>
