<div class="form-group product-option-field" data-option-type="{{ $optionType }}">
    <label id="{{ $optionType }}-picker-label" for="{{ $optionType }}-picker-trigger">{{ $optionLabel }}</label>
    <input type="hidden" id="{{ $optionType }}" name="{{ $optionType }}" value="{{ $optionValue }}">
    <details class="product-option-picker">
        <summary id="{{ $optionType }}-picker-trigger" aria-labelledby="{{ $optionType }}-picker-label {{ $optionType }}-picker-value"><span id="{{ $optionType }}-picker-value">{{ $optionValue ?: 'Pilih '.$optionLabel }}</span><span class="product-option-chevron" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></span></summary>
        <div class="product-option-panel">
            <div class="product-option-list" role="group" aria-label="Pilihan {{ $optionLabel }}"></div>
            <button type="button" class="product-option-add-toggle" aria-expanded="false" aria-controls="{{ $optionType }}-option-add" aria-label="Tambah {{ strtolower($optionLabel) }}" title="Tambah {{ strtolower($optionLabel) }}"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></button>
            <div id="{{ $optionType }}-option-add" class="product-option-add" hidden>
                <input type="text" class="form-control" maxlength="{{ $optionType === 'unit' ? 20 : 50 }}" aria-label="{{ $optionLabel }} baru" placeholder="Tambah {{ strtolower($optionLabel) }}" autocomplete="off">
                <button type="button" class="btn product-option-add-button" aria-label="Simpan {{ strtolower($optionLabel) }} baru" title="Simpan {{ strtolower($optionLabel) }} baru"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></button>
            </div>
            <p class="product-option-error" role="status" aria-live="polite"></p>
        </div>
    </details>
</div>
