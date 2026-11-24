---
judul: Dokumentasi Sistem Kasir Bangunan (tb01)
versi_sistem: V2 (V2_ENABLED=true)
terakhir_diperbarui: 2026-11-24
sumber_kebenaran: kode di repositori ini (app/Services/V2, app/Http, routes, database/migrations)
---

# Dokumentasi Sistem Kasir Bangunan (tb01)

Dokumen ini menjelaskan cara kerja sistem dari awal sampai akhir: membuka shift, berjualan, menagih, mengirim barang, menerima barang dari supplier, sampai rekap keuangan (jurnal, neraca, laba rugi, arus kas).

Cara membaca:
- Bagian 1–4 adalah gambaran umum dan persiapan. Baca dulu bila baru mengenal sistem.
- Bagian 5–12 menjelaskan setiap alur kerja harian, lengkap dengan aturan dan contoh angka.
- Bagian 13–15 menjelaskan pembukuan dan laporan keuangan.
- Bagian 16–20 adalah rujukan: nomor dokumen, status, keamanan, tabel data, dan lokasi kode.
- Bagian 21 berisi batasan sistem saat ini dan hal yang masih perlu diputuskan.

Semua aturan di dokumen ini diambil dari kode yang berjalan, bukan dari rencana. Bila kode berubah, dokumen ini ikut diperbarui (lihat bagian 22).

---

## Daftar isi

1. Gambaran sistem
2. Istilah penting
3. Peran dan hak akses
4. Memulai dari nol
5. Alur harian dalam satu gambar
6. Shift toko bersama dan laci kas
7. Penjualan di Kasir
8. Tagihan dan pembayaran
9. Persetujuan dan penolakan pembayaran
10. Pengiriman, sopir, uang muka, dan biaya ritase
11. Penerimaan barang dari supplier
12. Master data
13. Saldo awal pembukuan
14. Pembukuan otomatis (jurnal)
15. Laporan dan rekap keuangan
16. Stok barang
17. Nomor dokumen dan status
18. Keamanan dan keandalan
19. Peta tabel database
20. Peta kode untuk pengembang
21. Batasan saat ini dan hal yang perlu diputuskan
22. Cara merawat dokumen ini
23. Riwayat perubahan dokumen

---

## 1. Gambaran sistem

Kasir Bangunan adalah aplikasi web (Laravel) untuk toko material bangunan. Satu aplikasi menangani:

- penjualan di kasir (tunai, transfer, kredit) dengan harga eceran atau harga kontraktor;
- shift toko bersama dan uang laci;
- piutang pelanggan dan utang supplier;
- pengiriman barang dengan armada toko, sopir, uang muka sopir, dan biaya perjalanan;
- penerimaan barang dari supplier (pembelian kredit);
- data induk: produk, pelanggan, supplier, karyawan dan sopir, mobil, satuan, kategori;
- pembukuan berpasangan (double entry) yang dibuat otomatis dari setiap transaksi;
- laporan: laporan harian, ringkasan penjualan, jurnal umum, neraca, laba rugi, arus kas.

### 1.1 V2 dan arsip lama

Sistem punya dua generasi:

| | V2 (yang dipakai) | Sistem lama (V1) |
|---|---|---|
| Status | Aktif karena `.env` berisi `V2_ENABLED=true` | Arsip |
| Halaman | Satu ruang kerja di `/` dengan menu samping | Halaman terpisah: `/products`, `/expenses`, `/ritase`, `/stock-opname`, `/inventory/receipts`, `/piutang`, dll. |
| Akses | Admin dan kasir sesuai peran | Hanya admin, dan **hanya membaca** (GET). Semua simpan/ubah ditolak 403 oleh `V2Cutover` |
| Tabel | `v2_*`, ditambah tabel bersama | `sales`, `sale_items`, `expenses`, `ritases`, `stock_opnames`, dll. |

Tabel yang dipakai bersama oleh V1 dan V2: `products`, `stock_movements`, `chart_of_accounts`, `journal_entries`, `journal_entry_lines`, `inventory_receipts`, `inventory_receipt_items`, `vehicles`, `users`. Artinya stok dan buku besar hanya ada satu.

Halaman Keuangan ditampilkan di dalam ruang kerja V2. Jurnal umum dan laporan tetap **baca saja** untuk mutasi lama. **Bagan akun mempunyai jalur simpan V2 khusus admin** (bagian14.4), dan **jurnal penyesuaian kas/bank mempunyai jalur V2 khusus admin** (bagian14.5). Form saldo awal lama dan jalur mutasi lama tetap ditolak, bukan dibuka seluruhnya.

### 1.2 Menu ruang kerja

Saat kirim login, skeleton langsung menutupi formulir sambil menunggu pemeriksaan akun oleh server; bukan autentikasi optimistis. Setelah halaman tujuan diterima, skeleton workspace tampil selama susunan panel disiapkan. Panel Keuangan yang belum memiliki cache memakai skeleton bersama; data cache yang masih tersedia dapat langsung ditampilkan. Blok skeleton memakai gradasi kilau yang bergerak dari kiri ke kanan (1,5 detik, berulang) plus denyut halus, sehingga terlihat proses masih berjalan. Animasi menghormati reduced-motion; saat reduced-motion aktif blok tampil statis tanpa kilau. Error loading menampilkan pesan/fallback, tidak mengisi angka palsu. Password/session tidak disimpan dalam preferensi loading.

| Grup | Menu | Siapa |
|---|---|---|
| Pengawasan | Ringkasan, Persetujuan pembayaran, Akun login | Admin |
| Operasional | Kasir (POS), Tagihan, Pengiriman, Kas & laporan (tab Shift toko dan Laporan harian) | Admin dan kasir |
| Operasional | Penerimaan barang | Admin |
| Operasional | Status pembayaran | Kasir, untuk memantau pembayaran yang diajukan sendiri |
| Master data | Produk, Supplier & pelanggan, Karyawan & sopir, Mobil, Satuan & kategori | Admin |
| Keuangan | Pengeluaran, Jurnal umum, Neraca, Laba rugi, Arus kas, Bagan akun (COA) | Admin |

Halaman pertama setelah login: admin ke **Ringkasan**, kasir ke **Kasir**.

Pil status di kepala ringkasan memakai gaya badge tema: latar lembut, titik warna, tanpa bingkai tebal — hijau untuk ok, kuning untuk peringatan, biru untuk info, merah lembut untuk masalah.

Submenu gabungan (**Supplier/Pelanggan**, **Satuan/Kategori**) mengganti panel di tempat tanpa memuat ulang data atau memutar ulang animasi masuk; isinya memakai transisi opacity/geser singkat. Perpindahan menu sidebar tetap memakai animasi dan mengembalikan gulir ke atas.

**Pengeluaran** (Keuangan, admin) membuka pencatatan biaya operasional toko yang tidak berasal dari penjualan, piutang, atau laci kasir. Akun beban dipilih dari COA aktif (di luar HPP/persediaan), sumber dana Kas Tunai (1101) atau salah satu Rekening Bank aktif (bawaan 1102 dan akun aset bertanda Rekening Bank di COA). Nominal diformat Rupiah saat diketik dan dikirim sebagai angka mentah. Simpan mengirim ke endpoint V2 `/v2/expenses` (bukan rute arsip lama yang menolak penulisan), memposting jurnal seimbang beban bertambah dan Kas/Bank terpilih berkurang, lalu jurnal dikunci. Tampilan memakai tema ruang kerja: kartu ringkas, empat fakta (total, transaksi, tunai, bank), filter tanggal/akun/sumber, dan tabel transaksi; daftar terakhir menampilkan sumber dana dan status Terkunci. Halaman ini tersedia sebagai panel workspace dan tetap dapat dibuka penuh lewat URL `/expenses`.

Untuk kasir, menu "Kas & laporan" bernama "Shift & laporan saya" dan hanya berisi data miliknya sendiri.

Pada layar ≤760 px, daftar Pengeluaran memakai kartu label–nilai yang sama dengan tabel operasional. Pada tablet hingga 900 px, kolom tetap cukup lebar dan badge tidak terpotong per huruf; geser kiri–kanan di dalam tabel untuk melihat kolom yang tidak muat. Tampilan desktop tetap berupa tabel.

### 1.3 Aksi baris pada tabel

Semua tabel ruang kerja V2 menampilkan kolom **Aksi** sebagai satu tombol tiga titik per baris; panel kecil berisi aksi asli baris itu (Detail, Ubah, Arsipkan, pembayaran, dokumen, dan seterusnya). Panel dibuka dengan klik, Enter/Space, atau panah bawah; Escape menutup dan mengembalikan fokus ke tombol; klik di luar, gulir, atau ubah ukuran jendela juga menutupnya. Hanya satu panel terbuka pada satu waktu, dan posisinya dijaga agar tetap di dalam layar.

Kontrol dipindahkan apa adanya, bukan disalin: listener, nilai form, kunci idempotensi, dan dialog yang sudah menempel pada baris tetap bekerja. Dialog tetap berada di baris; panel hanya menampung pemicunya. Tanpa JavaScript, tombol aksi lama tetap tampil seperti semula. Tombol **+ Tambah** pada katalog Kasir sengaja tidak diubah menjadi tiga titik agar menambah barang tetap satu klik.

### 1.4 Tooltip bersama

Tooltip tunggal dipakai di ruang kerja V2, halaman laporan lama, dan halaman login. Sumber teks: `data-tip`, `title` lama (dibaca sekali saat hover/fokus lalu dikembalikan), atau teks terpotong (`data-tip-truncate`, hanya bila benar-benar terpotong). Muncul saat hover dengan jeda singkat dan saat fokus keyboard; Escape, blur, klik luar, gulir, atau ubah ukuran menutupnya. Posisi dijaga di dalam layar dan dibalik bila ruang tidak cukup. `aria-describedby` dipasang sementara. Navigasi/rail, kalender, dan `[data-tip-skip]` dikecualikan. Pada layar sentuh tooltip tidak menutupi tombol beraksi; teks terpotong tetap bisa dibaca lewat tip. Cetak menyembunyikan tooltip. Peringatan penting (checkout, pembayaran, arsip) tetap berupa teks, bukan tooltip.

### 1.5 Pil aksi cepat mobile

Pada layar ≤900px tersedia pil aksi cepat di tengah bawah, maksimal tiga tombol tanpa menu tambahan. Lebarnya maksimal 400 px, dan menyusut pada ponsel agar tetap berjarak dari tepi layar. Tampilannya berupa pil hijau gelap berisi ikon saja; aksi aktif/utama berada di lingkaran lime dengan ikon hijau gelap, sedangkan label lengkap tetap tersedia untuk pembaca layar.
- **Admin**: Buat orderan (Kasir + fokus pencarian katalog), Barang masuk (Penerimaan + dialog Catat penerimaan), Pengeluaran (halaman pengeluaran + modal Catat pengeluaran).
- **Kasir**: Kasir (fokus katalog), Riwayat (Riwayat penjualan), Status bayar (Status pembayaran).

Pil tidak tampil di halaman Kasir karena bar keranjang sudah menjadi aksi utama. Setiap aksi satu ketukan dan memakai kontrol/form yang sudah ada; tidak ada alur baru. Pil disembunyikan saat dialog/modal terbuka, lembar keranjang, drawer sidebar, atau kolom teks sedang fokus; konten utama diberi ruang bawah agar baris terakhir tidak tertutup. Desktop ≥901px tidak menampilkan pil. Tombol lama di dalam halaman tetap tersedia sebagai cadangan.

---

## 2. Istilah penting

| Istilah | Arti |
|---|---|
| Shift | Sesi operasional toko yang dibuka/ditutup admin dan dipakai bersama seluruh kasir. Setiap transaksi tetap mencatat petugasnya; laci direkap bersama. Shift pribadi lama tetap menjadi riwayat, bukan dipindahkan. |
| Laci (kas laci) | Uang tunai fisik yang dipegang kasir selama shift. Dihitung dari modal awal ditambah uang masuk dikurangi uang keluar. |
| Modal awal | Uang tunai di laci saat shift dibuka. |
| Carry | Membawa uang hasil hitung shift sebelumnya sebagai modal shift berikutnya. |
| Selisih | Uang fisik yang dihitung dikurangi uang yang seharusnya ada. Bukan nol berarti shift masuk pemeriksaan (review). |
| Nota / penjualan | Satu transaksi kasir, nomor `INV-…`. |
| Tagihan (invoice) | Catatan uang yang harus dibayar. Piutang (`AR-…`) dari penjualan, utang (`AP-…`) dari pembelian. |
| Piutang | Uang yang harus dibayar pelanggan ke toko. |
| Utang | Uang yang harus dibayar toko ke supplier. |
| Pembayaran | Satu kali bayar terhadap tagihan. Bisa langsung tercatat (posted), menunggu verifikasi (pending), atau diajukan (proposed). |
| Surat jalan | Dokumen pengiriman barang, nomor `SJ-…`. |
| Uang muka sopir | Uang yang diberikan ke sopir sebelum berangkat, diselesaikan setelah pulang dengan bukti biaya. |
| Biaya ritase | Biaya perjalanan (BBM dan uang jalan) yang dicatat langsung tanpa uang muka. |
| HPP | Harga pokok: modal per satuan barang. Dihitung rata-rata tertimbang setiap ada penerimaan barang. |
| Harga kontraktor | Harga khusus per produk untuk pelanggan kontraktor. Kosong berarti sama dengan harga jual. |
| Jurnal | Catatan pembukuan berpasangan: total debit selalu sama dengan total kredit. |
| Akun (COA) | Pos pembukuan seperti Kas, Bank, Piutang, Penjualan. Daftar lengkap di bagian 14.1. |
| Diarsipkan | Data master yang dinonaktifkan. Tidak muncul untuk transaksi baru, tetapi riwayatnya tetap utuh. Tidak ada penghapusan. |

---

## 3. Peran dan hak akses

Ada dua peran: **admin** dan **kasir** (`v2_user_roles`). Akun tanpa peran atau yang dinonaktifkan tidak bisa memakai sistem sama sekali (403).

| Kegiatan | Admin | Kasir |
|---|---|---|
| Buka dan tutup shift toko | Ya | Tidak |
| Memakai shift toko aktif untuk transaksi | Ya | Ya |
| Selesaikan pemeriksaan selisih shift | Ya | Tidak |
| Menjual di kasir | Ya (perlu shift terbuka) | Ya (perlu shift terbuka) |
| Membuat pelanggan baru dari kasir | Ya | Ya |
| Mencatat pembayaran piutang | Ya | Ya |
| Mengajukan pembayaran ke supplier | Ya | Ya |
| Menyetujui pembayaran / memverifikasi transfer | Ya | Tidak |
| Menolak pembayaran | Ya | Tidak |
| Mengirim / menandai diterima surat jalan | Ya, semua | Hanya surat jalan dari penjualannya sendiri |
| Uang muka sopir, penyelesaian, biaya ritase | Ya | Tidak |
| Penerimaan barang dari supplier | Ya | Tidak |
| Master data dan impor Excel | Ya | Tidak |
| Saldo awal pembukuan | Ya | Tidak |
| Akun login (buat, ubah, nonaktifkan) | Ya | Tidak |
| Laporan harian | Semua kasir | Hanya miliknya |
| Ringkasan penjualan di Kasir | Seluruh toko | Seluruh toko, sama untuk periode yang sama |
| Keuangan (jurnal, neraca, laba rugi, arus kas) | Baca saja | Tidak |
| Bagan akun (COA): tambah, ubah, arsip | Ya, melalui jalur V2 | Tidak |
| Riwayat transaksi POS | Semua petugas pada shift/filter terpilih | Sama dengan admin |
| Cetak nota / surat jalan | Semua | Dokumen transaksi bersama yang dapat dilihat di POS |

Aturan tambahan untuk akun login (`AccountController`):
- Login menerima username pendek (misalnya `kasir01`) atau email. Nama tampilan bukan identifier login.
- Username unik, 3–32 karakter huruf/angka/titik/garis bawah/tanda hubung, diawali huruf/angka; disimpan huruf kecil. `Kasir01` dan `kasir01` adalah username yang sama. `admin` tetap alias khusus akun admin lama dan tidak bisa diberikan ke akun baru.
- Akun wajib mempunyai minimal salah satu: username atau email. Akun username saja menyimpan email NULL, bukan email rekaan. Email lama dan password lama tidak diubah migrasi.
- Kata sandi minimal 12 karakter.
- Admin tidak bisa menonaktifkan dirinya sendiri atau melepas peran adminnya sendiri.
- Admin aktif terakhir tidak boleh dinonaktifkan atau diturunkan.
- Akun yang masih punya shift terbuka harus menutup shift dulu sebelum diganti perannya atau dinonaktifkan.
- Setiap perubahan dicatat di `v2_account_audits`.
- Mengubah username, peran, status, atau password menaikkan `auth_version`, merotasi remember token, dan mengakhiri sesi lama akun itu. Mengubah nama tampilan saja tidak mengakhiri sesi.

