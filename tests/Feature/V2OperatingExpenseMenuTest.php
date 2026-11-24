<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class V2OperatingExpenseMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Menu pengeluaran hanya diuji pada SQLite :memory:.');
        }
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        DB::table('v2_user_roles')->insert(['user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    public function test_admin_menu_links_the_existing_operating_expense_page(): void
    {
        $admin = $this->actor('admin');

        $this->actingAs($admin)->get(route('v2.index'))->assertOk()
            ->assertSee('Pengeluaran')
            ->assertSee('data-remote="'.route('expenses.index').'"', false);
    }

    public function test_cashier_menu_does_not_expose_operating_expenses(): void
    {
        $cashier = $this->actor('cashier');

        $this->actingAs($cashier)->get(route('v2.index'))->assertOk()
            ->assertDontSee('data-remote="'.route('expenses.index').'"', false);
    }

    public function test_expense_page_can_render_as_a_workspace_partial_without_the_full_shell(): void
    {
        $admin = $this->actor('admin');

        $response = $this->actingAs($admin)->get(route('expenses.index', ['partial' => 1]))->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('data-remote-content', $html);
        $this->assertStringContainsString('Pengeluaran Operasional', $html);
        $this->assertStringContainsString('expenses-page', $html);
        $this->assertStringContainsString('exp-facts', $html);
        $this->assertStringContainsString('exp-table', $html);
        $this->assertStringNotContainsString('master-toolbar', $html);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $html);
        $this->assertStringNotContainsString('workspace-sidebar', $html);
    }
}
