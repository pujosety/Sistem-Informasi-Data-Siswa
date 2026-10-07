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
        app(SchoolContext::class)->reset();

        return $next($request);
    }
}
