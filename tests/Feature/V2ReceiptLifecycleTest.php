<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2ReceiptLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Receipt lifecycle tests require isolated SQLite :memory:.');
        }
    }

    /** @return array{admin:User, product:int, payload:array<string,mixed>} */
    private function fixture(): array
    {
        $admin = User::factory()->create();
        DB::table('v2_user_roles')->insert(['user_id' => $admin->id, 'role' => 'admin', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['1301', 'Persediaan', 'asset', 'debit'], ['2101', 'Utang', 'liability', 'credit']] as [$code, $name, $type, $normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore(['account_code' => $code, 'account_name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $supplier = DB::table('v2_parties')->insertGetId(['kind' => 'supplier', 'code' => 'SYN-SUP-LIFE', 'name' => 'Supplier Uji Final', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $product = DB::table('products')->insertGetId(['sku' => 'SYN-LIFE-1', 'name' => 'Semen Uji Final', 'unit' => 'sak', 'category' => 'Umum', 'cost_price' => '50000.0000', 'selling_price' => '60000.00', 'stock_quantity' => '10.0000', 'min_stock' => '1.0000', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('v2_product_suppliers')->insert(['product_id' => $product, 'supplier_id' => $supplier, 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()]);

        return ['admin' => $admin, 'product' => $product, 'payload' => ['supplier_id' => $supplier, 'receipt_date' => now()->toDateString(), 'idempotency_key' => 'syn-lifecycle-draft', 'items' => [['product_id' => $product, 'quantity' => '2', 'unit_cost' => '52000']]]];
    }

    public function test_http_save_edit_then_explicit_confirmation_posts_once_and_locks_the_document(): void
    {
        ['admin' => $admin, 'product' => $product, 'payload' => $payload] = $this->fixture();
        $saved = $this->actingAs($admin)->postJson(route('v2.receipts.store'), $payload)->assertOk()->assertJsonPath('status', 'draft');
        $draft = $saved->json('draft_id');
        $this->assertDatabaseCount('inventory_receipts', 0);
        $this->assertDatabaseCount('v2_invoices', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertEquals(10, DB::table('products')->where('id', $product)->value('stock_quantity'));
        $this->get(route('v2.index'))->assertOk()->assertSee('Draf penerimaan')->assertSee('Ubah draf')->assertSee('Konfirmasi dan kunci');

        $payload['items'][0]['quantity'] = '3';
        $payload['version'] = $saved->json('version');
        $edited = $this->postJson(route('v2.receipt-drafts.update', $draft), $payload)->assertOk();
        $version = $edited->json('version');
        $url = route('v2.receipt-drafts.confirm', $draft);
        $this->postJson($url, ['version' => $version])->assertUnprocessable()->assertJsonValidationErrors('confirm_final');
        $this->assertDatabaseCount('inventory_receipts', 0);
        $confirmed = $this->postJson($url, ['version' => $version, 'confirm_final' => true])->assertOk()->assertJsonPath('status', 'confirmed');
        $this->postJson($url, ['version' => $version, 'confirm_final' => true])->assertOk();
        $this->assertEquals(13, DB::table('products')->where('id', $product)->value('stock_quantity'));
        $this->assertDatabaseCount('inventory_receipts', 1);
        $this->assertDatabaseCount('v2_invoices', 1);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseHas('v2_invoices', ['receipt_id' => $confirmed->json('receipt_id'), 'amount' => 156000]);
        $this->postJson(route('v2.receipt-drafts.update', $draft), $payload)->assertUnprocessable();
        $this->postJson(route('v2.receipt-drafts.cancel', $draft), ['version' => $version, 'reason' => 'Coba batal final'])->assertUnprocessable();
        $this->postJson(route('v2.receipts.void', $confirmed->json('receipt_id')), ['reason' => 'Coba batal final', 'idempotency_key' => 'syn-bypass'])->assertUnprocessable();
        $this->get(route('v2.index'))->assertOk()->assertSee('Dikonfirmasi')->assertSee('Terkunci')->assertDontSee('Batalkan penerimaan');
    }

    public function test_cancelled_draft_is_retained_and_never_posts_stock_or_debt(): void
    {
        ['admin' => $admin, 'product' => $product, 'payload' => $payload] = $this->fixture();
        $saved = $this->actingAs($admin)->postJson(route('v2.receipts.store'), $payload)->assertOk();
        $draft = $saved->json('draft_id');
        $this->postJson(route('v2.receipt-drafts.cancel', $draft), ['version' => $saved->json('version'), 'reason' => 'Nota salah dipilih'])->assertOk()->assertJsonPath('status', 'cancelled');
        $this->postJson(route('v2.receipt-drafts.confirm', $draft), ['version' => $saved->json('version'), 'confirm_final' => true])->assertUnprocessable();
        $this->assertDatabaseHas('v2_receipt_drafts', ['id' => $draft, 'status' => 'cancelled', 'cancel_reason' => 'Nota salah dipilih']);
        $this->assertDatabaseCount('inventory_receipts', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('v2_invoices', 0);
        $this->assertEquals(10, DB::table('products')->where('id', $product)->value('stock_quantity'));
    }

    public function test_guests_and_cashiers_cannot_access_draft_mutations(): void
    {
        ['payload' => $payload] = $this->fixture();
        $this->postJson(route('v2.receipts.store'), $payload)->assertUnauthorized();
        $cashier = User::factory()->create();
        DB::table('v2_user_roles')->insert(['user_id' => $cashier->id, 'role' => 'cashier', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($cashier);
        $this->postJson(route('v2.receipts.store'), $payload)->assertForbidden();
        $this->postJson(route('v2.receipt-drafts.update', 1), $payload)->assertForbidden();
        $this->postJson(route('v2.receipt-drafts.confirm', 1), ['confirm_final' => true, 'version' => 1])->assertForbidden();
        $this->postJson(route('v2.receipt-drafts.cancel', 1), ['reason' => 'Coba batal', 'version' => 1])->assertForbidden();
        $this->assertDatabaseCount('v2_receipt_drafts', 0);
    }
}
