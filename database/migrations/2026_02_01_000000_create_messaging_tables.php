<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // whatsapp, instagram, ...
            $table->string('name');
            $table->string('external_id')->nullable();
            $table->string('status')->default('connected'); // connected, action_needed, pending, disconnected
            $table->timestamps();
            $table->index(['workspace_id', 'type']);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('status')->default('open'); // open, pending, resolved
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('unread')->default(0);
            $table->boolean('window_open')->default(true);
            $table->boolean('sla_breaching')->default(false);
            $table->string('last_message')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'last_message_at']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('direction'); // in, out
            $table->string('author'); // customer, agent, bot, system
            $table->text('body');
            $table->string('status')->nullable(); // sending, sent, delivered, read, failed, queued
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->index(['conversation_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('channels');
    }
};
