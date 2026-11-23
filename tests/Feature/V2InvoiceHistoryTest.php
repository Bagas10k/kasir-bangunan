<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\V2\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2InvoiceHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Invoice fixtures require isolated SQLite :memory:.');
        }
    }

    private function actor(string $role = 'cashier'): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function invoice(string $number, string $direction = 'receivable', string $paid = '0.00', array $extra = []): int
    {
        return DB::table('v2_invoices')->insertGetId(array_merge([
            'number' => $number, 'direction' => $direction, 'amount' => '100.00',
            'paid' => $paid, 'status' => $paid === '100.00' ? 'paid' : 'open',
            'created_at' => '2025-01-01 10:00:00', 'updated_at' => '2025-01-01 10:00:00',
        ], $extra));
    }

    public function test_default_list_is_unpaid_receivables_without_a_recent_record_cap(): void
    {
        $this->invoice('AR-OLDEST');
        for ($i = 1; $i <= 105; $i++) {
            $this->invoice('AR-'.sprintf('%03d', $i));
        }
        $this->invoice('AR-PAID', paid: '100.00');
        $this->invoice('AP-OPEN', 'payable');

        $this->actingAs($this->actor())->getJson(route('v2.invoices.index'))
            ->assertOk()->assertJsonPath('filters.invoice_direction', 'receivable')
            ->assertJsonPath('filters.invoice_status', 'unpaid')
            ->assertJsonPath('total', 106);
        $response = $this->getJson(route('v2.invoices.index', ['invoice_page' => 11]))->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('AR-OLDEST', $html);
        $this->assertStringNotContainsString('AR-PAID', $html);
        $this->assertStringNotContainsString('AP-OPEN', $html);
    }

    public function test_paid_supplier_invoice_retains_detail_and_complete_payment_history_but_no_payment_form(): void
    {
        $actor = $this->actor('admin');
        $party = DB::table('v2_parties')->insertGetId(['kind' => 'supplier', 'code' => 'SYN-SUP', 'name' => 'Supplier Sintetis']);
        $id = $this->invoice('AP-LUNAS', 'payable', '100.00', ['party_id' => $party, 'due_date' => '2025-02-01']);
        $other = $this->invoice('AR-OTHER');
        foreach ([[$id, 'posted', 'SYN-POSTED'], [$id, 'rejected', '<script>synthetic</script>'], [$other, 'posted', 'SYN-OTHER']] as [$invoice, $status, $reference]) {
            DB::table('v2_payments')->insert([
                'invoice_id' => $invoice, 'requested_by' => $actor->id, 'amount' => '100.00',
                'method' => 'transfer', 'status' => $status, 'reference' => $reference,
                'idempotency_key' => 'syn-'.$reference, 'request_hash' => hash('sha256', $reference),
                'created_at' => '2025-01-01 12:00:00', 'updated_at' => '2025-01-01 12:00:00',
            ]);
        }
        $response = $this->actingAs($actor)->getJson(route('v2.invoices.index', ['invoice_direction' => 'payable', 'invoice_status' => 'paid']))
            ->assertOk()->assertJsonPath('total', 1);
        $html = $response->json('html');
        foreach (['AP-LUNAS', 'Supplier Sintetis', 'Detail', 'SYN-POSTED', 'Ditolak', '&lt;script&gt;synthetic&lt;/script&gt;', '01/02/2025'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('SYN-OTHER', $html);
        $this->assertStringNotContainsString('form-collect', $html);
        $this->assertDatabaseCount('v2_payments', 3);
    }

    public function test_workspace_renders_separate_tabs_and_keeps_collection_guard_and_sources(): void
    {
        $id = $this->invoice('AP-PARTIAL', 'payable', '25.00');
        $response = $this->actingAs($this->actor())->get(route('v2.index', ['invoice_direction' => 'payable']))->assertOk();
        $response->assertSee('id="invoice-tabs"', false)
            ->assertSee('data-invoice-tab="receivable"', false)
            ->assertSee('data-invoice-tab="payable"', false)
            ->assertSee('id="invoice-filter-form"', false)
            ->assertSee('id="invoice-results"', false)
            ->assertSee('name="seen_payment_id"', false)
            ->assertSee('name="idempotency_key"', false)
            ->assertSee(route('v2.payments.store', ['id' => $id]), false)
            ->assertSee('Ajukan pembayaran')
            ->assertSee('value="main"', false)->assertSee('value="transfer"', false)->assertSee('value="cash"', false);
    }

    public function test_filters_compose_by_direction_status_name_and_date_without_using_sales_dates(): void
    {
        $party = DB::table('v2_parties')->insertGetId(['kind' => 'supplier', 'code' => 'SYN-FILTER', 'name' => 'Pemasok Sintetis Gamma']);
        $this->invoice('AP-MATCH', 'payable', '25.00', ['party_id' => $party, 'created_at' => '2025-03-02 23:59:59']);
        $this->invoice('AP-EARLIER', 'payable', extra: ['party_id' => $party]);
        $this->invoice('AR-SAME-NAME', extra: ['party_id' => $party, 'created_at' => '2025-03-02 12:00:00']);
        $response = $this->actingAs($this->actor())->getJson(route('v2.invoices.index', [
            'invoice_direction' => 'payable', 'invoice_search' => 'Gamma', 'invoice_start' => '2025-03-02',
            'invoice_end' => '2025-03-02', 'start_date' => '2026-11-21', 'end_date' => '2026-11-21',
        ]))->assertOk()->assertJsonPath('total', 1);
        $this->assertStringContainsString('AP-MATCH', $response->json('html'));
        $this->assertStringNotContainsString('AP-EARLIER', $response->json('html'));
    }

    public function test_all_statuses_include_paid_and_partial_invoices_with_server_pagination(): void
    {
        $this->invoice('AR-PAID', paid: '100.00');
        $this->invoice('AR-PARTIAL', paid: '25.00');
        $this->invoice('AR-OPEN');
        $response = $this->actingAs($this->actor())->getJson(route('v2.invoices.index', ['invoice_status' => 'all', 'invoice_per_page' => 25]))
            ->assertOk()->assertJsonPath('total', 3);
        $this->assertStringContainsString('AR-PAID', $response->json('html'));
        $this->assertStringContainsString('Dibayar sebagian', $response->json('html'));
    }

    public function test_invoice_endpoint_requires_an_assigned_role_and_rejects_invalid_filters(): void
    {
        $this->getJson(route('v2.invoices.index'))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson(route('v2.invoices.index'))->assertForbidden();
        $this->actingAs($this->actor());
        foreach ([
            ['invoice_direction' => ['payable']], ['invoice_direction' => 'invalid'],
            ['invoice_status' => 'pending'], ['invoice_search' => str_repeat('a', 101)],
            ['invoice_start' => '2025-02-30'], ['invoice_start' => '2025-03-01', 'invoice_end' => '2025-02-01'],
            ['invoice_per_page' => 1000], ['invoice_page' => 0],
        ] as $filters) {
            $this->getJson(route('v2.invoices.index', $filters))->assertUnprocessable();
        }
    }

    public function test_date_filter_accepts_an_end_date_without_a_start_date(): void
    {
        $this->invoice('AR-PAST');
        $this->actingAs($this->actor())->getJson(route('v2.invoices.index', ['invoice_end' => '2025-01-01']))
            ->assertOk()->assertJsonPath('total', 1);
    }

    public function test_pending_payments_remain_unpaid_and_the_duplicate_guard_ignores_rejections(): void
    {
        $actor = $this->actor();
        $id = $this->invoice('AR-PENDING');
        $lastSeen = null;
        foreach (['posted', 'pending', 'rejected'] as $status) {
            $payment = DB::table('v2_payments')->insertGetId([
                'invoice_id' => $id, 'requested_by' => $actor->id, 'amount' => '10.25',
                'method' => 'transfer', 'status' => $status, 'reference' => 'SYN-'.$status,
                'idempotency_key' => 'syn-'.$status, 'request_hash' => hash('sha256', $status),
                'created_at' => '2025-01-01 12:00:00', 'updated_at' => '2025-01-01 12:00:00',
            ]);
            if ($status === 'pending') {
                $lastSeen = $payment;
            }
        }
        DB::table('v2_invoices')->where('id', $id)->update(['paid' => '10.25']);
        $response = $this->actingAs($actor)->getJson(route('v2.invoices.index'))->assertOk()->assertJsonPath('total', 1);
        $html = $response->json('html');
        $this->assertStringContainsString('name="seen_payment_id" value="'.$lastSeen.'"', $html);
        $this->assertStringContainsString('data-remaining="89.75"', $html);
        $this->assertStringContainsString('Menunggu verifikasi', $html);
        $this->assertStringContainsString('SYN-rejected', $html);
        $this->assertStringContainsString('Catat pembayaran', $html);
        $this->assertSame('10.25', (string) Money::value((string) DB::table('v2_invoices')->where('id', $id)->value('paid')));
        $this->assertDatabaseCount('journal_entries', 0);
    }
}
