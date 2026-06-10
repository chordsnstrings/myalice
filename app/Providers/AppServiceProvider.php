<?php

namespace App\Providers;

use App\Channels\ChannelManager;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Observers\ConversationObserver;
use App\Observers\MessageObserver;
use App\Support\Plans;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChannelManager::class);
    }

    public function boot(): void
    {
        // Force HTTPS URL generation in production (defence in depth alongside
        // the .htaccess redirect and trusted proxies).
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Per-workspace API rate limit (M19-FR-04); falls back to the user/IP.
        RateLimiter::for('api', function (Request $request) {
            // The 'api' limiter only runs behind auth:sanctum, so a user is present.
            $key = $request->user()->workspace_id ?? $request->ip();

            return Limit::perMinute(120)->by((string) $key);
        });

        // Auth endpoints: throttle brute force, keyed by email + IP so one
        // attacker can't lock out a victim by guessing their address (C-24).
        RateLimiter::for('auth', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                Limit::perMinute(5)->by(mb_strtolower($email).'|'.$request->ip()),
                Limit::perMinute(20)->by((string) $request->ip()),
            ];
        });

        // Role-to-capability gates (§4.3). Keyed off the user's workspace role.
        Gate::define('manage-billing', fn (User $u) => $u->workspace_role === 'owner');
        Gate::define('manage-team', fn (User $u) => in_array($u->workspace_role, ['owner', 'manager'], true));
        Gate::define('manage-channels', fn (User $u) => in_array($u->workspace_role, ['owner', 'manager'], true));
        Gate::define('manage-bots', fn (User $u) => in_array($u->workspace_role, ['owner', 'manager'], true));
        Gate::define('manage-api', fn (User $u) => in_array($u->workspace_role, ['owner', 'developer'], true));

        // Plan-based feature gate (§10). Used by the nav lock and feature routes.
        Gate::define('use-automation', fn (User $u) => Plans::includes(optional($u->currentWorkspace)->plan ?? 'premium', 'automation'));
        Gate::define('use-ai-agents', fn (User $u) => Plans::includes(optional($u->currentWorkspace)->plan ?? 'premium', 'ai_agents'));

        // Lifecycle capture for analytics (first-response / resolution / assignment).
        Conversation::observe(ConversationObserver::class);
        Message::observe(MessageObserver::class);
    }
}
