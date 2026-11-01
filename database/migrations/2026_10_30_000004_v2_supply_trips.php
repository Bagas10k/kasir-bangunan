<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 2 C: tabel idempotensi v2_requests + kolom pembayaran pada v2_trips.
 * Aditif: enum/kolom trips legacy dan model receipt lama tidak diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor')->constrained('users')->restrictOnDelete();
            $table->string('type', 30);
            $table->string('key', 100);
            $table->string('request_hash', 64);
            $table->json('result')->nullable();
            $table->timestamps();
            $table->unique(['actor', 'type', 'key']);
        });

        $missing = [];
        foreach (['payment_method', 'idempotency_key', 'request_hash'] as $column) {
            if (! Schema::hasColumn('v2_trips', $column)) {
                $missing[] = $column;
            }
        }

        if ($missing !== []) {
            Schema::table('v2_trips', function (Blueprint $table) use ($missing) {
                if (in_array('payment_method', $missing, true)) {
                    $table->string('payment_method', 10)->nullable();
                }
                if (in_array('idempotency_key', $missing, true)) {
                    $table->string('idempotency_key', 100)->nullable()->unique();
                }
                if (in_array('request_hash', $missing, true)) {
                    $table->string('request_hash', 64)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('v2_trips', function (Blueprint $table) {
            foreach (['idempotency_key', 'request_hash', 'payment_method'] as $column) {
                if (Schema::hasColumn('v2_trips', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('v2_requests');
    }
};
