<?php
/**
 * A focused ERD: only the tables that carry the academic model.
 *
 * The full 95-table diagram is accurate but unreadable on a slide or in print.
 * This variant keeps Identity + Akademik + Kehadiran, which is the part worth
 * explaining, and is generated from the same live schema so it cannot drift.
 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$GROUPS = [
    'Identitas' => ['users', 'students', 'parents', 'guardian_relationships'],
    'Akademik' => [
        'academic_years', 'departments', 'classes', 'enrollments',
        'homeroom_assignments', 'subjects', 'grades',
    ],
    'Kehadiran' => ['attendance_sessions', 'attendance_records'],
];

$INTERESTING = [
    'id', 'user_id', 'student_id', 'parent_id', 'guardian_user_id',
    'academic_year_id', 'classroom_id', 'department_id', 'enrollment_id',
    'subject_id', 'attendance_session_id', 'name', 'code', 'level', 'status',
    'score', 'term', 'capacity', 'room', 'is_active', 'started_at', 'ended_at',
    'relationship', 'is_primary', 'date',
];

$existing = collect(Schema::getTables())->map(fn ($t) => is_array($t) ? $t['name'] : $t->name)->all();

$fks = DB::select(
    'SELECT TABLE_NAME tbl, COLUMN_NAME col, REFERENCED_TABLE_NAME ref
     FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL'
);
$fkIndex = [];
foreach ($fks as $fk) {
    $fkIndex[$fk->tbl][] = ['col' => $fk->col, 'ref' => $fk->ref];
}

$nodeId = [];
foreach ($existing as $i => $t) {
    $nodeId[$t] = 't'.$i;
}

$esc = fn (string $s) => str_replace(['"', "'", '[', ']', '<', '>', '|'], ['&quot;', '&#39;', '(', ')', '&lt;', '&gt;', '/'], $s);

$lines = [
    '%% ERD — model inti (Identitas, Akademik, Kehadiran)',
    '%% GENERATED FROM THE LIVE SCHEMA by tools/erd-core.php',
    'flowchart LR',
];

$gi = 0;
foreach ($GROUPS as $group => $tables) {
    $tables = array_values(array_filter($tables, fn ($t) => in_array($t, $existing, true)));
    if ($tables === []) {
        continue;
    }

    $lines[] = "  subgraph G{$gi} [\"{$esc($group)}\"]";
    $lines[] = '    direction TB';

    foreach ($tables as $table) {
        $cols = collect(Schema::getColumns($table))
            ->filter(fn ($c) => in_array($c['name'], $INTERESTING, true))
            ->map(fn ($c) => $esc($c['name']).' '.$esc($c['type']))
            ->values();

        $body = $cols->isEmpty() ? $esc('id') : $cols->implode('<br/>');
        $lines[] = "    {$nodeId[$table]}[\"{$esc($table)}<br/>{$body}\"]";
    }

    $lines[] = '  end';
    $gi++;
}

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

        $lines[] = "  {$nodeId[$link['ref']]} -->|\"1:N {$esc($link['col'])}\"| {$nodeId[$child]}";
    }
}

file_put_contents(__DIR__.'/../docs/diagrams/database-erd-core.mmd', implode("\n", $lines)."\n");

$diagrammed = 0;
foreach ($GROUPS as $tables) {
    $diagrammed += count(array_filter($tables, fn ($t) => in_array($t, $existing, true)));
}

printf("tables   : %d\n", $diagrammed);
printf("relations: %d\n", count($drawn));
echo "written  : docs/diagrams/database-erd-core.mmd\n";
