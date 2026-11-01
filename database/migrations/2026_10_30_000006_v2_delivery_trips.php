<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Siklus kirim V2 (jejak audit dispatch/receive) + uang muka sopir dan
 * penyelesaiannya. Aditif: kolom v2_deliveries lama dan v2_trips tidak diubah,
 * tidak ada tabel kedua untuk beban yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        $missing = [];
        foreach (['dispatched_by', 'dispatched_at', 'received_by', 'received_at'] as $column) {
            if (! Schema::hasColumn('v2_deliveries', $column)) {
                $missing[] = $column;
            }
        }

        if ($missing !== []) {
            Schema::table('v2_deliveries', function (Blueprint $table) use ($missing) {
                if (in_array('dispatched_by', $missing, true)) {
                    $table->unsignedBigInteger('dispatched_by')->nullable();
                    $table->foreign('dispatched_by')->references('id')->on('users')->restrictOnDelete();
                }
                if (in_array('dispatched_at', $missing, true)) {
                    $table->timestamp('dispatched_at')->nullable();
                }
                if (in_array('received_by', $missing, true)) {
                    $table->unsignedBigInteger('received_by')->nullable();
                    $table->foreign('received_by')->references('id')->on('users')->restrictOnDelete();
                }
                if (in_array('received_at', $missing, true)) {
                    $table->timestamp('received_at')->nullable();
                }
            });
        }

        Schema::create('v2_driver_advances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->restrictOnDelete();
            $table->string('driver_name');
            $table->string('destination')->nullable();
            $table->unsignedBigInteger('delivery_id')->nullable();
            $table->foreign('delivery_id')->references('id')->on('v2_deliveries')->restrictOnDelete();
            $table->date('date');
            $table->decimal('amount', 19, 2);
            $table->enum('method', ['cash', 'transfer']);
            $table->enum('status', ['open', 'settled'])->default('open');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->restrictOnDelete();
            $table->string('idempotency_key')->unique();
            $table->string('request_hash', 64);
            $table->timestamps();
            $table->index(['vehicle_id', 'date']);
            $table->index('status');
        });

        Schema::create('v2_driver_advance_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advance_id')->unique()->constrained('v2_driver_advances')->restrictOnDelete();
            $table->decimal('fuel_cost', 19, 2)->default(0);
            $table->decimal('allowance', 19, 2)->default(0);
            $table->decimal('expense_total', 19, 2);
            $table->decimal('refund_amount', 19, 2);
            $table->enum('refund_method', ['cash', 'transfer']);
            $table->boolean('refund_received')->default(false);
            $table->string('status', 10)->default('settled');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->restrictOnDelete();
            $table->foreignId('settled_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('settled_at');
            $table->string('idempotency_key')->unique();
            $table->string('request_hash', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_driver_advance_settlements');
        Schema::dropIfExists('v2_driver_advances');

        foreach (['dispatched_by', 'received_by'] as $column) {
            if (Schema::hasColumn('v2_deliveries', $column)) {
                Schema::table('v2_deliveries', function (Blueprint $table) use ($column) {
                    $table->dropForeign([$column]);
                });
            }
        }

        Schema::table('v2_deliveries', function (Blueprint $table) {
            foreach (['dispatched_by', 'dispatched_at', 'received_by', 'received_at'] as $column) {
                if (Schema::hasColumn('v2_deliveries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