### 3.1 Membuat akses kasir dan mengganti password

1. Login sebagai admin, buka **Pengawasan › Akun login › Tambah akun**.
2. Isi **Nama tampilan**, misalnya Kasir Pagi, dan **Username login**, misalnya `kasir01`. Email boleh dikosongkan jika username sudah diisi; akun email lama tetap didukung.
3. Pilih **Hak akses: Kasir**, **Status akun: Aktif**.
4. Isi **Password baru** minimal 12 karakter dan **Ulangi password** dengan nilai yang sama; simpan. Password diisi langsung di aplikasi, tidak dicatat di panduan/log.
5. Kasir masuk lewat halaman login yang sama dengan username tersebut dan password yang ditetapkan. Admin membuka shift toko; kasir otomatis memakai shift aktif itu sebelum menjual.
6. Untuk reset password, admin memilih **Ubah akun**, mengisi password baru beserta konfirmasi, kemudian menyimpan. Password lama tidak dapat dilihat kembali karena hanya hash yang disimpan. Kosongkan kedua kolom password saat mengubah data lain agar password tetap.
7. Untuk akun email lama yang ingin memakai username, pilih **Ubah akun** lalu isi username unik; email lama boleh dipertahankan. Perubahan username mengakhiri sesi akun itu dan memerlukan login ulang.

---

## 4. Memulai dari nol

Urutan menyiapkan toko baru. Langkah 1 dan 2 dikerjakan pengembang di server; sisanya lewat aplikasi.

1. **Siapkan peran dan akun pembukuan wajib**
   `php artisan v2:prepare --admin=<id user> --cashier=<id user> [--cashier=<id lain>] --apply`
   Perintah ini memberi peran admin/kasir dan menambahkan akun wajib yang belum ada: 1101, 1102, 1103, 1191, 1301, 2101, 3101, 4101, 4102, 5101, 6101. Tanpa `--apply`, perintah hanya memeriksa (tidak mengubah apa pun).
   Untuk instalasi yang sudah berjalan dan **hanya kekurangan akun wajib**, pengembang memakai `php artisan v2:prepare --admin=<id admin aktif> --accounts-only` untuk memeriksa, lalu menambahkan `--apply` untuk melengkapi akun yang hilang. Mode ini tidak mengubah peran, saldo, stok, transaksi, nama akun lama atau pemetaan utang/piutang historis. Akun yang sudah ada tetapi tidak aktif/salah klasifikasi ditolak untuk diperiksa, bukan dibetulkan diam-diam. Ini bukan persetujuan cutover atau rekonsiliasi data lama.
2. Setelah itu akun login berikutnya cukup dibuat lewat menu **Pengawasan › Akun login**.
3. **Isi master data** (admin): satuan dan kategori, supplier, produk (tautkan ke supplier), pelanggan, karyawan dan sopir, mobil. Bisa satu per satu atau impor Excel (bagian 12.7).
4. **Posting saldo awal** (admin, sekali seumur instalasi): menu **Kas & laporan**, kartu "Saldo awal pembukuan". Isi kas, bank, stok awal per produk, piutang awal, utang awal (bagian 13). Saldo awal hanya bisa diposting selama belum ada jurnal, stok, atau tagihan apa pun.
5. **Admin membuka shift toko**, kemudian kasir mulai berjualan memakai shift tersebut (bagian 6 dan 7).

Penting: barang hanya bisa dijual bila stoknya cukup. Stok masuk lewat saldo awal atau penerimaan barang dari supplier.

---

## 5. Alur harian dalam satu gambar

```
 KASIR                                   ADMIN
 ─────                                   ─────
 Gunakan shift toko aktif ◄───────────── Admin buka shift (modal awal)
        │                               │
 Jual di Kasir ──┬─ Tunai ──► uang masuk laci, piutang langsung lunas
                 ├─ Transfer ─► pembayaran "menunggu" ─────► Verifikasi transfer ─► masuk Bank
                 └─ Kredit ──► piutang terbuka (DP tunai/transfer boleh)
        │
 Dikirim? ──► Surat jalan "Siap dikirim" ──► Dikirim (pilih mobil + sopir) ──► Diterima
        │                                    Uang muka sopir ─► Penyelesaian (biaya + sisa)
 Pelanggan bayar piutang ─┬─ Tunai ──► masuk laci
                          └─ Transfer ─► menunggu ─────────► Verifikasi transfer
 Ajukan bayar supplier ────────────────────────────────────► Setujui (pemohon tidak boleh menyetujui sendiri)
        │                                Penerimaan barang dari supplier ─► stok naik + utang supplier
 Admin tutup shift (rekap otomatis seluruh kasir + hitung uang fisik)
        ├─ cocok ──► Selesai
        └─ selisih ─► Perlu diperiksa ──────────────────────► Selesaikan dengan alasan
                                         Laporan harian, Ringkasan, Jurnal, Neraca, Laba rugi, Arus kas
```

---

## 6. Kas harian bersama dan laci kas

Kode internal tetap `Operations::openShift`, `activeStoreShift`, `closeShift`, `resolveShift`, `ShiftClosingSummary`. Menu: **Kas & laporan › Kas harian** (kasir: **Kas harian & laporan saya**). Istilah shift di nama tabel/API adalah istilah teknis lama; penanda yang terlihat oleh pengguna adalah tanggal kas harian, bukan nomor urut.

### 6.1 Membuka kas harian

- Hanya admin yang boleh membuka/menutup shift toko. Maksimal satu shift toko aktif untuk seluruh akun, dilindungi indeks unik `store_open_key` dan pemeriksaan server.
- **Satu kali buka dan tutup per tanggal**, menurut tanggal server aplikasi. Catatan baru menyimpan `v2_shifts.business_date` dengan indeks unik. Sesudah ditutup, baik cocok maupun perlu pemeriksaan, tanggal itu tidak dapat dibuka ulang oleh admin mana pun; hari berikutnya boleh dibuat setelah catatan yang masih terbuka selesai ditutup.
- Pemeriksaan tanggal juga membaca `opened_at` catatan lama, sehingga catatan lama yang tanggalnya sama tetap menghalangi pembukaan ulang. Migrasi hanya menambah kolom nullable dan indeks; tidak mengisi ulang, menggabungkan, mengganti ID, atau mengubah angka catatan lama. Lebih dari satu catatan pada tanggal yang sama dari sistem lama tetap disimpan untuk audit.
- Catatan lama yang melewati tengah malam tidak ditutup atau dipindah ke tanggal baru otomatis. Tanggal yang ditampilkan tetap tanggal pembukaannya. ID angka dan relasi transaksi tetap dipakai di belakang layar.
- Kasir tidak membuka shift sendiri. Penjualan, penerimaan tunai dan pembayaran laci memakai ID shift toko aktif yang sama. `v2_sales.user_id`/pengaju pembayaran tetap petugas sebenarnya, bukan diganti menjadi admin pembuka.
- Status di layar admin/kasir memakai sumber aktif yang sama (`GET /v2/shifts/active`, baca saja, no-store). Tab yang terlihat memeriksa setiap sekitar 5 detik dan saat kembali fokus. Perubahan status memperbarui data di tempat tanpa menghapus keranjang atau memuat ulang halaman. Jika status tidak dapat diperiksa, checkout ditahan dan keranjang tetap disimpan; server tetap memeriksa shift saat transaksi.
- Jika belum dibuka, kasir melihat "Menunggu admin membuka kas harian". Setelah ditutup pada hari yang sama, layar menyebut tanggalnya sudah ditutup, bukan meminta membuka lagi. Aksi buka disembunyikan dan form dinonaktifkan; ketika tanggal server sudah berganti dan tidak ada laci terbuka, aksi dapat digunakan kembali tanpa membuang keranjang.
- `user_id` shift menyimpan pembuka; `closed_by` menyimpan admin penutup. `is_shared` membedakan shift toko dari shift pribadi historis.
- Saat peningkatan versi, bila hanya ada satu shift terbuka dan pembukanya admin, ID itu dijadikan shift toko tanpa mengubah uang/transaksinya. Bila ada beberapa shift lama atau pembukanya kasir, tidak digabung otomatis: admin memeriksa dan menutup masing-masing dari riwayat, lalu membuka shift toko. Jangan reset data atau menganggap semua laci lama satu laci.
- Rekap API menyebut `scope=store` untuk laci bersama dan `sales_scope=own/all` untuk rincian penjualan. Kasir hanya menerima rincian penjualan miliknya; admin menerima seluruh penjualan shift untuk penutupan. Saldo laci bersama tidak dibatasi per kasir.
- Modal awal tidak boleh negatif dan boleh nol.
- Sumber modal:

| Sumber | Arti | Aturan |
|---|---|---|
| Dari admin (`admin`) | Modal diberikan admin | Tidak boleh memilih shift asal |
| Sisa shift sebelumnya (`carry`) | Modal diambil dari uang hasil hitung shift sebelumnya | Wajib memilih shift toko bersama yang sudah ditutup (boleh pembuka admin berbeda), atau shift pribadi historis milik admin sendiri; modal tidak boleh melebihi uang hitung shift asal |
| Admin + sisa shift (`both`) | Sebagian dari sisa shift, sisanya dari admin | Modal harus lebih besar dari uang hitung shift asal; seluruh uang hitung dibawa, sisanya dicatat sebagai modal dari admin |

- Satu shift asal hanya bisa dibawa sekali.
- Membuka shift **tidak membuat jurnal**. Laci adalah bagian dari akun Kas (1101); memindahkan uang dari kas toko ke laci tidak mengubah total kas toko.

### 6.2 Uang masuk dan keluar laci

Setiap gerakan uang tunai laci dicatat di `v2_shift_movements`:

| Arah | Jenis | Kapan |
|---|---|---|
| Masuk | `sale` | Penjualan tunai |
| Masuk | `collection` | DP tunai penjualan kredit, atau pelunasan piutang secara tunai |
| Masuk | `adjustment` | Sisa uang muka sopir dikembalikan tunai (masuk ke laci toko aktif) |
| Keluar | `supplier` | Pembayaran tunai ke supplier yang sudah disetujui |
| Keluar | `adjustment` | Uang muka sopir tunai, biaya ritase tunai (dari laci toko aktif) |

**Uang seharusnya di laci = modal awal + semua uang masuk − semua uang keluar.**

### 6.3 Menutup kas harian

1. Saat popup **Tutup kas harian** dibuka, sistem menampilkan **rekap otomatis** untuk tanggal kas itu (read-only, `ShiftClosingSummary`):
   - Penjualan: jumlah nota, total, dan rincian tunai / transfer / kredit.
   - Laci: kas awal, uang masuk, uang keluar, dan **seharusnya di laci** (rumus bagian 6.2).
   - Jumlah pengajuan tunai dari laci ini yang masih menunggu persetujuan admin (tidak dikurangkan dari saldo, hanya peringatan).
2. Admin menghitung uang fisik laci bersama lalu mengisi hasilnya. Sistem langsung membandingkan dan menampilkan **Cocok** atau **Selisih lebih/kurang Rp …**.
3. Saat menutup, form mengirim **tanda rekap** (`fingerprint`). Bila transaksi laci, penjualan, atau pengajuan berubah sejak popup dibuka, server menolak dengan "tutup ulang untuk melihat angka terbaru", supaya yang dibandingkan selalu angka terkini.
4. Selisih nol → status **Selesai** (`closed`). Selisih bukan nol → status **Perlu diperiksa** (`review`).
5. Hanya admin yang bisa menutup. Rekap shift toko mencakup seluruh petugas yang bertransaksi pada ID shift yang sama; tidak hanya penjualan admin pembuka. Kasir dapat melihat riwayat bersama, tetapi tidak memiliki tombol/izin membuka atau menutup. Sesi laporan penjualan kasir tetap dibatasi miliknya; angka laci berlabel laci toko bersama.

Contoh: modal 500.000, penjualan tunai 2.300.000, bayar supplier tunai 200.000. Seharusnya 500.000 + 2.300.000 − 200.000 = 2.600.000. Kasir menghitung 2.590.000. Selisih −10.000, shift masuk pemeriksaan.

### 6.4 Pemeriksaan selisih

- Admin membuka **Kas & laporan**, kartu "Pemeriksaan selisih kas harian", lalu menulis alasan (5–1000 karakter). Status menjadi Selesai, dan nama admin, waktu, serta alasan tersimpan. Pemeriksaan tidak membuka ulang tanggal tersebut.
- **Selisih kas tidak dijurnal otomatis.** Buku besar Kas tetap memakai angka seharusnya. Lihat bagian 21.
- Ringkasan membedakan **Selisih bulan ini (riwayat)** dan **Belum diselesaikan**. Riwayat adalah jumlah neto selisih shift berstatus closed/review yang ditutup pada bulan berjalan; selesai diperiksa tidak menghapus nilainya. Bulan berikutnya hanya menghitung shift yang ditutup pada bulan baru.
- Belum diselesaikan adalah jumlah neto selisih shift yang masih review **dari semua tanggal**, termasuk bulan lalu. Jumlah shift tetap ditampilkan dan peringatan mengikuti jumlah kasus, bukan netonya: kurang10.000 dan lebih10.000 bernilai neto0 tetapi tetap2kasus yang perlu diperiksa.
- Setelah admin mencatat pemeriksaan, shift itu keluar dari perhitungan belum diselesaikan; nominal selisih asli, admin, waktu dan alasan tetap tersimpan. Ini penyelesaian **pemeriksaan**, bukan bukti uang pengganti sudah diterima atau penyesuaian saldo kas. Koreksi pembukuan, bila diperlukan, memakai proses terpisah pada bagian14.5.
- Daftar pemeriksaan mengambil seluruh shift review, tidak dibatasi30shift terbaru seperti tabel riwayat. Hak admin untuk menyelesaikan dan batas data kasir (shift sendiri/toko bersama) tidak berubah.

---

## 7. Penjualan di Kasir

Kode: `Operations::checkout` (server), `pos-cashier-script` (layar). Menu: **Operasional › Kasir (POS)**.

### 7.1 Langkah di layar

Katalog dibuat lebih ringkas: nama/SKU, stok/satuan, harga dan tombol Tambah. Kategori tetap tersedia pada filter. Produk dengan stok0 menampilkan tombol **Habis** nonaktif; fungsi tambah juga menolak sebelum membuat atau menaikkan baris keranjang. Draf keranjang yang dipulihkan tidak dibuang jika stok berubah, tetapi stok tidak cukup membuat isian tidak valid dan tombol pembayaran nonaktif. Validasi server tetap memeriksa stok pada saat simpan.

Tombol Filter berada di kanan kolom pencarian dalam kepala katalog; popover kategori/kondisi stok dan Reset tetap memakai kontrol yang sama. Tombol Tambah memiliki padding dan lebar otomatis agar teks tidak menempel ke tepi (di mobile tetap penuh). Saat keranjang dibuka/ditutup, latar ikut menggelap/terang kembali selama300ms ease-in-out bersamaan dengan morph; reduced-motion melewati transisi. Klik halaman di bawahnya tetap dicegah sampai penutupan selesai.

Jumlah di tengah tombol−/+ pada katalog dapat diketik langsung, termasuk pecahan dengan koma/titik hingga4desimal. Nilai positif diteruskan ke input keranjang existing dan mengikuti validasi stok/perhitungan yang sama. Nilai kosong,0, negatif, teks atau terlalu banyak desimal tidak diterapkan; blur/Enter mengembalikan nilai sah terakhir. Angka0 tidak menghapus barang diam-diam; penghapusan tetap lewat kontrol yang tersedia.

1. Cari barang di katalog (nama atau SKU, minimal 2 huruf; tekan `/` untuk langsung ke kolom cari), lalu tekan **+ Pilih**. Barang yang sama ditekan lagi menambah jumlahnya.
2. Atur jumlah dengan tombol − dan +, atau ketik langsung (boleh desimal sampai 4 angka, misalnya 2,5 m³).
3. (Opsional) Diskon per barang: nominal (Rp) atau persen.
4. Isi nama pembeli. Kosongkan untuk **pelanggan umum**. Mengetik nama memunculkan saran pelanggan terdaftar; nama baru bisa langsung disimpan sebagai pelanggan baru (kode `CUS-0001` dan seterusnya).
5. Pilih harga **Eceran** atau **Kontraktor** (bagian 7.2).
6. Tekan **Checkout & Pembayaran**, lalu pilih metode Tunai, Transfer, atau Kredit dan isi nominal.
7. (Opsional) Buka "Diskon nota & pengiriman": diskon nota, cara penyerahan (diambil atau dikirim), tujuan kirim, ongkir.
8. **Tinjau Pembayaran**, lalu **Konfirmasi & Simpan Transaksi**.
9. Setelah tersimpan, muncul popup **Penjualan berhasil** berisi nomor nota, total, kembalian, status, dan tombol Cetak nota (serta Surat jalan bila dikirim). Menutup popup langsung menyiapkan transaksi berikutnya tanpa memuat ulang halaman: keranjang kosong, stok di katalog sudah berkurang, ringkasan penjualan diperbarui.

