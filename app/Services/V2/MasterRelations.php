<?php

namespace App\Services\V2;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MasterRelations
{
    public function records(int $actor, string $kind): object
    {
        app(Operations::class)->requireRole($actor, ['admin']);
        $this->assertKind($kind);

        if ($kind === 'driver') {
            return DB::table('v2_drivers as drivers')
                ->leftJoin('v2_parties as employees', 'employees.id', '=', 'drivers.employee_id')
                ->select('drivers.*', 'employees.is_active as employee_is_active')
                ->selectRaw("CASE WHEN drivers.kind = 'internal' THEN employees.name ELSE drivers.name END AS name")
                ->orderByDesc('drivers.id')->limit(100)->get();
        }

        // $kind sudah divalidasi assertKind: hanya 'unit' atau 'category', aman dipakai sebagai nama kolom.
        return DB::table('product_options')->where('type', $kind)
            ->select('product_options.*')
            ->selectRaw('(select count(*) from products where products.'.$kind.'_id = product_options.id) as used_count')
            ->orderByDesc('id')->limit(100)->get();
    }

    public function store(int $actor, string $kind, array $data): int
    {
        app(Operations::class)->requireRole($actor, ['admin']);
        $this->assertKind($kind);

        return DB::transaction(function () use ($kind, $data): int {
            $row = $this->attributes($kind, $data);
            $this->assertUnique($kind, $row);

            return DB::table($kind === 'driver' ? 'v2_drivers' : 'product_options')->insertGetId($row + [
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function update(int $actor, string $kind, int $id, array $data): void
    {
        app(Operations::class)->requireRole($actor, ['admin']);
        $this->assertKind($kind);

        DB::transaction(function () use ($kind, $id, $data): void {
            $current = $this->rowQuery($kind, $id)->lockForUpdate()->first();
            if (! $current) {
                throw new RuntimeException('Data tidak ditemukan.');
            }
            if ($kind === 'driver' && ($data['kind'] ?? $current->kind) === 'external') {
                $data['employee_id'] = null;
            }
            $row = $this->attributes($kind, $data + (array) $current);
            $this->assertUnique($kind, $row, $id);
            $this->rowQuery($kind, $id)->update($row + ['updated_at' => now()]);

            if ($kind !== 'driver') {
                DB::table('products')->where($kind.'_id', $id)->update([$kind => $row['name'], 'updated_at' => now()]);
            }
        });
    }

    public function archive(int $actor, string $kind, int $id): void
    {
        app(Operations::class)->requireRole($actor, ['admin']);
        $this->assertKind($kind);

        DB::transaction(function () use ($kind, $id): void {
            if (! $this->rowQuery($kind, $id)->lockForUpdate()->exists()) {
                throw new RuntimeException('Data tidak ditemukan.');
            }
            $this->rowQuery($kind, $id)->update(['is_active' => false, 'updated_at' => now()]);
        });
    }

    /** Resolve and lock the current driver identity inside the caller's transaction. */
    public function driver(int $id): object
    {
        $this->requireTransaction();
        $driver = DB::table('v2_drivers')->where('id', $id)->where('is_active', true)->lockForUpdate()->first();
        if (! $driver) {
            throw new RuntimeException('Sopir tidak aktif atau tidak ditemukan.');
        }
        if ($driver->kind === 'internal') {
            $driver->name = $this->employee((int) $driver->employee_id)->name;
        } elseif ($driver->kind !== 'external' || $driver->employee_id !== null) {
            throw new RuntimeException('Relasi sopir tidak valid.');
        }

        return $driver;
    }

    /** Resolve legacy text only during writes; preview must never create option masters. */
    public function productAttributes(array $data, array $row, ?object $current = null): array
    {
        $this->requireTransaction();
        foreach (['unit', 'category'] as $kind) {
            $field = $kind.'_id';
            if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                $id = $this->id($data[$field], $field);
                $option = DB::table('product_options')->where('id', $id)->where('type', $kind)->lockForUpdate()->first();
            } else {
                $name = $this->text($row[$kind], $kind, $kind === 'unit' ? 32 : 50);
                $option = DB::table('product_options')->where('type', $kind)->where('name', $name)->lockForUpdate()->first();
                if (! $option) {
                    DB::table('product_options')->insertOrIgnore([
                        'type' => $kind, 'name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $option = DB::table('product_options')->where('type', $kind)->where('name', $name)->lockForUpdate()->first();
                }
            }
            // Master yang sudah tertaut ke produk ini tetap sah walau diarsip; pilihan baru wajib aktif.
            $alreadyLinked = $option && $current !== null && (int) ($current->{$field} ?? 0) === (int) $option->id;
            if (! $option || (! $option->is_active && ! $alreadyLinked)) {
                throw new RuntimeException("Master {$kind} tidak aktif atau tidak ditemukan.");
            }
            $row[$field] = (int) $option->id;
            $row[$kind] = $this->text($option->name, $kind, $kind === 'unit' ? 32 : 50);
        }

        return $row;
    }

    /** Caller holds the product lock, serializing replacement and primary selection. */
    public function syncProductSuppliers(int $productId, array $data): void
    {
        if (! array_key_exists('supplier_ids', $data) && ! array_key_exists('primary_supplier_id', $data)) {
            return;
        }
        $this->requireTransaction();
        $existing = DB::table('v2_product_suppliers')->where('product_id', $productId)->lockForUpdate()->get();
        $replace = array_key_exists('supplier_ids', $data);
        $ids = $replace ? $data['supplier_ids'] : $existing->pluck('supplier_id')->all();
        if (! is_array($ids)) {
            throw new RuntimeException('Daftar supplier tidak valid.');
        }
        $ids = array_values(array_unique(array_map(fn (mixed $id): int => $this->id($id, 'supplier_ids'), $ids)));
        sort($ids);
        $oldPrimary = $existing->first(fn (object $link): bool => (bool) $link->is_primary);
        $primary = array_key_exists('primary_supplier_id', $data)
            ? $this->nullableId($data['primary_supplier_id'], 'primary_supplier_id')
            : ($oldPrimary && in_array((int) $oldPrimary->supplier_id, $ids, true) ? (int) $oldPrimary->supplier_id : null);
        if ($primary !== null && ! in_array($primary, $ids, true)) {
            throw new RuntimeException('Supplier utama harus termasuk daftar supplier produk.');
        }
        $validateIds = $replace ? $ids : ($primary === null ? [] : [$primary]);
        $suppliers = DB::table('v2_parties')->whereIn('id', $validateIds)->where('kind', 'supplier')
            ->orderBy('id')->lockForUpdate()->get(['id', 'is_active']);
        // Link yang sudah ada ke supplier terarsip dipertahankan; link baru wajib ke supplier aktif.
        $linkedIds = $existing->pluck('supplier_id')->map(fn ($id): int => (int) $id)->all();
        $usable = $suppliers->every(fn (object $supplier): bool => (bool) $supplier->is_active || in_array((int) $supplier->id, $linkedIds, true));
        if ($suppliers->pluck('id')->map(fn ($id): int => (int) $id)->all() !== $validateIds || ! $usable) {
            throw new RuntimeException('Supplier tidak aktif atau tidak ditemukan.');
        }

        if ($replace) {
            DB::table('v2_product_suppliers')->where('product_id', $productId)->delete();
            foreach ($ids as $supplierId) {
                DB::table('v2_product_suppliers')->insert([
                    'product_id' => $productId, 'supplier_id' => $supplierId, 'is_primary' => $supplierId === $primary,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        } else {
            DB::table('v2_product_suppliers')->where('product_id', $productId)->update(['is_primary' => false, 'updated_at' => now()]);
            if ($primary !== null) {
                DB::table('v2_product_suppliers')->where('product_id', $productId)->where('supplier_id', $primary)
                    ->update(['is_primary' => true, 'updated_at' => now()]);
            }
        }
    }

    private function attributes(string $kind, array $data): array
    {
        if ($kind !== 'driver') {
            return ['type' => $kind, 'name' => $this->text($data['name'] ?? '', 'name', $kind === 'unit' ? 32 : 50)];
        }
        $driverKind = $data['kind'] ?? null;
        if (! in_array($driverKind, ['internal', 'external'], true)) {
            throw new RuntimeException('Jenis sopir harus internal atau external.');
        }
        $employeeId = $this->nullableId($data['employee_id'] ?? null, 'employee_id');
        if ($driverKind === 'external' && $employeeId !== null) {
            throw new RuntimeException('Sopir eksternal tidak boleh terhubung ke pegawai.');
        }
        $name = $driverKind === 'internal'
            ? $this->employee($employeeId ?? 0)->name
            : $this->text($data['name'] ?? '', 'name', 255);

        return [
            'code' => $this->text($data['code'] ?? '', 'code', 64), 'kind' => $driverKind,
            'employee_id' => $employeeId, 'name' => $name,
            'phone' => $this->text($data['phone'] ?? '', 'phone', 32, false) ?: null,
            'notes' => $this->text($data['notes'] ?? '', 'notes', 255, false) ?: null,
        ];
    }

    private function employee(int $id): object
    {
        $employee = DB::table('v2_parties')->where('id', $id)->where('kind', 'employee')->where('is_active', true)->lockForUpdate()->first();
        if (! $employee) {
            throw new RuntimeException('Pegawai sopir tidak aktif atau tidak ditemukan.');
        }

        return $employee;
    }

    private function assertUnique(string $kind, array $row, ?int $except = null): void
    {
        $query = DB::table($kind === 'driver' ? 'v2_drivers' : 'product_options');
        if ($except !== null) {
            $query->where('id', '<>', $except);
        }
        if ($kind === 'driver') {
            if ((clone $query)->where('code', $row['code'])->exists()) {
                throw new RuntimeException('Kode sopir sudah terdaftar.');
            }
            if ($row['employee_id'] !== null && $query->where('employee_id', $row['employee_id'])->exists()) {
                throw new RuntimeException('Pegawai sudah terdaftar sebagai sopir.');
            }
        } elseif ($query->where('type', $kind)->where('name', $row['name'])->exists()) {
            throw new RuntimeException('Nama master sudah terdaftar.');
        }
    }

    private function rowQuery(string $kind, int $id): Builder
    {
        $query = DB::table($kind === 'driver' ? 'v2_drivers' : 'product_options')->where('id', $id);

        return $kind === 'driver' ? $query : $query->where('type', $kind);
    }

    private function assertKind(string $kind): void
    {
        if (! in_array($kind, ['driver', 'unit', 'category'], true)) {
            throw new RuntimeException("Kind {$kind} tidak valid.");
        }
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Resolusi relasi wajib di dalam transaksi.');
        }
    }

    private function nullableId(mixed $value, string $field): ?int
    {
        return $value === null || $value === '' ? null : $this->id($value, $field);
    }

    private function id(mixed $value, string $field): int
    {
        if ((! is_int($value) && ! is_string($value)) || preg_match('/^[1-9][0-9]*$/D', (string) $value) !== 1
            || filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new RuntimeException("Kolom '{$field}' bukan ID valid.");
        }

        return (int) $value;
    }

    private function text(mixed $value, string $field, int $max, bool $required = true): string
    {
        if (! is_scalar($value) && $value !== null) {
            throw new RuntimeException("Kolom '{$field}' tidak valid.");
        }
        $text = trim((string) $value);
        if ($required && $text === '') {
            throw new RuntimeException("Kolom '{$field}' wajib diisi.");
        }
        if (mb_strlen($text) > $max) {
            throw new RuntimeException("Kolom '{$field}' melebihi batas {$max} karakter.");
        }

        return $text;
    }
}
