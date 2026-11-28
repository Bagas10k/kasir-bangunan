<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('v2_sales') && ! Schema::hasColumn('v2_sales', 'notes')) {
            Schema::table('v2_sales', function (Blueprint $table): void {
                $table->string('notes', 500)->nullable()->after('fulfillment');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('v2_sales') && Schema::hasColumn('v2_sales', 'notes')) {
            Schema::table('v2_sales', function (Blueprint $table): void {
                $table->dropColumn('notes');
            });
        }
    }
};
