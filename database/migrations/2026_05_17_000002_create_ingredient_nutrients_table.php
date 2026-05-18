<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pivot capturing the full per-100g nutrient set for an ingredient. The
     * 29 flat columns on the ingredients table remain the fast path for the
     * recipe builder; this table holds every remaining metric (water, ash,
     * individual amino acids, fatty acids, sugars, carotenoids, …).
     */
    public function up(): void
    {
        Schema::create('ingredient_nutrients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nutrient_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 4)->nullable();
            $table->timestamps();

            $table->unique(['ingredient_id', 'nutrient_id']);
            $table->index('nutrient_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredient_nutrients');
    }
};
