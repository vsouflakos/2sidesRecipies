<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ingredient_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('from_amount', 10, 4);
            $table->foreignId('from_unit_id')->constrained('units');
            $table->decimal('gram_weight', 10, 4);
            $table->string('modifier', 100)->nullable();
            $table->string('source');
            $table->string('source_ref', 50)->nullable();
            $table->timestamps();

            $table->index('ingredient_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredient_conversions');
    }
};
