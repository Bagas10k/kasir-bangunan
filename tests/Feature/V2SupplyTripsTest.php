<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\Ledger;
use App\Services\V2\SupplyTrips;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class V2SupplyTripsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('V2 supply/trip tests require isolated SQLite :memory:.');
        }
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert([
            'user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }

    private function accounts(): void
    {
        foreach ([
            ['1101', 'Kas', 'asset', 'debit'],
            ['1102', 'Bank', 'asset', 'debit'],
            ['1301', 'Persediaan', 'asset', 'debit'],
            ['2101', 'Utang Usaha', 'liability', 'credit'],
            ['3101', 'Modal', 'equity', 'credit'],
            ['4102', 'Pendapatan Ongkir', 'revenue', 'credit'],
            ['6101', 'Biaya Perjalanan', 'expense', 'debit'],
        ] as [$code, $name, $type, $normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore([
                'account_code' => $code, 'account_name' => $name, 'account_type' => $type,
                'normal_balance' => $normal, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function product(string $stock = '10.0000', string $cost = '60.1234'): int
    {
        return (int) DB::table('products')->insertGetId([
            'sku' => 'SYN-SUP-001', 'name' => 'Material Sintetis Beta', 'unit' => 'unit',
            'category' => 'Umum', 'cost_price' => $cost, 'selling_price' => '100.00',
            'stock_quantity' => $stock, 'min_stock' => '1.0000', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function supplier(): int
    {
        return (int) DB::table('v2_parties')->insertGetId([
            'kind' => 'supplier', 'code' => 'SYN-S-001', 'name' => 'Pemasok Sintetis Beta',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function vehicle(string $status = 'active'): int
    {
        return (int) DB::table('vehicles')->insertGetId([
            'license_plate' => 'SYN-0001-BETA', 'vehicle_type' => 'pickup', 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function openShift(int $adminId, string $opening = '500.00'): int
    {
        return (int) DB::table('v2_shifts')->insertGetId([
            'user_id' => $adminId, 'open_user_id' => $adminId, 'source' => 'admin',
            'opening_cash' => $opening, 'status' => 'open', 'opened_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function fund(string $account, string $amount): void
    {
        Ledger::post('test_funding', 1, 'Saldo awal uji', [
            ['account_code' => $account, 'debit' => $amount, 'credit' => '0.00'],
            ['account_code' => '3101', 'debit' => '0.00', 'credit' => $amount],
        ]);
    }

    private function receiptPayload(int $supplierId, int $productId, string $qty = '5', string $cost = '100'): array
    {
        return [
            'supplier_id' => $supplierId,
            'receipt_date' => '2026-10-30',
            'items' => [['product_id' => $productId, 'quantity' => $qty, 'unit_cost' => $cost]],
            'idempotency_key' => 'syn-receipt-0001',
        ];
    }

    private function assertForbidden(callable $call): void
    {
        try {
            $call();
            $this->fail('Aksi non-admin seharusnya ditolak 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    private function assertInvalid(callable $call): void
    {
        try {
            $call();
            $this->fail('Muatan tidak sah seharusnya ditolak validasi.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_non_admin_cannot_receive_or_post_trip(): void
    {
        $this->accounts();
        $cashier = $this->actor('cashier');
        $supplier = $this->supplier();
        $product = $this->product();
        $vehicle = $this->vehicle();
        $service = app(SupplyTrips::class);

        $this->assertForbidden(fn () => $service->receive($cashier->id, $this->receiptPayload($supplier, $product)));
        $this->assertForbidden(fn () => $service->trip($cashier->id, [
            'vehicle_id' => $vehicle, 'driver_name' => 'Sopir Sintetis', 'date' => '2026-10-30',
            'destination' => 'Zona Beta', 'fuel_cost' => '10.00', 'allowance' => '0.00',
            'payment_method' => 'transfer', 'idempotency_key' => 'syn-trip-cashier-01',
        ]));

        $this->assertDatabaseCount('inventory_receipts', 0);
        $this->assertDatabaseCount('v2_trips', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_credit_receipt_posts_moving_average_journal_and_payable_invoice(): void
    {
        $this->accounts();
        $admin = $this->actor('admin');
        $supplier = $this->supplier();
        $product = $this->product();

        $result = app(SupplyTrips::class)->receive($admin->id, $this->receiptPayload($supplier, $product));

        $receipt = DB::table('inventory_receipts')->where('id', $result['receipt_id'])->first();
        $this->assertSame('purchase', $receipt->receipt_type);
        $this->assertSame('2101', $receipt->credit_account);
        $this->assertSame('Pemasok Sintetis Beta', $receipt->supplier_name);
        $this->assertSame('500.00', number_format((float) $receipt->total_amount, 2, '.', ''));
        $this->assertNotNull($receipt->journal_entry_id);
        $this->assertNotNull($receipt->receipt_number);

        $item = DB::table('inventory_receipt_items')->where('inventory_receipt_id', $receipt->id)->first();
        $this->assertSame('5.0000', number_format((float) $item->quantity, 4, '.', ''));
        $this->assertSame('500.00', number_format((float) $item->subtotal, 2, '.', ''));

        // (10 * 60.1234 + 5 * 100) / 15 = 73.4156
        $this->assertSame('73.4156', number_format((float) DB::table('products')->where('id', $product)->value('cost_price'), 4, '.', ''));
        $this->assertSame('15.0000', number_format((float) DB::table('products')->where('id', $product)->value('stock_quantity'), 4, '.', ''));

        $movement = DB::table('stock_movements')->where('reference_type', 'v2_receipt')->first();
        $this->assertSame((int) $receipt->id, (int) $movement->reference_id);
        $this->assertSame('15.0000', number_format((float) $movement->balance_after, 4, '.', ''));

        $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $receipt->journal_entry_id)->orderBy('account_code')->get();
        $this->assertCount(2, $lines);
        $this->assertSame('1301', $lines[0]->account_code);
        $this->assertSame('500.00', number_format((float) $lines[0]->debit, 2, '.', ''));
        $this->assertSame('2101', $lines[1]->account_code);
        $this->assertSame('500.00', number_format((float) $lines[1]->credit, 2, '.', ''));

        $invoice = DB::table('v2_invoices')->where('id', $result['invoice_id'])->first();
        $this->assertSame('payable', $invoice->direction);
        $this->assertSame((int) $receipt->id, (int) $invoice->receipt_id);
        $this->assertSame($supplier, (int) $invoice->party_id);
        $this->assertSame('open', $invoice->status);
        $this->assertSame('500.00', number_format((float) $invoice->amount, 2, '.', ''));
        $this->assertSame(0.0, (float) $invoice->paid);
        $this->assertSame('500.00', $result['total']);
    }

    public function test_repeat_receipt_key_is_idempotent_and_different_payload_rejected(): void
    {
        $this->accounts();
        $admin = $this->actor('admin');
        $supplier = $this->supplier();
        $product = $this->product();
        $service = app(SupplyTrips::class);

        $first = $service->receive($admin->id, $this->receiptPayload($supplier, $product));
        $second = $service->receive($admin->id, $this->receiptPayload($supplier, $product));

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('inventory_receipts', 1);
        $this->assertDatabaseCount('inventory_receipt_items', 1);
        $this->assertDatabaseCount('v2_invoices', 1);
        $this->assertDatabaseCount('v2_requests', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame(1, DB::table('journal_entries')->where('reference_type', 'v2_receipt')->count());
        $this->assertSame(2, DB::table('journal_entry_lines')->where('journal_entry_id', DB::table('inventory_receipts')->value('journal_entry_id'))->count());
        $this->assertSame('15.0000', number_format((float) DB::table('products')->where('id', $product)->value('stock_quantity'), 4, '.', ''));

        $this->assertInvalid(fn () => $service->receive($admin->id, $this->receiptPayload($supplier, $product, '6')));
        $this->assertSame('15.0000', number_format((float) DB::table('products')->where('id', $product)->value('stock_quantity'), 4, '.', ''));
    }

    public function test_cash_trip_posts_expense_separately_from_delivery_revenue(): void
    {
        $this->accounts();
        $admin = $this->actor('admin');
        $vehicle = $this->vehicle();
        $this->fund('1101', '1000.00');
        $shiftId = $this->openShift($admin->id, '500.00');
        $service = app(SupplyTrips::class);

        $payload = [
            'vehicle_id' => $vehicle, 'driver_name' => 'Sopir Sintetis', 'date' => '2026-10-30',
            'destination' => 'Zona Beta', 'fuel_cost' => '100.00', 'allowance' => '50.00',
            'payment_method' => 'cash', 'idempotency_key' => 'syn-trip-cash-0001',
        ];

        $result = $service->trip($admin->id, $payload);
        $this->assertSame('150.00', $result['total']);

        $trip = DB::table('v2_trips')->where('id', $result['trip_id'])->first();
        $this->assertSame('posted', $trip->status);
        $this->assertSame('cash', $trip->payment_method);
        $this->assertSame('150.00', number_format((float) $trip->total_cost, 2, '.', ''));
        $this->assertSame((int) $admin->id, (int) $trip->requested_by);
        $this->assertSame((int) $result['journal_entry_id'], (int) $trip->journal_entry_id);

        $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $result['journal_entry_id'])->orderBy('account_code')->get();
        $this->assertCount(2, $lines);
        $this->assertSame('1101', $lines[0]->account_code);
        $this->assertSame('150.00', number_format((float) $lines[0]->credit, 2, '.', ''));
        $this->assertSame('6101', $lines[1]->account_code);
        $this->assertSame('150.00', number_format((float) $lines[1]->debit, 2, '.', ''));
        // Ongkir 4102 tidak ikut dijurnal pada beban perjalanan.
        $this->assertSame(0, DB::table('journal_entry_lines')->where('journal_entry_id', $result['journal_entry_id'])->where('account_code', '4102')->count());

        $movement = DB::table('v2_shift_movements')->where('reference_type', 'v2_trip')->first();
        $this->assertSame('out', $movement->direction);
        $this->assertSame('adjustment', $movement->kind);
        $this->assertSame((int) $shiftId, (int) $movement->shift_id);
        $this->assertStringContainsString('Biaya perjalanan', (string) $movement->note);
        $this->assertStringContainsString('bukan penyesuaian umum', (string) $movement->note);

        // Replay kunci sama: tidak menggandakan trip/jurnal/movement.
        $replay = $service->trip($admin->id, $payload);
        $this->assertSame($result, $replay);
        $this->assertDatabaseCount('v2_trips', 1);
        $this->assertDatabaseCount('v2_shift_movements', 1);

        // Laci 500 - 150 = 350; biaya 400 harus ditolak tanpa jejak.
        $this->assertInvalid(fn () => $service->trip($admin->id, array_merge($payload, [
            'fuel_cost' => '400.00', 'allowance' => '0.00', 'idempotency_key' => 'syn-trip-cash-0002',
        ])));
        $this->assertDatabaseCount('v2_trips', 1);
        $this->assertDatabaseCount('v2_shift_movements', 1);
    }

    public function test_transfer_trip_requires_bank_balance_and_writes_nothing_when_short(): void
    {
        $this->accounts();
        $admin = $this->actor('admin');
        $vehicle = $this->vehicle('maintenance');
        $service = app(SupplyTrips::class);

        $payload = [
            'vehicle_id' => $vehicle, 'driver_name' => 'Sopir Sintetis', 'date' => '2026-10-30',
            'destination' => 'Zona Gamma', 'fuel_cost' => '100.00', 'allowance' => '0.00',
            'payment_method' => 'transfer', 'idempotency_key' => 'syn-trip-bank-0001',
        ];

        // Kendaraan non-aktif ditolak.
        $this->assertInvalid(fn () => $service->trip($admin->id, $payload));

        DB::table('vehicles')->where('id', $vehicle)->update(['status' => 'active']);

        // Bank kosong: gagal tanpa jejak.
        $this->assertInvalid(fn () => $service->trip($admin->id, $payload));
        $this->assertDatabaseCount('v2_trips', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('v2_shift_movements', 0);

        $this->fund('1102', '100.00');
        $result = $service->trip($admin->id, $payload);
        $this->assertSame('100.00', $result['total']);

        $lines = DB::table('journal_entry_lines')->where('journal_entry_id', $result['journal_entry_id'])->orderBy('account_code')->get();
        $this->assertSame('1102', $lines[0]->account_code);
        $this->assertSame('100.00', number_format((float) $lines[0]->credit, 2, '.', ''));
        $this->assertSame('6101', $lines[1]->account_code);
        $this->assertDatabaseCount('v2_shift_movements', 0);
        $this->assertDatabaseCount('v2_trips', 1);
    }

    public function test_receipt_rejects_inactive_supplier_and_duplicate_product(): void
    {
        $this->accounts();
        $admin = $this->actor('admin');
        $supplier = $this->supplier();
        $product = $this->product();
        $service = app(SupplyTrips::class);

        DB::table('v2_parties')->where('id', $supplier)->update(['is_active' => false]);
        $this->assertInvalid(fn () => $service->receive($admin->id, $this->receiptPayload($supplier, $product)));

        DB::table('v2_parties')->where('id', $supplier)->update(['is_active' => true]);
        $payload = $this->receiptPayload($supplier, $product);
        $payload['idempotency_key'] = 'syn-receipt-0002';
        $payload['items'][] = ['product_id' => $product, 'quantity' => '1', 'unit_cost' => '100'];
        $this->assertInvalid(fn () => $service->receive($admin->id, $payload));

        $this->assertDatabaseCount('inventory_receipts', 0);
        $this->assertDatabaseCount('v2_requests', 0);
        $this->assertSame('10.0000', number_format((float) DB::table('products')->where('id', $product)->value('stock_quantity'), 4, '.', ''));
    }
}
