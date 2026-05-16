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
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('ingredient_categories');
            $table->string('source');
            $table->string('source_id');
            $table->string('usda_fdc_id')->nullable();
            $table->string('name_cache');
            $table->boolean('verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('data_hash', 32)->nullable();
            $table->string('foodex2_code')->nullable();

            // Nutrition columns per 100g (all DECIMAL(10,4), nullable)
            $table->decimal('energy_kcal', 10, 4)->nullable();
            $table->decimal('protein_g', 10, 4)->nullable();
            $table->decimal('fat_g', 10, 4)->nullable();
            $table->decimal('saturated_fat_g', 10, 4)->nullable();
            $table->decimal('monounsaturated_fat_g', 10, 4)->nullable();
            $table->decimal('polyunsaturated_fat_g', 10, 4)->nullable();
            $table->decimal('carbs_g', 10, 4)->nullable();
            $table->decimal('sugars_g', 10, 4)->nullable();
            $table->decimal('starch_g', 10, 4)->nullable();
            $table->decimal('fibre_g', 10, 4)->nullable();
            $table->decimal('sodium_mg', 10, 4)->nullable();
            $table->decimal('calcium_mg', 10, 4)->nullable();
            $table->decimal('iron_mg', 10, 4)->nullable();
            $table->decimal('magnesium_mg', 10, 4)->nullable();
            $table->decimal('phosphorus_mg', 10, 4)->nullable();
            $table->decimal('potassium_mg', 10, 4)->nullable();
            $table->decimal('zinc_mg', 10, 4)->nullable();
            $table->decimal('vitamin_a_ug', 10, 4)->nullable();
            $table->decimal('vitamin_b1_mg', 10, 4)->nullable();
            $table->decimal('vitamin_b2_mg', 10, 4)->nullable();
            $table->decimal('vitamin_b3_mg', 10, 4)->nullable();
            $table->decimal('vitamin_b6_mg', 10, 4)->nullable();
            $table->decimal('vitamin_b9_ug', 10, 4)->nullable();
            $table->decimal('vitamin_b12_ug', 10, 4)->nullable();
            $table->decimal('vitamin_c_mg', 10, 4)->nullable();
            $table->decimal('vitamin_d_ug', 10, 4)->nullable();
            $table->decimal('vitamin_e_mg', 10, 4)->nullable();
            $table->decimal('vitamin_k_ug', 10, 4)->nullable();
            $table->decimal('cholesterol_mg', 10, 4)->nullable();

            // Reserved frozen-dessert columns (all nullable)
            $table->decimal('total_solids_pct', 8, 4)->nullable();
            $table->decimal('fat_pct', 8, 4)->nullable();
            $table->decimal('msnf_pct', 8, 4)->nullable();
            $table->decimal('sugar_pct', 8, 4)->nullable();
            $table->decimal('other_solids_pct', 8, 4)->nullable();
            $table->decimal('water_pct', 8, 4)->nullable();
            $table->decimal('pac_coefficient', 8, 4)->nullable();
            $table->decimal('pod_coefficient', 8, 4)->nullable();
            $table->decimal('de_value', 8, 4)->nullable();
            $table->decimal('brix', 8, 4)->nullable();
            $table->string('ingredient_class')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['source', 'source_id']);
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
