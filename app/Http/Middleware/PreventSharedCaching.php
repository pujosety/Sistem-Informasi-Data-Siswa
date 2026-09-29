<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops a CDN from storing a response that carries per-visitor state.
 *
 * WHY THIS EXISTS
 *
 * A Vercel function is torn down between requests, so every visitor gets a
 * fresh container. If the edge also caches the response, the login page comes
 * back from cache carrying a CSRF token that was minted for the FIRST
 * visitor's session, while the browser now holds a session cookie issued by a
 * different container. The token and the session no longer match and the form
 * submission is rejected with 419 "Sesi kedaluwarsa".
 *
 * The mismatch is not a race. It is structural: there is no way for a cached
 * page and a live session to agree, because they are produced by two different
 * application instances. The symptom looks like a session timeout to the user
 * and like a CSRF bug to the operator, and neither reading points at caching.
 *
 * WHY ONLY THESE ROUTES
 *
 * Anything with a session in it must be private. Static assets under /build
 * and /branding are content-addressed by hash, so they SHOULD be cached
 * publicly and skipping them would only make the site slower. The
 * Authorization header is a second, independent reason: a shared cache that
 * stores one visitor's authenticated HTML and serves it to the next is a
 * disclosure, not just a stale token.
 *
 * This is deliberately a header-only change. It removes no feature and alters
 * no response body.
 */
class PreventSharedCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->mustNotBeShared($request, $response)) {
            // no-store is the instruction that matters: it forbids storing the
            // response at all. The others are belt and braces for proxies that
            // only honour the older directives.
            $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            // A cached response must never be reused after a revalidation
            // failure either.
            $response->headers->set('Vary', trim($response->headers->get('Vary', '').' Cookie, Authorization', ', '));

            return $response;
        }

        return $response;
    }

    private function mustNotBeShared(Request $request, Response $response): bool
    {
        // An authenticated response is never shareable, whatever the route.
        if ($request->user() !== null) {
            return true;
        }

        // A Set-Cookie means this response established per-visitor state.
        if ($response->headers->has('Set-Cookie')) {
            return true;
        }

        // A CSRF token is minted into the body; sharing that is the bug above.
        if ($request->isMethod('GET') && $this->bodyCarriesCsrfToken($response)) {
            return true;
        }

        /*
         * A request that already carries a session cookie is a returning
         * visitor. Their page depends on that session, so it is private even
         * when the response itself sets no cookie — which is the case on every
         * GET after the first.
         *
         * This check comes last on purpose. It is the broadest signal, so
         * anything more specific above it keeps its own reason on record.
         */
        return $request->hasCookie((string) config('session.cookie'));
    }

    private function bodyCarriesCsrfToken(Response $response): bool
    {
        $contentType = (string) $response->headers->get('Content-Type', '');

        if (! str_contains($contentType, 'text/html')) {
            return false;
        }

        $content = $response->getContent();

        // Only read a bounded prefix: the check is for the hidden input, and a
        // full-body scan on every request would cost more than it is worth.
        return is_string($content) && str_contains(substr($content, 0, 200_000), 'name="_token"');
    }
}
