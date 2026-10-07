<?php

use App\Services\SettingsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        // The identity migration updates rows directly, so it must invalidate
        // the forever-cached settings collection before the first new request.
        Cache::forget(SettingsService::CACHE_KEY);
    }

    public function down(): void
    {
        // Cache invalidation has no reversible schema state.
    }
};
