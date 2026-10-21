<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir Material & Bangunan</title>
    <style>
        :root {
            --primary: #2d6a4f;
            --primary-hover: #1b4332;
            --accent: #52b788;
            --mint-surface: #e8f5e9;
            --mint-border: #d8f3dc;
            --canvas: #f8faf8;
            --surface: #ffffff;
            --text-main: #1c2826;
            --text-muted: #495057;
            --border-subtle: #e8f5e9;
            --border-control: #ced4da;
            --danger-bg: #fde8e8;
            --danger-border: #f5c2c7;
            --danger-text: #842029;
            --sidebar-bg: #1b4332;
            --sidebar-dark: #163828;
            --sidebar-text: #d8f3dc;
            --sidebar-hover: #2d6a4f;
            --sidebar-accent: #74c69d;
            --sidebar-indicator: #52b788;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            overflow-x: hidden;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--canvas);
            color: var(--text-main);
            font-size: 14px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* Layout Structure */
        .app-layout {
            display: flex;
            min-height: 100vh;
            width: 100%;
            overflow-x: hidden;
        }

        /* Sidebar Styling */
        .app-sidebar {
            width: 250px;
            min-width: 250px;
            flex-shrink: 0;
            background-color: var(--sidebar-bg);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            border-right: 1px solid rgba(0, 0, 0, 0.08);
        }

        .sidebar-header {
            padding: 20px 18px 16px;
            border-bottom: 1px solid rgba(216, 243, 220, 0.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-brand {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .sidebar-title {
            font-size: 16px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.5px;
            color: #ffffff;
            line-height: 1.2;
        }

        .sidebar-subtitle {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            color: var(--sidebar-accent);
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        .sidebar-close-btn {
            display: none;
            background: transparent;
            border: none;
            color: var(--sidebar-text);
            cursor: pointer;
            padding: 6px;
            border-radius: 4px;
            line-height: 0;
        }

        .sidebar-close-btn:hover {
            background-color: var(--sidebar-hover);
            color: #ffffff;
        }

        .sidebar-nav {
            padding: 16px 12px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .nav-group {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .nav-group-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--sidebar-accent);
            padding: 0 10px 6px;
        }

        .sidebar-nav a {
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            min-height: 40px;
            display: flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 6px;
            border-left: 3px solid transparent;
            transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
        }

        .sidebar-nav a:hover {
            color: #ffffff;
            background-color: var(--sidebar-hover);
            text-decoration: none;
        }

        .sidebar-nav a.active {
            color: #ffffff;
            background-color: var(--sidebar-hover);
            border-left-color: var(--sidebar-indicator);
            font-weight: 600;
        }

        .sidebar-footer {
            padding: 16px;
            background-color: var(--sidebar-dark);
            border-top: 1px solid rgba(216, 243, 220, 0.12);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .operator-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .operator-title {
            font-size: 12px;
            font-weight: 600;
            color: #ffffff;
        }

        .operator-desc {
            font-size: 11px;
            color: var(--sidebar-accent);
        }

        .sidebar-logout-btn {
            background-color: var(--primary);
            color: #ffffff;
            border: 1px solid rgba(216, 243, 220, 0.25);
            min-height: 36px;
            padding: 6px 14px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            border-radius: 6px;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }

        .sidebar-logout-btn:hover {
            background-color: var(--sidebar-hover);
            border-color: var(--sidebar-accent);
        }

        /* Backdrop */
        .sidebar-backdrop {
            display: none;
        }

        /* Main Area */
        .app-main {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .main-topbar {
            background-color: var(--surface);
            border-bottom: 1px solid var(--mint-border);
            padding: 12px 24px;
            min-height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .hamburger-btn {
            display: none;
            background: transparent;
            border: 1px solid var(--border-control);
            color: var(--primary);
            min-height: 40px;
            min-width: 40px;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            line-height: 0;
        }

        .hamburger-btn:hover {
            background-color: var(--mint-surface);
            border-color: var(--accent);
        }

        .topbar-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-main);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .topbar-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
            background-color: var(--mint-surface);
            border: 1px solid var(--mint-border);
            padding: 4px 10px;
            border-radius: 12px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--accent);
            display: inline-block;
        }

        .main-content {
            padding: 24px;
            flex: 1;
            min-width: 0;
            width: 100%;
        }

        /* Generic Components */
        .card {
            background-color: var(--surface);
            border: 1px solid var(--mint-border);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(45, 106, 79, 0.04);
            padding: 20px;
            margin-bottom: 24px;
        }

        h2 {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary-hover);
            margin-top: 0;
            border-bottom: 2px solid var(--mint-surface);
            padding-bottom: 10px;
            letter-spacing: 0.3px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-top: 10px;
        }

        th, td {
            text-align: left;
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-subtle);
            vertical-align: middle;
        }

        th {
            background-color: #f0f7f3;
            color: var(--text-main);
            font-weight: 600;
            border-bottom: 2px solid var(--mint-border);
        }

        .btn {
            background-color: var(--primary);
            color: #ffffff;
            border: 1px solid var(--primary);
            min-height: 44px;
            min-width: 44px;
            padding: 10px 18px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }

        .btn:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            color: #ffffff;
        }

        input, select, textarea {
            min-height: 44px;
            padding: 8px 12px;
            font-family: inherit;
            font-size: 14px;
            color: var(--text-main);
            background-color: var(--surface);
            border: 1px solid var(--border-control);
            border-radius: 6px;
            width: 100%;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
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
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text-main);
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .alert {
            padding: 12px 16px;
            margin-bottom: 18px;
            border-radius: 6px;
            font-size: 13px;
            line-height: 1.5;
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
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            border-radius: 4px;
            border: 1px solid var(--mint-border);
            background-color: var(--mint-surface);
            color: var(--primary);
        }

        /* Responsive Mobile Styles */
        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }

            .app-sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                width: 260px;
                max-width: 80vw;
                height: 100vh;
                transform: translateX(-100%);
                transition: transform 0.25s ease-in-out;
                z-index: 1050;
            }

            .app-sidebar.open {
                transform: translateX(0);
                box-shadow: 4px 0 16px rgba(0, 0, 0, 0.25);
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
                z-index: 1040;
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
                padding: 10px 16px;
            }

            .main-content {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar Backdrop for Mobile -->
        <div id="sidebar-backdrop" class="sidebar-backdrop" aria-hidden="true"></div>

        <!-- Sidebar Navigation -->
        <aside id="app-sidebar" class="app-sidebar" aria-label="Navigasi Utama">
            <div class="sidebar-header">
                <div class="sidebar-brand">
                    <h1 class="sidebar-title">Kasir Bangunan</h1>
                    <span class="sidebar-subtitle">Terminal Material</span>
                </div>
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
                    <a href="{{ route('pos.index') }}" class="{{ request()->routeIs('pos.*') ? 'active' : '' }}">Kasir (POS)</a>
                </div>

                <!-- Kategori: INVENTORI -->
                <div class="nav-group">
                    <div class="nav-group-label">Inventori</div>
                    <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.*') ? 'active' : '' }}">Produk</a>
                    <a href="{{ route('inventory.receipts') }}" class="{{ request()->routeIs('inventory.*') ? 'active' : '' }}">Penerimaan Barang</a>
                    <a href="{{ route('opname.index') }}" class="{{ request()->routeIs('opname.*') ? 'active' : '' }}">Stock Opname</a>
                </div>

                <!-- Kategori: OPERASIONAL -->
                <div class="nav-group">
                    <div class="nav-group-label">Operasional</div>
                    <a href="{{ route('vehicles.index') }}" class="{{ request()->routeIs('vehicles.*') ? 'active' : '' }}">Armada Kendaraan</a>
                    <a href="{{ route('ritase.index') }}" class="{{ request()->routeIs('ritase.*') ? 'active' : '' }}">Ritase</a>
                    <a href="{{ route('expenses.index') }}" class="{{ request()->routeIs('expenses.*') ? 'active' : '' }}">Pengeluaran</a>
                </div>

                <!-- Kategori: LAPORAN -->
                <div class="nav-group">
                    <div class="nav-group-label">Laporan</div>
                    <a href="{{ route('reports.profit_loss') }}" class="{{ request()->routeIs('reports.profit_loss') ? 'active' : '' }}">Laba Rugi</a>
                    <a href="{{ route('reports.balance_sheet') }}" class="{{ request()->routeIs('reports.balance_sheet') ? 'active' : '' }}">Neraca</a>
                </div>
            </nav>

            <div class="sidebar-footer">
                <div class="operator-info">
                    <span class="operator-title">Operator Kasir</span>
                    <span class="operator-desc">Terminal Aktif</span>
                </div>
                @auth
                <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                    @csrf
                    <button type="submit" class="sidebar-logout-btn">Keluar</button>
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
                    <span class="topbar-title">Dashboard Material &amp; Bangunan</span>
                </div>
                <div class="topbar-right">
                    <span class="topbar-status"><span class="status-dot"></span> Sistem Siap</span>
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
        });
    </script>
</body>
</html>
