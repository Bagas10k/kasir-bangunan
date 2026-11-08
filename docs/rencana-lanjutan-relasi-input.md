# Rencana lanjutan relasi penginputan data

Status: SELESAI DIEKSEKUSI dan DITUTUP 2026-11-05 (lihat entri terakhir `logeksekusi.md`).
Koreksi 2026-11-05: bagian §4–§6 di bawah adalah catatan checkpoint 2026-11-04 dan SEBAGIAN USANG. Dua tes yang disebut stub sudah berisi tes substantif; migrasi relasi sudah Ran di MySQL lokal; suite penuh 430 tes hijau; read-back MySQL dan uji browser fixture 1440/390 sudah dijalankan. Gerbang yang masih NOT RUN: login ke aplikasi asli (vault kosong), Axe, perangkat fisik.
Pembaruan Selesai: 2026-11-04 20:30 WIB.
Workspace: `C:\laragon\www\tb01`.
Branch terverifikasi: `feat/cash-flow-role-hierarchy`.
Pelaksana: Hermes / ag/gemini-3.8-flash-high.

## 1. Mandat dan keputusan yang sudah disepakati

Fokus pada proyek workspace ini, khususnya keterhubungan penginputan data V2. Jangan membawa implementasi proyek PBJ/server lain.

- Sopir karyawan dan sopir luar sama-sama terdaftar dan dipilih, bukan mengetik identitas bebas pada tiap transaksi.
- Sopir internal terhubung ke master karyawan.
- Produk bisa memiliki beberapa supplier; supplier utama opsional.
- Satuan dan kategori memakai master bersama.
- Pemilihan surat jalan menurunkan tujuan dan penugasan kendaraan/sopir. Server wajib menegakkan kecocokan relasi.
- Pengguna sebelumnya menyetujui implementasi dan migrasi lokal tanpa reset data, setelah pengujian terisolasi. Pesan terakhir meminta berhenti dan meninggalkan rencana karena token habis; jangan mengeksekusi migrasi pada checkpoint ini.
- Riwayat teks lama dipertahankan; identitas sopir lama tidak ditebak dari kesamaan nama. Jangan mengubah perhitungan uang, stok, atau jurnal sebagai perluasan scope.
- Tidak commit, push, reset, atau deploy ke server lain.

## 2. Wajib dibaca saat melanjutkan

Baca `AGENTS.md`, `CLAUDE.md`, `agent.md`, `desain.md`, `memori.md`, dan akhir `logeksekusi.md`. Periksa ulang `.ai/rules/index.md` bila direktori itu muncul (sebelumnya tidak ada). Baca skill lokal `.agents/skills/laravel-best-practices/SKILL.md` dan `.agents/skills/testing-best-practices/SKILL.md` beserta aturan sesuai file.

Skill konteks: `otak-koding`, `database-design-migrations`, `erp-engineering-doctrine`, `hermes-problem-integrity`, `mode-planning`, `ponytail`, `autonomous-orchestrator`; UI: `frontend-agent-craft`, `e2e-browser-testing`. Skill material/PBJ bukan izin melakukan reset atau mengganti pola proyek ini.

Jalankan `git status --short` dan baca diff sebelum mengedit. Working tree sejak awal sudah berisi perubahan pengguna/pekerjaan lain. Jangan mengembalikan seluruh file ke HEAD atau menganggap seluruh diff hasil tugas ini.

## 3. Toolchain dan lingkungan terverifikasi

- Laravel 13.38.0; PHP yang benar 8.4.27.
- PHP default shell ternyata 8.3.7 dan gagal platform check Composer (memerlukan >= 8.4.1).
- Selalu gunakan binary eksplisit:
  `C:/laragon/bin/php/php-8.4.27-1-nts-Win32-vs17-x64/php.exe`
- Composer melalui PHP tersebut + `C:/laragon/bin/composer/composer.phar`.
- Terminal Hermes pada Windows menggunakan bash, bukan PowerShell. Pakai path native forward slash untuk executable.
- Tes wajib `APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= CACHE_STORE=array`.
- Database lokal yang dibaca Boost: MySQL, host 127.0.0.1:3306, database kasir_bangunan. Jangan membaca `.env` atau mencetak kredensial.
- Pembacaan agregat saat checkpoint: products=0, product_options=2, v2_parties=0, inventory_receipts=0, v2_deliveries=0, v2_trips=0, v2_driver_advances=0, journal_entries=0. Ini observasi saat itu, bukan alasan reset dan bukan jaminan keadaan berikutnya.
- Boost `GetAbsoluteUrl` menghasilkan `http://localhost:8000/v2`, tetapi curl HEAD mendapat 404 dan header PHP/8.3.7. URL itu belum terbukti melayani workspace ini. Jangan mengganti/mematikan proses yang sudah ada tanpa identifikasi.
- Tool Boost dapat dipanggil lewat Artisan `boost:execute-tool` dengan class `Laravel\Boost\Mcp\Tools\...` dan argumen JSON base64. Gunakan `DatabaseSchema` sebelum skema, `DatabaseQuery` untuk baca SQL, `SearchDocs` untuk API, dan `GetAbsoluteUrl` sebelum membagikan URL.

