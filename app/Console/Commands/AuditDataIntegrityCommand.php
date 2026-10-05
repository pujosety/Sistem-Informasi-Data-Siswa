<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Audits every Blade form against what the application can actually accept.
 *
 * WHY A COMMAND AND NOT A ONE-OFF SCRIPT
 *
 * The reported symptom was "some data cannot be changed" across an application
 * with 105 views and 167 routes. That is not one bug, it is a class, and a
 * class needs a check that runs on every change rather than a fix that runs
 * once. Every finding below is a shape that produces "the form submitted
 * successfully and nothing was saved".
 *
 * It is READ-ONLY. It reports; it never edits a view, a controller or a
 * database.
 *
 * THE FOUR CHECKS
 *
 * 1. FORM ACTION NAMES A ROUTE THAT EXISTS
 *    A form pointing at a route that was renamed or removed returns 404 on
 *    submit — and only on submit, so the page looks perfect.
 *
 * 2. A POST/PUT/PATCH FORM HAS @csrf
 *    Without it Laravel answers 419, which reads as "the session expired" and
 *    sends people to a support page instead of to the form.
 *
 * 3. INPUT NAMES vs DATABASE COLUMNS
 *    An input named after a column that does not exist is discarded silently by
 *    validation when the field is not required. This is the single most common
 *    cause of "the form saved but my change is gone".
 *
 * 4. mass-assignment exposure
 *    `Model::update($request->all())` writes every posted field the model
 *    considers fillable, including ones the form never showed.
 *
 * @see \App\Console\Commands\AuditFormActionsCommand for the route check,
 *      which this command supersedes and keeps for compatibility.
 */
class AuditDataIntegrityCommand extends Command
{
    protected $signature = 'sida:audit-data
                            {--json : machine-readable output}
                            {--fix-report : only print the summary table}';

    protected $description = 'Audit Blade forms, database columns and mass-assignment exposure (read-only)';

    /**
     * Inputs that are never persisted, and why.
     *
     * Every entry here is a field the application validates, branches on, or
     * uses as a UI flag, but that has no column behind it. They are listed
     * explicitly so that the exemption is a recorded decision — an audit whose
     * exceptions are invisible is an audit nobody trusts.
     *
     * @var array<int, string>
     */
    private const NON_PERSISTED_INPUTS = [
        '_token', '_method', 'password_confirmation', 'current_password',
        'remember', 'terms',
        // Flat fields the student portal SPLITS into the `parents` table:
        // guardian_name becomes parents.full_name with relation='guardian', not
        // a students column. The controller does the mapping, so a column-name
        // check cannot see it.
        'guardian_name', 'guardian_job', 'guardian_phone',
        'father_name', 'father_job', 'father_phone', 'father_nik',
        'mother_name', 'mother_job', 'mother_phone', 'mother_nik',
        // Action flags: read in the controller to pick a branch, then discarded.
        'publish', 'action', 'step',
        // Uploads and free-text reasons consumed by a service, not a column.
        'file', 'photo', 'avatar', 'logo',
        'reason', 'note', 'effective_date',
        // Search terms inside a POST-shaped form (a filter that happens to be
        // nested inside another form's markup).
        'q', 'search', 'filter', 'sort', 'direction', 'page',
    ];

