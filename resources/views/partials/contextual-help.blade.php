<style>
.main-topbar .top-context{display:flex;flex-direction:row;align-items:center;gap:var(--ui-space-tight);min-width:0}
.main-topbar .top-context>strong{display:block;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.main-topbar .contextual-help-trigger{flex:none;align-self:flex-start}
.main-topbar .contextual-help-trigger[hidden]{display:none!important}
</style>
<script>
(() => {
    const topics = {
        ringkasan: ['Ringkasan toko', 'Pantau penjualan, uang, pengiriman, dan stok dalam satu halaman.', 'Periode hanya menyaring penjualan. Saldo tetap menunjukkan kondisi saat ini.'],
        kasir: ['Kasir', 'Pilih barang, atur pembeli, tahan transaksi bila perlu, lalu periksa pembayaran sebelum menyimpan nota.', 'Kas harian harus aktif. Transfer dan kredit wajib pelanggan terdaftar; transfer perlu bukti dan verifikasi admin.'],
        'riwayat-penjualan': ['Riwayat penjualan', 'Cari nota dan buka Detail untuk melihat barang, pembayaran, sisa tagihan, atau mencetak ulang.', 'Daftar awal menampilkan hari ini. Gunakan Filter untuk mencari transaksi pada tanggal lain.'],
        tagihan: ['Tagihan', 'Pantau piutang pelanggan dan utang supplier. Buka Detail sebelum mencatat pembayaran.', 'Pengajuan yang menunggu persetujuan belum melunasi tagihan. Periksa status sebelum mencatat ulang.'],
        persetujuan: ['Persetujuan pembayaran', 'Periksa tagihan, nominal, sumber dana, dan bukti sebelum menyetujui atau menolak pembayaran.', 'Persetujuan memengaruhi saldo dan pelunasan. Foto bukti saja belum berarti pembayaran terverifikasi.'],
        pengiriman: ['Pengiriman', 'Atur sopir dan kendaraan, lalu pantau keberangkatan, penerimaan barang, dan biaya perjalanan.', 'Status barang diterima berbeda dari status lunas. Periksa pembayaran pada nota atau Tagihan.'],
        penerimaan: ['Penerimaan barang', 'Pilih barang dan supplier, isi jumlah serta HPP, lalu periksa dan simpan draf penerimaan.', 'Draf belum menambah stok atau utang. Konfirmasi dan kunci hanya setelah barang serta dokumen sesuai.'],
        'stok-opname': ['Stok opname', 'Cocokkan stok sistem dengan hasil hitung fisik, lalu catat selisih beserta alasannya.', 'Penyesuaian stok bukan pengganti pencatatan penjualan, penerimaan, atau retur.'],
        shift: ['Kas Harian Toko', 'Satu catatan laci bersama per tanggal yang dibuka dan ditutup oleh admin.', 'Transaksi penjualan di Kasir hanya dapat disimpan selama kas harian berstatus aktif.'],
        laporan: ['Laporan harian', 'Pilih tanggal untuk membaca rekap penjualan, pembayaran, dan kas sesuai hak akses.', 'Laporan kasir hanya mencakup petugas tersebut. Tanggal laporan tidak mengubah periode halaman lain.'],
        akun: ['Akun login', 'Kelola identitas masuk dan peran petugas yang boleh mengakses aplikasi.', 'Peran admin memiliki akses keuangan dan pengelolaan data. Data karyawan tidak otomatis menjadi akun login.'],
        'master-product': ['Produk', 'Atur identitas, satuan, kategori, supplier, serta harga eceran dan kontraktor barang.', 'Perubahan master tidak mencatat transaksi stok. Gunakan penerimaan, penjualan, atau stok opname.'],
        'master-supplier': ['Supplier', 'Kelola pemasok dan tautkan produk yang dipasok agar bisa dipilih saat penerimaan.', 'Membuat data supplier tidak menambah utang. Utang dicatat melalui transaksi terkait.'],
        'master-customer': ['Pelanggan', 'Simpan identitas pembeli dan pilihan harga eceran atau kontraktor untuk Kasir.', 'Kasir tetap bisa mengganti tipe harga. Periksa harga pada keranjang sebelum menyimpan penjualan.'],
        'master-employee': ['Karyawan & sopir', 'Siapkan petugas dan sopir yang digunakan dalam kegiatan operasional toko.', 'Mengarsipkan tidak menghapus riwayat. Data karyawan dan akun login dikelola terpisah.'],
        'master-vehicle': ['Mobil', 'Kelola identitas, jenis, dan status armada yang dipakai untuk pengiriman.', 'Kendaraan harus aktif agar bisa dipilih. Mengubah master tidak mengubah status pengiriman.'],
        'master-unit': ['Satuan', 'Siapkan satuan yang konsisten untuk jumlah barang pada produk dan transaksi.', 'Satuan tidak mengonversi kemasan secara otomatis. Gunakan jumlah sesuai satuan produk.'],
        'master-category': ['Kategori', 'Kelompokkan produk agar katalog dan pencarian barang lebih mudah digunakan.', 'Perubahan kategori tidak menambah stok atau mengubah harga barang.'],
        pengeluaran: ['Pengeluaran', 'Catat biaya operasional dengan akun beban, nominal, dan sumber Kas atau Bank yang sesuai.', 'Simpan langsung memposting jurnal dan mengurangi Kas/Bank terpilih, bukan laci kasir.'],
        jurnal: ['Jurnal umum', 'Telusuri debit dan kredit transaksi. Gunakan Pindah dana atau penyesuaian untuk koreksi yang sesuai.', 'Transaksi operasional sudah dibukukan otomatis. Jangan mencatat ulang atau memakai saldo awal untuk koreksi harian.'],
        neraca: ['Neraca', 'Lihat posisi aset, kewajiban, dan ekuitas pada tanggal laporan yang dipilih.', 'Neraca menunjukkan posisi saldo, bukan penjualan periode. Telusuri angka melalui jurnal dan transaksi asal.'],
        'laba-rugi': ['Laba rugi', 'Bandingkan pendapatan, HPP, dan beban untuk melihat hasil usaha selama periode tertentu.', 'Laba bukan saldo kas. Penjualan kredit dapat memengaruhi laba sebelum uangnya diterima.'],
        'arus-kas': ['Arus kas', 'Pantau aliran masuk dan keluar Kas serta Bank pada periode laporan.', 'Arus kas berbeda dari laba. Perpindahan antar Kas/Bank bukan pendapatan baru.'],
        'bagan-akun': ['Bagan akun', 'Kelola akun pembukuan dan tandai akun aset yang digunakan sebagai rekening Bank.', 'Akun inti dilindungi sistem. Menambah atau mengubah akun tidak memindahkan saldo.']
    };

    function init() {
        const trigger = document.getElementById('contextual-help-trigger');
        if (!trigger || !window.uiTooltip) return;
        const routeTopics = {
            'reports.profit_loss': 'laba-rugi', 'reports.balance_sheet': 'neraca',
            'reports.cash_flow': 'arus-kas', 'journals.index': 'jurnal',
            'coa.index': 'bagan-akun', 'expenses.index': 'pengeluaran'
        };
        const navigation = document.querySelector('nav[data-role]');
        function sync() {
            window.uiTooltip.hide();
            const key = document.body.dataset.activeTask || routeTopics[trigger.dataset.route];
            const topic = Object.hasOwn(topics, key) ? topics[key] : null;
            trigger.hidden = !topic;
            if (!topic) {
                delete trigger.dataset.tip;
                delete trigger.dataset.tipTitle;
                delete trigger.dataset.tipNote;
                delete trigger.dataset.tipArticle;
                return;
            }
            trigger.dataset.tipTitle = topic[0];
            trigger.dataset.tip = topic[1];
            trigger.dataset.tipNote = topic[2];
            if (trigger.dataset.helpBase) {
                const article = key === 'persetujuan' && navigation?.dataset.role === 'admin' ? 'verifikasi-pembayaran' : key;
                trigger.dataset.tipArticle = trigger.dataset.helpBase + '/' + article;
            }
            if (key === 'persetujuan' && navigation?.dataset.role === 'kasir') {
                trigger.dataset.tipTitle = 'Status pembayaran';
                trigger.dataset.tip = 'Pantau pembayaran yang Anda ajukan sendiri dan baca hasil verifikasi admin.';
                trigger.dataset.tipNote = 'Jika ditolak, periksa alasannya sebelum mengajukan perbaikan. Pembayaran menunggu belum melunasi tagihan.';
            }
            trigger.setAttribute('aria-label', 'Bantuan: ' + trigger.dataset.tipTitle);
        }
        new MutationObserver(sync).observe(document.body, { attributes: true, attributeFilter: ['data-active-task'] });
        sync();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
</script>