## 4. Yang sudah ditulis (belum seluruhnya diverifikasi)

### Skema

`database/migrations/2026_11_04_190820_add_v2_master_relations.php`:

- `product_options.is_active`.
- `products.unit_id/category_id` nullable FK ke product_options; teks lama tetap disimpan.
- `v2_product_suppliers`: product_id, supplier_id, is_primary, timestamps, unique pasangan. Generated column `primary_product_id` dengan unique index membatasi satu supplier utama per produk.
- `v2_drivers`: code unik, name, kind internal/external, employee_id nullable unik FK v2_parties, phone, notes, is_active, timestamps.
- `driver_id` nullable FK pada v2_deliveries, v2_trips, v2_driver_advances; driver_name historis tetap ada.
- Backfill opsi dari teks produk yang tidak kosong dan muat 50 karakter; tidak menimpa label/harga/stok.
- Backfill relasi supplier hanya dari invoice V2 payable -> receipt items yang memiliki identitas supplier nyata; tidak memilih supplier utama otomatis.
- Tidak membuat sopir dari nama historis.

PENTING: `artisan migrate:status` pada checkpoint exit 0 dan migrasi ini masih `Pending`. Belum ada migrasi database lokal.

### Backend

- `app/Services/V2/MasterData.php`: perubahan normalisasi opsi dan sinkronisasi supplier produk; mempertahankan lima kind lama.
- `app/Services/V2/MasterRelations.php`: master driver/unit/category; API `records(actor,kind)`, `store(actor,kind,data)`, `update(actor,kind,id,data)`, `archive(actor,kind,id)`, `driver(id)`, `productAttributes(data,row)`, `syncProductSuppliers(productId,data)`.
- `app/Services/V2/TransportAssignment.php`: penguncian surat jalan, validasi penugasan dan reservasi prepared delivery yang belum ditugaskan.
- `app/Services/V2/DeliveryTrips.php`, `SupplyTrips.php`: perubahan driver_id, snapshot nama dari master, hubungan surat jalan, pengecekan supplier-produk. Wajib baca diff dan uji; jangan anggap selesai karena file sudah ada.
- `app/Http/Controllers/V2/WorkspaceController.php`: query master relasi, driver aktif, opsi produk, supplier links, transport deliveries; endpoint relasi. File ini juga punya perubahan sebelum tugas (filter/paginasi riwayat POS), jangan ditimpa.
- `routes/v2.php`: POST/PUT/PATCH `/v2/relations/{kind}` untuk driver/unit/category.

Kontrak produk dari UI: `unit_id`, `category_id`, `supplier_ids` array, `primary_supplier_id` opsional. Excel lama tetap memakai teks unit/category yang dinormalisasi ke master saat commit; preview tidak boleh menulis master.

### UI

Diubah:
- `resources/views/v2/master-page-item.blade.php`
- `resources/views/v2/sprint2.blade.php`
- `resources/views/v2/driver-operations.blade.php`
- `resources/views/v2/workspace.blade.php`
- `resources/views/v2/ui-workspace.blade.php`

Ditambah:
- `resources/views/v2/product-supplier-fields.blade.php`
- `resources/views/v2/driver-options.blade.php`
- `resources/views/v2/relation-master.blade.php`
- `resources/views/v2/relation-fields.blade.php`

UI memuat menu Sopir serta Satuan & Kategori, pilihan master pada produk, checkbox multi-supplier + utama, filter produk sesuai supplier penerimaan, pilihan driver_id, dan pengisian tujuan/penugasan dari surat jalan. Baru diuji rendering HTTP, belum interaksi browser.

### Tes

- `tests/Feature/V2RelationMigrationTest.php`: uji upgrade/backfill dan satu supplier utama.
- `tests/Feature/V2WorkspaceViewTest.php`: fixture sintetis relasi + assertion form/role; bisa menulis HTML fixture melalui V2_PREVIEW_DIR.
- `tests/Feature/V2MasterRelationsTest.php`: sudah berisi tes substantif; belum ada hasil lulus parent yang tercatat.
- `tests/Feature/V2OperationalRelationsTest.php`: pada pembacaan checkpoint hanya helper fixture/assertion, BELUM ADA METODE TES. Wajib diselesaikan; syntax valid bukan coverage.
- `tests/Feature/V2RelationFormsTest.php`: MASIH STUB `test_example`. Wajib diganti tes endpoint relevan, bukan dibiarkan memberi kesan coverage.
- `tests/Feature/V2DeliveryTripsTest.php` dan `V2SupplyTripsTest.php`: fixture lama sudah mulai disesuaikan subagen; baca diff dan pertahankan assertion bisnis.

