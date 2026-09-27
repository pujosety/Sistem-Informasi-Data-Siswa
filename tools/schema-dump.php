<?php
// Dump the columns of the tables the database document describes, so the
// document can be checked against reality rather than memory.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$core = [
    'users', 'students', 'enrollments', 'academic_years', 'classes',
    'homeroom_assignments', 'guardian_relationships', 'parents',
    'attendance_sessions', 'attendance_records', 'grades', 'alumni',
    'registrations', 'documents', 'document_types', 'settings',
    'activity_logs', 'notifications',
];

$fks = [];
foreach (DB::select(
    'SELECT TABLE_NAME t, COLUMN_NAME c, REFERENCED_TABLE_NAME r
     FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL'
) as $fk) {
    if (in_array($fk->t, $core, true)) {
        $fks[$fk->t][] = $fk->c.' -> '.$fk->r;
    }
}

foreach ($core as $t) {
    if (! Schema::hasTable($t)) {
        echo "MISSING: {$t}\n";

        continue;
    }

    $cols = collect(Schema::getColumns($t))->map(fn ($c) => $c['name']);
    echo "\n## {$t}  (".count($cols)." kolom)\n";
    echo implode(', ', $cols->all())."\n";

    if (isset($fks[$t])) {
        echo '  FK: '.implode(' | ', $fks[$t])."\n";
    }
}

echo "\n=== row counts ===\n";
foreach ($core as $t) {
    if (Schema::hasTable($t)) {
        echo "  {$t}: ".DB::table($t)->count()."\n";
    }
}

echo "\n=== index on enrollments ===\n";
foreach (DB::select(
    'SELECT INDEX_NAME i, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) c
     FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "enrollments"
     GROUP BY INDEX_NAME'
) as $idx) {
    echo "  {$idx->i}: {$idx->c}\n";
}
