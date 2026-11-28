# Help Center Kasir Bangunan — Draf untuk dikoreksi

- **Status:** Draf teks, belum diterapkan di aplikasi.
- **Cakupan:** Panduan pengguna POS V2 untuk kasir dan admin.
- **Tujuan:** Pengguna bisa menemukan cara menyelesaikan pekerjaan atau memahami status transaksi tanpa membaca dokumentasi teknis.

## 1. Cara masuk dari POS

- **Dari halaman fitur:** ikon info yang sudah ada tetap menampilkan ringkasan saat hover. Saat ikon diklik atau diketuk, kartu dapat dikunci terbuka dan menampilkan tautan **Baca panduan lengkap**. Tautan bisa difokuskan lewat keyboard; pengguna tidak perlu mengejar kartu hover dengan kursor.
- **Dari menu aplikasi:** tautan **Pusat Bantuan** menuju beranda dokumentasi. Beranda berisi pencarian dan pintasan untuk pekerjaan yang paling sering dilakukan.
- **Dari tautan yang dibagikan:** buka langsung artikel atau subjudul, misalnya `panduan/kasir/mencatat-penjualan#pembayaran`. Ini contoh alamat, bukan rute yang sudah dibuat.
- Panduan bersifat baca saja. Saat dibuka dari POS, transaksi yang sedang disusun tidak boleh terganggu: **keranjang, isian, dan halaman aktif tetap utuh**. Usulan: buka artikel di tab baru dari POS dengan penanda yang jelas; navigasi antarartikel tetap di tab panduan.

## 2. Fitur yang perlu dibuat

| Fitur | Keputusan | Alasan |
|---|---|---|
| Global Search | **Masuk versi awal** | Cari judul dan isi artikel dengan bahasa pengguna, termasuk istilah seperti *shift*, *kas harian*, *DP*, *cicilan*, dan *foto transfer*. Judul yang cocok muncul lebih dulu. Hasil mengarah langsung ke artikel atau subjudul. |
| Sidebar Navigation | **Masuk versi awal** | Kelompokkan panduan per modul sesuai istilah aplikasi. Menu panduan tidak perlu menyalin setiap menu POS satu per satu. |
| Table of Contents | **Masuk versi awal** | Tampilkan daftar subjudul untuk artikel panjang saja; artikel pendek tidak perlu panel tambahan. |
| Scroll Spy | Tunda | Tautan daftar isi sudah cukup untuk versi awal. Tambahkan jika artikel panjang sulit dinavigasi. |
| Breadcrumb | **Masuk versi awal** | Jalur pendek seperti **Pusat Bantuan › Penjualan › Mencatat penjualan** menunjukkan posisi dan cara kembali. |
| Version Switcher | Tunda | Tampilkan label **Berlaku untuk POS V2**. Pemilih versi baru berguna jika dua versi yang masih dipakai benar-benar memerlukan panduan berbeda. |
| Role-Based Documentation | **Masuk versi awal** | Bedakan panduan bersama dan khusus admin. Pencarian, daftar modul, tautan langsung, dan artikel terkait mengikuti hak akses yang sama. |
| Interactive Walkthrough | Tunda | Berpotensi mengganggu layar kerja dan cepat usang saat tombol berubah. Panduan langkah demi langkah cukup dulu. |
| Image Lightbox | Tunda | Versi awal berbasis teks. Tambahkan hanya bila ilustrasi detail benar-benar perlu diperbesar. |
| Video Tutorial | Tunda | Perlu waktu produksi dan pemeliharaan; pertimbangkan untuk alur yang masih sulit dipahami dari teks. |
| Deep Linking | **Masuk versi awal** | Artikel dan subjudul punya alamat tetap untuk dibuka dari POS atau dibagikan antarpetugas. |
| Related Articles | **Masuk versi awal** | Maksimal tiga tautan yang memang menjadi langkah berikutnya, misalnya dari penjualan ke riwayat atau status pembayaran. |
| Article Feedback | Tunda | Tombol Ya/Tidak belum berguna jika belum ada orang dan proses untuk menindaklanjuti masukan. |
| Export to PDF | Tidak perlu ekspor khusus | Pakai **Cetak → Simpan sebagai PDF** bawaan browser untuk artikel yang sedang dibuka. |
| Print Friendly | **Masuk versi awal** | Cetak isi artikel tanpa navigasi, pencarian, atau kontrol lain. |
| Changelog | Tunda sebagai halaman terpisah | Tampilkan perubahan yang berdampak pada pengguna di artikel terkait; buat halaman tersendiri jika jumlahnya mulai banyak. |
| Last Updated | **Masuk versi awal** | Tampilkan tanggal terakhir isi artikel benar-benar ditinjau. |
| Dark Mode | Tunda sebagai fitur tersendiri | Tidak perlu sakelar tambahan. Ikuti tema aplikasi bila tersedia. |

## 3. Daftar artikel perdana

Urutan berdasarkan pekerjaan pengguna, bukan urutan menu atau struktur database.

