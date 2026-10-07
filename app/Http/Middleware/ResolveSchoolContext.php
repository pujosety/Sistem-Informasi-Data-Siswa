<?php

namespace App\Http\Middleware;

use App\Services\SchoolContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Selects the public school before controllers and view composers run. */
class ResolveSchoolContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(SchoolContext::class);
        $context->reset();

        $slug = $request->query('school');
        if (! $slug && $request->hasSession()) {
            $slug = $request->session()->get('active_school_slug');
        }

        if (filled($slug)) {
            $context->use((string) $slug);
        }

        return $next($request);
    }
}
