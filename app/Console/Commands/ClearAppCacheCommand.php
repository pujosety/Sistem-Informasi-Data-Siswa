<?php

namespace App\Console\Commands;

use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Clears the application cache that the web container actually reads.
 *
 * WHY THIS COMMAND EXISTS
 *
 * `SettingsService` memoises every setting row under one forever-cache key, and
 * `flush()` invalidates it with `Cache::forget()`. That works from the web
 * container. It does NOT work from a one-off job run with a different cache
 * store — and the deployment script deliberately runs artisan with
 * `CACHE_STORE=array` so a job can never write to the production cache.
 *
 * On an array store, `forget()` forgets nothing. So a seeder writes correct
 * rows, reports success, and the row is never invalidated; the next visitor
 * reads the stale entry and sees the school it was before.
 *
 * The symptom is genuinely confusing, because the defaults mask half of it:
 * `school.name` falls back to its DEFAULTS value and renders fine, while
 * `school.npsn`, `school.address` and `school.headmaster` have no default and
 * come back blank. So the page shows the school NAME and then says the profile
 * has not been filled in.
 *
 * This deletes the key directly in SQL rather than going through Cache, so it
 * works regardless of which store the calling process was configured with.
 */
class ClearAppCacheCommand extends Command
{
    protected $signature = 'sida:clear-cache {--dry-run : List what would be removed}';

    protected $description = 'Remove the application cache rows the web container reads';

    public function handle(SettingsService $settings): int
    {
        $dry = $this->option('dry-run');

        $this->line('cache store in this process: '.config('cache.default'));

        if (! $this->tableExists('cache')) {
            $this->error('the `cache` table does not exist — nothing to clear');

            return self::FAILURE;
        }

        // What is in there, named only. The values are serialised payloads and
        // are never printed.
        $rows = DB::table('cache')->pluck('key');

        $this->line('rows in `cache`: '.$rows->count());

        foreach ($rows as $key) {
            $this->line('  '.$key);
        }

        // Everything this application owns. A foreign key would be somebody
        // else's cache on a shared database, and this database is not shared,
        // but the prefix is checked anyway so the command cannot be pointed at
        // a store it does not understand.
        $owned = $rows->filter(fn ($k) => str_starts_with($k, 'laravel-cache-'));
        $other = $rows->reject(fn ($k) => str_starts_with($k, 'laravel-cache-'));

        if ($dry) {
            $this->newLine();
            $this->comment(sprintf(
                'Dry run: would delete %d row(s) and keep %d unrecognised.',
                $owned->count(),
                $other->count()
            ));

            return self::SUCCESS;
        }

        $deleted = DB::table('cache')->whereIn('key', $owned->all())->delete();

        $this->newLine();
        $this->info("deleted {$deleted} row(s)");

        if ($other->isNotEmpty()) {
            $this->warn('left untouched: '.implode(', ', $other->all()));
        }

        // Also forget it in this process, so anything after this command in
        // the same run reads fresh.
        $settings->flush();
        Cache::forget('sida.settings');

        return self::SUCCESS;
    }

    private function tableExists(string $table): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
}