Isi keranjang disimpan di browser, jadi tidak hilang bila halaman tertutup sebelum disimpan.

Di HP dan tablet tegak, keranjang tampil sebagai bar di bawah layar yang membuka lembar atau laci; aturan dan perhitungannya sama.

Syarat sebelum menyimpan: **shift toko sudah dibuka admin dan masih aktif**, untuk metode apa pun. Kasir memakai shift itu otomatis, tanpa membuka shift pribadi.

### 7.2 Harga eceran dan harga kontraktor

- Setiap produk punya **harga jual** dan, bila diisi, **harga kontraktor**.
- Setiap pelanggan punya penanda harga: eceran (default) atau kontraktor.
- Memilih pelanggan kontraktor otomatis mengaktifkan harga kontraktor. Kasir tetap boleh menggantinya per transaksi.
- Bila harga kontraktor dipakai tetapi sebuah produk tidak punya harga kontraktor, produk itu memakai harga jual biasa.
- Server menghitung ulang harga dari database; angka dari browser tidak dipercaya.
- Jenis harga yang dipakai tersimpan di nota (`v2_sales.price_tier`).

### 7.3 Urutan perhitungan

Semua uang dihitung dengan desimal pasti (BigDecimal), 2 angka di belakang koma, pembulatan setengah ke atas. Tidak ada perhitungan uang memakai float.

1. **Harga satuan** = harga jual atau harga kontraktor (bagian 7.2).
2. **Bruto baris** = jumlah × harga satuan.
3. **Diskon baris**: nominal (tidak boleh melebihi bruto) atau persen (0–100).
4. **Neto baris** = bruto − diskon baris.
5. **Dasar barang** = jumlah semua neto baris.
6. **Diskon nota** dihitung dari dasar barang (bukan dari ongkir).
7. **Total** = dasar barang − diskon nota + ongkir. Total harus lebih dari nol.
8. **HPP** = jumlah × HPP produk saat itu, per baris.

Aturan barang:
- Barang yang sama tidak boleh muncul dua baris dalam satu nota.
- Barang yang diarsipkan tidak bisa dijual.
- Jumlah harus lebih dari nol dan tidak boleh melebihi stok.

### 7.4 Metode pembayaran

| Metode | Pelanggan | Nominal yang diisi | Hasil |
|---|---|---|---|
| Tunai | Boleh umum | Minimal sebesar total. Lebihnya jadi kembalian | Nota **Lunas**. Uang masuk laci |
| Transfer | Wajib terdaftar | 0 atau tepat sebesar total | Nota **Belum lunas**. Bila diisi, tercatat pembayaran transfer **menunggu verifikasi** admin |
| Kredit | Wajib terdaftar | Uang muka (DP) 0 sampai total. Bila DP diisi, pilih masuk ke tunai atau transfer | DP tunai: langsung tercatat dan masuk laci. DP transfer: menunggu verifikasi. Sisanya menjadi piutang |

Status nota: **Lunas** (`paid`), **Dibayar sebagian** (`partial`), **Belum lunas** (`unpaid`). Status berubah otomatis setiap ada pembayaran yang tercatat.

### 7.5 Yang terjadi saat nota disimpan

Semua langkah di bawah terjadi dalam satu transaksi database. Bila satu langkah gagal, tidak ada yang tersimpan.

1. Nota `INV-YYYYMMDD-00001` dan baris barangnya tersimpan. Nama barang, harga, dan HPP dicatat sebagai foto saat itu.
2. Stok setiap barang berkurang, dan setiap pengurangan dicatat di `stock_movements`.
3. Tagihan piutang `AR-YYYYMMDD-00001` dibuat sebesar total, bahkan untuk penjualan tunai (langsung lunas).
4. Jurnal penjualan dibuat (bagian 14.2).
5. Bila ada pembayaran tunai: pembayaran tercatat (posted), jurnal penerimaan kas dibuat, dan uang masuk laci.
6. Bila ada pembayaran transfer: pembayaran dicatat sebagai **menunggu**, tanpa jurnal.
7. Bila dikirim: surat jalan `SJ-YYYYMMDD-00001` dibuat berstatus **Siap dikirim**.

### 7.6 Contoh lengkap

Pelanggan kontraktor membeli Semen 10 sak. Harga kontraktor 65.000, HPP 52.000. Diskon baris 10.000, diskon nota 5%, ongkir 50.000. Dibayar tunai 700.000.

| Langkah | Angka |
|---|---|
| Bruto baris | 10 × 65.000 = 650.000 |
| Neto baris | 650.000 − 10.000 = 640.000 |
| Diskon nota 5% | 32.000 |
| Barang bersih | 608.000 |
| Ongkir | 50.000 |
| **Total** | **658.000** |
| Kembalian | 700.000 − 658.000 = 42.000 |
| HPP | 10 × 52.000 = 520.000 |

Jurnal penjualan:

| Akun | Debit | Kredit |
|---|---:|---:|
| 1103 Piutang usaha | 658.000 | |
| 4101 Penjualan | | 608.000 |
| 4102 Pendapatan ongkir | | 50.000 |
| 5101 HPP | 520.000 | |
| 1301 Persediaan | | 520.000 |

Jurnal penerimaan tunai:

| Akun | Debit | Kredit |
|---|---:|---:|
| 1101 Kas | 658.000 | |
| 1103 Piutang usaha | | 658.000 |

Laci bertambah 658.000 (kembalian tidak dihitung sebagai uang masuk).

### 7.7 Simpan ganda

Setiap nota punya kunci unik dari browser. Bila tombol simpan tertekan dua kali atau jaringan mengulang permintaan, sistem mengembalikan nota yang sama tanpa membuat nota, stok, atau jurnal kedua. Kunci yang sama dengan isi berbeda ditolak.

### 7.8 Cetak nota dan surat jalan

`/v2/documents/{id}/receipt` (nota) dan `/v2/documents/{id}/delivery` (surat jalan). Hanya membaca data. Admin dan kasir aktif dapat membuka dokumen transaksi bersama di POS, termasuk transaksi petugas lain dan shift historis. Ini tidak memberi kasir izin mengirim/menyelesaikan surat jalan petugas lain, menyetujui pembayaran, atau mengubah transaksi. Akun tanpa peran atau nonaktif ditolak.

### 7.9 Ringkasan penjualan di atas katalog

Menampilkan penjualan, jumlah transaksi, rata-rata per nota, stok yang perlu perhatian, grafik penjualan, dan 10 produk terlaris untuk Hari ini, 7 hari, atau 30 hari. Setiap angka dibandingkan dengan periode sebelumnya. Admin dan kasir memakai seluruh data toko yang sama, termasuk penjualan yang dibuat admin, bukan difilter berdasarkan akun yang sedang masuk. Kasir tanpa transaksi pribadi tetap melihat angka toko. Sumber: `PosDashboard` (hanya membaca). Pilihan periode disimpan per browser, bukan disinkronkan antar akun/perangkat; bandingkan periode yang sama. Grafik tetap menurut periode tersebut, tidak mengikuti filter riwayat di bawah. Izin kelola akun, master, persetujuan dan laporan harian per petugas tidak berubah; akses riwayat/nota bersama diatur pada bagian7.8/7.10.

### 7.10 Riwayat penjualan dan piutang pada nota yang sama

Tabel Riwayat penjualan memiliki kolom **Nomor nota** dan **Pembeli** terpisah. Nomor/kode menampilkan tanggal dan jam; kolom pembeli menampilkan nama yang dapat ditekan untuk membuka Detail serta nama petugas. Kolom pembayaran, total, sisa tagihan, status dan dokumen tetap tersedia. Pada mobile, identitas nota dan pembeli tetap berlabel terpisah dan tautan dokumen ditampilkan sebagai aksi yang mudah ditekan.

**Tanggal khusus Ringkasan Kasir:** selain tiga periode cepat pada7.9, tersedia rentang dari–sampai, lengkap, mulai≤akhir, tidak di masa depan, maksimal366hari. `GET /v2/sales/dashboard` hanya admin/kasir aktif; data tetap seluruh toko. Membaca rentang nyata dan pembanding tepat sebelumnya sepanjang jumlah hari yang sama, termasuk data lebih tua dari60hari. Sehari digambar per jam; beberapa hari per hari, di atas62hari dikelompokkan7hari dan di atas180hari30hari. Ringkasan/grafik/terlaris berubah bersama, stok tetap kondisi saat ini, lima penjualan terbaru dan tanggal menu lain tidak ikut difilter. Preferensi tersimpan terpisah. Refresh latar memakai rentang yang sudah diterapkan, bukan tanggal draft; gagal fetch dan respons usang tidak menghapus hasil/keranjang.

Keranjang Kasir memakai bar mengambang pada desktop, tablet dan HP. Katalog menggunakan lebar penuh; **Lihat keranjang** membuka panel kanan di desktop/tablet atau lembar bawah di HP. Menutup panel atau berpindah menu tidak menghapus barang. Ringkasan lembar keranjang HP hanya menampilkan diskon bila ada lalu total; subtotal barang tidak diulang. Bar hanya muncul saat ada barang dan menu Kasir aktif. Kontrol jumlah, diskon, dan pembayaran tetap menggunakan formulir/perhitungan semula. Tidak ada tombol kosongkan keranjang massal; barang dihapus satu per satu.

Daftar barang di dalam panel keranjang dapat digulir terpisah, termasuk melalui keyboard setelah area daftar difokuskan. Ringkasan dan tombol pembayaran berada di luar daftar sehingga tidak ikut bergulir bersama barang. Panel tetap memiliki gulir cadangan pada layar sangat pendek agar semua kontrol dapat dijangkau.

Animasi pembukaan panel bermula dari bar keranjang (morph posisi/ukuran/radius/warna), bukan slide dari sisi layar. Buka dan tutup normal berdurasi300ms dengan ease-in-out; tutup mengecil kembali ke posisi bar pembuka lalu mengembalikan fokus. Perpindahan kulit animasi ke panel dijadwalkan bertumpang-tindih tanpa keduanya transparan bersamaan; bar tidak mengulang animasi masuk setelah ditutup. Form tidak ikut diskalakan atau digandakan. Handoff pembayaran, keranjang kosong dan pindah menu tetap menutup langsung serta membersihkan animasi tanpa mengubah barang. Reduced-motion memakai tampilan akhir langsung. Sidebar desktop mempertahankan layout label saat rail melebar dan menganimasikan tinggi submenu agar susunan teks tidak meloncat.

Angka KPI ringkasan Kasir ditween dari nilai periode sebelumnya saat ganti periode, dan dari nilai lama saat uiLive menyegarkan data. Subtotal/harga satuan/total, ringkasan bar keranjang dan kembalian memakai tween300ms. Ini visual saja: input jumlah/uang dibayar tidak bergerak sendiri, nilai akhir tetap presisi dan langsung tersedia untuk konfirmasi serta pembaca layar. Reduced-motion melewati animasi. Tidak menerapkan tween pada seluruh angka di semua menu, nota atau buku besar.

Tween juga meliputi angka dalam teks jumlah jenis/barang, subtotal barang, jumlah barang di pembayaran, label diskon, keterangan KPI dan kuantitas produk terlaris yang berubah. Bagian label tetap, angka akhir untuk transaksi tidak menunggu animasi. Tombol **Tanggal khusus** membuka panel mengambang ke samping dengan Dari/Sampai sejajar dan animasi sistem; bukan memperpanjang dashboard ke bawah. Jika sisi layar tidak cukup, panel menyesuaikan ke area yang muat. Tutup/Escape/klik luar atau pindah menu menutup panel tanpa menghapus tanggal yang diketik; kalender di dalamnya tetap dapat digunakan.

Scrollbar keranjang dan katalog mengikuti warna tema. Pada HP, nama produk memakai satu baris area penuh (dapat membungkus), stok/harga dikelompokkan di bawah dan tombol tambah/kontrol jumlah berada pada baris tersendiri. Susunan daftar katalog tidak lagi dibatasi tinggi tabel desktop. Garis submenu mobile mengikuti pola desktop; kolom pencarian tabel memakai lebar penuh satu baris sendiri di HP dengan tinggi satu kontrol, tanpa mengubah cara pencarian atau filter bekerja.

- Menu **Kasir (POS)** tidak lagi memuat tabel riwayat panjang. Di area ringkasan **di atas keranjang**, kartu putih **Penjualan terbaru** berdampingan dengan **Produk terlaris**, dengan tinggi mengikuti isinya, bukan panjang keranjang. Isinya **5 penjualan terbaru seluruh toko**, tanpa filter tanggal riwayat: nama pembeli, atau Pelanggan umum bila kosong, dengan nomor nota kecil sebagai pembeda. Kartu hanya dirender sekali dan tetap tampil saat periode grafik diganti. Klik nama membuka Detail; tautan **Lihat semua penjualan** membuka menu **Operasional › Riwayat penjualan**. Di tablet grafik berada di atas dua kartu daftar; di HP kartu disusun ke bawah. Tidak mengunci tinggi halaman atau menyembunyikan kontrol untuk memaksakan tanpa gulir di HP.
- Menu **Riwayat penjualan** memuat tabel lengkap: nota/pembeli (tanggal, jam dan petugas di bawahnya), metode, total, sisa tagihan, status, serta akses dokumen. Piutang yang berasal dari penjualan tetap melekat pada nota tersebut; cicilan tidak membuat penjualan kedua. Menu Tagihan tetap tersedia, termasuk utang supplier dan piutang saldo awal/migrasi tanpa nota penjualan.
- Admin dan kasir membaca data bersama, bukan hanya transaksi akun sendiri. Tampilan awal adalah **Mingguan: 7 hari terakhir termasuk hari ini**, dari tanggal server aplikasi. Pilihan cepat **Harian** berarti hari ini, **Mingguan** 7 hari, dan **Bulanan** 30 hari (bukan minggu/bulan kalender). Menutup laci tidak menghilangkan riwayat. Tidak ada pemilih nomor shift; metadata tanggal laci asal tetap disimpan sebagai keterangan aksesibel.
- Pencarian nomor nota/nama pembeli, metode, status (**Semua penjualan / Belum lunas / Dibayar sebagian / Lunas**), tanggal transaksi, dan jumlah baris10/25/50 diproses di server sebelum pagination. Belum lunas mencakup pembayaran sebagian. Sisa/status memakai saldo tagihan yang terkait, dan transfer pending diberi penanda **Menunggu verifikasi**, bukan dianggap lunas. Rentang atau satu batas tanggal boleh dipakai; mengosongkan kedua tanggal secara eksplisit mencari seluruh tanggal.
- Dengan JavaScript aktif, filter, pilihan periode, Atur ulang dan pagination mengambil fragmen `GET /v2/sales/history` (authenticated, private/no-store) dan hanya mengganti hasil. Tautan fallback GET membuka `#riwayat-penjualan`. Filter tetap terpisah dari periode grafik, Tagihan dan laporan; tidak mengubah keranjang. Reset meminta rentang mingguan terbaru dari server,10 baris, serta pencarian/metode/status kosong. Tanggal manual menjadi periode khusus. Pembaruan uiLive mempertahankan filter terapan dan draft isian.
- Tombol **Tanggal khusus** pada ringkasan Kasir langsung membuka kalender rentang bersama, tanpa popup perantara. Pilih awal/akhir lalu **Oke** untuk menerapkan; Tutup/Esc/klik luar membatalkan pilihan sementara. Validasi maksimal366hari dan batas hari ini tetap berlaku; lima penjualan terbaru serta tanggal menu lain tidak mengikuti rentang ini.
- Perpindahan periode ringkasan Kasir memakai transisi grafik bersama: figur chart masuk dengan durasi token morph dan batang tumbuh dari nilai periode sebelumnya atau nol; reduced-motion melewati animasi. Tombol **Tanggal khusus** menampilkan rentang yang sudah diterapkan (misalnya 18 Nov – 24 Nov), status sukses dipersingkat, dan rentang yang sama tidak dimuat ulang; pembaruan uiLive tetap memuat ulang agar data segar.
- Kartu diskon per baris keranjang membuka/menutup dengan morph tinggi dan isi (bukan muncul mendadak); isian memakai tinggi40px dan turun menjadi satu kolom pada layar sangat sempit. Ringkasan keranjang tetap menampilkan total dan diskon bila ada.
- Chart Ringkasan admin dan Kasir, daftar produk terlaris, hasil katalog, serta angka ringkasan memakai animasi data bersama300ms. Pergantian periode membaca snapshot sebelumnya; pembaruan uiLive mengganti chart/daftar yang datanya berubah, bukan memutar ulang hasil identik. Angka canonical/aksesibel langsung hasil akhir, tidak mengubah pembukuan; reduced-motion menampilkan hasil langsung. Form tersembunyi tanggal khusus tidak membuat tombol tambahan **Isi formulir**.
- **Ringkasan Admin** ditata padat agar informasi utama muat satu layar desktop: tindak lanjut, empat KPI, chart, terlaris dan status operasional tetap tersedia. Laptop pendek menampilkan Uang/Pengiriman/Stok sebagai ringkasan bernilai/status yang dapat dibuka lewat **Rincian**; membuka rincian boleh menambah gulir, tidak membuang informasi. Desktop tinggi membuka rincian langsung; ponsel tetap memakai gulir vertikal, bukan pemotongan isi. Perhitungan/periode/hak akses tidak berubah.
- Navigasi membersihkan parameter workspace yang tidak relevan: **date** hanya ditampilkan pada menu Laporan harian; **master_kind** lama dan **partial** tidak dibawa ke alamat menu. Contoh produk cukup `/#master-product`. Saat kembali ke Laporan dalam halaman yang sama, URL memakai tanggal yang sudah diterapkan dari server, bukan tanggal yang masih diketik. Pembaruan uiLive tetap meminta tanggal laporan terapan secara internal agar laporan tidak diam-diam berganti ke hari ini. Path/subpath dan parameter lain yang tidak dikenal tetap dipertahankan; filter terapan menu dan isi form tidak direset. Setelah muat ulang tautan produk yang bersih, tanggal laporan yang tidak aktif kembali mengikuti default server; tautan Laporan sendiri tetap membawa tanggal.
- Bila kas harian belum dibuka, sudah ditutup, atau statusnya belum dapat diperiksa, keterangan **Belum bisa checkout** tampil dekat tombol keranjang. Untuk keranjang terisi dan valid, klik Checkout menampilkan penjelasan yang dapat difokuskan, bukan tombol diam; pembayaran tidak dibuka. Admin diarahkan ke menu Kas harian, kasir diminta menunggu/menghubungi admin; kas yang sudah ditutup menunggu hari berikutnya. Keranjang tetap tersimpan dan pemberitahuan mengikuti status shift lintas sesi. Keranjang kosong/isian tidak valid tetap menonaktifkan checkout; validasi server tetap berlaku.
- Klik nama pada daftar ringkas atau tabel membuka satu dialog Detail melalui `GET /v2/sales/{id}/detail`. Identitas nota/nama dan status tampil di atas; barang ditampilkan dalam tabel jumlah/harga/diskon/subtotal. Total, terbayar, sisa, diskon nota/ongkir bila ada serta riwayat cicilan dikelompokkan dengan jelas; setiap pembayaran memiliki nominal/status, waktu, petugas, referensi, alasan penolakan dan tautan foto bila tersedia. **Cetak struk/nota** berada di footer untuk nota aktif bersama tombol Tutup; aksi cetak lama dibersihkan saat memuat detail lain atau menutup dialog. Bila masih ada sisa, form **Catat pembayaran** menggunakan endpoint, kunci idempotensi, dan penjaga entri dobel yang sama dengan Tagihan; tidak membuat salinan invoice. Tunai langsung tercatat; transfer baru berpengaruh sesudah verifikasi admin.
- Pembayaran selesai akan memperbarui riwayat/saldo lewat uiLive. Tidak mencetak bukti cicilan khusus; nota yang dicetak membaca pembayaran tercatat dan sisa terbaru. Foto hanya untuk transfer pelanggan, per pembayaran (bagian8.7).
- Detail/fragmen memeriksa peran admin/kasir aktif. Respons detail lama tidak boleh mengganti penjualan lain atau dialog yang sudah ditutup. Galat mempertahankan hasil lama, tidak membocorkan rincian server, dan memungkinkan membuka ulang. Semua endpoint baca hanya membaca; izin persetujuan, pengiriman dan laporan per petugas tidak diperluas.

