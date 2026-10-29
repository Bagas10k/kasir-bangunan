<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);
            $table->string('name', 50);
            $table->timestamps();
            $table->unique(['type', 'name']);
        });

        DB::table('product_options')->insert([
            ['type' => 'unit', 'name' => 'sak'],
            ['type' => 'category', 'name' => 'Umum'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_options');
    }
};