Dua subagen diminta stop saat pengguna meminta checkpoint. Jangan menunggu atau polling mereka; bila laporan parsial datang, cocokkan dengan file nyata sebelum menggunakan klaimnya.

## 5. Bukti yang benar-benar dijalankan parent

Semua PHPUnit di bawah memakai SQLite :memory: dan APP_ENV=testing.

1. `artisan test --compact tests/Feature/V2RelationMigrationTest.php`: pertama gagal karena rebuild SQLite products di dalam transaksi RefreshDatabase saat upgrade. Tes diperbaiki agar migrasi berjalan tanpa outer transaction, pada koneksi :memory: baru. Rerun: exit 0, 2 tests, 7 assertions.
2. `artisan test --compact tests/Feature/V2WorkspaceViewTest.php`: exit 0, 3 tests, 86 assertions. Hasil ini sebelum perubahan terakhir tabel tampilan supplier dan conditional cell relation-master; rerun setelah final edit.
3. `artisan view:cache --no-interaction`: exit 0 pada snapshot sebelum beberapa edit terakhir.
4. `npm run build`: exit 0, Vite 8.3.4. Build tidak menguji perilaku JS inline Blade.
5. `php -l` untuk MasterRelations, TransportAssignment, V2OperationalRelationsTest: exit 0 pada checkpoint. Operational test belum memiliki test case meski syntax valid.
6. `artisan migrate:status --no-interaction`: exit 0, migrasi relasi tetap Pending.

Belum dijalankan/diselesaikan: seluruh tes relasi dan regresi V2, formatter Pint, uji browser desktop/mobile, migrasi lokal, verifikasi runtime terautentikasi. Tidak ada klaim semua selesai.

Fixture HTML sintetis hasil V2WorkspaceViewTest:
`C:/Users/bagas/AppData/Local/hermes/cache/scratch/tb01-relations-preview/admin.html`
`C:/Users/bagas/AppData/Local/hermes/cache/scratch/tb01-relations-preview/cashier.html`
Direktori scratch dapat dipangkas otomatis; regenerate bila hilang. Jangan menganggap fixture statis sebagai keberhasilan backend nyata.

## 6. Rencana eksekusi berikutnya, berurutan

### A. Integrasi dan celah backend (prioritas pertama)

1. Baca ulang file aktual dan diff. Selesaikan tes operational yang baru helpers serta stub forms.
2. Cocokkan controller/routes/UI dengan MasterRelations. MasterData::kinds() harus tetap lima kind lama; master driver/unit/category memakai endpoint relations terpisah.
3. Uji input produk berbasis ID tanpa unit/category teks; update parsial harus menjaga nilai yang tidak diubah. Supplier utama harus termasuk supplier produk, duplikat/pihak bukan supplier/arsip ditolak tanpa partial write.
4. Periksa perubahan sopir internal -> eksternal: UI men-disable employee_id, sedangkan service update menggabungkan data dengan row lama. Bila employee_id lama ikut dipertahankan, perpindahan jenis dapat gagal; tentukan clearing eksplisit dengan tes. Jangan ubah identitas histori.
5. Periksa edit produk yang masih terhubung opsi/supplier arsip. UI mempertahankan pilihan arsip, tetapi service menolak master tidak aktif. Harus ada perilaku jelas dan pesan yang dapat ditindaklanjuti; jangan menghapus link diam-diam.
6. Uji dispatch/ritase/advance dengan driver aktif/nonaktif, employee salah kind/arsip, vehicle nonaktif, penugasan silang, input nama/tujuan palsu, pengulangan idempotency setelah rename/arsip, dan konflik uang muka vs biaya aktual. Kegagalan tidak boleh meninggalkan posting/jurnal/stok berubah.
7. Tentukan perilaku surat jalan historis ber-driver_name namun driver_id kosong: jangan tebak identitas. TransportAssignment saat ini menganggap unassigned hanya jika driver_id, vehicle_id, driver_name semuanya null. Pastikan tidak memblokir penyelesaian riwayat tanpa penjelasan; minta keputusan pengguna jika butuh rekonsiliasi manual baru.
8. Pastikan pemilihan surat jalan lama (tidak masuk 30 penjualan terbaru) tersedia tanpa melonggarkan role scope. Query transportDeliveries saat ini seluruh delivery khusus admin; evaluasi kebutuhan batas/search tanpa memotong pilihan operasional secara diam-diam.
9. Periksa generated unique primary supplier dan FK pada engine MySQL lokal. Uji SQLite bukan bukti konkurensi/locking MySQL. Jangan mengubah schema yang sudah diterapkan sebelumnya.

