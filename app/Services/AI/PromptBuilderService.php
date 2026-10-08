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
        $context = collect($knowledge)
            ->map(fn (AiKnowledge $row) => "[{$row->title}]\n{$row->content}")
            ->implode("\n\n");

        return "Reference data for the user's question follows. Answer in the user's language and use only this data.\n\nDATA SEKOLAH:\n".$context;
    }
}
