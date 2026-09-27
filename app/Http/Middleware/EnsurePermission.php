<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side permission gate.
 *
 * Route visibility in the sidebar is UX only; this is the actual enforcement.
 * A user without the permission gets 403 even when typing the URL directly.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // A disabled account can hold valid permissions but still must not act.
        if (! $user->isActive()) {
            $this->logout($request);

            return redirect()->route('login')
                ->withErrors(['email' => 'Akun Anda telah dinonaktifkan.']);
        }

        $required = array_filter(array_map('trim', $permissions));

        if ($required === []) {
            return $next($request);
        }

        foreach ($required as $permission) {
            if (! $user->can($permission)) {
                abort(403, 'Anda tidak memiliki izin untuk tindakan ini.');
            }
        }

        return $next($request);
    }

    private function logout(Request $request): void
    {
        auth()->guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
