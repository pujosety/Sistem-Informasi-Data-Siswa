<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Populate the fictional LYFLA showcase after the schema is available.
 *
 * Wasmer's persisted dashboard job can override the repository's post-deploy
 * script. Keeping this idempotent data fill in the migration ledger means a
 * normal `migrate --force` still injects the requested demo dataset even when
 * that dashboard job is stale. All underlying commands are keyed/idempotent;
 * no reset flag is used, and real operator settings are preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        $commands = [
            ['db:seed', ['--class' => 'PermissionSeeder', '--force' => true, '--no-interaction' => true]],
            ['db:seed', ['--class' => 'LandingPageSeeder', '--force' => true, '--no-interaction' => true]],
            ['db:seed', ['--class' => 'LandingPhotosSeeder', '--force' => true, '--no-interaction' => true]],
            ['showcase:seed', ['--students-per-class' => 6]],
            ['showcase:extras', []],
            ['showcase:public-site', []],
            ['showcase:hris-content', []],
            ['lyfla:landing-demo', ['--posts' => 4, '--graduates' => 5, '--force' => true]],
        ];

        foreach ($commands as [$command, $parameters]) {
            $exitCode = Artisan::call($command, $parameters);

            if ($exitCode !== 0) {
                throw new RuntimeException(
                    sprintf('LYFLA showcase migration command failed: %s (exit %d)', $command, $exitCode)
                );
            }
        }
    }

    public function down(): void
    {
        // Deliberately no-op. This migration creates review/demo data alongside
        // an existing school database; rollback must never delete student data.
    }
};
