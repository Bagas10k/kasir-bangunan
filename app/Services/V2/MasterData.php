<?php

namespace App\Services\V2;

use App\Services\V2\Operations;
use Brick\Math\RoundingMode;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Phar;
use PharData;
use RuntimeException;

class MasterData
{
    private const KINDS = ['product', 'customer', 'supplier', 'employee', 'vehicle'];

    /** Batas panjang teks per kolom (kolom DB string 255, kode lebih pendek). */
    private const MAX_TEXT = [
        'sku' => 64,
        'code' => 64,
        'license_plate' => 32,
        'name' => 255,
        'unit' => 32,
        'category' => 50,
        'notes' => 255,
        'vehicle_type' => 32,
    ];

    private const MAX_ROWS = 1000;
    private const MAX_COLS = 128;
    private const MAX_CELLS_PER_ROW = 512;
    private const MAX_ZIP_ENTRIES = 100;
    private const MAX_UNCOMPRESSED = 10 * 1024 * 1024;

    private const FORBIDDEN_COLUMNS = ['stock', 'stock_quantity', 'stock_qty', 'balance', 'opening_balance', 'saldo', 'stok'];

    /** Menangkap <f>, <f/>, <f attr>, dan tag ber-namespace seperti <x:f>. */
    private const FORMULA_PATTERN = '/<(?:[A-Za-z0-9_.-]+:)?f[\s\/>]/i';

    public function kinds(): array
    {
        return self::KINDS;
    }

    public function columns(string $kind): array
    {
        return match ($kind) {
            'product' => ['sku', 'name', 'unit', 'category', 'cost_price', 'selling_price', 'min_stock'],
            'customer', 'supplier', 'employee' => ['code', 'name', 'notes'],
            'vehicle' => ['license_plate', 'vehicle_type'],
            default => throw new RuntimeException("Kind {$kind} tidak valid."),
        };
    }

    private function requireAdmin(int $actor): void
    {
        /** @var Operations $ops */
        $ops = app(Operations::class);
        $ops->requireRole($actor, ['admin']);
    }

    public function records(int $actor, string $kind): object
    {
        $this->requireAdmin($actor);
        if (!in_array($kind, self::KINDS, true)) {
            throw new RuntimeException("Kind {$kind} tidak valid.");
        }

        if ($kind === 'product') {
            return DB::table('products')->orderBy('id', 'desc')->limit(100)->get();
        }

        if ($kind === 'vehicle') {
            return DB::table('vehicles')->orderBy('id', 'desc')->limit(100)->get();
        }

        return DB::table('v2_parties')->where('kind', $kind)->orderBy('id', 'desc')->limit(100)->get();
    }

    public function store(int $actor, string $kind, array $data): int
    {
        $this->requireAdmin($actor);
        if (!in_array($kind, self::KINDS, true)) {
            throw new RuntimeException("Kind {$kind} tidak valid.");
        }

        return DB::transaction(function () use ($kind, $data) {
            $row = $this->validateRow($kind, $data);
            [$table, $keyColumn, $keyLabel] = $this->keySpec($kind);
            $keyValue = $row[$keyColumn];

            if (DB::table($table)->where($keyColumn, $keyValue)->lockForUpdate()->exists()) {
                throw new RuntimeException("{$keyLabel} {$keyValue} sudah terdaftar.");
            }

            $now = now();
            if ($kind === 'product') {
                $payload = $row + ['stock_quantity' => '0.0000', 'is_active' => true]; // zero product stock
            } elseif ($kind === 'vehicle') {
                $payload = $row + ['status' => 'active'];
            } else {
                $payload = $row + ['kind' => $kind, 'is_active' => true];
            }
            $payload['created_at'] = $now;
            $payload['updated_at'] = $now;

            return DB::table($table)->insertGetId($payload);
        });
    }

