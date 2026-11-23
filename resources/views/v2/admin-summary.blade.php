{{--
    Pengawasan · Ringkasan (admin). Rancangan FigCraft halaman "Pengawasan · Ringkasan" (desktop + HP).
    Data: $metrics (MonitorMetrics), $posDashboard (PosDashboard, semua kasir), $adminSummary (AdminSummary). Semua hanya baca.
    Periode (Hari ini / 7 hari / 30 hari) hanya mengganti penjualan, grafik, dan produk terlaris; saldo selalu per hari ini.
--}}
@php
    $sum = $adminSummary ?? ['drawer' => ['total' => '0', 'count' => 0], 'bank' => '0', 'receivable_overdue' => 0, 'unassigned' => null, 'stock_watch' => []];
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $monthsLong = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $now = now();
    $dayLabel = fn (string $date): string => (int) substr($date, 8, 2).' '.$months[(int) substr($date, 5, 2) - 1];
    $longDate = fn ($d): string => $days[$d->dayOfWeek].', '.$d->day.' '.$monthsLong[$d->month - 1].' '.$d->year;
    $short = function ($value): string {
        $v = \Brick\Math\BigDecimal::of((string) $value);
        $abs = $v->abs();
        foreach ([['mi', '1000000000'], ['jt', '1000000'], ['rb', '1000']] as [$suffix, $size]) {
            if ($abs->isGreaterThanOrEqualTo($size)) {
                $text = rtrim(rtrim((string) $abs->dividedBy($size, 1, \Brick\Math\RoundingMode::Down), '0'), '.');
                return ($v->isNegative() ? '−' : '').'Rp '.str_replace('.', ',', $text).' '.$suffix;
            }
        }
        return \App\Services\V2\Money::display((string) $v);
    };
    $signed = function ($value): string {
        $v = \Brick\Math\BigDecimal::of((string) $value);
        return ($v->isNegative() ? '−' : ($v->isPositive() ? '+' : '')).\App\Services\V2\Money::display((string) $v->abs());
    };
    $periods = ['today' => 'Hari ini', '7d' => '7 hari', '30d' => '30 hari'];
    $periodDays = ['today' => 1, '7d' => 7, '30d' => 30];
    $todo = array_values(array_filter([
        ['transfer menunggu', 'transfer menunggu verifikasi', $metrics['awaiting']['count'], $short($metrics['awaiting']['total']).' belum masuk saldo', 'persetujuan', 'Periksa', 'warn'],
        ['shift selisih kas', 'shift dengan selisih kas', $metrics['shifts']['review'], 'perlu diperiksa admin', 'shift', 'Periksa', 'bad'],
        ['surat jalan siap dikirim', 'surat jalan siap dikirim', $metrics['deliveries']['prepared'], $metrics['deliveries']['unassigned'] > 0 ? $metrics['deliveries']['unassigned'].' belum ada sopir' : 'belum berangkat', 'pengiriman', 'Buka', 'warn'],
        ['produk stok habis', 'produk stok habis', $metrics['stock']['out'], 'perlu dipesan ulang', 'master-product', 'Buka', 'bad'],
        ['produk stok menipis', 'produk stok menipis', $metrics['stock']['low'], 'di bawah stok minimum', 'master-product', 'Buka', 'warn'],
    ], fn ($row) => $row[2] > 0));
    $cashTotal = \Brick\Math\BigDecimal::of((string) $sum['drawer']['total'])->plus((string) $sum['bank']);
    $difference = \Brick\Math\BigDecimal::of((string) $metrics['shifts']['difference_month']);
    $unresolvedDifference = (string) $metrics['shifts']['difference_unresolved'];
    $deliveries = $metrics['deliveries'];
    $watch = $sum['stock_watch'];
