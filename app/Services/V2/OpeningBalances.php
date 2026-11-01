<?php

namespace App\Services\V2;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Layanan Saldo Awal V2.
 * Singleton batch pembukaan mandiri: kas, bank, persediaan, piutang, dan utang.
 * Sekali posting, seimbang, atomik, immutable, idempotent.
 *
 * Penjurnalan:
 * DR Kas (1101)
 * DR Bank (1102)
 * DR Persediaan (1301)
 * DR Piutang Usaha (1103)
 * CR Utang Usaha (2101)
 * Selisih dialokasikan ke Ekuitas / Modal Awal (3101) sebagai Kredit (jika Aset > Liabilitas)
 * atau Debit (jika Liabilitas > Aset).
 *
 * Catatan Arsitektur:
 * Pembukaan legacy menggunakan bridge tersendiri dan tidak ada pembukaan kedua.
 * Bukan forced reset data yang sudah berjalan.
 */
class OpeningBalances
{
    public const SINGLETON_KEY = 1;

    private const CASH_ACCOUNT = '1101';
    private const BANK_ACCOUNT = '1102';
    private const AR_ACCOUNT = '1103';
    private const INVENTORY_ACCOUNT = '1301';
    private const AP_ACCOUNT = '2101';
    private const EQUITY_ACCOUNT = '3101';

    /**
     * Pastikan aktor memiliki peran admin.
     */
    public function requireAdmin(int $actor): void
    {
        $role = DB::table('v2_user_roles')->where('user_id', $actor)->value('role');
        if ($role !== 'admin') {
            abort(403, 'Hanya peran admin yang berwenang mengelola saldo awal V2.');
        }
    }

    /**
     * Pratinjau saldo awal tanpa mutasi basis data.
     *
     * @param int $actor ID pengguna
     * @param array<string, mixed> $data Payload saldo awal
     * @return array<string, mixed>
     */
    public function preview(int $actor, array $data): array
    {
        $this->requireAdmin($actor);
        $this->assertSystemCanAcceptOpening();

        $normalized = $this->validateAndNormalize($data);

        return [
            'status' => 'preview',
            'summary' => $normalized['summary'],
            'journal_lines' => $normalized['journal_lines'],
            'inventory' => $normalized['inventory'],
            'receivables' => $normalized['receivables'],
            'payables' => $normalized['payables'],
        ];
    }

