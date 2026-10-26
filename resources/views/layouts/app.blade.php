<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir Material &amp; Bangunan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --font-sans: 'Sora', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --font-mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;

            --canvas: #f3f6f4;
            --surface: #ffffff;
            --surface-hover: #fafdfb;
            --primary: #2d6a4f;
            --primary-hover: #1b4332;
            --primary-light: #d8f3dc;
            --accent: #52b788;
            --mint-surface: #e8f5e9;
            --mint-border: #d8f3dc;

            --text-main: #1c2826;
            --text-muted: #5c6b64;
            --text-caption: #526359;

            --border-card: #e7eee9;
            --border-control: #d9e2dc;
            --border-subtle: #f0f4f1;

            --danger: #b02a37;
            --danger-bg: #fde8e8;
            --danger-border: #f5c2c7;
            --danger-text: #842029;

            --sidebar-bg: #ffffff;
            --sidebar-border: #e7eee9;
            --sidebar-text: #4a5568;
            --sidebar-active-bg: #e8f5e9;
            --sidebar-active-text: #1b4332;
            --sidebar-hover-bg: #f4f8f5;
            --sidebar-label: #526359;

            /* Mathematical Concentric Radius Hierarchy (Linear/Fulcrum Style) */
            --radius-window: 24px;
            --radius-modal: 20px;
            --radius-card: 16px;
            --radius-inner: 10px;
            --radius-control: 9px;
            --radius-badge: 6px;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            width: 100%;
            min-height: 100vh;
            background-color: var(--canvas);
            color: var(--text-main);
            font-family: var(--font-sans);
            font-size: 14px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overflow-x: hidden;
        }

        .app-layout {
            display: flex;
            height: 100vh;
            width: 100%;
            overflow: hidden;
        }

        @media (min-width: 901px) {
            .app-layout { position: relative; }
            .app-sidebar {
                position: absolute;
                inset: 0 auto 0 0;
                height: 100%;
                overflow-y: auto;
            }
            .app-main {
                flex: none;
                width: calc(100% - 260px);
                margin-left: 260px;
            }
        }

        /* ─── SIDEBAR (Clean White SaaS Rail like Hireism) ──────────────── */
        .app-sidebar {
            width: 260px;
            min-width: 260px;
            flex-shrink: 0;
            background-color: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            display: flex;
            flex-direction: column;
            position: relative;
            height: 100%;
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
            z-index: 100;
        }
        .app-sidebar::-webkit-scrollbar { display: none; }

        .sidebar-header {
            padding: 24px 20px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-subtle);
        }

        .sidebar-brand-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .brand-icon-badge {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(45, 106, 79, 0.25);
            flex-shrink: 0;
        }

        .sidebar-brand-text {
            display: flex;
            flex-direction: column;
        }

        .sidebar-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--primary-hover);
            letter-spacing: -0.01em;
            line-height: 1.2;
        }

        .sidebar-subtitle {
            font-size: 10.5px;
            font-weight: 700;
            color: var(--accent);
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .sidebar-close-btn {
            display: none;
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 6px;
            border-radius: 8px;
        }

        .sidebar-close-btn:hover {
            background-color: var(--sidebar-hover-bg);
            color: var(--primary);
        }

        .sidebar-nav {
            padding: 20px 14px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .nav-group {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .nav-group-label {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--sidebar-label);
            padding: 0 12px 6px;
        }

        .sidebar-nav a {
            color: #64748b;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            min-height: 38px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: var(--radius-control);
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        .sidebar-nav a svg {
            color: #94a3b8;
            transition: color 0.15s ease;
            flex-shrink: 0;
        }

        .sidebar-nav a:hover:not(.active) {
            background-color: rgba(20, 50, 35, 0.04);
            color: #1c2826;
        }

        .sidebar-nav a:hover:not(.active) svg {
            color: #475569;
        }

        .sidebar-nav a.active {
            background-color: rgba(45, 106, 79, 0.08);
            color: #1b4332;
            font-weight: 600;
        }

        .sidebar-nav a.active svg {
            color: #1b4332;
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--border-subtle);
            display: flex;
            flex-direction: column;
            gap: 12px;
            background-color: #fbfdfc;
        }

        .sidebar-logout-btn {
            background-color: #ffffff;
            color: var(--primary-hover);
            border: 1px solid var(--mint-border);
            min-height: 36px;
            padding: 8px 14px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 9999px;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.15s ease;
        }

        .sidebar-logout-btn:hover {
            background-color: var(--mint-surface);
            border-color: var(--accent);
        }

        /* ─── MAIN CONTENT AREA ─────────────────────────────────────────── */
        .app-main {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow-y: auto;
        }

        /* Topbar Header */
        .main-topbar {
            background-color: var(--surface);
            border-bottom: 1px solid var(--sidebar-border);
            padding: 14px 28px;
            min-height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1;
            max-width: 500px;
        }

        .hamburger-btn {
            display: none;
            background: transparent;
            border: 1px solid var(--border-control);
            color: var(--primary);
            width: 40px;
            height: 40px;
            padding: 8px;
            border-radius: 10px;
            cursor: pointer;
            align-items: center;
            justify-content: center;
        }

        .topbar-search-wrapper {
            position: relative;
            width: 100%;
        }

        .topbar-search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-caption);
            pointer-events: none;
        }

        .topbar-search-input {
            width: 100%;
            min-height: 40px;
            background-color: var(--canvas);
            border: 1px solid var(--border-control);
            border-radius: 9999px;
            padding: 8px 16px 8px 40px;
            font-family: inherit;
            font-size: 13px;
            color: var(--text-main);
            transition: all 0.15s ease;
        }

        .topbar-search-input:focus {
            outline: none;
            background-color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.12);
        }

        .topbar-location { font-size: 13px; font-weight: 600; color: var(--primary-hover); }

        /* A single semantic select remains the submitted form control. */
        .select-shell { position: relative; width: 100%; }
        .select-shell.is-enhanced > select {
            position: absolute;
            inline-size: 1px;
            block-size: 1px;
            opacity: 0;
            pointer-events: none;
            padding: 0;
            border: 0;
        }
        .select-trigger {
            display: flex;
            width: 100%;
            min-height: 44px;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 9px 14px;
            border: 1px solid var(--border-control);
            border-radius: var(--radius-control);
            background: var(--surface);
            color: var(--text-main);
            font: inherit;
            font-size: 13.5px;
            text-align: left;
            cursor: pointer;
            transition: border-color .16s ease, box-shadow .16s ease;
        }
        .select-trigger:hover { border-color: var(--primary); }
        .select-trigger:focus-visible {
            outline: 2px solid var(--primary-hover);
            outline-offset: 2px;
        }
        .select-trigger::after {
            content: '';
            width: 8px;
            height: 8px;
            flex: none;
            border-right: 2px solid var(--primary-hover);
            border-bottom: 2px solid var(--primary-hover);
            transform: translateY(-2px) rotate(45deg);
            transition: transform .16s ease;
        }
        .select-shell.is-open .select-trigger::after { transform: translateY(2px) rotate(225deg); }
        .select-options {
            position: fixed;
            max-height: min(260px, 40dvh);
            overflow-y: auto;
            padding: 4px;
            background: var(--surface);
            border: 1px solid var(--border-control);
            border-radius: var(--radius-control);
            box-shadow: 0 14px 28px rgba(20, 53, 38, .16);
            z-index: 100001;
            animation: dropdownIn .26s cubic-bezier(.34, 1.56, .64, 1);
        }
        @keyframes dropdownIn {
            from { opacity: 0; transform: translateY(-12px) scale(.85); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .select-options[hidden] { display: none; }
        .select-option {
            display: block;
            width: 100%;
            min-height: 42px;
            padding: 10px;
            border: 0;
            border-radius: var(--radius-badge);
            background: transparent;
            color: var(--text-main);
            font: inherit;
            font-size: 13px;
            text-align: left;
            cursor: pointer;
            white-space: normal;
        }
        .select-option:hover, .select-option:focus-visible, .select-option[aria-selected="true"] {
            background: var(--mint-surface);
            color: var(--primary-hover);
        }
        .select-option:focus-visible { outline: 2px solid var(--primary-hover); outline-offset: -2px; }
        .select-option[hidden] { display: none; }
        .select-option:disabled { color: var(--text-muted); cursor: not-allowed; }
        .select-shell.is-invalid .select-trigger { border-color: var(--danger); }
        @media (prefers-reduced-motion: reduce) {
            .select-trigger, .select-trigger::after { transition: none; }
        }

        /* ─── MENU TRANSITIONS: content morphs, app shell stays still ──── */
        @view-transition {
            navigation: auto;
        }

        /* Exclude the sidebar/topbar so switching menus never re-fades the frame. */
        #app-sidebar, .main-topbar, #sidebar-backdrop {
            view-transition-name: none;
        }
        .main-content {
            view-transition-name: main-content;
        }

        ::view-transition-old(main-content) {
            animation: 140ms cubic-bezier(0.4, 0, 0.2, 1) both morphOut;
        }
        ::view-transition-new(main-content) {
            animation: 260ms cubic-bezier(0.16, 1, 0.3, 1) both morphIn;
        }

        @keyframes morphOut {
            to { opacity: 0; }
        }
        @keyframes morphIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: none; }
        }

        /* Fallback for browsers without View Transitions: a single soft enter. */
        @supports not (view-transition-name: main-content) {
            .main-content {
                animation: fallbackEnter 0.24s cubic-bezier(0.16, 1, 0.3, 1) both;
            }
        }
        @keyframes fallbackEnter {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            ::view-transition-old(main-content),
            ::view-transition-new(main-content),
            .main-content { animation: none !important; }
        }

        .main-content {
            padding: 28px;
            flex: 1;
            max-width: none;
            width: 100%;
            margin: 0 auto;
        }

        /* ─── ELEVATED CARDS & COMPONENTS ───────────────────────────────── */
        .card {
            background-color: var(--surface);
            border: 1px solid var(--border-card);
            border-radius: var(--radius-card);
            box-shadow: 0 4px 20px -2px rgba(27, 67, 50, 0.05);
            padding: 24px;
            margin-bottom: 24px;
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1),
                        box-shadow 0.25s ease,
                        border-color 0.25s ease;
        }

        .card:hover {
            box-shadow: 0 10px 28px -4px rgba(27, 67, 50, 0.08);
        }

        /* ─── PAGINATION ─────────────────────────────────────────────── */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid var(--border-subtle);
        }
        .pagination-meta {
            font-size: 12.5px;
            color: var(--text-muted);
            font-variant-numeric: tabular-nums;
        }
        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .page-btn {
            display: inline-flex;
            align-items: center;
            min-height: 32px;
            padding: 4px 12px;
            border: 1px solid var(--border-control);
            border-radius: var(--radius-badge);
            background: var(--surface);
            color: var(--text-main);
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease;
        }
        a.page-btn:hover {
            background: var(--mint-surface);
            border-color: var(--mint-border);
            color: var(--primary-hover);
        }
        .page-btn.disabled {
            color: var(--text-caption);
            border-color: var(--border-subtle);
            cursor: default;
        }
        .page-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
            cursor: default;
        }

        /* ─── TABLE FILTER TOOLBAR (refined, compact, accessible) ─────────── */
        .table-filters {
            display: flex;
            align-items: center;
            gap: 8px 10px;
            flex-wrap: wrap;
            margin-bottom: 12px;
            padding: 8px 12px;
            background: #fbfdfc;
            border: 1px solid var(--border-control, #d9e2dc);
            border-radius: 10px;
        }
        .table-search {
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--border-control, #d9e2dc);
            border-radius: 8px;
            background: #ffffff;
            padding: 4px 10px;
            flex: 1 1 200px;
            min-width: 160px;
            max-width: 320px;
            min-height: 32px;
            height: 32px;
            box-sizing: border-box;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .table-search:focus-within {
            border-color: var(--primary, #2d6a4f);
            box-shadow: 0 0 0 2px rgba(45, 106, 79, .12);
        }
        .table-search svg { color: var(--text-caption); flex-shrink: 0; width: 14px; height: 14px; }
        .table-search input {
            border: 0;
            outline: none;
            background: transparent;
            flex: 1;
            min-width: 0;
            font: inherit;
            font-size: 12px;
            color: var(--text-main);
        }
        .table-perpage {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--text-caption);
            white-space: nowrap;
        }
        .table-perpage select,
        .table-perpage .form-control {
            min-height: 32px;
            height: 32px;
            padding: 2px 8px;
            font-size: 12px;
            border-radius: 8px;
            border: 1px solid var(--border-control, #d9e2dc);
            background: #ffffff;
            color: var(--text-main);
        }
        .table-perpage select:focus {
            border-color: var(--primary, #2d6a4f);
            outline: none;
            box-shadow: 0 0 0 2px rgba(45, 106, 79, .12);
        }
        .table-filters .table-reset {
            min-height: 32px;
            height: 32px;
            padding: 4px 14px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            background: var(--mint-surface, #e8f5e9);
            color: var(--primary-hover, #1b4332);
            border: 1px solid var(--mint-border, #a3b18a);
            line-height: 1;
        }
        .table-filters .table-reset:hover {
            background: var(--primary-light, #d8f3dc);
            border-color: var(--accent, #52b788);
        }
        th a.th-sort {
            color: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            transition: color 0.15s ease;
        }
        th a.th-sort:hover { color: var(--primary-hover); }
        .th-arrow { font-size: 9px; color: var(--text-caption); }

        h2 {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary-hover);
            letter-spacing: -0.015em;
            margin-top: 0;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-subtle);
            text-wrap: balance;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
            margin-top: 12px;
        }

        th {
            background-color: #f7faf8;
            color: var(--text-muted);
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-card);
            text-align: left;
        }

        td {
            padding: 13px 14px;
            border-bottom: 1px solid var(--border-subtle);
            vertical-align: middle;
            color: var(--text-main);
            transition: background-color 0.16s ease;
        }

        tr:hover td {
            background-color: var(--surface-hover);
        }

        .tabular-nums {
            font-variant-numeric: tabular-nums;
        }

        .sku-code {
            font-family: var(--font-mono);
            font-size: 12px;
            font-weight: 600;
            color: var(--primary);
            background-color: var(--mint-surface);
            padding: 3px 8px;
            border-radius: 6px;
            display: inline-block;
        }

        /* Buttons */
        .btn {
            background-color: var(--primary);
            color: #ffffff;
            border: 1px solid var(--primary);
            min-height: 42px;
            padding: 9px 20px;
            font-family: inherit;
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.15s ease;
            box-shadow: 0 2px 8px rgba(45, 106, 79, 0.2);
        }

        .btn:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        .btn-secondary {
            background-color: var(--mint-surface);
            color: var(--primary-hover);
            border: 1px solid var(--mint-border);
            border-radius: 9999px;
            min-height: 38px;
            padding: 8px 16px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border-radius: var(--radius-control);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .btn-secondary:hover {
            background-color: var(--primary-light);
            border-color: var(--accent);
        }

        /* Inline table row actions (edit / delete / locked) */
        .btn-action-edit {
            background-color: var(--mint-surface);
            color: var(--primary-hover);
            border: 1px solid var(--mint-border);
            border-radius: 6px;
            padding: 3px 10px;
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color .15s ease, border-color .15s ease, transform .15s ease;
        }
        .btn-action-edit:hover, .btn-action-edit:focus-visible { background-color: var(--primary-light); border-color: var(--accent); }
        .btn-action-edit:active { transform: scale(.96); }
        .btn-action-delete {
            background-color: #fde8e8;
            color: var(--danger-text);
            border: 1px solid var(--danger-border);
            border-radius: 6px;
            padding: 3px 10px;
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color .15s ease, transform .15s ease;
        }
        .btn-action-delete:hover, .btn-action-delete:focus-visible { background-color: #fbd5d5; }
        .btn-action-delete:active { transform: scale(.96); }
        .btn-action-disabled {
            background-color: #f3f4f6;
            color: #9ca3af;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 3px 10px;
            font-size: 11.5px;
            cursor: not-allowed;
        }
        .btn-action-locked {
            background-color: #f3f4f6;
            color: #6b7280;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 3px 10px;
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color .15s ease, color .15s ease, transform .15s ease;
        }
        .btn-action-locked:hover, .btn-action-locked:focus-visible { background-color: #e9ecef; color: #4b5563; }
        .btn-action-locked:active { transform: scale(.96); }

        /* Form Controls */
        input, select, textarea {
            min-height: 42px;
            padding: 10px 14px;
            font-family: inherit;
            font-size: 13.5px;
            color: var(--text-main);
            background-color: #ffffff;
            border: 1px solid var(--border-control);
            border-radius: var(--radius-control);
            width: 100%;
            transition: all 0.15s ease;
        }

        input:hover, select:hover, textarea:hover {
            border-color: #b5c4bc;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.15);
        }

        /* Date inputs: match the light green theme */
        input[type="date"], input[type="datetime-local"], input[type="month"] {
            color-scheme: light;
        }
        input[type="date"]::-webkit-calendar-picker-indicator,
        input[type="datetime-local"]::-webkit-calendar-picker-indicator {
            cursor: pointer;
            opacity: 0.55;
            transition: opacity 0.15s ease;
        }
        input[type="date"]::-webkit-calendar-picker-indicator:hover,
        input[type="datetime-local"]::-webkit-calendar-picker-indicator:hover {
            opacity: 1;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted);
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        /* Alerts */
        .alert {
            padding: 14px 18px;
            margin-bottom: 20px;
            border-radius: var(--radius-inner);
            font-size: 13.5px;
            line-height: 1.5;
            display: block;
        }

        .alert-success {
            background-color: var(--mint-surface);
            border: 1px solid #b7e4c7;
            color: #1b4332;
        }

        .alert-error {
            background-color: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger-text);
        }

        /* ─── Toast Notifications (Sonner-inspired stacking, bottom-right) ─── */
        #toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 1000100;
            width: min(88vw, 360px);
            pointer-events: none;
        }
        #toast-container:hover .toast { pointer-events: auto; }
        .toast-list {
            position: relative;
            display: flex;
            flex-direction: column-reverse;
            gap: 0;
        }
        .toast {
            pointer-events: auto;
            position: relative;
            overflow: hidden;
            width: 100%;
            box-sizing: border-box;
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--surface, #ffffff);
            border: 1px solid var(--border-card, #e7eee9);
            border-radius: var(--radius-control, 9px);
            padding: 12px 14px;
            box-shadow: 0 4px 16px rgba(0,0,0,.08), 0 1px 3px rgba(0,0,0,.04);
            font-family: var(--font-sans, inherit);
            font-size: 13px;
            font-weight: 500;
            line-height: 1.45;
            color: var(--text-main, #1c2826);
            transform: translateY(0) scale(1);
            transition: transform .3s cubic-bezier(.21, 1.02, .73, 1),
                        opacity .3s ease,
                        margin-bottom .3s ease;
            will-change: transform, opacity;
            margin-bottom: 8px;
        }
        .toast:last-child { margin-bottom: 0; }
        .toast[data-entering] {
            animation: toastSlideUp .35s cubic-bezier(.21, 1.02, .73, 1) forwards;
        }
        .toast-dot {
            flex-shrink: 0;
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .toast-success .toast-dot { background: var(--primary, #2d6a4f); }
        .toast-error .toast-dot { background: var(--danger, #b02a37); }
        .toast-text {
            flex: 1;
            min-width: 0;
            word-break: break-word;
        }
        .toast-close {
            background: none;
            border: none;
            font-size: 15px;
            line-height: 1;
            cursor: pointer;
            color: var(--text-caption, #526359);
            opacity: 0;
            padding: 4px;
            border-radius: var(--radius-badge, 6px);
            transition: opacity .15s ease, background .15s ease;
            flex-shrink: 0;
        }
        .toast:hover .toast-close { opacity: .6; }
        .toast-close:hover { opacity: 1 !important; background: var(--canvas, #f3f6f4); }
        .toast-action {
            flex-shrink: 0;
            background: transparent;
            color: var(--primary, #2d6a4f);
            border: 1px solid var(--border-control, #d9e2dc);
            border-radius: var(--radius-badge, 6px);
            padding: 4px 12px;
            font-family: var(--font-sans, inherit);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            min-height: 28px;
            transition: background .15s ease, border-color .15s ease;
        }
        .toast-action:hover { background: var(--mint-surface, #e8f5e9); border-color: var(--accent, #52b788); }
        .toast-progress {
            position: absolute;
            left: 0;
            bottom: 0;
            height: 2px;
            width: 100%;
            transform-origin: left;
            animation: toastProgress linear forwards;
        }
        .toast-success .toast-progress { background: var(--accent, #52b788); opacity: .35; }
        .toast-error .toast-progress { background: var(--danger, #b02a37); opacity: .25; }
        .toast-dismiss { animation: toastDismiss .25s ease forwards; }
        @keyframes toastSlideUp {
            from { opacity: 0; transform: translateY(16px) scale(.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes toastDismiss {
            to { opacity: 0; transform: translateX(100%) scale(.95); }
        }
        @keyframes toastProgress { from { transform: scaleX(1); } to { transform: scaleX(0); } }
        @media (max-width: 600px) {
            #toast-container { bottom: 16px; right: 12px; left: 12px; width: auto; }
            .toast { padding: 12px 12px; font-size: 13px; }
            .toast-close { opacity: .5; padding: 6px; min-width: 32px; min-height: 32px; display: flex; align-items: center; justify-content: center; }
            .toast-action { min-height: 32px; padding: 5px 14px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .toast, .toast-dismiss, .toast-progress, .toast[data-entering] { animation-duration: .01ms !important; transition-duration: .01ms !important; }
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
            border-radius: var(--radius-badge);
            border: 1px solid var(--mint-border);
            background-color: var(--mint-surface);
            color: var(--primary);
        }

        /* ─── MODAL POPUP DIALOGS (Centered, Viewport Clamped, Teleported) ───────────────── */
        .modal-overlay {
            display: flex;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(15, 23, 42, 0.48);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 99999;
            align-items: center;
            justify-content: center;
            padding: 24px;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.22s cubic-bezier(0.16, 1, 0.3, 1),
                        visibility 0.22s,
                        backdrop-filter 0.22s ease;
            will-change: opacity, backdrop-filter;
        }

        .modal-overlay.open {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .modal-dialog {
            background-color: var(--surface);
            border: 1px solid rgba(45, 106, 79, 0.14);
            border-radius: var(--radius-modal);
            box-shadow: 0 24px 48px -12px rgba(27, 67, 50, 0.22), 0 8px 16px -4px rgba(0, 0, 0, 0.06), inset 0 1px 1px rgba(255, 255, 255, 0.9);
            width: 100%;
            max-width: 500px;
            max-height: calc(100vh - 60px);
            display: flex;
            flex-direction: column;
            position: relative;
            transform: scale(0.92) translateY(16px);
            opacity: 0;
            transition: transform 0.28s cubic-bezier(0.34, 1.45, 0.7, 1),
                        opacity 0.2s ease;
            will-change: transform, opacity;
            overflow: hidden;
        }

        .modal-overlay.open .modal-dialog {
            transform: scale(1) translateY(0);
            opacity: 1;
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: var(--surface);
            flex-shrink: 0;
        }

        .modal-title-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-icon-badge {
            width: 38px;
            height: 38px;
            border-radius: var(--radius-control);
            background-color: var(--mint-surface);
            border: 1px solid var(--mint-border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            flex-shrink: 0;
        }

        .modal-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.01em;
            margin: 0;
        }

        .modal-close-btn {
            background: none;
            border: 1px solid transparent;
            width: 32px;
            height: 32px;
            border-radius: var(--radius-badge);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-caption);
            font-size: 20px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .modal-close-btn:hover {
            background-color: var(--mint-surface);
            border-color: var(--mint-border);
            color: var(--primary);
        }

        .modal-body {
            padding: 20px 24px;
            overflow-y: auto;
            flex: 1;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-subtle);
            background-color: var(--surface);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-shrink: 0;
        }

        /* ─── COLLAPSIBLE ICON RAIL (desktop, hover-to-expand) ─────────── */
        .sidebar-pin-btn { display: none; }

        @media (min-width: 901px) {
            .app-layout { position: relative; }
            .app-sidebar {
                position: absolute;
                inset: 0 auto 0 0;
                height: 100%;
                overflow-x: hidden;
            }
            .app-main {
                flex: none;
                width: calc(100% - 260px);
                margin-left: 260px;
            }
            .app-sidebar.collapsed ~ .app-main {
                width: calc(100% - 76px);
                margin-left: 76px;
            }

            .sidebar-pin-btn {
                display: inline-flex;
                align-items: center;
                justify-content: flex-start;
                gap: 8px;
                width: 100%;
                margin-top: 10px;
                min-height: 34px;
                padding: 6px 12px;
                border: 1px solid var(--mint-border);
                background: #ffffff;
                color: var(--primary-hover);
                border-radius: var(--radius-badge);
                font: inherit;
                font-size: 12px;
                font-weight: 600;
                cursor: pointer;
            }
            .sidebar-pin-btn:hover { background: var(--mint-surface); }
            .sidebar-pin-btn svg { flex-shrink: 0; }

            /* Labels fade out/in smoothly — they stay in layout, the rail clips them. */
            .sidebar-brand-text, .nav-group-label, .sidebar-nav a > span,
            .sidebar-logout-btn > span, .sidebar-pin-btn > span {
                white-space: nowrap;
                transition: opacity .18s ease;
            }

            .app-sidebar {
                width: 260px;
                min-width: 260px;
                transition: width .28s cubic-bezier(.22, 1, .36, 1),
                            min-width .28s cubic-bezier(.22, 1, .36, 1);
            }
            .app-sidebar.collapsed {
                width: 76px;
                min-width: 76px;
            }
            .app-sidebar.collapsed .sidebar-brand-text,
            .app-sidebar.collapsed .nav-group-label,
            .app-sidebar.collapsed .sidebar-nav a > span,
            .app-sidebar.collapsed .sidebar-logout-btn > span,
            .app-sidebar.collapsed .sidebar-pin-btn > span {
                opacity: 0;
                pointer-events: none;
            }

            @media (hover: hover) {
                .app-sidebar.collapsed:hover {
                    width: 260px;
                    min-width: 260px;
                }
                .app-sidebar.collapsed:hover .sidebar-brand-text,
                .app-sidebar.collapsed:hover .nav-group-label,
                .app-sidebar.collapsed:hover .sidebar-nav a > span,
                .app-sidebar.collapsed:hover .sidebar-logout-btn > span,
                .app-sidebar.collapsed:hover .sidebar-pin-btn > span {
                    opacity: 1;
                }
            }
        }

        /* ─── RESPONSIVE MOBILE ─────────────────────────────────────────── */
        @media (max-width: 900px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }

            .app-sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                width: 270px;
                max-width: 82vw;
                height: 100vh;
                transform: translateX(-100%);
                transition: transform 0.25s ease-in-out;
                box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
            }

            .app-sidebar.open {
                transform: translateX(0);
            }

            .sidebar-close-btn {
                display: inline-flex;
            }

            .sidebar-backdrop {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.45);
                z-index: 95;
                opacity: 0;
                transition: opacity 0.25s ease;
            }

            .sidebar-backdrop.open {
                display: block;
                opacity: 1;
            }

            .hamburger-btn {
                display: inline-flex;
            }

            .main-topbar {
                padding: 12px 18px;
            }

            .main-content {
                padding: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar Backdrop for Mobile -->
        <div id="sidebar-backdrop" class="sidebar-backdrop" aria-hidden="true"></div>

        <!-- Sidebar Navigation (Clean White SaaS Rail) -->
        <aside id="app-sidebar" class="app-sidebar collapsed" aria-label="Navigasi Utama">
            <div class="sidebar-header">
                <a href="{{ route('pos.index') }}" class="sidebar-brand-wrapper">
                    <div class="brand-icon-badge">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                            <line x1="8" y1="21" x2="16" y2="21"></line>
                            <line x1="12" y1="17" x2="12" y2="21"></line>
                        </svg>
                    </div>
                    <div class="sidebar-brand-text">
                        <h1 class="sidebar-title">Kasir Bangunan</h1>
                        <span class="sidebar-subtitle">Terminal Material</span>
                    </div>
                </a>
                <button type="button" id="sidebar-close" class="sidebar-close-btn" aria-label="Tutup Menu">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <nav class="sidebar-nav">
                <!-- Kategori: TRANSAKSI -->
                <div class="nav-group">
                    <div class="nav-group-label">Transaksi</div>
                    <a href="{{ route('pos.index') }}" class="{{ request()->routeIs('pos.*') ? 'active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        <span>Kasir (POS)</span>
                    </a>
                    <a href="{{ route('receivables.index') }}" class="{{ request()->routeIs('receivables.*') ? 'active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                            <path d="M2 10h20"></path>
                            <path d="M7 15h3"></path>
                        </svg>
                        <span>Daftar Piutang</span>
                    </a>
                </div>

                <!-- Kategori: INVENTORI -->
                <div class="nav-group">
                    <div class="nav-group-label">Inventori</div>
                    <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.*') ? 'active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m7.5 4.27 9 5.15"></path>
                            <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path>
                            <path d="m3.3 7 8.7 5 8.7-5"></path>
                            <path d="M12 22V12"></path>
                        </svg>
                        <span>Produk</span>
                    </a>
                    <a href="{{ route('inventory.receipts') }}" class="{{ request()->routeIs('inventory.*') ? 'active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 11 12 14 22 4"></polyline>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                        </svg>
                        <span>Penerimaan Barang</span>
                    </a>
                    <a href="{{ route('opname.index') }}" class="{{ request()->routeIs('opname.*') ? 'active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                            <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                            <path d="M9 14h6"></path>
                            <path d="M9 10h6"></path>
                            <path d="M9 18h6"></path>
                        </svg>
                        <span>Stock Opname</span>
                    </a>
                </div>

                <!-- Kategori: OPERASIONAL -->
                <div class="nav-group">
                    <div class="nav-group-label">Operasional</div>
                    <a href="{{ route('vehicles.index') }}" class="{{ request()->routeIs('vehicles.*') ? 'active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="1" y="3" width="15" height="13"></rect>
                            <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                            <circle cx="5.5" cy="18.5" r="2.5"></circle>
                            <circle cx="18.5" cy="18.5" r="2.5"></circle>
                        </svg>
                        <span>Armada Kendaraan</span>
                    </a>
                    <a href="{{ route('ritase.index') }}" class="{{ request()->routeIs('ritase.*') ? 'active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <span>Ritase</span>
                    </a>
                    <a href="{{ route('expenses.index') }}" class="{{ request()->routeIs('expenses.*') ? 'active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="1" x2="12" y2="23"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                        <span>Pengeluaran</span>
                    </a>
                </div>

                <!-- Kategori: LAPORAN -->
                <div class="nav-group">
                    <div class="nav-group-label">Laporan</div>
                    <a href="{{ route('reports.profit_loss') }}" class="{{ request()->routeIs('reports.profit_loss') ? 'active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                        <span>Laba Rugi</span>
                    </a>
                    <a href="{{ route('reports.balance_sheet') }}" class="{{ request()->routeIs('reports.balance_sheet') ? 'active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        <span>Neraca</span>
                    </a>
                    <a href="{{ route('journals.index') }}" class="{{ request()->routeIs('journals.*') ? 'active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16v16H4z"></path>
                            <path d="M8 8h8"></path><path d="M8 12h3"></path><path d="M13 12h3"></path><path d="M8 16h3"></path><path d="M13 16h3"></path>
                        </svg>
                        <span>Jurnal Umum</span>
                    </a>
                    <a href="{{ route('coa.index') }}" class="{{ request()->routeIs('coa.*') ? 'active' : '' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"></path>
                            <path d="M6 6h10"></path>
                            <path d="M6 10h10"></path>
                            <path d="M6 14h10"></path>
                        </svg>
                        <span>Bagan Akun (COA)</span>
                    </a>
                </div>
            </nav>

            <div class="sidebar-footer">
                @auth
                <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                    @csrf
                    <button type="submit" class="sidebar-logout-btn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        <span>Keluar</span>
                    </button>
                </form>
                @endauth
                <button type="button" id="sidebar-pin" class="sidebar-pin-btn" aria-pressed="false" aria-label="Kunci menu tetap terbuka">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="11 17 6 12 11 7"></polyline><polyline points="18 17 13 12 18 7"></polyline></svg>
                    <span>Ciutkan Menu</span>
                </button>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="app-main">
            <header class="main-topbar">
                <div class="topbar-left">
                    <button type="button" id="sidebar-toggle" class="hamburger-btn" aria-label="Buka Menu">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="3" y1="12" x2="21" y2="12"></line>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <line x1="3" y1="18" x2="21" y2="18"></line>
                        </svg>
                    </button>
                    <span class="topbar-location">{{ request()->routeIs('pos.*') ? 'Kasir' : (request()->routeIs('ritase.*') ? 'Ritase' : (request()->routeIs('opname.*') ? 'Stok Opname' : (request()->routeIs('inventory.receipts*') ? 'Penerimaan Barang' : 'Kasir Bangunan'))) }}</span>
                </div>
            </header>

            <main class="main-content">
                @yield('content')
            </main>
        </div>
    </div>

    <!-- Modals Portal Area (at root of <body>, completely outside transformed containers) -->
    @yield('modals')

    <!-- Toast notification container (Sonner-style stacking) -->
    <div id="toast-container" aria-live="polite" aria-atomic="false"><div class="toast-list"></div></div>

    <!-- Reusable "Aksi Terkunci" info dialog (shared across CRUD tables) -->
    <div class="modal-overlay" id="lock-info-overlay" role="dialog" aria-modal="true" aria-labelledby="lock-info-title">
        <div class="modal-dialog" style="max-width: 420px;">
            <div class="modal-header">
                <div class="modal-title-wrapper">
                    <div class="modal-icon-badge">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="modal-title" id="lock-info-title">Aksi Terkunci</h3>
                        <div style="font-size: 12px; color: var(--text-caption); margin-top: 2px;">Proteksi Integritas Data</div>
                    </div>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeModal('lock-info-overlay')" aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body" style="padding: 20px 24px;">
                <p id="lock-info-body" style="margin: 0; font-size: 13.5px; line-height: 1.6; color: var(--text-main);"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('lock-info-overlay')">Mengerti</button>
            </div>
        </div>
    </div>

    <script>
    window.__notify = { success: @json(session('success')), error: @json(session('error')), errors: @json($errors->any() ? $errors->all() : []) };
    (function () {
        function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
        function showToast(msg, type) {
            if (!msg) return;
            var list = document.querySelector('#toast-container .toast-list');
            if (!list) return;
            var el = document.createElement('div');
            el.className = 'toast toast-' + type;
            el.setAttribute('role', type === 'error' ? 'alert' : 'status');
            el.setAttribute('data-entering', '');
            var DURATION = type === 'error' ? 6000 : 4000;
            // esc() sanitizes all user content; dot/text/close are hardcoded safe HTML
            el.innerHTML = '<span class="toast-dot"></span>'
                + '<span class="toast-text">' + esc(msg) + '</span>'
                + '<button class="toast-close" aria-label="Tutup">\u00d7</button>'
                + '<span class="toast-progress" style="animation-duration:' + DURATION + 'ms;"></span>';
            el.querySelector('.toast-close').onclick = function() { dismiss(el); };
            list.prepend(el);
            // Remove entering attr after animation
            setTimeout(function() { el.removeAttribute('data-entering'); }, 360);
            // Auto-dismiss
            var timer = setTimeout(function () { dismiss(el); }, DURATION);
            // Pause on hover
            el.addEventListener('mouseenter', function() { clearTimeout(timer); var bar = el.querySelector('.toast-progress'); if (bar) bar.style.animationPlayState = 'paused'; });
            el.addEventListener('mouseleave', function() { var bar = el.querySelector('.toast-progress'); if (bar) bar.style.animationPlayState = 'running'; timer = setTimeout(function() { dismiss(el); }, 2000); });
        }
        function dismiss(el) {
            if (!el.isConnected) return;
            el.classList.add('toast-dismiss');
            setTimeout(function () { el.remove(); }, 260);
        }
        document.addEventListener('DOMContentLoaded', function () {
            var f = window.__notify || {};
            if (f.success) showToast(f.success, 'success');
            if (f.error) showToast(f.error, 'error');
            (f.errors || []).forEach(function (e) { showToast(e, 'error'); });
        });
    })();
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Automatically teleport all .modal-overlay directly under <body> so position:fixed is never trapped by transformed parents
            document.querySelectorAll('.modal-overlay').forEach(function (m) {
                if (m.parentElement !== document.body) {
                    document.body.appendChild(m);
                }
            });

            var toggle = document.getElementById('sidebar-toggle');
            var sidebar = document.getElementById('app-sidebar');
            var backdrop = document.getElementById('sidebar-backdrop');
            var closeBtn = document.getElementById('sidebar-close');

            function openSidebar() {
                if (sidebar) sidebar.classList.add('open');
                if (backdrop) backdrop.classList.add('open');
            }

            function closeSidebar() {
                if (sidebar) sidebar.classList.remove('open');
                if (backdrop) backdrop.classList.remove('open');
            }

            if (toggle) toggle.addEventListener('click', openSidebar);
            if (backdrop) backdrop.addEventListener('click', closeSidebar);
            if (closeBtn) closeBtn.addEventListener('click', closeSidebar);

            // Collapsible icon rail: default collapsed, hover expands, pin keeps open.
            var pinBtn = document.getElementById('sidebar-pin');
            function setSidebarPinned(pinned) {
                if (sidebar) sidebar.classList.toggle('collapsed', !pinned);
                if (pinBtn) pinBtn.setAttribute('aria-pressed', pinned ? 'true' : 'false');
                try { localStorage.setItem('kasirSidebarPinned', pinned ? '1' : '0'); } catch (e) {}
            }
            setSidebarPinned(localStorage.getItem('kasirSidebarPinned') === '1');
            if (pinBtn) pinBtn.addEventListener('click', function () {
                setSidebarPinned(sidebar.classList.contains('collapsed'));
            });

            // Close modal on Escape key
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.modal-overlay.open').forEach(function (m) {
                        m.classList.remove('open');
                        document.body.style.overflow = '';
                    });
                }
            });

            // Close modal on backdrop click
            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('modal-overlay')) {
                    e.target.classList.remove('open');
                    document.body.style.overflow = '';
                }
            });
        });

        // Theme-native single-select: the real select owns form validation and submitted values.
        (function () {
            let current = null;
            let optionsPanel = null;
            let triggerButton = null;
            let panelIndex = 0;
            function closePicker(restoreFocus) {
                if (!current) return;
                current.classList.remove('is-open');
                triggerButton.setAttribute('aria-expanded', 'false');
                optionsPanel.hidden = true;
                optionsPanel.style.display = 'none';
                if (restoreFocus) triggerButton.focus();
                current = null;
            }
            function positionPicker() {
                if (!current) return;
                const rect = triggerButton.getBoundingClientRect();
                const below = window.innerHeight - rect.bottom;
                const height = Math.min(optionsPanel.scrollHeight, 260, window.innerHeight * .4);
                const width = Math.min(rect.width, window.innerWidth - 24);
                optionsPanel.style.width = width + 'px';
                optionsPanel.style.left = Math.max(12, Math.min(rect.left, window.innerWidth - width - 12)) + 'px';
                optionsPanel.style.top = (below >= height + 8 || below >= rect.top ? rect.bottom + 4 : Math.max(4, rect.top - height - 4)) + 'px';
            }
            function openPicker(shell) {
                if (current === shell) { closePicker(true); return; }
                closePicker(false);
                const select = shell.querySelector('select');
                const trigger = shell.querySelector('.select-trigger');
                const panel = shell._selectOptions;
                (shell.closest('.modal-overlay') || document.body).appendChild(panel);
                panel.replaceChildren();
                const entries = [...select.options];
                if (entries.length > 8) {
                    const search = document.createElement('input');
                    search.type = 'search';
                    search.className = 'form-control';
                    search.placeholder = 'Cari pilihan...';
                    search.setAttribute('aria-label', 'Cari pilihan');
                    panel.appendChild(search);
                    search.addEventListener('input', () => {
                        panel.querySelectorAll('.select-option').forEach(option => {
                            option.hidden = !option.textContent.toLocaleLowerCase('id').includes(search.value.toLocaleLowerCase('id'));
                        });
                    });
                }
                entries.forEach((entry, index) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'select-option';
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', String(entry.selected));
                    option.textContent = entry.textContent;
                    option.disabled = entry.disabled;
                    option.addEventListener('click', () => {
                        select.selectedIndex = index;
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                        closePicker(true);
                    });
                    panel.appendChild(option);
                });
                current = shell;
                optionsPanel = panel;
                triggerButton = trigger;
                panel.hidden = false;
                panel.style.display = 'block';
                shell.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
                trigger.setAttribute('aria-controls', panel.id);
                positionPicker();
                (panel.querySelector('input') || panel.querySelector('.select-option[aria-selected="true"]:not(:disabled)') || panel.querySelector('.select-option:not(:disabled)'))?.focus();
            }
            function enhanceSelects(root) {
                root.querySelectorAll('select:not([multiple]):not([data-native-select])').forEach(select => {
                    if (select.closest('.select-shell')) return;
                    const shell = document.createElement('div');
                    shell.className = 'select-shell';
                    select.parentNode.insertBefore(shell, select);
                    shell.appendChild(select);
                    const trigger = document.createElement('button');
                    trigger.type = 'button';
                    trigger.className = 'select-trigger';
                    trigger.setAttribute('aria-haspopup', 'listbox');
                    trigger.setAttribute('aria-expanded', 'false');
                    const label = select.id ? document.querySelector('label[for="' + CSS.escape(select.id) + '"]') : null;
                    const labelText = label?.textContent.trim() || select.name.replace(/\[.*$/, '').replaceAll('_', ' ');
                    trigger.setAttribute('aria-label', labelText);
                    const panel = document.createElement('div');
                    panel.className = 'select-options';
                    panel.id = 'select-options-' + (++panelIndex);
                    panel.setAttribute('role', 'listbox');
                    panel.setAttribute('aria-label', labelText);
                    panel.hidden = true;
                    shell._selectOptions = panel;
                    shell.appendChild(trigger);
                    shell.classList.add('is-enhanced');
                    select.tabIndex = -1;
                    select.setAttribute('aria-hidden', 'true');
                    function sync() {
                        trigger.textContent = select.selectedOptions[0]?.textContent || 'Pilih...';
                        shell.classList.toggle('is-invalid', !select.validity.valid);
                    }
                    select.addEventListener('change', sync);
                    select.addEventListener('invalid', event => {
                        event.preventDefault();
                        sync();
                        trigger.focus();
                        if (current !== shell) openPicker(shell);
                    });
                    trigger.addEventListener('click', () => openPicker(shell));
                    trigger.addEventListener('keydown', event => {
                        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                            event.preventDefault();
                            if (current !== shell) openPicker(shell);
                        }
                    });
                    panel.addEventListener('keydown', event => {
                        const visible = [...panel.querySelectorAll('.select-option:not([hidden]):not(:disabled)')];
                        if (event.key === 'Escape') { event.stopPropagation(); closePicker(true); return; }
                        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                            event.preventDefault();
                            const index = visible.indexOf(document.activeElement);
                            visible[(index + (event.key === 'ArrowDown' ? 1 : -1) + visible.length) % visible.length]?.focus();
                        }
                        if (event.key === 'Enter' && event.target.tagName === 'INPUT') {
                            event.preventDefault();
                            visible[0]?.click();
                        }
                    });
                    sync();
                });
            }
            document.addEventListener('DOMContentLoaded', () => {
                enhanceSelects(document);
                new MutationObserver(records => {
                    if (records.some(record => [...record.addedNodes].some(node => node.nodeType === 1 && (node.matches?.('select') || node.querySelector?.('select'))))) {
                        enhanceSelects(document);
                    }
                }).observe(document.body, { childList: true, subtree: true });
            });
            document.addEventListener('pointerdown', event => {
                if (current && !current.contains(event.target) && !optionsPanel.contains(event.target)) closePicker(false);
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && current) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    closePicker(true);
                }
            }, true);
            window.addEventListener('resize', positionPicker);
            window.addEventListener('scroll', positionPicker, true);
            window.enhanceSelects = enhanceSelects;
        })();

        // Global Modal Controls
        function openModal(id) {
            var modal = document.getElementById(id);
            if (!modal) return;
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
            var firstInput = modal.querySelector('input:not([type=hidden]):not([readonly]), .select-trigger, textarea');
            if (firstInput) {
                setTimeout(function () { firstInput.focus(); }, 80);
            }
        }

        function closeModal(id) {
            var modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.remove('open');
            document.body.style.overflow = '';
        }

        function showLockInfo(title, message) {
            var titleEl = document.getElementById('lock-info-title');
            var bodyEl = document.getElementById('lock-info-body');
            if (titleEl) titleEl.textContent = title;
            if (bodyEl) bodyEl.textContent = message;
            openModal('lock-info-overlay');
        }

        // Global currency input formatting (Rupiah thousand-separator)
        function currencyScale(el) {
            var v = parseInt(el.getAttribute('data-currency-scale'), 10);
            return isNaN(v) ? 2 : v;
        }

        function rawToDisplay(raw, scale) {
            var s = String(raw == null ? '' : raw).trim();
            if (s === '') return '';
            var neg = s.charAt(0) === '-';
            if (neg) s = s.slice(1);
            var parts = s.split('.');
            var intPart = (parts[0] || '').replace(/\D/g, '').replace(/^0+(?=\d)/, '');
            var decPart = (parts[1] || '').replace(/\D/g, '').slice(0, scale);
            if (intPart === '') intPart = '0';
            var out = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            if (decPart.length) out += ',' + decPart;
            return (neg ? '-' : '') + out;
        }

        function formatDisplay(display, scale) {
            var s = String(display == null ? '' : display).trim();
            if (s === '') return '';
            var neg = s.charAt(0) === '-';
            s = s.replace(/[^\d,]/g, '');
            if (s === '') return '';
            var parts = s.split(',');
            var intPart = (parts[0] || '').replace(/\D/g, '').replace(/^0+(?=\d)/, '');
            var decPart = parts.slice(1).join('').replace(/\D/g, '').slice(0, scale);
            var hasComma = s.indexOf(',') !== -1;
            if (intPart === '') intPart = '0';
            var out = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            if (decPart.length) out += ',' + decPart;
            else if (hasComma) out += ',';
            return (neg ? '-' : '') + out;
        }

        function displayToRaw(display) {
            var s = String(display == null ? '' : display).trim();
            if (s === '') return '';
            var neg = s.charAt(0) === '-';
            if (neg) s = s.slice(1);
            s = s.replace(/\./g, '').replace(',', '.');
            return (neg ? '-' : '') + s;
        }

        window.setCurrencyInput = function (el, raw) {
            el.value = rawToDisplay(raw, currencyScale(el));
        };

        function initCurrencyInputs() {
            var els = document.querySelectorAll('[data-currency-input]');
            for (var i = 0; i < els.length; i++) {
                els[i].value = rawToDisplay(els[i].value, currencyScale(els[i]));
            }
        }

        document.addEventListener('input', function (e) {
            var el = e.target;
            if (!el || !el.hasAttribute) return;
            if (el.hasAttribute('data-currency-input')) {
                el.value = formatDisplay(el.value, currencyScale(el));
                try { el.setSelectionRange(el.value.length, el.value.length); } catch (err) {}
            } else if (el.hasAttribute('data-plate-input')) {
                el.value = formatPlate(el.value);
                try { el.setSelectionRange(el.value.length, el.value.length); } catch (err) {}
            }
        });

        // Vehicle plate number: canonical "B 9123 XYZ"
        function formatPlate(value) {
            var s = String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
            var full = s.match(/^([A-Z]{1,2})(\d{1,4})([A-Z]{0,3})$/);
            if (full) {
                return full[1] + ' ' + full[2] + (full[3] ? ' ' + full[3] : '');
            }
            var partial = s.match(/^([A-Z]{1,2})(\d*)$/);
            if (partial) {
                return partial[1] + (partial[2] ? ' ' + partial[2] : '');
            }
            return s;
        }

        function initPlateInputs() {
            var els = document.querySelectorAll('[data-plate-input]');
            for (var i = 0; i < els.length; i++) {
                els[i].value = formatPlate(els[i].value);
            }
        }

        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || !form.querySelectorAll) return;
            var els = form.querySelectorAll('[data-currency-input]');
            for (var i = 0; i < els.length; i++) {
                els[i].value = displayToRaw(els[i].value);
            }
        }, true);

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { initCurrencyInputs(); initPlateInputs(); });
        } else {
            initCurrencyInputs();
            initPlateInputs();
        }
    </script>
</body>
</html>
