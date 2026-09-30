<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Checks that every route a Blade form posts to actually exists.
 *
 * THE FAILURE THIS CATCHES
 *
 * A form whose action names a route that was renamed or renamed-away does not
 * error at build time. Blade renders the page, the operator fills it in, presses
 * save, and gets a 404 — with no indication that the button is broken, because
 * the form looks perfectly correct in the source.
 *
 * The gradebook did exactly this: it posted to `grades.store` while the route
 * was registered as `academic.grades.store`. The form was written before the
 * route was moved under the academic prefix and nobody followed it.
 *
 * Also reports forms with NO action at all, which submit to the current URL and
 * look like they work until the page is reached through a different path.
 *
 * Read-only: it resolves route names, never requests one.
 */
class AuditFormActionsCommand extends Command
{
    protected $signature = 'sida:audit-forms {--json : Machine-readable output}';

    protected $description = 'Report every Blade form whose action names a route that does not exist';

    public function handle(): int
    {
        $known = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->flip();

        $views = base_path('resources/views');

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views));

        $problems = [];
        $checked = 0;

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            $relative = str_replace($views.DIRECTORY_SEPARATOR, '', $file->getPathname());

            /*
             * A form tag can wrap across lines, and several here do:
             *
             *   <form method="POST" action="{{ route('x') }}"
             *         onsubmit="return confirm('...')">
             *
             * A regex that only understands a single line reports those as
             * actionless — three false positives on the first run, which is
             * enough to teach everyone to ignore the output. So match the
             * element, then read attributes from the whole matched text.
             */
            preg_match_all("~<form\\b(?:[^>\"']|\"[^\"]*\"|'[^']*')*>~is", $source, $tags);

            foreach ($tags[0] as $tag) {
                $checked++;

                $isPost = (bool) preg_match('/method\s*=\s*"POST"/i', $tag);

                if (! preg_match('/action\s*=\s*"([^"]*)"/i', $tag, $m)) {
                    // A GET form with no action is a filter: it submits to the
                    // current URL, which is exactly right. A POST with no
                    // action is a form whose target is wherever the operator
                    // happened to arrive from.
                    $problems[] = [
                        'file' => $relative,
                        'kind' => $isPost ? 'post_without_action' : 'get_filter_ok',
                        'detail' => $isPost ? 'POST to the current URL' : 'GET filter',
                    ];

                    continue;
                }

                $action = $m[1];

                if (! str_contains($action, 'route(')) {
                    continue; // a literal URL is fine
                }

                // Every route(...) call in the action, including a ternary.
                preg_match_all("/route\(\s*'([^']+)'/", $action, $names);

                foreach ($names[1] as $name) {
                    if (! isset($known[$name])) {
                        $problems[] = [
                            'file' => $relative,
                            'kind' => 'unknown_route',
                            'detail' => $name,
                        ];
                    }
                }
            }
        }

        if ($this->option('json')) {
            $real = array_values(array_filter($problems, fn ($p) => $p['kind'] !== 'get_filter_ok'));

            $this->line(json_encode(['checked' => $checked, 'problems' => $real], JSON_PRETTY_PRINT));

            return $real === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->line("forms checked: {$checked}");

        // A GET filter with no action is CORRECT — it submits to the current
        // URL by design. Only the two real kinds are reported, or the output
        // is a wall of false positives and nobody reads it.
        $real = array_values(array_filter(
            $problems,
            fn ($p) => $p['kind'] !== 'get_filter_ok'
        ));

        $filters = count($problems) - count($real);

        $this->line('GET filters (correct as-is): '.$filters);

        if ($real === []) {
            $this->info('every form action names a route that exists');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error(count($real).' form action problem(s):');
        $this->table(['file', 'kind', 'detail'], array_map(
            fn ($p) => [$p['file'], $p['kind'], $p['detail']],
            $real
        ));

        $this->comment('A 404 on save is what an operator sees. It reads as a broken');
        $this->comment('button, not as a form pointing at a route that does not exist.');

        return self::FAILURE;
    }
}