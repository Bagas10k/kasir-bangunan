<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir Material & Bangunan</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; background: #fafafa; color: #111; margin: 0; padding: 0; font-size: 14px; line-height: 1.5; }
        header { background: #000; color: #fff; padding: 12px 20px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; }
        header h1 { font-size: 15px; margin: 0; text-transform: uppercase; letter-spacing: 1px; }
        nav { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        nav a { color: #ccc; text-decoration: none; font-size: 13px; min-height: 44px; display: inline-flex; align-items: center; padding: 0 10px; border-radius: 4px; }
        nav a:hover, nav a.active { color: #fff; background: #222; text-decoration: none; }
        main { padding: 20px; max-width: 1100px; margin: 0 auto; }
        .card { background: #fff; border: 1px solid #ddd; padding: 18px; margin-bottom: 24px; border-radius: 4px; }
        h2 { font-size: 15px; margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #eee; vertical-align: middle; }
        th { background: #f5f5f5; font-weight: 600; }
        .btn { background: #111; color: #fff; border: 1px solid #111; min-height: 44px; min-width: 44px; padding: 10px 18px; font-family: inherit; font-size: 14px; cursor: pointer; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; }
        .btn:hover { background: #333; }
        input, select, textarea { min-height: 44px; padding: 8px 12px; font-family: inherit; font-size: 14px; border: 1px solid #ccc; border-radius: 4px; width: 100%; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #000; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
            header { flex-direction: column; align-items: stretch; }
            nav { justify-content: flex-start; }
        }
        .alert { padding: 12px 14px; margin-bottom: 16px; border-radius: 4px; font-size: 13px; }
        .alert-success { background: #eefbee; border: 1px solid #badbcc; color: #0f5132; }
        .alert-error { background: #fde8e8; border: 1px solid #f5c2c7; color: #842029; }
        .badge { display: inline-block; padding: 3px 8px; font-size: 11px; text-transform: uppercase; border-radius: 3px; border: 1px solid #ccc; }
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
                <button type="submit" class="btn" style="min-height:36px; padding:6px 12px; font-size:12px; background:#222; border-color:#333;">Keluar</button>
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
