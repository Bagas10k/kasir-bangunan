# Panduan Operasional & Protokol Agent (`agent.md`)

Dokumen ini adalah pedoman tata kelola dan protokol wajib bagi setiap autonomous agent yang bekerja di repositori `kasir-bangunan-app`.

---

## 1. Urutan Baca Wajib Sebelum Bekerja
Sebelum menjalankan analisis, modifikasi berkas, atau mengeksekusi instruksi pengguna, agent WAJIB membaca dokumen dengan urutan berikut:
1. **`AGENTS.md` / `CLAUDE.md`**: Titik masuk konfigurasi dan panduan lingkungan kerja dasar.
2. **`agent.md`** (Dokumen ini): Memahami urutan baca, protokol verifikasi runtime, aturan pencatatan log eksekusi, serta integritas batasan ruang lingkup (scope).
3. **`desain.md`**: Memahami prinsip UI/UX, standar aksesibilitas, larangan emoji UI, ketentuan data sintetis, serta fleksibilitas tema berbasis referensi.
4. **`memori.md`**: Memahami fakta arsitektur aplikasi (subpath proxy, POS route, environment), batasan keamanan/kredensial, dan daftar fakta yang harus diverifikasi tiap tugas.
5. **`logeksekusi.md`**: Memeriksa riwayat pekerjaan sebelumnya, memahami format entri log terbukti, serta mempersiapkan pencatatan log untuk tugas aktif.

---

## 2. Integritas Scope & Perlindungan Workspace
- **Hormati Perubahan yang Ada**: Saat memulai pekerjaan, periksa `git status --short`. Jangan pernah mengubah, mengembalikan (*revert*), atau menimpa berkas yang sedang diubah oleh pekerjaan lain atau berkas pelacak seperti `build_tracker.json`.
- **Batas Sentuhan Berkas**: Modifikasi HANYA berkas yang secara eksplisit diminta atau menjadi ketergantungan langsung dari tugas. Jangan menyentuh berkas aplikasi lain, skema basis data, konfigurasi kontainer/deployment, atau berkas kredensial.
- **Karakteristik Deployment Produksi**: Host deployment menggunakan *read-only bind mount* ke dalam kontainer produksi. Perubahan berkas di host dapat langsung terbaca oleh aplikasi yang berjalan. Dilarang melakukan eksperimen sembarangan atau menulis berkas sementara di direktori aplikasi aktif.

---

## 3. Prinsip Verifikasi Runtime (Grounding Truth)
- **Hindari Asumsi**: Jangan pernah mengasumsikan keberadaan berkas, isi tabel database, status kontainer, rute publik, atau akun pengguna hanya berdasarkan riwayat percakapan sebelumnya.
- **Klaim Berbasis Bukti Nyata**: Setiap klaim keberhasilan, perbaikan bug, atau kelulusan fitur WAJIB didasarkan pada eksekusi perintah nyata (exit code 0, output PHPUnit/Pest di SQLite in-memory, curl response, atau inspeksi DOM/layout).
- **Pengujian Terisolasi**: Pengujian fungsional dan regresi wajib dijalankan menggunakan SQLite in-memory (`DB_CONNECTION=sqlite DB_DATABASE=:memory: APP_ENV=testing`). Dilarang menjalankan perintah destruktif seperti `migrate:fresh` terhadap database aktif atau kontainer produksi.
- **Transparansi Keterbatasan**: Jika suatu pengujian tidak dapat dijalankan (misalnya verifikasi browser terautentikasi karena ketiadaan kredensial vault), catat keterbatasan tersebut secara eksplisit sebagai gerbang yang belum diuji (*untested gate*), bukan mengklaim sukses semu (*phantom pass*).

---

## 4. Protokol Pembaruan Log Eksekusi (`logeksekusi.md`)
- **Wajib Diperbarui Setiap Tugas**: Setelah menyelesaikan pekerjaan dan verifikasi runtime, agent WAJIB mencatat hasil tugas pada berkas `logeksekusi.md`.
- **Bersifat Append-Only**: Dilarang keras menimpa (*overwrite*), menyunting mundur, atau menghapus riwayat entri log terdahulu. Entri baru selalu ditambahkan di bagian akhir dokumen.
- **Struktur Entri Valid**: Entri harus memuat tanggal eksekusi, identitas pelaksana, target tugas, daftar berkas yang dibuat/diubah, perintah verifikasi beserta kode keluar (*exit code*), serta ringkasan hasil pengujian nyata.
