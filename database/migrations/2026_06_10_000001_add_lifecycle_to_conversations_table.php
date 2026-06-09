<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('first_response_at')->nullable()->after('sla_breaching');
            $table->timestamp('assigned_at')->nullable()->after('first_response_at');
            $table->timestamp('resolved_at')->nullable()->after('assigned_at');
            $table->timestamp('awaiting_csat_at')->nullable()->after('resolved_at');

            $table->index(['workspace_id', 'resolved_at']);
            $table->index(['workspace_id', 'assignee_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'resolved_at']);
            $table->dropIndex(['workspace_id', 'assignee_id', 'created_at']);
            $table->dropColumn(['first_response_at', 'assigned_at', 'resolved_at', 'awaiting_csat_at']);
        });
    }
};
