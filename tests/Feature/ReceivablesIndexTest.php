<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReceivablesIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('RefreshDatabase is only permitted against isolated SQLite :memory:.');
        }
    }

    private function authenticateUser(): User
    {
        $user = User::create([
            'name' => 'Operator Test',
            'email' => 'operator_' . uniqid() . '@example.test',
            'password' => bcrypt('password123'),
        ]);

        $this->actingAs($user);
        return $user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('receivables.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_receivables_index_and_read_only_notice(): void
    {
        $this->authenticateUser();

        $response = $this->get(route('receivables.index'));
        $response->assertStatus(200);
        $response->assertSee('Daftar Piutang');
        $response->assertSee('Halaman ini bersifat baca-saja. Saldo menunjukkan sisa tagihan saat checkout (total dikurangi pembayaran awal); pencatatan pelunasan setelah transaksi belum tersedia.');
    }

    public function test_partially_paid_credit_sale_is_visible_with_correct_remaining_balance(): void
    {
        $this->authenticateUser();

        DB::table('sales')->insert([
            'invoice_number' => 'INV-CR-PARTIAL-001',
            'customer_type' => 'retail',
            'payment_method' => 'credit',
            'cogs_amount' => '0.00',
            'change_amount' => '0.00',
            'total_amount' => '1500000.00',
            'paid_amount' => '500000.00',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $response = $this->get(route('receivables.index'));
        $response->assertStatus(200);
        $response->assertSee('INV-CR-PARTIAL-001');
        $response->assertSee('Retail');
        // Total: 1.500.000,00; Paid: 500.000,00; Remaining: 1.000.000,00
        $response->assertSee('1.500.000,00');
        $response->assertSee('500.000,00');
        $response->assertSee('1.000.000,00');
        $response->assertSee('Transaksi piutang');
        $response->assertSee('Total pembayaran awal');
        $response->assertSee('Total sisa piutang');
        $response->assertSee('>1</strong>', false);
    }

    public function test_cash_sale_and_fully_paid_credit_sale_are_hidden(): void
    {
        $this->authenticateUser();

        // 1. Partially paid credit (should be visible)
        DB::table('sales')->insert([
            'invoice_number' => 'INV-CR-OUTSTANDING',
            'customer_type' => 'contractor',
            'payment_method' => 'credit',
            'cogs_amount' => '0.00',
            'change_amount' => '0.00',
            'total_amount' => '2500000.00',
            'paid_amount' => '1000000.00',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // 2. Cash sale (should be hidden)
        DB::table('sales')->insert([
            'invoice_number' => 'INV-CASH-HIDDEN',
            'customer_type' => 'retail',
            'payment_method' => 'cash',
            'cogs_amount' => '0.00',
            'change_amount' => '0.00',
            'total_amount' => '500000.00',
            'paid_amount' => '500000.00',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // 3. Fully paid credit sale (total_amount == paid_amount, should be hidden)
        DB::table('sales')->insert([
            'invoice_number' => 'INV-CR-FULLYPAID-HIDDEN',
            'customer_type' => 'retail',
            'payment_method' => 'credit',
            'cogs_amount' => '0.00',
            'change_amount' => '0.00',
            'total_amount' => '800000.00',
            'paid_amount' => '800000.00',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $response = $this->get(route('receivables.index'));
        $response->assertStatus(200);
        $response->assertSee('INV-CR-OUTSTANDING');
        $response->assertDontSee('INV-CASH-HIDDEN');
        $response->assertDontSee('INV-CR-FULLYPAID-HIDDEN');
    }

    public function test_filters_by_invoice_number_customer_type_and_date_range(): void
    {
        $this->authenticateUser();

        DB::table('sales')->insert([
            'invoice_number' => 'INV-CR-TARGET',
            'customer_type' => 'contractor',
            'payment_method' => 'credit',
            'cogs_amount' => '0.00',
            'change_amount' => '0.00',
            'total_amount' => '3000000.00',
            'paid_amount' => '1000000.00',
            'created_at' => Carbon::create(2026, 10, 15, 10, 0, 0),
            'updated_at' => Carbon::create(2026, 10, 15, 10, 0, 0),
        ]);

        DB::table('sales')->insert([
            'invoice_number' => 'INV-CR-OTHER',
            'customer_type' => 'retail',
            'payment_method' => 'credit',
            'cogs_amount' => '0.00',
            'change_amount' => '0.00',
            'total_amount' => '4000000.00',
            'paid_amount' => '1000000.00',
            'created_at' => Carbon::create(2026, 10, 1, 10, 0, 0),
            'updated_at' => Carbon::create(2026, 10, 1, 10, 0, 0),
        ]);

        // Filter by invoice search
        $responseSearch = $this->get(route('receivables.index', ['search' => 'TARGET']));
        $responseSearch->assertSee('INV-CR-TARGET');
        $responseSearch->assertDontSee('INV-CR-OTHER');

        // Filter by customer_type
        $responseType = $this->get(route('receivables.index', ['customer_type' => 'contractor']));
        $responseType->assertSee('INV-CR-TARGET');
        $responseType->assertDontSee('INV-CR-OTHER');

        // Filter by date range
        $responseDate = $this->get(route('receivables.index', [
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-20',
        ]));
        $responseDate->assertSee('INV-CR-TARGET');
        $responseDate->assertDontSee('INV-CR-OTHER');
    }
}
