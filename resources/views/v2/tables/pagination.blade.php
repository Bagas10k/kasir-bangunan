@php
    $noun = $noun ?? 'data';
    $perPage = (int) $paginator->perPage();
    $total = (int) $paginator->total();
    $currentPage = (int) $paginator->currentPage();
    $lastPage = max(1, (int) $paginator->lastPage());
    $from = $paginator->firstItem() ?? 0;
    $to = $paginator->lastItem() ?? 0;
    $pageStart = max(1, min($currentPage - 2, $lastPage - 4));
    $pageEnd = min($lastPage, $pageStart + 4);
    $showControls = $total > 10 || $lastPage > 1 || $perPage !== 10;
@endphp
<div class="ui-pager" data-st-pager @if($total === 0) hidden @endif>
    <p class="ui-status-line" role="status" aria-live="polite" style="margin:0">Menampilkan {{ $from }}–{{ $to }} dari {{ $total }} {{ $noun }}.</p>
    <div class="ui-pager-controls" @if(!$showControls) hidden @endif>
        <label>Tampilkan <select data-st-size aria-label="Jumlah baris per halaman">
            @foreach([10, 25, 50] as $sizeOption)
                <option value="{{ $sizeOption }}" @selected($perPage === $sizeOption)>{{ $sizeOption }} baris</option>
            @endforeach
        </select></label>
        <button type="button" data-st-page="{{ max(1, $currentPage - 1) }}" @disabled($currentPage <= 1)>Sebelumnya</button>
        <span class="ui-pages" role="group" aria-label="Halaman {{ $currentPage }} dari {{ $lastPage }}">
            @for($n = $pageStart; $n <= $pageEnd; $n++)
                <button type="button" data-st-page="{{ $n }}" aria-label="Halaman {{ $n }}" @if($n === $currentPage) aria-current="page" @endif>{{ $n }}</button>
            @endfor
        </span>
        <button type="button" data-st-page="{{ min($lastPage, $currentPage + 1) }}" @disabled($currentPage >= $lastPage)>Lanjut</button>
    </div>
</div>
