<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\OpeningBalances;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class V2OpeningBalancesTest extends TestCase
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

    private function accounts(): void
    {
        $rows = [
            ['1101', 'Kas', 'asset', 'debit'],
            ['1102', 'Bank', 'asset', 'debit'],
            ['1103', 'Piutang Usaha', 'asset', 'debit'],
            ['1301', 'Persediaan Barang Dagang', 'asset', 'debit'],
            ['2101', 'Utang Usaha', 'liability', 'credit'],
            ['3101', 'Modal Pemilik', 'equity', 'credit'],
        ];

        foreach ($rows as [$code, $name, $type, $normal]) {
            DB::table('chart_of_accounts')->insert([
                'account_code' => $code,
                'account_name' => $name,
                'account_type' => $type,
                'normal_balance' => $normal,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function product(string $sku, string $name, string $stock = '0.0000', string $cost = '0.0000'): int
    {
        return DB::table('products')->insertGetId([
            'sku' => $sku,
            'name' => $name,
            'unit' => 'zak',
            'cost_price' => $cost,
            'selling_price' => '100000.00',
            'stock_quantity' => $stock,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function party(string $kind, string $code, string $name): int
    {
        return DB::table('v2_parties')->insertGetId([
            'kind' => $kind,
            'code' => $code,
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cashier_is_forbidden_from_preview_and_post(): void
    {
        $this->accounts();
        $cashier = $this->actor('cashier');
        $service = new OpeningBalances();

        $payload = [
            'date' => '2026-10-30',
            'cash' => '1000000.00',
            'idempotency_key' => 'idemp-cashier-test',
        ];

        try {
            $service->preview($cashier->id, $payload);
            $this->fail('Preview by cashier should throw 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        try {
            $service->post($cashier->id, $payload);
            $this->fail('Post by cashier should throw 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        // Verify zero writes occurred
        $this->assertDatabaseCount('v2_opening_batches', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_preview_returns_balanced_calculation_without_writing(): void
    {
        $this->accounts();
        $admin = $this->actor('admin');
        $prodId = $this->product('SEMEN-01', 'Material Sintetis Alpha');
        $custParty = $this->party('customer', 'CUST-001', 'Pelanggan Sintetis Alpha');
        $suppParty = $this->party('supplier', 'SUPP-001', 'Supplier Sintetis Alpha');

        $service = new OpeningBalances();

        $data = [
            'date' => '2026-10-30',
            'reference' => 'SALDO-AWAL-2026',
            'cash' => '5000000.00',
            'bank' => '20000000.00',
            'inventory' => [
                ['product_id' => $prodId, 'quantity' => '100.0000', 'unit_cost' => '65000.0000'],
            ],
            'receivables' => [
                ['party_id' => $custParty, 'number' => 'AR-OP-001', 'amount' => '3000000.00', 'due_date' => '2026-11-15'],
            ],
            'payables' => [
                ['party_id' => $suppParty, 'number' => 'AP-OP-001', 'amount' => '4500000.00', 'due_date' => '2026-11-20'],
            ],
        ];

        $preview = $service->preview($admin->id, $data);

        $this->assertSame('preview', $preview['status']);
        $this->assertSame('5000000.00', $preview['summary']['cash']);
        $this->assertSame('20000000.00', $preview['summary']['bank']);
        $this->assertSame('6500000.00', $preview['summary']['inventory']);
        $this->assertSame('3000000.00', $preview['summary']['receivables']);
        $this->assertSame('4500000.00', $preview['summary']['payables']);
        // Assets = 5M + 20M + 6.5M + 3M = 34.5M. Liabilities = 4.5M. Equity = 30M Credit
        $this->assertSame('30000000.00', $preview['summary']['equity']);
        $this->assertSame('credit', $preview['summary']['equity_direction']);

        // No database writes
        $this->assertDatabaseCount('v2_opening_batches', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('v2_invoices', 0);
    }

    public function test_post_creates_balanced_batch_journal_stock_and_invoices_atomically(): void
    {
        $this->accounts();
        $admin = $this->actor('admin');
        $prodId = $this->product('PASIR-01', 'Pasir Pasang 1 Truk');
        $custParty = $this->party('customer', 'CUST-002', 'Pelanggan Sintetis Beta');
        $suppParty = $this->party('supplier', 'SUPP-002', 'Supplier Sintetis Beta');

        $service = new OpeningBalances();

        $data = [
            'date' => '2026-10-30',
            'reference' => 'SALDO-AWAL-OCTOBER',
            'idempotency_key' => 'idemp-v2-opening-001',
            'cash' => '10000000.00',
            'bank' => '50000000.00',
            'inventory' => [
                ['product_id' => $prodId, 'quantity' => '10.0000', 'unit_cost' => '1500000.0000'],
            ],
            'receivables' => [
                ['party_id' => $custParty, 'number' => 'INV-AR-001', 'amount' => '5000000.00', 'due_date' => '2026-11-30'],
            ],
            'payables' => [
                ['party_id' => $suppParty, 'number' => 'BILL-AP-001', 'amount' => '8000000.00', 'due_date' => '2026-12-05'],
            ],
        ];

        $res = $service->post($admin->id, $data);

        $this->assertSame('posted', $res['status']);
        $this->assertDatabaseCount('v2_opening_batches', 1);

        // Check Batch Record
        $batch = DB::table('v2_opening_batches')->first();
        $this->assertSame(OpeningBalances::SINGLETON_KEY, $batch->singleton_key);
        $this->assertSame('SALDO-AWAL-OCTOBER', $batch->reference);
        $this->assertSame('10000000.00', (string) \App\Services\V2\Money::value((string) $batch->cash_amount, 2));
        $this->assertSame('50000000.00', (string) \App\Services\V2\Money::value((string) $batch->bank_amount, 2));
        $this->assertSame('15000000.00', (string) \App\Services\V2\Money::value((string) $batch->inventory_amount, 2));
        $this->assertSame('5000000.00', (string) \App\Services\V2\Money::value((string) $batch->ar_amount, 2));
        $this->assertSame('8000000.00', (string) \App\Services\V2\Money::value((string) $batch->ap_amount, 2));
        // Total Assets = 10M + 50M + 15M + 5M = 80M. Liabilities = 8M. Equity = 72M
        $this->assertSame('72000000.00', (string) \App\Services\V2\Money::value((string) $batch->equity_amount, 2));
        $this->assertSame('credit', $batch->equity_direction);

        // Check Balanced Journal
        $this->assertNotNull($batch->journal_entry_id);
        $journal = DB::table('journal_entries')->where('id', $batch->journal_entry_id)->first();
        $this->assertSame('v2_opening_balance', $journal->reference_type);

        $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $journal->id)->get();
        $totalDebit = '0.00';
        $totalCredit = '0.00';
        foreach ($lines as $line) {
            $totalDebit = (string) \Brick\Math\BigDecimal::of($totalDebit)->plus((string) $line->debit)->toScale(2);
            $totalCredit = (string) \Brick\Math\BigDecimal::of($totalCredit)->plus((string) $line->credit)->toScale(2);
        }
        $this->assertSame($totalDebit, $totalCredit);
        $this->assertSame('80000000.00', $totalDebit);

        // Check Product Stock & Movement
        $prod = DB::table('products')->where('id', $prodId)->first();
        $this->assertSame('10.0000', (string) \App\Services\V2\Money::value((string) $prod->stock_quantity, 4));
        $this->assertSame('1500000.0000', (string) \App\Services\V2\Money::value((string) $prod->cost_price, 4));

        $movement = DB::table('stock_movements')->where('product_id', $prodId)->first();
        $this->assertSame('v2_opening_balance', $movement->reference_type);
        $this->assertSame('10.0000', (string) \App\Services\V2\Money::value((string) $movement->quantity_delta, 4));
        $this->assertSame('10.0000', (string) \App\Services\V2\Money::value((string) $movement->balance_after, 4));

        // Check V2 Invoices created with NO sales/receipts (not fake sales)
        $this->assertDatabaseCount('v2_sales', 0);
        $this->assertDatabaseCount('v2_invoices', 2);

        $arInv = DB::table('v2_invoices')->where('number', 'INV-AR-001')->first();
        $this->assertSame('receivable', $arInv->direction);
        $this->assertNull($arInv->sale_id);
        $this->assertSame('5000000.00', (string) \App\Services\V2\Money::value((string) $arInv->amount, 2));
        $this->assertSame('0.00', (string) \App\Services\V2\Money::value((string) $arInv->paid, 2));
        $this->assertSame('open', $arInv->status);

        $apInv = DB::table('v2_invoices')->where('number', 'BILL-AP-001')->first();
        $this->assertSame('payable', $apInv->direction);
        $this->assertNull($apInv->receipt_id);
        $this->assertSame('8000000.00', (string) \App\Services\V2\Money::value((string) $apInv->amount, 2));
        $this->assertSame('open', $apInv->status);
    }

    public function test_idempotency_returns_same_batch_and_rejects_modified_payload(): void
    {
        $this->accounts();
        $admin = $this->actor('admin');
        $service = new OpeningBalances();

        $data = [
            'date' => '2026-10-30',
            'reference' => 'SALDO-AWAL-IDEMP',
            'idempotency_key' => 'idemp-key-repeatable-001',
            'cash' => '2000000.00',
            'bank' => '3000000.00',
        ];

        $first = $service->post($admin->id, $data);
        $this->assertSame('posted', $first['status']);

        // Repeated post with EXACT same payload returns idempotent_ok
        $second = $service->post($admin->id, $data);
        $this->assertSame('idempotent_ok', $second['status']);
        $this->assertSame($first['batch_id'], $second['batch_id']);

        // Modified payload with same or different key is rejected
        $modifiedData = $data;
        $modifiedData['cash'] = '9999999.00';

        $this->expectException(ValidationException::class);
        $service->post($admin->id, $modifiedData);
    }

    public function test_rejects_posting_if_legacy_journal_already_exists(): void
    {
        $this->accounts();
        $admin = $this->actor('admin');

        // Insert legacy journal entry
        $journalId = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'sale',
            'reference_id' => 99,
            'description' => 'Existing legacy journal',
            'transaction_date' => '2026-10-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            ['journal_entry_id' => $journalId, 'account_code' => '1101', 'account_name' => 'Kas', 'debit' => 1000, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $journalId, 'account_code' => '4101', 'account_name' => 'Pendapatan', 'debit' => 0, 'credit' => 1000, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $service = new OpeningBalances();
        $data = [
            'date' => '2026-10-30',
            'idempotency_key' => 'idemp-legacy-clash',
            'cash' => '1000000.00',
        ];

        $this->expectException(ValidationException::class);
        $service->post($admin->id, $data);
    }

    public function test_rejects_posting_if_product_stock_already_non_zero(): void
    {
        $this->accounts();
        $admin = $this->actor('admin');
        // Existing non-zero stock
        $this->product('EXIST-01', 'Batu Bata Merah', '500.0000', '1000.0000');

        $service = new OpeningBalances();
        $data = [
            'date' => '2026-10-30',
            'idempotency_key' => 'idemp-stock-clash',
            'cash' => '1000000.00',
        ];

        $this->expectException(ValidationException::class);
        $service->post($admin->id, $data);
    }
}
