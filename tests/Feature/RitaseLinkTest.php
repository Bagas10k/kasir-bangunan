<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RitaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class RitaseLinkTest extends TestCase
{
    use RefreshDatabase;

    private int $vehicleId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startBooks();

        $this->vehicleId = DB::table('vehicles')->insertGetId([
            'license_plate' => 'B 9001 TEST',
            'vehicle_type' => 'truck',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSyntheticSale(string $invoiceNumber = 'INV-SYNTH-001'): int
    {
        return DB::table('sales')->insertGetId([
            'invoice_number' => $invoiceNumber,
            'customer_type' => 'contractor',
            'payment_method' => 'cash',
            'total_amount' => '1500000.00',
            'cogs_amount' => '1100000.00',
            'paid_amount' => '1500000.00',
            'change_amount' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_standalone_haulage_without_sale_id_persists_and_creates_journal_if_cost_positive(): void
    {
        $service = new RitaseService();

        $result = $service->recordRitase(
            vehicleId: $this->vehicleId,
            driverName: 'Synthetic Driver Alpha',
            destination: 'Proyek Wilayah Alpha',
            cargoSummary: 'Material Pasir 1 Rit',
            fuelCost: 150000.00,
            driverAllowance: 100000.00,
            saleId: null
        );

        $this->assertNull($result['sale_id']);
        $this->assertNotNull($result['journal_entry_id']);

        $this->assertDatabaseHas('ritases', [
            'id' => $result['ritase_id'],
            'vehicle_id' => $this->vehicleId,
            'sale_id' => null,
            'driver_name' => 'Synthetic Driver Alpha',
            'destination' => 'Proyek Wilayah Alpha',
            'total_cost' => '250000.00',
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'id' => $result['journal_entry_id'],
            'reference_type' => 'ritase',
            'reference_id' => $result['ritase_id'],
        ]);
    }

    public function test_linked_sale_resolves_invoice_number_and_persists_link(): void
    {
        $saleId = $this->createSyntheticSale('INV-SYNTH-2026-99');
        $service = new RitaseService();

        $result = $service->recordRitase(
            vehicleId: $this->vehicleId,
            driverName: 'Synthetic Driver Beta',
            destination: 'Proyek Wilayah Beta',
            cargoSummary: 'Semen 100 Sak',
            fuelCost: 200000.00,
            driverAllowance: 100000.00,
            saleId: $saleId
        );

        $this->assertEquals($saleId, $result['sale_id']);

        $this->assertDatabaseHas('ritases', [
            'id' => $result['ritase_id'],
            'sale_id' => $saleId,
            'driver_name' => 'Synthetic Driver Beta',
        ]);

        $joined = DB::table('ritases')
            ->leftJoin('sales', 'ritases.sale_id', '=', 'sales.id')
            ->where('ritases.id', $result['ritase_id'])
            ->select('ritases.*', 'sales.invoice_number')
            ->first();

        $this->assertNotNull($joined);
        $this->assertEquals('INV-SYNTH-2026-99', $joined->invoice_number);

        // Journal check
        $journal = DB::table('journal_entries')->where('id', $result['journal_entry_id'])->first();
        $this->assertNotNull($journal);
        $this->assertStringContainsString('INV-SYNTH-2026-99', $journal->description);
    }

    public function test_invalid_sale_id_rejected_with_no_database_write(): void
    {
        $service = new RitaseService();

        $initialCount = DB::table('ritases')->count();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sale not found for ID: 999999');

        try {
            $service->recordRitase(
                vehicleId: $this->vehicleId,
                driverName: 'Synthetic Driver Gamma',
                destination: 'Proyek Wilayah Gamma',
                cargoSummary: 'Batu Split',
                fuelCost: 50000.00,
                driverAllowance: 50000.00,
                saleId: 999999
            );
        } finally {
            $this->assertEquals($initialCount, DB::table('ritases')->count());
        }
    }

    public function test_zero_cost_trip_has_no_orphan_journal(): void
    {
        $saleId = $this->createSyntheticSale('INV-SYNTH-ZERO-01');
        $service = new RitaseService();

        $result = $service->recordRitase(
            vehicleId: $this->vehicleId,
            driverName: 'Synthetic Driver Delta',
            destination: 'Lokasi Inspeksi Dekat',
            cargoSummary: 'Muatan Kosong',
            fuelCost: 0,
            driverAllowance: 0,
            saleId: $saleId
        );

        $this->assertNull($result['journal_entry_id']);

        $this->assertDatabaseHas('ritases', [
            'id' => $result['ritase_id'],
            'total_cost' => '0.00',
            'journal_entry_id' => null,
        ]);

        $orphanJournals = DB::table('journal_entries')
            ->where('reference_type', 'ritase')
            ->where('reference_id', $result['ritase_id'])
            ->count();

        $this->assertEquals(0, $orphanJournals);
    }

    public function test_ritase_controller_web_flow_and_ui_resolution(): void
    {
        $user = User::factory()->create();
        $saleId = $this->createSyntheticSale('INV-WEB-UI-777');

        // GET index displays sales invoice in select option
        $response = $this->actingAs($user)->get(route('ritase.index'));
        $response->assertStatus(200);
        $response->assertSee('INV-WEB-UI-777');
        $response->assertSee('Kaitan Nota Penjualan');

        // POST valid store with sale_id
        $postResponse = $this->actingAs($user)->post(route('ritase.store'), [
            'vehicle_id' => $this->vehicleId,
            'sale_id' => $saleId,
            'driver_name' => 'Synthetic Driver Web',
            'destination' => 'Proyek Web Sukses',
            'cargoSummary' => 'Besi Beton',
            'fuel_cost' => '60000',
            'driver_allowance' => '40000',
        ]);

        $postResponse->assertRedirect(route('ritase.index'));
        $postResponse->assertSessionHas('success');

        // Verify index displays the linked invoice number
        $afterResponse = $this->actingAs($user)->get(route('ritase.index'));
        $afterResponse->assertStatus(200);
        $afterResponse->assertSee('Nota: INV-WEB-UI-777');

        // POST with invalid sale_id fails validation
        $invalidPostResponse = $this->actingAs($user)->post(route('ritase.store'), [
            'vehicle_id' => $this->vehicleId,
            'sale_id' => 888888,
            'driver_name' => 'Synthetic Driver Fail',
            'destination' => 'Destinasi Invalid',
        ]);
        $invalidPostResponse->assertSessionHasErrors('sale_id');
    }
}
