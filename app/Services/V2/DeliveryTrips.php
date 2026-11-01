<?php

namespace App\Services\V2;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Siklus pengiriman V2 (prepared -> sent -> received) + uang muka sopir.
 * Uang lewat Money (BigDecimal, HalfUp), jurnal lewat Ledger. Tanpa mutasi stok.
 *
 * Advance: DR 1191 (uang muka karyawan) / CR 1101|1102.
 * Settle : DR 6101 aktual + DR 1101|1102 sisa kembali / CR 1191 uang muka.
 *
 * Batasan jujur: biaya aktual di atas uang muka DITOLAK. Selisih lebih harus
 * dicatat sebagai pengeluaran terpisah (belum didukung di sini).
 */
class DeliveryTrips
{
    private const CASH = '1101';
    private const BANK = '1102';
    private const ADVANCE_ASSET = '1191';
    private const TRIP_EXPENSE = '6101';

    /** prepared -> sent: snapshot kendaraan/sopir + audit dispatched_by/at. Tanpa jurnal/stok. */
    public function dispatch(int $actor, int $deliveryId, array $data): void
    {
        if ($deliveryId <= 0) {
            $this->invalid('Pengiriman tidak valid.');
        }

        $vehicleId = $this->id($data['vehicle_id'] ?? null, 'Kendaraan');
        $driver = $this->text($data['driver_name'] ?? null, 'Nama sopir', 100);
        if ($driver === null) {
            $this->invalid('Nama sopir wajib diisi.');
        }

        DB::transaction(function () use ($actor, $deliveryId, $vehicleId, $driver) {
            $this->lockActor($actor);

            $delivery = DB::table('v2_deliveries')->where('id', $deliveryId)->lockForUpdate()->first();
            if (! $delivery) {
                $this->invalid('Pengiriman tidak ditemukan.');
            }
            $this->access($actor, $delivery);

            if ($delivery->status === 'sent') {
                return; // kirim ulang identik: tanpa efek samping
            }
            if ($delivery->status === 'received') {
                $this->invalid('Pengiriman yang sudah diterima tidak dapat dikirim ulang.');
            }

            $vehicle = DB::table('vehicles')->where('id', $vehicleId)->lockForUpdate()->first();
            if (! $vehicle || $vehicle->status !== 'active') {
                $this->invalid('Kendaraan tidak ditemukan atau tidak aktif.');
            }

            DB::table('v2_deliveries')->where('id', $deliveryId)->update([
                'vehicle_id' => $vehicleId,
                'driver_name' => $driver,
                'status' => 'sent',
                'dispatched_by' => $actor,
                'dispatched_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /** sent -> received: audit received_by/at. Tanpa mutasi stok. */
    public function receive(int $actor, int $deliveryId): void
    {
        if ($deliveryId <= 0) {
            $this->invalid('Pengiriman tidak valid.');
        }

        DB::transaction(function () use ($actor, $deliveryId) {
            $this->lockActor($actor);

            $delivery = DB::table('v2_deliveries')->where('id', $deliveryId)->lockForUpdate()->first();
            if (! $delivery) {
                $this->invalid('Pengiriman tidak ditemukan.');
            }
            $this->access($actor, $delivery);

            if ($delivery->status === 'received') {
                return; // terima ulang identik: tanpa efek samping
            }
            if ($delivery->status !== 'sent') {
                $this->invalid('Pengiriman harus dikirim terlebih dahulu sebelum diterima.');
            }

            DB::table('v2_deliveries')->where('id', $deliveryId)->update([
                'status' => 'received',
                'received_by' => $actor,
                'received_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Uang muka sopir (admin): DR 1191 / CR kas|bank. Kas wajib shift admin terbuka,
     * saldo laci dan saldo akun harus cukup di bawah kunci.
     *
     * @return array{advance_id:int, amount:string, journal_entry_id:int, status:string}
     */
    public function advance(int $actor, array $data): array
    {
        $this->requireAdmin($actor);

        $vehicleId = $this->id($data['vehicle_id'] ?? null, 'Kendaraan');
        $driver = $this->text($data['driver_name'] ?? null, 'Nama sopir', 100);
        if ($driver === null) {
            $this->invalid('Nama sopir wajib diisi.');
        }
        $destination = $this->text($data['destination'] ?? null, 'Tujuan', 150);
        $deliveryId = ($data['delivery_id'] ?? null) === null || ($data['delivery_id'] ?? null) === ''
            ? null
            : $this->id($data['delivery_id'], 'Pengiriman');
        $date = ($data['date'] ?? null) === null || ($data['date'] ?? null) === ''
            ? now()->toDateString()
            : $this->date($data['date'], 'Tanggal uang muka');

        $amount = $this->amount($data['amount'] ?? null, 2, 'Jumlah uang muka');
        if (! $amount->isPositive()) {
            $this->invalid('Jumlah uang muka harus lebih dari nol.');
        }

        $method = $data['method'] ?? null;
        if (! in_array($method, ['cash', 'transfer'], true)) {
            $this->invalid('Metode uang muka harus cash atau transfer.');
        }

        $key = $this->key($data['idempotency_key'] ?? null);
        $hash = $this->hash('advance', $actor, [
            'vehicle_id' => $vehicleId,
            'driver_name' => $driver,
            'destination' => $destination,
            'delivery_id' => $deliveryId,
            'date' => $date,
            'amount' => (string) $amount,
            'method' => $method,
        ]);

        try {
            return DB::transaction(function () use ($actor, $vehicleId, $deliveryId, $driver, $destination, $date, $amount, $method, $key, $hash) {
                $this->lockActor($actor);
                $seen = $this->replay($actor, 'advance', $key, $hash);
                if ($seen !== null) {
                    return $seen;
                }

                $vehicle = DB::table('vehicles')->where('id', $vehicleId)->lockForUpdate()->first();
                if (! $vehicle || $vehicle->status !== 'active') {
                    $this->invalid('Kendaraan tidak ditemukan atau tidak aktif.');
                }
                if ($deliveryId !== null && ! DB::table('v2_deliveries')->where('id', $deliveryId)->exists()) {
                    $this->invalid('Pengiriman tidak ditemukan.');
                }

                $account = $method === 'cash' ? self::CASH : self::BANK;
                $shift = null;
                if ($method === 'cash') {
                    $shift = $this->openShift($actor);
                    if ($this->drawerBalance($shift)->isLessThan($amount)) {
                        $this->invalid('Saldo laci kas tidak cukup untuk uang muka ini.');
                    }
                }

                $this->requireAccount(self::ADVANCE_ASSET);
                $this->guardAccount($account, $amount);

                $now = now();
                $advanceId = (int) DB::table('v2_driver_advances')->insertGetId([
                    'vehicle_id' => $vehicleId,
                    'driver_name' => $driver,
                    'destination' => $destination,
                    'delivery_id' => $deliveryId,
                    'date' => $date,
                    'amount' => $this->str($amount),
                    'method' => $method,
                    'status' => 'open',
                    'requested_by' => $actor,
                    'journal_entry_id' => null,
                    'idempotency_key' => $key,
                    'request_hash' => $hash,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $journalId = Ledger::post('v2_driver_advance', $advanceId, 'Uang muka sopir '.$driver.' '.$date, [
                    ['account_code' => self::ADVANCE_ASSET, 'debit' => $this->str($amount), 'credit' => '0.00'],
                    ['account_code' => $account, 'debit' => '0.00', 'credit' => $this->str($amount)],
                ], $date);

                DB::table('v2_driver_advances')->where('id', $advanceId)->update([
                    'journal_entry_id' => $journalId,
                    'updated_at' => $now,
                ]);

                if ($shift !== null) {
                    DB::table('v2_shift_movements')->insert([
                        'shift_id' => (int) $shift->id,
                        'direction' => 'out',
                        'kind' => 'adjustment',
                        'amount' => $this->str($amount),
                        'reference_type' => 'v2_driver_advance',
                        'reference_id' => $advanceId,
                        'note' => 'Uang muka sopir '.$driver,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $result = ['advance_id' => $advanceId, 'amount' => $this->str($amount), 'journal_entry_id' => $journalId, 'status' => 'open'];
                $this->remember($actor, 'advance', $key, $hash, $result);

                return $result;
            });
        } catch (QueryException $e) {
            $this->invalid('Kunci idempotensi uang muka sudah dipakai.');
        }
    }

    /**
     * Penyelesaian uang muka (admin): DR 6101 aktual + DR kas|bank sisa kembali,
     * CR 1191 uang muka. Beban hanya dicatat di sini (tanpa v2_trips kedua).
     *
     * @return array{advance_id:int, settlement_id:int, expense_total:string, refund_amount:string, journal_entry_id:int, status:string}
     */
    public function settle(int $actor, int $advanceId, array $data): array
    {
        $this->requireAdmin($actor);
        if ($advanceId <= 0) {
            $this->invalid('Uang muka tidak valid.');
        }

        $fuel = $this->amount($data['fuel_cost'] ?? '0', 2, 'Biaya bahan bakar');
        $allowance = $this->amount($data['allowance'] ?? '0', 2, 'Uang jalan');
        if ($fuel->isNegative() || $allowance->isNegative()) {
            $this->invalid('Biaya aktual tidak boleh negatif.');
        }
        $actual = $fuel->plus($allowance);

        $refundMethod = $data['refund_method'] ?? null;
        if ($refundMethod !== null && ! in_array($refundMethod, ['cash', 'transfer'], true)) {
            $this->invalid('Metode pengembalian harus cash atau transfer.');
        }
        $refundReceived = ($data['refund_received'] ?? null) === true;

        $key = $this->key($data['idempotency_key'] ?? null);
        $hash = $this->hash('settle', $actor, [
            'advance_id' => $advanceId,
            'fuel_cost' => (string) $fuel,
            'allowance' => (string) $allowance,
            'refund_method' => $refundMethod,
            'refund_received' => $refundReceived,
        ]);

        try {
            return DB::transaction(function () use ($actor, $advanceId, $fuel, $allowance, $actual, $refundMethod, $refundReceived, $key, $hash) {
                $this->lockActor($actor);
                $seen = $this->replay($actor, 'settle', $key, $hash);
                if ($seen !== null) {
                    return $seen;
                }

                $advance = DB::table('v2_driver_advances')->where('id', $advanceId)->lockForUpdate()->first();
                if (! $advance) {
                    $this->invalid('Uang muka tidak ditemukan.');
                }
                if ($advance->status === 'settled') {
                    $this->invalid('Uang muka ini sudah diselesaikan.');
                }

                $this->requireAccount(self::ADVANCE_ASSET);
                $advanceAmount = Money::value((string) $advance->amount);
                if ($actual->isGreaterThan($advanceAmount)) {
                    $this->invalid('Biaya aktual melebihi uang muka; selisih lebih harus dicatat sebagai pengeluaran terpisah (belum didukung).');
                }

                $refund = $advanceAmount->minus($actual);
                if ($refund->isPositive()) {
                    if (! $refundReceived) {
                        $this->invalid('Sisa uang muka '.$this->str($refund).' harus dikonfirmasi dengan refund_received=true.');
                    }
                    if ($refundMethod === null) {
                        $this->invalid('Metode pengembalian sisa wajib diisi (cash atau transfer).');
                    }
                }

                $account = $refundMethod === 'transfer' ? self::BANK : self::CASH;
                $shift = null;
                if ($refund->isPositive() && $account === self::CASH) {
                    $shift = $this->openShift($actor); // uang kembali masuk laci shift sendiri
                }

                $now = now();
                $settlementId = (int) DB::table('v2_driver_advance_settlements')->insertGetId([
                    'advance_id' => $advanceId,
                    'fuel_cost' => $this->str($fuel),
                    'allowance' => $this->str($allowance),
                    'expense_total' => $this->str($actual),
                    'refund_amount' => $this->str($refund),
                    'refund_method' => $account === self::BANK ? 'transfer' : 'cash',
                    'refund_received' => $refundReceived,
                    'status' => 'settled',
                    'journal_entry_id' => null,
                    'settled_by' => $actor,
                    'settled_at' => $now,
                    'idempotency_key' => $key,
                    'request_hash' => $hash,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $lines = [];
                if ($actual->isPositive()) {
                    $lines[] = ['account_code' => self::TRIP_EXPENSE, 'debit' => $this->str($actual), 'credit' => '0.00'];
                }
                if ($refund->isPositive()) {
                    $lines[] = ['account_code' => $account, 'debit' => $this->str($refund), 'credit' => '0.00'];
                }
                $lines[] = ['account_code' => self::ADVANCE_ASSET, 'debit' => '0.00', 'credit' => $this->str($advanceAmount)];

                $journalId = Ledger::post(
                    'v2_driver_advance_settlement',
                    $settlementId,
                    'Penyelesaian uang muka sopir '.$advance->driver_name,
                    $lines
                );

                DB::table('v2_driver_advance_settlements')->where('id', $settlementId)->update([
                    'journal_entry_id' => $journalId,
                    'updated_at' => now(),
                ]);
                DB::table('v2_driver_advances')->where('id', $advanceId)->update([
                    'status' => 'settled',
                    'updated_at' => now(),
                ]);

                if ($shift !== null) {
                    DB::table('v2_shift_movements')->insert([
                        'shift_id' => (int) $shift->id,
                        'direction' => 'in',
                        'kind' => 'adjustment',
                        'amount' => $this->str($refund),
                        'reference_type' => 'v2_driver_advance_settlement',
                        'reference_id' => $settlementId,
                        'note' => 'Sisa uang muka sopir '.$advance->driver_name,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $result = [
                    'advance_id' => $advanceId,
                    'settlement_id' => $settlementId,
                    'expense_total' => $this->str($actual),
                    'refund_amount' => $this->str($refund),
                    'journal_entry_id' => $journalId,
                    'status' => 'settled',
                ];
                $this->remember($actor, 'settle', $key, $hash, $result);

                return $result;
            });
        } catch (QueryException $e) {
            $this->invalid('Kunci idempotensi penyelesaian sudah dipakai.');
        }
    }

    /** Admin, atau kasir pemilik penjualan pengiriman tersebut. */
    private function access(int $actor, object $delivery): void
    {
        $role = DB::table('v2_user_roles')->where('user_id', $actor)->value('role');
        if ($role === 'admin') {
            return;
        }
        if ($role === 'cashier') {
            $owner = DB::table('v2_sales')->where('id', $delivery->sale_id)->value('user_id');
            if ((int) $owner === $actor) {
                return;
            }
        }
        abort(403);
    }

    private function requireAdmin(int $actor): void
    {
        if (DB::table('v2_user_roles')->where('user_id', $actor)->value('role') !== 'admin') {
            abort(403);
        }
    }

    private function lockActor(int $actor): void
    {
        if (! DB::table('users')->where('id', $actor)->lockForUpdate()->exists()) {
            abort(403);
        }
    }

    private function openShift(int $actor): object
    {
        $shift = DB::table('v2_shifts')
            ->where('user_id', $actor)
            ->where('status', 'open')
            ->lockForUpdate()
            ->first();
        if (! $shift) {
            $this->invalid('Transaksi tunai wajib memakai shift admin yang sedang terbuka.');
        }

        return $shift;
    }

    private function drawerBalance(object $shift): BigDecimal
    {
        $balance = Money::value((string) $shift->opening_cash);
        foreach (DB::table('v2_shift_movements')->where('shift_id', $shift->id)->get(['direction', 'amount']) as $movement) {
            $balance = $movement->direction === 'in'
                ? $balance->plus((string) $movement->amount)
                : $balance->minus((string) $movement->amount);
        }

        return $balance;
    }

    /** Akun wajib aktif; service tidak pernah membuat akun baru. */
    private function requireAccount(string $code): void
    {
        if (! DB::table('chart_of_accounts')->where('account_code', $code)->where('is_active', true)->lockForUpdate()->exists()) {
            $this->invalid('Akun '.$code.' belum tersedia di bagan akun.');
        }
    }

    /** Akun wajib sudah ada (tidak pernah dibuat di sini) dan saldonya cukup. */
    private function guardAccount(string $code, BigDecimal $amount): void
    {
        if (! DB::table('chart_of_accounts')->where('account_code', $code)->where('is_active', true)->lockForUpdate()->exists()) {
            $this->invalid('Akun '.$code.' belum tersedia di bagan akun.');
        }

        $balance = Money::value('0');
        foreach (DB::table('journal_entry_lines')->where('account_code', $code)->get(['debit', 'credit']) as $line) {
            $balance = $balance->plus((string) $line->debit)->minus((string) $line->credit);
        }
        if ($balance->isLessThan($amount)) {
            $this->invalid('Saldo akun '.$code.' tidak cukup untuk pengeluaran ini.');
        }
    }

    private function replay(int $actor, string $type, string $key, string $hash): ?array
    {
        $row = DB::table('v2_requests')->where('actor', $actor)->where('type', $type)->where('key', $key)->first();
        if ($row === null) {
            return null;
        }
        if (! hash_equals((string) $row->request_hash, $hash)) {
            $this->invalid('Kunci idempotensi sudah dipakai untuk muatan berbeda.');
        }

        $result = json_decode((string) $row->result, true);
        if (! is_array($result)) {
            $this->invalid('Hasil idempotensi tidak terbaca.');
        }

        return $result;
    }

    private function remember(int $actor, string $type, string $key, string $hash, array $result): void
    {
        DB::table('v2_requests')->insert([
            'actor' => $actor,
            'type' => $type,
            'key' => $key,
            'request_hash' => $hash,
            'result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function hash(string $type, int $actor, array $payload): string
    {
        return hash('sha256', json_encode(
            ['type' => $type, 'actor' => $actor, 'payload' => $payload],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
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

    private function amount(mixed $value, int $scale, string $field): BigDecimal
    {
        if (is_int($value)) {
            $raw = (string) $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $raw = trim($value);
        } else {
            $this->invalid($field.' wajib berupa angka.');
        }

        try {
            if (BigDecimal::of($raw)->strippedOfTrailingZeros()->getScale() > $scale) {
                $this->invalid($field.' maksimal '.$scale.' desimal.');
            }

            return Money::value($raw, $scale)->toScale($scale, RoundingMode::HalfUp);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->invalid($field.' tidak valid.');
        }
    }

    private function date(mixed $value, string $field): string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            $this->invalid($field.' harus berformat YYYY-MM-DD.');
        }
        [$year, $month, $day] = array_map('intval', explode('-', $value));
        if (! checkdate($month, $day, $year)) {
            $this->invalid($field.' bukan tanggal yang sah.');
        }

        return $value;
    }

    private function key(mixed $value): string
    {
        if (! is_string($value)) {
            $this->invalid('Kunci idempotensi wajib diisi.');
        }
        $value = trim($value);
        if (preg_match('/^[\x20-\x7E]{1,100}$/', $value) !== 1) {
            $this->invalid('Kunci idempotensi 1..100 karakter ASCII.');
        }

        return $value;
    }

    private function text(mixed $value, string $field, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            $this->invalid($field.' harus berupa teks.');
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $max) {
            $this->invalid($field.' maksimal '.$max.' karakter.');
        }

        return $value;
    }

    private function str(BigDecimal $value): string
    {
        return (string) $value->toScale(2, RoundingMode::HalfUp);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['delivery' => $message]);
    }
}
