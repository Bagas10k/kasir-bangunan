---
judul: Dokumentasi Sistem Kasir Bangunan (tb01)
versi_sistem: V2 (V2_ENABLED=true)
terakhir_diperbarui: 2026-11-20
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

Halaman Keuangan (Jurnal umum, Neraca, Laba rugi, Arus kas, Bagan akun) adalah halaman lama yang ditampilkan di dalam ruang kerja V2. Karena aturan arsip di atas, halaman-halaman ini **hanya bisa dibaca** setelah V2 aktif. Tombol simpan di halaman itu (tambah akun, jurnal penyesuaian, saldo awal lama) akan ditolak. Lihat bagian 21.

### 1.2 Menu ruang kerja

| Grup | Menu | Siapa |
|---|---|---|
| Pengawasan | Ringkasan, Persetujuan pembayaran, Akun login | Admin |
| Operasional | Kasir (POS), Tagihan, Pengiriman, Kas & laporan (tab Shift toko dan Laporan harian) | Admin dan kasir |
| Operasional | Penerimaan barang | Admin |
| Master data | Produk, Supplier & pelanggan, Karyawan & sopir, Mobil, Satuan & kategori | Admin |
| Keuangan | Jurnal umum, Neraca, Laba rugi, Arus kas, Bagan akun (COA) | Admin |

Halaman pertama setelah login: admin ke **Ringkasan**, kasir ke **Kasir**.

Untuk kasir, menu "Kas & laporan" bernama "Shift & laporan saya" dan hanya berisi data miliknya sendiri.

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
| Keuangan (jurnal, neraca, laba rugi, arus kas, COA) | Baca saja | Tidak |
| Cetak nota / surat jalan | Semua | Hanya penjualannya sendiri |

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

## 6. Shift toko bersama dan laci kas

Kode: `Operations::openShift`, `activeStoreShift`, `closeShift`, `resolveShift`, `ShiftClosingSummary`. Menu: **Kas & laporan › Shift toko** (kasir: **Shift toko & laporan saya**).

### 6.1 Membuka shift

- Hanya admin yang boleh membuka/menutup shift toko. Maksimal satu shift toko aktif untuk seluruh akun, dilindungi indeks unik `store_open_key` dan pemeriksaan server.
- Kasir tidak membuka shift sendiri. Penjualan, penerimaan tunai dan pembayaran laci memakai ID shift toko aktif yang sama. `v2_sales.user_id`/pengaju pembayaran tetap petugas sebenarnya, bukan diganti menjadi admin pembuka.
- Status di layar admin/kasir memakai sumber aktif yang sama (`GET /v2/shifts/active`, baca saja, no-store). Tab yang terlihat memeriksa setiap sekitar 5 detik dan saat kembali fokus. Perubahan status memperbarui data di tempat tanpa menghapus keranjang atau memuat ulang halaman. Jika status tidak dapat diperiksa, checkout ditahan dan keranjang tetap disimpan; server tetap memeriksa shift saat transaksi.
- Jika belum dibuka, kasir melihat "Menunggu admin membuka shift toko". Setelah ditutup admin, transaksi baru tidak boleh masuk shift lama; perlu shift berikutnya dibuka admin.
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

### 6.3 Menutup shift

1. Saat popup **Tutup shift** dibuka, sistem menampilkan **rekap otomatis** untuk shift itu (read-only, `ShiftClosingSummary`):
   - Penjualan: jumlah nota, total, dan rincian tunai / transfer / kredit.
   - Laci: kas awal, uang masuk, uang keluar, dan **seharusnya di laci** (rumus bagian 6.2).
   - Jumlah pengajuan tunai dari laci ini yang masih menunggu persetujuan admin (tidak dikurangkan dari saldo, hanya peringatan).
