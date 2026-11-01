# Publish V2 — runbook kandidat

Status dokumen: instruksi rilis, BUKAN bukti deployment. Target aktif tetap `/home/ubuntu/kasir-bangunan-app` dan database yang sama. Source kandidat `/home/ubuntu/kasir-bangunan-rebuild`. Jangan mengosongkan database, mengganti password, atau menjalankan migrate:fresh.

## Cakupan dan batas

- Kasir/admin; master create/update/archive; master Excel preview+commit; saldo awal instalasi kosong; shift/carry/review; diskon item lalu invoice; ongkir terpisah; cash/credit/pending transfer; cicilan customer/supplier; pengiriman; biaya aktual dan uang muka/realisasi sopir; ringkasan per shift; nota/surat jalan cetak.
- Payroll ditunda. Biaya realisasi sopir > uang muka ditolak; gunakan pencatatan biaya terpisah yang tidak menduplikasi jumlah aktual yang sudah diselesaikan.
- Master tampil maksimal100 terbaru, shift/sales30, invoice100, payment50 di workspace. Batas ini harus diketahui: bukan arsip lengkap. Laporan menjumlahkan seluruh data sesuai cakupan, bukan page limit.
- Saldo awal stok/piutang/utang rinci masih input JSON berlabel pada form admin. Excel hanya master, bukan saldo/transaksi. Jangan menjanjikan wizard saldo awal nonteknis.
- Tidak ada edit/hapus dokumen posted. Koreksi finansial/retur belum termasuk UI V2; jangan mengedit jurnal langsung.
- Hari bisnis penjualan/kas memakai tanggal buka shift. Bank posted memakai tanggal jurnal verifikasi; pending adalah status kini dari pengajuan pada tanggal terpilih, bukan snapshot historis pending.
- `APP_TIMEZONE=Asia/Jakarta` mengatur transaksi baru. Timestamp historis yang sebelumnya UTC tidak ditulis ulang. Arsip lama admin tetap tersedia read-only.

## Bukti yang harus dibaca

Workspace privat `/home/ubuntu/.hermes/work/kasir-rebuild-20261029/`: `full-final-tests.log`, `mariadb-constraint-tests.log`, `mariadb-rehearsal.json`, `concurrency-proof.json`, `prepare-rehearsal.json`, screenshot kandidat. Fixture memakai data sintetis. Login browser diuji menggunakan sesi sintetis Laravel, bukan kata sandi produksi; login/password publik belum diuji lewat vault.

## Prasyarat sebelum menyentuh target

1. Jadwalkan jendela henti transaksi. Bekukan akses tulis di proxy/maintenance sebelum backup cutoff; jangan hanya meminta pengguna berhenti. Bila ada queue worker/scheduler khusus aplikasi ini, hentikan pemrosesannya dan tunggu pekerjaan aktif selesai. Inventaris dulu, jangan menghentikan service tetangga. Catat release hash dan siapa operatornya.
2. Baca mount live dan DB effective dari container yang benar. Jangan mengandalkan nama dari riwayat.
3. Ambil backup BARU source tracked+dirty+untracked, `.env`/config secara privat mode600, dan dump DB transactional termasuk triggers. `gzip -t` + checksum. Backup lama dipakai rehearsal, bukan menggantikan backup cutover terbaru.
4. Restore backup terbaru pada DB disposable dan cocokkan COUNT/CHECKSUM journal_entries, journal_entry_lines, products, stock_movements. Jangan mengimpor ke database produksi untuk latihan.
5. Siapkan ID akun admin dan akun kasir existing secara eksplisit. `v2:prepare` tidak membuat user atau mengganti password. Jika belum ada akun kasir, provision lewat prosedur akun terotorisasi; jangan membuat password dummy dalam source.
6. Bandingkan HEAD source bundle dengan checkout aktif, termasuk perubahan remote/lokal setelah kandidat dibuat. Jangan menimpa perubahan lebih baru secara massal.

## Urutan deploy terkontrol (contoh perintah artisan)

Perintah berikut dijalankan pada container aplikasi yang sudah dipastikan benar, sesudah bundle source ditempatkan dan sebelum akses baru dibuka. Angka `<ADMIN_ID>`/`<CASHIER_ID>` adalah parameter operator, bukan nilai yang boleh ditebak.

```sh
php artisan down
# Proxy tetap menahan pengguna; pekerja/scheduler khusus aplikasi telah dihentikan.
php artisan migrate --force
php artisan v2:prepare --admin=<ADMIN_ID> --cashier=<CASHIER_ID>
php artisan v2:prepare --admin=<ADMIN_ID> --cashier=<CASHIER_ID> --apply
php artisan view:clear
php artisan config:clear
# Pastikan environment baru terbaca, cache/OPcache source sesuai, dan health internal lulus.
php artisan up
# Buka akses proxy paling akhir; mulai ulang hanya pekerja yang memang ditemukan.
```

`v2:prepare` default read-only, menolak AR/AP/sisa kredit legacy yang belum dipetakan. --apply hanya menambah COA sistem yang belum ada dan grant role eksplisit; tidak membuat jurnal/stok/saldo awal. Saldo legacy menggunakan tabel stock/journal yang sama, sehingga tidak disalin lagi. Hasil rehearsal snapshot: outstanding legacy nol, checksum unchanged. Jangan menganggap snapshot itu kondisi live terbaru.

Aktifkan `V2_ENABLED=true` dan `APP_TIMEZONE=Asia/Jakarta` pada environment deployment resmi; pastikan environment benar-benar terbaca, bukan hanya menulis .env yang kalah oleh Compose. Recreate hanya app jika env berubah; tidak restart database atau service tetangga. Root sesudah login menuju V2; jalur tulis legacy ditolak dan arsip GET admin tetap dapat dibaca. Ketika flag false, perilaku legacy tetap digunakan untuk rollback sebelum transaksi V2 dimulai.

## Acceptance sesudah deploy

- Baca balik role, COA, migrations dan hash jurnal/stok baseline sebelum transaksi baru.
- Login HTTP200 tidak cukup: akun kasir membuka workspace, tidak dapat master/legacy write; admin dapat master dan arsip.
- Uji nonmutating public `/kasir-bangunan/login`, `/kasir-bangunan/up`, redirect root subpath. Transaksi uji jangan menambah data palsu di buku nyata; lakukan pada clone, lalu validasi satu transaksi bisnis sah jika pemilik mengotorisasi.
- Catat URL, source hash, waktu, exit status, dan perubahan data yang memang diharapkan.

## Rollback

- SEBELUM transaksi V2 pertama: nonaktifkan flag dan kembalikan source backup yang cocok. Migrasi aditif tidak perlu DROP; jangan migrate:rollback/drop otomatis.
- SETELAH transaksi V2: hentikan input baru. Flag false saja TIDAK aman—legacy tidak mengenali subledger V2 dan dapat menulis bertabrakan. Pilih forward-fix atau restore database+source pada cutoff yang disepakati dengan rekonsiliasi transaksi sesudah backup. Jangan memulihkan backup sambil membuang transaksi diam-diam.
- Tidak ada rollback database otomatis dalam bundle ini. Pemulihan DB membutuhkan persetujuan cutoff dan daftar transaksi terdampak.
