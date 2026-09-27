<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            // Legacy role guard, kept so existing routes keep working.
            'role' => EnsureRole::class,
            // The real enforcement going forward.
            'can' => \App\Http\Middleware\EnsurePermission::class,
        ]);

        // Runs for every request so validation errors can always redirect.
        $middleware->append(\App\Http\Middleware\EnsureRedirectFallback::class);

        $middleware->redirectGuestsTo(fn () => route('login'));

        // Managed hosts (Wasmer included) terminate TLS in front of PHP, so the
        // request Laravel sees is plain HTTP. Without trusting the proxy,
        // url()/asset()/route() would emit http:// and secure cookies would
        // never be set — a redirect loop or a silent logout.
        //
        // '*' is safe here because the app is only ever exposed through that
        // proxy; set TRUSTED_PROXIES to a comma-separated list to narrow it.
        $proxies = env('TRUSTED_PROXIES', '*');
        $middleware->trustProxies(
            $proxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', (string) $proxies)))),
            Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // One polished template for every HTTP error; no stack traces leak in production.
        $exceptions->render(function (Throwable $e, $request) {
            $accept = (string) $request->headers->get('accept');

            if (! str_contains($accept, 'text/html')) {
                return null;
            }

            // AuthenticationException carries NO status code, so the
            // method_exists() check below classified it as a 500 and our
            // renderer swallowed Laravel's guest→login redirect. It must be
            // handed back to the framework so the auth middleware can do its job.
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return null;
            }

            // Only real HTTP exceptions carry a status code. A raw Error /
            // QueryException has none, and calling getStatusCode() on those
            // would itself crash the renderer.
            $status = method_exists($e, 'getStatusCode') ? (int) $e->getStatusCode() : 500;

            // ValidationException is how the framework says "redirect back
            // with field errors". Rendering it as a 500 destroyed that UX and
            // was the real cause of a 500 on every invalid form submission.
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return null;
            }

            // Same for session/auth/Throttle problems.
            if (in_array($status, [401, 419, 422, 429], true)) {
                return null;
            }

            // A missing model in production is a 404, not a 500.
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                $status = 404;
            }

            return response()->view('errors.minimal', [
                'exception' => $e,
                'status' => $status,
            ], $status);
        });

        $exceptions->render(function (UnauthorizedException $e, $request) {
            abort(403, 'Anda tidak memiliki izin untuk tindakan ini.');
        });
    })->create();
