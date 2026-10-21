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
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--canvas);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            font-size: 14px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        header {
            background-color: var(--primary);
            color: #ffffff;
            padding: 12px 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
        }

        header h1 {
            font-size: 16px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.5px;
            color: #ffffff;
        }

        nav {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        nav a {
            color: #d8f3dc;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            padding: 0 12px;
            border-radius: 6px;
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        nav a:hover, nav a.active {
            color: #ffffff;
            background-color: var(--primary-hover);
            text-decoration: none;
        }

        main {
            padding: 24px 20px;
            max-width: 1160px;
            margin: 0 auto;
        }

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

        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
            header {
                flex-direction: column;
                align-items: stretch;
            }
            nav {
                justify-content: flex-start;
            }
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
    </style>
</head>
<body>
    <header>
        <h1>Kasir Bangunan</h1>
        <nav>
            <a href="{{ route('pos.index') }}">Kasir (POS)</a>
            <a href="{{ route('products.index') }}">Produk</a>
            <a href="{{ route('inventory.receipts') }}">Penerimaan Barang</a>
            <a href="{{ route('vehicles.index') }}">Armada Kendaraan</a>
            <a href="{{ route('opname.index') }}">Stock Opname</a>
            <a href="{{ route('expenses.index') }}">Pengeluaran</a>
            <a href="{{ route('ritase.index') }}">Ritase</a>
            <a href="{{ route('reports.profit_loss') }}">Laba Rugi</a>
            <a href="{{ route('reports.balance_sheet') }}">Neraca</a>
            @auth
            <form method="POST" action="{{ route('logout') }}" style="display:inline-flex; align-items:center;">
                @csrf
                <button type="submit" class="btn" style="min-height:36px; padding:6px 14px; font-size:12px; background:var(--primary-hover); border-color:var(--primary-hover);">Keluar</button>
            </form>
            @endauth
        </nav>
    </header>
    <main>
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
</body>
</html>