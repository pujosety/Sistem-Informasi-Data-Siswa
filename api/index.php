<?php

/**
 * Vercel entry point for SIDA.
 *
 * WHY A FORWARDER EXISTS
 *
 * Vercel requires a function's entry point to live in the `api/` directory, but
 * Laravel's front controller is `public/index.php`. This file is the thing
 * Vercel invokes; it hands off to the real one.
 *
 * It could not simply `require` the front controller. The PHP built-in server
 * that vercel-php spawns uses THIS file as the router script, and a router
 * script is re-included on every request. `require` would then re-run the whole
 * Laravel bootstrap a second time per request, on top of the one the server
 * already performed, which corrupts global state and leaks memory until the
 * container is recycled.
 *
 * The router-script branch below therefore returns after a single
 * `require_once` — a router script must produce the response itself, not fall
 * through into more output — while the direct-execution branch exists so
 * `php -S localhost:8000 api/index.php` keeps working during local testing.
 *
 * WHY $_SERVER IS REBUILT RATHER THAN ASSUMED
 *
 * When the built-in server runs a request through a router script, several
 * server variables describe the ROUTER rather than the request that was
 * actually made, and a handful of the real ones are missing entirely. Booting
 * Laravel on those produces failures that look unrelated to this file:
 *
 *   - SCRIPT_NAME/PHP_SELF are the router (`/api/index.php`), so every
 *     url()->current(), route() URL and form action points visitors at an
 *     endpoint that does not exist publicly.
 *   - SCRIPT_FILENAME is the router's path, so Laravel's own maintenance-mode
 *     check and any relative path resolution walks the wrong tree.
 *   - REQUEST_URI is intact, so the router branch can rebuild the rest from it.
 *
 * The values are copied from the trusted environment the server does provide
 * (the HTTP_* server variables) and from the original request URI, never from
 * client-supplied headers, because URL generation is exactly the thing being
 * corrected here.
 */

$frontController = __DIR__.'/../public/index.php';

if (! is_file($frontController)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "SIDA front controller missing at {$frontController}.\n";

    exit;
}

if (PHP_SAPI === 'cli-server') {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';

    // A router script is handed every request, including ones for real files
    // under the document root (the compiled Vite assets, the PWA manifest and
    // service worker, the favicons, robots.txt). Returning false hands the
    // request back to the built-in server so it serves the file itself, with
    // the right content type and range support, instead of routing an asset
    // request into Laravel.
    if ($method === 'GET' || $method === 'HEAD') {
        $candidate = __DIR__.'/../public'.$path;

        if ($path !== '/' && is_file($candidate) && ! str_ends_with($candidate, '.php')) {
            return false;
        }
    }

    // Rebuild the request-shaped server variables from what the server gives us.
    //
    // REQUEST_URI is the untouched original — the built-in server sets it
    // before consulting the router, which is why the path above could be parsed
    // out of it at all. QUERY_STRING is recovered from it because the server
    // empties it while a router script runs.
    $parts = parse_url($uri);

    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = realpath($frontController) ?: $frontController;
    $_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__.'/../public') ?: __DIR__.'/../public';

    if (isset($parts['query'])) {
        $_SERVER['QUERY_STRING'] = $parts['query'];
    } else {
        $_SERVER['QUERY_STRING'] = '';
    }

    if (isset($_SERVER['HTTP_HOST'])) {
        $_SERVER['SERVER_NAME'] = explode(':', (string) $_SERVER['HTTP_HOST'])[0];
    }
}

require_once $frontController;
