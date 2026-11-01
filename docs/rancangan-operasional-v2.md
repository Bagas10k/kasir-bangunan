# Rancangan Operasional Kasir V2

Status: RANCANGAN KERJA, bukan klaim fitur selesai. Disusun dari keputusan pengguna pada 29 Oktober 2026. Dokumen ini menjadi kontrak pembangunan fresh dan dasar pemeriksaan hasil.

## 1. Sasaran dan batas rilis

Aplikasi operasional hijau-putih untuk admin dan kasir: master, penjualan, modal shift, kas/bank, cicilan piutang/utang, penerimaan barang, surat jalan, nota, dan pertanggungjawaban ritase sopir. Klien baru mengisi datanya sendiri. Sistem yang sekarang tidak dikosongkan.

Pengguna mengizinkan penggantian aplikasi aktif setelah backup. Pembangunan dan uji dilakukan pada worktree terpisah agar sumber produksi yang di-bind langsung tidak berisi kode setengah jadi. Penggantian URL aktif dilakukan hanya setelah gerbang rilis lulus. Backup bukan bukti restore; restore diperiksa terpisah. Tidak ada restart atau perubahan sistem tetangga.

Kode domain dan layar V2 ditulis fresh dalam batas namespace/tabel V2 yang jelas, bukan menambah tambalan pada view lama. Laravel, autentikasi, runtime container dan kemampuan teknis yang benar tetap digunakan; fresh tidak berarti mengulang framework, menghapus riwayat, atau mengganti kata sandi.

## 2. Keputusan yang sudah dikunci

- Kasir mengajukan pembayaran utang supplier; admin mengesahkan. Usulan tidak memengaruhi saldo/jurnal.
- Penggajian ditunda. Master karyawan/staf tetap tersedia; staf adalah kategori karyawan, bukan modul orang duplikat.
- Ongkir pelanggan dan biaya sopir terpisah. Pendapatan ongkir tidak boleh di-net langsung dengan biaya sopir.
- Excel hanya data master: produk, pelanggan, supplier, karyawan/staf dan kendaraan. Saldo awal melalui formulir terpisah; riwayat penjualan lama bukan ruang impor master.
- Modal kembalian per hari/shift berasal dari admin atau saldo bawaan shift lama; sumber dicatat dan tidak menjadi penjualan baru.
- Kasir menghitung uang fisik saat tutup shift; admin memeriksa hanya bila selisih. Selisih bukan biaya/pendapatan otomatis.
- Piutang pelanggan dan utang supplier dapat dicicil. Cicilan harus punya alokasi terhadap tagihan asal dan sisa yang dapat ditelusuri.
- Cicilan tunai disahkan kasir; transfer menunggu admin. Transfer pending tidak mengurangi piutang.
- Transfer penjualan baru boleh belum lunas meskipun barang diserahkan. Nota/surat jalan wajib menampilkan status belum lunas; transfer tidak dianggap uang bank sebelum diverifikasi.
- Kasir/admin boleh memberi diskon tanpa plafon jabatan. Tetap tidak boleh nominal negatif atau melebihi dasar harga barang.
- Diskon per barang dahulu, lalu diskon subtotal barang sesudah diskon baris. Ongkir tidak ikut diskon.
- Surat jalan wajib untuk pengiriman. Ambil sendiri cukup nota. Surat jalan bukan penjualan atau pengurangan stok kedua.
- Tema hijau-putih dipertahankan, lebih ringkas, mudah dipakai, responsif, tanpa emoji UI.

## 3. Aturan operasional yang diturunkan

Berikut keputusan teknis/operasional konservatif yang dipilih agar tidak perlu konfirmasi berulang. Semuanya dicatat, diuji dan dapat direvisi tanpa mengubah riwayat posted.

