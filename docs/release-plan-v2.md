# Rencana sampai siap publish V2

Scope: kebutuhan kanonik rancangan-operasional-v2.md; payroll ditunda. Produksi tidak disentuh sebelum gerbang lulus. Target akhir ready-to-publish bukan sekadar build.

## DAG dan acceptance

1. [x] Inspect kontrak, source, runtime, resource host.
2. [x] Worker berkas terpisah: OpeningBalances, DeliveryTrips, 3 Blade view.
3. [x] Mandor integrasi controller/route dan koreksi temuan runtime, lalu V2 tests exit0.
4. [x] Kandidat interaktif database sintetis; browser kasir/admin: shift, checkout DP transfer, approve, cicilan, import, dokumen, close/review. Screenshot aktual.
5. [x] MariaDB disposable: restore backup, migration, checksum legacy, test concurrency dua koneksi.
6. [x] Legacy bridge masters/roles/outstanding invoices tanpa journal/stok ganda; dry-run reconciliation sebelum apply.
7. [x] QC independen terikat hash final; source bundle/rollback dan PDF screenshot.
8. [x] Kandidat siap publish terkontrol dalam batas docs/publish-v2.md; produksi belum diubah. Gerbang saat deploy: backup cutoff terbaru, preflight ulang, role eksplisit dan health publik.

## Pembatas implementasi

- Uang muka sopir dibatasi realisasi <= uang muka; selisih lebih tidak diklaim didukung.
- Saldo awal hanya untuk instalasi kosong, tidak menggandakan pembukaan legacy.
- Berkas baru tetap worktree rebuild; live source dilarang disentuh selama kandidat belum siap.
- Satu pengujian fungsional relevan setelah landing, perbaikan hanya atas kegagalan empiris.
- Sprint maks30menit; bila belum semua selesai laporkan gerbang tersisa tanpa klaim siap publish.
