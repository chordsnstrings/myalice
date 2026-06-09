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

    /** Team & roles (B11.2). */
    public function team(): Response
    {
        $members = User::orderBy('name')->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->workspace_role,
            'status' => 'active',
        ]);

        return Inertia::render('Settings/Team', [
            'members' => $members,
            'subscription' => $this->subscription(),
        ]);
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

    /** Channels list (B9.1). */
    public function channels(): Response
    {
        $connected = Channel::get()->keyBy('type');
        $all = ['whatsapp', 'instagram', 'messenger', 'telegram', 'line', 'viber', 'web'];

        $channels = collect($all)->map(fn ($type) => [
            'type' => $type,
            'name' => $connected[$type]->name ?? null,
            'status' => $connected[$type]->status ?? 'not_connected',
        ]);

        return Inertia::render('Settings/Channels', ['channels' => $channels]);
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
