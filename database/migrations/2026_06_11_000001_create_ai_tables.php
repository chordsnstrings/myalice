<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-workspace LLM providers (Anthropic/OpenAI/Gemini/OpenAI-compatible).
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // anthropic | openai | gemini | openai_compatible
            $table->string('name');
            $table->text('credentials'); // encrypted:array — { api_key, base_url?, model }
            $table->string('status')->default('connected');
            $table->boolean('is_default')->default(false);
            $table->unsignedTinyInteger('fallback_order')->default(0);
            $table->timestamps();
            $table->index(['workspace_id', 'is_default']);
        });

        // Per-workspace AI sales-agent profile (one 'all' row + optional per-channel).
        Schema::create('ai_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Sales Assistant');
            $table->boolean('enabled')->default(true);
            $table->string('mode')->default('auto');     // off | suggest | auto | autopilot
            $table->string('goal')->default('sale');     // sale | lead | support
            $table->string('channel_scope')->default('all');
            $table->string('tone')->default('friendly');
            $table->string('methodology')->default('consultative_spin');
            $table->text('custom_instructions')->nullable();
            $table->text('business_profile')->nullable();
            $table->json('guardrails')->nullable();
            $table->foreignId('ai_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['workspace_id', 'channel_scope']);
        });

        // Append-only log of AI activity for conversion tracking.
        Schema::create('ai_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ai_agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // reply|draft|create_lead|create_order|send_payment_link|handoff|error
            $table->json('payload')->nullable();
            $table->string('status')->default('ok'); // ok | failed
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['workspace_id', 'type', 'created_at']);
            $table->index(['conversation_id']);
        });

        // Per-conversation AI engagement state.
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('ai_status')->nullable()->after('awaiting_csat_at'); // active|handed_off|suppressed
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('ai_status');
        });
        Schema::dropIfExists('ai_actions');
        Schema::dropIfExists('ai_agents');
        Schema::dropIfExists('ai_providers');
    }
};
