# Pedoman Desain Antarmuka & Aksesibilitas (`desain.md`)

Dokumen ini menetapkan standar visual, tata letak antarmuka pengguna (UI), dan prinsip aksesibilitas (UX/A11y) untuk Kasir Bangunan App.

---

## 1. Prinsip Aksesibilitas & Ergonomi (Accessibility & UI Standards)
- **Target Sentuh Ergonomis (Touch Targets)**:
  - Tombol aksi utama, tautan navigasi, dan kontrol mobile wajib memiliki ukuran target sentuh minimal **44×44px**.
  - Kontrol stepper kuantitas keranjang belanja (tombol `−` dan `+`) wajib berukuran minimal **36×36px** agar mudah ditekan pada layar sentuh.
- **Tipografi & Kontras Warna**:
  - Kontras teks terhadap latar belakang wajib memenuhi standar WCAG AA (rasio minimal 4.5:1 untuk teks normal dan 3:1 untuk teks besar).
  - Teks sekunder/keterangan (*caption*) minimal berukuran 11px tebal atau 12–13px reguler dengan kontras yang tetap terbaca.
- **Perlakuan Angka Finansial (Tabular Numbers)**:
  - Seluruh nilai moneter (Rupiah), harga pokok penjualan (HPP), harga jual, subtotal, kuantitas stok, dan saldo pembukuan wajib menggunakan fitur angka tabular (`font-variant-numeric: tabular-nums` atau keluarga `font-mono`) serta perataan kanan (*right-aligned*) di tabel dan ringkasan.
- **Aksesibilitas Keyboard & Pembaca Layar**:
  - Seluruh elemen interaktif wajib memiliki indikator fokus yang jelas (*focus visible*).
  - Drawer navigasi atau sidebar yang sedang tertutup wajib disembunyikan dari navigasi keyboard (gunakan `tabindex="-1"`, `aria-hidden="true"`, atau `display: none`) agar tidak menangkap fokus secara tersembunyi.
- **Respek terhadap Preferensi Pengguna**:
  - Wajib menghormati preferensi gerak pengguna melalui media query `@media (prefers-reduced-motion: reduce)` dengan mematikan transisi berdurasi panjang dan animasi dekoratif.

---

## 2. Larangan Emoji pada Antarmuka (No Emoji UI)
- **Aturan Mutlak**: Dilarang menggunakan emoji Unicode (seperti 📦, 🛒, ⚠️, 💰, 🚚, ✅, ❌, 🔧) pada tombol aksi, label menu, kartu ringkasan, status badges, maupun header antarmuka aplikasi.
- **Solusi Alternatif**:
  - Gunakan ikon SVG inline yang bersih, presisi, berskala proporsional, dan memiliki semantik yang tepat.
  - Gunakan badge status berbentuk kapsul (*pill badge*) dengan teks murni yang padat dan warna semantik lembut (pastel).

---

## 3. Kebijakan Data Sintetis (Synthetic Data Only)
- **Wajib Menggunakan Data Sintetis**: Seluruh data yang ditampilkan dalam pratinjau antarmuka, *fixtures*, data awal (*seed*), tangkapan layar, maupun skenario uji coba visual WAJIB berupa data sintetis netral.
- **Dilarang Data Riil/Pribadi**: Dilarang mencantumkan nama orang nyata, nomor telepon pribadi, nomor rekening bank sebenarnya, alamat pelanggan riil, atau data transaksi komersial asli pemilik/pengguna aplikasi.
- **Contoh Data Netral**:
  - Nama Produk: "Semen Portland Tipe I 50kg", "Batu Bata Merah Standar", "Besi Beton Ulir 10mm".
  - Pelanggan: "Pelanggan Umum", "Proyek Perumahan Griya Indah", "Kontraktor Mitra Bangun".

---

## 4. Fleksibilitas Tema Antarmuka (Jangan Memaksakan Satu Tema)
Antarmuka aplikasi memiliki beberapa domain operasional dengan karakteristik kebutuhan visual yang berbeda. Jangan memaksakan satu tema kaku ke seluruh halaman:
- **Workspace Kasir / POS (Kanonik)**:
  - Karakter: Bersih, ramah mata kasir, kanvas lembut (`#f3f6f4`), kartu putih dengan bayangan halus (*subtle green shadow*), kontrol tombol kapsul penuh (`border-radius: 9999px`), dan layout kartu terstruktur pada keranjang belanja (menghindari tabel sempit yang terpotong).
  - Kontrak alur yang disetujui: katalog kiri dan keranjang kanan pada desktop; barang/jumlah `−/+`, total dan checkout langsung tersedia. Pada ponsel susun katalog lalu keranjang. Popup hanya pembayaran dan tinjau transaksi; membatalkan menjaga keranjang, konfirmasi akhir mengirim satu transaksi. Jangan mengganti POS dengan halaman daftar-nota dahulu atau popup penjualan panjang.
  - Penambahan role, shift, piutang, dan operasional lain mengikuti alur kasir ini. Pertahankan fitur lama yang sudah bekerja; perubahan susunan utama memerlukan persetujuan pemilik, bukan asumsi agen. Sidebar tetap kiri, menjadi drawer kiri di ponsel.
