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
        Schema::create('recipe_tag', function (Blueprint $table) {
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['recipe_id', 'tag_id']);
        });

        // Add deferred FK constraints for circular references (using plain columns in migrations above)
        Schema::table('recipes', function (Blueprint $table) {
            $table->foreign('cuisine_id')->references('id')->on('cuisines')->nullOnDelete();
            $table->foreign('yield_unit_id')->references('id')->on('units')->nullOnDelete();
            $table->foreign('current_version_id')->references('id')->on('recipe_versions')->nullOnDelete();
        });

        Schema::table('recipe_ingredient_lines', function (Blueprint $table) {
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->nullOnDelete();
            $table->foreign('sub_recipe_version_id')->references('id')->on('recipe_versions')->nullOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipe_ingredient_lines', function (Blueprint $table) {
            $table->dropForeign(['ingredient_id']);
            $table->dropForeign(['sub_recipe_version_id']);
            $table->dropForeign(['unit_id']);
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropForeign(['cuisine_id']);
            $table->dropForeign(['yield_unit_id']);
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('recipe_tag');
    }
};
