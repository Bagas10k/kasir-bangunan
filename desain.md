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
- Oke hanya mengisi tanggal; menerapkan filter tetap lewat tombol filter halaman (mis. Terapkan). Pengecualian ringkasan Kasir: tombol Tanggal khusus langsung membuka kalender dan Oke sekaligus menerapkan rentang.
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
- Pilihan tetap yang sedikit memakai dropdown (Jenis mobil) atau segmen (Jenis sopir). Catatan memakai penghitung karakter.
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
- Pemilih barang berupa kolom pencarian (`ui-pick`, ikon kaca pembesar, placeholder "Cari nama atau SKU barang"), bukan daftar turun, karena produk bisa ribuan. Sumber pilihan: `GET /v2/suppliers/{id}/products?q=` (admin; 30 teratas; hanya barang aktif yang tertaut ke supplier aktif). Daftar pilihan `position:fixed` agar tidak terpotong dialog dan membalik ke atas bila ruang bawah sempit. Tinggi daftar maksimal tepat 6 pilihan (dihitung dari tinggi baris sebenarnya); lebih dari itu digulir, kurang dari itu mengikuti isi. Tiap pilihan dua baris: nama dan satuan, lalu SKU, stok, HPP saat ini. Barang yang sudah dipilih di baris lain redup dan tidak bisa dipilih. HPP positif saat ini mengisi HPP satuan sebagai nilai bawaan yang dapat diedit, tanpa menimpa isian manual.
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
- Penanda kas harian memakai tanggal, bukan nomor #…: "Kas harian YYYY-MM-DD aktif", "sudah ditutup", atau "Menunggu admin membuka kas harian". Pembaruan status tetap ringan saat tab terlihat atau kembali fokus, tanpa reload/kehilangan keranjang. Sesudah ditutup pada tanggal yang sama, jangan meminta pembukaan ulang. Form admin tetap ada di DOM agar penangan tidak hilang; fieldset dinonaktifkan dan pemicu popup disembunyikan sampai tanggal baru tersedia.
- Pada ponsel, judul Ringkasan penjualan, status shift, dan segmen periode disusun tiga baris penuh. Pesan shift panjang tidak boleh mempersempit judul hingga terpotong per huruf; permukaan status memiliki target sentuh minimal 44px.
- Ringkasan laci diberi label "Laci toko bersama". Ringkasan penjualan, grafik dan produk terlaris di dashboard POS memakai data seluruh toko yang sama untuk admin/kasir pada periode yang sama; riwayat serta laporan per petugas tetap sesuai hak akses. Admin menutup dengan rekap seluruh petugas pada shift yang sama. Semua dialog tetap memakai perilaku/gerak bersama dan pemutakhiran uiLive.

## 29. Riwayat POS bersama per hari — revisi tahap1

- Riwayat admin/kasir sama pada tanggal dan filter yang sama, dengan awal hari ini. Nama petugas tetap di bawah invoice. Riwayat hari itu tidak hilang setelah laci ditutup. Laporan harian per petugas dan izin aksi admin tetap terpisah.
- Dropdown Shift aktif/Semua shift/nomor shift dihapus. Kontrol yang tersisa: invoice, pembayaran, rentang tanggal dan jumlah baris. Reset kembali ke hari ini dari tanggal server, sekalipun halaman melewati tengah malam. Galat Reset mempertahankan isian semula.
- Asal kas harian tetap disimpan sebagai metadata. Tampilan ringkas mengikuti bagian29.1: tanggal/jam/petugas di bawah nota, bukan kolom tanggal dan kategori berulang. Warna/font dan target sentuh minimal44px mengikuti komponen bersama.
- Hasil riwayat mempunyai status pemuatan dan galat sendiri. Terapkan, Reset dan pagination tidak mengganti halaman/keranjang atau menimpa draft isian. Galat tidak menghapus hasil sebelumnya. Penggantian hasil dilakukan pada area aria-live, sementara uiLive umum melewati komponen ini dan memicu pembacaan ulang dengan filter yang tetap.
- Penamaan menu, header, status, popup buka/tutup, saldo bawaan dan pemeriksaan menggunakan Kas harian dengan penanda tanggal. Nomor internal pada atribut/rute tetap untuk relasi dan audit. Hanya satu kali buka–tutup per tanggal; catatan lama tidak digabung secara finansial. Tidak menambah menu bank atau mengubah rumus uang/stok/jurnal.

### 29.1 Penyederhanaan riwayat transaksi

- Riwayat langsung berupa daftar nota, awalnya hari ini. Bagian atas hanya pencarian nomor nota, Cari, dan tombol Filter. Tanggal, pembayaran, jumlah baris, Tampilkan dan Hari ini masuk panel Filter bersama (bagian30).
- Markup asli tetap tersedia tanpa JavaScript; dengan JavaScript, disclosure Filter tambahan dilebur ke panel bersama, bukan dibuat dua lapis popup.
- Tabel lima kolom: Nota, Pembayaran, Total, Status, Dokumen. Tanggal/jam dan petugas menjadi satu keterangan di bawah nomor nota. Kategori kas harian tidak menjadi kolom tersendiri; metadata asal tetap tersedia untuk aksesibilitas.
- HP memakai daftar dua kolom dengan nota/total, pembayaran/status, lalu tautan dokumen. Hilangkan petunjuk berulang; pertahankan satu keterangan cakupan tanggal dan semua petugas. Pencarian, reset, pagination, dan uiLive tetap memakai fragmen tanpa mengubah keranjang.

## 30. Filter tabel bersama — pencarian + panel ringkas

Berkas: `partials/table-filter-styles.blade.php` (kedua cangkang), `partials/filter-disclosure-script.blade.php` (`window.uiTableFilters`).

