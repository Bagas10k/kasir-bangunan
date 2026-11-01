<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\MasterData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Phar;
use PharData;
use Tests\TestCase;

class V2MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('V2 tests require isolated SQLite :memory:.');
        }
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert([
            'user_id' => $user->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $user;
    }

    private function createSampleXlsx(
        array $headers,
        array $rows,
        ?string $formula = null,
        bool $withMacro = false,
        ?string $extraSheetXml = null
    ): string
    {
        $tmpDir = storage_path('app/private/test_make_' . bin2hex(random_bytes(8)));
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        $zipPath = $tmpDir . '/sample.zip';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>' .
            '</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>';

        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>' .
            '</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>' .
            '</workbook>';

        $allStrings = [];
        foreach ($headers as $h) {
            $allStrings[] = (string) $h;
        }
        foreach ($rows as $r) {
            foreach ($r as $val) {
                if (!is_numeric($val)) {
                    $allStrings[] = (string) $val;
                }
            }
        }
        $uniqueStrings = array_values(array_unique($allStrings));
        $strMap = array_flip($uniqueStrings);

        $sstXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($uniqueStrings) . '" uniqueCount="' . count($uniqueStrings) . '">';
        foreach ($uniqueStrings as $str) {
            $sstXml .= '<si><t>' . htmlspecialchars($str, ENT_XML1, 'UTF-8') . '</t></si>';
        }
        $sstXml .= '</sst>';

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<sheetData>';

        // Header row
        $sheetXml .= '<row r="1">';
        foreach ($headers as $cIdx => $h) {
            $colLetter = chr(65 + $cIdx);
            $sIdx = $strMap[(string) $h];
            $sheetXml .= '<c r="' . $colLetter . '1" t="s"><v>' . $sIdx . '</v></c>';
        }
        $sheetXml .= '</row>';

        // Data rows
        foreach ($rows as $rIdx => $r) {
            $rowNum = $rIdx + 2;
            $sheetXml .= '<row r="' . $rowNum . '">';
            foreach ($r as $cIdx => $val) {
                $colLetter = chr(65 + $cIdx);
                if (is_numeric($val)) {
                    $sheetXml .= '<c r="' . $colLetter . $rowNum . '"><v>' . $val . '</v></c>';
                } else {
                    $sIdx = $strMap[(string) $val];
                    $sheetXml .= '<c r="' . $colLetter . $rowNum . '" t="s"><v>' . $sIdx . '</v></c>';
                }
            }
            if ($formula !== null && $rIdx === 0) {
                $sheetXml .= '<c r="Z' . $rowNum . '"><f>' . htmlspecialchars($formula, ENT_XML1, 'UTF-8') . '</f><v>0</v></c>';
            }
            if ($extraSheetXml !== null && $rIdx === 0) {
                $sheetXml .= $extraSheetXml;
            }
            $sheetXml .= '</row>';
        }
        $sheetXml .= '</sheetData></worksheet>';

        $phar = new PharData($zipPath, 0, null, Phar::ZIP);
        $phar->addFromString('[Content_Types].xml', $contentTypes);
        $phar->addFromString('_rels/.rels', $rels);
        $phar->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
        $phar->addFromString('xl/workbook.xml', $workbook);
        $phar->addFromString('xl/sharedStrings.xml', $sstXml);
        $phar->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        if ($withMacro) {
            $phar->addFromString('xl/vbaProject.bin', 'fake macro bin content');
        }
        unset($phar);

        $outPath = storage_path('app/private/test_out_' . bin2hex(random_bytes(8)) . '.xlsx');
        copy($zipPath, $outPath);
        unlink($zipPath);
        @rmdir($tmpDir);

        return $outPath;
    }

    public function test_non_admin_cannot_access_master_data(): void
    {
        $cashier = $this->actor('cashier');
        $service = app(MasterData::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->records($cashier->id, 'product');
    }

    public function test_admin_can_store_and_read_master_records_with_zero_product_stock(): void
    {
        $admin = $this->actor('admin');
        $service = app(MasterData::class);

        $prodId = $service->store($admin->id, 'product', [
            'sku' => 'SYN-P-01',
            'name' => 'Semen Padang 50kg',
            'unit' => 'sak',
            'category' => 'Semen',
            'cost_price' => '65000.00',
            'selling_price' => '72000.00',
            'min_stock' => '10.0000',
        ]);

        $prod = DB::table('products')->where('id', $prodId)->first();
        $this->assertSame('SYN-P-01', $prod->sku);
        $this->assertEquals(0, (float) $prod->stock_quantity); // strict zero product stock requirement

        $custId = $service->store($admin->id, 'customer', [
            'code' => 'SYN-C-01',
            'name' => 'Toko Bangunan Berkah',
            'notes' => 'Mitra prioritas',
        ]);
        $cust = DB::table('v2_parties')->where('id', $custId)->first();
        $this->assertSame('customer', $cust->kind);
        $this->assertSame('SYN-C-01', $cust->code);

        $vehId = $service->store($admin->id, 'vehicle', [
            'license_plate' => 'B 1234 SYN',
            'vehicle_type' => 'truck',
        ]);
        $veh = DB::table('vehicles')->where('id', $vehId)->first();
        $this->assertSame('B 1234 SYN', $veh->license_plate);

        $records = $service->records($admin->id, 'product');
        $this->assertCount(1, $records);
    }

    public function test_template_generation_and_preview_commit_lifecycle(): void
    {
        $admin = $this->actor('admin');
        $service = app(MasterData::class);

        // 1. Template generation
        $templateBytes = $service->template($admin->id, 'product');
        $this->assertNotEmpty($templateBytes);

        $tempPath = storage_path('app/private/template_test_' . bin2hex(random_bytes(8)) . '.xlsx');
        file_put_contents($tempPath, $templateBytes);

        // Empty template preview gives error (no data rows)
        $previewEmpty = $service->preview($admin->id, 'product', $tempPath);
        unlink($tempPath);
        $this->assertNull($previewEmpty['batch_id']);
        $this->assertContains('Berkas tidak memuat baris data.', $previewEmpty['errors']);

        // 2. Real XLSX with valid product rows
        $xlsxPath = $this->createSampleXlsx(
            ['sku', 'name', 'unit', 'category', 'cost_price', 'selling_price', 'min_stock'],
            [
                ['SYN-P-101', 'Paku Kayu 5cm', 'kg', 'Paku', '15000.00', '20000.00', '5.0000'],
                ['SYN-P-102', 'Paku Kayu 7cm', 'kg', 'Paku', '16000.00', '21000.00', '5.0000'],
            ]
        );

        $preview = $service->preview($admin->id, 'product', $xlsxPath);
        unlink($xlsxPath);

        $this->assertNotNull($preview['batch_id']);
        $this->assertSame(2, $preview['count']);
        $this->assertEmpty($preview['errors']);

        // 3. Commit batch
        $commitResult = $service->commit($admin->id, $preview['batch_id']);
        $this->assertSame('committed', $commitResult['status']);
        $this->assertSame(2, $commitResult['inserted_count']);
        $this->assertFalse($commitResult['already_committed']);

        // Check products inserted with stock_quantity = 0.0000
        $p1 = DB::table('products')->where('sku', 'SYN-P-101')->first();
        $this->assertNotNull($p1);
        $this->assertEquals(0, (float) $p1->stock_quantity);

        // 4. Idempotent commit returns same result without double insert
        $commitRepeat = $service->commit($admin->id, $preview['batch_id']);
        $this->assertSame('committed', $commitRepeat['status']);
        $this->assertSame(2, $commitRepeat['inserted_count']);
        $this->assertTrue($commitRepeat['already_committed']);

        $countInDb = DB::table('products')->whereIn('sku', ['SYN-P-101', 'SYN-P-102'])->count();
        $this->assertSame(2, $countInDb);
    }

    public function test_preview_rejects_formulas_macros_duplicates_and_stock_fields(): void
    {
        $admin = $this->actor('admin');
        $service = app(MasterData::class);

        // Reject formula
        $pathFormula = $this->createSampleXlsx(
            ['sku', 'name', 'unit', 'category', 'cost_price', 'selling_price', 'min_stock'],
            [['SYN-F-01', 'Barang Formula', 'unit', 'Umum', '1000', '2000', '0']],
            '=SUM(A1:B1)'
        );
        $resFormula = $service->preview($admin->id, 'product', $pathFormula);
        unlink($pathFormula);
        $this->assertNull($resFormula['batch_id']);
        $this->assertStringContainsString('Formula terdeteksi', $resFormula['errors'][0]);

        // Reject macros
        $pathMacro = $this->createSampleXlsx(
            ['sku', 'name', 'unit', 'category', 'cost_price', 'selling_price', 'min_stock'],
            [['SYN-M-01', 'Barang Makro', 'unit', 'Umum', '1000', '2000', '0']],
            null,
            true
        );
        $resMacro = $service->preview($admin->id, 'product', $pathMacro);
        unlink($pathMacro);
        $this->assertNull($resMacro['batch_id']);
        $this->assertStringContainsString('macro', $resMacro['errors'][0]);

        // Reject duplicate codes in file
        $pathDup = $this->createSampleXlsx(
            ['code', 'name', 'notes'],
            [
                ['SYN-DUP-01', 'Pelanggan 1', 'Notes 1'],
                ['SYN-DUP-01', 'Pelanggan 2', 'Notes 2'],
            ]
        );
        $resDup = $service->preview($admin->id, 'customer', $pathDup);
        unlink($pathDup);
        $this->assertNull($resDup['batch_id']);
        $this->assertStringContainsString('Duplikasi kode', $resDup['errors'][0]);
    }

    public function test_preview_rejects_negative_product_values_without_batch(): void
    {
        $admin = $this->actor('admin');
        $service = app(MasterData::class);

        $path = $this->createSampleXlsx(
            ['sku', 'name', 'unit', 'category', 'cost_price', 'selling_price', 'min_stock'],
            [
                ['SYN-NEG-01', 'Semen Harga Negatif', 'sak', 'Semen', '-65000.00', '72000.00', '10.0000'],
                ['SYN-NEG-02', 'Semen Stok Negatif', 'sak', 'Semen', '65000.00', '72000.00', '-1.0000'],
            ]
        );

        $result = $service->preview($admin->id, 'product', $path);
        unlink($path);

        $this->assertNull($result['batch_id']);
        $this->assertStringContainsString('tidak boleh negatif', implode(' ', $result['errors']));
        $this->assertSame(0, DB::table('v2_import_batches')->count());
    }

    public function test_preview_rejects_keys_already_in_database_without_creating_batch(): void
    {
        $admin = $this->actor('admin');
        $service = app(MasterData::class);

        $service->store($admin->id, 'product', [
            'sku' => 'SYN-EX-01',
            'name' => 'Semen Eksisting',
            'unit' => 'sak',
            'cost_price' => '65000',
            'selling_price' => '72000',
            'min_stock' => '10',
        ]);

        $path = $this->createSampleXlsx(
            ['sku', 'name', 'unit', 'category', 'cost_price', 'selling_price', 'min_stock'],
            [['SYN-EX-01', 'Semen Bentrok DB', 'sak', 'Semen', '65000.00', '72000.00', '10.0000']]
        );

        $result = $service->preview($admin->id, 'product', $path);
        unlink($path);

        $this->assertNull($result['batch_id']);
        $this->assertStringContainsString('sudah terdaftar di database', implode(' ', $result['errors']));
        $this->assertSame(0, DB::table('v2_import_batches')->count());
    }

    public function test_preview_rejects_self_closing_formula_and_oversized_column_reference(): void
    {
        $admin = $this->actor('admin');
        $service = app(MasterData::class);

        $headers = ['sku', 'name', 'unit', 'category', 'cost_price', 'selling_price', 'min_stock'];
        $rows = [['SYN-FRM-01', 'Barang Formula Pendek', 'unit', 'Umum', '1000', '2000', '0']];

        $pathSelfClosing = $this->createSampleXlsx($headers, $rows, null, false, '<c r="Z2"><f/></c>');
        $resSelfClosing = $service->preview($admin->id, 'product', $pathSelfClosing);
        unlink($pathSelfClosing);

        $this->assertNull($resSelfClosing['batch_id']);
        $this->assertStringContainsString('Formula terdeteksi', $resSelfClosing['errors'][0]);

        $pathWide = $this->createSampleXlsx($headers, $rows, null, false, '<c r="XFE2"><v>1</v></c>');
        $resWide = $service->preview($admin->id, 'product', $pathWide);
        unlink($pathWide);

        $this->assertNull($resWide['batch_id']);
        $this->assertStringContainsString('batas', $resWide['errors'][0]);
        $this->assertSame(0, DB::table('v2_import_batches')->count());
    }

    /* ------------------------------------------------------------------
     * Update & archive V2 (tanpa hard delete, stok/snapshot tidak berubah)
     * ------------------------------------------------------------------ */

    private static int $masterSeq = 0;

    private function masterProduct(array $overrides = []): int
    {
        $n = ++self::$masterSeq;

        return (int) DB::table('products')->insertGetId(array_merge([
            'sku' => 'SYN-V2M-' . $n,
            'name' => 'Material Sintetis ' . $n,
            'unit' => 'sak',
            'category' => 'Bahan Utama',
            'cost_price' => '10000.0000',
            'selling_price' => '12500.00',
            'stock_quantity' => '0.0000',
            'min_stock' => '2.0000',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function masterParty(string $kind, array $overrides = []): int
    {
        $n = ++self::$masterSeq;

        return (int) DB::table('v2_parties')->insertGetId(array_merge([
            'kind' => $kind,
            'code' => 'SYN-PTY-' . $n,
            'name' => 'Pihak Sintetis ' . $n,
            'notes' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function masterVehicle(): int
    {
        $n = ++self::$masterSeq;

        return (int) DB::table('vehicles')->insertGetId([
            'license_plate' => 'SYN ' . $n . ' AA',
            'vehicle_type' => 'truck',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function productPayload(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'SYN-V2M-EDIT',
            'name' => 'Material Sintetis Hasil Ubah',
            'unit' => 'kg',
            'category' => 'Agregat',
            'cost_price' => '10000.0000',
            'selling_price' => '13000.00',
            'min_stock' => '2.0000',
        ], $overrides);
    }

    public function test_non_admin_cannot_update_or_archive(): void
    {
        $cashier = $this->actor('cashier');
        $service = app(MasterData::class);
        $id = $this->masterProduct();
        $sku = (string) DB::table('products')->where('id', $id)->value('sku');

        foreach ([
            fn () => $service->update($cashier->id, 'product', $id, $this->productPayload()),
            fn () => $service->archive($cashier->id, 'product', $id),
        ] as $call) {
            try {
                $call();
                $this->fail('Peran cashier seharusnya ditolak.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }

        $this->assertSame(1, (int) DB::table('products')->where('id', $id)->value('is_active'));
        $this->assertSame($sku, DB::table('products')->where('id', $id)->value('sku'));
    }

    public function test_update_and_archive_reject_unknown_kind(): void
    {
        $admin = $this->actor('admin');
        $service = app(MasterData::class);

        foreach ([
            fn () => $service->update($admin->id, 'warehouse', 1, []),
            fn () => $service->archive($admin->id, 'warehouse', 1),
        ] as $call) {
            try {
                $call();
                $this->fail('Kind di luar allowlist seharusnya ditolak.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('tidak valid', $e->getMessage());
            }
        }
    }

    public function test_product_update_changes_catalog_fields_but_keeps_stock_and_created_at(): void
    {
        $admin = $this->actor('admin');
        $id = $this->masterProduct(['sku' => 'SYN-V2M-OLD', 'name' => 'Nama Lama', 'stock_quantity' => '4.5000']);
        $before = DB::table('products')->where('id', $id)->first();

        $this->travel(2)->minutes();
        app(MasterData::class)->update($admin->id, 'product', $id, $this->productPayload(['sku' => 'SYN-V2M-NEW']));

        $after = DB::table('products')->where('id', $id)->first();
        $this->assertSame('SYN-V2M-NEW', $after->sku);
        $this->assertSame('Material Sintetis Hasil Ubah', $after->name);
        $this->assertSame('kg', $after->unit);
        $this->assertSame('Agregat', $after->category);
        $this->assertEquals(13000.0, (float) $after->selling_price);
        $this->assertEquals((float) $before->stock_quantity, (float) $after->stock_quantity);
        $this->assertSame((string) $before->created_at, (string) $after->created_at);
        $this->assertTrue((string) $after->updated_at > (string) $before->updated_at);
    }

    public function test_product_rename_keeps_sales_snapshot_and_stock(): void
    {
        $admin = $this->actor('admin');
        $id = $this->masterProduct(['sku' => 'SYN-V2M-SNAP', 'name' => 'Nama Saat Jual', 'stock_quantity' => '3.0000']);

        $shiftId = (int) DB::table('v2_shifts')->insertGetId([
            'user_id' => $admin->id, 'open_user_id' => $admin->id, 'source' => 'admin',
            'opening_cash' => '0.00', 'status' => 'open', 'opened_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $saleId = (int) DB::table('v2_sales')->insertGetId([
            'number' => 'SYN-V2M-SALE', 'user_id' => $admin->id, 'shift_id' => $shiftId,
            'total' => '12500.00', 'cogs' => '10000.00', 'request_hash' => str_repeat('a', 64),
            'payment_method' => 'cash', 'status' => 'paid', 'fulfillment' => 'pickup',
            'idempotency_key' => 'syn-v2m-sale', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_sale_items')->insert([
            'sale_id' => $saleId, 'product_id' => $id, 'name' => 'Nama Saat Jual',
            'quantity' => '1.0000', 'unit_price' => '12500.00', 'cost_price' => '10000.0000',
            'discount' => '0.00', 'subtotal' => '12500.00', 'created_at' => now(), 'updated_at' => now(),
        ]);

        app(MasterData::class)->update($admin->id, 'product', $id, $this->productPayload([
            'sku' => 'SYN-V2M-SNAP', 'name' => 'Nama Baru',
        ]));

        $this->assertSame('Nama Saat Jual', DB::table('v2_sale_items')->where('sale_id', $saleId)->value('name'));
        $this->assertEquals(3.0, (float) DB::table('products')->where('id', $id)->value('stock_quantity'));
        $this->assertSame('Nama Baru', DB::table('products')->where('id', $id)->value('name'));
    }

    public function test_duplicate_key_rejected_except_own_key(): void
    {
        $admin = $this->actor('admin');
        $service = app(MasterData::class);
        $first = $this->masterProduct(['sku' => 'SYN-V2M-DUP-A']);
        $second = $this->masterProduct(['sku' => 'SYN-V2M-DUP-B']);

        try {
            $service->update($admin->id, 'product', $second, $this->productPayload(['sku' => 'SYN-V2M-DUP-A']));
            $this->fail('SKU duplikat seharusnya ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('sudah terdaftar', $e->getMessage());
        }
        $this->assertSame('SYN-V2M-DUP-B', DB::table('products')->where('id', $second)->value('sku'));

        $service->update($admin->id, 'product', $first, $this->productPayload(['sku' => 'SYN-V2M-DUP-A']));
        $this->assertSame('Material Sintetis Hasil Ubah', DB::table('products')->where('id', $first)->value('name'));

        $this->masterParty('customer', ['code' => 'SYN-V2M-PTY']);
        $other = $this->masterParty('customer');
        try {
            $service->update($admin->id, 'customer', $other, ['code' => 'SYN-V2M-PTY', 'name' => 'Bentrok']);
            $this->fail('Kode pihak duplikat seharusnya ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('sudah terdaftar', $e->getMessage());
        }
        $this->assertNotSame('Bentrok', DB::table('v2_parties')->where('id', $other)->value('name'));
    }

    public function test_cost_locked_when_stock_present(): void
    {
        $admin = $this->actor('admin');
        $id = $this->masterProduct(['stock_quantity' => '2.0000', 'cost_price' => '10000.0000']);

        try {
            app(MasterData::class)->update($admin->id, 'product', $id, $this->productPayload(['cost_price' => '11000.0000']));
            $this->fail('Ubah HPP produk berstok seharusnya ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('HPP tidak dapat diubah', $e->getMessage());
        }
        $this->assertEquals(10000.0, (float) DB::table('products')->where('id', $id)->value('cost_price'));
        $this->assertEquals(2.0, (float) DB::table('products')->where('id', $id)->value('stock_quantity'));
    }

    public function test_cost_locked_when_movement_history_exists_but_selling_price_updatable(): void
    {
        $admin = $this->actor('admin');
        $service = app(MasterData::class);
        $id = $this->masterProduct(['stock_quantity' => '0.0000', 'cost_price' => '10000.0000']);
        DB::table('stock_movements')->insert([
            'product_id' => $id, 'reference_type' => 'opening', 'reference_id' => null,
            'quantity_delta' => '5.0000', 'balance_after' => '5.0000', 'notes' => 'sintetis',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $service->update($admin->id, 'product', $id, $this->productPayload(['cost_price' => '9000.0000']));
            $this->fail('Ubah HPP produk bersejarah seharusnya ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('riwayat pergerakan', $e->getMessage());
        }
        $this->assertEquals(10000.0, (float) DB::table('products')->where('id', $id)->value('cost_price'));

        $service->update($admin->id, 'product', $id, $this->productPayload([
            'cost_price' => '10000.0000', 'selling_price' => '15500.00', 'name' => 'Harga Jual Naik',
        ]));

        $row = DB::table('products')->where('id', $id)->first();
        $this->assertEquals(10000.0, (float) $row->cost_price);
        $this->assertEquals(15500.0, (float) $row->selling_price);
        $this->assertSame('Harga Jual Naik', $row->name);
    }

    public function test_unused_product_cost_is_editable(): void
    {
        $admin = $this->actor('admin');
        $id = $this->masterProduct(['stock_quantity' => '0.0000', 'cost_price' => '10000.0000']);

        $this->assertSame([], app(MasterData::class)->costLockedIds([$id]));
        app(MasterData::class)->update($admin->id, 'product', $id, $this->productPayload(['cost_price' => '11111.5000']));

        $this->assertEquals(11111.5, (float) DB::table('products')->where('id', $id)->value('cost_price'));
        $this->assertEquals(0.0, (float) DB::table('products')->where('id', $id)->value('stock_quantity'));
    }

    public function test_archive_marks_inactive_without_deleting_rows(): void
    {
        $admin = $this->actor('admin');
        $service = app(MasterData::class);
        $product = $this->masterProduct();
        $customer = $this->masterParty('customer');
        $vehicle = $this->masterVehicle();

        $service->archive($admin->id, 'product', $product);
        $service->archive($admin->id, 'customer', $customer);
        $service->archive($admin->id, 'vehicle', $vehicle);

        $this->assertSame(0, (int) DB::table('products')->where('id', $product)->value('is_active'));
        $this->assertSame(0, (int) DB::table('v2_parties')->where('id', $customer)->value('is_active'));
        $this->assertSame('inactive', DB::table('vehicles')->where('id', $vehicle)->value('status'));
        $this->assertSame(1, DB::table('products')->where('id', $product)->count());
        $this->assertSame(1, DB::table('v2_parties')->where('id', $customer)->count());
    }

    public function test_archived_record_is_editable_but_never_auto_unarchived(): void
    {
        $admin = $this->actor('admin');
        $service = app(MasterData::class);
        $product = $this->masterProduct();
        $service->archive($admin->id, 'product', $product);

        $service->update($admin->id, 'product', $product, $this->productPayload([
            'sku' => (string) DB::table('products')->where('id', $product)->value('sku'),
            'is_active' => '1',
        ]));

        $row = DB::table('products')->where('id', $product)->first();
        $this->assertSame('Material Sintetis Hasil Ubah', $row->name);
        $this->assertSame(0, (int) $row->is_active);

        $customer = $this->masterParty('customer');
        $service->archive($admin->id, 'customer', $customer);
        $code = (string) DB::table('v2_parties')->where('id', $customer)->value('code');
        $service->update($admin->id, 'customer', $customer, ['code' => $code, 'name' => 'Nama Baru Nonaktif', 'is_active' => '1']);

        $party = DB::table('v2_parties')->where('id', $customer)->first();
        $this->assertSame('Nama Baru Nonaktif', $party->name);
        $this->assertSame(0, (int) $party->is_active);
    }

    public function test_party_kind_invariant_blocks_cross_kind_update_and_archive(): void
    {
        $admin = $this->actor('admin');
        $service = app(MasterData::class);
        $supplier = $this->masterParty('supplier', ['code' => 'SYN-V2M-SUP']);

        foreach ([
            fn () => $service->update($admin->id, 'customer', $supplier, ['code' => 'SYN-V2M-SUP', 'name' => 'Salah Kind']),
            fn () => $service->archive($admin->id, 'customer', $supplier),
        ] as $call) {
            try {
                $call();
                $this->fail('Akses lintas kind seharusnya ditolak.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('tidak ditemukan', $e->getMessage());
            }
        }

        $row = DB::table('v2_parties')->where('id', $supplier)->first();
        $this->assertSame('supplier', $row->kind);
        $this->assertSame(1, (int) $row->is_active);
        $this->assertNotSame('Salah Kind', $row->name);
    }

    public function test_party_notes_limit_matches_db_column_length(): void
    {
        $admin = $this->actor('admin');
        $service = app(MasterData::class);
        $id = $this->masterParty('customer');
        $code = (string) DB::table('v2_parties')->where('id', $id)->value('code');

        $service->update($admin->id, 'customer', $id, ['code' => $code, 'name' => 'Catatan 255', 'notes' => str_repeat('n', 255)]);
        $this->assertSame(255, strlen((string) DB::table('v2_parties')->where('id', $id)->value('notes')));

        try {
            $service->update($admin->id, 'customer', $id, ['code' => $code, 'name' => 'Catatan 256', 'notes' => str_repeat('n', 256)]);
            $this->fail('Catatan melebihi 255 karakter seharusnya ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('255 karakter', $e->getMessage());
        }
        $this->assertSame(255, strlen((string) DB::table('v2_parties')->where('id', $id)->value('notes')));
    }

    public function test_partial_renders_inline_forms_without_prompt(): void
    {
        // Partial memakai route() milik parent; lewati sampai route itu terdaftar.
        if (! \Illuminate\Support\Facades\Route::has('v2.master.update') || ! \Illuminate\Support\Facades\Route::has('v2.master.archive')) {
            $this->markTestSkipped('Route v2.master.update/v2.master.archive belum terdaftar oleh parent.');
        }

        $this->actor('admin');
        $used = $this->masterProduct(['stock_quantity' => '1.0000']);

        $html = view('v2.master-records', [
            'masterKind' => 'product',
            'masterRecords' => DB::table('products')->orderBy('id')->get(),
        ])->render();

        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString('Aktif', $html);
        $this->assertStringContainsString('data-method="PUT"', $html);
        $this->assertStringContainsString('data-method="PATCH"', $html);
        // Nama route milik parent; cukup pastikan aksi mengarah ke /master/{kind}/{id}.
        $this->assertMatchesRegularExpression('#action="[^"]*/master/product/' . $used . '"#', $html);
        $this->assertSame(2, preg_match_all('#action="[^"]*/master/product/' . $used . '"#', $html));
        $this->assertStringContainsString('readonly', $html);
        $this->assertStringContainsString('X-CSRF-TOKEN', $html);
        $this->assertStringNotContainsString('window.prompt', $html);
    }
}
