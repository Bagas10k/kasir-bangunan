<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Harga kontraktor.
 * - products.contractor_price: harga per satuan untuk pelanggan kontraktor; NULL = pakai harga jual biasa.
 * - v2_parties.price_tier: penanda pelanggan (retail/contractor); kasir mengikutinya dan boleh mengganti per transaksi.
 * - v2_sales.price_tier: harga yang dipakai pada nota (jejak audit). Data lama otomatis 'retail'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('contractor_price', 19, 2)->nullable()->after('selling_price');
        });
        Schema::table('v2_parties', function (Blueprint $table) {
            $table->string('price_tier', 16)->default('retail')->after('name');
        });
        Schema::table('v2_sales', function (Blueprint $table) {
            $table->string('price_tier', 16)->default('retail')->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('v2_sales', fn (Blueprint $table) => $table->dropColumn('price_tier'));
        Schema::table('v2_parties', fn (Blueprint $table) => $table->dropColumn('price_tier'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('contractor_price'));
    }
};