---

## 8. Tagihan dan pembayaran

Kode: `Operations::collect`, `WorkspaceController::invoices`. Menu: **Operasional › Tagihan**. Daftar dipisahkan menjadi tab **Piutang pelanggan** dan **Utang supplier**, dengan awal **Belum lunas** (termasuk dibayar sebagian). Setiap baris punya **Detail**; baris yang belum lunas punya satu aksi pembayaran utama. Popup pembayaran tetap berisi total, terbayar, sisa, jumlah dibayar, metode, dan referensi. Kolom jumlah dibayar kosong; tombol **Lunasi Rp …** mengisi sisa tagihan dengan satu klik, memakai nilai desimal asli termasuk sen.

### 8.1 Jenis tagihan

| Arah | Asal | Nomor |
|---|---|---|
| Piutang (`receivable`) | Setiap penjualan, atau piutang di saldo awal | `AR-YYYYMMDD-00001` (penjualan), atau nomor isian saldo awal |
| Utang (`payable`) | Setiap penerimaan barang dari supplier, atau utang di saldo awal | `AP-000001` (penerimaan), atau nomor isian saldo awal |

Status tagihan: **Terbuka** (`open`) atau **Lunas** (`paid`). Sisa = nilai − sudah dibayar.

### 8.2 Mencatat pembayaran piutang

- Nominal harus lebih dari nol dan tidak boleh melebihi sisa.
- **Tunai**: kasir wajib punya shift terbuka. Pembayaran langsung tercatat (posted), uang masuk laci, sisa tagihan berkurang, dan status nota ikut diperbarui. Jurnal: debit 1101 Kas, kredit 1103 Piutang.
- **Transfer**: foto bukti wajib untuk pembayaran pelanggan baru dari UI/API, termasuk DP dan cicilan. Dicatat sebagai **menunggu verifikasi**, tanpa jurnal dan tanpa mengubah sisa sampai admin memverifikasi (bagian9 dan8.7).
- Pembayaran sebagian boleh dilakukan berkali-kali.

### 8.3 Pembayaran utang supplier

- Tombol di baris tagihan supplier: **Bayar ke supplier** (admin) atau **Ajukan pembayaran** (kasir).
- **Sumber dana** yang dipilih di popup: **Kas Utama** (`main`, akun 1101), **Bank Utama / Rekening Bank** (`transfer`, akun 1102 atau rekening bank aktif COA lewat `bank_account_code`), atau **Laci shift** (`cash`, akun 1101 dengan uang laci shift yang sedang terbuka).
- **Admin**: pembayaran langsung tercatat (`posted`) dan mengurangi utang. Kas Utama dan Bank Utama memakai saldo toko, tidak memerlukan shift, dan tidak mengubah hitungan laci. Laci shift memakai uang laci shift admin sendiri (harus terbuka dan cukup) dan mencatat uang keluar di laci itu.
- **Kasir**: dicatat sebagai **pengajuan** (`proposed`) tanpa jurnal dan baru mengurangi utang setelah admin menyetujui. Sumber Laci shift memakai shift toko yang aktif saat pengajuan; Kas Utama dan Bank Utama tidak perlu shift. Pengajuan laci pada shift yang sudah ditutup tidak boleh dipindah diam-diam ke shift berikutnya; ajukan ulang.
- Saldo akun 1101 atau rekening bank terpilih tidak boleh menjadi negatif; jurnal debit 2101, kredit 1101 atau rekening bank terpilih.
- Nominal tidak boleh melebihi sisa tagihan. Kas Utama hanya untuk tagihan supplier, bukan pelunasan piutang pelanggan (piutang tetap Tunai/Transfer).

### 8.5 Penjaga entri dobel pembayaran

- Form bayar membawa id pembayaran terakhir (bukan ditolak) saat halaman dibuat (`seen_payment_id`). Bila sebelum disimpan sudah ada pembayaran baru pada tagihan itu (mis. kasir dan admin mencatat uang yang sama), server menolak dengan pesan berisi nominal, sumber dana, nama pencatat, dan jam, lalu tombol simpan berubah menjadi **Ya, tetap catat**. Simpan berikutnya dikirim dengan `confirm_duplicate` dan dicatat seperti biasa.
- Pemeriksaan berjalan di dalam kunci baris tagihan, jadi berlaku juga untuk simpan yang benar-benar bersamaan. Tagihan lunas tetap ditolak "Tagihan sudah lunas".
- Pemanggil tanpa `seen_payment_id` tidak diperiksa. Klik dobel dengan kunci transaksi yang sama tetap dianggap satu pembayaran.

### 8.4 Status pembayaran

| Status | Arti |
|---|---|
| Diajukan (`proposed`) | Pembayaran utang ke supplier oleh kasir, menunggu persetujuan admin |
| Menunggu (`pending`) | Transfer dari pelanggan, menunggu admin mencocokkan dengan mutasi bank |
| Tercatat (`posted`) | Sudah masuk pembukuan dan mengurangi sisa tagihan |
| Ditolak (`rejected`) | Dibatalkan admin dengan alasan; tidak pernah masuk pembukuan |

### 8.6 Penelusuran seluruh riwayat tagihan

- Filter mencari nomor tagihan atau nama pelanggan/supplier. Panel Filter bersama memuat status **Belum lunas / Lunas / Semua status**, rentang tanggal pembuatan tagihan, dan jumlah baris **10 / 25 / 50**. Awalnya seluruh tanggal; tagihan lama tetap dapat ditemukan. Arah tab, status, pencarian, dan tanggal diterapkan bersamaan, bukan saling menggantikan.
- Pagination dilakukan di server tanpa batas daftar 100 tagihan terbaru. Urutan ID terbaru dahulu; jumlah hasil dan navigasi halaman mencakup semua catatan yang sesuai filter. Parameter Tagihan memakai awalan `invoice_`, terpisah dari tanggal riwayat penjualan dan laporan.
- **Detail** tersedia juga untuk tagihan lunas. Isinya nomor, nama, total, terbayar, sisa, jatuh tempo, asal penjualan/penerimaan atau saldo awal/migrasi, serta seluruh riwayat pembayaran tagihan tersebut. Riwayat memuat nominal, waktu, sumber dana/metode, referensi, pencatat, pemberi persetujuan bila ada, dan alasan/pelaku penolakan bila ada. Pembayaran menunggu/diajukan/ditolak tetap terlihat, tetapi tidak dihitung sebagai pelunasan. Tagihan lunas tidak menawarkan pembayaran baru.
- Admin dan kasir yang memiliki peran dapat membaca kedua tab seperti cakupan daftar Tagihan sebelumnya. Hak mencatat, mengajukan, menyetujui, dan menolak pembayaran tidak berubah. Daftar **Persetujuan pembayaran** tetap terpisah dari riwayat setiap tagihan.
- `GET /v2/invoices` mengembalikan fragmen HTML, filter terapan, dan jumlah hasil, dengan autentikasi, pemeriksaan peran, validasi filter, serta `private, no-store`. Endpoint hanya membaca; tidak menambah atau mengubah uang, stok, tagihan, maupun jurnal.
- Pindah tab, Terapkan, Reset, dan pagination mengganti hasil di tempat tanpa memuat ulang dokumen/keranjang. Reset mempertahankan arah tab, kembali ke Belum lunas, seluruh tanggal, 10 baris, dan pencarian kosong. Tombol ringkasan piutang/utang membuka arah terkait dengan status Belum lunas.
- Pembaruan setelah simpan memakai filter/halaman terapan tanpa menimpa draft isian. Pembaruan latar menunggu popup Detail/Bayar ditutup dan tidak membatalkan permintaan tab/filter pengguna yang masih berjalan. Form pada baris hasil baru dipasang ulang beserta kunci transaksi dan penjaga entri dobel.
- Kegagalan jaringan/sesi/filter mempertahankan hasil lama dan isian; pesan galat tidak membocorkan rincian internal server. Respons permintaan lama tidak boleh menimpa hasil yang lebih baru.

### 8.7 Foto bukti transfer pelanggan

- Pembayaran awal **Transfer** di Kasir, DP Kredit dengan metode Transfer dan nominal positif, serta cicilan/pelunasan Transfer di Detail/Tagihan wajib menyertakan satu foto. Tunai tidak meminta foto. Alur pembayaran supplier tidak diubah oleh fitur ini.
- Format JPG/JPEG, PNG atau WebP; isi berkas diperiksa sebagai gambar, maksimal5MB dan dimensi maksimal8000×8000. SVG dan berkas yang hanya diganti namanya menjadi gambar ditolak. Foto dikirim sebagai multipart bersama payload pembayaran/penjualan, bukan melalui transaksi pembayaran kedua.
- Setiap pembayaran memiliki paling banyak satu lampiran di `v2_payment_proofs`; berkas disimpan dengan nama acak di disk lokal **privat** (`storage/app/private/transfer-proofs`), bukan disk public. Path, nama asli dan isi gambar tidak ditampilkan pada JSON atau log pekerjaan. File privat dan database harus dicadangkan bersama.
- Penyimpanan pembayaran dan metadata lampiran dibungkus satu transaksi database. Unggahan gagal membatalkan pembayaran/penjualan; berkas baru dibersihkan bila transaksi gagal setelah penulisan. Replay kunci transaksi yang sama dengan foto sama mengembalikan pembayaran lama tanpa file/pembayaran ganda. Foto berbeda pada pembayaran yang sudah memiliki bukti ditolak, tidak menimpa bukti audit.
- Foto tersedia melalui **Lihat bukti transfer** pada riwayat pembayaran Detail nota, Detail Tagihan, dan daftar Persetujuan pembayaran. `GET /v2/payments/{id}/proof` memeriksa admin/kasir aktif sesuai cakupan transaksi bersama, memakai private/no-store, tipe gambar, nosniff, dan pembatasan frekuensi. Tidak ada URL publik file. Guest, akun nonaktif dan akun tanpa peran ditolak; lampiran tidak ditemukan memberi404.
- Foto tidak otomatis membuktikan dana diterima. Admin tetap harus mencocokkan mutasi bank sebelum verifikasi. Catatan transfer lama tanpa foto tetap bisa dibaca dan diperiksa sesuai alur sebelumnya; ditandai bahwa foto belum tersedia, tidak dihapus/dikonversi. Foto tidak mengubah aturan saldo, stok, jurnal, atau pengajuan supplier.
- Fitur memerlukan migrasi tambahan `2026_11_21_132025_create_v2_payment_proofs_table.php`; hanya membuat tabel metadata kosong tanpa mengubah pembayaran/jurnal historis. Tidak menjalankan ulang migrasi lama. Menghapus tabel/berkas berarti menghilangkan bukti, sehingga rollback berisi data bukan operasi pemeliharaan rutin.

---

## 9. Persetujuan dan penolakan pembayaran

Kode: `Operations::approvePayment`, `rejectPayment`. Menu: **Pengawasan › Persetujuan pembayaran**. Hanya admin.

Daftar memiliki halaman khusus: admin melihat judul **Verifikasi pembayaran**, sedangkan kasir membuka **Operasional › Status pembayaran** untuk memantau pembayaran miliknya sendiri. Tabel tidak ditampilkan lagi sebagai blok bersama di Kasir/Kas harian/menu lain; menggunakan animasi masuk dan jarak antarkartu workspace. Kasir tidak mendapat tombol persetujuan/penolakan, dan kolom Pengaju/Aksi disembunyikan agar daftar lebih ringkas. Data, filter status/pencarian, bukti transfer serta proses pembukuan existing tetap sama. Tunai pelanggan langsung tercatat; transfer pelanggan dan pengajuan pembayaran supplier dapat menunggu pemeriksaan.

### 9.1 Verifikasi transfer pelanggan

- Hanya untuk pembayaran transfer berstatus menunggu.
- Nominal tidak boleh melebihi sisa tagihan saat ini.
- Jurnal: debit 1102 Bank, kredit 1103 Piutang. Sisa tagihan dan status nota diperbarui.

### 9.2 Persetujuan pembayaran ke supplier

- Pengajuan datang dari kasir. Pengajuan lama yang dibuat admin sebelum aturan bayar langsung juga bisa disetujui admin (larangan menyetujui pengajuan sendiri dihapus karena toko hanya punya satu admin).
- Pembayaran dengan sumber **Laci shift** (`cash`):
  - shift toko saat pengajuan harus masih terbuka (shift pribadi historis tetap memeriksa pemohon);
  - uang di laci bersama harus cukup;
  - uang keluar dicatat di shift pengajuan yang sama, identitas pengaju tetap tercatat.
- Pembayaran dengan sumber **Kas Utama** (`main`) atau **Bank Utama** (`transfer`) tidak memakai shift dan tidak mengubah hitungan laci; yang berkurang hanya saldo akun 1101/1102 di buku besar.
- Saldo akun yang dipakai (Kas atau Bank) di buku besar tidak boleh menjadi negatif.
- Jurnal: debit 2101 Utang usaha, kredit 1101 Kas Utama atau 1102 Bank Utama.

### 9.3 Penolakan

- Untuk pembayaran berstatus menunggu atau diajukan.
- Alasan wajib 5–1000 karakter; nama admin dan waktu tersimpan.
- Tidak ada jurnal dan sisa tagihan tidak berubah.

