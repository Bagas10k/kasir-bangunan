<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (!Schema::hasColumn('users', 'auth_version')) {
                $table->unsignedInteger('auth_version')->default(1);
            }
        });

        if (!Schema::hasTable('v2_account_audits')) {
            Schema::create('v2_account_audits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('actor_id')->constrained('users');
                $table->foreignId('target_id')->constrained('users');
                $table->string('action');
                $table->json('changes')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_account_audits');
        Schema::table('users', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('users', 'is_active')) {
                $drop[] = 'is_active';
            }
            if (Schema::hasColumn('users', 'auth_version')) {
                $drop[] = 'auth_version';
            }
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