2. Admin menghitung uang fisik laci bersama lalu mengisi hasilnya. Sistem langsung membandingkan dan menampilkan **Cocok** atau **Selisih lebih/kurang Rp …**.
3. Saat menutup, form mengirim **tanda rekap** (`fingerprint`). Bila transaksi laci, penjualan, atau pengajuan berubah sejak popup dibuka, server menolak dengan "tutup ulang untuk melihat angka terbaru", supaya yang dibandingkan selalu angka terkini.
4. Selisih nol → status **Selesai** (`closed`). Selisih bukan nol → status **Perlu diperiksa** (`review`).
5. Hanya admin yang bisa menutup. Rekap shift toko mencakup seluruh petugas yang bertransaksi pada ID shift yang sama; tidak hanya penjualan admin pembuka. Kasir dapat melihat riwayat bersama, tetapi tidak memiliki tombol/izin membuka atau menutup. Sesi laporan penjualan kasir tetap dibatasi miliknya; angka laci berlabel laci toko bersama.

Contoh: modal 500.000, penjualan tunai 2.300.000, bayar supplier tunai 200.000. Seharusnya 500.000 + 2.300.000 − 200.000 = 2.600.000. Kasir menghitung 2.590.000. Selisih −10.000, shift masuk pemeriksaan.

### 6.4 Pemeriksaan selisih

- Admin membuka **Kas & laporan**, kartu "Pemeriksaan selisih shift", lalu menulis alasan (5–1000 karakter). Status shift menjadi Selesai, dan nama admin, waktu, serta alasan tersimpan.
- **Selisih kas tidak dijurnal otomatis.** Buku besar Kas tetap memakai angka seharusnya. Lihat bagian 21.

---

## 7. Penjualan di Kasir

Kode: `Operations::checkout` (server), `pos-cashier-script` (layar). Menu: **Operasional › Kasir (POS)**.

### 7.1 Langkah di layar

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

`/v2/documents/{id}/receipt` (nota) dan `/v2/documents/{id}/delivery` (surat jalan). Hanya membaca data. Kasir hanya bisa membuka dokumen dari penjualannya sendiri.

### 7.9 Ringkasan penjualan di atas katalog

Menampilkan penjualan, jumlah transaksi, rata-rata per nota, stok yang perlu perhatian, grafik penjualan, dan 10 produk terlaris untuk Hari ini, 7 hari, atau 30 hari. Setiap angka dibandingkan dengan periode sebelumnya. Admin dan kasir memakai seluruh data toko yang sama, termasuk penjualan yang dibuat admin, bukan difilter berdasarkan akun yang sedang masuk. Kasir tanpa transaksi pribadi tetap melihat angka toko. Sumber: `PosDashboard` (hanya membaca). Pilihan periode disimpan per browser, bukan disinkronkan antar akun/perangkat; bandingkan periode yang sama. Ini tidak memperluas izin kelola akun, ubah master, persetujuan, cetak nota atau rincian riwayat/laporan per petugas.

---

## 8. Tagihan dan pembayaran

Kode: `Operations::collect`. Menu: **Operasional › Tagihan**. Setiap baris tagihan yang belum lunas (pelanggan maupun supplier) punya tombol bayar sendiri yang membuka popup berisi total, terbayar, sisa, jumlah dibayar, metode, dan referensi. Kolom jumlah dibayar kosong; tombol **Lunasi Rp …** mengisi sisa tagihan dengan satu klik.

### 8.1 Jenis tagihan

| Arah | Asal | Nomor |
|---|---|---|
| Piutang (`receivable`) | Setiap penjualan, atau piutang di saldo awal | `AR-YYYYMMDD-00001` (penjualan), atau nomor isian saldo awal |
| Utang (`payable`) | Setiap penerimaan barang dari supplier, atau utang di saldo awal | `AP-000001` (penerimaan), atau nomor isian saldo awal |

Status tagihan: **Terbuka** (`open`) atau **Lunas** (`paid`). Sisa = nilai − sudah dibayar.

### 8.2 Mencatat pembayaran piutang

