<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\Operations;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class V2TransferProofTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Transfer proof tests require SQLite :memory:.');
        }
    }

    private function actor(string $role = 'cashier'): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function invoice(): int
    {
        return DB::table('v2_invoices')->insertGetId(['number' => 'SYN-AR', 'direction' => 'receivable', 'amount' => '1000', 'paid' => '0', 'status' => 'open']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['amount' => '200.50', 'method' => 'transfer', 'idempotency_key' => 'synthetic-transfer-key', 'reference' => 'SYN-TF'], $overrides);
    }

    public function test_transfer_installment_requires_a_photo_before_creating_a_payment(): void
    {
        $id = $this->invoice();
        $this->actingAs($this->actor())->postJson(route('v2.payments.store', $id), $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('transfer_proof');
        $this->assertDatabaseCount('v2_payments', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_transfer_photo_is_private_bound_to_one_payment_and_does_not_settle_until_admin_approval(): void
    {
        Storage::fake('local');
        $cashier = $this->actor();
        $admin = $this->actor('admin');
        $id = $this->invoice();
        $photo = UploadedFile::fake()->image('synthetic-transfer.png');
        $response = $this->actingAs($cashier)->post(route('v2.payments.store', $id), $this->payload(['transfer_proof' => $photo]), ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('status', 'pending');
        $paymentId = $response->json('id');
        $proof = DB::table('v2_payment_proofs')->where('payment_id', $paymentId)->first();
        $this->assertNotNull($proof);
        Storage::disk('local')->assertExists($proof->path);
        $this->assertStringNotContainsString($proof->path, $response->getContent());
        $this->assertSame(0, (int) DB::table('v2_invoices')->where('id', $id)->value('paid'));
        $this->assertDatabaseCount('journal_entries', 0);
        $this->get(route('v2.payments.proof', $paymentId))->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'image/png')
            ->assertStreamedContent(file_get_contents($photo->getRealPath()));
        $fragment = $this->getJson(route('v2.invoices.index'))->assertOk()->json('html');
        $this->assertStringContainsString(route('v2.payments.proof', $paymentId), $fragment);
        $this->assertStringNotContainsString($proof->path, $fragment);
        $this->actingAs(User::factory()->create())->get(route('v2.payments.proof', $paymentId))->assertForbidden();
        foreach ([['1102', 'Bank', 'asset', 'debit'], ['1103', 'Piutang', 'asset', 'debit']] as [$code,$name,$type,$normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore(['account_code' => $code, 'account_name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'is_active' => true]);
        }
        $this->actingAs($admin)->postJson(route('v2.payments.approve', $paymentId))->assertOk();
        $this->assertDatabaseHas('v2_invoices', ['id' => $id, 'paid' => '200.50']);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_replay_keeps_one_photo_and_rejects_a_different_photo_without_overwriting(): void
    {
        Storage::fake('local');
        $id = $this->invoice();
        $this->actingAs($this->actor());
        $photo = UploadedFile::fake()->image('synthetic.png', 40, 40);
        $this->post(route('v2.payments.store', $id), $this->payload(['transfer_proof' => $photo]), ['Accept' => 'application/json'])->assertOk();
        $path = DB::table('v2_payment_proofs')->value('path');
        $this->post(route('v2.payments.store', $id), $this->payload(['transfer_proof' => $photo]), ['Accept' => 'application/json'])->assertOk();
        $this->assertDatabaseCount('v2_payments', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->post(route('v2.payments.store', $id), $this->payload(['transfer_proof' => UploadedFile::fake()->image('different.png', 60, 60)]), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('transfer_proof');
        $this->assertSame($path, DB::table('v2_payment_proofs')->value('path'));
        Storage::disk('local')->assertExists($path);
    }

    public function test_invalid_and_oversized_files_and_cash_attachments_are_rejected_without_writes(): void
    {
        Storage::fake('local');
        $id = $this->invoice();
        $this->actingAs($this->actor());
        foreach ([
            UploadedFile::fake()->createWithContent('fake.png', '<?php echo "not an image";'),
            UploadedFile::fake()->image('large.png')->size(5121),
            UploadedFile::fake()->createWithContent('fake.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ] as $photo) {
            $this->post(route('v2.payments.store', $id), $this->payload(['transfer_proof' => $photo]), ['Accept' => 'application/json'])
                ->assertUnprocessable()->assertJsonValidationErrors('transfer_proof');
        }
        $this->post(route('v2.payments.store', $id), $this->payload(['method' => 'cash', 'transfer_proof' => UploadedFile::fake()->image('cash.png')]), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('transfer_proof');
        $this->assertDatabaseCount('v2_payments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_rejected_overpayment_leaves_no_payment_or_orphaned_file(): void
    {
        Storage::fake('local');
        $id = $this->invoice();
        $this->actingAs($this->actor())->post(route('v2.payments.store', $id), $this->payload(['amount' => '1001', 'transfer_proof' => UploadedFile::fake()->image('over.png')]), ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('v2_payments', 0);
        $this->assertDatabaseCount('v2_payment_proofs', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_checkout_and_credit_transfer_deposit_require_their_own_photo(): void
    {
        Storage::fake('local');
        $admin = $this->actor('admin');
        $cashier = $this->actor();
        foreach ([['1101', 'Kas', 'asset', 'debit'], ['1102', 'Bank', 'asset', 'debit'], ['1103', 'Piutang', 'asset', 'debit'], ['1301', 'Persediaan', 'asset', 'debit'], ['4101', 'Penjualan', 'revenue', 'credit'], ['5101', 'HPP', 'expense', 'debit']] as [$code,$name,$type,$normal]) {
            DB::table('chart_of_accounts')->insertOrIgnore(['account_code' => $code, 'account_name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'is_active' => true]);
        }
        $product = DB::table('products')->insertGetId(['sku' => 'SYN-PROOF', 'name' => 'Material Sintetis', 'unit' => 'sak', 'category' => 'Umum', 'cost_price' => '60', 'selling_price' => '100', 'stock_quantity' => '10', 'is_active' => true]);
        $customer = DB::table('v2_parties')->insertGetId(['kind' => 'customer', 'code' => 'SYN-PROOF-C', 'name' => 'Pembeli Sintetis', 'is_active' => true]);
        app(Operations::class)->openShift($admin->id, '1000', 'admin');
        $data = ['items' => [['product_id' => $product, 'quantity' => '1']], 'delivery_fee' => '0', 'payment_method' => 'transfer', 'paid_amount' => '100', 'idempotency_key' => 'synthetic-initial-proof', 'fulfillment' => 'pickup', 'customer_id' => $customer];
        $this->actingAs($cashier)->postJson(route('v2.sales.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('transfer_proof');
        $response = $this->post(route('v2.sales.store'), $data + ['transfer_proof' => UploadedFile::fake()->image('initial.png')], ['Accept' => 'application/json'])->assertOk();
        $this->assertDatabaseHas('v2_invoices', ['id' => $response->json('invoice_id'), 'paid' => '0']);
        $this->assertDatabaseCount('v2_payment_proofs', 1);
        $data['payment_method'] = 'credit';
        $data['deposit_method'] = 'transfer';
        $data['paid_amount'] = '25';
        $data['idempotency_key'] = 'synthetic-deposit-proof';
        $this->postJson(route('v2.sales.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('transfer_proof');
        $this->post(route('v2.sales.store'), $data + ['transfer_proof' => UploadedFile::fake()->image('deposit.png')], ['Accept' => 'application/json'])->assertOk();
        $this->assertDatabaseCount('v2_payment_proofs', 2);
        $this->assertDatabaseCount('v2_sales', 2);
    }

    public function test_failed_private_storage_rolls_back_the_payment(): void
    {
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::set('local', $disk);
        $id = $this->invoice();
        $this->actingAs($this->actor())->post(route('v2.payments.store', $id), $this->payload(['transfer_proof' => UploadedFile::fake()->image('synthetic.png')]), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('message', 'Foto bukti belum dapat disimpan. Pembayaran belum dicatat.');
        $this->assertDatabaseCount('v2_payments', 0);
        $this->assertDatabaseCount('v2_payment_proofs', 0);
    }

    public function test_storage_exception_does_not_expose_private_paths_or_leave_a_payment(): void
    {
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andThrow(new \RuntimeException('private-storage-path-and-config'));
        Storage::set('local', $disk);
        $id = $this->invoice();
        $response = $this->actingAs($this->actor())->post(route('v2.payments.store', $id), $this->payload(['transfer_proof' => UploadedFile::fake()->image('synthetic.png')]), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('message', 'Foto bukti belum dapat disimpan. Pembayaran belum dicatat.');
        $this->assertStringNotContainsString('private-storage-path', $response->getContent());
        $this->assertDatabaseCount('v2_payments', 0);
    }

    public function test_proof_read_and_upload_require_active_roles_and_missing_proof_is_not_found(): void
    {
        Storage::fake('local');
        $id = $this->invoice();
        $this->getJson(route('v2.payments.proof', 1))->assertUnauthorized();
        $this->postJson(route('v2.payments.store', $id), $this->payload())->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson(route('v2.payments.store', $id), $this->payload())->assertForbidden();
        $cashier = $this->actor();
        $this->actingAs($cashier)->getJson(route('v2.payments.proof', 999))->assertNotFound();
        DB::table('users')->where('id', $cashier->id)->update(['is_active' => false]);
        $this->getJson(route('v2.payments.proof', 1))->assertUnauthorized();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
