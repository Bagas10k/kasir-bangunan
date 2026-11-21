<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sumber dana pembayaran: cash = laci shift, main = Kas Utama (tunai toko), transfer = Bank Utama.
 * MySQL memakai enum; SQLite (tes) memakai varchar sehingga nilai baru langsung diterima.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->enum(['cash', 'transfer', 'main']);
        // Akun 1101 kini pemapung tunai utama toko (bukan hanya laci shift).
        DB::table('chart_of_accounts')->where('account_code', '1101')->update(['account_name' => 'Kas Utama (Tunai)', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('v2_payments')->where('method', 'main')->update(['method' => 'cash']);
        DB::table('chart_of_accounts')->where('account_code', '1101')->update(['account_name' => 'Kas Toko (Tunai)', 'updated_at' => now()]);
        $this->enum(['cash', 'transfer']);
    }

    private function enum(array $values): void
    {
        if (DB::getDriverName() === 'mysql') {
            $list = implode(',', array_map(fn (string $value) => "'".$value."'", $values));
            DB::statement('ALTER TABLE `v2_payments` MODIFY `method` ENUM('.$list.') NOT NULL');

            return;
        }

        Schema::table('v2_payments', fn (Blueprint $table) => $table->string('method', 16)->change());
    }
};