- Hak akses diperiksa server-side; menyembunyikan tombol tidak cukup. Admin mengelola master, pembukaan saldo, verifikasi transfer, persetujuan pembayaran dan laporan keseluruhan. Kasir melihat shift sendiri, penjualan, piutang dan pengajuan yang relevan.
- Satu shift terbuka per kasir. Saldo bank bukan uang laci. Ringkasan shift menampilkan tunai dan transfer terverifikasi/pending secara terpisah.
- Diskon nominal rupiah dan persen dapat dipilih dengan tipe eksplisit; basis, nilai input dan hasil pembulatan disimpan. Persen 0–100; tidak boleh menghasilkan penjualan barang negatif. Jika implementasi tahap awal hanya nominal, keterbatasan disebutkan dan tidak dilabel lengkap.
- Tagihan kredit wajib identitas pelanggan/supplier. Penjualan tunai ambil sendiri dapat memakai pelanggan umum tanpa data pribadi dummy.
- Satu pembayaran dapat dialokasikan ke tagihan pihak yang sama; jumlah alokasi sama dengan pembayaran, tidak melampaui sisa tagihan. Pending transfer harus mencegah usulan ganda/over-allocation saat disahkan, memakai lock dan idempotensi.
- Persetujuan bukan bukti uang telah dikirim: status pengajuan, persetujuan, pembayaran aktual, penolakan dipisahkan bila alurnya memerlukan. Pengurangan utang terjadi hanya bersama posting pembayaran aktual.
- Stok keluar mengikuti posting penjualan/serah barang satu kali. Tagihan transfer pending tetap membuat piutang, bukan pembayaran bank fiktif. Pembayaran transfer setelah diverifikasi memindah Bank vs Piutang, tanpa mengulang pendapatan/HPP/stok.
- Dokumen posted tidak diedit/dihapus. Koreksi melalui pembalikan/penyesuaian dengan alasan, hubungan dokumen dan aktor tercatat.

## 4. Peta layar dan tanggung jawab

### Kasir
Buka shift (sumber uang + nominal) -> POS -> nota -> surat jalan bila dikirim -> penerimaan cicilan -> mutasi laci -> laporan harian -> hitung uang dan tutup shift. Daftar piutang menampilkan pelanggan, nota, jatuh tempo bila ada, total, pembayaran sah, pending, dan sisa. Pengajuan utang menampilkan tagihan supplier dan jejak status, bukan tombol hapus transaksi.

### Admin
Dashboard pengecualian (transfer menunggu, pembayaran usulan, selisih shift) -> master -> penerimaan barang tunai/utang -> daftar utang -> persetujuan/pembayaran -> saldo awal -> ritase -> laporan. Laporan menyajikan angka riil; data belum tersedia tampil kosong, bukan dummy seolah transaksi asli.

### Dokumen dan ritase
Nota menyimpan harga/diskon/ongkir/payment state snapshot. Surat jalan menaut nota, penerima/tujuan, kendaraan/sopir dan daftar barang; nomor unik dan status disiapkan/dikirim/diterima. Ritase manual boleh ditautkan ke surat jalan; identitas kendaraan/sopir dan biaya bersumber jelas. Uang muka sopir dan realisasi tidak dihitung sebagai beban dua kali. Selisih/uang kembali sopir diselesaikan melalui mutasi yang dapat diaudit. Transaksi manual tidak otomatis berarti stok atau jurnal boleh kosong.

## 5. Model data dan kontrak uang

Batas domain fresh: roles/permission, master entities, cashier shifts, cash movements, sales/items, invoice balances, payment requests/allocations, receipts/items, deliveries/items, trips/settlements, opening batches, import batches/audit, journal/lines.

Tabel V2 dibuat additif; tidak menjatuhkan tabel legacy. Referensi ID dan snapshot nama dokumen menjaga dokumen lama tetap dapat dibaca saat master diarsipkan. Master terpakai diarsipkan, bukan dihapus. Pemetaan legacy eksplisit dengan kunci unik agar pemindahan ulang tidak menggandakan.

Uang DECIMAL(19,2); kuantitas/HPP empat desimal. Aritmetika string desimal server-side. Jurnal debit/kredit berpasangan dan seimbang per dokumen, transaksi atomik, kunci per sumber saldo/tagihan, idempotency key unik. Saldo laporan turunan jurnal/alokasi, bukan angka kedua yang bisa berbeda sendiri. Impor uang dilarang pada template master.

Modal shift = pemindahan internal/penetapan kustodian, bukan laba baru. Penjualan = Piutang/Kas + Pendapatan/HPP sesuai peristiwa. Penerimaan pembelian utang = Persediaan vs Utang. Cicilan = Kas/Bank vs Piutang atau Utang vs Kas/Bank, bukan penjualan/pembelian kedua.

## 6. Excel dan pembukaan mandiri

