<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

/**
 * Verifies a real session against the DEPLOYED site.
 *
 * WHY NOT curl
 *
 * Two things make an HTTP client the wrong tool here, and both look like an
 * application failure:
 *
 *  1. `laravel-session` is set with the `secure` flag, so a client that does
 *     not fully trust the TLS connection silently refuses to store it. The
 *     next POST then has a CSRF token from one session and a session cookie
 *     from another, and the answer is 419 — which reads as "login is broken"
 *     when nothing is wrong.
 *  2. The response must not be shared-cached, and a container that destroys
 *     itself between requests replays a cached login page whose CSRF token
 *     belongs to a session that no longer exists. That is the exact failure
 *     this application already guards against with PreventSharedCaching.
 *
 * Driving the session from inside PHP, with the same cookie jar the framework
 * uses, tests the thing that actually matters: can a signed-in user reach the
 * protected screens.
 */
class VerifyProductionSessionCommand extends Command
{
    protected $signature = 'sida:verify-production
        {--base=https://sida-4136.wasmer.app}
        {--email=admin@sida.test}
        {--password=password123}';

    protected $description = 'Sign in to the deployed site and fetch the protected pages with a real session';

    /** Never echoed, only used to sign in. */
    private string $cookie = '';

    public function handle(): int
    {
        $base = rtrim((string) $this->option('base'), '/');

        $login = $this->get("$base/login");

        if ($login['status'] !== 200) {
            $this->error("/login returned {$login['status']}");

            return self::FAILURE;
        }

        if (! preg_match('/name="_token" value="([^"]+)"/', $login['body'], $m)) {
            $this->error('no CSRF token in the login form — /login did not render one');

            return self::FAILURE;
        }

        $this->line('  GET  /login            '.$login['status'].'  session cookie: '
            .($this->cookie !== '' ? 'received' : 'MISSING'));

        $response = $this->get("$base/login", [
            '_token' => $m[1],
            'email' => (string) $this->option('email'),
            'password' => (string) $this->option('password'),
        ], post: true);

        $signedIn = $response['status'] === 302
            && ! str_contains((string) $response['redirect'], '/login');

        $this->line(sprintf(
            '  POST /login            %d  %s',
            $response['status'],
            $signedIn ? 'signed in' : 'NOT signed in'
        ));

        if (! $signedIn) {
            $this->error('Could not sign in. Is the demo account seeded, and is the password right?');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('protected pages:');

        $pages = [
            '/admin/kepegawaian', '/admin/konten', '/admin/modul', '/admin/media',
            '/kesiswaan/data-siswa', '/kesiswaan/statistik', '/akademik/tahun-ajaran',
            '/akademik/kelas', '/laporan', '/profil/kepegawaian',
        ];

        $anon = 0;
        $ok = 0;
        $broken = [];

        foreach ($pages as $page) {
            $r = $this->get($base.$page);
            printf("  %-28s %d\n", $page, $r['status']);

            match (true) {
                $r['status'] === 200 => $ok++,
                $r['status'] === 302 => $anon++,
                default => $broken[$page] = $r['status'],
            };
        }

        $this->newLine();
        $this->line("reachable: $ok   still redirected: $anon   errored: ".count($broken));

        if ($broken !== []) {
            $this->error('These returned something other than 200 or 302:');

            foreach ($broken as $page => $status) {
                $this->line("    $page -> $status");
            }

            return self::FAILURE;
        }

        $anon > 0 && $this->warn('Some pages still redirect. Either the role lacks the permission, or the nav entry is module-gated off.');

        return self::SUCCESS;
    }

    /**
     * One request, one session.
     *
     * The cookie is carried by hand rather than by curl's jar, because the jar
     * is the thing that was silently refusing to store a `secure` cookie.
     */
    private function get(string $url, array $data = [], bool $post = false): array
    {
        $client = Http::withOptions(['verify' => true]);

        if ($this->cookie !== '') {
            $client = $client->withHeaders(['Cookie' => $this->cookie]);
        }

        $response = $post
            ? $client->asForm()->post($url, $data)
            : $client->get($url);

        /*
         * Guzzle collapses several Set-Cookie headers into ONE string joined
         * with ", ", so splitting on ";" — the obvious move — cuts the header
         * in half and can hand back half of the XSRF cookie's attributes
         * instead of the session id. Split on the cookie boundary first, then
         * on ";" within each cookie, and keep only the one we want.
         */
        $setCookie = $response->header('Set-Cookie');
        $setCookie = is_array($setCookie) ? $setCookie : explode(', ', (string) $setCookie);

        foreach ($setCookie as $line) {
            if (! str_contains($line, 'laravel-session=')) {
                continue;
            }

            $pair = trim(explode(';', trim($line))[0]);

            if (str_starts_with($pair, 'laravel-session=')) {
                $this->cookie = $pair;
            }
        }

        return [
            'status' => $response->status(),
            'body' => $response->body(),
            'redirect' => $response->header('Location'),
        ];
    }
}
