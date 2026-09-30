<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Why is the login 419?
 *
 * A 419 means the CSRF token in the form and the one in the session do not
 * match. The token is stored IN the session, so a 419 on a first-ever login
 * means the session is not surviving between the GET and the POST.
 *
 * The two candidates, and they need opposite fixes:
 *
 *   - the session write is failing (driver misconfigured, table missing, DB
 *     unreachable on write but readable on read) — the application is broken
 *     and no user can log in at all;
 *   - the session IS persisting and the cookie is not coming back — the
 *     harness is at fault and the application is fine.
 *
 * So the probe prints the cookie value it received, the cookie value it sent
 * back, and whether they are the same. That single comparison separates the
 * two without guessing.
 */
class DiagnoseLoginCommand extends Command
{
    protected $signature = 'sida:diagnose-login {--base=https://sida-4136.wasmer.app}';

    protected $description = 'Find out why signing in returns 419';

    public function handle(): int
    {
        $base = rtrim((string) $this->option('base'), '/');

        $first = Http::withOptions(['verify' => true])->get("$base/login");

        /*
         * Guzzle joins several Set-Cookie headers into one string with ", ", so
         * the cookies have to be split on THAT boundary first. Splitting on ";"
         * — the obvious move — cuts the combined header in half and the
         * "laravel-session" line it finds is really the tail of XSRF-TOKEN's
         * attribute list, which is why the first version of this probe reported
         * a session id that changed on every request.
         */
        $cookies = $this->cookies($first->header('Set-Cookie'));
        $sessionCookie = $cookies['laravel-session'] ?? null;

        $this->line('GET /login  ->  '.$first->status());
        $this->line('  Set-Cookie present : '.($cookies !== [] ? 'yes' : 'NO'));
        $this->line('  laravel-session     : '.($sessionCookie !== null ? 'received' : 'MISSING'));

        foreach ($cookies as $name => $pair) {
            $attrs = array_map('trim', array_slice(explode(';', $pair), 1));
            // Names and attributes only. A session id is a credential.
            $this->line("    {$name}  [".implode('; ', $attrs).']');
        }

        if ($sessionCookie === null) {
            $this->error('No session cookie at all. Laravel issued no session — check SESSION_DRIVER.');

            return self::FAILURE;
        }

        preg_match('/name="_token" value="([^"]+)"/', $first->body(), $m);
        $this->line('  CSRF token in form  : '.isset($m[1]) ? 'present' : 'MISSING');

        // Second request WITH the cookie: does the app hand back the SAME
        // session, or a new one? A new one on every request means sessions
        // are not persisting, and no login can ever succeed.
        $second = Http::withOptions(['verify' => true])
            ->withHeaders(['Cookie' => $sessionCookie])
            ->get("$base/login");

        $secondCookie = $this->cookies($second->header('Set-Cookie'))['laravel-session'] ?? null;

        $this->newLine();
        $this->line('GET /login again, with the cookie:');
        $this->line('  status              : '.$second->status());
        $this->line('  sent back the same  : '.($sessionCookie === $secondCookie ? 'yes' : 'NO — a new session was issued'));

        $token2 = null;
        preg_match('/name="_token" value="([^"]+)"/', $second->body(), $m2);
        $token2 = $m2[1] ?? null;

        $this->line('  token changed       : '
            .(isset($m[1], $token2) ? ($m[1] === $token2 ? 'no' : 'YES') : 'n/a'));
        $this->comment('A CSRF token that changes on every request means the session behind it is new every time.');

        return self::SUCCESS;
    }

    /**
     * Name => full cookie pair, parsed from a Guzzle Set-Cookie header.
     *
     * @param  string|string[]|null  $header
     * @return array<string, string>
     */
    private function cookies($header): array
    {
        $header = is_array($header) ? $header : explode(', ', (string) $header);

        $out = [];

        foreach ($header as $line) {
            $pair = trim(explode(';', trim($line))[0]);

            if (! str_contains($pair, '=')) {
                continue;
            }

            $out[explode('=', $pair, 2)[0]] = $pair;
        }

        return $out;
    }
}
