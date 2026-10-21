<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Driver-guarded CHECK constraints for MySQL 8.4+ and MariaDB 11.4+.
     * On SQLite, existing triggers in migration 000002 handle enforcement.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $this->assertPreflightClean();

        // 1. Nonnegative product stock constraint
        DB::statement("ALTER TABLE products ADD CONSTRAINT chk_products_stock_nonnegative CHECK (stock_quantity >= 0)");

        // 2. Journal line legs constraint:
        // Legs must be non-negative, and strictly one-sided (debit > 0 and credit = 0, or credit > 0 and debit = 0)
        DB::statement("ALTER TABLE journal_entry_lines ADD CONSTRAINT chk_journal_entry_lines_legs CHECK ((debit >= 0 AND credit >= 0) AND ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0)))");
    }

    /**
     * Pre-flight validation before executing DDL.
     * MariaDB/MySQL execute implicit commits on DDL statements.
     * If the first ALTER succeeds and the second fails due to invalid rows,
     * the database is left in a partially migrated state.
     * This pre-flight query validates existing rows first and fails fast.
     */
    public function assertPreflightClean(): void
    {
        $negativeStockCount = (int) DB::table('products')
            ->where('stock_quantity', '<', 0)
            ->count();

        $invalidLegsCount = (int) DB::table('journal_entry_lines')
            ->whereRaw('debit < 0 OR credit < 0 OR (debit = 0 AND credit = 0) OR (debit > 0 AND credit > 0) OR debit IS NULL OR credit IS NULL')
            ->count();

        if ($negativeStockCount > 0 || $invalidLegsCount > 0) {
            $issues = [];
            if ($negativeStockCount > 0) {
                $issues[] = "{$negativeStockCount} product(s) with negative stock";
            }
            if ($invalidLegsCount > 0) {
                $issues[] = "{$invalidLegsCount} journal_entry_line(s) with invalid legs";
            }

            throw new \RuntimeException(
                'Pre-flight migration check failed: detected ' . implode(' and ', $issues) .
                '. Cleanse legacy invalid rows before applying schema constraints.'
            );
        }
    }

    /**
     * Reverse the migrations.
     * Uses driver-specific syntax:
     * MariaDB uses 'ALTER TABLE ... DROP CONSTRAINT <name>'
     * MySQL uses 'ALTER TABLE ... DROP CHECK <name>'
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $isMariaDb = ($driver === 'mariadb');
        if (!$isMariaDb) {
            try {
                $version = (string) (DB::selectOne("SELECT VERSION() as v")?->v ?? '');
                $isMariaDb = str_contains(strtolower($version), 'mariadb');
            } catch (\Throwable) {
                $isMariaDb = false;
            }
        }

        if ($isMariaDb) {
            DB::statement("ALTER TABLE products DROP CONSTRAINT chk_products_stock_nonnegative");
            DB::statement("ALTER TABLE journal_entry_lines DROP CONSTRAINT chk_journal_entry_lines_legs");
        } else {
            DB::statement("ALTER TABLE products DROP CHECK chk_products_stock_nonnegative");
            DB::statement("ALTER TABLE journal_entry_lines DROP CHECK chk_journal_entry_lines_legs");
        }
    }
};
