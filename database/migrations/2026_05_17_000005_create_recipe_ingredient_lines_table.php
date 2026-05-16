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
        Schema::create('recipe_ingredient_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('recipe_sections')->cascadeOnDelete();
            $table->unsignedBigInteger('ingredient_id')->nullable();
            $table->unsignedBigInteger('sub_recipe_version_id')->nullable();
            $table->decimal('quantity', 14, 6)->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->decimal('quantity_g', 14, 6)->nullable();
            $table->text('prep_note')->nullable();
            $table->decimal('yield_pct', 7, 4)->nullable();
            $table->boolean('is_flour_base')->default(false);
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index('ingredient_id');
            $table->index('sub_recipe_version_id');
            $table->index('unit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredient_lines');
    }
};
