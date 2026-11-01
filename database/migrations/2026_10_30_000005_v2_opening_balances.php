<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel saldo awal V2: batch pembukaan mandiri tunggal (immutable once per install).
 * Aditif: tidak mengubah skema legacy maupun sibling.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_opening_batches', function (Blueprint $table) {
            $table->id();
            // Singleton key memastikan hanya ada tepat satu batch pembukaan V2 secara global
            $table->unsignedTinyInteger('singleton_key')->default(1)->unique();
            $table->date('date');
            $table->string('reference')->default('SALDO-AWAL-V2');
            $table->string('idempotency_key')->unique();
            $table->string('request_hash', 64);
            $table->decimal('cash_amount', 19, 2)->default(0);
            $table->decimal('bank_amount', 19, 2)->default(0);
            $table->decimal('inventory_amount', 19, 2)->default(0);
            $table->decimal('ar_amount', 19, 2)->default(0);
            $table->decimal('ap_amount', 19, 2)->default(0);
            $table->decimal('equity_amount', 19, 2)->default(0);
            $table->enum('equity_direction', ['debit', 'credit'])->default('credit');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        // MariaDB CHECK and SQLite triggers enforce numeric invariants
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER trg_v2_opening_batches_guard BEFORE INSERT ON v2_opening_batches
                WHEN NEW.singleton_key <> 1 OR NEW.cash_amount < 0 OR NEW.bank_amount < 0 OR NEW.inventory_amount < 0 OR NEW.ar_amount < 0 OR NEW.ap_amount < 0 OR NEW.equity_amount < 0
                BEGIN SELECT RAISE(ABORT, 'Invalid opening batch amounts or singleton constraint'); END;");
        } elseif ($driver === 'mysql') {
            DB::unprepared("CREATE TRIGGER trg_v2_opening_batches_guard BEFORE INSERT ON v2_opening_batches FOR EACH ROW
                BEGIN
                    IF NEW.singleton_key <> 1 OR NEW.cash_amount < 0 OR NEW.bank_amount < 0 OR NEW.inventory_amount < 0 OR NEW.ar_amount < 0 OR NEW.ap_amount < 0 OR NEW.equity_amount < 0 THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid opening batch amounts or singleton constraint';
                    END IF;
                END");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_v2_opening_batches_guard');
        } elseif ($driver === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_v2_opening_batches_guard');
        }
        Schema::dropIfExists('v2_opening_batches');
    }
};
