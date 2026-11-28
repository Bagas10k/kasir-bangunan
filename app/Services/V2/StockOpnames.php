<?php

namespace App\Services\V2;

use App\Support\SafeLog;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stok opname operasional V2: mencocokkan stok fisik gudang dengan catatan sistem secara atomik,
 * mencatat mutasi stok (`v2_stock_opname`), dan memposting jurnal selisih nilai persediaan (5201 / 7101 atau 4201).
 */
final class StockOpnames
{
    private const INVENTORY = '1301';

    private const LOSS_ACCOUNT = '5201';

    private const FALLBACK_GAIN_ACCOUNT = '7101';

    private const LEGACY_GAIN_ACCOUNT = '4201';

    public function __construct(private readonly Operations $operations) {}

    /** @return array{total:int, results:list<array{id:int, sku:string, name:string, unit:string, stock:string, cost:string, minimum:string}>} */
    public function searchProducts(int $actor, string $query): array
    {
        $this->operations->requireRole($actor, ['admin']);
        $term = trim($query);
        $base = DB::table('products')
            ->where('is_active', true)
            ->when($term !== '', function ($q) use ($term): void {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(fn ($sub) => $sub
                    ->whereRaw('lower(name) like ?', [$like])
                    ->orWhereRaw('lower(sku) like ?', [$like]));
            });

        $total = (clone $base)->count();
        $rows = $base->orderBy('name')->limit(30)->get(['id', 'sku', 'name', 'unit', 'stock_quantity', 'cost_price', 'min_stock']);

        return [
            'total' => $total,
            'results' => $rows->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'sku' => (string) $row->sku,
                'name' => (string) $row->name,
                'unit' => (string) $row->unit,
                'stock' => (string) Money::value((string) $row->stock_quantity, 4),
                'cost' => (string) Money::value((string) $row->cost_price, 4),
                'minimum' => (string) Money::value((string) ($row->min_stock ?? '0'), 4),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function record(int $actor, array $data): array
    {
        $this->operations->requireRole($actor, ['admin']);

        $date = $this->date($data['opname_date'] ?? now()->toDateString());
        if ($date > now()->toDateString()) {
            $this->invalid('Tanggal opname tidak boleh melewati hari ini.');
        }

        $key = $this->key($data['idempotency_key'] ?? null);
        $notes = $this->text($data['notes'] ?? null, 'Catatan', 255);

        $rawItems = $data['items'] ?? null;
        if (! is_array($rawItems) || count($rawItems) < 1 || count($rawItems) > 100) {
            $this->invalid('Pilih 1 sampai 100 barang untuk dicatat stok opname.');
        }

        $items = [];
        foreach ($rawItems as $row) {
            if (! is_array($row)) {
                $this->invalid('Format item opname tidak valid.');
            }
            $productId = $this->id($row['product_id'] ?? null, 'Produk');
            if (isset($items[$productId])) {
                $this->invalid('Produk duplikat dalam satu opname; gabungkan baris yang sama.');
            }
            $physicalQty = $this->decimal($row['physical_qty'] ?? null, 'Jumlah fisik');
            if ($physicalQty->isNegative()) {
                $this->invalid('Jumlah fisik tidak boleh negatif.');
            }
            $items[$productId] = [
                'product_id' => $productId,
                'physical_qty' => $physicalQty,
                'expected_stock' => isset($row['expected_stock']) ? $this->decimal($row['expected_stock'], 'Stok sistem saat penghitungan') : null,
            ];
        }
        ksort($items);

        $hash = hash('sha256', json_encode([
            'date' => $date,
            'notes' => $notes,
            'items' => array_map(fn (array $row): array => [
                'product_id' => $row['product_id'],
                'physical_qty' => (string) $row['physical_qty'],
            ] + ($row['expected_stock'] === null ? [] : ['expected_stock' => (string) $row['expected_stock']]), array_values($items)),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $result = DB::transaction(function () use ($actor, $date, $key, $notes, $items, $hash): array {
            DB::table('users')->where('id', $actor)->lockForUpdate()->first();

            $existing = DB::table('v2_stock_opnames')->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                abort_unless((int) $existing->created_by === $actor, 403);
                if (! hash_equals((string) $existing->request_hash, $hash)) {
                    $this->invalid('Kunci transaksi stok opname sudah dipakai untuk isi berbeda.');
                }

                return $this->replay((int) $existing->id);
            }

            $products = DB::table('products')
                ->whereIn('id', array_keys($items))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== count($items)) {
                $this->invalid('Sebagian produk tidak ditemukan.');
            }

            foreach ($items as $productId => $row) {
                $product = $products[$productId];
                if (! (bool) $product->is_active) {
                    $this->invalid('Produk '.$product->name.' sudah diarsipkan.');
                }
                if (DB::table('stock_movements')->where('product_id', $productId)->whereDate('created_at', '>', $date)->exists()) {
                    $this->invalid('Tanggal opname tidak boleh mendahului mutasi stok terbaru untuk '.$product->name.'.');
                }
                if ($row['expected_stock'] === null || ! $row['expected_stock']->isEqualTo(Money::value((string) $product->stock_quantity, 4))) {
                    $this->invalid('Stok sistem untuk '.$product->name.' sudah berubah. Pilih ulang barang dan periksa kembali jumlah fisiknya.');
                }
            }

            $now = now();
            $opnameId = (int) DB::table('v2_stock_opnames')->insertGetId([
                'number' => 'TMP-'.bin2hex(random_bytes(8)),
                'opname_date' => $date,
                'status' => 'posted',
                'loss_total' => '0.00',
                'gain_total' => '0.00',
                'notes' => $notes,
                'created_by' => $actor,
                'journal_entry_id' => null,
                'idempotency_key' => $key,
                'request_hash' => $hash,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $number = 'OPN-'.str_replace('-', '', $date).'-'.str_pad((string) $opnameId, 5, '0', STR_PAD_LEFT);
            DB::table('v2_stock_opnames')->where('id', $opnameId)->update(['number' => $number, 'updated_at' => $now]);

            $lossTotal = BigDecimal::zero()->toScale(2);
            $gainTotal = BigDecimal::zero()->toScale(2);
            $processed = [];

            foreach ($items as $productId => $row) {
                $product = $products[$productId];
                $systemQty = Money::value((string) $product->stock_quantity, 4);
                /** @var BigDecimal $physicalQty */
                $physicalQty = $row['physical_qty'];
                $diffQty = $physicalQty->minus($systemQty)->toScale(4, RoundingMode::HalfUp);
                $unitCost = Money::value((string) $product->cost_price, 4);
                $valueDiff = $diffQty->multipliedBy($unitCost)->toScale(2, RoundingMode::HalfUp);

                DB::table('v2_stock_opname_items')->insert([
                    'stock_opname_id' => $opnameId,
                    'product_id' => $productId,
                    'system_qty' => (string) $systemQty,
                    'physical_qty' => (string) $physicalQty,
                    'difference_qty' => (string) $diffQty,
                    'cost_price' => (string) $unitCost,
                    'value_difference' => (string) $valueDiff,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if (! $diffQty->isZero()) {
                    DB::table('products')->where('id', $productId)->update([
                        'stock_quantity' => (string) $physicalQty,
                        'updated_at' => $now,
                    ]);
                    DB::table('stock_movements')->insert([
                        'product_id' => $productId,
                        'reference_type' => 'v2_stock_opname',
                        'reference_id' => $opnameId,
                        'quantity_delta' => (string) $diffQty,
                        'balance_after' => (string) $physicalQty,
                        'notes' => 'Stok opname '.$number,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if ($valueDiff->isNegative()) {
                    $lossTotal = $lossTotal->plus($valueDiff->abs());
                } elseif ($valueDiff->isPositive()) {
                    $gainTotal = $gainTotal->plus($valueDiff);
                }

                $processed[] = [
                    'product_id' => $productId,
                    'system_qty' => (string) $systemQty,
                    'physical_qty' => (string) $physicalQty,
                    'difference_qty' => (string) $diffQty,
                    'value_difference' => (string) $valueDiff,
                ];
            }

            $journalId = null;
            if ($lossTotal->isPositive() || $gainTotal->isPositive()) {
                $gainAccount = DB::table('chart_of_accounts')
                    ->where('account_code', self::LEGACY_GAIN_ACCOUNT)
                    ->where('is_active', true)
                    ->exists()
                    ? self::LEGACY_GAIN_ACCOUNT
                    : self::FALLBACK_GAIN_ACCOUNT;

                $lines = [];
                if ($lossTotal->isPositive()) {
                    $lines[] = ['account_code' => self::LOSS_ACCOUNT, 'debit' => (string) $lossTotal->toScale(2), 'credit' => '0.00'];
                }
                if ($gainTotal->isPositive()) {
                    $lines[] = ['account_code' => $gainAccount, 'debit' => '0.00', 'credit' => (string) $gainTotal->toScale(2)];
                }

                $netInventory = $gainTotal->minus($lossTotal)->toScale(2, RoundingMode::HalfUp);
                if ($netInventory->isPositive()) {
                    $lines[] = ['account_code' => self::INVENTORY, 'debit' => (string) $netInventory, 'credit' => '0.00'];
                } elseif ($netInventory->isNegative()) {
                    $lines[] = ['account_code' => self::INVENTORY, 'debit' => '0.00', 'credit' => (string) $netInventory->abs()];
                }

                // If both loss and gain happened and happened to net to exactly 0.00 on Inventory,
                // lines already contain DR 5201 (X) and CR 7101 (X) which balances cleanly!
                $journalId = Ledger::post('v2_stock_opname', $opnameId, 'Stok opname '.$number, $lines, $date);
            }

            DB::table('v2_stock_opnames')->where('id', $opnameId)->update([
                'loss_total' => (string) $lossTotal->toScale(2),
                'gain_total' => (string) $gainTotal->toScale(2),
                'journal_entry_id' => $journalId,
                'updated_at' => $now,
            ]);

            return [
                'opname_id' => $opnameId,
                'number' => $number,
                'opname_date' => $date,
                'loss_total' => (string) $lossTotal->toScale(2),
                'gain_total' => (string) $gainTotal->toScale(2),
                'journal_entry_id' => $journalId,
                'item_count' => count($processed),
                'items' => $processed,
            ];
        });

        SafeLog::info('stock_opname.posted', [
            'actor_id' => $actor,
            'opname_id' => $result['opname_id'],
            'number' => $result['number'],
            'loss_total' => $result['loss_total'],
            'gain_total' => $result['gain_total'],
        ]);

        return $result;
    }

    /** @return array<string, mixed> */
    private function replay(int $opnameId): array
    {
        $row = DB::table('v2_stock_opnames')->where('id', $opnameId)->first();
        $items = DB::table('v2_stock_opname_items')->where('stock_opname_id', $opnameId)->orderBy('id')->get();

        return [
            'opname_id' => (int) $row->id,
            'number' => (string) $row->number,
            'opname_date' => (string) $row->opname_date,
            'loss_total' => (string) Money::value((string) $row->loss_total),
            'gain_total' => (string) Money::value((string) $row->gain_total),
            'journal_entry_id' => $row->journal_entry_id === null ? null : (int) $row->journal_entry_id,
            'item_count' => $items->count(),
            'items' => $items->map(fn (object $item): array => [
                'product_id' => (int) $item->product_id,
                'system_qty' => (string) Money::value((string) $item->system_qty, 4),
                'physical_qty' => (string) Money::value((string) $item->physical_qty, 4),
                'difference_qty' => (string) Money::value((string) $item->difference_qty, 4),
                'value_difference' => (string) Money::value((string) $item->value_difference),
            ])->all(),
            'replayed' => true,
        ];
    }

    private function id(mixed $value, string $field): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[1-9]\d{0,17}$/', trim($value)) === 1) {
            return (int) trim($value);
        }
        $this->invalid($field.' tidak valid.');
    }

    private function decimal(mixed $value, string $field): BigDecimal
    {
        if (is_int($value)) {
            $raw = (string) $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $raw = trim($value);
        } else {
            $this->invalid($field.' wajib berupa angka.');
        }

        if (preg_match('/^-?\d{1,15}(\.\d{1,4})?$/', $raw) !== 1) {
            $this->invalid($field.' wajib angka desimal maksimal 4 angka pecahan.');
        }

        try {
            return Money::value($raw, 4)->toScale(4, RoundingMode::HalfUp);
        } catch (\Throwable) {
            $this->invalid($field.' tidak valid.');
        }
    }

    private function date(mixed $value): string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            $this->invalid('Tanggal opname harus berformat YYYY-MM-DD.');
        }
        [$year, $month, $day] = array_map('intval', explode('-', $value));
        if (! checkdate($month, $day, $year)) {
            $this->invalid('Tanggal opname tidak sah.');
        }

        return $value;
    }

    private function key(mixed $value): string
    {
        if (! is_string($value) || preg_match('/^[\x20-\x7E]{1,100}$/', trim($value)) !== 1) {
            $this->invalid('Kunci idempotensi wajib 1..100 karakter ASCII.');
        }

        return trim($value);
    }

    private function text(mixed $value, string $field, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            $this->invalid($field.' harus berupa teks.');
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        if (mb_strlen($trimmed) > $max) {
            $this->invalid($field.' maksimal '.$max.' karakter.');
        }

        return $trimmed;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['stock_opname' => $message]);
    }
}
