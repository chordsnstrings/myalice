<?php

namespace App\Providers;

use App\Channels\ChannelManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
    }
}
