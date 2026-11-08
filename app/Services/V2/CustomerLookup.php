<?php

namespace App\Services\V2;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pencarian pelanggan di kasir: mencari yang sudah ada, membuat yang belum ada.
 *
 * Kasir boleh memakai jalur ini (master data tetap milik admin), karena nama pembeli
 * diketik langsung saat melayani. Tidak menyentuh uang, stok, atau jurnal.
 */
final class CustomerLookup
{
    private const MIN_LENGTH = 2;

    /** Sama dengan batas nama di MasterData. */
    private const MAX_NAME = 255;

    public function __construct(private Operations $operations) {}

    /** Mencari pelanggan dengan nama serupa; dipakai untuk saran ketik di kasir. */
    public function search(string $term, int $limit = 8): array
    {
        $term = trim($term);
        if (mb_strlen($term) < self::MIN_LENGTH) {
            return [];
        }

        return DB::table('v2_parties')
            ->where('kind', 'customer')
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('name', 'like', '%'.$term.'%')->orWhere('code', 'like', $term.'%'))
            ->orderBy('name')
            ->limit(max(1, min($limit, 20)))
            ->get(['id', 'code', 'name', 'price_tier'])
            ->map(fn ($row) => ['id' => (int) $row->id, 'code' => (string) $row->code, 'name' => (string) $row->name, 'price_tier' => (string) ($row->price_tier ?: 'retail')])
            ->all();
    }

    /**
     * Mengembalikan pelanggan dengan nama ini; dibuat bila belum ada.
     * Nama yang sama (tanpa membedakan huruf besar/kecil) tidak pernah dibuat dua kali.
     *
     * @return array{id:int, code:string, name:string, created:bool}
     */
    public function resolve(int $actor, string $name): array
    {
        $this->operations->requireRole($actor, ['admin', 'cashier']);
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if (mb_strlen($name) < self::MIN_LENGTH) {
            throw new RuntimeException('Nama pelanggan minimal '.self::MIN_LENGTH.' huruf.');
        }
        if (mb_strlen($name) > self::MAX_NAME) {
            throw new RuntimeException('Nama pelanggan maksimal '.self::MAX_NAME.' huruf.');
        }

        return DB::transaction(function () use ($name) {
            $existing = DB::table('v2_parties')
                ->where('kind', 'customer')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->lockForUpdate()
                ->first(['id', 'code', 'name', 'is_active', 'price_tier']);

            if ($existing) {
                if (! $existing->is_active) {
                    throw new RuntimeException('Pelanggan "'.$existing->name.'" sedang diarsipkan. Minta admin mengaktifkannya.');
                }

                return ['id' => (int) $existing->id, 'code' => (string) $existing->code, 'name' => (string) $existing->name, 'price_tier' => (string) ($existing->price_tier ?: 'retail'), 'created' => false];
            }

            $code = $this->nextCode();
            $id = DB::table('v2_parties')->insertGetId([
                'kind' => 'customer', 'code' => $code, 'name' => $name, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['id' => (int) $id, 'code' => $code, 'name' => $name, 'price_tier' => 'retail', 'created' => true];
        });
    }

    /** Kode berurutan mengikuti pola master pelanggan (CUS-0001). */
    private function nextCode(): string
    {
        $last = DB::table('v2_parties')
            ->where('kind', 'customer')
            ->where('code', 'like', 'CUS-%')
            ->orderByDesc('code')
            ->value('code');

        $next = $last ? ((int) substr((string) $last, 4)) + 1 : 1;
        do {
            $code = 'CUS-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (DB::table('v2_parties')->where('code', $code)->exists());

        return $code;
    }
}
