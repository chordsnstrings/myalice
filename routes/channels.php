<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Only members of a workspace may subscribe to its private channel.
Broadcast::channel('workspace.{workspaceId}', function (User $user, int $workspaceId) {
    return (int) $user->workspace_id === $workspaceId;
});