    /**
     * Ubah master tanpa menyentuh stok, kind, status arsip, atau created_at.
     * Record nonaktif tetap boleh diubah, tapi tidak pernah otomatis diaktifkan lagi.
     */
    public function update(int $actor, string $kind, int $id, array $data): void
    {
        $this->requireAdmin($actor);
        $this->assertKind($kind);

        DB::transaction(function () use ($kind, $id, $data): void {
            [$table, $keyColumn, $keyLabel] = $this->keySpec($kind);
            $current = $this->rowQuery($kind, $id)->lockForUpdate()->first();
            if (!$current) {
                throw new RuntimeException('Data tidak ditemukan.');
            }

            $row = $this->validateRow($kind, $data);

            // Field yang tidak dikirim dipertahankan, bukan di-reset ke 0/null.
            if ($kind === 'product') {
                foreach (['cost_price' => 4, 'selling_price' => 2, 'min_stock' => 4] as $field => $scale) {
                    if (!array_key_exists($field, $data)) {
                        $row[$field] = $this->decimal($current->{$field} ?? '0', $field, $scale);
                    }
                }
            } elseif ($kind !== 'vehicle' && !array_key_exists('notes', $data)) {
                $row['notes'] = $current->notes;
            }

            if (DB::table($table)->where($keyColumn, $row[$keyColumn])->where('id', '<>', $id)->exists()) {
                throw new RuntimeException("{$keyLabel} {$row[$keyColumn]} sudah terdaftar.");
            }

            // HPP produk yang sudah punya stok/riwayat hanya boleh berubah lewat
            // penerimaan atau saldo awal, supaya nilai persediaan tidak direvaluasi diam-diam.
            if ($kind === 'product'
                && $row['cost_price'] !== $this->decimal($current->cost_price ?? '0', 'cost_price', 4)
                && $this->costLockedIds([$id]) !== []) {
                throw new RuntimeException('HPP tidak dapat diubah: produk sudah punya stok atau riwayat pergerakan. Ubah HPP lewat penerimaan/saldo awal.');
            }

            $row['updated_at'] = now();
            DB::table($table)->where('id', $id)->update($row);
        });
    }

    /** Arsip = nonaktif. Tidak ada hard delete. */
    public function archive(int $actor, string $kind, int $id): void
    {
        $this->requireAdmin($actor);
        $this->assertKind($kind);

        DB::transaction(function () use ($kind, $id): void {
            [$table] = $this->keySpec($kind);
            if (!$this->rowQuery($kind, $id)->lockForUpdate()->exists()) {
                throw new RuntimeException('Data tidak ditemukan.');
            }

            $payload = $kind === 'vehicle'
                ? ['status' => 'inactive', 'updated_at' => now()]
                : ['is_active' => false, 'updated_at' => now()];
            DB::table($table)->where('id', $id)->update($payload);
        });
    }