- Berlaku pada toolbar tabel V2 (master, operasional), katalog POS, riwayat POS, filter tabel halaman lama, jurnal/COA, periode laporan keuangan, dan tanggal laporan harian. Tabel yang hanya memiliki pencarian tidak diberi tombol Filter kosong. Periode grafik/dashboard bukan filter tabel dan tetap pada tempatnya.
- Di luar panel: pencarian, Cari bila form GET memerlukannya, satu tombol Filter, dan aksi Tambah/Impor/Muat ulang yang sudah ada. Tidak ada lagi deretan panjang dropdown/chip. Tombol Filter memuat jumlah pilihan; status pilihan dapat tampil sebagai keterangan pendek.
- Panel nonmodal putih maksimal384px, radius14px, border lembut dan bayangan ringan; header judul/keterangan singkat + tutup44px, isi vertikal berlabel, aksi di dasar. Pilihan status berupa baris penuh, indikator pilihan tetap berbentuk teks dan penanda. Tidak membuat dialog/popup pembayaran baru.
- Panel mengikuti pemicu, membalik ke atas bila perlu, berjarak12px dari tepi viewport dan memiliki gulir internal. Memakai Popover API manual bila tersedia agar tidak terpotong kartu; fallback fixed tetap mempertahankan posisi terhadap ancestor ber-transform.
- Enter/Space membuka lewat tombol native. Esc menutup dan mengembalikan fokus; Tab keluar/klik luar menutup tanpa mengubah pilihan. Kalender/select anak tidak ikut menutup induk; Esc menyelesaikan anak terlebih dahulu. Menutup induk juga menutup picker anaknya agar tidak tertinggal di top layer.
- Node kontrol hanya dipindah, bukan disalin: ID/nama, form owner, nilai, validasi, penangan filter, izin, dan query lama tetap. Chip/live filter tetap langsung menyaring; form server tetap lewat Terapkan/Tampilkan. Riwayat menutup panel setelah respons berhasil, bukan saat request dimulai/gagal. Reset Hari ini menyamakan tanggal dasar badge dengan tanggal server.
- Komponen dipasang sekali, juga pada panel Keuangan yang datang belakangan dan pembaruan uiLive. Panel yang ditinggalkan ditutup. Tidak menambah polling, request filter baru, penyimpanan pilihan baru, atau dependensi.
- **Gerak tidak diganti**: masuk `animation:var(--ui-float-in)` (bagian6), titik asal mengikuti sisi pemicu, posisi memakai top/left bukan transform; `prefers-reduced-motion` menonaktifkannya. Tidak menambah keyframes tersendiri. Grup `.ui-chips`/`.period-presets` tetap memakai `slidePill` dan baris tabel tetap memakai sistem gerak bersama. Animasi keluar boleh langsung hilang sesuai bagian6.
- Referensi interaksi: Radix Popover (penempatan, collision, fokus dan Escape), ditelaah21Nov2026; implementasi tetap vanillaJS yang sudah dipakai proyek, tanpa memasang Radix.

## 31. Bagan akun — input manual operasional

- Tambah/Ubah tetap memakai popup COA dan tema/gerak Keuangan yang ada. Pada mode operasional, form mengirim ke jalur V2 khusus admin, bukan membuka kembali mutasi arsip lama.
- Akun non-inti memakai Arsipkan, bukan Hapus. Akun inti tetap memiliki penanda; larangan mengubah kode/nonaktif diperiksa server. Riwayat nama akun di jurnal tidak diganti.
- Simpan menahan kirim ganda sampai respons selesai. Galat ditulis sebagai teks pada form dan mempertahankan isian. Setelah berhasil, popup ditutup dan hanya isi Bagan akun diperbarui: dokumen/keranjang tidak dimuat ulang, filter terapan dan draft tetap.
- Berhasil menyimpan tetapi gagal memperbarui daftar diberitahukan sebagai dua hasil berbeda. Respons simpan lama tidak boleh menutup popup atau menimpa menu lain yang sudah dibuka. Cache panel Keuangan diinvalidasi sesudah mutasi sehingga data lama tidak muncul lagi saat kembali.
- Tidak menambah animasi sendiri; panel memakai `uiMotion.enter`, popup mengikuti lapis Keuangan dan reduced-motion yang sudah tersedia.

## 32. Penyesuaian kas/bank — koreksi tanpa mengubah saldo awal

- Panel "Sesuaikan saldo" pada Jurnal umum tetap berupa disclosure yang sudah ada. Pada mode operasional, pilihan akun yang disesuaikan dibatasi Kas (1101) dan Bank (1102); akun lawan tetap satu daftar akun aktif agar jurnal seimbang.
- Form meminta alasan koreksi dan password login, bukan hanya nominal. Kesalahan ditulis sebagai teks di atas grid form, fokus pindah ke pesan, dan isian selain password dipertahankan. Password dikosongkan setelah gagal maupun berhasil.
- Simpan menahan kirim ganda. Setelah berhasil, hanya blok ringkasan saldo, panel penyesuaian, dan rincian pembukaan yang diganti; daftar aktivitas jurnal dan pemantauannya tidak dihentikan atau dimuat ulang. Filter serta halaman jurnal yang sedang tampil tidak berubah.
- Berhasil menyimpan tetapi gagal memuat ringkasan dilaporkan sebagai dua hasil berbeda agar admin tidak memposting ulang. Respons lama tidak boleh mengganti panel Keuangan yang sudah ditinggalkan.
- Status "Terkunci" dan rincian pembukaan membaca saldo awal V2. Saat V2 aktif, form saldo awal lama tidak ditampilkan; panel mengarahkan ke Kas harian → Saldo awal pembukuan.
- Tidak menambah animasi baru. Blok yang diperbarui memakai `uiMotion.enter`; reduced-motion tetap menonaktifkan gerak.

