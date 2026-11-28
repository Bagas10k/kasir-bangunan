<?php

namespace App\Services\V2;

use Brick\Math\RoundingMode;
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

        $rows = DB::table('v2_parties')
            ->where('kind', 'customer')
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('name', 'like', '%'.$term.'%')->orWhere('code', 'like', $term.'%'))
            ->orderBy('name')
            ->limit(max(1, min($limit, 20)))
            ->get(['id', 'code', 'name', 'price_tier']);

        $receivables = $this->receivableSummaries($rows->pluck('id')->map(fn ($id): int => (int) $id)->all());

        return $rows
            ->map(function ($row) use ($receivables): array {
                $id = (int) $row->id;
                $summary = $receivables[$id] ?? $this->emptyReceivableSummary();

                return [
                    'id' => $id,
                    'code' => (string) $row->code,
                    'name' => (string) $row->name,
                    'price_tier' => (string) ($row->price_tier ?: 'retail'),
                    'open_invoices_count' => $summary['open_invoices_count'],
                    'open_balance' => $summary['open_balance'],
                    'overdue_count' => $summary['overdue_count'],
                    'overdue_balance' => $summary['overdue_balance'],
                ];
            })
            ->all();
    }

    /**
     * Mengembalikan pelanggan dengan nama ini; dibuat bila belum ada.
     * Nama yang sama (tanpa membedakan huruf besar/kecil) tidak pernah dibuat dua kali.
     *
     * @return array{id:int, code:string, name:string, price_tier:string, open_invoices_count:int, open_balance:string, overdue_count:int, overdue_balance:string, created:bool}
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
                $summary = $this->receivableSummaries([(int) $existing->id])[(int) $existing->id] ?? $this->emptyReceivableSummary();

                return [
                    'id' => (int) $existing->id,
                    'code' => (string) $existing->code,
                    'name' => (string) $existing->name,
                    'price_tier' => (string) ($existing->price_tier ?: 'retail'),
                    'open_invoices_count' => $summary['open_invoices_count'],
                    'open_balance' => $summary['open_balance'],
                    'overdue_count' => $summary['overdue_count'],
                    'overdue_balance' => $summary['overdue_balance'],
                    'created' => false,
                ];
            }

            $code = $this->nextCode();
            $id = DB::table('v2_parties')->insertGetId([
                'kind' => 'customer', 'code' => $code, 'name' => $name, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return array_merge(
                ['id' => (int) $id, 'code' => $code, 'name' => $name, 'price_tier' => 'retail'],
                $this->emptyReceivableSummary(),
                ['created' => true],
            );
        });
    }

    /**
     * Ringkasan piutang terbuka dan lewat tempo per pelanggan dengan satu query agregat (tanpa N+1).
     *
     * @param  list<int>  $customerIds
     * @return array<int, array{open_invoices_count:int, open_balance:string, overdue_count:int, overdue_balance:string}>
     */
    private function receivableSummaries(array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        $today = now()->toDateString();
        $buckets = [];
        $invoices = DB::table('v2_invoices')
            ->where('direction', 'receivable')
            ->whereIn('party_id', $customerIds)
            ->whereColumn('paid', '<', 'amount')
            ->get(['party_id', 'amount', 'paid', 'due_date']);

        foreach ($invoices as $invoice) {
            $partyId = (int) $invoice->party_id;
            $remaining = Money::value((string) $invoice->amount, 2)
                ->minus(Money::value((string) $invoice->paid, 2))
                ->toScale(2, RoundingMode::HalfUp);
            if (! $remaining->isPositive()) {
                continue;
            }
            if (! isset($buckets[$partyId])) {
                $buckets[$partyId] = [
                    'open_invoices_count' => 0,
                    'open_balance' => Money::value('0', 2),
                    'overdue_count' => 0,
                    'overdue_balance' => Money::value('0', 2),
                ];
            }
            $buckets[$partyId]['open_invoices_count']++;
            $buckets[$partyId]['open_balance'] = $buckets[$partyId]['open_balance']->plus($remaining);
            $dueDate = $invoice->due_date !== null && $invoice->due_date !== '' ? substr((string) $invoice->due_date, 0, 10) : null;
            if ($dueDate !== null && $dueDate < $today) {
                $buckets[$partyId]['overdue_count']++;
                $buckets[$partyId]['overdue_balance'] = $buckets[$partyId]['overdue_balance']->plus($remaining);
            }
        }

        $result = [];
        foreach ($buckets as $partyId => $bucket) {
            $result[$partyId] = [
                'open_invoices_count' => $bucket['open_invoices_count'],
                'open_balance' => (string) $bucket['open_balance']->toScale(2, RoundingMode::HalfUp),
                'overdue_count' => $bucket['overdue_count'],
                'overdue_balance' => (string) $bucket['overdue_balance']->toScale(2, RoundingMode::HalfUp),
            ];
        }

        return $result;
    }

    /** @return array{open_invoices_count:int, open_balance:string, overdue_count:int, overdue_balance:string} */
    private function emptyReceivableSummary(): array
    {
        return [
            'open_invoices_count' => 0,
            'open_balance' => '0.00',
            'overdue_count' => 0,
            'overdue_balance' => '0.00',
        ];
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
