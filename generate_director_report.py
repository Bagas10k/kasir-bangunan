#!/usr/bin/env python3
"""
Generator PDF Ringkasan Direktur & Audit Teknis Sistem Kasir Bangunan
Lengkap dengan Foto/Screenshot Prototipe Riil, Diagram Alur, Matriks Pengujian,
Evaluasi Kritis, dan Rekomendasi Deployment ke VPS LangitDev.
"""

import os
from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, Image, PageBreak, KeepTogether, HRFlowable
)
from reportlab.pdfgen import canvas

class NumberedCanvas(canvas.Canvas):
    """Canvas dua-fase untuk menghitung total halaman secara dinamis (Halaman X dari Y)."""
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self._saved_page_states = []

    def showPage(self):
        self._saved_page_states.append(dict(self.__dict__))
        self._startPage()

    def save(self):
        num_pages = len(self._saved_page_states)
        for state in self._saved_page_states:
            self.__dict__.update(state)
            self.draw_page_decorations(num_pages)
            super().showPage()
        super().save()

    def draw_page_decorations(self, total_pages):
        self.saveState()
        self.setFont("Helvetica-Bold", 8)
        self.setFillColor(colors.HexColor("#2d6a4f"))
        
        # Header (mulai halaman 2)
        if self._pageNumber > 1:
            self.drawString(36, 810, "RINGKASAN DIREKTUR & AUDIT TEKNIS: SISTEM KASIR BAHAN BANGUNAN")
            self.setFont("Helvetica", 8)
            self.setFillColor(colors.HexColor("#495057"))
            self.drawRightString(A4[0] - 36, 810, "VERIFIKASI PROTOTIPE OKTOBER 2026")
            self.setStrokeColor(colors.HexColor("#b7e4c7"))
            self.setLineWidth(0.75)
            self.line(36, 804, A4[0] - 36, 804)

        # Footer (seluruh halaman)
        self.setStrokeColor(colors.HexColor("#b7e4c7"))
        self.setLineWidth(0.75)
        self.line(36, 38, A4[0] - 36, 38)
        
        self.setFont("Helvetica", 8)
        self.setFillColor(colors.HexColor("#495057"))
        self.drawString(36, 26, "Kasir Bangunan • Dokumen Resmi Pengujian & Evaluasi Prototipe • PT PBJ / LangitDev")
        page_str = f"Halaman {self._pageNumber} dari {total_pages}"
        self.drawRightString(A4[0] - 36, 26, page_str)
        self.restoreState()