    /** @return int[] id produk yang HPP-nya terkunci oleh stok atau riwayat pergerakan. */
    public function costLockedIds(array $productIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $productIds)));
        if ($ids === []) {
            return [];
        }

        $withStock = DB::table('products')->whereIn('id', $ids)->where('stock_quantity', '<>', '0')->pluck('id')->all();
        $withHistory = DB::table('stock_movements')->whereIn('product_id', $ids)->distinct()->pluck('product_id')->all();

        return array_values(array_unique(array_map('intval', array_merge($withStock, $withHistory))));
    }

    private function assertKind(string $kind): void
    {
        if (!in_array($kind, self::KINDS, true)) {
            throw new RuntimeException("Kind {$kind} tidak valid.");
        }
    }

    /** Baris tunggal terikat kind (parties), supaya kind tidak bisa ditukar lewat id. */
    private function rowQuery(string $kind, int $id)
    {
        [$table] = $this->keySpec($kind);
        $query = DB::table($table)->where('id', $id);

        return in_array($kind, ['product', 'vehicle'], true) ? $query : $query->where('kind', $kind);
    }

    /** Validasi + normalisasi satu baris master. Lempar RuntimeException berisi pesan pertama yang gagal. */
    private function validateRow(string $kind, array $data): array
    {
        if ($kind === 'product') {
            return [
                'sku' => $this->text($data['sku'] ?? '', 'sku', true),
                'name' => $this->text($data['name'] ?? '', 'name', true),
                'unit' => $this->text($data['unit'] ?? '', 'unit', true),
                'category' => $this->text($data['category'] ?? 'Umum', 'category') ?: 'Umum',
                'cost_price' => $this->decimal($data['cost_price'] ?? '0', 'cost_price', 4),
                'selling_price' => $this->decimal($data['selling_price'] ?? '0', 'selling_price', 2),
                'min_stock' => $this->decimal($data['min_stock'] ?? '0', 'min_stock', 4),
            ];
        }

        if ($kind === 'vehicle') {
            return [
                'license_plate' => $this->text($data['license_plate'] ?? '', 'license_plate', true),
                'vehicle_type' => $this->text($data['vehicle_type'] ?? 'truck', 'vehicle_type') ?: 'truck',
            ];
        }

        $notes = $this->text($data['notes'] ?? '', 'notes');

        return [
            'code' => $this->text($data['code'] ?? '', 'code', true),
            'name' => $this->text($data['name'] ?? '', 'name', true),
            'notes' => $notes === '' ? null : $notes,
        ];
    }

    private function text(mixed $value, string $field, bool $required = false): string
    {
        if (!is_scalar($value) && $value !== null) {
            throw new RuntimeException("Kolom '{$field}' tidak valid.");
        }
        $text = trim((string) $value);
        if ($text === '') {
            if ($required) {
                throw new RuntimeException("Kolom '{$field}' wajib diisi.");
            }
            return '';
        }
        $max = self::MAX_TEXT[$field] ?? 255;
        if (mb_strlen($text) > $max) {
            throw new RuntimeException("Kolom '{$field}' melebihi batas {$max} karakter.");
        }

        return $text;
    }

    /** Parse nilai uang/kuantitas non-negatif dengan skala tetap. */
    private function decimal(mixed $value, string $field, int $scale): string
    {
        $raw = is_scalar($value) ? trim((string) $value) : '';
        if ($raw === '') {
            $raw = '0';
        }
        try {
            $amount = Money::value($raw, $scale);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException("Kolom '{$field}' bukan angka valid: " . $e->getMessage());
        }
        if ($amount->isNegative()) {
            throw new RuntimeException("Kolom '{$field}' tidak boleh negatif.");
        }

        return (string) $amount->toScale($scale, RoundingMode::HalfUp);
    }

    /** @return array{0: string, 1: string, 2: string} [table, kolom kunci, label] */
    private function keySpec(string $kind): array
    {
        return match ($kind) {
            'product' => ['products', 'sku', 'SKU'],
            'vehicle' => ['vehicles', 'license_plate', 'Nomor polisi'],
            default => ['v2_parties', 'code', 'Kode'],
        };
    }

    private function existingKeys(string $kind, array $keys): array
    {
        $keys = array_values(array_unique(array_filter($keys, fn ($key) => $key !== '')));
        if ($keys === []) {
            return [];
        }
        [$table, $column] = $this->keySpec($kind);

        return DB::table($table)->whereIn($column, $keys)->pluck($column)->all();
    }

    public function template(int $actor, string $kind): string
    {
        $this->requireAdmin($actor);
        $cols = $this->columns($kind);
        return $this->buildMinimalXlsx($cols);
    }

    public function preview(int $actor, string $kind, string $filePath): array
    {
        $this->requireAdmin($actor);
        if (!in_array($kind, self::KINDS, true)) {
            throw new RuntimeException("Kind {$kind} tidak valid.");
        }

        if (!file_exists($filePath)) {
            throw new RuntimeException('Berkas tidak ditemukan.');
        }

        if (filesize($filePath) > 2 * 1024 * 1024) { // max 2MB compressed
            return $this->previewReject('Ukuran berkas melebihi batas 2MB.');
        }

        $parseResult = $this->parseXlsxFile($filePath, $this->columns($kind));
        if (!empty($parseResult['errors'])) {
            return $this->previewReject($parseResult['errors'][0]);
        }

        $parsedRows = $parseResult['rows'];
        $rowCount = count($parsedRows);
        if ($rowCount === 0) {
            return $this->previewReject('Berkas tidak memuat baris data.');
        }
        if ($rowCount > self::MAX_ROWS) {
            return $this->previewReject('Jumlah baris melebihi batas maksimum ' . self::MAX_ROWS . ' baris.');
        }

        $keyColumn = $this->keySpec($kind)[1];
        $errors = [];
        $uniqueKeys = [];
        $validatedRecords = [];

        foreach ($parsedRows as $idx => $row) {
            $rowNum = $idx + 2; // header is row 1
            try {
                $record = $this->validateRow($kind, $row);
            } catch (RuntimeException $e) {
                $errors[] = "Baris {$rowNum}: " . $e->getMessage();
                continue;
            }

            $key = (string) $record[$keyColumn];
            if (isset($uniqueKeys[$key])) {
                $errors[] = "Baris {$rowNum}: Duplikasi kode '{$key}' di dalam berkas.";
            } else {
                $uniqueKeys[$key] = true;
            }
            $validatedRecords[] = $record;
        }

        // Unik terhadap data yang sudah ada: batch tidak boleh dibuat bila ada bentrok.
        if (empty($errors)) {
            $existing = $this->existingKeys($kind, array_keys($uniqueKeys));
            if (!empty($existing)) {
                $errors[] = 'Data sudah terdaftar di database: ' . implode(', ', $existing) . '.';
            }
        }

        if (!empty($errors)) {
            return [
                'batch_id' => null,
                'count' => $rowCount,
                'rows' => array_slice($parsedRows, 0, 100),
                'errors' => $errors,
            ];
        }

        // Simpan preview batch ke database
        $batchId = DB::table('v2_import_batches')->insertGetId([
            'actor_id' => $actor,
            'kind' => $kind,
            'status' => 'preview',
            'row_count' => count($validatedRecords),
            'rows' => json_encode($validatedRecords, JSON_THROW_ON_ERROR),
            'committed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'batch_id' => $batchId,
            'count' => count($validatedRecords),
            'rows' => array_slice($validatedRecords, 0, 100),
            'errors' => [],
        ];
    }

    public function commit(int $actor, int $batchId): array
    {
        $this->requireAdmin($actor);

        return DB::transaction(function () use ($actor, $batchId) {
            $batch = DB::table('v2_import_batches')->where('id', $batchId)->lockForUpdate()->first();
            if (!$batch) {
                throw new RuntimeException('Batch import tidak ditemukan.');
            }
            if ((int) $batch->actor_id !== $actor) {
                abort(403);
            }

            // Idempotent check
            if ($batch->status === 'committed') {
                return [
                    'batch_id' => $batch->id,
                    'kind' => $batch->kind,
                    'status' => 'committed',
                    'inserted_count' => (int) $batch->row_count,
                    'already_committed' => true,
                ];
            }

            $kind = $batch->kind;
            $rows = json_decode($batch->rows, true, 512, JSON_THROW_ON_ERROR);

            // Revalidasi + insert per baris lewat satu jalur (store): aturan sama dengan form manual,
            // dan kegagalan satu baris membatalkan seluruh transaksi komit.
            foreach ($rows as $row) {
                $this->store($actor, $kind, $row);
            }

            DB::table('v2_import_batches')->where('id', $batchId)->update([
                'status' => 'committed',
                'committed_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'batch_id' => $batch->id,
                'kind' => $kind,
                'status' => 'committed',
                'inserted_count' => count($rows),
                'already_committed' => false,
            ];
        });
    }

    private function previewReject(string $error): array
    {
        return [
            'batch_id' => null,
            'count' => 0,
            'rows' => [],
            'errors' => [$error],
        ];
    }

    private function buildMinimalXlsx(array $headers): string
    {
        $tmpDir = storage_path('app/private/tmp_xlsx_' . bin2hex(random_bytes(8)));
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        $zipPath = $tmpDir . '/template.zip';

        try {
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

            // Shared strings for headers
            $sstCount = count($headers);
            $sstXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $sstCount . '" uniqueCount="' . $sstCount . '">';
            foreach ($headers as $h) {
                $cleanH = htmlspecialchars((string) $h, ENT_XML1, 'UTF-8');
                $sstXml .= "<si><t>{$cleanH}</t></si>";
            }
            $sstXml .= '</sst>';

            // Sheet xml
            $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
                '<sheetData><row r="1">';
            foreach ($headers as $colIdx => $h) {
                $colLetter = chr(65 + $colIdx);
                $sheetXml .= '<c r="' . $colLetter . '1" t="s"><v>' . $colIdx . '</v></c>';
            }
            $sheetXml .= '</row></sheetData></worksheet>';

            $phar = new PharData($zipPath, 0, null, Phar::ZIP);
            $phar->addFromString('[Content_Types].xml', $contentTypes);
            $phar->addFromString('_rels/.rels', $rels);
            $phar->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
            $phar->addFromString('xl/workbook.xml', $workbook);
            $phar->addFromString('xl/sharedStrings.xml', $sstXml);
            $phar->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
            unset($phar);

            $bytes = file_get_contents($zipPath);
            return $bytes;
        } finally {
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
            if (is_dir($tmpDir)) {
                @rmdir($tmpDir);
            }
        }
    }

    private function parseXlsxFile(string $filePath, array $expectedColumns): array
    {
        $tmpDir = storage_path('app/private/tmp_extract_' . bin2hex(random_bytes(8)));
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $copyZip = $tmpDir . '/source.zip';
        copy($filePath, $copyZip);

        try {
            $phar = new PharData($copyZip);

            $entries = 0;
            $totalUncompressed = 0;
            $xmlEntries = [];

            /** @var \PharFileInfo $file */
            foreach (new \RecursiveIteratorIterator($phar) as $file) {
                if (++$entries > self::MAX_ZIP_ENTRIES) {
                    unset($phar);
                    return $this->reject('Jumlah entri di dalam ZIP melebihi batas ' . self::MAX_ZIP_ENTRIES . '.');
                }
                if ($file->isDir()) {
                    continue;
                }

                $rel = $this->relativeEntryPath($file->getPathname(), $copyZip);
                $lowerRel = strtolower($rel);

                if ($file->isLink()) {
                    unset($phar);
                    return $this->reject('Berkas mengandung symlink yang dilarang.');
                }
                if (!$this->isSafeEntryPath($rel)) {
                    unset($phar);
                    return $this->reject('Berkas mengandung path entri tidak aman: ' . $rel);
                }
                // Reject macros and external references
                if (str_ends_with($lowerRel, '.bin') || str_contains($lowerRel, 'vba') || str_contains($lowerRel, 'externallink')) {
                    unset($phar);
                    return $this->reject('Berkas mengandung macro/bin/externalLinks yang dilarang.');
                }

                $totalUncompressed += $file->getSize();
                if ($totalUncompressed > self::MAX_UNCOMPRESSED) {
                    unset($phar);
                    return $this->reject('Ukuran uncompressed berkas melebihi batas 10MB.');
                }

                if (str_ends_with($lowerRel, '.xml') || str_ends_with($lowerRel, '.rels')) {
                    $xmlEntries[] = $rel;
                }
            }

            $phar->extractTo($tmpDir);
            unset($phar);

            // Semua XML diperiksa sebelum parsing: DOCTYPE/ENTITY, external links, formula (sheet apa pun).
            foreach ($xmlEntries as $rel) {
                $path = $tmpDir . '/' . $rel;
                if (!is_file($path)) {
                    return $this->reject('Entri XML tidak dapat dibaca: ' . $rel);
                }
                $content = (string) file_get_contents($path);

                if (preg_match('/<!DOCTYPE|<!ENTITY/i', $content)) {
                    return $this->reject('Dilarang menggunakan DOCTYPE atau ENTITY di dalam XML.');
                }
                if (preg_match('/TargetMode\s*=\s*(["\'])\s*External/i', $content)) {
                    return $this->reject('Berkas mengandung referensi eksternal (TargetMode External) yang dilarang.');
                }
                if (preg_match(self::FORMULA_PATTERN, $content)) {
                    return $this->reject('Formula terdeteksi pada lembar kerja. Formula dilarang.');
                }
            }

            // Read shared strings
            $sharedStrings = [];
            $sstPath = $tmpDir . '/xl/sharedStrings.xml';
            if (is_file($sstPath)) {
                $sstXml = simplexml_load_string((string) file_get_contents($sstPath), 'SimpleXMLElement', LIBXML_NONET);
                if ($sstXml !== false) {
                    foreach ($sstXml->si as $si) {
                        if (isset($si->t)) {
                            $sharedStrings[] = (string) $si->t;
                        } elseif (isset($si->r)) {
                            $txt = '';
                            foreach ($si->r as $r) {
                                $txt .= (string) $r->t;
                            }
                            $sharedStrings[] = $txt;
                        } else {
                            $sharedStrings[] = '';
                        }
                    }
                }
            }

            // Find sheet1
            $sheetPath = $tmpDir . '/xl/worksheets/sheet1.xml';
            if (!is_file($sheetPath)) {
                return $this->reject('Worksheet sheet1.xml tidak ditemukan di dalam XLSX.');
            }

            $sheetXml = simplexml_load_string((string) file_get_contents($sheetPath), 'SimpleXMLElement', LIBXML_NONET);
            if ($sheetXml === false) {
                return $this->reject('Format XML worksheet tidak valid.');
            }

            $rows = [];
            $headers = [];
            $rowIndex = 0;

            if (isset($sheetXml->sheetData->row)) {
                foreach ($sheetXml->sheetData->row as $r) {
                    $rowIndex++;
                    if ($rowIndex > self::MAX_ROWS + 1) {
                        return $this->reject('Jumlah baris melebihi batas maksimum ' . self::MAX_ROWS . ' baris.');
                    }

                    $cells = [];
                    $cellCount = 0;
                    foreach ($r->c as $c) {
                        if (++$cellCount > self::MAX_CELLS_PER_ROW) {
                            return $this->reject('Jumlah sel pada baris ' . $rowIndex . ' melebihi batas ' . self::MAX_CELLS_PER_ROW . '.');
                        }
                        $cellRef = strtoupper((string) $c['r']);
                        if (preg_match('/^([A-Z]{1,3})(\d{1,7})$/', $cellRef, $m) !== 1) {
                            return $this->reject('Referensi sel tidak valid: "' . $cellRef . '".');
                        }
                        $colIdx = $this->colLetterToIndex($m[1]);
                        if ($colIdx < 0 || $colIdx >= self::MAX_COLS) {
                            return $this->reject('Referensi kolom ' . $m[1] . ' di luar batas ' . self::MAX_COLS . ' kolom.');
                        }

                        $type = (string) $c['t'];
                        $val = '';
                        if ($type === 's') {
                            $sIndex = (int) (string) $c->v;
                            $val = $sharedStrings[$sIndex] ?? '';
                        } elseif ($type === 'inlineStr' && isset($c->is->t)) {
                            $val = (string) $c->is->t;
                        } elseif (isset($c->v)) {
                            $val = (string) $c->v;
                        }

                        $cells[$colIdx] = trim($val);
                    }

                    if ($rowIndex === 1) {
                        $headerError = $this->readHeaders($cells, $expectedColumns, $headers);
                        if ($headerError !== null) {
                            return $this->reject($headerError);
                        }
                        continue;
                    }

                    // Data row
                    $rowData = [];
                    $hasData = false;
                    foreach ($headers as $colIdx => $headerName) {
                        if ($headerName === '') {
                            continue;
                        }
                        $val = $cells[$colIdx] ?? '';
                        if ($val !== '') {
                            $hasData = true;
                        }
                        $rowData[$headerName] = $val;
                    }

                    if ($hasData) {
                        $rows[] = $rowData;
                    }
                }
            }

            if ($headers === []) {
                return $this->reject('Baris header XLSX tidak ditemukan.');
            }

            return ['rows' => $rows, 'errors' => []];
        } catch (\Throwable $e) {
            return $this->reject('Gagal memproses berkas XLSX: ' . $e->getMessage());
        } finally {
            $this->recursiveDelete($tmpDir);
        }
    }

    /**
     * Validasi header: wajib lengkap, tanpa duplikat, tanpa kolom asing, tanpa kolom saldo/stok.
     * Mengisi $headers dengan nama kolom per indeks (string kosong untuk sel kosong).
     */
    private function readHeaders(array $cells, array $expectedColumns, array &$headers): ?string
    {
        $headers = [];
        $maxIdx = $cells === [] ? -1 : max(array_keys($cells));
        if ($maxIdx + 1 > self::MAX_COLS) {
            return 'Header melebihi batas ' . self::MAX_COLS . ' kolom.';
        }

        $seen = [];
        for ($i = 0; $i <= $maxIdx; $i++) {
            $name = strtolower((string) ($cells[$i] ?? ''));
            if ($name === '') {
                $headers[$i] = '';
                continue;
            }
            if (in_array($name, self::FORBIDDEN_COLUMNS, true)) {
                return "Kolom saldo/stok '{$name}' dilarang dalam master import.";
            }
            if (isset($seen[$name])) {
                return "Kolom header ganda '{$name}' pada XLSX.";
            }
            if (!in_array($name, $expectedColumns, true)) {
                return "Kolom header '{$name}' tidak dikenal. Kolom wajib: " . implode(', ', $expectedColumns);
            }
            $seen[$name] = true;
            $headers[$i] = $name;
        }

        $missing = array_diff($expectedColumns, array_values($headers));
        if (!empty($missing)) {
            return 'Kolom header XLSX tidak sesuai. Kolom wajib: ' . implode(', ', $expectedColumns);
        }

        return null;
    }

    private function reject(string $message): array
    {
        return ['rows' => [], 'errors' => [$message]];
    }

    /** Path entri relatif di dalam arsip (tanpa prefix phar://…/source.zip/). */
    private function relativeEntryPath(string $pathName, string $copyZip): string
    {
        $prefix = 'phar://' . $copyZip . '/';
        if (str_starts_with($pathName, $prefix)) {
            return substr($pathName, strlen($prefix));
        }
        $pos = strpos($pathName, '.zip/');

        return $pos === false ? $pathName : substr($pathName, $pos + 5);
    }

    private function isSafeEntryPath(string $rel): bool
    {
        if ($rel === '' || str_contains($rel, "\0")) {
            return false;
        }
        if (str_starts_with($rel, '/') || preg_match('/^[A-Za-z]:/', $rel) === 1) {
            return false;
        }
        foreach (explode('/', $rel) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    private function colLetterToIndex(string $col): int
    {
        $col = strtoupper($col);
        $len = strlen($col);
        if ($len === 0 || $len > 3) {
            return -1;
        }
        $idx = 0;
        for ($i = 0; $i < $len; $i++) {
            $idx = $idx * 26 + (ord($col[$i]) - 64);
        }
        return $idx - 1;
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            if (is_dir($path) && !is_link($path)) {
                $this->recursiveDelete($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}