## 33. Tagihan — dua tab dan riwayat lengkap

- Daftar Tagihan operasional memakai tab Piutang pelanggan dan Utang supplier (`ui-subtabs`), default Belum lunas termasuk pembayaran sebagian. Tab memakai semantik tablist/tab/tabpanel, pilihan aktif, roving tabindex, panah kiri/kanan serta Home/End. Ukuran target tab dan aksi baris minimal44px.
- Pencarian nomor/nama berada di luar panel Filter bersama. Status, satu rentang tanggal tagihan, jumlah baris, Terapkan dan Reset mengikuti pola bagian30. Tanggal Tagihan tidak mengikuti tanggal Kasir atau laporan.
- Pagination server menampilkan jumlah seluruh hasil, bukan halaman dari potongan100 terbaru. Baris hanya menampilkan Detail dan satu aksi utama pembayaran; tidak ada tombol bayar untuk tagihan lunas.
- Detail memakai dialog dan gerak bersama yang sudah ada, tanpa komponen popup baru. Nomor/nama, saldo, jatuh tempo, asal, dan seluruh riwayat pembayaran tetap bisa dilihat setelah lunas; pembayaran menunggu/ditolak diberi label jelas.
- Penggantian hasil memasang ulang popup pembayaran dan kunci transaksi. Draft filter tidak ditimpa. Pembaruan latar menunggu popup terbuka ditutup, tidak membatalkan perpindahan tab yang sedang berjalan. Galat mempertahankan hasil sebelumnya; respons lama diabaikan.
- Tombol Lunasi menggunakan saldo mentah desimal, bukan menghapus tanda baca dari teks Rupiah; sen tidak boleh berubah menjadi ratusan Rupiah. Gerak baris/penanda pilihan dan reduced-motion tetap memakai sistem bersama.

## 34. Kasir ringkas, riwayat penjualan dan bukti transfer

- Menggantikan penempatan riwayat panjang di Kasir pada bagian29/29.1: tabel/filter berada di menu Riwayat penjualan. Kasir mempertahankan katalog, keranjang dan ringkasan; daftar5 penjualan terbaru berupa nama + nomor nota kecil berada dalam kartu putih di area ringkasan atas, di samping Produk terlaris, bukan bagian kolom keranjang. Gaya judul, peringkat dan pembatas daftar mengikuti Produk terlaris; tinggi sesuai isi dan tidak meregang mengikuti keranjang. Grafik + dua kartu sejajar di desktop, grafik di atas dua kartu pada tablet, bertumpuk pada HP. Satu kartu terbaru permanen berada di luar panel periode; tidak menggandakan ID/daftar untuk setiap periode. Tanpa batas tinggi/overflow paksa yang memotong kontrol di layar kecil.
- Klik nama pada daftar atau tabel membuka dialog Detail bersama, memakai `input-dialog`, gerak dan tombol tutup yang ada. Detail berisi barang, saldo, seluruh riwayat pembayaran dan Cetak struk/nota. Form pembayaran lanjutan hanya saat masih ada sisa; ID kontrolnya berbeda dari form Tagihan agar label/fokus tidak bertabrakan.
- Riwayat lengkap menampilkan nama/nota, waktu/petugas, metode, total, sisa dan status. Transfer pending diberi teks Menunggu verifikasi. Filter status menyatu dengan pencarian/metode/tanggal melalui panel bersama; tanggal tidak menyetir dashboard.
- Tunai tanpa lampiran. Transfer pelanggan (termasuk DP dan cicilan) menampilkan unggah foto wajib, JPG/PNG/WebP maksimal5MB. Bukti per pembayaran dapat dibuka dari Detail dan Persetujuan. Tidak menampilkan path file privat, tidak membuat cetak bukti cicilan terpisah, dan tidak menyatakan lunas hanya karena foto diunggah.
- Request membawa foto asli lewat multipart; pembatalan/galat mempertahankan input pembayaran. Ganti metode ke tunai menyembunyikan, menonaktifkan dan membersihkan pilihan foto. Pembaruan hasil tetap tanpa reload/kehilangan keranjang; respons Detail lama tidak mengganti dialog yang lebih baru atau sudah ditutup.

### 34.1 Perapian tiga kartu ringkasan

- Grafik, Produk terlaris, dan Penjualan terbaru memiliki tinggi sejajar dalam baris ringkasan, ditentukan oleh isi kartu terpanjang, bukan panjang keranjang. Grafik mengisi ruang kartu dengan sumbu/grid yang ikut menyesuaikan; keterangan puncak berada di bawah grafik agar tidak bertabrakan dengan judul.
- Produk terlaris memakai satu kolom untuk1–5 produk, dua kolom bila6–10, dan selalu satu kolom di HP. Nama membungkus, bukan dipotong elipsis pada kartu sempit.
- Penjualan terbaru tetap lima baris: nama tebal sedang, nomor nota kecil berwarna sekunder, jarak seragam dan target klik44px. Tautan semua penjualan berada di dasar kartu. Tidak menambah tinggi/gulir paksa atau mengubah data/periode.

### 34.2 Detail penjualan terstruktur