Menekan setuju atau tolak dua kali tidak membuat catatan ganda.

---

## 10. Pengiriman, sopir, uang muka, dan biaya ritase

Kode: `DeliveryTrips`, `SupplyTrips::trip`, `TransportAssignment`. Menu: **Operasional › Pengiriman**.

### 10.1 Siklus surat jalan

```
Siap dikirim (prepared) ──Kirim──► Sedang dikirim (sent) ──Terima──► Diterima (received)
```

- Surat jalan dibuat otomatis saat nota disimpan dengan cara penyerahan "Dikirim". Tujuan kirim wajib diisi.
- **Kirim**: pilih mobil aktif dan sopir aktif dari master. Waktu dan pengirim dicatat.
- **Terima**: hanya untuk surat jalan yang sudah dikirim. Waktu dan penerima dicatat.
- Admin bisa mengurus semua surat jalan; kasir hanya surat jalan dari penjualannya sendiri.
- Mengirim atau menerima dua kali dengan data yang sama tidak berpengaruh.
- **Pengiriman tidak mengubah stok** (stok sudah berkurang saat nota disimpan) dan tidak membuat jurnal.
- Ongkir yang ditagih ke pelanggan adalah pendapatan (4102). Biaya perjalanan yang dikeluarkan toko dicatat terpisah sebagai beban (6101), dan keduanya tidak saling memotong.

### 10.2 Uang muka sopir (admin)

Dipakai bila sopir dibekali uang sebelum berangkat.

- Isi mobil, sopir, tujuan (atau tautkan ke surat jalan), tanggal, jumlah, dan metode (tunai/transfer).
- Tunai: shift toko harus terbuka dan laci bersama harus cukup. Uang keluar dicatat pada shift toko aktif, pelaku tetap admin.
- Saldo Kas/Bank di buku besar tidak boleh menjadi negatif.
- Jurnal: debit 1191 Uang muka sopir, kredit 1101 Kas atau 1102 Bank.
- Status: **Terbuka** sampai diselesaikan.

### 10.3 Penyelesaian uang muka (admin)

Setelah sopir pulang:

- Isi biaya BBM dan uang jalan yang sebenarnya.
- Biaya aktual **tidak boleh melebihi** uang muka (lihat bagian 21).
- Bila ada sisa, admin wajib mengonfirmasi sisa sudah diterima dan memilih masuk ke tunai atau transfer. Sisa tunai masuk ke laci toko bersama, jadi shift toko harus terbuka.
- Jurnal: debit 6101 Biaya perjalanan (biaya aktual), debit Kas/Bank (sisa), kredit 1191 Uang muka sopir (seluruh uang muka).

Contoh: uang muka tunai 300.000. BBM 180.000 dan uang jalan 80.000 (total 260.000). Sisa 40.000 dikembalikan tunai.

| Akun | Debit | Kredit |
|---|---:|---:|
| 6101 Biaya perjalanan | 260.000 | |
| 1101 Kas | 40.000 | |
| 1191 Uang muka sopir | | 300.000 |

### 10.4 Biaya ritase langsung (admin)

Dipakai bila biaya perjalanan dibayar tanpa uang muka.

- Isi mobil, sopir, tanggal, tujuan (atau tautkan surat jalan), BBM, uang jalan, dan metode bayar. Total harus lebih dari nol.
- Tunai: shift toko harus terbuka dan laci bersama cukup; uang keluar dicatat di shift toko aktif.
- Jurnal: debit 6101 Biaya perjalanan, kredit Kas/Bank.

### 10.5 Aturan sopir dan mobil

- Sopir berasal dari master sopir (bagian 12.4). Sopir internal terhubung ke data karyawan aktif; sopir luar tidak boleh terhubung ke karyawan.
- Mobil harus aktif.
- Bila surat jalan sudah dikirim dengan pasangan sopir dan mobil tertentu, uang muka atau ritase yang ditautkan ke surat jalan itu harus memakai pasangan yang sama.
- Surat jalan lama yang hanya punya nama sopir (tanpa data master) tidak ditebak dari nama. Pemeriksaan manual diperlukan.

---

## 11. Penerimaan barang dari supplier

Kode: `ReceiptDrafts::save/confirm/cancel`, `SupplyTrips::receive`. Menu: **Operasional › Penerimaan barang** (admin).

### 11.1 Draf penerimaan

- Alur baru: **Draf → Dikonfirmasi (terkunci)**; draf juga bisa menjadi **Dibatalkan**. Hanya admin yang mengelola draf dan mengonfirmasi.
- Pilih barang dari Perlu restok untuk mengisi supplier otomatis bila hanya satu; bila beberapa pilih salah satu, dan bila belum ada supplier dapat dipilih/dibuat manual melalui konfirmasi (bagian11.4). Supplier aktif juga tetap dapat dipilih langsung. Lengkapi tanggal penerimaan, No. nota supplier (opsional, maks 64 karakter), dan 1–100 baris barang (produk, jumlah, harga beli per satuan). Satu dokumen untuk satu supplier.
- Barang dicari di server (nama/SKU) dan harus aktif serta tertaut ke supplier. Satu produk hanya boleh satu baris; jumlah harus positif, harga tidak negatif, total harus positif.
- **Periksa penerimaan** menampilkan ringkasan supplier, tanggal, barang, jumlah × harga, perkiraan HPP, dan total. Harga di luar setengah–dua kali HPP saat ini ditandai kuning. Tombol **Simpan draf penerimaan** menyimpan draf, bukan memposting transaksi.
- Draf disimpan di `v2_receipt_drafts`, terpisah dari penerimaan final. Draf **tidak mengubah stok, HPP, utang, kas, bank, jurnal, atau ringkasan penerimaan final**.
- Daftar **Draf penerimaan** menyediakan **Ubah draf**, **Konfirmasi penerimaan**, dan **Batalkan draf** (alasan wajib). Draf dibatalkan tidak dihapus dan tidak mengubah pembukuan.
- Versi draf diperiksa saat ubah/konfirmasi/batal. Bila admin lain sudah mengubahnya, muat ulang data dan periksa lagi; jangan menimpa atau mengonfirmasi isi lama.

### 11.2 Konfirmasi dan pembukuan

1. Admin membuka konfirmasi dari daftar draf, memeriksa supplier, tanggal, tiap barang dan harga, lalu mencentang pernyataan pemeriksaan dan menekan **Konfirmasi dan kunci**.
2. Server membaca isi draf yang tersimpan (bukan rincian kiriman konfirmasi), memeriksa versi serta supplier/produk/relasi yang masih aktif. Tanggal tidak boleh mundur melewati mutasi stok berikutnya.
3. Penjaga No. nota supplier diperiksa kembali saat konfirmasi: nomor yang sama untuk supplier yang sama (tanpa beda huruf dan spasi tepi) memerlukan konfirmasi khusus **Ya, tetap konfirmasi**. Penerimaan yang sudah dibatalkan sebelum aturan baru tidak dihitung.
4. Dalam satu transaksi, server membuat `RCV-…` beserta rincian, menambah stok, memperbarui HPP rata-rata tertimbang, membuat tagihan `AP-…`, dan memposting jurnal debit 1301 Persediaan / kredit 2101 Utang usaha.
   HPP baru = (stok lama × HPP lama + jumlah masuk × harga beli) ÷ (stok lama + jumlah masuk).
5. Draf ditandai `confirmed`, ditautkan ke penerimaan final, serta disimpan siapa dan kapan yang mengonfirmasi. Pengulangan konfirmasi yang sama tidak menambah stok atau jurnal lagi. Kegagalan posting menggagalkan seluruh transaksi dan draf tetap belum dikonfirmasi.
6. Pembayaran dilakukan terpisah melalui Tagihan (bagian 8.3). Draf belum punya tagihan, sehingga belum bisa dibayar.

Riwayat penerimaan menampilkan 50 dokumen final terakhir dan total nilai per halaman. Simpan/perbarui/konfirmasi/batalkan draf memperbarui data di tempat tanpa muat ulang penuh.

### 11.3 Kunci final dan data lama

**Ongkir pembelian opsional:** draf menyimpan ongkir, metode supplier/kas/bank dan akun beban aktif pilihan admin. Ongkir0 mempertahankan perilaku lama; draf belum memposting stok, beban atau utang. Konfirmasi dengan ongkir ikut supplier memposting debit1301 sebesar barang, debit akun beban sebesar ongkir dan kredit2101 sebesar barang+ongkir. Jika dibayar kas/bank, utang tetap nilai barang, beban dikreditkan ke1101/1102 pada jurnal penerimaan yang sama. Kas wajib memakai kas harian aktif pada tanggal penerimaan dan saldo laci/buku besar cukup; bank wajib saldo buku besar cukup. Pengeluaran kas direkam sebagai mutasi `v2_receipt_freight` untuk rekap laci. Akses tetap admin dengan pola pengeluaran langsung seperti biaya perjalanan, tidak menambahkan tahap persetujuan baru atau foto transfer pelanggan. Semua posting atomik dan idempoten.

HPP rata-rata tertimbang dan nilai barang/persediaan tetap tanpa ongkir. `total_amount` penerimaan/draf tetap nilai barang; nominal invoice menambahkan ongkir hanya jika metodenya supplier. Form review, konfirmasi draf dan riwayat menunjukkan ongkir terpisah. Pembayaran utang kemudian tidak membuat beban ongkir lagi.

Memerlukan migrasi aditif `2026_11_23_173947_add_freight_to_v2_receipts.php` pada draf/final, dengan default0 bagi data lama. **Migrasi database toko belum dijalankan**; kontrol ongkir nonaktif dan server menolak ongkir positif sebelum schema siap. Admin perlu memberi izin migrasi terpisah. Tidak memilih/membuat akun beban otomatis dan tidak menghitung ulang HPP/jurnal historis.

- Penerimaan yang sudah dikonfirmasi **tidak boleh diubah atau dibatalkan biasa**, termasuk bila belum dibayar dan stoknya belum dipakai. Tombol pembatalan final dihapus; endpoint pembatalan lama tetap menolak di server agar tidak bisa dilewati lewat kiriman manual.
- Penerimaan lama yang sudah menambah stok/jurnal tetap dianggap **final**, bukan diubah menjadi draf. Tidak ada penghitungan ulang stok atau jurnal historis dalam migrasi ini.
- Riwayat pembatalan yang sudah terjadi sebelum aturan baru tetap dipertahankan, lengkap dengan waktu/admin/alasan dan nilai dicoret; tidak ikut total aktif.
- Salah input sebelum konfirmasi: **Ubah draf**. Setelah final, kesalahan memerlukan dokumen koreksi/retur, bukan membuka kunci atau menghapus penerimaan. **Alur koreksi/retur penerimaan final belum tersedia pada paket ini.**

### 11.4 Daftar Perlu restok

- Dialog **Catat penerimaan** menampilkan **Info penerimaan**, **Rekomendasi stok**, **Barang diterima**, lalu **Ongkir** jika tersedia. Pada dialog sempit, barang terpilih tampil sebagai kartu berlabel dengan subtotal per barang; dialog lebar memakai baris sejajar. Rekomendasi tetap mencakup seluruh toko dan hanya produk aktif: **habis** bila stok≤0, **menipis** bila stok>0 dan stok≤minimum. Habis didahulukan; minimum0 tidak membuat stok positif dianggap menipis.
- Tersedia pencarian nama/SKU, filter Perlu restok/Habis/Menipis dengan jumlah, dan halaman20 barang. Jumlah mengikuti pencarian dan cakupan supplier, bukan hanya baris halaman aktif. Halaman yang sudah tidak tersedia dikembalikan ke halaman terakhir. Daftar memuat stok, satuan, minimum dan supplier aktif terkait; produk tanpa relasi tetap terlihat dengan label.
- Checkbox **Hanya supplier terpilih** dihapus; daftar tetap seluruh toko. Klik barang dengan satu supplier aktif langsung mengisi supplier dan menambahkan barang. Jika supplier form sudah terhubung ke barang, gunakan supplier tersebut. Bila ada beberapa supplier dan belum ada yang cocok, tampilkan pilihan terkait dan tombol **Gunakan supplier**, tidak memilih supplier utama diam-diam.
- Barang tanpa supplier aktif menyediakan **Isi supplier**: pilih yang sudah terdaftar atau **Tambah supplier baru** (nama wajib, catatan opsional). **Simpan dan hubungkan ke barang** adalah konfirmasi penulisan master/relasi. Memilih opsi atau menekan Batal sebelum konfirmasi tidak menulis. Konfirmasi ini langsung menyimpan supplier/relasinya walaupun draf penerimaan belum disimpan; membatalkan penerimaan sesudahnya tidak menghapus master tersebut.
- Bila penerimaan sudah memiliki barang/jumlah/HPP, supplier lain tidak boleh mengganti supplier atau menghapus isian secara otomatis maupun lewat pemilih manual. Simpan/selesaikan draf supplier saat ini terlebih dahulu. Bila form kosong, pengisian supplier dari barang tidak menghapus tanggal atau nomor nota.
- **Tambahkan** memakai baris kosong atau membuat baris baru hingga batas 100, tanpa perlu klik **Tambah barang manual**. Fokus dan posisi gulir tetap di daftar rekomendasi agar beberapa barang bisa ditambahkan berurutan. Produk yang sudah masuk ditandai **Ditambahkan** dan tidak bisa masuk dua kali. Baris parsial tidak ditimpa. Jumlah wajib diisi sesuai penerimaan sebenarnya. HPP positif produk saat ini menjadi harga bawaan, dapat diedit sesuai faktur. Harga manual/draf tidak ditimpa, termasuk 0; saran otomatis yang belum diedit boleh mengikuti produk baru. Produk tanpa HPP positif tetap memerlukan isian harga. Filter, pencarian, dan pagination tidak menghapus isian.
- `GET /v2/receipts/restock-products` hanya untuk admin aktif, autentikasi wajib, respons private/no-store. Parameter: `q` maksimum100 karakter, `status=critical|out|low`, opsional `supplier_id` aktif, `page`1–100000 (dijepit ke halaman terakhir). Relasi supplier dibaca batch hanya untuk barang pada halaman itu, tanpa memperluas cakupan endpoint pemilih barang supplier yang sudah ada.
- `POST /v2/receipts/product-supplier` hanya admin aktif, CSRF dan throttle30/menit. Wajib produk aktif, konfirmasi, kunci idempotensi, serta salah satu supplier aktif existing atau nama supplier baru. Kode supplier baru mengikuti Master Supplier (SUP-…); nama existing/arsip tidak dibuat ulang atau diaktifkan otomatis. Pembuatan master, tambah relasi dan hasil idempotensi berada dalam satu transaksi; relasi lain/supplier utama dipertahankan, relasi pertama menjadi utama. Gagal tulis membatalkan seluruh operasi dan tidak membocorkan detail SQL. UI memblokir double-click, mempertahankan input saat gagal, dan memakai kunci sama untuk mengulang muatan sama.
- Pencarian barang biasa tetap dibatasi supplier terpilih, sekarang juga menampilkan minimum dan status stok. Memuat panel/menambah baris belum membuat transaksi: draf dan konfirmasi tetap mengikuti11.1–11.2; tidak mengubah stok, HPP atau utang dari daftar ini.

---

## 12. Master data

Halaman **Karyawan & sopir** menampilkan satu daftar gabungan; pembungkus dialog karyawan/sopir tidak lagi dirender sebagai kartu kosong. Dialog tambah/edit/impor tetap tersedia. **Jenis mobil** pada tambah/ubah berupa dropdown Pick-up, Truk engkel, Truk double dan Dump truck; nilai jenis lama di luar daftar tetap muncul sebagai opsi tersimpan agar tidak berubah saat menyimpan edit lain.

Kode: `MasterData`, `MasterRelations`, `FormCatalog`. Menu: **Master data** (admin).

Aturan umum:
- **Tidak ada penghapusan.** Data hanya bisa **diarsipkan**. Data arsip tidak muncul untuk transaksi baru, tetapi riwayat transaksinya tetap utuh. Arsip bisa diubah, tetapi tidak aktif kembali dengan sendirinya. Gunakan **Aktifkan kembali** pada aksi baris untuk memulihkannya; status dan riwayat lama tetap dipertahankan.
- Kode/SKU/nomor polisi harus unik.
- Kolom yang tidak dikirim saat mengubah data dipertahankan, tidak direset ke nol.
- Form tambah mengisi kode berikutnya secara otomatis (`PRD-0001`, `PLG-0001`, `SUP-0001`, `KRY-0001`, `SPR-0001`), dan kode itu boleh diganti.

### 12.1 Produk

