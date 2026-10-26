<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>


## Aturan Dokumentasi Wajib Repo Kasir Bangunan

Setiap agent yang bekerja pada repositori ini WAJIB membaca dan mematuhi dokumen tata kelola berikut sebelum memulai pekerjaan:

1. [`agent.md`](agent.md) — Urutan baca, prinsip verifikasi runtime, aturan logging, dan integritas scope pekerjaan.
2. [`desain.md`](desain.md) — Prinsip UI/UX, aksesibilitas (A11y), larangan emoji UI, aturan data sintetis, dan referensi desain.
3. [`memori.md`](memori.md) — Fakta arsitektur penting, penanganan reverse proxy subpath, isolasi testing, dan batas kerahasiaan.
4. [`logeksekusi.md`](logeksekusi.md) — Format entri riwayat kerja (append-only) dan kewajiban membuktikan klaim dengan pengujian empiris.

Dilarang menyentuh file di luar cakupan tugas, dilarang menimpa log eksekusi terdahulu, dan selalu lakukan pengujian terisolasi sebelum mengklaim selesai.
