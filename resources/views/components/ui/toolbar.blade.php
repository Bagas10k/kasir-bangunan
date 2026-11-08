@props(['search' => null, 'chips' => [], 'label' => 'Cari data', 'default' => null])
{{-- Toolbar tabel standar. Perilaku (cari, chip, halaman) dipasang oleh ui-tables.blade.php pada tabel [data-ui-table] terdekat. --}}
<div class="ui-toolbar" data-ui-toolbar>
    @if($search !== null)
        <div class="ui-search" role="search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" data-ui-search placeholder="{{ $search }}" aria-label="{{ $label }}" autocomplete="off">
        </div>
    @endif
    @if(count($chips))
        <div class="ui-chips" role="group" aria-label="Saring menurut status">
            @foreach($chips as $chipLabel => $chipFilter)
                <button type="button" class="ui-chip" data-ui-chip="{{ $chipFilter }}" aria-pressed="{{ ($default === null ? $loop->first : $chipLabel === $default) ? 'true' : 'false' }}">{{ $chipLabel }}</button>
            @endforeach
        </div>
    @endif
    @if(! $slot->isEmpty())<div class="ui-toolbar-actions">{{ $slot }}</div>@endif
</div>
