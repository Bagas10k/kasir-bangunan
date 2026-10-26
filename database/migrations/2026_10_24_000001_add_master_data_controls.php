<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('category', 50)->default('Umum')->after('unit');
            $table->decimal('min_stock', 19, 4)->default(0)->after('stock_quantity');
            $table->boolean('is_active')->default(true)->after('min_stock');
            $table->index(['is_active', 'category']);
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('normal_balance');
            $table->index(['is_active', 'account_type']);
        });
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'account_type']);
            $table->dropColumn('is_active');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'category']);
            $table->dropColumn(['category', 'min_stock', 'is_active']);
        });
    }
};
