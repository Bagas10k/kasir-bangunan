@php([$position, $label, $tip] = $illustration)
<figure class="hc-figure">
    <p class="hc-figure-label">Petunjuk layar</p>
    <div class="hc-mock hc-mock--{{ $position }}" aria-hidden="true">
        <div class="hc-mock-top"><span></span><span></span></div>
        <div class="hc-mock-layout">
            <div class="hc-mock-rail"><span></span><span></span><span></span></div>
            <div class="hc-mock-content">
                <div class="hc-mock-line hc-mock-head">
                    @if ($position === 'heading')
                        <strong class="hc-mock-focus">{{ $label }}</strong>
                    @else
                        <span class="hc-mock-bar"></span>
                    @endif
                    @if ($position === 'toolbar')
                        <strong class="hc-mock-focus">{{ $label }}</strong>
                    @else
                        <span class="hc-mock-chip"></span>
                    @endif
                </div>
                @if ($position === 'tab')
                    <div class="hc-mock-line hc-mock-tabs">
                        <span class="hc-mock-chip"></span><strong class="hc-mock-focus">{{ $label }}</strong>
                    </div>
                @endif
                <div class="hc-mock-panel">
                    <div class="hc-mock-line"><span class="hc-mock-bar"></span><span class="hc-mock-chip"></span></div>
                    @if ($position === 'field')
                        <div class="hc-mock-line hc-mock-field">
                            <strong class="hc-mock-focus">{{ $label }}</strong><span class="hc-mock-chip"></span>
                        </div>
                    @else
                        <div class="hc-mock-line">
                            <span class="hc-mock-bar"></span>
                            @if ($position === 'row')
                                <strong class="hc-mock-focus">{{ $label }}</strong>
                            @else
                                <span class="hc-mock-chip"></span>
                            @endif
                        </div>
                    @endif
                    <div class="hc-mock-line"><span class="hc-mock-bar"></span><span class="hc-mock-chip"></span></div>
                </div>
                @if (in_array($position, ['footer', 'footer-start'], true))
                    <div class="hc-mock-line hc-mock-footer">
                        @if ($position === 'footer-start')
                            <strong class="hc-mock-focus">{{ $label }}</strong><span class="hc-mock-chip"></span>
                        @else
                            <span class="hc-mock-chip"></span><strong class="hc-mock-focus">{{ $label }}</strong>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
    <figcaption><strong>Sorotan: {{ $label }}.</strong> {{ $tip }} <span class="hc-figure-kind">Ilustrasi layar, bukan tombol aktif.</span></figcaption>
</figure>
