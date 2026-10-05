<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only integrity audit of the production-shaped database.
 *
 * WHAT THIS IS FOR
 *
 * Brief §20 asks for a check that finds invalid foreign keys, nulls in required
 * columns, unsupported status values and duplicate unique data — and asks
 * explicitly that it NOT change anything while auditing. So this command only
 * ever SELECTs. It has no write path, no --fix flag and no destructive option,
 * by design: an audit you are afraid to run is an audit nobody runs.
 *
 * THE FOUR CHECKS
 *
 * 1. ORPHANED ROWS — a foreign key pointing at a row that no longer exists.
 *    These cannot normally happen with real constraints enforced, but MySQL
 *    accepts an invalid value when foreign_key_checks is off, which is what a
 *    restored or migrated dump can leave behind. The result is a row that
 *    renders as an empty cell forever.
 *
 * 2. REQUIRED COLUMNS THAT ARE NULL — the schema says NOT NULL, so this can
 *    only appear if the constraint was added without cleaning the data, or a
 *    migration was edited after it ran.
 *
 * 3. UNSUPPORTED STATUS VALUES — a value outside the enum the application uses.
 *    Reported per table because each one has its own set.
 *
 * 4. DUPLICATES IN UNIQUE COLUMNS — the constraint should prevent these, so a
 *    hit means the data was loaded with the checks disabled.
 */
class AuditIntegrityCommand extends Command
{
    protected $signature = 'sida:audit-integrity
                            {--json : machine-readable output}
                            {--orphans : only check orphaned foreign keys}';

    protected $description = 'Read-only integrity audit: orphans, nulls, statuses, duplicates';

    /** Known status domains, so "unknown" means something. */
    private const STATUSES = [
        'registrations' => ['draft', 'submitted', 'pending', 'revision', 'verified', 'rejected'],
        'documents' => ['missing', 'pending', 'valid', 'rejected'],
        'enrollments' => ['active', 'transferred', 'graduated', 'completed'],
        'homeroom_assignments' => ['active', 'ended', 'replaced'],
        'employees' => ['active', 'inactive', 'resigned', 'retired'],
        'students' => ['active', 'inactive', 'graduated', 'transferred'],
        'cms_posts' => ['draft', 'published', 'scheduled', 'archived'],
        'modules' => ['enabled', 'disabled'],
    ];

    /** Foreign keys worth auditing, as [table, column, referenced table]. */
    private const RELATIONS = [
        ['students', 'user_id', 'users'],
        ['students', 'class_id', 'classes'],
        ['students', 'academic_year_id', 'academic_years'],
        ['registrations', 'student_id', 'students'],
        ['documents', 'registration_id', 'registrations'],
        ['documents', 'document_type_id', 'document_types'],
        ['enrollments', 'student_id', 'students'],
        ['enrollments', 'class_id', 'classes'],
        ['parents', 'student_id', 'students'],
        ['guardian_relationships', 'student_id', 'students'],
        ['guardian_relationships', 'user_id', 'users'],
        ['attendance_records', 'session_id', 'attendance_sessions'],
        ['grades', 'enrollment_id', 'enrollments'],
        ['homeroom_assignments', 'classroom_id', 'classes'],
        ['homeroom_assignments', 'teacher_id', 'users'],
        ['employees', 'user_id', 'users'],
        ['classroom_announcements', 'classroom_id', 'classes'],
    ];

