<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * TEMPORARY deployment diagnostic. Remove once the cause is found.
 *
 * Deliberately returns booleans and counts only. It never returns a host, a
 * database name, a credential, an environment value, or an exception message —
 * those routinely embed connection strings, and this route is unauthenticated
 * while it exists.
 *
 * The question it answers is narrow: is the database unreachable, or is it
 * reachable but empty? Those need opposite fixes, so guessing is not an option.
 */
class DiagnosticController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $report = [
            'app' => 'SIDA',
            'env' => app()->environment(),
            'debug' => (bool) config('app.debug'),
        ];

        // 1. Can we open a connection at all?
        try {
            DB::connection()->getPdo();
            $report['connection'] = 'ok';
        } catch (Throwable $e) {
            // The exception CLASS is safe and diagnostic; its MESSAGE is not,
            // because PDO messages embed the host and sometimes the user.
            $report['connection'] = 'failed';
            $report['connection_exception'] = class_basename($e);

            return response()->json($report);
        }

        // 2. Is the driver what we expect, without naming the host or database?
        $report['driver'] = config('database.connections.'.config('database.default').'.driver');

        // 3. Do the tables the bootstrap path needs exist? A fresh, unmigrated
        //    database is the most likely cause of "boot works, every session
        //    route fails", and the fix is a migration, not a config change.
        foreach (['migrations', 'users', 'settings', 'cache', 'cache_locks', 'sessions', 'jobs'] as $table) {
            try {
                $report['tables'][$table] = Schema::hasTable($table);
            } catch (Throwable) {
                $report['tables'][$table] = 'error';
            }
        }

        // 4. How many migrations are actually recorded?
        try {
            $report['migrations_recorded'] = DB::table('migrations')->count();
        } catch (Throwable) {
            $report['migrations_recorded'] = null;
        }

        // 5. Does the configured session driver work? This is the step the
        //    failing routes share.
        $report['session_driver'] = config('session.driver');
        $report['cache_store'] = config('cache.default');

        return response()->json($report);
    }
}
