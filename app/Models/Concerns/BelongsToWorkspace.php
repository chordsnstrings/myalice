<?php

namespace App\Models\Concerns;

use App\Models\Scopes\WorkspaceScope;
use App\Models\Workspace;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Row-level multi-tenancy (single DB). Every tenant-owned model uses this trait:
 * it applies a global scope filtering by the active workspace and auto-fills
 * workspace_id on create. No cross-tenant leakage — enforced in tests (G0.3).
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(new WorkspaceScope);

        static::creating(function ($model): void {
            if (! $model->workspace_id && Tenancy::id()) {
                $model->workspace_id = Tenancy::id();
            }
        });
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