- Nominal harus lebih dari nol dan tidak boleh melebihi sisa.
- **Tunai**: kasir wajib punya shift terbuka. Pembayaran langsung tercatat (posted), uang masuk laci, sisa tagihan berkurang, dan status nota ikut diperbarui. Jurnal: debit 1101 Kas, kredit 1103 Piutang.
- **Transfer**: dicatat sebagai **menunggu verifikasi**, tanpa jurnal dan tanpa mengubah sisa tagihan sampai admin memverifikasi (bagian 9).
- Pembayaran sebagian boleh dilakukan berkali-kali.

### 8.3 Pembayaran utang supplier

- Tombol di baris tagihan supplier: **Bayar ke supplier** (admin) atau **Ajukan pembayaran** (kasir).
- **Sumber dana** yang dipilih di popup: **Kas Utama** (`main`, akun 1101), **Bank Utama** (`transfer`, akun 1102), atau **Laci shift** (`cash`, akun 1101 dengan uang laci shift yang sedang terbuka).
- **Admin**: pembayaran langsung tercatat (`posted`) dan mengurangi utang. Kas Utama dan Bank Utama memakai saldo toko, tidak memerlukan shift, dan tidak mengubah hitungan laci. Laci shift memakai uang laci shift admin sendiri (harus terbuka dan cukup) dan mencatat uang keluar di laci itu.
- **Kasir**: dicatat sebagai **pengajuan** (`proposed`) tanpa jurnal dan baru mengurangi utang setelah admin menyetujui. Sumber Laci shift memakai shift toko yang aktif saat pengajuan; Kas Utama dan Bank Utama tidak perlu shift. Pengajuan laci pada shift yang sudah ditutup tidak boleh dipindah diam-diam ke shift berikutnya; ajukan ulang.
- Saldo akun 1101/1102 tidak boleh menjadi negatif; jurnal debit 2101, kredit 1101 atau 1102.
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

---

## 9. Persetujuan dan penolakan pembayaran

Kode: `Operations::approvePayment`, `rejectPayment`. Menu: **Pengawasan › Persetujuan pembayaran**. Hanya admin.

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
- Pilih supplier aktif, tanggal penerimaan, No. nota supplier (opsional, maks 64 karakter), dan 1–100 baris barang (produk, jumlah, harga beli per satuan).
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

- Penerimaan yang sudah dikonfirmasi **tidak boleh diubah atau dibatalkan biasa**, termasuk bila belum dibayar dan stoknya belum dipakai. Tombol pembatalan final dihapus; endpoint pembatalan lama tetap menolak di server agar tidak bisa dilewati lewat kiriman manual.
- Penerimaan lama yang sudah menambah stok/jurnal tetap dianggap **final**, bukan diubah menjadi draf. Tidak ada penghitungan ulang stok atau jurnal historis dalam migrasi ini.
- Riwayat pembatalan yang sudah terjadi sebelum aturan baru tetap dipertahankan, lengkap dengan waktu/admin/alasan dan nilai dicoret; tidak ikut total aktif.
- Salah input sebelum konfirmasi: **Ubah draf**. Setelah final, kesalahan memerlukan dokumen koreksi/retur, bukan membuka kunci atau menghapus penerimaan. **Alur koreksi/retur penerimaan final belum tersedia pada paket ini.**

---

## 12. Master data

Kode: `MasterData`, `MasterRelations`, `FormCatalog`. Menu: **Master data** (admin).

Aturan umum:
- **Tidak ada penghapusan.** Data hanya bisa **diarsipkan**. Data arsip tidak muncul untuk transaksi baru, tetapi riwayat transaksinya tetap utuh. Arsip bisa diubah, tetapi tidak aktif kembali dengan sendirinya.
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

### 12.2 Pelanggan

Kode, nama, catatan (alamat, telepon), dan **harga di kasir** (eceran atau kontraktor).

Pelanggan juga bisa dibuat langsung dari kasir (kode `CUS-0001` dan seterusnya, harga eceran). Pelanggan arsip tidak bisa dipilih di kasir.

### 12.3 Supplier

