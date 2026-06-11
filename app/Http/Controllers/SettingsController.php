<?php

namespace App\Http\Controllers;

use App\Models\BusinessHour;
use App\Models\Channel;
use App\Models\QuickReply;
use App\Models\Subscription;
use App\Models\Tag;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /** Workspace / general (B11.1). */
    public function workspace(): Response
    {
        $ws = Tenancy::currentOrFail();

        return Inertia::render('Settings/Workspace', [
            'workspace' => [
                'name' => $ws->name,
                'locale' => $ws->locale,
                'timezone' => $ws->timezone,
                'currency' => $ws->currency,
            ],
        ]);
    }

    /** Team & roles (B11.2) — scoped to the current workspace's members. */
    public function team(): Response
    {
        $ws = Tenancy::currentOrFail();

        $members = $ws->members()->orderBy('name')->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->getAttribute('pivot')->workspace_role,
            'status' => 'active',
        ]);

        return Inertia::render('Settings/Team', [
            'members' => $members,
            'subscription' => $this->subscription(),
        ]);
    }

    /** Add a teammate to the current workspace (existing user or a new one). */
    public function addMember(Request $request): RedirectResponse
    {
        $ws = Tenancy::currentOrFail();

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(['owner', 'manager', 'agent', 'developer'])],
        ]);

        $user = User::firstWhere('email', $data['email']);
        if (! $user) {
            $user = User::create([
                'name' => $data['name'] ?: (string) strtok($data['email'], '@'),
                'email' => $data['email'],
                'password' => Hash::make(Str::random(40)),
                'workspace_id' => $ws->id,
                'workspace_role' => $data['role'],
            ]);
        }

        if ($ws->members()->whereKey($user->id)->exists()) {
            return back()->withErrors(['email' => 'Already a member of this workspace.']);
        }

        $ws->members()->attach($user->id, ['workspace_role' => $data['role']]);

        return back()->with('success', "{$user->name} was added.");
    }

    /** Remove a teammate from the current workspace. */
    public function removeMember(Request $request, User $member): RedirectResponse
    {
        $ws = Tenancy::currentOrFail();
        abort_unless($ws->members()->whereKey($member->id)->exists(), 404);
        abort_if($member->id === $request->user()->id, 403, "You can't remove yourself.");

        $isOwner = $ws->members()->whereKey($member->id)->first()->getAttribute('pivot')->workspace_role === 'owner';
        if ($isOwner && $ws->members()->wherePivot('workspace_role', 'owner')->count() <= 1) {
            return back()->withErrors(['member' => 'A workspace needs at least one owner.']);
        }

        $ws->members()->detach($member->id);

        // If this was their active workspace, move them to another membership.
        if ($member->workspace_id === $ws->id) {
            $next = $member->workspaces()->first();
            $member->update([
                'workspace_id' => $next?->id,
                'workspace_role' => $next ? $next->getAttribute('pivot')->workspace_role : 'agent',
            ]);
        }

        return back()->with('success', "{$member->name} was removed.");
    }

    /** Quick replies & tags (B11.4). */
    public function content(): Response
    {
        return Inertia::render('Settings/Content', [
            'quick_replies' => QuickReply::orderBy('shortcut')->get(['id', 'shortcut', 'body']),
            'tags' => Tag::orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    /** Business hours (B11.3). */
    public function hours(): Response
    {
        return Inertia::render('Settings/Hours', [
            'hours' => BusinessHour::orderBy('day')->get(),
            'timezone' => Tenancy::currentOrFail()->timezone,
        ]);
    }

    /** Channels list + onboarding metadata (B9.1 / B9.2). */
    public function channels(): Response
    {
        $connected = Channel::get()->keyBy('type');
        $all = ['whatsapp', 'instagram', 'messenger', 'telegram', 'line', 'viber', 'web'];
        $meta = ['whatsapp', 'instagram', 'messenger'];

        $channels = collect($all)->map(fn ($type) => [
            'type' => $type,
            'name' => $connected[$type]->name ?? null,
            'external_id' => $connected[$type]->external_id ?? null,
            'status' => $connected[$type]->status ?? 'not_connected',
            'onboardable' => in_array($type, $meta, true),
            'webhook_url' => in_array($type, $meta, true) ? url("/api/webhooks/{$type}") : null,
            'verify_token' => in_array($type, $meta, true) ? config("services.{$type}.verify_token") : null,
        ]);

        return Inertia::render('Settings/Channels', [
            'channels' => $channels,
            'embedded' => [
                'configured' => filled(config('services.meta.app_id')),
                'app_id' => config('services.meta.app_id'),
                'graph_version' => config('services.meta.graph_version', 'v21.0'),
                'config_id' => [
                    'whatsapp' => config('services.meta.config_id.whatsapp'),
                    'messenger' => config('services.meta.config_id.messenger'),
                    'instagram' => config('services.meta.config_id.instagram'),
                ],
            ],
        ]);
    }

    /** Billing & subscription (B11.5). */
    public function billing(): Response
    {
        return Inertia::render('Settings/Billing', [
            'subscription' => $this->subscription(),
        ]);
    }

    /** Wallet / prepaid credits (B11.6). */
    public function wallet(): Response
    {
        $ws = Tenancy::currentOrFail();

        $ledger = WalletTransaction::latest()->get()->map(fn (WalletTransaction $t) => [
            'id' => $t->id,
            'type' => $t->type,
            'amount' => (float) $t->amount,
            'balance_after' => (float) $t->balance_after,
            'description' => $t->description,
            'created_at' => $t->created_at->toIso8601String(),
        ]);

        return Inertia::render('Settings/Wallet', [
            'balance' => (float) $ws->wallet_balance,
            'currency' => $ws->currency,
            'ledger' => $ledger,
        ]);
    }

    /** Developer / API & webhooks (B11.7). */
    public function developer(): Response
    {
        return Inertia::render('Settings/Developer');
    }

    /** Profile & notifications (B11.8). */
    public function profile(): Response
    {
        return Inertia::render('Settings/Profile');
    }

    /** Web chat widget config (B12.1). */
    public function widget(): Response
    {
        $channel = Channel::where('type', 'whatsapp')->first();

        return Inertia::render('Settings/Widget', [
            'workspace_id' => Tenancy::currentOrFail()->id,
            'whatsapp' => $channel?->external_id,
        ]);
    }

    /** QR codes & link generator (B12.2). */
    public function qr(): Response
    {
        return Inertia::render('Settings/Qr', [
            'phone' => '15551234567',
        ]);
    }

    /** @return array{plan: string, billing_cycle: string, seats: int, status: string, renews_at: string|null} */
    private function subscription(): array
    {
        $sub = Subscription::first();

        if (! $sub) {
            return ['plan' => 'premium', 'billing_cycle' => 'monthly', 'seats' => 5, 'status' => 'trialing', 'renews_at' => null];
        }

        return [
            'plan' => $sub->plan,
            'billing_cycle' => $sub->billing_cycle,
            'seats' => $sub->seats,
            'status' => $sub->status,
            'renews_at' => optional($sub->renews_at)->toIso8601String(),
        ];
    }
}