- **Halaman Master Data (Produk, Kendaraan, Akun COA)**:
  - Mengikuti keselarasan visual kanonik Kasir/POS: kanvas rapi, kartu putih teratur, bilah filter fleksibel (*flex-wrap toolbar*), dan status kapsul netral/operasional yang tidak saling bertabrakan.
- **Buku Besar, Jurnal, dan Laporan Keuangan (Ledger Industrial)**:
  - Karakter: Tata letak ledger padat, tabel bergaris presisi (*dense auditable table*), batas kontainer yang rapi, tampilan angka auditable, tanpa ornamen dekoratif berlebihan yang memakan ruang pandang auditor/pemilik.

---

## 5. Kewajiban Riset Referensi Desain
- **Larangan Template Slop**: Dilarang menggunakan komponen default bawaan model AI yang kaku dan usang (misalnya alert box era 2010 dengan border tebal, atau toast dengan ikon lingkaran besar dan tombol OK).
- **Riset Sebelum Mengimplementasikan Komponen**:
  - Bila diminta merancang komponen antarmuka baru (misalnya notifikasi toast, dialog konfirmasi, bilah filter canggih), teliti dan adopsi pola desain antarmuka modern yang terbukti (contoh: pola toast minimalis ala Sonner/Vercel Geist, bilah aksi ala Tailwind UI/Linear).
  - Terapkan animasi yang halus (*slide-up entry*, *slide-out exit*), radius sudut yang luwes, dan tata letak responsif yang telah teruji pada viewport desktop (1440px) maupun mobile (390px).

---

## 6. Kontrak Gerak Permukaan Mengambang (Dropdown, Tanggal, Popup)
Setiap elemen yang muncul di atas halaman wajib memakai **satu animasi yang sama**. Ini berlaku untuk dropdown pilihan, combobox/pencarian, kalender tanggal, flyout menu, popup filter, popup form (dialog), dan komponen serupa yang dibuat di kemudian hari.
- **Animasi masuk (acuan: dropdown pilihan)**: `opacity 0 → 1`, `translateY(-12px) scale(.85) → none`, durasi **0,26 detik**, easing `cubic-bezier(.34, 1.56, .64, 1)`. Implementasi bersama: `@keyframes ui-float-in` dan variabel `--ui-float-in` di `resources/views/v2/ui-dna.blade.php` bagian 16. Komponen baru cukup memakai `animation: var(--ui-float-in)` atau masuk ke daftar selektor di bagian itu; jangan membuat keyframes masuk sendiri.
- **Titik asal**: dropdown, kalender, dan popover tumbuh dari sisi pemicunya (`transform-origin` atas, atau bawah bila terbuka ke atas).
- **Dialog (popup form) berbeda**: naik dan memudar, `translateY(18px) scale(.97) → none`, 0,26 detik, `cubic-bezier(.22,.8,.24,1)` (`--ui-dialog-in`); latar redup memudar 0,22 detik. Keluar: turun dan memudar `translateY(10px) scale(.98)` 0,16 detik.
- **Animasi keluar**: kebalikan singkat, `opacity 1 → 0` dan `none → translateY(-12px) scale(.85)` selama **0,14 detik** (`cubic-bezier(.4, 0, 1, 1)`). Elemen tanpa animasi keluar boleh langsung hilang.
- **Posisi tidak boleh memakai `transform`** pada elemen yang dianimasikan, karena animasi akan menimpanya. Gunakan `top/left`, `inset`, atau `margin`.
- **Reduced motion**: di bawah `prefers-reduced-motion: reduce` animasi dimatikan; elemen langsung tampil.

### Pemilih tanggal
- Memilih tanggal di kalender **tidak menutup** kalender. Tombol **Oke** menyimpan pilihan dan menutup. **Tutup**, klik di luar, Esc, atau menggulir halaman menutup **tanpa menyimpan**, dan nilai kembali seperti sebelum kalender dibuka. Berlaku untuk tanggal tunggal maupun rentang di seluruh aplikasi.
- Oke hanya mengisi tanggal; menerapkan filter tetap lewat tombol filter halaman (mis. Terapkan).
- Kolom tanggal yang bisa diketik memakai `data-date-typed`, dan rentang satu kolom memakai `data-date-range-start` / `data-date-range-end` (format `dd/mm/yyyy` dan `dd/mm/yyyy – dd/mm/yyyy`). Tanggal yang diketik dianggap sudah disimpan, tanpa menunggu Oke.
- Rentang tanggal ditampilkan sebagai **satu kolom**, bukan dua kolom Dari/Sampai.

