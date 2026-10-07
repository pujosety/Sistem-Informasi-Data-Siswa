<?php

use App\Services\SettingsService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Adds only missing branding customizer rows. Existing production
        // values remain untouched because SettingsService uses firstOrCreate.
        app(SettingsService::class)->seedDefaults();
    }

    public function down(): void
    {
        // Customizer settings are intentionally retained on rollback so a
        // deployment rollback cannot erase administrator branding choices.
    }
};
