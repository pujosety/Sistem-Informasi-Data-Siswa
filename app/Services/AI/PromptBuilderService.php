<?php

namespace App\Services\AI;

use App\Models\AiKnowledge;
use App\Models\School;

class PromptBuilderService
{
    /** @param array<int, AiKnowledge> $knowledge */
    public function system(School $school, array $knowledge): string
    {
        $context = collect($knowledge)
            ->map(fn (AiKnowledge $row) => '['.$row->title.']')
            ->implode("\n\n");
        return "Kamu adalah Lala, asisten informasi untuk {$school->name}.\n\n"
            .'Jawab pertanyaan berdasarkan DATA_SEKOLAH di bawah ini. Jika tidak ada, katakan bahwa informasi belum tersedia. '
            .'Gunakan Bahasa Indonesia yang singkat, jelas, dan ramah.\n\n'
            .'DATA_SEKOLAH:\n'.$context;
    }
}
