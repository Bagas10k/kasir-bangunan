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