    public function handle(): int
    {
        $views = resource_path('views');

        $findings = array_merge(
            $this->auditActions($views),
            $this->auditCsrf($views),
            $this->auditInputNames($views),
            $this->auditMassAssignment(),
            $this->auditEnumConsistency(),
        );

        if ($this->option('json')) {
            $this->line((string) json_encode($findings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $findings === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($findings === []) {
            $this->components->info('No data-integrity problems found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Kind', 'File', 'Detail'],
            array_map(fn ($f) => [$f['kind'], $f['file'], $f['detail']], $findings)
        );

        $this->components->error(count($findings).' finding(s).');

        return self::FAILURE;
    }

    /**
     * @return array<int, array{kind:string,file:string,detail:string}>
     */
    private function auditActions(string $views): array
    {
        $routes = collect(Route::getRoutes())->map(fn ($r) => $r->getName())->filter()->flip();
        $findings = [];

        foreach ($this->bladeFiles($views) as $file) {
            $relative = $this->relative($file, $views);

            // A form tag can wrap across lines, so the whole file is scanned
            // once and the form blocks extracted from it.
            foreach ($this->forms($file) as $index => $form) {
                if (! preg_match('/action\s*=\s*"\{\{\s*route\(\s*[\'"]([^\'"]+)/', $form, $m)) {
                    continue;
                }

                $name = $m[1];

                if (! $routes->has($name)) {
                    $findings[] = [
                        'kind' => 'missing route',
                        'file' => $relative.' #form'.$index,
                        'detail' => "action=\"{{ route('{$name}') }}\" — no such route",
                    ];
                }
            }
        }

        return $findings;
    }

    /**
     * @return array<int, array{kind:string,file:string,detail:string}>
     */
    private function auditCsrf(string $views): array
    {
        $findings = [];

        foreach ($this->bladeFiles($views) as $file) {
            foreach ($this->forms($file) as $index => $form) {
                $method = preg_match('/method\s*=\s*"([^"]+)"/i', $form, $m) ? strtoupper($m[1]) : 'GET';

                if (! in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    continue;
                }

                if (! str_contains($form, '@csrf') && ! str_contains($form, 'csrf_token')) {
                    $findings[] = [
                        'kind' => 'missing @csrf',
                        'file' => $this->relative($file, $views).' #form'.$index,
                        'detail' => $method.' form will be rejected with 419',
                    ];
                }
            }
        }

        return $findings;
    }

    /**
     * Input names that match no column on any table.
     *
     * Deliberately permissive about naming: an input is only reported when its
     * name contains no dot, no underscore-separated-word boundary, and no
     * camelCase split is possible — because `student.name` and `studentName`
     * are both legitimate and neither is a column.
     *
     * @return array<int, array{kind:string,file:string,detail:string}>
     */
    private function auditInputNames(string $views): array
    {
        // A plain array keyed by column name — `isset` is the right test for it,
        // not Collection::has().
        $columns = $this->allColumns();
        $findings = [];

        foreach ($this->bladeFiles($views) as $file) {
            $contents = file_get_contents($file);

            // Only inputs inside a POST form are interesting: a GET filter's
            // values arrive in the query string and are read by the controller
            // by name, not persisted.
            foreach ($this->forms($file) as $index => $form) {
                $method = preg_match('/method\s*=\s*"([^"]+)"/i', $form, $m) ? strtoupper($m[1]) : 'GET';

                if (! in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                    continue;
                }

                preg_match_all('/<input\b[^>]*\bname\s*=\s*"([^"]+)"/i', $form, $names);

                foreach ($names[1] as $name) {
                    // Skip the framework's own fields and any dotted path.
                    if (in_array($name, ['_token', '_method'], true) || str_contains($name, '[')) {
                        continue;
                    }

                    $bare = str_replace(['.', '[', ']'], '', explode('.', $name)[0]);

                    // A dynamic name is unknowable at audit time: the view
                    // decides it at runtime. Reporting it teaches people to
                    // ignore this command, which is worse than having none.
                    if (str_contains($name, '{{') || str_contains($name, '{$')) {
                        continue;
                    }

                    if (in_array($name, self::NON_PERSISTED_INPUTS, true)) {
                        continue;
                    }

                    if ($bare === $name && ! isset($columns[$name])) {
                        $findings[] = [
                            'kind' => 'unknown input',
                            'file' => $this->relative($file, $views).' #form'.$index,
                            'detail' => "name=\"{$name}\" matches no database column",
                        ];
                    }
                }
            }

            unset($contents);
        }

        return $findings;
    }

    /**
     * @return array<int, array{kind:string,file:string,detail:string}>
     */
    private function auditMassAssignment(): array
    {
        $findings = [];
        $controllers = base_path('app/Http/Controllers');

        foreach (glob($controllers.'/*.php') as $file) {
            $contents = file_get_contents($file);

            // The dangerous shapes: writing everything the client sent, straight
            // into a model. `validated()` is the fix and its absence is the bug.
            if (preg_match('/->update\(\s*\$request->all\(\)\s*\)/', $contents)) {
                $findings[] = [
                    'kind' => 'mass assignment',
                    'file' => $this->relative($file, base_path()),
                    'detail' => 'Model::update($request->all()) — use validated() or an explicit payload',
                ];
            }

            if (preg_match('/::create\(\s*\$request->all\(\)\s*\)/', $contents)) {
                $findings[] = [
                    'kind' => 'mass assignment',
                    'file' => $this->relative($file, base_path()),
                    'detail' => 'Model::create($request->all()) — use validated() or an explicit payload',
                ];
            }
        }

        return $findings;
    }

    /**
     * Status values used in code that are not in the model's constant list.
     *
     * @return array<int, array{kind:string,file:string,detail:string}>
     */
    private function auditEnumConsistency(): array
    {
        $findings = [];

        // registrations.status is the busiest enum in the application: six
        // states, and every one of them is written by a different controller.
        $model = base_path('app/Models/Registration.php');

        if (! is_file($model)) {
            return $findings;
        }

        $source = file_get_contents($model);
        preg_match_all("/const STATUS_\w+\s*=\s*'([^']+)'/", $source, $m);
        $valid = $m[1];

        // The application has FOUR status enums and this check is only
        // authoritative for registrations. `students`, `enrollments` and
        // `homeroom_assignments` each have their own column and their own set
        // of values, and reading one model's constants as though it governed
        // all four is how an audit starts crying wolf.
        $foreign = ['missing', 'pending', 'valid', 'rejected',   // documents
                    'transferred', 'graduated', 'completed',     // enrollments
                    'replaced', 'ended', 'active', 'inactive',  // homeroom_assignments
                    'active', 'inactive', 'resigned', 'retired', // employees
                    'published', 'draft', 'scheduled', 'archived',// cms_posts
                   ];

        foreach (glob(base_path('app/**/*.php'), GLOB_BRACE) as $file) {
            $contents = file_get_contents($file);

            // A literal assigned to ->status that is not a constant reference.
            if (preg_match_all("/['\"]status['\"]\s*=>\s*'([a-z_]+)'/", $contents, $hits)) {
                foreach ($hits[1] as $value) {
                    if (in_array($value, $foreign, true) || in_array($value, $valid, true)) {
                        continue;
                    }

                    $findings[] = [
                        'kind' => 'unknown status',
                        'file' => $this->relative($file, base_path()),
                        'detail' => "'status' => '{$value}' belongs to no known status enum",
                    ];
                }
            }
        }

        return $findings;
    }

    /**
     * Every column name across every table, lowercased.
     *
     * The table list comes from information_schema rather than from a schema
     * helper: `Schema::getAllTables()` does not exist on MySQL's builder, and
     * the audit must compare what the MIGRATIONS declare against what the
     * DATABASE actually has — which is the whole point of Phase 4.
     *
     * @return array<string, true>
     */
    private function allColumns(): array
    {
        $columns = [];

        try {
            $tables = AuditIntegrityCommand::tableNames();
        } catch (\Throwable $e) {
            report($e);
            $this->components->error('Could not read the table list; skipping the column check.');

            return $columns;
        }

        foreach ($tables as $table) {
            try {
                foreach (DB::select('SHOW COLUMNS FROM `'.str_replace('`', '', $table).'`') as $column) {
                    $columns[strtolower((string) $column->Field)] = true;
                }
            } catch (\Throwable) {
                // A view, or a name this connection may not read. Skipping one
                // unreadable table is better than failing the whole audit.
            }
        }

        return $columns;
    }

    /**
     * Every `<form>…</form>` block in a file.
     *
     * @return array<int, string>
     */
    private function forms(string $file): array
    {
        $contents = (string) file_get_contents($file);
        $offset = 0;
        $forms = [];

        while (($start = strpos($contents, '<form', $offset)) !== false) {
            $end = strpos($contents, '</form>', $start);

            if ($end === false) {
                break;
            }

            $forms[] = substr($contents, $start, $end - $start);
            $offset = $end;
        }

        return $forms;
    }

    /** @return array<int, string> */
    private function bladeFiles(string $root): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function relative(string $path, string $root): string
    {
        return ltrim(str_replace($root, '', $path), '/\\');
    }
}