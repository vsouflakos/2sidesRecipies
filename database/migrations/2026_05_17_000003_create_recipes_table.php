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
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('hero_image_path')->nullable();
            $table->decimal('yield_amount', 12, 4);
            $table->unsignedBigInteger('yield_unit_id')->nullable();
            $table->decimal('portions', 12, 4);
            $table->decimal('portion_size_g', 12, 4)->nullable();
            $table->integer('prep_time_minutes')->nullable();
            $table->integer('cook_time_minutes')->nullable();
            $table->string('difficulty')->nullable();
            $table->unsignedBigInteger('cuisine_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->decimal('selling_price', 12, 4)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('cuisine_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
