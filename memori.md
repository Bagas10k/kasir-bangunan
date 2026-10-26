# Memori & Arsitektur Repositori (`memori.md`)

Dokumen ini memuat ringkasan fakta arsitektur penting, konfigurasi lingkungan, dan batas keamanan repositori `kasir-bangunan-app` yang harus dipahami oleh setiap agent.

---

## 1. Fakta Arsitektur Aplikasi
- **Lokasi Proyek**: Repositori beralamat absolut di `/home/ubuntu/kasir-bangunan-app`.
- **Stack Teknologi**:
  - Backend: PHP 8.2+ dengan Laravel Framework.
  - Frontend: Blade templating engine, Tailwind CSS, Alpine.js / Vanilla JS untuk micro-interactions dan manajemen status keranjang belanja.
  - Database Runtime: MariaDB / MySQL untuk produksi; SQLite in-memory untuk pengujian otomatis terisolasi.
- **Topologi Jaringan & Subpath Reverse Proxy**:
  - Aplikasi publik beroperasi di balik *reverse proxy* pada subpath: `https://www.jajandigital.web.id/kasir-bangunan/`.
  - Rute POS utama adalah `route('pos.index')`.
  - Seluruh penanganan form login, tautan aset, dan pengalihan (*redirect*) wajib mempertahankan prefix `/kasir-bangunan`. Hindari pengalihan mentah seperti `redirect()->intended('/')` yang dapat melempar sesi ke domain root di luar aplikasi.
- **Mekanisme Deployment Produksi**:
  - Kontainer produksi menjalankan aplikasi menggunakan mekanisme *host bind mount read-only*.
  - Perubahan berkas pada direktori aplikasi di host dapat langsung terbaca oleh kontainer produksi yang sedang berjalan. Karena itu, hindari perubahan berkas eksperimental atau modifikasi kode tanpa pengujian terisolasi terlebih dahulu.

---

## 2. Batasan Keamanan, Kredensial, & Privasi Data
- **Kerahasiaan Kredensial**:
  - Dilarang keras membaca, mendokumentasikan, mencetak (*print* ke log/chat), atau menyalin nilai rahasia seperti kunci aplikasi (`APP_KEY`), kata sandi database, token API, session secret, atau berkas `.env`.
  - Jangan pernah menyimpan kata sandi demo atau akun administratif ke dalam dokumen panduan, berkas markdown, maupun skill.
- **Perlindungan Otomasi Peramban**:
  - Bila pengujian memerlukan akun terautentikasi di peramban, gunakan kredensial yang tersimpan di *browser vault* yang terisolasi. Jangan pernah mengetikkan kata sandi ke dalam skrip otomasi terbuka atau shell publik.
  - Jika kredensial vault tidak tersedia, laporkan secara terbuka bahwa gerbang login peramban publik belum diuji (*untested gate*).
- **Perlindungan Data Pribadi**:
  - Repositori ini tidak boleh memuat data pribadi riil (PII), nomor identitas kependudukan, nomor rekening aktif, atau nomor kontak asli milik siapapun. Selalu gunakan entitas sintetis dalam pengujian dan dokumentasi.

---

## 3. Fakta Operasional yang Wajib Diverifikasi Tiap Tugas
Sebelum melakukan tindakan pada basis kode atau data, agen wajib melakukan verifikasi faktual:
1. **Status Kontainer & Basis Data**: Jangan berasumsi database atau kontainer aktif. Bedakan antara lingkungan produksi, demo/stage, dan lingkungan uji terisolasi sebelum melakukan kueri atau migrasi.
2. **Pemisahan Lingkungan Pengujian**:
   - Pengujian fitur dan unit test WAJIB dijalankan dalam konteks terisolasi: `DB_CONNECTION=sqlite DB_DATABASE=:memory: APP_ENV=testing`.
   - Dilarang keras menjalankan `php artisan migrate:fresh` pada kontainer atau database produksi.
3. **Penyimpanan Nilai Uang (Presisi Desimal)**:
   - Nilai uang dan HPP dihitung menggunakan tipe data string desimal dan presisi tinggi (`BigDecimal`), bukan operasi aritmetika floating point native (`(float)` atau JavaScript `Number()`) yang rentan penyimpangan sen IEEE-754.
4. **Verifikasi Perubahan Berkas**:
   - Selalu periksa `git status --short` sebelum dan sesudah bekerja untuk memastikan tidak ada efek samping (*side effects*) atau berkas liar yang tertinggal.
