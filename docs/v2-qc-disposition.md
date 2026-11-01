# Disposisi QC core V2 — sprint pertama

Status: review diterima, bukan persetujuan rilis. Source utama yang diperiksa induk cocok dengan hash review: `Operations.php` SHA-256 `515b6abdeffa03ed9287cbc299b4af4a1b051ab1396058d38d1e1565c74d2787`. Laporan asli privat: `/home/ubuntu/.hermes/work/kasir-rebuild-20261029/qc-core.md`.

## 1. Idempotensi paralel — UNCERTAIN, belum direproduksi

Review menyatakan dua checkout identik dapat sama-sama melewati cek kunci lalu gagal unique index. Source aktual `Operations.php:198-205` memanggil `openShiftFor($actor, true)` SEBELUM membaca idempotency_key. `openShiftFor` memakai `lockForUpdate` pada shift aktor. Dengan demikian klaim bahwa dua request aktor yang sama pasti tidak terserialisasi belum terbukti dan tidak boleh disebut bug terkonfirmasi.

Tetap ada gerbang NOT RUN: uji dua koneksi MariaDB untuk key sama, aktor sama/berbeda, isolasi transaksi dan lock; observasi status HTTP, satu transaksi persisted, serta respons replay/penolakan yang sesuai. Menangkap semua QueryException lalu menganggap sukses bukan perbaikan yang aman. Tidak ada perubahan kode dibuat dari hipotesis ini.

## 2. Uang muka transfer untuk penjualan kredit — KNOWN keterbatasan input

`Operations.php:291-316,408-440` memperlakukan `payment_method=credit` dengan `paid_amount>0` sebagai uang muka TUNAI. Controller belum memiliki metode uang muka terpisah. Produk belum mendukung perekaman uang muka transfer langsung di checkout kredit; tidak ada bukti bahwa request transfer yang didukung telah dibukukan salah.

Risiko UX: pengguna memasukkan DP transfer sebagai uang muka kredit tanpa metode eksplisit, sehingga kas laci salah. Selesaikan pada slice pembayaran: metode DP eksplisit, transfer tetap pending sampai admin, tidak masuk drawer/bank sebelum sah, dan tes penutupan shift. Sampai itu selesai, jangan mengiklankan semua variasi DP telah didukung.

## 3. Resolusi selisih shift — KNOWN alur belum lengkap

Selisih menghasilkan `review` (`Operations.php:136`); carry mensyaratkan `closed` (`:68`); route/service belum memiliki penyelesaian review oleh admin. Source juga mengizinkan buka shift baru setelah review, tetapi larangan seluruh aktivitas baru bukan keputusan pengguna yang sudah dikunci. Jangan menambahkan blokir kerja global tanpa dasar.

Kebutuhan berikutnya: aksi admin meninjau dengan alasan/aktor/timestamp dan hasil tercatat, menjaga counted/expected/difference historis, lalu menentukan kelayakan carry tanpa membukukan selisih otomatis sebagai biaya/pendapatan. Uji jalur review sampai selesai, bukan hanya perubahan enum.

## Bukti dan batas

- Parent-run terakhir: PHPUnit terisolasi SQLite `:memory:`, 15 tes / 86 assertion, exit 0 (`php-current-verification.log`).
- QC tidak menjalankan tes sendiri; report adalah inspeksi source, bukan reproduksi runtime.
- Bank outflow, self-approval, proposal supplier tanpa shift, konkurensi MariaDB dan migrasi target engine masih perlu bukti khusus.
- NOT NULL pada kolom wajib tetap berlaku; catatan SQL NULL dari review tidak otomatis membuktikan NULL dapat melewati schema.
- Visual final setelah perubahan grid masih belum terverifikasi karena capture timeout.
- Tidak ada perubahan production, migration live, atau promosi. Review tidak memicu loop perbaikan otomatis; ini backlog untuk sprint berikutnya.
