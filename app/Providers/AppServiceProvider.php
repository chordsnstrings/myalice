<?php

namespace App\Providers;

use App\Channels\ChannelManager;
use App\Models\User;
use App\Support\Plans;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChannelManager::class);
    }

    public function boot(): void
    {
        // Per-workspace API rate limit (M19-FR-04); falls back to the user/IP.
        RateLimiter::for('api', function (Request $request) {
            // The 'api' limiter only runs behind auth:sanctum, so a user is present.
            $key = $request->user()->workspace_id ?? $request->ip();

            return Limit::perMinute(120)->by((string) $key);
        });

        // Role-to-capability gates (§4.3). Keyed off the user's workspace role.
        Gate::define('manage-billing', fn (User $u) => $u->workspace_role === 'owner');
        Gate::define('manage-team', fn (User $u) => in_array($u->workspace_role, ['owner', 'manager'], true));
        Gate::define('manage-channels', fn (User $u) => in_array($u->workspace_role, ['owner', 'manager'], true));
        Gate::define('manage-bots', fn (User $u) => in_array($u->workspace_role, ['owner', 'manager'], true));
        Gate::define('manage-api', fn (User $u) => in_array($u->workspace_role, ['owner', 'developer'], true));

        // Plan-based feature gate (§10). Used by the nav lock and feature routes.
        Gate::define('use-automation', fn (User $u) => Plans::includes(optional($u->currentWorkspace)->plan ?? 'premium', 'automation'));
    }
}
