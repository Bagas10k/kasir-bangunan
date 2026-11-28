<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2SupplierActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Supplier activity tests require isolated SQLite :memory:.');
        }
    }

    private function fixture(): array
    {
        $this->travelTo(now()->setDate(2026, 11, 23)->setTime(12, 0));
        $admin = User::factory()->create();
        $cashier = User::factory()->create();
        foreach ([[$admin, 'admin'], [$cashier, 'cashier']] as [$user, $role]) {
            DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role]);
        }
        $supplier = DB::table('v2_parties')->insertGetId(['kind' => 'supplier', 'code' => 'SYN-S-ACT', 'name' => 'Supplier Sintetis Aktivitas', 'is_active' => true]);
        $other = DB::table('v2_parties')->insertGetId(['kind' => 'supplier', 'code' => 'SYN-S-OTHER', 'name' => 'Supplier Lain Sintetis', 'is_active' => true]);
        $invoices = [];
        for ($i = 1; $i <= 21; $i++) {
            $invoices[] = DB::table('v2_invoices')->insertGetId(['direction' => 'payable', 'party_id' => $supplier, 'number' => 'SYN-ACT-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'amount' => '100.25', 'paid' => '20.10', 'status' => 'open', 'created_at' => '2026-11-20 10:00:00']);
        }
        DB::table('v2_invoices')->insert(['direction' => 'payable', 'party_id' => $supplier, 'number' => 'SYN-OUTSIDE', 'amount' => '100.25', 'paid' => '20.10', 'status' => 'open', 'created_at' => '2026-10-01 10:00:00']);
        $otherInvoice = DB::table('v2_invoices')->insertGetId(['direction' => 'payable', 'party_id' => $other, 'number' => 'SYN-OTHER-PARTY', 'amount' => '999', 'paid' => '0', 'status' => 'open', 'created_at' => '2026-11-20 10:00:00']);
        $payment = DB::table('v2_payments')->insertGetId(['invoice_id' => $invoices[0], 'amount' => '20.10', 'method' => 'transfer', 'status' => 'posted', 'requested_by' => $admin->id, 'idempotency_key' => 'syn-act-posted', 'request_hash' => 'posted', 'created_at' => '2026-11-21 10:00:00']);
        DB::table('v2_payments')->insert(['invoice_id' => $invoices[0], 'amount' => '7.05', 'method' => 'transfer', 'status' => 'proposed', 'requested_by' => $admin->id, 'idempotency_key' => 'syn-act-proposed', 'request_hash' => 'proposed', 'created_at' => '2026-11-21 11:00:00']);

        return compact('admin', 'cashier', 'supplier', 'otherInvoice', 'payment');
    }

    public function test_supplier_popup_is_paginated_but_range_print_contains_every_entry(): void
    {
        $f = $this->fixture();
        $base = '/v2/suppliers/'.$f['supplier'];
        $range = '?from=2026-11-01&to=2026-11-23';
        $firstPage = $this->actingAs($f['admin'])->getJson($base.'/activity'.$range)->assertOk()
            ->assertJsonPath('total', 23)->assertJsonPath('last_page', 2)
            ->assertJsonPath('outstanding', '1763.30')->assertJsonPath('awaiting', '7.05');
        $this->assertStringContainsString('1–20 dari 23 aktivitas', $firstPage->json('html'));
        $this->assertStringContainsString('from=2026-11-01&amp;to=2026-11-23&amp;page=2', $firstPage->json('html'));
        $secondPage = $this->getJson($base.'/activity'.$range.'&page=2')->assertOk();
        $this->assertStringContainsString('21–23 dari 23 aktivitas', $secondPage->json('html'));
        $this->assertStringContainsString('Halaman 2 dari 2', $secondPage->json('html'));
        $this->assertStringContainsString('Sebelumnya', $secondPage->json('html'));
        $this->assertStringNotContainsString('Berikutnya', $secondPage->json('html'));
        $print = $this->get($base.'/statement?from=2026-11-01&to=2026-11-23&page=2')->assertOk()
            ->assertSee('SYN-ACT-01')->assertSee('SYN-ACT-21')->assertDontSee('SYN-OUTSIDE')->assertDontSee('SYN-OTHER-PARTY');
        $this->assertStringContainsString('no-store', $print->headers->get('Cache-Control'));
        $single = $this->get($base.'/statement?from=2026-11-01&to=2026-11-23&entry_type=payment&entry_id='.$f['payment'])->assertOk();
        $this->assertSame(1, $single->viewData('entries')->count());
        $this->get($base.'/statement?from=2026-11-01&to=2026-11-23&entry_type=invoice&entry_id='.$f['otherInvoice'])->assertNotFound();
    }

    public function test_activity_and_print_require_an_active_admin_and_valid_range(): void
    {
        $f = $this->fixture();
        foreach (['activity', 'statement'] as $action) {
            $path = '/v2/suppliers/'.$f['supplier'].'/'.$action;
            $this->getJson($path)->assertUnauthorized();
            $this->actingAs($f['cashier'])->getJson($path)->assertForbidden();
            $this->actingAs($f['admin'])->getJson($path.'?from=2026-11-23&to=2026-11-01')->assertUnprocessable();
            $this->getJson($path.'?from=2020-01-01&to=2026-11-01')->assertUnprocessable();
            $this->getJson($path.'?entry_type=invoice')->assertUnprocessable();
            $unassigned = User::factory()->create();
            $this->actingAs($unassigned)->getJson($path)->assertForbidden();
            $f['admin']->forceFill(['is_active' => false])->save();
            $this->actingAs($f['admin'])->getJson($path)->assertUnauthorized();
            $f['admin']->forceFill(['is_active' => true])->save();
            $this->app['auth']->forgetGuards();
        }
    }
}
