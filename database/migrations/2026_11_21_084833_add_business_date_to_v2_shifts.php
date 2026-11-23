<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('v2_shifts', function (Blueprint $table): void {
            $table->date('business_date')->nullable()->unique();
        });
    }

    public function down(): void
    {
        if (DB::table('v2_shifts')->whereNotNull('business_date')->exists()) {
            throw new RuntimeException('Tanggal kas harian harus dipertahankan; pembatalan migrasi ditolak.');
        }

        Schema::table('v2_shifts', function (Blueprint $table): void {
            $table->dropUnique(['business_date']);
            $table->dropColumn('business_date');
        });
    }
};
