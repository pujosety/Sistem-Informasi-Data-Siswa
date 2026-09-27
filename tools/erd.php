<?php
/**
 * Generate the ERD from the LIVE schema.
 *
 * Output is a Mermaid FLOWCHART, not an erDiagram: the erDiagram grammar has
 * no `subgraph` support at all (verified against mermaid-cli 11), so domain
 * grouping could not be expressed and every render failed. A flowchart with
 * subgraphs carries the same information and does render.
 *
 * Everything here is read from information_schema, so the diagram cannot drift
 * from the database the application actually uses.
 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Domain grouping and the order tables appear in within each group. */
$GROUPS = [
    'Akademik' => [
        'academic_years', 'departments', 'classes', 'enrollments',
        'homeroom_assignments', 'subjects', 'grades', 'alumni',
    ],
    'Kehadiran' => ['attendance_sessions', 'attendance_records'],
    'Pendaftaran' => [
        'registrations', 'document_types', 'documents', 'verifications',
    ],
    'Identitas' => ['users', 'students', 'parents', 'guardian_relationships'],
    'Sistem' => [
        'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
        'role_has_permissions', 'settings', 'activity_logs', 'sessions',
        'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'notifications', 'personal_access_tokens', 'password_reset_tokens',
    ],
];

/** Columns worth showing; everything else is noise in a diagram. */
$INTERESTING = [
    'id', 'user_id', 'student_id', 'parent_id', 'guardian_user_id',
    'academic_year_id', 'classroom_id', 'department_id', 'enrollment_id',
    'subject_id', 'attendance_session_id', 'document_type_id',
    'registration_id', 'role_id', 'permission_id', 'model_id',
    'created_by', 'verified_by', 'recorded_by', 'published_by',
    'teacher_id', 'corrected_by', 'assigned_by',
    'name', 'code', 'level', 'status', 'type', 'value', 'completeness',
    'score', 'term', 'capacity', 'room', 'is_active', 'is_default',
    'is_primary', 'relationship', 'linked_via', 'started_at', 'ended_at',
    'submitted_at', 'verified_at', 'date', 'action', 'key', 'guard_name',
];

$existing = collect(Schema::getTables())
    ->map(fn ($t) => is_array($t) ? $t['name'] : $t->name)
    ->all();

$fks = DB::select(
    'SELECT TABLE_NAME tbl, COLUMN_NAME col, REFERENCED_TABLE_NAME ref
     FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL'
);

$fkIndex = [];
foreach ($fks as $fk) {
    $fkIndex[$fk->tbl][] = ['col' => $fk->col, 'ref' => $fk->ref];
}

// Mermaid node ids must be alphanumeric; map real table names to safe ids.
$nodeId = [];
foreach ($existing as $i => $t) {
    $nodeId[$t] = 't'.$i;
}

$esc = fn (string $s) => str_replace(['"', "'", '[', ']', '<', '>', '|'], ['&quot;', '&#39;', '(', ')', '&lt;', '&gt;', '/'], $s);

$lines = [];
$lines[] = '%% ERD — Sistem Informasi Data Siswa';
$lines[] = '%% GENERATED FROM THE LIVE SCHEMA by tools/erd.php';
$lines[] = '%% Do not hand-edit: regenerate, or the diagram and the database drift apart.';
$lines[] = 'flowchart LR';

$gi = 0;
foreach ($GROUPS as $group => $tables) {
    $tables = array_values(array_filter($tables, fn ($t) => in_array($t, $existing, true)));
    if ($tables === []) {
        continue;
    }

    $gid = 'G'.$gi++;
    $lines[] = "  subgraph {$gid} [\"{$esc($group)}\"]";
    $lines[] = '    direction TB';

    foreach ($tables as $table) {
        $cols = collect(Schema::getColumns($table))
            ->filter(fn ($c) => in_array($c['name'], $INTERESTING, true))
            ->map(fn ($c) => $esc($c['name']).' '.$esc($c['type']))
            ->values();

        $body = $cols->isEmpty() ? $esc('id') : $cols->implode('<br/>');
        $label = $esc($table).'<br/>'.$body;
        $lines[] = "    {$nodeId[$table]}[\"{$label}\"]";
    }

    $lines[] = '  end';
}

// Relationships, all after the subgraphs.
$drawn = [];
foreach ($fkIndex as $child => $links) {
    if (! in_array($child, $existing, true)) {
        continue;
    }

    foreach ($links as $link) {
        if (! in_array($link['ref'], $existing, true)) {
            continue;
        }

        $key = $link['ref'].'->'.$child;
        if (isset($drawn[$key])) {
            continue;
        }
        $drawn[$key] = true;

        // "1" --> "N" is erDiagram syntax and is a parse error inside a
        // flowchart. Put the cardinality in the edge label instead.
        $lines[] = "  {$nodeId[$link['ref']]} -->|\"1:N {$esc($link['col'])}\"| {$nodeId[$child]}"; 
    }
}

$mmd = implode("\n", $lines)."\n";
file_put_contents(__DIR__.'/../docs/diagrams/database-erd.mmd', $mmd);

printf("tables in database : %d\n", count($existing));
printf("tables diagrammed  : %d\n", count(array_filter($GROUPS, 'count')));
printf("relationships      : %d\n", count($drawn));
echo "written            : docs/diagrams/database-erd.mmd\n";
