<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ingredient_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name', 500);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['ingredient_id', 'locale']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ingredient_translations ADD FULLTEXT INDEX ft_name (name)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredient_translations');
    }
};