- Identitas di atas: nomor nota sebagai judul, nama pembeli, badge pelunasan, lalu tanggal/metode awal/penyerahan dalam label-nilai. Rincian barang memakai tabel dengan jumlah, harga satuan, diskon dan subtotal; diskon nota/ongkir ditampilkan bila ada.
- Ringkasan Total belanja/Sudah dibayar/Sisa tagihan tetap tiga kelompok, dapat membungkus di layar sempit. Riwayat bukan satu kalimat panjang: setiap pembayaran menjadi kartu dengan nominal, metode, badge, waktu, petugas, referensi, bukti foto dan alasan penolakan bila ada.
- Form cicilan/pelunasan tetap hanya untuk nota bersisa, dengan ID dan penangan existing. Tutup berada di kiri footer, Cetak struk/nota di kanan. Tombol cetak berasal dari detail nota aktif, dipindah (bukan digandakan) ke footer dan dibersihkan saat muat baru/ditutup agar tidak tercetak nota sebelumnya.
- Seluruh CSS khusus dialog memanggil token `ui-tokens` untuk warna, jarak, ukuran, bobot font dan radius. Tidak menambah warna atau animasi baru; grid responsif mengikuti lebar konten dan tabel dapat digulir horizontal tanpa menggulir seluruh dialog ke samping.

### 34.3 Keranjang mengambang di desktop

- Katalog memakai satu kolom lebar penuh pada semua layar. Setelah barang ditambahkan, bar jumlah/total/Lihat keranjang tampil di kanan bawah desktop dan tablet; HP tetap memakai bar bawah.
- Keranjang tidak lagi menjadi kolom permanen. Desktop/tablet membuka panel kanan, HP lembar bawah. Daftar, stepper, diskon, total, kosongkan dua langkah dan pembayaran memakai kontrol asli tanpa menyalin keranjang atau perhitungan.
- Panel tertutup bersifat inert; tombol pembuka menyampaikan aria-expanded/controls. Tab berputar di dalam panel, Escape/latar/tutup menutup dan fokus kembali ke pembuka. Dialog pembayaran native tidak ditahan oleh perangkap Tab keranjang.
- Berpindah menu menyembunyikan bar dan menutup panel tanpa menghapus barang. Lebar panel desktop dibatasi token 34rem; bar 32.5rem, tetap dibatasi lebar viewport.
- Daftar `#items` memiliki gulir vertikal sendiri di dalam panel yang dibatasi tinggi viewport. Identitas pembeli, ringkasan total dan aksi pembayaran berada di luar area gulir barang. Daftar dapat difokuskan lewat keyboard, memiliki label region, scrollbar stabil dan overscroll terkurung. Tinggi minimum area barang6rem; pada viewport sangat pendek gulir panel tetap menjadi pengaman agar kontrol tidak terpotong.

### 34.4 Scrollbar dan penyelarasan mobile

- Scrollbar keranjang/katalog/pagination menggunakan hijau tema dan track hijau lembut, berujung bulat; tetap scrollbar native dengan fallback warna sistem untuk forced-colors. Tidak menggunakan kontrol gulir JavaScript.
- Katalog HP menaruh nama produk sepanjang baris, stok/harga di bawahnya, lalu aksi tambah/stepper selebar baris dengan target minimal44px. Batas tinggi tabel desktop dilepas pada susunan daftar HP agar tidak menimpa pagination; halaman angka dapat digulir horizontal sendiri.
- Garis submenu HP memakai cabang melengkung dari komponen sidebar desktop, tanpa garis vertikal tambahan yang terduplikasi.
- Pencarian Kas harian/Shift memiliki tinggi kontrol44px, satu border dan ikon di dalam layout flex. Di HP lebarnya mengikuti toolbar; flex-basis tidak lagi membuatnya membesar vertikal seperti area teks.
- Tabel katalog desktop/tablet membatasi Produk40% dan masing-masing Stok/Harga/Aksi20% melalui token lebar kolom; nama panjang membungkus, bukan membuat kolom Produk mengambil hampir seluruh lebar. Daftar HP tidak terpengaruh.

## 35. Penerimaan — Perlu restok sebelum memilih supplier

- Form Catat penerimaan memuat panel **Perlu restok** di atas pemilihan supplier. Pilih barang seperti katalog: satu supplier aktif diisi otomatis; beberapa supplier meminta pilihan, kecuali supplier form sudah termasuk pilihan barang tersebut. Membuka form memuat stok kritis seluruh toko, tidak harus mengetahui supplier/nama barang lebih dahulu. Tidak memuat data stok kritis ke HTML awal atau meminta data selama dialog tertutup.
- Filter tombol Perlu restok/Stok habis/Stok menipis memuat jumlah sesuai pencarian/cakupan supplier, tidak hanya jumlah baris pada halaman. Pencarian nama/SKU memakai tombol Cari/Enter tanpa mengirim form penerimaan. Daftar20 per halaman dan pagination server; default habis dahulu, lalu nama/ID untuk urutan stabil.
- Setiap barang menampilkan nama/SKU/satuan, badge teks, stok saat ini, minimum, supplier aktif terkait dan aksi. Grid ringkas desktop menjadi daftar dua kolom dengan aksi selebar baris di HP. Area daftar gulir maksimal `--ui-restock-list-max:22rem` atau50dvh; kontrol tetap44px dan memakai token bersama.
- Checkbox Hanya supplier terpilih dihapus; daftar selalu seluruh toko. Barang tanpa supplier aktif menyediakan pemilih supplier existing atau Tambah supplier baru di panel inline. Tombol **Simpan dan hubungkan ke barang** mengonfirmasi penulisan master/relasi; memilih opsi atau Batal belum menulis. Multi-supplier hanya memerlukan **Gunakan supplier**, tanpa mengubah relasi.
- Satu penerimaan tetap satu supplier. Supplier otomatis/manual tidak boleh berganti ketika sudah ada barang/jumlah/HPP; permintaan ditolak dengan keterangan untuk menyelesaikan/menyimpan draf terlebih dahulu. Handler lama yang menghapus semua pilihan barang saat supplier berubah dihapus. Perubahan supplier pada form kosong mempertahankan tanggal/nomor nota dan isian lain.
- Tambahkan langsung memakai baris kosong yang benar-benar belum diisi atau membuat baris baru (maksimal100), tidak perlu klik Tambah barang sebelumnya dan tidak menimpa baris parsial. Barang duplikat ditandai Sudah ditambahkan. Fokus/gulir berpindah ke jumlah; jumlah tetap diisi sesuai kedatangan nyata. HPP positif saat ini menjadi nilai bawaan yang dapat diedit, bukan perhitungan berdasarkan kekurangan stok.
- Memfilter/mencari/membuka halaman tidak mengubah isian penerimaan. Respons lama atau respons setelah dialog ditutup diabaikan; gagal baca mempertahankan daftar lama dan isian, menonaktifkan aksi daftar sampai pembacaan berhasil. Tidak memakai warna saja untuk status atau memasukkan teks produk/supplier sebagai HTML.
- Konfirmasi supplier memakai CSRF dan kunci idempotensi per muatan, double-click diblokir, retry muatan sama memakai kunci yang sama. Input manual dipertahankan saat gagal; tombol Batal/tutup dialog tidak menginterupsi write yang sedang berjalan. Relasi ditambah secara aditif di server, tanpa mengganti supplier utama/relasi lain; tidak memposting stok atau utang.

