<?php

namespace App\Support\V2;

use InvalidArgumentException;

/**
 * Acuan tunggal untuk semua isian form master V2.
 *
 * Setiap kolom, dropdown relasi, pilihan, dan ceklis dibangun dari templat di sini,
 * sehingga label, batas panjang, tipe kontrol, dan sumber relasi selalu sama di semua form.
 * Validasi tetap milik service (MasterData, MasterRelations); batas di sini dicocokkan oleh tes.
 */
final class FormCatalog
{
    /** Templat kolom: type menentukan kontrol yang dirender komponen <x-ui.field>. */
    public const FIELDS = [
        'sku' => ['type' => 'code', 'label' => 'SKU', 'max' => 64, 'required' => true, 'placeholder' => 'PRD-0001', 'hint' => 'Otomatis, boleh diganti.'],
        'code' => ['type' => 'code', 'label' => 'Kode', 'max' => 64, 'required' => true, 'hint' => 'Otomatis, boleh diganti.'],
        'name' => ['type' => 'text', 'label' => 'Nama', 'max' => 255, 'required' => true],
        'notes' => ['type' => 'textarea', 'label' => 'Catatan', 'max' => 255, 'placeholder' => 'Alamat, nomor telepon, atau keterangan lain'],
        'phone' => ['type' => 'tel', 'label' => 'Telepon', 'max' => 32, 'placeholder' => '0812 0000 0000'],
        'unit' => ['type' => 'ref', 'label' => 'Satuan', 'name' => 'unit_id', 'source' => 'unit', 'required' => true, 'hint' => 'Satuan jual, mis. sak atau batang.'],
        'category' => ['type' => 'ref', 'label' => 'Kategori', 'name' => 'category_id', 'source' => 'category', 'required' => true, 'hint' => 'Belum ada? Tambahkan di menu Satuan & kategori.'],
        'cost_price' => ['type' => 'money', 'label' => 'Harga beli (HPP)', 'scale' => 4, 'default' => '0', 'hint' => 'Modal per satuan.'],
        'selling_price' => ['type' => 'money', 'label' => 'Harga jual', 'scale' => 2, 'default' => '0', 'hint' => 'Harga ke pelanggan per satuan.'],
        'contractor_price' => ['type' => 'money', 'label' => 'Harga kontraktor', 'scale' => 2, 'nullable' => true, 'placeholder' => 'Sama dengan harga jual', 'hint' => 'Kosongkan bila sama dengan harga jual.'],
        'price_tier' => ['type' => 'segmented', 'label' => 'Harga di kasir', 'required' => true, 'default' => 'retail',
            'choices' => ['retail' => 'Harga eceran', 'contractor' => 'Harga kontraktor'], 'hint' => 'Pelanggan kontraktor otomatis dapat harga kontraktor di Kasir.'],
        'min_stock' => ['type' => 'qty', 'label' => 'Stok minimum', 'scale' => 4, 'default' => '0', 'unit_from' => 'unit_id', 'hint' => 'Ditandai menipis di bawah angka ini.'],
        'license_plate' => ['type' => 'plate', 'label' => 'Nomor polisi', 'max' => 32, 'required' => true, 'placeholder' => 'B 1234 XYZ', 'hint' => 'Huruf besar dan spasi diatur otomatis.'],
        'vehicle_type' => ['type' => 'suggest', 'label' => 'Jenis mobil', 'max' => 32, 'placeholder' => 'Ketik atau pilih di bawah', 'suggest' => ['Pick-up', 'Truk engkel', 'Truk double', 'Dump truck']],
        'driver_kind' => ['type' => 'segmented', 'label' => 'Jenis sopir', 'name' => 'kind', 'required' => true, 'default' => 'external',
            'choices' => ['external' => 'Sopir luar', 'internal' => 'Karyawan toko']],
        'employee' => ['type' => 'ref', 'label' => 'Karyawan', 'name' => 'employee_id', 'source' => 'employee', 'hint' => 'Nama sopir mengikuti data karyawan.'],
        'suppliers' => ['type' => 'multiref', 'label' => 'Supplier', 'name' => 'supplier_ids[]', 'source' => 'supplier',
            'empty' => 'Pilih supplier untuk ditambahkan', 'hint' => 'Bisa lebih dari satu. Supplier pertama menjadi supplier utama.'],
        'option_name' => ['type' => 'text', 'label' => 'Nama', 'max' => 50, 'required' => true],
    ];

