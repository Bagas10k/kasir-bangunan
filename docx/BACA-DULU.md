# Peta alur hidup tb01

Dokumen ini menampilkan alur yang ditemukan di kode, bukan spesifikasi yang memerintah kode. Jika berbeda, kode adalah sumber kebenaran. Jangan mengubah perhitungan aplikasi agar cocok dengan tulisan ini.

## Buka yang mana?

- `alur.html`: peta ALUR KERJA TOKO—penjualan, barang masuk, bayar supplier, tagih pelanggan, pengiriman, shift, biaya sopir, persiapan, dan pembukuan. Bukan daftar rute atau grafik fungsi. Buka melalui `http://127.0.0.1:8767/alur.html` untuk pembaruan tanpa reload; berkas HTML juga menyimpan snapshot terakhir.
- `live.py`: server/watcher lokal baca-saja untuk HTML. Tidak menjalankan PHP, Laravel, query database atau model AI.
- `DIAGRAM.md`: sembilan diagram proses bisnis Mermaid, berikut pelaku dan rujukan kode per tahap. Dibangun bersama snapshot HTML, bukan pengganti model/bukti teknis yang lebih rinci.
- `ALUR-SISTEM.docx`: dokumen Word lengkap, hulu → hilir, setiap langkah disertai guard, hasil/cabang gagal, perubahan tabel dan kutipan kode asli bernomor baris.
- `ALUR.md`: peta gabungan Mermaid, tabel langkah dan indeks operasi data. Dapat dibaca sebagai teks walau Mermaid tidak dirender.
- `BUKTI-KODE.md`: cuplikan kode langsung, dikelompokkan per langkah; bukan pseudocode. Path dihitung dari root repositori.
- `modul/*.md`: peta yang lebih kecil per modul.
- `model.json`: model gabungan format peta-alur-hidup v2; keluaran generator, jangan diedit langsung.
- `sumber/modul/*.json`: hasil penelusuran per modul dan pemilih rentang bukti. Narasi hasil analisis dirawat di sini.
- `sumber/diagram-view.js`: renderer SVG lokal untuk diagram gambaran toko dan setiap proses; disematkan langsung ke HTML tanpa CDN.
- `sumber/lintas-modul.json`: urutan baca, hubungan lintas modul, batas cakupan dan kejanggalan sumber.
- `sinkron.json`: tanda terima build, hash sumber working tree, resep dan keluaran. Commit dasar bukan seluruh identitas sumber karena ada perubahan lokal belum commit.

Folder `public/peta-alur-hidup/` adalah aplikasi timeline dari pekerjaan terdahulu, bukan peta kode ini; tidak disentuh dalam tugas ini.

## HTML dengan pembaruan otomatis

Mulai dari Gambaran sistem, kemudian pilih proses seperti Penjualan atau Barang masuk. Setiap proses menjelaskan pemicu, pelaku, tindakan di aplikasi, pilihan/cabang, hasil, dampak stok/uang/tagihan/jurnal, serta proses lanjutannya. Bukti kode berada di rincian tertutup pada tahap terkait; nama fungsi dan endpoint tidak menjadi navigasi utama.

Diagram ditampilkan terlebih dahulu: kotak tindakan, belah ketupat pilihan, ujung bulat akhir cabang, dan garis putus-putus alternatif. Klik tahap atau gunakan Tab lalu Enter untuk menuju rinciannya. Pembatalan draf dan penolakan pembayaran berakhir sendiri, tidak diteruskan ke hasil sukses. Pada HP, jalur utama ditampilkan di tengah; geser diagram secara horizontal untuk melihat cabang, tanpa menggeser seluruh halaman.

Definisi baca operasional ada di `sumber/alur-operasional.json`, ditautkan ke langkah sumber yang sudah dipetakan di `model.json` dan `sumber/modul/*.json`. Transisi cabang diperiksa agar tidak menunjuk tahap yang tidak ada. Pilihan alternatif—misalnya draf diubah ATAU dibatalkan ATAU dikonfirmasi—tidak digambar sebagai urutan wajib.

Dari root repositori:

```sh
python3 docx/live.py
```

Watcher memeriksa perubahan sumber setiap 1 detik dan halaman mengambil revisi terbaru setiap 1,5 detik. Setelah menyimpan kode, HTML/JSON dibangun ulang. Bukti kode dan nomor baris pada tahap operasional diperbarui, sedangkan ekstraksi rute, pemanggilan, guard, tabel dan status menjadi data pendukung, bukan isi utama yang menggantikan penjelasan proses. Halaman memperbarui isinya tanpa reload; pilihan proses, rincian terbuka dan pencarian dipertahankan.

