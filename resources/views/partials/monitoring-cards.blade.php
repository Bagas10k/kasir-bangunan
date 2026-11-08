{{--
    Monitor standar di atas setiap menu. Kartu: [label, nilai, keterangan?, nada?, filter?]
    nada: good|attention|problem|null. filter: "kunci:nilai1,nilai2" membuat ubin menjadi tombol penyaring tabel.
    Keterangan cakupan teknis (batas baris dimuat) sengaja tidak ditampilkan ke pengguna.
--}}
<section class="ui-monitor monitoring-block @if(!empty($monitoringHead)) ui-monitor--card @endif" aria-label="{{ $monitoringTitle ?? 'Ringkasan' }}" @isset($monitoringTask) data-monitoring-task="{{ $monitoringTask }}" @endisset>
    @if(!empty($monitoringHead))
        <div class="ui-monitor-head">
            <div><h2>{{ $monitoringHead[0] }}</h2><p class="note">{{ $monitoringHead[1] }}</p></div>
            @if(!empty($monitoringHead[2]))<span class="ui-pill ui-pill--{{ $monitoringHead[2][1] }}">{{ $monitoringHead[2][0] }}</span>@endif
        </div>
    @endif
    <div class="ui-monitor-grid">
        @foreach($monitoringCards as $card)
            @php
                $tone = $card[3] ?? null;
                $filter = $card[4] ?? null;
                $tag = $filter ? 'button' : 'div';
            @endphp
            <{{ $tag }} class="ui-tile @if($tone)ui-tile--{{ $tone }}@endif" @if($filter) type="button" data-ui-filter="{{ $filter }}" aria-pressed="false" @endif>
                <span class="ui-tile-body"><span class="ui-tile-label">{{ $card[0] }}</span>
                <strong class="ui-tile-value">{{ $card[1] }}</strong></span>
                @if(($card[2] ?? '') !== '')<span class="ui-tile-hint">{{ $card[2] }}</span>@endif
            </{{ $tag }}>
        @endforeach
    </div>
</section>
