@php
    $search = $search ?? '';
    $perPage = $perPage ?? 15;
    $placeholder = $placeholder ?? 'Cari data...';
@endphp
<form class="table-filters" method="GET" action="{{ url()->current() }}">
    <div class="table-search">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <input type="search" name="search" value="{{ $search }}" placeholder="{{ $placeholder }}" aria-label="Cari data">
    </div>
    <div class="table-perpage">
        <span>Tampilkan</span>
        <select name="per_page" onchange="this.form.submit()" aria-label="Jumlah data per halaman">
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