| Kolom | Aturan |
|---|---|
| SKU | Wajib, unik, maksimal 64 |
| Nama | Wajib |
| Satuan, Kategori | Wajib, dipilih dari master Satuan & kategori |
| Harga beli (HPP) | ≥ 0. **Terkunci** bila produk sudah punya stok atau riwayat mutasi; setelah itu HPP hanya berubah lewat penerimaan barang atau saldo awal |
| Harga jual | ≥ 0 |
| Harga kontraktor | Opsional. Kosong = sama dengan harga jual. Bila diisi, harus lebih dari 0 |
| Stok minimum | ≥ 0. Produk ditandai **menipis** bila stok ≤ stok minimum, dan **habis** bila stok ≤ 0 |
| Supplier | Satu atau lebih. Supplier pertama menjadi supplier utama. Wajib ditautkan agar produk bisa diterima dari supplier itu |

Stok **tidak bisa** diubah lewat form produk; stok hanya berubah lewat penjualan, penerimaan, dan saldo awal.

Daftar master produk menampilkan **SKU, Nama, Kategori, HPP / modal, Harga jual, Stok / satuan, Status, Aksi**. HPP/modal adalah harga per satuan, ditampilkan hingga4desimal yang tersimpan; stok dan satuan digabung dalam satu kolom. Angka memakai pemisah Indonesia tanpa konversi float.

Aksi **Detail** membuka rincian baca-saja: identitas, kategori, status, HPP, harga jual/kontraktor, stok/minimum, kondisi stok, dan seluruh supplier terkait (utama/diarsipkan). Supplier dipindahkan dari kolom daftar ke detail agar tabel ringkas, bukan dihapus dari produk. Ubah/Arsipkan tetap tersedia dan mengikuti penjaga lama; detail tidak mengubah stok, HPP, harga atau transaksi. Data master/HPP tetap hanya diberikan kepada admin, bukan kasir.

### 12.2 Pelanggan

Kode, nama, catatan (alamat, telepon), dan **harga di kasir** (eceran atau kontraktor).

Pelanggan juga bisa dibuat langsung dari kasir (kode `CUS-0001` dan seterusnya, harga eceran). Pelanggan arsip tidak bisa dipilih di kasir.

Daftar master menampilkan **Frekuensi belanja** (jumlah nota penjualan yang terhubung ke ID pelanggan, bukan jumlah cicilan) dan **Piutang pelanggan** (jumlah sisa positif tagihan receivable yang belum dibatalkan, termasuk saldo awal). Pembayaran menunggu/ditolak tidak mengurangi piutang; pelanggan tanpa transaksi bernilai0. Pembelian tanpa pelanggan terpilih tidak ditebak berdasarkan nama.

### 12.3 Supplier

Kode, nama, catatan. Menekan nama supplier menampilkan produk yang dipasoknya dan riwayat transaksinya. Kolom "Utang berjalan" menunjukkan sisa utang ke supplier itu.

Dialog rincian menampilkan strip fakta ringkas (utang berjalan ditonjolkan, lalu sudah dibayar, menunggu verifikasi, dan jumlah produk), **aktivitas tagihan dan pembayaran lebih dulu**, kemudian produk yang dipasok dengan peran Utama/Alternatif. Rentang aktivitas memakai dua tanggal, tombol Tampilkan, dan tautan cetak semua; tabel memakai badge status dan header lengket. Blok riwayat mati tidak lagi dirender. Tidak ada perubahan perhitungan/utang.

Detail supplier khusus admin memuat aktivitas tagihan dan pembayaran berdasarkan **tanggal pencatatan**, default30hari terakhir, maksimal366hari sampai hari ini. Daftar20aktivitas per halaman, tanpa batas8aktivitas lama. Ringkasan membedakan tagihan, pembayaran terverifikasi, pengajuan menunggu dalam rentang, dan **utang berjalan saat ini** seluruh tanggal. Tagihan dibatalkan tidak menambah utang/ringkasan tagihan; aktivitasnya tetap dapat ditelusuri.

**Cetak transaksi** mencetak satu aktivitas milik supplier tersebut; **Cetak semua dalam rentang** mencakup semua halaman pada rentang yang sudah diterapkan, bukan draft tanggal yang belum ditampilkan. Halaman cetak menyediakan Cetak/Simpan PDF, nomor tagihan, nominal, status, metode/referensi pembayaran. Ini rincian aktivitas, bukan bukti pembayaran atau saldo penutupan historis. Endpoint GET `suppliers/{id}/activity` dan `suppliers/{id}/statement` memeriksa admin aktif, lingkup supplier/entri, tanggal, dan memakai private/no-store. Tidak memposting stok, utang, pembayaran atau jurnal baru.

### 12.4 Karyawan dan sopir

- Karyawan: kode, nama, catatan.
- Sopir: jenis **karyawan toko** (terhubung ke data karyawan, nama mengikuti karyawan) atau **sopir luar**. Satu karyawan hanya bisa terdaftar sekali sebagai sopir.
- Tidak ada hapus permanen. **Arsipkan** menyembunyikan karyawan/sopir dari pilihan baru (misalnya penugasan surat jalan), tetapi riwayat pengiriman tetap utuh. Data arsip masih dapat diubah dan tidak aktif kembali dengan sendirinya; gunakan **Aktifkan kembali** pada aksi baris untuk memulihkannya.

### 12.5 Mobil

Nomor polisi (huruf besar dan spasi dirapikan otomatis) dan jenis mobil. Mengarsipkan mobil mengubah statusnya menjadi tidak aktif. Menekan nomor polisi menampilkan riwayat pemakaiannya.

### 12.6 Satuan dan kategori

Daftar pilihan untuk produk. Mengarsipkan tidak mengubah produk yang sudah memakainya.

### 12.7 Impor Excel

Untuk produk, pelanggan, supplier, karyawan, dan mobil.

1. Unduh template.
2. Isi di Excel.
3. Unggah file `.xlsx` (maksimal 2 MB). Sistem membuat pratinjau dan memeriksa semua baris.
4. Simpan. Semua baris disimpan sekaligus; bila satu baris gagal, tidak ada yang tersimpan.

Ditolak: rumus Excel, kolom stok/saldo, dan data yang sudah terdaftar. Baris disimpan lewat aturan yang sama dengan form manual. Menyimpan pratinjau yang sama dua kali tidak menggandakan data.

---

## 13. Saldo awal pembukuan

Kode: `OpeningBalances`. Menu: **Kas & laporan**, kartu "Saldo awal pembukuan". Hanya admin.

### 13.1 Syarat

Saldo awal **hanya sekali** seumur instalasi dan hanya bila sistem masih kosong:
- belum pernah ada saldo awal V2;
- belum ada jurnal apa pun;
- semua stok produk masih nol;
- belum ada tagihan apa pun.

Setelah diposting, saldo awal **tidak bisa diubah**. Selalu periksa pratinjau sebelum posting.

### 13.2 Isi

| Bagian | Isi |
|---|---|
| Tanggal, referensi | Tanggal pembukuan dimulai |
| Kas awal, Bank awal | Boleh 0 |
| Persediaan (opsional) | Per produk: jumlah dan HPP. Stok dan HPP produk langsung diisi |
| Piutang awal (opsional) | Per pelanggan: nomor faktur, jumlah, jatuh tempo |
| Utang awal (opsional) | Per supplier: nomor faktur, jumlah, jatuh tempo |

Saat ini persediaan, piutang, dan utang diisi dalam format JSON di form (lihat bagian 21).

### 13.3 Jurnal saldo awal

| Akun | Posisi |
|---|---|
| 1101 Kas, 1102 Bank, 1301 Persediaan, 1103 Piutang | Debit |
| 2101 Utang usaha | Kredit |
| 3101 Modal awal | Selisih penyeimbang: kredit bila aset lebih besar dari utang, debit bila sebaliknya |

Piutang dan utang awal menjadi tagihan biasa yang bisa ditagih atau dibayar lewat menu Tagihan.

Catatan: saat V2 aktif, halaman Jurnal umum tidak lagi menampilkan form saldo awal lama. Halaman itu menunjukkan status dari saldo awal V2 dan mengarahkan ke **Kas harian → Saldo awal pembukuan**. Form lama tetap dipakai hanya bila V2 tidak aktif.

---

## 14. Pembukuan otomatis (jurnal)

Kode: `Ledger::post`.

### 14.1 Bagan akun yang dipakai V2

| Kode | Nama (versi `v2:prepare`) | Jenis | Saldo normal | Dipakai untuk |
|---|---|---|---|---|
| 1101 | Kas Utama (Tunai) | Aset | Debit | Uang tunai toko di luar bank: kas utama/brankas dan semua laci shift. Bank 1102 terpisah. |
| 1102 | Bank | Aset | Debit | Rekening bank |
| 1103 | Piutang usaha | Aset | Debit | Tagihan ke pelanggan |
| 1191 | Uang muka sopir | Aset | Debit | Uang muka sopir yang belum diselesaikan |
| 1301 | Persediaan | Aset | Debit | Nilai stok barang (berdasarkan HPP) |
| 2101 | Utang usaha | Kewajiban | Kredit | Tagihan dari supplier |
| 3101 | Modal awal | Ekuitas | Kredit | Penyeimbang saldo awal |
| 4101 | Penjualan | Pendapatan | Kredit | Penjualan barang bersih |
| 4102 | Pendapatan ongkir | Pendapatan | Kredit | Ongkir yang ditagih ke pelanggan |
| 5101 | HPP | Beban | Debit | Modal barang yang terjual |
| 6101 | Biaya perjalanan | Beban | Debit | Uang muka yang terpakai dan biaya ritase |

Akun lain di bagan akun (mis. 5201, 6102–6199, 6201, 7101) berasal dari sistem lama dan tidak dipakai jurnal otomatis V2.

Nama akun di database bisa berbeda dengan tabel ini (lihat bagian 21); kodenya yang menentukan.

### 14.2 Peta jurnal otomatis

| Kejadian | Debit | Kredit | Jenis referensi |
|---|---|---|---|
| Penjualan (semua metode) | 1103 total; 5101 HPP | 4101 barang bersih; 4102 ongkir; 1301 HPP | `v2_sale` |
| Penjualan tunai / DP tunai / pelunasan piutang tunai | 1101 | 1103 | `v2_payment` |
| Verifikasi transfer pelanggan | 1102 | 1103 | `v2_payment` |
| Persetujuan bayar supplier | 2101 | 1101 atau 1102 | `v2_payment` |
| Konfirmasi penerimaan barang (bukan simpan draf) | 1301 | 2101 | `v2_receipt` |
| Pembatalan penerimaan historis (jalur baru dinonaktifkan) | 2101 | 1301 | `v2_receipt_void` |
| Uang muka sopir | 1191 | 1101 atau 1102 | `v2_driver_advance` |
| Penyelesaian uang muka | 6101 biaya; 1101/1102 sisa | 1191 | `v2_driver_advance_settlement` |
| Biaya ritase langsung | 6101 | 1101 atau 1102 | `v2_trip` |
| Saldo awal | 1101, 1102, 1301, 1103 (+3101 bila defisit) | 2101, 3101 | `v2_opening_balance` |

Tidak membuat jurnal: buka/tutup shift, selisih shift, pengajuan bayar supplier, transfer menunggu, penolakan, pengiriman, perubahan master.

### 14.3 Aturan setiap jurnal

- Minimal dua baris. Setiap baris berisi debit **atau** kredit, tidak keduanya.
- Total debit harus sama dengan total kredit, dan tidak boleh nol.
- Semua akun harus **sudah ada dan aktif** di bagan akun. Bila tidak, seluruh transaksi dibatalkan dengan pesan "Akun … belum ada di bagan akun".
- Nama akun di baris jurnal disalin dari bagan akun saat jurnal dibuat.
- Tanggal jurnal = hari ini, kecuali penerimaan barang, uang muka, dan saldo awal yang memakai tanggal isian.
- Jurnal tidak pernah diubah atau dihapus oleh alur V2.

### 14.4 Mengelola bagan akun (admin)

- Admin dapat menambah akun secara manual, mengubah nama/status akun dan mengarsipkan akun non-inti dari menu Bagan akun. Kode harus unik; kelompok kode, klasifikasi dan saldo normal harus sesuai.
- **Multi-rekening bank (`chart_of_accounts.is_bank_account`)**: akun Aset (`asset`) bersaldo normal Debet (`debit`) non-inti dapat ditandai sebagai **Rekening Bank** saat ditambah atau diubah di Bagan Akun. Akun inti `1102` selalu bertanda rekening bank dan tidak dapat dimatikan. Akun rekening bank aktif otomatis muncul pada pilihan rekening tujuan/sumber untuk transaksi Transfer/Bank di **Kasir (POS)**, **Tagihan & Pembayaran**, **Pengeluaran Operasional**, **Uang Muka/Realisasi Sopir**, **Biaya Ritase**, **Ongkir Penerimaan Barang**, **Penyesuaian Saldo**, **Ringkasan Admin**, dan **Laporan Arus Kas**. Jika parameter `bank_account_code` tidak dikirim, sistem otomatis memakai `1102`.
- Form pada mode aktif memakai `POST /v2/chart-of-accounts`, `PUT /v2/chart-of-accounts/{id}` dan `PATCH /v2/chart-of-accounts/{id}/archive`. Ketiganya memeriksa admin di server dan memakai autentikasi, CSRF serta pembatasan frekuensi. Kasir dan akun tanpa peran ditolak.
- Akun inti, termasuk1191,4102 dan6101, tidak boleh diubah nomornya atau dinonaktifkan, sekalipun belum pernah dipakai. Membuat akun inti dalam status tidak aktif juga ditolak.
- Akun yang sudah dipakai jurnal tidak boleh diubah kode, jenis atau saldo normalnya. Nama boleh diperbarui; nama pada jurnal historis tetap snapshot lama. Akun non-inti boleh diarsipkan dan diaktifkan kembali melalui Ubah. Tidak ada penghapusan akun pada jalur V2.
- Simpan/arsip memakai respons JSON dan memperbarui isi Bagan akun tanpa muat ulang dokumen. Filter yang diterapkan tetap, draft filter tidak ditimpa; cache panel lama diinvalidasi. Bila penyimpanan berhasil tetapi pemuatan daftar gagal, pesan menyebut data sudah tersimpan agar tidak disimpan ulang secara buta.
- Galat mempertahankan isian dan membuka kembali tombol. Aksi COA dicatat di log aplikasi dengan ID pelaku, ID/kode akun dan status, bukan password. Menambah/mengubah/arsip akun tidak membuat jurnal atau mengubah nilai saldo.
- Membuka halaman Bagan akun saat V2 aktif tidak otomatis melakukan seeding. Akun wajib dilengkapi secara eksplisit melalui proses persiapan atau mode pemeliharaan akun di bagian4.

### 14.5 Penyesuaian saldo kas dan bank (admin)

Kode: `CashBankAdjustments`. Menu: **Keuangan › Jurnal umum**, panel "Sesuaikan saldo".

- Penyesuaian adalah koreksi **sesudah pembukuan dimulai**, bukan saldo awal kedua. Bila belum ada jurnal, batch saldo awal V2, atau saldo awal lama yang diposting, server menolak dan meminta admin memakai **Kas harian → Saldo awal pembukuan**.
- **Pindah dana (Kas ↔ Bank / Antar-Bank)** (`CashBankTransfers`, `POST /v2/journals/transfers`): admin dapat memindahkan saldo antar `1101` dan seluruh rekening bank aktif (setor tunai ke bank, tarik tunai, atau transfer antar-bank) dengan konfirmasi password login. Saldo sumber dijaga agar tidak negatif, jurnal seimbang (`reference_type = cash_bank_transfer`) dikunci, dan arus kas bersih tetap nol.
- **Koreksi rekening saat verifikasi** (`POST /v2/payments/{id}/approve`): bila terdapat lebih dari satu rekening bank aktif, admin dapat mengoreksi rekening bank tujuan/sumber (`bank_account_code`) langsung saat memverifikasi transfer tanpa harus menolak dan menginput ulang pembayaran.
- **Jatuh tempo penjualan kredit** (`POST /v2/sales`): saat metode `credit`, kasir dapat mengisi tanggal jatuh tempo opsional (`due_date`, minimal hari ini) yang langsung tersimpan di `v2_invoices.due_date` dan dipantau oleh penghitung piutang lewat tempo di Ringkasan Admin.
- **Rincian & filter per rekening bank**: Laporan Harian (`bank.by_account`) merinci transfer terverifikasi dan menunggu per rekening bank; filter sumber dana pada Pengeluaran mendukung `cash`, `transfer` (semua bank), maupun per rekening (`bank:<kode>`).
- Admin mengisi tanggal, saldo tujuan, akun lawan, alasan koreksi, dan **password login sendiri**. Password diperiksa di server dengan hash; password tidak disimpan, tidak dicatat di log, dan tidak dikembalikan ke browser.
- Server menghitung selisih terhadap saldo buku besar saat itu dan hanya memposting selisihnya. Saldo awal yang sudah diposting, jurnal lama, stok, dan tagihan tidak diubah atau dihapus. Jurnal baru bertipe `adjustment` dan harus seimbang.
- Penyesuaian tidak boleh membuat saldo Kas atau Bank menjadi negatif, termasuk bila akun lawannya adalah Kas/Bank. Selisih nol ditolak karena tidak ada yang perlu diposting.
- Form mengirim JSON ke `POST /v2/journals/adjustments`, memakai autentikasi, CSRF, pembatasan frekuensi, dan pemeriksaan admin di server. Pengiriman ganda ditahan di browser; bila berhasil, hanya ringkasan saldo, panel penyesuaian, dan rincian pembukaan yang dimuat ulang tanpa memuat ulang dokumen atau menghentikan daftar jurnal. Galat mempertahankan isian selain password.
- Bila penyimpanan berhasil tetapi daftar gagal dimuat, pesan menyatakan jurnal sudah tersimpan. Cache panel Keuangan diinvalidasi agar angka lama tidak muncul lagi.
- Setiap posting dicatat di log aplikasi dengan ID pelaku, ID jurnal, akun, akun lawan, saldo sebelumnya, saldo tujuan, dan selisih.

