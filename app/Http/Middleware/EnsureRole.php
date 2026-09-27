<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $allowed = array_map(fn ($r) => trim($r), $roles);
        $has = $allowed === [] || $user->hasAnyRole($allowed);

        if (! $has) {
            abort(403, 'Anda tidak memiliki akses ke bagian ini.');
        }

        return $next($request);
    }
}
