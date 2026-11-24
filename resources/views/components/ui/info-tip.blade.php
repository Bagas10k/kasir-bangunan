{{--
    Tombol ikon informasi (Info Tooltip + Toggletip).
    Dipakai di label form, header tabel, dan kartu ringkasan. Tanpa emoji; memakai SVG inline dan kamus terpusat.
--}}
@props([
    'key' => null,
    'text' => null,
    'title' => null,
    'label' => 'Keterangan',
    'placement' => 'top',
    'dark' => false,
])
<button
    type="button"
    {{ $attributes->class(['ui-info-btn', 'ui-info-btn--dark' => $dark]) }}
    data-toggletip
    @if($key) data-tip-key="{{ $key }}" @endif
    @if($text) data-tip="{{ $text }}" @endif
    @if($title) data-tip-title="{{ $title }}" @endif
    @if($placement !== 'top') data-tip-placement="{{ $placement }}" @endif
    aria-label="{{ $label }}"
    aria-expanded="false"
    aria-controls="ui-tooltip"
><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg></button>