<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('v2_shifts', function (Blueprint $table): void {
            $table->boolean('is_shared')->default(false);
            $table->unsignedTinyInteger('store_open_key')->nullable()->unique();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->index(['is_shared', 'status']);
        });

        $open = DB::table('v2_shifts')->where('status', 'open')->get(['id', 'user_id']);
        if ($open->count() === 1 && DB::table('v2_user_roles')->where('user_id', $open->first()->user_id)->where('role', 'admin')->exists()) {
            DB::table('v2_shifts')->where('id', $open->first()->id)->update(['is_shared' => true, 'store_open_key' => 1]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('v2_shifts')->where('is_shared', true)->exists()) {
            throw new RuntimeException('Riwayat shift toko bersama harus dipertahankan; pembatalan migrasi ditolak.');
        }

        Schema::table('v2_shifts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropUnique(['store_open_key']);
            $table->dropIndex(['is_shared', 'status']);
            $table->dropColumn(['is_shared', 'store_open_key']);
        });
    }
};
