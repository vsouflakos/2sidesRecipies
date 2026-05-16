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
        Schema::create('recipe_draft_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_draft_id')->constrained()->cascadeOnDelete();
            $table->integer('sequence');
            $table->string('action');
            $table->json('before_snapshot');
            $table->timestamps();

            $table->index('recipe_draft_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_draft_edits');
    }
};
