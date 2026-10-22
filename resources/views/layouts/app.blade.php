<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir Material &amp; Bangunan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --font-sans: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
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
            --text-caption: #8a9990;

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
            --sidebar-label: #8a9990;

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
            min-height: 100vh;
            width: 100%;
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
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }

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
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            min-height: 42px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 9px 14px;
            border-radius: 12px;
            transition: all 0.15s ease;
        }

        .sidebar-nav a svg {
            color: var(--sidebar-label);
            transition: color 0.15s ease;
            flex-shrink: 0;
        }

        .sidebar-nav a:hover {
            background-color: var(--sidebar-hover-bg);
            color: var(--primary-hover);
        }

        .sidebar-nav a:hover svg {
            color: var(--primary);
        }

        .sidebar-nav a.active {
            background-color: var(--sidebar-active-bg);
            color: var(--sidebar-active-text);
            font-weight: 700;
            box-shadow: inset 3.5px 0 0 var(--primary);
        }

        .sidebar-nav a.active svg {
            color: var(--primary);
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--border-subtle);
            display: flex;
            flex-direction: column;
            gap: 12px;
            background-color: #fbfdfc;
        }

        .operator-card {
            display: flex;
            align-items: center;
            gap: 12px;
            background-color: var(--mint-surface);
            border: 1px solid var(--mint-border);
            padding: 10px 14px;
            border-radius: 14px;
        }

        .operator-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2d6a4f, #52b788);
            color: #ffffff;
            font-weight: 700;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .operator-meta {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .operator-title {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--primary-hover);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .operator-desc {
            font-size: 11px;
            color: var(--accent);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .operator-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: #22c55e;
            display: inline-block;
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
            min-height: 100vh;
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

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .topbar-btn-new {
            background-color: var(--primary);
            color: #ffffff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 9px 18px;
            border-radius: var(--radius-control);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
            box-shadow: 0 2px 8px rgba(45, 106, 79, 0.2);
        }

        .topbar-btn-new:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        .topbar-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 600;
            color: var(--primary-hover);
            background-color: var(--mint-surface);
            border: 1px solid var(--mint-border);
            padding: 7px 14px;
            border-radius: var(--radius-control);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #22c55e;
            box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.2);
        }

        /* ─── CROSS-DOCUMENT VIEW TRANSITIONS & PAGE MORPHING ─────────── */
        @view-transition {
            navigation: auto;
        }

        ::view-transition-old(root) {
            animation: 90ms cubic-bezier(0.4, 0, 1, 1) both fadeOut;
        }

        ::view-transition-new(root) {
            animation: 220ms cubic-bezier(0, 0, 0.2, 1) both fadeIn,
                       240ms cubic-bezier(0.16, 1, 0.3, 1) both slideUp;
        }

        @keyframes fadeOut {
            to { opacity: 0; }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
        }

        @keyframes slideUp {
            from { transform: translateY(12px) scale(0.995); }
            to { transform: translateY(0) scale(1); }
        }

        @keyframes pageMorphIn {
            0% {
                opacity: 0;
                transform: translateY(14px) scale(0.992);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .main-content {
            padding: 28px;
            flex: 1;
            max-width: 1360px;
            width: 100%;
            margin: 0 auto;
            animation: pageMorphIn 0.28s cubic-bezier(0.16, 1, 0.3, 1) both;
            will-change: transform, opacity;
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
            border-radius: var(--radius-control);
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

        /* ─── MODAL POPUP DIALOGS (Fluid Spring Morphing) ───────────────── */
        .modal-overlay {
            display: flex;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1),
                        visibility 0.25s,
                        backdrop-filter 0.25s ease;
            will-change: opacity, backdrop-filter;
        }

        .modal-overlay.open {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .modal-dialog {
            background-color: var(--surface);
            border: 1px solid rgba(45, 106, 79, 0.12);
            border-radius: 28px;
            box-shadow: 0 24px 48px -12px rgba(27, 67, 50, 0.22), 0 8px 16px -4px rgba(0, 0, 0, 0.05), inset 0 1px 1px rgba(255, 255, 255, 0.9);
            width: 100%;
            max-width: 520px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            transform: scale(0.92) translateY(20px);
            opacity: 0;
            transition: transform 0.32s cubic-bezier(0.34, 1.45, 0.7, 1),
                        border-radius 0.32s cubic-bezier(0.16, 1, 0.3, 1),
                        opacity 0.22s ease;
            will-change: transform, opacity, border-radius;
        }

        .modal-overlay.open .modal-dialog {
            transform: scale(1) translateY(0);
            border-radius: var(--radius-modal);
            opacity: 1;
        }

        /* ─── MORPHING BUTTONS & PILL CONTROLS ──────────────────────────── */
        .btn, .topbar-btn-new, .btn-pill-submit, .btn-action-edit, .btn-action-delete, .filter-tab, .category-chip-card {
            transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1),
                        box-shadow 0.2s ease,
                        background-color 0.15s ease,
                        border-color 0.15s ease,
                        border-radius 0.25s ease;
            will-change: transform;
        }

        .btn:hover, .topbar-btn-new:hover, .btn-pill-submit:hover {
            transform: translateY(-2px) scale(1.02);
        }

        .btn:active, .topbar-btn-new:active, .btn-pill-submit:active {
            transform: translateY(0) scale(0.97);
        }

        .btn-action-edit:hover, .btn-action-delete:hover, .filter-tab:hover {
            transform: translateY(-1.5px) scale(1.03);
        }

        .sidebar-nav a {
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1),
                        background-color 0.15s ease,
                        color 0.15s ease;
        }

        .sidebar-nav a:hover {
            transform: translateX(4px);
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            background-color: var(--surface);
            z-index: 2;
            border-top-left-radius: var(--radius-modal);
            border-top-right-radius: var(--radius-modal);
        }

        .modal-title {
            font-size: 16.5px;
            font-weight: 800;
            color: var(--primary-hover);
            letter-spacing: -0.01em;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-title::before {
            content: "";
            width: 4px;
            height: 16px;
            background-color: var(--accent);
            border-radius: 2px;
            display: inline-block;
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
            font-size: 18px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .modal-close-btn:hover {
            background-color: var(--mint-surface);
            border-color: var(--mint-border);
            color: var(--primary);
        }

        .modal-body {
            padding: 24px 26px;
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
        <aside id="app-sidebar" class="app-sidebar" aria-label="Navigasi Utama">
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
                <div class="operator-card">
                    <div class="operator-avatar">OK</div>
                    <div class="operator-meta">
                        <span class="operator-title">Operator Kasir</span>
                        <span class="operator-desc"><span class="operator-dot"></span> Terminal Aktif</span>
                    </div>
                </div>
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
                    <div class="topbar-search-wrapper">
                        <svg class="topbar-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" class="topbar-search-input" placeholder="Cari material, SKU, nota, atau armada..." aria-label="Pencarian Cepat">
                    </div>
                </div>
                <div class="topbar-right">
                    <span class="topbar-status"><span class="status-dot"></span> Sistem Siap</span>
                    <a href="{{ route('pos.index') }}" class="topbar-btn-new">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        <span>Transaksi Baru</span>
                    </a>
                </div>
            </header>

            <main class="main-content">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-error">
                        <ul style="margin: 0; padding-left: 18px;">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
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

        // Global Modal Controls
        function openModal(id) {
            var modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
            var firstInput = modal.querySelector('input:not([type=hidden]):not([readonly]), select, textarea');
            if (firstInput) {
                setTimeout(function () { firstInput.focus(); }, 60);
            }
        }

        function closeModal(id) {
            var modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.remove('open');
            document.body.style.overflow = '';
        }
    </script>
</body>
</html>
