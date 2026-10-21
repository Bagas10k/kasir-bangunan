<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add unique index for journal entry reference (reference_type, reference_id)
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unique(['reference_type', 'reference_id'], 'journal_entries_ref_type_id_unique');
        });

        // 2. DB constraints via triggers for SQLite/MySQL compatibility
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // Nonnegative stock constraint triggers
            DB::unprepared("
                CREATE TRIGGER trg_products_stock_nonnegative_update
                BEFORE UPDATE OF stock_quantity ON products
                FOR EACH ROW
                WHEN NEW.stock_quantity < 0
                BEGIN
                    SELECT RAISE(ABORT, 'CHECK constraint failed: stock_quantity cannot be negative');
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER trg_products_stock_nonnegative_insert
                BEFORE INSERT ON products
                FOR EACH ROW
                WHEN NEW.stock_quantity < 0
                BEGIN
                    SELECT RAISE(ABORT, 'CHECK constraint failed: stock_quantity cannot be negative');
                END;
            ");

            // Journal entry lines legs: debit >= 0 and credit >= 0
            DB::unprepared("
                CREATE TRIGGER trg_journal_entry_lines_legs_insert
                BEFORE INSERT ON journal_entry_lines
                FOR EACH ROW
                WHEN NEW.debit < 0 OR NEW.credit < 0
                BEGIN
                    SELECT RAISE(ABORT, 'CHECK constraint failed: journal line legs must be non-negative');
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER trg_journal_entry_lines_legs_update
                BEFORE UPDATE OF debit, credit ON journal_entry_lines
                FOR EACH ROW
                WHEN NEW.debit < 0 OR NEW.credit < 0
                BEGIN
                    SELECT RAISE(ABORT, 'CHECK constraint failed: journal line legs must be non-negative');
                END;
            ");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_products_stock_nonnegative_update;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_products_stock_nonnegative_insert;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_entry_lines_legs_insert;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_entry_lines_legs_update;");
        }

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique('journal_entries_ref_type_id_unique');
        });
    }
};
