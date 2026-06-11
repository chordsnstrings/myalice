<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track which connected Channel (a specific Facebook Page / WhatsApp number /
 * Instagram account) a conversation arrived on, so AI agents can be configured
 * per page (AiAgent::resolveFor channel:{id} → type → all).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('channel_id')->nullable()->after('channel');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', fn (Blueprint $table) => $table->dropColumn('channel_id'));
    }
};
