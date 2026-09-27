<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Screenshot / documentation session helper.
 *
 * A headless browser has no interactive login, so this issues the SAME session
 * a normal login would — same guard, same session regeneration, same
 * last_login_at bookkeeping — and returns the plain session cookie.
 *
 * It is deliberately not a backdoor: it is gated on LOCAL_DEBUG_HELPER=1 in
 * .env, refuses to run in production, and is never enabled by the shipped
 * .env.example. Without that flag the routes are not registered at all, so a
 * production deployment cannot expose them.
 */
class ScreenshotSessionController extends Controller
{
    public function __construct(private readonly Request $request) {}

    /**
     * Log in and hand back the session cookie for the given email.
     */
    public function login(Request $request)
    {
        $this->guard();

        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json(['ok' => false, 'reason' => 'no such user'], 404);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return response()->json([
            'ok' => true,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name'),
        ]);
    }

    /** Log the current session out. */
    public function logout(Request $request)
    {
        $this->guard();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    /**
     * Blocked in production, and blocked entirely unless the opt-in flag is set.
     */
    private function guard(): void
    {
        abort_if(app()->environment('production'), 404);

        abort_unless(
            (bool) env('LOCAL_DEBUG_HELPER'),
            404,
            'Screenshot session helper is disabled. Set LOCAL_DEBUG_HELPER=1 in .env to enable it locally.',
        );
    }
}
