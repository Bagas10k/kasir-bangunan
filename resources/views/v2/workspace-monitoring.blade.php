@php
    /** Monitor tiap menu. Kartu: [label, nilai, keterangan, nada, filter]. Angka dari MonitorMetrics (penuh, bukan daftar terpotong). */
    $m = $metrics;
    $today = now()->toDateString();
    $dailyOk = is_array($daily);
    $reportLabel = $reportDate === $today ? 'hari ini' : \Carbon\Carbon::parse($reportDate)->locale('id')->translatedFormat('j M Y');
    $attention = fn (int|string $n): ?string => (float) $n > 0 ? 'attention' : null;
    $actionCount = $m['awaiting']['count'] + $m['shifts']['review'] + $m['deliveries']['prepared'];
    $monthLabel = \Carbon\Carbon::parse($today)->locale('id')->translatedFormat('F Y');
    // Persentase hanya untuk keterangan tampilan; nilai uang tetap dari MonitorMetrics (BigDecimal).
    $billed = \Brick\Math\BigDecimal::of($m['receivable']['billed']);
    $paidShare = $billed->isZero() ? '0%' : \Brick\Math\BigDecimal::of($m['receivable']['paid'])->multipliedBy(100)->dividedBy($billed, 0, \Brick\Math\RoundingMode::Down).'%';
    // Judul kartu ringkasan per menu Operasional: [judul, keterangan, pil (teks, nada) atau null].
    $monitoringHeads = [
        'tagihan' => ['Ringkasan tagihan', 'Piutang pelanggan dan utang ke supplier yang belum lunas', $m['awaiting']['count'] > 0 ? [$m['awaiting']['count'].' transfer menunggu verifikasi', 'ok'] : null],
        'pengiriman' => ['Ringkasan pengiriman', 'Status surat jalan saat ini', $m['deliveries']['unassigned'] > 0 ? [$m['deliveries']['unassigned'].' surat jalan belum ada mobil', 'warn'] : null],
        'shift' => ['Ringkasan kas', 'Riwayat selisih bulanan dan pemeriksaan yang belum selesai', $m['shifts']['review'] > 0 ? [$m['shifts']['review'].' shift perlu diperiksa', 'warn'] : null],
        'laporan' => ['Ringkasan harian', 'Tunai, transfer terverifikasi, dan transfer menunggu dipisahkan', null],
        'penerimaan' => ['Ringkasan penerimaan', 'Barang masuk bulan ini: pembelian dan stok awal', ['Khusus admin', 'ok']],
    ];

    $monitoringPages = [
        'shift' => [
            ['Shift terbuka', $m['shifts']['open'], 'shift toko bersama; riwayat pribadi tetap terpisah', 'hero', 'status:open'],
            ['Belum diselesaikan', $rp($m['shifts']['difference_unresolved']), $m['shifts']['review'].' shift · neto pemeriksaan semua tanggal', $attention($m['shifts']['review']), 'status:review'],
            ['Selisih bulan ini (riwayat)', $rp($m['shifts']['difference_month']), 'neto seluruh shift yang ditutup bulan ini, termasuk yang sudah diperiksa', null],
        ],
        'tagihan' => [
            ['Sisa piutang', $rp($m['receivable']['total']), $m['receivable']['count'].' tagihan belum lunas', 'hero', 'direction:receivable'],
            ['Total piutang', $rp($m['receivable']['billed']), 'nilai '.$m['receivable']['count'].' tagihan belum lunas', null],
            ['Terbayar', $rp($m['receivable']['paid']), $paidShare.' dari total piutang', null],
            ['Utang supplier', $rp($m['payable']['total']), $m['payable']['count'] > 0 ? $m['payable']['count'].' tagihan perlu dibayar' : 'tidak ada', $attention($m['payable']['count']) ? 'problem' : null, 'direction:payable'],
        ],
        'pengiriman' => array_values(array_filter([
            ['Siap dikirim', $m['deliveries']['prepared'].' surat jalan', 'menunggu berangkat', 'hero', 'status:prepared'],
            ['Sedang dikirim', $m['deliveries']['sent'], 'dalam perjalanan', null, 'status:sent'],
            ['Diterima hari ini', $m['deliveries']['received_today'], 'sudah sampai di pelanggan', null, 'status:received'],
            $isAdmin ? ['Uang muka terbuka', $rp($m['advances']['total']), $m['advances']['count'].' belum direalisasi', $m['advances']['count'] > 0 ? 'problem' : null] : null,
        ])),
        'laporan' => [
            ['Penjualan '.$reportLabel, $rp($daily['revenue']['total_sales'] ?? '0'), (int) ($daily['sales_count'] ?? 0).' transaksi', 'hero'],
            ['Uang tunai seharusnya di laci', $rp($daily['cash']['expected'] ?? '0'), $dailyOk ? (($daily['cash_scope'] ?? '') === 'store' ? 'laci toko bersama: kas awal + masuk - keluar' : 'kas awal + masuk - keluar') : '', null],
            ['Transfer menunggu verifikasi', $rp($daily['bank']['pending'] ?? '0'), 'belum masuk bank', $attention($daily['bank']['pending'] ?? '0')],
        ],
    ];
    if ($isAdmin) {
        // Ringkasan admin punya halaman sendiri (v2.admin-summary), tanpa kartu monitor di atasnya.
        $monitoringPages['persetujuan'] = [
            ['Menunggu verifikasi', $m['awaiting']['count'], 'transfer dan pembayaran supplier', $attention($m['awaiting']['count']), 'status:pending,proposed'],
            ['Nilai menunggu', $rp($m['awaiting']['total']), 'belum memengaruhi saldo', $attention($m['awaiting']['total'])],
            ['Ditolak 30 hari terakhir', $m['rejected_30d'], null, null, 'status:rejected'],
        ];
        $monitoringPages['penerimaan'] = [
            ['Total penerimaan', $m['receipts_month']['count'].' transaksi', $monthLabel, 'hero'],
            ['Pembelian / restock', $m['receipts_month']['purchase'], 'transaksi masuk', null],
            ['Saldo / stok awal', $m['receipts_month']['opening'], 'stok awal dicatat', null],
            ['Total nilai penerimaan', $rp($m['receipts_month']['total']), 'masuk ke persediaan', null],
        ];
    }
@endphp
@foreach($monitoringPages as $task => $cards)
    @include('partials.monitoring-cards', ['monitoringTask' => $task, 'monitoringTitle' => $monitoringHeads[$task][0] ?? 'Ringkasan '.$task, 'monitoringHead' => $monitoringHeads[$task] ?? null, 'monitoringCards' => $cards])
@endforeach
