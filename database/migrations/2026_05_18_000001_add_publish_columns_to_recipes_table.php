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
        Schema::table('recipes', function (Blueprint $table) {
            $table->boolean('is_published')->default(false)->after('selling_price');
            $table->unsignedBigInteger('published_version_id')->nullable()->after('is_published');
            $table->timestamp('published_at')->nullable()->after('published_version_id');
            $table->index('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropIndex(['published_at']);
            $table->dropColumn(['is_published', 'published_version_id', 'published_at']);
        });
    }
};
