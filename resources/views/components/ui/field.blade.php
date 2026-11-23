{{--
Satu kolom isian dari templat FormCatalog. Jangan menulis <input> master secara manual:
tambahkan templatnya di App\Support\V2\FormCatalog lalu panggil komponen ini.
--}}
@props(['field', 'id', 'value' => null, 'options' => [], 'selected' => [], 'locked' => false, 'span' => 6, 'mode' => 'add'])
@php
    $type = $field['type'];
    $name = $field['name'];
    $required = (bool) ($field['required'] ?? false);
    $raw = $value ?? ($mode === 'add' ? ($field['default'] ?? '') : '');
    $raw = is_scalar($raw) ? (string) $raw : '';
    $max = $field['max'] ?? null;
    $placeholder = $field['placeholder'] ?? null;
    // Form Tambah: angka bawaan 0 tidak ditampilkan (pengguna tidak perlu menghapusnya); nilai yang dikirim tetap 0.
    $blankZero = $mode === 'add' && in_array($type, ['money', 'qty'], true) && preg_match('/^0*(\.0*)?$/', trim($raw)) === 1;
    if ($blankZero && ! $placeholder) { $placeholder = '0'; }
    $numberText = function (string $value, int $scale): string {
        $value = trim($value);
        if ($value === '') { return ''; }
        $negative = str_starts_with($value, '-');
        [$int, $dec] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');
        $int = ltrim(preg_replace('/\D/', '', $int), '0') ?: '0';
        $dec = rtrim(substr(preg_replace('/\D/', '', $dec), 0, $scale), '0');
        return ($negative ? '-' : '').preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $int).($dec !== '' ? ','.$dec : '');
    };
    // Petunjuk hanya di form tambah: form ubah dirender sekali per baris data, jadi dijaga ringkas (batas ukuran halaman Kasir).
    $hint = $type === 'multiref' || $mode !== 'add' ? null : ($field['hint'] ?? null);
    $hintId = $hint ? $id.'-hint' : null;
    $optional = $mode === 'add' && ! $required && ! in_array($type, ['segmented', 'checklist', 'multiref'], true);
    $optionLabel = fn ($option): string => ($field['source'] ?? '') === 'employee' && ! empty($option->code)
        ? $option->code.' · '.$option->name
        : (string) $option->name;