    public function handle(): int
    {
        $findings = [];

        if (! $this->option('orphans')) {
            $findings = array_merge(
                $this->orphans(),
                $this->nullsInRequired(),
                $this->badStatuses(),
                $this->duplicateUniques(),
            );
        } else {
            $findings = $this->orphans();
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($findings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $findings === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($findings === []) {
            $this->components->info('Integrity clean: no orphans, no null violations, no unknown statuses, no duplicates.');

            return self::SUCCESS;
        }

        $this->table(
            ['Check', 'Where', 'Detail'],
            array_map(fn ($f) => [$f['check'], $f['where'], $f['detail']], $findings)
        );

        $this->newLine();
        $this->components->warn(count($findings).' issue(s). Nothing was changed — this command only reads.');

        return self::FAILURE;
    }

    /** @return array<int, array{check:string,where:string,detail:string}> */
    private function orphans(): array
    {
        $findings = [];

        foreach (self::RELATIONS as [$table, $column, $parent]) {
            if (! Schema::hasTable($table) || ! Schema::hasTable($parent)) {
                continue;
            }

            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            try {
                $count = DB::table($table)
                    ->whereNotNull($column)
                    ->whereNotIn($column, DB::table($parent)->select('id'))
                    ->count();
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            if ($count > 0) {
                $findings[] = [
                    'check' => 'orphan',
                    'where' => "{$table}.{$column}",
                    'detail' => "{$count} row(s) point at a {$parent} row that does not exist",
                ];
            }
        }

        return $findings;
    }

    /**
     * Every table name, read from information_schema.
     *
     * `Schema::getAllTables()` does not exist on MySQL's builder. Two audits
     * need this list, so it lives here as a shared static rather than being
     * written twice — two implementations of "what tables exist" is how two
     * audits end up disagreeing about the schema.
     *
     * @return array<int, string>
     */
    public static function tableNames(): array
    {
        return array_map(
            fn ($row) => (string) reset($row),
            DB::select('SHOW TABLES')
        );
    }

    /** @return array<int, array{check:string,where:string,detail:string}> */
    private function nullsInRequired(): array
    {
        $findings = [];

        foreach (self::tableNames() as $table) {
            try {
                foreach (DB::select('SHOW COLUMNS FROM `'.$table.'`') as $column) {
                    if ($column->Null === 'YES' || strtoupper((string) $column->Default) !== 'NULL') {
                        continue;
                    }

                    // Timestamps and primary keys are NOT NULL but a null there
                    // is impossible, so they only add noise.
                    if (in_array($column->Field, ['id', 'created_at', 'updated_at'], true)) {
                        continue;
                    }

                    $nulls = DB::table($table)->whereNull($column->Field)->count();

                    if ($nulls > 0) {
                        $findings[] = [
                            'check' => 'null in NOT NULL',
                            'where' => "{$table}.{$column->Field}",
                            'detail' => "{$nulls} row(s) are null but the column is NOT NULL",
                        ];
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $findings;
    }

    /** @return array<int, array{check:string,where:string,detail:string}> */
    private function badStatuses(): array
    {
        $findings = [];

        foreach (self::STATUSES as $table => $allowed) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'status')) {
                continue;
            }

            try {
                $seen = DB::table($table)
                    ->select('status', DB::raw('COUNT(*) as total'))
                    ->groupBy('status')
                    ->pluck('total', 'status');
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            foreach ($seen as $value => $count) {
                if (! in_array((string) $value, $allowed, true)) {
                    $findings[] = [
                        'check' => 'unknown status',
                        'where' => "{$table}.status",
                        'detail' => "'{$value}' ({$count} row(s)) is not one of: ".implode(', ', $allowed),
                    ];
                }
            }
        }

        return $findings;
    }

    /** @return array<int, array{check:string,where:string,detail:string}> */
    private function duplicateUniques(): array
    {
        $findings = [];

        foreach (self::tableNames() as $table) {
            try {
                $indexes = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Non_unique = 0');
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            // Group the columns of each composite unique index.
            $grouped = [];

            foreach ($indexes as $index) {
                $grouped[$index->Key_name][] = $index->Column_name;
            }

            foreach ($grouped as $name => $columns) {
                if ($name === 'PRIMARY') {
                    continue;
                }

                $dupe = DB::table($table)
                    ->select($columns)
                    ->selectRaw('COUNT(*) as total')
                    ->groupBy($columns)
                    ->having('total', '>', 1)
                    ->count();

                if ($dupe > 0) {
                    $findings[] = [
                        'check' => 'duplicate',
                        'where' => "{$table} ({$name})",
                        'detail' => "{$dupe} value combination(s) appear more than once",
                    ];
                }
            }
        }

        return $findings;
    }
}