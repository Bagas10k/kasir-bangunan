@if ($paginator->hasPages())
<nav class="pagination" aria-label="Navigasi halaman">
    <div class="pagination-meta">
        Menampilkan {{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }} dari {{ $paginator->total() }} data
    </div>
    <div class="pagination-controls">
        @if ($paginator->onFirstPage())
            <span class="page-btn disabled">&lsaquo; Sebelumnya</span>
        @else
            <a class="page-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">&lsaquo; Sebelumnya</a>
        @endif
        <span class="page-btn active">Halaman {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a class="page-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya &rsaquo;</a>
        @else
            <span class="page-btn disabled">Berikutnya &rsaquo;</span>
        @endif
    </div>
</nav>
@endif