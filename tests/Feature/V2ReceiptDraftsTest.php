<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\ReceiptDrafts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class V2ReceiptDraftsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Receipt draft tests require SQLite :memory:.');
        }
    }

    private function fixture(): array
    {
        $this->travelTo('2026-11-16 12:00:00');
        $actor = User::factory()->create()->id;
        DB::table('v2_user_roles')->insert(['user_id' => $actor, 'role' => 'admin', 'created_at' => now(), 'updated_at' => now()]);
        $supplier = DB::table('v2_parties')->insertGetId(['kind' => 'supplier', 'code' => 'SYN-DRAFT', 'name' => 'Pemasok Sintetis', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $product = DB::table('products')->insertGetId(['sku' => 'SYN-DRAFT', 'name' => 'Material Sintetis', 'unit' => 'unit', 'category' => 'Umum', 'cost_price' => '60', 'selling_price' => '100', 'stock_quantity' => '10', 'min_stock' => '1', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('v2_product_suppliers')->insert(['supplier_id' => $supplier, 'product_id' => $product, 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['1301', 'Persediaan', 'asset', 'debit'], ['2101', 'Utang', 'liability', 'credit']] as [$code, $name, $type, $normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore(['account_code' => $code, 'account_name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        return [$actor, ['supplier_id' => $supplier, 'receipt_date' => '2026-11-16', 'idempotency_key' => 'draft-one', 'items' => [['product_id' => $product, 'quantity' => '2.5', 'unit_cost' => '100.125']]]];
    }

    public function test_edit_rejects_stale_version_and_changed_idempotency_payload(): void
    {
        [$actor, $payload] = $this->fixture();
        $service = app(ReceiptDrafts::class);
        $draft = $service->save($actor, $payload);
        $edited = $payload;
        $edited['version'] = 1;
        $edited['items'][0]['quantity'] = '3';
        $result = $service->save($actor, $edited, $draft['draft_id']);
        $this->assertSame(2, $result['version']);
        $this->assertSame('300.38', $result['total']);
        foreach ([fn () => $service->save($actor, $edited, $draft['draft_id']), fn () => $service->save($actor, $edited)] as $call) {
            try {
                $call();
                $this->fail('Expected validation failure.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('receipt_draft', $e->errors());
            }
        }
        $this->assertDatabaseHas('v2_receipt_drafts', ['id' => $draft['draft_id'], 'version' => 2]);
    }

    public function test_duplicate_nota_confirmation_rolls_back_and_can_be_explicitly_overridden(): void
    {
        [$actor, $payload] = $this->fixture();
        $payload['supplier_invoice_number'] = 'SYN-NOTA';
        $service = app(ReceiptDrafts::class);
        $first = $service->save($actor, $payload);
        $service->confirm($actor, $first['draft_id'], ['version' => 1]);
        $payload['idempotency_key'] = 'second';
        $second = $service->save($actor, $payload);
        try {
            $service->confirm($actor, $second['draft_id'], ['version' => 1]);
            $this->fail('Expected duplicate receipt warning.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('duplicate_receipt', $e->errors());
        }
        $this->assertDatabaseHas('v2_receipt_drafts', ['id' => $second['draft_id'], 'status' => 'draft', 'version' => 1, 'receipt_id' => null]);
        $this->assertDatabaseCount('inventory_receipts', 1);
        $result = $service->confirm($actor, $second['draft_id'], ['version' => 1, 'confirm_duplicate' => true]);
        $this->assertSame('confirmed', $result['status']);
        $this->assertDatabaseCount('inventory_receipts', 2);
    }

    public function test_missing_role_is_denied_for_all_actions(): void
    {
        [$actor, $payload] = $this->fixture();
        DB::table('v2_user_roles')->where('user_id', $actor)->delete();
        $service = app(ReceiptDrafts::class);
        foreach ([fn () => $service->save($actor, $payload), fn () => $service->confirm($actor, 1, ['version' => 1]), fn () => $service->cancel($actor, 1, ['version' => 1])] as $call) {
            try {
                $call();
                $this->fail('Expected 403.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }

    public function test_invalid_inactive_or_unlinked_data_is_rejected(): void
    {
        [$actor, $payload] = $this->fixture();
        $service = app(ReceiptDrafts::class);
        $cases = [array_replace($payload, ['receipt_date' => '2026-11-17']), array_replace($payload, ['receipt_date' => '2026-02-30']), array_replace($payload, ['items' => []]), array_replace($payload, ['items' => [$payload['items'][0], $payload['items'][0]]])];
        foreach (['0', '-1', '0.00001', 'abc'] as $quantity) {
            $bad = $payload;
            $bad['items'][0]['quantity'] = $quantity;
            $cases[] = $bad;
        }
        foreach ($cases as $bad) {
            try {
                $service->save($actor, $bad);
                $this->fail('Expected invalid payload.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('receipt_draft', $e->errors());
            }
        }
        DB::table('v2_parties')->update(['is_active' => false]);
        try {
            $service->save($actor, $payload);
            $this->fail('Expected inactive supplier rejection.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('receipt_draft', $e->errors());
        }
        DB::table('v2_parties')->update(['is_active' => true]);
        DB::table('v2_product_suppliers')->delete();
        $this->expectException(ValidationException::class);
        $service->save($actor, $payload);
    }

    public function test_confirm_posts_stored_snapshot_once(): void
    {
        [$actor, $payload] = $this->fixture();
        $service = app(ReceiptDrafts::class);
        $draft = $service->save($actor, $payload);

        $result = $service->confirm($actor, $draft['draft_id'], ['version' => 1, 'supplier_id' => 99999, 'items' => []]);

        $this->assertSame('confirmed', $result['status']);
        $this->assertSame(2, $result['version']);
        $this->assertSame('250.31', $result['total']);
        $this->assertSame($result, $service->confirm($actor, $draft['draft_id'], ['version' => 1]));
        foreach (['inventory_receipts', 'v2_invoices', 'journal_entries', 'stock_movements'] as $table) {
            $this->assertDatabaseCount($table, 1);
        }
        $this->assertDatabaseHas('products', ['id' => $payload['items'][0]['product_id'], 'stock_quantity' => '12.5']);
        $this->assertDatabaseHas('v2_receipt_drafts', ['id' => $draft['draft_id'], 'confirmed_by' => $actor, 'receipt_id' => $result['receipt_id']]);
    }

    public function test_cancel_has_no_effects_and_cannot_be_confirmed(): void
    {
        [$actor, $payload] = $this->fixture();
        $service = app(ReceiptDrafts::class);
        $draft = $service->save($actor, $payload);

        $result = $service->cancel($actor, $draft['draft_id'], ['version' => 1, 'reason' => 'Salah pemasok']);

        $this->assertSame('cancelled', $result['status']);
        $this->assertSame($result, $service->cancel($actor, $draft['draft_id'], ['version' => 1]));
        $this->assertDatabaseCount('inventory_receipts', 0);
        $this->expectException(ValidationException::class);
        $service->confirm($actor, $draft['draft_id'], ['version' => 2]);
    }

    public function test_save_is_idempotent_without_posting_effects(): void
    {
        [$actor, $payload] = $this->fixture();
        $before = DB::table('products')->first();
        $service = app(ReceiptDrafts::class);

        $draft = $service->save($actor, $payload);

        $this->assertSame(['draft_id' => $draft['draft_id'], 'status' => 'draft', 'version' => 1, 'total' => '250.31'], $draft);
        $this->assertSame($draft, $service->save($actor, $payload));
        $this->assertDatabaseCount('v2_receipt_drafts', 1);
        foreach (['inventory_receipts', 'stock_movements', 'v2_invoices', 'journal_entries'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertEquals($before, DB::table('products')->first());
    }
}
