<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('v2_shifts', 'carry_amount')) {
            Schema::table('v2_shifts', function (Blueprint $table) {
                $table->decimal('carry_amount', 19, 2)->default('0');
            });
        }
        if (!Schema::hasColumn('v2_shifts', 'admin_amount')) {
            Schema::table('v2_shifts', function (Blueprint $table) {
                $table->decimal('admin_amount', 19, 2)->default('0');
            });
        }
        if (!Schema::hasColumn('v2_shifts', 'reviewed_by')) {
            Schema::table('v2_shifts', function (Blueprint $table) {
                $table->unsignedBigInteger('reviewed_by')->nullable();
            });
        }
        if (!Schema::hasColumn('v2_shifts', 'reviewed_at')) {
            Schema::table('v2_shifts', function (Blueprint $table) {
                $table->timestamp('reviewed_at')->nullable();
            });
        }
        if (!Schema::hasColumn('v2_shifts', 'review_note')) {
            Schema::table('v2_shifts', function (Blueprint $table) {
                $table->text('review_note')->nullable();
            });
        }
        if (!Schema::hasColumn('v2_payments', 'resolution_note')) {
            Schema::table('v2_payments', function (Blueprint $table) {
                $table->text('resolution_note')->nullable();
            });
        }
        if (!Schema::hasColumn('v2_payments', 'resolved_by')) {
            Schema::table('v2_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('resolved_by')->nullable();
            });
        }
        if (!Schema::hasColumn('v2_payments', 'resolved_at')) {
            Schema::table('v2_payments', function (Blueprint $table) {
                $table->timestamp('resolved_at')->nullable();
            });
        }

        if (!in_array('v2_shifts_carried_from_id_unique', array_column(Schema::getIndexes('v2_shifts'), 'name'), true)) {
            Schema::table('v2_shifts', function (Blueprint $table) {
                $table->unique('carried_from_id');
            });
        }
    }

    public function down(): void
    {
        if (in_array('v2_shifts_carried_from_id_unique', array_column(Schema::getIndexes('v2_shifts'), 'name'), true)) {
            Schema::table('v2_shifts', function (Blueprint $table) {
                $table->dropUnique(['carried_from_id']);
            });
        }
        foreach (['carry_amount', 'admin_amount', 'reviewed_by', 'reviewed_at', 'review_note'] as $column) {
            if (Schema::hasColumn('v2_shifts', $column)) {
                Schema::table('v2_shifts', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
        foreach (['resolution_note', 'resolved_by', 'resolved_at'] as $column) {
            if (Schema::hasColumn('v2_payments', $column)) {
                Schema::table('v2_payments', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};