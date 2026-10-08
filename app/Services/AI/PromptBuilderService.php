<?php

namespace App\Services\AI;

use App\Models\AiKnowledge;
use App\Models\School;

class PromptBuilderService
{
    /** @param array<int, AiKnowledge> $knowledge */
    public function system(School $school, array $knowledge): string
    {
        return 'Balas dalam Bahasa Indonesia dengan singkat dan ramah. Gunakan hanya data yang diberikan.';
    }

    /** @param array<int, AiKnowledge> $knowledge */
    public function context(array $knowledge): string
    {
        return 'Reference data only.';
    }
}
