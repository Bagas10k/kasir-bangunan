<?php

namespace Tests;

use App\Models\User;
use App\Services\OpeningBalanceService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    // actingAs is an explicit test login: bind the session to that identity too.
    public function actingAs(Authenticatable $user, $guard = null)
    {
        $this->withSession(['v2_auth_stamp' => [(int) $user->getAuthIdentifier(), (int) ($user->auth_version ?? 1)]]);

        return parent::actingAs($user, $guard);
    }

    protected function storeShiftAdmin(): User
    {
        $admin = User::query()->join('v2_user_roles as r', 'r.user_id', '=', 'users.id')
            ->where('r.role', 'admin')->where('users.is_active', true)->select('users.*')->orderBy('users.id')->first();
        if ($admin !== null) {
            return $admin;
        }

        $admin = User::factory()->create(['name' => 'Admin Shift Sintetis', 'is_active' => true, 'auth_version' => 1]);
        DB::table('v2_user_roles')->insert(['user_id' => $admin->id, 'role' => 'admin', 'created_at' => now(), 'updated_at' => now()]);

        return $admin;
    }

    protected function startBooks(string $cash = '1000000000.00', string $bank = '1000000000.00'): void
    {
        $service = app(OpeningBalanceService::class);
        if ($service->current()?->status === 'posted') {
            return;
        }

        $service->saveDraft('2026-01-01', $cash, $bank);
        $service->post();
    }
}
