<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('csat_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel');                // denormalized for fast per-channel grouping
            $table->unsignedTinyInteger('rating');    // 1..5
            $table->text('comment')->nullable();
            $table->timestamp('rated_at');
            $table->timestamps();

            $table->index(['workspace_id', 'rated_at']);
            $table->index(['workspace_id', 'agent_id', 'rated_at']);
            $table->index(['workspace_id', 'channel', 'rated_at']);
        });

        // Per-workspace toggle for automatic post-resolution CSAT surveys.
        Schema::table('workspaces', function (Blueprint $table) {
            $table->boolean('csat_enabled')->default(true)->after('billing_status');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('csat_enabled');
        });

        Schema::dropIfExists('csat_ratings');
    }
};
