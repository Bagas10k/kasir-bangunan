<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\DemoData;
use App\Services\V2\MasterInsights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Relasi yang dipakai layar master: produk per supplier, utang supplier, dan riwayat pemakaian mobil.
 */
class V2MasterInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('RefreshDatabase is only permitted against isolated SQLite :memory:.');
        }
    }

    private function admin(): User
    {
        $user = User::factory()->create(['name' => 'Admin Sintetis Relasi']);
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => 'admin', 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    public function test_supplier_rincian_lists_supplied_products_and_outstanding_debt(): void
    {
        $admin = $this->admin();
        app(DemoData::class)->seed($admin->id);
        $supplier = (int) DB::table('v2_parties')->where('code', 'SUP-0001')->value('id');

        // Utang: satu tagihan 1.000.000 dengan pembayaran terverifikasi 400.000, plus satu pengajuan 100.000.
        $invoice = DB::table('v2_invoices')->insertGetId([
            'direction' => 'payable', 'party_id' => $supplier, 'number' => 'BILL-SYN-1',
            'amount' => '1000000.00', 'paid' => '400000.00', 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_payments')->insert([
            ['invoice_id' => $invoice, 'amount' => '400000.00', 'method' => 'transfer', 'status' => 'posted', 'requested_by' => $admin->id, 'idempotency_key' => 'syn-pay-1', 'request_hash' => 'h1', 'created_at' => now(), 'updated_at' => now()],
            ['invoice_id' => $invoice, 'amount' => '100000.00', 'method' => 'transfer', 'status' => 'proposed', 'requested_by' => $admin->id, 'idempotency_key' => 'syn-pay-2', 'request_hash' => 'h2', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('v2_invoices')->insert(['direction' => 'payable', 'party_id' => $supplier, 'number' => 'SYN-VOIDED-SUPPLIER',
            'amount' => '900000.00', 'paid' => '0.00', 'status' => 'open', 'voided_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $info = app(MasterInsights::class)->suppliers([$supplier])[$supplier];

        $this->assertSame('600000.00', $info['outstanding'], 'Utang = tagihan dikurangi yang sudah dibayar.');
        $this->assertSame('1000000.00', $info['billed']);
        $this->assertSame('400000.00', $info['settled']);
        $this->assertSame('100000.00', $info['awaiting'], 'Pengajuan belum mengurangi utang.');
        $skus = array_column($info['products'], 'sku');
        $this->assertContains('PRD-0001', $skus, 'Produk yang menautkan supplier ini ikut terdaftar.');
        $this->assertTrue($info['products'][array_search('PRD-0001', $skus, true)]['primary'], 'Supplier utama ditandai.');
        $this->assertNotEmpty($info['history']);
        $this->assertSame(2, collect($info['history'])->where('type', 'payment')->count());
    }

    public function test_vehicle_rincian_lists_usage_with_driver_identity(): void
    {
        $admin = $this->admin();
        app(DemoData::class)->seed($admin->id);
        $vehicle = (int) DB::table('vehicles')->where('license_plate', 'B 9123 KJT')->value('id');
        $driver = (int) DB::table('v2_drivers')->where('code', 'SPR-0001')->value('id');
        $driverName = (string) DB::table('v2_drivers')->where('id', $driver)->value('name');

        $shift = DB::table('v2_shifts')->insertGetId([
            'user_id' => $admin->id, 'open_user_id' => $admin->id, 'opening_cash' => '0.00',
            'status' => 'open', 'source' => 'admin', 'opened_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $sale = DB::table('v2_sales')->insertGetId([
            'user_id' => $admin->id, 'shift_id' => $shift, 'number' => 'INV-SYN-VEH', 'subtotal' => '68500.00',
            'delivery_fee' => '0.00', 'total' => '68500.00', 'payment_method' => 'cash', 'tendered' => '68500.00',
            'change_amount' => '0.00', 'status' => 'paid', 'fulfillment' => 'delivery', 'idempotency_key' => 'syn-sale-veh',
            'request_hash' => 'hv', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('v2_deliveries')->insert([
            'sale_id' => $sale, 'number' => 'SJ-SYN-1', 'vehicle_id' => $vehicle, 'driver_id' => $driver,
            'driver_name' => $driverName, 'destination' => 'Proyek Sintetis', 'status' => 'sent',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $info = app(MasterInsights::class)->vehicles([$vehicle])[$vehicle];

        $this->assertSame(1, $info['deliveries']);
        $this->assertNotEmpty($info['history']);
        $this->assertSame($driverName, $info['history'][0]['driver'], 'Riwayat mobil menyebut sopirnya.');
        $this->assertSame('Proyek Sintetis', $info['history'][0]['destination']);
        $this->assertNotNull($info['last_used']);
    }

    public function test_customer_counts_sales_once_and_keeps_unverified_and_voided_debt_separate(): void
    {
        $admin = $this->admin();
        $customer = DB::table('v2_parties')->insertGetId(['kind' => 'customer', 'code' => 'SYN-C-METRIC', 'name' => 'Pelanggan Sintetis', 'is_active' => true]);
        $other = DB::table('v2_parties')->insertGetId(['kind' => 'customer', 'code' => 'SYN-C-OTHER', 'name' => 'Pelanggan Lain Sintetis', 'is_active' => true]);
        $shift = DB::table('v2_shifts')->insertGetId(['user_id' => $admin->id, 'source' => 'admin', 'opening_cash' => 0, 'status' => 'open', 'opened_at' => now()]);
        foreach ([1, 2] as $index) {
            DB::table('v2_sales')->insert(['number' => 'SYN-METRIC-'.$index, 'user_id' => $admin->id, 'shift_id' => $shift, 'customer_id' => $customer,
                'total' => '70.00', 'payment_method' => 'credit', 'status' => 'unpaid', 'fulfillment' => 'pickup', 'request_hash' => 'metric-'.$index, 'idempotency_key' => 'metric-'.$index]);
        }
        $invoice = DB::table('v2_invoices')->insertGetId(['party_id' => $customer, 'direction' => 'receivable', 'number' => 'SYN-AR-METRIC', 'amount' => '140.25', 'paid' => '40.10', 'status' => 'open']);
        DB::table('v2_invoices')->insert(['party_id' => $customer, 'direction' => 'receivable', 'number' => 'SYN-AR-VOID', 'amount' => '999.99', 'paid' => '0', 'status' => 'open', 'voided_at' => now()]);
        DB::table('v2_payments')->insert(['invoice_id' => $invoice, 'amount' => '7.05', 'method' => 'transfer', 'status' => 'pending', 'requested_by' => $admin->id, 'idempotency_key' => 'syn-metric-pending', 'request_hash' => 'h']);

        $info = app(MasterInsights::class)->customers([$customer, $other]);

        $this->assertSame(2, $info[$customer]['purchase_count']);
        $this->assertSame('100.15', $info[$customer]['outstanding']);
        $this->assertSame(0, $info[$other]['purchase_count']);
        $this->assertSame('0.00', $info[$other]['outstanding']);
    }

    public function test_master_screens_expose_relation_details_and_merged_staff_table(): void
    {
        $admin = $this->admin();
        app(DemoData::class)->seed($admin->id);
        $html = $this->actingAs($admin)->get(route('v2.index'))->assertOk()->getContent();

        $supplier = (int) DB::table('v2_parties')->where('code', 'SUP-0001')->value('id');
        $vehicle = (int) DB::table('vehicles')->where('license_plate', 'B 9123 KJT')->value('id');
        $this->assertStringContainsString('data-insight="supplier" data-insight-id="'.$supplier.'"', $html);
        $this->assertStringContainsString('data-insight="vehicle" data-insight-id="'.$vehicle.'"', $html);
        $this->assertStringContainsString('id="master-insights"', $html);

        // Karyawan dan sopir satu tabel: sopir karyawan tidak muncul dua kali sebagai baris terpisah.
        $start = strpos($html, 'id="table-master-staff"');
        $table = substr($html, $start, strpos($html, '</table>', $start) - $start);
        $this->assertSame(count(DemoData::EMPLOYEES) + 2, substr_count($table, '<tr data-role='), 'Karyawan ditambah sopir luar.');
        $this->assertStringContainsString('Karyawan · Sopir', $table);
        $this->assertStringContainsString('Sopir luar', $table);
        $this->assertStringNotContainsString('id="master-section-driver" aria-labelledby', substr($html, $start, 2000));
    }
}
