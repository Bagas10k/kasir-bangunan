<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Halaman keuangan dimuat ke panel workspace lewat fetch (?partial=1).
 * Potongan itu tidak boleh membawa cangkang (sidebar, header, aset) agar tidak dobel.
 */
class V2RemotePanelTest extends TestCase
{
    use RefreshDatabase;

    private const PANELS = ['journals.index', 'coa.index', 'reports.profit_loss', 'reports.balance_sheet', 'reports.cash_flow'];

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('RefreshDatabase is only permitted against isolated SQLite :memory:.');
        }
    }

    private function admin(): User
    {
        $user = User::factory()->create(['name' => 'Admin Sintetis Panel']);
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => 'admin', 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    public function test_partial_returns_content_only_and_full_page_still_works(): void
    {
        config(['v2.enabled' => true]);
        $admin = $this->admin();

        foreach (self::PANELS as $name) {
            $partial = $this->actingAs($admin)->get(route($name, ['partial' => 1]))->assertOk();
            $html = $partial->getContent();

            $partial->assertSee('data-remote-content', false)
                ->assertDontSee('<!DOCTYPE html>', false)
                ->assertDontSee('id="workspace-sidebar"', false)
                ->assertDontSee('class="nav sidebar-nav"', false)
                ->assertDontSee('fonts.googleapis.com', false);
            $this->assertStringNotContainsString('<body', $html, "Potongan {$name} tanpa cangkang.");

            // Halaman penuh tetap bisa dibuka langsung (tautan lama, bookmark, unduhan).
            $full = $this->actingAs($admin)->get(route($name))->assertOk();
            $full->assertSee('<!DOCTYPE html>', false)->assertSee('id="workspace-sidebar"', false);

            // Isi utamanya sama: potongan adalah bagian dari halaman penuh.
            $this->assertGreaterThan(400, strlen($html));
            $this->assertLessThan(strlen($full->getContent()), strlen($html));
        }
    }

    public function test_workspace_links_finance_menu_to_panels_without_inlining_them(): void
    {
        config(['v2.enabled' => true]);
        $admin = $this->admin();
        $workspace = $this->actingAs($admin)->get(route('pos.index'))->assertOk();
        $html = $workspace->getContent();

        foreach (['jurnal' => 'journals.index', 'neraca' => 'reports.balance_sheet', 'laba-rugi' => 'reports.profit_loss', 'arus-kas' => 'reports.cash_flow', 'bagan-akun' => 'coa.index'] as $task => $route) {
            $this->assertStringContainsString('data-task="'.$task.'" data-remote="'.route($route).'"', $html);
        }
        // Panel disiapkan saat kursor menyentuh menu, dan isinya disimpan agar kunjungan ulang tidak menunggu.
        $this->assertStringContainsString("addEventListener('pointerenter'", $html);
        $this->assertStringContainsString('remoteCache', $html);
        $this->assertStringContainsString('CACHE_FRESH_MS', $html);

        // Isi halaman keuangan tidak ikut dimuat di awal.
        $workspace->assertDontSee('id="coa-form"', false)
            ->assertDontSee('id="post-opening-form"', false)
            ->assertDontSee('ledger-filter-account', false);
    }

    public function test_cashier_cannot_reach_finance_panels(): void
    {
        config(['v2.enabled' => true]);
        $cashier = User::factory()->create(['name' => 'Kasir Sintetis Panel']);
        DB::table('v2_user_roles')->insert(['user_id' => $cashier->id, 'role' => 'cashier', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($cashier)->get(route('pos.index'))->assertOk()->assertDontSee('data-remote=', false);
        foreach (self::PANELS as $name) {
            $this->actingAs($cashier)->get(route($name, ['partial' => 1]))->assertForbidden();
        }
    }
}
