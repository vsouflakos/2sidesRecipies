<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Mirrors the cache columns on recipe_versions so the recipe list card can
     * read live draft metrics instead of a stale committed-version snapshot.
     */
    public function up(): void
    {
        Schema::table('recipe_drafts', function (Blueprint $table) {
            $table->json('cached_nutrition_json')->nullable()->after('data');
            $table->decimal('cached_cost_per_portion', 12, 4)->nullable()->after('cached_nutrition_json');
            $table->json('cached_allergen_slugs')->nullable()->after('cached_cost_per_portion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipe_drafts', function (Blueprint $table) {
            $table->dropColumn([
                'cached_nutrition_json',
                'cached_cost_per_portion',
                'cached_allergen_slugs',
            ]);
        });
    }
};
