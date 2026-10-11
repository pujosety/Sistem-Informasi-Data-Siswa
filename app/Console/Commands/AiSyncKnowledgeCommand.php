<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\AI\SchoolKnowledgeSyncService;
use Illuminate\Console\Command;

class AiSyncKnowledgeCommand extends Command
{
    protected $signature = 'ai:sync-knowledge {school? : School slug; defaults to the primary school}';

    protected $description = 'Synchronize public school content into the LYFLA AI knowledge base';

    public function handle(SchoolKnowledgeSyncService $sync): int
    {
        $school = School::withoutGlobalScopes()
            ->when($this->argument('school'), fn ($query, $slug) => $query->where('slug', $slug))
            ->when(! $this->argument('school'), fn ($query) => $query->where('is_default', true))
            ->where('is_active', true)
            ->first();

        if (! $school) {
            $this->error('Active school was not found.');
            return self::FAILURE;
        }

        $count = $sync->sync($school);
        $this->info("Synchronized {$count} knowledge records for {$school->name}.");

        return self::SUCCESS;
    }
}
