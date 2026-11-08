# Rencana perombakan UI/UX isi menu V2 (header monitor + tabel konsisten)

Status: RENCANA, menunggu lampu hijau. Belum ada kode yang diubah.
Disusun: 2026-11-05. Workspace: `C:\laragon\www\tb01`, branch `feat/cash-flow-role-hierarchy`.
Dasar: audit DOM dan screenshot 16 menu pada fixture sintetis (1440 dan 390).

## A. Sasaran dan batasan

### Sasaran
Isi setiap menu terlihat satu keluarga, mudah dipahami orang tua maupun muda, profesional dan tenang (bukan ramai/norak). Dua hal utama yang diminta:
1. Header monitor yang bermakna di setiap menu.
2. Tabel yang seragam di setiap menu yang berisi daftar data.

### Temuan audit (bukti, bukan opini)
| # | Temuan | Bukti |
|---|--------|-------|
| 1 | Hanya 7 elemen `<table>` untuk 16 menu. Persetujuan, Tagihan, Shift, Ringkasan, Penerimaan berupa daftar teks bertitik, bukan tabel. | audit DOM |
| 2 | Ukuran teks terlalu kecil dan tidak seragam: 99 dari 184 deklarasi `font-size` di bawah 13px. Font tabel 11 / 12 / 13 / 13.5px, padding sel 8 / 12 / 24px. | grep CSS + computed style |
| 3 | Judul menu muncul 3 kali (topbar, h1, h2 kartu). Judul h2 berukuran 16 / 18 / 20 / 21px. | screenshot Master Produk, Shift |
| 4 | Monitor hanya hitungan daftar yang dipotong batas (30/50/100), bukan angka bisnis. Contoh: "Tagihan ditampilkan 2". Ringkasan dan Kasir tidak memakai pola monitor yang sama. | `workspace-monitoring.blade.php` |
| 5 | Bahasa pengembang bocor ke layar: "Maksimal 100 data terbaru", "pelanggan id 1", "sumber Dari admin", "belum dihitung". | screenshot Tagihan, Shift |
| 6 | Format uang tidak seragam: `Rp 315,00`, `Rp 200`, `Rp 100,00`. | screenshot Kasir, Laporan |
| 7 | Satu hitungan muncul 4 kali di Master Produk (chip, 3 kartu, dua catatan, "1 data ditampilkan"). Di mobile kartu KPI memenuhi layar sebelum data muncul. | screenshot 390 |
| 8 | "Belum buka shift / Buka shift dahulu" melayang terpisah di kanan atas tiap halaman. | semua screenshot |
| 9 | Sidebar hanya ikon (76px), beberapa ikon mirip (Pelanggan, Karyawan, Supplier). Lebar konten terkunci sekitar 960px sementara layar 1440 menyisakan ruang kosong. | screenshot |
| 10 | CSS tersebar: 4 blok `:root`, `incumbent-styles` 1962 baris, 18 sampai 27 `style=""` inline di panel master. | grep |

### Dalam lingkup
- Pola halaman standar: header halaman, monitor, toolbar, tabel, status, aksi baris, kondisi kosong, paginasi.
- Token desain (tipografi, warna, jarak) dan format uang/angka tampilan.
- Perapihan teks UI (bahasa Indonesia sederhana, tanpa istilah teknis).
- Tampilan mobile: tabel menjadi kartu di bawah 640px.
- Navigasi: label, ikon unik, tooltip, dan tombol sematkan (lihat Keputusan).

### Di luar lingkup (ditolak tegas)
- Logika uang, stok, jurnal, shift, dan hak akses. Tidak diubah.
- Susunan utama POS (katalog kiri, keranjang kanan, popup pembayaran). Disetujui pemilik di `desain.md`; hanya tipografi dan token yang disentuh.
- Halaman buku besar (Jurnal, Neraca, Laba Rugi, Arus Kas, COA): karakter "ledger industrial" tetap. Hanya header dan token yang diselaraskan.
- Tema baru. Hijau-putih dipertahankan.
- Migrasi database, commit, push, deploy.

### Satu tambahan backend yang diperlukan (read-only)
Monitor yang bermakna (total piutang dalam rupiah, nilai menunggu verifikasi) tidak bisa dihitung dari daftar yang dipotong 30/100. Dibutuhkan satu layanan baca `MonitorMetrics` berisi `SELECT SUM/COUNT` saja. Tidak ada INSERT/UPDATE, tidak menyentuh `Ledger`, `Operations`, atau perhitungan uang. Dibuktikan dengan `git diff --stat` kosong pada berkas posting dan tes angka sama sebelum/sesudah.

