<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Standard Chart of Accounts for Building Material Store.
     */
    public static function defaultAccounts(): array
    {
        return [
            // 1. Aset (Assets) - Normal Debit
            ['account_code' => '1101', 'account_name' => 'Kas Toko (Tunai)', 'account_type' => 'asset', 'normal_balance' => 'debit'],
            ['account_code' => '1102', 'account_name' => 'Rekening Bank', 'account_type' => 'asset', 'normal_balance' => 'debit'],
            ['account_code' => '1103', 'account_name' => 'Piutang Usaha (Pelanggan/Kontraktor)', 'account_type' => 'asset', 'normal_balance' => 'debit'],
            ['account_code' => '1301', 'account_name' => 'Persediaan Material Bangunan', 'account_type' => 'asset', 'normal_balance' => 'debit'],

            // 2. Kewajiban (Liabilities) - Normal Credit
            ['account_code' => '2101', 'account_name' => 'Utang Usaha (Pemasok/Supplier)', 'account_type' => 'liability', 'normal_balance' => 'credit'],

            // 3. Ekuitas (Equity) - Normal Credit
            ['account_code' => '3101', 'account_name' => 'Modal Pemilik / Modal Awal', 'account_type' => 'equity', 'normal_balance' => 'credit'],

            // 4. Pendapatan (Revenues) - Normal Credit
            ['account_code' => '4101', 'account_name' => 'Pendapatan Penjualan Material', 'account_type' => 'revenue', 'normal_balance' => 'credit'],
            ['account_code' => '7101', 'account_name' => 'Pendapatan Lain-lain', 'account_type' => 'revenue', 'normal_balance' => 'credit'],

            // 5. Beban Pokok & Penyesuaian (COGS) - Normal Debit
            ['account_code' => '5101', 'account_name' => 'Beban Pokok Penjualan (HPP)', 'account_type' => 'expense', 'normal_balance' => 'debit'],
            ['account_code' => '5201', 'account_name' => 'Beban Selisih Opname (Susut/Rusak)', 'account_type' => 'expense', 'normal_balance' => 'debit'],

            // 6. Beban Operasional (Operating Expenses) - Normal Debit
            ['account_code' => '6101', 'account_name' => 'Beban Operasional Toko', 'account_type' => 'expense', 'normal_balance' => 'debit'],
            ['account_code' => '6102', 'account_name' => 'Beban Listrik & Air', 'account_type' => 'expense', 'normal_balance' => 'debit'],
            ['account_code' => '6103', 'account_name' => 'Beban Pemeliharaan & Perbaikan', 'account_type' => 'expense', 'normal_balance' => 'debit'],
            ['account_code' => '6104', 'account_name' => 'Beban Perlengkapan & ATK', 'account_type' => 'expense', 'normal_balance' => 'debit'],
            ['account_code' => '6105', 'account_name' => 'Beban Gaji & Upah Tenaga', 'account_type' => 'expense', 'normal_balance' => 'debit'],
            ['account_code' => '6199', 'account_name' => 'Beban Lain-lain', 'account_type' => 'expense', 'normal_balance' => 'debit'],
            ['account_code' => '6201', 'account_name' => 'Beban Transportasi & Ritase Armada', 'account_type' => 'expense', 'normal_balance' => 'debit'],
        ];
    }

    public function run(): void
    {
        $accounts = self::defaultAccounts();
        $now = now();

        foreach ($accounts as $acc) {
            DB::table('chart_of_accounts')->updateOrInsert(
                ['account_code' => $acc['account_code']],
                [
                    'account_name' => $acc['account_name'],
                    'account_type' => $acc['account_type'],
                    'normal_balance' => $acc['normal_balance'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
