<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarProfileRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Tests require isolated SQLite :memory:.');
        }
    }

    public function test_sidebar_has_no_fake_profile_but_keeps_logout(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('pos.index'));

        $response->assertOk();
        $response->assertDontSee('class="operator-card"', false);
        $response->assertDontSee('Operator Kasir');
        $response->assertDontSee('Terminal Aktif');
        $response->assertSee('class="sidebar-logout-btn"', false);
        $response->assertSee('action="'.route('logout').'"', false);
    }
}
