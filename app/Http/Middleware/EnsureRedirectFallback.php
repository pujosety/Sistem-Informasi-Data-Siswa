<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarantees a redirect target for every request.
 *
 * Laravel's validation handler calls back(), which throws when there is no
 * referer and no previous URL — exactly what happens for JSON, CLI and test
 * callers. That turned a friendly "please fix this field" redirect into a 500.
 * Setting a fallback URL keeps the UX correct without weakening any check.
 */
class EnsureRedirectFallback
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && ! $request->headers->has('referer')) {
            $request->headers->set('referer', url('/dashboard'));
        }

        return $next($request);
    }
}
