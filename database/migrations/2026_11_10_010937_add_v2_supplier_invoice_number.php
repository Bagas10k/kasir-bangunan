<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor nota/faktur dari supplier pada penerimaan barang (opsional), untuk memperingatkan nota yang sama dicatat dua kali.
 * Dicocokkan per supplier (lewat tagihan utang penerimaan), tanpa membedakan huruf besar/kecil dan spasi tepi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_receipts', function (Blueprint $table) {
            $table->string('supplier_invoice_number', 64)->nullable()->after('supplier_name');
            $table->index('supplier_invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_receipts', function (Blueprint $table) {
            $table->dropIndex(['supplier_invoice_number']);
            $table->dropColumn('supplier_invoice_number');
        });
    }
};
