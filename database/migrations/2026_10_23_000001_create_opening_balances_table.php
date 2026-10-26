<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('singleton_key')->default(1)->unique();
            $table->date('transaction_date');
            $table->decimal('cash_amount', 19, 2)->unsigned()->default(0);
            $table->decimal('bank_amount', 19, 2)->unsigned()->default(0);
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER trg_opening_balances_insert_guard BEFORE INSERT ON opening_balances
                WHEN NEW.cash_amount < 0 OR NEW.bank_amount < 0 OR NEW.status NOT IN ('draft', 'posted') OR NEW.singleton_key <> 1
                BEGIN SELECT RAISE(ABORT, 'Invalid opening balance'); END;");
            DB::unprepared("CREATE TRIGGER trg_opening_balances_update_guard BEFORE UPDATE ON opening_balances
                WHEN OLD.status = 'posted' OR NEW.cash_amount < 0 OR NEW.bank_amount < 0 OR NEW.status NOT IN ('draft', 'posted') OR NEW.singleton_key <> 1
                BEGIN SELECT RAISE(ABORT, 'Posted opening balance is immutable'); END;");
            DB::unprepared("CREATE TRIGGER trg_opening_balances_delete_guard BEFORE DELETE ON opening_balances
                WHEN OLD.status = 'posted'
                BEGIN SELECT RAISE(ABORT, 'Posted opening balance is immutable'); END;");
        } elseif ($driver === 'mysql') {
            DB::unprepared("CREATE TRIGGER trg_opening_balances_insert_guard BEFORE INSERT ON opening_balances FOR EACH ROW BEGIN IF NEW.singleton_key <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Opening balance must be singleton'; END IF; END");
            DB::unprepared("CREATE TRIGGER trg_opening_balances_update_guard BEFORE UPDATE ON opening_balances FOR EACH ROW BEGIN IF OLD.status = 'posted' OR NEW.singleton_key <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted opening balance is immutable'; END IF; END");
            DB::unprepared("CREATE TRIGGER trg_opening_balances_delete_guard BEFORE DELETE ON opening_balances FOR EACH ROW BEGIN IF OLD.status = 'posted' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted opening balance is immutable'; END IF; END");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        DB::unprepared('DROP TRIGGER IF EXISTS trg_opening_balances_insert_guard');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_opening_balances_update_guard');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_opening_balances_delete_guard');
        Schema::dropIfExists('opening_balances');
    }
};
