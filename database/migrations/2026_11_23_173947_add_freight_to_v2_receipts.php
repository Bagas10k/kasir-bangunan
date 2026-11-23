<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['v2_receipt_drafts', 'inventory_receipts'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->decimal('freight_amount', 19, 2)->default(0);
                $table->string('freight_method', 10)->nullable();
                $table->string('freight_account', 20)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['v2_receipt_drafts', 'inventory_receipts'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn(['freight_amount', 'freight_method', 'freight_account']);
            });
        }
    }
};
