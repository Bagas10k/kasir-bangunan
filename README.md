<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Pembangunan operasional V2

Status: **kandidat rilis V2 teruji, belum dipasang ke produksi**. Runbook aktivasi, batas fitur, dan rollback berada di [docs/publish-v2.md](docs/publish-v2.md). Rancangan dan keputusan bisnis berada di [docs/rancangan-operasional-v2.md](docs/rancangan-operasional-v2.md); kontrak implementasi di [docs/v2-contract.md](docs/v2-contract.md); status terkini di [docs/build_tracker_v2.json](docs/build_tracker_v2.json).

### Prasyarat dan batas aman

- Runtime teruji: PHP 8.5.11 di container `composer:2`, Node 26.8.2 dan npm 11.19.1. Manifest menetapkan PHP ^8.3; kompatibilitas versi lain belum diuji ulang untuk V2.
- Source V2: worktree `/home/ubuntu/kasir-bangunan-rebuild`, branch `rebuild/operasional-v2`. Source produksi `/home/ubuntu/kasir-bangunan-app` di-bind langsung ke container; jangan memakai produksi untuk tes.
- `v2_*` bersifat additif. `products`, `stock_movements`, COA dan jurnal legacy dipakai sebagai adapter sumber data tunggal, bukan dibuat ledger kedua.
- User lama tidak otomatis memperoleh akses V2. Grant peran admin/cashier harus eksplisit; kata sandi dan identitas login lama tidak diganti.

### Build aset (teruji)

```sh
npm ci --ignore-scripts --no-audit --no-fund
npm run build
```

`hermes verify --json` saat ini mendeteksi Vite dan menghasilkan `ok:true`; readiness Vite HTTP 404 **bukan** smoke test aplikasi Laravel.

### Tes inti (teruji dalam container terisolasi)

Jalankan PHPUnit dengan `APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= SESSION_DRIVER=array` dan APP_KEY ephemeral, pada source serta vendor tree yang sama:

```sh
php vendor/bin/phpunit --do-not-cache-result --fail-on-warning --display-warnings \
  tests/Feature/V2*Test.php
```

Hasil paket hasil ekstraksi: **345 tests, 2137 assertions, exit 0**, delapan tes MariaDB dilewati di SQLite. Tes MariaDB terpisah **9 tests, 22 assertions, exit 0**, satu tes khusus SQLite dilewati. Koreksi middleware final sudah termasuk dalam hasil ini; laporan rilis terpisah memuat hash artefak yang diserahkan. Host tidak memiliki PHP; runner container network-isolated memakai vendor disposable di `/home/ubuntu/.hermes/cache/scratch/kasir-v2-runtime/vendor`. Vendor itu dihasilkan dengan `composer dump-autoload --no-scripts --no-interaction` pada mount `/app` agar classmap tidak menunjuk source produksi. Jangan menganggap tes SQLite membuktikan lock/precision MariaDB atau browser UI.

### Pemulihan alur kasir (kandidat, belum produksi)

Kasir V2 mengikuti pola kasir lama: katalog dan keranjang langsung tersedia, pembayaran dan tinjau transaksi di popup. Kasir lama `resources/views/pos/index.blade.php` tidak diubah. Status revisi dan bukti terbaru: [docs/ui-polish-tracker.json](docs/ui-polish-tracker.json).

```sh
node scripts/test-v2-pos-ui.cjs
bash scripts/test-v2.sh
```

Hasil revisi UI: 9 pemeriksaan pratinjau angka dan 68 tes / 563 assertion, exit 0. Browser kandidat sintetis memverifikasi batal tanpa POST, konfirmasi ganda menghasilkan satu POST, nota tersimpan, serta keyboard pada 1440/390. **Paket rilis yang disebut di atas mendahului revisi UI ini dan belum dikemas ulang.** Audit Axe/perangkat fisik belum dijalankan; penilaian visual pemilik masih menunggu.

### Run dan rilis

Route `/v2` memakai autentikasi Laravel dan peran V2. Browser kandidat sintetis sudah menjalankan penjualan, transfer, cicilan, supplier, impor, pengiriman, uang muka/realisasi, dan review shift. Restore backup, migrasi, checksum legacy serta retry paralel MariaDB teruji pada database disposable. **Produksi belum dipromosikan**. Jalankan pra-rilis terbaru dan backup cutoff sebelum aktivasi; jangan menjalankan composer `setup` terhadap produksi. Lihat [publish-v2.md](docs/publish-v2.md).

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
