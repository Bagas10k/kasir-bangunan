<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\User;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_requests_are_denied_and_redirected_to_login(): void
    {
        $protectedRoutes = [
            ['GET', '/'],
            ['POST', '/pos/checkout'],
            ['GET', '/pos/receipt/1'],
            ['GET', '/products'],
            ['POST', '/products'],
            ['GET', '/stock-opname'],
            ['POST', '/stock-opname'],
            ['GET', '/expenses'],
            ['POST', '/expenses'],
            ['GET', '/ritase'],
            ['POST', '/ritase'],
            ['GET', '/reports/profit-loss'],
            ['GET', '/reports/balance-sheet'],
        ];

        foreach ($protectedRoutes as [$method, $uri]) {
            $response = $this->call($method, $uri);
            $response->assertStatus(302);
            $response->assertRedirect('/login');
        }
    }

    public function test_login_page_renders_clean_form(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('LOGIN KASIR');
        $response->assertSee('Email');
        $response->assertSee('Password');
    }

    public function test_authenticated_synthetic_user_can_access_protected_routes(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');
        $response->assertStatus(200);

        $productResponse = $this->actingAs($user)->get('/products');
        $productResponse->assertStatus(200);

        $reportResponse = $this->actingAs($user)->get('/reports/profit-loss');
        $reportResponse->assertStatus(200);
    }

    public function test_authenticated_user_can_create_product_with_zero_initial_stock(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/products', [
            'sku' => 'SKU-NEUTRAL-99',
            'name' => 'Semen Standar 50kg',
            'unit' => 'zak',
            'cost_price' => '55000.00',
            'selling_price' => '65000.00',
        ]);

        $this->assertDatabaseHas('products', [
            'sku' => 'SKU-NEUTRAL-99',
            'stock_quantity' => 0.0000,
        ]);
    }

    public function test_login_authenticates_synthetic_user_and_regenerates_session(): void
    {
        $password = 'TestSecret123!';
        $user = User::factory()->create([
            'email' => 'kasir.synthetic@example.local',
            'password' => Hash::make($password),
        ]);

        $response = $this->post('/login', [
            'email' => 'kasir.synthetic@example.local',
            'password' => $password,
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rate_limiting_triggers_after_repeated_failures(): void
    {
        RateLimiter::clear('kasir.fail@example.local|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'kasir.fail@example.local',
                'password' => 'wrong-pass',
            ]);
        }

        $response = $this->post('/login', [
            'email' => 'kasir.fail@example.local',
            'password' => 'wrong-pass',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_logout_invalidates_session_and_redirects(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');
        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_demo_login_authenticates_and_redirects_to_pos(): void
    {
        $response = $this->post('/login/demo');
        $response->assertRedirect(route('pos.index'));
        $this->assertAuthenticated();
    }
}
