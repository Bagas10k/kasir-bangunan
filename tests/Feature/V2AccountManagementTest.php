<?php

namespace Tests\Feature;

use App\Http\Controllers\V2\AccountController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class V2AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Account tests require SQLite :memory:.');
        }
    }

    public function test_inactive_login_and_existing_session_revocation(): void
    {
        $cashier = $this->createActor('cashier', false);
        $this->post(route('login'), ['email'=>$cashier->email,'password'=>'password12345'])->assertSessionHasErrors('email');
        $this->assertGuest();
        DB::table('users')->where('id',$cashier->id)->update(['is_active'=>true]);
        $this->post(route('login'), ['email'=>$cashier->email,'password'=>'password12345'])->assertRedirect(route('pos.index'));
        $this->getJson(route('v2.accounts.index'))->assertForbidden();
        DB::table('users')->where('id',$cashier->id)->update(['is_active'=>false]);
        $this->getJson(route('v2.index'))->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_changed_auth_version_rejects_stale_session_and_password_is_not_optional_on_create(): void
    {
        $admin = $this->createActor('admin');
        $this->actingAs($admin)->getJson(route('v2.accounts.index'))->assertOk();
        DB::table('users')->where('id',$admin->id)->increment('auth_version');
        $this->getJson(route('v2.accounts.index'))->assertUnauthorized();
        $this->assertGuest();
        $admin->refresh();
        $this->actingAs($admin)->postJson(route('v2.accounts.store'), ['name'=>'Kasir Sintetis','email'=>'missing@example.invalid','role'=>'cashier','is_active'=>true])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    private function createActor(string $role = 'admin', bool $isActive = true): User
    {
        $user = User::factory()->create([
            'email' => 'actor_' . uniqid() . '@example.invalid',
            'password' => Hash::make('password12345'),
            'is_active' => $isActive,
            'auth_version' => 1,
        ]);

        DB::table('v2_user_roles')->insert([
            'user_id' => $user->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    public function test_guest_is_forbidden_or_redirected(): void
    {
        $response = $this->getJson('/v2/accounts');
        $this->assertTrue(in_array($response->status(), [401, 403]));
    }

    public function test_cashier_cannot_access_account_management(): void
    {
        $cashier = $this->createActor('cashier');

        $this->actingAs($cashier)->getJson('/v2/accounts')->assertStatus(403);

        $this->actingAs($cashier)->postJson('/v2/accounts', [
            'name' => 'Cashier Test',
            'email' => 'newuser@example.invalid',
            'password' => 'secret123456',
            'password_confirmation' => 'secret123456',
            'role' => 'cashier',
            'is_active' => true,
        ])->assertStatus(403);
    }

    public function test_list_accounts_returns_safe_fields_only(): void
    {
        $admin = $this->createActor('admin');
        $this->createActor('cashier');

        $response = $this->actingAs($admin)->getJson('/v2/accounts');
        $response->assertStatus(200);

        $accounts = $response->json('accounts');
        $this->assertIsArray($accounts);
        $this->assertGreaterThanOrEqual(2, count($accounts));

        foreach ($accounts as $acc) {
            $this->assertArrayHasKey('id', $acc);
            $this->assertArrayHasKey('name', $acc);
            $this->assertArrayHasKey('email', $acc);
            $this->assertArrayHasKey('is_active', $acc);
            $this->assertArrayHasKey('role', $acc);
            $this->assertArrayHasKey('updated_at', $acc);
            $this->assertArrayNotHasKey('password', $acc);
            $this->assertArrayNotHasKey('remember_token', $acc);
            $this->assertArrayNotHasKey('auth_version', $acc);
        }
    }

    public function test_create_user_hashes_password_and_no_password_in_response(): void
    {
        $admin = $this->createActor('admin');

        $payload = [
            'name' => 'Kasir Sintetis Delta',
            'email' => 'john.cashier@example.invalid',
            'password' => 'validpassword123',
            'password_confirmation' => 'validpassword123',
            'role' => 'cashier',
            'is_active' => true,
        ];

        $response = $this->actingAs($admin)->postJson('/v2/accounts', $payload);
        $response->assertStatus(201);

        $data = $response->json();
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('auth_version', $data);
        $this->assertArrayNotHasKey('remember_token', $data);

        $createdUser = User::find($data['id']);
        $this->assertNotNull($createdUser);
        $this->assertTrue(Hash::check('validpassword123', $createdUser->password));
        $this->assertDatabaseHas('v2_user_roles', [
            'user_id' => $data['id'],
            'role' => 'cashier',
        ]);
    }

    public function test_validation_rules_duplicate_email_and_weak_password(): void
    {
        $admin = $this->createActor('admin');
        $existing = $this->createActor('cashier');

        // Weak password (<12 chars)
        $this->actingAs($admin)->postJson('/v2/accounts', [
            'name' => 'Bad PW',
            'email' => 'badpw@example.invalid',
            'password' => 'short',
            'password_confirmation' => 'short',
            'role' => 'cashier',
            'is_active' => true,
        ])->assertStatus(422);

        // Duplicate email
        $this->actingAs($admin)->postJson('/v2/accounts', [
            'name' => 'Duplicate',
            'email' => strtoupper($existing->email),
            'password' => 'validpassword123',
            'password_confirmation' => 'validpassword123',
            'role' => 'cashier',
            'is_active' => true,
        ])->assertStatus(422);

        // Invalid role
        $this->actingAs($admin)->postJson('/v2/accounts', [
            'name' => 'Invalid Role',
            'email' => 'invalidrole@example.invalid',
            'password' => 'validpassword123',
            'password_confirmation' => 'validpassword123',
            'role' => 'superadmin',
            'is_active' => true,
        ])->assertStatus(422);
    }

    public function test_update_role_and_active_status(): void
    {
        $admin = $this->createActor('admin');
        $target = $this->createActor('cashier');

        $response = $this->actingAs($admin)->putJson("/v2/accounts/{$target->id}", [
            'name' => 'Target Updated',
            'email' => 'target.updated@example.invalid',
            'role' => 'admin',
            'is_active' => false,
            'password' => '',
            'password_confirmation' => '',
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'Target Updated',
            'email' => 'target.updated@example.invalid',
            'is_active' => 0,
        ]);
        $this->assertDatabaseHas('v2_user_roles', [
            'user_id' => $target->id,
            'role' => 'admin',
        ]);
    }

    public function test_forbid_self_disable_and_demotion(): void
    {
        $admin = $this->createActor('admin');

        // Self demote
        $this->actingAs($admin)->putJson("/v2/accounts/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'cashier',
            'is_active' => true,
        ])->assertStatus(422);

        // Self disable
        $this->actingAs($admin)->putJson("/v2/accounts/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'admin',
            'is_active' => false,
        ])->assertStatus(422);
    }

    public function test_preserve_last_active_admin(): void
    {
        $admin1 = $this->createActor('admin');
        $admin2 = $this->createActor('admin');

        // Disable admin2 -> should succeed because admin1 is still active admin
        $this->actingAs($admin1)->putJson("/v2/accounts/{$admin2->id}", [
            'name' => $admin2->name,
            'email' => $admin2->email,
            'role' => 'admin',
            'is_active' => false,
        ])->assertStatus(200);

        // Now attempt to demote or disable admin1 -> should fail because admin1 is the last active admin
        $this->actingAs($admin1)->putJson("/v2/accounts/{$admin1->id}", [
            'name' => $admin1->name,
            'email' => $admin1->email,
            'role' => 'cashier',
            'is_active' => true,
        ])->assertStatus(422);
    }

    public function test_sessions_deletion_and_auth_version_increment_on_sensitive_change(): void
    {
        $admin = $this->createActor('admin');
        $target = $this->createActor('cashier');

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->insert([
                'id' => 'session_test_target_123',
                'user_id' => $target->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'payload' => 'dummy',
                'last_activity' => time(),
            ]);
        }

        $oldVersion = $target->auth_version;

        $response = $this->actingAs($admin)->putJson("/v2/accounts/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'cashier',
            'is_active' => $target->is_active,
            'password' => 'newpassword12345',
            'password_confirmation' => 'newpassword12345',
        ]);
        $response->assertStatus(200);

        $target->refresh();
        $this->assertEquals($oldVersion + 1, $target->auth_version);

        if (Schema::hasTable('sessions')) {
            $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        }
    }

    public function test_audit_logs_contain_no_passwords_or_pii_strings(): void
    {
        $admin = $this->createActor('admin');

        $this->actingAs($admin)->postJson('/v2/accounts', [
            'name' => 'Secret Person',
            'email' => 'secretperson@example.invalid',
            'password' => 'supersecretpass123',
            'password_confirmation' => 'supersecretpass123',
            'role' => 'cashier',
            'is_active' => true,
        ])->assertStatus(201);

        $audit = DB::table('v2_account_audits')->orderByDesc('id')->first();
        $this->assertNotNull($audit);

        $changesStr = (string) $audit->changes;
        $this->assertStringNotContainsString('Secret Person', $changesStr);
        $this->assertStringNotContainsString('secretperson@example.invalid', $changesStr);
        $this->assertStringNotContainsString('supersecretpass123', $changesStr);

        $changes = json_decode($changesStr, true);
        $this->assertArrayHasKey('changed_fields', $changes);
        $this->assertArrayHasKey('role', $changes);
        $this->assertArrayHasKey('is_active', $changes);
    }

    public function test_guard_downgrade_or_deactivation_with_open_shift(): void
    {
        $admin = $this->createActor('admin');
        $cashier = $this->createActor('cashier');

        // Create open shift for cashier
        DB::table('v2_shifts')->insert([
            'user_id' => $cashier->id,
            'status' => 'open',
            'opened_at' => now(),
            'opening_cash' => 100000,
            'source' => 'admin',
            'open_user_id' => $cashier->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempt deactivation -> should be rejected with 422
        $this->actingAs($admin)->putJson("/v2/accounts/{$cashier->id}", [
            'name' => $cashier->name,
            'email' => $cashier->email,
            'role' => 'cashier',
            'is_active' => false,
        ])->assertStatus(422);

        // Attempt role change -> should be rejected with 422
        $this->actingAs($admin)->putJson("/v2/accounts/{$cashier->id}", [
            'name' => $cashier->name,
            'email' => $cashier->email,
            'role' => 'admin',
            'is_active' => true,
        ])->assertStatus(422);

        // Password-only update while shift open -> should SUCCEED
        $this->actingAs($admin)->putJson("/v2/accounts/{$cashier->id}", [
            'name' => $cashier->name,
            'email' => $cashier->email,
            'role' => 'cashier',
            'is_active' => true,
            'password' => 'freshpassword123',
            'password_confirmation' => 'freshpassword123',
        ])->assertStatus(200);
    }
}
