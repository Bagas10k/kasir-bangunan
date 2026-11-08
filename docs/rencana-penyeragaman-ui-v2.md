# Rencana penyeragaman UI V2 (seluruh halaman)

Tujuan: satu tatanan yang sama di semua menu supaya pengguna tidak perlu belajar ulang tiap halaman,
tetap ringan untuk toko bangunan dengan banyak produk dan banyak transaksi.
Backend, perhitungan uang, stok, dan jurnal TIDAK berubah. Semua hitungan tampilan memakai BigDecimal.

## 1. Patokan (dikerjakan di halaman Kasir POS, lalu disalin ke halaman lain)

### 1.1 Patokan palet resmi dari Kasir

Kasir/POS menjadi golden reference halaman lain. Menyamakan kartu dengan katalog berarti bentuk dan kerapian, BUKAN membuat checkout putih. Header aplikasi dan checkout tetap hijau gelap. Wariskan komponen/hierarki/perilaku, bukan menyalin grafik atau isi POS ke semua menu.

| Peran | Token / nilai | Batas penerapan |
|---|---|---|
| Identitas gelap | `--ui-side` #0f2a1d | Header, sidebar, checkout POS |
| Lapisan gelap | `--ui-side-2` #143826 | Badge/status di bidang gelap |
| Teks gelap-background | `--ui-side-ink` #e7f3ec | Judul/label/helper di bidang gelap |
| Teks sekunder gelap-background | `--ui-side-mute` #9fb8a9 | Hanya bila kontras lulus |
| Aksen di bidang gelap | `--ui-side-active` #c8f26b | Navigasi aktif, tautan header, checkout; teks #0f2a1d |
| Permukaan data | `--ui-surface` putih | Katalog, daftar barang, dialog dan kartu data biasa |
| Kanvas | `--dna-canvas` #f6f8f7 | Latar halaman |
| Garis kartu | `--dna-line` #e4ebe6 | Bidang gelap memakai `--ui-side-line` |

Token implementasi ada di `ui-theme`, `ui-tokens`, `ui-minimal`; lapis akhir bersama `ui-dna`. Kedua cangkang `v2/workspace` dan `layouts/app` wajib konsisten. Jangan menambah warna literal/CSS berbeda per halaman. Status/tombol shift masuk kepala kartu Ringkasan penjualan tanpa kartu terpisah. Pelanggan tetap field ringkas; tabel keranjang yang disetujui dipertahankan.

### 1.2 Peran warna semantik (satu arti = satu warna, di semua halaman)
| Peran | Dipakai untuk | Token |
|---|---|---|
| Aksi utama | Tombol Simpan/Checkout, tab terpilih, batang grafik | `--ui-green` #14532d, teks putih |
| Terpilih / sorotan lembut | chip aktif, baris terpilih, tab terpilih ringan | `--ui-accent-soft` + teks `--ui-green` |
| Aksi kedua | Batal, Reset, Ubah, Pilih | putih, garis `--ui-line`, teks `--ui-green` |
| Positif | naik, lunas, tersedia | hijau `--ui-green` di atas `--ui-green-soft` |
| Perhatian | stok menipis, tempo mendekat | kuning `--ui-warn-*` |
| Masalah | stok habis, selisih kas, gagal | merah `--ui-bad-*` |
| Netral | status biasa, kategori | abu `--ui-mute-*` |
| Teks | judul/angka utama `--ui-ink`, keterangan `--ui-ink-2`, penjelas `--ui-ink-3` | |
Aturan: warna tidak pernah satu-satunya penanda (selalu ada teks/ikon), kontras teks >= 4.5:1.

### 1.2 Tiga tingkat baca
Judul/nilai utama (tebal, gelap) -> keterangan 14 px -> penjelas 12,5 px. Angka: rata kanan, tabular.

### 1.3 Kepala halaman bergaya dashboard
Setiap menu punya satu kepala ringkas: 3-4 angka kunci + satu grafik kecil yang menjawab pertanyaan menu itu,
plus pembanding periode sebelumnya. Grafik tidak boleh membuat halaman kerja turun lebih dari ~230 px
dan bisa dilipat (pilihan diingat di browser).

### 1.4 Aturan skala (toko banyak produk, banyak transaksi)
- Tabel/katalog: tampilkan 40 baris pertama yang cocok, tombol "Tampilkan lebih banyak"; saringan bekerja pada seluruh data.
- Dropdown berisi lebih dari ~15 pilihan diganti kolom ketik (`uiPick`/combobox), bukan `<select>` penuh.
- Daftar pelanggan tidak dikirim penuh ke halaman; dicari lewat `v2.customers.search`.
- Agregat dashboard dihitung di layanan (bukan di Blade), dibaca dengan `cursor()` dan kolom seperlunya, dijumlah BigDecimal,
  tidak memuat seluruh baris ke memori. Lebih dari ~5.000 produk: pindahkan pencarian katalog ke server (tahap lanjutan).
- Rentang grafik dibatasi 30 hari; label sumbu X dijarangkan supaya terbaca.

## 2. Urutan kerja
1. **Kasir POS** (halaman patokan): tema, kepala dashboard + grafik, rapikan katalog/keranjang, aturan skala. <- tahap ini
2. Ringkasan, Persetujuan, Tagihan, Pengiriman, Penerimaan, Shift (transaksional): kepala dashboard + tabel baku.
3. Master data (Produk, Supplier & pelanggan, Karyawan & sopir, Mobil, Satuan & kategori): kepala + tabel baku + dialog baku.
4. Keuangan (Jurnal, Neraca, Laba rugi, Arus kas, COA): halaman lama di dalam panel, disamakan tipografi dan warnanya.
Setiap tahap selesai bila: audit ukur lulus (huruf, kontras, jumlah huruf besar penuh = 0, tidak meluber di 390 px),
tes lulus, dan pengguna menyetujui tangkapan layarnya.

## 3. Di luar cakupan
Perubahan perhitungan uang/stok/jurnal, migrasi baru, deposit supplier, pencarian katalog di server.