## B. Prinsip desain (cocok orang tua dan muda, profesional)

| Aspek | Aturan |
|-------|--------|
| Ukuran teks | Isi 16px, isi tabel minimal 15px, label kolom 14px tebal sedang, keterangan minimal 13px. Tidak ada teks di bawah 13px. Semua dalam `rem`. |
| Kontras | Teks minimal 4.5:1, teks isi 7:1 bila bisa. Status selalu teks + penanda, tidak hanya warna. |
| Warna | Satu aksen hijau. Hijau = baik, kuning tua = perlu perhatian, merah = masalah, abu = arsip. Tanpa gradien, bayangan tipis. |
| Target sentuh | Minimal 44px. Tinggi baris tabel 52px. |
| Bahasa | Kata sehari-hari. Tampilkan nama, bukan ID. Tombol memakai kata kerja: "Catat pembayaran", bukan "Submit". |
| Uang | Satu format tampilan `Rp 1.234.567` (koma desimal hanya bila bukan nol), rata kanan, `tabular-nums`. Hanya tampilan, perhitungan tetap string desimal di server. |
| Ukuran teks pengguna | Tombol A- / A / A+ (100 / 115 / 130%) disimpan per perangkat. Murah karena semua `rem`. |
| Tanpa emoji | Sesuai `desain.md`. |

## C. Pola halaman standar (berlaku untuk 16 menu)

```
Topbar      : tombol menu | nama pengguna + peran | status shift (satu tempat, satu chip)
Header      : judul menu SATU kali | satu kalimat penjelasan | tombol aksi utama (kanan)
Monitor     : 3-4 ubin: label sederhana | angka besar | keterangan kecil | nada (netral/perhatian/masalah)
              klik ubin = memfilter tabel di bawahnya
Toolbar     : cari | chip filter status | aksi sekunder
Tabel       : kolom seragam | angka rata kanan | badge status | maksimal 2 aksi terlihat
Kosong      : apa ini, kenapa kosong, apa langkah berikutnya
Paginasi    : 10/25/50 | "Menampilkan 1-10 dari 37"
Mobile <640 : tabel menjadi kartu (kolom kunci jadi judul kartu), monitor 2 kolom ringkas
```

Komponen Blade (berkas baru, nama kelas berawalan `ui-` agar tidak bentrok dengan CSS lama):
`x-page-header`, `x-monitor`, `x-toolbar`, `x-data-table`, `x-status-badge`, `x-row-actions`, `x-empty-state`, `x-pagination`.

### Isi monitor per menu
| Menu | Ubin monitor | Sumber |
|------|--------------|--------|
| Ringkasan Admin | Penjualan hari ini (Rp + jumlah) / Piutang terbuka (Rp) / Utang supplier (Rp) / Perlu tindakan (jumlah) | `daily()` + MonitorMetrics |
| Persetujuan Pembayaran | Menunggu verifikasi / Nilai menunggu (Rp) / Ditolak | MonitorMetrics |
| Kasir (POS) | 3 kartu yang sudah ada dipertahankan, hanya diselaraskan gayanya | data sekarang |
| Tagihan | Piutang terbuka (Rp) / Utang terbuka (Rp) / Menunggu verifikasi | MonitorMetrics |
| Pengiriman | Siap dikirim / Sedang dikirim / Diterima hari ini / Uang muka sopir terbuka (Rp) | MonitorMetrics |
| Penerimaan Barang | Penerimaan bulan ini (jumlah, nilai) / Utang dari penerimaan / Supplier aktif | MonitorMetrics |
| Produk | Aktif / Stok menipis / Stok habis / Diarsip | data + MonitorMetrics |
| Pelanggan, Supplier, Karyawan | Aktif / Diarsip (+ Supplier: Utang terbuka; Pelanggan: Piutang terbuka) | MonitorMetrics |
| Sopir | Internal / Eksternal / Diarsip | data |
| Satuan dan Kategori | Aktif / Diarsip / Dipakai produk | data |
| Mobil | Aktif / Nonaktif | data |
| Akun Login | Admin / Kasir / Nonaktif | data |
| Shift / Pengawasan Shift | Terbuka / Perlu pemeriksaan / Selisih bulan ini (Rp) | MonitorMetrics |
| Laporan harian | Penjualan (Rp) / Tunai di laci / Transfer menunggu | `daily()` |