## 36. Loading dan periode khusus

- Skeleton bersama memakai bentuk judul, tiga ringkasan dan baris tabel tanpa angka palsu, role status dan penanda dekoratif aria-hidden. Warna/ukuran/durasi melalui token; reduced-motion mematikan animasi.
- Klik Masuk yang lolos validasi HTML langsung menampilkan skeleton sambil menunggu POST autentikasi native. Tidak menganggap autentikasi sukses sebelum server memeriksa akun. Shell login inert selama kirim; pageshow memulihkan kontrol saat kembali lewat Back. Tidak menyimpan password/token sesi di storage atau mengirim kredensial lewat jalur tambahan.
- Workspace menampilkan skeleton selama main belum data-ready, bukan ruang kosong; no-JS menampilkan isi tanpa skeleton permanen. Panel Keuangan yang belum memiliki cache memakai skeleton sama; hasil cache boleh langsung tampil. Error mengganti skeleton dengan status dan akses halaman penuh, tidak memuat angka dummy.
- Tanggal khusus berdiri sendiri di ringkasan Kasir dengan pemilih rentang existing, validasi server maksimal366 hari dan tidak melewati hari ini. Ringkasan/pembanding/grafik/terlaris berubah bersama, lima penjualan terbaru tidak difilter. Grafik sehari per jam; lebih panjang per hari,7 hari per bucket di atas62hari dan30hari per bucket di atas180hari.
- Pembaruan tanpa reload/menyentuh keranjang. Respons usang ditolak; gagal mempertahankan hasil. Refresh latar memakai rentang terakhir yang diterapkan, bukan isian tanggal yang belum dikirim. Preferensi Kasir terpisah dari tanggal menu lain.

## 37. Ongkir penerimaan

- Ongkir opsional default0, dibebankan langsung dan bukan komponen HPP. Jika positif user memilih Ikut tagihan supplier/Kas harian/Bank serta akun beban aktif; tidak otomatis memakai6101 atau membuat akun baru.
- Form/tinjauan menampilkan nilai barang, ongkir, total biaya, utang dan pengeluaran terpisah. Draf dan riwayat menampilkan rincian ongkir; draf belum memposting apa pun. Ringkasan baru memakai kalkulasi desimal BigInt.
- Sebelum migrasi aditif disetujui/dijalankan, kontrol ongkir dinonaktifkan dan diberi keterangan; penerimaan tanpa ongkir tetap berjalan. Tidak mengubah pembukuan historis atau memaksa migrasi saat halaman dibuka.

## 38. Kontinuitas sidebar, jumlah katalog dan morph keranjang

- Sidebar desktop mempertahankan lebar layout brand/nav/footer sesuai ukuran terbuka, sedangkan permukaan luarnya yang melebar/terpotong. Label nowrap memudar, tidak menyusun ulang kata ketika rail berubah. Submenu diukur dari scrollHeight dan max-height dianimasikan; grup yang baru dibuka diukur ulang. Flyout berlebar tetap dan anak tidak menyusut sehingga pergantian grup tidak mengubah susunan teks. Mobile tetap drawer dan reduced-motion menonaktifkan transisi.
- Angka tengah stepper katalog merupakan input jumlah, bukan output readonly. Menerima nilai positif dengan koma/titik desimal maksimum4digit; nilai sah diteruskan ke input asli keranjang beserta event input, bukan perhitungan kedua. Draft tidak valid/0/kosong tidak mengubah isi; saat blur/Enter kembali ke nilai sah terakhir. Escape membatalkan draft tidak valid. Tombol−/+ tetap bekerja, kontrol44px, label nama barang dan status galat terhubung.
- Membuka keranjang memakai morph dari posisi/ukuran/radius/warna bar keranjang ke panel akhir, bukan translate dari luar layar. Hanya kulit visual inert/aria-hidden yang berubah ukuran; form asli tetap pada ukuran akhirnya dan crossfade agar tulisan tidak tertekan/membungkus selama animasi. Tidak menyalin input atau ID. Buka dan tutup normal memakai morph 300ms ease-in-out; tutup mengecil ke geometri bar pembuka dan mengembalikan fokus setelah selesai. Klik tutup berulang diabaikan selama penutupan. Handoff pembayaran, keranjang kosong, atau pindah menu tetap menutup langsung dan membersihkan animasi agar tidak menahan alur.
- Token: catalog-quantity-width4rem, morph-duration300ms/morph-easing ease-in-out/morph-layer121, sidebar-duration260ms/sidebar-text-duration180ms. Tanpa dependensi animasi baru; reduced-motion tetap langsung.
- Handoff antikedip: kulit dan panel memakai durasi, easing dan waktu mulai yang sama. Saat buka, panel sudah opak sebelum kulit memudar; saat tutup, kulit tetap opak sampai bar mengambil alih. Bar disembunyikan lewat visibility selama panel terbuka, bukan display:none, agar animasi masuk bar tidak berulang ketika ditutup.

