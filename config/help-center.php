<?php

return [
    'reviewed' => '27 November 2026',
    // Penanda otomatis teks panduan (lihat App\Support\HelpText).
    'markup' => [
        // Sebutan menu (peka huruf besar) => artikel tujuan, tampil sebagai tag tautan.
        'links' => [
            'Kas harian' => 'shift',
            'Riwayat penjualan' => 'riwayat-penjualan',
            'Status pembayaran' => 'persetujuan',
            'Tagihan' => 'tagihan',
            'Pindah dana' => 'pindah-dana',
            'Sesuaikan saldo' => 'koreksi-saldo-kas-bank',
            'Saldo awal pembukuan' => 'saldo-awal',
            'Jurnal umum' => 'jurnal',
            'Pengeluaran' => 'pengeluaran',
            'Tahan transaksi' => 'tahan-transaksi',
            'Laporan harian' => 'laporan',
            'Laba rugi' => 'laba-rugi',
            'Arus kas' => 'arus-kas',
            'Neraca' => 'neraca',
            'Bagan akun' => 'bagan-akun',
            'Impor Excel' => 'impor-excel',
            'Draf penerimaan' => 'ubah-draf-penerimaan',
            'Riwayat penerimaan' => 'penerimaan',
            'retur pembelian' => 'retur-pembelian',
            'retur penjualan' => 'retur-penjualan',
            'stok opname' => 'stok-opname',
            'Stok opname' => 'stok-opname',
            'harga kontraktor' => 'harga-kontraktor',
            'Harga kontraktor' => 'harga-kontraktor',
        ],
        // Istilah yang dicetak miring (tidak peka huruf besar).
        'terms' => ['HPP', 'harga modal', 'rata-rata tertimbang', 'piutang', 'utang', 'debit', 'kredit', 'aset', 'kewajiban', 'ekuitas', 'persediaan', 'uang muka', 'realisasi', 'draf', 'jatuh tempo', 'beban', 'selisih'],
    ],
    // Pencarian: kata yang diabaikan dan kelompok kata yang dianggap sama artinya.
    'search' => [
        'stopwords' => ['cara', 'gimana', 'bagaimana', 'kenapa', 'mengapa', 'kok', 'apa', 'yang', 'untuk', 'di', 'ke', 'dari', 'dan', 'atau', 'saya', 'aku', 'mau', 'ingin', 'bisa', 'tolong', 'mohon', 'agar', 'supaya', 'dengan', 'pada', 'ini', 'itu'],
        'synonyms' => [
            ['bayar', 'lunas', 'cicil', 'cicilan', 'pembayaran'],
            ['tagihan', 'tagih', 'piutang', 'utang', 'hutang'],
            ['nota', 'struk', 'faktur', 'invoice', 'kuitansi'],
            ['kas', 'laci', 'shift'],
            ['barang', 'produk', 'stok', 'item'],
            ['supplier', 'pemasok', 'suplier'],
            ['pelanggan', 'pembeli', 'customer', 'konsumen'],
            ['transfer', 'bank', 'rekening'],
            ['kirim', 'antar', 'pengiriman', 'sopir', 'armada'],
            ['retur', 'kembalikan', 'pengembalian'],
            ['harga', 'tarif'],
            ['hapus', 'arsip', 'arsipkan'],
            ['cetak', 'print'],
            ['penerimaan', 'restok', 'pembelian', 'beli'],
            ['biaya', 'pengeluaran', 'beban', 'ongkos'],
            ['laporan', 'rekap'],
            ['modal', 'hpp'],
            ['akun', 'login', 'password', 'username'],
        ],
    ],
    // Ilustrasi: posisi sorotan, label dari layar, petunjuk lokasi.
    'illustrations' => [
        'kasir' => ['footer', 'Lihat keranjang', 'Setelah barang ditambahkan, cari tombol ini pada bar ringkasan keranjang.'],
        'tahan-transaksi' => ['footer-start', 'Tahan transaksi', 'Tombol ini ada di bagian bawah keranjang, di sebelah kiri tombol pembayaran.'],
        'harga-kontraktor' => ['field', 'Kontraktor', 'Pilihan jenis harga ada di area pembeli pada keranjang atau pembayaran.'],
        'shift' => ['heading', 'Kas harian', 'Buka bagian ini untuk memeriksa apakah kas toko sudah aktif.'],
        'riwayat-penjualan' => ['toolbar', 'Filter', 'Cari Filter di atas daftar nota jika ingin memilih tanggal atau status.'],
        'tagihan' => ['row', 'Detail', 'Buka aksi pada baris tagihan yang dipilih untuk melihat sisa dan riwayat pembayaran.'],
        'bayar-supplier' => ['tab', 'Utang supplier', 'Pilih tab ini di halaman Tagihan sebelum mencari tagihan pemasok.'],
        'sumber-dana-supplier' => ['field', 'Sumber dana', 'Pilihan ini muncul di formulir pembayaran tagihan supplier.'],
        'transfer-ditolak' => ['row', 'Ditolak', 'Cari tanda ini pada kolom Status di daftar pembayaran.'],
        'penerimaan' => ['footer', 'Periksa penerimaan', 'Tombol ini ada di bagian bawah formulir barang masuk, sebelum simpan draf.'],
        'ubah-draf-penerimaan' => ['row', 'Ubah draf', 'Buka aksi pada baris di daftar Draf penerimaan, bukan di riwayat penerimaan final.'],
        'stok-opname' => ['toolbar', '+ Catat stok opname', 'Cari tombol di bagian atas daftar Stok opname.'],
        'impor-excel' => ['toolbar', 'Impor Excel', 'Buka menu master tujuan. Tombol ini berada di kepala daftar data.'],
        'aktifkan-data' => ['row', 'Aktifkan kembali', 'Gunakan filter Diarsipkan, lalu buka aksi pada baris data yang perlu dipulihkan.'],
        'koreksi-saldo-kas-bank' => ['heading', 'Sesuaikan saldo', 'Cari tombol pada bagian Penyesuaian saldo di Jurnal umum.'],
        'mulai' => ['heading', 'Operasional', 'Grup menu Operasional di sidebar berisi pekerjaan harian: Kasir, Riwayat penjualan, Tagihan, dan Kas harian.'],
        'ringkasan' => ['heading', 'Perlu ditindaklanjuti', 'Bagian ini ada paling atas di Ringkasan dan hanya berisi pekerjaan yang masih tertunda.'],
        'akun' => ['toolbar', 'Tambah akun', 'Tombol ini ada di kepala daftar Akun login.'],
        'tutup-kas' => ['row', 'Tutup kas harian', 'Tombol ini ada pada baris kas harian yang masih aktif di daftar Kas harian.'],
        'persetujuan' => ['row', 'Menunggu verifikasi', 'Status ini tampil di kolom Status. Artinya pembayaran belum dihitung sebagai pelunasan.'],
        'verifikasi-pembayaran' => ['row', 'Verifikasi pembayaran', 'Tombol ini ada di aksi baris pengajuan. Tekan setelah nominal dan bukti dicocokkan.'],
        'pengiriman' => ['row', 'Kirim barang', 'Tombol ini ada di aksi baris pengiriman yang sudah siap berangkat.'],
        'biaya-sopir' => ['footer', 'Simpan realisasi', 'Tombol ini ada di bawah formulir Realisasi uang muka, setelah BBM dan uang jalan diisi.'],
        'laporan' => ['toolbar', 'Tampilkan laporan', 'Pilih Tanggal laporan, lalu tekan tombol ini di bagian atas Laporan harian.'],
        'retur-penjualan' => ['footer-start', 'Catat retur penjualan', 'Bagian ini ada di dalam Detail nota. Buka untuk memilih barang dan jumlah yang dikembalikan.'],
        'retur-pembelian' => ['row', 'Retur', 'Tombol ini ada di aksi baris Riwayat penerimaan yang sudah final.'],
        'master-product' => ['toolbar', 'Tambah produk', 'Tombol ini ada di kepala daftar Produk, di samping Impor Excel.'],
        'master-supplier' => ['toolbar', 'Tambah supplier', 'Tombol ini ada di kepala daftar Supplier.'],
        'master-customer' => ['toolbar', 'Tambah pelanggan', 'Tombol ini ada di kepala daftar Pelanggan.'],
        'master-employee' => ['toolbar', 'Tambah karyawan', 'Tombol ini ada di kepala daftar Karyawan & sopir.'],
        'master-vehicle' => ['toolbar', 'Tambah mobil', 'Tombol ini ada di kepala daftar Mobil.'],
        'master-unit' => ['toolbar', 'Tambah satuan', 'Tombol ini ada di kepala daftar Satuan.'],
        'master-category' => ['toolbar', 'Tambah kategori', 'Tombol ini ada di kepala daftar Kategori.'],
        'pengeluaran' => ['toolbar', '+ Catat pengeluaran', 'Tombol ini ada di bagian atas halaman Pengeluaran.'],
        'jurnal' => ['toolbar', 'Terapkan', 'Atur periode atau cari uraian di Filter, lalu tekan Terapkan untuk menampilkan jurnal.'],
        'pindah-dana' => ['heading', 'Pindah dana', 'Kartu dan tombol Pindah dana ada di halaman Jurnal umum, di samping Penyesuaian saldo.'],
        'saldo-awal' => ['heading', 'Saldo awal pembukuan', 'Kartu ini ada di menu Kas harian dan hanya bisa diposting sekali.'],
        'neraca' => ['toolbar', 'Tampilkan', 'Pilih tanggal laporan, lalu tekan Tampilkan untuk memperbarui Neraca.'],
        'laba-rugi' => ['toolbar', 'Tampilkan', 'Pilih rentang tanggal, lalu tekan Tampilkan untuk memperbarui Laba rugi.'],
        'arus-kas' => ['toolbar', 'Tampilkan', 'Pilih rentang tanggal, lalu tekan Tampilkan untuk memperbarui Arus kas.'],
        'bagan-akun' => ['toolbar', 'Tambah Akun', 'Tombol ini ada di kepala daftar Bagan akun.'],
    ],
    // Penjelasan: cara kerja fitur dalam bahasa pengguna (1–2 paragraf per artikel).
    'details' => [
        'mulai' => [
            'Setiap transaksi di POS saling terhubung. Saat nota penjualan disimpan, stok barang berkurang, uang atau tagihan pelanggan tercatat, dan pembukuan dibuat otomatis. Petugas tidak perlu mencatat hal yang sama di beberapa tempat.',
            'Pekerjaan harian dimulai dari Kas harian, berlanjut ke penjualan dan penagihan, lalu ditutup dengan pemeriksaan kas dan laporan. Admin mengurus persiapan data, barang masuk, pembayaran, dan keuangan; kasir berfokus melayani pembeli.',
        ],
        'ringkasan' => [
            'Ringkasan adalah halaman pemantauan khusus admin. Isinya hanya membaca data: penjualan, uang di laci dan rekening, tagihan yang lewat jatuh tempo, pengiriman, serta barang yang hampir habis. Tidak ada transaksi yang bisa diubah dari halaman ini.',
            'Bagian Perlu ditindaklanjuti hanya menampilkan pekerjaan yang masih tertunda. Bila semuanya selesai, bagian itu menunjukkan bahwa semua beres. Pilihan periode (hari ini, 7 hari, 30 hari) hanya memengaruhi angka penjualan dan grafik.',
        ],
        'akun' => [
            'Akun login adalah identitas untuk masuk ke aplikasi. Setiap akun punya satu peran: admin atau kasir. Peran menentukan menu yang tampil dan tindakan yang boleh dilakukan; pemeriksaan hak akses juga dilakukan oleh server, bukan hanya oleh tampilan.',
            'Akun login berbeda dari data karyawan. Saat membuat akun, isi username atau email, dan buat password. Jika username diisi, email boleh kosong. Mengubah username, peran, status, atau password mengakhiri sesi akun tersebut. Nonaktifkan akun yang tidak lagi dipakai agar riwayat transaksi tetap utuh.',
            'Akun bisa memakai nama pengguna singkat sehingga tidak wajib memakai email. Jika nama pengguna diubah, sesi lama orang tersebut berakhir dan ia perlu masuk kembali.',
        ],
        'kasir' => [
            'Kasir menyimpan satu nota untuk setiap penjualan. Harga dihitung ulang oleh server dari data produk, sehingga angka di layar tidak bisa diubah sembarangan. Saat nota tersimpan, stok berkurang dan pembukuan dibuat otomatis.',
            'Hasil pembayaran berbeda menurut metodenya. Tunai langsung lunas dan uangnya masuk laci. Transfer menunggu pemeriksaan admin sebelum dianggap lunas. Kredit mencatat sisa yang belum dibayar sebagai piutang pelanggan; uang muka boleh dibayar tunai atau transfer.',
        ],
        'tahan-transaksi' => [
            'Tahan transaksi menyimpan isi keranjang sementara di browser yang sedang dipakai. !!Keranjang tersebut belum menjadi nota, belum mengurangi stok, dan belum masuk laporan.!!',
            'Hingga 10 keranjang bisa ditahan. Karena tersimpan di browser, keranjang tertahan tidak terlihat di perangkat lain dan bisa hilang bila data browser dibersihkan. Selesaikan transaksi tertahan pada hari yang sama bila memungkinkan.',
        ],
        'harga-kontraktor' => [
            'Setiap produk punya harga jual biasa dan boleh punya harga kontraktor. Pelanggan yang ditandai kontraktor otomatis memakai harga kontraktor, tetapi kasir tetap bisa memilih harga eceran untuk transaksi tertentu.',
            'Jenis harga yang dipakai tersimpan di nota sebagai jejak. Barang yang tidak punya harga kontraktor tetap memakai harga jual biasa walaupun pilihan Kontraktor aktif.',
        ],
        'shift' => [
            'Kas harian adalah catatan satu laci uang toko untuk satu tanggal. Semua kasir memakai laci yang sama, sedangkan nama petugas tetap tercatat pada setiap nota dan pembayaran.',
            '!!Penjualan hanya bisa disimpan saat kas harian aktif.!! Uang di laci dihitung dari kas awal, ditambah uang tunai yang masuk, dikurangi uang tunai yang keluar. Transfer dan saldo bank tidak termasuk uang laci.',
        ],
        'tutup-kas' => [
            'Admin membuka kas harian sekali per tanggal dengan modal awal, lalu menutupnya di akhir hari. !!Kas yang sudah ditutup tidak bisa dibuka lagi pada tanggal yang sama.!!',
            'Saat menutup kas, sistem menampilkan rekap penjualan dan perkiraan uang yang seharusnya ada di laci. Admin menghitung uang fisik dan memasukkan hasilnya. Jika berbeda, selisihnya tercatat dan kas ditandai perlu diperiksa. Jika rekap berubah karena ada transaksi baru, admin diminta mengambil rekap terbaru.',
        ],
        'riwayat-penjualan' => [
            'Riwayat penjualan berisi semua nota toko, termasuk transaksi petugas lain. Daftar awalnya menampilkan hari ini; tanggal, metode, dan status pembayaran bisa diubah lewat Filter.',
            'Detail nota memuat barang, sisa tagihan, serta riwayat pembayarannya. !!Nota yang sudah tersimpan tidak bisa dihapus.!! Jika ada barang yang dikembalikan, admin mencatatnya sebagai retur penjualan dari nota yang sama.',
        ],
        'tagihan' => [
            'Tagihan mengumpulkan dua jenis kewajiban: piutang, yaitu uang yang masih harus dibayar pelanggan, dan utang supplier, yaitu uang yang masih harus dibayar toko. Pembayaran sebagian boleh dicatat berkali-kali sampai sisanya nol.',
            'Status tagihan berubah otomatis: Belum lunas, Dibayar sebagian, lalu Lunas. !!Hanya pembayaran berstatus tercatat yang mengurangi sisa.!! Transfer yang masih diperiksa atau sudah ditolak tetap terlihat di riwayat, tetapi tidak dihitung sebagai pelunasan.',
        ],
        'bayar-supplier' => [
            'Pembayaran yang dicatat admin langsung mengurangi utang toko kepada supplier. Pembayaran yang dicatat kasir menjadi pengajuan; utang baru berkurang setelah admin menyetujuinya.',
            'Nominal tidak boleh melebihi sisa tagihan, dan saldo sumber dana tidak boleh menjadi negatif. Sistem memberi peringatan jika ada pembayaran lain yang baru dicatat untuk tagihan yang sama.',
        ],
        'sumber-dana-supplier' => [
            'Sumber dana menunjukkan dari mana uang benar-benar keluar. Kas Utama dan rekening Bank memakai saldo toko, tidak memerlukan kas harian, dan tidak mengubah hitungan laci.',
            'Laci shift memakai uang tunai di laci kas harian yang sedang aktif, sehingga uang yang keluar tercatat di rekap laci. Pilihan ini hanya bisa dipakai jika kas harian masih terbuka dan uang di laci cukup.',
        ],
        'persetujuan' => [
            'Status pembayaran memperlihatkan perjalanan sebuah pembayaran: Diajukan atau Menunggu berarti belum dihitung, Tercatat berarti sudah mengurangi tagihan, dan Ditolak berarti tidak pernah masuk pembukuan.',
            'Kasir hanya melihat pembayaran yang ia ajukan sendiri. Admin melihat semua pengajuan dan dapat memeriksa foto bukti transfer langsung dari daftar.',
        ],
        'transfer-ditolak' => [
            'Transfer ditolak bila admin tidak menemukan uang yang cocok di rekening atau buktinya tidak sesuai. Pembayaran yang ditolak tidak mengurangi tagihan dan tetap tersimpan di riwayat beserta alasannya.',
            '!!Foto bukti hanya membantu pemeriksaan; foto saja tidak membuat pembayaran lunas.!! Pastikan masalahnya jelas sebelum mencatat pembayaran baru, supaya uang yang sama tidak tercatat dua kali.',
        ],
        'verifikasi-pembayaran' => [
            '!!Admin menyetujui transfer pelanggan setelah uangnya benar-benar terlihat masuk di rekening toko.!! Persetujuan mengurangi sisa tagihan dan membuat pembukuan otomatis.',
            'Jika toko punya lebih dari satu rekening, admin dapat membetulkan rekening tujuan saat menyetujui. Untuk pengajuan pembayaran supplier, sistem memeriksa bahwa saldo sumber dana cukup sebelum pembayaran dicatat.',
        ],
        'pengiriman' => [
            'Pengiriman dibuat dari nota yang barangnya diantar armada toko. Statusnya bergerak dari Disiapkan, Dikirim, sampai Diterima, dan dicatat sendiri, terpisah dari status pembayaran.',
            'Surat jalan bisa dicetak dari nota terkait tanpa menampilkan harga. Sopir dan mobil yang dipilih harus berstatus aktif di Master data.',
        ],
        'biaya-sopir' => [
            'Uang muka adalah uang yang diberikan kepada sopir sebelum berangkat. Uang ini belum dianggap biaya, karena pemakaiannya belum diketahui.',
            'Setelah perjalanan, admin mencatat realisasi biaya seperti BBM dan uang jalan. Biaya yang terpakai dicatat sebagai beban perjalanan, dan sisa uang muka dikembalikan oleh sopir. Jika biaya melebihi uang muka, kekurangannya dicatat terpisah lewat Pengeluaran.',
        ],
        'laporan' => [
            'Laporan harian merangkum penjualan, pembayaran tunai dan transfer, serta pergerakan laci untuk satu tanggal. Tanggal yang dipakai adalah tanggal kas harian dibuka, sehingga transaksi lewat tengah malam tetap masuk ke hari kas tersebut.',
            'Admin melihat seluruh toko, sedangkan kasir hanya melihat transaksinya sendiri. Laporan hanya membaca data dan tidak mengubah angka apa pun.',
        ],
        'penerimaan' => [
            'Barang masuk dari supplier dicatat dalam dua tahap. !!Draf boleh diubah atau dibatalkan dan belum memengaruhi stok, harga modal, maupun utang.!!',
            'Saat draf dikonfirmasi, stok bertambah, harga modal barang dihitung ulang dengan rata-rata tertimbang, dan utang kepada supplier tercatat. Dokumen yang sudah dikonfirmasi terkunci. Ongkos kirim dicatat sebagai biaya, bukan menambah harga modal barang.',
        ],
        'ubah-draf-penerimaan' => [
            'Selama masih draf, penerimaan barang bisa diubah sebanyak yang diperlukan tanpa memengaruhi stok atau utang. Draf yang dibatalkan tetap terlihat beserta alasannya agar jejaknya jelas.',
            '!!Setelah dikonfirmasi, penerimaan terkunci dan tidak bisa diubah.!! Kesalahan pada dokumen final diperbaiki lewat retur pembelian, bukan dengan mencatat penerimaan baru yang sama.',
        ],
        'stok-opname' => [
            'Stok opname membandingkan jumlah barang di sistem dengan hasil hitung fisik di gudang. Sistem menyesuaikan stok ke hasil hitung dan menyimpan alasannya.',
            'Nilai selisihnya dibukukan otomatis: barang yang kurang dicatat sebagai kerugian selisih stok, barang yang lebih dicatat sebagai keuntungan selisih stok. Riwayat opname tetap tersimpan untuk diperiksa.',
        ],
        'retur-penjualan' => [
            'Retur penjualan mencatat barang yang dikembalikan pelanggan dari nota asal. Stok barang bertambah kembali, dan pendapatan dari nota itu dikurangi.',
            'Jika nota masih punya sisa tagihan, nilai retur lebih dulu mengurangi tagihan tersebut. Jika masih ada kelebihan, barulah uang dikembalikan kepada pelanggan lewat laci, Kas, atau Bank. Nota asal tidak dihapus.',
        ],
        'retur-pembelian' => [
            'Retur pembelian mencatat barang yang dikembalikan toko kepada supplier dari penerimaan yang sudah final. Stok barang berkurang sesuai jumlah yang dikembalikan.',
            'Nilai retur mengurangi utang kepada supplier. Jika utangnya sudah dibayar, supplier mengembalikan uang ke Kas atau Bank. !!Jumlah retur tidak boleh melebihi stok yang masih ada di gudang.!!',
        ],
        'master-product' => [
            'Data produk menentukan nama, kode, satuan, kategori, harga jual, harga kontraktor, dan batas stok minimum. Barang yang stoknya sama dengan atau di bawah batas minimum ditandai menipis di Kasir dan Ringkasan.',
            'Stok tidak diubah dari menu ini. Stok bertambah lewat penerimaan barang, berkurang lewat penjualan, dan disesuaikan lewat stok opname atau retur. Harga modal dihitung otomatis saat penerimaan dikonfirmasi.',
        ],
        'master-supplier' => [
            'Supplier adalah pemasok barang toko. Produk yang ditautkan ke supplier akan muncul saat mencatat penerimaan barang dari supplier tersebut.',
            'Utang muncul dari penerimaan barang yang dikonfirmasi atau dari saldo awal. Di rincian supplier, pilih Rentang tanggal lalu Tampilkan untuk melihat tagihan dan pembayaran menurut tanggal pencatatan. Gunakan Sebelumnya atau Berikutnya jika ada lebih dari satu halaman. Cetak periode mencetak seluruh aktivitas pada tanggal yang dipilih. Sisa utang di ringkasan menunjukkan kondisi saat ini; pengajuan yang belum disetujui tidak menguranginya.',
        ],
        'master-customer' => [
            'Data pelanggan diperlukan untuk penjualan transfer dan kredit, untuk mencatat piutang, dan untuk menentukan harga eceran atau kontraktor secara otomatis.',
            'Pelanggan bisa ditambahkan dari menu ini atau langsung dari Kasir saat mengetik nama baru. Detail pelanggan menampilkan riwayat belanja dan sisa piutang.',
        ],
        'master-employee' => [
            'Data karyawan dan sopir dipakai dalam kegiatan operasional, misalnya memilih sopir untuk pengiriman. Data ini berbeda dari akun login: membuat karyawan tidak otomatis membuat akun untuk masuk aplikasi.',
            'Sopir internal terhubung dengan data karyawan, sedangkan sopir eksternal dicatat tersendiri. Mengarsipkan data tidak menghapus riwayat pengiriman yang sudah tercatat.',
        ],
        'master-vehicle' => [
            'Data mobil berisi armada toko yang dipakai untuk mengantar barang. Hanya mobil berstatus aktif yang bisa dipilih saat menyiapkan pengiriman.',
            'Detail mobil menampilkan riwayat perjalanannya. Mengubah atau mengarsipkan data mobil tidak mengubah pengiriman yang sudah tercatat.',
        ],
        'master-unit' => [
            'Satuan menunjukkan cara barang dihitung, misalnya sak, batang, meter, atau meter kubik. Jumlah boleh memakai desimal, misalnya 2,5 meter kubik.',
            'Sistem tidak mengubah satu satuan ke satuan lain secara otomatis. Jika barang dijual per buah, catat penerimaan dan penjualannya juga per buah.',
        ],
        'master-category' => [
            'Kategori mengelompokkan produk supaya mudah dicari dan disaring di katalog Kasir.',
            'Kategori tidak memengaruhi harga, stok, atau pembukuan. Mengganti kategori produk hanya mengubah pengelompokan tampilannya.',
        ],
        'impor-excel' => [
            'Impor Excel menambah banyak data master sekaligus: produk, pelanggan, supplier, karyawan, atau mobil. Sistem memeriksa semua baris dan menampilkan pratinjau sebelum data disimpan.',
            '!!Penyimpanan bersifat semua atau tidak sama sekali: jika satu baris salah, tidak ada baris yang tersimpan.!! Rumus Excel, kolom stok atau saldo, dan data yang sudah terdaftar akan ditolak. Menyimpan pratinjau yang sama dua kali tidak menggandakan data.',
        ],
        'aktifkan-data' => [
            'Mengarsipkan data menyembunyikannya dari pilihan transaksi baru tanpa menghapus riwayatnya. Nota, pengiriman, dan laporan lama tetap menampilkan data tersebut.',
            'Data arsip bisa diaktifkan kembali kapan saja dari daftar master. Cara ini lebih aman daripada membuat data baru dengan nama atau kode yang sama.',
        ],
        'pengeluaran' => [
            'Pengeluaran mencatat biaya toko di luar pembelian barang, seperti listrik, alat tulis, atau upah. !!Uangnya diambil dari Kas Utama atau rekening Bank, bukan dari laci kas harian.!!',
            'Saat disimpan, saldo sumber dana langsung berkurang dan pembukuan dibuat lalu dikunci. Saldo sumber dana tidak boleh menjadi negatif. Sistem memberi peringatan jika ada pengeluaran yang sangat mirip agar biaya tidak tercatat dua kali.',
        ],
        'jurnal' => [
            'Jurnal umum adalah catatan pembukuan lengkap toko. Setiap penjualan, pembayaran, penerimaan barang, dan pengeluaran otomatis membuat jurnal yang seimbang antara sisi debit dan kredit.',
            'Jurnal dari transaksi operasional terkunci dan tidak diubah langsung. Jika ada kesalahan, perbaiki dari transaksi asalnya, misalnya lewat retur, atau gunakan Pindah dana dan Sesuaikan saldo bila memang diperlukan.',
        ],
        'pindah-dana' => [
            'Pindah dana mencatat uang yang berpindah tempat di dalam toko: setor kas ke bank, tarik tunai dari bank, atau pindah antar rekening. Total uang toko tidak berubah, jadi tidak dianggap pendapatan atau biaya.',
            'Admin wajib mengonfirmasi dengan password login sendiri. Saldo sumber dana tidak boleh menjadi negatif, dan sistem memberi peringatan bila ada perpindahan serupa dalam waktu berdekatan.',
        ],
        'koreksi-saldo-kas-bank' => [
            'Sesuaikan saldo dipakai jika saldo Kas atau Bank di sistem berbeda dari kenyataan setelah semua transaksi diperiksa. Admin cukup mengisi saldo yang seharusnya; sistem menghitung dan mencatat selisihnya saja.',
            '!!Koreksi memerlukan alasan dan password login admin.!! Jurnal lama dan saldo awal tidak diubah, dan saldo Kas atau Bank tidak boleh menjadi negatif. Koreksi ini hanya bisa dipakai setelah pembukuan dimulai.',
        ],
        'saldo-awal' => [
            'Saldo awal mencatat posisi toko saat pertama kali memakai sistem: uang kas dan bank, stok barang, piutang pelanggan, dan utang supplier.',
            'Saldo awal hanya bisa diposting sekali, saat sistem masih kosong: belum ada jurnal, stok, atau tagihan. !!Setelah diposting, saldo awal tidak bisa diubah.!! Periksa pratinjau dengan teliti sebelum mengunci.',
        ],
        'neraca' => [
            'Neraca menunjukkan posisi keuangan toko pada satu tanggal: aset (uang, piutang, persediaan barang), kewajiban (utang), dan ekuitas atau modal, termasuk laba atau rugi berjalan.',
            'Total aset harus sama dengan total kewajiban ditambah ekuitas. Jika ada selisih, periksa saldo awal dan jurnal pada periode tersebut. Neraca hanya membaca data.',
        ],
        'laba-rugi' => [
            'Laba rugi menghitung hasil usaha dalam satu periode: pendapatan penjualan dikurangi harga modal barang yang terjual, lalu dikurangi biaya operasional.',
            'Harga modal barang yang terjual berbeda dari nilai pembelian stok baru; barang yang belum terjual masih dicatat sebagai persediaan. Retur penjualan dan potongan mengurangi pendapatan.',
        ],
        'arus-kas' => [
            'Arus kas merangkum uang yang benar-benar masuk dan keluar dari Kas dan seluruh rekening Bank selama periode tertentu.',
            'Penjualan kredit belum dihitung sebagai uang masuk sampai pelanggan membayar. Penerimaan langsung saat penjualan dipisahkan dari pelunasan piutang, dan perpindahan antar Kas atau Bank tidak dihitung sebagai uang masuk baru.',
        ],
        'bagan-akun' => [
            'Bagan akun adalah daftar pos pembukuan, seperti kas, bank, piutang, persediaan, utang, pendapatan, dan biaya. Setiap jurnal memakai akun-akun ini.',
            'Akun yang terhubung ke transaksi otomatis dilindungi: kode dan jenisnya terkunci, hanya namanya yang boleh disesuaikan. Akun lain bisa diarsipkan, tidak dihapus. Akun aset dapat ditandai sebagai rekening bank agar muncul sebagai pilihan pembayaran.',
        ],
    ],
    // Artikel: judul, fungsi, langkah, pertanyaan => jawaban, menu tujuan, khusus admin.
    'groups' => [
        'Mulai di sini' => [
            'mulai' => ['Kenali alur kerja toko', 'POS menghubungkan barang, penjualan, tagihan, uang, dan laporan. Sekali transaksi disimpan, bagian yang terkait diperbarui sesuai jenis transaksinya.', [
                'Pastikan Kas harian aktif sebelum melayani pembeli.',
                'Gunakan Kasir untuk menjual dan Riwayat penjualan untuk menemukan nota lama.',
                'Gunakan Tagihan untuk melunasi transaksi yang masih bersisa, bukan membuat nota yang sama lagi.',
                'Admin memeriksa pembayaran, barang masuk, dan laporan sebelum menutup kas.',
            ], [
                'Kenapa menu saya berbeda dari teman saya?' => 'Menu mengikuti hak akses. Kasir berfokus pada penjualan dan pekerjaan harian; beberapa menu hanya tampil untuk admin.',
                'Apakah saya harus mencatat penjualan lagi di pembukuan?' => 'Tidak. Jurnal dibuat otomatis untuk transaksi yang sudah tercatat.',
            ], 'kasir', false],
            'ringkasan' => ['Baca Ringkasan toko', 'Lihat pekerjaan yang perlu ditindaklanjuti, penjualan, pengiriman, uang, dan barang yang menipis.', [
                'Mulai dari bagian Perlu ditindaklanjuti.',
                '((Pilih periode untuk membandingkan penjualan dan produk terlaris.))',
                'Buka menu asal untuk memeriksa rincian atau melakukan tindakan.',
            ], ['Kenapa saldo tidak berubah ketika periode diganti?' => 'Periode menyaring penjualan. Kartu saldo tetap menggambarkan posisi saat ini.'], 'ringkasan', true],
            'akun' => ['Kelola akun login', 'Akun login memberi petugas akses ke aplikasi. Akun ini berbeda dari catatan karyawan atau sopir.', [
                'Cari akun sebelum menambah petugas.',
                'Pilih peran sesuai tanggung jawab. Jangan memilih admin hanya agar semua menu terlihat.',
                'Periksa status aktif ketika petugas tidak dapat masuk.',
            ], ['Sudah membuat karyawan; kenapa belum bisa masuk?' => 'Data karyawan tidak otomatis membuat akun login. Admin harus menyiapkan akun yang sesuai.'], 'akun', true],
        ],
        'Operasional' => [
            'kasir' => ['Catat penjualan di Kasir', 'Gunakan katalog dan keranjang untuk menjual barang, memilih pembeli, mencatat pembayaran dan membuat nota.', [
                'Pastikan Kas harian aktif. Cari barang dengan nama atau SKU, lalu tambahkan ke keranjang.',
                'Buka Lihat keranjang. Periksa jumlah, satuan, diskon, dan harga Eceran atau Kontraktor.',
                'Pilih pembeli. Pembeli umum bisa dipakai untuk tunai; transfer dan kredit memerlukan pelanggan terdaftar.',
                'Pilih Tunai, Transfer, atau Kredit. Untuk transfer yang dicatat, siapkan foto bukti. ((Kredit dapat memakai uang muka dan jatuh tempo opsional.))',
                'Tentukan diambil atau dikirim. ((Periksa ongkir, tujuan, dan catatan bila diperlukan.))',
                'Tinjau pembayaran dan konfirmasi sekali. Setelah berhasil, catat nomor nota atau cetak dokumen yang diperlukan.',
            ], [
                'Tombol bayar tidak bisa dipakai?' => 'Periksa apakah kas aktif, keranjang berisi barang, dan jumlah tidak melebihi stok.',
                'Pembeli belum jadi belanja sekarang?' => 'Pakai Tahan transaksi, lalu buka kembali dari Tertahan pada browser yang sama. Ini bukan nota dan belum mengurangi stok.',
                'Harga kontraktor tidak muncul pada satu barang?' => 'Jika barang itu tidak punya harga kontraktor, sistem memakai harga jual biasa. Cek keranjang sebelum menyimpan.',
                'Sudah kirim foto tapi belum lunas?' => 'Bukti transfer masih harus diperiksa admin. Lihat Status pembayaran; jangan mencatat pembayaran dua kali.',
                'Koneksi putus setelah konfirmasi?' => 'Cari nota di Riwayat penjualan sebelum mencoba lagi. !!Jangan membuat nota baru hanya karena pesan berhasil belum terlihat.!!',
            ], 'kasir', false],
            'tahan-transaksi' => ['Tahan dan lanjutkan transaksi', 'Simpan keranjang sementara saat pembeli belum siap. Transaksi tertahan belum menjadi nota dan tidak mengurangi stok.', [
                'Saat barang sudah ada di keranjang, pilih Tahan transaksi.',
                'Untuk melanjutkan, buka Tertahan di kepala katalog dan pilih Lanjutkan pada keranjang yang benar.',
                'Periksa pembeli, barang, jumlah, dan stok sebelum pembayaran.',
            ], [
                'Daftar Tertahan tidak muncul?' => 'Tombol hanya tampil ketika ada keranjang tertahan. Daftarnya tersimpan di browser yang sama, bukan di Riwayat penjualan.',
                'Bisa dilanjutkan di perangkat lain?' => 'Tidak. Kembali ke browser yang dipakai saat menahan transaksi. Maksimal 10 keranjang bisa ditahan di sana.',
                'Stok berkurang saat transaksi dilanjutkan?' => 'Penjualan lain bisa terjadi selama keranjang ditahan. Sesuaikan jumlah dengan stok yang tersedia.',
            ], 'kasir', false],
            'harga-kontraktor' => ['Pilih harga eceran atau kontraktor', 'Jenis harga menentukan tarif yang dipakai untuk barang di keranjang penjualan.', [
                '((Pilih nama pelanggan jika sudah terdaftar.))',
                'Pelanggan yang ditandai kontraktor otomatis memakai harga Kontraktor. Cek pilihan Eceran atau Kontraktor di keranjang.',
                'Periksa harga tiap barang sebelum meninjau pembayaran. Kasir bisa mengganti jenis harga untuk transaksi itu.',
            ], [
                'Kenapa satu barang tetap memakai harga eceran?' => 'Barang tanpa harga kontraktor tetap memakai harga jual biasa. Minta admin memeriksa data produknya bila memang perlu harga berbeda.',
                'Apakah mengganti harga di Kasir mengubah harga pelanggan selamanya?' => 'Tidak. Pilihan di Kasir hanya untuk transaksi ini. Pengaturan bawaan pelanggan ada di Master data.',
            ], 'kasir', false],
            'shift' => ['Periksa Kas harian', 'Satu laci toko digunakan bersama pada tanggal yang sama. Nama petugas tetap tercatat pada tiap transaksi.', [
                'Buka Kas harian dan lihat apakah statusnya aktif.',
                'Jika belum aktif, minta admin membuka kas. Jika sudah ditutup hari ini, cek dengan admin sebelum melanjutkan pekerjaan.',
                'Bedakan uang fisik di laci dari saldo Kas Utama dan rekening Bank toko.',
            ], [
                'Kenapa kasir tidak punya tombol buka atau tutup?' => 'Hanya admin yang membuka dan menutup kas harian.',
                'Apakah semua pembayaran masuk laci?' => 'Pembayaran tunai laci menambah uang laci. Transfer dan pembayaran dari rekening Bank bukan uang fisik di laci.',
            ], 'shift', false],
            'tutup-kas' => ['Buka dan tutup Kas harian', 'Admin mengatur modal awal serta mencocokkan uang fisik dengan catatan toko.', [
                'Saat buka, pilih sumber kas awal yang sesuai. ((Jika membawa sisa sebelumnya, pilih catatan kas asal.))',
                'Saat tutup, baca rekap penjualan dan arus masuk/keluar laci.',
                'Hitung uang fisik secara langsung, isi hasilnya, dan periksa kecocokan sebelum konfirmasi.',
                'Jika ada selisih, periksa nota dan pergerakan laci. Gunakan catatan pemeriksaan untuk menandai bahwa selisih telah ditelusuri.',
            ], [
                'Kenapa rekap perlu dibuka ulang?' => 'Angka bisa berubah karena transaksi lain. Ambil rekap terbaru sebelum tutup.',
                'Kas sudah ditutup tetapi toko masih perlu setor?' => 'Kas yang sama tidak dibuka lagi. Gunakan Pindah dana pada Jurnal umum jika memang terjadi perpindahan Kas/Bank toko.',
            ], 'shift', true],
            'riwayat-penjualan' => ['Cari, periksa, atau cetak ulang nota', 'Riwayat menampilkan nota, barang, sisa tagihan, dan pembayaran. Nota tidak perlu dibuat ulang untuk mencetaknya.', [
                'Cari nama pembeli atau nomor nota. ((Gunakan Filter untuk mengganti tanggal, status, atau metode.))',
                'Buka Detail untuk melihat barang dan riwayat pembayaran.',
                'Pilih Cetak struk/nota. Gunakan A4 atau Struk 80 mm sesuai printer. Untuk pengiriman, gunakan Surat jalan bila tersedia.',
            ], [
                'Nota lama tidak ditemukan?' => 'Daftar awal menampilkan hari ini. Perluas tanggal dan kosongkan filter lain.',
                'Bagaimana kalau barang dikembalikan atau nota salah?' => 'Buka Detail pada baris nota. Jika barang masih bisa diretur, admin dapat memilih Catat retur. Kasir meminta admin memprosesnya. Jangan membuat nota kedua untuk menutupi nota yang salah.',
            ], 'riwayat-penjualan', false],
            'tagihan' => ['Catat cicilan dan cek tagihan', 'Piutang adalah uang yang pelanggan masih harus bayar. Utang supplier adalah uang yang toko masih harus bayar.', [
                'Pilih Piutang pelanggan atau Utang supplier. ((Cari nomor tagihan atau nama.))',
                'Buka Detail dan baca Terbayar, Sisa, serta riwayat sebelum mencatat pembayaran.',
                'Isi nominal dan metode atau sumber sesuai pilihan yang tersedia. Untuk transfer pelanggan, siapkan foto bukti.',
                'Periksa hasilnya. Jika masuk sebagai pengajuan, tunggu verifikasi sebelum menilai tagihan sudah lunas.',
            ], [
                'Tagihan lunas tidak terlihat?' => 'Filter awal biasanya menampilkan yang belum lunas. Ubah status untuk mencari tagihan lunas.',
                'Nominal dan tagihan sama diberi peringatan?' => 'Periksa riwayat; lanjutkan hanya bila benar-benar pembayaran berbeda.',
                'Lunasi atau cicilan?' => 'Lunasi mengisi sisa yang belum dibayar. Untuk cicilan, isi jumlah yang benar-benar diterima.',
            ], 'tagihan', false],
            'bayar-supplier' => ['Bayar utang supplier', 'Bayar tagihan pemasok dari Tagihan. Admin dapat mencatat langsung; kasir mengajukan pembayaran untuk diperiksa admin.', [
                'Buka Tagihan → Utang supplier. Cari nama pemasok atau nomor tagihan.',
                'Buka Detail. Cocokkan sisa tagihan dan pembayaran sebelumnya.',
                'Pilih Bayar ke supplier atau Ajukan pembayaran, sesuai akses. Isi jumlah dan pilih sumber dana yang benar-benar dipakai.',
                'Setelah disimpan, lihat apakah statusnya Tercatat atau Diajukan. Pengajuan baru mengurangi utang setelah admin menyetujui.',
            ], [
                'Mau melunasi seluruh utang?' => 'Gunakan Lunasi untuk mengisi sisa tagihan. Jika hanya membayar sebagian, isi jumlah yang dibayar.',
                'Tombol bayar hilang?' => 'Cek status di Detail. Tagihan yang sudah lunas tidak menerima pembayaran baru.',
            ], 'tagihan', false],
            'sumber-dana-supplier' => ['Pilih sumber dana untuk bayar supplier', 'Kas Utama dan Bank memakai saldo toko. Laci shift memakai uang tunai di laci kas harian.', [
                'Pilih Kas Utama jika uang dibayar dari kas toko di luar laci harian.',
                'Pilih rekening Bank yang benar jika pembayaran keluar dari bank toko.',
                'Pilih Laci shift hanya jika uang benar-benar keluar dari laci yang sedang aktif dan saldonya cukup.',
            ], [
                'Kenapa pembayaran tidak mengurangi laci?' => 'Pembayaran dari Kas Utama atau Bank bukan gerakan uang laci. Periksa sumber dana sebelum menyimpan.',
                'Pengajuan dari laci belum disetujui, tetapi kas sudah ditutup?' => 'Pengajuan itu tidak otomatis pindah ke kas hari berikutnya. Koordinasikan dengan admin sebelum mengajukan ulang.',
            ], 'tagihan', false],
            'persetujuan' => ['Pantau status pembayaran', 'Status menunjukkan apakah pembayaran masih menunggu, telah tercatat, atau ditolak.', [
                'Cari pembayaran dari nota atau tagihan terkait.',
                'Bandingkan nominal, tanggal, dan bukti dengan catatan yang Anda pegang.',
                'Jika ditolak, baca alasan lalu perbaiki sesuai arahan admin.',
            ], ['Kenapa saldo tagihan belum turun?' => 'Pengajuan menunggu atau ditolak belum dihitung sebagai pelunasan. Jangan mengajukan ulang sebelum memeriksa statusnya.'], 'persetujuan', false],
            'transfer-ditolak' => ['Tangani transfer yang ditolak', 'Transfer yang ditolak tidak mengurangi sisa tagihan. Periksa alasannya sebelum mencatat pembayaran lagi.', [
                'Buka Status pembayaran atau riwayat pembayaran di Detail nota. Cari transaksi yang ditolak.',
                'Baca alasan penolakan lalu cocokkan nota, nominal, dan bukti yang benar dengan admin.',
                'Jika pembayaran memang perlu dicatat ulang, lakukan dari tagihan atau nota yang sama sesuai arahan admin. Jangan membuat nota penjualan kedua.',
            ], [
                'Foto bukti tidak bisa diunggah?' => 'Gunakan foto JPG, PNG, atau WebP maksimal 5 MB. Pastikan rincian pada foto terbaca.',
                'Dana terlihat sudah masuk tetapi status ditolak?' => 'Minta admin mencocokkan uang masuk di rekening dan alasan penolakan. Jangan meminta pelanggan bayar ulang sebelum diperiksa.',
            ], 'persetujuan', false],
            'verifikasi-pembayaran' => ['Periksa pengajuan pembayaran', 'Admin memeriksa apakah pembayaran sesuai sebelum menyetujui atau menolak pengajuan.', [
                'Buka pengajuan. Cocokkan nota atau tagihan, nominal, sumber dana, dan bukti.',
                'Untuk transfer, cocokkan dengan uang yang benar-benar masuk dan rekening tujuan yang tepat.',
                'Setujui hanya setelah cocok. Jika menolak, beri alasan agar petugas tahu apa yang perlu diperbaiki.',
            ], ['Foto terlihat jelas; apakah cukup?' => 'Tidak. Cocokkan transaksi bank dan pastikan bukti tidak sudah dipakai untuk pembayaran lain.'], 'persetujuan', true],
            'pengiriman' => ['Siapkan dan pantau pengiriman', 'Pengiriman melacak barang yang disiapkan, dibawa armada, dan diterima. Statusnya terpisah dari pembayaran.', [
                'Cari pengiriman berdasarkan nota dan periksa barang serta tujuan.',
                'Lengkapi sopir dan kendaraan sesuai aksi yang tersedia.',
                'Catat keberangkatan saat barang berangkat dan terima saat barang benar-benar sampai.',
            ], [
                'Sopir atau mobil tidak muncul?' => 'Minta admin memeriksa apakah data aktif dan sesuai.',
                'Barang sudah sampai, tagihan belum lunas?' => 'Itu mungkin. Cek pembayaran di Tagihan, bukan dengan mengubah status kirim.',
                'Aksi tidak ada pada pengiriman petugas lain?' => 'Aksi bergantung pada hak akses dan status dokumen. Hubungi admin bila harus ditindaklanjuti.',
            ], 'pengiriman', false],
            'biaya-sopir' => ['Catat uang muka dan biaya perjalanan', 'Pisahkan uang yang diberikan sebelum perjalanan dari biaya yang benar-benar dipakai.', [
                'Catat uang muka untuk sopir yang tepat.',
                'Setelah perjalanan, kumpulkan bukti dan catat realisasi seperti BBM atau uang jalan.',
                'Periksa jumlah sisa yang dikembalikan atau biaya lanjutan yang perlu ditangani.',
            ], ['Uang muka sudah diberikan. Apakah itu langsung beban?' => 'Tidak. Biaya diketahui setelah realisasi dicatat. Jangan mencatat biaya yang sama dua kali.'], 'pengiriman', true],
            'laporan' => ['Baca laporan harian', 'Laporan harian merangkum penjualan, pembayaran, dan laci untuk tanggal dan cakupan petugas yang berlaku.', [
                'Pilih tanggal laporan.',
                'Bandingkan penjualan dengan rincian tunai, transfer, dan kredit.',
                'Gunakan nomor nota atau riwayat pembayaran untuk menelusuri selisih.',
            ], [
                'Kenapa total penjualan tidak sama dengan uang laci?' => 'Penjualan kredit dan transfer tidak sama dengan uang fisik. Laci juga bisa berisi kas awal atau pergerakan tunai lain.',
                'Kenapa laporan kasir berbeda dari admin?' => 'Laporan kasir mengikuti cakupan petugas. Admin dapat melihat cakupan toko.',
            ], 'laporan', false],
        ],
        'Stok & pembelian' => [
            'penerimaan' => ['Catat barang masuk', 'Penerimaan menambah stok dan mencatat utang supplier setelah dokumen diperiksa dan dikonfirmasi.', [
                'Buka Catat penerimaan. Cari barang lewat Perlu restok atau pilih supplier dan barang terkait.',
                'Isi tanggal, nomor dokumen, jumlah, serta harga beli per satuan. ((Periksa juga ongkir bila ada.))',
                'Pilih Periksa penerimaan. Cocokkan jumlah dan harga dengan dokumen, lalu simpan draf.',
                'Periksa barang fisik dan konfirmasi draf ketika yakin. Sistem memperbarui stok dan mengunci dokumen.',
            ], [
                'Draf sudah disimpan, kenapa stok belum bertambah?' => 'Draf belum mengubah stok atau utang. Perbaruan terjadi setelah konfirmasi final.',
                'Barang tidak bisa dipilih?' => 'Periksa apakah produk aktif dan terhubung ke supplier yang tepat.',
                'Harga beli mendapat peringatan?' => 'Cek apakah harga diisi per satuan yang digunakan, bukan harga satu karung atau satu dus untuk barang yang dijual per buah.',
                'Penerimaan sudah final tetapi barang dikembalikan?' => 'Gunakan retur pembelian bila sesuai, bukan membatalkan draf yang sudah dikunci.',
            ], 'penerimaan', true],
            'ubah-draf-penerimaan' => ['Perbaiki atau batalkan draf penerimaan', 'Kesalahan pada draf bisa diperbaiki sebelum konfirmasi. Draf belum mengubah stok atau utang.', [
                'Buka daftar Draf penerimaan dan cari dokumen supplier yang benar.',
                'Gunakan Ubah untuk memperbaiki rincian, lalu simpan dan periksa kembali.',
                'Jika draf tidak dipakai, pilih Batalkan draf dan tulis alasan agar petugas lain tahu penyebabnya.',
                'Konfirmasi dan kunci hanya untuk penerimaan yang sudah cocok dengan barang dan dokumen fisik.',
            ], [
                'Dokumen sudah ada di Riwayat penerimaan final?' => 'Itu bukan draf lagi. Jangan membuat penerimaan yang sama untuk menutupi kesalahan. Jika barang benar-benar dikembalikan, gunakan panduan retur pembelian.',
                'Nomor nota supplier dianggap mirip?' => 'Cari dokumen asal di draf dan riwayat lebih dulu. Jangan lanjut sebelum yakin itu penerimaan yang berbeda.',
            ], 'penerimaan', true],
            'stok-opname' => ['Cocokkan stok dengan hasil hitung', 'Stok opname digunakan untuk mencatat selisih antara stok di sistem dan barang yang benar-benar ada.', [
                'Hitung fisik barang menggunakan satuan yang sama seperti di produk.',
                'Buka Catat stok opname dan pilih barang yang dihitung.',
                'Masukkan jumlah fisik, tulis alasan jika ada selisih, lalu periksa ulang sebelum simpan.',
            ], ['Kenapa stok sistem berubah saat barang dihitung?' => 'Penjualan atau barang masuk bisa terjadi bersamaan. Cocokkan transaksi terbaru sebelum menyimpan penyesuaian.'], 'stok-opname', true],
            'retur-penjualan' => ['Tangani barang dikembalikan pelanggan', 'Retur penjualan mengoreksi barang pada nota asal dan dapat mengurangi piutang atau menghasilkan pengembalian dana.', [
                'Cari nota asal di Riwayat penjualan dan buka Detail.',
                'Gunakan Retur. Periksa barang, jumlah yang masih boleh diretur, dan barang yang diterima kembali.',
                'Periksa perubahan piutang dan cara refund bila tersedia sebelum menyimpan.',
            ], ['Kenapa refund lebih kecil daripada nilai retur?' => 'Sistem mengurangi sisa piutang terlebih dahulu. Jika masih ada kelebihan, barulah muncul pengembalian dana.'], 'riwayat-penjualan', true],
            'retur-pembelian' => ['Kembalikan barang kepada supplier', 'Retur pembelian mencatat barang yang dikembalikan dari penerimaan final dan menyesuaikan utang atau pengembalian dana.', [
                'Buka Riwayat penerimaan dan pilih penerimaan asal.',
                'Buka Retur, periksa jumlah yang masih tersedia untuk dikembalikan, lalu isi alasan.',
                'Periksa pengaruh pada stok dan utang sebelum menyimpan.',
            ], ['Jumlah retur ditolak?' => 'Pastikan tidak melebihi jumlah yang dapat diretur atau stok gudang saat ini.'], 'penerimaan', true],
        ],
        'Master data' => [
            'master-product' => ['Atur barang, harga, dan SKU', 'Data produk menentukan bagaimana barang dicari, dihitung, dan dijual.', [
                'Cari nama atau SKU agar tidak membuat barang ganda.',
                'Isi satuan, kategori, pemasok, dan harga jual. ((Isi harga kontraktor jika berbeda.))',
                'Periksa harga modal dan stok minimum. Gunakan penerimaan atau opname untuk mengubah stok.',
            ], ['Barang tidak dijual lagi?' => 'Arsipkan, jangan membuat ulang dengan SKU sama. Riwayat transaksi tetap tersimpan.'], 'master-product', true],
            'master-supplier' => ['Siapkan data pemasok', 'Supplier adalah pemasok barang. Hubungan produk dan supplier membantu pemilihan barang saat penerimaan.', [
                'Cari pemasok, lalu tambah atau ubah data yang perlu.',
                'Tautkan produk yang dipasok dan pastikan keduanya aktif.',
                '((Buka aktivitas supplier jika ingin melihat riwayat transaksi.))',
            ], ['Supplier ada tetapi belum memiliki utang?' => 'Data master tidak membuat utang. Utang berasal dari penerimaan final atau saldo awal yang dicatat.'], 'master-supplier', true],
            'master-customer' => ['Siapkan data pelanggan', 'Data pelanggan dipakai untuk identitas nota, harga kontraktor, kredit, dan pelacakan tagihan.', [
                'Cari sebelum membuat pelanggan baru.',
                'Isi identitas yang diperlukan dan pilih jenis harga bawaan.',
                '((Periksa Detail untuk melihat aktivitas dan tagihan pelanggan.))',
            ], ['Pelanggan terdaftar tetapi harga salah?' => 'Cek penanda kontraktor di master lalu periksa tipe harga yang dipilih kasir di keranjang.'], 'master-customer', true],
            'master-employee' => ['Kelola karyawan dan sopir', 'Data petugas dan sopir dipakai dalam operasi toko, bukan untuk masuk ke aplikasi.', [
                'Cari petugas sebelum menambah data.',
                'Bedakan sopir internal yang terhubung ke karyawan dari sopir eksternal.',
                'Arsipkan data yang tidak dipakai lagi agar tidak membingungkan pilihan baru.',
            ], ['Sudah ada karyawan tetapi belum bisa dipilih sebagai sopir?' => 'Periksa apakah data sopirnya sudah dibuat dan aktif.'], 'master-employee', true],
            'master-vehicle' => ['Siapkan armada pengiriman', 'Data mobil membantu petugas memilih kendaraan yang tepat saat mengirim barang.', [
                'Cari nomor kendaraan sebelum menambah data.',
                'Isi identitas dan jenis kendaraan. Cek status aktifnya.',
                'Arsipkan mobil yang tidak dipakai. Riwayat perjalanan tetap ada.',
            ], ['Kendaraan aktif belum muncul?' => 'Periksa apakah Anda berada di pengiriman yang tepat dan aksinya memang tersedia.'], 'master-vehicle', true],
            'master-unit' => ['Atur satuan barang', 'Satuan memberi arti pada angka jumlah, misalnya buah, sak, meter, atau meter kubik.', [
                'Cari satuan sebelum membuat nama baru.',
                'Gunakan satuan yang sama untuk barang yang dihitung dengan cara yang sama.',
                'Periksa satuan pada produk sebelum melakukan transaksi.',
            ], ['Apakah 1 dus otomatis menjadi 12 buah?' => 'Tidak. Jangan menganggap satuan master sebagai konversi otomatis.'], 'master-unit', true],
            'master-category' => ['Kelompokkan produk', 'Kategori membantu petugas menemukan barang lewat filter katalog.', [
                'Buat nama kategori yang singkat dan mudah dibedakan.',
                'Pasangkan kategori pada produk yang sesuai.',
                '((Periksa filter katalog setelah pengelompokan diubah.))',
            ], ['Produk masih belum terlihat?' => 'Bersihkan filter kategori dan stok, lalu cek status produk. Kategori tidak mengubah jumlah stok.'], 'master-category', true],
            'impor-excel' => ['Impor data dari Excel', 'Impor membantu menambah banyak produk, pemasok, pelanggan, karyawan, atau mobil sekaligus.', [
                'Buka menu master yang ingin diisi, lalu pilih Unduh template dari menu tersebut.',
                'Isi data tanpa mengganti judul kolom. Simpan dalam format .xlsx dengan ukuran paling besar 2 MB.',
                'Pilih Impor Excel dan unggah file. Periksa pratinjau serta perbaiki baris yang diberi pesan.',
                'Simpan hanya setelah pratinjau benar. Periksa daftar untuk memastikan data baru muncul.',
            ], [
                'Satu baris salah, apakah baris lain tetap masuk?' => 'Tidak. Perbaiki file lalu unggah ulang. Penyimpanan berhasil untuk semua baris sekaligus, atau tidak ada yang tersimpan.',
                'Bisa impor saldo atau stok dari template ini?' => 'Tidak. Template master untuk identitas barang dan orang, bukan untuk mengisi saldo atau menyesuaikan stok. Hindari kolom tambahan dan rumus Excel.',
            ], 'master-product', true],
            'aktifkan-data' => ['Aktifkan kembali data yang diarsipkan', 'Arsip menyembunyikan data dari pilihan transaksi baru tanpa menghapus riwayat.', [
                'Buka daftar master asal. Gunakan Filter dan pilih Diarsipkan, lalu cari nama atau kode data.',
                'Periksa data yang dipilih. Buka aksi baris dan pilih Aktifkan kembali jika tersedia.',
                'Cek daftar Aktif dan pastikan relasi yang diperlukan masih benar sebelum memakainya lagi.',
            ], [
                'Kenapa tidak buat data baru saja?' => 'Utamakan memakai kembali catatan lama supaya riwayat tidak terpecah dan nama atau kode tidak ganda.',
                'Sudah aktif tetapi belum muncul saat transaksi?' => 'Periksa filter dan syarat fiturnya. Contohnya, barang perlu tertaut dengan supplier yang tepat agar bisa dipilih saat penerimaan.',
            ], 'master-product', true],
        ],
        'Keuangan' => [
            'pengeluaran' => ['Catat biaya operasional', 'Pengeluaran mencatat biaya toko seperti listrik, ATK, atau upah dari Kas atau Bank toko.', [
                'Isi tanggal, alasan, nominal, dan akun beban yang sesuai.',
                'Pilih sumber dana yang benar-benar dipakai. Periksa rekening bila memilih Bank.',
                'Periksa rincian lalu simpan. Sistem langsung mencatat biaya dan pengurangan saldo sumber dana.',
            ], [
                'Apakah ini pengeluaran uang laci?' => 'Tidak. Menu ini menggunakan saldo Kas/Bank toko, bukan kas harian di laci.',
                'Muncul peringatan transaksi mirip?' => 'Cari transaksi yang sama di daftar pengeluaran. Lanjutkan hanya jika biaya itu memang berbeda.',
            ], 'pengeluaran', true],
            'jurnal' => ['Telusuri catatan pembukuan', 'Jurnal menunjukkan dampak transaksi terhadap saldo. Penjualan dan transaksi lain yang tercatat sudah dibuatkan jurnal otomatis.', [
                'Cari berdasarkan tanggal atau uraian.',
                'Baca akun, nominal, dan transaksi asal untuk memahami perubahan saldo.',
                'Gunakan Pindah dana untuk perpindahan nyata, atau penyesuaian hanya bila pemeriksaan menunjukkan saldo memang perlu dikoreksi.',
            ], [
                'Jurnal terkunci; harus dicatat ulang?' => 'Tidak. Telusuri transaksi sumber dan gunakan alur koreksi yang benar. Pencatatan ulang bisa menggandakan angka.',
                'Apa arti debit dan kredit?' => 'Keduanya adalah sisi pencatatan. Dampak tambah atau kurang tergantung jenis akun, bukan semata-mata sisi yang digunakan.',
            ], 'jurnal', true],
            'pindah-dana' => ['Pindahkan dana antar Kas dan Bank', 'Gunakan saat uang berpindah tempat, misalnya setor Kas ke Bank atau pindah antar rekening toko.', [
                'Buka Jurnal umum lalu pilih Pindah dana.',
                'Pilih sumber dan tujuan yang berbeda. Masukkan jumlah, tanggal, dan keterangan.',
                'Cocokkan bukti perpindahan dan periksa saldo sebelum menyimpan.',
            ], ['Setoran bank dianggap penjualan baru?' => 'Tidak. Uang hanya berpindah tempat, bukan menambah pendapatan atau mencatat biaya baru.'], 'jurnal', true],
            'koreksi-saldo-kas-bank' => ['Koreksi saldo Kas atau Bank', 'Gunakan Sesuaikan saldo hanya setelah selisih ditelusuri. Ini bukan cara mencatat uang yang benar-benar berpindah rekening.', [
                'Cocokkan uang fisik atau laporan bank dengan saldo di Jurnal umum. Periksa transaksi yang belum dicatat terlebih dahulu.',
                'Buka Sesuaikan saldo. Pilih Kas atau rekening Bank yang benar, tanggal, dan masukkan saldo yang seharusnya.',
                'Pilih akun lawan yang sesuai asal selisih, isi alasan, dan konfirmasi dengan password login sendiri. Jika tidak yakin akun lawannya, minta pemeriksa pembukuan memastikannya.',
                'Setelah tersimpan, periksa jurnal baru. Sistem mencatat selisihnya saja; catatan lama tetap utuh.',
            ], [
                'Toko baru mulai dan belum punya pembukuan?' => 'Gunakan Saldo awal pembukuan di Kas harian, bukan Sesuaikan saldo.',
                'Uang baru disetor ke bank?' => 'Gunakan Pindah dana jika uang benar-benar berpindah. Jangan mencatat koreksi untuk meniru setoran.',
            ], 'jurnal', true],
            'saldo-awal' => ['Siapkan saldo awal pembukuan', 'Saldo awal adalah posisi uang, barang, utang, dan piutang saat toko mulai memakai sistem, bukan alat memperbaiki transaksi hari ini.', [
                'Kumpulkan catatan kas, rekening, stok, piutang, dan utang pada tanggal awal.',
                'Buka Kas harian dan cari Saldo awal pembukuan.',
                'Periksa pratinjau serta rincian sebelum mengunci. Jangan mengisi saldo kira-kira.',
            ], ['Saldo awal sudah terkunci, bagaimana memperbaikinya?' => 'Telusuri dulu penyebabnya. Gunakan transaksi koreksi yang sesuai, bukan membuat saldo awal kedua.'], 'shift', true],
            'neraca' => ['Baca posisi aset, utang, dan modal', 'Neraca menjawab: apa yang dimiliki toko, apa yang masih harus dibayar, dan berapa bagian modal pada satu tanggal?', [
                'Pilih tanggal laporan.',
                'Baca aset, kewajiban, dan ekuitas atau modal.',
                'Jika ada angka tak wajar, telusuri tanggal dan transaksi asal lewat Jurnal umum.',
            ], ['Kenapa neraca bukan laporan omset?' => 'Neraca menunjukkan posisi pada satu tanggal. Untuk hasil penjualan dan biaya selama periode, buka Laba rugi.'], 'neraca', true],
            'laba-rugi' => ['Baca laba atau rugi toko', 'Laba rugi membandingkan pendapatan dengan harga modal barang yang terjual dan biaya lain pada periode tertentu.', [
                'Pilih rentang tanggal yang ingin diperiksa.',
                'Baca pendapatan, harga pokok penjualan (HPP), dan beban.',
                'Telusuri transaksi asal melalui jurnal jika ada angka yang tidak cocok.',
            ], ['Laba ada, tapi uang belum masuk?' => 'Penjualan kredit dapat memengaruhi laba sebelum pembeli membayar. Buka Arus kas untuk melihat pergerakan uang.'], 'laba-rugi', true],
            'arus-kas' => ['Periksa uang masuk dan keluar', 'Arus kas menjawab ke mana uang bergerak selama periode, berbeda dari laba dan posisi aset.', [
                'Pilih rentang tanggal, lalu lihat penerimaan dan pengeluaran.',
                'Bedakan penjualan yang langsung dibayar dari pelunasan piutang lama.',
                'Jika saldo tidak terduga, cocokkan jurnal dan catatan sumber dana.',
            ], ['Apakah memindahkan dana antar rekening membuat kas bertambah?' => 'Tidak. Perpindahan internal tidak menghasilkan uang baru bagi toko.'], 'arus-kas', true],
            'bagan-akun' => ['Pahami daftar akun pembukuan', 'Bagan akun mengelompokkan uang, tagihan, persediaan, pendapatan, dan biaya agar laporan bisa dibaca dengan benar.', [
                'Cari akun yang sudah ada sebelum menambah.',
                'Pilih jenis akun sesuai kegunaannya; aktifkan penanda Rekening Bank hanya untuk rekening toko yang sesuai.',
                'Arsipkan akun non-inti yang tidak dipakai hanya setelah memeriksa kegunaannya.',
            ], [
                'Laci Kas Harian masuk akun mana?' => 'Uang di laci termasuk akun 1101 — Kas Utama (Tunai), bersama uang tunai toko lainnya. Laci tidak punya kode akun sendiri. Lihat modal awal, uang masuk, dan uang keluar per hari di Kas harian. Memindahkan modal dari kas toko ke laci saat membuka hari tidak menambah saldo akun 1101.',
                'Mengapa akun tertentu tidak bisa diubah?' => 'Akun yang terhubung ke transaksi otomatis dilindungi. Mengubah daftar akun juga tidak memindahkan saldo.',
            ], 'bagan-akun', true],
        ],
    ],
];