| Modul | Artikel prioritas | Pembaca |
|---|---|---|
| Mulai | Mengenal ruang kerja; tugas kasir dan admin | Keduanya |
| Penjualan | Mencatat penjualan; mencari dan mencetak ulang nota; memilih harga eceran atau kontraktor | Keduanya |
| Kas harian | Memeriksa apakah kas aktif; membuka dan menutup kas; memeriksa selisih | Cek status: keduanya. Buka, tutup, dan selisih: admin. |
| Tagihan & pembayaran | Mencatat cicilan pelanggan; membaca status transfer; memeriksa pengajuan; membayar supplier | Langkah umum: keduanya sesuai izin. Verifikasi: admin. |
| Pengiriman | Menyiapkan dan memantau pengiriman; mencatat uang muka dan realisasi biaya | Panduan bersama, dengan tindakan tertentu ditandai sesuai peran |
| Penerimaan & stok | Menyimpan draf dan mengonfirmasi penerimaan; mencatat stok opname; menangani retur | Admin |
| Master data | Mengelola produk, pemasok, pelanggan, dan harga; memahami arsip data | Admin |
| Keuangan & laporan | Mencatat pengeluaran; membaca jurnal, neraca, laba rugi, dan arus kas; melihat laporan harian | Keuangan: admin. Laporan harian: sesuai cakupan peran. |

**Urutan penulisan:** mulai dari **Mencatat penjualan**, **Kas harian**, **Riwayat nota**, serta **Status transfer dan cicilan**. Setelah gaya dan isinya disetujui, lanjutkan ke artikel admin dan modul berikutnya. Daftar di atas adalah usulan cakupan, bukan daftar artikel yang sudah lengkap.

## 4. Format artikel

1. **Judul berbentuk tindakan**, misalnya *Mencatat penjualan*, bukan sekadar *Modul POS*.
2. **Ringkasan satu atau dua kalimat**, serta peran dan prasyarat yang benar-benar penting.
3. **Langkah singkat** dengan nama tombol atau menu seperti di aplikasi.
4. **Hasil yang diharapkan** dan penjelasan status yang sering disalahartikan.
5. **Jika tidak berhasil**, jelaskan penyebab umum dan tindakan berikutnya.
6. **Artikel terkait**, versi berlaku, dan tanggal terakhir ditinjau.

Gunakan contoh angka dan identitas sintetis. Jangan masukkan data pelanggan asli, kredensial, atau contoh yang bisa disalahartikan sebagai transaksi toko.

## 5. Contoh artikel — Mencatat penjualan

**Pusat Bantuan › Penjualan › Mencatat penjualan**

- **Untuk:** Kasir dan admin · **Berlaku untuk:** POS V2
- **Terakhir ditinjau:** diisi saat artikel disetujui.

Catat penjualan dari katalog sampai nota tersimpan. Gunakan panduan ini untuk transaksi **tunai**, **transfer**, atau **kredit**.

### Sebelum mulai

- **Kas harian** sudah dibuka oleh admin dan masih aktif. Kasir tidak perlu membuka laci pribadi.
- Pastikan jumlah yang dijual tidak melebihi stok.
- Siapkan data pelanggan untuk transaksi transfer atau kredit. Jika ada pembayaran melalui transfer, siapkan foto buktinya.

### Langkah transaksi

1. Buka **Operasional › Kasir (POS)**. Cari barang berdasarkan nama atau SKU, lalu pilih barang dari katalog.
2. Buka **Lihat keranjang**. Periksa jumlah, satuan, dan diskon jika ada.
3. Pilih pembeli dan tipe harga **Eceran** atau **Kontraktor**. Periksa ulang harga di keranjang sebelum lanjut.
4. Lanjutkan ke pembayaran. Periksa metode, nominal, serta cara barang diserahkan: diambil atau dikirim.
5. Tinjau rincian dan konfirmasi sekali. Setelah muncul **Penjualan berhasil**, periksa nomor nota dan status pembayaran. Cetak nota atau surat jalan bila diperlukan.

### Pembayaran

| Metode | Yang perlu diperhatikan |
|---|---|
| **Tunai** | Masukkan uang yang diterima. Jika cukup, nota lunas dan sistem menampilkan kembalian bila ada. |
| **Transfer** | Pilih pelanggan terdaftar. Jika mencatat pembayaran transfer, unggah foto bukti. Status menunggu verifikasi belum berarti lunas. |
| **Kredit** | Pilih pelanggan terdaftar. Jika ada uang muka, pilih cara bayarnya. DP transfer tetap perlu verifikasi; sisa yang belum dibayar menjadi piutang. |

### Jika tidak berhasil

| Yang terlihat | Yang perlu dilakukan |
|---|---|
| Kas harian belum aktif | Hubungi admin. Jika kas hari itu sudah ditutup, jangan mencoba membukanya ulang untuk tanggal yang sama. |
| Barang habis atau jumlah melebihi stok | Kembali ke keranjang dan periksa jumlah serta satuannya. |
| Transfer masih menunggu verifikasi | Buka **Status pembayaran**. Jangan mencatat pembayaran ulang hanya karena nota belum lunas. |
| Tidak yakin transaksi tersimpan | Periksa **Riwayat penjualan** sebelum mengulang konfirmasi. |

**Baca juga:** *Memeriksa kas harian* · *Mencari dan mencetak ulang nota* · *Memantau status transfer*.

## 6. Yang perlu dikoreksi sebelum lanjut

- Apakah nama menu utamanya **Pusat Bantuan**, **Panduan POS**, atau istilah lain?
- Apakah empat topik perdana sudah sesuai masalah yang paling sering ditanyakan petugas?
- Apakah contoh artikel cukup ringkas, atau ada bagian yang terlalu pendek untuk pengguna baru?
- Apakah panduan dari POS sebaiknya dibuka di tab baru agar transaksi tetap utuh?

**Rujukan draf:** [Dokumentasi Sistem Kasir Bangunan](dokumentasi-sistem.md), terutama bagian 3 dan 6–16. Nama tombol dan detail izin yang akan diterbitkan diperiksa kembali pada aplikasi sebelum implementasi. Referensi gambar dari pemilik dicatat sebagai susunan informasi (pencarian, modul, artikel, daftar isi), bukan spesifikasi layout pada tahap ini.
