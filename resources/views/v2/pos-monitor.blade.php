{{--
    Kepala dashboard kasir. Semua angka datang dari App\Services\V2\PosDashboard (BigDecimal); tampilan ini hanya merangkai.
    Tiga periode dirender sekaligus di server dan hanya ditukar tampil oleh JS, jadi tidak ada hitungan di browser.
--}}
@php
    $posDashboard = $posDashboard ?? [];
    $decimal = fn ($v) => \Brick\Math\BigDecimal::of((string) ($v ?? '0'));
    $catalog = collect($products ?? []);
    $stockOut = $catalog->filter(fn ($p) => $decimal($p->stock_quantity)->isLessThanOrEqualTo(0));
    $stockLow = $catalog->filter(fn ($p) => $decimal($p->stock_quantity)->isGreaterThan(0)
        && $decimal($p->min_stock ?? 0)->isGreaterThan(0)
        && $decimal($p->stock_quantity)->isLessThanOrEqualTo($decimal($p->min_stock)));
    $stockTotal = $catalog->count();
    $isAdminView = ($role ?? '') === 'admin';
    $periodButtons = ['today' => 'Hari ini', '7d' => '7 hari', '30d' => '30 hari'];
    $initial = '7d';
    $deltaText = function (array $d, string $against): array {
        return match ($d['direction']) {
            'naik' => ['up', 'Naik '.$d['pct'].'% dari '.$against],
            'turun' => ['down', 'Turun '.$d['pct'].'% dari '.$against],
            'baru' => ['new', 'Belum ada data '.$against],
            default => ['flat', 'Sama seperti '.$against],
        };
    };
@endphp

