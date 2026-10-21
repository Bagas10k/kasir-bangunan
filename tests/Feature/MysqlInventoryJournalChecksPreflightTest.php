<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MysqlInventoryJournalChecksPreflightTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $database = $connection->getDatabaseName();

        if ($driver !== 'sqlite' || $database !== ':memory:') {
            throw new \RuntimeException(
                sprintf(
                    'MysqlInventoryJournalChecksPreflightTest must only be run with an in-memory SQLite database (:memory:). Current driver [%s] with database [%s] rejected.',
                    $driver,
                    $database
                )
            );
        }
    }

    private function getMigration(): object
    {
        return require database_path('migrations/2026_10_21_000006_add_mysql_inventory_journal_checks.php');
    }

    public function test_preflight_passes_on_clean_data(): void
    {
        $migration = $this->getMigration();
        $migration->assertPreflightClean();
        $this->assertTrue(true);
    }

    public function test_preflight_throws_runtime_exception_on_negative_stock_without_exposing_private_data(): void
    {
        // Simulate dirty legacy table without SQLite CHECK constraint
        DB::statement('DROP TABLE products');
        DB::statement('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, sku TEXT, name TEXT, unit TEXT, cost_price NUMERIC, selling_price NUMERIC, stock_quantity NUMERIC, created_at TEXT, updated_at TEXT)');

        DB::table('products')->insert([
            'name' => 'Secret Customer Special Batch',
            'sku' => 'SKU-CONFIDENTIAL-999',
            'stock_quantity' => -12,
            'unit' => 'sak',
            'cost_price' => 55000,
            'selling_price' => 65000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = $this->getMigration();

        $this->expectException(\RuntimeException::class);

        try {
            $migration->assertPreflightClean();
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('Secret Customer Special Batch', $e->getMessage());
            $this->assertStringNotContainsString('SKU-CONFIDENTIAL-999', $e->getMessage());
            $this->assertStringContainsString('1 product(s) with negative stock', $e->getMessage());
            throw $e;
        }
    }

    public function test_preflight_throws_runtime_exception_on_invalid_journal_legs(): void
    {
        // Simulate dirty legacy table without trigger restrictions
        DB::statement('DROP TABLE journal_entry_lines');
        DB::statement('CREATE TABLE journal_entry_lines (id INTEGER PRIMARY KEY AUTOINCREMENT, journal_entry_id INTEGER, account_code TEXT, account_name TEXT, debit NUMERIC, credit NUMERIC, created_at TEXT, updated_at TEXT)');

        // Insert invalid leg: both debit = 0 and credit = 0 (dirty history)
        DB::table('journal_entry_lines')->insert([
            'journal_entry_id' => 1,
            'account_code' => '9999.01',
            'account_name' => 'Secret Internal Ledger',
            'debit' => 0,
            'credit' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = $this->getMigration();

        $this->expectException(\RuntimeException::class);

        try {
            $migration->assertPreflightClean();
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('9999.01', $e->getMessage());
            $this->assertStringNotContainsString('Secret Internal Ledger', $e->getMessage());
            $this->assertStringContainsString('1 journal_entry_line(s) with invalid legs', $e->getMessage());
            throw $e;
        }
    }

    public function test_migration_up_and_down_are_noop_on_sqlite(): void
    {
        $this->assertEquals('sqlite', DB::getDriverName());

        $migration = $this->getMigration();
        // Driver guard skips DDL on SQLite without error
        $migration->up();
        $migration->down();

        $this->assertTrue(true);
    }
}
