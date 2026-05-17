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
        Schema::create('recipe_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('recipe_version_id')->constrained('recipe_versions')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->timestamp('tested_at');
            $table->text('tasting_notes')->nullable();
            $table->unsignedTinyInteger('overall_rating');
            $table->json('ratings')->nullable();
            $table->text('hypothesis')->nullable();
            $table->text('outcome_narrative')->nullable();
            $table->string('verdict')->nullable();
            $table->json('change_rows')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_tests');
    }
};