<section class="pd" id="pos-dashboard" aria-labelledby="pd-title">
    <header class="pd-head">
        <div class="pd-heading">
            <h2 id="pd-title">Ringkasan penjualan</h2>
            <p class="pd-sub" id="pd-sub">{{ $posDashboard[$initial]['sub_label'] ?? '' }}</p>
        </div>
        <div class="pd-controls">
            <div class="pos-shift-notice" id="store-shift-state" data-live-skip data-pos-shift-state="{{ $activeStoreShift ? 'open' : 'closed' }}" data-store-shift-id="{{ $activeStoreShift->id ?? '' }}" data-today-status="{{ !empty($todayCashDay) ? substr((string) $todayCashDay->opened_at, 0, 10).':'.$todayCashDay->status : '' }}" data-status-url="{{ route('v2.shifts.active') }}" data-manage-shift="{{ $isAdminView ? '1' : '0' }}" role="status" aria-live="polite" aria-label="Status kas harian">
                <strong>{{ $activeStoreShift ? 'Kas harian '.substr((string) $activeStoreShift->opened_at, 0, 10).' aktif' : (!empty($todayCashDay) ? 'Kas harian '.substr((string) $todayCashDay->opened_at, 0, 10).' sudah ditutup' : ($isAdminView ? 'Kas harian belum dibuka' : 'Menunggu admin membuka kas harian')) }}</strong>
                <a class="pos-shift-action" href="#shift" data-task-link="shift"><span>{{ $activeStoreShift || !empty($todayCashDay) || !$isAdminView ? 'Lihat kas harian' : 'Buka kas harian' }}</span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></a>
            </div>
            <div class="pd-seg" id="sales-period-control" role="group" aria-label="Pilih rentang waktu">
                @foreach ($periodButtons as $key => $text)
                    <button type="button" data-period="{{ $key }}" aria-pressed="{{ $key === $initial ? 'true' : 'false' }}">{{ $text }}</button>
                @endforeach
            </div>
            <div id="pd-custom">
                <button type="button" id="pd-custom-trigger" aria-expanded="false" aria-controls="pd-custom-panel">Tanggal khusus</button>
                <div id="pd-custom-panel" class="pd-custom-panel" role="region" aria-labelledby="pd-custom-title" hidden>
                <div class="pd-custom-heading"><strong id="pd-custom-title">Rentang tanggal</strong><button type="button" id="pd-custom-close" aria-label="Tutup tanggal khusus">×</button></div>
                <form id="pd-custom-form" action="{{ route('v2.sales.dashboard') }}">
                    <div class="pd-date-field"><label for="pd-from">Dari</label><input type="date" id="pd-from" required max="{{ now()->toDateString() }}"></div>
                    <div class="pd-date-field"><label for="pd-to">Sampai</label><input type="date" id="pd-to" required max="{{ now()->toDateString() }}"></div>
                    <button type="submit">Terapkan</button>
                    <p id="pd-custom-status" role="status" aria-live="polite">Maksimal 366 hari. Tidak mengubah tanggal menu lain.</p>
                </form>
                </div>
            </div>
        </div>
    </header>

    <div class="pd-body" id="pd-body">
        @foreach ($posDashboard as $key => $d)
            @php [$tone, $deltaLabel] = $deltaText($d['delta'], $d['previous_label']); @endphp
            <div class="pd-pane" data-pane="{{ $key }}" @if ($key !== $initial) hidden @endif>
                <div class="pd-kpis">
                        <div class="pd-kpi pd-kpi--lead">
                            <span class="pd-kpi-label">Penjualan</span>
                            <strong class="pd-kpi-value" data-number-key="revenue" @if ($key === $initial) id="sales-revenue" @endif>{{ $d['formatted_revenue'] }}</strong>
                            <span class="pd-delta pd-delta--{{ $tone }}" data-number-text="delta">{{ $deltaLabel }}</span>
                        </div>
                        <div class="pd-kpi">
                            <span class="pd-kpi-label">Transaksi</span>
                            <strong class="pd-kpi-value" data-number-key="transactions">{{ number_format($d['transaction_count'], 0, ',', '.') }}</strong>
                            <span class="pd-kpi-note" data-number-text="previous-count">{{ number_format($d['previous_count'], 0, ',', '.') }} {{ $d['previous_label'] }}</span>
                        </div>
                        <div class="pd-kpi">
                            <span class="pd-kpi-label"><span class="pd-long">Rata-rata per nota</span><span class="pd-short">Rata-rata</span></span>
                            <strong class="pd-kpi-value" data-number-key="average">{{ $d['formatted_average'] }}</strong>
                            <span class="pd-kpi-note" data-number-text="average-count">dari {{ number_format($d['transaction_count'], 0, ',', '.') }} nota</span>
                        </div>
                        <div class="pd-kpi pd-kpi--stock @if ($stockOut->count() > 0) is-bad @elseif ($stockLow->count() > 0) is-warn @endif">
                            <span class="pd-kpi-label"><span class="pd-long">Stok perlu perhatian</span><span class="pd-short">Stok perhatian</span></span>
                            <strong class="pd-kpi-value" data-number-key="stock">{{ $stockOut->count() + $stockLow->count() }}</strong>
                            <span class="pd-kpi-note"><span data-number-text="stock-counts">{{ $stockOut->count() }} habis · {{ $stockLow->count() }} menipis</span><span class="pd-long" data-number-text="stock-total"> dari {{ number_format($stockTotal, 0, ',', '.') }} produk</span></span>
                            @if ($isAdminView && ($stockOut->count() + $stockLow->count()) > 0)
                                <a class="pd-kpi-link" href="#master-product" data-task-link="master-product">Lihat stok</a>
                            @endif
                        </div>
                    </div>

                @php
                    $bucketLabels = array_column($d['buckets'] ?? [], 'label');
                    $rangeLabel = $key === 'today' || count($bucketLabels) === 0 ? $d['sub_label'] : reset($bucketLabels).' – '.end($bucketLabels);
                @endphp
                <div class="pd-main">
                    <div class="pd-card-head"><h3 class="pd-card-title">{{ ($d['hourly'] ?? ($key === 'today')) ? 'Penjualan per jam' : 'Penjualan per periode' }}</h3><p class="pd-card-sub">{{ $rangeLabel }}</p></div>
                    <figure class="pd-chart" @if ($key === $initial) id="sales-dashboard-card" @endif>
                        {{-- Pertahankan titik kait grafik; periode kosong hanya menampilkan keterangan, bukan batang semu. --}}
                        @php $emptyChart = $d['peak'] === null; @endphp
                        <div class="pd-plot @if ($emptyChart) is-empty @endif" role="img" aria-label="Grafik penjualan {{ $d['sub_label'] }}. @if ($emptyChart)Belum ada penjualan.@else Tertinggi {{ $d['peak']['formatted'] }} pada {{ $d['peak']['label'] }}.@endif">
                                <div class="pd-y" aria-hidden="true">
                                    @foreach (array_reverse($d['axis']) as $tick)<span>{{ $emptyChart && $tick['pct'] > 0 ? '' : $tick['label'] }}</span>@endforeach
                                </div>
                                <div class="pd-area">
                                    <div class="pd-grid" aria-hidden="true"><i></i><i></i><i></i></div>
                                    <div class="pd-bars" @if ($key === $initial) id="sales-chart" @endif>
                                        @foreach ($d['buckets'] as $bucket)
                                            <div class="pd-col @if ($bucket['peak']) is-peak @endif" title="{{ $bucket['label'] }} · {{ $bucket['formatted_total'] }}">
                                                <i style="height: {{ $bucket['height'] }}%"></i>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="pd-x" aria-hidden="true">
                                        @foreach ($d['buckets'] as $bucket)<span>{{ $bucket['show'] ? $bucket['label'] : '' }}</span>@endforeach
                                    </div>
                                    @if ($emptyChart)<p class="pd-plot-note" role="status">Belum ada penjualan pada periode ini. Batang muncul setelah ada nota.</p>@endif
                                </div>
                            </div>
                            @unless ($emptyChart)
                                <figcaption>Tertinggi <strong>{{ $d['peak']['formatted'] }}</strong> pada {{ $d['peak']['label'] }}</figcaption>
                            @endunless
                    </figure>
                </div>

                <aside class="pd-side" aria-label="Produk terlaris {{ $d['sub_label'] }}">
                    <div class="pd-card-head"><h3 class="pd-card-title">Produk terlaris</h3><p class="pd-card-sub">@if (count($d['top_products']) > 1){{ count($d['top_products']) }} besar · @endif{{ $d['sub_label'] }}</p></div>
                    @if (count($d['top_products']) === 0)
                        <p class="pd-empty">Belum ada barang terjual.</p>
                    @else
                        <ol class="pd-top" style="--pd-rows: {{ min(5, count($d['top_products'])) }}; --pd-columns: {{ count($d['top_products']) > 5 ? 2 : 1 }}">
                            @foreach ($d['top_products'] as $i => $top)
                                <li>
                                    <span class="pd-rank">{{ $i + 1 }}</span>
                                    <span class="pd-top-name" title="{{ $top['name'] }}">{{ $top['name'] }}</span>
                                    <span class="pd-top-qty" data-number-text="top-{{ $top['name'] }}">{{ $top['formatted_qty'] }} {{ $top['unit'] }}</span>
                                    <i class="pd-top-bar" style="width: {{ $top['share'] }}%" aria-hidden="true"></i>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </aside>
            </div>
        @endforeach
        @include('v2.recent-sales')
    </div>
</section>

<script id="pos-monitor-precomputed" type="application/json">
{!! json_encode($posDashboard, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
</script>

@include('v2.pos-period-script')
@include('v2.pos-period-popover')
