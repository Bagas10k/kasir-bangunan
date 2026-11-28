<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel idempotency generik V2 untuk operasi keuangan (pengeluaran operasional, pindah dana)
 * tanpa mengubah tabel jurnal atau tabel arsip lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_operation_idempotencies', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 40);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 100);
            $table->string('request_hash', 64);
            $table->unsignedBigInteger('resource_id');
            $table->json('response_payload');
            $table->timestamps();
            $table->unique(['scope', 'idempotency_key'], 'uq_v2_op_idempotency_scope_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_operation_idempotencies');
    }
};