### Tabel per menu (kolom, urutan prioritas)
| Menu | Kolom | Aksi baris |
|------|-------|-----------|
| Produk | SKU, Nama, Satuan, Kategori, Harga jual (kanan), Stok (kanan), Supplier, Status | Ubah, Arsip |
| Pelanggan / Supplier / Karyawan | Kode, Nama, Catatan, Status | Ubah, Arsip |
| Sopir | Kode, Nama, Jenis, Status | Ubah, Arsip |
| Satuan / Kategori | Nama, Dipakai produk, Status | Ubah, Arsip |
| Mobil | Nomor polisi, Tipe, Status | Ubah, Arsip |
| Akun Login | Nama, Email login, Peran, Status | Ubah akun |
| Persetujuan Pembayaran | Tanggal, Nomor tagihan, Nama pihak, Metode, Jumlah, Pengaju, Status | Verifikasi, Tolak |
| Tagihan (dua tab: Piutang pelanggan / Utang supplier) | Nomor, Nama pihak, Tanggal, Total, Terbayar, Sisa, Status | Catat pembayaran |
| Pengiriman (tab: Pengiriman / Biaya ritase / Uang muka sopir) | Surat jalan, Tanggal, Tujuan, Mobil dan sopir, Status | Kirim, Diterima, Dokumen |
| Penerimaan Barang | Tanggal, Nomor, Supplier, Jumlah barang, Nilai, Pembayaran, Status | Lihat |
| Shift | Nomor, Kasir, Dibuka, Kas awal, Perkiraan, Fisik, Selisih, Status | Tutup shift, Periksa |
| Riwayat Transaksi (di Kasir) | Invoice, Tanggal, Metode, Total, Status | Nota, Surat jalan |
| Laporan harian | Tetap gaya laporan (label kiri, angka kanan), digrupkan jelas, bukan tabel data | tanggal + Tampilkan |

## D. Rencana langkah (DAG)

Eksekusi tunggal oleh satu pelaksana. Alasan: semua fase menyentuh berkas bersama (`workspace.blade.php` 634 baris, `incumbent-styles` 1962 baris); pemecahan paralel memicu konflik. Satu pengawas independen (read-only) hanya di Fase 6 untuk audit screenshot dan kontras.

| Fase | Isi | Keluaran | Ukuran |
|------|-----|----------|--------|
| 0 | Fondasi. 0.0 simpan snapshot patch working tree + screenshot "sebelum". 0.1 berkas token tunggal (tipografi, warna, jarak, fokus) menggantikan 4 blok `:root`. 0.2 formatter uang/angka (Blade + JS, tampilan saja). 0.3 delapan komponen Blade. 0.4 halaman galeri komponen (fixture) untuk QA visual. | token + komponen + galeri teruji | M |
| 1 | Kerangka + monitor. Topbar dirapikan (satu chip shift, judul tidak ganda). Header halaman standar di 16 menu. Layanan `MonitorMetrics` (read-only) + tesnya. Semua ubin monitor sesuai tabel. Teks UI diganti bahasa sederhana. | 16 menu berkepala seragam + monitor bermakna | M |
| 2 | Tabel master: Produk, Pelanggan, Supplier, Karyawan, Sopir, Satuan/Kategori, Mobil, Akun Login. Hapus `style=""` inline dan CSS lama yang terganti. | 8 menu memakai `x-data-table` | M |
| 3 | Tabel transaksional: Persetujuan, Tagihan, Shift, Pengiriman (+ ritase, uang muka), Penerimaan (riwayat baru, query baca), Ringkasan ("Perlu tindakan"). Nama menggantikan ID. | 6 menu daftar-teks menjadi tabel | L |
| 4 | Kasir dan Laporan: hanya tipografi/token, tabel Riwayat Transaksi dan Produk dinaikkan ke standar. Susunan POS tidak berubah. | POS selaras tanpa ubah alur | S |
| 5 | Navigasi dan mobile: label, ikon unik, tooltip, tombol sematkan, A-/A/A+. Lebar konten memanfaatkan layar. Header Keuangan (ledger) diselaraskan. | sidebar mudah dikenali | M |
| 6 | QC akhir. Audit kontras dan ukuran teks otomatis, overflow 320/390/768/1024/1440, zoom teks 200%, keyboard, axe-core, bandingkan sebelum/sesudah, seluruh tes. | laporan bukti | S |