    /** Keterangan singkat di bawah judul bagian form (bagian tanpa judul tidak berketerangan). */
    public const SECTION_NOTES = [
        'Identitas barang' => 'Nama dan pengelompokan barang di katalog.',
        'Harga dan stok' => 'Boleh dikosongkan dulu; harga bisa diubah kapan saja.',
        'Supplier barang' => 'Pemasok yang biasa mengirim barang ini.',
    ];

    /** Awalan kode otomatis per jenis data. */
    public const CODE_PREFIX = ['product' => 'PRD', 'customer' => 'PLG', 'supplier' => 'SUP', 'employee' => 'KRY', 'driver' => 'SPR'];

    /**
     * Susunan form per jenis: daftar bagian, tiap bagian berisi [kunci templat, lebar, override].
     * Lebar memakai grid 6 kolom: 2 = sepertiga, 3 = setengah, 6 = penuh.
     */
    public static function layout(string $kind): array
    {
        return match ($kind) {
            'product' => [
                ['Identitas barang', [['sku', 2], ['name', 4, ['placeholder' => 'Mis. Semen Tiga Roda 50 kg']], ['unit', 3], ['category', 3]]],
                ['Harga dan stok', [['cost_price', 3], ['selling_price', 3], ['contractor_price', 3], ['min_stock', 3]]],
                ['Supplier barang', [['suppliers', 6]], 'data-product-suppliers'],
            ],
            'customer' => [
                [null, [['code', 2], ['name', 4, ['placeholder' => 'Nama lengkap atau nama usaha']], ['price_tier', 6], ['notes', 6]]],
            ],
            'supplier', 'employee' => [
                [null, [['code', 2], ['name', 4, ['placeholder' => 'Nama lengkap atau nama usaha']], ['notes', 6]]],
            ],
            'vehicle' => [
                [null, [['license_plate', 3], ['vehicle_type', 3]]],
            ],
            'driver' => [
                [null, [['driver_kind', 6], ['employee', 6, ['wrap' => 'data-driver-employee']], ['code', 2], ['name', 4, ['label' => 'Nama sopir', 'placeholder' => 'Nama lengkap sopir']], ['phone', 2], ['notes', 4, ['placeholder' => 'Mis. biasa membawa truk engkel']]]],
            ],
            'unit' => [[null, [['option_name', 6, ['max' => 32, 'label' => 'Nama satuan', 'placeholder' => 'sak, batang, m³', 'hint' => 'Tulis singkat seperti yang dipakai di nota.']]]]],
            'category' => [[null, [['option_name', 6, ['label' => 'Nama kategori', 'placeholder' => 'Semen & perekat', 'hint' => 'Dipakai untuk menyaring katalog di Kasir.']]]]],
            default => throw new InvalidArgumentException("Form {$kind} tidak dikenal."),
        };
    }

    /** Templat kolom yang sudah digabung dengan override bagian. */
    public static function field(string $key, array $override = []): array
    {
        if (! isset(self::FIELDS[$key])) {
            throw new InvalidArgumentException("Kolom {$key} tidak ada di katalog.");
        }
        $field = $override + self::FIELDS[$key] + ['key' => $key];
        $field['name'] ??= $key === 'option_name' ? 'name' : $key;

        return $field;
    }

    /** Kode berikutnya, mis. PLG-0012, dari kode yang sudah ada; null bila jenis tanpa kode otomatis. */
    public static function nextCode(string $kind, iterable $existing): ?string
    {
        $prefix = self::CODE_PREFIX[$kind] ?? null;
        if ($prefix === null) {
            return null;
        }
        $max = 0;
        foreach ($existing as $code) {
            if (preg_match('/^'.$prefix.'-(\d+)$/', (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return sprintf('%s-%04d', $prefix, $max + 1);
    }
}
