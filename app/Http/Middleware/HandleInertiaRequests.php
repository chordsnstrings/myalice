<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props shared with every Inertia response (auth, flash, locale).
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $workspace = $user?->currentWorkspace;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->workspace_role ?? 'owner',
                    'avatar' => $user->avatar ?? null,
                ] : null,
                'workspace' => $workspace ? [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'plan' => $workspace->plan,
                    'wallet_balance' => (float) $workspace->wallet_balance,
                    'currency' => $workspace->currency,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'locale' => app()->getLocale(),
        ];
    }
}
