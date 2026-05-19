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
        Schema::table('recipe_conversations', function (Blueprint $table) {
            // Tracks the lifecycle of a queued agent turn: idle | generating | failed.
            $table->string('agent_status')->default('idle')->after('recipe_id');
            // The failure reason surfaced to the chat UI when agent_status is 'failed'.
            $table->text('agent_error')->nullable()->after('agent_status');
            // When the current (or most recent) turn was dispatched.
            $table->timestamp('agent_started_at')->nullable()->after('agent_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipe_conversations', function (Blueprint $table) {
            $table->dropColumn(['agent_status', 'agent_error', 'agent_started_at']);
        });
    }
};
