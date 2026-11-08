<?php

namespace App\Services\V2;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Data contoh master (sintetis) untuk melihat tampilan dan relasi form.
 *
 * Hanya memakai jalur resmi MasterData/MasterRelations, tanpa stok, transaksi, atau jurnal.
 * Setiap id yang dibuat dicatat di manifest sehingga bisa dihapus lagi tanpa menyentuh data asli.
 */
final class DemoData
{
    public const UNITS = ['sak', 'batang', 'lembar', 'kg', 'pail', 'kaleng', 'buah', 'dus', 'm³'];

    public const CATEGORIES = ['Semen & perekat', 'Besi & baja', 'Kayu & triplek', 'Cat & pelapis', 'Pipa & sanitasi', 'Keramik & lantai', 'Atap & plafon', 'Pasir & batu'];

    public const SUPPLIERS = [
        ['SUP-0001', 'CV Sumber Semen Nusantara'], ['SUP-0002', 'PT Baja Prima Logam'], ['SUP-0003', 'UD Kayu Jati Makmur'],
        ['SUP-0004', 'CV Warna Cemerlang'], ['SUP-0005', 'PT Tirta Pipa Mandiri'], ['SUP-0006', 'UD Pasir Kali Jaya'],
    ];

    public const CUSTOMERS = [
        ['PLG-0001', 'Hendra Wijaya', 'Langganan, renovasi rumah'], ['PLG-0002', 'CV Karya Bangun Persada', 'Kontraktor, tempo 30 hari'],
        ['PLG-0003', 'Sri Lestari', null], ['PLG-0004', 'PT Graha Mandala Konstruksi', 'Proyek ruko'],
        ['PLG-0005', 'Bambang Susilo', null], ['PLG-0006', 'Toko Tani Subur', 'Ambil sendiri'],
        ['PLG-0007', 'Yayasan Pendidikan Harapan', 'Pembangunan kelas baru'], ['PLG-0008', 'Rudi Hartanto', null],
        ['PLG-0009', 'CV Mitra Sipil Utama', 'Kontraktor'], ['PLG-0010', 'Dewi Anggraini', null],
    ];

    public const EMPLOYEES = [
        ['KRY-0001', 'Agus Santoso', 'Kepala gudang'], ['KRY-0002', 'Budi Hartono', 'Sopir'], ['KRY-0003', 'Dedi Kurniawan', 'Sopir'],
        ['KRY-0004', 'Rina Marlina', 'Kasir'], ['KRY-0005', 'Eko Prasetyo', 'Pembantu gudang'], ['KRY-0006', 'Siti Aminah', 'Admin'],
    ];

    /** [kode, jenis, kode karyawan | nama sopir luar, telepon] */
    public const DRIVERS = [
        ['SPR-0001', 'internal', 'KRY-0002', '0812-0000-1102'], ['SPR-0002', 'internal', 'KRY-0003', '0812-0000-1103'],
        ['SPR-0003', 'external', 'Joko Susilo', '0813-0000-2201'], ['SPR-0004', 'external', 'Wawan Setiawan', '0813-0000-2202'],
    ];

    public const VEHICLES = [['B 9123 KJT', 'Truk engkel'], ['B 9547 PLM', 'Pick-up'], ['B 9871 TRS', 'Dump truck'], ['B 9310 NWA', 'Pick-up']];

