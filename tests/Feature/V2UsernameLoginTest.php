<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class V2UsernameLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_username_only_account_logs_in_with_normalized_identifier_and_remember(): void
    {
        $user = User::factory()->create(['name' => 'Operator Sintetis', 'username' => 'kasir01', 'email' => null])->refresh();
        $this->withSession(['url.intended' => '/products']);
        $oldSessionId = session()->getId();
        RateLimiter::hit('kasir01|127.0.0.1', 60);

        $response = $this->post('/login', ['email' => '  KaSiR01  ', 'password' => 'password', 'remember' => '1']);

        $response->assertRedirect('/');
        $response->assertSessionHas('v2_auth_stamp', [(int) $user->id, (int) $user->auth_version]);
        $response->assertSessionMissing('url.intended');
        $response->assertCookie(Auth::guard()->getRecallerName());
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertSame(0, RateLimiter::attempts('kasir01|127.0.0.1'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => null]);
    }

    #[TestWith(['  ADMIN  ', 'admin@kasirbangunan.test'])]
    #[TestWith(['  EXISTING@EXAMPLE.TEST  ', 'existing@example.test'])]
    public function test_legacy_identifiers_are_normalized(string $identifier, string $email): void
    {
        $user = User::factory()->create(['name' => 'Operator Sintetis', 'email' => $email]);

        $response = $this->post('/login', ['email' => $identifier, 'password' => 'password']);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    #[TestWith(['missing01', 'password', true])]
    #[TestWith(['kasir01', 'wrong-password', true])]
    #[TestWith(['kasir01', 'password', false])]
    public function test_failed_username_login_has_generic_error(string $identifier, string $password, bool $active): void
    {
        User::factory()->create(['name' => 'Operator Sintetis', 'username' => 'kasir01', 'email' => null, 'is_active' => $active]);

        $response = $this->post('/login', ['email' => $identifier, 'password' => $password]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email' => 'Nama pengguna atau kata sandi salah.']);
        $response->assertSessionMissing('_old_input.password');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_username_failures_share_normalized_rate_limit(): void
    {
        $this->freezeTime();
        RateLimiter::clear('missing01|127.0.0.1');
        foreach (['missing01', 'MISSING01', '  missing01  ', 'Missing01', ' missing01 '] as $identifier) {
            $this->post('/login', ['email' => $identifier, 'password' => 'wrong-password']);
        }

        $response = $this->post('/login', ['email' => ' MISSING01 ', 'password' => 'wrong-password']);

        $response->assertSessionHasErrors(['email' => 'Terlalu banyak percobaan login. Coba lagi dalam 60 detik.']);
        $this->assertGuest();
    }

    public function test_new_cashier_account_logs_in_and_is_restricted_to_cashier_workspace(): void
    {
        $admin = User::factory()->create();
        DB::table('v2_user_roles')->insert(['user_id' => $admin->id, 'role' => 'admin', 'created_at' => now(), 'updated_at' => now()]);
        $created = $this->actingAs($admin)->postJson(route('v2.accounts.store'), [
            'name' => 'Kasir Akses Sintetis', 'username' => 'kasir01', 'role' => 'cashier', 'is_active' => true,
            'password' => 'SyntheticLoginPass123!', 'password_confirmation' => 'SyntheticLoginPass123!',
        ])->assertCreated();
        $cashier = User::findOrFail($created->json('id'));
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login'), ['email' => 'Kasir01', 'password' => 'SyntheticLoginPass123!'])
            ->assertRedirect(route('pos.index'));
        $this->assertAuthenticatedAs($cashier);
        $workspace = $this->get(route('v2.index'))->assertOk();
        $this->assertStringContainsString('data-role="cashier"', $workspace->getContent());
        $this->getJson(route('v2.accounts.index'))->assertForbidden();
        $this->postJson(route('v2.accounts.store'), ['username' => 'adminbaru'])->assertForbidden();
    }

    public function test_login_form_shows_short_username_example(): void
    {
        $this->get('/login')->assertOk()->assertSee('contoh: kasir01 atau kasir@toko.com');
    }
}