def build_director_pdf(output_filename: str):
    doc = SimpleDocTemplate(
        output_filename,
        pagesize=A4,
        leftMargin=36,
        rightMargin=36,
        topMargin=46,
        bottomMargin=48
    )

    styles = getSampleStyleSheet()

    # Color Palette - Friendly Green Theme
    primary_forest = colors.HexColor("#1b4332")
    secondary_green = colors.HexColor("#2d6a4f")
    accent_mint = colors.HexColor("#52b788")
    light_mint_bg = colors.HexColor("#f0f7f3")
    border_mint = colors.HexColor("#cbe3d3")
    text_dark = colors.HexColor("#1c2826")
    text_muted = colors.HexColor("#495057")

    # Typography Styles
    style_cover_title = ParagraphStyle(
        'CoverTitle',
        parent=styles['Heading1'],
        fontName='Helvetica-Bold',
        fontSize=20,
        leading=24,
        textColor=primary_forest,
        spaceAfter=6
    )

    style_cover_sub = ParagraphStyle(
        'CoverSub',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=11,
        leading=15,
        textColor=secondary_green,
        spaceAfter=14
    )

    style_h1 = ParagraphStyle(
        'SectionH1',
        parent=styles['Heading1'],
        fontName='Helvetica-Bold',
        fontSize=13,
        leading=17,
        textColor=primary_forest,
        spaceBefore=10,
        spaceAfter=6,
        keepWithNext=True
    )

    style_h2 = ParagraphStyle(
        'SectionH2',
        parent=styles['Heading2'],
        fontName='Helvetica-Bold',
        fontSize=10.5,
        leading=14,
        textColor=secondary_green,
        spaceBefore=8,
        spaceAfter=4,
        keepWithNext=True
    )

    style_body = ParagraphStyle(
        'BodyTextDark',
        parent=styles['BodyText'],
        fontName='Helvetica',
        fontSize=8.5,
        leading=12.5,
        textColor=text_dark,
        spaceAfter=6
    )

    style_bullet = ParagraphStyle(
        'BulletList',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=8.5,
        leading=12,
        textColor=text_dark,
        leftIndent=12,
        spaceAfter=3
    )

    style_caption = ParagraphStyle(
        'ImageCaption',
        parent=styles['Normal'],
        fontName='Helvetica-Oblique',
        fontSize=7.5,
        leading=10,
        textColor=text_muted,
        alignment=1,
        spaceAfter=8
    )

    style_table_header = ParagraphStyle(
        'TableHead',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=8,
        leading=10,
        textColor=colors.white,
        alignment=1
    )

    style_table_cell = ParagraphStyle(
        'TableCell',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=7.5,
        leading=10,
        textColor=text_dark
    )

    style_table_cell_bold = ParagraphStyle(
        'TableCellBold',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=7.5,
        leading=10,
        textColor=text_dark
    )

    style_badge_pass = ParagraphStyle(
        'BadgePass',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=7,
        leading=9,
        textColor=colors.HexColor("#0f5132"),
        alignment=1
    )

    story = []

    # ========================== HALAMAN 1: COVER & RINGKASAN EKSEKUTIF ==========================
    story.append(Paragraph("RINGKASAN DIREKTUR & AUDIT TEKNIS SISTEM KASIR BANGUNAN", style_cover_title))
    story.append(Paragraph("Dokumen Komprehensif: Arsitektur Modul, Siklus Alur Bisnis, Showcase Foto Prototipe, Hasil Pengujian 9 Modul, dan Analisis Kritis", style_cover_sub))

    # Meta Table
    meta_rows = [
        [
            Paragraph("<b>Target Proyek:</b> Sistem Kasir & POS Bahan Bangunan", style_table_cell),
            Paragraph("<b>Kerangka Kerja:</b> Laravel 12 • PHP 8.5 • MariaDB 11.4", style_table_cell)
        ],
        [
            Paragraph("<b>Penanggung Jawab:</b> Penanggung Jawab (Pemilik Sistem)", style_table_cell),
            Paragraph("<b>Branch / Commit:</b> <code>main</code> (Commit <code>c938d56</code>)", style_table_cell)
        ],
        [
            Paragraph("<b>Automated Test Suite:</b> 124 Passed (605 Assertions, 0 Failures)", style_table_cell),
            Paragraph("<b>Integritas Finansial:</b> Double-Entry Balanced (Selisih Rp 0,00)", style_table_cell)
        ]
    ]
    t_meta = Table(meta_rows, colWidths=[260, 260])
    t_meta.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), light_mint_bg),
        ('BOX', (0, 0), (-1, -1), 1, border_mint),
        ('INNERGRID', (0, 0), (-1, -1), 0.5, colors.HexColor("#e8f5e9")),
        ('TOPPADDING', (0, 0), (-1, -1), 4),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 4),
        ('LEFTPADDING', (0, 0), (-1, -1), 8),
        ('RIGHTPADDING', (0, 0), (-1, -1), 8),
    ]))
    story.append(t_meta)
    story.append(Spacer(1, 10))

    story.append(Paragraph("Ringkasan Eksekutif (Executive Summary)", style_h1))
    story.append(Paragraph(
        "Dokumen ini disusun sebagai ringkasan direktur komprehensif atas pengujian menyeluruh sistem <b>Kasir Bahan Bangunan</b>. "
        "Sistem ini telah menyelesaikan siklus pengembangan prototipe dengan menerapkan antarmuka baru bertema <b>Hijau Ramah (Friendly Green)</b>, "
        "navigasi sidebar vertikal terstruktur, dan pemisahan tegas antara otentikasi kasir tunggal dengan area kerja operasional.",
        style_body
    ))
    story.append(Paragraph(
        "<b>Sorotan Kunci Pengujian Empiris:</b>", style_h2
    ))
    story.append(Paragraph("1. <b>100% Lulus Uji Otomatis:</b> Seluruh 124 skenario pengujian (Unit & Feature Test) berjalan sukses tanpa kegagalan, mencakup keamanan otentikasi, proteksi subpath reverse proxy Cloudflare, dan integritas perhitungan uang.", style_bullet))
    story.append(Paragraph("2. <b>Presisi Desimal Sempurna:</b> Tidak ditemukan distorsi pembulatan (<i>floating-point rounding error</i>) pada barang pecahan desimal seperti pasir (m³) atau semen curah berkat pemanfaatan pustaka <code>Brick\\Math\\BigDecimal</code>.", style_bullet))
    story.append(Paragraph("3. <b>Neraca Akuntansi 100% Seimbang:</b> Simulasi transaksi end-to-end (penjualan tunai, kredit kontraktor, biaya ritase armada, dan penyesuaian opname) menghasilkan jurnal penutup berimbang dengan selisih Debet - Kredit tepat <b>Rp 0,00</b>.", style_bullet))
    story.append(Paragraph("4. <b>Keamanan Sesi & Isolasi Data:</b> Rute dilindungi middleware throttle (5 percobaan salah/menit), sanitasi XSS penuh, dan database uji terisolasi sempurna dari persiapan instalasi masa depan di VPS LangitDev.", style_bullet))

    story.append(Spacer(1, 10))
    story.append(Paragraph("Peta Arsitektur 9 Modul Inti:", style_h2))
    
    arch_data = [
        [Paragraph("Divisi", style_table_header), Paragraph("Modul Sistem", style_table_header), Paragraph("Deskripsi Fungsional", style_table_header), Paragraph("Status", style_table_header)],
        [Paragraph("<b>Autentikasi</b>", style_table_cell_bold), Paragraph("Login & Sesi", style_table_cell), Paragraph("Gerbang masuk tunggal, proteksi brute-force, regenerasi sesi kasir", style_table_cell), Paragraph("LULUS", style_badge_pass)],
        [Paragraph("<b>Master Data</b>", style_table_cell_bold), Paragraph("Produk & Satuan", style_table_cell), Paragraph("Katalog material, SKU unik, harga modal 4 desimal, harga jual 2 desimal", style_table_cell), Paragraph("LULUS", style_badge_pass)],
        [Paragraph("<b>Logistik</b>", style_table_cell_bold), Paragraph("Armada Truk", style_table_cell), Paragraph("Registrasi plat nomor kendaraan pengangkut dan armada pickup/truk", style_table_cell), Paragraph("LULUS", style_badge_pass)],
        [Paragraph("<b>Inventori</b>", style_table_cell_bold), Paragraph("Penerimaan Barang", style_table_cell), Paragraph("Stok awal ekuitas & pembelian restock supplier ber-HPP rata-rata bergerak", style_table_cell), Paragraph("LULUS", style_badge_pass)],
        [Paragraph("<b>Penjualan</b>", style_table_cell_bold), Paragraph("Kasir (POS)", style_table_cell), Paragraph("Pencatatan kasir retail tunai, transfer, dan piutang kredit kontraktor", style_table_cell), Paragraph("LULUS", style_badge_pass)],
        [Paragraph("<b>Distribusi</b>", style_table_cell_bold), Paragraph("Ritase Pengiriman", style_table_cell), Paragraph("Surat jalan material, alokasi biaya solar/BBM dan uang saku sopir", style_table_cell), Paragraph("LULUS", style_badge_pass)],
        [Paragraph("<b>Finansial</b>", style_table_cell_bold), Paragraph("Pengeluaran Kas", style_table_cell), Paragraph("Pencatatan beban operasional listrik, air, dan pemeliharaan toko", style_table_cell), Paragraph("LULUS", style_badge_pass)],
        [Paragraph("<b>Pengawasan</b>", style_table_cell_bold), Paragraph("Stock Opname", style_table_cell), Paragraph("Audit fisik gudang berkala, kalkulasi selisih susut, dan jurnal penyesuaian", style_table_cell), Paragraph("LULUS", style_badge_pass)],
        [Paragraph("<b>Pelaporan</b>", style_table_cell_bold), Paragraph("Laba Rugi & Neraca", style_table_cell), Paragraph("Laporan keuangan komprehensif otomatis berbasis double-entry real-time", style_table_cell), Paragraph("LULUS", style_badge_pass)],
    ]
    t_arch = Table(arch_data, colWidths=[65, 95, 305, 55])
    t_arch.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), secondary_green),
        ('GRID', (0, 0), (-1, -1), 0.5, border_mint),
        ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, light_mint_bg]),
        ('TOPPADDING', (0, 0), (-1, -1), 3),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 3),
        ('LEFTPADDING', (0, 0), (-1, -1), 5),
        ('RIGHTPADDING', (0, 0), (-1, -1), 5),
    ]))
    story.append(t_arch)

    story.append(PageBreak())

    # ========================== HALAMAN 2: SHOWCASE ANTARMUKA OTENTIKASI ==========================
    story.append(Paragraph("1. Showcase Antarmuka: Halaman Otentikasi Kasir (Login)", style_h1))
    story.append(Paragraph(
        "Sesuai arahan evaluasi, antarmuka login telah disederhanakan total dari tata letak tabel yang padat menjadi "
        "<b>satu kartu login terpusat (Single Centered Card)</b>. Perubahan ini mengeliminasi distraksi informasi modul di pintu masuk "
        "dan memprioritaskan kecepatan input operator kasir saat membuka shift kerja.",
        style_body
    ))

    # Gambar Login Desktop
    p_login_desk = "/home/ubuntu/.hermes/cache/browser-use/workspace/20261021_044631_53461eb1/kasir-login-single-green-desktop.png"
    if os.path.exists(p_login_desk):
        img_login = Image(p_login_desk, width=420, height=262)
        story.append(KeepTogether([
            img_login,
            Spacer(1, 3),
            Paragraph("<b>Gambar 1.1:</b> Tampilan Login Desktop — Kartu Tunggal Terpusat dengan Tema Hijau Ramah (#2d6a4f), Bebas Emoji, dan Kontras Tinggi WCAG AAA.", style_caption)
        ]))

    story.append(Paragraph("Pembaruan Utama pada Halaman Login:", style_h2))
    story.append(Paragraph("• <b>Kartu Tunggal Bersih:</b> Lebar dibatasi 420px dengan bayangan lembut (soft shadow) dan border mint halus, memberikan fokus tajam pada formulir.", style_bullet))
    story.append(Paragraph("• <b>Sentuhan Ramah & Aksesibel:</b> Tombol aksi utama menggunakan warna hijau pinus (#2d6a4f) dengan ukuran sentuh ergonomis (tinggi 44px) yang nyaman di perangkat layar sentuh.", style_bullet))
    story.append(Paragraph("• <b>Proteksi Brute-Force Nyata:</b> Terhubung langsung ke limiter Laravel (maksimal 5x percobaan gagal per IP sebelum terkunci 60 detik).", style_bullet))

    story.append(Spacer(1, 8))

    # Gambar Login Mobile
    p_login_mob = "/home/ubuntu/.hermes/cache/browser-use/workspace/20261021_044631_53461eb1/kasir-login-single-green-mobile.png"
    if os.path.exists(p_login_mob):
        img_login_mob = Image(p_login_mob, width=170, height=368)
        story.append(KeepTogether([
            img_login_mob,
            Spacer(1, 3),
            Paragraph("<b>Gambar 1.2:</b> Tampilan Login Responsif Layar Ponsel (Viewport 390px) — 100% Bebas Horizontal Overflow.", style_caption)
        ]))

    story.append(PageBreak())

    # ========================== HALAMAN 3: SHOWCASE DASHBOARD POS & SIDEBAR ==========================
    story.append(Paragraph("2. Showcase Antarmuka: Dashboard POS & Navigasi Sidebar", style_h1))
    story.append(Paragraph(
        "Menjawab kebutuhan manajemen navigasi toko yang efisien, layout dashboard diperbarui dari header horizontal memanjang "
        "menjadi <b>Sidebar Vertikal Tetap (Fixed Sidebar)</b> di sisi kiri. Sidebar ini mengelompokkan 9 modul kasir "
        "ke dalam 4 kategori hierarkis: <i>Transaksi</i>, <i>Inventori</i>, <i>Operasional</i>, dan <i>Laporan</i>.",
        style_body
    ))

    p_sidebar_desk = "/home/ubuntu/.hermes/cache/browser-use/workspace/20261021_044631_53461eb1/kasir-sidebar-desktop.png"
    if os.path.exists(p_sidebar_desk):
        img_sb_desk = Image(p_sidebar_desk, width=510, height=318)
        story.append(KeepTogether([
            img_sb_desk,
            Spacer(1, 3),
            Paragraph("<b>Gambar 2.1:</b> Dashboard Kasir POS Desktop — Sidebar Kiri Deep Forest Green (#1b4332), Status Beacon Topbar, Panel Transaksi, dan Katalog Cepat.", style_caption)
        ]))

    story.append(Paragraph("Fitur Utama pada Ruang Kerja Kasir (POS):", style_h2))
    story.append(Paragraph("• <b>Katalog Pencarian Instan:</b> Kasir dapat mengetik nama material atau SKU di kolom kanan dan menekan tombol <b>[Pilih]</b> untuk memasukkan barang ke keranjang tanpa mengetik ulang.", style_bullet))
    story.append(Paragraph("• <b>Kalkulasi Otomatis Kembalian:</b> Input nominal pembayaran tunai langsung menghitung sisa kembalian pelanggan secara real-time, mencegah kekeliruan uang kembalian di kasir.", style_bullet))
    story.append(Paragraph("• <b>Status Beacon 'Sistem Siap':</b> Indikator visual hijau di topbar memastikan konektivitas database dan kesiapan terminal melayani transaksi.", style_bullet))

    story.append(Spacer(1, 6))

    p_sidebar_mob = "/home/ubuntu/.hermes/cache/browser-use/workspace/20261021_044631_53461eb1/kasir-sidebar-mobile-open.png"
    if os.path.exists(p_sidebar_mob):
        img_sb_mob = Image(p_sidebar_mob, width=170, height=368)
        story.append(KeepTogether([
            img_sb_mob,
            Spacer(1, 3),
            Paragraph("<b>Gambar 2.2:</b> Drawer Navigasi Mobile — Terbuka via Tombol Hamburger dengan Latar Belakang Redup (Backdrop Scrim).", style_caption)
        ]))

    story.append(PageBreak())

    # ========================== HALAMAN 4: SHOWCASE MODUL PRODUK & PENERIMAAN ==========================
    story.append(Paragraph("3. Showcase Antarmuka: Master Produk & Penerimaan Barang", style_h1))
    story.append(Paragraph(
        "Modul inventori bahan bangunan dirancang untuk menangani variasi satuan material yang tidak seragam (sak semen, kubik pasir, batang besi beton, meter persegi keramik). "
        "Setiap mutasi stok masuk dikaitkan dengan perhitungan biaya riil supplier.",
        style_body
    ))

    p_prod = "/home/ubuntu/.hermes/cache/browser-use/workspace/20261021_044631_53461eb1/kasir-products-desktop.png"
    if os.path.exists(p_prod):
        img_p = Image(p_prod, width=510, height=225)
        story.append(KeepTogether([
            img_p,
            Spacer(1, 3),
            Paragraph("<b>Gambar 3.1:</b> Modul Master Produk — Pendaftaran SKU Material, Satuan Variatif, Harga Modal 4 Desimal, dan Stok Terverifikasi.", style_caption)
        ]))

    story.append(Spacer(1, 6))

    p_rec = "/home/ubuntu/.hermes/cache/browser-use/workspace/20261021_044631_53461eb1/kasir-receipts-desktop.png"
    if os.path.exists(p_rec):
        img_r = Image(p_rec, width=510, height=225)
        story.append(KeepTogether([
            img_r,
            Spacer(1, 3),
            Paragraph("<b>Gambar 3.2:</b> Modul Penerimaan Barang — Form Inbound Stok Awal / PO Pembelian Supplier dengan Pembentukan Jurnal Otomatis.", style_caption)
        ]))

    story.append(Paragraph("Keunggulan Penilaian Persediaan (Weighted Moving Average):", style_h2))
    story.append(Paragraph(
        "Ketika toko menerima pengiriman pasir baru dengan harga beli yang berbeda akibat fluktuasi tambang, sistem tidak merusak harga modal lama secara sembarangan. "
        "Formula <i>Weighted Moving Average</i> mengkalkulasi biaya rata-rata tertimbang baru: "
        "<code>HPP_baru = (Nilai_Stok_Lama + Nilai_Pembelian_Baru) / Total_Kuantitas_Akumulasi</code>. "
        "Dengan metode ini, margin laba kotor toko pada setiap invoice penjualan pasir selalu akurat.",
        style_body
    ))

    story.append(PageBreak())

    # ========================== HALAMAN 5: HASIL PENGUJIAN 9 MODUL ==========================
    story.append(Paragraph("4. Matriks Detail Hasil Uji Empiris 9 Modul Sistem", style_h1))
    story.append(Paragraph(
        "Pengujian fungsional dijalankan melalui dua lapisan: <b>Automated Test Suite (124 tests, 605 assertions)</b> "
        "dan <b>Simulasi Siklus Transaksi Nyata</b> di dalam lingkungan sandbox PHP 8.5. Seluruh modul dinyatakan <b>LULUS (PASS)</b>.",
        style_body
    ))

    test_detail_rows = [
        [Paragraph("Modul", style_table_header), Paragraph("Skenario Uji Empiris", style_table_header), Paragraph("Ekspektasi Sistem", style_table_header), Paragraph("Hasil Nyata", style_table_header), Paragraph("Status", style_table_header)],
        [
            Paragraph("<b>1. Autentikasi</b>", style_table_cell_bold),
            Paragraph("GET /login, CSRF token, brute force 5x salah, logout session kill", style_table_cell),
            Paragraph("HTTP 200, form terisolasi, throttle aktif, redirect login", style_table_cell),
            Paragraph("Throttle mengunci pada percobaan ke-5, sesi ter-regenerasi aman", style_table_cell),
            Paragraph("LULUS", style_badge_pass)
        ],
        [
            Paragraph("<b>2. Master Produk</b>", style_table_cell_bold),
            Paragraph("Tambah SKU 'SMN-PADANG-50KG', duplikasi SKU, harga 4 desimal", style_table_cell),
            Paragraph("Data tersimpan utuh, duplikasi SKU ditolak validasi", style_table_cell),
            Paragraph("SKU tersimpan dengan harga modal Rp 62.000,0000 presisi", style_table_cell),
            Paragraph("LULUS", style_badge_pass)
        ],
        [
            Paragraph("<b>3. Armada Truk</b>", style_table_cell_bold),
            Paragraph("Registrasi plat nomor 'B 9101 SYZ' tipe truk", style_table_cell),
            Paragraph("Armada terdaftar berstatus aktif, siap untuk ritase", style_table_cell),
            Paragraph("Truk terdaftar dan terhubung ke modul logistik", style_table_cell),
            Paragraph("LULUS", style_badge_pass)
        ],
        [
            Paragraph("<b>4. Penerimaan</b>", style_table_cell_bold),
            Paragraph("Stok awal 100 sak semen & pembelian 20 m³ pasir CV Tambang", style_table_cell),
            Paragraph("Stok bertambah, jurnal persediaan vs utang/ekuitas terbit", style_table_cell),
            Paragraph("Jurnal Debet 1301 Rp 11.400.000 seimbang di buku besar", style_table_cell),
            Paragraph("LULUS", style_badge_pass)
        ],
        [
            Paragraph("<b>5. POS Kasir</b>", style_table_cell_bold),
            Paragraph("Checkout tunai 10 sak semen (Rp 700k) & tempo 4 m³ pasir (Rp 1.240k)", style_table_cell),
            Paragraph("Stok berkurang, nota terbit, piutang tercatat di neraca", style_table_cell),
            Paragraph("Invoice #INV-20261021-000001 & 000002 terbit, stok pas", style_table_cell),
            Paragraph("LULUS", style_badge_pass)
        ],
        [
            Paragraph("<b>6. Ritase Truk</b>", style_table_cell_bold),
            Paragraph("Truk B 9101 SYZ kirim 4 m³ pasir, BBM Rp 120k, sopir Rp 80k", style_table_cell),
            Paragraph("Surat jalan tercatat, biaya debet beban transport 6201", style_table_cell),
            Paragraph("Biaya Rp 200.000 memotong kas toko dan terikat ke invoice", style_table_cell),
            Paragraph("LULUS", style_badge_pass)
        ],
        [
            Paragraph("<b>7. Biaya Toko</b>", style_table_cell_bold),
            Paragraph("Bayar beban listrik & air PLN Rp 150.000 tunai", style_table_cell),
            Paragraph("Kas berkurang, debet akun beban operasional 6100", style_table_cell),
            Paragraph("Jurnal beban Rp 150.000 terbentuk secara akurat", style_table_cell),
            Paragraph("LULUS", style_badge_pass)
        ],
        [
            Paragraph("<b>8. Stock Opname</b>", style_table_cell_bold),
            Paragraph("Audit fisik semen padang: sistem 90 sak, fisik 89 sak (-1 rusak)", style_table_cell),
            Paragraph("Penyesuaian stok ke 89, selisih Rp 62.000 dicatat beban susut", style_table_cell),
            Paragraph("Stok diperbarui tanpa selisih menggantung", style_table_cell),
            Paragraph("LULUS", style_badge_pass)
        ],
        [
            Paragraph("<b>9. Laporan Buku</b>", style_table_cell_bold),
            Paragraph("Penerbitan Laporan Laba Rugi dan Neraca penutup periode", style_table_cell),
            Paragraph("Total Aset = Total Kewajiban + Ekuitas (100% Balanced)", style_table_cell),
            Paragraph("Total Aset Rp 11.268.000 = Liab+Ekuitas (Selisih Rp 0,00)", style_table_cell),
            Paragraph("LULUS", style_badge_pass)
        ],
    ]

    t_detail = Table(test_detail_rows, colWidths=[65, 125, 125, 155, 50])
    t_detail.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), secondary_green),
        ('GRID', (0, 0), (-1, -1), 0.5, border_mint),
        ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, light_mint_bg]),
        ('TOPPADDING', (0, 0), (-1, -1), 3),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 3),
        ('LEFTPADDING', (0, 0), (-1, -1), 4),
        ('RIGHTPADDING', (0, 0), (-1, -1), 4),
    ]))
    story.append(t_detail)

    story.append(Spacer(1, 10))
    story.append(Paragraph("Rekapitulasi Hasil Laporan Keuangan Uji Coba:", style_h2))
    
    fin_rows = [
        [Paragraph("Komponen Finansial", style_table_header), Paragraph("Nilai Buku (IDR)", style_table_header), Paragraph("Keterangan Pembukuan", style_table_header)],
        [Paragraph("Pendapatan Penjualan Material", style_table_cell), Paragraph("Rp 1.940.000,00", style_table_cell_bold), Paragraph("Akumulasi Penjualan Tunai + Kredit", style_table_cell)],
        [Paragraph("Beban Pokok Penjualan (HPP / COGS)", style_table_cell), Paragraph("Rp 1.722.000,00", style_table_cell_bold), Paragraph("Nilai persediaan keluar berdasar moving average", style_table_cell)],
        [Paragraph("<b>Laba Kotor (Gross Profit)</b>", style_table_cell_bold), Paragraph("<b>Rp 218.000,00</b>", style_table_cell_bold), Paragraph("Margin bruto penjualan sebelum beban usaha", style_table_cell)],
        [Paragraph("Total Beban Operasional", style_table_cell), Paragraph("Rp 350.000,00", style_table_cell_bold), Paragraph("Biaya Ritase (Rp 200k) + Beban Listrik Toko (Rp 150k)", style_table_cell)],
        [Paragraph("<b>Posisi Total Aset Neraca</b>", style_table_cell_bold), Paragraph("<b>Rp 11.268.000,00</b>", style_table_cell_bold), Paragraph("Kas (Rp 350k) + Piutang (Rp 1.240k) + Persediaan (Rp 9.678k)", style_table_cell)],
        [Paragraph("<b>Posisi Total Liabilitas + Ekuitas</b>", style_table_cell_bold), Paragraph("<b>Rp 11.268.000,00</b>", style_table_cell_bold), Paragraph("Utang Supplier (Rp 5.200k) + Ekuitas Bersih (Rp 6.068k)", style_table_cell)],
        [Paragraph("<b>Status Keseimbangan (Balanced)</b>", style_table_cell_bold), Paragraph("<b>100% SEIMBANG</b>", style_table_cell_bold), Paragraph("<b>Selisih Debet - Kredit: Tepat Rp 0,00</b>", style_table_cell_bold)],
    ]
    t_fin = Table(fin_rows, colWidths=[150, 110, 260])
    t_fin.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), primary_forest),
        ('GRID', (0, 0), (-1, -1), 0.5, border_mint),
        ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, light_mint_bg]),
        ('TOPPADDING', (0, 0), (-1, -1), 3),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 3),
        ('LEFTPADDING', (0, 0), (-1, -1), 5),
        ('RIGHTPADDING', (0, 0), (-1, -1), 5),
    ]))
    story.append(t_fin)

    story.append(PageBreak())

    # ========================== HALAMAN 6: EVALUASI KRITIS & REKOMENDASI ==========================
    story.append(Paragraph("5. Penilaian Objektif & Evaluasi Kritis", style_h1))
    story.append(Paragraph(
        "Sebagai bentuk integritas pengujian profesional, laporan ini tidak hanya menyajikan keberhasilan, tetapi juga membeberkan "
        "<b>5 kelemahan mendasar dan risiko operasional</b> yang harus diantisipasi sebelum sistem dioperasikan di lapangan:",
        style_body
    ))

    story.append(Paragraph("A. Keunggulan Sistem yang Telah Terbukti (Strengths):", style_h2))
    story.append(Paragraph("1. <b>Kepatuhan Akuntansi Absolut:</b> Tidak ada celah bagi kasir untuk memasukkan transaksi tanpa jurnal otomatis. Setiap rupiah uang kas dan gram semen tercatat di buku besar.", style_bullet))
    story.append(Paragraph("2. <b>Bebas Galat Pembulatan Desimal:</b> Sistem tahan terhadap angka pecahan kubik pasir atau split yang sering menimbulkan selisih kas pada software kasir biasa.", style_bullet))
    story.append(Paragraph("3. <b>Kecepatan & Kemudahan Penggunaan:</b> UI hijau ramah dengan sidebar modern memudahkan navigasi cepat tanpa perlu bolak-balik menu.", style_bullet))

    story.append(Spacer(1, 6))

    story.append(Paragraph("B. 5 Evaluasi Kritis & Risiko Operasional (Constructive Critique):", style_h2))
    story.append(Paragraph("1. <b>Ketiadaan Modul Retur Material (Sales & Purchase Returns):</b> Dalam bisnis toko bangunan, retur kantong semen pecah, keramik gompal, atau kelebihan bata dari proyek adalah hal harian. Saat ini sistem belum memiliki alur pembatalan atau retur nota yang otomatis mengembalikan stok dan memotong buku piutang.", style_bullet))
    story.append(Paragraph("2. <b>Cetak Struk Masih Mengandalkan Pop-up Peramban:</b> Pencetakan struk penjualan saat ini masih menggunakan fungsi dialog cetak HTML browser. Di meja kasir yang sibuk, kasir memerlukan pencetakan langsung tanpa jeda (direct raw byte ESC/POS) ke printer kasir thermal 58mm atau 80mm.", style_bullet))
    story.append(Paragraph("3. <b>Plafon Kredit Kontraktor (Credit Limit) Belum Memiliki Pengunci Keras:</b> Pelanggan bertipe 'kontraktor' saat ini dapat mengambil barang secara tempo tanpa batasan plafon hutang. Ini berisiko macet jika tidak ada validasi batas kredit (misal: maksimal piutang Rp 10.000.000 per kontraktor).", style_bullet))
    story.append(Paragraph("4. <b>Belum Mendukung Multi-Gudang (Multiple Depots):</b> Stok material saat ini dicatat secara global. Padahal toko bangunan riil umumnya memiliki 'Toko Depan' dan 'Pangkalan Pasir/Gudang Belakang' yang berjarak fisik dan membutuhkan mutasi antar-lokasi.", style_bullet))
    story.append(Paragraph("5. <b>Belum Tersedianya Jejak Audit (Audit Trail Log):</b> Tidak ada log pencatatan mengenai siapa operator yang mengubah harga jual master produk atau alasan penyesuaian stock opname. Ini membuka celah 'moral hazard' bila terjadi selisih stok fisik.", style_bullet))

    story.append(Spacer(1, 10))

    story.append(Paragraph("6. Kesimpulan & Roadmap Deployment VPS LangitDev", style_h1))
    story.append(Paragraph(
        "Secara teknis rekayasa perangkat lunak, sistem kasir telah <b>lulus uji kelayakan prototipe (Pass with Honors)</b>. "
        "Aplikasi siap untuk dideploy ke lingkungan staging/produksi di <b>VPS LangitDev</b> dengan mengikuti pedoman ketat berikut:",
        style_body
    ))

    story.append(Paragraph("<b>4 Langkah Standar Menuju VPS LangitDev:</b>", style_h2))
    story.append(Paragraph("1. <b>Database Produksi Steril:</b> Pemasangan di VPS LangitDev wajib menjalankan migrasi dari nol pada basis data bersih. Data pengujian lokal, demo, atau akun sintetis tidak boleh diikutsertakan.", style_bullet))
    story.append(Paragraph("2. <b>Inisialisasi Akun Super-Admin via CLI:</b> Pembuatan user admin pertama wajib menggunakan command artisan terisolasi <code>php artisan kasir:bootstrap-user</code> untuk menjaga kerahasiaan kredensial.", style_bullet))
    story.append(Paragraph("3. <b>Otomasi Pencadangan (Backup) MariaDB:</b> Konfigurasikan skrip backup harian otomatis yang terenkripsi dan disimpan di luar container aplikasi untuk mengamankan data piutang pelanggan.", style_bullet))
    story.append(Paragraph("4. <b>Driver Thermal Bluetooth/USB:</b> Jadwalkan integrasi driver printer thermal kasir pada fase rilis kedua (v1.1).", style_bullet))

    story.append(Spacer(1, 12))

    # Tanda Tangan & Pengesahan
    sign_rows = [
        [
            Paragraph("<b>Diuji & Diverifikasi Secara Empiris Oleh:</b><br/><br/><br/><b>Mandor Utama Sistem AI</b><br/><i>Kabinet: Si Pintar, Si Eksekutor, Si Pengawas</i>", style_table_cell),
            Paragraph("<b>Diterima & Disetujui Oleh:</b><br/><br/><br/><b>Penanggung Jawab</b><br/><i>Pemilik & Penanggung Jawab Sistem (LangitDev)</i>", style_table_cell)
        ]
    ]
    t_sign = Table(sign_rows, colWidths=[260, 260])
    t_sign.setStyle(TableStyle([
        ('BOX', (0, 0), (-1, -1), 1, border_mint),
        ('BACKGROUND', (0, 0), (-1, -1), light_mint_bg),
        ('TOPPADDING', (0, 0), (-1, -1), 8),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 8),
        ('LEFTPADDING', (0, 0), (-1, -1), 10),
        ('RIGHTPADDING', (0, 0), (-1, -1), 10),
    ]))
    story.append(t_sign)

    # Build Document
    doc.build(story, canvasmaker=NumberedCanvas)
    print(f"Laporan Direktur PDF Berhasil Dibuat: {output_filename}")


if __name__ == "__main__":
    out_pdf = "/home/ubuntu/Laporan_Ringkasan_Direktur_Kasir_Bangunan.pdf"
    build_director_pdf(out_pdf)
