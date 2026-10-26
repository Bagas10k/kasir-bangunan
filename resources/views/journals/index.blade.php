@extends('layouts.app')

@section('content')
@php
    $sourceLabels = [
        'opening_balance' => 'Saldo Pembukaan',
        'sale' => 'Penjualan',
        'expense' => 'Pengeluaran',
        'ritase' => 'Ritase',
        'inventory_receipt' => 'Penerimaan Barang',
        'stock_opname' => 'Stok Opname',
        'adjustment' => 'Penyesuaian',
    ];
    $openingStatus = $opening?->status ?? 'not_started';
    $formatMoney = function ($value) {
        $decimal = \Brick\Math\BigDecimal::of((string) $value)->toScale(2, \Brick\Math\RoundingMode::HalfUp);
        $negative = $decimal->isNegative();
        [$whole, $fraction] = explode('.', ltrim((string) $decimal, '-'));
        $formatted = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole) . ',' . $fraction;
        return $negative ? '-' . $formatted : $formatted;
    };
    $openingTotal = \Brick\Math\BigDecimal::of($cashOpening)->plus($bankOpening)->toScale(2);
@endphp
<style>
    .ledger-heading { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; margin-bottom:20px; }
    .ledger-heading h1 { margin:0 0 5px; font-size:24px; letter-spacing:-.025em; color:var(--primary-hover); }
    .ledger-heading p { margin:0; max-width:680px; color:var(--text-muted); font-size:13px; line-height:1.55; }
    .ledger-status { border:1px solid var(--mint-border); background:var(--mint-surface); color:var(--primary-hover); padding:7px 10px; border-radius:var(--radius-inner); font:700 11px var(--font-mono); white-space:nowrap; }
    .balance-strip { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin-bottom:16px; }
    .balance-tile { border:1px solid var(--border-card); border-radius:var(--radius-card); background:var(--surface); padding:16px; min-width:0; }
    .balance-tile small { display:block; color:var(--text-caption); font-size:10px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; margin-bottom:8px; }
    .balance-tile strong { display:block; color:var(--text-main); font:800 clamp(18px,2vw,25px) var(--font-mono); font-variant-numeric:tabular-nums; overflow-wrap:anywhere; }
    .balance-tile span { display:block; color:var(--text-muted); font-size:11.5px; margin-top:5px; }
    .opening-panel { display:grid; grid-template-columns:minmax(0,1.1fr) minmax(280px,.9fr); gap:18px; margin-bottom:18px; }
    .opening-panel .card { margin:0; }
    .section-title { margin:0 0 4px; font-size:16px; color:var(--text-main); }
    .section-copy { margin:0 0 16px; color:var(--text-muted); font-size:12.5px; line-height:1.55; }
    .opening-form { display:grid; grid-template-columns:150px 1fr 1fr; gap:12px; align-items:end; }
    .opening-form .form-group { margin:0; }
    .opening-actions { display:flex; align-items:center; gap:9px; flex-wrap:wrap; margin-top:14px; }
    .lock-note { border-left:3px solid var(--primary); padding:10px 12px; background:var(--mint-surface); color:var(--primary-hover); font-size:12px; line-height:1.55; }
    .opening-list { display:grid; gap:10px; }
    .opening-row { display:flex; justify-content:space-between; gap:16px; border-bottom:1px solid var(--border-subtle); padding-bottom:9px; font-size:12.5px; }
    .opening-row:last-child { border-bottom:0; padding-bottom:0; }
    .opening-row span { color:var(--text-muted); }
    .opening-row strong { color:var(--text-main); font-family:var(--font-mono); font-variant-numeric:tabular-nums; text-align:right; }
    .ledger-filter {
        display: grid;
        grid-template-columns: repeat(2, minmax(130px, 1fr)) minmax(160px, 1.2fr) minmax(140px, 1fr) auto;
        grid-template-areas: "start end source account actions";
        align-items: end;
        gap: 12px;
        margin-bottom: 16px;
        padding: 14px;
        background: #fbfdfc;
        border: 1px solid var(--border-card, #e5e7eb);
        border-radius: 12px;
    }
    .ledger-filter .form-group {
        margin: 0;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 5px;
        min-width: 0;
    }
    .ledger-filter .form-group label {
        color: var(--text-caption, #526359);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .02em;
        white-space: nowrap;
        margin: 0;
    }
    .ledger-filter .form-control {
        min-height: 38px;
        height: 38px;
        padding: 6px 10px;
        font-size: 12px;
        border-radius: 8px;
        border: 1px solid var(--border-control, #d9e2dc);
        background: #ffffff;
        color: var(--text-main, #1c2826);
        width: 100%;
        box-sizing: border-box;
    }
    .ledger-filter .form-control:focus {
        border-color: var(--primary, #2d6a4f);
        outline: none;
        box-shadow: 0 0 0 2px rgba(45, 106, 79, .12);
    }
    .ledger-filter-start { grid-area: start; }
    .ledger-filter-end { grid-area: end; }
    .ledger-filter-source { grid-area: source; }
    .ledger-filter-account { grid-area: account; }
    .ledger-filter-actions {
        grid-area: actions;
        display: flex;
        gap: 8px;
        align-items: center;
        margin: 0;
    }
    .ledger-filter-actions > * { white-space: nowrap; }
    .ledger-filter .btn,
    .ledger-filter .btn-secondary {
        min-height: 38px;
        height: 38px;
        padding: 6px 16px;
        font-size: 12px;
        font-weight: 600;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        white-space: nowrap;
        text-decoration: none;
        box-sizing: border-box;
    }
    .ledger-filter .btn {
        background: var(--primary, #2d6a4f);
        color: #ffffff;
        border: 1px solid var(--primary, #2d6a4f);
    }
    .ledger-filter .btn-secondary {
        background: var(--mint-surface, #e8f5e9);
        color: var(--primary-hover, #1b4332);
        border: 1px solid var(--mint-border, #a3b18a);
    }
    .journal-stack { display:grid; gap:12px; }
    .journal-entry { border:1px solid var(--border-card); border-radius:var(--radius-card); background:var(--surface); overflow:hidden; }
    .journal-head { display:grid; grid-template-columns:120px minmax(0,1fr) auto; gap:14px; align-items:center; padding:14px 16px; background:var(--surface-subtle, #f7f9f7); border-bottom:1px solid var(--border-subtle); }
    .journal-date { color:var(--text-main); font:700 12px var(--font-mono); }
    .journal-desc strong { display:block; font-size:13px; color:var(--text-main); margin-bottom:3px; }
    .journal-desc span { color:var(--text-caption); font:600 10.5px var(--font-mono); text-transform:uppercase; letter-spacing:.04em; }
    .balance-state { color:var(--primary-hover); font-size:10.5px; font-weight:800; text-transform:uppercase; }
    .journal-table { width:100%; margin:0; }
    .journal-table th { font-size:10px; letter-spacing:.06em; }
    .journal-table td { padding-top:10px; padding-bottom:10px; }
    .journal-table .amount { text-align:right; font-family:var(--font-mono); font-variant-numeric:tabular-nums; }
    .journal-total td { font-weight:800; border-top:1px solid var(--border-card); background:var(--mint-surface); }
    .journal-empty { text-align:center; padding:34px 18px; color:var(--text-muted); }
    .feed-note { display:flex; align-items:center; gap:8px; color:var(--text-caption); font-size:11px; margin-top:12px; }
    .feed-dot { width:7px; height:7px; border-radius:50%; background:var(--primary); }
    @media(max-width:900px) {
        .opening-panel { grid-template-columns:1fr; }
        .opening-form { grid-template-columns:1fr; }
        .ledger-filter {
            grid-template-columns: 1fr 1fr;
            grid-template-areas:
                "start end"
                "source account"
                "actions actions";
        }
        .balance-strip { grid-template-columns:1fr; }
    }
    @media(max-width:640px) {
        .ledger-heading { flex-direction:column; }
        .ledger-filter {
            grid-template-columns: minmax(0, 1fr);
            grid-template-areas: "start" "end" "source" "account" "actions";
            gap: 10px;
            padding: 12px;
        }
        .ledger-filter-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: 100%;
        }
        .ledger-filter .btn,
        .ledger-filter .btn-secondary {
            min-height: 40px;
            height: 40px;
            width: 100%;
        }
        .journal-head { grid-template-columns:1fr; gap:7px; }
        .journal-table th:nth-child(2), .journal-table td:nth-child(2) { display:none; }
    }
    @media(prefers-reduced-motion:reduce) { * { scroll-behavior:auto !important; transition:none !important; } }
</style>

<header class="ledger-heading">
    <div>
        <h1>Jurnal Umum</h1>
        <p>Catatan kronologis seluruh perpindahan nilai antar-akun. Bagan Akun tetap menjadi master akun; aktivitas debit dan kredit berada di halaman ini.</p>
    </div>
    <div class="ledger-status" id="feed-status">PEMBARUAN OTOMATIS · 10 DETIK</div>
</header>

@if($errors->has('opening_balance'))
    <div class="alert alert-danger" role="alert">{{ $errors->first('opening_balance') }}</div>
@endif

<section class="balance-strip" aria-label="Ringkasan Kas dan Bank">
    <article class="balance-tile">
        <small>Saldo Akhir Kas Tunai</small>
        <strong>Rp {{ $formatMoney($cashBalance) }}</strong>
        <span>Saldo awal + seluruh mutasi akun 1101</span>
    </article>
    <article class="balance-tile">
        <small>Saldo Akhir Bank</small>
        <strong>Rp {{ $formatMoney($bankBalance) }}</strong>
        <span>Saldo awal + seluruh mutasi akun 1102</span>
    </article>
    <article class="balance-tile">
        <small>Status Saldo Pembukaan</small>
        <strong>{{ $openingStatus === 'posted' ? 'TERKUNCI' : ($openingStatus === 'draft' ? 'DRAF' : 'BELUM DIISI') }}</strong>
        <span>{{ $openingStatus === 'posted' ? 'Koreksi melalui jurnal penyesuaian' : 'Dapat ditinjau sebelum Mulai Pembukuan' }}</span>
    </article>
</section>

<section class="opening-panel">
    <div class="card">
        <h2 class="section-title">Saldo Pembukaan</h2>
        <p class="section-copy">Isi nilai Kas Tunai dan Bank pada tanggal mulai pembukuan. Sistem otomatis membuat lawan jurnal ke Ekuitas Saldo Awal agar Neraca tetap seimbang.</p>

        @if($openingStatus !== 'posted')
        <form method="POST" action="{{ route('journals.opening.save') }}">
            @csrf
            <div class="opening-form">
                <div class="form-group">
                    <label for="transaction_date">Tanggal Mulai</label>
                    <input class="form-control" id="transaction_date" name="transaction_date" type="date" value="{{ old('transaction_date', $opening?->transaction_date ?? now()->format('Y-m-d')) }}" required>
                </div>
                <div class="form-group">
                    <label for="cash_amount">Saldo Awal Kas</label>
                    <input class="form-control" id="cash_amount" name="cash_amount" type="text" inputmode="decimal" data-currency-input data-currency-scale="2" value="{{ old('cash_amount', $cashOpening) }}" required>
                </div>
                <div class="form-group">
                    <label for="bank_amount">Saldo Awal Bank</label>
                    <input class="form-control" id="bank_amount" name="bank_amount" type="text" inputmode="decimal" data-currency-input data-currency-scale="2" value="{{ old('bank_amount', $bankOpening) }}" required>
                </div>
            </div>
            <div class="opening-actions">
                <button class="btn" type="submit">Simpan Draf</button>
                @if($openingStatus === 'draft')
                    <button class="btn btn-secondary" type="submit" form="post-opening-form">Mulai Pembukuan</button>
                @endif
            </div>
        </form>
        @if($openingStatus === 'draft')
        <form id="post-opening-form" method="POST" action="{{ route('journals.opening.post') }}" onsubmit="return confirm('Mulai pembukuan sekarang? Saldo awal akan terkunci permanen dan koreksi berikutnya wajib melalui jurnal penyesuaian.');">
            @csrf
        </form>
        @endif
        @else
            <div class="lock-note"><strong>Saldo awal telah diposting.</strong> Angka pembukaan tidak dapat diubah atau dihapus. Koreksi dilakukan melalui jurnal penyesuaian agar jejak audit tetap utuh.</div>
        @endif
    </div>

    <aside class="card">
        <h2 class="section-title">Rincian Pembukaan</h2>
        <p class="section-copy">Sumber pembentukan saldo awal yang menjadi titik mulai Buku Besar.</p>
        <div class="opening-list">
            <div class="opening-row"><span>1101 · Kas Tunai</span><strong>Rp {{ $formatMoney($cashOpening) }}</strong></div>
            <div class="opening-row"><span>1102 · Bank</span><strong>Rp {{ $formatMoney($bankOpening) }}</strong></div>
            <div class="opening-row"><span>3101 · Ekuitas Saldo Awal</span><strong>Rp {{ $formatMoney($openingTotal) }}</strong></div>
            <div class="opening-row"><span>Tanggal mulai</span><strong>{{ $opening?->transaction_date ? \Carbon\Carbon::parse($opening->transaction_date)->format('d/m/Y') : '—' }}</strong></div>
        </div>
    </aside>
</section>

<section class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:14px;">
        <div>
            <h2 class="section-title">Aktivitas Perpindahan Nilai</h2>
            <p class="section-copy" style="margin-bottom:0;">Setiap blok adalah satu transaksi yang harus memiliki total debit dan kredit sama.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('journals.index') }}" class="ledger-filter">
        <div class="form-group ledger-filter-start">
            <label for="start_date">Dari Tanggal</label>
            <input class="form-control" id="start_date" name="start_date" type="date" value="{{ $filters['start_date'] ?? '' }}">
        </div>
        <div class="form-group ledger-filter-end">
            <label for="end_date">Sampai Tanggal</label>
            <input class="form-control" id="end_date" name="end_date" type="date" value="{{ $filters['end_date'] ?? '' }}">
        </div>
        <div class="form-group ledger-filter-source">
            <label for="source">Sumber Aktivitas</label>
            <select class="form-control" id="source" name="source">
                <option value="">Semua sumber</option>
                @foreach($sources as $source)
                    <option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ $sourceLabels[$source] ?? ucfirst(str_replace('_', ' ', $source)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group ledger-filter-account">
            <label for="account">Kode Akun</label>
            <input class="form-control" id="account" name="account" value="{{ $filters['account'] ?? '' }}" placeholder="Semua akun">
        </div>
        <div class="ledger-filter-actions">
            <button class="btn" type="submit">Terapkan</button>
            <a class="btn btn-secondary" href="{{ route('journals.index') }}">Reset</a>
        </div>
    </form>

    <div class="journal-stack" id="journal-stack" data-latest-id="{{ (int) ($entries->max('id') ?? 0) }}">
        @forelse($entries as $entry)
        <article class="journal-entry" data-entry-id="{{ $entry['id'] }}">
            <header class="journal-head">
                <div class="journal-date">{{ \Carbon\Carbon::parse($entry['transaction_date'])->format('d/m/Y') }}</div>
                <div class="journal-desc">
                    <strong>{{ $entry['description'] }}</strong>
                    <span>{{ $sourceLabels[$entry['reference_type']] ?? str_replace('_', ' ', $entry['reference_type']) }} · #{{ $entry['id'] }}</span>
                </div>
                <div class="balance-state">{{ $entry['is_balanced'] ? 'Seimbang' : 'Tidak Seimbang' }}</div>
            </header>
            <div style="overflow-x:auto;">
                <table class="journal-table">
                    <thead><tr><th>Kode</th><th>Nama Akun</th><th style="text-align:right;">Debit (Rp)</th><th style="text-align:right;">Kredit (Rp)</th></tr></thead>
                    <tbody>
                    @foreach($entry['lines'] as $line)
                        <tr>
                            <td><span class="sku-code">{{ $line['account_code'] }}</span></td>
                            <td>{{ $line['account_name'] }}</td>
                            <td class="amount">{{ \Brick\Math\BigDecimal::of($line['debit'])->isGreaterThan(0) ? $formatMoney($line['debit']) : '—' }}</td>
                            <td class="amount">{{ \Brick\Math\BigDecimal::of($line['credit'])->isGreaterThan(0) ? $formatMoney($line['credit']) : '—' }}</td>
                        </tr>
                    @endforeach
                    <tr class="journal-total"><td colspan="2">Total</td><td class="amount">{{ $formatMoney($entry['total_debit']) }}</td><td class="amount">{{ $formatMoney($entry['total_credit']) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </article>
        @empty
            <div class="journal-empty">Belum ada aktivitas jurnal pada filter ini.</div>
        @endforelse
    </div>
    <div class="feed-note"><span class="feed-dot" aria-hidden="true"></span><span id="feed-note-text">Catatan terbaru diperiksa saat halaman aktif.</span></div>
</section>

<script>
(function () {
    const stack = document.getElementById('journal-stack');
    const status = document.getElementById('feed-status');
    const note = document.getElementById('feed-note-text');
    if (!stack || !window.fetch) return;

    const sourceLabels = @json($sourceLabels);
    const money = value => {
        const raw = String(value ?? '0').trim();
        const negative = raw.startsWith('-');
        const clean = negative ? raw.slice(1) : raw;
        const [wholeRaw, fractionRaw = ''] = clean.split('.');
        const whole = (wholeRaw || '0').replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return `${negative ? '-' : ''}${whole},${(fractionRaw + '00').slice(0, 2)}`;
    };
    const isPositiveMoney = value => {
        const raw = String(value ?? '0').trim();
        return !raw.startsWith('-') && !/^0*(?:\.0*)?$/.test(raw);
    };
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    const render = entry => {
        const rows = entry.lines.map(line => `<tr><td><span class="sku-code">${escapeHtml(line.account_code)}</span></td><td>${escapeHtml(line.account_name)}</td><td class="amount">${isPositiveMoney(line.debit) ? money(line.debit) : '—'}</td><td class="amount">${isPositiveMoney(line.credit) ? money(line.credit) : '—'}</td></tr>`).join('');
        const date = entry.transaction_date.split('-').reverse().join('/');
        return `<article class="journal-entry" data-entry-id="${entry.id}"><header class="journal-head"><div class="journal-date">${date}</div><div class="journal-desc"><strong>${escapeHtml(entry.description)}</strong><span>${escapeHtml(sourceLabels[entry.reference_type] || entry.reference_type.replaceAll('_', ' '))} · #${entry.id}</span></div><div class="balance-state">${entry.is_balanced ? 'Seimbang' : 'Tidak Seimbang'}</div></header><div style="overflow-x:auto"><table class="journal-table"><thead><tr><th>Kode</th><th>Nama Akun</th><th style="text-align:right">Debit (Rp)</th><th style="text-align:right">Kredit (Rp)</th></tr></thead><tbody>${rows}<tr class="journal-total"><td colspan="2">Total</td><td class="amount">${money(entry.total_debit)}</td><td class="amount">${money(entry.total_credit)}</td></tr></tbody></table></div></article>`;
    };

    async function refreshFeed() {
        if (document.hidden) return;
        try {
            const after = Number(stack.dataset.latestId || 0);
            const response = await fetch(`{{ route('journals.feed') }}?after_id=${after}`, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json();
            if (payload.entries.length) {
                stack.querySelector('.journal-empty')?.remove();
                // All server fields are escaped in render(); only fixed ledger markup reaches this sink.
                payload.entries.forEach(entry => stack.insertAdjacentHTML('afterbegin', render(entry)));
                stack.dataset.latestId = String(payload.latest_id);
                note.textContent = `${payload.entries.length} aktivitas baru dimuat tanpa memuat ulang halaman.`;
            } else {
                note.textContent = 'Tidak ada aktivitas baru. Pemeriksaan terakhir berhasil.';
            }
            status.textContent = 'TERHUBUNG · PEMBARUAN 10 DETIK';
        } catch (error) {
            status.textContent = 'TERPUTUS · COBA LAGI OTOMATIS';
            note.textContent = 'Feed gagal diperiksa; data yang sudah tampil tetap aman.';
        }
    }

    let timer = setInterval(refreshFeed, 10000);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            clearInterval(timer);
            timer = null;
        } else {
            refreshFeed();
            if (!timer) timer = setInterval(refreshFeed, 10000);
        }
    });
})();
</script>
@endsection
