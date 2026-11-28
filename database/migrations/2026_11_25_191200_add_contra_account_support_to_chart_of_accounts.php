<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('chart_of_accounts', 'is_contra')) {
            Schema::table('chart_of_accounts', function (Blueprint $table) {
                $table->boolean('is_contra')->default(false)->after('normal_balance');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chart_of_accounts') && Schema::hasColumn('chart_of_accounts', 'is_contra')) {
            Schema::table('chart_of_accounts', function (Blueprint $table) {
                $table->dropColumn('is_contra');
            });
        }
    }
};