### B. Verifikasi otomatis

Jalankan PHP eksplisit, contoh bash:

    APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= CACHE_STORE=array "C:/laragon/bin/php/php-8.4.27-1-nts-Win32-vs17-x64/php.exe" artisan test --compact tests/Feature/V2RelationMigrationTest.php tests/Feature/V2MasterRelationsTest.php tests/Feature/V2OperationalRelationsTest.php tests/Feature/V2RelationFormsTest.php tests/Feature/V2WorkspaceViewTest.php tests/Feature/V2DeliveryTripsTest.php tests/Feature/V2SupplyTripsTest.php

Lalu regresi yang relevan, misalnya seluruh `--filter=V2` serta accounting/cash flow bila jalur terpengaruh. Gunakan timeout cukup; jangan menyebut timeout sebagai lulus. Run setelah setiap perubahan tes.

Jalankan formatter wajib `vendor/bin/pint --dirty --format agent` melalui PHP 8.4. Periksa cakupan dirty files sebelum/sesudah agar tidak menimpa perubahan pengguna di luar tugas. Jalankan `git diff --check`, `artisan view:cache`, `npm run build`, dan rerun tes setelah format.

### C. Browser dan UI

- Regenerate fixture sintetis memakai V2_PREVIEW_DIR pada V2WorkspaceViewTest; jangan tampilkan data pribadi nyata.
- Jalankan preview GET-only di port kosong yang benar-benar diidentifikasi. Blokir mutasi/jaringan produksi dari fixture, jangan arahkan fetch fixture ke aplikasi aktif.
- Desktop 1440px dan mobile 390px: buka modal produk, pilih unit/kategori, dua supplier, utama; ganti supplier penerimaan lalu pastikan pilihan barang lama dibersihkan; pilih surat jalan lalu pastikan tujuan dan penugasan sesuai.
- Modal sopir: internal mengambil nama karyawan, external bisa diisi; keyboard Tab/Escape, fokus kembali, tidak overflow, error tetap terlihat.
- Screenshot viewport nyata dan inspeksi gambar. Bedakan bukti fixture browser dari backend HTTP.
- Uji runtime terautentikasi hanya via browser vault (list terlebih dahulu). Jika vault tidak tersedia, laporkan gate login belum diuji; jangan membuat kredensial atau menulis password ke skrip.

### D. Migrasi lokal setelah tes lulus dan tugas dilanjutkan

- Revalidasi target DB lokal serta migration status. Jangan migrate:fresh, reset, seed data demo, atau menghapus transaksi.
- Kode saat ini sudah membaca tabel relasi secara langsung sementara migrasi masih pending: workspace runtime belum siap dipakai pada schema lama. Jangan klaim UI lokal aktif sebelum migrasi/verification.
- Tinjau SQL dengan `artisan migrate --pretend --path=database/migrations/2026_11_04_190820_add_v2_master_relations.php --no-interaction` dan pastikan hanya migrasi ini.
- Setelah gerbang lulus, jalankan migrasi aditif yang sudah diizinkan pengguna, memakai path spesifik. Jangan menyapu migrasi lain yang tidak termasuk tugas.
- Read-back dengan Boost DatabaseSchema/DatabaseQuery: FK tersedia, link tidak orphan, satu primary per produk, jumlah record historis/nominal/stock tetap sesuai sebelum migrasi, tidak ada sopir hasil tebakan nama.
- `artisan view:clear --no-interaction`; temukan server workspace dengan PHP yang benar. Jangan mengganti aplikasi lain yang kebetulan memakai port 8000.

### E. Penutupan

Append hasil nyata ke logeksekusi.md, update checklist hanya untuk yang benar-benar terverifikasi, laporkan batas uji yang tersisa. Jangan commit/push tanpa permintaan baru.

## 7. Kriteria selesai

- Master sopir internal/eksternal, opsi produk, multi-supplier dan primary terhubung melalui ID.
- Semua jalur input terkait memakai pilihan yang sama dan server menolak relasi salah secara atomik.
- Riwayat lama tetap tersedia; tidak ada reset atau perubahan perhitungan keuangan/stok.
- Tes relevan dan build lulus pada snapshot akhir; tidak ada stub tes yang dianggap coverage.
- Migrasi lokal selesai serta dibuktikan read-back; browser diuji atau batas yang benar-benar terblokir disebut tegas.

## 8. Perubahan yang bukan milik tugas ini

Sejak awal sudah dirty: AGENTS.md, CLAUDE.md, composer.json/lock, logeksekusi.md, bagian WorkspaceController dan berkas pos-cashier*. Untracked awal mencakup .agents, .claude, .mcp.json, boost.json, opencode.json. Jangan menghapus, men-stage massal, atau mengubahnya sebagai pembersihan checkpoint.
