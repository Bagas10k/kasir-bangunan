<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DemoSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('RefreshDatabase is only permitted against isolated SQLite :memory:.');
        }
    }

    public function test_demo_setup_page_accessible_when_zero_users(): void
    {
        $this->assertEquals(0, User::count());

        $response = $this->get('/demo-setup');

        $response->assertStatus(200);
        $response->assertSee('Setup Akun Demo');
        $response->assertSee('name="_token"', false);
    }

    public function test_demo_user_can_be_created_and_authenticated(): void
    {
        $this->assertEquals(0, User::count());

        $response = $this->post('/demo-setup', [
            'name' => 'Pengguna Demo',
            'email' => 'demo.kasir@example.com',
            'password' => 'Kombinasi123',
        ]);

        $response->assertRedirect(route('pos.index'));
        $this->assertAuthenticated();
        $this->assertEquals(1, User::count());

        $user = User::first();
        $this->assertEquals('Pengguna Demo', $user->name);
        $this->assertEquals('demo.kasir@example.com', $user->email);
        $this->assertEquals($user->id, Auth::id());
    }

    public function test_demo_setup_is_one_time_only_and_forbids_subsequent_access(): void
    {
        User::create([
            'name' => 'Pengguna Lama',
            'email' => 'pengguna.lama@example.com',
            'password' => 'Rahasia123',
        ]);

        $this->assertEquals(1, User::count());

        // Akses kedua GET harus ditolak (403)
        $responseGet = $this->get('/demo-setup');
        $responseGet->assertStatus(403);

        // Akses kedua POST harus ditolak (403)
        $responsePost = $this->post('/demo-setup', [
            'name' => 'Pengguna Baru',
            'email' => 'pengguna.baru@example.com',
            'password' => 'RahasiaBaru123',
        ]);
        $responsePost->assertStatus(403);
    }

    public function test_validation_requires_required_fields_and_strong_password(): void
    {
        // Password lemah (< 8 karakter, tanpa angka)
        $response = $this->post('/demo-setup', [
            'name' => '',
            'email' => 'bukan-email',
            'password' => 'pendek',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertEquals(0, User::count());
    }

    public function test_gate_blocks_access_outside_demo_environment(): void
    {
        // Simulasikan lingkungan produksi di luar demo
        $this->app['env'] = 'production';
        Config::set('app.env', 'production');
        // Keep the test connection in disposable SQLite :memory:; only simulate a non-demo app environment.

        $response = $this->get('/demo-setup');
        $response->assertStatus(404);

        $responsePost = $this->withSession(['_token' => 'synthetic-demo-csrf'])
            ->post('/demo-setup', [
                '_token' => 'synthetic-demo-csrf',
                'name' => 'Pengguna Prod',
                'email' => 'prod@example.test',
                'password' => 'ProdPassword123',
            ]);
        $responsePost->assertStatus(404);
    }
}
