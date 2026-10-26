@php
    $search = $search ?? '';
    $perPage = $perPage ?? 15;
    $placeholder = $placeholder ?? 'Cari data...';
@endphp
<form class="table-filters" method="GET" action="{{ url()->current() }}" role="search">
    <div class="table-search">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <input type="search" id="table-search-input" name="search" value="{{ $search }}" placeholder="{{ $placeholder }}" aria-label="Cari data" autocomplete="off">
    </div>
    <div class="table-perpage">
        <label for="table-per-page">Tampilkan</label>
        <select id="table-per-page" name="per_page" class="form-control" onchange="this.form.submit()" aria-label="Jumlah data per halaman">
            @foreach ([10, 25, 50] as $n)
                <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
            @endforeach
        </select>
        <span>baris</span>
    </div>
    @if ($search !== '')
        <a class="btn btn-secondary table-reset" href="{{ url()->current() }}" role="button">Reset</a>
    @endif
</form>