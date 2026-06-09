<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active workspace from the authenticated user (or session
 * override for multi-workspace switching) and binds it for the request (G0.3).
 */
class SetCurrentWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $workspace = $user->currentWorkspace;

            if ($workspace) {
                Tenancy::set($workspace);
            }
        }

        return $next($request);
    }
}
