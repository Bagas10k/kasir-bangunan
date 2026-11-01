<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\Operations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function __construct(private Operations $operations) {}

    public function index(Request $request): JsonResponse
    {
        $actorId = (int) $request->user()->id;
        $this->operations->requireRole($actorId, ['admin']);

        if (!$request->user()->is_active) {
            abort(403, 'Actor is inactive.');
        }

        $accounts = DB::table('users')
            ->leftJoin('v2_user_roles', 'users.id', '=', 'v2_user_roles.user_id')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.is_active',
                'v2_user_roles.role',
                'users.updated_at',
            ])
            ->orderBy('users.id')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'email' => (string) $row->email,
                    'is_active' => (bool) $row->is_active,
                    'role' => $row->role,
                    'updated_at' => (string) $row->updated_at,
                ];
            });

        return response()->json(['accounts' => $accounts])->header('Cache-Control','private, no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $actorId = (int) $request->user()->id;
        $this->operations->requireRole($actorId, ['admin']);

        if (!$request->user()->is_active) {
            abort(403, 'Actor is inactive.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255', 'email'],
            'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed'],
            'role' => ['required', 'string', 'in:admin,cashier'],
            'is_active' => ['required', 'boolean'],
        ]);

        $normalizedEmail = strtolower(trim($validated['email']));

        try {
            return DB::transaction(function () use ($actorId, $validated, $normalizedEmail) {
                // Lock ALL v2_user_roles rows in stable user_id order BEFORE fetching actor/target
                DB::table('v2_user_roles')->orderBy('user_id')->lockForUpdate()->get();

                // Recheck actor own role row under transaction lock
                $actor = DB::table('users')->where('id', $actorId)->lockForUpdate()->first();
                $actorRole = DB::table('v2_user_roles')->where('user_id', $actorId)->value('role');
                if (!$actor || !$actor->is_active || $actorRole !== 'admin') {
                    abort(403, 'Forbidden.');
                }

                $existingEmail = DB::table('users')->where('email', $normalizedEmail)->exists();
                if ($existingEmail) {
                    throw ValidationException::withMessages(['email' => ['Email login sudah digunakan.']]);
                }

                $now = now();
                $userId = DB::table('users')->insertGetId([
                    'name' => $validated['name'],
                    'email' => $normalizedEmail,
                    'password' => Hash::make($validated['password']),
                    'is_active' => (bool) $validated['is_active'],
                    'auth_version' => 1,
                    'remember_token' => Str::random(10),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('v2_user_roles')->insert([
                    'user_id' => $userId,
                    'role' => $validated['role'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Audit log: NO passwords/hash/secrets/PII
                DB::table('v2_account_audits')->insert([
                    'actor_id' => $actorId,
                    'target_id' => $userId,
                    'action' => 'create',
                    'changes' => json_encode([
                        'changed_fields' => ['name', 'email', 'role', 'is_active', 'password'],
                        'role' => $validated['role'],
                        'is_active' => (bool) $validated['is_active'],
                    ]),
                    'created_at' => $now,
                ]);

                $account = [
                    'id' => (int) $userId,
                    'name' => (string) $validated['name'],
                    'email' => (string) $normalizedEmail,
                    'is_active' => (bool) $validated['is_active'],
                    'role' => (string) $validated['role'],
                    'updated_at' => (string) $now,
                ];

                return response()->json($account, 201);
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE') || in_array((int) ($e->errorInfo[1] ?? 0), [1062, 2067], true)) {
                throw ValidationException::withMessages(['email' => ['Email login sudah digunakan.']]);
            }
            throw $e;
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        $actorId = (int) $request->user()->id;
        $this->operations->requireRole($actorId, ['admin']);

        if (!$request->user()->is_active) {
            abort(403, 'Actor is inactive.');
        }

        $targetId = (int) $id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255', 'email'],
            'role' => ['required', 'string', 'in:admin,cashier'],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:12', 'max:128', 'confirmed'],
        ]);

        $normalizedEmail = strtolower(trim($validated['email']));

        try {
            return DB::transaction(function () use ($actorId, $targetId, $validated, $normalizedEmail) {
                // Lock ALL v2_user_roles rows in stable user_id order BEFORE fetching actor/target
                DB::table('v2_user_roles')->orderBy('user_id')->lockForUpdate()->get();

                // Recheck actor own role row under transaction
                $actor = DB::table('users')->where('id', $actorId)->lockForUpdate()->first();
                $actorRole = DB::table('v2_user_roles')->where('user_id', $actorId)->value('role');
                if (!$actor || !$actor->is_active || $actorRole !== 'admin') {
                    abort(403, 'Forbidden.');
                }

                $target = DB::table('users')->where('id', $targetId)->lockForUpdate()->first();
                if (!$target) {
                    abort(404, 'User not found.');
                }

                $targetRole = DB::table('v2_user_roles')->where('user_id', $targetId)->lockForUpdate()->value('role') ?? 'cashier';

                $newRole = $validated['role'];
                $newIsActive = (bool) $validated['is_active'];

                // Rule: Forbid self disable / demotion
                if ($actorId === $targetId) {
                    if ($newRole !== 'admin' || !$newIsActive) {
                        abort(422, 'Akun sendiri tidak dapat dinonaktifkan atau dilepas dari Admin.');
                    }
                }

                // Rule: Always preserve at least 1 active admin
                $isTargetActiveAdmin = ($targetRole === 'admin' && (bool) $target->is_active);
                $willTargetBeActiveAdmin = ($newRole === 'admin' && $newIsActive);

                if ($isTargetActiveAdmin && !$willTargetBeActiveAdmin) {
                    $activeAdminCount = DB::table('v2_user_roles')
                        ->join('users', 'users.id', '=', 'v2_user_roles.user_id')
                        ->where('v2_user_roles.role', 'admin')
                        ->where('users.is_active', true)
                        ->count();

                    if ($activeAdminCount <= 1) {
                        abort(422, 'Admin aktif terakhir harus dipertahankan.');
                    }
                }

                // Rule: Guard downgrade/deactivation while target open v2_shifts to avoid stranded shift
                $hasOpenShift = DB::table('v2_shifts')
                    ->where('user_id', $targetId)
                    ->where('status', 'open')
                    ->exists();

                if ($hasOpenShift) {
                    $isRoleChange = ($targetRole !== $newRole);
                    $isDeactivation = ((bool) $target->is_active && !$newIsActive);
                    if ($isRoleChange || $isDeactivation) {
                        abort(422, 'Tutup shift akun ini dahulu sebelum mengganti role atau menonaktifkannya.');
                    }
                }

                // Check duplicate email for other users
                $emailTaken = DB::table('users')
                    ->where('email', $normalizedEmail)
                    ->where('id', '!=', $targetId)
                    ->exists();
                if ($emailTaken) {
                    throw ValidationException::withMessages(['email' => ['Email login sudah digunakan.']]);
                }

                // Track changes
                $changedFields = [];
                if ($target->name !== $validated['name']) {
                    $changedFields[] = 'name';
                }
                if ($target->email !== $normalizedEmail) {
                    $changedFields[] = 'email';
                }
                $roleChanged = ($targetRole !== $newRole);
                if ($roleChanged) {
                    $changedFields[] = 'role';
                }
                $statusChanged = ((bool) $target->is_active !== $newIsActive);
                if ($statusChanged) {
                    $changedFields[] = 'is_active';
                }
                $passwordChanged = !empty($validated['password']);
                if ($passwordChanged) {
                    $changedFields[] = 'password';
                }

                $now = now();
                $userUpdates = [
                    'name' => $validated['name'],
                    'email' => $normalizedEmail,
                    'is_active' => $newIsActive,
                    'updated_at' => $now,
                ];

                if ($passwordChanged) {
                    $userUpdates['password'] = Hash::make($validated['password']);
                }

                // On change role/status/password increment auth_version, rotate remember_token, delete sessions
                if ($roleChanged || $statusChanged || $passwordChanged) {
                    $userUpdates['auth_version'] = (int) $target->auth_version + 1;
                    $userUpdates['remember_token'] = Str::random(60);

                    if (Schema::hasTable('sessions')) {
                        DB::table('sessions')->where('user_id', $targetId)->delete();
                    }
                }

                DB::table('users')->where('id', $targetId)->update($userUpdates);

                DB::table('v2_user_roles')->updateOrInsert(
                    ['user_id' => $targetId],
                    ['role' => $newRole, 'updated_at' => $now, 'created_at' => $now]
                );

                // Audit log: NO passwords/hash/secrets/PII in audit details
                $auditChanges = [
                    'changed_fields' => $changedFields,
                ];
                if ($roleChanged) {
                    $auditChanges['old_role'] = $targetRole;
                    $auditChanges['new_role'] = $newRole;
                }
                if ($statusChanged) {
                    $auditChanges['old_is_active'] = (bool) $target->is_active;
                    $auditChanges['new_is_active'] = $newIsActive;
                }

                DB::table('v2_account_audits')->insert([
                    'actor_id' => $actorId,
                    'target_id' => $targetId,
                    'action' => 'update',
                    'changes' => json_encode($auditChanges),
                    'created_at' => $now,
                ]);

                $updated = [
                    'id' => $targetId,
                    'name' => (string) $validated['name'],
                    'email' => (string) $normalizedEmail,
                    'is_active' => $newIsActive,
                    'role' => (string) $newRole,
                    'updated_at' => (string) $now,
                ];

                return response()->json($updated);
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE') || in_array((int) ($e->errorInfo[1] ?? 0), [1062, 2067], true)) {
                throw ValidationException::withMessages(['email' => ['Email login sudah digunakan.']]);
            }
            throw $e;
        }
    }
}
