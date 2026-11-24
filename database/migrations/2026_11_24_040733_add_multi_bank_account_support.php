<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('chart_of_accounts', 'is_bank_account')) {
            Schema::table('chart_of_accounts', function (Blueprint $table) {
                $table->boolean('is_bank_account')->default(false)->after('is_active');
            });

            DB::table('chart_of_accounts')
                ->where('account_code', '1102')
                ->update(['is_bank_account' => true]);
        }

        if (Schema::hasTable('v2_payments') && ! Schema::hasColumn('v2_payments', 'bank_account_code')) {
            Schema::table('v2_payments', function (Blueprint $table) {
                $table->string('bank_account_code', 20)->nullable()->after('method');
            });
        }

        if (Schema::hasTable('expenses') && ! Schema::hasColumn('expenses', 'bank_account_code')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->string('bank_account_code', 20)->nullable()->after('payment_method');
            });
        }

        if (Schema::hasTable('v2_driver_advances') && ! Schema::hasColumn('v2_driver_advances', 'bank_account_code')) {
            Schema::table('v2_driver_advances', function (Blueprint $table) {
                $table->string('bank_account_code', 20)->nullable()->after('method');
            });
        }

        if (Schema::hasTable('v2_driver_advance_settlements') && ! Schema::hasColumn('v2_driver_advance_settlements', 'refund_bank_account_code')) {
            Schema::table('v2_driver_advance_settlements', function (Blueprint $table) {
                $table->string('refund_bank_account_code', 20)->nullable()->after('refund_method');
            });
        }

        if (Schema::hasTable('v2_trips') && ! Schema::hasColumn('v2_trips', 'bank_account_code')) {
            Schema::table('v2_trips', function (Blueprint $table) {
                $table->string('bank_account_code', 20)->nullable()->after('payment_method');
            });
        }

        if (Schema::hasTable('v2_receipt_drafts') && ! Schema::hasColumn('v2_receipt_drafts', 'freight_bank_account_code')) {
            Schema::table('v2_receipt_drafts', function (Blueprint $table) {
                $table->string('freight_bank_account_code', 20)->nullable()->after('freight_account');
            });
        }

        if (Schema::hasTable('inventory_receipts') && ! Schema::hasColumn('inventory_receipts', 'freight_bank_account_code')) {
            Schema::table('inventory_receipts', function (Blueprint $table) {
                $table->string('freight_bank_account_code', 20)->nullable()->after('freight_account');
            });
        }
    }

    public function down(): void
    {
        foreach ([
            ['inventory_receipts', 'freight_bank_account_code'],
            ['v2_receipt_drafts', 'freight_bank_account_code'],
            ['v2_trips', 'bank_account_code'],
            ['v2_driver_advance_settlements', 'refund_bank_account_code'],
            ['v2_driver_advances', 'bank_account_code'],
            ['expenses', 'bank_account_code'],
            ['v2_payments', 'bank_account_code'],
            ['chart_of_accounts', 'is_bank_account'],
        ] as [$tableName, $column]) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, $column)) {
                Schema::table($tableName, function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