Kode, nama, catatan. Menekan nama supplier menampilkan produk yang dipasoknya dan riwayat transaksinya. Kolom "Utang berjalan" menunjukkan sisa utang ke supplier itu.

### 12.4 Karyawan dan sopir

- Karyawan: kode, nama, catatan.
- Sopir: jenis **karyawan toko** (terhubung ke data karyawan, nama mengikuti karyawan) atau **sopir luar**. Satu karyawan hanya bisa terdaftar sekali sebagai sopir.

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

Catatan: halaman Jurnal umum masih menampilkan form saldo awal lama (hanya kas dan bank). Setelah V2 aktif, form lama itu ditolak sistem; gunakan saldo awal V2 di atas.

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
- **Uang**: tunai di laci (shift terbuka), **bank terverifikasi = saldo akun 1102 di buku besar**, transfer menunggu, uang muka sopir terbuka, selisih kas bulan ini.
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

Kode: `generateCashFlow`. Metode langsung, hanya dari jurnal yang menyentuh 1101 Kas dan 1102 Bank.

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

1. **[cek data] Akun 1191 dan 4102 belum ada di bagan akun lokal.** Akibatnya, menurut kode, penjualan dengan ongkir lebih dari 0 dan uang muka sopir akan gagal dengan pesan "Akun … belum ada di bagan akun". Jalankan `php artisan v2:prepare --admin=<id> --cashier=<id> --apply` (perintah ini juga memeriksa peran yang sudah ada).
2. **[cek data] Nama akun 6101 berbeda.** Di database lokal 6101 bernama "Beban Operasional Toko", padahal V2 memakainya untuk biaya perjalanan sopir. Akun 6201 "Beban Transportasi & Ritase Armada" tidak dipakai V2. Perlu diputuskan: ganti nama 6101, atau ubah V2 agar memakai 6201.
3. **Halaman Keuangan hanya bisa dibaca.** Setelah V2 aktif, menambah/mengubah akun di Bagan akun, jurnal penyesuaian, dan saldo awal lama di halaman Jurnal ditolak (403). Belum ada jalur V2 untuk menambah akun atau membuat jurnal penyesuaian.
4. **Selisih kas shift tidak dijurnal.** Bila uang fisik kurang atau lebih, buku besar Kas tetap memakai angka seharusnya. Selisih hanya dicatat di shift dengan alasan dari admin.
5. **Belum ada beban operasional di V2.** Listrik, gaji, perlengkapan, dan beban lain (6102–6199) hanya ada di halaman lama, yang sekarang hanya bisa dibaca.
6. **Belum ada retur atau pembatalan nota penjualan.** Nota yang sudah disimpan tidak bisa dibatalkan atau dikembalikan lewat sistem. Penerimaan final juga terkunci; koreksi/retur belum tersedia. Hanya draf penerimaan yang bisa diubah/dibatalkan (bagian 11.3).
7. **Belum ada stok opname atau penyesuaian stok di V2.** Stok hanya berubah lewat saldo awal, penerimaan, dan penjualan.
8. **Penjualan kredit tidak punya jatuh tempo.** "Lewat tempo" hanya bisa terjadi pada piutang dari saldo awal.
9. **Biaya perjalanan melebihi uang muka belum didukung.** Selisih lebih harus dicatat terpisah, padahal jalur pengeluaran terpisah belum ada (lihat butir 5).
10. **Penerimaan barang selalu kredit.** Pembelian tunai dicatat sebagai utang lalu dibayar lewat pengajuan pembayaran supplier.
11. **Form saldo awal V2 memakai JSON** untuk persediaan, piutang, dan utang, sehingga sulit diisi orang awam.
12. **Dua pola kode pelanggan**: `CUS-` (dibuat dari kasir) dan `PLG-` (dari form master).
13. **Jenis harga (eceran/kontraktor) belum tampil** di riwayat transaksi dan cetak nota, walaupun sudah tersimpan.
14. **Laporan harian memakai tanggal shift dibuka.** Shift yang melewati tengah malam dihitung ke tanggal buka.

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
