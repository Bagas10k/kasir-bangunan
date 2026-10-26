<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SubpathRoutingTest extends TestCase
{
    use RefreshDatabase;

    private string $csrfToken = 'test-subpath-csrf-token';

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('RefreshDatabase is only permitted against isolated SQLite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://example.test/kasir-bangunan',
            'session.path' => '/kasir-bangunan',
            'session.cookie' => 'kasir_bangunan_session',
        ]);

        // Re-boot AppServiceProvider under new configuration
        app(AppServiceProvider::class, ['app' => app()])->boot();
    }

    public function test_route_helpers_include_subpath_prefix(): void
    {
        $this->assertSame(
            'https://example.test/kasir-bangunan/login',
            route('login'),
            'route("login") must include subpath prefix'
        );

        $this->assertSame(
            'https://example.test/kasir-bangunan',
            route('pos.index'),
            'route("pos.index") must include subpath prefix'
        );
    }

    public function test_get_login_form_action_includes_subpath(): void
    {
        $response = $this->withServerVariables([
            'SCRIPT_NAME' => '/kasir-bangunan/index.php',
            'SCRIPT_FILENAME' => base_path('public/index.php'),
        ])->get(route('login'));

        $response->assertStatus(200);

        // Assert form action contains subpath login URL without dumping full CSRF HTML
        $expectedAction = 'https://example.test/kasir-bangunan/login';
        $content = $response->getContent();

        $this->assertTrue(
            str_contains($content, 'action="' . $expectedAction . '"'),
            'Login form action must point to subpath login route'
        );
    }

    public function test_session_cookie_is_scoped_to_subpath_and_custom_name(): void
    {
        $response = $this->withServerVariables([
            'SCRIPT_NAME' => '/kasir-bangunan/index.php',
            'SCRIPT_FILENAME' => base_path('public/index.php'),
        ])->get(route('login'));

        $response->assertStatus(200);

        $cookies = $response->headers->getCookies();
        $sessionCookie = null;
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === 'kasir_bangunan_session') {
                $sessionCookie = $cookie;
                break;
            }
        }

        $this->assertNotNull($sessionCookie, 'Session cookie kasir_bangunan_session must be present');
        $this->assertSame('/kasir-bangunan', $sessionCookie->getPath(), 'Session cookie path must be scoped to /kasir-bangunan');
    }

    public function test_successful_login_lands_under_subpath_prefix(): void
    {
        $syntheticPassword = 'SyntheticPassword123!';
        $user = User::factory()->create([
            'email' => 'synthetic.cashier@example.test',
            'password' => Hash::make($syntheticPassword),
        ]);

        $response = $this->withSession(['_token' => $this->csrfToken])
            ->withServerVariables([
                'SCRIPT_NAME' => '/kasir-bangunan/index.php',
                'SCRIPT_FILENAME' => base_path('public/index.php'),
            ])->post(route('login'), [
                '_token' => $this->csrfToken,
                'email' => 'synthetic.cashier@example.test',
                'password' => $syntheticPassword,
            ]);

        $response->assertRedirect(route('pos.index'));
        $this->assertSame(
            'https://example.test/kasir-bangunan',
            $response->headers->get('Location'),
            'Login redirect must land under subpath prefix'
        );
        $this->assertAuthenticatedAs($user);
    }

    public function test_successful_login_with_root_intended_does_not_escape_subpath(): void
    {
        $syntheticPassword = 'SyntheticPassword123!';
        $user = User::factory()->create([
            'email' => 'synthetic.intended@example.test',
            'password' => Hash::make($syntheticPassword),
        ]);

        // Simulate upstream receiving intended '/' or root before subpath was resolved
        $response = $this->withSession([
                '_token' => $this->csrfToken,
                'url.intended' => '/',
            ])
            ->withServerVariables([
                'SCRIPT_NAME' => '/kasir-bangunan/index.php',
                'SCRIPT_FILENAME' => base_path('public/index.php'),
            ])->post(route('login'), [
                '_token' => $this->csrfToken,
                'email' => 'synthetic.intended@example.test',
                'password' => $syntheticPassword,
            ]);

        $response->assertRedirect(route('pos.index'));
        $this->assertSame(
            'https://example.test/kasir-bangunan',
            $response->headers->get('Location'),
            'Login with intended root must not escape subpath'
        );
        $this->assertAuthenticatedAs($user);
    }

    public function test_successful_login_ignores_intended_url_outside_application_prefix(): void
    {
        $password = 'SyntheticPassword123!';
        $user = User::factory()->create([
            'email' => 'synthetic.external-intended@example.test',
            'password' => Hash::make($password),
        ]);

        $response = $this->withSession([
                '_token' => $this->csrfToken,
                'url.intended' => 'https://example.test/',
            ])
            ->withServerVariables([
                'SCRIPT_NAME' => '/kasir-bangunan/index.php',
                'SCRIPT_FILENAME' => base_path('public/index.php'),
            ])->post(route('login'), [
                '_token' => $this->csrfToken,
                'email' => $user->email,
                'password' => $password,
            ]);

        $response->assertRedirect(route('pos.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_redirects_to_subpath_login_route_without_escaping(): void
    {
        $response = $this->withSession(['_token' => $this->csrfToken])
            ->withServerVariables([
                'SCRIPT_NAME' => '/kasir-bangunan/index.php',
                'SCRIPT_FILENAME' => base_path('public/index.php'),
            ])->post(route('login'), [
                '_token' => $this->csrfToken,
                'email' => 'nonexistent.user@example.test',
                'password' => 'WrongPassword999!',
            ]);

        $response->assertRedirect(route('login'));
        $this->assertSame(
            'https://example.test/kasir-bangunan/login',
            $response->headers->get('Location'),
            'Invalid credentials redirect must stay strictly under subpath route'
        );
        $response->assertSessionHasErrors(['email']);
        $this->assertFalse(Auth::check(), 'No user should be authenticated on invalid login');
    }

    public function test_validation_failure_redirects_to_subpath_login_route_without_escaping(): void
    {
        $response = $this->withSession(['_token' => $this->csrfToken])
            ->withServerVariables([
                'SCRIPT_NAME' => '/kasir-bangunan/index.php',
                'SCRIPT_FILENAME' => base_path('public/index.php'),
            ])->post(route('login'), [
                '_token' => $this->csrfToken,
                'email' => 'invalid-email-format',
                'password' => '',
            ]);

        $response->assertRedirect(route('login'));
        $this->assertSame(
            'https://example.test/kasir-bangunan/login',
            $response->headers->get('Location'),
            'Validation failure redirect must stay strictly under subpath route'
        );
        $response->assertSessionHasErrors(['email', 'password']);
        $this->assertFalse(Auth::check(), 'No user should be authenticated on validation failure');
    }

    public function test_throttle_limit_redirects_to_subpath_login_route_without_escaping(): void
    {
        $email = 'throttled.user@example.test';
        $ip = '127.0.0.1';
        $throttleKey = mb_strtolower($email) . '|' . $ip;

        // Exhaust the 5 attempts
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($throttleKey, 60);
        }

        $response = $this->withSession(['_token' => $this->csrfToken])
            ->withServerVariables([
                'REMOTE_ADDR' => $ip,
                'SCRIPT_NAME' => '/kasir-bangunan/index.php',
                'SCRIPT_FILENAME' => base_path('public/index.php'),
            ])->post(route('login'), [
                '_token' => $this->csrfToken,
                'email' => $email,
                'password' => 'RandomPassword123!',
            ]);

        $response->assertRedirect(route('login'));
        $this->assertSame(
            'https://example.test/kasir-bangunan/login',
            $response->headers->get('Location'),
            'Throttled redirect must stay strictly under subpath route'
        );
        $response->assertSessionHasErrors(['email']);
        $this->assertFalse(Auth::check(), 'No user should be authenticated when throttled');
    }
}
