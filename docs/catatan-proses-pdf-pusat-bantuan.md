# Catatan Proses — PDF Pusat Bantuan

Status: **BELUM SELESAI** (script siap, PDF belum dibuat, belum diverifikasi).
Tanggal catatan: 2026-11-28.

## Tujuan

Membuat satu PDF rapi berisi **seluruh 41 artikel Pusat Bantuan** (5 modul), dengan sampul,
halaman "Tentang panduan ini", daftar isi berhalaman, bookmark, dan nomor halaman.
Keluaran akhir: `docs/pusat-bantuan-kasir-bangunan.pdf`.

## Sudah selesai

1. **Sumber materi dibaca dan diekspor** dari `config/help-center.php`:
   - `C:\Users\bagas\AppData\Local\Temp\opencode\help-center-source.json`
   - Dibuat dengan: `php -r "echo json_encode(require 'config/help-center.php', JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);" | Set-Content -Encoding utf8 <path>`
   - Isi: `reviewed`, `details`, `illustrations`, `groups` (5 modul, 41 artikel).
2. **Script pembangun PDF ditulis** (belum dijalankan):
   - `C:\Users\bagas\AppData\Local\Temp\opencode\build-help-pdf.py`
   - ReportLab Platypus: `BaseDocTemplate` + `multiBuild`, TOC dua tingkat, bookmark
     outline, header/footer + nomor halaman, label akses, kotak peringatan kuning,
     skema "letak kontrol" (bukan tangkapan layar), daftar panduan terkait.
   - Ada `assert` penjaga: 41 artikel, 5 modul, setiap artikel punya `details` dan
     `illustrations`. Bila salah satu gagal, PDF tidak akan ditulis.
3. **Aturan proyek sudah dibaca**: `agent.md`, `desain.md`, `memori.md`, `logeksekusi.md`,
   `docs/dokumentasi-sistem.md` bagian 1.7, memori bersama (`10`, `20`, `50`).

## Belum selesai

1. **Dependensi ReportLab belum terpasang.** Perintah yang dijalankan:
   ```
   python -m pip install --target C:\Users\bagas\AppData\Local\Temp\opencode\pdf-tools reportlab pymupdf
   ```
   Proses ini masih berjalan di latar belakang dan **lambat** karena PyMuPDF 63 MB.
   PyMuPDF hanya dipakai untuk verifikasi, bukan untuk membangun PDF. Jalur cepat:
   ```
   python -m pip install --target C:\Users\bagas\AppData\Local\Temp\opencode\pdf-tools reportlab
   ```
2. **PDF belum dibangun.** Jalankan:
   ```
   python "C:\Users\bagas\AppData\Local\Temp\opencode\build-help-pdf.py"
   ```
   Keluaran: `docs/pusat-bantuan-kasir-bangunan.pdf` + `help-center-pages.json`
   (peta halaman tiap artikel untuk pemeriksaan).
3. **Belum diverifikasi**: jumlah halaman, kelengkapan 41 judul, TOC menunjuk halaman
   yang benar, tidak ada teks terpotong/overflow, bookmark berfungsi, nomor halaman
   konsisten (sampul = halaman 1 tanpa angka tercetak).
4. **Belum dicatat** di `logeksekusi.md` (append-only, format bagian 1 dokumen itu) dan
   belum ada entri di `MEMORI-BERSAMA/40-jurnal.md`.

## Cara memeriksa hasil

```powershell
# jumlah halaman + teks per halaman (butuh pymupdf)
python -c "import fitz;d=fitz.open(r'C:\laragon\www\tb01\docs\pusat-bantuan-kasir-bangunan.pdf');print(d.page_count);print(d.metadata['title']);print(d.get_toc()[:8])"
```

Yang harus benar:
- `page_count` wajar (perkiraan 35–50 halaman untuk 41 artikel).
- `get_toc()` berisi 5 entri level 0 dan 41 entri level 1, judulnya sama dengan
  `config/help-center.php`.
- Setiap artikel muncul sekali; tidak ada artikel yang hilang atau dobel.
- Cari teks `!!` atau `((` di PDF: **tidak boleh ada** (penanda harus sudah dirender).
- Halaman daftar isi menampilkan nomor halaman yang sama dengan halaman artikel.

## Catatan teknis bila perlu diubah

- Font memakai Arial dari `C:\Windows\Fonts` (`arial.ttf`, `arialbd.ttf`, `ariali.ttf`,
  `arialbi.ttf`). Di mesin lain, ganti path atau pakai font TTF lain.
- Ukuran: A4, margin 20 mm, body 10,1 pt, leading 14,4 pt.
- Artikel admin diberi label `KHUSUS ADMIN`; artikel lain `KASIR DAN ADMIN`.
- Tautan "Panduan terkait" mengarah ke bookmark artikel lain dalam PDF yang sama.
- Satu artikel = mulai halaman baru (`PageBreak`), jadi tiap judul mudah ditemukan.
- Skema letak kontrol digambar vektor (`Locator`), sengaja tanpa data transaksi nyata,
  sesuai aturan data sintetis di `desain.md`.
- Bila ingin PDF juga memuat catatan kaki "Terakhir ditinjau", nilainya diambil dari
  `reviewed` di sumber JSON (`27 November 2026`).

## Berkas yang disentuh

| Berkas | Status |
|---|---|
| `docs/pusat-bantuan-kasir-bangunan.pdf` | belum ada (target) |
| `docs/catatan-proses-pdf-pusat-bantuan.md` | dibuat (catatan ini) |
| `C:\Users\bagas\AppData\Local\Temp\opencode\build-help-pdf.py` | dibuat, belum dijalankan |
| `C:\Users\bagas\AppData\Local\Temp\opencode\help-center-source.json` | dibuat |

Tidak ada berkas aplikasi, rute, migrasi, atau konfigurasi yang diubah.
