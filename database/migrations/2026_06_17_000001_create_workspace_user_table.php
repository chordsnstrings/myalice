<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-workspace membership: a user can belong to many workspaces, each with its
 * own role. `users.workspace_id` / `users.workspace_role` remain the *active*
 * selection (driven by the switcher); this pivot is the source of truth for who
 * may access which workspace. Existing single-workspace users are backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('workspace_role')->default('agent');
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
        });

        // Backfill membership from each user's current workspace.
        $now = now();
        $rows = DB::table('users')
            ->whereNotNull('workspace_id')
            ->get(['id', 'workspace_id', 'workspace_role']);

        foreach ($rows as $u) {
            DB::table('workspace_user')->insertOrIgnore([
                'workspace_id' => $u->workspace_id,
                'user_id' => $u->id,
                'workspace_role' => $u->workspace_role ?? 'agent',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_user');
    }
};
