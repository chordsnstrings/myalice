<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Multi-workspace membership: switch the active workspace and create new ones.
 * `users.workspace_id` / `workspace_role` hold the active selection; the
 * workspace_user pivot governs membership + per-workspace role.
 */
class WorkspaceController extends Controller
{
    /** Switch the active workspace (must be a member). */
    public function switch(Request $request, Workspace $workspace): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isMemberOf($workspace->id), 403);

        $user->update([
            'workspace_id' => $workspace->id,
            'workspace_role' => $user->roleIn($workspace->id) ?? 'agent',
        ]);

        return redirect('/inbox')->with('success', "Switched to {$workspace->name}");
    }

    /** Create a new workspace and make the creator its owner + active workspace. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $workspace = Workspace::create([
            'name' => $data['name'],
            'plan' => 'premium',
            'wallet_balance' => 0,
        ]);

        $workspace->members()->attach($user->id, ['workspace_role' => 'owner']);
        $user->update(['workspace_id' => $workspace->id, 'workspace_role' => 'owner']);

        return redirect('/inbox')->with('success', "“{$workspace->name}” is ready.");
    }
}