Template .xlsx terpisah per jenis master dengan kode eksternal unik, kolom wajib, tipe data dan petunjuk. Alur: download template -> isi -> unggah di area berizin -> pratinjau/validasi baris -> konfirmasi impor -> hasil sukses/gagal yang dapat diunduh. Tidak ada import diam-diam saat file masuk. Formula/macro/external links tidak dijalankan; batas ukuran, baris, file, duplikasi dan referensi dijaga. Gunakan fixture sintetis saja.

Saldo awal ditulis sebagai draf formulir: kas/bank, stok bernilai, piutang/utang per faktur dan aset bila dipakai. Posting pembukaan wajib seimbang, tersambung ke subledger, sekali, dan immutable. Tidak menggandakan pembukaan legacy. Tidak menanam saldo agar demo tampak hidup.

## 7. Tahapan pembangunan (DAG)

1. PDF awal ini + kontrak .md + tracker, inventaris schema/route/deploy dan backup sumber/database.
2. Fondasi V2: permission server, schema additif, angka eksak, posting atomik dan uji penolakan.
3. Master dan onboarding: pelanggan/supplier/karyawan/kendaraan/produk, template Excel dan saldo awal draf.
4. Shift + POS: modal kembalian, diskon bertingkat, ongkir terpisah, cash/transfer pending, nota dan surat jalan.
5. Tagihan: piutang/utang, cicilan, verifikasi transfer, pengajuan/pembayaran supplier dan mutasi laci.
6. Ritase, pertanggungjawaban sopir, laporan harian terpisah tunai/bank/pending dan pengecualian.
7. Pematangan hijau-putih + keyboard/responsif, test runtime per slice, MariaDB rehearsal, browser riil dan QC final.
8. Backup/restore rehearsal + rekonsiliasi/pemetaan data + penggantian aktif satu kali, health check, monitor dan rollback.

Pekerja DeepSeek memakai combo deepseek-1017-rr yang sudah dibuktikan bergilir ali/al icn versi 1017. Pekerjaan dibagi berkas terpisah; laporan anak bukan bukti. Mandor menjalankan verifikasi artefak/tes; pengawas hanya mencatat kekurangan, tidak mengulang eksekusi tanpa batas. Sprint mandiri maksimal 30 menit, lalu laporan hasil, batasan dan sisa. Operasi yang perlu bertahan harus memakai proses terkelola, bukan klaim kerja diam-diam.

## 8. Kriteria lulus yang dapat diperiksa

- Kasir gagal mengakses fungsi admin, termasuk POST langsung, tanpa efek samping.
- Diskon baris lalu subtotal benar; ongkir utuh; batas negatif/over-discount ditolak.
- Stok dan jurnal tidak ganda pada double click/retry; over-selling ditolak.
- Transfer pending belum menambah bank; verifikasi sekali menurunkan piutang sekali.
- Cicilan tidak melampaui sisa; pengajuan supplier belum mengurangi saldo; audit aktor dan status ada.
- Buka/tutup shift dan carry-forward tidak menghasilkan uang baru; selisih terlihat, bukan dihapus.
- Surat jalan pengiriman wajib, nota ambil sendiri cukup; dokumen tidak memicu posting kedua.
- Master import memiliki dry-run, duplicate check, validasi dan kegagalan atomik; master import tidak mengubah saldo.
- Migrasi MariaDB additif lulus rehearsal; data historis dan hash jurnal yang tidak termasuk migrasi tetap identik.
- Backend tests exit 0; aset build exit 0; URL target benar; browser interaksi login/checkout/pending/approval/close shift dan screenshot 390/1440 diperiksa. Setiap gate belum dijalankan diberi NOT RUN.

## 9. Rilis, rollback dan bukti

Simpan backup source, file local dirty/untracked, config sensitif secara privat serta dump DB dengan transaksi konsisten, routines/triggers; validasi gzip + table count + checksum. Uji restore pada DB disposable. Jangan mempercayai dump saja.

Promosi hanya bundle yang telah lulus. Tambahan schema tidak dibalik dengan DROP otomatis saat kode rollback; data keuangan yang sudah diposting membutuhkan forward-fix atau restore dengan window transaksi yang disepakati. URL publik tetap /kasir-bangunan. Layanan selain aplikasi ini di luar scope.

PDF pertama ini adalah rancangan v0.1, bukan screenshot aplikasi selesai. Revisi dokumen dan status pengujian disimpan bersama source; file rencana tidak menggantikan program yang bekerja. Akhir rilis harus menyertakan tautan aplikasi, PDF hasil/screenshot, daftar tes riil, backup/rollback, serta kekurangan yang masih ada.
