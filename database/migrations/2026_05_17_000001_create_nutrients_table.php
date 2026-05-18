<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Reference table of nutrient definitions, seeded from USDA FoodData
     * Central nutrient.csv. Holds every nutrient the source files describe so
     * the full nutrient set can be captured in ingredient_nutrients.
     */
    public function up(): void
    {
        Schema::create('nutrients', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('usda_nutrient_id')->unique();
            $table->string('name');
            $table->string('unit');
            $table->string('nutrient_nbr')->nullable();
            $table->decimal('rank', 10, 1)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nutrients');
    }
};
