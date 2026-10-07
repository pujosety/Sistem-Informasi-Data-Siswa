<?php

use App\Models\School;
use App\Services\AI\SchoolKnowledgeSyncService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $school = School::withoutGlobalScopes()
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        if ($school) {
            app(SchoolKnowledgeSyncService::class)->sync($school);
        }
    }

    public function down(): void
    {
        // Knowledge remains additive and is intentionally not destructively removed.
    }
};
