@php
    $rp = fn ($value): string => \App\Services\V2\Money::display($value);
    $when = fn ($value): string => $value ? \Carbon\Carbon::parse($value)->locale('id')->translatedFormat('j M Y, H:i') : '—';
    $sourceLabel = ['admin' => 'Dari admin', 'carry' => 'Sisa kas sebelumnya', 'both' => 'Admin + sisa kas'];
@endphp
<div class="ui-table-wrap scroll" tabindex="0" role="region" aria-label="Riwayat kas harian">
    <table class="ui-table">
        <caption class="sr">Riwayat kas per tanggal, termasuk catatan lama yang tetap disimpan</caption>
        <thead><tr><th scope="col">Tanggal kas</th><th scope="col">Dibuka oleh</th><th scope="col">Dibuka</th><th scope="col" class="ui-num">Kas awal</th><th scope="col" class="ui-num">Perkiraan</th><th scope="col" class="ui-num">Uang fisik</th><th scope="col" class="ui-num">Selisih</th><th scope="col">Status</th><th scope="col" class="ui-actions">Aksi</th></tr></thead>
        <tbody>
        @forelse ($shifts as $s)
            <tr data-status="{{ $s->status ?? '' }}">
                <td class="ui-primary ui-nowrap" data-label="Tanggal kas">{{ substr((string) $s->opened_at, 0, 10) }}<span class="ui-sub">{{ !empty($s->business_date) ? 'Kas harian' : 'Catatan lama' }} · {{ $sourceLabel[$s->source ?? ''] ?? '' }}</span></td>
                <td data-label="Dibuka oleh">{{ $s->user_name ?? '—' }}</td>
                <td data-label="Dibuka" class="ui-nowrap">{{ $when($s->opened_at ?? null) }}</td>
                <td data-label="Kas awal" class="ui-num">{{ $rp($s->opening_cash ?? null) }}</td>
                <td data-label="Perkiraan" class="ui-num">{{ $rp($s->expected_cash ?? null) }}</td>
                <td data-label="Uang fisik" class="ui-num">{{ ($s->counted_cash ?? null) === null ? 'Belum dihitung' : $rp($s->counted_cash) }}</td>
                <td data-label="Selisih" class="ui-num @if(($s->difference ?? null) !== null && ! preg_match('/^-?0+(?:\.0+)?$/', (string) $s->difference)) ui-neg @endif">{{ ($s->difference ?? null) === null ? '—' : $rp($s->difference) }}</td>
                <td data-label="Status"><x-ui.status :status="$s->status ?? ''"/></td>
                <td class="ui-actions">
                @if ($isAdmin && ($s->status ?? '') === 'open')
                    <form class="form-close" action="{{ route('v2.shifts.close', ['id' => $s->id]) }}" data-shift="{{ $s->id }}" novalidate>
                        <h3 class="sr">Tutup kas harian {{ substr((string) $s->opened_at, 0, 10) }}</h3>
                        <div class="close-recap" data-close-recap hidden></div>
                        <div class="row">
                            <div class="field">
                                <label for="counted-{{ $s->id }}">Uang fisik dihitung ({{ substr((string) $s->opened_at, 0, 10) }})</label>
                                <input id="counted-{{ $s->id }}" name="counted" type="text" data-money inputmode="decimal" autocomplete="off" maxlength="30" pattern="[0-9]+([.][0-9]{1,2})?" aria-describedby="counted-help-{{ $s->id }}" required>
                                <p class="note" id="counted-help-{{ $s->id }}">Angka otomatis di atas menunjukkan yang seharusnya ada di laci. Hitung uang fisik, lalu isi hasilnya di sini untuk dibandingkan.</p>
                                <p class="close-match" data-close-match hidden></p>
                            </div>
                            <div class="field" style="flex:0 0 auto">
                                <button type="submit" class="secondary">Tutup kas harian</button>
                            </div>
                        </div>
                        <p class="err" data-error tabindex="-1" hidden></p>
                        <p class="ok" data-ok hidden></p>
                    </form>
                @else
                    <span class="ui-muted">—</span>
                @endif
                </td>
            </tr>
        @empty
            <tr data-ui-empty-static><td colspan="9" class="ui-empty"><strong>Tidak ada kas harian sesuai saringan.</strong>{{ $isAdmin ? 'Tekan Buka kas harian untuk memulai.' : 'Menunggu admin membuka kas harian.' }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@include('v2.tables.pagination', ['paginator' => $shifts, 'noun' => 'kas harian'])
