<?php

namespace App\Services\V2;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReceiptDrafts
{
    public function __construct(private SupplyTrips $supplyTrips) {}

    /** @return array{draft_id:int,status:string,version:int,total:string} */
    public function save(int $actor, array $data, ?int $id = null): array
    {
        $this->requireAdmin($actor);

        return DB::transaction(function () use ($actor, $data, $id) {
            DB::table('users')->where('id', $actor)->lockForUpdate()->first();
            $snapshot = $this->snapshot($data);
            $hashSnapshot = $snapshot;
            if (($hashSnapshot['freight_amount'] ?? '0.00') === '0.00') {
                unset($hashSnapshot['freight_amount'], $hashSnapshot['freight_method'], $hashSnapshot['freight_account']);
            }
            $hash = hash('sha256', json_encode($hashSnapshot, JSON_THROW_ON_ERROR));
            if ($id === null) {
                $key = $data['idempotency_key'] ?? null;
                if (! is_string($key) || preg_match('/^[\x20-\x7E]{1,100}$/', $key) !== 1) {
                    $this->invalid('Kunci idempotensi wajib 1..100 karakter ASCII.');
                }
                $row = DB::table('v2_receipt_drafts')->where('created_by', $actor)->where('idempotency_key', $key)->lockForUpdate()->first();
                if ($row !== null) {
                    if (! hash_equals($row->request_hash, $hash)) {
                        $this->invalid('Kunci idempotensi sudah dipakai untuk muatan berbeda.');
                    }

                    return $this->result($row);
                }
                $id = DB::table('v2_receipt_drafts')->insertGetId($snapshot + ['created_by' => $actor, 'idempotency_key' => $key, 'request_hash' => $hash, 'status' => 'draft', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            } else {
                $row = $this->locked($id);
                $this->editable($row, $data);
                DB::table('v2_receipt_drafts')->where('id', $id)->update($snapshot + ['version' => $row->version + 1, 'updated_at' => now()]);
            }

            return $this->result(DB::table('v2_receipt_drafts')->where('id', $id)->first());
        });
    }

    /** @return array{draft_id:int,status:string,version:int,receipt_id:int,invoice_id:int,total:string} */
    public function confirm(int $actor, int $id, array $input): array
    {
        $this->requireAdmin($actor);

        return DB::transaction(function () use ($actor, $id, $input) {
            DB::table('users')->where('id', $actor)->lockForUpdate()->first();
            $row = $this->locked($id);
            if ($row->status === 'confirmed') {
                return $this->result($row);
            }
            $this->editable($row, $input);
            $posted = $this->supplyTrips->receive($actor, [
                'supplier_id' => (int) $row->supplier_id,
                'receipt_date' => $row->receipt_date,
                'notes' => $row->notes,
                'supplier_invoice_number' => $row->supplier_invoice_number,
                'items' => json_decode($row->items, true, 512, JSON_THROW_ON_ERROR),
                'idempotency_key' => 'draft-receipt-'.$id,
                'confirm_duplicate' => $input['confirm_duplicate'] ?? false,
                'freight_amount' => (string) ($row->freight_amount ?? '0'),
                'freight_method' => $row->freight_method ?? null,
                'freight_account' => $row->freight_account ?? null,
            ]);
            DB::table('v2_receipt_drafts')->where('id', $id)->update(['status' => 'confirmed', 'version' => $row->version + 1, 'receipt_id' => $posted['receipt_id'], 'confirmed_by' => $actor, 'confirmed_at' => now(), 'updated_at' => now()]);

            return $this->result($this->locked($id));
        });
    }

    /** @return array{draft_id:int,status:string,version:int,total:string} */
    public function cancel(int $actor, int $id, array $input): array
    {
        $this->requireAdmin($actor);

        return DB::transaction(function () use ($actor, $id, $input) {
            DB::table('users')->where('id', $actor)->lockForUpdate()->first();
            $row = $this->locked($id);
            if ($row->status === 'cancelled') {
                return $this->result($row);
            }
            $this->editable($row, $input);
            $reason = $input['reason'] ?? null;
            if (! is_string($reason) || mb_strlen(trim($reason)) < 5 || mb_strlen(trim($reason)) > 255) {
                $this->invalid('Alasan pembatalan wajib 5..255 karakter.');
            }
            DB::table('v2_receipt_drafts')->where('id', $id)->update(['status' => 'cancelled', 'version' => $row->version + 1, 'cancelled_by' => $actor, 'cancelled_at' => now(), 'cancel_reason' => trim($reason), 'updated_at' => now()]);

            return $this->result($this->locked($id));
        });
    }

    private function requireAdmin(int $actor): void
    {
        abort_unless(DB::table('v2_user_roles')->where('user_id', $actor)->value('role') === 'admin', 403);
    }

    private function snapshot(array $data): array
    {
        $validator = Validator::make($data, [
            'supplier_id' => ['required', 'integer', 'min:1'],
            'receipt_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array'],
            'items.*.product_id' => ['required', 'integer', 'min:1', 'distinct'],
        ]);
        if ($validator->fails()) {
            $this->invalid($validator->errors()->first());
        }
        $supplier = (int) $data['supplier_id'];
        if (! DB::table('v2_parties')->where('id', $supplier)->where('kind', 'supplier')->where('is_active', true)->exists()) {
            $this->invalid('Supplier tidak ditemukan atau tidak aktif.');
        }
        $items = [];
        $total = BigDecimal::zero()->toScale(2);
        $ids = array_column($data['items'], 'product_id');
        $products = DB::table('products')->whereIn('id', $ids)->get(['id', 'is_active'])->keyBy('id');
        $links = DB::table('v2_product_suppliers')->where('supplier_id', $supplier)->whereIn('product_id', $ids)->pluck('product_id')->flip();
        foreach ($data['items'] as $row) {
            $product = (int) $row['product_id'];
            if (! isset($products[$product]) || ! $products[$product]->is_active) {
                $this->invalid('Produk #'.$product.' tidak ditemukan atau tidak aktif.');
            }
            if (isset($items[$product])) {
                $this->invalid('Produk duplikat; gabungkan menjadi satu baris.');
            }
            if (! $links->has($product)) {
                $this->invalid('Produk #'.$product.' belum terhubung ke supplier yang dipilih. Atur relasi produk-supplier pada master terlebih dahulu.');
            }
            $quantity = $this->decimal($row['quantity'] ?? null);
            $cost = $this->decimal($row['unit_cost'] ?? null);
            if (! $quantity->isPositive() || $cost->isNegative()) {
                $this->invalid('Jumlah harus positif dan harga tidak boleh negatif.');
            }
            $total = $total->plus($quantity->multipliedBy($cost)->toScale(2, RoundingMode::HalfUp));
            $items[$product] = ['product_id' => $product, 'quantity' => (string) $quantity, 'unit_cost' => (string) $cost];
        }
        if (! $total->isPositive()) {
            $this->invalid('Total penerimaan harus lebih dari nol.');
        }
        try {
            Money::value((string) $total);
        } catch (\InvalidArgumentException) {
            $this->invalid('Total melampaui kapasitas penyimpanan.');
        }
        ksort($items);

        $freight = $this->supplyTrips->freight($data);

        return ['supplier_id' => $supplier, 'receipt_date' => $data['receipt_date'], 'notes' => $this->text($data['notes'] ?? null), 'supplier_invoice_number' => $this->text($data['supplier_invoice_number'] ?? null), 'items' => json_encode(array_values($items), JSON_THROW_ON_ERROR), 'total_amount' => (string) $total]
            + (Schema::hasColumn('v2_receipt_drafts', 'freight_amount') ? $freight : []);
    }

    private function decimal(mixed $value): BigDecimal
    {
        if ((! is_string($value) && ! is_int($value)) || preg_match('/^-?\d{1,15}(\.\d{1,4})?$/', (string) $value) !== 1) {
            $this->invalid('Jumlah dan harga wajib angka desimal maksimal 4 angka pecahan.');
        }
        try {
            return Money::value((string) $value, 4);
        } catch (\InvalidArgumentException) {
            $this->invalid('Nilai angka melampaui kapasitas penyimpanan.');
        }
    }

    private function text(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }

    private function locked(int $id): object
    {
        $row = DB::table('v2_receipt_drafts')->where('id', $id)->lockForUpdate()->first();
        if ($row === null) {
            $this->invalid('Draf penerimaan tidak ditemukan.');
        }

        return $row;
    }

    private function editable(object $row, array $input): void
    {
        if ($row->status !== 'draft') {
            $this->invalid('Hanya draf yang dapat diubah; penerimaan final terkunci.');
        }
        if (filter_var($input['version'] ?? null, FILTER_VALIDATE_INT) !== (int) $row->version) {
            $this->invalid('Versi draf telah berubah. Muat ulang sebelum melanjutkan.');
        }
    }

    private function result(object $row): array
    {
        $result = ['draft_id' => (int) $row->id, 'status' => $row->status, 'version' => (int) $row->version, 'total' => (string) Money::value((string) $row->total_amount)];
        if ($row->status === 'confirmed') {
            $result['receipt_id'] = (int) $row->receipt_id;
            $result['invoice_id'] = (int) DB::table('v2_invoices')->where('receipt_id', $row->receipt_id)->value('id');
        }

        return $result;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['receipt_draft' => $message]);
    }
}