Kode sumber adalah masukan langsung. Alur operasional memakai transisi yang ditelusuri dari kode, dengan syarat dan identitas dokumen yang sama; bukan urutan definisi fungsi. Diagram ringkas seluruh toko adalah hubungan antar-proses, bukan kewajiban menjalankan semua proses berurutan. Ekstraksi pemanggilan statis hanya bukti pendukung; pemanggilan dinamis/vendor yang tidak bisa diurai tidak ditebak. Provisioning/seed/console dan DemoSetupController tidak dipublikasikan; nilai sensitif serta identitas literal dalam kode disamarkan. Kode aplikasi tidak diubah otomatis.

Penjelasan bahasa manusia dari pemetaan lama tetap terpisah dari fakta hasil ekstraksi. Jika sumber/dependensi berubah, catatan itu ditandai belum ditinjau; watcher tidak akan berpura-pura telah memahami seluruh perubahan aturan bisnis. Bagian sumber, guard, data, status literal dan hubungan fungsi tetap diambil ulang otomatis. Jika sumber sedang setengah tersimpan/tidak dapat diurai, snapshot baik terakhir dipertahankan dengan peringatan.

Server hanya bind `127.0.0.1`; bukan fitur publik aplikasi. API snapshot memerlukan token sesi lokal otomatis, memeriksa Host/Origin, dan tidak melayani path sumber atau daftar direktori. Jangan menaruh HTML/JSON ini di web publik karena berisi kode proyek. Watcher perlu tetap berjalan untuk pembaruan otomatis; setelah komputer/proses berhenti, jalankan kembali perintah di atas. Tidak memasang autostart Windows atau mengubah server aplikasi.

Untuk membuat snapshot sekali tanpa server:

```sh
python3 docx/live.py --build
```

HTML dan Word mempunyai lifecycle terpisah: watcher memperbarui `alur.html`/`alur-live.json` dan `DIAGRAM.md`, tidak diam-diam menyetujui ulang narasi atau mengubah Word/Markdown teknis hasil telaah. `DIAGRAM.md` berpenanda generator; berkas manual tanpa penanda ditolak agar tidak ditimpa. Uji parser, watcher, server lokal dan keamanan tanpa menjalankan aplikasi:

```sh
python3 docx/sumber/test_live.py
```

## Ringkasan hulu sampai hilir

1. Login/peran aktif; siapkan master produk, pihak, opsi, sopir dan kendaraan. Impor master opsional. Saldo awal hanya untuk sistem bersih, bukan langkah yang dipaksa sesudah transaksi berjalan.
2. Supplier dan produk tertaut → simpan draf penerimaan → ubah ATAU batalkan ATAU konfirmasi final. Konfirmasi baru menambah stok/HPP, utang AP dan jurnal; kas/bank belum keluar.
3. AP → pembayaran Kas Utama / Bank Utama / Laci shift. Admin posting langsung; kasir mengajukan → admin setujui ATAU tolak. Pembayaran yang sudah posted bukan objek penolakan biasa.
4. Buka shift dan susun keranjang → konfirmasi penjualan → nota, stok keluar, piutang, pendapatan dan HPP. Cash, transfer, credit adalah cabang alternatif; transfer pending bukan saldo bank yang sudah diterima.
5. Sisa piutang ditagih. Penerimaan cash menambah laci shift penerima; transfer perlu verifikasi admin. Jangan menyamakan total penjualan dengan uang laci.
6. Pilihan delivery membuat SJ prepared → sent → received. Pengiriman tidak mengurangi stok sekali lagi. Trip langsung dan uang muka adalah alur biaya independen, tidak wajib pada setiap pengiriman.
7. Rekap shift → hitung uang fisik → cocok closed ATAU selisih review → resolusi admin. Ini bukan tutup buku/periode akuntansi dan tidak otomatis menjurnal selisih.
8. Jurnal transaksi dibaca buku jurnal, laba rugi, neraca dan arus kas; ketiga laporan bukan rangkaian posting berurutan.

## Periksa sinkronisasi

Dari root repositori, Python 3.12 atau lebih baru:

```sh
python3 docx/sinkron.py --check
```