## 39. Produk ringkas, stok habis dan number tweening Kasir

- Katalog mempertahankan nama, SKU, stok/satuan, harga dan aksi; kategori tetap dapat difilter tanpa diulang pada setiap baris. Hilangkan bayangan kartu dan pil stok dekoratif; stok menjadi teks sederhana dengan status Habis yang eksplisit. Padding ringkas tetap mempertahankan target sentuh44px dan input jumlah yang dapat diketik.
- Tombol produk stok0 nonaktif dengan label Habis dan nama produk yang aksesibel. Fungsi tambah juga memeriksa stok sebelum menyentuh keranjang; bukan CSS saja. Draf lama tidak dihapus diam-diam, tetapi kuantitas melebihi stok membuat total Periksa isian dan checkout nonaktif. Validasi stok server existing tetap otoritatif.
- Number tweening opt-in pada KPI ringkasan Kasir saat ganti periode/refresh data, subtotal/harga satuan/total keranjang, badge jumlah jenis, subtotal/diskon bar dan kembalian. Tidak menganimasikan input jumlah, uang dibayar, ID, tanggal, baris buku besar atau data yang dikirim.
- Token `--ui-number-duration:var(--ui-morph-duration)` mengikuti300ms. Perubahan memakai ease-in-out dan requestAnimationFrame; aritmetika uang tetap BigInt berskala, floating point hanya untuk waktu animasi. Update berulang membatalkan frame lama dan bergerak dari angka visual terakhir. Node terlepas/tab tersembunyi dan reduced-motion langsung memakai hasil akhir.
- Angka untuk pembaca layar/konfirmasi pembayaran langsung nilai akhir, bukan angka antara. Visual tween aria-hidden; canonical text disembunyikan secara visual oleh kelas khusus agar tidak bergantung pada utility CSS yang tidak dimuat workspace. uiLive mempertahankan node KPI, bukan mengganti seluruh panel pasif, agar tween benar-benar berjalan.

## 40. Filter katalog, ruang tombol dan latar keranjang

- Filter katalog berada di kanan kolom pencarian pada baris tools dalam kepala katalog, bukan baris sendirian di bawahnya. Grid minmax menjaga pencarian dapat mengecil pada mobile; keterangan hasil tetap di bawah. Memindahkan wrapper sumber existing, tidak mengkloning select/reset atau mengubah perilaku popover/fokus/filter menu lain.
- Tombol Tambah memakai padding vertikal space-control dan horizontal space-section, lebar auto di desktop dan tetap penuh di mobile; target sentuh minimal44px. Lebar64px/padding0 lama tidak boleh memampatkan teks.
- Backdrop selalu terpasang sebagai lapisan renderable dengan opacity0/visibility hidden saat tutup. Buka mengubahnya ke opacity1 bersamaan dengan morph; tutup normal memakai kelas pm-cart-closing untuk memulai fade-out sejak awal morph, bukan sesudah panel hilang. Durasi/easing memakai token morph300ms/ease-in-out; reduced-motion tanpa transisi. Lapisan tetap mencegah klik tembus sampai penutupan selesai.
- Token baru ui-cart-backdrop memusatkan warna overlay existing rgba(9,30,18,.55), bukan mengganti intensitasnya. Z-index diturunkan dari ui-cart-layer.

## 41. Halaman status dan verifikasi pembayaran

- Admin tetap memakai Pengawasan › Persetujuan pembayaran, dengan judul halaman Verifikasi pembayaran. Kasir memiliki Operasional › Status pembayaran; melihat pembayaran miliknya sendiri tanpa aksi verifikasi/penolakan. Tidak menggandakan tabel atau memperluas hak akses.
- Tabel harus menjadi anak panel persetujuan untuk kedua peran, bukan tertinggal di stack sumber. Kasir sebelumnya tidak punya pemilik navigasi panel ini sehingga tabel muncul di luar panel pada semua menu; perbaikan dilakukan pada kepemilikan menu, bukan sekadar menyembunyikan tabel lewat CSS.
- Masuk halaman memakai uiMotion.enter dan kelas ui-dna-ops existing, termasuk stagger baris/reduced-motion. Tidak membuat animasi terpisah. Antar kartu ringkasan/daftar/form penolakan memakai gap space-panel, tanpa tumpukan margin kartu; penjelasan, toolbar/filter dan tabel memiliki jarak bertoken.
- Judul/penjelasan membedakan transfer pelanggan yang perlu pemeriksaan, pengajuan supplier, dan tunai pelanggan yang langsung tercatat. Kasir menampilkan6kolom tanpa Pengaju/Aksi yang berulang/kosong; admin tetap8kolom dengan tindakan. Pesan kosong memberi penjelasan jenis pembayaran yang akan muncul.

## 42. Kolom riwayat penjualan