Aturan eksekusi: satu fase selesai hanya jika suite hijau dan screenshot fase itu sudah dilihat. Tes HTML yang mengunci markup lama (`V2WorkspaceViewTest`, 104 assertion) diperbarui sengaja per fase, bukan dilemahkan. CSS lama dihapus hanya setelah menu yang memakainya terverifikasi.

## E. Kriteria penerimaan (harus bisa dibuktikan)

1. Skrip audit CSS/computed style: 0 teks di bawah 13px; isi 16px; isi tabel minimal 15px; kontras teks minimal 4.5:1 di semua 16 menu.
2. Setiap menu: tepat satu `h1`, satu monitor 3-4 ubin, dan setiap daftar data adalah `<table>` semantik (ber-`caption`/`aria-label`). Di bawah 640px berubah menjadi kartu.
3. grep pada HTML hasil render: tidak ada "pelanggan id", "sumber Dari", "Maksimal 100", "belum dihitung". Semua teks "Rp" cocok pola `Rp \d{1,3}(\.\d{3})*(,\d{2})?`.
4. Tidak ada overflow horizontal pada 320 / 390 / 768 / 1024 / 1440; tidak ada pemotongan teks pada zoom 200%.
5. Keyboard: urutan Tab wajar, fokus terlihat 3px, Escape menutup dialog dan fokus kembali ke pemicu; hasil axe-core 0 pelanggaran serius/kritis (dipasang di folder scratch, `package.json` tidak berubah).
6. `artisan test --compact` penuh hijau (baseline 430 tes, 8 skip MariaDB) ditambah tes baru untuk `MonitorMetrics`; Pint, `git diff --check`, `view:cache`, `npm run build` lulus.
7. Berkas posting uang/stok/jurnal tidak berubah (`git diff --stat` kosong); angka monitor sama dengan query SQL langsung pada data fixture.
8. Screenshot sebelum/sesudah 1440 dan 390 untuk 16 menu dilihat langsung; kesenjangan dicatat apa adanya.

NOT RUN yang tetap berlaku sampai ada akses: login ke aplikasi asli (vault kosong), perangkat fisik, uji dengan pengguna lanjut usia sungguhan. Disarankan: setelah Fase 3, Bagas atau satu orang tua mencoba 5 tugas (cari produk, catat pembayaran, tutup shift, kirim barang, lihat laporan).

## F. Risiko, mitigasi, rollback

| Risiko | Mitigasi |
|--------|----------|
| CSS lama (1962 baris) menimpa gaya baru | Komponen memakai kelas `ui-*`; migrasi per menu; hapus CSS lama setelah terverifikasi |
| Angka monitor salah karena daftar dipotong | `MonitorMetrics` memakai agregasi SQL penuh, bukan daftar; diuji terhadap data fixture |
| Tes HTML lama gagal massal | Diperbarui per fase dengan assertion setara |
| Layout POS berubah tanpa izin | POS hanya disentuh tipografi/token; screenshot POS dibandingkan per fase |
| Pekerjaan lokal yang belum di-commit hilang | Fase 0.0: simpan `git diff` + daftar untracked ke scratch sebelum mulai. Rollback per berkas dari patch itu. Tidak ada commit/reset tanpa permintaan |
| Batas 30/50/100 data mengecoh | Paginasi dan pencarian di sisi klien di atas data yang dimuat; label jujur "dari N data terbaru". Paginasi server-side adalah rencana terpisah |

## G. Keputusan terbuka

1. Sidebar. Log 2026-11-05 mencatat Bagas meminta sidebar kembali menjadi rail ikon saat kursor keluar. Rail ikon tanpa label sulit bagi pengguna lanjut usia. Pilihan:
   - (rekomendasi) Perilaku rail dipertahankan, ditambah ikon unik per menu, tooltip, dan tombol "Sematkan" untuk melebarkan permanen (diingat per perangkat).
   - Default terbuka berlabel di layar 1280px ke atas, bisa dilipat.
   - Biarkan seperti sekarang.
2. Monitor memakai agregasi baru `MonitorMetrics` (read-only) agar angka bermakna. Alternatif: tetap hitungan dari daftar terpotong (cepat, tapi menyesatkan). Default rencana ini: pakai `MonitorMetrics`.
