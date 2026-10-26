<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ritases', function (Blueprint $table) {
            $table->foreignId('inventory_receipt_id')->nullable()->constrained('inventory_receipts')->nullOnDelete();
            $table->string('driver_name')->nullable()->change();
            $table->string('destination')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Refuse destructive rollback while pending trips have legitimate missing details.
        if (\Illuminate\Support\Facades\DB::table('ritases')->whereNull('driver_name')->orWhereNull('destination')->exists()) {
            throw new \RuntimeException('Complete pending ritase details before rolling back this schema.');
        }
        Schema::table('ritases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_receipt_id');
            $table->string('driver_name')->nullable(false)->change();
            $table->string('destination')->nullable(false)->change();
        });
    }
};
