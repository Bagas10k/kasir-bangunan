{{--
Merender seluruh isian form master dari \App\Support\V2\FormCatalog::layout($kind).
$sources: ['unit' => [...], 'category' => [...], 'supplier' => [...], 'employee' => [...]]
--}}
@props(['kind', 'prefix', 'record' => null, 'sources' => [], 'locked' => [], 'existingCodes' => []])
@php
    $mode = $record ? 'edit' : 'add';
    $links = collect($sources['supplierLinks'] ?? []);
    $valueOf = function (array $field) use ($record, $kind, $existingCodes, $links) {
        if (! $record) {
            if ($field['key'] === 'code' || $field['key'] === 'sku') {
                return \App\Support\V2\FormCatalog::nextCode($kind, $existingCodes);
            }
            return null;
        }
        $property = rtrim($field['name'], '[]');
        if (in_array($field['type'], ['checklist', 'multiref'], true)) {
            return null;
        }
        if ($field['key'] === 'primary_supplier' && $links->isNotEmpty()) {
            return (string) ($links->first(fn ($link) => (bool) $link->is_primary)->supplier_id ?? '');
        }
        return isset($record->{$property}) ? (string) $record->{$property} : '';
    };
    $supplierChoices = collect($sources['supplier'] ?? [])->keyBy('id');
    foreach ($links as $link) {
        if (! $supplierChoices->has($link->supplier_id)) {
            $supplierChoices->put($link->supplier_id, (object) ['id' => $link->supplier_id, 'name' => $link->name, 'is_active' => $link->is_active]);
        }
    }
@endphp
@php $titledSections = collect(\App\Support\V2\FormCatalog::layout($kind))->filter(fn ($section) => $section[0] !== null)->count(); $step = 0; @endphp
@foreach (\App\Support\V2\FormCatalog::layout($kind) as $section)
@php [$title, $items] = $section; $sectionAttr = $section[2] ?? null; @endphp
<div class="uf-section" @if($sectionAttr) {{ $sectionAttr }} @endif>
@if ($title && $mode !== 'add')
<h3 class="uf-section-title">{{ $title }}</h3>
@elseif ($title)
@php $step++; $sectionNote = $mode === 'add' ? (\App\Support\V2\FormCatalog::SECTION_NOTES[$title] ?? null) : null; @endphp
<div class="uf-section-head">
@if($titledSections > 1)<span class="uf-step" aria-hidden="true">{{ $step }}</span>@endif
<div><h3 class="uf-section-title">{{ $title }}</h3>@if($sectionNote)<p class="uf-section-note">{{ $sectionNote }}</p>@endif</div>
</div>
@endif
<div class="uf-grid">
@foreach ($items as $item)
                @php
                    $field = \App\Support\V2\FormCatalog::field($item[0], $item[2] ?? []);
                    $source = $field['source'] ?? null;
                    $options = $source === 'supplier' ? $supplierChoices->values() : ($sources[$source] ?? []);
                    $selected = $field['key'] === 'suppliers' ? ($links->isNotEmpty() ? $links->sortByDesc('is_primary')->pluck('supplier_id')->all() : (array) ($record->supplier_ids ?? [])) : [];
                @endphp
<x-ui.field :field="$field" :id="$prefix.'-'.$field['key']" :value="$valueOf($field)" :options="$options" :selected="$selected" :span="$item[1]" :mode="$mode" :locked="in_array($field['key'], $locked, true)"/>
@endforeach
</div>
</div>
@endforeach
