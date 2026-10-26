<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER trg_journal_lines_immutable_update
                BEFORE UPDATE ON journal_entry_lines
                FOR EACH ROW
                BEGIN SELECT RAISE(ABORT, 'Posted journal lines are immutable'); END;");
            DB::unprepared("CREATE TRIGGER trg_journal_lines_immutable_delete
                BEFORE DELETE ON journal_entry_lines
                FOR EACH ROW
                BEGIN SELECT RAISE(ABORT, 'Posted journal lines are immutable'); END;");
            DB::unprepared("CREATE TRIGGER trg_journal_entries_immutable_update
                BEFORE UPDATE ON journal_entries
                FOR EACH ROW
                BEGIN SELECT RAISE(ABORT, 'Posted journal entries are immutable'); END;");
            DB::unprepared("CREATE TRIGGER trg_journal_entries_immutable_delete
                BEFORE DELETE ON journal_entries
                FOR EACH ROW
                BEGIN SELECT RAISE(ABORT, 'Posted journal entries are immutable'); END;");
        } elseif ($driver === 'mysql') {
            DB::unprepared("CREATE TRIGGER trg_journal_lines_immutable_update BEFORE UPDATE ON journal_entry_lines FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted journal lines are immutable'");
            DB::unprepared("CREATE TRIGGER trg_journal_lines_immutable_delete BEFORE DELETE ON journal_entry_lines FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted journal lines are immutable'");
            DB::unprepared("CREATE TRIGGER trg_journal_entries_immutable_update BEFORE UPDATE ON journal_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted journal entries are immutable'");
            DB::unprepared("CREATE TRIGGER trg_journal_entries_immutable_delete BEFORE DELETE ON journal_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted journal entries are immutable'");
        }
    }

    public function down(): void
    {
        foreach ([
            'trg_journal_lines_immutable_update',
            'trg_journal_lines_immutable_delete',
            'trg_journal_entries_immutable_update',
            'trg_journal_entries_immutable_delete',
        ] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
};