## 7. Pola Halaman Operasional (Tagihan, Pengiriman, Penerimaan, Kas & laporan)
- Judul halaman tampil di topbar (nama menu); tidak ada judul ganda di isi halaman.
- Kartu "Ringkasan ..." di atas: ubin pertama hijau tua (#14532D, label/keterangan lime, angka putih), ubin lain putih berbingkai dengan panel abu muda. Pil status di kanan atas hanya bila ada yang perlu perhatian.
- Kartu daftar: judul 18px, keterangan 13px, kolom cari + chip pil (aktif hijau tua), tabel berkepala hijau tua dengan teks putih, status berupa pil berbingkai (hijau = selesai, kuning = menunggu, merah = belum dibayar, biru = berjalan), aksi baris berupa tombol garis; aksi utama baris boleh hijau penuh.
- Halaman: "Menampilkan a–b dari n" di kiri, Sebelumnya / nomor halaman / Lanjut di kanan.
- Popup formulir: kepala hijau tua (judul lime, subjudul konteks), isi putih, baris aksi di dasar: Batal (garis) kiri dan aksi utama (lime) kanan. Pilihan dua opsi (Tunai/Transfer) memakai segmen, bukan dropdown.
- HP (<=760px): ringkasan dua kolom dengan ubin utama selebar kartu, chip bisa digeser, baris tabel menjadi kartu label-nilai, tombol aksi selebar kartu (44px), popup hampir selebar layar.

## 8. Sistem Gerak Halaman (Kasir, Operasional, Master)
Halus tapi terasa (180–320 ms), satu sistem bersama di `ui-dna` bagian 21 dan `resources/views/v2/ui-motion.blade.php` (`window.uiMotion`). Jangan membuat keyframes masuk sendiri per halaman.
- **Pindah menu**: kartu di panel naik 14px dan memudar bertahap (0,32 dtk, jeda 70 ms per kartu); ubin ringkasan menyusul (jeda 50 ms per ubin).
- **Baris tabel**: muncul bertahap (0,26 dtk, jeda 26 ms, maks 12 baris) setiap halaman, pencarian, atau saringan tabel berganti (event `ui-table:render`).
- **Tombol**: menekan `scale(.96)` saat ditekan; ubin ringkasan yang bisa diklik terangkat 2px saat disorot.
- **Setelah simpan**: form memanggil `uiMotion.markSaved(form)`; setelah data diperbarui di tempat (bagian 19), tabel membuka halaman yang memuat baris itu, menggulir ke baris, lalu menyorotnya hijau muda dengan garis kiri hijau tua (2,2 dtk).
- Hanya `opacity`/`transform`/`scale`/`translate`, dengan `animation-fill-mode: backwards` agar tidak ada transform tertinggal pada panel.
- `prefers-reduced-motion: reduce` mematikan semuanya; sorotan simpan tetap tampil sebagai warna diam.

## 9. Pola Halaman Master (Produk, Supplier & pelanggan, Karyawan & sopir, Mobil, Satuan & kategori)
Sama dengan pola Operasional (bagian 7) ditambah:
- Ringkasan per menu dengan ubin hero = jumlah aktif; pil merah bila ada stok habis, kuning bila ada tagihan belum lunas.
- Tombol kepala tabel di kanan: "+ Tambah ..." hijau tua, Impor Excel dan Unduh template bergaris.
- Aksi baris: Ubah bergaris hijau, Arsipkan bergaris merah lembut (bukan merah penuh, karena mengarsipkan bisa dibatalkan dan tidak menghapus riwayat).
- Popup tambah/ubah/impor memakai tema popup Operasional: kepala hijau tua, judul lime, nama data di subjudul, Batal kiri dan Simpan lime kanan.

## 10. Pola Halaman Keuangan (Jurnal umum, Neraca, Laba rugi, Arus kas, Bagan akun)
Lapis gaya di `resources/views/v2/finance-dna.blade.php`, di-include di akhir isi kelima halaman. Gaya ini ikut terbawa saat halaman dimuat ke panel workspace maupun saat dibuka penuh, dan tidak menambah ukuran HTML halaman lain.
- Kartu putih berbingkai #E4EBE6 radius 12; judul kartu 20px; breadcrumb disembunyikan karena judul sudah ada di topbar.
- Ubin ringkasan: ubin pertama hijau tua; ubin hasil (`is-result`) hijau muda; ubin rugi (`is-alert`) merah muda.
- Saringan tanpa kotak: periode cepat berupa chip pil (aktif hijau tua), isian 44px, Tampilkan/Terapkan hijau tua, Reset bergaris.
- Tabel laporan berkepala hijau tua; baris judul kelompok hijau tebal; subtotal abu muda; total utama (`grand`) hijau muda.
- Angka memakai Inter tabular, bukan monospace.
- Jurnal umum: satu transaksi = satu kartu, kepala abu muda (tanggal, uraian, pil Seimbang), baris Total hijau muda. Kepala kolom di dalam kartu sengaja terang supaya kartu yang berulang tidak berat.
- Popup Tambah/Ubah akun memakai tema popup Operasional.
- HP: ubin 2 kolom dengan ubin utama selebar kartu; baris Bagan akun menjadi kartu label-nilai dengan tiga tombol sama lebar.

## 11. Pola Form Input Master (tambah, ubah, impor)
Isian dibangun dari `App\Support\V2\FormCatalog` + `<x-ui.field>`; gaya di `ui-dna` bagian 20, perilaku di `v2/ui-forms.blade.php`.
- Form dengan lebih dari satu bagian: tiap bagian berupa kartu abu muda dengan nomor langkah (1, 2, 3), judul, dan keterangan singkat.
- Label di atas kontrol. Kolom wajib bertanda bintang merah, kolom tidak wajib berlabel "Opsional". Subjudul popup tambah menjelaskan tujuan form.
- Petunjuk satu baris di bawah kolom (mis. "Modal per satuan."). Petunjuk, keterangan bagian, dan label Opsional hanya muncul di form tambah, karena form ubah dirender sekali per baris data dan halaman Kasir dibatasi di bawah 2,5 MB.
- Semua kontrol setinggi 46px. Angka uang memakai awalan Rp dan stok memakai akhiran satuan dalam satu kotak. Untung atau rugi per satuan tampil sebagai pil hijau atau merah.
- Kode otomatis memakai Inter tebal hijau, bukan monospace.
- Pesan salah tampil di bawah kolomnya dalam bahasa Indonesia ("Wajib diisi.", "Pilih salah satu."), dengan bingkai merah. Kolom wajib pertama yang salah difokuskan, dan pesannya hilang begitu diperbaiki.
- Saat popup dibuka, fokus langsung ke kolom wajib pertama yang masih kosong.
- Klik di luar popup tidak menutupnya bila isian sudah diubah; popup hanya bergetar halus. Batal dan Esc tetap menutup.
- Pilihan tetap yang sedikit memakai tombol pilihan cepat (Jenis mobil) atau segmen (Jenis sopir). Catatan memakai penghitung karakter.
- Impor Excel: tiga langkah (Unduh template, Isi, Unggah), lalu kotak unggah bergaris putus-putus yang bisa diklik atau menerima berkas yang diseret. Nama dan ukuran berkas tampil setelah dipilih.

## 12. Kasir HP (<=760px): bar keranjang dan lembar bawah
Lapisan tampilan `v2/pos-mobile.blade.php`, di-include setelah `pos-cashier-script`. Semua aksi memakai kontrol asli (stepper, hapus, nominal), jadi perhitungan tetap milik skrip kasir. Angka ringkasan dibaca dari event `kasir:cart` (`count`, `qty`, `gross`, `discount`, `total` dalam sen BigInt).
- Bar keranjang hijau tua menempel di bawah layar: ikon dengan lencana jumlah jenis, "N jenis · M barang", total, dan tombol lime "Lihat keranjang". Bar hanya tampil bila keranjang berisi.
- Barang yang sudah ada di keranjang menampilkan stepper − qty + di baris katalog. Tombol − pada qty 1 menghapus barang.
- Keranjang dan Pembayaran tampil sebagai lembar bawah dengan pegangan, kartu barang putih, dan panel total (Subtotal, Diskon merah, Total belanja). Diskon baris tampil sebagai pil "Diskon −Rp x" + "Ubah diskon".
- "Kosongkan keranjang" harus ditekan dua kali (dalam 3 detik).
- Pembayaran: sakelar "Antar dengan armada toko" mengisi Cara penyerahan, dan untuk tunai tersedia pilihan nominal cepat (Uang pas, kelipatan 50 rb, kelipatan 100 rb). Tombol Kembali membuka lembar keranjang lagi.
- Saat lembar terbuka, `view-transition-name` milik `main` dilepas, karena konteks tumpukannya membuat latar gelap menutupi lembar.

## 13. Pengawasan · Ringkasan (admin)
Ada di `v2/admin-summary.blade.php` dengan gaya dan skrip di `admin-summary-style`. Data berasal dari `MonitorMetrics`, `PosDashboard` (semua kasir), dan `AdminSummary` (kas laci shift terbuka, saldo akun 1102, piutang lewat tempo, daftar pantau stok). Semuanya hanya membaca.
- Urutan halaman: tanggal + pilihan periode (Hari ini / 7 hari / 30 hari, tersimpan di localStorage), Perlu ditindaklanjuti, ubin ringkasan, grafik penjualan + produk terlaris, lalu Uang, Pengiriman, dan Stok.
- Periode hanya mengubah penjualan, grafik, dan produk terlaris. Saldo selalu dihitung per hari ini.
- Perlu ditindaklanjuti hanya menampilkan butir yang jumlahnya lebih dari 0. Bila semuanya 0, kartu berubah menjadi "Semua beres".
- HP: tindak lanjut menjadi daftar berpanah, ubin tersusun 1 + 2×2 (ditambah ubin Penerimaan), terlaris 5 besar dengan tombol "Lihat 10 besar". Uang terbuka, sedangkan Pengiriman dan Stok tertutup dengan ringkasan di kanan.

## 14. Harga kontraktor
- Data: `products.contractor_price` (boleh kosong; kosong berarti memakai harga jual), `v2_parties.price_tier` (`retail`/`contractor`, khusus pelanggan), dan `v2_sales.price_tier` sebagai jejak harga yang dipakai di nota. Migrasi: `2026_11_07_000001_add_v2_contractor_pricing`.
- Server (`Operations::checkout`) menentukan harga dari `price_tier` yang dikirim kasir. Bila tidak dikirim, harga mengikuti penanda pelanggan. Produk tanpa harga kontraktor tetap memakai harga jual. Jurnal tidak berubah karena mengikuti total nota.
- Master Produk: kolom "Harga kontraktor" (Opsional) dengan pil untung/rugi terhadap HPP. Angka 0 ditolak; kosongkan bila sama dengan harga jual. Di daftar, harga kontraktor tampil di bawah harga jual.
- Master Pelanggan: pilihan "Harga di kasir" (Harga eceran / Harga kontraktor). Pelanggan kontraktor diberi lencana hijau "Kontraktor" di daftar.
- Kasir: tombol Eceran/Kontraktor ada di kartu pembeli (latar hijau tua, tombol aktif lime) dan di Pembayaran ("Tipe pelanggan", tombol aktif hijau). Memilih pelanggan kontraktor langsung mengaktifkan Kontraktor, dan kasir tetap boleh mengganti. Harga kontraktor ditandai pil hijau muda di keranjang. Di katalog, harga kontraktor tebal dengan harga jual dicoret di bawahnya. Tinjau Pembayaran menampilkan baris "Harga: Harga kontraktor".

## 15. Kasir di tablet
- 761–999px: katalog selebar layar. Keranjang muncul lewat bar mengambang di kanan bawah lalu laci dari kanan (lebar maksimal 460px, di atas kepala aplikasi). Baris katalog yang sudah masuk keranjang menampilkan stepper (kolom Aksi 136px). Pembayaran tetap berupa dialog di tengah. Ringkasan penjualan: ubin 2×2, pilihan periode dan status shift dalam satu baris.
- 1000–1100px (tablet mendatar): katalog dan keranjang berdampingan (keranjang 360px, menempel saat digulir). Kolom Stok/Harga/Aksi lebarnya tetap, sisa lebar untuk nama barang, dan keterangan SKU dipotong satu baris.
- 761–1100px: tinggi ubin ringkasan mengikuti isinya (tidak meluap) dan tautan "Lihat stok" pindah ke bawah ubin.
- Batas 1000px dipilih karena di bawahnya kolom nama barang menjadi lebih sempit dari 120px bila keranjang ikut berdampingan.

## 16. Hasil proses: popup penjualan dan pemberitahuan

Berkas: `resources/views/v2/ui-result.blade.php` (CSS `#ui-result-dna`, kelas `ur-*`; API `window.uiResult`).

- Penjualan berhasil: dialog `ur-dialog` di tengah (HP: lembar bawah). Isi: lencana centang lime yang menggambar sendiri, nomor nota, total besar, ringkasan Pembeli, Metode, Dibayar, Kembalian (bila ada), Status (pil Lunas/Dibayar sebagian/Belum lunas), dan nomor surat jalan bila dikirim. Aksi: Cetak nota, Surat jalan (bila dikirim), Penjualan berikutnya (utama, fokus awal). Menutup dialog (Esc, klik latar, tombol) menyiapkan transaksi berikutnya tanpa memuat ulang: keranjang dan form dikosongkan, stok katalog dikurangi sesuai nota, ringkasan dan riwayat diperbarui lewat `uiLive`. Transfer menampilkan catatan menunggu verifikasi admin.
- Proses lain (buka/tutup shift, pembayaran, persetujuan, pengiriman, uang muka, ritase, penerimaan, saldo awal, impor, master, akun login): toast di kanan atas bawah kepala aplikasi (HP: di atas bar bawah). Muncul otomatis dari pembungkus `fetch` berdasarkan alamat dan metode permintaan (daftar `RULES`); tidak ada handler yang perlu diubah. Hanya jawaban sukses yang memunculkan toast. Penanda di `sessionStorage` (`ur-pending`) membuat toast tampil lagi tanpa animasi setelah halaman dimuat ulang (maks 3 detik sesudah dibuat).
- Nada: hijau untuk sukses, kuning untuk perhatian (shift ditutup dengan selisih, pembayaran ditolak). Toast sukses 5,2 detik, perhatian 8 detik; berhenti saat kursor atau fokus di atasnya; tombol tutup 44px. Memakai `popover="manual"` agar berada di atas dialog modal.
- Gerak: dialog `--ui-dialog-in`, lencana memantul, toast geser masuk; semua mati saat reduced-motion.
- Untuk menambah toast bagi rute baru, tambahkan satu baris di `RULES` dan uji di `tests/ui-result.test.mjs`.

## 17. Popup Catat barang masuk (Penerimaan)

- Popup memakai lebar `popup-wide` (860px). Pada 520px kolom Barang runtuh jadi 30px karena empat kolom lain bersisa tetap (92/128/112/44px). Di bawah 720px baris otomatis jadi dua tingkat (Barang penuh, lalu Jumlah dan HPP).
- Kalimat pengantar tampil sekali, di kepala popup. Salinannya di badan form dihapus saat popup dibuat.
- Kartu `section.card` pembungkus form tidak lagi digambar di dalam popup (tanpa bingkai ganda, bar Batal/Simpan menempel penuh ke tepi). Aturan ini berlaku untuk semua popup `input-popup-*` (Penerimaan, Biaya ritase, Saldo awal).
- Pilihan supplier satu baris dengan elipsis (tidak membungkus jadi dua baris). Tombol hapus baris 44px.
- Pemilih barang berupa kolom pencarian (`ui-pick`, ikon kaca pembesar, placeholder "Cari nama atau SKU barang"), bukan daftar turun, karena produk bisa ribuan. Sumber pilihan: `GET /v2/suppliers/{id}/products?q=` (admin; 30 teratas; hanya barang aktif yang tertaut ke supplier aktif). Daftar pilihan `position:fixed` agar tidak terpotong dialog dan membalik ke atas bila ruang bawah sempit. Tinggi daftar maksimal tepat 6 pilihan (dihitung dari tinggi baris sebenarnya); lebih dari itu digulir, kurang dari itu mengikuti isi. Tiap pilihan dua baris: nama dan satuan, lalu SKU, stok, HPP saat ini. Barang yang sudah dipilih di baris lain redup dan tidak bisa dipilih. HPP saat ini juga tampil sebagai petunjuk di kolom HPP satuan (bukan isian otomatis).
- Efisiensi: daftar produk tidak lagi disematkan di templat baris, jadi HTML halaman tidak membesar mengikuti jumlah produk (uji skala ribuan produk turun dari 2413 KB ke 2108 KB).

## 18. Kalender: tanggal tunggal dan rentang

Berkas: `resources/views/v2/shared-date-picker.blade.php` (dan salinan lama `partials/themed-date-picker.blade.php`).

- Satu tanggal (mis. Tanggal terima, tanggal transaksi): kalender ringkas satu bulan, lebar 344px. Judul bulan ("November 2026") ada di antara panah, tanpa judul bulan kedua. Baris aksi: Hapus, Hari ini, Tutup, Oke, masing-masing satu baris.
- Rentang (tanggal mulai dan tanggal akhir, mis. filter riwayat): kalender dua bulan berdampingan, lebar 620px, judul "Pilih rentang tanggal" dengan ringkasan rentang.
- Pilihan ditentukan otomatis: bila form memiliki pasangan `start_date` dan `end_date` (atau kolom rentang `data-date-range-start`) kalender rentang dipakai, selain itu kalender satu tanggal. Berlaku untuk semua form yang memakai pemilih ini.
- Di HP (<=720px) keduanya selebar layar dikurangi 24px dan hanya satu bulan yang tampil.

## 19. Simpan tanpa muat ulang halaman (uiLive)

Berkas: `resources/views/v2/ui-live.blade.php` (dimuat di `<head>` sebelum skrip lain). Aturan: setelah simpan, halaman TIDAK dimuat ulang.

- Setelah simpan berhasil, form memanggil `uiLive.saved(form)`: kunci form dibuka, dialognya ditutup, form tambah dikosongkan, lalu `uiLive.refresh()` mengambil halaman yang sama di latar belakang dan menyalin datanya ke halaman yang sedang terbuka. Panel aktif, gulir, saringan, dan halaman tabel tetap.
- Yang disalin: isi `<tbody>` diganti utuh (lalu `ui-tables` membaca ulang baris lewat `wrap._uiTableReload`), teks/angka lain disamakan di tempat (kartu ringkasan, status, kepala aplikasi), pilihan `<select>` disamakan (nilai terpilih tetap), dan nilai bawaan form tambah (mis. kode berikutnya PRD-0005) diperbarui. Wadah `[data-live-list]` (daftar form berulang) diganti utuh. Isian pengguna di form tidak ditimpa.
- `[data-live-skip]` menandai elemen yang diatur skrip (katalog dan keranjang Kasir, status pencarian, judul halaman di kepala, total halaman riwayat penerimaan). Elemen seperti ini wajib diberi tanda itu agar tidak ditimpa teks dari server.
- Perilaku untuk isi baru dipasang ulang lewat `uiLive.onPatch(fn(root))`: label kolom HP dan popup form baris (`ui-workspace`), form master (`ui-forms`), sopir master dan kunci transaksi (`sprint2`), kunci tagihan (`workspace`). Penangan submit form di baris tabel dipasang di tingkat dokumen (delegasi), bukan per form.
- Kasir: `kasirNextSale()` mengurangi stok katalog sesuai nota yang sudah dikonfirmasi server, mengosongkan keranjang dan form, membuat kunci transaksi baru, lalu memperbarui ringkasan.
- Bila halaman gagal diambil (sesi habis, server mati), barulah halaman dimuat ulang penuh sebagai cadangan.
- Uji: `tests/ui-live.test.mjs` menolak `location.reload()` baru di `resources/views/v2`. Untuk elemen yang diatur skrip, uji `uiLive.refresh()` tanpa perubahan data: teks semua panel harus sama sebelum dan sesudah.

## 20. Penerimaan: tinjau sebelum simpan dan pembatalan

- Form Catat barang masuk: tombol utama **Periksa penerimaan**. Ringkasan tampil di popup yang sama (bagian isian disembunyikan): supplier, tanggal, daftar barang (jumlah × harga = nilai, HPP lama → baru), total utang besar hijau, tombol Ubah lagi dan Simpan draf penerimaan (44px). Fokus pindah ke judul ringkasan.
- Harga di bawah ½ atau di atas 2× HPP saat ini: baris berlatar kuning muda dengan garis kiri kuning tua dan teks peringatan cokelat; tidak memblokir simpan.
- HP: baris ringkasan bertumpuk (nama, hitungan, HPP), tombol berbagi lebar.
- Penerimaan final terkunci (bagian 26); pembatalan hanya pada draf. Baris yang sudah dibatalkan sebelum aturan baru tetap menampilkan lencana merah Dibatalkan + waktu · admin · alasan, angka dicoret abu.

## 21. Kolom nominal uang

Berkas: `resources/views/v2/ui-money.blade.php` (dimuat di `<head>` ruang kerja, setelah `ui-live`).

- Kolom uang diberi `data-money` (V2) atau `data-currency-input` (halaman lama yang dimuat di panel Keuangan). Saat mengetik, titik ribuan muncul sendiri (1.250.000); desimal pakai koma. Titik yang diketik pengguna diabaikan karena titik selalu pemisah ribuan. Posisi kursor dijaga.
- Tidak ada isian 0 bawaan: kolom yang dulu berisi 0 kini kosong dengan placeholder 0, dan bila dibiarkan kosong tetap dikirim 0 (kolom itu tidak lagi wajib diketik). Kolom tanpa 0 bawaan (kas awal, uang fisik, nominal bayar) tetap wajib.
- Nilai yang dibaca skrip (`.value`) dan yang dikirim (FormData maupun submit biasa) tetap angka mentah (1250000 / 1250000.5), sehingga server dan perhitungan tidak berubah.
- Dibayar di Kasir memakai `data-money-display` (hanya format, skrip Kasir tetap mengurai sendiri) dan kosong bila nol.
- Kolom master produk (`data-ui-number`) sudah punya format sendiri dan tidak disentuh.
- Uji: `tests/ui-money.test.mjs`.

## 22. Popup bayar tagihan

- Satu popup untuk piutang pelanggan dan utang supplier, dibuka dari tombol di kolom Aksi tabel Tagihan: judul sesuai aksi (Catat pembayaran / Bayar ke supplier / Ajukan pembayaran), subjudul nama · nomor, ringkasan Total/Terbayar/Sisa (Sisa hijau tua), Jumlah dibayar (kosong, format ribuan), tombol sekunder lebar penuh **Lunasi Rp …** di bawahnya, Metode sebagai tombol pilihan Tunai/Transfer, Referensi, petunjuk akibat sesuai arah dan peran, tombol utama lime.
- Bagian terpisah "Pengajuan pembayaran supplier" dihapus.

## 23. Audit popup formulir (standar yang berlaku)

Seluruh popup ruang kerja (35 jenis) diperiksa di 1440 dan 390. Aturan yang ditetapkan:

- Footer selalu Batal (kiri, sempit) + aksi utama lime (kanan, lebar). Tombol di dalam bagian `[data-popup-skip]` (mis. tinjau penerimaan) tidak dihitung sebagai tombol kirim utama, sehingga Periksa penerimaan ikut pindah ke footer.
- Form sopir (Kirim barang, Bayarkan uang muka, Realisasi uang muka) di popup memakai grid dua kolom selebar popup; satu kolom di bawah 560px. Label `<label>Teks<select>` dipaksa satu kolom penuh (`grid-template-columns:minmax(0,1fr)`), karena tanpa itu pilihan menyusut selebar teksnya.
- Realisasi uang muka menghitung langsung "Uang muka − biaya = sisa dikembalikan sopir" (kuning bila biaya melebihi uang muka). Tombol "Simpan realisasi"; judul popup lewat `data-popup-title`.
- Kunci transaksi otomatis (idempotency) tidak ditampilkan sama sekali.
- Tolak pengajuan menampilkan nominal Rupiah, arah (Bayar ke/Dari), nama, nomor tagihan, metode, dan pengaju; bukan "Pembayaran #2 · 100000.00".
- Buka shift: tanpa label kecil ganda; pilihan "Tidak membawa sisa" (bukan "tanpa carry").
- Form Tambah master: kolom harga/stok minimum kosong (bukan 0); nilai yang dikirim tetap 0.
- HP: tombol tutup popup, tombol Lunasi, dan saran jenis mobil minimal 44px. Selektor penimpa memakai `:is(…,#u#u#u)` karena aturan tema berbobot ID.

## 24. Peringatan entri dobel

- Bila server menolak dengan `duplicate_payment` atau `duplicate_receipt`, kotak pesan form berubah kuning (`form.is-duplicate-warning`), bukan merah, dan tombol simpan berganti **Ya, tetap catat** / **Ya, tetap simpan** (`window.uiConfirmDuplicate`). Label tombol kembali saat popup ditutup atau supplier/nomor nota diganti.
- Di tinjauan penerimaan, pesan dipindah tepat di atas tombol Ubah lagi / simpan; tombol simpan tinjauan memakai lime seperti aksi utama lain.
## 25. Kontinuitas gerak: tidak ada yang muncul tiba-tiba

Aturan: elemen yang **tidak punya asal** masuk dengan animasi; elemen yang **punya asal** memakai morph (objek yang sama berpindah/berubah, isinya berganti mulus). Semua mati saat `prefers-reduced-motion`. Kode: `resources/views/v2/ui-motion.blade.php` (`window.uiMotion`).

- **Kartu sub-menu rail**: buka = keluar dari arah ikon grup (geser kanan + skala, titik asal di tengah ikon); pindah ke grup lain = kartu yang sama meluncur ke posisi baru dan berubah ukuran (`morphBox`, FLIP 300 ms), isi lama memudar 110 ms sementara isi baru naik bertahap; tutup = keluar 140 ms (ease-in) setelah jeda 170 ms. Klik tautan, ubah ukuran jendela, dan pin menutup seketika.
- **Penanda pilihan meluncur** (`slidePill`): segmen (Tunai/Transfer), chip saringan, tab, pilihan periode/harga (`[role=radiogroup]`, `[role=tablist]`, `.ui-chips`, `.ui-segment`, `.ui-subtabs`, `.pd-seg`, `.period-presets`). Lapisan sementara bergerak dari pilihan lama ke baru (240 ms); warna teks bertukar di tengah jalan supaya selalu terbaca. Dipicu otomatis dari perubahan `aria-checked/pressed/selected` atau kelas `active`, tanpa mengubah penangan.
- **Popup berubah tinggi** (galat muncul, tinjau penerimaan, bagian form bersyarat): tinggi popup bertransisi 240 ms. Tema mengunci `height:fit-content !important`, jadi dipakai transisi tinggi inline `!important`, bukan WAAPI.
- **Bagian yang muncul** di dalam popup atau kotak galat/sukses di form (`hidden` dilepas): pudar-turun 220 ms (`reveal`). Baris tabel, daftar pilihan, kalender, dan panel punya animasinya sendiri dan dikecualikan.
- **Judul halaman** di kepala pudar-naik saat pindah menu; warna grup aktif di rail bertransisi.
- Uji gerak: ukur posisi/tinggi tiap frame (`requestAnimationFrame`) lalu periksa rangkaian screenshot; angka transform saja bukan bukti.

### 25.1 Popup bayar supplier: tiga sumber dana

- Kolom **Jumlah dibayar** tetap selebar baris; kolom **Sumber dana** diletakkan satu baris penuh di bawahnya (`.collect-source`) berisi tiga segmen: Kas Utama, Bank Utama, Laci shift. Jangan menaruhnya di separuh baris: tiga label tidak muat dan teks terlipat di 1440 maupun 390.
- Petunjuk di bawah form menyebut bedanya: Kas Utama dan Bank Utama memakai saldo toko dan tidak mengubah laci; Laci shift memakai uang laci yang sedang terbuka.
- Daftar pembayaran memakai judul kolom **Sumber dana** dengan label Kas Utama, Bank Utama, Laci shift (bukan lagi "Metode").

## 26. Penerimaan: draf dan konfirmasi final

- Daftar Draf penerimaan terpisah dari Riwayat penerimaan final. Badge Draf menjelaskan "Belum masuk stok/utang"; draf batal tetap terlihat dengan alasan.
- Form penerimaan mempertahankan tinjau dalam popup yang sama, tetapi tombol akhir menjadi "Simpan draf penerimaan"; petunjuk tidak boleh mengklaim stok/utang sudah berubah saat draf disimpan.
- Konfirmasi dari baris draf memakai popup bersama, rincian barang/total, peringatan dokumen akan dikunci, checkbox pemeriksaan wajib, dan tombol "Konfirmasi dan kunci". Tidak menambahkan konfirmasi native browser.
- Penerimaan final menampilkan badge Dikonfirmasi dan teks Terkunci, tanpa tombol Batalkan penerimaan. Pembatalan hanya pada draf dan meminta alasan.
- Gerak memakai dialog/morph bersama yang sudah ada; pembaruan daftar lewat uiLive. Layout dan target sentuh diperiksa di 1440/390.
## 27. Rekap otomatis saat menutup shift

- Popup Tutup shift memuat kartu rekap (read-only) sebelum kolom "Uang fisik di laci": Penjualan (nota, total, tunai/transfer/kredit) dan Laci (kas awal, masuk, keluar, seharusnya di laci), serta peringatan bila ada pengajuan tunai yang belum disetujui.
- Saat admin mengetik hasil hitung, muncul baris status: hijau "Cocok dengan perkiraan Rp …" atau kuning "Selisih lebih/kurang Rp …". Angka dihitung dalam sen (BigInt), bukan float.
- Penutupan mengirim fingerprint rekap; server menolak bila rekap sudah berubah sejak popup dibuka (meminta tutup ulang), tidak menimpa data.
- Rekap memakai endpoint GET read-only `/v2/shifts/{id}/closing-summary`; layout dan target sentuh diperiksa di 1440/390.

## 28. Shift toko sinkron admin dan kasir

- Status shift di kepala Ringkasan penjualan dan header membaca shift toko yang sama. Kasir tidak melihat form buka/tutup; hanya admin yang mengelola. Identitas pembuka ditampilkan sebagai "Dibuka oleh", bukan dianggap kasir pelaku semua nota.
- Status "Menunggu admin membuka shift toko", "Shift toko #… aktif", dan kegagalan pemeriksaan diperbarui dari endpoint kecil saat tab terlihat atau kembali fokus. Perubahan status tidak memuat ulang halaman, tidak mengosongkan keranjang, dan tidak mengganti nilai uang. Checkout ditahan ketika shift belum aktif atau status tidak dapat diperiksa.
- Pada ponsel, judul Ringkasan penjualan, status shift, dan segmen periode disusun tiga baris penuh. Pesan shift panjang tidak boleh mempersempit judul hingga terpotong per huruf; permukaan status memiliki target sentuh minimal 44px.
- Ringkasan laci diberi label "Laci toko bersama". Ringkasan penjualan, grafik dan produk terlaris di dashboard POS memakai data seluruh toko yang sama untuk admin/kasir pada periode yang sama; riwayat serta laporan per petugas tetap sesuai hak akses. Admin menutup dengan rekap seluruh petugas pada shift yang sama. Semua dialog tetap memakai perilaku/gerak bersama dan pemutakhiran uiLive.
