<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Exceptions\UnauthorizedException;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            /*
             | The public school website takes the root route HERE, in the `then`
             | hook, and not in routes/web.php.
             |
             | Laravel registers its default `home` route AFTER the application's
             | own routes, and registers it as `ANY` — so it answers `/` for
             | every method and, being later in the collection, is matched first.
             | Defining the same path in web.php is not enough: the route is
             | registered correctly and is still never reached, which looks
             | exactly like the application route being missing.
             |
             | `then` runs after every other route file has been loaded, so
             | registering here puts ours last and therefore first in matching
             | order. The name stays `home` so any existing `route('home')` call
             | keeps working.
             */
            Route::get('/', [\App\Http\Controllers\PublicHomeController::class, '__invoke'])
                ->name('home');

            // The rest of the public school website. Indonesian paths, matching
            // the rest of the application; no auth, and none of them load a
            // student record.
            //
            // `/tentang`, not `/profil`. `/profil` is already the STUDENT's
            // profile — profile.edit, profile.update and profile.password — and
            // registering a second route on that path shadowed all three,
            // which took out the topbar and nav on every authenticated page.
            // The public page needed a name that was actually free.
            Route::get('/tentang', [\App\Http\Controllers\PublicHomeController::class, 'profile'])
                ->name('public.about');
            Route::get('/program', [\App\Http\Controllers\PublicHomeController::class, 'programs'])
                ->name('public.programs');
            Route::get('/ppdb', [\App\Http\Controllers\PublicHomeController::class, 'admission'])
                ->name('public.admission');
            Route::get('/kontak', [\App\Http\Controllers\PublicHomeController::class, 'contact'])
                ->name('public.contact');

            /* TEMPORARY deployment diagnostic. Registered directly rather than
               in routes/web.php, because everything there is wrapped in the
               `web` group — StartSession included — so a session failure killed
               the diagnostic before its controller could report the failure.
               DELETE once the production cause is resolved. */
            Route::get('/__diag', \App\Http\Controllers\DiagnosticController::class)
                ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class);
        },
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

        /*
         | Forbids shared caching of any response that carries per-visitor state.
         |
         | On a host that destroys the container between requests, a cached
         | login page and a live session can never agree: the page is replayed
         | from one application instance while the browser holds a session
         | cookie minted by another, so the CSRF token in the form does not
         | match and every submission is rejected with 419. The mismatch is
         | structural, not a race, so no amount of session tuning fixes it —
         | the page simply must not be stored.
         |
         | Appended rather than prepended so it wraps everything that might set
         | a cookie, including the session middleware. See
         | @see \App\Http\Middleware\PreventSharedCaching for the full reasoning.
         */
        $middleware->append(\App\Http\Middleware\PreventSharedCaching::class);

        $middleware->redirectGuestsTo(fn () => route('login'));

        // Managed hosts (Wasmer included) terminate TLS in front of PHP, so the
        // request Laravel sees is plain HTTP. Without trusting the proxy,
        // url()/asset()/route() would emit http:// and secure cookies would
        // never be set — a redirect loop or a silent logout.
        //
        // '*' is safe here because the app is only ever exposed through that
        // proxy; set TRUSTED_PROXIES to a comma-separated list to narrow it.
        /*
         | An EMPTY or whitespace-only TRUSTED_PROXIES must still mean "trust the
         | proxy", not "trust nobody".
         |
         | It used to be passed straight through, so an empty value became an
         | empty array. Proven consequence on a platform that terminates TLS in
         | front of PHP: Request::isSecure() returned false even though
         | X-Forwarded-Proto said https. Secure session cookies were then
         | dropped by the browser, every request looked logged out, and the
         | guest->login redirect cycle rendered as a 500.
         |
         | The default stays '*', and now an explicitly empty value resolves to
         | the same thing instead of silently disabling proxy trust.
         */
        $raw = trim((string) env('TRUSTED_PROXIES', '*'));
        $proxies = ($raw === '' || $raw === '*')
            ? '*'
            : array_values(array_filter(array_map('trim', explode(',', $raw))));

        $middleware->trustProxies(
            $proxies,
            // X-Forwarded-Host was missing here, so the public hostname was
            // dropped and url() used the backend's internal name. That also
            // broke the host Laravel compares against, which is what surfaces
            // as a redirect loop behind a proxy.
            Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
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