    /** [sku, nama, satuan, kategori, hpp, harga jual, stok minimum, [kode supplier], kode supplier utama] */
    public const PRODUCTS = [
        ['PRD-0001', 'Semen Portland 50 kg', 'sak', 'Semen & perekat', '62000', '68500', '40', ['SUP-0001'], 'SUP-0001'],
        ['PRD-0002', 'Semen Putih 40 kg', 'sak', 'Semen & perekat', '88000', '96000', '10', ['SUP-0001'], 'SUP-0001'],
        ['PRD-0003', 'Mortar Perekat Bata 40 kg', 'sak', 'Semen & perekat', '72000', '79000', '15', ['SUP-0001'], null],
        ['PRD-0004', 'Lem Keramik 25 kg', 'sak', 'Semen & perekat', '58000', '64000', '10', ['SUP-0001'], null],
        ['PRD-0005', 'Besi Beton Polos 10 mm', 'batang', 'Besi & baja', '78000', '86000', '50', ['SUP-0002'], 'SUP-0002'],
        ['PRD-0006', 'Besi Beton Ulir 13 mm', 'batang', 'Besi & baja', '132000', '145000', '30', ['SUP-0002'], 'SUP-0002'],
        ['PRD-0007', 'Kawat Bendrat', 'kg', 'Besi & baja', '21000', '25000', '20', ['SUP-0002'], null],
        ['PRD-0008', 'Wiremesh M8', 'lembar', 'Besi & baja', '410000', '445000', '5', ['SUP-0002'], null],
        ['PRD-0009', 'Triplek 9 mm 122x244', 'lembar', 'Kayu & triplek', '118000', '131000', '20', ['SUP-0003'], 'SUP-0003'],
        ['PRD-0010', 'Kayu Kaso 5x7 4 m', 'batang', 'Kayu & triplek', '42000', '48000', '40', ['SUP-0003'], null],
        ['PRD-0011', 'Papan Meranti 2x20 4 m', 'batang', 'Kayu & triplek', '65000', '73000', '20', ['SUP-0003'], null],
        ['PRD-0012', 'Cat Tembok Interior Putih 25 kg', 'pail', 'Cat & pelapis', '545000', '598000', '4', ['SUP-0004'], 'SUP-0004'],
        ['PRD-0013', 'Cat Kayu dan Besi 1 kg', 'kaleng', 'Cat & pelapis', '68000', '76000', '12', ['SUP-0004'], null],
        ['PRD-0014', 'Pelapis Anti Bocor 4 kg', 'pail', 'Cat & pelapis', '158000', '176000', '6', ['SUP-0004'], null],
        ['PRD-0015', 'Pipa PVC 3/4 inci 4 m', 'batang', 'Pipa & sanitasi', '32000', '37500', '30', ['SUP-0005'], 'SUP-0005'],
        ['PRD-0016', 'Pipa PVC 4 inci 4 m', 'batang', 'Pipa & sanitasi', '118000', '131000', '10', ['SUP-0005'], 'SUP-0005'],
        ['PRD-0017', 'Kran Air Tembok 1/2 inci', 'buah', 'Pipa & sanitasi', '24000', '29000', '24', ['SUP-0005'], null],
        ['PRD-0018', 'Kloset Jongkok Putih', 'buah', 'Pipa & sanitasi', '185000', '210000', '4', ['SUP-0005'], null],
        ['PRD-0019', 'Keramik Lantai 40x40 Krem', 'dus', 'Keramik & lantai', '61000', '68000', '20', ['SUP-0001', 'SUP-0004'], null],
        ['PRD-0020', 'Granit 60x60 Abu', 'dus', 'Keramik & lantai', '168000', '186000', '10', ['SUP-0001'], null],
        ['PRD-0021', 'Genteng Metal Pasir', 'lembar', 'Atap & plafon', '34000', '39000', '50', ['SUP-0002'], null],
        ['PRD-0022', 'Gypsum Board 9 mm', 'lembar', 'Atap & plafon', '64000', '72000', '20', ['SUP-0003'], null],
        ['PRD-0023', 'Baja Ringan C75 6 m', 'batang', 'Atap & plafon', '98000', '108000', '30', ['SUP-0002'], 'SUP-0002'],
        ['PRD-0024', 'Pasir Beton', 'm³', 'Pasir & batu', '285000', '320000', '3', ['SUP-0006'], 'SUP-0006'],
        ['PRD-0025', 'Batu Split 1-2', 'm³', 'Pasir & batu', '310000', '345000', '3', ['SUP-0006'], 'SUP-0006'],
        ['PRD-0026', 'Bata Merah', 'buah', 'Pasir & batu', '750', '900', '2000', ['SUP-0006'], null],
    ];

    /** Urutan hapus mengikuti ketergantungan relasi. */
    private const REMOVE_ORDER = ['products' => 'products', 'drivers' => 'v2_drivers', 'vehicles' => 'vehicles', 'parties' => 'v2_parties', 'options' => 'product_options'];

    public function __construct(private MasterData $master, private MasterRelations $relations) {}

