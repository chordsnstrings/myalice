<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active UI locale (session → workspace default → app default).
 * RTL mirroring for Arabic is handled in the root view via the `dir` attribute (A13).
 */
class SetLocale
{
    /** @var list<string> */
    protected array $supported = ['en', 'ar', 'es', 'pt'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', config('app.locale'));

        if (is_string($locale) && in_array($locale, $this->supported, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
