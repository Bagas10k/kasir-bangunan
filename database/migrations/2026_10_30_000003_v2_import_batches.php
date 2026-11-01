<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('v2_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->enum('kind', ['product', 'customer', 'supplier', 'employee', 'vehicle']);
            $table->enum('status', ['preview', 'committed'])->default('preview');
            $table->unsignedInteger('row_count')->default(0);
            $table->json('rows'); // Array of validated record payloads ready to commit
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();

            $table->index(['actor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_import_batches');
    }
};
