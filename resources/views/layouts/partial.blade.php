{{--
    Cangkang tipis untuk panel workspace: hanya isi halaman, tanpa sidebar/header/aset.
    Dipakai saat permintaan menyertakan ?partial=1 (dimuat lewat fetch oleh ui-workspace).
    Halaman penuh tetap tersedia di URL yang sama tanpa parameter itu.
--}}
<div class="main-content" data-remote-content>
    @yield('content')
</div>
@hasSection('modals')
    <div data-remote-modals>
        @yield('modals')
    </div>
@endif