- Nomor nota dan Pembeli dipisahkan menjadi dua kolom. Nomor/kode ditampilkan tegas dengan tanggal/jam di bawah; nama pembeli tetap tombol detail, petugas menjadi teks sekunder di kolom pembeli. Label aksesibel tombol menyertakan nomor nota supaya pembeli berulang dapat dibedakan.
- Padding tabel dan kontrol melalui token existing, nominal rata kanan/tabular; dokumen berupa tautan bertarget44px. Mobile mempertahankan label Nomor nota/Pembeli terpisah, teks membungkus, min-width tabel lama dinonaktifkan agar tidak memaksa gulir horizontal; dokumen ditampilkan penuh sebagai aksi kartu.
- Tidak mengubah pencarian, filter tanggal, urutan, pagination, perhitungan sisa/status, atau endpoint detail/cetak.

## 43. Angka berlabel Kasir dan tanggal khusus ke samping

- Tween300ms juga mencakup angka di dalam teks jumlah jenis/barang, subtotal barang, jumlah di pembayaran dan label diskon per barang. Helper text memisahkan bagian angka dari label, mempertahankan nilai desimal/format Indonesia dan canonical final; pembaruan cepat membatalkan frame sebelumnya. Input editable tetap tidak ditween.
- Keterangan KPI (pembanding/jumlah nota/stok), persentase perubahan dan kuantitas produk terlaris memakai marker data-number-text. Saat pindah periode, nilai lama dicocokkan berdasarkan peran/nama produk, bukan urutan angka sembarang; jika label berubah angka langsung final, tidak menween antara makna berbeda. uiLive memakai helper text tanpa mengganti ancestor ringkasan.
- Tanggal khusus bukan details yang memanjangkan dashboard. Tombol membuka region popover manual ke sisi kiri jika muat, sisi kanan berikutnya; hanya layar sempit memakai fallback posisi bawah yang dijepit viewport. Dari/Sampai sejajar, judul/Tutup di atas dan Terapkan/status di bawah. Token baru ui-period-panel-width28rem, warna/jarak/radius memakai token existing.
- Animasi masuk memakai ui-float-in existing dan reduced-motion. Tombol mengumumkan expanded/controls; Tutup/Escape mengembalikan fokus, klik luar/pindah menu menutup. Klik kalender/select anak tidak menutup parent; menutup parent juga menutup child picker agar tidak tertinggal. Nilai tanggal, fetch, preferensi dan isolasi keranjang tetap pada handler periode existing.

## 44. Pembungkus dialog master dan isian penerimaan

- Daftar Karyawan & sopir tetap satu. Wrapper master-section-employee/driver hanya menampung dialog: tanpa kelas card/aria-labelledby ke judul yang tidak dirender, bertanda data-dialog-host dan display:contents pada panel. Dialog tambah/edit/impor dan listener tetap ada; wrapper tidak mengambil ruang atau animasi kartu kosong.
- Jenis mobil memakai dropdown shared field type select: Pick-up, Truk engkel, Truk double, Dump truck. Jenis lama di luar pilihan ditampilkan sebagai opsi tersimpan yang tetap selected agar edit tidak mengganti data diam-diam. Tidak mengubah backend menjadi enum atau memigrasi data lama.
- HPP bawaan dipakai untuk pencarian barang biasa dan rekomendasi. Nilai string positif sampai4desimal, tanpa konversi float; field kosong atau saran otomatis yang belum diedit dapat diperbarui. Isian manual/draf, termasuk0, tidak ditimpa. Produk tanpa HPP positif membiarkan harga kosong. Nilai ini tetap harus diperiksa terhadap harga faktur.
- Rekomendasi langsung membuat/mengisi baris penerimaan dan menetapkan value produk secara eksplisit sebelum sinkron pemilih. Jika sudah ada barang, baris berikutnya dibuat otomatis. Setelah ditambahkan, fokus dan gulir ke baris jumlah. Guard supplier tunggal, duplikat, busy/review, maksimum100 dan perlindungan baris parsial dipertahankan.

## 45. Kalender langsung, periode riwayat dan aktivitas master

- Menggantikan popup perantara bagian43: Tanggal khusus langsung membuka kalender rentang shared; Oke menerapkan, pembatalan memulihkan snapshot. Tetap memakai animasi/keyboard kalender existing.
- Riwayat menyediakan tombol Harian/Mingguan/Bulanan dengan aria-pressed. Default mingguan7hari inklusif; tanggal manual tetap tersedia, reset meminta minggu terbaru dari server.
- Pelanggan memiliki frekuensi transaksi dan piutang tersisa rata kanan/tabular. Dialog supplier existing dipakai kembali, dengan satu kolom rentang tanggal, status pemuatan/error, pagination dan cetak rentang terapan.
- Warna/jarak/radius memakai ui-tokens existing. Token --ui-supplier-history-height:50dvh membatasi area gulir aktivitas; menggantikan token lebar popup perantara yang tidak dipakai. Tidak menambah sistem tema/dependensi.

## 46. Penjelasan checkout tanpa kas harian aktif

- Keterangan shift di keranjang tidak boleh disembunyikan oleh tema. Saat belum buka, sudah ditutup atau status tidak diketahui, tampilkan pesan Belum bisa checkout dekat tombol, memakai token peringatan existing dan role status.
- Tombol checkout keranjang valid tetap dapat diklik untuk menerima penjelasan; dialog pembayaran tetap diblokir selama kas tidak aktif. Pesan klik memakai alert yang dapat difokuskan. Keranjang tidak dihapus; saat shift aktif pemberitahuan disembunyikan kembali.

## 47. Animasi data bersama untuk chart, daftar dan angka

