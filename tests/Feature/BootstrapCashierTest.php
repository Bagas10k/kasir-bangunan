<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BootstrapCashierTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('BootstrapCashierTest requires disposable SQLite :memory:.');
        }
    }

    public function test_bootstrap_user_succeeds_with_synthetic_neutral_fixture_and_masked_secret(): void
    {
        $email = 'cashier.test@example.local';
        $name = 'Synthetic Cashier';
        $password = 'SafePass123!';

        $this->artisan('kasir:bootstrap-user', [
            '--email' => $email,
            '--name' => $name,
        ])
            ->expectsQuestion('Enter initial user password', $password)
            ->expectsOutput('Initial user created successfully.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('users', 1);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertSame($name, $user->name);

        // Assert bcrypt/argon verification succeeds
        $this->assertTrue(Hash::check($password, $user->password));

        // Assert password is not stored in plaintext
        $this->assertNotSame($password, $user->password);
    }

    public function test_bootstrap_user_fails_closed_when_options_are_missing(): void
    {
        $this->artisan('kasir:bootstrap-user')
            ->expectsOutput('Bootstrap refused: Explicit --email and --name must both be provided.')
            ->assertExitCode(1);

        $this->assertDatabaseEmpty('users');

        $this->artisan('kasir:bootstrap-user', ['--name' => 'Synthetic Only'])
            ->expectsOutput('Bootstrap refused: Explicit --email and --name must both be provided.')
            ->assertExitCode(1);

        $this->assertDatabaseEmpty('users');

        $this->artisan('kasir:bootstrap-user', ['--email' => 'cashier.test@example.local'])
            ->expectsOutput('Bootstrap refused: Explicit --email and --name must both be provided.')
            ->assertExitCode(1);

        $this->assertDatabaseEmpty('users');
    }

    public function test_bootstrap_user_refuses_second_invocation_and_asserts_no_writes(): void
    {
        $email1 = 'first.user@example.local';
        $name1 = 'First Cashier';
        $pass1 = 'FirstPass123!';

        $this->artisan('kasir:bootstrap-user', [
            '--email' => $email1,
            '--name' => $name1,
        ])
            ->expectsQuestion('Enter initial user password', $pass1)
            ->assertExitCode(0);

        $this->assertDatabaseCount('users', 1);

        // Second invocation must fail and make no writes
        $email2 = 'second.user@example.local';
        $name2 = 'Second Cashier';

        $this->artisan('kasir:bootstrap-user', [
            '--email' => $email2,
            '--name' => $name2,
        ])
            ->expectsOutput('Bootstrap refused: Users table is not empty. Cannot bootstrap initial user.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['email' => $email2]);
    }

    public function test_bootstrap_user_validates_password_strength_and_writes_nothing_on_failure(): void
    {
        // Weak password (< 8 chars or no numbers)
        $this->artisan('kasir:bootstrap-user', [
            '--email' => 'weak.user@example.local',
            '--name' => 'Weak User',
        ])
            ->expectsQuestion('Enter initial user password', 'short')
            ->assertExitCode(1);

        $this->assertDatabaseEmpty('users');
    }

    public function test_bootstrap_user_validates_email_format(): void
    {
        $this->artisan('kasir:bootstrap-user', [
            '--email' => 'not-an-email',
            '--name' => 'Invalid Email User',
        ])
            ->expectsQuestion('Enter initial user password', 'ValidPass123!')
            ->assertExitCode(1);

        $this->assertDatabaseEmpty('users');
    }

    public function test_bootstrap_refuses_when_production_environment_has_wrong_database(): void
    {
        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';
        Config::set('database.connections.sqlite.database', 'wrong_db_name');

        $this->artisan('kasir:bootstrap-user', [
            '--email' => 'prod.test@example.local',
            '--name' => 'Prod User',
        ])
            ->expectsOutput('Bootstrap refused: use the dedicated cashier database or disposable SQLite :memory: test.')
            ->assertExitCode(1);

        $this->assertDatabaseEmpty('users');

        $this->app['env'] = $originalEnv;
    }

    public function test_bootstrap_refuses_when_staging_environment_has_wrong_database(): void
    {
        $originalEnv = $this->app['env'];
        $this->app['env'] = 'staging';
        Config::set('database.connections.sqlite.database', 'wrong_staging_db');

        $this->artisan('kasir:bootstrap-user', [
            '--email' => 'staging.test@example.local',
            '--name' => 'Staging User',
        ])
            ->expectsOutput('Bootstrap refused: use the dedicated cashier database or disposable SQLite :memory: test.')
            ->assertExitCode(1);

        $this->assertDatabaseEmpty('users');

        $this->app['env'] = $originalEnv;
    }
}
