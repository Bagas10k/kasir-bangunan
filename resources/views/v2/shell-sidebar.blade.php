@php
    $shellAdmin = ($role ?? app(\App\Services\V2\Operations::class)->role(auth()->id())) === 'admin';
@endphp
<aside id="workspace-sidebar" class="workspace-sidebar app-sidebar collapsed" aria-label="Navigasi aplikasi">
    <div class="sidebar-brand sidebar-header"><div class="brand-mark" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4zM8 8h8M8 12h3M8 16h8"/></svg></div><div class="sidebar-brand-text"><strong>Kasir Bangunan</strong><small>Terminal Material</small></div><button type="button" class="sidebar-close secondary" aria-label="Tutup menu">×</button></div>
    @include('partials.workspace-navigation', ['workspacePage' => $workspacePage ?? false])
    <div class="sidebar-footer"><span class="role-label">{{ $shellAdmin ? 'Admin' : 'Kasir' }}</span><form method="post" action="{{ route('logout') }}">@csrf<button class="secondary" title="Keluar"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M9 4H4v16h5M13 7l5 5-5 5M8 12h10"/></svg><span>Keluar</span></button></form></div>
</aside>
<button type="button" id="sidebar-overlay" class="sidebar-overlay" aria-label="Tutup menu"></button>