    /** @return array{products:int[],drivers:int[],vehicles:int[],parties:int[],options:int[]} id yang baru dibuat */
    public function seed(int $actor): array
    {
        $made = array_fill_keys(array_keys(self::REMOVE_ORDER), []);

        $options = ['unit' => [], 'category' => []];
        foreach (['unit' => self::UNITS, 'category' => self::CATEGORIES] as $type => $names) {
            foreach ($names as $name) {
                $id = DB::table('product_options')->where('type', $type)->where('name', $name)->value('id');
                if (! $id) {
                    $id = $this->relations->store($actor, $type, ['name' => $name]);
                    $made['options'][] = $id;
                }
                $options[$type][$name] = (int) $id;
            }
        }

        $party = function (string $kind, string $code, string $name, ?string $notes) use ($actor, &$made): int {
            $id = DB::table('v2_parties')->where('code', $code)->value('id');
            if (! $id) {
                $id = $this->master->store($actor, $kind, ['code' => $code, 'name' => $name, 'notes' => $notes ?? '']);
                $made['parties'][] = $id;
            }

            return (int) $id;
        };
        $suppliers = [];
        foreach (self::SUPPLIERS as [$code, $name]) {
            $suppliers[$code] = $party('supplier', $code, $name, null);
        }
        foreach (self::CUSTOMERS as [$code, $name, $notes]) {
            $party('customer', $code, $name, $notes);
        }
        $employees = [];
        foreach (self::EMPLOYEES as [$code, $name, $notes]) {
            $employees[$code] = $party('employee', $code, $name, $notes);
        }

        foreach (self::DRIVERS as [$code, $kind, $who, $phone]) {
            if (DB::table('v2_drivers')->where('code', $code)->exists()) {
                continue;
            }
            $data = ['code' => $code, 'kind' => $kind, 'phone' => $phone];
            $data += $kind === 'internal' ? ['employee_id' => $employees[$who]] : ['name' => $who];
            $made['drivers'][] = $this->relations->store($actor, 'driver', $data);
        }

        foreach (self::VEHICLES as [$plate, $type]) {
            if (! DB::table('vehicles')->where('license_plate', $plate)->exists()) {
                $made['vehicles'][] = $this->master->store($actor, 'vehicle', ['license_plate' => $plate, 'vehicle_type' => $type]);
            }
        }

        foreach (self::PRODUCTS as [$sku, $name, $unit, $category, $cost, $price, $min, $supplierCodes, $primary]) {
            if (DB::table('products')->where('sku', $sku)->exists()) {
                continue;
            }
            $made['products'][] = $this->master->store($actor, 'product', [
                'sku' => $sku, 'name' => $name, 'unit_id' => $options['unit'][$unit], 'category_id' => $options['category'][$category],
                'cost_price' => $cost, 'selling_price' => $price, 'min_stock' => $min,
                'supplier_ids' => array_map(fn (string $code): int => $suppliers[$code], $supplierCodes),
                'primary_supplier_id' => $primary ? $suppliers[$primary] : null,
            ]);
        }

        return $made;
    }

    /**
     * Hapus hanya id dari manifest. Baris yang sudah dipakai transaksi atau punya stok dilewati.
     *
     * @return array{removed:int,kept:list<string>}
     */
    public function remove(array $manifest): array
    {
        $removed = 0;
        $kept = [];
        foreach (self::REMOVE_ORDER as $group => $table) {
            foreach (array_map('intval', $manifest[$group] ?? []) as $id) {
                try {
                    DB::transaction(function () use ($table, $id): void {
                        if ($table === 'products') {
                            $stock = (string) DB::table('products')->where('id', $id)->value('stock_quantity');
                            if ($stock !== '' && ! BigDecimal::of($stock)->isZero()) {
                                throw new RuntimeException('masih punya stok');
                            }
                            DB::table('v2_product_suppliers')->where('product_id', $id)->delete();
                        }
                        DB::table($table)->where('id', $id)->delete();
                    });
                    $removed++;
                } catch (Throwable $e) {
                    $kept[] = "{$table}#{$id}";
                }
            }
        }

        return ['removed' => $removed, 'kept' => $kept];
    }
}
