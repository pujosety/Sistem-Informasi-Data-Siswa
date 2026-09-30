<?php

namespace App\Console\Commands;

use App\Models\Module;
use Illuminate\Console\Command;

/**
 * Turns on modules whose implementation has landed.
 *
 * WHY THIS EXISTS RATHER THAN A MIGRATION
 *
 * The registry migration seeds with `insertOrIgnore`, deliberately, so that a
 * re-run or a second deploy cannot reset an operator's toggle. That same
 * guarantee means editing the seed list does nothing to a database that already
 * has the row — which is exactly the case that matters, because the modules
 * being enabled here are ones that were seeded *off* on purpose.
 *
 * So enabling them is a data change, and this command is the idempotent way to
 * make it. Running it twice changes nothing the second time, and it never
 * touches a module it was not asked about.
 */
class EnableBuiltModulesCommand extends Command
{
    protected $signature = 'sida:enable-modules {--dry-run : Show what would change}';

    protected $description = 'Enable registry modules whose implementation now exists';

    /**
     * Only modules that actually have a screen.
     *
     * The list is explicit rather than derived, because "has a route" is not
     * the test — a module can have routes and still be a stub. Each entry here
     * is a claim that someone can open it and use it.
     */
    private const BUILT = [
        'cms' => 'CMS: the editor, the publication gate and the revision history exist',
        'hris' => 'HRIS: employment records have a list, a form and a detail screen',
    ];

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        $changed = 0;

        foreach (self::BUILT as $key => $reason) {
            $module = Module::query()->where('key', $key)->first();

            if (! $module) {
                $this->warn("  [{$key}] not in the registry — was it renamed?");

                continue;
            }

            if ($module->is_enabled) {
                $this->line("  [{$key}] already enabled");

                continue;
            }

            if ($module->is_required) {
                // Cannot happen for these two, but the guard is what makes this
                // safe to extend: a required module is never switched off by
                // definition, so enabling it is a no-op worth refusing loudly.
                $this->error("  [{$key}] is required and cannot be toggled");

                return self::FAILURE;
            }

            $this->line("  [{$key}] enable — {$reason}");

            if (! $dry) {
                $module->update(['is_enabled' => true]);
            }

            $changed++;
        }

        if ($changed === 0) {
            $this->info('Nothing to change.');

            return self::SUCCESS;
        }

        $this->info($dry
            ? "Dry run: {$changed} module(s) would be enabled."
            : "Enabled {$changed} module(s).");

        return self::SUCCESS;
    }
}
