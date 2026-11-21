@php
    $workspacePage = $workspacePage ?? false;
    $headerRole = $role ?? app(\App\Services\V2\Operations::class)->role(auth()->id());
    $headerTitle = match (true) {
        request()->routeIs('reports.profit_loss') => 'Laba Rugi',
        request()->routeIs('reports.balance_sheet') => 'Neraca',
        request()->routeIs('reports.cash_flow') => 'Arus Kas',
        request()->routeIs('journals.*') => 'Jurnal Umum',
        request()->routeIs('coa.*') => 'Bagan Akun (COA)',
        request()->routeIs('receivables.*') => 'Piutang Usaha',
        request()->routeIs('products.*') => 'Produk (arsip)',
        request()->routeIs('inventory.*') => 'Penerimaan Barang',
        request()->routeIs('opname.*') => 'Stok Opname',
        request()->routeIs('vehicles.*') => 'Kendaraan',
        request()->routeIs('ritase.*') => 'Ritase',
        request()->routeIs('expenses.*') => 'Pengeluaran',
        default => 'Ruang kerja',
    };
@endphp
<header class="{{ $workspacePage ? 'top ' : '' }}main-topbar">
    <div class="{{ $workspacePage ? 'wrap' : 'unified-topbar-inner' }}">
        <button type="button" class="mobile-menu-toggle {{ $workspacePage ? 'secondary' : 'hamburger-btn' }}" aria-label="Buka menu" aria-controls="workspace-sidebar" aria-expanded="false"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
        <div class="top-context" data-live-skip><strong>{{ $headerTitle }}</strong></div>
        <div class="header-state"><span>{{ $headerRole === 'admin' ? 'Admin' : 'Kasir' }}</span><a href="{{ $workspacePage ? '#shift' : route('pos.index').'#shift' }}" @if($workspacePage) data-task-link="shift" data-store-shift-header data-live-skip @endif>{{ isset($activeStoreShift) && $activeStoreShift ? 'Shift toko #'.$activeStoreShift->id : ($headerRole === 'admin' ? 'Kelola shift toko' : 'Lihat shift toko') }}</a></div>
    </div>
</header>