@endphp
<section id="admin-overview" class="as-page" aria-label="Ringkasan pengawasan" data-as-period="7d">
<div class="as-top">
<p class="as-stamp"><span class="as-stamp-date">{{ $longDate($now) }} · </span>diperbarui {{ $now->format('H.i') }}</p>
<div class="as-seg" role="radiogroup" aria-label="Periode ringkasan">@foreach($periods as $key => $label)<button type="button" role="radio" data-as-pick="{{ $key }}" aria-checked="{{ $key === '7d' ? 'true' : 'false' }}">{{ $label }}</button>@endforeach</div>
</div>
<section class="card as-card as-todo" aria-labelledby="as-todo-h">
<h2 id="as-todo-h">Perlu ditindaklanjuti</h2>
@if(count($todo) === 0)
<div class="as-ok"><span class="as-ok-mark" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg></span><p><strong>Semua beres</strong><span>Tidak ada pembayaran, shift, pengiriman, atau stok yang menunggu tindakan.</span></p></div>
@else
<p class="as-sub"><span class="as-desk">Hal yang menunggu keputusan Anda. Hilang sendiri setelah ditangani.</span><span class="as-mob">{{ count($todo) }} hal menunggu Anda</span></p>
<ul class="as-todo-list">
@foreach($todo as [$label, $labelLong, $count, $hint, $target, $button, $tone])
<li><a class="as-todo-item" href="#{{ $target }}" data-task-link="{{ $target }}" title="{{ $count }} {{ $labelLong }} · {{ $hint }}"><span class="as-dot as-dot--{{ $tone }}" aria-hidden="true"></span><strong class="as-todo-n">{{ $count }}</strong><span class="as-todo-label"><span class="as-desk">{{ $label }}</span><span class="as-mob">{{ $count }} {{ $labelLong }}</span></span><small class="as-todo-hint">{{ $hint }}</small><span class="as-todo-go">{{ $button }}</span></a></li>
@endforeach
</ul>
@endif
</section>
<section class="card as-card as-kpi" aria-labelledby="as-kpi-h">
<h2 id="as-kpi-h">@foreach($periods as $key => $label)<span data-period="{{ $key }}">Ringkasan {{ $key === 'today' ? 'hari ini' : $label }}</span>@endforeach</h2>
<p class="as-sub">@foreach($periodDays as $key => $n)@php $from = $now->copy()->subDays($n - 1); @endphp<span data-period="{{ $key }}">{{ $n === 1 ? $longDate($now) : $dayLabel($from->format('Y-m-d')).' – '.$dayLabel($now->format('Y-m-d')).' '.$now->year }}</span>@endforeach · saldo ditampilkan per hari ini</p>
<div class="as-tiles">
<div class="as-tile as-tile--hero">
<small>Penjualan</small>
@foreach($periods as $key => $label)@php $p = $posDashboard[$key]; $d = $p['delta']; $base = $key === 'today' ? 'kemarin' : $periodDays[$key].' hari sebelumnya'; @endphp
<div data-period="{{ $key }}"><strong data-number-key="admin-revenue">{{ $p['formatted_revenue'] }}</strong><span>@if($d['pct'] !== null){{ $d['direction'] === 'turun' ? '−' : '+' }}{{ $d['pct'] }}%<span class="as-desk">&nbsp;dari {{ $base }}</span> · @elseif($d['direction'] === 'baru')<span class="as-desk">belum ada pembanding ·&nbsp;</span>@endif<span data-number-text="admin-transactions">{{ $p['transaction_count'] }} nota</span></span></div>
@endforeach
</div>
<div class="as-tile"><small><span class="as-dot as-dot--muted" aria-hidden="true"></span>Kas di laci</small><strong>{{ \App\Services\V2\Money::display($sum['drawer']['total']) }}</strong><span>{{ $sum['drawer']['count'] > 0 ? $sum['drawer']['count'].' shift terbuka' : 'tidak ada shift terbuka' }}</span></div>
<div class="as-tile"><small><span class="as-dot as-dot--{{ $sum['receivable_overdue'] > 0 ? 'bad' : 'muted' }}" aria-hidden="true"></span>Piutang<span class="as-desk">&nbsp;pelanggan</span></small><strong>{{ \App\Services\V2\Money::display($metrics['receivable']['total']) }}</strong><span><span class="as-desk">{{ $metrics['receivable']['count'] }} tagihan · </span>{{ $sum['receivable_overdue'] }} lewat tempo</span></div>
<div class="as-tile"><small><span class="as-dot as-dot--muted" aria-hidden="true"></span>Utang supplier</small><strong>{{ \App\Services\V2\Money::display($metrics['payable']['total']) }}</strong><span>{{ $metrics['payable']['count'] }} tagihan<span class="as-desk"> belum dibayar</span></span></div>
<div class="as-tile as-mob-tile"><small><span class="as-dot as-dot--muted" aria-hidden="true"></span>Penerimaan</small><strong>{{ $short($metrics['receipts_month']['total']) }}</strong><span>bulan ini</span></div>
</div>
</section>
<div class="as-row2">
<section class="card as-card as-chart" aria-labelledby="as-chart-h">
<h2 id="as-chart-h"><span data-period="today">Penjualan per jam</span><span data-period="7d">Penjualan harian</span><span data-period="30d">Penjualan harian</span></h2>
@foreach($periods as $key => $label)@php $p = $posDashboard[$key]; @endphp
<div data-period="{{ $key }}" data-motion-region data-motion-version="{{ hash('sha256', json_encode($p['buckets'])) }}">
<p class="as-sub">@if($p['peak'])Tertinggi Rp {{ $p['peak']['short'] }} pada {{ $p['peak']['date'] ? $dayLabel($p['peak']['date']) : 'pukul '.$p['peak']['label'] }}@else Belum ada penjualan pada periode ini @endif</p>
<div class="as-plot as-plot--{{ $key }}" role="img" aria-label="Grafik penjualan {{ strtolower($label) }}@if($p['peak']), tertinggi {{ $p['peak']['formatted'] }}@endif">
@foreach($p['buckets'] as $i => $b)<span class="as-col @if($b['peak']) is-peak @endif" style="--h:{{ $b['height'] }};--i:{{ $i }}" title="{{ $b['date'] ? $dayLabel($b['date']) : $b['label'] }}: {{ $b['formatted_total'] }}"><i data-motion-bar="{{ $b['height'] }}" data-motion-key="sale-{{ $b['date'] ?? $b['label'] }}"></i>@if($b['show'])<small>@if($b['date']){{ (int) substr($b['date'], 8, 2) }}<span class="as-desk"> {{ $months[(int) substr($b['date'], 5, 2) - 1] }}</span>@else{{ substr($b['label'], 0, 2) }}@endif</small>@endif</span>@endforeach
</div>
</div>
@endforeach
</section>
<section class="card as-card as-best" aria-labelledby="as-best-h">
<h2 id="as-best-h">Produk terlaris</h2>
@foreach($periods as $key => $label)@php $rows = $posDashboard[$key]['top_products']; @endphp
<div data-period="{{ $key }}" data-motion-region data-motion-version="{{ hash('sha256', json_encode($rows)) }}">
<p class="as-sub"><span class="as-desk">{{ count($rows) ?: 10 }} besar</span><span class="as-mob">{{ min(5, count($rows)) ?: 5 }} besar</span> · {{ strtolower($label) }}</p>
@if(count($rows) === 0)<p class="as-empty">Belum ada barang terjual pada periode ini.</p>@else
<ol class="as-best-list" style="--rows:{{ (int) ceil(count($rows) / 2) }}">@foreach($rows as $i => $row)<li data-motion-item @if($i >= 5) class="as-more" @endif><span class="as-rank">{{ $i + 1 }}</span><span class="as-name">{{ $row['name'] }}</span><strong data-number-text="top-{{ $row['name'] }}">{{ $row['formatted_qty'] }} {{ $row['unit'] }}</strong></li>@endforeach</ol>
@if(count($rows) > 5)<button type="button" class="as-more-btn" aria-expanded="false">Lihat {{ count($rows) }} besar</button>@endif
@endif
</div>
@endforeach
</section>
</div>
<div class="as-row3">
<details class="card as-card as-fold" data-as-fold open>
<summary><span class="as-fold-h"><h2>Uang</h2><small class="as-sub">Posisi per hari ini</small></span><span class="as-fold-val">{{ $short((string) $cashTotal) }}</span></summary>
<div class="as-fold-body">
<a class="as-link" href="#shift" data-task-link="shift">Kas & laporan</a>
<dl class="as-lines">
<div><dt>Tunai di laci<span class="as-desk">&nbsp;(shift terbuka)</span></dt><dd>{{ \App\Services\V2\Money::display($sum['drawer']['total']) }}</dd></div>
<div><dt>Bank terverifikasi</dt><dd>{{ \App\Services\V2\Money::display($sum['bank']) }}</dd></div>
<div class="{{ \Brick\Math\BigDecimal::of((string) $metrics['awaiting']['total'])->isPositive() ? 'is-warn' : '' }}"><dt><span class="as-dot as-dot--warn" aria-hidden="true"></span>Transfer menunggu</dt><dd>{{ \App\Services\V2\Money::display($metrics['awaiting']['total']) }}</dd></div>
<div><dt>Uang muka sopir terbuka</dt><dd>{{ \App\Services\V2\Money::display($metrics['advances']['total']) }}</dd></div>
<div><dt title="Neto seluruh shift yang ditutup bulan ini, termasuk yang sudah diperiksa">Selisih bulan ini (riwayat)</dt><dd>{{ $signed((string) $difference) }}</dd></div>
<div class="{{ $metrics['shifts']['review'] > 0 ? 'is-warn' : '' }}"><dt title="Pemeriksaan selisih yang belum selesai dari semua tanggal; bukan saldo kas">Belum diselesaikan</dt><dd>{{ $signed($unresolvedDifference) }} · {{ $metrics['shifts']['review'] }} shift</dd></div>
</dl>
</div>
</details>
<details class="card as-card as-fold" data-as-fold>
<summary><span class="as-fold-h"><h2>Pengiriman</h2><small class="as-sub">Status surat jalan</small></span><span class="as-fold-val">{{ $deliveries['prepared'] }} siap · {{ $deliveries['sent'] }} jalan</span></summary>
<div class="as-fold-body">
<a class="as-link" href="#pengiriman" data-task-link="pengiriman">Pengiriman</a>
<div class="as-status"><div class="is-ready"><strong>{{ $deliveries['prepared'] }}</strong><span>Siap</span></div><div class="is-sent"><strong>{{ $deliveries['sent'] }}</strong><span>Dikirim</span></div><div class="is-done"><strong>{{ $deliveries['received_today'] }}</strong><span>Diterima hari ini</span></div></div>
@if($sum['unassigned'])<p class="as-alert" role="note">{{ $sum['unassigned'] }}{{ $deliveries['unassigned'] > 1 ? ' dan '.($deliveries['unassigned'] - 1).' surat jalan lain' : '' }} belum ada sopir — tetapkan sebelum berangkat</p>@endif
<dl class="as-lines">
<div><dt>Uang muka sopir terbuka</dt><dd>{{ $metrics['advances']['count'] }} · {{ \App\Services\V2\Money::display($metrics['advances']['total']) }}</dd></div>
<div><dt>Mobil aktif</dt><dd>{{ $metrics['vehicles']['active'] }} dari {{ $metrics['vehicles']['active'] + $metrics['vehicles']['inactive'] }}</dd></div>
</dl>
</div>
</details>
<details class="card as-card as-fold" data-as-fold>
<summary><span class="as-fold-h"><h2>Stok</h2><small class="as-sub">{{ $metrics['stock']['active'] }} produk aktif</small></span><span class="as-fold-val {{ $metrics['stock']['out'] + $metrics['stock']['low'] > 0 ? 'is-bad' : '' }}">{{ $metrics['stock']['out'] }} habis · {{ $metrics['stock']['low'] }} menipis</span></summary>
<div class="as-fold-body">
<a class="as-link" href="#master-product" data-task-link="master-product">Produk</a>
@if(count($watch) === 0)<p class="as-empty">Semua stok di atas batas minimum.</p>@else
<ul class="as-watch">@foreach($watch as $item)<li class="{{ $item['out'] ? 'is-out' : 'is-low' }}"><span class="as-dot as-dot--{{ $item['out'] ? 'bad' : 'warn' }}" aria-hidden="true"></span><span class="as-name">{{ $item['name'] }}</span><strong>{{ $item['qty'] }} {{ $item['unit'] }}</strong><small>min {{ $item['min'] }}</small></li>@endforeach</ul>
@endif
<p class="as-foot">Penerimaan bulan ini: {{ \App\Services\V2\Money::display($metrics['receipts_month']['total']) }} · {{ $metrics['receipts_month']['count'] }} transaksi</p>
</div>
</details>
</div>
</section>
@include('v2.admin-summary-style')
