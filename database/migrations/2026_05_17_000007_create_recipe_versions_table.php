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
        Schema::create('recipe_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->integer('version_number');
            $table->foreignId('committed_by')->constrained('users');
            $table->timestamp('committed_at');
            $table->string('change_note')->nullable();
            $table->json('snapshot');
            $table->decimal('yield_g', 14, 4)->nullable();
            $table->json('cached_nutrition_json')->nullable();
            $table->decimal('cached_cost_per_gram', 16, 8)->nullable();
            $table->decimal('cached_cost_per_portion', 12, 4)->nullable();
            $table->json('cached_allergen_slugs')->nullable();
            $table->decimal('cached_selling_price', 12, 4)->nullable();
            $table->timestamps();

            $table->unique(['recipe_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_versions');
    }
};