---

## 15. Laporan dan rekap keuangan

Semua laporan **hanya membaca** data. Angka dijumlahkan dengan desimal pasti di PHP, tidak dengan SUM SQL atau float.

### 15.1 Laporan harian

Kode: `ReportsDocuments::daily`. Menu: **Kas & laporan › Laporan harian**. Pilih tanggal.

- **Hari bisnis = tanggal shift dibuka.** Penjualan dan kas dihitung dari shift-shift yang dibuka pada tanggal itu.
- Admin melihat semua kasir; kasir hanya miliknya.

| Bagian | Isi |
|---|---|
| Penjualan | Bruto barang, diskon (baris + nota), barang bersih, ongkir, total penjualan, jumlah nota |
| Kas | Modal awal, uang masuk laci (penjualan tunai dan pelunasan tunai saja), uang keluar, **seharusnya di laci**, uang dihitung, selisih |
| Bank | Transfer terverifikasi (menurut tanggal verifikasi) dan transfer menunggu (menurut tanggal pengajuan) |
| Shift | Jumlah shift: terbuka, selesai, perlu diperiksa |

Penjualan kredit tidak dihitung sebagai uang kas masuk. Transfer tidak dihitung sebagai uang laci.

### 15.2 Ringkasan di atas setiap menu Operasional

Kartu angka di atas Tagihan, Pengiriman, Penerimaan, Kas & laporan, Persetujuan, dan setiap menu Master (`MonitorMetrics`). Contoh: total piutang dan jumlah tagihan terbuka, transfer menunggu, surat jalan siap dikirim, stok habis/menipis, selisih kas bulan ini.

### 15.3 Pengawasan › Ringkasan (admin)

Kode: `AdminSummary` + `MonitorMetrics` + `PosDashboard`.

- **Perlu ditindaklanjuti**: transfer menunggu, shift dengan selisih, surat jalan siap dikirim (dan yang belum punya sopir), produk habis, produk menipis. Hanya butir yang jumlahnya lebih dari nol yang tampil.
- **Ringkasan periode** (Hari ini / 7 hari / 30 hari): penjualan dan perbandingan dengan periode sebelumnya, ditambah kas di laci, piutang (dan yang lewat tempo), serta utang supplier. Saldo selalu per hari ini.
- **Grafik penjualan** (per jam untuk Hari ini, per hari untuk 7/30 hari) dan **produk terlaris** (10 besar).
- **Uang**: tunai di laci (shift terbuka), **bank terverifikasi = total saldo seluruh akun Rekening Bank di buku besar** (termasuk rincian per rekening bila lebih dari satu), transfer menunggu, uang muka sopir terbuka, selisih kas bulan ini.
- **Pengiriman**: siap, dikirim, diterima hari ini, uang muka sopir terbuka, mobil aktif.
- **Stok**: 5 produk yang paling perlu perhatian (habis dulu, lalu yang paling jauh di bawah minimum), dan nilai penerimaan bulan ini.

### 15.4 Jurnal umum

Semua jurnal beserta barisnya, bisa disaring. Ada status pembaruan otomatis setiap 10 detik. Admin, baca saja.

### 15.5 Laba rugi

Kode: `AccountingReportService::generateProfitAndLoss`. Pilih rentang tanggal (preset "Semua" bila tanpa tanggal).

| Kelompok | Akun | Rumus per akun |
|---|---|---|
| Pendapatan | 4xxx (dan akun bertipe pendapatan selain 7xxx) | kredit − debit |
| HPP | 5xxx | debit − kredit |
| Beban operasional | 6xxx (dan akun bertipe beban selain 5xxx/8xxx) | debit − kredit |
| Pendapatan lain | 7xxx | kredit − debit |
| Beban lain | 8xxx | debit − kredit |

- Laba kotor = Pendapatan − HPP
- Laba usaha = Laba kotor − Beban operasional
- **Laba bersih** = Laba usaha + Pendapatan lain − Beban lain

### 15.6 Neraca

Kode: `generateBalanceSheet`. Per tanggal tertentu.

- Aset (1xxx) = debit − kredit. Kewajiban (2xxx) dan Ekuitas (3xxx) = kredit − debit.
- Laba bersih sejak awal sampai tanggal itu ditambahkan ke ekuitas sebagai laba ditahan.
- **Seimbang** bila Aset = Kewajiban + Ekuitas + Laba ditahan, **dan** setiap jurnal sampai tanggal itu seimbang.

### 15.7 Arus kas

Kode: `generateCashFlow`. Metode langsung, dari seluruh jurnal yang menyentuh `1101` Kas dan seluruh akun Rekening Bank (`1102` serta akun bertanda `is_bank_account`). Pemindahan dana internal antar-kas/bank menghasilkan arus kas bersih nol.

- Kas awal = saldo Kas + Bank sebelum tanggal mulai. Kas akhir = saldo per tanggal akhir, dirinci per akun.
- Perpindahan Kas ↔ Bank tidak dihitung sebagai arus masuk/keluar.
- Pengelompokan menurut jenis referensi jurnal, atau menurut akun lawannya bila jenisnya tidak dikenal:

| Arus | Kelompok | Asal |
|---|---|---|
| Masuk: pelunasan piutang | Operasi | `v2_payment` (tunai/transfer dari pelanggan), atau lawan 1103 |
| Masuk: penjualan/pendapatan | Operasi | lawan akun 4xxx |
| Masuk: setoran modal | Pendanaan | `v2_opening_balance`, `opening_balance`, atau lawan 3xxx |
| Masuk: pinjaman | Pendanaan | lawan 2xxx |
| Masuk: penjualan aset tetap | Investasi | lawan 12xx/15xx/16xx |
| Masuk: lainnya | Operasi | mis. sisa uang muka sopir yang dikembalikan |
| Keluar: bayar utang supplier | Operasi | `v2_payment` |
| Keluar: uang muka sopir | Operasi | `v2_driver_advance` |
| Keluar: biaya ritase | Operasi | `v2_trip`, `ritase` |
| Keluar: beban operasional | Operasi | `expense` (sistem lama) |
| Keluar: penarikan modal | Pendanaan | saldo awal dengan arah keluar |
- Laporan Arus kas, Neraca, dan Laba rugi bisa diunduh CSV.

---

## 16. Stok barang

| Kejadian | Stok | HPP | Catatan mutasi (`stock_movements.reference_type`) |
|---|---|---|---|
| Saldo awal | Diisi | Diisi | `v2_opening_balance` |
| Penerimaan dari supplier | Bertambah | Rata-rata tertimbang | `v2_receipt` |
| Pembatalan penerimaan historis (bukan draf) | Berkurang | Kembali ke sebelum penerimaan | `v2_receipt_void` |
| Penjualan | Berkurang | Tetap | `v2_sale` |
| Pengiriman | Tetap | Tetap | – |
| Ubah master produk | Tidak bisa | Hanya bila belum ada stok/mutasi | – |

- Jumlah memakai 4 angka desimal (mis. 2,5 m³).
- Stok tidak bisa menjadi negatif lewat V2: penjualan yang melebihi stok ditolak server.
- Setiap mutasi menyimpan perubahan dan saldo sesudahnya, jadi riwayat stok bisa ditelusuri.
- Tidak ada stok opname, retur, atau penyesuaian stok di V2 (lihat bagian 21).

---

## 17. Nomor dokumen dan status

### 17.1 Nomor

| Dokumen | Format | Contoh |
|---|---|---|
| Nota penjualan | `INV-YYYYMMDD-NNNNN` | INV-20261108-00012 |
| Tagihan piutang dari penjualan | `AR-YYYYMMDD-NNNNN` | AR-20261108-00012 |
| Surat jalan | `SJ-YYYYMMDD-NNNNN` | SJ-20261108-00003 |
| Penerimaan barang | `RCV-NNNNNN` | RCV-000045 |
| Tagihan utang dari penerimaan | `AP-NNNNNN` | AP-000045 |
| Pelanggan dari kasir | `CUS-NNNN` | CUS-0007 |
| Kode dari form master | `PRD-`, `PLG-`, `SUP-`, `KRY-`, `SPR-` + 4 angka | PLG-0012 |

Angka `NNNNN` adalah nomor urut internal (ID), jadi tidak kembali ke 1 setiap hari.

### 17.2 Status

| Data | Status |
|---|---|
| Shift | Terbuka `open` → Selesai `closed`, atau Perlu diperiksa `review` → Selesai |
| Nota | Belum lunas `unpaid`, Dibayar sebagian `partial`, Lunas `paid` |
| Tagihan | Terbuka `open`, Lunas `paid` |
| Pembayaran | Diajukan `proposed`, Menunggu `pending`, Tercatat `posted`, Ditolak `rejected` |
| Surat jalan | Siap dikirim `prepared` → Sedang dikirim `sent` → Diterima `received` |
| Uang muka sopir | Terbuka `open` → Selesai `settled` |
| Impor Excel | Pratinjau `preview` → Tersimpan `committed` |
| Master | Aktif / Diarsipkan (`is_active`; mobil: `status` active/inactive) |

---

## 18. Keamanan dan keandalan

- **Login**: maksimal 5 percobaan gagal per username/email yang dinormalisasi dan IP, lalu harus menunggu. Akun nonaktif tidak bisa login.
- **Sesi**: setiap permintaan memeriksa akun masih aktif dan `auth_version` belum berubah. Bila akun dinonaktifkan atau diubah, sesinya langsung berakhir.
- **Peran**: setiap aksi memeriksa peran di server, bukan hanya menyembunyikan tombol.
- **Arsip lama**: setelah V2 aktif, semua halaman lama hanya bisa dibaca admin (`V2Cutover`). Halaman `/demo-setup` dimatikan.
- **Transaksi atomik**: setiap aksi berjalan dalam satu transaksi database dengan penguncian baris (`lockForUpdate`). Bila satu bagian gagal, semuanya dibatalkan.
- **Anti-ganda (idempotensi)**: penjualan, pembayaran, uang muka, penyelesaian, penerimaan, ritase, saldo awal, dan impor memakai kunci unik. Pengulangan dengan isi sama mengembalikan hasil sebelumnya; isi berbeda ditolak.
- **Uang**: tidak pernah memakai float. Semua uang disimpan `DECIMAL(19,2)`, jumlah barang `DECIMAL(19,4)`, HPP `DECIMAL(19,4)`.
- **Tidak ada hapus**: master diarsipkan; transaksi dan jurnal tidak diubah atau dihapus oleh alur V2.

---

## 19. Peta tabel database

| Tabel | Isi |
|---|---|
| `users`, `v2_user_roles`, `v2_account_audits` | Akun login, peran, jejak perubahan akun |
| `v2_shifts`, `v2_shift_movements` | Shift dan gerakan uang laci |
| `v2_sales`, `v2_sale_items` | Nota penjualan dan barangnya |
| `v2_invoices` | Tagihan piutang dan utang |
| `v2_payments` | Pembayaran atas tagihan |
| `v2_deliveries` | Surat jalan |
| `v2_driver_advances`, `v2_driver_advance_settlements` | Uang muka sopir dan penyelesaiannya |
| `v2_trips` | Biaya ritase langsung |
| `v2_parties` | Pelanggan, supplier, karyawan (`kind`) |
| `v2_drivers` | Sopir |
| `v2_product_suppliers` | Tautan produk ke supplier (dan supplier utama) |
| `v2_import_batches` | Pratinjau dan hasil impor Excel |
| `v2_opening_batches` | Saldo awal V2 (satu baris) |
| `v2_requests` | Catatan anti-ganda untuk uang muka, penyelesaian, penerimaan, ritase |
| `products`, `product_options` | Produk, satuan, kategori (bersama) |
| `stock_movements` | Mutasi stok (bersama) |
| `vehicles` | Mobil (bersama) |
| `inventory_receipts`, `inventory_receipt_items` | Penerimaan barang (bersama) |
| `chart_of_accounts`, `journal_entries`, `journal_entry_lines` | Bagan akun dan buku besar (bersama) |
| `opening_balances` | Saldo awal sistem lama (kas dan bank saja) |
| `sales`, `sale_items`, `expenses`, `ritases`, `stock_opnames`, `stock_opname_items` | Data sistem lama (arsip) |

---

## 20. Peta kode untuk pengembang

| Bagian | Lokasi |
|---|---|
| Rute V2 | `routes/v2.php` (prefix `/v2`), halaman utama `/` lewat `PosController::index` |
| Kontroler V2 | `app/Http/Controllers/V2/WorkspaceController.php`, `AccountController.php` |
| Shift, penjualan, tagihan, persetujuan | `app/Services/V2/Operations.php` |
| Jurnal | `app/Services/V2/Ledger.php` |
| Uang dan diskon | `app/Services/V2/Money.php` |
| Pengiriman, uang muka | `app/Services/V2/DeliveryTrips.php`, `TransportAssignment.php` |
| Penerimaan barang, ritase | `app/Services/V2/SupplyTrips.php` |
| Saldo awal | `app/Services/V2/OpeningBalances.php` |
| Master | `app/Services/V2/MasterData.php`, `MasterRelations.php`, `MasterInsights.php`, `app/Support/V2/FormCatalog.php` |
| Pelanggan dari kasir | `app/Services/V2/CustomerLookup.php` |
| Laporan harian dan dokumen cetak | `app/Services/V2/ReportsDocuments.php` |
| Ringkasan | `PosDashboard.php`, `MonitorMetrics.php`, `AdminSummary.php` |
| Laporan keuangan | `app/Services/AccountingReportService.php` |
| Aturan arsip dan sesi | `app/Http/Middleware/V2Cutover.php`, `EnsureActiveAccount.php` |
| Persiapan rilis | `app/Console/Commands/V2Prepare.php` (`php artisan v2:prepare`) |
| Tampilan | `resources/views/v2/*` (pedoman visual di `desain.md`) |
| Tes | `tests/Feature/V2*Test.php` |

Menjalankan tes secara terisolasi (tidak menyentuh database asli):

```
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= CACHE_STORE=array php artisan test
```

---

## 21. Batasan saat ini dan hal yang perlu diputuskan

Daftar ini jujur tentang apa yang belum ditangani sistem. Butir bertanda **[cek data]** ditemukan pada database lokal per 8 November 2026.

1. **[cek data] Nama akun 6101 berbeda.** Di database lokal 6101 bernama "Beban Operasional Toko", padahal V2 memakainya untuk biaya perjalanan sopir. Akun 6201 "Beban Transportasi & Ritase Armada" tidak dipakai V2. Perlu diputuskan: ganti nama 6101, atau ubah V2 agar memakai 6201.
2. **Jalur saldo awal dan penyesuaian lama masih terkunci.** Jalur lama ditolak403 setelah V2 aktif. Saldo awal V2 dan penyesuaian kas/bank mempunyai jalur V2 sendiri (bagian13 dan14.5). Penyesuaian akun selain Kas/Bank belum tersedia.
3. **Selisih kas shift tidak dijurnal.** Bila uang fisik kurang atau lebih, buku besar Kas tetap memakai angka seharusnya. Selisih hanya dicatat di shift dengan alasan dari admin.
4. **Belum ada beban operasional di V2.** Listrik, gaji, perlengkapan, dan beban lain (6102–6199) hanya ada di halaman lama, yang sekarang hanya bisa dibaca.
5. **Belum ada retur atau pembatalan nota penjualan.** Nota yang sudah disimpan tidak bisa dibatalkan atau dikembalikan lewat sistem. Penerimaan final juga terkunci; koreksi/retur belum tersedia. Hanya draf penerimaan yang bisa diubah/dibatalkan (bagian 11.3).
6. **Belum ada stok opname atau penyesuaian stok di V2.** Stok hanya berubah lewat saldo awal, penerimaan, dan penjualan.
7. **Jatuh tempo penjualan kredit bersifat opsional.** Kasir dapat mengisi tanggal jatuh tempo saat checkout kredit; bila dikosongkan, tagihan tidak memiliki batas jatuh tempo.
8. **Biaya perjalanan melebihi uang muka belum didukung.** Selisih lebih harus dicatat terpisah, padahal jalur pengeluaran terpisah belum ada (lihat butir 4).
9. **Penerimaan barang selalu kredit.** Pembelian tunai dicatat sebagai utang lalu dibayar lewat pengajuan pembayaran supplier.
10. **Form saldo awal V2 memakai JSON** untuk persediaan, piutang, dan utang, sehingga sulit diisi orang awam.
11. **Dua pola kode pelanggan**: `CUS-` (dibuat dari kasir) dan `PLG-` (dari form master).
12. **Jenis harga (eceran/kontraktor) belum tampil** di riwayat transaksi dan cetak nota, walaupun sudah tersimpan.
13. **Laporan harian memakai tanggal shift dibuka.** Shift yang melewati tengah malam dihitung ke tanggal buka.

