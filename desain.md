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
- **Setelah simpan**: form memanggil `uiMotion.markSaved(form)` sebelum muat ulang; setelah halaman dimuat, tabel membuka halaman yang memuat baris itu, menggulir ke baris, lalu menyorotnya hijau muda dengan garis kiri hijau tua (2,2 dtk).
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
