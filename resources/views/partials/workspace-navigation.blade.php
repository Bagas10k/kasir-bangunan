@php
    $workspacePage = $workspacePage ?? false;
    $navigationRole = $role ?? app(\App\Services\V2\Operations::class)->role(auth()->id());
    $navigationAdmin = $navigationRole === 'admin';
    $homeRoute = config('v2.enabled') ? 'pos.index' : 'v2.index';
    // Ikon hanya di tingkat grup; sub-menu berupa teks, jadi rail tertutup tidak punya ikon kembar.
    // Menu gabungan: [label, [task => label tab, ...]]; tab pertama menjadi tujuan menu.
    $menuGroups = [
        'pengawasan' => ['Pengawasan', 'M2 12h4l3-8 6 16 3-8h4', [
            'ringkasan' => 'Ringkasan',
            'persetujuan' => 'Persetujuan pembayaran',
            'akun' => 'Akun login',
        ]],
        'operasional' => ['Operasional', 'M3 9 5 4h14l2 5M3 9h18M3 9v11h18V9M9 20v-6h6v6', [
            'kasir' => 'Kasir (POS)',
            'riwayat-penjualan' => 'Riwayat penjualan',
            'tagihan' => 'Tagihan',
            ...($navigationAdmin ? [] : ['persetujuan' => 'Status pembayaran']),
            'pengiriman' => 'Pengiriman',
            'penerimaan' => 'Penerimaan barang',
            'shift' => [$navigationAdmin ? 'Kas & laporan' : 'Kas harian & laporan saya', ['shift' => 'Kas harian', 'laporan' => $navigationAdmin ? 'Laporan harian' : 'Laporan saya']],
        ]],
        'master' => ['Master data', 'M4 6c0-3.5 16-3.5 16 0v12c0 3.5-16 3.5-16 0zM4 6c0 3.5 16 3.5 16 0M4 12c0 3.5 16 3.5 16 0', [
            'master-product' => 'Produk',
            'master-supplier' => ['Supplier & pelanggan', ['master-supplier' => 'Supplier', 'master-customer' => 'Pelanggan']],
            'master-employee' => 'Karyawan & sopir',
            'master-vehicle' => 'Mobil',
            'master-unit' => ['Satuan & kategori', ['master-unit' => 'Satuan', 'master-category' => 'Kategori']],
        ]],
    ];
    $adminOnlyGroups = ['pengawasan', 'master'];
    $adminOnlyTasks = ['penerimaan'];
    // Halaman keuangan dimuat ke panel workspace lewat fetch; URL penuhnya tetap berfungsi.
    $financialLinks = [
        'pengeluaran' => ['expenses.index', 'Pengeluaran'],
        'jurnal' => ['journals.index', 'Jurnal umum'],
        'neraca' => ['reports.balance_sheet', 'Neraca'],
        'laba-rugi' => ['reports.profit_loss', 'Laba rugi'],
        'arus-kas' => ['reports.cash_flow', 'Arus kas'],
        'bagan-akun' => ['coa.index', 'Bagan akun (COA)'],
    ];
@endphp
<nav class="nav sidebar-nav" aria-label="Ruang kerja" data-role="{{ $navigationRole }}" data-default-task="{{ $navigationAdmin ? 'ringkasan' : 'kasir' }}">
    @foreach($menuGroups as $groupKey => [$groupTitle, $iconPath, $items])
        @continue(!$navigationAdmin && in_array($groupKey, $adminOnlyGroups, true))
        <details class="workspace-menu-group" data-menu-group="{{ $groupKey }}" @if($groupKey === 'operasional') open @endif>
            <summary title="{{ $groupTitle }}"><svg class="menu-group-mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $iconPath }}"/></svg><span>{{ $groupTitle }}</span></summary>
            <div class="workspace-submenu">
                @foreach($items as $task => $item)
                    @continue(!$navigationAdmin && in_array($task, $adminOnlyTasks, true))
                    @php [$label, $tabs] = is_array($item) ? $item : [$item, null]; @endphp
                    <a href="{{ $workspacePage ? '#'.$task : route($homeRoute).'#'.$task }}" @if($workspacePage) data-task="{{ $task }}" @endif @if($tabs) data-tabs="{{ json_encode($tabs, JSON_UNESCAPED_UNICODE) }}" @endif><span>{{ $label }}</span></a>
                @endforeach
            </div>
        </details>
    @endforeach
    @if($navigationAdmin)
        <details class="workspace-menu-group" data-menu-group="keuangan" @if(request()->routeIs('reports.*', 'journals.*', 'coa.*', 'expenses.*')) open @endif>
            <summary title="Keuangan"><svg class="menu-group-mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4.5A1.5 1.5 0 0 1 6.5 3H20v15H6.5A1.5 1.5 0 0 0 5 19.5zM5 19.5A1.5 1.5 0 0 0 6.5 21H20M9 7h7M9 11h5"/></svg><span>Keuangan</span></summary>
            <div class="workspace-submenu">
                @foreach($financialLinks as $task => [$routeName, $label])
                    <a href="{{ route($routeName) }}" @if($workspacePage) data-task="{{ $task }}" data-remote="{{ route($routeName) }}" @endif @if(request()->routeIs($routeName)) aria-current="page" @endif><span>{{ $label }}</span></a>
                @endforeach
            </div>
        </details>
    @endif
</nav>
