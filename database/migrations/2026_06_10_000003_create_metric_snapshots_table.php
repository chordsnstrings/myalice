<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Daily pre-aggregated rollups backing multi-day trend lines. Stores
        // sums + counts (never pre-divided averages) so any range re-aggregates.
        Schema::create('metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->string('channel')->nullable();    // null = all-channels rollup
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete(); // null = all-agents

            $table->unsignedInteger('conversations')->default(0);
            $table->unsignedInteger('resolved')->default(0);
            $table->unsignedBigInteger('first_response_seconds_sum')->default(0);
            $table->unsignedInteger('first_response_count')->default(0);
            $table->unsignedBigInteger('resolution_seconds_sum')->default(0);
            $table->unsignedInteger('resolution_count')->default(0);
            $table->unsignedInteger('csat_sum')->default(0);
            $table->unsignedInteger('csat_count')->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->unsignedInteger('orders')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'day', 'channel', 'agent_id'], 'metric_snapshot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_snapshots');
    }
};