- Ringkasan admin dan Kasir menggunakan uiMotion.dataSnapshot/dataChange, bukan animasi batang admin tersendiri. Durasi dari ui-number-duration dan easing dari ui-morph-easing:300ms/ease-in-out. Batang vertikal berubah tinggi, bar produk berubah lebar; daftar produk terlaris/katalog memudar-naik memakai jarak space-tight. Tidak mengkloning atau menskalakan teks/isian.
- Snapshot batang memakai dimensi visual saat ini, dicocokkan menurut tanggal/jam atau nama produk. Angka dicocokkan menurut peran/nama produk dan memakai uiNumbers BigInt existing; nilai canonical/aksesibel langsung final. Kemunculan pertama dapat menghitung dari0, pergantian periode dari snapshot sebelumnya. Isian editable dan payload tidak dianimasikan.
- Animasi lama dibatalkan saat pembaruan cepat. Konten tersembunyi, tab latar dan reduced-motion tidak memulai animasi. Maksimal12item daftar dianimasikan agar katalog panjang tidak membebani.
- Area chart/produk terlaris ditandai data-motion-region dengan versi berdasarkan data server. uiLive mengganti area pasif hanya ketika versinya berubah, termasuk geometri/label chart dan kondisi kosong; data sama tidak memutar animasi ulang. Pilihan periode, form/keranjang dan perluasan daftar admin tetap dipertahankan.
- Form internal Tanggal khusus adalah GET tersembunyi dan dikecualikan dari popupForms; tidak boleh menghasilkan tombol Isi formulir. Hanya pemicu Tanggal khusus yang membuka kalender.

## 48. Ringkasan Admin satu layar desktop

- Pada desktop lebar minimal1180px, Ringkasan memakai gap/control dan padding/group yang lebih padat: tugas tindak lanjut satu bilah, kepala KPI sejajar rentang tanggal, empat KPI tetap jelas, chart dan terlaris sejajar, tiga rincian operasional sejajar. Tidak menetapkan overflow:hidden atau tinggi layar yang memotong konten.
- Tinggi plot memakai token --ui-overview-plot-height:clamp(6rem,14dvh,9rem). Font angka penting tetap terbaca, data/label/status tidak dihapus. Tautan tindak lanjut memiliki title dengan jumlah, status lengkap dan konteks.
- Desktop setinggi maksimal860px memulai Uang/Pengiriman/Stok sebagai disclosure ringkas dengan nilai/status di summary dan petunjuk Rincian. Pengguna dapat membukanya lewat native details/keyboard; membuka rincian boleh menambah scroll. Desktop lebih tinggi tetap membuka semua rincian.
- HP mempertahankan susunan satu kolom dan gulir vertikal alami; tidak mengecilkan skala halaman agar dipaksa muat. Tidak mengubah layout menu Kasir/keuangan/master.

## 49. Riwayat selisih dan pemeriksaan yang belum selesai

- Uang pada Ringkasan Admin dan monitor Kas harian membedakan Selisih bulan ini (riwayat) dengan Belum diselesaikan. Riwayat memakai nada netral, bukan merah terus setelah pemeriksaan selesai.
- Belum diselesaikan menampilkan neto dan jumlah shift review semua tanggal. Peringatan mengikuti jumlah kasus>0, sehingga neto0 dari selisih plus/minus tidak terlihat sebagai tidak ada masalah. Label/hint menjelaskan bahwa ini pemeriksaan, bukan saldo kas.
- Kartu monitor Kas mempertahankan tiga ubin; nilai Perlu diperiksa lama menjadi nominal Belum diselesaikan dengan jumlah kasus sebagai keterangan dan filter review tetap tersedia. Tidak menambah ubin kosong atau mengubah layout kompak.
- Penjelasan pada daftar pemeriksaan menyatakan riwayat tetap disimpan dan tidak ada jurnal penyesuaian otomatis. Proses penyelesaian tetap membutuhkan catatan admin.

## 50. Kolom master produk dan detail baca-saja

- Tabel produk tepat8kolom: SKU, Nama, Kategori, HPP / modal, Harga jual, Stok / satuan, Status dan Aksi. Harga kontraktor tetap keterangan harga jual bila terisi; kondisi Habis/Menipis tetap menyertai stok. Kolom Supplier dipindahkan ke rincian.
- Detail memakai native dialog dan pengelola popup existing, bukan form baru atau endpoint baru. Fakta produk ditata dua kolom desktop/satu kolom HP, dengan label jelas, angka tabular rata kanan, warna/jarak/radius dari token existing. Tombol Tutup memulihkan fokus ke pemicu; Ubah/Arsipkan tidak berubah.
- HPP per satuan ditampilkan hingga4desimal dan kuantitas hingga desimal yang tersimpan melalui pemformatan string, bukan float. Semua nama/teks di-escape Blade. Supplier utama/arsip dan alasan HPP terkunci tetap terlihat di rincian.
- Dialog ikut baris master sehingga fragmen uiLive yang mengganti baris membawa rincian terbaru; tidak menyimpan salinan JSON statis yang menjadi kedaluwarsa setelah edit.

## 51. Kepemilikan parameter URL workspace

- Pemilihan menu tetap memakai hash existing. Tanggal laporan hanya tampak di URL #laporan; produk/Kasir/Tagihan/menu lain tidak membawa date. master_kind lama yang sudah digantikan hash dan partial transport dibersihkan, tanpa menghapus parameter asing atau prefix deployment.
- Pembersihan memakai replaceState tanpa reload atau menambah entri history. Berlaku pada tautan awal, klik menu dan navigasi hash. Form/filter tidak dimodifikasi.
- Tanggal laporan terapan dibaca dari defaultValue input server, bukan value yang mungkin masih draft. Kembali ke Laporan dalam dokumen yang sama memulihkan tanggal terapan di alamat; uiLive menggunakannya pada GET internal sekalipun menu aktif memiliki URL bersih. Form fallback/reset riwayat penjualan tidak membawa date/master_kind.