    /**
     * Eksekusi posting saldo awal V2 secara atomik dan idempotent.
     *
     * @param int $actor ID pengguna
     * @param array<string, mixed> $data Payload saldo awal
     * @return array<string, mixed>
     */
    public function post(int $actor, array $data): array
    {
        $this->requireAdmin($actor);

        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'Idempotency key wajib diisi.']);
        }

        $requestHash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // Cek batch pembukaan yang sudah ada
        $existing = DB::table('v2_opening_batches')->where('singleton_key', self::SINGLETON_KEY)->first();
        if ($existing) {
            if ($existing->idempotency_key === $idempotencyKey && $existing->request_hash === $requestHash) {
                return [
                    'status' => 'idempotent_ok',
                    'batch_id' => $existing->id,
                    'journal_entry_id' => $existing->journal_entry_id,
                    'reference' => $existing->reference,
                    'posted_at' => $existing->posted_at,
                    'summary' => [
                        'cash' => $existing->cash_amount,
                        'bank' => $existing->bank_amount,
                        'inventory' => $existing->inventory_amount,
                        'receivables' => $existing->ar_amount,
                        'payables' => $existing->ap_amount,
                        'equity' => $existing->equity_amount,
                        'equity_direction' => $existing->equity_direction,
                    ],
                ];
            }

            throw ValidationException::withMessages([
                'opening_balance' => 'Saldo awal V2 sudah pernah diposting dan bersifat permanen (immutable once per install).',
            ]);
        }

        $this->assertSystemCanAcceptOpening();
        $normalized = $this->validateAndNormalize($data);

        return DB::transaction(function () use ($actor, $normalized, $idempotencyKey, $requestHash) {
            // Lock singleton serialization via pessimistic check
            $locked = DB::table('v2_opening_batches')
                ->where('singleton_key', self::SINGLETON_KEY)
                ->lockForUpdate()
                ->first();

            if ($locked) {
                if ($locked->idempotency_key === $idempotencyKey && $locked->request_hash === $requestHash) {
                    return [
                        'status' => 'idempotent_ok',
                        'batch_id' => $locked->id,
                        'journal_entry_id' => $locked->journal_entry_id,
                        'reference' => $locked->reference,
                        'posted_at' => $locked->posted_at,
                    ];
                }
                throw ValidationException::withMessages([
                    'opening_balance' => 'Saldo awal V2 sudah pernah diposting.',
                ]);
            }

            DB::table('chart_of_accounts')->where('account_code', self::EQUITY_ACCOUNT)->lockForUpdate()->first();
            DB::table('products')->orderBy('id')->lockForUpdate()->get(['id']);
            $this->assertSystemCanAcceptOpening();

            // 1. Buat Batch Record terlebih dahulu
            $batchId = DB::table('v2_opening_batches')->insertGetId([
                'singleton_key' => self::SINGLETON_KEY,
                'date' => $normalized['date'],
                'reference' => $normalized['reference'],
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'cash_amount' => $normalized['summary']['cash'],
                'bank_amount' => $normalized['summary']['bank'],
                'inventory_amount' => $normalized['summary']['inventory'],
                'ar_amount' => $normalized['summary']['receivables'],
                'ap_amount' => $normalized['summary']['payables'],
                'equity_amount' => $normalized['summary']['equity'],
                'equity_direction' => $normalized['summary']['equity_direction'],
                'created_by' => $actor,
                'journal_entry_id' => null,
                'posted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. Posting Jurnal Seimbang via Ledger
            $journalId = Ledger::post(
                'v2_opening_balance',
                $batchId,
                'Saldo awal pembukaan sistem V2 (' . $normalized['reference'] . ')',
                $normalized['journal_lines'],
                $normalized['date']
            );

            DB::table('v2_opening_batches')->where('id', $batchId)->update([
                'journal_entry_id' => $journalId,
            ]);

            // 3. Update Persediaan & Stock Movements
            foreach ($normalized['inventory'] as $item) {
                $productId = $item['product_id'];
                $qty = $item['quantity'];
                $cost = $item['unit_cost'];

                // Update produk secara atomik
                DB::table('products')->where('id', $productId)->update([
                    'stock_quantity' => $qty,
                    'cost_price' => $cost,
                    'updated_at' => now(),
                ]);

                // Catat mutasi stok pembukaan
                DB::table('stock_movements')->insert([
                    'product_id' => $productId,
                    'reference_type' => 'v2_opening_balance',
                    'reference_id' => $batchId,
                    'quantity_delta' => $qty,
                    'balance_after' => $qty,
                    'notes' => 'Saldo awal stok V2: ' . $normalized['reference'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 4. Buat Faktur Piutang (Receivables) tanpa transaksi penjualan palsu
            foreach ($normalized['receivables'] as $ar) {
                DB::table('v2_invoices')->insert([
                    'direction' => 'receivable',
                    'party_id' => $ar['party_id'],
                    'sale_id' => null,
                    'receipt_id' => null,
                    'number' => $ar['number'],
                    'amount' => $ar['amount'],
                    'paid' => '0.00',
                    'due_date' => $ar['due_date'],
                    'status' => 'open',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 5. Buat Faktur Utang (Payables) tanpa fake purchase
            foreach ($normalized['payables'] as $ap) {
                DB::table('v2_invoices')->insert([
                    'direction' => 'payable',
                    'party_id' => $ap['party_id'],
                    'sale_id' => null,
                    'receipt_id' => null,
                    'number' => $ap['number'],
                    'amount' => $ap['amount'],
                    'paid' => '0.00',
                    'due_date' => $ap['due_date'],
                    'status' => 'open',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return [
                'status' => 'posted',
                'batch_id' => $batchId,
                'journal_entry_id' => $journalId,
                'reference' => $normalized['reference'],
                'posted_at' => now()->toDateTimeString(),
                'summary' => $normalized['summary'],
            ];
        });
    }

    /**
     * Memeriksa prasyarat pembukaan:
     * Menolak jika sudah ada jurnal keuangan (legacy maupun V2) atau stok produk non-zero
     * kecuali sistem bersih / seed kosong.
     */
    private function assertSystemCanAcceptOpening(): void
    {
        // 1. Cek apakah v2_opening_batches sudah ada
        if (DB::table('v2_opening_batches')->exists()) {
            throw ValidationException::withMessages([
                'opening_balance' => 'Saldo awal V2 sudah pernah diposting dan bersifat sekali pakai (singleton).',
            ]);
        }

        // 2. Cek apakah ada jurnal di journal_entries
        $journalCount = DB::table('journal_entries')->count();
        if ($journalCount > 0) {
            throw ValidationException::withMessages([
                'journal' => 'Tidak dapat memposting saldo awal: jurnal transaksi keuangan sudah ada dalam sistem.',
            ]);
        }

        // 3. Cek apakah ada stok produk yang bernilai bukan nol
        $hasNonZeroStock = DB::table('products')->where('stock_quantity', '!=', 0)->exists();
        if ($hasNonZeroStock) {
            throw ValidationException::withMessages([
                'inventory' => 'Tidak dapat memposting saldo awal: stok produk telah terisi sebelum saldo awal dibuka.',
            ]);
        }

        // 4. Cek apakah sudah ada faktur V2
        if (DB::table('v2_invoices')->exists()) {
            throw ValidationException::withMessages([
                'invoices' => 'Tidak dapat memposting saldo awal: data faktur V2 sudah ada.',
            ]);
        }
    }

    /**
     * Validasi format dan normalisasi semua nilai angka ke exact string decimal.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validateAndNormalize(array $data): array
    {
        $date = trim((string) ($data['date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            throw ValidationException::withMessages(['date' => 'Tanggal saldo awal wajib berformat YYYY-MM-DD yang valid.']);
        }

        $reference = trim((string) ($data['reference'] ?? ''));
        if ($reference === '') {
            $reference = 'SALDO-AWAL-V2';
        }
        if (mb_strlen($reference) > 50) {
            throw ValidationException::withMessages(['reference' => 'Referensi maksimal 50 karakter.']);
        }

        // Parse Kas & Bank
        $cashStr = (string) ($data['cash'] ?? '0.00');
        $bankStr = (string) ($data['bank'] ?? '0.00');

        $cash = Money::value($cashStr, 2);
        $bank = Money::value($bankStr, 2);

        if ($cash->isNegative()) {
            throw ValidationException::withMessages(['cash' => 'Saldo kas awal tidak boleh negatif.']);
        }
        if ($bank->isNegative()) {
            throw ValidationException::withMessages(['bank' => 'Saldo bank awal tidak boleh negatif.']);
        }

        // Parse Inventory
        $rawInventory = $data['inventory'] ?? [];
        if (!is_array($rawInventory)) {
            throw ValidationException::withMessages(['inventory' => 'Data persediaan harus berupa array.']);
        }

        $totalInventory = BigDecimal::of('0.00');
        $normalizedInventory = [];
        $seenProductIds = [];

        foreach ($rawInventory as $idx => $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId <= 0 || !DB::table('products')->where('id', $productId)->exists()) {
                throw ValidationException::withMessages(["inventory.{$idx}.product_id" => "Produk ID {$productId} tidak ditemukan."]);
            }
            if (isset($seenProductIds[$productId])) {
                throw ValidationException::withMessages(["inventory.{$idx}.product_id" => "Produk ID {$productId} terduplikasi dalam saldo awal."]);
            }
            $seenProductIds[$productId] = true;

            $qty = Money::value((string) ($item['quantity'] ?? '0'), 4);
            $cost = Money::value((string) ($item['unit_cost'] ?? '0'), 4);

            if ($qty->isNegative()) {
                throw ValidationException::withMessages(["inventory.{$idx}.quantity" => 'Kuantitas persediaan tidak boleh negatif.']);
            }
            if ($cost->isNegative()) {
                throw ValidationException::withMessages(["inventory.{$idx}.unit_cost" => 'HPP persediaan tidak boleh negatif.']);
            }

            // Subtotal persediaan dibulatkan ke skala uang 2
            $subtotal = $qty->multipliedBy($cost)->toScale(2, RoundingMode::HalfUp);
            $totalInventory = $totalInventory->plus($subtotal);

            $normalizedInventory[] = [
                'product_id' => $productId,
                'quantity' => (string) $qty,
                'unit_cost' => (string) $cost,
                'subtotal' => (string) $subtotal,
            ];
        }

        // Parse Receivables (Piutang)
        $rawAr = $data['receivables'] ?? [];
        if (!is_array($rawAr)) {
            throw ValidationException::withMessages(['receivables' => 'Data piutang harus berupa array.']);
        }

        $totalAr = BigDecimal::of('0.00');
        $normalizedAr = [];
        $seenInvoiceNumbers = [];

        foreach ($rawAr as $idx => $ar) {
            $partyId = isset($ar['party_id']) && $ar['party_id'] !== null ? (int) $ar['party_id'] : null;
            if ($partyId === null || !DB::table('v2_parties')->where('id', $partyId)->where('kind', 'customer')->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(["receivables.{$idx}.party_id" => "Pihak/pelanggan ID {$partyId} tidak ditemukan."]);
            }

            $number = trim((string) ($ar['number'] ?? ''));
            if ($number === '') {
                throw ValidationException::withMessages(["receivables.{$idx}.number" => 'Nomor faktur piutang wajib diisi.']);
            }
            if (isset($seenInvoiceNumbers[$number]) || DB::table('v2_invoices')->where('number', $number)->exists()) {
                throw ValidationException::withMessages(["receivables.{$idx}.number" => "Nomor faktur {$number} sudah digunakan."]);
            }
            $seenInvoiceNumbers[$number] = true;

            $amount = Money::value((string) ($ar['amount'] ?? '0'), 2);
            if ($amount->isLessThanOrEqualTo(BigDecimal::zero())) {
                throw ValidationException::withMessages(["receivables.{$idx}.amount" => 'Jumlah piutang harus lebih besar dari 0.']);
            }

            $dueDate = isset($ar['due_date']) && $ar['due_date'] !== null ? trim((string) $ar['due_date']) : null;
            if ($dueDate !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate) || !checkdate((int) substr($dueDate, 5, 2), (int) substr($dueDate, 8, 2), (int) substr($dueDate, 0, 4)))) {
                throw ValidationException::withMessages(["receivables.{$idx}.due_date" => 'Jatuh tempo piutang tidak valid.']);
            }

            $totalAr = $totalAr->plus($amount);
            $normalizedAr[] = [
                'party_id' => $partyId,
                'number' => $number,
                'amount' => (string) $amount,
                'due_date' => $dueDate,
            ];
        }

        // Parse Payables (Utang)
        $rawAp = $data['payables'] ?? [];
        if (!is_array($rawAp)) {
            throw ValidationException::withMessages(['payables' => 'Data utang harus berupa array.']);
        }

        $totalAp = BigDecimal::of('0.00');
        $normalizedAp = [];

        foreach ($rawAp as $idx => $ap) {
            $partyId = isset($ap['party_id']) && $ap['party_id'] !== null ? (int) $ap['party_id'] : null;
            if ($partyId === null || !DB::table('v2_parties')->where('id', $partyId)->where('kind', 'supplier')->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(["payables.{$idx}.party_id" => "Pihak/supplier ID {$partyId} tidak ditemukan."]);
            }

            $number = trim((string) ($ap['number'] ?? ''));
            if ($number === '') {
                throw ValidationException::withMessages(["payables.{$idx}.number" => 'Nomor faktur utang wajib diisi.']);
            }
            if (isset($seenInvoiceNumbers[$number]) || DB::table('v2_invoices')->where('number', $number)->exists()) {
                throw ValidationException::withMessages(["payables.{$idx}.number" => "Nomor faktur {$number} sudah digunakan."]);
            }
            $seenInvoiceNumbers[$number] = true;

            $amount = Money::value((string) ($ap['amount'] ?? '0'), 2);
            if ($amount->isLessThanOrEqualTo(BigDecimal::zero())) {
                throw ValidationException::withMessages(["payables.{$idx}.amount" => 'Jumlah utang harus lebih besar dari 0.']);
            }

            $dueDate = isset($ap['due_date']) && $ap['due_date'] !== null ? trim((string) $ap['due_date']) : null;
            if ($dueDate !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate) || !checkdate((int) substr($dueDate, 5, 2), (int) substr($dueDate, 8, 2), (int) substr($dueDate, 0, 4)))) {
                throw ValidationException::withMessages(["payables.{$idx}.due_date" => 'Jatuh tempo utang tidak valid.']);
            }

            $totalAp = $totalAp->plus($amount);
            $normalizedAp[] = [
                'party_id' => $partyId,
                'number' => $number,
                'amount' => (string) $amount,
                'due_date' => $dueDate,
            ];
        }

        // Hitung Keseimbangan Jurnal & Modal/Ekuitas
        // Total Aset = Kas + Bank + Persediaan + Piutang
        $totalAssets = $cash->plus($bank)->plus($totalInventory)->plus($totalAr);
        // Total Liabilitas = Utang
        $totalLiabilities = $totalAp;

        $journalLines = [];

        if ($cash->isPositive()) {
            $journalLines[] = [
                'account_code' => self::CASH_ACCOUNT,
                'debit' => (string) $cash,
                'credit' => '0.00',
            ];
        }

        if ($bank->isPositive()) {
            $journalLines[] = [
                'account_code' => self::BANK_ACCOUNT,
                'debit' => (string) $bank,
                'credit' => '0.00',
            ];
        }

        if ($totalInventory->isPositive()) {
            $journalLines[] = [
                'account_code' => self::INVENTORY_ACCOUNT,
                'debit' => (string) $totalInventory,
                'credit' => '0.00',
            ];
        }

        if ($totalAr->isPositive()) {
            $journalLines[] = [
                'account_code' => self::AR_ACCOUNT,
                'debit' => (string) $totalAr,
                'credit' => '0.00',
            ];
        }

        if ($totalAp->isPositive()) {
            $journalLines[] = [
                'account_code' => self::AP_ACCOUNT,
                'debit' => '0.00',
                'credit' => (string) $totalAp,
            ];
        }

        // Selisih aset dan liabilitas ke ekuitas (3101)
        // Aset = Liabilitas + Ekuitas => Ekuitas = Aset - Liabilitas
        $diff = $totalAssets->minus($totalLiabilities);
        $equityDirection = 'credit';
        $equityAmount = $diff->abs();

        if ($diff->isPositive()) {
            // Aset > Liabilitas => Kredit Ekuitas
            $equityDirection = 'credit';
            $journalLines[] = [
                'account_code' => self::EQUITY_ACCOUNT,
                'debit' => '0.00',
                'credit' => (string) $equityAmount,
            ];
        } elseif ($diff->isNegative()) {
            // Liabilitas > Aset => Debit Ekuitas
            $equityDirection = 'debit';
            $journalLines[] = [
                'account_code' => self::EQUITY_ACCOUNT,
                'debit' => (string) $equityAmount,
                'credit' => '0.00',
            ];
        } else {
            // Jika selisih tepat 0 dan tidak ada baris sama sekali, tolak
            if (empty($journalLines)) {
                throw ValidationException::withMessages([
                    'opening_balance' => 'Saldo awal tidak boleh kosong sama sekali.',
                ]);
            }
        }

        return [
            'date' => $date,
            'reference' => $reference,
            'summary' => [
                'cash' => (string) $cash,
                'bank' => (string) $bank,
                'inventory' => (string) $totalInventory,
                'receivables' => (string) $totalAr,
                'payables' => (string) $totalAp,
                'assets_total' => (string) $totalAssets,
                'liabilities_total' => (string) $totalLiabilities,
                'equity' => (string) $equityAmount,
                'equity_direction' => $equityDirection,
            ],
            'journal_lines' => $journalLines,
            'inventory' => $normalizedInventory,
            'receivables' => $normalizedAr,
            'payables' => $normalizedAp,
        ];
    }
}