Cek ini tidak memerlukan python-docx, tidak memulai Laravel, tidak membaca `.env` atau database, tidak menulis hasil, dan tidak mengubah sumber. Exit 0 berarti identitas berkas/resep/keluaran yang dipantau cocok dan lokasi bukti masih valid. Exit 1/2 berarti belum sinkron atau ada input/bukti yang tidak sah; baca daftar path yang dilaporkan.

Yang dipantau: berkas PHP `app/`, `routes/`, `database/migrations/`; Blade `resources/views/`; JS/TS `resources/js/`; CSS `resources/css/`; `bootstrap/app.php`, `config/v2.php`, `composer.json`, `package.json`. Perubahan, penambahan dan penghapusan diperiksa. Berkas yang dipantau belum tentu sudah ditelusuri semua alurnya.

Yang tidak dipantau: `.env`, kredensial/seed, database, vendor, runtime/cache, deployment dan path di luar cakupan. Cek ini BUKAN pembuktian bahwa semua narasi benar, seluruh rute terpetakan, atau aplikasi lulus tes runtime. Pemeriksa Word tidak menjalankan watcher; pembaruan HTML memakai `live.py` secara terpisah.

## Jika sumber berubah

1. Baca diff lokal dan handler/guard/write terkait. Periksa identitas entitas di setiap panah, bukan sekadar nomor baris masih ada. Cari juga aksi baru yang belum terpetakan.
2. Perbarui `sumber/modul/*.json` dan `sumber/lintas-modul.json` berdasarkan kode. Sesuaikan pemilih rentang `proofs`; jangan mengetik salinan kode ke dalam narasi.
3. Bangun ulang dengan interpreter alat dokumentasi yang memiliki `python-docx`. Dependensi hanya untuk alat dokumen, bukan aplikasi Laravel. Contoh dengan uv dari Git Bash:

```sh
env -u PYTHONPATH uv run --isolated --no-project --python 3.12 --with python-docx python docx/sinkron.py --build --accept-reviewed-source
```

Flag `--accept-reviewed-source` adalah pengakuan operator bahwa sumber sudah ditelusuri ulang, BUKAN analisis semantik otomatis. Jangan gunakan hanya untuk menghilangkan galat. Build tanpa flag itu menolak perubahan sumber. Jika hanya memperbaiki narasi/pemilih bukti dan sumber tetap sama, gunakan `--build` tanpa flag tersebut.

4. Jalankan `python3 docx/sinkron.py --check`, tinjau Word dan peta modul. Generator menolak menimpa keluaran yang diedit manual/asing; jangan mengedit `ALUR*.md`, `model.json`, `BUKTI-KODE.md` atau Word secara langsung. Catatan manual dapat ditaruh di berkas lain dan tidak dihapus generator.

Generator menggunakan salinan helper kanonis skill peta-alur-hidup di `sumber/render_map.py`, sehingga tidak membutuhkan path skill milik mesin tertentu. Untuk pemeriksaan renderer mandiri:

```sh
python3 docx/sumber/render_map.py docx/model.json --root . --output docx/ALUR.md --check
```

Uji alat sinkron saja (tanpa aplikasi/database):

```sh
PYTHONDONTWRITEBYTECODE=1 python3 docx/sumber/test_sinkron.py
```

## Kejanggalan yang sengaja tidak diperbaiki

- Token rekap tutup shift opsional; UI membolehkan penutupan saat fetch rekap gagal. Server tetap menghitung expected terbaru, tetapi tidak selalu menolak snapshot lama tanpa token.
- `workspace.blade.php:55-56` mengisi `$sourceLabel` dua kali: peta sumber pembayaran tertimpa sumber shift, sehingga kolom sumber dana dapat menjadi `—`. Temuan statis, belum diuji browser dalam tugas ini.
- Pengajuan laci pending tidak menghalangi tutup shift, sedangkan approval kemudian mensyaratkan shift pemohon masih open.
- `SupplyTrips::voidReceipt` menolak final, sementara PHPDoc lama masih menjelaskan reversal. Pesan koreksi/retur tidak berarti fitur retur final sudah dibangun.

Bukti lebih rinci, pengecualian dan modul belum terpetakan terdapat dalam dokumen utama dan bagian batas analisis. Tidak ada perubahan aturan, uang, stok, jurnal, data, migrasi, commit, push, atau deploy pada tugas dokumentasi ini.
