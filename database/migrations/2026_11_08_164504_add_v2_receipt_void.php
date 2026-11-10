<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembatalan penerimaan barang yang salah input.
 * - inventory_receipt_items.cost_before: HPP produk sebelum penerimaan, supaya pembatalan mengembalikan HPP persis.
 * - inventory_receipts.voided_*: siapa, kapan, alasan, dan jurnal balik pembatalan (jejak audit; baris tidak dihapus).
 * - v2_invoices.voided_at: tagihan supplier milik penerimaan yang dibatalkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_receipt_items', function (Blueprint $table) {
            $table->decimal('cost_before', 19, 4)->nullable()->after('unit_cost');
        });
        Schema::table('inventory_receipts', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->foreign('voided_by')->references('id')->on('users')->restrictOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->unsignedBigInteger('void_journal_entry_id')->nullable();
            $table->foreign('void_journal_entry_id')->references('id')->on('journal_entries')->restrictOnDelete();
        });
        Schema::table('v2_invoices', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('v2_invoices', fn (Blueprint $table) => $table->dropColumn('voided_at'));
        Schema::table('inventory_receipts', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropForeign(['void_journal_entry_id']);
            $table->dropColumn(['voided_at', 'voided_by', 'void_reason', 'void_journal_entry_id']);
        });
        Schema::table('inventory_receipt_items', fn (Blueprint $table) => $table->dropColumn('cost_before'));
    }
};
