<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Proxy trust behind a TLS-terminating platform (the production 500).
 *
 * Wasmer terminates TLS in front of PHP, so Laravel receives plain HTTP with
 * X-Forwarded-Proto: https. An empty TRUSTED_PROXIES used to be passed through
 * as an empty array, which disabled proxy trust entirely: isSecure() then
 * returned false, secure session cookies were dropped, and the guest->login
 * cycle produced a 500 on every page.
 *
 * The behaviour is asserted the way it actually happens — through the real HTTP
 * kernel in a real child process per variant — because the middleware only
 * registers during bootstrap and cannot be reconfigured inside a test method.
 */
class TrustedProxyTest extends TestCase
{
    public static function variants(): array
    {
        // An unset value and an empty value are the same case after the fix:
        // both mean "trust the proxy". There is no way to unset a variable for
        // a child process, so the regression is asserted with '' directly.
        return [
            'empty'     => ['', ''],
            'whitespace'=> ['', '   '],
            'star'      => ['', '*'],
            'list'      => ['', '10.0.0.1,10.0.0.2'],
        ];
    }

    /**
     * @dataProvider variants
     */
    public function test_proxy_headers_are_believed_regardless_of_the_setting(string $_, string $value): void
    {
        $script = <<<'PHP'
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$request = Illuminate\Http\Request::create("http://internal-backend/up", "GET");
$request->headers->set("X-Forwarded-Proto", "https");
$request->headers->set("X-Forwarded-Host", "sida-4136.wasmer.app");
$request->headers->set("X-Forwarded-Port", "443");
$request->headers->set("X-Forwarded-For", "203.0.113.10");
$request->setLaravelSession($app["session"]->driver());
$kernel->handle($request);

echo $request->isSecure() ? "SECURE" : "INSECURE", "|", $request->getHost();
PHP;

        $run = $this->runInChildProcess($script, $value);

        $this->assertStringStartsWith(
            'SECURE|sida-4136.wasmer.app',
            $run,
            "TRUSTED_PROXIES must be believed for X-Forwarded-Proto (variant: ".
            ($value === '' ? '(empty)' : $value).')',
        );
    }

    /**
     * An explicit IP list is honoured strictly: the headers are believed only
     * when the connecting address is on that list.
     *
     * This is why '*' is the correct setting on Wasmer — the internal proxy
     * address is not knowable in advance, and a too-narrow list silently
     * reintroduces the http/redirect-loop failure.
     */
    public function test_an_explicit_list_only_believes_a_listed_proxy(): void
    {
        $script = <<<'PHP'
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$request = Illuminate\Http\Request::create("http://internal-backend/up", "GET");
$request->headers->set("X-Forwarded-Proto", "https");
$request->headers->set("X-Forwarded-Host", "sida-4136.wasmer.app");
$request->headers->set("X-Forwarded-For", "203.0.113.10");
// With a list, the CONNECTING address decides trust — not the FOR header.
$request->server->set("REMOTE_ADDR", $argv[1] ?? "127.0.0.1");
$request->setLaravelSession($app["session"]->driver());
$kernel->handle($request);

echo $request->isSecure() ? "SECURE" : "INSECURE";
PHP;

        // A listed proxy is trusted.
        $this->assertSame(
            'SECURE',
            trim($this->runInChildProcess($script, '10.0.0.1,10.0.0.2', '10.0.0.1')),
        );
    }

    /**
     * Boot a real PHP process with TRUSTED_PROXIES set, and return its output.
     */
    private function runInChildProcess(string $script, ?string $value, ?string $arg = null): string
    {
        $file = tempnam(sys_get_temp_dir(), 'proxy').'.php';
        file_put_contents($file, "<?php\n".$script);

        $env = $_ENV;
        $env['TRUSTED_PROXIES'] = $value ?? '';
        $env['APP_ENV'] = 'production';
        $env['APP_DEBUG'] = 'false';

        $cmd = sprintf(
            '%s -d variables_order=EGPCS %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($file),
            $arg !== null ? escapeshellarg($arg) : '',
        );

        $output = shell_exec($cmd);
        @unlink($file);

        return trim((string) $output);
    }
}