---

## 22. Cara merawat dokumen ini

- Dokumen ini diperbarui **setiap kali ada perubahan perilaku sistem**: aturan, perhitungan, jurnal, status, peran, menu, nomor dokumen, atau tabel. Perubahan tampilan murni dicatat di `desain.md`, bukan di sini.
- Saat memperbarui:
  1. ubah bagian yang terkait;
  2. ganti `terakhir_diperbarui` di bagian atas;
  3. tambahkan satu baris di Riwayat perubahan (bagian 23);
  4. bila sebuah batasan di bagian 21 sudah diselesaikan, hapus dari daftar dan sebutkan di riwayat.
- Kode adalah sumber kebenaran. Bila dokumen dan kode berbeda, periksa kode, lalu perbaiki dokumen (atau laporkan bila kodenya yang keliru).

---

## 23. Riwayat perubahan dokumen

| Tanggal | Perubahan |
|---|---|
| 2026-11-24 | Daftar Pengeluaran memakai gaya tabel operasional bersama, termasuk kartu berlabel di ponsel. Pil aksi bawah dipusatkan dengan lebar maksimal 400 px (bagian 1.2 dan 1.5). |
| 2026-11-24 | Pil aksi cepat mobile (maks 3) bergaya pil hijau gelap ikon-saja dengan lingkaran lime untuk aksi aktif: admin Buat orderan/Barang masuk/Pengeluaran; kasir Kasir/Riwayat/Status bayar; tampil ≤900px, disembunyikan di Kasir, modal, keranjang, sidebar, kolom fokus (bagian1.5). |
| 2026-11-24 | Penambahan alur **Pindah Dana (Kas ↔ Bank / Antar-Bank)** (`POST /v2/journals/transfers`), koreksi rekening bank tujuan saat verifikasi pembayaran admin, rincian per rekening bank di Laporan Harian & filter sumber Pengeluaran (`bank:<kode>`), serta input **jatuh tempo opsional** pada penjualan kredit di Kasir (Bagian 14.5, 21). |
| 2026-11-24 | Dukungan **Multi-Rekening Bank** dari Bagan Akun (`chart_of_accounts.is_bank_account`, migrasi `2026_11_24_040733_add_multi_bank_account_support`, layanan `App\Services\V2\BankAccounts`): akun Aset non-inti bertanda Rekening Bank otomatis muncul sebagai pilihan rekening tujuan/sumber di Kasir (POS), Tagihan & Verifikasi Pembayaran, Pengeluaran Operasional, Uang Muka/Realisasi Sopir, Biaya Ritase, Ongkir Penerimaan, Penyesuaian Saldo, Ringkasan Admin, dan Laporan Arus Kas (`1102` tetap bawaan). |
| 2026-11-24 | `AccountBalanceService::assertSufficient()` kini mengakui dimulainya pembukuan lewat `v2_opening_batches` maupun `journal_entries` (selaras dengan `CashBankAdjustments`), sehingga pengeluaran Kas/Bank V2 tidak tertolak karena tabel legacy `opening_balances` kosong. |
| 2026-11-24 | Perbaikan menu **Pengeluaran** (`account_code` & `source` `cash`/`bank`, gaya modal mandiri di workspace, simpan AJAX tanpa muat ulang + toast, dan fix Reset filter `bindRemote`) serta penerapan sistem keterangan terpadu (**Info Toggletip**, **Rich Tooltip**, **Hover Card**, dan baris **Margin keuntungan** `BigDecimal` di Detail Produk) lintas menu Master, Operasional, Pengawasan, dan Keuangan. |
| 2026-11-24 | Menu admin **Pengeluaran** (Keuangan) untuk biaya operasional di luar penjualan/piutang/kasir, tersimpan lewat endpoint V2 dan tampil dengan tema ruang kerja; submenu Supplier/Pelanggan dan Satuan/Kategori berganti tanpa memuat ulang dengan transisi ringan (bagian1.2). |
| 2026-11-24 | Tooltip bersama di semua halaman; data arsip master/relasi dapat diaktifkan kembali; dialog rincian supplier/mobil memakai strip fakta ringkas dan aktivitas lebih dulu (bagian1.4/12.3/12.4). |
| 2026-11-24 | Kartu diskon keranjang memakai morph, grafik Kasir bertransisi saat ganti periode, panel tanggal khusus menampilkan rentang terpakai dan tidak memuat ulang rentang yang sama (bagian7.10). |
| 2026-11-24 | Nama supplier/mobil tidak lagi menambahkan karakter "›" buatan CSS pada nama aksesibel dan menandai dialognya; keterangan karyawan/sopir menjelaskan arsip tidak menghapus riwayat pengiriman (bagian12.3/12.4). |
| 2026-11-24 | Kolom cari tabel memakai satu baris penuh di HP; pil status kepala monitor memakai warna badge tema; tombol kosongkan keranjang massal dan baris subtotal di lembar keranjang HP dihapus (bagian1.2/7.10). |
| 2026-11-24 | URL menu tidak membawa tanggal laporan yang tidak relevan atau parameter master_kind/partial lama; tanggal terapan laporan dipertahankan saat pindah menu/uiLive tanpa menimpa draft (bagian7.10). |
| 2026-11-24 | Kolom master produk menjadi SKU/nama/kategori/HPP/harga jual/stok+satuan/status/aksi, dengan detail baca-saja dan supplier terkait; HPP4desimal tetap utuh (bagian12.1). |
| 2026-11-24 | Pisahkan riwayat selisih bulanan dan neto/jumlah pemeriksaan belum selesai semua tanggal; selisih lama tetap bisa diperiksa di luar30shift terbaru. Penyelesaian tidak menghapus nominal historis atau membuat jurnal (bagian6.4). |
| 2026-11-24 | Ringkasan Admin dipadatkan untuk satu layar desktop; rincian operasional dapat dibuka pada laptop pendek, mobile tetap bergulir tanpa pemotongan isi (bagian7.10, desain48). |
| 2026-11-23 | Animasi data chart admin/Kasir, produk terlaris, katalog dan angka memakai helper bersama; uiLive memperbarui geometri chart saat data berubah; tombol Isi formulir dari form tanggal tersembunyi dicegah (bagian7.10, desain47). |
| 2026-11-23 | Keterangan belum bisa checkout saat kas harian belum aktif ditampilkan kembali; klik checkout memberi alasan tanpa membuka pembayaran/menghapus keranjang (bagian7.10). |
| 2026-11-23 | Kalender khusus Kasir langsung; riwayat default7hari dan periode cepat1/7/30hari; frekuensi/piutang pelanggan; perbaikan popup supplier, aktivitas berhalaman dan cetak per aktivitas/seluruh rentang khusus admin (bagian7.10/12.2/12.3). |
| 2026-11-23 | Jumlah katalog dapat diketik dengan validasi/pemulihan draft; sidebar menjaga layout teks dan tinggi submenu; keranjang morph dari bar pembuka tanpa menskalakan/menyalin form (bagian7.1/7.10). |
| 2026-11-23 | Skeleton login/workspace/Keuangan, tanggal khusus Kasir maksimal366hari dan pembanding periode, serta ongkir pembelian sebagai beban (supplier/kas/bank). Migrasi ongkir hanya diuji terisolasi, belum dijalankan pada database toko. QA browser tulis dan konkurensi MySQL masih terblokir lingkungan (bagian1.2/7.10/11.3). |
| 2026-11-21 | Penyederhanaan Perlu restok: hapus filter supplier, pilih barang dahulu; supplier tunggal otomatis, multi-supplier dipilih, supplier manual existing/baru dengan konfirmasi aditif dan idempotensi. Pergantian supplier tidak lagi menghapus isian penerimaan (bagian11.1/11.4). |
| 2026-11-21 | Panel Perlu restok di form penerimaan: stok kritis seluruh toko sebelum memilih supplier, pencarian/filter dan pagination server, relasi supplier serta tambah baris tanpa duplikasi/menimpa isian. Endpoint baca admin; draf/konfirmasi pembukuan tetap (bagian11.4). |
| 2026-11-21 | Perapian dialog Detail penjualan: identitas/status/meta, tabel barang, ringkasan saldo, kartu riwayat pembayaran, dan cetak di footer yang dibersihkan saat ganti/tutup nota. Tampilan memakai token bersama; perhitungan dan alur pembayaran tidak diubah (bagian7.10). |
| 2026-11-21 | Perapian visual tiga kartu ringkasan: tinggi sejajar antar kartu (tidak mengikuti keranjang), grafik menyesuaikan ruang, produk terlaris1–5 satu kolom/6–10 dua kolom, nama membungkus, dan tipografi terbaru lebih padat. Data/alur penjualan tidak berubah. |
| 2026-11-21 | Tata letak Penjualan terbaru dipindah dari bawah keranjang ke kartu putih di ringkasan, di samping Produk terlaris. Satu kartu di luar panel periode; tinggi sesuai isi, grid responsif, alur detail/cetak tetap (bagian7.10). |
| 2026-11-21 | Kasir memakai5 nama/nota terbaru; tabel lengkap dipindah ke menu Riwayat penjualan dengan pencarian pembeli, status/sisa piutang dan Detail/cicilan/cetak pada nota yang sama. Foto transfer pelanggan wajib per pembayaran awal/DP/cicilan; privat, tervalidasi, satu lampiran per pembayaran, replay aman, tanpa pelunasan otomatis. Transaksi lama dan alur supplier dipertahankan (bagian7.10/8.2/8.7). |
| 2026-11-21 | Tahap3 Tagihan: tab Piutang pelanggan/Utang supplier, default Belum lunas, pencarian/status/tanggal terpisah per menu, pagination server tanpa potongan 100 terbaru, Detail dan seluruh riwayat pembayaran tetap tersedia saat lunas. Fragmen GET khusus peran memperbarui daftar tanpa reload, menjaga draft/popup dan urutan respons. Tombol Lunasi memakai nilai desimal asli termasuk sen (bagian8/8.6). |
| 2026-11-21 | Tahap2 keuangan: penyesuaian Kas/Bank khusus admin melalui `POST /v2/journals/adjustments` dengan tanggal, saldo tujuan, akun lawan aktif, alasan, dan konfirmasi password; hanya selisih yang dijurnal, saldo awal/jurnal lama tidak diubah, Kas/Bank tidak boleh negatif, dan pengiriman ganda ditahan. Halaman Jurnal memakai jalur V2, memperbarui ringkasan tanpa reload, menampilkan saldo awal V2 sebagai terkunci, dan tidak lagi menampilkan form saldo awal lama (bagian13/14.5/21). |
| 2026-11-21 | Tahap1 keuangan: COA manual khusus admin melalui jalur V2, arsip tanpa hapus, perlindungan1191/4102/6101 dan akun yang sudah dipakai, penyimpanan JSON tanpa reload serta invalidasi cache. Mode pemeliharaan `v2:prepare --accounts-only` melengkapi akun hilang tanpa peran/jurnal;1191 dan4102 sudah ditambahkan pada lokal dan dibaca kembali aktif. Penyesuaian saldo serta tahap lain belum termasuk. |
| 2026-11-21 | Koreksi kebutuhan menjadi kas harian: satu buka–tutup per tanggal (`business_date` unik untuk catatan baru, legacy tetap utuh). Riwayat default hari ini, tanpa pemilih nomor shift, kategori memakai tanggal kas; sesudah tutup tidak diminta membuka lagi pada hari yang sama. Reset mengikuti tanggal server, termasuk pergantian hari. Uang/stok/jurnal dan rekening bank tidak diubah (bagian6/7.10). |
| 2026-11-21 | Tahap1 POS: riwayat gabungan per shift, default shift aktif, pemilih shift lama/semua shift, nama petugas dan nota bersama. Filter/reset/pagination memakai fragmen tanpa reload dan menjaga keranjang; aturan laci harian dan rekening bank belum termasuk tahap ini (bagian7.8–7.10). |
| 2026-11-20 | Koreksi ringkasan POS: admin/kasir memakai agregat seluruh toko untuk KPI, grafik, produk terlaris, dan pembanding semua periode. Pilihan periode tetap per browser; atribusi transaksi dan pembatasan aksi/rincian per petugas tidak berubah (bagian 7.9). |
| 2026-11-20 | Shift toko bersama dibuka/ditutup admin, kasir otomatis memakai ID shift yang sama dengan identitas transaksi tetap terpisah. Status lintas sesi diperbarui ringan, tombol admin dibatasi server/UI, laci bersama berlabel jelas; riwayat lama tidak dipindah. Migrasi is_shared/store_open_key/closed_by (bagian 6). |
| 2026-11-20 | Akun login mendukung username pendek unik tanpa wajib email; email lama dan alias admin tetap didukung. Form akun menambah username, pencarian/tabel menampilkan username, dan perubahan username mengakhiri sesi lama (bagian 3.1). Migrasi menambah users.username dan membuat email nullable, tanpa mengubah akun/password lama. |
| 2026-11-16 | Penerimaan: simpan draf tanpa efek stok/utang, ubah/batal draf, konfirmasi terpisah dan kunci final di server; data lama tetap final. Koreksi/retur final belum tersedia (bagian 11). |
| 2026-11-16 | Tutup shift menampilkan rekap otomatis (rincian penjualan dan perkiraan laci) sebelum kasir mengisi uang fisik; banding cocok/selisih langsung, dan penutupan menolak rekap yang sudah kedaluwarsa (bagian 6.3). |
| 2026-11-08 | Dokumen pertama: seluruh alur V2 dari shift sampai laporan keuangan, termasuk harga kontraktor, tampilan HP/tablet, dan daftar batasan. |
| 2026-11-10 | Pembayaran supplier memakai sumber dana Kas Utama (1101), Bank Utama (1102), atau Laci shift; akun 1101 diberi nama Kas Utama (Tunai) (bagian 8.3, 9.2, 14.1). Kas Utama dan Bank Utama tidak memakai shift dan tidak mengubah laci. |
| 2026-11-08 | Penjualan: popup "Penjualan berhasil" setelah simpan (bagian 7.1 langkah 9). Tidak ada perubahan aturan atau angka. |
| 2026-11-08 | Penerimaan barang: pemilih barang berupa pencarian server (nama/SKU) per supplier (bagian 11.1). Tidak ada perubahan aturan stok, HPP, atau jurnal. |
| 2026-11-08 | Seluruh ruang kerja: setelah simpan (tambah/ubah/arsip master, penerimaan, shift, pembayaran, persetujuan, pengiriman, penjualan) halaman tidak lagi dimuat ulang; data diperbarui di tempat. Tidak ada perubahan aturan atau angka. |
| 2026-11-08 | Penerimaan barang: langkah Periksa sebelum simpan dan pembatalan penerimaan salah input oleh admin dengan alasan, jurnal balik, stok dan HPP kembali (bagian 11.1, 11.3, 14, 16). Migrasi `2026_11_08_164504_add_v2_receipt_void`. |
| 2026-11-08 | Input nominal uang: titik ribuan otomatis saat mengetik; kolom tanpa isian 0 bawaan, kosong berarti 0 untuk kolom yang dulu berisi 0 (biaya BBM, uang jalan, ongkir, saldo awal di halaman Jurnal). Nilai yang dikirim ke server tetap angka mentah; aturan dan angka tidak berubah. |
| 2026-11-08 | Pembayaran tagihan: tombol bayar di tiap baris (pelanggan dan supplier), nominal kosong + tombol Lunasi; admin membayar utang supplier langsung tercatat, kasir tetap mengajukan; larangan admin menyetujui pengajuan sendiri dihapus (bagian 8, 9.2). |
| 2026-11-10 | Penjaga entri dobel: pembayaran tagihan yang sudah berubah sejak form dibuka dan nomor nota supplier yang sama meminta konfirmasi (bagian 8.5, 11.1). Migrasi `2026_11_10_010937_add_v2_supplier_invoice_number`. Tidak ada perubahan perhitungan. |
