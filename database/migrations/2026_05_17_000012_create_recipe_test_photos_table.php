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
        Schema::create('recipe_test_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_test_id')->constrained('recipe_tests')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_test_photos');
    }
};
