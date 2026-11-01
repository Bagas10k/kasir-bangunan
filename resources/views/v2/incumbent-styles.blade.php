{{-- Incumbent CSS copied verbatim from layouts/app and pos/index; no legacy file modified. --}}
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

        .topbar-location { font-size: clamp(18px, 1.5vw, 22px); font-weight: 700; line-height: 1.3; letter-spacing: -.02em; color: var(--primary-hover); }

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
            padding: 10px 12px;
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
        @media (max-width: 600px) {
            .table-filters { gap: 8px; padding: 10px; }
            .table-search { flex-basis: 100%; max-width: none; }
            .table-perpage { flex: 1 1 auto; }
            .table-filters .table-reset { min-height: 40px; height: 40px; }
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
