<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Illuminate\Database\QueryException;

class MySqlConstraintTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if ($this->isMysqlOrMariaDb()) {
            $dbName = (string) DB::connection()->getDatabaseName();
            $optIn = (string) (env('KASIR_DISPOSABLE_DB_TEST') ?? getenv('KASIR_DISPOSABLE_DB_TEST'));

            if (!str_starts_with($dbName, 'kasir_ephem_') || $optIn !== '1') {
                $this->fail(
                    "Safety guard: MySqlConstraintTest RefreshDatabase refused on {$this->getDbDriver()} database '{$dbName}'. " .
                    "Requires disposable DB name with exact prefix 'kasir_ephem_' AND explicit opt-in environment flag KASIR_DISPOSABLE_DB_TEST=1."
                );
            }
        }
    }

    private function getDbDriver(): string
    {
        return DB::connection()->getDriverName();
    }

    private function isMysqlOrMariaDb(): bool
    {
        return in_array($this->getDbDriver(), ['mysql', 'mariadb'], true);
    }

    /**
     * Verify that SQLite ignores MySQL CHECK constraints and relies on SQLite triggers.
     */
    public function test_sqlite_suite_ignores_mysql_check_guards_cleanly(): void
    {
        if ($this->isMysqlOrMariaDb()) {
            $this->markTestSkipped('SQLite guard verification test. Skipped on MySQL/MariaDB.');
        }

        $this->assertEquals('sqlite', $this->getDbDriver());

        // Verify SQLite trigger still protects stock_quantity >= 0
        $this->expectException(QueryException::class);
        DB::table('products')->insert([
            'sku' => 'SYNTH-SQLITE-NEG-1',
            'name' => 'Synthetic Negative Product',
            'unit' => 'zak',
            'cost_price' => '10000.00',
            'selling_price' => '15000.00',
            'stock_quantity' => '-5.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Verify MySQL/MariaDB rejects negative stock_quantity via CHECK constraint.
     */
    public function test_mysql_mariadb_enforces_nonnegative_stock_check(): void
    {
        if (!$this->isMysqlOrMariaDb()) {
            $this->markTestSkipped('MySQL/MariaDB DB-level constraint test. Skipped on SQLite.');
        }

        $this->expectException(QueryException::class);
        DB::table('products')->insert([
            'sku' => 'SYNTH-MYSQL-NEG-STOCK',
            'name' => 'Synthetic Negative Stock',
            'unit' => 'zak',
            'cost_price' => '25000.00',
            'selling_price' => '30000.00',
            'stock_quantity' => '-1.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Verify MySQL/MariaDB accepts non-negative stock_quantity.
     */
    public function test_mysql_mariadb_allows_nonnegative_stock(): void
    {
        if (!$this->isMysqlOrMariaDb()) {
            $this->markTestSkipped('MySQL/MariaDB DB-level constraint test. Skipped on SQLite.');
        }

        $id = DB::table('products')->insertGetId([
            'sku' => 'SYNTH-MYSQL-VALID-STOCK',
            'name' => 'Synthetic Valid Stock',
            'unit' => 'zak',
            'cost_price' => '25000.00',
            'selling_price' => '30000.00',
            'stock_quantity' => '15.5000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertGreaterThan(0, $id);
    }

    /**
     * Verify MySQL/MariaDB rejects negative debit or credit via CHECK constraint.
     */
    public function test_mysql_mariadb_enforces_nonnegative_journal_legs(): void
    {
        if (!$this->isMysqlOrMariaDb()) {
            $this->markTestSkipped('MySQL/MariaDB DB-level constraint test. Skipped on SQLite.');
        }

        $journalId = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'manual',
            'reference_id' => 9901,
            'description' => 'Synthetic Negative Leg Test',
            'transaction_date' => date('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('journal_entry_lines')->insert([
            'journal_entry_id' => $journalId,
            'account_code' => '1101',
            'account_name' => 'Kas Toko',
            'debit' => '-50000.00',
            'credit' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Verify MySQL/MariaDB rejects two-sided positive leg (debit > 0 and credit > 0).
     */
    public function test_mysql_mariadb_rejects_two_sided_journal_leg(): void
    {
        if (!$this->isMysqlOrMariaDb()) {
            $this->markTestSkipped('MySQL/MariaDB DB-level constraint test. Skipped on SQLite.');
        }

        $journalId = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'manual',
            'reference_id' => 9902,
            'description' => 'Synthetic Two-Sided Leg Test',
            'transaction_date' => date('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('journal_entry_lines')->insert([
            'journal_entry_id' => $journalId,
            'account_code' => '1101',
            'account_name' => 'Kas Toko',
            'debit' => '50000.00',
            'credit' => '50000.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Verify MySQL/MariaDB rejects all-zero leg (debit = 0 and credit = 0).
     */
    public function test_mysql_mariadb_rejects_zero_sided_journal_leg(): void
    {
        if (!$this->isMysqlOrMariaDb()) {
            $this->markTestSkipped('MySQL/MariaDB DB-level constraint test. Skipped on SQLite.');
        }

        $journalId = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'manual',
            'reference_id' => 9903,
            'description' => 'Synthetic Zero Leg Test',
            'transaction_date' => date('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('journal_entry_lines')->insert([
            'journal_entry_id' => $journalId,
            'account_code' => '1101',
            'account_name' => 'Kas Toko',
            'debit' => '0.00',
            'credit' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Verify MySQL/MariaDB accepts valid one-sided journal legs.
     */
    public function test_mysql_mariadb_allows_valid_one_sided_legs(): void
    {
        if (!$this->isMysqlOrMariaDb()) {
            $this->markTestSkipped('MySQL/MariaDB DB-level constraint test. Skipped on SQLite.');
        }

        $journalId = DB::table('journal_entries')->insertGetId([
            'reference_type' => 'manual',
            'reference_id' => 9904,
            'description' => 'Synthetic Valid Legs Test',
            'transaction_date' => date('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $debitLineId = DB::table('journal_entry_lines')->insertGetId([
            'journal_entry_id' => $journalId,
            'account_code' => '1101',
            'account_name' => 'Kas Toko',
            'debit' => '75000.00',
            'credit' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $creditLineId = DB::table('journal_entry_lines')->insertGetId([
            'journal_entry_id' => $journalId,
            'account_code' => '4101',
            'account_name' => 'Pendapatan Penjualan',
            'debit' => '0.00',
            'credit' => '75000.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertGreaterThan(0, $debitLineId);
        $this->assertGreaterThan(0, $creditLineId);
    }
}
