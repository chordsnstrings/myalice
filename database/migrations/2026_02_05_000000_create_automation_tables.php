<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('channel_scope')->default('all');
            $table->string('status')->default('draft'); // live, paused, draft
            $table->json('graph')->nullable(); // nodes + edges
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger_type'); // abandoned_cart, order_confirmation, shipping, upsell, feedback, re_engagement, welcome
            $table->json('conditions')->nullable();
            $table->json('timing')->nullable();
            $table->foreignId('message_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('paused'); // active, paused
            $table->unsignedInteger('sent')->default(0);
            $table->decimal('recovered_revenue', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
        Schema::dropIfExists('chatbots');
    }
};
