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
        Schema::create('ingredient_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 4);
            $table->string('currency', 3)->default('EUR');
            $table->decimal('quantity', 10, 4);
            $table->foreignId('unit_id')->constrained('units');
            $table->decimal('per_gram_cost', 16, 8)->nullable();
            $table->date('recorded_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'ingredient_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredient_prices');
    }
};
