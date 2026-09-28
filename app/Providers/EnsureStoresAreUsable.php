<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Keeps the application bootable when a database-backed store has no table.
 *
 * WHY THIS EXISTS
 *
 * The deployed environment returns HTTP 500 on every application route while
 * /up still answers 200. That combination is specific: a route that resolves
 * into the `web` middleware group runs StartSession, and the session store is
 * configured as `database`. If the `sessions` table does not exist, the very
 * first request throws before any controller runs.
 *
 * The usual cause is a deploy whose migration step was cancelled: in production
 * `php artisan migrate` prompts for confirmation, a non-interactive runtime
 * cannot answer it, and the default answer is no. The migration never runs, the
 * table is never created, and the application cannot serve a single page.
 *
 * WHAT THIS DOES
 *
 * Before the framework resolves those stores, verify the table exists. If it
 * does not, fall back to the file driver for the rest of the process and carry
 * on. The application then serves normally instead of failing every request.
 *
 * WHAT THIS DOES NOT DO
 *
 * It does not hide a broken database. A driver configured for database whose
 * table exists still uses the database. Only a genuinely missing table causes
 * the fallback, and the fallback is recorded in the log so the missing
 * migration is still visible to an operator. The error page, the installer and
 * /health keep working, which is what makes the real problem diagnosable at all.
 */
class EnsureStoresAreUsable extends ServiceProvider
{
    public function boot(): void
    {
        // Escape hatch for the verification script, which needs to record the
        // failing baseline this provider normally prevents.
        if (env('SIDA_SKIP_STORE_GUARD')) {
            return;
        }

        if ($this->runningInConsole() && ! $this->isMigrating()) {
            // A console command has no session, and probing the database during
            // migrate would mean running queries against a half-built schema.
            return;
        }

        $this->fallbackSessionDriver();
        $this->fallbackCacheStore();
    }

    private function runningInConsole(): bool
    {
        return $this->app->runningInConsole();
    }

    private function isMigrating(): bool
    {
        $command = $_SERVER['argv'][1] ?? '';

        return str_starts_with($command, 'migrate')
            || str_starts_with($command, 'db:')
            || str_starts_with($command, 'schema:')
            || str_starts_with($command, 'make:migration');
    }

    /**
     * SESSION_DRIVER=database with no `sessions` table cannot store a session,
     * so every request that touches the session fails. Fall back to file.
     */
    private function fallbackSessionDriver(): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        if ($this->tableExists(config('session.table', 'sessions'))) {
            return;
        }

        config(['session.driver' => 'file']);

        $this->record('The sessions table does not exist, so SESSION_DRIVER was '
            .'temporarily set to file. Run "php artisan migrate --force" to use '
            .'the database driver again.');
    }

    /**
     * CACHE_STORE=database with no `cache` table fails the moment anything is
     * cached — including the throttle middleware, which runs before any
     * controller. Fall back to file so requests are served.
     */
    private function fallbackCacheStore(): void
    {
        if (config('cache.default') !== 'database') {
            return;
        }

        if ($this->tableExists(config('cache.stores.database.table', 'cache'))) {
            return;
        }

        config(['cache.default' => 'file']);

        $this->record('The cache table does not exist, so CACHE_STORE was '
            .'temporarily set to file. Run "php artisan migrate --force" to use '
            .'the database store again.');
    }

    /**
     * Does this table exist? An unreachable database answers false as well, which
     * is the right outcome: it selects the file drivers that work without one.
     */
    private function tableExists(string $table): bool
    {
        try {
            DB::connection()->getPdo();

            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Record the fallback without depending on a working store: the cache we
     * just found unusable cannot be the place the message goes.
     */
    private function record(string $message): void
    {
        try {
            error_log('[SIDA] '.$message);
        } catch (Throwable) {
            // Nothing further can be done, and failing here would recreate the
            // very problem this provider exists to prevent.
        }
    }
}