@endphp
<div {{ $attributes->class(['uf', 'uf-w-'.$span, 'uf-'.$type]) }} @if($type === 'multiref') data-multiref-name="{{ $name }}" @endif @if(! empty($field['wrap'])) {{ $field['wrap'] }} @endif>
@if (in_array($type, ['segmented', 'checklist', 'multiref'], true))
<fieldset class="uf-group">
<legend class="uf-label">{{ $field['label'] }}@if($required)<span class="uf-req" aria-hidden="true">*</span>@endif</legend>
@if($hint)<p class="uf-hint uf-hint-top" id="{{ $hintId }}">{{ $hint }}</p>@endif
@if ($type === 'multiref')
@php $picked = array_values(array_filter(array_map('intval', $selected))); @endphp
<div class="uf-multi" data-ui-multiref data-empty="{{ $field['empty'] ?? 'Pilih data' }}">
<select aria-label="{{ $field['label'] }}" data-multiref-picker @if($required) data-required @endif>
<option value="">{{ $field['empty'] ?? 'Pilih data' }}</option>
@foreach ($options as $option)
<option value="{{ $option->id }}" @disabled(in_array((int) $option->id, $picked, true))>{{ $option->name }}{{ ($option->is_active ?? true) ? '' : ' (arsip)' }}</option>
@endforeach
</select>
<ul class="uf-picked" data-multiref-list>
@foreach ($picked as $index => $pickedId)
@php $chosen = collect($options)->first(fn ($option) => (int) $option->id === $pickedId); @endphp
<li class="uf-pick"><span class="uf-pick-name">{{ $chosen->name ?? ('#'.$pickedId) }}</span>@if($index === 0)<span class="uf-pick-main">Utama</span>@endif<button type="button" class="uf-pick-x" data-multiref-remove aria-label="Hapus {{ $chosen->name ?? $pickedId }}">×</button><input type="hidden" name="{{ $name }}" value="{{ $pickedId }}"></li>
@endforeach
</ul>
<p class="uf-none" data-multiref-empty @if($picked) hidden @endif>Belum ada {{ strtolower($field['label']) }} dipilih.</p>
@if(! empty($field['hint']))<p class="uf-hint">{{ $field['hint'] }}</p>@endif
<input type="hidden" name="primary_supplier_id" data-multiref-primary value="{{ $picked[0] ?? '' }}">
</div>
@elseif ($type === 'segmented')
<div class="uf-seg" role="presentation">
@foreach ($field['choices'] as $choice => $choiceLabel)
<label class="uf-seg-item"><input type="radio" name="{{ $name }}" value="{{ $choice }}" @checked(($raw !== '' ? $raw : ($field['default'] ?? '')) === $choice) @if($required) required @endif><span>{{ $choiceLabel }}</span></label>
@endforeach
</div>
@else
<div class="uf-checks">
@forelse ($options as $option)
<label class="uf-check"><input type="checkbox" name="{{ $name }}" value="{{ $option->id }}" @checked(in_array((int) $option->id, array_map('intval', $selected), true))><span>{{ $option->name }}@unless($option->is_active ?? true)<em class="uf-archived">arsip</em>@endunless</span></label>
@empty
<p class="uf-none">Belum ada data aktif.</p>
@endforelse
</div>
@endif
</fieldset>
@else
<label class="uf-label" for="{{ $id }}">{{ $field['label'] }}@if($required)<span class="uf-req" aria-hidden="true">*</span>@elseif($optional)<span class="uf-opt">Opsional</span>@endif</label>
@switch($type)
@case('select')
<select id="{{ $id }}" name="{{ $name }}" @if($required) required @endif @if($hintId) aria-describedby="{{ $hintId }}" @endif>
<option value="">Pilih {{ strtolower($field['label']) }}</option>
@foreach($field['choices'] as $choice)
<option value="{{ $choice }}" @selected($raw === $choice)>{{ $choice }}</option>
@endforeach
@if($raw !== '' && !in_array($raw, $field['choices'], true))
<option value="{{ $raw }}" selected>{{ $raw }} (tersimpan)</option>
@endif
</select>
@break
@case('ref')
<select id="{{ $id }}" name="{{ $name }}" @if($required) required @endif data-ref="{{ $field['source'] }}" @if($hintId) aria-describedby="{{ $hintId }}" @endif>
<option value="">{{ $field['empty'] ?? 'Pilih '.strtolower($field['label']) }}</option>
@foreach ($options as $option)
@if (($option->is_active ?? true) || (string) $option->id === $raw)
<option value="{{ $option->id }}" @selected((string) $option->id === $raw) @if(($field['source'] ?? '') === 'employee') data-name="{{ $option->name }}" @endif>{{ $optionLabel($option) }}{{ ($option->is_active ?? true) ? '' : ' (arsip)' }}</option>
@endif
@endforeach
</select>
@break
@case('money')
@case('qty')
<div class="uf-affix">
@if($type === 'money')<span class="uf-pre" aria-hidden="true">Rp</span>@endif
<input id="{{ $id }}" type="text" inputmode="decimal" autocomplete="off" data-ui-number="{{ $name }}" data-scale="{{ $field['scale'] ?? 2 }}" @if(! empty($field['nullable'])) data-ui-blank @endif @if($placeholder) placeholder="{{ $placeholder }}" @endif value="{{ $blankZero ? '' : $numberText($raw, (int) ($field['scale'] ?? 2)) }}" @if($required) required @endif @if($locked) readonly aria-describedby="{{ $id }}-lock{{ $hintId ? ' '.$hintId : '' }}" @elseif($hintId) aria-describedby="{{ $hintId }}" @endif>
@if($type === 'qty')<span class="uf-suf" data-unit-suffix="{{ $field['unit_from'] ?? '' }}" aria-hidden="true">satuan</span>@endif
<input type="hidden" name="{{ $name }}" value="{{ $raw === '' ? ($field['default'] ?? '') : $raw }}">
</div>
@if($locked)<span class="uf-lock" id="{{ $id }}-lock">Terkunci, sudah ada mutasi stok</span>@endif
@break
@case('textarea')
<textarea id="{{ $id }}" name="{{ $name }}" rows="2" @if($max) maxlength="{{ $max }}" data-ui-count @endif @if($placeholder) placeholder="{{ $placeholder }}" @endif @if($required) required @endif @if($hintId) aria-describedby="{{ $hintId }}" @endif>{{ $raw }}</textarea>
@if($max)<span class="uf-count" aria-hidden="true" data-count-for="{{ $id }}">{{ mb_strlen($raw) }}/{{ $max }}</span>@endif
@break
@case('suggest')
<input id="{{ $id }}" name="{{ $name }}" type="text" value="{{ $raw }}" list="{{ $id }}-list" autocomplete="off" @if($max) maxlength="{{ $max }}" @endif @if($placeholder) placeholder="{{ $placeholder }}" @endif @if($required) required @endif>
<datalist id="{{ $id }}-list">@foreach($field['suggest'] as $suggestion)<option value="{{ $suggestion }}"></option>@endforeach</datalist>
<div class="uf-quick" role="group" aria-label="Pilihan cepat {{ strtolower($field['label']) }}" data-suggest-for="{{ $id }}">
@foreach($field['suggest'] as $suggestion)<button type="button" data-suggest="{{ $suggestion }}" aria-pressed="{{ $raw === $suggestion ? 'true' : 'false' }}">{{ $suggestion }}</button>@endforeach
</div>
@break
@default
<input id="{{ $id }}" name="{{ $name }}" value="{{ $raw }}"
type="{{ $type === 'tel' ? 'tel' : 'text' }}"
@if($max) maxlength="{{ $max }}" @endif
@if($placeholder) placeholder="{{ $placeholder }}" @endif
@if($required) required @endif @if($hintId) aria-describedby="{{ $hintId }}" @endif
@if($type === 'code') class="uf-code" spellcheck="false" autocapitalize="characters" autocomplete="off" @endif
@if($type === 'plate') class="uf-code" data-ui-plate spellcheck="false" autocomplete="off" @endif>
@endswitch
@if($hint)<p class="uf-hint" id="{{ $hintId }}">{{ $hint }}</p>@endif
@endif
</div>
