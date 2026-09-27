<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;

/**
 * Default settings for a fresh installation.
 *
 * Idempotent: only fills keys that do not exist yet, so re-running never
 * overwrites an administrator's choices.
 */
class SettingsSeeder extends Seeder
{
    public function __construct(private readonly SettingsService $settings) {}

    public function run(): void
    {
        $this->settings->seedDefaults();

        $this->command?->info('  settings: '.$this->settings->all()->count().' keys.');
    }
}
