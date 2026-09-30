<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every form in the application must post somewhere real.
 *
 * THE FAILURE THIS PREVENTS
 *
 * A form whose action names a route that was renamed does not fail at build
 * time and does not fail in the page source. Blade renders it, the operator
 * fills it in, presses save, and gets a 404. The form looks perfectly correct
 * to anyone reading the code.
 *
 * Two real instances, both found by the audit this test now automates:
 *
 *   - the gradebook posted to `grades.store` and `grades.publish` while the
 *     routes were registered as `academic.grades.store` / `.publish`. Every
 *     save a teacher made was a 404.
 *   - the academic-year dialog had NO action at all, so "Tambah Tahun Ajaran"
 *     POSTed to whatever URL the operator was standing on, which is the index
 *     page where the only POST routes are activate and arsipkan. A 405 from a
 *     form that looks completely correct.
 *
 * A GET form with no action is CORRECT — that is a filter submitting to the
 * current URL — so only POST forms are reported here, and only for the two
 * failure kinds.
 */
class FormActionTest extends TestCase
{
    /** @return array<int, string> absolute paths of every blade view */
    private function bladeFiles(): array
    {
        $views = base_path('resources/views');

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($views));

        foreach ($iterator as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /** @test */
    public function every_form_action_names_a_route_that_exists(): void
    {
        $known = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->flip();

        $problems = [];

        foreach ($this->bladeFiles() as $path) {
            $source = file_get_contents($path);
            $relative = str_replace(base_path('resources/views').DIRECTORY_SEPARATOR, '', $path);

            /*
             * Match the whole element, allowing a quoted attribute to contain
             * '>'. Several forms here wrap across lines, and a single-line
             * regex reports them as actionless — which is how the audit's first
             * run produced three false positives and taught us to ignore it.
             */
            preg_match_all("~<form\b(?:[^>\"']|\"[^\"]*\"|'[^']*')*>~is", $source, $tags);

            foreach ($tags[0] as $tag) {
                $isPost = (bool) preg_match('/method\s*=\s*["\']POST["\']/i', $tag);

                if (! preg_match('/action\s*=\s*"([^"]*)"/i', $tag, $m)) {
                    // A GET form with no action submits to the current URL,
                    // which is exactly what a filter should do.
                    if ($isPost) {
                        $problems[] = "{$relative}: POST form with no action";
                    }

                    continue;
                }

                $action = $m[1];

                if (! str_contains($action, 'route(')) {
                    continue; // a literal URL is not checkable and not wrong
                }

                preg_match_all("/route\(\s*'([^']+)'/", $action, $names);

                foreach ($names[1] as $name) {
                    if (! isset($known[$name])) {
                        $problems[] = "{$relative}: route [{$name}] does not exist";
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $problems,
            "A form pointing at a missing route 404s on save, which an operator "
            ."reads as a broken button:\n  - ".implode("\n  - ", $problems)
        );
    }

    /**
     * The specific regression, pinned so a re-introduction names itself.
     *
     * @test
     */
    public function the_gradebook_posts_to_the_routes_its_controller_is_registered_on(): void
    {
        $this->assertNotNull(
            Route::getRoutes()->getByName('academic.grades.store'),
            'The grade save route is missing entirely.'
        );

        $view = file_get_contents(base_path('resources/views/academic/gradebook/index.blade.php'));

        $this->assertStringNotContainsString(
            "route('grades.store'",
            $view,
            "The gradebook posts to 'grades.store', which is not a route. "
            ."Every save is a 404."
        );
    }

    /** @test */
    public function the_academic_year_form_has_an_action_for_its_create_route(): void
    {
        $view = file_get_contents(base_path('resources/views/academic/years/index.blade.php'));

        $this->assertStringContainsString(
            "route('academic.years.store')",
            $view,
            'The year form has no action, so creating a year POSTs to the index '
            .'page, where the only POST routes are activate and arsipkan.'
        );
    }
}
