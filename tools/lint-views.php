<?php
/**
 * Compile every Blade view and report parse errors.
 *
 * A Blade template that fails to compile only surfaces as a 500 (or, worse, a
 * timeout behind a single-threaded dev server) at request time. This catches it
 * in one second as part of the verification loop.
 *
 * Run: php tools/lint-views.php
 * Exit code 0 = all templates compile.
 */

$root = dirname(__DIR__);
$viewPath = $root.'/resources/views';

// Boot just enough of the framework to use the Blade compiler.
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/** @var Illuminate\View\Compilers\BladeCompiler $blade */
$blade = $app['blade.compiler'];

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewPath));
$failures = [];
$checked = 0;

foreach ($files as $file) {
    if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }

    $checked++;
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($viewPath) + 1));
    $source = (string) file_get_contents($file->getPathname());

    try {
        $compiled = $blade->compileString($source);

        // Compile to a temp file so the real PHP parser validates the output.
        $tmp = tempnam(sys_get_temp_dir(), 'bladeparse').'.php';
        file_put_contents($tmp, $compiled);

        exec('php -l '.escapeshellarg($tmp).' 2>&1', $out, $code);
        unlink($tmp);

        if ($code !== 0) {
            $failures[$relative] = trim(implode("\n", $out));
            $out = [];
        }
    } catch (Throwable $e) {
        $failures[$relative] = get_class($e).': '.$e->getMessage();
    }
}

printf("Compiled %d Blade templates.\n", $checked);

if ($failures) {
    echo "\nFAILED:\n";
    foreach ($failures as $file => $error) {
        echo "  $file\n    ".str_replace("\n", "\n    ", $error)."\n";
    }
    echo "\n".count($failures)." template(s) failed to compile.\n";
    exit(1);
}

echo "All templates compile cleanly.\n";
exit(0);
