<?php

use Database\Seeders\Smpn4MetroSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // The Wasmer persisted after-deploy hook currently runs migrations but
        // does not run the repository seeder. Keep the official-source demo
        // school idempotent and make its production data part of the migration
        // ledger, while keeping RefreshDatabase fixtures isolated.
        if (app()->environment('testing')) {
            return;
        }

        app(Smpn4MetroSeeder::class)->run();
    }

    public function down(): void
    {
        // Demo content is additive and must not be destructively removed.
    }
};
