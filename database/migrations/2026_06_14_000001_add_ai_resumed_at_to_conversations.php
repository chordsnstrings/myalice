<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a human hand a conversation back to the AI: `ai_resumed_at` marks the
 * moment, so only agent messages sent AFTER it count as a fresh human takeover
 * (the engagement back-off ignores messages before the resume).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('ai_resumed_at')->nullable()->after('reengaged_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', fn (Blueprint $table) => $table->dropColumn('ai_resumed_at'));
    }
};
