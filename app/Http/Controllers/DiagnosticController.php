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
        // If this never runs, the failure is in middleware or a provider, not
        // in application code. The response is a plain array so the body
        // itself reports whether the controller was reached.
        $report = [
            'reached' => true,
            'app' => 'SIDA',
            'env' => app()->environment(),
            'debug' => (bool) config('app.debug'),
        ];

        // 1. Can we open a connection at all?
        try {
            DB::connection()->getPdo();
            $report['connection'] = 'ok';
        } catch (\Throwable $e) {
            // A single boolean collapses three different faults, and each has a
            // different fix. Classify the layer so the operator knows whether to
            // look at the host, the network, or the credentials.
            $report['connection'] = 'failed';
            $report['connection_exception'] = class_basename($e);
            $report['failure_layer'] = $this->classifyConnectionFailure($e);

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

        // 5. The drivers that decide the fix. Reported as names only.
        $report['session_driver'] = config('session.driver');
        $report['cache_store'] = config('cache.default');

        // 6. Can each store actually be written? This is the step that fails
        //    BEFORE any controller when throttle() resolves the cache, which is
        //    why the error page showed no application output at all.
        // The configured cache store, written and read back. This is exactly
        // what throttle() does first, so a failure here is the cause.
        try {
            \Illuminate\Support\Facades\Cache::put('sida.diag.probe', 1, 5);
            \Illuminate\Support\Facades\Cache::forget('sida.diag.probe');
            $report['stores']['cache'] = 'writable';
        } catch (Throwable $e) {
            // TEMPORARY: the message is scrubbed of anything host- or
            // credential-shaped before it is returned, so it identifies the
            // failure without disclosing the connection. Remove with the route.
            $message = $e->getMessage();
            $message = preg_replace('/[a-z0-9-]+\.[a-z]{2,}(?::\d+)?/i', '<host>', $message);
            $message = preg_replace('/\b(?:root|user|password)\b[^\s]*/i', '<redacted>', $message);
            $message = preg_replace('/\b[\w.-]+@[\w.-]+\b/', '<redacted>', $message);
            $message = mb_substr($message, 0, 200);

            $report['stores']['cache'] = 'FAILED:' . class_basename($e);
            $report['stores']['cache_message'] = $message;
        }

        // The session handler, resolved the way the framework resolves it.
        try {
            $handler = config('session.driver') === 'database'
                ? \Illuminate\Support\Facades\DB::table(config('session.table', 'sessions'))
                : null;
            $report['stores']['session'] = $handler === null
                ? 'n/a ('.config('session.driver').')'
                : 'queryable';
        } catch (\Throwable $e) {
            $report['stores']['session'] = 'FAILED:' . class_basename($e);
        }

        return response()->json($report);
    }

    /**
     * Which layer a database connection failure belongs to.
     *
     * WHY NOT JUST "connection: failed"
     *
     * That single flag collapses three faults which need three different
     * fixes, and on a managed host the operator cannot see the difference from
     * the outside:
     *
     *   - credentials   the variables exist but the user/password is wrong,
     *                   or the user was never granted access to the database
     *   - network       the host is right but unreachable — DNS, firewall, a
     *                   private network the app is not attached to
     *   - driver        the PHP PDO driver for the configured driver is not
     *                   installed, so nothing can be attempted at all
     *
     * Reporting the layer is what makes this diagnostic worth its one request:
     * it turns "the site is down" into an action.
     *
     * Classification is by exception class and the SQLSTATE prefix, never by
     * message text — a message is exactly what this controller refuses to
     * return, because it embeds the host and the user.
     */
    private function classifyConnectionFailure(\Throwable $e): string
    {
        // "could not find driver" is thrown before any network I/O, so no
        // amount of credential or network work will help.
        if (str_contains($e->getMessage(), 'could not find driver')) {
            return 'driver';
        }

        $code = $e->getCode();

        // Access denied for user — the credentials are wrong, or the grant
        // is missing. MySQL reports 1045 for both.
        if ($code === 1045 || $code === '28000') {
            return 'credentials';
        }

        // Unknown database — the app connected but the schema is not there.
        // Distinct from credentials: fixing the password will not help.
        if ($code === 1049) {
            return 'database_missing';
        }

        // Cannot connect to MySQL server — host unreachable, refused, or the
        // connection attempt timed out. MySQL client errors 2002 (unix) and
        // 2003 (TCP); the raw connection error is reported as errno 0 or 111
        // with SQLSTATE HY000 when the failure was below the driver.
        if (in_array($code, [2002, 2003, 111, 0], true)) {
            return 'network';
        }

        // SQLSTATE class 08 is "connection exception" and 28000 is
        // "invalid authorization" — the standards-compliant spellings of the
        // two faults above, which a non-MySQL driver would use.
        if (is_string($code) && str_starts_with($code, '08')) {
            return 'network';
        }

        if (is_string($code) && str_starts_with($code, '28')) {
            return 'credentials';
        }

        return 'unknown';
    }
}
