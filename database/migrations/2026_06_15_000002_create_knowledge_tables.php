<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent knowledge: sources (a website URL, a Facebook Page, or pasted text) that
 * are fetched into plain-text snippets and injected into the system prompt.
 * `ai_agent_id` null = shared across the workspace's agents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_agent_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type'); // website | facebook_page | manual
            $table->string('url')->nullable();
            $table->string('title');
            $table->string('status')->default('pending'); // pending | fetched | error
            $table->timestamp('last_fetched_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'ai_agent_id']);
        });

        Schema::create('knowledge_snippets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('knowledge_source_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('content');
            $table->unsignedInteger('char_count')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'knowledge_source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_snippets');
        Schema::dropIfExists('knowledge_sources');
    }
};
